<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class ChargeSchemaTest extends TestCase
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

    public function testF9CreatesOnlyTheApprovedChargeSchema(): void
    {
        self::assertSame(1, $this->tableExists('charges'), 'charges must exist after migrations');
        self::assertSame([
            'id',
            'lease_id',
            'type',
            'period_month',
            'due_date',
            'amount',
            'description',
            'created_by_user_id',
            'created_at',
            'voided_by_user_id',
            'voided_at',
            'void_reason',
        ], $this->columns('charges'));

        $expectedTypes = [
            'id' => 'bigint',
            'lease_id' => 'bigint',
            'type' => 'varchar',
            'period_month' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal',
            'description' => 'varchar',
            'created_by_user_id' => 'bigint',
            'created_at' => 'datetime',
            'voided_by_user_id' => 'bigint',
            'voided_at' => 'datetime',
            'void_reason' => 'varchar',
        ];
        self::assertSame($expectedTypes, $this->dataTypes('charges'));
        self::assertSame([
            'id' => 'NO',
            'lease_id' => 'NO',
            'type' => 'NO',
            'period_month' => 'YES',
            'due_date' => 'NO',
            'amount' => 'NO',
            'description' => 'YES',
            'created_by_user_id' => 'YES',
            'created_at' => 'NO',
            'voided_by_user_id' => 'YES',
            'voided_at' => 'YES',
            'void_reason' => 'YES',
        ], $this->nullability('charges'));

        $types = $this->columnTypes('charges');
        self::assertMatchesRegularExpression('/^bigint(?:\(\d+\))? unsigned$/', $types['id']);
        self::assertMatchesRegularExpression('/^bigint(?:\(\d+\))? unsigned$/', $types['lease_id']);
        self::assertSame('varchar(32)', $types['type']);
        self::assertSame('decimal(12,2)', $types['amount']);
        self::assertSame('varchar(500)', $types['description']);
        self::assertMatchesRegularExpression('/^bigint(?:\(\d+\))? unsigned$/', $types['created_by_user_id']);
        self::assertMatchesRegularExpression('/^bigint(?:\(\d+\))? unsigned$/', $types['voided_by_user_id']);
        self::assertSame('varchar(500)', $types['void_reason']);

        self::assertSame('charges.lease_id->leases.id:RESTRICT', $this->foreignKey('charges', 'lease_id'));
        self::assertSame('charges.created_by_user_id->users.id:SET NULL', $this->foreignKey('charges', 'created_by_user_id'));
        self::assertSame('charges.voided_by_user_id->users.id:SET NULL', $this->foreignKey('charges', 'voided_by_user_id'));

        self::assertSame([
            'charges_created_by_user_index' => 'created_by_user_id',
            'charges_lease_due_index' => 'lease_id,due_date',
            'charges_lease_period_type_index' => 'lease_id,period_month,type',
            'charges_voided_by_user_index' => 'voided_by_user_id',
        ], $this->indexes('charges'));

        foreach ([
            'payments',
            'payment_allocations',
            'payment_reports',
            'receivables',
            'balances',
            'late_fees',
            'charge_periods',
            'charge_status_history',
            'ledger_entries',
        ] as $futureTable) {
            self::assertSame(0, $this->tableExists($futureTable), $futureTable . ' must not exist in F9');
        }

        foreach ([
            'status',
            'balance',
            'outstanding_amount',
            'paid_amount',
            'remaining_amount',
            'days_overdue',
            'currency',
        ] as $derivedOrFutureColumn) {
            self::assertNotContains($derivedOrFutureColumn, $this->columns('charges'));
        }
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

    /** @return array<string, string> */
    private function dataTypes(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT column_name, data_type FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position',
        );
        $statement->execute(['table' => $table]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** @return array<string, string> */
    private function nullability(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT column_name, is_nullable FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position',
        );
        $statement->execute(['table' => $table]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** @return array<string, string> */
    private function columnTypes(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT column_name, column_type FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position',
        );
        $statement->execute(['table' => $table]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_KEY_PAIR));
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

    /** @return array<string, string> */
    private function indexes(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ",") '
            . 'FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND index_name <> "PRIMARY" '
            . 'GROUP BY index_name ORDER BY index_name',
        );
        $statement->execute(['table' => $table]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_KEY_PAIR));
    }
}
