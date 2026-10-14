<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Maintenance;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class VisitSchemaTest extends TestCase
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

    public function testF15AddsOneVisitTableWithOnlyRequestAndAuditOwnership(): void
    {
        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() "
            . "AND table_name LIKE 'maintenance\\_%' ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['maintenance_requests', 'maintenance_visits'], array_map('strval', $tables));
        self::assertSame([
            'id', 'maintenance_request_id', 'proposed_slots_json', 'selected_start_at', 'duration_minutes',
            'technician_name', 'technician_contact', 'status', 'result_notes', 'created_by_user_id', 'created_at',
            'updated_at', 'completed_by_user_id', 'completed_at', 'cancelled_by_user_id', 'cancelled_at',
            'cancel_reason',
        ], $this->columns('maintenance_visits'));
        self::assertSame('maintenance_requests.id:RESTRICT', $this->foreignKeyTarget('maintenance_request_id'));
        foreach (['lease_id', 'provider_id', 'notification_job_id', 'sent_at', 'delivered_at'] as $excluded) {
            self::assertNotContains($excluded, $this->columns('maintenance_visits'));
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
        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    private function foreignKeyTarget(string $column): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT CONCAT(k.referenced_table_name, ".", k.referenced_column_name, ":", r.delete_rule) '
            . 'FROM information_schema.key_column_usage k '
            . 'JOIN information_schema.referential_constraints r '
            . 'ON r.constraint_schema = k.constraint_schema AND r.constraint_name = k.constraint_name '
            . 'WHERE k.table_schema = DATABASE() AND k.table_name = \'maintenance_visits\' '
            . 'AND k.column_name = :column AND k.referenced_table_name IS NOT NULL',
        );
        $statement->execute(['column' => $column]);
        $target = $statement->fetchColumn();
        return is_string($target) ? $target : null;
    }
}
