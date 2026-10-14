<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class PaymentRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $receiptDirectory;
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

        $this->directory = sys_get_temp_dir() . '/alquila_payment_routes_' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        $this->receiptDirectory = $this->directory . '/receipts';
        $this->qrDirectory = $this->directory . '/qrs';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-13T10:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        foreach ([$this->receiptDirectory, $this->qrDirectory] as $storage) {
            foreach (glob($storage . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (is_file($path) || is_link($path)) {
                    @unlink($path);
                }
            }
            @rmdir($storage);
        }
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory . '/branding');
        @rmdir($this->directory . '/unit-media');
        @rmdir($this->directory . '/candidate-documents');
        @rmdir($this->directory . '/contracts');
        @rmdir($this->directory);
    }

    public function testStaffQueueAuthorizationReceiptDeliveryAndTerminalActions(): void
    {
        $tenant = $this->createLease('Tenant <script>alert(1)</script>');
        $chargeId = $this->insertCharge($tenant['lease_id'], $tenant['actor_id'], '500.00');
        $methodId = $this->insertPaymentMethod(null, 'Cuenta de nómina');
        $first = $this->report($tenant['lease_id'], $methodId, $chargeId, '20.00', $this->upload($this->pngBytes(), 'proof.png', 'image/png'));
        $second = $this->report($tenant['lease_id'], $methodId, $chargeId, '20.00');
        $viewerId = $this->insertUser('viewer@example.com', 'Viewer Finance', 'admin', ['finance.view']);
        $managerId = $this->insertUser('manager@example.com', 'Manager Finance', 'admin', ['finance.view', 'finance.manage']);
        $forcedId = $this->insertUser('forced@example.com', 'Forced Finance', 'admin', ['finance.view', 'finance.manage']);
        $ownerId = $this->insertUser('owner@example.com', 'Owner Finance', 'owner', []);
        $pollutedTenantId = $this->insertUser(
            'polluted@example.com', 'Polluted Tenant', 'tenant', ['finance.view', 'finance.manage'],
        );
        $ownerPayment = $this->report($tenant['lease_id'], $methodId, $chargeId, '30.00');
        $app = $this->app('/sub');

        $guest = $this->request($app, 'GET', '/sub/admin/finance');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $detailPath = '/sub/admin/finance/payments/' . $first['id'];
        $this->login($forcedId, true);
        $forcedGet = $this->request($app, 'GET', $detailPath);
        self::assertSame(302, $forcedGet->getStatusCode());
        self::assertSame('/sub/account/password', $forcedGet->getHeaderLine('Location'));
        $forcedPost = $this->request($app, 'POST', $detailPath . '/reconcile', [
            'allocations' => [(string) $chargeId => '20.00'],
        ]);
        self::assertSame(302, $forcedPost->getStatusCode());
        self::assertSame('/sub/account/password', $forcedPost->getHeaderLine('Location'));

        $this->login($viewerId);
        $queue = $this->request($app, 'GET', '/sub/admin/finance');
        $queueHtml = (string) $queue->getBody();
        self::assertSame(200, $queue->getStatusCode());
        self::assertStringContainsString('Pendientes de conciliación', $queueHtml);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $queueHtml);
        self::assertStringNotContainsString('<script>alert(1)</script>', $queueHtml);
        self::assertStringContainsString('Cuenta de nómina', $queueHtml);
        self::assertStringContainsString('Posible reporte duplicado', $queueHtml);
        self::assertStringContainsString('/sub/admin/finance/payments/' . $first['id'], $queueHtml);

        $detail = $this->request($app, 'GET', $detailPath);
        $detailHtml = (string) $detail->getBody();
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('Tenant &lt;script&gt;alert(1)&lt;/script&gt;', $detailHtml);
        self::assertStringContainsString('Asignaciones propuestas', $detailHtml);
        self::assertStringNotContainsString('Conciliar pago', $detailHtml);
        self::assertStringNotContainsString('Rechazar reporte', $detailHtml);
        self::assertStringNotContainsString('receipt-', $detailHtml);
        self::assertStringNotContainsString('qr_stored_name', $detailHtml);

        $receipt = $this->request($app, 'GET', $detailPath . '/receipt');
        self::assertSame(200, $receipt->getStatusCode());
        self::assertSame('image/png', $receipt->getHeaderLine('Content-Type'));
        self::assertSame((string) strlen($this->pngBytes()), $receipt->getHeaderLine('Content-Length'));
        self::assertSame('private, no-store', $receipt->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $receipt->getHeaderLine('X-Content-Type-Options'));
        self::assertSame($this->pngBytes(), (string) $receipt->getBody());
        $storedReceipt = $this->service()->privatePaymentReceipt((int) $first['id']);
        self::assertNotNull($storedReceipt);
        file_put_contents($storedReceipt['path'], 'tampered');
        self::assertSame(404, $this->request($app, 'GET', $detailPath . '/receipt')->getStatusCode());

        self::assertSame(403, $this->request($app, 'POST', $detailPath . '/reconcile', [
            'allocations' => [(string) $chargeId => '20.00'],
        ])->getStatusCode());
        self::assertSame(403, $this->request($app, 'POST', $detailPath . '/reject', ['reason' => 'No coincide'])->getStatusCode());

        $this->login($managerId);
        $managerDetail = $this->request($app, 'GET', $detailPath);
        self::assertStringContainsString('Conciliar pago', (string) $managerDetail->getBody());
        self::assertStringContainsString('Rechazar reporte', (string) $managerDetail->getBody());

        $this->login($pollutedTenantId);
        self::assertSame(403, $this->request($app, 'GET', '/sub/admin/finance')->getStatusCode());
        self::assertSame(403, $this->request($app, 'GET', $detailPath)->getStatusCode());

        $this->login($ownerId);
        $ownerDetail = $this->request($app, 'GET', '/sub/admin/finance/payments/' . $ownerPayment['id']);
        self::assertSame(200, $ownerDetail->getStatusCode());
        self::assertStringContainsString('Conciliar pago', (string) $ownerDetail->getBody());
        $ownerReconcile = $this->request(
            $app,
            'POST',
            '/sub/admin/finance/payments/' . $ownerPayment['id'] . '/reconcile',
            ['allocations' => [(string) $chargeId => '30.00']],
        );
        self::assertSame(303, $ownerReconcile->getStatusCode());
        self::assertSame('reconciled', $this->service()->findPayment((int) $ownerPayment['id'])['state']);

        $this->login($managerId);
        self::assertSame(403, $this->request($app, 'POST', $detailPath . '/reconcile', [
            'allocations' => [(string) $chargeId => '20.00'],
        ], withCsrf: false)->getStatusCode());
        $reconcile = $this->request($app, 'POST', $detailPath . '/reconcile', [
            'allocations' => [(string) $chargeId => '20.00'],
        ]);
        self::assertSame(303, $reconcile->getStatusCode());
        self::assertSame($detailPath, $reconcile->getHeaderLine('Location'));
        self::assertSame('reconciled', $this->service()->findPayment((int) $first['id'])['state']);

        $reject = $this->request($app, 'POST', '/sub/admin/finance/payments/' . $second['id'] . '/reject', [
            'reason' => '  El comprobante no coincide.  ',
        ]);
        self::assertSame(303, $reject->getStatusCode());
        self::assertSame('/sub/admin/finance/payments/' . $second['id'], $reject->getHeaderLine('Location'));
        self::assertSame('rejected', $this->service()->findPayment((int) $second['id'])['state']);
    }

    public function testTenantOwnershipReportHistoryAndPrivateReceiptOrdering(): void
    {
        $owned = $this->createLease('Tenant Owner');
        $foreign = $this->createLease('Foreign Tenant');
        $chargeId = $this->insertCharge($owned['lease_id'], $owned['actor_id'], '300.00', 'Cargo propio');
        $foreignChargeId = $this->insertCharge($foreign['lease_id'], $foreign['actor_id'], '300.00', 'Cargo ajeno');
        $methodId = $this->insertPaymentMethod(null, 'Medio global');
        $foreignMethodId = $this->insertPaymentMethod($foreign['property_id'], 'Método ajeno');
        $existing = $this->report(
            $owned['lease_id'], $methodId, $chargeId, '15.00', $this->upload($this->pngBytes(), 'old-proof.png', 'image/png'),
        );
        $foreignPayment = $this->report(
            $foreign['lease_id'], $methodId, $foreignChargeId, '20.00', $this->upload($this->pngBytes(), 'foreign.png', 'image/png'),
        );
        $this->service()->rejectPayment((int) $existing['id'], 'El soporte está incompleto.', $owned['actor_id'], $this->now());
        $tenantApp = $this->app('/sub');
        $this->login($owned['tenant_id']);

        $detail = $this->request($tenantApp, 'GET', '/sub/tenant/leases/' . $owned['lease_id']);
        $detailHtml = (string) $detail->getBody();
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('Reportar pago', $detailHtml);
        self::assertStringContainsString('name="payment_method_id"', $detailHtml);
        self::assertStringContainsString('name="allocations[' . $chargeId . ']"', $detailHtml);
        self::assertStringContainsString('El soporte está incompleto.', $detailHtml);
        self::assertStringContainsString('Rechazado', $detailHtml);
        self::assertStringNotContainsString('rejected_by_user_id', $detailHtml);
        self::assertStringNotContainsString('receipt_stored_name', $detailHtml);

        $traced = $this->tracedPdo();
        $appWithTrace = $this->app('/sub', $traced);
        self::assertSame(404, $this->request($appWithTrace, 'GET', '/sub/tenant/leases/' . $foreign['lease_id'])->getStatusCode());
        self::assertSame([], array_filter($traced->preparedSql, static fn (string $sql): bool =>
            str_contains($sql, 'payments') || str_contains($sql, 'payment_methods')));
        $traced->preparedSql = [];
        self::assertSame(404, $this->request(
            $appWithTrace,
            'GET',
            '/sub/tenant/leases/' . $foreign['lease_id'] . '/payments/' . $foreignPayment['id'] . '/receipt',
        )->getStatusCode());
        self::assertSame([], array_filter($traced->preparedSql, static fn (string $sql): bool =>
            str_contains($sql, 'payments') || str_contains($sql, 'payment_methods')));

        $badCharge = $this->request($tenantApp, 'POST', '/sub/tenant/leases/' . $owned['lease_id'] . '/payments', [
            'payment_method_id' => (string) $methodId,
            'amount' => '20.00',
            'paid_on' => '2026-10-12',
            'allocations' => [(string) $foreignChargeId => '20.00'],
        ]);
        self::assertSame(422, $badCharge->getStatusCode());
        $badMethod = $this->request($tenantApp, 'POST', '/sub/tenant/leases/' . $owned['lease_id'] . '/payments', [
            'payment_method_id' => (string) $foreignMethodId,
            'amount' => '20.00',
            'paid_on' => '2026-10-12',
            'allocations' => [(string) $chargeId => '20.00'],
        ]);
        self::assertSame(422, $badMethod->getStatusCode());

        $reported = $this->request($tenantApp, 'POST', '/sub/tenant/leases/' . $owned['lease_id'] . '/payments', [
            'payment_method_id' => (string) $methodId,
            'amount' => '20.00',
            'paid_on' => '2026-10-12',
            'paid_time' => '09:30',
            'allocations' => [(string) $chargeId => '20.00'],
        ], ['receipt' => $this->upload($this->pngBytes(), 'receipt.png', 'image/png')]);
        self::assertSame(303, $reported->getStatusCode());
        self::assertSame('/sub/tenant/leases/' . $owned['lease_id'], $reported->getHeaderLine('Location'));

        $ownPayments = $this->service()->listPaymentsForLease($owned['lease_id']);
        self::assertCount(2, $ownPayments);
        $newPayment = $ownPayments[0];
        self::assertSame('pending_reconciliation', $newPayment['state']);
        self::assertSame(200, $this->request($tenantApp, 'GET', '/sub/tenant/leases/' . $owned['lease_id'])->getStatusCode());
        $historyHtml = (string) $this->request($tenantApp, 'GET', '/sub/tenant/leases/' . $owned['lease_id'])->getBody();
        self::assertStringContainsString('Pendiente de conciliación', $historyHtml);
        self::assertStringContainsString('/sub/tenant/leases/' . $owned['lease_id'] . '/payments/' . $newPayment['id'] . '/receipt', $historyHtml);
        self::assertStringNotContainsString('Finance Actor', $historyHtml);
        self::assertStringNotContainsString('receipt-' . str_repeat('a', 32), $historyHtml);

        $ownReceipt = $this->request(
            $tenantApp,
            'GET',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/payments/' . $existing['id'] . '/receipt',
        );
        self::assertSame(200, $ownReceipt->getStatusCode());
        self::assertSame('image/png', $ownReceipt->getHeaderLine('Content-Type'));
        $storedReceipt = $this->service()->privatePaymentReceipt((int) $existing['id']);
        self::assertNotNull($storedReceipt);
        file_put_contents($storedReceipt['path'], 'tampered');
        self::assertSame(404, $this->request(
            $tenantApp,
            'GET',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/payments/' . $existing['id'] . '/receipt',
        )->getStatusCode());
        $wrongPayment = $this->request(
            $tenantApp,
            'GET',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/payments/' . $foreignPayment['id'] . '/receipt',
        );
        self::assertSame(404, $wrongPayment->getStatusCode());
        self::assertSame(403, $this->request($tenantApp, 'GET', '/sub/admin/finance')->getStatusCode());
    }

    private function app(string $basePath = '', ?PDO $pdo = null): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.example' . $basePath,
            'base_path' => $basePath,
            'logo_directory' => $this->directory . '/branding',
            'unit_media_directory' => $this->directory . '/unit-media',
            'candidate_document_directory' => $this->directory . '/candidate-documents',
            'contract_directory' => $this->directory . '/contracts',
            'payment_method_qr_directory' => $this->qrDirectory,
            'payment_receipt_directory' => $this->receiptDirectory,
            'today' => static fn (): string => '2026-10-13',
            'pdo' => $pdo ?? $this->pdo,
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
        (new SessionGuard())->login(['id' => $userId, 'must_change_password' => $mustChangePassword], time());
    }

    private function insertUser(string $email, string $name, string $role, array $permissions): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, permissions_json) VALUES (?, ?, ?, ?, ?)',
        );
        $statement->execute([
            $role, $name, $email, password_hash('TemporaryPass123', PASSWORD_DEFAULT),
            $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createLease(string $tenantName): array
    {
        $suffix = bin2hex(random_bytes(5));
        $tenantId = $this->insertUser('tenant-' . $suffix . '@example.com', $tenantName, 'tenant', []);
        $actorId = $this->insertUser('actor-' . $suffix . '@example.com', 'Finance Actor', 'admin', []);
        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city) VALUES (?, ?, ?)');
        $property->execute(['Property ' . $suffix, 'Calle 1', 'Bogotá']);
        $propertyId = (int) $this->pdo->lastInsertId();
        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) VALUES (?, ?, ?, ?, ?)',
        );
        $unit->execute([$propertyId, bin2hex(random_bytes(12)), 'apartment', 'APT-' . $suffix, 'occupied']);
        $unitId = (int) $this->pdo->lastInsertId();
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $candidate->execute([
            $unitId, 'cc', 'DOC-' . $suffix, $tenantName, '3001234567', 'candidate-' . $suffix . '@example.com',
            'approved', hash('sha256', $suffix), '2027-01-01 00:00:00', '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();
        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type) VALUES (?, ?)');
        $template->execute(['Template ' . $suffix, 'urban_housing']);
        $templateId = (int) $this->pdo->lastInsertId();
        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) VALUES (?, 1, ?, ?)',
        );
        $version->execute([$templateId, 'Contrato', '[]']);
        $versionId = (int) $this->pdo->lastInsertId();
        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, '
            . 'administration_fee, start_date, end_date, duration_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $contract->execute([
            $candidateId, $unitId, $versionId, 'urban_housing', 'finalized', '1500000.00', '200000.00',
            '2026-10-01', '2027-09-30', 'Doce meses',
        ]);
        $contractId = (int) $this->pdo->lastInsertId();
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) VALUES (?, ?, ?, ?)',
        );
        $lease->execute([$contractId, $tenantId, $actorId, '2026-10-01 12:00:00']);

        return [
            'lease_id' => (int) $this->pdo->lastInsertId(),
            'contract_id' => $contractId,
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'property_id' => $propertyId,
        ];
    }

    private function insertCharge(int $leaseId, int $actorId, string $amount, string $description = 'Cargo de prueba'): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO charges (lease_id, type, due_date, amount, description, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?)',
        );
        $statement->execute([$leaseId, 'rent', '2026-10-20', $amount, $description, $actorId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertPaymentMethod(?int $propertyId, string $label): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO payment_methods (property_id, type, label, recipient_name, institution_name, account_type, '
            . 'account_number, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
        );
        $statement->execute([$propertyId, 'bank_account', $label, 'Titular visible', 'Banco', 'savings', '123456']);

        return (int) $this->pdo->lastInsertId();
    }

    private function service(): FinanceService
    {
        return new FinanceService(
            $this->pdo,
            new PaymentMethodService($this->pdo, $this->qrDirectory),
            $this->receiptDirectory,
        );
    }

    private function report(int $leaseId, int $methodId, int $chargeId, string $amount, mixed $receipt = null): array
    {
        return $this->service()->reportPayment(
            $leaseId, $methodId, $amount, '2026-10-12', null,
            [['charge_id' => $chargeId, 'amount' => $amount]], $receipt, $this->now(),
        );
    }

    private function upload(string $bytes, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_payment_route_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create payment route upload fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, $mime, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function tracedPdo(): PDO
    {
        $host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
        $port = getenv('TEST_DB_PORT') ?: '3306';
        $database = getenv('TEST_DB_NAME') ?: 'alquila_test';
        $username = getenv('TEST_DB_USER') ?: 'root';
        $password = getenv('TEST_DB_PASSWORD') ?: 'root';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        return new class($dsn, $username, $password) extends PDO {
            public array $preparedSql = [];

            public function __construct(string $dsn, string $username, string $password)
            {
                parent::__construct($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                $this->preparedSql[] = $query;

                return parent::prepare($query, $options);
            }
        };
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

    private function now(): int
    {
        return (new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('America/Bogota')))->getTimestamp();
    }
}
