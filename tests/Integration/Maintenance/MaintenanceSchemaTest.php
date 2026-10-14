<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Maintenance;

use AlquilaPro\Auth\Permissions;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class MaintenanceSchemaTest extends TestCase
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

    public function testF14CreatesExactlyOneApprovedTableAndColumns(): void
    {
        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() "
            . "AND table_name = 'maintenance_requests' ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['maintenance_requests'], array_map('strval', $tables));
        self::assertSame([
            'id', 'lease_id', 'category', 'priority', 'description', 'photos_json', 'status', 'assigned_user_id',
            'damage_classification', 'resolution_notes', 'charge_id', 'created_by_user_id', 'created_at', 'updated_at',
            'resolved_by_user_id', 'resolved_at', 'cancelled_by_user_id', 'cancelled_at', 'cancel_reason',
        ], $this->columns('maintenance_requests'));

        foreach ([
            'lease_id' => 'leases.id:RESTRICT',
            'assigned_user_id' => 'users.id:SET NULL',
            'charge_id' => 'charges.id:RESTRICT',
            'created_by_user_id' => 'users.id:SET NULL',
            'resolved_by_user_id' => 'users.id:SET NULL',
            'cancelled_by_user_id' => 'users.id:SET NULL',
        ] as $column => $target) {
            self::assertSame($target, $this->foreignKeyTarget('maintenance_requests', $column), $column);
        }

        self::assertSame(1, $this->uniqueIndexCount('maintenance_requests', 'charge_id'));
        foreach (['estimated_cost', 'final_cost', 'amount', 'balance', 'visit_id', 'provider_id', 'sla_due_at'] as $excluded) {
            self::assertNotContains($excluded, $this->columns('maintenance_requests'));
        }
    }

    public function testMaintenancePermissionCatalogContainsOnlyViewAndManage(): void
    {
        self::assertSame(
            ['maintenance.view', 'maintenance.manage'],
            Permissions::sanitize(['maintenance.view', 'maintenance.manage', 'maintenance.delete', 'maintenance.view']),
        );
        self::assertTrue(Permissions::allows(
            ['role' => 'gestor', 'permissions' => ['maintenance.view']],
            'maintenance.view',
        ));
        self::assertFalse(Permissions::allows(
            ['role' => 'tenant', 'permissions' => ['maintenance.view', 'maintenance.manage']],
            'maintenance.view',
        ));
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

    private function foreignKeyTarget(string $table, string $column): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT CONCAT(k.referenced_table_name, ".", k.referenced_column_name, ":", r.delete_rule) '
            . 'FROM information_schema.key_column_usage k '
            . 'JOIN information_schema.referential_constraints r '
            . 'ON r.constraint_schema = k.constraint_schema AND r.constraint_name = k.constraint_name '
            . 'WHERE k.table_schema = DATABASE() AND k.table_name = :table AND k.column_name = :column '
            . 'AND k.referenced_table_name IS NOT NULL',
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        $target = $statement->fetchColumn();
        return is_string($target) ? $target : null;
    }

    private function uniqueIndexCount(string $table, string $column): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() '
            . 'AND table_name = :table AND column_name = :column AND non_unique = 0',
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn();
    }
}
