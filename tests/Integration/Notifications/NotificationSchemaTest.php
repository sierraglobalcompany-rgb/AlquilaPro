<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Notifications;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class NotificationSchemaTest extends TestCase
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

    public function testMigrationAddsOneMinimalQueueTableWithUniqueDedupeAndNoStatus(): void
    {
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables "
            . "WHERE table_schema = DATABASE() AND table_name LIKE 'notification_%' ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['notification_jobs'], $tables);

        $columns = $this->pdo->query(
            "SELECT column_name FROM information_schema.columns "
            . "WHERE table_schema = DATABASE() AND table_name = 'notification_jobs' ORDER BY ordinal_position",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([
            'id', 'type', 'recipient_email', 'recipient_name', 'subject', 'template_key', 'payload_json',
            'scheduled_at', 'dedupe_key', 'attempt_count', 'last_attempt_at', 'claim_until', 'sent_at', 'failed_at',
            'last_error', 'created_at',
        ], $columns);
        self::assertNotContains('status', $columns);

        $uniqueDedupeIndex = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() "
            . "AND table_name = 'notification_jobs' AND index_name = 'notification_jobs_dedupe_uq' "
            . "AND column_name = 'dedupe_key' AND non_unique = 0",
        )->fetchColumn();
        self::assertSame(1, (int) $uniqueDedupeIndex);
    }
}
