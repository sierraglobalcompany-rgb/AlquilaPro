<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Documents;

use AlquilaPro\Auth\Permissions;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class DocumentSecurityRegressionTest extends TestCase
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
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
    }

    public function testDocumentArchitectureStaysWithinTheApprovedAuthorityBudget(): void
    {
        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() "
            . "AND (table_name = 'documents' OR table_name LIKE 'document_%' "
            . "OR table_name LIKE 'inventory_%' OR table_name LIKE 'manual_%') ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['documents'], $tables);

        $columns = $this->pdo->query(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() "
            . "AND table_name = 'documents' ORDER BY ordinal_position",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([
            'id', 'property_id', 'lease_id', 'community_incident_id', 'type', 'title', 'content_json', 'stored_name', 'mime_type', 'sha256',
            'size_bytes', 'supersedes_document_id', 'created_by_user_id', 'created_at',
        ], $columns);

        $appDirectory = dirname(__DIR__, 3) . '/app/Documents';
        $files = array_map('basename', glob($appDirectory . '/*.php') ?: []);
        sort($files);
        self::assertSame(['DocumentService.php', 'routes.php'], $files);
        self::assertSame(
            ['documents.view', 'documents.manage'],
            Permissions::sanitize(['documents.view', 'documents.manage', 'documents.delete', 'documents.admin']),
        );

        $migration = (string) file_get_contents(dirname(__DIR__, 3) . '/database/migrations/018_create_documents.sql');
        self::assertSame(1, preg_match_all('/\bCREATE\s+TABLE\b/i', $migration));
        self::assertSame(0, preg_match_all('/\bALTER\s+TABLE\b/i', $migration));
        self::assertDoesNotMatchRegularExpression('/\b(?:candidate_documents|insurance_documents|maintenance_photos)\b/i', $migration);
    }

    public function testDocumentRoutesAndPrivateStorageDoNotAddParallelOrPublicDeliveryPaths(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = (string) file_get_contents($root . '/app/Documents/routes.php');
        preg_match_all('/\$app->(get|post)\(\'([^\']+)\'/i', $routes, $matches, PREG_SET_ORDER);
        $registered = array_map(
            static fn (array $match): array => [strtoupper($match[1]), $match[2]],
            $matches,
        );
        self::assertSame([
            ['GET', '/admin/documents'],
            ['POST', '/admin/documents'],
            ['GET', '/admin/documents/{id:[0-9]+}'],
            ['GET', '/admin/documents/{id:[0-9]+}/file'],
            ['GET', '/tenant/leases/{leaseId:[0-9]+}/documents/{documentId:[0-9]+}/file'],
        ], $registered);

        $bootstrap = (string) file_get_contents($root . '/bootstrap/app.php');
        self::assertStringContainsString("'/alquilapro-private/'", $bootstrap);
        self::assertStringContainsString("\$settings['document_directory']", $bootstrap);
        $index = (string) file_get_contents($root . '/index.php');
        self::assertStringContainsString('ALQUILAPRO_DOCUMENT_DIRECTORY', $index);

        $service = (string) file_get_contents($root . '/app/Documents/DocumentService.php');
        self::assertDoesNotMatchRegularExpression('/\bFinanceService\b|\b(?:INSERT|UPDATE|DELETE)\s+\w*charges\b/i', $service);
        self::assertStringNotContainsString('candidate_documents', $service);
        self::assertStringNotContainsString('insurance_documents', $service);
    }
}
