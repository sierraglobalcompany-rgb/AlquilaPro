<?php

declare(strict_types=1);

use AlquilaPro\Auth\AuthService;
use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Auth\UserService;
use AlquilaPro\Candidates\CandidateDocumentService;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Candidates\ScreeningService;
use AlquilaPro\Contracts\ContractService;
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
use AlquilaPro\Maintenance\MaintenanceService;
use AlquilaPro\Maintenance\VisitService;
use AlquilaPro\Notifications\NotificationService;
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

    $renderInstaller = static function (?string $error = null) use ($runtime, $installPath, $installerView, $csrf, $basePath): string {
        $action = $installPath;
        $csrfToken = $csrf->token();
        $assetHref = $basePath . '/assets/app.css';
        ob_start();
        require $installerView;
        return (string) ob_get_clean();
    };

    if (!$lock->isInstalled()) {
        $app->get('/', static function (ServerRequestInterface $request, ResponseInterface $response) use ($installPath): ResponseInterface {
            return $response->withHeader('Location', $installPath)->withStatus(302);
        });
    }

    $app->get('/install', static function (ServerRequestInterface $request, ResponseInterface $response) use ($lock, $renderInstaller): ResponseInterface {
        if ($lock->isInstalled()) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($renderInstaller());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/install', static function (ServerRequestInterface $request, ResponseInterface $response) use ($lock, $installer, $baseUrl, $homePath, $renderInstaller): ResponseInterface {
        if ($lock->isInstalled()) {
            return $response->withStatus(404);
        }

        $form = (array) ($request->getParsedBody() ?? []);

        try {
            $installer->install([
                'database' => [
                    'host' => $form['db_host'] ?? '',
                    'port' => $form['db_port'] ?? '3306',
                    'database' => $form['db_name'] ?? '',
                    'username' => $form['db_user'] ?? '',
                    'password' => $form['db_password'] ?? '',
                ],
                'app_name' => $form['app_name'] ?? 'Alquila Pro',
                'owner_name' => $form['owner_name'] ?? '',
                'owner_email' => $form['owner_email'] ?? '',
                'owner_password' => $form['owner_password'] ?? '',
                'base_url' => $baseUrl,
            ]);
        } catch (RuntimeException $exception) {
            $response->getBody()->write($renderInstaller($exception->getMessage()));
            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withStatus(422);
        } catch (Throwable) {
            $response->getBody()->write($renderInstaller('No fue posible completar la instalación.'));
            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withStatus(422);
        }

        return $response->withHeader('Location', $homePath)->withStatus(303);
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

            $auth = new AuthService($pdo);
            $session = new SessionGuard();
            $branding = new Branding($pdo, $logoDirectory);
            $view = new View($viewsPath, $branding, $basePath);
            $properties = new PropertyService($pdo);
            $unitMedia = new UnitMediaService($pdo, $unitMediaDirectory);
            $candidateDocuments = new CandidateDocumentService($pdo, $candidateDocumentDirectory);
            $candidates = new CandidateService(
                $pdo,
                candidateDocumentService: $candidateDocuments,
                notificationService: $notificationService,
            );
            $screening = new ScreeningService($pdo, $candidates);
            $contracts = new ContractService($pdo, $candidates, $screening, $properties, $contractDirectory);
            $users = new UserService($pdo);
            $leases = new LeaseService($pdo, $users);
            $paymentMethods = new PaymentMethodService($pdo, $paymentMethodQrDirectory);
            $finance = new FinanceService($pdo, $paymentMethods, $paymentReceiptDirectory);
            $obligations = new ObligationService($pdo, $finance, $obligationSupportDirectory);
            $insurance = new InsuranceService($pdo, $insuranceDocumentDirectory);
            $maintenance = new MaintenanceService($pdo, $finance, $maintenancePhotoDirectory);
            $visits = new VisitService($pdo);

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
                'finance' => $finance,
                'obligations' => $obligations,
                'insurance' => $insurance,
                'maintenance' => $maintenance,
                'visits' => $visits,
                'payment_methods' => $paymentMethods,
                'properties' => $properties,
                'today' => $settings['today'] ?? null,
            ];

            $authRoutes = require dirname(__DIR__) . '/app/Auth/routes.php';
            $authRoutes($app, $shared + ['users' => $users]);

            $uiRoutes = require dirname(__DIR__) . '/app/UI/routes.php';
            $uiRoutes($app, $shared);

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
                'properties' => $properties,
                'screening' => $screening,
                'contracts' => $contracts,
            ]);

            $contractRoutes = require dirname(__DIR__) . '/app/Contracts/routes.php';
            $contractRoutes($app, $shared + [
                'contracts' => $contracts,
                'leases' => $leases,
            ]);

            $leaseRoutes = require dirname(__DIR__) . '/app/Leases/routes.php';
            $leaseRoutes($app, $shared + [
                'leases' => $leases,
                'contracts' => $contracts,
            ]);

            $financeRoutes = require dirname(__DIR__) . '/app/Finance/routes.php';
            $financeRoutes($app, $shared);

            $insuranceRoutes = require dirname(__DIR__) . '/app/Insurance/routes.php';
            $insuranceRoutes($app, $shared);

            $maintenanceRoutes = require dirname(__DIR__) . '/app/Maintenance/routes.php';
            $maintenanceRoutes($app, $shared + ['leases' => $leases]);

            if ($notificationService instanceof NotificationService) {
                $notificationRoutes = require dirname(__DIR__) . '/app/Notifications/routes.php';
                $notificationRoutes($app, [
                    'notifications' => $notificationService,
                    'cron_secret' => $notificationCronSecret,
                ]);
            }
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

    return $app;
};
