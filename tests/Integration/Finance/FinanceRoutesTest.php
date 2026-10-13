<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class FinanceRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;

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

        $this->directory = sys_get_temp_dir() . '/alquila_finance_routes_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-11T20:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testPortfolioRequiresLoginAndRendersEscapedCanonicalDataForFinanceViewer(): void
    {
        $tenantName = '<script>alert("tenant")</script>';
        $fixture = $this->createLease($tenantName, 'Casa <img src=x onerror=alert(1)>', 'A<1>');
        $this->insertCharge($fixture['lease_id'], 'rent', '2026-10-01', '2026-10-12', '125.50', 'Cargo visible');
        $this->insertCharge(
            $fixture['lease_id'], 'fine', null, '2026-10-01', '900.00', 'MOTIVO PRIVADO F9', '2026-10-02 12:00:00',
        );
        $viewerId = $this->insertUser('Viewer Finance', 'viewer@example.com', 'gestor', ['finance.view']);
        $app = $this->app('/sub');

        $guest = $this->request($app, 'GET', '/sub/admin/finance');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $this->login($viewerId);
        $response = $this->request($app, 'GET', '/sub/admin/finance');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Cartera', $body);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;tenant&quot;)&lt;/script&gt;', $body);
        self::assertStringContainsString('Casa &lt;img src=x onerror=alert(1)&gt;', $body);
        self::assertStringContainsString('A&lt;1&gt;', $body);
        self::assertStringNotContainsString('<script>alert("tenant")</script>', $body);
        self::assertStringNotContainsString('MOTIVO PRIVADO F9', $body);
        self::assertStringNotContainsString('void_reason', $body);
        self::assertStringNotContainsString('voided_by_user_id', $body);
        self::assertStringNotContainsString('created_by_user_id', $body);
        self::assertStringContainsString('125.50', $body);
        self::assertStringContainsString('admin/contracts/' . $fixture['contract_id'], $body);
        self::assertStringNotContainsString('name="period_month"', $body);
        self::assertStringNotContainsString('name="amount"', $body);
        self::assertStringNotContainsString('payments', strtolower($body));
        self::assertStringNotContainsString('data-chart', $body);
        self::assertStringNotContainsString('/sub/admin/miembros', $body);

        $before = $this->chargeCount($fixture['lease_id']);
        $blocked = $this->request($app, 'POST', '/sub/admin/leases/' . $fixture['lease_id'] . '/charges/monthly', [
            'period_month' => '2026-10-01',
            'due_date' => '2026-10-05',
        ]);
        self::assertSame(403, $blocked->getStatusCode());
        self::assertSame($before, $this->chargeCount($fixture['lease_id']));
    }

    public function testFinanceGuardAppliesPasswordGateTenantIsolationAndOwnerBypass(): void
    {
        $forcedId = $this->insertUser('Forced User', 'forced@example.com', 'admin', ['finance.view'], true);
        $tenantId = $this->insertUser('Tenant User', 'tenant@example.com', 'tenant', ['finance.view', 'finance.manage']);
        $this->pdo->exec(
            'UPDATE users SET permissions_json = ' . $this->pdo->quote('["finance.view","finance.manage"]')
            . ' WHERE id = ' . $tenantId,
        );
        $ownerId = $this->insertUser('Owner User', 'owner@example.com', 'owner', []);
        $app = $this->app();

        $this->login($forcedId, true);
        $forced = $this->request($app, 'GET', '/admin/finance');
        self::assertSame(302, $forced->getStatusCode());
        self::assertSame('/account/password', $forced->getHeaderLine('Location'));

        $this->login($tenantId);
        self::assertSame(403, $this->request($app, 'GET', '/admin/finance')->getStatusCode());

        $this->login($ownerId);
        self::assertSame(200, $this->request($app, 'GET', '/admin/finance')->getStatusCode());
    }

    public function testFinanceManagerCanUseAllMutationsWithPrgAndInvalidInputsDoNotMutate(): void
    {
        $fixture = $this->createLease();
        $chargeToVoid = $this->insertCharge(
            $fixture['lease_id'], 'service', null, '2026-10-20', '50.00', 'Servicio de prueba',
        );
        $managerId = $this->insertUser('Finance Manager', 'manager@example.com', 'gestor', ['finance.manage']);
        $app = $this->app('/sub');
        $this->login($managerId);

        $monthly = $this->request($app, 'POST', '/sub/admin/leases/' . $fixture['lease_id'] . '/charges/monthly', [
            'period_month' => '2026-10-01',
            'due_date' => '2026-10-05',
        ]);
        self::assertSame(303, $monthly->getStatusCode());
        self::assertSame('/sub/admin/contracts/' . $fixture['contract_id'], $monthly->getHeaderLine('Location'));

        $manual = $this->request($app, 'POST', '/sub/admin/leases/' . $fixture['lease_id'] . '/charges', [
            'type' => 'repair',
            'amount' => '75.25',
            'due_date' => '2026-10-25',
            'description' => 'Reparación autorizada',
            'period_month' => '',
        ]);
        self::assertSame(303, $manual->getStatusCode());
        self::assertSame('/sub/admin/contracts/' . $fixture['contract_id'], $manual->getHeaderLine('Location'));

        $void = $this->request($app, 'POST', '/sub/admin/charges/' . $chargeToVoid . '/void', [
            'reason' => 'Registro duplicado',
        ]);
        self::assertSame(303, $void->getStatusCode());
        self::assertSame('/sub/admin/contracts/' . $fixture['contract_id'], $void->getHeaderLine('Location'));

        $countAfterValidPosts = $this->chargeCount($fixture['lease_id']);
        $manualRent = $this->request($app, 'POST', '/sub/admin/leases/' . $fixture['lease_id'] . '/charges', [
            'type' => 'rent',
            'amount' => '10.00',
            'due_date' => '2026-10-25',
            'description' => 'No permitido en vía manual',
            'period_month' => '',
        ]);
        self::assertSame(422, $manualRent->getStatusCode());

        $outsidePeriod = $this->request($app, 'POST', '/sub/admin/leases/' . $fixture['lease_id'] . '/charges/monthly', [
            'period_month' => '2027-10-01',
            'due_date' => '2027-10-05',
        ]);
        self::assertSame(422, $outsidePeriod->getStatusCode());

        $emptyReason = $this->request($app, 'POST', '/sub/admin/charges/' . $chargeToVoid . '/void', ['reason' => '   ']);
        self::assertSame(422, $emptyReason->getStatusCode());
        self::assertSame($countAfterValidPosts, $this->chargeCount($fixture['lease_id']));
        $stored = $this->pdo->prepare('SELECT void_reason FROM charges WHERE id = :id');
        $stored->execute(['id' => $chargeToVoid]);
        self::assertSame('Registro duplicado', $stored->fetchColumn());
    }

    public function testEveryFinancePostUsesGlobalCsrfMiddleware(): void
    {
        $fixture = $this->createLease();
        $chargeId = $this->insertCharge(
            $fixture['lease_id'], 'fine', null, '2026-10-20', '20.00', 'Multa de prueba',
        );
        $managerId = $this->insertUser('Finance Manager', 'manager@example.com', 'gestor', ['finance.manage']);
        $app = $this->app();
        $this->login($managerId);

        $posts = [
            ['/admin/leases/' . $fixture['lease_id'] . '/charges/monthly', [
                'period_month' => '2026-10-01', 'due_date' => '2026-10-05',
            ]],
            ['/admin/leases/' . $fixture['lease_id'] . '/charges', [
                'type' => 'service', 'amount' => '12.00', 'due_date' => '2026-10-20', 'description' => 'Servicio',
            ]],
            ['/admin/charges/' . $chargeId . '/void', ['reason' => 'Anulación']],
        ];
        foreach ($posts as [$path, $body]) {
            $response = $this->request($app, 'POST', $path, $body, withCsrf: false);
            self::assertSame(403, $response->getStatusCode(), $path);
        }

        self::assertSame(1, $this->chargeCount($fixture['lease_id']));
        $voidedAt = $this->pdo->query('SELECT voided_at FROM charges WHERE id = ' . $chargeId)->fetchColumn();
        self::assertNull($voidedAt);
    }

    public function testExistingContractDetailShowsFinanceReadAndManageControlsOnlyWithTheirPermissions(): void
    {
        $fixture = $this->createLease('Tenant Admin Context');
        $chargeId = $this->insertCharge(
            $fixture['lease_id'], 'service', null, '2026-10-12', '75.25', '<b>Caldera reparada</b>',
        );
        $viewerId = $this->insertUser(
            'Finance Read Viewer', 'finance-read@example.com', 'gestor', ['contracts.view', 'finance.view'],
        );
        $managerId = $this->insertUser(
            'Finance Context Manager', 'finance-context-manager@example.com', 'gestor', [
                'contracts.view', 'finance.view', 'finance.manage',
            ],
        );
        $contractViewerId = $this->insertUser(
            'Contract Only Viewer', 'contract-only@example.com', 'gestor', ['contracts.view'],
        );
        $app = $this->app('/sub');
        $detailPath = '/sub/admin/contracts/' . $fixture['contract_id'];

        $this->login($viewerId);
        $viewerResponse = $this->request($app, 'GET', $detailPath);
        $viewerHtml = (string) $viewerResponse->getBody();
        self::assertSame(200, $viewerResponse->getStatusCode());
        self::assertStringContainsString('Cargos del arrendamiento', $viewerHtml);
        self::assertStringContainsString('Saldo pendiente', $viewerHtml);
        self::assertStringContainsString('75.25', $viewerHtml);
        self::assertStringContainsString('&lt;b&gt;Caldera reparada&lt;/b&gt;', $viewerHtml);
        self::assertStringNotContainsString('<b>Caldera reparada</b>', $viewerHtml);
        self::assertStringNotContainsString('name="period_month"', $viewerHtml);
        self::assertStringNotContainsString('name="void_reason"', $viewerHtml);

        $this->login($managerId);
        $managerResponse = $this->request($app, 'GET', $detailPath);
        $managerHtml = (string) $managerResponse->getBody();
        self::assertSame(200, $managerResponse->getStatusCode());
        self::assertStringContainsString('action="/sub/admin/leases/' . $fixture['lease_id'] . '/charges/monthly"', $managerHtml);
        self::assertStringContainsString('action="/sub/admin/leases/' . $fixture['lease_id'] . '/charges"', $managerHtml);
        self::assertStringContainsString('action="/sub/admin/charges/' . $chargeId . '/void"', $managerHtml);
        self::assertStringContainsString('name="csrf_token"', $managerHtml);
        foreach (['service', 'fine', 'repair', 'other'] as $manualType) {
            self::assertStringContainsString('value="' . $manualType . '"', $managerHtml);
        }
        self::assertStringNotContainsString('value="rent"', $managerHtml);
        self::assertStringNotContainsString('value="administration"', $managerHtml);

        $this->login($contractViewerId);
        $contractOnly = $this->request($app, 'GET', $detailPath);
        $contractOnlyHtml = (string) $contractOnly->getBody();
        self::assertSame(200, $contractOnly->getStatusCode());
        self::assertStringContainsString('Arrendamiento', $contractOnlyHtml);
        self::assertStringNotContainsString('Cargos del arrendamiento', $contractOnlyHtml);
        self::assertStringNotContainsString('75.25', $contractOnlyHtml);
        self::assertStringNotContainsString('/admin/leases/' . $fixture['lease_id'] . '/charges', $contractOnlyHtml);
    }

    public function testFinanceOnlyManagerCanFollowPortfolioAndMutationRedirectsSafely(): void
    {
        $fixture = $this->createLease('Finance Workflow Tenant', 'Finance Workflow Property', 'FIN-WORKFLOW');
        $this->insertCharge(
            $fixture['lease_id'], 'rent', '2026-10-01', '2026-10-05', '1500000.00', 'Existing rent',
        );
        $managerId = $this->insertUser(
            'Finance Only Manager', 'finance-only-manager@example.com', 'gestor', ['finance.view', 'finance.manage'],
        );
        $app = $this->app('/sub');
        $contractPath = '/sub/admin/contracts/' . $fixture['contract_id'];

        $this->login($managerId);
        $portfolio = $this->request($app, 'GET', '/sub/admin/finance');
        self::assertSame(200, $portfolio->getStatusCode());
        self::assertStringContainsString('href="' . $contractPath . '"', (string) $portfolio->getBody());

        $detail = $this->request($app, 'GET', $contractPath);
        self::assertSame(200, $detail->getStatusCode());
        $detailHtml = (string) $detail->getBody();
        self::assertStringContainsString('Cargos del arrendamiento', $detailHtml);
        self::assertStringNotContainsString('Plantilla:', $detailHtml);
        self::assertStringNotContainsString('Resumen', $detailHtml);

        $manual = $this->request($app, 'POST', '/sub/admin/leases/' . $fixture['lease_id'] . '/charges', [
            'type' => 'service',
            'amount' => '25.00',
            'due_date' => '2026-10-20',
            'description' => 'Finance-only manual service',
        ]);
        self::assertSame(303, $manual->getStatusCode());
        self::assertSame($contractPath, $manual->getHeaderLine('Location'));
        $manualDetail = $this->request($app, 'GET', $manual->getHeaderLine('Location'));
        self::assertSame(200, $manualDetail->getStatusCode());
        self::assertStringContainsString('Finance-only manual service', (string) $manualDetail->getBody());

        $monthly = $this->request($app, 'POST', '/sub/admin/leases/' . $fixture['lease_id'] . '/charges/monthly', [
            'period_month' => '2026-10-01',
            'due_date' => '2026-10-30',
        ]);
        self::assertSame(303, $monthly->getStatusCode());
        self::assertSame($contractPath, $monthly->getHeaderLine('Location'));
        self::assertSame(200, $this->request($app, 'GET', $monthly->getHeaderLine('Location'))->getStatusCode());

        $chargeId = (int) $this->pdo->query(
            "SELECT id FROM charges WHERE lease_id = " . $fixture['lease_id'] . " AND type = 'rent' ORDER BY id LIMIT 1",
        )->fetchColumn();
        self::assertGreaterThan(0, $chargeId);
        $void = $this->request($app, 'POST', '/sub/admin/charges/' . $chargeId . '/void', [
            'reason' => 'Finance-only correction',
        ]);
        self::assertSame(303, $void->getStatusCode());
        self::assertSame($contractPath, $void->getHeaderLine('Location'));
        $voidDetail = $this->request($app, 'GET', $void->getHeaderLine('Location'));
        self::assertSame(200, $voidDetail->getStatusCode());
        self::assertStringContainsString('voided', (string) $voidDetail->getBody());
        self::assertStringNotContainsString('Finance-only correction', (string) $voidDetail->getBody());

        self::assertSame(
            403,
            $this->request($app, 'GET', $contractPath . '/pdf')->getStatusCode(),
            'Finance permission must not grant access to the contract PDF.',
        );
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
            'pdo' => $this->pdo,
        ]);
    }

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        bool $withCsrf = true,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://alquilapro.example' . $path);
        if (strtoupper($method) === 'POST' && $withCsrf) {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
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

    /** @return array{lease_id:int,contract_id:int,tenant_id:int,unit_id:int} */
    private function createLease(
        string $tenantName = 'Tenant Finance',
        string $propertyName = 'Property Finance',
        string $unitCode = 'FIN-101',
    ): array {
        $suffix = bin2hex(random_bytes(5));
        $tenantId = $this->insertUser($tenantName, 'tenant-' . $suffix . '@example.com', 'tenant', []);
        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city) VALUES (:name, :address, :city)');
        $property->execute(['name' => $propertyName, 'address' => 'Calle Finanzas 1', 'city' => 'Bogotá']);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) '
            . 'VALUES (:property_id, :public_key, :type, :code, :status)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'type' => 'apartment',
            'code' => $unitCode,
            'status' => 'occupied',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates '
            . '(unit_id, document_type, document_number, full_name, phone, email, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :status, '
            . ':token_hash, :expires_at, :consent_at)',
        );
        $candidate->execute([
            'unit_id' => $unitId,
            'document_type' => 'cc',
            'document_number' => 'DOC-' . strtoupper($suffix),
            'full_name' => $tenantName,
            'phone' => '3001234567',
            'email' => 'candidate-' . $suffix . '@example.com',
            'status' => 'approved',
            'token_hash' => hash('sha256', 'token-' . $suffix),
            'expires_at' => '2027-01-01 00:00:00',
            'consent_at' => '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type) VALUES (:name, :type)');
        $template->execute(['name' => 'Finance Template ' . $suffix, 'type' => 'urban_housing']);
        $templateId = (int) $this->pdo->lastInsertId();
        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) '
            . 'VALUES (:template_id, 1, :body, :variables)',
        );
        $version->execute(['template_id' => $templateId, 'body' => 'Contrato', 'variables' => '[]']);
        $versionId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts '
            . '(candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, '
            . 'administration_fee, start_date, end_date, duration_text) '
            . 'VALUES (:candidate_id, :unit_id, :version_id, :type, :status, :rent, :fee, :start, :end, :duration)',
        );
        $contract->execute([
            'candidate_id' => $candidateId,
            'unit_id' => $unitId,
            'version_id' => $versionId,
            'type' => 'urban_housing',
            'status' => 'finalized',
            'rent' => '1500000.00',
            'fee' => '200000.00',
            'start' => '2026-10-01',
            'end' => '2027-09-30',
            'duration' => 'Doce meses',
        ]);
        $contractId = (int) $this->pdo->lastInsertId();

        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_at) VALUES (:contract_id, :user_id, :activated_at)',
        );
        $lease->execute([
            'contract_id' => $contractId,
            'user_id' => $tenantId,
            'activated_at' => '2026-10-01 12:00:00',
        ]);

        return [
            'lease_id' => (int) $this->pdo->lastInsertId(),
            'contract_id' => $contractId,
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
        ];
    }

    private function insertCharge(
        int $leaseId,
        string $type,
        ?string $periodMonth,
        string $dueDate,
        string $amount,
        string $description,
        ?string $voidedAt = null,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO charges '
            . '(lease_id, type, period_month, due_date, amount, description, voided_by_user_id, voided_at, void_reason) '
            . 'VALUES (:lease_id, :type, :period, :due, :amount, :description, :voided_by, :voided_at, :reason)',
        );
        $statement->execute([
            'lease_id' => $leaseId,
            'type' => $type,
            'period' => $periodMonth,
            'due' => $dueDate,
            'amount' => $amount,
            'description' => $description,
            'voided_by' => $voidedAt === null ? null : 1,
            'voided_at' => $voidedAt,
            'reason' => $voidedAt === null ? null : 'Internal void reason',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function chargeCount(int $leaseId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM charges WHERE lease_id = :lease_id');
        $statement->execute(['lease_id' => $leaseId]);

        return (int) $statement->fetchColumn();
    }
}
