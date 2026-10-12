<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Properties;

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

final class PropertiesLauncherTest extends TestCase
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
        foreach (['unit_media', 'units', 'properties', 'settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_properties_launcher_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-11T21:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testOwnerSeesPropertiesLauncherCard(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner', []);
        $app = $this->app();
        $this->login($app, 'owner@example.com', 'OwnerPassword123');

        $body = (string) $this->request($app, 'GET', '/')->getBody();
        self::assertStringContainsString('href="/admin/properties"', $body);
        self::assertStringContainsString('>Propiedades<', $body);
    }

    public function testPropertiesViewerSeesCardWithoutFalseEmptyState(): void
    {
        $this->insertUser('properties@example.com', 'PropertiesPass123', 'admin', ['properties.view']);
        $app = $this->app();
        $this->login($app, 'properties@example.com', 'PropertiesPass123');

        $body = (string) $this->request($app, 'GET', '/')->getBody();
        self::assertStringContainsString('href="/admin/properties"', $body);
        self::assertStringContainsString('>Propiedades<', $body);
        self::assertStringNotContainsString('No tienes aplicaciones disponibles', $body);
        self::assertStringNotContainsString('href="/admin/users"', $body);
        self::assertStringNotContainsString('href="/admin/appearance"', $body);
    }

    public function testUserWithoutPropertiesPermissionDoesNotSeePropertiesCard(): void
    {
        $this->insertUser('users@example.com', 'UsersPassword123', 'admin', ['users.view']);
        $app = $this->app();
        $this->login($app, 'users@example.com', 'UsersPassword123');

        $body = (string) $this->request($app, 'GET', '/')->getBody();
        self::assertStringNotContainsString('href="/admin/properties"', $body);
        self::assertStringNotContainsString('>Propiedades<', $body);
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
            'unit_media_directory' => $this->directory . '/unit-media',
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

    private function insertUser(string $email, string $password, string $role, array $permissions): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, 0)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => 'Launcher Test',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);
    }
}
