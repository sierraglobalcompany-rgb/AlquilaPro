<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Properties;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class PropertiesSchemaTest extends TestCase
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

    public function testF4MigrationCreatesOnlyPropertiesUnitsAndUnitMedia(): void
    {
        self::assertSame(1, $this->tableExists('properties'));
        self::assertSame(1, $this->tableExists('units'));
        self::assertSame(1, $this->tableExists('unit_media'));

        self::assertSame(0, $this->tableExists('catalog_listings'));
        self::assertSame(0, $this->tableExists('apartments'));
        self::assertSame(0, $this->tableExists('parking_spaces'));
    }

    public function testF4PublicKeyAndPropertyCodeConstraintsAreUnique(): void
    {
        $uniqueIndexes = $this->uniqueIndexColumns('units');

        self::assertContains('public_key', $uniqueIndexes);
        self::assertContains('property_id,code', $uniqueIndexes);

        $statement = $this->pdo->query(
            "SELECT character_maximum_length FROM information_schema.columns "
            . "WHERE table_schema = DATABASE() AND table_name = 'units' AND column_name = 'public_key'",
        );
        self::assertSame(24, (int) $statement->fetchColumn());
    }

    public function testF4SchemaKeepsRequiredForeignKeysAndNoParallelAvailabilityBoolean(): void
    {
        $foreignKeys = $this->foreignKeys();

        self::assertContains('units.property_id->properties.id', $foreignKeys);
        self::assertContains('units.linked_apartment_id->units.id', $foreignKeys);
        self::assertContains('unit_media.unit_id->units.id', $foreignKeys);

        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns "
            . "WHERE table_schema = DATABASE() AND table_name = 'units' AND column_name = 'available'",
        );
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    private function tableExists(string $table): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
        );
        $statement->execute(['table' => $table]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<string> */
    private function uniqueIndexColumns(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR \",\") AS columns_list '
            . 'FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND non_unique = 0 '
            . 'GROUP BY index_name ORDER BY index_name',
        );
        $statement->execute(['table' => $table]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @return list<string> */
    private function foreignKeys(): array
    {
        $statement = $this->pdo->query(
            'SELECT CONCAT(table_name, ".", column_name, "->", referenced_table_name, ".", referenced_column_name) '
            . 'FROM information_schema.key_column_usage '
            . 'WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL '
            . "AND table_name IN ('units', 'unit_media')",
        );

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }
}
