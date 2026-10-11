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

        foreach (['migration_probe', 'settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
    }

    public function testAppliesF1MigrationsAndSecondRunIsIdempotent(): void
    {
        if (!class_exists(MigrationRunner::class)) {
            self::fail('MigrationRunner must exist before this behavior can pass.');
        }

        $directory = dirname(__DIR__, 3) . '/database/migrations';

        $first = MigrationRunner::migrate($this->pdo, $directory);
        $second = MigrationRunner::migrate($this->pdo, $directory);

        self::assertSame(2, $first['applied']);
        self::assertSame(['001_create_users.sql', '002_create_settings.sql'], $first['migrations']);
        self::assertSame(0, $second['applied']);
        self::assertSame([], $second['migrations']);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());
        self::assertSame(1, $this->tableExists('users'));
        self::assertSame(1, $this->tableExists('settings'));
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
