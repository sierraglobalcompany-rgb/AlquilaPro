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
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AuthVisualTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private App $app;

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

        $this->directory = sys_get_temp_dir() . '/alquila_auth_visual_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-11T20:00:00-05:00',
        ]);

        $this->app = $this->buildApp('');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testAuthPagesShareF3ShellAndConfiguredBrandingWithoutBottomNav(): void
    {
        $this->upsertSetting('app.name', 'Casa Sierra');
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner', true);

        $login = $this->request($this->app, 'GET', '/login');
        $reset = $this->request($this->app, 'GET', '/reset-password?token=abc');

        $this->request($this->app, 'POST', '/login', [
            'email' => 'owner@example.com',
            'password' => 'OwnerPassword123',
        ]);
        $change = $this->request($this->app, 'GET', '/account/password');

        foreach ([$login, $reset, $change] as $response) {
            self::assertSame(200, $response->getStatusCode());
            $body = (string) $response->getBody();
            self::assertStringContainsString('data-f3-auth-shell', $body);
            self::assertStringContainsString('Casa Sierra', $body);
            self::assertStringContainsString('href="/assets/app.css"', $body);
            self::assertStringNotContainsString('data-bottom-nav', $body);
        }

        $_SESSION = [];
        $failed = $this->request($this->app, 'POST', '/login', [
            'email' => 'owner@example.com',
            'password' => 'NeverEchoThisPassword123',
        ]);
        self::assertSame(422, $failed->getStatusCode());
        self::assertStringNotContainsString('NeverEchoThisPassword123', (string) $failed->getBody());
    }

    public function testAuthStylesheetRespectsSubfolderBasePath(): void
    {
        $_SESSION = [];
        $app = $this->buildApp('/miapp');
        $response = $this->request($app, 'GET', '/miapp/login', [], '/miapp');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('href="/miapp/assets/app.css"', $body);
        self::assertStringNotContainsString('/miapp/miapp/', $body);
    }

    public function testInstallerUsesSameLocalStylesWithoutInstalledBranding(): void
    {
        $_SESSION = [];
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        $app = $factory([
            'lock_path' => $this->directory . '/not-installed.lock',
            'database_config_path' => $this->directory . '/not-installed-database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com',
            'base_path' => '',
        ]);

        $response = $this->request($app, 'GET', '/install');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('href="/assets/app.css"', $body);
        self::assertStringContainsString('class="auth-shell', $body);
    }

    private function buildApp(string $basePath): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com' . $basePath,
            'base_path' => $basePath,
            'pdo' => $this->pdo,
        ]);
    }

    private function request(App $app, string $method, string $path, array $body = [], string $basePath = ''): \Psr\Http\Message\ResponseInterface
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

    private function insertUser(string $email, string $password, string $role, bool $mustChangePassword): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :must_change_password)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => 'Owner Visual',
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);
    }

    private function upsertSetting(string $key, string $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        );
        $statement->execute(['setting_key' => $key, 'setting_value' => $value]);
    }
}
