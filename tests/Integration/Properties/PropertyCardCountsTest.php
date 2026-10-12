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

final class PropertyCardCountsTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_property_counts_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-12T00:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testPropertyCardShowsSimpleCountsByCanonicalUnitStatus(): void
    {
        $this->insertOwner();
        $propertyId = $this->insertProperty();
        $this->insertUnit($propertyId, 'available', 'A-101');
        $this->insertUnit($propertyId, 'available', 'A-102');
        $this->insertUnit($propertyId, 'in_process', 'A-103');
        $this->insertUnit($propertyId, 'occupied', 'A-104');
        $this->insertUnit($propertyId, 'inactive', 'P-01', 'parking');
        $this->insertUnit($propertyId, 'maintenance', 'P-02', 'parking');

        $app = $this->app();
        $this->login($app);

        $response = $this->request($app, 'GET', '/admin/properties');
        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();
        self::assertStringContainsString('Disponibles: 2', $html);
        self::assertStringContainsString('En proceso: 1', $html);
        self::assertStringContainsString('Ocupadas: 1', $html);
        self::assertStringContainsString('Inactivas: 1', $html);
        self::assertStringContainsString('Mantenimiento: 1', $html);
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
        );

        if (strtoupper($method) === 'POST') {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }

        return $app->handle($request);
    }

    private function insertOwner(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . "VALUES ('owner', 'Owner Test', 'owner@example.com', :password_hash, 1, NULL, 0)",
        );
        $statement->execute([
            'password_hash' => password_hash('OwnerPassword123', PASSWORD_DEFAULT),
        ]);
    }

    private function insertProperty(): int
    {
        $this->pdo->exec(
            "INSERT INTO properties (name, address, city, sector) "
            . "VALUES ('Edificio Conteos', 'Calle 1 # 2-3', 'Medellín', 'Calasanz')",
        );

        return (int) $this->pdo->lastInsertId();
    }

    private function insertUnit(int $propertyId, string $status, string $code, string $type = 'apartment'): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) '
            . 'VALUES (:property_id, :public_key, :type, :code, :status)',
        );
        $statement->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'type' => $type,
            'code' => $code,
            'status' => $status,
        ]);
    }
}
