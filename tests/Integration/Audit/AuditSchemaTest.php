<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Audit;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuditSchemaTest extends TestCase
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
            $safe = str_replace('`', '``', (string) $table);
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safe));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
    }

    public function testMigration022CreatesOnlyApprovedAuditTableAndRecipientNullability(): void
    {
        $path = dirname(__DIR__, 3) . '/database/migrations/022_create_audit_log.sql';
        self::assertFileExists($path, 'F22 migration 022 must exist.');
        $sql = (string) file_get_contents($path);

        preg_match_all('/CREATE TABLE\s+([a-z_]+)/i', $sql, $createdTables);
        self::assertSame(['audit_log'], $createdTables[1] ?? []);
        self::assertSame([
            'id',
            'actor_user_id',
            'occurred_at',
            'action',
            'entity_type',
            'entity_id',
            'summary',
            'ip_address',
            'metadata_json',
        ], $this->columns('audit_log'));
        self::assertSame([['actor_user_id', 'users']], $this->foreignKeys('audit_log'));

        foreach (['updated_at', 'deleted_at', 'status'] as $forbidden) {
            self::assertNotContains($forbidden, $this->columns('audit_log'));
        }
        foreach (['security_events', 'login_events', 'audit_metadata', 'privacy_jobs', 'retention_rules'] as $forbiddenTable) {
            self::assertSame(0, $this->tableExists($forbiddenTable));
        }

        self::assertSame('YES', $this->nullable('notification_jobs', 'recipient_email'));
        self::assertSame('YES', $this->nullable('notification_jobs', 'recipient_name'));
        self::assertSame('NO', $this->nullable('notification_jobs', 'subject'));
        self::assertSame('NO', $this->nullable('notification_jobs', 'template_key'));
    }

    private function columns(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT column_name FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position',
        );
        $statement->execute(['table' => $table]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

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

    private function nullable(string $table, string $column): string
    {
        $statement = $this->pdo->prepare(
            'SELECT is_nullable FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1',
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (string) $statement->fetchColumn();
    }

    private function tableExists(string $table): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
        );
        $statement->execute(['table' => $table]);
        return (int) $statement->fetchColumn();
    }
}
