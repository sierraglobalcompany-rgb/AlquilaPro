<?php

declare(strict_types=1);

use AlquilaPro\Audit\AuditService;
use AlquilaPro\Auth\AuthService;
use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Auth\UserService;
use AlquilaPro\Candidates\CandidateDocumentService;
use AlquilaPro\Candidates\CandidateHouseholdService;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Candidates\ScreeningService;
use AlquilaPro\Community\CommunityService;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\Installer;
use AlquilaPro\Foundation\RuntimeRequirements;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\ObligationService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Insurance\InsuranceService;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\Leases\TerminationService;
use AlquilaPro\Maintenance\MaintenanceService;
use AlquilaPro\Maintenance\VisitService;
use AlquilaPro\Memberships\MembershipService;
use AlquilaPro\Notifications\NotificationService;
use AlquilaPro\Notifications\WhatsAppService;
use AlquilaPro\Properties\PropertyService;
use AlquilaPro\Properties\UnitMediaService;
use AlquilaPro\UI\Branding;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PHPMailer\PHPMailer\PHPMailer;
use Slim\App;
use Slim\Factory\AppFactory;

return static function (array $settings): App {
    $lockPath = (string) ($settings['lock_path'] ?? dirname(__DIR__) . '/storage/app/installed.lock');
    $databaseConfigPath = (string) ($settings['database_config_path'] ?? dirname(__DIR__) . '/config/database.php');
    $migrationDirectory = (string) ($settings['migration_directory'] ?? dirname(__DIR__) . '/database/migrations');
    $baseUrl = rtrim((string) ($settings['base_url'] ?? ''), '/');
    $basePath = '/' . trim((string) ($settings['base_path'] ?? ''), '/');
    $basePath = $basePath === '/' ? '' : $basePath;
    $viewsPath = dirname(__DIR__) . '/resources/views';
    $logoDirectory = (string) ($settings['logo_directory'] ?? dirname(__DIR__) . '/storage/branding');
    $unitMediaDirectory = (string) ($settings['unit_media_directory'] ?? dirname(__DIR__) . '/storage/unit-media');
    $candidateDocumentDirectory = (string) (
        $settings['candidate_document_directory'] ?? dirname(__DIR__) . '/storage/candidate-documents'
    );
    $contractDirectory = (string) ($settings['contract_directory'] ?? dirname(__DIR__) . '/storage/contracts');
    $paymentMethodQrDirectory = (string) (
        $settings['payment_method_qr_directory'] ?? dirname(__DIR__) . '/storage/payment-method-qrs'
    );
    $paymentReceiptDirectory = (string) (
        $settings['payment_receipt_directory'] ?? dirname(__DIR__) . '/storage/payment-receipts'
    );
    $obligationSupportDirectory = (string) (
        $settings['obligation_support_directory'] ?? dirname(__DIR__) . '/storage/obligation-supports'
    );
    $insuranceDocumentDirectory = (string) (
        $settings['insurance_document_directory'] ?? dirname(__DIR__) . '/storage/insurance-documents'
    );
    $maintenancePhotoDirectory = (string) (
        $settings['maintenance_photo_directory'] ?? dirname(__DIR__) . '/storage/maintenance-photos'
    );
    $documentDirectory = trim((string) ($settings['document_directory'] ?? ''));
    if ($documentDirectory === '') {
        $documentDirectory = dirname(__DIR__, 2) . '/alquilapro-private/' . basename(dirname(__DIR__)) . '/documents';
    }
    $candidateHouseholdMediaDirectory = trim((string) ($settings['candidate_household_media_directory'] ?? ''));
    if ($candidateHouseholdMediaDirectory === '') {
        $candidateHouseholdMediaDirectory = dirname(__DIR__, 2) . '/alquilapro-private/'
            . basename(dirname(__DIR__)) . '/candidate-household';
    }

    $lock = new InstallationLock($lockPath);
    $installer = new Installer($lock, $migrationDirectory, $databaseConfigPath);
    $csrf = new Csrf();
    $app = AppFactory::create();

    if ($basePath !== '') {
        $app->setBasePath($basePath);
    }

    $storageProbe = dirname($lockPath);
    while (!is_dir($storageProbe)) {
        $parent = dirname($storageProbe);
        if ($parent === $storageProbe) {
            break;
        }
        $storageProbe = $parent;
    }
    $storageWritable = is_writable($storageProbe);

    $runtime = RuntimeRequirements::evaluate(PHP_VERSION, get_loaded_extensions(), $storageWritable);
    $installPath = $basePath . '/install';
    $homePath = $basePath . '/';
    $installerView = $viewsPath . '/install.php';

    $wizardSessionKey = 'alquilapro_installer_wizard';
    $readWizardState = static function () use ($wizardSessionKey): array {
        $state = $_SESSION[$wizardSessionKey] ?? null;
        if (!is_array($state)) {
            $state = ['step' => 0];
            $_SESSION[$wizardSessionKey] = $state;
        }

        return $state;
    };
    $saveWizardState = static function (array $state) use ($wizardSessionKey): void {
        $_SESSION[$wizardSessionKey] = $state;
    };
    $databaseFingerprint = static function (array $database) use ($csrf): string {
        $canonical = [
            (string) ($database['host'] ?? ''),
            (string) ($database['port'] ?? ''),
            (string) ($database['database'] ?? ''),
            (string) ($database['username'] ?? ''),
            (string) ($database['password'] ?? ''),
        ];

        return hash_hmac('sha256', serialize($canonical), $csrf->token());
    };
    $renderInstaller = static function (array $state, ?string $error = null) use (
        $runtime,
        $installPath,
        $installerView,
        $csrf,
        $basePath,
    ): string {
        $step = (int) ($state['step'] ?? 0);
        $csrfToken = $csrf->token();
        $assetHref = $basePath . '/assets/app.css';
        $continuePath = $basePath . '/install/continue';
        $testDatabasePath = $basePath . '/install/test-database';
        $backDatabasePath = $basePath . '/install/back-database';
        $loginPath = $basePath . '/login';
        $viewState = $state;
        if (isset($viewState['database']) && is_array($viewState['database'])) {
            unset($viewState['database']['password']);
        }
        unset($viewState['owner_password'], $viewState['tested_fingerprint']);
        ob_start();
        require $installerView;
        return (string) ob_get_clean();
    };

    if (!$lock->isInstalled()) {
        $app->get('/', static function (ServerRequestInterface $request, ResponseInterface $response) use ($installPath): ResponseInterface {
            return $response->withHeader('Location', $installPath)->withStatus(302);
        });
    }

    $app->get('/install', static function (ServerRequestInterface $request, ResponseInterface $response) use ($lock, $renderInstaller, $readWizardState): ResponseInterface {
        if ($lock->isInstalled()) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($renderInstaller($readWizardState()));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/install/continue', static function (ServerRequestInterface $request, ResponseInterface $response) use (
        $lock,
        $runtime,
        $readWizardState,
        $saveWizardState,
        $renderInstaller,
    ): ResponseInterface {
        if ($lock->isInstalled()) {
            return $response->withStatus(404);
        }

        $state = $readWizardState();
        $form = (array) ($request->getParsedBody() ?? []);
        $step = (int) ($state['step'] ?? 0);

        if (!$runtime['ok']) {
            $state['step'] = 0;
            $saveWizardState($state);
            $response->getBody()->write($renderInstaller($state, 'El servidor no cumple los requisitos de Alquila Pro.'));
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
        }

        if ($step === 0) {
            $state['step'] = 1;
        } elseif ($step === 2) {
            $appName = trim((string) ($form['app_name'] ?? ''));
            if ($appName === '') {
                $response->getBody()->write($renderInstaller($state, 'El nombre de la empresa es obligatorio.'));
                return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
            }
            $state['app_name'] = $appName;
            $state['step'] = 3;
        } elseif ($step === 3) {
            $ownerName = trim((string) ($form['owner_name'] ?? ''));
            $ownerEmail = strtolower(trim((string) ($form['owner_email'] ?? '')));
            $ownerPassword = (string) ($form['owner_password'] ?? '');
            if ($ownerName === '' || filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) === false || strlen($ownerPassword) < 12) {
                $response->getBody()->write($renderInstaller($state, 'Los datos del propietario no son válidos.'));
                return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
            }
            $state['owner_name'] = $ownerName;
            $state['owner_email'] = $ownerEmail;
            $state['owner_password'] = $ownerPassword;
            $state['step'] = 4;
        } elseif ($step === 4) {
            $state['step'] = 5;
        } else {
            $response->getBody()->write($renderInstaller($state, 'Completa el paso actual antes de continuar.'));
            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withStatus(422);
        }

        $saveWizardState($state);
        $response->getBody()->write($renderInstaller($state));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/install/test-database', static function (ServerRequestInterface $request, ResponseInterface $response) use (
        $lock,
        $runtime,
        $installer,
        $databaseFingerprint,
        $readWizardState,
        $saveWizardState,
        $renderInstaller,
    ): ResponseInterface {
        if ($lock->isInstalled()) {
            return $response->withStatus(404);
        }

        $state = $readWizardState();
        if ((int) ($state['step'] ?? 0) !== 1) {
            $response->getBody()->write($renderInstaller($state, 'Completa los requisitos antes de probar la base de datos.'));
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
        }
        if (!$runtime['ok']) {
            $state['step'] = 0;
            $saveWizardState($state);
            $response->getBody()->write($renderInstaller($state, 'El servidor no cumple los requisitos de Alquila Pro.'));
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
        }

        $form = (array) ($request->getParsedBody() ?? []);
        $database = [
            'host' => $form['db_host'] ?? '',
            'port' => $form['db_port'] ?? '3306',
            'database' => $form['db_name'] ?? '',
            'username' => $form['db_user'] ?? '',
            'password' => $form['db_password'] ?? '',
        ];
        $state['database'] = $database;
        $state['database_tested'] = false;
        unset($state['tested_fingerprint']);
        $state['step'] = 1;
        $saveWizardState($state);

        try {
            $installer->testDatabase($database);
        } catch (Throwable $exception) {
            $error = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'No fue posible validar la configuración de la base de datos.';
            unset($state['database']['password']);
            $saveWizardState($state);
            $response->getBody()->write($renderInstaller($state, $error));
            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withStatus(422);
        }

        $state['database_tested'] = true;
        $state['tested_fingerprint'] = $databaseFingerprint($database);
        $state['step'] = 2;
        $saveWizardState($state);
        $response->getBody()->write($renderInstaller($state));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/install/back-database', static function (ServerRequestInterface $request, ResponseInterface $response) use (
        $lock,
        $readWizardState,
        $saveWizardState,
        $renderInstaller,
    ): ResponseInterface {
        if ($lock->isInstalled()) {
            return $response->withStatus(404);
        }

        $state = $readWizardState();
        $step = (int) ($state['step'] ?? 0);
        if ($step < 2 || $step > 5) {
            $response->getBody()->write($renderInstaller($state, 'No hay una prueba de base de datos que modificar.'));
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
        }

        $state['step'] = 1;
        $state['database_tested'] = false;
        unset($state['tested_fingerprint']);
        if (isset($state['database']) && is_array($state['database'])) {
            unset($state['database']['password']);
        }
        $saveWizardState($state);
        $response->getBody()->write($renderInstaller($state));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/install', static function (ServerRequestInterface $request, ResponseInterface $response) use (
        $lock,
        $installer,
        $runtime,
        $baseUrl,
        $wizardSessionKey,
        $databaseFingerprint,
        $readWizardState,
        $renderInstaller,
    ): ResponseInterface {
        if ($lock->isInstalled()) {
            return $response->withStatus(404);
        }

        $state = $readWizardState();
        $database = $state['database'] ?? null;
        $testedFingerprint = $state['tested_fingerprint'] ?? null;
        if ((int) ($state['step'] ?? 0) !== 5
            || !$runtime['ok']
            || !is_array($database)
            || ($state['database_tested'] ?? false) !== true
            || !is_string($testedFingerprint)
            || !hash_equals($testedFingerprint, $databaseFingerprint($database))
        ) {
            if (!$runtime['ok']) {
                $state['step'] = 0;
            } else {
                $state['step'] = 1;
            }
            $state['database_tested'] = false;
            unset($state['tested_fingerprint']);
            if (isset($state['database']) && is_array($state['database'])) {
                unset($state['database']['password']);
            }
            $_SESSION[$wizardSessionKey] = $state;
            $response->getBody()->write($renderInstaller($state, 'Vuelve a probar la conexión antes de instalar.'));
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
        }

        try {
            $installer->install([
                'database' => $database,
                'app_name' => (string) ($state['app_name'] ?? ''),
                'owner_name' => (string) ($state['owner_name'] ?? ''),
                'owner_email' => (string) ($state['owner_email'] ?? ''),
                'owner_password' => (string) ($state['owner_password'] ?? ''),
                'base_url' => $baseUrl,
            ]);
        } catch (Throwable) {
            $response->getBody()->write($renderInstaller($state, 'No fue posible completar la instalación.'));
            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withStatus(422);
        }

        unset($_SESSION[$wizardSessionKey]);
        $response->getBody()->write($renderInstaller(['step' => 6]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/health', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $payload = json_encode(['status' => 'ok', 'version' => '0.1.0-f1'], JSON_THROW_ON_ERROR);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    });

    if ($lock->isInstalled()) {
        $pdo = $settings['pdo'] ?? null;
        if (!$pdo instanceof \PDO && is_file($databaseConfigPath)) {
            $database = require $databaseConfigPath;
            if (is_array($database)) {
                $pdo = DatabaseConnector::connect(DatabaseConfig::fromArray($database));
            }
        }

        if ($pdo instanceof \PDO) {
            $notificationService = $settings['notifications'] ?? null;
            $notificationCronSecret = (string) ($settings['notification_cron_secret'] ?? '');
            if (array_key_exists('notification_settings', $settings)) {
                $mailSettings = $settings['notification_settings'];
                if (!is_array($mailSettings)) {
                    throw new RuntimeException('La configuración SMTP de notificaciones no es válida.');
                }
                $smtpHost = trim((string) ($mailSettings['host'] ?? ''));
                $smtpPort = filter_var($mailSettings['port'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 65535],
                ]);
                $smtpUsername = (string) ($mailSettings['username'] ?? '');
                $smtpPassword = (string) ($mailSettings['password'] ?? '');
                $smtpEncryption = strtolower(trim((string) ($mailSettings['encryption'] ?? '')));
                $fromEmail = trim((string) ($mailSettings['from_email'] ?? ''));
                $fromName = trim((string) ($mailSettings['from_name'] ?? ''));
                $notificationCronSecret = (string) ($mailSettings['cron_secret'] ?? '');
                if ($smtpHost === '' || preg_match('/[\r\n\x00]/', $smtpHost) === 1
                    || $smtpPort === false || $smtpUsername === '' || $smtpPassword === ''
                    || !in_array($smtpEncryption, ['tls', 'ssl'], true)
                    || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false
                    || $fromName === '' || preg_match('/[\r\n\x00]/', $fromName) === 1
                    || strlen($notificationCronSecret) < 32
                ) {
                    throw new RuntimeException('La configuración SMTP de notificaciones no es válida.');
                }

                $transport = static function (
                    string $email,
                    string $name,
                    string $subject,
                    string $htmlBody,
                    string $textBody,
                ) use ($smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpEncryption, $fromEmail, $fromName): void {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = $smtpHost;
                    $mail->Port = $smtpPort;
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtpUsername;
                    $mail->Password = $smtpPassword;
                    $mail->SMTPSecure = $smtpEncryption === 'tls'
                        ? PHPMailer::ENCRYPTION_STARTTLS
                        : PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Timeout = 15;
                    $mail->CharSet = PHPMailer::CHARSET_UTF8;
                    $mail->setFrom($fromEmail, $fromName);
                    $mail->addAddress($email, $name);
                    $mail->Subject = $subject;
                    $mail->isHTML(true);
                    $mail->Body = $htmlBody;
                    $mail->AltBody = $textBody;
                    $mail->send();
                };
                $notificationService = new NotificationService($pdo, $transport);
            }
            if (!$notificationService instanceof NotificationService) {
                $notificationService = null;
            }
            $whatsApp = new WhatsAppService($pdo);
            $audit = new AuditService($pdo);

            $auth = new AuthService($pdo);
            $session = new SessionGuard();
            $branding = new Branding($pdo, $logoDirectory);
            $view = new View($viewsPath, $branding, $basePath);
            $properties = new PropertyService($pdo);
            $unitMedia = new UnitMediaService($pdo, $unitMediaDirectory);
            $candidateDocuments = new CandidateDocumentService($pdo, $candidateDocumentDirectory);
            $candidateHousehold = new CandidateHouseholdService($pdo, $candidateHouseholdMediaDirectory);
            $candidates = new CandidateService(
                $pdo,
                candidateDocumentService: $candidateDocuments,
                notificationService: $notificationService,
            );
            $screening = new ScreeningService($pdo, $candidates);
            $contracts = new ContractService($pdo, $candidates, $screening, $properties, $contractDirectory);
            $users = new UserService($pdo);
            $leases = new LeaseService($pdo, $users);
            $documents = new DocumentService($pdo, $documentDirectory);
            $community = new CommunityService($pdo);
            $paymentMethods = new PaymentMethodService($pdo, $paymentMethodQrDirectory);
            $finance = new FinanceService($pdo, $paymentMethods, $paymentReceiptDirectory);
            $memberships = new MembershipService($pdo, $finance);
            $obligations = new ObligationService($pdo, $finance, $obligationSupportDirectory);
            $insurance = new InsuranceService($pdo, $insuranceDocumentDirectory);
            $maintenance = new MaintenanceService($pdo, $finance, $maintenancePhotoDirectory);
            $visits = new VisitService($pdo);
            $bonusEnabled = (bool) ($settings['good_conduct_bonus_enabled'] ?? false);
            $termination = new TerminationService(
                $pdo,
                $leases,
                $finance,
                $obligations,
                $insurance,
                $documents,
                $community,
                $memberships,
                $bonusEnabled,
            );

            $currentUser = static function () use ($session, $auth): ?array {
                $userId = $session->userId(time());
                if ($userId === null) {
                    return null;
                }

                $user = $auth->findUser($userId);
                if ($user === null || !$user['is_active']) {
                    $session->logout();
                    return null;
                }

                return $user;
            };

            $shared = [
                'auth' => $auth,
                'session' => $session,
                'csrf' => $csrf,
                'base_path' => $basePath,
                'base_url' => $baseUrl,
                'branding' => $branding,
                'view' => $view,
                'current_user' => $currentUser,
                'audit' => $audit,
                'finance' => $finance,
                'obligations' => $obligations,
                'insurance' => $insurance,
                'maintenance' => $maintenance,
                'visits' => $visits,
                'payment_methods' => $paymentMethods,
                'properties' => $properties,
                'today' => $settings['today'] ?? null,
                'memberships' => $memberships,
            ];

            $authRoutes = require dirname(__DIR__) . '/app/Auth/routes.php';
            $authRoutes($app, $shared + [
                'users' => $users,
                'pdo' => $pdo,
            ]);

            $uiRoutes = require dirname(__DIR__) . '/app/UI/routes.php';
            $uiRoutes($app, $shared);

            $auditRoutes = require dirname(__DIR__) . '/app/Audit/routes.php';
            $auditRoutes($app, $shared);

            $propertyRoutes = require dirname(__DIR__) . '/app/Properties/routes.php';
            $propertyRoutes($app, $shared + [
                'properties' => $properties,
                'unit_media' => $unitMedia,
                'candidate_documents' => $candidateDocuments,
            ]);

            $candidateRoutes = require dirname(__DIR__) . '/app/Candidates/routes.php';
            $candidateRoutes($app, $shared + [
                'candidates' => $candidates,
                'candidate_documents' => $candidateDocuments,
                'candidate_household' => $candidateHousehold,
                'documents' => $documents,
                'properties' => $properties,
                'unit_media' => $unitMedia,
                'screening' => $screening,
                'contracts' => $contracts,
            ]);

            $contractRoutes = require dirname(__DIR__) . '/app/Contracts/routes.php';
            $contractRoutes($app, $shared + [
                'contracts' => $contracts,
                'leases' => $leases,
                'candidates' => $candidates,
            ]);

            $leaseRoutes = require dirname(__DIR__) . '/app/Leases/routes.php';
            $leaseRoutes($app, $shared + [
                'leases' => $leases,
                'contracts' => $contracts,
                'documents' => $documents,
                'community' => $community,
                'memberships' => $memberships,
                'termination' => $termination,
                'good_conduct_bonus_enabled' => $bonusEnabled,
            ]);

            $membershipRoutes = require dirname(__DIR__) . '/app/Memberships/routes.php';
            $membershipRoutes($app, $shared + [
                'memberships' => $memberships,
                'leases' => $leases,
            ]);

            $documentRoutes = require dirname(__DIR__) . '/app/Documents/routes.php';
            $documentRoutes($app, $shared + [
                'documents' => $documents,
                'leases' => $leases,
            ]);

            $communityRoutes = require dirname(__DIR__) . '/app/Community/routes.php';
            $communityRoutes($app, $shared + [
                'community' => $community,
                'documents' => $documents,
            ]);

            $financeRoutes = require dirname(__DIR__) . '/app/Finance/routes.php';
            $financeRoutes($app, $shared);

            $insuranceRoutes = require dirname(__DIR__) . '/app/Insurance/routes.php';
            $insuranceRoutes($app, $shared);

            $maintenanceRoutes = require dirname(__DIR__) . '/app/Maintenance/routes.php';
            $maintenanceRoutes($app, $shared + ['leases' => $leases]);

            $notificationRoutes = require dirname(__DIR__) . '/app/Notifications/routes.php';
            $notificationRoutes($app, $shared + [
                'notifications' => $notificationService,
                'whatsapp' => $whatsApp,
                'pdo' => $pdo,
                'cron_secret' => $notificationCronSecret,
            ]);
        }
    }

    $responseFactory = $app->getResponseFactory();
    $cronPath = $basePath . '/cron/notifications';
    $app->add(static function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($csrf, $responseFactory, $cronPath): ResponseInterface {
        $isCronRequest = strtoupper($request->getMethod()) === 'POST'
            && $request->getUri()->getPath() === $cronPath;
        if (strtoupper($request->getMethod()) === 'POST' && !$isCronRequest) {
            $body = $request->getParsedBody();
            $token = is_array($body) ? (string) ($body['csrf_token'] ?? '') : '';

            if (!$csrf->validate($token)) {
                $response = $responseFactory->createResponse(403);
                $response->getBody()->write('Solicitud inválida.');
                return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }
        }

        return $handler->handle($request);
    });

    $errorMiddleware = $app->addErrorMiddleware(false, false, false);
    $errorMiddleware->setDefaultErrorHandler(
        static function (
            ServerRequestInterface $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails,
        ) use ($responseFactory): ResponseInterface {
            if ($exception instanceof \Slim\Exception\HttpNotFoundException) {
                return $responseFactory->createResponse(404);
            }

            $requestPath = $request->getUri()->getPath();
            $safePath = preg_replace(
                '#(/apply/continue/)[a-f0-9]{64}(?=/|$)#i',
                '$1[redacted]',
                $requestPath,
            ) ?? $requestPath;

            error_log(sprintf(
                'AlquilaPro unhandled %s on %s %s',
                $exception::class,
                $request->getMethod(),
                $safePath,
            ));

            $response = $responseFactory->createResponse(500);
            $response->getBody()->write('Ocurrió un error interno.');

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        },
    );

    $app->add(static function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        return $handler->handle($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN');
    });

    return $app;
};
