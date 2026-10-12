<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Auth;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthSchemaTest extends TestCase
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

        foreach (['unit_media', 'units', 'properties', 'settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
    }

    public function testF2AuthFieldsExistOnUsersAfterMigrations(): void
    {
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $columns = $this->pdo->query(
            "SELECT column_name FROM information_schema.columns "
            . "WHERE table_schema = DATABASE() AND table_name = 'users' ORDER BY ordinal_position",
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ([
            'permissions_json',
            'must_change_password',
            'failed_login_attempts',
            'locked_until',
            'last_login_at',
            'reset_token_hash',
            'reset_expires_at',
        ] as $column) {
            self::assertContains($column, $columns, sprintf('Missing F2 auth column: %s', $column));
        }
    }
}
