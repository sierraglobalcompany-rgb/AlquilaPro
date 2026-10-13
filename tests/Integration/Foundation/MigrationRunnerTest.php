<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Foundation;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationRunnerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!class_exists(DatabaseConfig::class) || !class_exists(DatabaseConnector::class)) {
            self::fail('Database foundation must exist before migration tests can run.');
        }

        $this->pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]));

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'migration_probe',
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
    }

    public function testAppliesProjectMigrationsAndSecondRunIsIdempotent(): void
    {
        if (!class_exists(MigrationRunner::class)) {
            self::fail('MigrationRunner must exist before this behavior can pass.');
        }

        $directory = dirname(__DIR__, 3) . '/database/migrations';

        $first = MigrationRunner::migrate($this->pdo, $directory);
        $second = MigrationRunner::migrate($this->pdo, $directory);

        self::assertSame(6, $first['applied']);
        self::assertSame(
            [
                '001_create_users.sql',
                '002_create_settings.sql',
                '003_extend_users_for_auth.sql',
                '004_create_properties_units_media.sql',
                '005_create_candidates_references_requirements_documents.sql',
                '006_create_screening_processes.sql',
            ],
            $first['migrations'],
        );
        self::assertSame(0, $second['applied']);
        self::assertSame([], $second['migrations']);
        self::assertSame(6, (int) $this->pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());
        self::assertSame(1, $this->tableExists('users'));
        self::assertSame(1, $this->tableExists('settings'));
        self::assertSame(1, $this->tableExists('properties'));
        self::assertSame(1, $this->tableExists('units'));
        self::assertSame(1, $this->tableExists('unit_media'));
        self::assertSame(1, $this->tableExists('candidates'));
        self::assertSame(1, $this->tableExists('candidate_references'));
        self::assertSame(1, $this->tableExists('unit_application_requirements'));
        self::assertSame(1, $this->tableExists('candidate_documents'));
        self::assertSame(1, $this->tableExists('screening_processes'));
    }

    public function testFailedMigrationIsNotRecordedAsApplied(): void
    {
        if (!class_exists(MigrationRunner::class)) {
            self::fail('MigrationRunner must exist before this behavior can pass.');
        }

        $directory = sys_get_temp_dir() . '/alquila_migrations_' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);

        file_put_contents(
            $directory . '/001_valid.sql',
            'CREATE TABLE migration_probe (id INT PRIMARY KEY) ENGINE=InnoDB',
        );
        file_put_contents($directory . '/002_invalid.sql', 'THIS IS NOT VALID SQL');

        try {
            MigrationRunner::migrate($this->pdo, $directory);
            self::fail('Expected invalid migration to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('No fue posible aplicar la migración 002_invalid.sql.', $exception->getMessage());
            self::assertSame(
                ['001_valid.sql'],
                $this->pdo->query('SELECT migration FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN),
            );
        } finally {
            @unlink($directory . '/001_valid.sql');
            @unlink($directory . '/002_invalid.sql');
            @rmdir($directory);
        }
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
