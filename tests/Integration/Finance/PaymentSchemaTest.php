<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class PaymentSchemaTest extends TestCase
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

    public function testF11AddsExactlyPaymentsAndPaymentAllocationsWithFrozenColumnsAndConstraints(): void
    {
        self::assertSame(1, $this->tableExists('payments'));
        self::assertSame(1, $this->tableExists('payment_allocations'));
        self::assertSame(2, $this->tableCount([
            'payments',
            'payment_allocations',
            'payment_reports',
            'payment_status_history',
            'payment_receipts',
            'ledger_entries',
            'credits',
            'refunds',
        ]));

        self::assertSame([
            'id', 'lease_id', 'payment_method_id', 'amount', 'paid_on', 'paid_time',
            'payment_method_snapshot_json', 'reported_at', 'receipt_stored_name', 'receipt_mime_type',
            'receipt_sha256', 'receipt_size_bytes', 'reconciled_by_user_id', 'reconciled_at',
            'rejected_by_user_id', 'rejected_at', 'rejection_reason',
        ], $this->columns('payments'));
        self::assertSame([
            'id', 'payment_id', 'charge_id', 'amount',
        ], $this->columns('payment_allocations'));

        $paymentTypes = $this->columnTypes('payments');
        self::assertSame('decimal(12,2)', $paymentTypes['amount']);
        self::assertSame('date', $paymentTypes['paid_on']);
        self::assertSame('time', $paymentTypes['paid_time']);
        self::assertSame('text', $paymentTypes['payment_method_snapshot_json']);
        self::assertSame('datetime', $paymentTypes['reported_at']);
        self::assertSame('char(64)', $paymentTypes['receipt_sha256']);
        self::assertSame('bigint unsigned', preg_replace('/\(\d+\)/', '', $paymentTypes['receipt_size_bytes']));
        self::assertSame('varchar(500)', $paymentTypes['rejection_reason']);
        self::assertMatchesRegularExpression(
            '/^current_timestamp(?:\(\))?$/i',
            (string) $this->default('payments', 'reported_at'),
        );

        $allocationTypes = $this->columnTypes('payment_allocations');
        self::assertSame('decimal(12,2)', $allocationTypes['amount']);
        self::assertSame('decimal(12,2)', $paymentTypes['amount']);

        self::assertSame('payments.lease_id->leases.id:RESTRICT', $this->foreignKey('payments', 'lease_id'));
        self::assertSame('payments.payment_method_id->payment_methods.id:RESTRICT', $this->foreignKey('payments', 'payment_method_id'));
        self::assertSame('payments.reconciled_by_user_id->users.id:SET NULL', $this->foreignKey('payments', 'reconciled_by_user_id'));
        self::assertSame('payments.rejected_by_user_id->users.id:SET NULL', $this->foreignKey('payments', 'rejected_by_user_id'));
        self::assertSame('payment_allocations.payment_id->payments.id:RESTRICT', $this->foreignKey('payment_allocations', 'payment_id'));
        self::assertSame('payment_allocations.charge_id->charges.id:RESTRICT', $this->foreignKey('payment_allocations', 'charge_id'));
        self::assertSame([
            'payments_lease_reported_index' => 'lease_id,reported_at',
            'payments_method_date_amount_index' => 'payment_method_id,paid_on,amount',
            'payments_pending_index' => 'reconciled_at,rejected_at,reported_at',
            'payments_receipt_sha_index' => 'receipt_sha256',
            'payments_reconciled_by_index' => 'reconciled_by_user_id',
            'payments_rejected_by_index' => 'rejected_by_user_id',
        ], $this->indexes('payments'));
        self::assertSame([
            'payment_allocations_charge_index' => 'charge_id',
            'payment_allocations_payment_charge_unique' => 'payment_id,charge_id',
        ], $this->indexes('payment_allocations'));

        foreach (['status', 'balance', 'is_duplicate', 'duplicate_of_id', 'reported_by_user_id'] as $forbiddenColumn) {
            self::assertNotContains($forbiddenColumn, $this->columns('payments'));
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

    /** @param list<string> $tables */
    private function tableCount(array $tables): int
    {
        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() '
            . 'AND table_name IN (' . $placeholders . ')',
        );
        $statement->execute($tables);

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
    private function columnTypes(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT column_name, column_type FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position',
        );
        $statement->execute(['table' => $table]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    private function default(string $table, string $column): mixed
    {
        $statement = $this->pdo->prepare(
            'SELECT column_default FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
        );
        $statement->execute(['table' => $table, 'column' => $column]);

        return $statement->fetchColumn();
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
