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

    private function service(): PropertyService
    {
        if (!class_exists(PropertyService::class)) {
            self::fail('PropertyService must exist before Task 2 property GREEN can pass.');
        }

        return new PropertyService($this->pdo);
    }
}
