<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class PaymentMethodSchemaTest extends TestCase
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

    public function testF10CreatesOnlyTheApprovedPaymentMethodSchema(): void
    {
        self::assertSame(1, $this->tableExists('payment_methods'), 'payment_methods must exist after migrations');
        self::assertSame([
            'id',
            'property_id',
            'type',
            'label',
            'recipient_name',
            'institution_name',
            'account_type',
            'account_number',
            'breb_key_type',
            'breb_key_value',
            'wallet_identifier',
            'instructions',
            'qr_stored_name',
            'qr_mime_type',
            'qr_sha256',
            'qr_size_bytes',
            'sort_order',
            'is_active',
            'created_at',
            'updated_at',
        ], $this->columns('payment_methods'));

        self::assertSame([
            'id' => 'bigint',
            'property_id' => 'bigint',
            'type' => 'varchar',
            'label' => 'varchar',
            'recipient_name' => 'varchar',
            'institution_name' => 'varchar',
            'account_type' => 'varchar',
            'account_number' => 'varchar',
            'breb_key_type' => 'varchar',
            'breb_key_value' => 'varchar',
            'wallet_identifier' => 'varchar',
            'instructions' => 'varchar',
            'qr_stored_name' => 'varchar',
            'qr_mime_type' => 'varchar',
            'qr_sha256' => 'char',
            'qr_size_bytes' => 'bigint',
            'sort_order' => 'int',
            'is_active' => 'tinyint',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ], $this->dataTypes('payment_methods'));
        self::assertSame([
            'id' => 'NO',
            'property_id' => 'YES',
            'type' => 'NO',
            'label' => 'NO',
            'recipient_name' => 'YES',
            'institution_name' => 'YES',
            'account_type' => 'YES',
            'account_number' => 'YES',
            'breb_key_type' => 'YES',
            'breb_key_value' => 'YES',
            'wallet_identifier' => 'YES',
            'instructions' => 'YES',
            'qr_stored_name' => 'YES',
            'qr_mime_type' => 'YES',
            'qr_sha256' => 'YES',
            'qr_size_bytes' => 'YES',
            'sort_order' => 'NO',
            'is_active' => 'NO',
            'created_at' => 'NO',
            'updated_at' => 'YES',
        ], $this->nullability('payment_methods'));

        $types = $this->columnTypes('payment_methods');
        self::assertMatchesRegularExpression('/^bigint(?:\(\d+\))? unsigned$/', $types['id']);
        self::assertMatchesRegularExpression('/^bigint(?:\(\d+\))? unsigned$/', $types['property_id']);
        self::assertSame('varchar(32)', $types['type']);
        self::assertSame('varchar(120)', $types['label']);
        self::assertSame('varchar(190)', $types['recipient_name']);
        self::assertSame('varchar(190)', $types['institution_name']);
        self::assertSame('varchar(40)', $types['account_type']);
        self::assertSame('varchar(120)', $types['account_number']);
        self::assertSame('varchar(40)', $types['breb_key_type']);
        self::assertSame('varchar(190)', $types['breb_key_value']);
        self::assertSame('varchar(190)', $types['wallet_identifier']);
        self::assertSame('varchar(1000)', $types['instructions']);
        self::assertSame('varchar(190)', $types['qr_stored_name']);
        self::assertSame('varchar(80)', $types['qr_mime_type']);
        self::assertSame('char(64)', $types['qr_sha256']);
        self::assertMatchesRegularExpression('/^bigint(?:\(\d+\))? unsigned$/', $types['qr_size_bytes']);
        self::assertMatchesRegularExpression('/^int(?:\(\d+\))? unsigned$/', $types['sort_order']);
        self::assertSame('tinyint(1)', $types['is_active']);

        self::assertSame('payment_methods.property_id->properties.id:RESTRICT', $this->foreignKey('payment_methods', 'property_id'));
        self::assertSame([
            'payment_methods_active_order_index' => 'is_active,sort_order',
            'payment_methods_property_active_order_index' => 'property_id,is_active,sort_order',
        ], $this->indexes('payment_methods'));

        $defaults = $this->defaults('payment_methods');
        self::assertSame('1', (string) $defaults['sort_order']);
        self::assertSame('1', (string) $defaults['is_active']);
        self::assertMatchesRegularExpression('/^current_timestamp(?:\(\))?$/i', (string) $defaults['created_at']);

        foreach ([
            'payment_method_scopes',
            'lease_payment_methods',
            'property_payment_methods',
            'bank_accounts',
            'wallets',
            'breb_keys',
            'payment_providers',
            'bank_tokens',
            'payment_transactions',
            'payment_intents',
            'payment_reports',
        ] as $forbiddenTable) {
            self::assertSame(0, $this->tableExists($forbiddenTable), $forbiddenTable . ' must not be introduced by F10');
        }

        self::assertDoesNotMatchRegularExpression(
            '/(?:password|pin|otp|api_key|secret|token|webhook_secret)/i',
            implode(',', $this->columns('payment_methods')),
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

    /** @return array<string, string|null> */
    private function defaults(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT column_name, column_default FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position',
        );
        $statement->execute(['table' => $table]);

        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
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
