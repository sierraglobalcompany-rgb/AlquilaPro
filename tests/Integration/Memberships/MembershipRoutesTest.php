<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Memberships;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Memberships\MembershipService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class MembershipRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private int $ownerId;
    private int $tenantId;
    private int $leaseId;

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
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', str_replace('`', '``', (string) $table)));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_membership_routes_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-14T10:00:00-05:00',
        ]);

        $this->ownerId = $this->insertUser('membership-owner@example.test', 'owner');
        $this->tenantId = $this->insertUser('membership-tenant@example.test', 'tenant');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_at) VALUES (:contract, :tenant, :activated_at)',
        );
        $lease->execute([
            'contract' => random_int(1_000_000, 2_000_000_000),
            'tenant' => $this->tenantId,
            'activated_at' => '2026-10-01 12:00:00',
        ]);
        $this->leaseId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testPermissionsAndStaffCatalogAndDecisionRoutes(): void
    {
        self::assertSame(
            ['memberships.view', 'memberships.manage'],
            Permissions::sanitize(['memberships.view', 'memberships.manage', 'memberships.delete', 'memberships.view']),
        );
        self::assertTrue(Permissions::allows(['role' => 'owner', 'permissions' => []], 'memberships.manage'));
        self::assertTrue(Permissions::allows(['role' => 'admin', 'permissions' => ['memberships.view']], 'memberships.view'));
        self::assertFalse(Permissions::allows(['role' => 'admin', 'permissions' => ['memberships.view']], 'memberships.manage'));
        self::assertFalse(Permissions::allows(
            ['role' => 'tenant', 'permissions' => ['memberships.view', 'memberships.manage']],
            'memberships.view',
        ));

        $viewerEmail = 'membership-viewer@example.test';
        $managerEmail = 'membership-manager@example.test';
        $tenantEmail = 'membership-tenant@example.test';
        $this->insertUser($viewerEmail, 'admin', ['memberships.view']);
        $this->insertUser($managerEmail, 'admin', ['memberships.manage']);
        $this->insertUser('membership-forced@example.test', 'admin', ['memberships.view'], true);

        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, $viewerEmail, 'MembershipPassword123');
        self::assertSame(200, $this->request($viewerApp, 'GET', '/sub/admin/memberships')->getStatusCode());
        self::assertSame(403, $this->request($viewerApp, 'POST', '/sub/admin/memberships/services', [
            'name' => 'Bloqueado', 'description' => 'Sin permiso', 'current_price' => '10', 'duration_days' => '30',
        ])->getStatusCode());
        self::assertSame(403, $this->request($viewerApp, 'POST', '/sub/admin/memberships/1/decision', [
            'decision' => 'skip',
        ])->getStatusCode());
        self::assertSame(403, $this->request($viewerApp, 'POST', '/sub/admin/memberships/services', [
            'name' => 'Sin CSRF', 'description' => 'Bloqueado', 'current_price' => '1', 'duration_days' => '1',
        ], false)->getStatusCode());

        $_SESSION = [];
        $forcedApp = $this->app('/sub');
        $forcedLogin = $this->request($forcedApp, 'POST', '/sub/login', [
            'email' => 'membership-forced@example.test', 'password' => 'MembershipPassword123',
        ]);
        self::assertSame(303, $forcedLogin->getStatusCode());
        self::assertSame('/sub/account/password', $forcedLogin->getHeaderLine('Location'));
        $forcedRoute = $this->request(
            $forcedApp,
            'GET',
            '/sub/admin/memberships',
        );
        self::assertSame(302, $forcedRoute->getStatusCode());
        self::assertSame('/sub/account/password', $forcedRoute->getHeaderLine('Location'));

        $_SESSION = [];
        $managerApp = $this->app('/sub');
        $this->login($managerApp, $managerEmail, 'MembershipPassword123');
        self::assertSame(403, $this->request($managerApp, 'GET', '/sub/admin/memberships')->getStatusCode());
        $created = $this->request($managerApp, 'POST', '/sub/admin/memberships/services', [
            'name' => 'Asistencia digital',
            'description' => 'Soporte para el arrendamiento',
            'current_price' => '35.50',
            'duration_days' => '10',
        ]);
        self::assertSame(303, $created->getStatusCode());
        self::assertSame('/sub/admin/memberships', $created->getHeaderLine('Location'));
        $serviceId = (int) $this->pdo->query('SELECT MAX(id) FROM digital_services')->fetchColumn();
        self::assertGreaterThan(0, $serviceId);
        self::assertSame(303, $this->request($managerApp, 'POST', '/sub/admin/memberships/services/' . $serviceId, [
            'name' => 'Asistencia digital',
            'description' => 'Soporte actualizado',
            'current_price' => '35.50',
            'duration_days' => '10',
            'active' => '1',
        ])->getStatusCode());
        self::assertSame('Soporte actualizado', $this->pdo->query(
            'SELECT description FROM digital_services WHERE id = ' . $serviceId,
        )->fetchColumn());
        self::assertSame(422, $this->request($managerApp, 'POST', '/sub/admin/memberships/' . $serviceId . '/decision', [
            'decision' => 'skip',
        ])->getStatusCode());

        $_SESSION = [];
        $tenantApp = $this->app('/sub');
        $this->login($tenantApp, $tenantEmail, 'MembershipPassword123');
        self::assertSame(403, $this->request($tenantApp, 'GET', '/sub/admin/memberships')->getStatusCode());
        self::assertSame(403, $this->request($tenantApp, 'POST', '/sub/admin/memberships/services', [
            'name' => 'Bloqueado', 'description' => 'Tenant', 'current_price' => '1', 'duration_days' => '1',
        ])->getStatusCode());

        $membership = $this->service()->accept(
            $serviceId, $this->leaseId, $this->tenantId, '35.50', 10, '2026-10-14', 1_800_000_010,
        );
        $_SESSION = [];
        $ownerApp = $this->app('/sub');
        $this->login($ownerApp, 'membership-owner@example.test', 'MembershipPassword123');
        $page = $this->request($ownerApp, 'GET', '/sub/admin/memberships');
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Asistencia digital', (string) $page->getBody());
        self::assertStringContainsString('Crear cargo', (string) $page->getBody());
        self::assertStringContainsString('No crear cargo', (string) $page->getBody());
        self::assertSame(303, $this->request($ownerApp, 'POST', '/sub/admin/memberships/' . $membership['id'] . '/decision', [
            'decision' => 'create',
            'due_date' => '2026-10-20',
            'description' => 'Servicio adicional',
        ])->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
        self::assertSame('create', $this->service()->findMembership((int) $membership['id'])['charge_decision']);
    }

    private function app(string $basePath): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        return $factory([
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
            'pdo' => $this->pdo,
        ]);
    }

    private function service(): MembershipService
    {
        return new MembershipService(
            $this->pdo,
            new FinanceService($this->pdo, new PaymentMethodService($this->pdo, sys_get_temp_dir()), sys_get_temp_dir()),
        );
    }

    private function request(App $app, string $method, string $path, array $body = [], bool $includeCsrf = true): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://alquilapro.sierraglobalcompany.com' . $path);
        if (strtoupper($method) === 'POST' && $includeCsrf) {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }
        return $app->handle($request);
    }

    private function login(App $app, string $email, string $password): void
    {
        $response = $this->request($app, 'POST', '/sub/login', ['email' => $email, 'password' => $password]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/sub/', $response->getHeaderLine('Location'));
    }

    private function insertUser(string $email, string $role, array $permissions = [], bool $mustChangePassword = false): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :name, :email, :hash, 1, :permissions, :must_change)',
        );
        $statement->execute([
            'role' => $role,
            'name' => 'Membership Route User',
            'email' => $email,
            'hash' => password_hash('MembershipPassword123', PASSWORD_DEFAULT),
            'permissions' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change' => $mustChangePassword ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
