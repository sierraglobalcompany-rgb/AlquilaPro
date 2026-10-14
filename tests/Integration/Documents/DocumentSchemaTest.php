<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Documents;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class DocumentSchemaTest extends TestCase
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

    public function testMigrationCreatesOneImmutablePropertyOrLeaseDocumentTable(): void
    {
        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() "
            . "AND (table_name = 'documents' OR table_name LIKE 'documents\\_%') ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['documents'], $tables);

        $columns = $this->pdo->query(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() "
            . "AND table_name = 'documents' ORDER BY ordinal_position",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([
            'id', 'property_id', 'lease_id', 'type', 'title', 'content_json', 'stored_name', 'mime_type',
            'sha256', 'size_bytes', 'supersedes_document_id', 'created_by_user_id', 'created_at',
        ], $columns);
        foreach (['status', 'history_id', 'document_type_id', 'inventory_item_id', 'version_id'] as $forbidden) {
            self::assertNotContains($forbidden, $columns);
        }

        $create = $this->pdo->query('SHOW CREATE TABLE documents')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($create);
        self::assertStringContainsString('documents_scope_xor_check', (string) $create['Create Table']);
        self::assertStringContainsString('documents_payload_check', (string) $create['Create Table']);
        self::assertStringContainsString('documents_supersedes_fk', (string) $create['Create Table']);
    }
}
