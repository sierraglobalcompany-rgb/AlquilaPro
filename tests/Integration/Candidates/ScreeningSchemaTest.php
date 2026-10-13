<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class ScreeningSchemaTest extends TestCase
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

    public function testF6MigrationCreatesOnlyScreeningProcesses(): void
    {
        self::assertSame(1, $this->tableExists('screening_processes'));

        foreach (['sura_screenings', 'sura_processes', 'screening_status_history', 'screening_providers', 'screening_supports'] as $table) {
            self::assertSame(0, $this->tableExists($table), $table . ' must not exist');
        }
    }

    public function testScreeningProcessesHasExactColumns(): void
    {
        self::assertSame([
            'id',
            'candidate_id',
            'provider',
            'product',
            'status',
            'contact_email',
            'portal_url',
            'result',
            'notes',
            'support_reference',
            'responsible_user_id',
            'started_at',
            'result_at',
            'created_at',
            'updated_at',
        ], $this->columns('screening_processes'));
    }

    public function testScreeningCandidateIsUniqueAndCascadesWithCandidate(): void
    {
        self::assertContains('candidate_id', $this->uniqueIndexColumns('screening_processes'));
        self::assertSame(
            'screening_processes.candidate_id->candidates.id:CASCADE',
            $this->foreignKeyWithDeleteRule('screening_processes', 'candidate_id'),
        );
    }

    public function testScreeningResponsibleUserIsNullableAndSetsNullOnUserDelete(): void
    {
        $nullable = $this->pdo->prepare(
            'SELECT is_nullable FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
        );
        $nullable->execute([
            'table' => 'screening_processes',
            'column' => 'responsible_user_id',
        ]);

        self::assertSame('YES', $nullable->fetchColumn());
        self::assertSame(
            'screening_processes.responsible_user_id->users.id:SET NULL',
            $this->foreignKeyWithDeleteRule('screening_processes', 'responsible_user_id'),
        );
    }

    public function testF6DoesNotAlterCandidateStatusOrAddPermissionsTables(): void
    {
        self::assertSame('varchar', strtolower((string) $this->columnType('candidates', 'status')));
        self::assertSame(0, $this->tableExists('screening_permissions'));
        self::assertSame(0, $this->tableExists('provider_permissions'));
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

    /** @return list<string> */
    private function uniqueIndexColumns(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR \',\') AS columns_list '
            . 'FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND non_unique = 0 '
            . 'GROUP BY index_name ORDER BY index_name',
        );
        $statement->execute(['table' => $table]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    private function foreignKeyWithDeleteRule(string $table, string $column): string
    {
        $statement = $this->pdo->prepare(
            'SELECT CONCAT(k.table_name, ".", k.column_name, "->", k.referenced_table_name, ".", k.referenced_column_name, ":", r.delete_rule) '
            . 'FROM information_schema.key_column_usage k '
            . 'INNER JOIN information_schema.referential_constraints r '
            . 'ON r.constraint_schema = k.constraint_schema AND r.constraint_name = k.constraint_name AND r.table_name = k.table_name '
            . 'WHERE k.table_schema = DATABASE() AND k.table_name = :table AND k.column_name = :column '
            . 'AND k.referenced_table_name IS NOT NULL LIMIT 1',
        );
        $statement->execute([
            'table' => $table,
            'column' => $column,
        ]);

        return (string) $statement->fetchColumn();
    }

    private function columnType(string $table, string $column): string
    {
        $statement = $this->pdo->prepare(
            'SELECT data_type FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
        );
        $statement->execute([
            'table' => $table,
            'column' => $column,
        ]);

        return (string) $statement->fetchColumn();
    }
}
