<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Insurance;

use AlquilaPro\Auth\Permissions;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class InsuranceSchemaTest extends TestCase
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

    public function testF13CreatesExactlyTheTwoApprovedTablesAndColumns(): void
    {
        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() "
            . "AND table_name LIKE 'insurance\\_%' ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['insurance_claims', 'insurance_policies'], array_map('strval', $tables));

        self::assertSame([
            'id', 'lease_id', 'provider_name', 'product_name', 'policy_number', 'coverage_start', 'coverage_end',
            'coverage_snapshot_json', 'documents_json', 'supersedes_policy_id', 'internal_notes', 'created_by_user_id',
            'created_at', 'updated_at', 'cancelled_by_user_id', 'cancelled_at', 'cancel_reason',
        ], $this->columns('insurance_policies'));
        self::assertSame([
            'id', 'policy_id', 'incident_on', 'description', 'claimed_amount', 'status', 'radicado', 'filed_on',
            'resolved_on', 'resolution_notes', 'checklist_snapshot_json', 'documents_json', 'created_by_user_id',
            'created_at', 'updated_at',
        ], $this->columns('insurance_claims'));

        foreach ([
            'insurance_policies' => [
                'lease_id' => 'leases.id:RESTRICT',
                'supersedes_policy_id' => 'insurance_policies.id:RESTRICT',
                'created_by_user_id' => 'users.id:SET NULL',
                'cancelled_by_user_id' => 'users.id:SET NULL',
            ],
            'insurance_claims' => [
                'policy_id' => 'insurance_policies.id:RESTRICT',
                'created_by_user_id' => 'users.id:SET NULL',
            ],
        ] as $table => $expected) {
            foreach ($expected as $column => $parent) {
                self::assertSame($parent, $this->foreignKeyTarget($table, $column), $table . '.' . $column);
            }
        }

        self::assertContains('lease_id,coverage_start,coverage_end', $this->indexColumns('insurance_policies'));
        self::assertContains('policy_number', $this->indexColumns('insurance_policies'));
        self::assertContains('policy_id,status', $this->indexColumns('insurance_claims'));
        self::assertContains('radicado', $this->indexColumns('insurance_claims'));
        self::assertSame(0, $this->tableExists('insurance_status_history'));
        self::assertSame(0, $this->tableExists('claim_checklist_items'));
    }

    public function testInsurancePermissionCatalogContainsOnlyViewAndManage(): void
    {
        self::assertSame(
            ['insurance.view', 'insurance.manage'],
            Permissions::sanitize(['insurance.view', 'insurance.manage', 'insurance.delete', 'insurance.view']),
        );
        self::assertTrue(Permissions::allows(
            ['role' => 'gestor', 'permissions' => ['insurance.view']],
            'insurance.view',
        ));
        self::assertFalse(Permissions::allows(
            ['role' => 'tenant', 'permissions' => ['insurance.view', 'insurance.manage']],
            'insurance.view',
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

    /** @return list<string> */
    private function indexColumns(string $table): array
    {
        $statement = $this->pdo->prepare(
            "SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') AS columns_list "
            . 'FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table '
            . 'AND index_name <> "PRIMARY" GROUP BY index_name ORDER BY index_name',
        );
        $statement->execute(['table' => $table]);
        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
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
