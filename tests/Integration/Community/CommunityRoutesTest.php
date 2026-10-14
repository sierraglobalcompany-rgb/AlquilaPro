<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Community;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class CommunityRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private int $ownerId;
    private int $propertyId;
    private int $leaseId;
    /** @var list<string> */
    private array $temporaryUploads = [];

    protected function setUp(): void
    {
        $_SESSION = [];
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

        $this->directory = sys_get_temp_dir() . '/alquila_community_routes_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-14T10:00:00-05:00',
        ]);

        $this->ownerId = $this->insertUser('community-owner@example.test', 'owner');
        $this->pdo->exec("INSERT INTO properties (name, address, city) VALUES ('Community Route Property', 'Calle 3', 'Bogotá')");
        $this->propertyId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_at) VALUES (:contract_id, :user_id, :activated_at)',
        );
        $lease->execute([
            'contract_id' => random_int(1_000_000, 2_000_000_000),
            'user_id' => $this->ownerId,
            'activated_at' => '2026-10-14 10:00:00',
        ]);
        $this->leaseId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach ($this->temporaryUploads as $upload) {
            if (is_file($upload) || is_link($upload)) {
                @unlink($upload);
            }
        }
        foreach (glob($this->directory . '/documents/*') ?: [] as $storedFile) {
            if (is_file($storedFile) || is_link($storedFile)) {
                @unlink($storedFile);
            }
        }
        @rmdir($this->directory . '/documents');
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testCommunityPermissionsAndStaffRoutesEnforceSeparateViewAndManageAccess(): void
    {
        self::assertSame(
            ['community.view', 'community.manage'],
            Permissions::sanitize(['community.view', 'community.manage', 'community.delete', 'community.view']),
        );
        self::assertTrue(Permissions::allows(['role' => 'owner', 'permissions' => []], 'community.manage'));
        self::assertTrue(Permissions::allows(['role' => 'admin', 'permissions' => ['community.view']], 'community.view'));
        self::assertFalse(Permissions::allows(['role' => 'admin', 'permissions' => ['community.view']], 'community.manage'));
        self::assertFalse(Permissions::allows(
            ['role' => 'tenant', 'permissions' => ['community.view', 'community.manage']],
            'community.view',
        ));

        $viewerId = $this->insertUser('community-viewer@example.test', 'admin', ['community.view']);
        $managerId = $this->insertUser('community-manager@example.test', 'admin', ['community.manage']);
        $tenantId = $this->insertUser('community-tenant@example.test', 'tenant', ['community.view', 'community.manage']);
        self::assertGreaterThan(0, $viewerId);
        self::assertGreaterThan(0, $managerId);
        self::assertGreaterThan(0, $tenantId);

        $guestApp = $this->app('/sub');
        self::assertSame(302, $this->request($guestApp, 'GET', '/sub/admin/community')->getStatusCode());

        $_SESSION = [];
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'community-viewer@example.test', 'CommunityPassword123');
        self::assertSame(200, $this->request($viewerApp, 'GET', '/sub/admin/community')->getStatusCode());
        self::assertSame(403, $this->request($viewerApp, 'POST', '/sub/admin/community/incidents', $this->incidentForm())->getStatusCode());

        $_SESSION = [];
        $managerApp = $this->app('/sub');
        $this->login($managerApp, 'community-manager@example.test', 'CommunityPassword123');
        self::assertSame(403, $this->request($managerApp, 'GET', '/sub/admin/community')->getStatusCode());
        self::assertSame(303, $this->request(
            $managerApp,
            'POST',
            '/sub/admin/community/incidents',
            $this->incidentForm(),
        )->getStatusCode());

        $_SESSION = [];
        $tenantApp = $this->app('/sub');
        $this->login($tenantApp, 'community-tenant@example.test', 'CommunityPassword123');
        self::assertSame(403, $this->request($tenantApp, 'GET', '/sub/admin/community')->getStatusCode());
        self::assertSame(403, $this->request(
            $tenantApp,
            'POST',
            '/sub/admin/community/incidents',
            $this->incidentForm(),
        )->getStatusCode());

        $_SESSION = [];
        $ownerApp = $this->app('/sub');
        $this->login($ownerApp, 'community-owner@example.test', 'CommunityPassword123');
        self::assertSame(200, $this->request($ownerApp, 'GET', '/sub/admin/community')->getStatusCode());
        self::assertSame(303, $this->request(
            $ownerApp,
            'POST',
            '/sub/admin/community/incidents',
            $this->incidentForm(),
        )->getStatusCode());
        self::assertSame(403, $this->request(
            $ownerApp,
            'POST',
            '/sub/admin/community/incidents',
            $this->incidentForm(),
            null,
            false,
        )->getStatusCode());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM community_incidents')->fetchColumn());
    }

    public function testStaffCanRunIncidentLifecycleAndManageF18EvidenceAndLegalNoticeVersions(): void
    {
        $app = $this->app('/sub');
        $this->login($app, 'community-owner@example.test', 'CommunityPassword123');

        $created = $this->request($app, 'POST', '/sub/admin/community/incidents', $this->incidentForm([
            'description' => 'Reporte <script>comunidad()</script>',
        ]));
        self::assertSame(303, $created->getStatusCode());
        $incidentId = (int) $this->pdo->query('SELECT MAX(id) FROM community_incidents')->fetchColumn();
        self::assertGreaterThan(0, $incidentId);

        self::assertSame(303, $this->request(
            $app,
            'POST',
            '/sub/admin/community/incidents/' . $incidentId . '/review',
        )->getStatusCode());
        self::assertSame(303, $this->request(
            $app,
            'POST',
            '/sub/admin/community/incidents/' . $incidentId . '/close',
            ['resolution_notes' => 'Acuerdo de convivencia alcanzado.'],
        )->getStatusCode());

        $evidence = $this->request(
            $app,
            'POST',
            '/sub/admin/community/incidents/' . $incidentId . '/evidence',
            ['title' => 'Fotografía del área común'],
            $this->upload($this->pngBytes()),
        );
        self::assertSame(303, $evidence->getStatusCode());
        $documentId = (int) $this->pdo->query(
            "SELECT id FROM documents WHERE community_incident_id = {$incidentId} AND type = 'incident_evidence'",
        )->fetchColumn();
        self::assertGreaterThan(0, $documentId);

        $file = $this->request(
            $app,
            'GET',
            '/sub/admin/community/incidents/' . $incidentId . '/documents/' . $documentId . '/file',
        );
        self::assertSame(200, $file->getStatusCode());
        self::assertSame('image/png', $file->getHeaderLine('Content-Type'));
        self::assertSame('private, no-store', $file->getHeaderLine('Cache-Control'));
        self::assertSame($this->pngBytes(), (string) $file->getBody());

        $legal = $this->request($app, 'POST', '/sub/admin/community/legal-notices', [
            'property_id' => (string) $this->propertyId,
            'title' => 'Aviso de convivencia',
            'source_label' => 'Secretaría local',
            'source_url' => 'https://example.test/legal',
            'effective_on' => '2026-10-14',
            'notice_text' => 'Información legal vigente para residentes.',
        ]);
        self::assertSame(303, $legal->getStatusCode());

        $page = $this->request($app, 'GET', '/sub/admin/community?property_id=' . $this->propertyId);
        self::assertSame(200, $page->getStatusCode());
        $html = (string) $page->getBody();
        self::assertStringContainsString('Reporte &lt;script&gt;comunidad()&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>comunidad()</script>', $html);
        self::assertStringContainsString('Acuerdo de convivencia alcanzado.', $html);
        self::assertStringContainsString('Secretaría local', $html);
        self::assertStringContainsString('Información legal vigente para residentes.', $html);
        self::assertStringNotContainsString('stored_name', $html);
        self::assertStringNotContainsString((string) $this->pdo->query(
            'SELECT sha256 FROM documents WHERE id = ' . $documentId,
        )->fetchColumn(), $html);

        self::assertSame(422, $this->request($app, 'POST', '/sub/admin/community/legal-notices', [
            'property_id' => (string) $this->propertyId,
            'title' => 'Aviso inválido',
            'source_label' => 'Entidad',
            'source_url' => 'javascript:alert(1)',
            'notice_text' => 'Texto',
        ])->getStatusCode());
    }

    private function app(string $basePath = ''): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com' . $basePath,
            'base_path' => $basePath,
            'logo_directory' => $this->directory . '/branding',
            'unit_media_directory' => $this->directory . '/unit-media',
            'candidate_document_directory' => $this->directory . '/candidate-documents',
            'contract_directory' => $this->directory . '/contracts',
            'payment_method_qr_directory' => $this->directory . '/payment-method-qrs',
            'maintenance_photo_directory' => $this->directory . '/maintenance-photos',
            'document_directory' => $this->directory . '/documents',
            'pdo' => $this->pdo,
        ]);
    }

    /** @param array<string,mixed> $body */
    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        ?UploadedFile $upload = null,
        bool $includeCsrf = true,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );
        if (strtoupper($method) === 'POST' && $includeCsrf) {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }
        if ($upload !== null) {
            $request = $request->withUploadedFiles(['evidence' => $upload]);
        }
        return $app->handle($request);
    }

    private function login(App $app, string $email, string $password): void
    {
        $response = $this->request($app, 'POST', '/sub/login', ['email' => $email, 'password' => $password]);
        self::assertSame(303, $response->getStatusCode());
    }

    /** @param list<string> $permissions */
    private function insertUser(string $email, string $role, array $permissions = []): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, permissions_json) '
            . 'VALUES (:role, :name, :email, :hash, 1, :permissions)',
        );
        $statement->execute([
            'role' => $role,
            'name' => 'Community ' . ucfirst($role),
            'email' => $email,
            'hash' => password_hash('CommunityPassword123', PASSWORD_DEFAULT),
            'permissions' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed> */
    private function incidentForm(array $override = []): array
    {
        return array_replace([
            'lease_id' => (string) $this->leaseId,
            'category' => 'Ruido',
            'occurred_on' => '2026-10-13',
            'description' => 'Reporte de ruido en zona común.',
        ], $override);
    }

    private function upload(string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_community_route_upload_');
        if ($path === false || file_put_contents($path, $bytes) !== strlen($bytes)) {
            self::fail('Unable to create community upload fixture.');
        }
        $this->temporaryUploads[] = $path;
        return new UploadedFile($path, 'evidence.png', 'image/png', strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);
        return $bytes;
    }
}
