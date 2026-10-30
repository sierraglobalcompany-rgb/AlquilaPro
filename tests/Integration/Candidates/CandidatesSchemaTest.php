<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Auth\Permissions;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class CandidatesSchemaTest extends TestCase
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
            'candidate_household_occupants',
            'candidate_household_pets',
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

    public function testF5MigrationCreatesExactlyCandidateTables(): void
    {
        foreach ([
            'candidates',
            'candidate_references',
            'unit_application_requirements',
            'candidate_documents',
            'candidate_household_occupants',
            'candidate_household_pets',
        ] as $table) {
            self::assertSame(1, $this->tableExists($table), $table . ' must exist');
        }

        foreach (['leads', 'applications', 'rental_applications'] as $table) {
            self::assertSame(0, $this->tableExists($table), $table . ' must not exist');
        }
    }

    public function testF5CandidateTablesHaveExactColumns(): void
    {
        self::assertSame([
            'id', 'unit_id', 'document_type', 'document_number', 'full_name', 'birth_date', 'phone', 'email',
            'current_address', 'employment_status', 'employer_name', 'occupation', 'monthly_income', 'employment_notes',
            'occupants_count', 'occupants_notes', 'pets_declared', 'pets_answered_at', 'pets_notes', 'status', 'continuation_token_hash',
            'continuation_token_expires_at', 'consent_at', 'submitted_at', 'created_at', 'updated_at',
        ], $this->columns('candidates'));

        self::assertSame([
            'id', 'candidate_id', 'reference_type', 'name', 'relationship', 'phone', 'email', 'created_at', 'updated_at',
        ], $this->columns('candidate_references'));

        self::assertSame([
            'id', 'unit_id', 'code', 'label', 'description', 'is_required', 'is_active', 'sort_order', 'created_at', 'updated_at',
        ], $this->columns('unit_application_requirements'));

        self::assertSame([
            'id', 'candidate_id', 'requirement_id', 'stored_name', 'original_name', 'mime_type', 'size_bytes', 'created_at', 'updated_at',
        ], $this->columns('candidate_documents'));

        self::assertSame([
            'id', 'candidate_id', 'full_name', 'relationship', 'age_years', 'occupation_or_activity', 'notes',
            'photo_stored_name', 'photo_mime_type', 'photo_size_bytes', 'photo_sha256', 'photo_consent_at', 'created_at', 'updated_at',
        ], $this->columns('candidate_household_occupants'));
        self::assertSame([
            'id', 'candidate_id', 'name', 'species', 'breed', 'size_category', 'age_years', 'vaccination_declaration', 'notes',
            'photo_stored_name', 'photo_mime_type', 'photo_size_bytes', 'photo_sha256', 'photo_consent_at', 'created_at', 'updated_at',
        ], $this->columns('candidate_household_pets'));
    }

    public function testCandidateIdentityAndTokenHashAreUnique(): void
    {
        $uniqueIndexes = $this->uniqueIndexColumns('candidates');

        self::assertContains('document_type,document_number', $uniqueIndexes);
        self::assertContains('continuation_token_hash', $uniqueIndexes);
    }

    public function testCandidateRequirementAndDocumentForeignKeysAreCorrect(): void
    {
        $foreignKeys = $this->foreignKeys();

        self::assertContains('candidates.unit_id->units.id', $foreignKeys);
        self::assertContains('candidate_references.candidate_id->candidates.id', $foreignKeys);
        self::assertContains('unit_application_requirements.unit_id->units.id', $foreignKeys);
        self::assertContains('candidate_documents.candidate_id->candidates.id', $foreignKeys);
        self::assertContains('candidate_documents.requirement_id->unit_application_requirements.id', $foreignKeys);
        self::assertContains('candidate_household_occupants.candidate_id->candidates.id', $foreignKeys);
        self::assertContains('candidate_household_pets.candidate_id->candidates.id', $foreignKeys);

        self::assertContains('unit_id,code', $this->uniqueIndexColumns('unit_application_requirements'));
        self::assertContains('candidate_id,requirement_id', $this->uniqueIndexColumns('candidate_documents'));
    }

    public function testF5DoesNotCreateLeadsOrApplicationsTables(): void
    {
        $tables = $this->pdo
            ->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name')
            ->fetchAll(PDO::FETCH_COLUMN);

        self::assertNotContains('leads', $tables);
        self::assertNotContains('applications', $tables);
        self::assertNotContains('rental_applications', $tables);
    }

    public function testPermissionsCatalogContainsExactlyTwoCandidatePermissions(): void
    {
        $sanitized = Permissions::sanitize([
            'candidates.view',
            'candidates.manage',
            'candidates.delete',
            'properties.view',
        ]);

        self::assertContains('candidates.view', $sanitized);
        self::assertContains('candidates.manage', $sanitized);
        self::assertNotContains('candidates.delete', $sanitized);
        self::assertTrue(Permissions::allows(['role' => 'gestor', 'permissions' => ['candidates.view']], 'candidates.view'));
        self::assertFalse(Permissions::allows(['role' => 'gestor', 'permissions' => ['candidates.view']], 'candidates.manage'));
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

    /** @return list<string> */
    private function foreignKeys(): array
    {
        $statement = $this->pdo->query(
            'SELECT CONCAT(table_name, ".", column_name, "->", referenced_table_name, ".", referenced_column_name) '
            . 'FROM information_schema.key_column_usage '
            . 'WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL '
            . "AND table_name IN ('candidates', 'candidate_references', 'unit_application_requirements', 'candidate_documents', 'candidate_household_occupants', 'candidate_household_pets')",
        );

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }
}
