<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\UI;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class DashboardTest extends TestCase
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
            $safeTable = str_replace(chr(96), chr(96) . chr(96), (string) $table);
            $quotedTable = chr(96) . $safeTable . chr(96);
            $this->pdo->exec('DROP TABLE IF EXISTS ' . $quotedTable);
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_dashboard_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-15T09:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        foreach (glob($this->directory . '/branding/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory . '/branding');
        @rmdir($this->directory);
    }

    public function testDashboardQuickActionsRespectPermissionsWithoutInventedMetrics(): void
    {
        $this->insertUser(
            'dashboard-viewer@example.com',
            'ViewerPassword123',
            'gestor',
            ['properties.view', 'candidates.view'],
            'Vista parcial',
        );
        $app = $this->app();
        $this->login($app, 'dashboard-viewer@example.com', 'ViewerPassword123');

        $body = (string) $this->request($app, 'GET', '/')->getBody();

        self::assertStringContainsString('dashboard-actions', $body);
        self::assertStringContainsString('class="app-card dashboard-action" href="/admin/properties"', $body);
        self::assertStringContainsString('class="app-card dashboard-action" href="/admin/candidates"', $body);
        self::assertStringNotContainsString('class="app-card dashboard-action" href="/admin/finance"', $body);
        self::assertStringNotContainsString('class="app-card dashboard-action" href="/admin/maintenance"', $body);
        self::assertStringNotContainsString('data-dashboard-metric', $body);
        self::assertStringNotContainsString('tasa de conversión', mb_strtolower($body));
        self::assertStringNotContainsString('100%', $body);

        $_SESSION = [];
        $this->insertUser('dashboard-restricted@example.com', 'RestrictedPass123', 'gestor', [], 'Sin permisos');
        $restrictedApp = $this->app();
        $this->login($restrictedApp, 'dashboard-restricted@example.com', 'RestrictedPass123');
        $restrictedBody = (string) $this->request($restrictedApp, 'GET', '/')->getBody();

        self::assertSame(0, substr_count($restrictedBody, 'class="dashboard-action"'));
        self::assertStringContainsString('No hay acciones rápidas disponibles para tu cuenta.', $restrictedBody);
    }

    public function testDashboardShowsOnlyRealPermissionScopedAttentionSignals(): void
    {
        $this->insertOperationalSignals();
        $this->insertUser(
            'dashboard-ops@example.com',
            'OperationalPass123',
            'gestor',
            ['finance.view', 'maintenance.view'],
            'Operaciones',
        );
        $app = $this->app();
        $this->login($app, 'dashboard-ops@example.com', 'OperationalPass123');

        $body = (string) $this->request($app, 'GET', '/')->getBody();

        self::assertStringContainsString('Atención requerida', $body);
        self::assertStringContainsString('data-dashboard-attention', $body);
        self::assertStringContainsString('data-dashboard-signal="finance"', $body);
        self::assertStringContainsString('1 pago por conciliar', $body);
        self::assertStringContainsString('href="/admin/finance"', $body);
        self::assertStringContainsString('data-dashboard-signal="maintenance"', $body);
        self::assertStringContainsString('2 solicitudes de mantenimiento abiertas', $body);
        self::assertStringContainsString('href="/admin/maintenance"', $body);
        self::assertStringNotContainsString('data-dashboard-metric', $body);

        $_SESSION = [];
        $this->insertUser(
            'dashboard-no-ops@example.com',
            'NoOperationalPass123',
            'gestor',
            ['properties.view'],
            'Sin señales operativas',
        );
        $restrictedApp = $this->app();
        $this->login($restrictedApp, 'dashboard-no-ops@example.com', 'NoOperationalPass123');
        $restrictedBody = (string) $this->request($restrictedApp, 'GET', '/')->getBody();

        self::assertStringNotContainsString('data-dashboard-signal="finance"', $restrictedBody);
        self::assertStringNotContainsString('data-dashboard-signal="maintenance"', $restrictedBody);
        self::assertStringNotContainsString('1 pago por conciliar', $restrictedBody);
        self::assertStringNotContainsString('2 solicitudes de mantenimiento abiertas', $restrictedBody);
    }

    public function testDashboardUsesScalarOwnerCountsInsteadOfHydratingQueues(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = (string) file_get_contents($root . '/app/Properties/routes.php');
        $finance = (string) file_get_contents($root . '/app/Finance/FinanceService.php');
        $maintenance = (string) file_get_contents($root . '/app/Maintenance/MaintenanceService.php');

        self::assertStringContainsString('$finance->countPendingPayments()', $routes);
        self::assertStringNotContainsString('count($finance->listPendingPayments())', $routes);
        self::assertStringContainsString('public function countPendingPayments(): int', $finance);

        self::assertStringContainsString('$maintenance->countActionableRequests()', $routes);
        self::assertStringNotContainsString("count($maintenance->listForStaff('open'))", $routes);
        self::assertStringNotContainsString("count($maintenance->listForStaff('in_progress'))", $routes);
        self::assertStringContainsString('public function countActionableRequests(): int', $maintenance);
    }

    private function insertOperationalSignals(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $tenantId = $this->insertUser(
            'tenant-' . $suffix . '@example.com',
            'TenantPassword123',
            'tenant',
            [],
            'Inquilino Dashboard',
        );
        $actorId = $this->insertUser(
            'actor-' . $suffix . '@example.com',
            'ActorPassword123',
            'admin',
            [],
            'Actor Dashboard',
        );

        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city) VALUES (?, ?, ?)');
        $property->execute(['Propiedad Dashboard', 'Calle 1', 'Medellín']);
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
            $unitId,
            'cc',
            'DOC-' . $suffix,
            'Candidato Dashboard',
            '3001234567',
            'candidate-' . $suffix . '@example.com',
            'approved',
            hash('sha256', 'candidate-' . $suffix),
            '2027-01-01 00:00:00',
            '2026-10-16 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type) VALUES (?, ?)');
        $template->execute(['Plantilla Dashboard ' . $suffix, 'urban_housing']);
        $templateId = (int) $this->pdo->lastInsertId();

        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) '
            . 'VALUES (?, 1, ?, ?)',
        );
        $version->execute([$templateId, 'Contrato dashboard', '[]']);
        $versionId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts '
            . '(candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, start_date, end_date, duration_text) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $contract->execute([
            $candidateId,
            $unitId,
            $versionId,
            'urban_housing',
            'finalized',
            '1200000.00',
            '2026-01-01',
            '2026-12-31',
            '12 meses',
        ]);
        $contractId = (int) $this->pdo->lastInsertId();

        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) VALUES (?, ?, ?, ?)',
        );
        $lease->execute([$contractId, $tenantId, $actorId, '2026-01-01 10:00:00']);
        $leaseId = (int) $this->pdo->lastInsertId();

        $paymentMethod = $this->pdo->prepare(
            'INSERT INTO payment_methods (property_id, type, label, recipient_name) VALUES (?, ?, ?, ?)',
        );
        $paymentMethod->execute([$propertyId, 'bank_transfer', 'Cuenta Dashboard', 'Sierra Global']);
        $paymentMethodId = (int) $this->pdo->lastInsertId();

        $pendingPayment = $this->pdo->prepare(
            'INSERT INTO payments '
            . '(lease_id, payment_method_id, amount, paid_on, payment_method_snapshot_json) VALUES (?, ?, ?, ?, ?)',
        );
        $pendingPayment->execute([
            $leaseId,
            $paymentMethodId,
            '100000.00',
            '2026-10-15',
            json_encode(['type' => 'bank_transfer', 'label' => 'Cuenta Dashboard'], JSON_THROW_ON_ERROR),
        ]);

        $reconciledPayment = $this->pdo->prepare(
            'INSERT INTO payments '
            . '(lease_id, payment_method_id, amount, paid_on, payment_method_snapshot_json, reconciled_by_user_id, reconciled_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        $reconciledPayment->execute([
            $leaseId,
            $paymentMethodId,
            '50000.00',
            '2026-10-14',
            json_encode(['type' => 'bank_transfer', 'label' => 'Cuenta Dashboard'], JSON_THROW_ON_ERROR),
            $actorId,
            '2026-10-15 10:00:00',
        ]);

        $maintenance = $this->pdo->prepare(
            'INSERT INTO maintenance_requests '
            . '(lease_id, category, priority, description, photos_json, status, created_by_user_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        $maintenance->execute([$leaseId, 'Plomería', 'urgent', 'Fuga activa', '[]', 'open', $actorId]);
        $maintenance->execute([$leaseId, 'Electricidad', 'high', 'Revisión requerida', '[]', 'in_progress', $actorId]);
        $maintenance->execute([$leaseId, 'Pintura', 'low', 'Caso cerrado', '[]', 'resolved', $actorId]);
    }

    private function app(string $basePath = ''): App
    {
        $brandingDirectory = $this->directory . '/branding';
        if (!is_dir($brandingDirectory)) {
            mkdir($brandingDirectory, 0700, true);
        }

        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com' . $basePath,
            'base_path' => $basePath,
            'logo_directory' => $brandingDirectory,
            'pdo' => $this->pdo,
        ]);
    }

    private function login(App $app, string $email, string $password): void
    {
        $response = $this->request($app, 'POST', '/login', [
            'email' => $email,
            'password' => $password,
        ]);

        self::assertSame(303, $response->getStatusCode());
    }

    private function request(App $app, string $method, string $path, array $body = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );

        if (strtoupper($method) === 'POST') {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }

        return $app->handle($request);
    }

    private function insertUser(
        string $email,
        string $password,
        string $role,
        array $permissions,
        string $name,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, 0)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => $name,
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
