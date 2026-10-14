<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Leases;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class TerminationSchemaTest extends TestCase
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
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
    }

    public function testTerminationMigrationCreatesOnlyApprovedF21TablesAndColumns(): void
    {
        $migrationPath = dirname(__DIR__, 3) . '/database/migrations/021_create_lease_termination.sql';
        self::assertFileExists($migrationPath, 'F21 migration 021 must exist.');
        $migration = (string) file_get_contents($migrationPath);

        preg_match_all('/CREATE TABLE\s+([a-z_]+)/i', $migration, $createdTables);
        self::assertSame(['good_conduct_bonuses', 'lease_liquidations'], $createdTables[1] ?? []);

        self::assertSame([
            'id',
            'lease_id',
            'amount',
            'note',
            'decision',
            'created_by_user_id',
            'created_at',
            'decided_by_user_id',
            'decided_at',
        ], $this->columns('good_conduct_bonuses'));
        self::assertSame([
            'id',
            'lease_id',
            'snapshot_json',
            'summary_note',
            'finalized_by_user_id',
            'finalized_at',
        ], $this->columns('lease_liquidations'));

        self::assertSame('UNI', $this->columnKey('good_conduct_bonuses', 'lease_id'));
        self::assertSame('UNI', $this->columnKey('lease_liquidations', 'lease_id'));

        self::assertSame([
            ['created_by_user_id', 'users'],
            ['decided_by_user_id', 'users'],
            ['lease_id', 'leases'],
        ], $this->foreignKeys('good_conduct_bonuses'));
        self::assertSame([
            ['finalized_by_user_id', 'users'],
            ['lease_id', 'leases'],
        ], $this->foreignKeys('lease_liquidations'));

        self::assertMatchesRegularExpression(
            "/CHECK\\s*\\(decision IS NULL OR decision IN \\('approved', 'denied'\\)\\)/i",
            $migration,
        );
        self::assertMatchesRegularExpression('/CHECK\s*\(amount > 0\)/i', $migration);

        foreach (['status', 'history', 'ledger', 'payment', 'payout', 'refund', 'wallet', 'deposit', 'escrow'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, strtolower($this->createTableBlock($migration, 'lease_liquidations')));
        }
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT column_name FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position',
        );
        $statement->execute(['table' => $table]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function columnKey(string $table, string $column): string
    {
        $statement = $this->pdo->prepare(
            'SELECT column_key FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1',
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (string) $statement->fetchColumn();
    }

    /** @return list<array{0:string,1:string}> */
    private function foreignKeys(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT column_name, referenced_table_name FROM information_schema.key_column_usage '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND referenced_table_name IS NOT NULL '
            . 'ORDER BY column_name',
        );
        $statement->execute(['table' => $table]);
        return $statement->fetchAll(PDO::FETCH_NUM);
    }

    private function createTableBlock(string $migration, string $table): string
    {
        $pattern = '/CREATE TABLE\\s+' . preg_quote($table, '/') . '\\s*\\((.*?)\\) ENGINE=/is';
        self::assertSame(1, preg_match($pattern, $migration, $matches));
        return (string) ($matches[1] ?? '');
    }
}
