<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Community;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class CommunitySchemaTest extends TestCase
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

    public function testCommunitySchemaHasOneIncidentTableWithAuditableLifecycleFields(): void
    {
        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() "
            . "AND (table_name LIKE 'community_%' OR table_name LIKE 'incident_%') ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['community_incidents'], $tables);

        $columns = $this->pdo->query(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() "
            . "AND table_name = 'community_incidents' ORDER BY ordinal_position",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([
            'id', 'lease_id', 'category', 'occurred_on', 'description', 'status', 'resolution_notes',
            'created_by_user_id', 'created_at', 'updated_at', 'closed_by_user_id', 'closed_at',
            'voided_by_user_id', 'voided_at', 'void_reason',
        ], $columns);

        $foreignKeys = $this->pdo->query(
            "SELECT column_name, referenced_table_name FROM information_schema.key_column_usage "
            . "WHERE table_schema = DATABASE() AND table_name = 'community_incidents' "
            . 'AND referenced_table_name IS NOT NULL ORDER BY column_name',
        )->fetchAll(PDO::FETCH_NUM);
        self::assertSame([
            ['closed_by_user_id', 'users'],
            ['created_by_user_id', 'users'],
            ['lease_id', 'leases'],
            ['voided_by_user_id', 'users'],
        ], $foreignKeys);

        $indexes = $this->pdo->query(
            "SELECT DISTINCT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() "
            . "AND table_name = 'community_incidents' ORDER BY index_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([
            'community_incidents_closed_by_user_index',
            'community_incidents_lease_occurred_index',
            'community_incidents_status_created_index',
            'community_incidents_user_created_index',
            'community_incidents_voided_by_user_index',
            'PRIMARY',
        ], $indexes);
    }
}
