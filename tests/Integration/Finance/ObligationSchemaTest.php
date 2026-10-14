<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class ObligationSchemaTest extends TestCase
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

    public function testF12CreatesOnlyTheApprovedObligationSchema(): void
    {
        self::assertSame(1, $this->tableExists('lease_obligations'), 'lease_obligations must exist');
        self::assertSame([
            'id',
            'lease_id',
            'type',
            'service_name',
            'description',
            'period_month',
            'issued_on',
            'due_on',
            'amount',
            'external_reference',
            'source_support_stored_name',
            'source_support_mime_type',
            'source_support_sha256',
            'source_support_size_bytes',
            'settlement_support_stored_name',
            'settlement_support_mime_type',
            'settlement_support_sha256',
            'settlement_support_size_bytes',
            'charge_id',
            'created_by_user_id',
            'created_at',
            'updated_at',
            'settled_by_user_id',
            'settled_at',
            'voided_by_user_id',
            'voided_at',
            'void_reason',
        ], $this->columns('lease_obligations'));

        self::assertSame([
            'id' => 'bigint',
            'lease_id' => 'bigint',
            'type' => 'varchar',
            'service_name' => 'varchar',
            'description' => 'varchar',
            'period_month' => 'date',
            'issued_on' => 'date',
            'due_on' => 'date',
            'amount' => 'decimal',
            'external_reference' => 'varchar',
            'source_support_stored_name' => 'varchar',
            'source_support_mime_type' => 'varchar',
            'source_support_sha256' => 'char',
            'source_support_size_bytes' => 'bigint',
            'settlement_support_stored_name' => 'varchar',
            'settlement_support_mime_type' => 'varchar',
            'settlement_support_sha256' => 'char',
            'settlement_support_size_bytes' => 'bigint',
            'charge_id' => 'bigint',
            'created_by_user_id' => 'bigint',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'settled_by_user_id' => 'bigint',
            'settled_at' => 'datetime',
            'voided_by_user_id' => 'bigint',
            'voided_at' => 'datetime',
            'void_reason' => 'varchar',
        ], $this->dataTypes('lease_obligations'));

        self::assertSame([
            'id' => 'NO',
            'lease_id' => 'NO',
            'type' => 'NO',
            'service_name' => 'YES',
            'description' => 'NO',
            'period_month' => 'YES',
            'issued_on' => 'YES',
            'due_on' => 'YES',
            'amount' => 'NO',
            'external_reference' => 'YES',
            'source_support_stored_name' => 'YES',
            'source_support_mime_type' => 'YES',
            'source_support_sha256' => 'YES',
            'source_support_size_bytes' => 'YES',
            'settlement_support_stored_name' => 'YES',
            'settlement_support_mime_type' => 'YES',
            'settlement_support_sha256' => 'YES',
            'settlement_support_size_bytes' => 'YES',
            'charge_id' => 'YES',
            'created_by_user_id' => 'YES',
            'created_at' => 'NO',
            'updated_at' => 'YES',
            'settled_by_user_id' => 'YES',
            'settled_at' => 'YES',
            'voided_by_user_id' => 'YES',
            'voided_at' => 'YES',
            'void_reason' => 'YES',
        ], $this->nullability('lease_obligations'));

        $types = $this->columnTypes('lease_obligations');
        foreach ([
            'id', 'lease_id', 'source_support_size_bytes', 'settlement_support_size_bytes',
            'charge_id', 'created_by_user_id', 'settled_by_user_id', 'voided_by_user_id',
        ] as $unsignedColumn) {
            self::assertMatchesRegularExpression('/^bigint(?:\(\d+\))? unsigned$/', $types[$unsignedColumn]);
        }
        self::assertSame('varchar(32)', $types['type']);
        self::assertSame('varchar(120)', $types['service_name']);
        self::assertSame('varchar(500)', $types['description']);
        self::assertSame('decimal(12,2)', $types['amount']);
        self::assertSame('varchar(190)', $types['external_reference']);
        self::assertSame('varchar(190)', $types['source_support_stored_name']);
        self::assertSame('varchar(80)', $types['source_support_mime_type']);
        self::assertSame('char(64)', $types['source_support_sha256']);
        self::assertSame('varchar(190)', $types['settlement_support_stored_name']);
        self::assertSame('varchar(80)', $types['settlement_support_mime_type']);
        self::assertSame('char(64)', $types['settlement_support_sha256']);
        self::assertSame('varchar(500)', $types['void_reason']);

        self::assertSame('lease_obligations.lease_id->leases.id:RESTRICT', $this->foreignKey('lease_obligations', 'lease_id'));
        self::assertSame('lease_obligations.charge_id->charges.id:RESTRICT', $this->foreignKey('lease_obligations', 'charge_id'));
        foreach (['created_by_user_id', 'settled_by_user_id', 'voided_by_user_id'] as $actorColumn) {
            self::assertSame(
                'lease_obligations.' . $actorColumn . '->users.id:SET NULL',
                $this->foreignKey('lease_obligations', $actorColumn),
            );
        }

        self::assertSame([
            'lease_obligations_charge_unique' => 'charge_id',
            'lease_obligations_created_by_user_index' => 'created_by_user_id',
            'lease_obligations_lease_due_index' => 'lease_id,due_on',
            'lease_obligations_lease_period_type_index' => 'lease_id,period_month,type',
            'lease_obligations_settled_by_user_index' => 'settled_by_user_id',
            'lease_obligations_voided_by_user_index' => 'voided_by_user_id',
        ], $this->indexes('lease_obligations'));

        foreach ([
            'utility_periods',
            'utility_bills',
            'ph_fines',
            'obligation_documents',
            'obligation_payments',
            'obligation_status_history',
        ] as $parallelAuthority) {
            self::assertSame(0, $this->tableExists($parallelAuthority), $parallelAuthority . ' must not exist in F12');
        }

        foreach ([
            'status', 'balance', 'paid_amount', 'tenant_payment_id', 'provider_id',
            'reminder_sent_at', 'notification_status',
        ] as $forbiddenColumn) {
            self::assertNotContains($forbiddenColumn, $this->columns('lease_obligations'));
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
