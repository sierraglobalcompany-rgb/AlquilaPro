<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Foundation;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class ReleaseMigrationTest extends TestCase
{
    private const MIGRATIONS = [
        '001_create_users.sql',
        '002_create_settings.sql',
        '003_extend_users_for_auth.sql',
        '004_create_properties_units_media.sql',
        '005_create_candidates_references_requirements_documents.sql',
        '006_create_screening_processes.sql',
        '007_create_contract_engine.sql',
        '008_create_leases.sql',
        '009_create_charges.sql',
        '010_create_payment_methods.sql',
        '011_create_payments.sql',
        '012_create_lease_obligations.sql',
        '013_create_insurance_policies_and_claims.sql',
        '014_create_maintenance_requests.sql',
        '015_create_maintenance_visits.sql',
        '016_create_notification_jobs.sql',
        '017_create_whatsapp_actions.sql',
        '018_create_documents.sql',
        '019_create_community_incidents.sql',
        '020_create_digital_services_memberships.sql',
        '021_create_lease_termination.sql',
        '022_create_audit_log.sql',
    ];

    private const FINAL_TABLES = [
        'users',
        'settings',
        'properties',
        'units',
        'unit_media',
        'candidates',
        'candidate_references',
        'unit_application_requirements',
        'candidate_documents',
        'screening_processes',
        'contract_templates',
        'contract_template_versions',
        'contracts',
        'leases',
        'charges',
        'payment_methods',
        'payments',
        'payment_allocations',
        'lease_obligations',
        'insurance_policies',
        'insurance_claims',
        'maintenance_requests',
        'maintenance_visits',
        'notification_jobs',
        'whatsapp_actions',
        'documents',
        'community_incidents',
        'digital_services',
        'service_memberships',
        'good_conduct_bonuses',
        'lease_liquidations',
        'audit_log',
    ];

    private PDO $pdo;
    private string $migrationDirectory;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]));
        $this->migrationDirectory = dirname(__DIR__, 3) . '/database/migrations';
        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();
    }

    public function testEmptyDatabaseReachesFinalSchemaAndRerunIsIdempotent(): void
    {
        self::assertSame(0, $this->tableExists('schema_migrations'));
        foreach (self::FINAL_TABLES as $table) {
            self::assertSame(0, $this->tableExists($table), $table . ' must be absent before the first migration.');
        }

        $first = MigrationRunner::migrate($this->pdo, $this->migrationDirectory);
        self::assertSame(self::MIGRATIONS, $first['migrations']);
        self::assertSame(count(self::MIGRATIONS), $first['applied']);
        $this->assertFinalSchema();

        $second = MigrationRunner::migrate($this->pdo, $this->migrationDirectory);
        self::assertSame(0, $second['applied']);
        self::assertSame([], $second['migrations']);
        self::assertSame(self::MIGRATIONS, $this->appliedMigrations());

        foreach (['users', 'properties', 'units', 'candidates', 'service_memberships', 'audit_log'] as $table) {
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn());
        }
    }

    public function testPreFifteenAndPreTwentySchemasUpgradeToFinalWithoutLosingRows(): void
    {
        foreach ([14, 19] as $baselineVersion) {
            $this->resetDatabase();
            $baselineDirectory = $this->copyMigrationsThrough($baselineVersion);

            try {
                $baseline = MigrationRunner::migrate($this->pdo, $baselineDirectory);
                self::assertSame($baselineVersion, $baseline['applied']);
                self::assertSame(array_slice(self::MIGRATIONS, 0, $baselineVersion), $baseline['migrations']);

                $email = 'legacy-f' . $baselineVersion . '@example.test';
                $insertUser = $this->pdo->prepare(
                    'INSERT INTO users (role, full_name, email, password_hash) VALUES (?, ?, ?, ?)',
                );
                $insertUser->execute(['owner', 'Legacy Owner', $email, password_hash('test-only-password', PASSWORD_DEFAULT)]);
                $insertSetting = $this->pdo->prepare(
                    'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)',
                );
                $insertSetting->execute(['release.legacy_marker', 'kept-' . $baselineVersion]);

                $upgrade = MigrationRunner::migrate($this->pdo, $this->migrationDirectory);
                $expectedUpgrade = array_slice(self::MIGRATIONS, $baselineVersion);
                self::assertSame($expectedUpgrade, $upgrade['migrations']);
                self::assertSame(count($expectedUpgrade), $upgrade['applied']);
                $this->assertFinalSchema();
                self::assertSame($email, $this->pdo->query(
                    "SELECT email FROM users WHERE full_name = 'Legacy Owner'",
                )->fetchColumn());
                self::assertSame(
                    'kept-' . $baselineVersion,
                    $this->pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'release.legacy_marker'")->fetchColumn(),
                );
                self::assertSame(0, MigrationRunner::migrate($this->pdo, $this->migrationDirectory)['applied']);
            } finally {
                $this->removeMigrationDirectory($baselineDirectory);
            }
        }
    }

    public function testF16F20F21AndF22SchemaCoexistsAfterUpgrade(): void
    {
        $result = MigrationRunner::migrate($this->pdo, $this->migrationDirectory);

        self::assertSame(self::MIGRATIONS, $result['migrations']);
        self::assertContains('016_create_notification_jobs.sql', $result['migrations']);
        self::assertContains('020_create_digital_services_memberships.sql', $result['migrations']);
        self::assertContains('021_create_lease_termination.sql', $result['migrations']);
        self::assertContains('022_create_audit_log.sql', $result['migrations']);
        $this->assertFinalSchema();

        self::assertTrue($this->columnIsNullable('notification_jobs', 'recipient_email'));
        self::assertTrue($this->columnIsNullable('notification_jobs', 'recipient_name'));
        foreach ([
            ['service_memberships', 'charge_decision'],
            ['service_memberships', 'charge_id'],
            ['good_conduct_bonuses', 'decided_at'],
            ['lease_liquidations', 'snapshot_json'],
            ['audit_log', 'metadata_json'],
            ['documents', 'community_incident_id'],
        ] as [$table, $column]) {
            self::assertTrue($this->columnExists($table, $column), $table . '.' . $column . ' must be present.');
        }
        self::assertSame(1, $this->constraintExists('documents', 'documents_community_incident_fk'));
        self::assertSame(1, $this->constraintExists('service_memberships', 'service_memberships_charge_fk'));
    }

    private function assertFinalSchema(): void
    {
        self::assertSame(1, $this->tableExists('schema_migrations'));
        self::assertSame(self::MIGRATIONS, $this->appliedMigrations());
        foreach (self::FINAL_TABLES as $table) {
            self::assertSame(1, $this->tableExists($table), $table . ' must exist in the final schema.');
        }
    }

    private function appliedMigrations(): array
    {
        return $this->pdo->query('SELECT migration FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
    }

    private function tableExists(string $table): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        );
        $statement->execute([$table]);

        return (int) $statement->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        );
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() === 1;
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT is_nullable FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        );
        $statement->execute([$table, $column]);

        return $statement->fetchColumn() === 'YES';
    }

    private function constraintExists(string $table, string $constraint): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.table_constraints '
            . 'WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
        );
        $statement->execute([$table, $constraint]);

        return (int) $statement->fetchColumn();
    }

    private function copyMigrationsThrough(int $version): string
    {
        $directory = sys_get_temp_dir() . '/alquila_migration_baseline_' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        foreach (glob($this->migrationDirectory . '/*.sql') ?: [] as $file) {
            if ((int) basename($file) > $version) {
                continue;
            }
            if (!copy($file, $directory . '/' . basename($file))) {
                self::fail('Could not prepare the historical migration baseline.');
            }
        }

        return $directory;
    }

    private function removeMigrationDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }

    private function resetDatabase(): void
    {
        $tables = array_merge(self::FINAL_TABLES, ['schema_migrations', 'migration_probe']);
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
