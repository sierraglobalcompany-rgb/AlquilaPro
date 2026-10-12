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

final class UnitLinkedApartmentOptionsTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_linked_apartment_' . bin2hex(random_bytes(6));
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

    public function testParkingEditorShowsOnlySamePropertyApartmentsAndKeepsCurrentSelection(): void
    {
        $this->insertOwner();
        $propertyA = $this->insertProperty('Edificio A');
        $propertyB = $this->insertProperty('Edificio B');
        $apartmentA = $this->insertUnit($propertyA, 'apartment', 'A-101');
        $apartmentB = $this->insertUnit($propertyB, 'apartment', 'B-101');
        $parking = $this->insertUnit($propertyA, 'parking', 'P-01', $apartmentA);

        $app = $this->app();
        $this->login($app);

        $response = $this->request($app, 'GET', '/admin/units/' . $parking);
        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();
        self::assertStringContainsString(
            '<option value="' . $apartmentA . '" selected>A-101</option>',
            $html,
        );
        self::assertStringNotContainsString('<option value="' . $apartmentB . '"', $html);
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

    private function insertProperty(string $name): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector) VALUES (:name, :address, :city, :sector)',
        );
        $statement->execute([
            'name' => $name,
            'address' => 'Calle 1 # 2-3',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertUnit(int $propertyId, string $type, string $code, ?int $linkedApartmentId = null): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status, linked_apartment_id) '
            . 'VALUES (:property_id, :public_key, :type, :code, :status, :linked_apartment_id)',
        );
        $statement->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'type' => $type,
            'code' => $code,
            'status' => 'available',
            'linked_apartment_id' => $linkedApartmentId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
