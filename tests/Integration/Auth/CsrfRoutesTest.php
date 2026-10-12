<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Auth;

use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CsrfRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $configPath;

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

        foreach (['settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_csrf_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        $this->configPath = $this->directory . '/database.php';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @unlink($this->configPath);
        @unlink($this->configPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testInstallPostWithoutCsrfIsForbiddenAndDoesNotInstall(): void
    {
        $app = $this->app(false);
        $response = $this->request($app, 'POST', '/install', [
            'db_host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'db_port' => getenv('TEST_DB_PORT') ?: '3306',
            'db_name' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'db_user' => getenv('TEST_DB_USER') ?: 'root',
            'db_password' => getenv('TEST_DB_PASSWORD') ?: 'root',
            'app_name' => 'Alquila Pro',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@example.com',
            'owner_password' => 'OwnerPassword123',
        ]);

        self::assertSame(403, $response->getStatusCode());
        self::assertFileDoesNotExist($this->lockPath);
    }

    public function testLoginPostWithoutCsrfIsForbidden(): void
    {
        $this->markInstalled();
        $this->insertOwner();
        $app = $this->app(true);

        $response = $this->request($app, 'POST', '/login', [
            'email' => 'owner@example.com',
            'password' => 'OwnerPassword123',
        ]);

        self::assertSame(403, $response->getStatusCode());
        self::assertArrayNotHasKey('_alquilapro_auth', $_SESSION);
    }

    public function testAdminMutationWithoutCsrfIsForbidden(): void
    {
        $this->markInstalled();
        $ownerId = $this->insertOwner();
        (new SessionGuard())->login([
            'id' => $ownerId,
            'role' => 'owner',
            'must_change_password' => false,
        ], time());
        $app = $this->app(true);

        $response = $this->request($app, 'POST', '/admin/users', [
            'full_name' => 'Blocked Admin',
            'email' => 'blocked@example.com',
            'role' => 'admin',
            'temporary_password' => 'TemporaryPass123',
        ]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE email = 'blocked@example.com'")->fetchColumn());
    }

    public function testAppearanceMutationWithoutCsrfIsForbidden(): void
    {
        $this->markInstalled();
        $ownerId = $this->insertOwner();
        (new SessionGuard())->login([
            'id' => $ownerId,
            'role' => 'owner',
            'must_change_password' => false,
        ], time());
        $app = $this->app(true);

        $response = $this->request($app, 'POST', '/admin/appearance', [
            'app_name' => 'Blocked Brand',
            'primary_color' => '#112233',
        ]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key IN ('app.name', 'brand.primary_color')")->fetchColumn());
    }

    public function testHealthRemainsPublicWithoutCsrf(): void
    {
        $app = $this->app(false);
        $response = $this->request($app, 'GET', '/health');

        self::assertSame(200, $response->getStatusCode());
    }

    private function app(bool $installed): App
    {
        if ($installed && !is_file($this->lockPath)) {
            $this->markInstalled();
        }

        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->configPath,
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com',
            'base_path' => '',
            'pdo' => $installed ? $this->pdo : null,
        ]);
    }

    private function request(App $app, string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );

        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }

        return $app->handle($request);
    }

    private function markInstalled(): void
    {
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-11T18:00:00-05:00',
        ]);
    }

    private function insertOwner(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (\'owner\', \'Owner\', \'owner@example.com\', :password_hash, 1)',
        );
        $statement->execute(['password_hash' => password_hash('OwnerPassword123', PASSWORD_DEFAULT)]);

        return (int) $this->pdo->lastInsertId();
    }
}
