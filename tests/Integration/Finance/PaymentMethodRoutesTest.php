<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class PaymentMethodRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $qrDirectory;
    private array $temporaryFiles = [];

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

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $safeTable = str_replace('`', '``', (string) $table);
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safeTable));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_payment_routes_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        $this->qrDirectory = $this->directory . '/private-qrs';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-13T12:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        foreach (glob($this->qrDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->qrDirectory);
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testFinanceGuardAppliesLoginPasswordTenantIsolationAndOwnerBypass(): void
    {
        $forcedId = $this->insertUser('Forced finance', 'forced-payments@example.com', 'admin', ['finance.view'], true);
        $tenantId = $this->insertUser(
            'Tenant finance',
            'tenant-payments@example.com',
            'tenant',
            ['finance.view', 'finance.manage'],
        );
        $this->pdo->prepare('UPDATE users SET permissions_json = :permissions WHERE id = :id')->execute([
            'permissions' => '["finance.view","finance.manage"]',
            'id' => $tenantId,
        ]);
        $ownerId = $this->insertUser('Owner finance', 'owner-payments@example.com', 'owner', []);
        $app = $this->app('/sub');

        $guest = $this->request($app, 'GET', '/sub/admin/finance/payment-methods');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $this->login($forcedId, true);
        $forced = $this->request($app, 'GET', '/sub/admin/finance/payment-methods');
        self::assertSame(302, $forced->getStatusCode());
        self::assertSame('/sub/account/password', $forced->getHeaderLine('Location'));

        $this->login($tenantId);
        self::assertSame(403, $this->request($app, 'GET', '/sub/admin/finance/payment-methods')->getStatusCode());

        $this->login($ownerId);
        self::assertSame(200, $this->request($app, 'GET', '/sub/admin/finance/payment-methods')->getStatusCode());
    }

    public function testFinanceViewerSeesEscapedConfigurationWithoutMutationFormsOrQrInternals(): void
    {
        $propertyId = $this->createProperty('Property <img src=x onerror=alert(1)>');
        $service = new PaymentMethodService($this->pdo, $this->qrDirectory);
        $service->create($this->input('bank_account', [
            'property_id' => (string) $propertyId,
            'label' => 'Cuenta <script>alert("label")</script>',
            'recipient_name' => 'Persona <img src=x onerror=alert(2)>',
            'institution_name' => 'Banco & Asociados',
            'account_type' => 'savings',
            'account_number' => '001234567890',
            'sort_order' => 8,
            'is_active' => 0,
        ]), null);
        $qr = $service->create(
            $this->input('breb_qr', ['label' => 'QR privado']),
            $this->upload($this->pngBytes(), 'private-original.png', 'image/png'),
        );
        $viewerId = $this->insertUser('Finance viewer', 'viewer-payments@example.com', 'gestor', ['finance.view']);
        $app = $this->app('/sub');
        $this->login($viewerId);

        $response = $this->request($app, 'GET', '/sub/admin/finance/payment-methods');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('Medios de pago', $body);
        self::assertStringContainsString('Global', $body);
        self::assertStringContainsString('Cuenta bancaria', $body);
        self::assertStringContainsString('Orden: 8 · Inactivo', $body);
        self::assertStringContainsString('Property &lt;img src=x onerror=alert(1)&gt;', $body);
        self::assertStringContainsString('Cuenta &lt;script&gt;alert(&quot;label&quot;)&lt;/script&gt;', $body);
        self::assertStringContainsString('Persona &lt;img src=x onerror=alert(2)&gt;', $body);
        self::assertStringContainsString('Banco &amp; Asociados', $body);
        self::assertStringContainsString('QR disponible', $body);
        self::assertStringNotContainsString('<script>alert("label")</script>', $body);
        self::assertStringNotContainsString('<img src=x onerror=alert(2)>', $body);
        self::assertStringNotContainsString($qr['qr_stored_name'], $body);
        self::assertStringNotContainsString($this->qrDirectory, $body);
        self::assertStringNotContainsString($qr['qr_sha256'], $body);
        self::assertStringNotContainsString('name="qr_stored_name"', $body);
        self::assertStringNotContainsString('name="qr_sha256"', $body);
        self::assertStringNotContainsString('method="post"', strtolower($body));
        foreach (['payment_transactions', 'reconciliación', 'reportar pago', 'ya pagué', 'allocation'] as $futureControl) {
            self::assertStringNotContainsString($futureControl, strtolower($body));
        }

        $blocked = $this->request($app, 'POST', '/sub/admin/finance/payment-methods', $this->input('manual'));
        self::assertSame(403, $blocked->getStatusCode());
        self::assertSame(2, $this->methodCount());
    }

    public function testManagerMutationsUseCsrfPrgBasePathAndInvalidInputIsAtomic(): void
    {
        $managerId = $this->insertUser(
            'Finance manager',
            'manager-payments@example.com',
            'gestor',
            ['finance.view', 'finance.manage'],
        );
        $app = $this->app('/panel');
        $this->login($managerId);

        $page = $this->request($app, 'GET', '/panel/admin/finance/payment-methods');
        $pageBody = (string) $page->getBody();
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('action="/panel/admin/finance/payment-methods"', $pageBody);
        self::assertStringContainsString('name="csrf_token"', $pageBody);

        $invalidCreate = $this->request($app, 'POST', '/panel/admin/finance/payment-methods', $this->input('manual', [
            'instructions' => '',
        ]));
        self::assertSame(422, $invalidCreate->getStatusCode());
        self::assertSame(0, $this->methodCount());
        self::assertStringNotContainsString('instructions', (string) $invalidCreate->getBody());

        $missingCsrfCreate = $this->request(
            $app,
            'POST',
            '/panel/admin/finance/payment-methods',
            $this->input('manual'),
            [],
            false,
        );
        self::assertSame(403, $missingCsrfCreate->getStatusCode());
        self::assertSame(0, $this->methodCount());

        $created = $this->request($app, 'POST', '/panel/admin/finance/payment-methods', $this->input('manual', [
            'label' => 'Transferencia manual',
        ]));
        self::assertSame(303, $created->getStatusCode());
        self::assertSame('/panel/admin/finance/payment-methods', $created->getHeaderLine('Location'));
        self::assertSame(1, $this->methodCount());
        $methodId = (int) $this->pdo->query('SELECT id FROM payment_methods LIMIT 1')->fetchColumn();

        $updatePath = '/panel/admin/finance/payment-methods/' . $methodId;
        $invalidUpdate = $this->request($app, 'POST', $updatePath, $this->input('wallet', [
            'label' => 'Debe permanecer manual',
            'wallet_identifier' => '',
        ]));
        self::assertSame(422, $invalidUpdate->getStatusCode());
        self::assertSame('manual', $this->method($methodId)['type']);
        self::assertSame('Transferencia manual', $this->method($methodId)['label']);

        $missingCsrfUpdate = $this->request(
            $app,
            'POST',
            $updatePath,
            $this->input('manual', ['label' => 'No CSRF']),
            [],
            false,
        );
        self::assertSame(403, $missingCsrfUpdate->getStatusCode());
        self::assertSame('Transferencia manual', $this->method($methodId)['label']);

        $updated = $this->request($app, 'POST', $updatePath, $this->input('wallet', [
            'label' => 'Billetera principal',
            'institution_name' => 'Nequi',
            'wallet_identifier' => '3001234567',
            'instructions' => 'Enviar al número registrado.',
            'is_active' => '0',
            'sort_order' => '4',
        ]));
        self::assertSame(303, $updated->getStatusCode());
        self::assertSame('/panel/admin/finance/payment-methods', $updated->getHeaderLine('Location'));
        self::assertSame('wallet', $this->method($methodId)['type']);
        self::assertSame(0, (int) $this->method($methodId)['is_active']);
        self::assertSame(4, (int) $this->method($methodId)['sort_order']);
    }

    public function testMultipartQrUploadUsesTheServiceAndRejectsFileOnNonQrMethod(): void
    {
        $managerId = $this->insertUser(
            'Finance manager upload',
            'manager-upload@example.com',
            'gestor',
            ['finance.view', 'finance.manage'],
        );
        $app = $this->app();
        $this->login($managerId);

        $manualWithFile = $this->request(
            $app,
            'POST',
            '/admin/finance/payment-methods',
            $this->input('manual'),
            ['qr' => $this->upload($this->pngBytes(), 'unexpected.png', 'image/png')],
        );
        self::assertSame(422, $manualWithFile->getStatusCode());
        self::assertSame(0, $this->methodCount());

        $created = $this->request(
            $app,
            'POST',
            '/admin/finance/payment-methods',
            $this->input('breb_qr', ['label' => 'QR de recepción']),
            ['qr' => $this->upload($this->pngBytes(), 'external-bank-qr.svg', 'image/svg+xml')],
        );
        self::assertSame(303, $created->getStatusCode());
        self::assertSame('/admin/finance/payment-methods', $created->getHeaderLine('Location'));
        self::assertSame(1, $this->methodCount());
        $row = $this->method((int) $this->pdo->query('SELECT id FROM payment_methods LIMIT 1')->fetchColumn());
        self::assertSame('image/png', $row['qr_mime_type']);
        self::assertSame('breb_qr', $row['type']);
    }

    private function app(string $basePath = ''): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.example' . $basePath,
            'base_path' => $basePath,
            'logo_directory' => $this->directory . '/branding',
            'payment_method_qr_directory' => $this->qrDirectory,
            'pdo' => $this->pdo,
        ]);
    }

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        array $files = [],
        bool $withCsrf = true,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://alquilapro.example' . $path);
        if (strtoupper($method) === 'POST' && $withCsrf) {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }
        if ($files !== []) {
            $request = $request->withUploadedFiles($files);
        }

        return $app->handle($request);
    }

    private function login(int $userId, bool $mustChangePassword = false): void
    {
        (new SessionGuard())->login([
            'id' => $userId,
            'must_change_password' => $mustChangePassword,
        ], time());
    }

    private function insertUser(string $name, string $email, string $role, array $permissions, bool $mustChange = false): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :name, :email, :password_hash, 1, :permissions, :must_change)',
        );
        $statement->execute([
            'role' => $role,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
            'permissions' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change' => $mustChange ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createProperty(string $name): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city) VALUES (:name, :address, :city)',
        );
        $statement->execute(['name' => $name, 'address' => 'Calle 1', 'city' => 'Bogotá']);

        return (int) $this->pdo->lastInsertId();
    }

    private function input(string $type, array $overrides = []): array
    {
        $typeFields = [
            'bank_account' => [
                'recipient_name' => 'Titular',
                'institution_name' => 'Banco',
                'account_type' => 'savings',
                'account_number' => '001234567890',
            ],
            'breb_key' => ['breb_key_type' => 'mobile', 'breb_key_value' => '3001234567'],
            'breb_qr' => [],
            'wallet' => ['institution_name' => 'Nequi', 'wallet_identifier' => '3001234567'],
            'manual' => ['instructions' => 'Use la referencia del contrato.'],
        ];

        return array_replace([
            'property_id' => null,
            'type' => $type,
            'label' => 'Medio de pago',
            'recipient_name' => null,
            'institution_name' => null,
            'account_type' => null,
            'account_number' => null,
            'breb_key_type' => null,
            'breb_key_value' => null,
            'wallet_identifier' => null,
            'instructions' => null,
            'sort_order' => 1,
            'is_active' => 1,
        ], $typeFields[$type] ?? [], $overrides);
    }

    private function upload(string $bytes, string $clientName, string $clientMime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_payment_route_upload_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create payment-method upload fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $clientName, $clientMime, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function methodCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM payment_methods')->fetchColumn();
    }

    private function method(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM payment_methods WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
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
}
