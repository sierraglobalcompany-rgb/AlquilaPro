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

final class PropertiesCsrfTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_properties_csrf_' . bin2hex(random_bytes(6));
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

    public function testPropertyMutationWithoutCsrfIsForbiddenAndDoesNotMutate(): void
    {
        $this->insertManager();
        $app = $this->app();
        $this->login($app);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://alquilapro.sierraglobalcompany.com/admin/properties')
            ->withParsedBody([
                'name' => 'No debe crearse',
                'address' => 'Calle 1',
                'city' => 'Medellín',
            ]);

        $response = $app->handle($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn());
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

    private function login(App $app): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://alquilapro.sierraglobalcompany.com/login')
            ->withParsedBody([
                'email' => 'manager@example.com',
                'password' => 'ManagerPassword123',
                'csrf_token' => (new Csrf())->token(),
            ]);

        $response = $app->handle($request);
        self::assertSame(303, $response->getStatusCode());
    }

    private function insertManager(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . "VALUES ('admin', 'Manager Test', 'manager@example.com', :password_hash, 1, :permissions_json, 0)",
        );
        $statement->execute([
            'password_hash' => password_hash('ManagerPassword123', PASSWORD_DEFAULT),
            'permissions_json' => json_encode(['properties.view', 'properties.manage'], JSON_THROW_ON_ERROR),
        ]);
    }
}
