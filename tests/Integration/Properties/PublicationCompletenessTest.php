<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Properties;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Properties\PropertyService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicationCompletenessTest extends TestCase
{
    private PDO $pdo;
    private PropertyService $service;

    protected function setUp(): void
    {
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

        $this->service = new PropertyService($this->pdo);
    }

    public function testPublicationRequiresPriceDescriptionRequirementsAndPhotoWithoutPersistingFlag(): void
    {
        $cases = [
            'price' => ['monthly_price' => null, 'description' => 'Apartamento iluminado', 'requirements' => 'Ingresos demostrables', 'photo' => true],
            'description' => ['monthly_price' => 1500000, 'description' => null, 'requirements' => 'Ingresos demostrables', 'photo' => true],
            'requirements' => ['monthly_price' => 1500000, 'description' => 'Apartamento iluminado', 'requirements' => null, 'photo' => true],
            'photo' => ['monthly_price' => 1500000, 'description' => 'Apartamento iluminado', 'requirements' => 'Ingresos demostrables', 'photo' => false],
        ];

        foreach ($cases as $name => $case) {
            $property = $this->property('Propiedad ' . $name);
            $unit = $this->service->createUnit($property['id'], [
                'type' => 'apartment',
                'code' => 'A-' . strtoupper(substr($name, 0, 3)),
                'status' => 'available',
                'monthly_price' => $case['monthly_price'],
                'description' => $case['description'],
                'requirements' => $case['requirements'],
            ]);

            if ($case['photo']) {
                $this->insertPhoto($unit['id']);
            }

            $rejected = false;
            try {
                $this->service->updateUnit($unit['id'], [
                    'type' => 'apartment',
                    'code' => $unit['code'],
                    'status' => 'available',
                    'monthly_price' => $case['monthly_price'],
                    'description' => $case['description'],
                    'requirements' => $case['requirements'],
                    'is_public' => 1,
                ]);
            } catch (RuntimeException $exception) {
                $rejected = true;
                self::assertNotSame('', $exception->getMessage());
            }

            self::assertTrue($rejected, 'Incomplete unit must not be publishable: ' . $name);
            self::assertFalse($this->service->findUnit($unit['id'])['is_public']);
        }
    }

    public function testCompleteUnitPublishesAndPublicReadsDisappearImmediatelyWhenStateChanges(): void
    {
        $property = $this->property('Catálogo');
        $unit = $this->service->createUnit($property['id'], [
            'type' => 'apartment',
            'code' => 'A-501',
            'status' => 'available',
            'monthly_price' => 1750000,
            'description' => 'Apartamento listo para habitar',
            'requirements' => 'Ingresos demostrables y referencias',
        ]);
        $this->insertPhoto($unit['id']);

        $published = $this->service->updateUnit($unit['id'], [
            'type' => 'apartment',
            'code' => 'A-501',
            'status' => 'available',
            'monthly_price' => 1750000,
            'description' => 'Apartamento listo para habitar',
            'requirements' => 'Ingresos demostrables y referencias',
            'is_public' => 1,
        ]);

        self::assertTrue($published['is_public']);
        self::assertTrue($this->service->isUnitPublicable($unit['id']));
        self::assertCount(1, $this->service->listPublicUnits(null, null, null));
        self::assertNotNull($this->service->findPublicUnit($unit['public_key']));

        $this->service->updateUnit($unit['id'], [
            'type' => 'apartment',
            'code' => 'A-501',
            'status' => 'maintenance',
            'monthly_price' => 1750000,
            'description' => 'Apartamento listo para habitar',
            'requirements' => 'Ingresos demostrables y referencias',
        ]);
        self::assertCount(0, $this->service->listPublicUnits(null, null, null));
        self::assertNull($this->service->findPublicUnit($unit['public_key']));

        $this->service->updateUnit($unit['id'], [
            'type' => 'apartment',
            'code' => 'A-501',
            'status' => 'available',
            'monthly_price' => 1750000,
            'description' => 'Apartamento listo para habitar',
            'requirements' => 'Ingresos demostrables y referencias',
        ]);
        self::assertCount(1, $this->service->listPublicUnits(null, null, null));

        $this->service->setPropertyActive($property['id'], false);
        self::assertFalse($this->service->isUnitPublicable($unit['id']));
        self::assertCount(0, $this->service->listPublicUnits(null, null, null));
        self::assertNull($this->service->findPublicUnit($unit['public_key']));
    }

    private function property(string $name): array
    {
        return $this->service->createProperty([
            'name' => $name,
            'address' => 'Calle 1 # 2-3',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);
    }

    private function insertPhoto(int $unitId): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', :stored_name, 'image/png', 1, 1)",
        );
        $statement->execute([
            'unit_id' => $unitId,
            'stored_name' => 'photo-' . bin2hex(random_bytes(16)) . '.png',
        ]);
    }
}
