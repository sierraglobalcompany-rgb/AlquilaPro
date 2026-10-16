<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Properties;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Properties\PropertyService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PublicLinkedParkingCountTest extends TestCase
{
    private PDO $pdo;

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
    }

    public function testPublicDtoIncludesLinkedParkingCountWithoutExposingParkingLinkDetails(): void
    {
        $service = new PropertyService($this->pdo);
        $property = $service->createProperty([
            'name' => 'Edificio R3',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);

        $complete = [
            'monthly_price' => 1500000,
            'description' => 'Unidad pública R3',
            'requirements' => 'Ingresos demostrables',
        ];

        $apartment = $service->createUnit($property['id'], array_merge($complete, [
            'type' => 'apartment',
            'code' => 'A-R3',
            'status' => 'available',
        ]));
        $otherApartment = $service->createUnit($property['id'], [
            'type' => 'apartment',
            'code' => 'A-OTHER',
            'status' => 'available',
        ]);

        $service->createUnit($property['id'], [
            'type' => 'parking',
            'code' => 'P-R3-1',
            'status' => 'occupied',
            'linked_apartment_id' => $apartment['id'],
        ]);
        $service->createUnit($property['id'], [
            'type' => 'parking',
            'code' => 'P-R3-2',
            'status' => 'inactive',
            'linked_apartment_id' => $apartment['id'],
        ]);
        $service->createUnit($property['id'], [
            'type' => 'parking',
            'code' => 'P-OTHER',
            'status' => 'available',
            'linked_apartment_id' => $otherApartment['id'],
        ]);

        $publicParking = $service->createUnit($property['id'], array_merge($complete, [
            'type' => 'parking',
            'code' => 'P-PUBLIC',
            'status' => 'available',
        ]));

        foreach ([$apartment, $publicParking] as $unit) {
            $this->insertPhoto($unit['id']);
        }
        $this->pdo->exec(
            'UPDATE units SET is_public = 1 WHERE id IN (' . $apartment['id'] . ',' . $publicParking['id'] . ')',
        );

        $publicUnits = $service->listPublicUnits(null, 'Medellín', 'Calasanz');
        self::assertCount(2, $publicUnits);

        $byKey = [];
        foreach ($publicUnits as $unit) {
            $byKey[$unit['public_key']] = $unit;
        }

        $publicApartment = $byKey[$apartment['public_key']];
        self::assertArrayHasKey('linked_parking_count', $publicApartment);
        self::assertSame(2, $publicApartment['linked_parking_count']);
        self::assertArrayNotHasKey('linked_apartment_id', $publicApartment);
        self::assertArrayNotHasKey('private_notes', $publicApartment);

        $parkingDto = $byKey[$publicParking['public_key']];
        self::assertArrayHasKey('linked_parking_count', $parkingDto);
        self::assertSame(0, $parkingDto['linked_parking_count']);

        $detail = $service->findPublicUnit($apartment['public_key']);
        self::assertIsArray($detail);
        self::assertArrayHasKey('linked_parking_count', $detail);
        self::assertSame(2, $detail['linked_parking_count']);
        self::assertArrayNotHasKey('linked_apartment_id', $detail);
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
