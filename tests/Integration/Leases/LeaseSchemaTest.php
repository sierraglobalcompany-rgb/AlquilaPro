<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Leases;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class LeaseSchemaTest extends TestCase
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
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $safeTable = str_replace('`', '``', (string) $table);
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safeTable));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
    }

    public function testF8CreatesOnlyTheApprovedLeasePersistence(): void
    {
        self::assertSame(1, $this->tableExists('leases'), 'leases must exist');
        self::assertSame([
            'id',
            'contract_id',
            'user_id',
            'activated_by_user_id',
            'activated_at',
            'ended_by_user_id',
            'ended_at',
        ], $this->columns('leases'));

        foreach (['tenants', 'lease_units', 'tenant_users', 'tenant_sessions'] as $table) {
            self::assertSame(0, $this->tableExists($table), $table . ' must not exist');
        }

        foreach ([
            'status',
            'unit_id',
            'candidate_id',
            'monthly_rent',
            'administration_fee',
            'start_date',
            'end_date',
            'created_at',
            'updated_at',
        ] as $column) {
            self::assertNotContains($column, $this->columns('leases'), $column . ' must not be duplicated in leases');
        }
    }

    public function testLeaseIndexesAndForeignKeysMatchTheApprovedOwnershipModel(): void
    {
        self::assertContains('contract_id', $this->uniqueIndexes('leases'));

        $indexes = $this->indexes('leases');
        foreach (['user_id', 'activated_by_user_id', 'ended_by_user_id'] as $column) {
            self::assertContains($column, $indexes, 'leases must index ' . $column);
        }

        self::assertSame('leases.contract_id->contracts.id:RESTRICT', $this->foreignKey('leases', 'contract_id'));
        self::assertSame('leases.user_id->users.id:RESTRICT', $this->foreignKey('leases', 'user_id'));
        self::assertSame(
            'leases.activated_by_user_id->users.id:SET NULL',
            $this->foreignKey('leases', 'activated_by_user_id'),
        );
        self::assertSame(
            'leases.ended_by_user_id->users.id:SET NULL',
            $this->foreignKey('leases', 'ended_by_user_id'),
        );
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
    private function columns(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT column_name FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position',
        );
        $statement->execute(['table' => $table]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @return list<string> */
    private function uniqueIndexes(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ",") '
            . 'FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND non_unique = 0 AND index_name <> "PRIMARY" '
            . 'GROUP BY index_name ORDER BY index_name',
        );
        $statement->execute(['table' => $table]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @return list<string> */
    private function indexes(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ",") '
            . 'FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND index_name <> "PRIMARY" '
            . 'GROUP BY index_name ORDER BY index_name',
        );
        $statement->execute(['table' => $table]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    private function foreignKey(string $table, string $column): string
    {
        $statement = $this->pdo->prepare(
            'SELECT CONCAT(k.table_name, ".", k.column_name, "->", k.referenced_table_name, ".", k.referenced_column_name, ":", r.delete_rule) '
            . 'FROM information_schema.key_column_usage k '
            . 'INNER JOIN information_schema.referential_constraints r '
            . 'ON r.constraint_schema = k.constraint_schema AND r.constraint_name = k.constraint_name AND r.table_name = k.table_name '
            . 'WHERE k.table_schema = DATABASE() AND k.table_name = :table AND k.column_name = :column '
            . 'AND k.referenced_table_name IS NOT NULL LIMIT 1',
        );
        $statement->execute(['table' => $table, 'column' => $column]);

        return (string) $statement->fetchColumn();
    }
}
