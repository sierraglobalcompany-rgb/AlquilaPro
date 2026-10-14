<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Notifications;

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

final class WhatsAppRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private int $ownerId;
    private int $allowedStaffId;
    private int $candidateId;
    private int $leaseId;
    private int $maintenanceRequestId;

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

        $this->directory = sys_get_temp_dir() . '/alquila_whatsapp_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-14T00:00:00-05:00',
        ]);

        $this->ownerId = $this->insertUser('owner@example.test', 'OwnerPassword123!', 'owner');
        $this->allowedStaffId = $this->insertUser('whatsapp-staff@example.test', 'StaffPassword123!', 'admin', ['whatsapp.use']);
        $this->insertUser('whatsapp-denied@example.test', 'DeniedPassword123!', 'admin');
        $this->insertUser('whatsapp-tenant@example.test', 'TenantPassword123!', 'tenant', ['whatsapp.use']);
        $this->insertUser('whatsapp-forced@example.test', 'ForcedPassword123!', 'admin', ['whatsapp.use'], true);
        $this->seedContexts();
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

    public function testOwnerCanPrepareAndOpenSnapshotWithoutForgingActorOrRecipient(): void
    {
        $basePath = '/portal';
        $app = $this->app($basePath);
        $this->login($app, 'owner@example.test', 'OwnerPassword123!', $basePath);

        $response = $this->request($app, 'POST', $basePath . '/admin/whatsapp', [
            'context_type' => 'candidate',
            'context_id' => (string) $this->candidateId,
            'template_key' => 'candidate_follow_up',
            'recipient_phone' => '+573009999999',
            'created_by_user_id' => (string) $this->allowedStaffId,
        ]);

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('Historial de acciones', $body);
        self::assertStringContainsString('Hola Ana &lt;img src=x&gt;', $body);
        self::assertStringNotContainsString('<img src=x>', $body);

        $action = $this->latestAction();
        self::assertSame($this->ownerId, (int) $action['created_by_user_id']);
        self::assertSame('573001234567', $action['recipient_phone_snapshot']);
        self::assertSame('candidate_follow_up', $action['template_key']);
        self::assertStringContainsString('Hola Ana <img src=x>', $action['message_snapshot']);
        self::assertStringStartsWith('https://wa.me/573001234567?text=', $action['url_snapshot']);
        self::assertNull($action['opened_at']);
        self::assertStringContainsString(
            'action="/portal/admin/whatsapp/' . (int) $action['id'] . '/open"',
            $body,
        );

        $opened = $this->request($app, 'POST', $basePath . '/admin/whatsapp/' . (int) $action['id'] . '/open', [
            'opened_by_user_id' => (string) $this->allowedStaffId,
        ]);
        self::assertSame(303, $opened->getStatusCode());
        self::assertSame($action['url_snapshot'], $opened->getHeaderLine('Location'));
        $saved = $this->action((int) $action['id']);
        self::assertSame($this->ownerId, (int) $saved['opened_by_user_id']);
        self::assertNotNull($saved['opened_at']);
    }

    public function testLeaseAndMaintenanceContextsUseTheirOwnExistingRecords(): void
    {
        $app = $this->app();
        $this->login($app, 'whatsapp-staff@example.test', 'StaffPassword123!');
        $cases = [
            ['lease', $this->leaseId, 'lease_contact', 'Hola Ana <img src=x>, nos comunicamos contigo'],
            ['maintenance_request', $this->maintenanceRequestId, 'maintenance_follow_up', 'solicitud de mantenimiento de plomería'],
        ];
        foreach ($cases as [$contextType, $contextId, $templateKey, $messagePart]) {
            $response = $this->request($app, 'POST', '/admin/whatsapp', [
                'context_type' => $contextType,
                'context_id' => (string) $contextId,
                'template_key' => $templateKey,
            ]);
            self::assertSame(200, $response->getStatusCode());
            $action = $this->latestAction();
            self::assertSame($contextType, $action['context_type']);
            self::assertSame((int) $contextId, (int) $action['context_id']);
            self::assertSame('573001234567', $action['recipient_phone_snapshot']);
            self::assertStringContainsString($messagePart, $action['message_snapshot']);
        }
    }

    public function testInvalidContextTemplateAndMissingContextCannotCreateAction(): void
    {
        $app = $this->app();
        $this->login($app, 'owner@example.test', 'OwnerPassword123!');
        $invalidForms = [
            ['context_type' => 'candidate', 'context_id' => '999999', 'template_key' => 'candidate_follow_up'],
            ['context_type' => 'unknown', 'context_id' => (string) $this->candidateId, 'template_key' => 'candidate_follow_up'],
            ['context_type' => 'candidate', 'context_id' => (string) $this->candidateId, 'template_key' => 'lease_contact'],
        ];
        foreach ($invalidForms as $form) {
            self::assertSame(422, $this->request($app, 'POST', '/admin/whatsapp', $form)->getStatusCode());
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM whatsapp_actions')->fetchColumn());
    }

    public function testRoutesRequirePermissionAndTenantRemainsDenied(): void
    {
        $anonymous = $this->request($this->app(), 'GET', '/admin/whatsapp');
        self::assertSame(302, $anonymous->getStatusCode());
        self::assertSame('/login', $anonymous->getHeaderLine('Location'));

        $deniedApp = $this->app();
        $this->login($deniedApp, 'whatsapp-denied@example.test', 'DeniedPassword123!');
        self::assertSame(403, $this->request($deniedApp, 'GET', '/admin/whatsapp')->getStatusCode());
        self::assertSame(403, $this->request($deniedApp, 'POST', '/admin/whatsapp', [
            'context_type' => 'candidate', 'context_id' => (string) $this->candidateId,
            'template_key' => 'candidate_follow_up',
        ])->getStatusCode());

        $_SESSION = [];
        $tenantApp = $this->app();
        $this->login($tenantApp, 'whatsapp-tenant@example.test', 'TenantPassword123!');
        self::assertSame(403, $this->request($tenantApp, 'GET', '/admin/whatsapp')->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM whatsapp_actions')->fetchColumn());
    }

    public function testPrepareAndOpenPostsStayPrivateAndTenantCannotOpenAnAction(): void
    {
        $ownerApp = $this->app();
        $this->login($ownerApp, 'owner@example.test', 'OwnerPassword123!');
        $prepared = $this->request($ownerApp, 'POST', '/admin/whatsapp', [
            'context_type' => 'candidate',
            'context_id' => (string) $this->candidateId,
            'template_key' => 'candidate_follow_up',
        ]);
        self::assertSame(200, $prepared->getStatusCode());
        $action = $this->latestAction();
        $openPath = '/admin/whatsapp/' . (int) $action['id'] . '/open';

        $_SESSION = [];
        $anonymousApp = $this->app();
        $prepare = $this->request($anonymousApp, 'POST', '/admin/whatsapp', [
            'context_type' => 'candidate',
            'context_id' => (string) $this->candidateId,
            'template_key' => 'candidate_follow_up',
        ]);
        self::assertSame(302, $prepare->getStatusCode());
        self::assertSame('/login', $prepare->getHeaderLine('Location'));
        $open = $this->request($anonymousApp, 'POST', $openPath);
        self::assertSame(302, $open->getStatusCode());
        self::assertSame('/login', $open->getHeaderLine('Location'));

        $_SESSION = [];
        $tenantApp = $this->app();
        $this->login($tenantApp, 'whatsapp-tenant@example.test', 'TenantPassword123!');
        self::assertSame(403, $this->request($tenantApp, 'POST', '/admin/whatsapp', [
            'context_type' => 'candidate',
            'context_id' => (string) $this->candidateId,
            'template_key' => 'candidate_follow_up',
        ])->getStatusCode());
        self::assertSame(403, $this->request($tenantApp, 'POST', $openPath)->getStatusCode());
        self::assertNull($this->action((int) $action['id'])['opened_at']);
    }

    public function testForcedPasswordAndCsrfGuardRemainActive(): void
    {
        $app = $this->app();
        $login = $this->request($app, 'POST', '/login', [
            'email' => 'whatsapp-forced@example.test', 'password' => 'ForcedPassword123!',
        ]);
        self::assertSame(303, $login->getStatusCode());
        self::assertSame('/account/password', $login->getHeaderLine('Location'));
        self::assertSame(302, $this->request($app, 'GET', '/admin/whatsapp')->getStatusCode());

        $_SESSION = [];
        $validApp = $this->app();
        $this->login($validApp, 'owner@example.test', 'OwnerPassword123!');
        self::assertSame(403, $this->request(
            $validApp,
            'POST',
            '/admin/whatsapp',
            ['context_type' => 'candidate', 'context_id' => (string) $this->candidateId, 'template_key' => 'candidate_follow_up'],
            false,
        )->getStatusCode());
    }

    public function testStoredNonHttpsDestinationIsRejectedBeforeOpenAudit(): void
    {
        $app = $this->app();
        $this->login($app, 'owner@example.test', 'OwnerPassword123!');
        $this->request($app, 'POST', '/admin/whatsapp', [
            'context_type' => 'candidate', 'context_id' => (string) $this->candidateId,
            'template_key' => 'candidate_follow_up',
        ]);
        $action = $this->latestAction();
        $update = $this->pdo->prepare('UPDATE whatsapp_actions SET url_snapshot = :url WHERE id = :id');
        $update->execute(['url' => 'javascript:alert(1)', 'id' => $action['id']]);

        $response = $this->request($app, 'POST', '/admin/whatsapp/' . (int) $action['id'] . '/open');
        self::assertSame(422, $response->getStatusCode());
        self::assertNull($this->action((int) $action['id'])['opened_at']);
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
            'base_url' => 'https://alquilapro.test' . $basePath,
            'base_path' => $basePath,
            'logo_directory' => $brandingDirectory,
            'pdo' => $this->pdo,
        ]);
    }

    private function login(App $app, string $email, string $password, string $basePath = ''): void
    {
        $response = $this->request($app, 'POST', $basePath . '/login', ['email' => $email, 'password' => $password]);
        self::assertSame(303, $response->getStatusCode());
    }

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        bool $includeCsrf = true,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://alquilapro.test' . $path);
        if (strtoupper($method) === 'POST' && $includeCsrf) {
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
        array $permissions = [],
        bool $mustChangePassword = false,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions, :must_change)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => ucfirst($role) . ' WhatsApp',
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change' => $mustChangePassword ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function seedContexts(): void
    {
        $this->pdo->exec("INSERT INTO properties (name, address, city) VALUES ('WhatsApp House', 'Calle 1', 'Bogotá')");
        $propertyId = (int) $this->pdo->lastInsertId();
        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) VALUES (:property_id, :public_key, \'apartment\', \'WA-1\', \'available\')',
        );
        $unit->execute(['property_id' => $propertyId, 'public_key' => bin2hex(random_bytes(12))]);
        $unitId = (int) $this->pdo->lastInsertId();

        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, \'cc\', \'WA-ROUTE-1\', \'Ana <img src=x>\', \'3001234567\', \'ana@example.test\', '
            . '\'submitted\', :token_hash, \'2027-01-01 00:00:00\', \'2026-10-14 12:00:00\')',
        );
        $candidate->execute(['unit_id' => $unitId, 'token_hash' => hash('sha256', random_bytes(32))]);
        $this->candidateId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type, is_active) VALUES (\'WhatsApp test contract\', \'urban_housing\', 1)',
        );
        $template->execute();
        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) '
            . 'VALUES (:template_id, 1, \'contract body\', \'[]\')',
        );
        $version->execute(['template_id' => $this->pdo->lastInsertId()]);
        $versionId = (int) $this->pdo->lastInsertId();
        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, '
            . 'start_date, end_date, duration_text) VALUES (:candidate, :unit, :version, \'urban_housing\', \'finalized\', '
            . '1500000, \'2026-10-01\', \'2027-09-30\', \'Doce meses\')',
        );
        $contract->execute(['candidate' => $this->candidateId, 'unit' => $unitId, 'version' => $versionId]);
        $contractId = (int) $this->pdo->lastInsertId();
        $tenantId = $this->insertUser('lease-tenant@example.test', 'LeaseTenant123!', 'tenant');
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) '
            . 'VALUES (:contract, :tenant, :actor, \'2026-10-01 12:00:00\')',
        );
        $lease->execute(['contract' => $contractId, 'tenant' => $tenantId, 'actor' => $this->ownerId]);
        $this->leaseId = (int) $this->pdo->lastInsertId();
        $maintenance = $this->pdo->prepare(
            'INSERT INTO maintenance_requests (lease_id, category, priority, description, photos_json, created_by_user_id) '
            . 'VALUES (:lease, \'plomería\', \'medium\', \'Llave con fuga\', \'[]\', :actor)',
        );
        $maintenance->execute(['lease' => $this->leaseId, 'actor' => $this->ownerId]);
        $this->maintenanceRequestId = (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed> */
    private function latestAction(): array
    {
        $row = $this->pdo->query('SELECT * FROM whatsapp_actions ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        return $row;
    }

    /** @return array<string,mixed> */
    private function action(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM whatsapp_actions WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        return $row;
    }
}
