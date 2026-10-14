<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Maintenance;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Maintenance\VisitService;
use AlquilaPro\Tests\Support\MaintenanceRequestFixture;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class MaintenanceRoutesTest extends TestCase
{
    use MaintenanceRequestFixture;

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
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', str_replace('`', '``', (string) $table)));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_maintenance_routes_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-13T10:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach (['branding', 'unit-media', 'candidate-documents', 'contracts', 'payment-method-qrs',
            'payment-receipts', 'obligation-supports', 'insurance-documents', 'maintenance-photos'] as $folder) {
            foreach (glob($this->directory . '/' . $folder . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->directory . '/' . $folder);
        }
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testStaffCanManageVisitsInsideMaintenanceRoutesWithBasePath(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $this->setPermissions($fixture['actor_id'], ['maintenance.view', 'maintenance.manage']);
        $app = $this->app('/sub');
        $this->login($app, $fixture['actor_email'], 'TemporaryPass123', '/sub');
        $detailPath = '/sub/admin/maintenance/' . $fixture['request_id'];
        $detail = $this->request($app, 'GET', $detailPath);
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('Agenda de visitas', (string) $detail->getBody());

        $now = time();
        $first = gmdate('Y-m-d H:i:s', $now + 86400);
        $second = gmdate('Y-m-d H:i:s', $now + 172800);
        $proposed = $this->request($app, 'POST', $detailPath . '/visits', [
            'operation' => 'propose',
            'slots' => $first . '|60' . "\n" . $second . '|90',
            'technician_name' => 'Técnico de prueba',
            'technician_contact' => '3001234567',
        ]);
        self::assertSame(303, $proposed->getStatusCode());
        self::assertSame($detailPath, $proposed->getHeaderLine('Location'));
        $proposedId = (int) $this->pdo->query(
            'SELECT id FROM maintenance_visits WHERE maintenance_request_id = ' . $fixture['request_id'],
        )->fetchColumn();
        self::assertGreaterThan(0, $proposedId);

        $newStart = gmdate('Y-m-d H:i:s', $now + 259200);
        $reprogrammed = $this->request($app, 'POST', $detailPath . '/visits/' . $proposedId . '/reprogram', [
            'slots' => $newStart . '|90',
        ]);
        self::assertSame(303, $reprogrammed->getStatusCode());
        self::assertSame('proposed', $this->visitStatus($proposedId));

        $directStart = gmdate('Y-m-d H:i:s', $now + 345600);
        $direct = $this->request($app, 'POST', $detailPath . '/visits', [
            'operation' => 'direct',
            'start_at' => $directStart,
            'duration_minutes' => '45',
            'technician_name' => 'Técnico directo',
        ]);
        self::assertSame(303, $direct->getStatusCode());
        $directId = (int) $this->pdo->query(
            'SELECT id FROM maintenance_visits WHERE maintenance_request_id = ' . $fixture['request_id']
            . ' AND selected_start_at = ' . $this->pdo->quote($directStart),
        )->fetchColumn();
        self::assertGreaterThan(0, $directId);

        $completed = $this->request($app, 'POST', $detailPath . '/visits/' . $directId . '/complete', [
            'result_notes' => 'Revisión terminada',
        ]);
        self::assertSame(303, $completed->getStatusCode());
        self::assertSame('completed', $this->visitStatus($directId));
        self::assertStringContainsString('Técnico de prueba', (string) $this->request($app, 'GET', $detailPath)->getBody());
    }

    public function testViewOnlyStaffCannotMutateAndForcedPasswordGateStillApplies(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $this->setPermissions($fixture['actor_id'], ['maintenance.view']);
        $now = time();
        (new VisitService($this->pdo))->propose($fixture['request_id'], [[
            'start_at' => gmdate('Y-m-d H:i:s', $now + 86400),
            'duration_minutes' => 60,
        ]], null, null, $fixture['actor_id'], $now);
        $viewer = $this->app('/sub');
        $this->login($viewer, $fixture['actor_email'], 'TemporaryPass123', '/sub');
        $detail = $this->request(
            $viewer,
            'GET',
            '/sub/admin/maintenance/' . $fixture['request_id'],
        );
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('Visita #', (string) $detail->getBody());
        self::assertSame(403, $this->request(
            $viewer,
            'POST',
            '/sub/admin/maintenance/' . $fixture['request_id'] . '/visits',
            ['operation' => 'direct'],
        )->getStatusCode());

        $forcedEmail = 'forced-' . bin2hex(random_bytes(5)) . '@example.test';
        $forcedId = $this->insertUser($forcedEmail, ['maintenance.view', 'maintenance.manage'], true);
        $_SESSION = [];
        $forced = $this->app('/sub');
        $this->login($forced, $forcedEmail, 'TemporaryPass123', '/sub', '/sub/account/password');
        self::assertGreaterThan(0, $forcedId);
        self::assertSame(302, $this->request(
            $forced,
            'POST',
            '/sub/admin/maintenance/' . $fixture['request_id'] . '/visits',
            ['operation' => 'direct'],
        )->getStatusCode());
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
            'pdo' => $this->pdo,
        ]);
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

    private function login(
        App $app,
        string $email,
        string $password,
        string $basePath = '',
        string $expectedLocation = '/',
    ): void
    {
        $response = $this->request($app, 'POST', $basePath . '/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame($expectedLocation === '/' ? $basePath . '/' : $expectedLocation, $response->getHeaderLine('Location'));
    }

    /** @param list<string> $permissions */
    private function setPermissions(int $userId, array $permissions): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET permissions_json = :permissions WHERE id = :id');
        $statement->execute(['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR), 'id' => $userId]);
    }

    /** @param list<string> $permissions */
    private function insertUser(string $email, array $permissions, bool $mustChangePassword): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (\'admin\', \'Visit Route User\', :email, :hash, 1, :permissions, :forced)',
        );
        $statement->execute([
            'email' => $email,
            'hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'forced' => $mustChangePassword ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function visitStatus(int $visitId): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM maintenance_visits WHERE id = :id');
        $statement->execute(['id' => $visitId]);
        return (string) $statement->fetchColumn();
    }
}
