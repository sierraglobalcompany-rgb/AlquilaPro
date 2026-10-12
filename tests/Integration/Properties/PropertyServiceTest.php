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

final class PropertyServiceTest extends TestCase
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

    public function testPropertyCrudNormalizesOptionalFieldsAndPreservesUnitsWhenInactive(): void
    {
        $service = $this->service();

        $created = $service->createProperty([
            'name' => '  Edificio Sol  ',
            'address' => '  Calle 10 # 20-30  ',
            'city' => '  Medellín  ',
            'sector' => '  Laureles  ',
            'administration_name' => '  Administración Sol  ',
            'administration_contact' => '  admin@example.com  ',
            'notes' => '  Nota privada  ',
        ]);

        self::assertGreaterThan(0, $created['id']);
        self::assertSame('Edificio Sol', $created['name']);
        self::assertSame('Calle 10 # 20-30', $created['address']);
        self::assertSame('Medellín', $created['city']);
        self::assertSame('Laureles', $created['sector']);
        self::assertSame('Administración Sol', $created['administration_name']);
        self::assertSame('admin@example.com', $created['administration_contact']);
        self::assertSame('Nota privada', $created['notes']);
        self::assertTrue($created['is_active']);

        $found = $service->findProperty($created['id']);
        self::assertSame($created['id'], $found['id']);
        self::assertCount(1, $service->listProperties());

        $updated = $service->updateProperty($created['id'], [
            'name' => 'Edificio Sol Renovado',
            'address' => 'Carrera 30 # 40-50',
            'city' => 'Medellín',
            'sector' => '   ',
            'administration_name' => '',
            'administration_contact' => '',
            'notes' => '',
        ]);
        self::assertSame('Edificio Sol Renovado', $updated['name']);
        self::assertNull($updated['sector']);
        self::assertNull($updated['administration_name']);
        self::assertNull($updated['administration_contact']);
        self::assertNull($updated['notes']);

        $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) '
            . "VALUES (:property_id, '0123456789abcdef01234567', 'apartment', 'A-101', 'available')",
        )->execute(['property_id' => $created['id']]);

        $service->setPropertyActive($created['id'], false);
        self::assertFalse($service->findProperty($created['id'])['is_active']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM units')->fetchColumn());
    }

    public function testPropertyValidationRejectsMissingRequiredFieldsAndMissingMutationTarget(): void
    {
        $service = $this->service();

        foreach ([
            ['name' => '', 'address' => 'Calle 1', 'city' => 'Medellín'],
            ['name' => 'Casa', 'address' => '', 'city' => 'Medellín'],
            ['name' => 'Casa', 'address' => 'Calle 1', 'city' => ''],
        ] as $invalid) {
            try {
                $service->createProperty($invalid);
                self::fail('Missing required property data must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        self::assertNull($service->findProperty(999999));

        $this->expectException(RuntimeException::class);
        $service->updateProperty(999999, [
            'name' => 'No existe',
            'address' => 'Calle 1',
            'city' => 'Medellín',
        ]);
    }

    public function testApartmentAndParkingUseOneUnitModelWithServerGeneratedTwentyFourHexKey(): void
    {
        $service = $this->service();
        $property = $this->property($service, 'Torre Uno', 'Medellín', 'Laureles');

        $apartment = $service->createUnit($property['id'], [
            'type' => 'apartment',
            'code' => ' A-101 ',
            'status' => 'available',
            'location_label' => ' Torre 1 ',
            'monthly_price' => '1500000',
            'description' => ' Apartamento iluminado ',
            'requirements' => ' Ingresos demostrables ',
            'private_notes' => ' Nota privada ',
            'unit_number' => ' 101 ',
            'tower' => ' 1 ',
            'floor' => ' 10 ',
            'area_m2' => '72.50',
            'bedrooms' => '3',
            'bathrooms' => '2.5',
            'administration_fee' => '250000',
            'registry_number' => ' 001-123 ',
            'parking_level' => 'S1',
            'vehicle_type' => 'car',
        ]);

        self::assertSame('apartment', $apartment['type']);
        self::assertSame('A-101', $apartment['code']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{24}$/', $apartment['public_key']);
        self::assertSame('available', $apartment['status']);
        self::assertFalse($apartment['is_public']);
        self::assertSame('1500000.00', $apartment['monthly_price']);
        self::assertSame(3, $apartment['bedrooms']);
        self::assertSame('72.50', $apartment['area_m2']);
        self::assertSame('2.5', $apartment['bathrooms']);
        self::assertNull($apartment['parking_level']);
        self::assertNull($apartment['vehicle_type']);
        self::assertNull($apartment['linked_apartment_id']);

        $parking = $service->createUnit($property['id'], [
            'type' => 'parking',
            'code' => ' P-01 ',
            'status' => 'available',
            'monthly_price' => 180000,
            'parking_level' => ' S1 ',
            'dimensions' => ' 2.5 x 5 ',
            'vehicle_type' => ' carro ',
            'linked_apartment_id' => $apartment['id'],
            'bedrooms' => 9,
            'bathrooms' => 8,
            'registry_number' => 'SHOULD-BE-NULL',
        ]);

        self::assertSame('parking', $parking['type']);
        self::assertSame('P-01', $parking['code']);
        self::assertSame('S1', $parking['parking_level']);
        self::assertSame('2.5 x 5', $parking['dimensions']);
        self::assertSame('carro', $parking['vehicle_type']);
        self::assertSame($apartment['id'], $parking['linked_apartment_id']);
        self::assertNull($parking['bedrooms']);
        self::assertNull($parking['bathrooms']);
        self::assertNull($parking['registry_number']);
        self::assertCount(2, $service->listUnitsForProperty($property['id']));
    }

    public function testUnitValidationRejectsUnsupportedValuesNegativeNumbersClientPublicKeyAndDuplicateCode(): void
    {
        $service = $this->service();
        $property = $this->property($service);

        $valid = [
            'type' => 'apartment',
            'code' => 'A-101',
            'status' => 'available',
        ];

        foreach ([
            array_merge($valid, ['type' => 'house']),
            array_merge($valid, ['status' => 'reserved']),
            array_merge($valid, ['monthly_price' => -1]),
            array_merge($valid, ['area_m2' => -1]),
            array_merge($valid, ['bedrooms' => -1]),
            array_merge($valid, ['bathrooms' => -1]),
            array_merge($valid, ['administration_fee' => -1]),
            array_merge($valid, ['public_key' => '0123456789abcdef01234567']),
        ] as $invalid) {
            try {
                $service->createUnit($property['id'], $invalid);
                self::fail('Invalid unit data must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        $first = $service->createUnit($property['id'], $valid);
        self::assertGreaterThan(0, $first['id']);

        try {
            $service->createUnit($property['id'], $valid);
            self::fail('Duplicate property code must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $service->createUnit(999999, $valid);
    }

    public function testParkingLinkRequiresApartmentInSamePropertyAndAllowsManyParkingUnitsPerApartment(): void
    {
        $service = $this->service();
        $propertyA = $this->property($service, 'A');
        $propertyB = $this->property($service, 'B');
        $apartmentA = $service->createUnit($propertyA['id'], [
            'type' => 'apartment', 'code' => 'A-1', 'status' => 'available',
        ]);
        $apartmentB = $service->createUnit($propertyB['id'], [
            'type' => 'apartment', 'code' => 'B-1', 'status' => 'available',
        ]);
        $parkingA1 = $service->createUnit($propertyA['id'], [
            'type' => 'parking', 'code' => 'P-1', 'status' => 'available',
            'linked_apartment_id' => $apartmentA['id'],
        ]);
        $parkingA2 = $service->createUnit($propertyA['id'], [
            'type' => 'parking', 'code' => 'P-2', 'status' => 'available',
            'linked_apartment_id' => $apartmentA['id'],
        ]);

        self::assertSame($apartmentA['id'], $parkingA1['linked_apartment_id']);
        self::assertSame($apartmentA['id'], $parkingA2['linked_apartment_id']);

        foreach ([
            ['code' => 'P-X', 'linked_apartment_id' => $apartmentB['id']],
            ['code' => 'P-Y', 'linked_apartment_id' => $parkingA1['id']],
        ] as $invalid) {
            try {
                $service->createUnit($propertyA['id'], [
                    'type' => 'parking',
                    'code' => $invalid['code'],
                    'status' => 'available',
                    'linked_apartment_id' => $invalid['linked_apartment_id'],
                ]);
                self::fail('Invalid parking link must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        $this->expectException(RuntimeException::class);
        $service->updateUnit($parkingA1['id'], [
            'type' => 'parking',
            'code' => 'P-1',
            'status' => 'available',
            'linked_apartment_id' => $parkingA1['id'],
        ]);
    }

    public function testApartmentTypeCannotChangeAfterCreation(): void
    {
        $service = $this->service();
        $property = $this->property($service, 'Tipos apartamento');
        $apartment = $service->createUnit($property['id'], [
            'type' => 'apartment', 'code' => 'A-TYPE', 'status' => 'available',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El tipo de la unidad no se puede cambiar.');
        $service->updateUnit($apartment['id'], [
            'type' => 'parking',
            'code' => 'A-TYPE',
            'status' => 'available',
        ]);
    }

    public function testParkingTypeCannotChangeAfterCreation(): void
    {
        $service = $this->service();
        $property = $this->property($service, 'Tipos parqueadero');
        $parking = $service->createUnit($property['id'], [
            'type' => 'parking', 'code' => 'P-TYPE', 'status' => 'available',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El tipo de la unidad no se puede cambiar.');
        $service->updateUnit($parking['id'], [
            'type' => 'apartment',
            'code' => 'P-TYPE',
            'status' => 'available',
        ]);
    }

    public function testUnitReadsUpdatesAndPublicQueriesUseCompleteActiveAvailablePublishedInventory(): void
    {
        $service = $this->service();
        $property = $this->property($service, 'Catálogo', 'Medellín', 'Calasanz');
        $otherProperty = $this->property($service, 'Otra', 'Bello', 'Cabañas');

        $complete = [
            'monthly_price' => 1500000,
            'description' => 'Unidad lista para publicar',
            'requirements' => 'Ingresos demostrables',
        ];
        $apartment = $service->createUnit($property['id'], array_merge($complete, [
            'type' => 'apartment', 'code' => 'A-201', 'status' => 'available',
            'location_label' => 'Torre Norte',
        ]));
        $parking = $service->createUnit($property['id'], array_merge($complete, [
            'type' => 'parking', 'code' => 'P-201', 'status' => 'available',
        ]));
        $occupied = $service->createUnit($property['id'], array_merge($complete, [
            'type' => 'apartment', 'code' => 'A-202', 'status' => 'occupied',
        ]));
        $bello = $service->createUnit($otherProperty['id'], array_merge($complete, [
            'type' => 'apartment', 'code' => 'B-101', 'status' => 'available',
        ]));

        foreach ([$apartment, $parking, $occupied, $bello] as $unit) {
            $this->insertPhoto($unit['id']);
        }

        $this->pdo->exec(
            'UPDATE units SET is_public = 1 WHERE id IN ('
            . implode(',', [$apartment['id'], $parking['id'], $occupied['id'], $bello['id']]) . ')',
        );

        self::assertSame($apartment['id'], $service->findUnit($apartment['id'])['id']);
        self::assertNull($service->findUnit(999999));

        $updated = $service->updateUnit($apartment['id'], [
            'type' => 'apartment',
            'code' => 'A-201-R',
            'status' => 'maintenance',
            'location_label' => 'Torre Sur',
            'monthly_price' => $complete['monthly_price'],
            'description' => $complete['description'],
            'requirements' => $complete['requirements'],
        ]);
        self::assertSame('A-201-R', $updated['code']);
        self::assertSame('maintenance', $updated['status']);
        self::assertSame('Torre Sur', $updated['location_label']);

        $this->pdo->prepare("UPDATE units SET status = 'available' WHERE id = :id")
            ->execute(['id' => $apartment['id']]);

        $publicUnits = $service->listPublicUnits(null, 'Medellín', 'Calasanz');
        self::assertCount(2, $publicUnits);
        foreach ($publicUnits as $publicUnit) {
            foreach ([
                'id',
                'property_id',
                'linked_apartment_id',
                'is_public',
                'private_notes',
                'unit_number',
                'registry_number',
                'created_at',
                'updated_at',
            ] as $privateKey) {
                self::assertArrayNotHasKey($privateKey, $publicUnit);
            }
        }
        self::assertCount(1, $service->listPublicUnits('apartment', 'Medellín', 'Calasanz'));
        self::assertCount(1, $service->listPublicUnits('parking', 'Medellín', 'Calasanz'));
        self::assertCount(1, $service->listPublicUnits(null, 'Bello', null));
        self::assertSame($apartment['public_key'], $service->findPublicUnit($apartment['public_key'])['public_key']);
        self::assertNull($service->findPublicUnit($occupied['public_key']));

        $service->setPropertyActive($property['id'], false);
        self::assertCount(0, $service->listPublicUnits(null, 'Medellín', null));
        self::assertNull($service->findPublicUnit($apartment['public_key']));

        try {
            $service->listPublicUnits('house', null, null);
            self::fail('Unsupported public type filter must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $service->updateUnit(999999, [
            'type' => 'apartment', 'code' => 'MISSING', 'status' => 'available',
        ]);
    }

    private function service(): PropertyService
    {
        if (!class_exists(PropertyService::class)) {
            self::fail('PropertyService must exist before Task 2 GREEN can pass.');
        }

        return new PropertyService($this->pdo);
    }

    private function property(
        PropertyService $service,
        string $name = 'Propiedad Test',
        string $city = 'Medellín',
        ?string $sector = null,
    ): array {
        return $service->createProperty([
            'name' => $name,
            'address' => 'Calle 1 # 2-3',
            'city' => $city,
            'sector' => $sector,
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
