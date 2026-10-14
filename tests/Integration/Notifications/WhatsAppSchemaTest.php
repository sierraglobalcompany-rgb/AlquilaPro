<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Notifications;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class WhatsAppSchemaTest extends TestCase
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
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', str_replace('`', '``', (string) $table)));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
    }

    public function testMigrationAddsOneActionTableWithSnapshotAndOpenAuditOnly(): void
    {
        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables "
            . "WHERE table_schema = DATABASE() AND table_name LIKE 'whatsapp_%' ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['whatsapp_actions'], $tables);

        $columns = $this->columns();
        self::assertSame([
            'id', 'context_type', 'context_id', 'recipient_phone_snapshot', 'template_key', 'message_snapshot',
            'url_snapshot', 'created_by_user_id', 'created_at', 'opened_by_user_id', 'opened_at',
        ], $columns);
        foreach (['status', 'sent_at', 'delivered_at', 'read_at', 'provider_message_id'] as $forbidden) {
            self::assertNotContains($forbidden, $columns);
        }

        self::assertSame([
            'created_by_user_id' => 'users',
            'opened_by_user_id' => 'users',
        ], $this->userForeignKeys());
    }

    /** @return list<string> */
    private function columns(): array
    {
        return array_values(array_map('strval', $this->pdo->query(
            "SELECT column_name FROM information_schema.columns "
            . "WHERE table_schema = DATABASE() AND table_name = 'whatsapp_actions' ORDER BY ordinal_position",
        )->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @return array<string,string> */
    private function userForeignKeys(): array
    {
        $rows = $this->pdo->query(
            "SELECT column_name, referenced_table_name FROM information_schema.key_column_usage "
            . "WHERE table_schema = DATABASE() AND table_name = 'whatsapp_actions' "
            . 'AND referenced_table_name IS NOT NULL ORDER BY column_name',
        )->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['column_name']] = (string) $row['referenced_table_name'];
        }
        ksort($result);
        return $result;
    }
}
