<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Audit;

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

final class AuditMutationBehaviorTest extends TestCase
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
            $safe = str_replace('`', '``', (string) $table);
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safe));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_audit_behavior_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '22',
            'installed_at' => '2026-10-15T00:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testSuccessfulUserCreationWritesExactlyOneSafeAuditRow(): void
    {
        $this->insertOwner();
        $app = $this->app();
        $this->login($app);

        $response = $this->request($app, 'POST', '/admin/users', [
            'full_name' => 'Audit Created User',
            'email' => 'audit-created@example.com',
            'role' => 'gestor',
            'temporary_password' => 'TemporaryPass123',
        ]);

        self::assertSame(303, $response->getStatusCode());
        $rows = $this->pdo->query(
            "SELECT action, entity_type, entity_id, summary, ip_address, metadata_json FROM audit_log WHERE action = 'user.created'",
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $rows);
        self::assertSame('user', $rows[0]['entity_type']);
        self::assertSame('203.0.113.44', $rows[0]['ip_address']);
        self::assertNull($rows[0]['metadata_json']);
        self::assertStringNotContainsString('TemporaryPass123', implode('|', array_map('strval', $rows[0])));

        $createdId = (int) $this->pdo->query(
            "SELECT id FROM users WHERE email = 'audit-created@example.com' LIMIT 1",
        )->fetchColumn();
        self::assertSame($createdId, (int) $rows[0]['entity_id']);
    }

    public function testRejectedUserCreationWritesNoAuditRow(): void
    {
        $this->insertOwner();
        $app = $this->app();
        $this->login($app);

        $response = $this->request($app, 'POST', '/admin/users', [
            'full_name' => 'Invalid User',
            'email' => 'invalid-user@example.com',
            'role' => 'not-a-role',
            'temporary_password' => 'TemporaryPass123',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM audit_log WHERE action = 'user.created'",
        )->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM users WHERE email = 'invalid-user@example.com'",
        )->fetchColumn());
    }

    public function testAuditWriteFailureDoesNotCommitHighValueUserCreation(): void
    {
        $this->insertOwner();
        $app = $this->app();
        $this->login($app);

        $this->pdo->exec('DROP TABLE audit_log');

        $response = $this->request($app, 'POST', '/admin/users', [
            'full_name' => 'Must Roll Back',
            'email' => 'must-rollback@example.com',
            'role' => 'gestor',
            'temporary_password' => 'TemporaryPass123',
        ]);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            0,
            (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE email = 'must-rollback@example.com'")->fetchColumn(),
            'A high-value mutation must not remain committed when its required audit evidence cannot be persisted.',
        );
    }

    private function app(): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com',
            'base_path' => '',
            'logo_directory' => $this->directory . '/branding',
            'pdo' => $this->pdo,
        ]);
    }

    private function login(App $app): void
    {
        $response = $this->request($app, 'POST', '/login', [
            'email' => 'owner@example.com',
            'password' => 'OwnerPassword123',
        ]);
        self::assertSame(303, $response->getStatusCode());
    }

    private function request(App $app, string $method, string $path, array $body = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
            ['REMOTE_ADDR' => '203.0.113.44'],
        );
        if (strtoupper($method) === 'POST') {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }

        return $app->handle($request);
    }

    private function insertOwner(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, must_change_password) '
            . "VALUES ('owner', 'Audit Owner', :email, :password_hash, 1, 0)",
        );
        $statement->execute([
            'email' => 'owner@example.com',
            'password_hash' => password_hash('OwnerPassword123', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
