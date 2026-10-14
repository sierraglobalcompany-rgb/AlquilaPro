<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Community;

use AlquilaPro\Auth\Permissions;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class CommunitySecurityRegressionTest extends TestCase
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

    public function testCommunityKeepsItsApprovedTableServicePermissionAndRouteBudget(): void
    {
        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() "
            . "AND (table_name LIKE 'community_%' OR table_name LIKE 'incident_%' "
            . "OR table_name LIKE 'legal_%' OR table_name LIKE 'support_%') ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['community_incidents'], $tables);

        $root = dirname(__DIR__, 3);
        $communityDirectory = $root . '/app/Community';
        $files = array_map('basename', glob($communityDirectory . '/*.php') ?: []);
        sort($files);
        self::assertSame(['CommunityService.php', 'routes.php'], $files);

        $serviceSource = (string) file_get_contents($communityDirectory . '/CommunityService.php');
        self::assertSame(1, preg_match_all('/\bclass\s+CommunityService\b/', $serviceSource));
        self::assertDoesNotMatchRegularExpression(
            '/UploadedFileInterface|documentDirectory|privateFile\s*\(|\b(?:fopen|fread|file_get_contents)\s*\(/',
            $serviceSource,
        );

        $permissionsSource = (string) file_get_contents($root . '/app/Auth/Permissions.php');
        preg_match_all('/^\s*\'(community\.[a-z]+)\',?\s*$/m', $permissionsSource, $permissionMatches);
        self::assertSame(['community.view', 'community.manage'], $permissionMatches[1] ?? []);
        self::assertSame(
            ['community.view', 'community.manage'],
            Permissions::sanitize(['community.view', 'community.manage', 'community.delete']),
        );

        $routesSource = (string) file_get_contents($communityDirectory . '/routes.php');
        preg_match_all('/\$app->(get|post)\(\'([^\']+)\'/i', $routesSource, $matches, PREG_SET_ORDER);
        $registered = array_map(
            static fn (array $match): array => [strtoupper($match[1]), $match[2]],
            $matches,
        );
        self::assertSame([
            ['GET', '/admin/community'],
            ['POST', '/admin/community/incidents'],
            ['POST', '/admin/community/incidents/{id:[0-9]+}/review'],
            ['POST', '/admin/community/incidents/{id:[0-9]+}/close'],
            ['POST', '/admin/community/incidents/{id:[0-9]+}/void'],
            ['POST', '/admin/community/incidents/{id:[0-9]+}/evidence'],
            ['POST', '/admin/community/legal-notices'],
            ['GET', '/admin/community/incidents/{incidentId:[0-9]+}/documents/{documentId:[0-9]+}/file'],
        ], $registered);

        $documentSource = (string) file_get_contents($root . '/app/Documents/DocumentService.php');
        self::assertStringContainsString('createIncidentFileVersion', $documentSource);
        self::assertStringContainsString('listCurrentForIncident', $documentSource);
        self::assertStringContainsString('privateFile', $documentSource);
    }
}
