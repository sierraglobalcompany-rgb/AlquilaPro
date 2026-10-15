<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Audit;

use AlquilaPro\Audit\AuditService;
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

final class AuditRoutesTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_audit_routes_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '22',
            'installed_at' => '2026-10-14T21:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testAuditViewerRequiresAuthenticationAndOwnerRole(): void
    {
        $anonymous = $this->request($this->app(), 'GET', '/admin/audit');
        self::assertSame(302, $anonymous->getStatusCode());
        self::assertSame('/login', $anonymous->getHeaderLine('Location'));

        $_SESSION = [];
        $this->insertUser('admin@example.com', 'AdminPassword123', 'admin', ['users.view', 'users.manage']);
        $app = $this->app();
        $this->login($app, 'admin@example.com', 'AdminPassword123');

        self::assertSame(403, $this->request($app, 'GET', '/admin/audit')->getStatusCode());
    }

    public function testAuditViewerHonorsForcedPasswordChange(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner', [], true);
        $app = $this->app();
        $login = $this->request($app, 'POST', '/login', [
            'email' => 'owner@example.com',
            'password' => 'OwnerPassword123',
        ]);
        self::assertSame('/account/password', $login->getHeaderLine('Location'));

        $response = $this->request($app, 'GET', '/admin/audit');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/account/password', $response->getHeaderLine('Location'));
    }

    public function testOwnerSeesRecentRowsEscapedWithoutRawMetadataPayload(): void
    {
        $ownerId = $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        (new AuditService($this->pdo))->record(
            $ownerId,
            'lease.ended',
            'lease',
            77,
            '<script>alert(1)</script>',
            '203.0.113.19',
            ['state' => 'ended'],
        );

        $app = $this->app();
        $this->login($app, 'owner@example.com', 'OwnerPassword123');
        $response = $this->request($app, 'GET', '/admin/audit');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('lease.ended', $body);
        self::assertStringContainsString('lease', $body);
        self::assertStringContainsString('77', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringNotContainsString('{"state":"ended"}', $body);
        self::assertStringNotContainsString('metadata_json', $body);
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
        array $permissions = [],
        bool $mustChangePassword = false,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, :must_change_password)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => ucfirst($role) . ' Audit Test',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
