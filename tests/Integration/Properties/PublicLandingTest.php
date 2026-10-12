<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Properties;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class PublicLandingTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_public_landing_' . bin2hex(random_bytes(6));
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

    public function testInstalledVisitorGetsPublicLandingInsteadOfLoginRedirect(): void
    {
        $this->upsertSetting('app.name', 'Arrienda Fácil');
        $app = $this->app();

        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest(
                'GET',
                'https://alquilapro.sierraglobalcompany.com/',
            ),
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Arrienda Fácil', $html);
        self::assertStringContainsString('href="/catalog"', $html);
        self::assertStringContainsString('Ver catálogo', $html);
        self::assertStringContainsString('href="/login"', $html);
        self::assertStringContainsString('Iniciar sesión', $html);
        self::assertStringNotContainsString('data-f3-app-shell', $html);
        self::assertStringNotContainsString('bottom-nav', $html);
        self::assertStringNotContainsString('Apps', $html);
    }

    public function testLandingAvailabilitySummaryAppearsOnlyForLivePublicableInventory(): void
    {
        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) '
            . 'VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio Resumen',
            'address' => 'Dirección privada',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, status, is_public, monthly_price, description, requirements) '
            . 'VALUES (:property_id, :public_key, :type, :code, :status, 1, :monthly_price, :description, :requirements)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => str_repeat('d', 24),
            'type' => 'apartment',
            'code' => 'A-RESUMEN',
            'status' => 'available',
            'monthly_price' => '1200000.00',
            'description' => 'Descripción pública',
            'requirements' => 'Requisitos públicos',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();
        $app = $this->app();

        $withoutPhoto = (string) $this->requestRoot($app)->getBody();
        self::assertStringNotContainsString('unidad disponible ahora', $withoutPhoto);

        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', :stored_name, 'image/jpeg', 1, 1)",
        )->execute([
            'unit_id' => $unitId,
            'stored_name' => 'photo-summary.jpg',
        ]);

        $withPhoto = (string) $this->requestRoot($app)->getBody();
        self::assertStringContainsString('1 unidad disponible ahora', $withPhoto);

        $this->pdo->prepare('UPDATE units SET status = :status WHERE id = :id')
            ->execute(['status' => 'occupied', 'id' => $unitId]);
        $occupied = (string) $this->requestRoot($app)->getBody();
        self::assertStringNotContainsString('unidad disponible ahora', $occupied);
    }

    public function testPublicLandingRespectsSubfolderBasePath(): void
    {
        $app = $this->app('/miapp');

        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest(
                'GET',
                'https://alquilapro.sierraglobalcompany.com/miapp/',
            ),
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('href="/miapp/catalog"', $html);
        self::assertStringContainsString('href="/miapp/login"', $html);
        self::assertStringContainsString('href="/miapp/assets/app.css"', $html);
        self::assertStringNotContainsString('/miapp/miapp/', $html);
    }

    private function app(string $basePath = ''): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com' . $basePath,
            'base_path' => $basePath,
            'logo_directory' => $this->directory . '/branding',
            'unit_media_directory' => $this->directory . '/unit-media',
            'pdo' => $this->pdo,
        ]);
    }

    private function requestRoot(App $app): \Psr\Http\Message\ResponseInterface
    {
        return $app->handle(
            (new ServerRequestFactory())->createServerRequest(
                'GET',
                'https://alquilapro.sierraglobalcompany.com/',
            ),
        );
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
