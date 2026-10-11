<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Foundation;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AppBootstrapTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $configPath;
    private string $migrationDirectory;

    protected function setUp(): void
    {
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

        $this->directory = sys_get_temp_dir() . '/alquila_app_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        $this->configPath = $this->directory . '/database.php';
        $this->migrationDirectory = dirname(__DIR__, 3) . '/database/migrations';
    }

    protected function tearDown(): void
    {
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @unlink($this->configPath);
        @unlink($this->configPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testRootRedirectsToInstallerBeforeInstallation(): void
    {
        $app = $this->app();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://alquilapro.sierraglobalcompany.com/');

        $response = $app->handle($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/install', $response->getHeaderLine('Location'));
    }

    public function testInstallerPageShowsDiagnosticAndForm(): void
    {
        $app = $this->app();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://alquilapro.sierraglobalcompany.com/install');

        $response = $app->handle($request);
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Instalar Alquila Pro', $body);
        self::assertStringContainsString('Diagnóstico del servidor', $body);
        self::assertStringContainsString('Base de datos', $body);
        self::assertStringContainsString('Propietario inicial', $body);
    }

    public function testFailedPostDoesNotEchoPasswords(): void
    {
        $app = $this->app();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://alquilapro.sierraglobalcompany.com/install')
            ->withParsedBody($this->form(['db_password' => 'supersecretfailure']));

        $response = $app->handle($request);
        $body = (string) $response->getBody();

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('No fue posible conectar con la base de datos.', $body);
        self::assertStringNotContainsString('supersecretfailure', $body);
        self::assertStringNotContainsString('correct-horse-battery', $body);
        self::assertFileDoesNotExist($this->lockPath);
    }

    public function testSuccessfulPostLocksInstallerAndRedirectsHome(): void
    {
        $app = $this->app();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://alquilapro.sierraglobalcompany.com/install')
            ->withParsedBody($this->form());

        $response = $app->handle($request);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/', $response->getHeaderLine('Location'));
        self::assertFileExists($this->lockPath);

        $blocked = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://alquilapro.sierraglobalcompany.com/install'),
        );
        self::assertSame(404, $blocked->getStatusCode());
    }

    public function testHealthEndpointContainsOnlyNonSecretStatusData(): void
    {
        $app = $this->app();
        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://alquilapro.sierraglobalcompany.com/health'),
        );

        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $data['status']);
        self::assertArrayHasKey('version', $data);
        self::assertArrayNotHasKey('database', $data);
        self::assertArrayNotHasKey('password', $data);
    }

    private function app(): App
    {
        $bootstrap = dirname(__DIR__, 3) . '/bootstrap/app.php';
        self::assertFileExists($bootstrap, 'bootstrap/app.php must exist before web behavior can pass.');

        $factory = require $bootstrap;

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->configPath,
            'migration_directory' => $this->migrationDirectory,
            'base_url' => 'https://alquilapro.sierraglobalcompany.com',
            'base_path' => '',
        ]);
    }

    private function form(array $override = []): array
    {
        return array_merge([
            'db_host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'db_port' => getenv('TEST_DB_PORT') ?: '3306',
            'db_name' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'db_user' => getenv('TEST_DB_USER') ?: 'root',
            'db_password' => getenv('TEST_DB_PASSWORD') ?: 'root',
            'app_name' => 'Alquila Pro',
            'owner_name' => 'Propietario Principal',
            'owner_email' => 'owner@example.com',
            'owner_password' => 'correct-horse-battery',
        ], $override);
    }
}
