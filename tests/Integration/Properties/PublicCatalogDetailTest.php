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

final class PublicCatalogDetailTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_public_detail_' . bin2hex(random_bytes(6));
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

    public function testPublicDetailShowsSafeFieldsAndHttpsVideoWithoutPrivateDataOrApply(): void
    {
        $propertyId = $this->insertProperty(
            '<script>Edificio Seguro</script>',
            'Calle secreta 123',
            'Medellín',
            'Calasanz',
            'Administración Privada',
            '3000000000',
            'NOTA INTERNA PROPIEDAD',
            true,
        );
        $publicKey = str_repeat('a', 24);
        $unitId = $this->insertUnit($propertyId, $publicKey, 'available', true, true);
        $this->insertPhoto($unitId);
        $this->insertVideo($unitId, 'https://video.example/watch?a=1&b=2');

        $response = $this->request($this->app(), '/catalog/' . $publicKey);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('&lt;script&gt;Edificio Seguro&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>Edificio Seguro</script>', $html);
        self::assertStringContainsString('Medellín', $html);
        self::assertStringContainsString('Calasanz', $html);
        self::assertStringContainsString('Ubicación pública segura', $html);
        self::assertStringContainsString('&lt;b&gt;Descripción pública&lt;/b&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringContainsString('Disponible', $html);
        self::assertStringContainsString('https://video.example/watch?a=1&amp;b=2', $html);
        self::assertStringContainsString('rel="noopener noreferrer"', $html);

        foreach ([
            'Calle secreta 123',
            'Administración Privada',
            '3000000000',
            'NOTA INTERNA PROPIEDAD',
            'NO PUBLICAR UNIDAD',
            'MATRICULA-SECRETA',
            'INT-A1',
            'Aplicar',
        ] as $privateValue) {
            self::assertStringNotContainsString($privateValue, $html);
        }
    }

    public function testDetailReturns404ForMalformedMissingOrNoLongerPublicableUnit(): void
    {
        $propertyId = $this->insertProperty('Edificio', 'Privada', 'Medellín', 'Laureles', null, null, null, true);
        $publicKey = str_repeat('b', 24);
        $unitId = $this->insertUnit($propertyId, $publicKey, 'available', true, true);
        $this->insertPhoto($unitId);
        $app = $this->app();

        self::assertSame(404, $this->request($app, '/catalog/not-a-valid-key')->getStatusCode());
        self::assertSame(404, $this->request($app, '/catalog/' . str_repeat('f', 24))->getStatusCode());

        $this->pdo->prepare('UPDATE units SET status = :status WHERE id = :id')
            ->execute(['status' => 'occupied', 'id' => $unitId]);
        self::assertSame(404, $this->request($app, '/catalog/' . $publicKey)->getStatusCode());

        $this->pdo->prepare('UPDATE units SET status = :status, monthly_price = NULL WHERE id = :id')
            ->execute(['status' => 'available', 'id' => $unitId]);
        self::assertSame(404, $this->request($app, '/catalog/' . $publicKey)->getStatusCode());
    }

    public function testPublicDetailRespectsSubfolderBasePath(): void
    {
        $propertyId = $this->insertProperty('Edificio Subfolder', 'Privada', 'Rionegro', 'Centro', null, null, null, true);
        $publicKey = str_repeat('c', 24);
        $unitId = $this->insertUnit($propertyId, $publicKey, 'available', true, true);
        $this->insertPhoto($unitId);

        $response = $this->request($this->app('/miapp'), '/miapp/catalog/' . $publicKey);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('href="/miapp/catalog"', $html);
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

    private function insertProperty(
        string $name,
        string $address,
        string $city,
        string $sector,
        ?string $administrationName,
        ?string $administrationContact,
        ?string $notes,
        bool $active,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO properties '
            . '(name, address, city, sector, administration_name, administration_contact, notes, is_active) '
            . 'VALUES (:name, :address, :city, :sector, :administration_name, :administration_contact, :notes, :is_active)',
        );
        $statement->execute([
            'name' => $name,
            'address' => $address,
            'city' => $city,
            'sector' => $sector,
            'administration_name' => $administrationName,
            'administration_contact' => $administrationContact,
            'notes' => $notes,
            'is_active' => $active ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertUnit(int $propertyId, string $publicKey, string $status, bool $isPublic, bool $complete): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements, private_notes, tower, floor, area_m2, bedrooms, bathrooms, administration_fee, registry_number) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, :status, :is_public, :monthly_price, :description, :requirements, :private_notes, :tower, :floor, :area_m2, :bedrooms, :bathrooms, :administration_fee, :registry_number)',
        );
        $statement->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'type' => 'apartment',
            'code' => 'INT-A1',
            'location_label' => 'Ubicación pública segura',
            'status' => $status,
            'is_public' => $isPublic ? 1 : 0,
            'monthly_price' => $complete ? '1500000.00' : null,
            'description' => $complete ? '<b>Descripción pública</b>' : null,
            'requirements' => $complete ? '<img src=x onerror=alert(1)>' : null,
            'private_notes' => 'NO PUBLICAR UNIDAD',
            'tower' => 'Torre 2',
            'floor' => '5',
            'area_m2' => '72.50',
            'bedrooms' => 3,
            'bathrooms' => '2.0',
            'administration_fee' => '300000.00',
            'registry_number' => 'MATRICULA-SECRETA',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertPhoto(int $unitId): void
    {
        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', :stored_name, 'image/jpeg', 1, 1)",
        )->execute([
            'unit_id' => $unitId,
            'stored_name' => 'photo-' . $unitId . '.jpg',
        ]);
    }

    private function insertVideo(int $unitId, string $url): void
    {
        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, external_url, sort_order, is_cover) "
            . "VALUES (:unit_id, 'video_link', :url, 2, 0)",
        )->execute(['unit_id' => $unitId, 'url' => $url]);
    }
}
