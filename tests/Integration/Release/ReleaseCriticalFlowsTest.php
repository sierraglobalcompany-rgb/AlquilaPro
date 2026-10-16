<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Release;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Candidates\ScreeningService;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\Installer;
use AlquilaPro\Notifications\NotificationService;
use AlquilaPro\Properties\PropertyService;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;

final class ReleaseCriticalFlowsTest extends TestCase
{
    private const TABLES = [
        'users', 'settings', 'properties', 'units', 'unit_media', 'candidates', 'candidate_references',
        'unit_application_requirements', 'candidate_documents', 'screening_processes', 'contract_templates',
        'contract_template_versions', 'contracts', 'leases', 'charges', 'payment_methods', 'payments',
        'payment_allocations', 'lease_obligations', 'insurance_policies', 'insurance_claims', 'maintenance_requests',
        'maintenance_visits', 'notification_jobs', 'whatsapp_actions', 'documents', 'community_incidents',
        'digital_services', 'service_memberships', 'good_conduct_bonuses', 'lease_liquidations', 'audit_log',
        'schema_migrations', 'migration_probe',
    ];

    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $configPath;
    private string $migrationDirectory;
    private array $temporaryFiles = [];
    private array $sentEmails = [];
    private string $cronSecret;
    private ?NotificationService $notifications = null;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]));
        $this->migrationDirectory = dirname(__DIR__, 3) . '/database/migrations';
        $this->directory = sys_get_temp_dir() . '/alquila_release_flow_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        $this->configPath = $this->directory . '/config/database.php';
        $this->cronSecret = bin2hex(random_bytes(32));
        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        $this->removeDirectory($this->directory);
        $_SESSION = [];
    }

    public function testInstallerToTenantPortalJourneyWithReleaseBoundaries(): void
    {
        $ownerEmail = 'owner-' . bin2hex(random_bytes(4)) . '@example.test';
        $ownerPassword = bin2hex(random_bytes(16));
        $ownerName = 'Ángela Muñoz';
        $installer = new Installer(
            new InstallationLock($this->lockPath),
            $this->migrationDirectory,
            $this->configPath,
        );
        $installer->install([
            'database' => $this->databaseInput(),
            'app_name' => 'Alquila Pro Bogotá',
            'base_url' => 'https://alquilapro.example',
            'owner_name' => $ownerName,
            'owner_email' => $ownerEmail,
            'owner_password' => $ownerPassword,
        ]);
        self::assertTrue((new InstallationLock($this->lockPath))->isInstalled());
        self::assertFileExists($this->configPath);

        $app = $this->app();
        $login = $this->request($app, 'POST', '/login', [
            'email' => $ownerEmail,
            'password' => $ownerPassword,
        ]);
        self::assertSame(303, $login->getStatusCode());
        self::assertSame('/', $login->getHeaderLine('Location'));
        self::assertSame(200, $this->request($app, 'GET', '/')->getStatusCode());

        $propertyName = 'Edificio Ñandú';
        $propertyResponse = $this->request($app, 'POST', '/admin/properties', [
            'name' => $propertyName,
            'address' => 'Carrera 11 # 82-20',
            'city' => 'Bogotá',
            'sector' => 'Chapinero',
        ]);
        self::assertSame(303, $propertyResponse->getStatusCode(), 'Installed owner must bypass scoped property permissions.');
        $propertyId = (int) $this->pdo->query('SELECT id FROM properties WHERE name = ' . $this->pdo->quote($propertyName))->fetchColumn();
        self::assertGreaterThan(0, $propertyId);

        $unitResponse = $this->request($app, 'POST', '/admin/properties/' . $propertyId . '/units', [
            'type' => 'apartment',
            'code' => 'A-204',
            'status' => 'available',
            'monthly_price' => '1850000',
            'description' => 'Apartamento luminoso cerca del parque.',
            'requirements' => 'Certificación de ingresos',
            'bedrooms' => '2',
            'bathrooms' => '2',
        ]);
        self::assertSame(303, $unitResponse->getStatusCode());
        $unitId = (int) $this->pdo->query("SELECT id FROM units WHERE code = 'A-204'")->fetchColumn();
        self::assertGreaterThan(0, $unitId);

        $photo = $this->request(
            $app,
            'POST',
            '/admin/units/' . $unitId . '/media/photo',
            [],
            ['photo' => $this->upload($this->pngBytes(), 'apartamento.png', 'image/png')],
        );
        self::assertSame(303, $photo->getStatusCode());
        $published = $this->request($app, 'POST', '/admin/units/' . $unitId, [
            'type' => 'apartment',
            'code' => 'A-204',
            'status' => 'available',
            'monthly_price' => '1850000',
            'description' => 'Apartamento luminoso cerca del parque.',
            'requirements' => 'Certificación de ingresos',
            'bedrooms' => '2',
            'bathrooms' => '2',
            'is_public' => '1',
        ]);
        self::assertSame(303, $published->getStatusCode());
        $unit = (new PropertyService($this->pdo))->findUnit($unitId);
        self::assertNotNull($unit);
        $publicKey = (string) $unit['public_key'];
        self::assertSame(200, $this->request($app, 'GET', '/catalog')->getStatusCode());
        $catalogDetail = $this->request($app, 'GET', '/catalog/' . $publicKey);
        self::assertSame(200, $catalogDetail->getStatusCode());
        self::assertStringContainsString('Edificio Ñandú', (string) $catalogDetail->getBody());

        $candidateName = 'María Gómez Ñ';
        $candidateEmail = 'maria-' . bin2hex(random_bytes(4)) . '@example.test';
        $candidateDocument = 'REL-' . bin2hex(random_bytes(4));
        $started = $this->request($app, 'POST', '/apply/' . $publicKey, [
            'document_type' => 'cc',
            'document_number' => $candidateDocument,
            'full_name' => $candidateName,
            'phone' => '3001234567',
            'email' => $candidateEmail,
            'current_address' => 'Calle 80 # 10-20',
            'consent' => '1',
        ]);
        self::assertSame(303, $started->getStatusCode());
        $token = basename($started->getHeaderLine('Location'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $token);
        $candidateLookup = $this->pdo->prepare('SELECT id FROM candidates WHERE continuation_token_hash = ?');
        $candidateLookup->execute([hash('sha256', $token)]);
        $candidateId = (int) $candidateLookup->fetchColumn();
        self::assertGreaterThan(0, $candidateId);

        $personal = $this->request($app, 'POST', '/apply/continue/' . $token, [
            'wizard_step' => 'personal',
            'full_name' => $candidateName,
            'current_address' => 'Calle 80 # 10-20',
        ]);
        self::assertSame('/apply/continue/' . $token . '/work', $personal->getHeaderLine('Location'));
        $work = $this->request($app, 'POST', '/apply/continue/' . $token, [
            'wizard_step' => 'work',
            'employment_status' => 'Empleado',
            'employer_name' => 'Empresa Bogotá',
        ]);
        self::assertSame('/apply/continue/' . $token . '/occupants', $work->getHeaderLine('Location'));
        $occupants = $this->request($app, 'POST', '/apply/continue/' . $token, [
            'wizard_step' => 'occupants',
            'occupants_count' => '2',
            'pets_declared' => '0',
        ]);
        self::assertSame('/apply/continue/' . $token . '/references', $occupants->getHeaderLine('Location'));
        $references = $this->request($app, 'POST', '/apply/continue/' . $token . '/references', [
            'wizard_step' => 'references',
            'references' => [],
        ]);
        self::assertSame('/apply/continue/' . $token . '/documents', $references->getHeaderLine('Location'));
        $submitted = $this->request($app, 'POST', '/apply/continue/' . $token . '/submit');
        self::assertSame('/apply/continue/' . $token . '/review', $submitted->getHeaderLine('Location'));
        self::assertSame('verification', $this->candidateStatus($candidateId));
        self::assertStringContainsString($candidateName, (string) $this->request($app, 'GET', '/admin/candidates/' . $candidateId)->getBody());

        $pendingScreening = $this->request($app, 'POST', '/admin/candidates/' . $candidateId . '/status', [
            'status' => 'pending_screening',
        ]);
        self::assertSame(303, $pendingScreening->getStatusCode());
        $screening = $this->request($app, 'POST', '/admin/candidates/' . $candidateId . '/screening', [
            'provider' => 'Aseguradora Local',
            'product' => 'Estudio de arrendamiento',
        ]);
        self::assertSame(303, $screening->getStatusCode());
        $approved = $this->request($app, 'POST', '/admin/candidates/' . $candidateId . '/screening', [
            'status' => 'approved',
            'result' => 'Aprobado para formalización',
        ]);
        self::assertSame(303, $approved->getStatusCode());

        $candidateService = new CandidateService($this->pdo);
        $screeningService = new ScreeningService($this->pdo, $candidateService);
        $contractService = new ContractService(
            $this->pdo,
            $candidateService,
            $screeningService,
            new PropertyService($this->pdo),
            $this->directory . '/contracts',
        );
        $ownerId = (int) $this->pdo->query('SELECT id FROM users WHERE email = ' . $this->pdo->quote($ownerEmail))->fetchColumn();
        $template = $contractService->createTemplate($ownerId, [
            'name' => 'Arrendamiento urbano',
            'document_type' => 'urban_housing',
        ]);
        $version = $contractService->createTemplateVersion((int) $template['id'], $ownerId, [
            'body_template' => 'Contrato de {{candidate.full_name}} para {{property.name}}. Inicio {{contract.start_date}}.',
            'variables' => [],
        ]);
        self::assertTrue($contractService->canCreateDraftForCandidate($candidateId));
        $draft = $this->request($app, 'POST', '/admin/contracts', [
            'candidate_id' => (string) $candidateId,
            'template_version_id' => (string) $version['id'],
            'monthly_rent' => '1850000',
            'administration_fee' => '180000',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'duration_text' => 'Doce meses',
            'manual_variables_json' => '{}',
            'tasks_json' => '[]',
            'annexes_json' => '[]',
        ]);
        self::assertSame(303, $draft->getStatusCode());
        $contractId = (int) $this->pdo->query('SELECT id FROM contracts WHERE candidate_id = ' . $candidateId)->fetchColumn();
        self::assertGreaterThan(0, $contractId);
        self::assertSame(303, $this->request($app, 'POST', '/admin/contracts/' . $contractId, ['action' => 'generate'])->getStatusCode());
        self::assertSame('generated', $this->contractStatus($contractId));
        self::assertSame(303, $this->request($app, 'POST', '/admin/contracts/' . $contractId, ['action' => 'finalize'])->getStatusCode());
        self::assertSame('finalized', $this->contractStatus($contractId));

        $pdf = $this->request($app, 'GET', '/admin/contracts/' . $contractId . '/pdf');
        self::assertSame(200, $pdf->getStatusCode());
        self::assertSame('application/pdf', $pdf->getHeaderLine('Content-Type'));
        self::assertSame('private, no-store', $pdf->getHeaderLine('Cache-Control'));
        self::assertStringStartsWith('%PDF-', (string) $pdf->getBody());

        $paymentMethod = $this->request(
            $app,
            'POST',
            '/admin/finance/payment-methods',
            [
                'property_id' => (string) $propertyId,
                'type' => 'breb_qr',
                'label' => 'QR de pago Bogotá',
                'sort_order' => '1',
                'is_active' => '1',
            ],
            ['qr' => $this->upload($this->pngBytes(), 'qr-bogota.png', 'image/png')],
        );
        self::assertSame(303, $paymentMethod->getStatusCode(), 'Installed owner must bypass finance permissions.');
        $methodId = (int) $this->pdo->query("SELECT id FROM payment_methods WHERE label = 'QR de pago Bogotá'")->fetchColumn();
        self::assertGreaterThan(0, $methodId);

        $activation = $this->request($app, 'POST', '/admin/contracts/' . $contractId . '/lease');
        self::assertSame(200, $activation->getStatusCode());
        self::assertSame('private, no-store', $activation->getHeaderLine('Cache-Control'));
        preg_match('/<code[^>]*data-temporary-password[^>]*>([^<]{6,})<\/code>/', (string) $activation->getBody(), $matches);
        $tenantPassword = html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES, 'UTF-8');
        self::assertSame($candidateDocument, $tenantPassword);
        $leaseId = (int) $this->pdo->query('SELECT id FROM leases WHERE contract_id = ' . $contractId)->fetchColumn();
        self::assertGreaterThan(0, $leaseId);
        $tenant = $this->pdo->query(
            'SELECT document_number, password_hash, must_change_password FROM users WHERE role = \'tenant\' AND document_number = '
            . $this->pdo->quote($candidateDocument),
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($tenant);
        self::assertSame($candidateDocument, $tenant['document_number']);
        self::assertSame(0, (int) $tenant['must_change_password']);
        self::assertTrue(password_verify($candidateDocument, (string) $tenant['password_hash']));

        $this->queueFakeEmail();
        $cron = $this->cronRequest($app);
        self::assertSame(200, $cron->getStatusCode());
        $cronResult = json_decode((string) $cron->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(0, $cronResult['generated']);
        self::assertSame(count($this->sentEmails), $cronResult['sent']);
        self::assertSame(0, $cronResult['failed']);
        self::assertSame(0, $cronResult['redacted']);
        self::assertContains($candidateEmail, $this->sentEmails);
        self::assertContains('release-recipient@example.test', $this->sentEmails);
        self::assertStringNotContainsString($this->cronSecret, (string) $cron->getBody());

        $_SESSION = [];
        $tenantLogin = $this->request($app, 'POST', '/login', [
            'email' => $candidateDocument,
            'password' => $tenantPassword,
        ]);
        self::assertSame(303, $tenantLogin->getStatusCode());
        self::assertSame('/', $tenantLogin->getHeaderLine('Location'));
        $tenantHome = $this->request($app, 'GET', '/tenant');
        self::assertSame(200, $tenantHome->getStatusCode());
        self::assertStringContainsString('A-204', (string) $tenantHome->getBody());
        self::assertStringContainsString('Edificio Ñandú', (string) $tenantHome->getBody());
        $tenantLease = $this->request($app, 'GET', '/tenant/leases/' . $leaseId);
        self::assertSame(200, $tenantLease->getStatusCode());
        self::assertStringContainsString('QR de pago Bogotá', (string) $tenantLease->getBody());

        $qr = $this->request($app, 'GET', '/tenant/leases/' . $leaseId . '/payment-methods/' . $methodId . '/qr');
        self::assertSame(200, $qr->getStatusCode());
        self::assertSame('image/png', $qr->getHeaderLine('Content-Type'));
        self::assertSame('private, no-store', $qr->getHeaderLine('Cache-Control'));
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string) $qr->getBody());
    }

    private function app(): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        $this->notifications = new NotificationService($this->pdo, function (string $email): void {
            $this->sentEmails[] = $email;
        });

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->configPath,
            'migration_directory' => $this->migrationDirectory,
            'base_url' => 'https://alquilapro.example',
            'base_path' => '',
            'pdo' => $this->pdo,
            'unit_media_directory' => $this->directory . '/unit-media',
            'candidate_document_directory' => $this->directory . '/candidate-documents',
            'contract_directory' => $this->directory . '/contracts',
            'document_directory' => $this->directory . '/documents',
            'payment_method_qr_directory' => $this->directory . '/payment-qrs',
            'notifications' => $this->notifications,
            'notification_cron_secret' => $this->cronSecret,
        ]);
    }

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        array $uploads = [],
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://alquilapro.example' . $path);
        if (strtoupper($method) === 'POST') {
            $body['csrf_token'] = (new Csrf())->token();
            $request = $request->withParsedBody($body);
        }
        if ($uploads !== []) {
            $request = $request->withUploadedFiles($uploads);
        }

        return $app->handle($request);
    }

    private function cronRequest(App $app): ResponseInterface
    {
        return $app->handle(
            (new ServerRequestFactory())
                ->createServerRequest('POST', 'https://alquilapro.example/cron/notifications')
                ->withHeader('X-AlquilaPro-Cron-Secret', $this->cronSecret),
        );
    }

    private function queueFakeEmail(): void
    {
        self::assertNotNull($this->notifications);
        $this->notifications->queue(
            'candidate_created',
            'release-recipient@example.test',
            'María',
            'Solicitud recibida',
            'candidate_created',
            ['event_date' => gmdate('Y-m-d')],
            gmdate('Y-m-d H:i:s', time() - 60),
            'release-flow:' . bin2hex(random_bytes(6)),
        );
    }

    private function databaseInput(): array
    {
        return [
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ];
    }

    private function candidateStatus(int $candidateId): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM candidates WHERE id = ?');
        $statement->execute([$candidateId]);

        return (string) $statement->fetchColumn();
    }

    private function contractStatus(int $contractId): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM contracts WHERE id = ?');
        $statement->execute([$contractId]);

        return (string) $statement->fetchColumn();
    }

    private function upload(string $bytes, string $clientName, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_release_upload_');
        if ($path === false || file_put_contents($path, $bytes) !== strlen($bytes)) {
            self::fail('Could not create a release upload fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $clientName, $mime, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }

    private function resetDatabase(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
