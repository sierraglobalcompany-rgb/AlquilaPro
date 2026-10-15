<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Leases;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\UserService;
use AlquilaPro\Community\CommunityService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\ObligationService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Insurance\InsuranceService;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\Leases\TerminationService;
use AlquilaPro\Memberships\MembershipService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class TerminationRoutesTest extends TestCase
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
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
        $this->resetDatabase();

        $this->directory = sys_get_temp_dir() . '/alquilapro-f21-routes-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-14T10:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach (['branding', 'unit-media', 'candidate-documents', 'contracts', 'payment-method-qrs', 'payment-receipts', 'obligation-supports', 'insurance-documents', 'maintenance-photos', 'documents'] as $name) {
            foreach (glob($this->directory . '/' . $name . '/*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($this->directory . '/' . $name);
        }
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testTerminationRoutesAreRegisteredInsideExistingLeaseSurface(): void
    {
        $contracts = $this->routeContracts($this->app('/sub'));
        foreach ([
            'GET /admin/leases/{id:[0-9]+}/termination',
            'POST /admin/leases/{id:[0-9]+}/bonus',
            'POST /admin/leases/{id:[0-9]+}/bonus/decision',
            'POST /admin/leases/{id:[0-9]+}/liquidation',
            'GET /tenant/leases/{id:[0-9]+}/liquidation',
            'POST /admin/leases/{id:[0-9]+}/end',
        ] as $contract) {
            self::assertContains($contract, $contracts);
        }
    }

    public function testAdminTerminationReadUsesContractsViewPasswordGateAndFinanceRedaction(): void
    {
        $this->requireTerminationRoutes();
        $actorId = $this->insertUser('fixture-owner@example.com', 'OwnerPassword123', 'owner');
        $tenantId = $this->insertUser('route-tenant@example.com', 'TenantPassword123', 'tenant');
        $lease = $this->createLease($tenantId, $actorId);
        $this->insertCharge($lease['lease_id'], $actorId, '98765.43', 'FINANCE-SECRET-F21');

        $guest = $this->request($this->app('/sub'), 'GET', '/sub/admin/leases/' . $lease['lease_id'] . '/termination');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $_SESSION = [];
        $this->insertUser('no-view@example.com', 'StaffPassword123', 'admin', []);
        $noViewApp = $this->app('/sub');
        $this->login($noViewApp, 'no-view@example.com', 'StaffPassword123', '/sub');
        self::assertSame(403, $this->request(
            $noViewApp,
            'GET',
            '/sub/admin/leases/' . $lease['lease_id'] . '/termination',
        )->getStatusCode());

        $_SESSION = [];
        $this->insertUser('forced-view@example.com', 'StaffPassword123', 'admin', ['contracts.view'], true);
        $forcedApp = $this->app('/sub');
        $this->login($forcedApp, 'forced-view@example.com', 'StaffPassword123', '/sub', '/sub/account/password');
        $forced = $this->request($forcedApp, 'GET', '/sub/admin/leases/' . $lease['lease_id'] . '/termination');
        self::assertSame(302, $forced->getStatusCode());
        self::assertSame('/sub/account/password', $forced->getHeaderLine('Location'));

        $_SESSION = [];
        $this->insertUser('contract-view@example.com', 'StaffPassword123', 'admin', ['contracts.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'contract-view@example.com', 'StaffPassword123', '/sub');
        $redacted = $this->request($viewerApp, 'GET', '/sub/admin/leases/' . $lease['lease_id'] . '/termination');
        self::assertSame(200, $redacted->getStatusCode());
        $redactedHtml = (string) $redacted->getBody();
        self::assertStringContainsString('Liquidación contractual', $redactedHtml);
        self::assertStringNotContainsString('FINANCE-SECRET-F21', $redactedHtml);
        self::assertStringNotContainsString('98765.43', $redactedHtml);

        $_SESSION = [];
        $this->insertUser(
            'finance-view@example.com',
            'StaffPassword123',
            'admin',
            ['contracts.view', 'finance.view'],
        );
        $financeApp = $this->app('/sub');
        $this->login($financeApp, 'finance-view@example.com', 'StaffPassword123', '/sub');
        $full = $this->request($financeApp, 'GET', '/sub/admin/leases/' . $lease['lease_id'] . '/termination');
        self::assertSame(200, $full->getStatusCode());
        $fullHtml = (string) $full->getBody();
        self::assertStringContainsString('FINANCE-SECRET-F21', $fullHtml);
        self::assertStringContainsString('98765.43', $fullHtml);
    }

    public function testBonusMutationsRequireManageCsrfAndExplicitFeatureEnablement(): void
    {
        $this->requireTerminationRoutes();
        $actorId = $this->insertUser('fixture-owner-2@example.com', 'OwnerPassword123', 'owner');
        $tenantId = $this->insertUser('bonus-tenant@example.com', 'TenantPassword123', 'tenant');
        $lease = $this->createLease($tenantId, $actorId);
        $this->insertUser('bonus-viewer@example.com', 'StaffPassword123', 'admin', ['contracts.view']);
        $this->insertUser(
            'bonus-manager@example.com',
            'StaffPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );

        $viewerApp = $this->app('/sub', null, false);
        $this->login($viewerApp, 'bonus-viewer@example.com', 'StaffPassword123', '/sub');
        self::assertSame(403, $this->request($viewerApp, 'POST', '/sub/admin/leases/' . $lease['lease_id'] . '/bonus', [
            'amount' => '500.00',
            'note' => 'No permitido',
        ])->getStatusCode());
        self::assertSame(0, $this->tableCount('good_conduct_bonuses'));

        $_SESSION = [];
        $disabledApp = $this->app('/sub', null, null);
        $this->login($disabledApp, 'bonus-manager@example.com', 'StaffPassword123', '/sub');
        $disabled = $this->request($disabledApp, 'POST', '/sub/admin/leases/' . $lease['lease_id'] . '/bonus', [
            'amount' => '500.00',
            'note' => 'Debe seguir apagado',
        ]);
        self::assertSame(422, $disabled->getStatusCode());
        self::assertSame(0, $this->tableCount('good_conduct_bonuses'));

        $noCsrf = $this->request(
            $disabledApp,
            'POST',
            '/sub/admin/leases/' . $lease['lease_id'] . '/bonus',
            ['amount' => '500.00'],
            false,
        );
        self::assertSame(403, $noCsrf->getStatusCode());
        self::assertSame(0, $this->tableCount('good_conduct_bonuses'));

        $_SESSION = [];
        $enabledApp = $this->app('/sub', null, true);
        $this->login($enabledApp, 'bonus-manager@example.com', 'StaffPassword123', '/sub');
        $created = $this->request($enabledApp, 'POST', '/sub/admin/leases/' . $lease['lease_id'] . '/bonus', [
            'amount' => '500.00',
            'note' => '  Bono F21  ',
        ]);
        self::assertSame(303, $created->getStatusCode());
        self::assertSame('/sub/admin/leases/' . $lease['lease_id'] . '/termination', $created->getHeaderLine('Location'));
        self::assertSame(1, $this->tableCount('good_conduct_bonuses'));

        $decided = $this->request($enabledApp, 'POST', '/sub/admin/leases/' . $lease['lease_id'] . '/bonus/decision', [
            'decision' => 'approved',
        ]);
        self::assertSame(303, $decided->getStatusCode());
        self::assertSame('approved', (string) $this->pdo->query(
            'SELECT decision FROM good_conduct_bonuses WHERE lease_id = ' . $lease['lease_id'],
        )->fetchColumn());
    }

    public function testLiquidationRequiresManageAndFinanceAndNeverEndsLeaseItself(): void
    {
        $this->requireTerminationRoutes();
        $actorId = $this->insertUser('fixture-owner-3@example.com', 'OwnerPassword123', 'owner');
        $tenantId = $this->insertUser('liquidation-tenant@example.com', 'TenantPassword123', 'tenant');
        $active = $this->createLease($tenantId, $actorId);
        $this->insertUser(
            'manage-only@example.com',
            'StaffPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $this->insertUser(
            'finalizer@example.com',
            'StaffPassword123',
            'admin',
            ['contracts.view', 'contracts.manage', 'finance.view'],
        );

        $managerApp = $this->app('/sub');
        $this->login($managerApp, 'manage-only@example.com', 'StaffPassword123', '/sub');
        self::assertSame(403, $this->request(
            $managerApp,
            'POST',
            '/sub/admin/leases/' . $active['lease_id'] . '/liquidation',
            ['property_returned' => '1', 'keys_returned_count' => '2'],
        )->getStatusCode());
        self::assertSame(0, $this->tableCount('lease_liquidations'));

        $_SESSION = [];
        $finalizerApp = $this->app('/sub');
        $this->login($finalizerApp, 'finalizer@example.com', 'StaffPassword123', '/sub');
        $activeAttempt = $this->request(
            $finalizerApp,
            'POST',
            '/sub/admin/leases/' . $active['lease_id'] . '/liquidation',
            ['property_returned' => '1', 'keys_returned_count' => '2'],
        );
        self::assertSame(422, $activeAttempt->getStatusCode());
        self::assertNull($this->pdo->query('SELECT ended_at FROM leases WHERE id = ' . $active['lease_id'])->fetchColumn());
        self::assertSame(0, $this->tableCount('lease_liquidations'));

        (new LeaseService($this->pdo, new UserService($this->pdo)))->end(
            $active['lease_id'],
            $actorId,
            1_800_300_010,
        );
        $finalized = $this->request(
            $finalizerApp,
            'POST',
            '/sub/admin/leases/' . $active['lease_id'] . '/liquidation',
            [
                'property_returned' => '1',
                'keys_returned_count' => '2',
                'handover_notes' => 'Entrega completa',
                'summary_note' => 'Cierre F21',
            ],
        );
        self::assertSame(303, $finalized->getStatusCode());
        self::assertSame('/sub/admin/leases/' . $active['lease_id'] . '/termination', $finalized->getHeaderLine('Location'));
        self::assertSame(1, $this->tableCount('lease_liquidations'));
    }

    public function testTenantReadsOnlyOwnFinalLiquidationAfterOwnershipResolution(): void
    {
        $this->requireTerminationRoutes();
        $actorId = $this->insertUser('fixture-owner-4@example.com', 'OwnerPassword123', 'owner');
        $tenantId = $this->insertUser('tenant-owner@example.com', 'TenantPassword123', 'tenant');
        $foreignId = $this->insertUser('tenant-foreign@example.com', 'TenantPassword123', 'tenant');
        $owned = $this->createLease($tenantId, $actorId);
        $foreign = $this->createLease($foreignId, $actorId);
        $empty = $this->createLease($tenantId, $actorId);

        $leases = new LeaseService($this->pdo, new UserService($this->pdo));
        $leases->end($owned['lease_id'], $actorId, 1_800_300_020);
        $leases->end($foreign['lease_id'], $actorId, 1_800_300_021);
        $this->terminationService($this->pdo, false)->finalizeLiquidation(
            $owned['lease_id'],
            ['property_returned' => true, 'keys_returned_count' => 2, 'notes' => 'Entrega segura'],
            'Liquidación visible al inquilino',
            $actorId,
            '2026-10-14',
            1_800_300_022,
        );
        $this->terminationService($this->pdo, false)->finalizeLiquidation(
            $foreign['lease_id'],
            [],
            'LIQUIDACION-AJENA-SECRETA',
            $actorId,
            '2026-10-14',
            1_800_300_023,
        );

        $tracedPdo = $this->tracedPdo();
        $app = $this->app('/sub', $tracedPdo);
        $this->login($app, 'tenant-owner@example.com', 'TenantPassword123', '/sub');

        $own = $this->request($app, 'GET', '/sub/tenant/leases/' . $owned['lease_id'] . '/liquidation');
        self::assertSame(200, $own->getStatusCode());
        $html = (string) $own->getBody();
        self::assertStringContainsString('Liquidación visible al inquilino', $html);
        self::assertStringContainsString('Entrega segura', $html);
        self::assertStringNotContainsString('finalized_by_user_id', $html);
        self::assertStringNotContainsString('created_by_user_id', $html);
        self::assertStringNotContainsString('decided_by_user_id', $html);
        self::assertStringNotContainsString('finalized_context', $html);
        self::assertStringNotContainsString('LIQUIDACION-AJENA-SECRETA', $html);

        $tracedPdo->preparedSql = [];
        $foreignResponse = $this->request(
            $app,
            'GET',
            '/sub/tenant/leases/' . $foreign['lease_id'] . '/liquidation',
        );
        self::assertSame(404, $foreignResponse->getStatusCode());
        $queries = array_map('strtolower', $tracedPdo->preparedSql);
        self::assertNotFalse(array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from leases'),
            $queries,
        ), true));
        self::assertSame([], array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'from lease_liquidations'),
        )));

        self::assertSame(404, $this->request(
            $app,
            'GET',
            '/sub/tenant/leases/' . $empty['lease_id'] . '/liquidation',
        )->getStatusCode());
    }

    public function testContractAndTenantDetailsLinkToCanonicalTerminationSurfaces(): void
    {
        $this->requireTerminationRoutes();
        $actorId = $this->insertUser('fixture-owner-5@example.com', 'OwnerPassword123', 'owner');
        $tenantId = $this->insertUser('link-tenant@example.com', 'TenantPassword123', 'tenant');
        $lease = $this->createLease($tenantId, $actorId);
        $this->insertUser('link-viewer@example.com', 'StaffPassword123', 'admin', ['contracts.view']);

        $staffApp = $this->app('/sub');
        $this->login($staffApp, 'link-viewer@example.com', 'StaffPassword123', '/sub');
        $contractHtml = (string) $this->request(
            $staffApp,
            'GET',
            '/sub/admin/contracts/' . $lease['contract_id'],
        )->getBody();
        self::assertStringContainsString('/sub/admin/leases/' . $lease['lease_id'] . '/termination', $contractHtml);

        (new LeaseService($this->pdo, new UserService($this->pdo)))->end($lease['lease_id'], $actorId, 1_800_300_030);
        $this->terminationService($this->pdo, false)->finalizeLiquidation(
            $lease['lease_id'],
            [],
            null,
            $actorId,
            '2026-10-14',
            1_800_300_031,
        );

        $_SESSION = [];
        $tenantApp = $this->app('/sub');
        $this->login($tenantApp, 'link-tenant@example.com', 'TenantPassword123', '/sub');
        $tenantHtml = (string) $this->request(
            $tenantApp,
            'GET',
            '/sub/tenant/leases/' . $lease['lease_id'],
        )->getBody();
        self::assertStringContainsString('/sub/tenant/leases/' . $lease['lease_id'] . '/liquidation', $tenantHtml);
    }

    private function requireTerminationRoutes(): void
    {
        $contracts = $this->routeContracts($this->app());
        foreach ([
            'GET /admin/leases/{id:[0-9]+}/termination',
            'POST /admin/leases/{id:[0-9]+}/bonus',
            'POST /admin/leases/{id:[0-9]+}/bonus/decision',
            'POST /admin/leases/{id:[0-9]+}/liquidation',
            'GET /tenant/leases/{id:[0-9]+}/liquidation',
        ] as $contract) {
            if (!in_array($contract, $contracts, true)) {
                self::markTestSkipped('F21 Task 5 termination routes are not registered yet.');
            }
        }
    }

    /** @return list<string> */
    private function routeContracts(App $app): array
    {
        $contracts = [];
        foreach ($app->getRouteCollector()->getRoutes() as $route) {
            foreach ($route->getMethods() as $method) {
                $contracts[] = strtoupper($method) . ' ' . $route->getPattern();
            }
        }
        sort($contracts);
        return $contracts;
    }

    private function app(string $basePath = '', ?PDO $pdo = null, ?bool $bonusEnabled = null): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        $settings = [
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com' . $basePath,
            'base_path' => $basePath,
            'logo_directory' => $this->directory . '/branding',
            'unit_media_directory' => $this->directory . '/unit-media',
            'candidate_document_directory' => $this->directory . '/candidate-documents',
            'contract_directory' => $this->directory . '/contracts',
            'payment_method_qr_directory' => $this->directory . '/payment-method-qrs',
            'payment_receipt_directory' => $this->directory . '/payment-receipts',
            'obligation_support_directory' => $this->directory . '/obligation-supports',
            'insurance_document_directory' => $this->directory . '/insurance-documents',
            'maintenance_photo_directory' => $this->directory . '/maintenance-photos',
            'document_directory' => $this->directory . '/documents',
            'pdo' => $pdo ?? $this->pdo,
            'today' => static fn (): string => '2026-10-14',
        ];
        if ($bonusEnabled !== null) {
            $settings['good_conduct_bonus_enabled'] = $bonusEnabled;
        }
        return $factory($settings);
    }

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        bool $withCsrf = true,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );
        if (strtoupper($method) === 'POST' && $withCsrf) {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }
        return $app->handle($request);
    }

    private function login(
        App $app,
        string $email,
        string $password,
        string $basePath = '',
        string $expectedLocation = '/',
    ): void {
        $response = $this->request($app, 'POST', $basePath . '/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame(
            $expectedLocation === '/' ? $basePath . '/' : $expectedLocation,
            $response->getHeaderLine('Location'),
        );
    }

    private function insertUser(
        string $email,
        string $password,
        string $role,
        array $permissions = [],
        bool $mustChangePassword = false,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :name, :email, :hash, 1, :permissions, :must_change_password)',
        );
        $statement->execute([
            'role' => $role,
            'name' => 'F21 Route User',
            'email' => strtolower($email),
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{lease_id:int,contract_id:int} */
    private function createLease(int $tenantId, int $actorId): array
    {
        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio F21 ' . bin2hex(random_bytes(2)),
            'address' => 'Calle 50 # 10-20',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status, monthly_price, administration_fee, unit_number) '
            . 'VALUES (:property_id, :public_key, \'apartment\', :code, \'occupied\', \'1500000.00\', \'200000.00\', \'501\')',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'code' => 'F21-ROUTE-' . bin2hex(random_bytes(3)),
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type, is_active, created_by_user_id) '
            . 'VALUES (:name, \'urban_housing\', 1, :actor)',
        );
        $template->execute(['name' => 'Template F21 ' . bin2hex(random_bytes(3)), 'actor' => $actorId]);
        $templateId = (int) $this->pdo->lastInsertId();

        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json, created_by_user_id) '
            . 'VALUES (:template, 1, \'Contrato F21\', \'[]\', :actor)',
        );
        $version->execute(['template' => $templateId, 'actor' => $actorId]);
        $versionId = (int) $this->pdo->lastInsertId();

        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, current_address, status, continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit, \'cc\', :document, \'Tenant F21\', \'3001112233\', :email, \'Carrera 10 # 11-12\', \'pending_screening\', :token, \'2027-01-01 00:00:00\', \'2026-10-14 10:00:00\')',
        );
        $candidate->execute([
            'unit' => $unitId,
            'document' => 'F21-' . bin2hex(random_bytes(4)),
            'email' => 'candidate-' . bin2hex(random_bytes(4)) . '@example.com',
            'token' => bin2hex(random_bytes(32)),
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, start_date, end_date, duration_text, resolved_variables_json, snapshot_html, pdf_stored_name, pdf_sha256, pdf_size_bytes, created_by_user_id, finalized_by_user_id, generated_at, finalized_at) '
            . 'VALUES (:candidate, :unit, :version, \'urban_housing\', \'finalized\', \'1750000.00\', \'250000.00\', \'2026-11-01\', \'2027-10-31\', \'Doce meses\', \'{}\', \'<article>F21</article>\', :pdf_name, :pdf_hash, 1234, :created_by, :finalized_by, \'2026-10-14 10:10:00\', \'2026-10-14 10:20:00\')',
        );
        $contract->execute([
            'candidate' => $candidateId,
            'unit' => $unitId,
            'version' => $versionId,
            'pdf_name' => str_repeat('a', 32) . '.pdf',
            'pdf_hash' => str_repeat('b', 64),
            'created_by' => $actorId,
            'finalized_by' => $actorId,
        ]);
        $contractId = (int) $this->pdo->lastInsertId();

        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) '
            . 'VALUES (:contract, :tenant, :actor, \'2026-10-14 11:00:00\')',
        );
        $lease->execute(['contract' => $contractId, 'tenant' => $tenantId, 'actor' => $actorId]);
        return ['lease_id' => (int) $this->pdo->lastInsertId(), 'contract_id' => $contractId];
    }

    private function insertCharge(int $leaseId, int $actorId, string $amount, string $description): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO charges (lease_id, type, due_date, amount, description, created_by_user_id) '
            . 'VALUES (:lease, \'service\', \'2026-10-10\', :amount, :description, :actor)',
        );
        $statement->execute([
            'lease' => $leaseId,
            'amount' => $amount,
            'description' => $description,
            'actor' => $actorId,
        ]);
    }

    private function terminationService(PDO $pdo, bool $bonusEnabled): TerminationService
    {
        $paymentMethods = new PaymentMethodService($pdo, $this->directory . '/payment-method-qrs');
        $finance = new FinanceService($pdo, $paymentMethods, $this->directory . '/payment-receipts');
        return new TerminationService(
            $pdo,
            new LeaseService($pdo, new UserService($pdo)),
            $finance,
            new ObligationService($pdo, $finance, $this->directory . '/obligation-supports'),
            new InsuranceService($pdo, $this->directory . '/insurance-documents'),
            new DocumentService($pdo, $this->directory . '/documents'),
            new CommunityService($pdo),
            new MembershipService($pdo, $finance),
            $bonusEnabled,
        );
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

    private function tableCount(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }

    private function resetDatabase(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'lease_liquidations', 'good_conduct_bonuses', 'payment_allocations', 'payments', 'service_memberships',
            'digital_services', 'lease_obligations', 'insurance_claims', 'insurance_policies', 'maintenance_visits',
            'maintenance_requests', 'community_incidents', 'documents', 'charges', 'payment_methods', 'leases',
            'contracts', 'contract_template_versions', 'contract_templates', 'screening_processes', 'candidate_documents',
            'unit_application_requirements', 'candidate_references', 'candidates', 'unit_media', 'units', 'properties',
            'settings', 'users',
        ] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}