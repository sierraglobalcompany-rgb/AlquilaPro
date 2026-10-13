<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Contracts;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class ContractSchemaTest extends TestCase
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
        foreach ([
            'contracts',
            'contract_template_versions',
            'contract_templates',
            'screening_processes',
            'candidate_documents',
            'candidate_references',
            'unit_application_requirements',
            'candidates',
            'unit_media',
            'units',
            'properties',
            'settings',
            'users',
            'schema_migrations',
        ] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
    }

    public function testF7MigrationCreatesExactlyContractEngineTables(): void
    {
        foreach (['contract_templates', 'contract_template_versions', 'contracts'] as $table) {
            self::assertSame(1, $this->tableExists($table), $table . ' must exist');
        }

        foreach (['contract_clauses', 'contract_tasks', 'contract_annexes', 'leases', 'tenants', 'contract_permissions'] as $table) {
            self::assertSame(0, $this->tableExists($table), $table . ' must not exist');
        }
    }

    public function testContractTemplatesHasExactColumnsAndUniqueName(): void
    {
        self::assertSame([
            'id',
            'name',
            'document_type',
            'is_active',
            'created_by_user_id',
            'created_at',
            'updated_at',
        ], $this->columns('contract_templates'));

        self::assertContains('name', $this->uniqueIndexes('contract_templates'));
        self::assertSame(
            'contract_templates.created_by_user_id->users.id:SET NULL',
            $this->foreignKey('contract_templates', 'created_by_user_id'),
        );
    }

    public function testTemplateVersionsAreImmutableRowsWithUniqueIncrementKey(): void
    {
        self::assertSame([
            'id',
            'template_id',
            'version_number',
            'body_template',
            'variables_json',
            'created_by_user_id',
            'created_at',
        ], $this->columns('contract_template_versions'));

        self::assertContains('template_id,version_number', $this->uniqueIndexes('contract_template_versions'));
        self::assertSame(
            'contract_template_versions.template_id->contract_templates.id:RESTRICT',
            $this->foreignKey('contract_template_versions', 'template_id'),
        );
        self::assertSame(
            'contract_template_versions.created_by_user_id->users.id:SET NULL',
            $this->foreignKey('contract_template_versions', 'created_by_user_id'),
        );
    }

    public function testContractsHasFrozenEvidenceColumnsIndexesAndNonCascadingEvidenceFks(): void
    {
        self::assertSame([
            'id',
            'candidate_id',
            'unit_id',
            'template_version_id',
            'document_type',
            'status',
            'monthly_rent',
            'administration_fee',
            'start_date',
            'end_date',
            'duration_text',
            'manual_variables_json',
            'tasks_json',
            'annexes_json',
            'resolved_variables_json',
            'snapshot_html',
            'pdf_stored_name',
            'pdf_sha256',
            'pdf_size_bytes',
            'created_by_user_id',
            'finalized_by_user_id',
            'generated_at',
            'finalized_at',
            'created_at',
            'updated_at',
        ], $this->columns('contracts'));

        $indexes = $this->indexes('contracts');
        foreach (['candidate_id', 'unit_id', 'template_version_id', 'status'] as $column) {
            self::assertContains($column, $indexes, 'contracts must index ' . $column);
        }

        self::assertSame('contracts.candidate_id->candidates.id:RESTRICT', $this->foreignKey('contracts', 'candidate_id'));
        self::assertSame('contracts.unit_id->units.id:RESTRICT', $this->foreignKey('contracts', 'unit_id'));
        self::assertSame(
            'contracts.template_version_id->contract_template_versions.id:RESTRICT',
            $this->foreignKey('contracts', 'template_version_id'),
        );
        self::assertSame('contracts.created_by_user_id->users.id:SET NULL', $this->foreignKey('contracts', 'created_by_user_id'));
        self::assertSame('contracts.finalized_by_user_id->users.id:SET NULL', $this->foreignKey('contracts', 'finalized_by_user_id'));
    }

    public function testContractJsonCapacityMatchesApprovedInputBounds(): void
    {
        self::assertSame('longtext', $this->columnType('contracts', 'manual_variables_json'));
        self::assertSame('text', $this->columnType('contracts', 'tasks_json'));
        self::assertSame('longtext', $this->columnType('contracts', 'annexes_json'));
    }

    public function testDompdfIsDirectRuntimeDependencyAndCiRequestsRequiredExtensions(): void
    {
        $root = dirname(__DIR__, 3);
        $composer = json_decode((string) file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('^3.1.6', $composer['require']['dompdf/dompdf'] ?? null);

        $lock = json_decode((string) file_get_contents($root . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
        $dompdf = null;
        foreach ($lock['packages'] ?? [] as $package) {
            if (($package['name'] ?? null) === 'dompdf/dompdf') {
                $dompdf = $package;
                break;
            }
        }
        self::assertIsArray($dompdf);
        self::assertMatchesRegularExpression('/^v?3\./', (string) ($dompdf['version'] ?? ''));

        $workflow = (string) file_get_contents($root . '/.github/workflows/ci.yml');
        self::assertMatchesRegularExpression('/extensions:\s*pdo,\s*pdo_mysql,\s*mbstring,\s*dom,\s*gd,\s*fileinfo/', $workflow);
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

    private function columnType(string $table, string $column): string
    {
        $statement = $this->pdo->prepare(
            'SELECT data_type FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1',
        );
        $statement->execute(['table' => $table, 'column' => $column]);

        return (string) $statement->fetchColumn();
    }

    /** @return list<string> */
    private function uniqueIndexes(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ",") '
            . 'FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND non_unique = 0 AND index_name <> "PRIMARY" '
            . 'GROUP BY index_name ORDER BY index_name',
        );
        $statement->execute(['table' => $table]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @return list<string> */
    private function indexes(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ",") '
            . 'FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND index_name <> "PRIMARY" '
            . 'GROUP BY index_name ORDER BY index_name',
        );
        $statement->execute(['table' => $table]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
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
}
