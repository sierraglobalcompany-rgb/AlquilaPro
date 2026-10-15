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
    ): void {
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
    }
}
