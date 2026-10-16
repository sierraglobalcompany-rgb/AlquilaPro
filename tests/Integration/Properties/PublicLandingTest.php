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

    public function testInstalledVisitorGetsApprovedPublicLandingWithIntegratedLogin(): void
    {
        $this->upsertSetting('app.name', 'Arrienda Fácil');
        $publicKey = str_repeat('a', 24);
        $this->seedPublicableApartment($publicKey, 'Edificio Destacado', 'A-401', 'photo-a.jpg');
        $app = $this->app();

        $response = $this->requestRoot($app);

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Arrienda Fácil', $html);
        self::assertStringContainsString('Propiedades disponibles', $html);
        self::assertStringContainsString('href="/catalog"', $html);
        self::assertStringContainsString('Ver todas', $html);
        self::assertStringContainsString('Edificio Destacado', $html);
        self::assertStringContainsString('Medellín', $html);
        self::assertStringContainsString('Calasanz', $html);
        self::assertStringContainsString('href="/catalog/' . $publicKey . '"', $html);
        self::assertStringContainsString('src="/catalog/' . $publicKey . '/photo/1"', $html);
        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('action="/login"', $html);
        self::assertStringContainsString('name="csrf_token"', $html);
        self::assertStringContainsString('name="identifier"', $html);
        self::assertStringContainsString('Usuario, correo o cédula', $html);
        self::assertStringContainsString('Contraseña', $html);
        self::assertStringContainsString('Iniciar sesión', $html);
        self::assertStringNotContainsString('Encuentra tu próximo espacio', $html);
        self::assertStringNotContainsString('unidad disponible ahora', $html);
        self::assertStringNotContainsString('NO FILTRAR', $html);
        self::assertStringNotContainsString('Google', $html);
        self::assertStringNotContainsString('data-login-close', $html);
        self::assertStringNotContainsString('bottom-nav', $html);
        self::assertStringNotContainsString('Apps', $html);
    }

    public function testLandingRendersAtMostEightFeaturedUnitsAndTruthfulEmptyState(): void
    {
        for ($index = 1; $index <= 9; $index++) {
            $this->seedPublicableApartment(
                str_pad((string) $index, 24, (string) $index),
                'Edificio ' . $index,
                'A-' . $index,
                'photo-' . $index . '.jpg',
            );
        }
        $app = $this->app();

        $html = (string) $this->requestRoot($app)->getBody();
        self::assertSame(8, substr_count($html, 'data-public-featured-unit'));
        self::assertStringNotContainsString('Edificio 9', $html);

        $this->pdo->exec("UPDATE units SET status = 'occupied'");
        $emptyHtml = (string) $this->requestRoot($app)->getBody();
        self::assertStringContainsString('data-public-empty', $emptyHtml);
        self::assertStringContainsString('No hay propiedades disponibles por ahora.', $emptyHtml);
        self::assertStringContainsString('action="/login"', $emptyHtml);
        self::assertStringContainsString('name="identifier"', $emptyHtml);
    }

    public function testStandaloneLoginKeepsUnifiedIdentifierAndApprovedSubmitCopy(): void
    {
        $app = $this->app();
        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest(
                'GET',
                'https://alquilapro.sierraglobalcompany.com/login',
            ),
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Usuario, correo o cédula', $html);
        self::assertStringContainsString('name="identifier"', $html);
        self::assertStringContainsString('name="csrf_token"', $html);
        self::assertStringContainsString('Iniciar sesión', $html);
        self::assertStringNotContainsString('>Entrar</button>', $html);
    }

    public function testPublicLandingRespectsSubfolderBasePath(): void
    {
        $publicKey = str_repeat('b', 24);
        $this->seedPublicableApartment($publicKey, 'Edificio Subfolder', 'B-201', 'photo-subfolder.jpg');
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
        self::assertStringContainsString('href="/miapp/catalog/' . $publicKey . '"', $html);
        self::assertStringContainsString('src="/miapp/catalog/' . $publicKey . '/photo/1"', $html);
        self::assertStringContainsString('action="/miapp/login"', $html);
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

    private function seedPublicableApartment(
        string $publicKey,
        string $propertyName,
        string $code,
        string $storedName,
    ): void {
        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) '
            . 'VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => $propertyName,
            'address' => 'Dirección privada',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements, private_notes, unit_number, tower, floor, area_m2, bedrooms, bathrooms) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, :status, 1, :monthly_price, :description, :requirements, :private_notes, :unit_number, :tower, :floor, :area_m2, :bedrooms, :bathrooms)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'type' => 'apartment',
            'code' => $code,
            'location_label' => 'Torre 1',
            'status' => 'available',
            'monthly_price' => '1200000.00',
            'description' => 'Descripción pública',
            'requirements' => 'Requisitos públicos',
            'private_notes' => 'NO FILTRAR',
            'unit_number' => '401',
            'tower' => '1',
            'floor' => '4',
            'area_m2' => '58.00',
            'bedrooms' => 2,
            'bathrooms' => '2.00',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', :stored_name, 'image/jpeg', 1, 1)",
        )->execute([
            'unit_id' => $unitId,
            'stored_name' => $storedName,
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
