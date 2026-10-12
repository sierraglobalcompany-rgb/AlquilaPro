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

final class PublicCatalogListTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_public_catalog_' . bin2hex(random_bytes(6));
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

    public function testCatalogShowsOnlyPublicableUnitsWithoutPrivateDataOrApply(): void
    {
        $medellin = $this->insertProperty('Edificio Visible', 'Medellín', 'Calasanz', true, 'Calle privada 99');
        $rionegro = $this->insertProperty('Parqueaderos Oriente', 'Rionegro', 'Llanogrande', true, 'Dirección secreta 10');
        $inactive = $this->insertProperty('Edificio Inactivo', 'Medellín', 'Centro', false, 'Dirección inactiva 1');

        $visibleApartment = $this->insertUnit($medellin, str_repeat('a', 24), 'apartment', 'INT-A1', 'available', true, true);
        $this->insertPhoto($visibleApartment);

        $visibleParking = $this->insertUnit($rionegro, str_repeat('b', 24), 'parking', 'INT-P1', 'available', true, true);
        $this->insertPhoto($visibleParking);

        $occupied = $this->insertUnit($medellin, str_repeat('c', 24), 'apartment', 'INT-A2', 'occupied', true, true);
        $this->insertPhoto($occupied);

        $incomplete = $this->insertUnit($medellin, str_repeat('d', 24), 'apartment', 'INT-A3', 'available', true, false);
        $this->insertPhoto($incomplete);

        $inactivePropertyUnit = $this->insertUnit($inactive, str_repeat('e', 24), 'apartment', 'INT-A4', 'available', true, true);
        $this->insertPhoto($inactivePropertyUnit);

        $app = $this->app();
        $response = $this->request($app, '/catalog');

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Edificio Visible', $html);
        self::assertStringContainsString('Parqueaderos Oriente', $html);
        self::assertStringContainsString('href="/catalog/' . str_repeat('a', 24) . '"', $html);
        self::assertStringContainsString('href="/catalog/' . str_repeat('b', 24) . '"', $html);
        self::assertStringNotContainsString(str_repeat('c', 24), $html);
        self::assertStringNotContainsString(str_repeat('d', 24), $html);
        self::assertStringNotContainsString(str_repeat('e', 24), $html);
        self::assertStringNotContainsString('Calle privada 99', $html);
        self::assertStringNotContainsString('Dirección secreta 10', $html);
        self::assertStringNotContainsString('INT-A1', $html);
        self::assertStringNotContainsString('INT-P1', $html);
        self::assertStringNotContainsString('Aplicar', $html);
    }

    public function testCatalogFiltersByTypeCityAndSectorAndRejectsInvalidType(): void
    {
        $medellin = $this->insertProperty('Edificio Medellín', 'Medellín', 'Calasanz', true, 'Privada M');
        $rionegro = $this->insertProperty('Edificio Rionegro', 'Rionegro', 'Llanogrande', true, 'Privada R');

        $apartment = $this->insertUnit($medellin, str_repeat('1', 24), 'apartment', 'A-1', 'available', true, true);
        $parking = $this->insertUnit($rionegro, str_repeat('2', 24), 'parking', 'P-1', 'available', true, true);
        $this->insertPhoto($apartment);
        $this->insertPhoto($parking);

        $app = $this->app();

        $apartments = (string) $this->request($app, '/catalog?type=apartment')->getBody();
        self::assertStringContainsString(str_repeat('1', 24), $apartments);
        self::assertStringNotContainsString(str_repeat('2', 24), $apartments);

        $rionegroOnly = (string) $this->request($app, '/catalog?city=Rionegro&sector=Llanogrande')->getBody();
        self::assertStringContainsString(str_repeat('2', 24), $rionegroOnly);
        self::assertStringNotContainsString(str_repeat('1', 24), $rionegroOnly);

        $invalid = $this->request($app, '/catalog?type=house');
        self::assertSame(422, $invalid->getStatusCode());
    }

    public function testCatalogLinksRespectSubfolderBasePath(): void
    {
        $property = $this->insertProperty('Edificio Subfolder', 'Medellín', 'Laureles', true, 'Privada');
        $unit = $this->insertUnit($property, str_repeat('f', 24), 'apartment', 'A-10', 'available', true, true);
        $this->insertPhoto($unit);

        $html = (string) $this->request($this->app('/miapp'), '/miapp/catalog')->getBody();
        self::assertStringContainsString('href="/miapp/catalog/' . str_repeat('f', 24) . '"', $html);
        self::assertStringContainsString('href="/miapp/"', $html);
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

    private function request(App $app, string $path): \Psr\Http\Message\ResponseInterface
    {
        return $app->handle(
            (new ServerRequestFactory())->createServerRequest(
                'GET',
                'https://alquilapro.sierraglobalcompany.com' . $path,
            ),
        );
    }

    private function insertProperty(string $name, string $city, string $sector, bool $active, string $address): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) '
            . 'VALUES (:name, :address, :city, :sector, :is_active)',
        );
        $statement->execute([
            'name' => $name,
            'address' => $address,
            'city' => $city,
            'sector' => $sector,
            'is_active' => $active ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertUnit(
        int $propertyId,
        string $publicKey,
        string $type,
        string $code,
        string $status,
        bool $isPublic,
        bool $complete,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements, bedrooms, bathrooms, administration_fee, parking_level, vehicle_type, private_notes, registry_number) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, :status, :is_public, :monthly_price, :description, :requirements, :bedrooms, :bathrooms, :administration_fee, :parking_level, :vehicle_type, :private_notes, :registry_number)',
        );
        $statement->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'type' => $type,
            'code' => $code,
            'location_label' => 'Sector seguro',
            'status' => $status,
            'is_public' => $isPublic ? 1 : 0,
            'monthly_price' => '1200000.00',
            'description' => $complete ? 'Unidad lista para arrendar' : null,
            'requirements' => $complete ? 'Documentos básicos' : null,
            'bedrooms' => $type === 'apartment' ? 2 : null,
            'bathrooms' => $type === 'apartment' ? '2.0' : null,
            'administration_fee' => $type === 'apartment' ? '250000.00' : null,
            'parking_level' => $type === 'parking' ? 'S1' : null,
            'vehicle_type' => $type === 'parking' ? 'Automóvil' : null,
            'private_notes' => 'NO PUBLICAR ESTA NOTA',
            'registry_number' => $type === 'apartment' ? 'MATRICULA-PRIVADA' : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertPhoto(int $unitId): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', :stored_name, 'image/jpeg', 1, 1)",
        );
        $statement->execute([
            'unit_id' => $unitId,
            'stored_name' => 'photo-' . $unitId . '.jpg',
        ]);
    }
}
