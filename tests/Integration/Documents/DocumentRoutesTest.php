<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Documents;

use AlquilaPro\Auth\Csrf;
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

final class DocumentRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $documentDirectory;
    private string $lockPath;
    /** @var list<string> */
    private array $temporaryFiles = [];

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
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['documents', 'leases', 'contracts', 'contract_template_versions', 'contract_templates', 'candidates', 'units', 'properties', 'users'] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->directory = sys_get_temp_dir() . '/alquila_document_routes_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->documentDirectory = $this->directory . '/documents';
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-14T10:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        foreach (glob($this->documentDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->documentDirectory);
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory . '/branding');
        @rmdir($this->directory . '/unit-media');
        @rmdir($this->directory . '/candidate-documents');
        @rmdir($this->directory . '/contracts');
        @rmdir($this->directory . '/payment-method-qrs');
        @rmdir($this->directory . '/payment-receipts');
        @rmdir($this->directory . '/obligation-supports');
        @rmdir($this->directory . '/insurance-documents');
        @rmdir($this->directory . '/maintenance-photos');
        @rmdir($this->directory . '/storage');
        @rmdir($this->directory);
    }

    public function testDocumentPermissionsAndStaffVersionHistoryAndPrivateDownload(): void
    {
        $readerPassword = 'ReaderPassword123';
        $managerPassword = 'ManagerPassword123';
        $this->insertUser('reader@example.com', $readerPassword, 'admin', ['documents.view']);
        $this->insertUser('manager@example.com', $managerPassword, 'admin', ['documents.view', 'documents.manage']);
        $this->insertUser('tenant@example.com', 'TenantPassword123', 'tenant');
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $propertyId = $this->insertProperty();

        $readerApp = $this->app('/sub');
        $this->login($readerApp, 'reader@example.com', $readerPassword, '/sub');
        self::assertSame(200, $this->request($readerApp, 'GET', '/sub/admin/documents')->getStatusCode());
        self::assertSame(403, $this->request($readerApp, 'POST', '/sub/admin/documents', [
            'scope' => 'property',
            'scope_id' => (string) $propertyId,
            'type' => 'property_document',
            'title' => 'No autorizado',
        ])->getStatusCode());

        $_SESSION = [];
        $tenantApp = $this->app('/sub');
        $this->login($tenantApp, 'tenant@example.com', 'TenantPassword123', '/sub');
        self::assertSame(403, $this->request($tenantApp, 'GET', '/sub/admin/documents')->getStatusCode());

        $_SESSION = [];
        $ownerApp = $this->app('/sub');
        $this->login($ownerApp, 'owner@example.com', 'OwnerPassword123', '/sub');
        self::assertSame(200, $this->request($ownerApp, 'GET', '/sub/admin/documents')->getStatusCode());

        $_SESSION = [];
        $managerApp = $this->app('/sub');
        $this->login($managerApp, 'manager@example.com', $managerPassword, '/sub');
        $beforeFailedUpload = (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn();
        $failedManualUpload = $this->request($managerApp, 'POST', '/sub/admin/documents', [
            'scope' => 'property',
            'scope_id' => (string) $propertyId,
            'type' => 'manual_convivencia',
            'title' => 'No guardar tras upload parcial',
            'content_json' => '{"rules":["No se debe guardar este fallback"]}',
        ], ['document' => $this->upload($this->pdfBytes(), 'partial.pdf', UPLOAD_ERR_PARTIAL)]);
        self::assertSame(422, $failedManualUpload->getStatusCode());
        self::assertSame($beforeFailedUpload, (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn());

        $first = $this->request($managerApp, 'POST', '/sub/admin/documents', [
            'scope' => 'property',
            'scope_id' => (string) $propertyId,
            'type' => 'property_document',
            'title' => 'Documento vigente anterior',
        ], ['document' => $this->upload($this->pdfBytes(), 'first.pdf')]);
        self::assertSame(303, $first->getStatusCode());
        $firstId = (int) $this->pdo->query('SELECT id FROM documents ORDER BY id DESC LIMIT 1')->fetchColumn();

        $second = $this->request($managerApp, 'POST', '/sub/admin/documents', [
            'scope' => 'property',
            'scope_id' => (string) $propertyId,
            'type' => 'property_document',
            'title' => 'Documento vigente nuevo',
            'supersedes_document_id' => (string) $firstId,
        ], ['document' => $this->upload($this->pdfBytes(), 'second.pdf')]);
        self::assertSame(303, $second->getStatusCode());
        $secondId = (int) $this->pdo->query('SELECT id FROM documents ORDER BY id DESC LIMIT 1')->fetchColumn();

        $current = (string) $this->request(
            $managerApp,
            'GET',
            '/sub/admin/documents?scope=property&id=' . $propertyId,
        )->getBody();
        self::assertStringContainsString('Documento vigente nuevo', $current);
        self::assertStringNotContainsString('Documento vigente anterior', $current);

        $history = (string) $this->request($managerApp, 'GET', '/sub/admin/documents/' . $secondId)->getBody();
        self::assertStringContainsString('Documento vigente anterior', $history);
        self::assertStringContainsString('Documento vigente nuevo', $history);

        $download = $this->request($managerApp, 'GET', '/sub/admin/documents/' . $secondId . '/file');
        self::assertSame(200, $download->getStatusCode());
        self::assertSame($this->pdfBytes(), (string) $download->getBody());
        self::assertSame('private, no-store', $download->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $download->getHeaderLine('X-Content-Type-Options'));

        $manualJson = $this->request($managerApp, 'POST', '/sub/admin/documents', [
            'scope' => 'property',
            'scope_id' => (string) $propertyId,
            'type' => 'manual_convivencia',
            'title' => 'Manual JSON anterior',
            'content_json' => '{"sections":[{"title":"Ruido","body":"Respetar el descanso."}]}',
        ]);
        self::assertSame(303, $manualJson->getStatusCode());
        $manualJsonId = (int) $this->pdo->query("SELECT id FROM documents WHERE type = 'manual_convivencia' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $manualPdf = $this->request($managerApp, 'POST', '/sub/admin/documents', [
            'scope' => 'property',
            'scope_id' => (string) $propertyId,
            'type' => 'manual_convivencia',
            'title' => 'Manual PDF vigente',
            'supersedes_document_id' => (string) $manualJsonId,
            'content_json' => '{}',
        ], ['document' => $this->upload($this->pdfBytes(), 'manual.pdf')]);
        self::assertSame(303, $manualPdf->getStatusCode());
        $manualPdfId = (int) $this->pdo->query("SELECT id FROM documents WHERE type = 'manual_convivencia' ORDER BY id DESC LIMIT 1")->fetchColumn();

        $manualCurrent = (string) $this->request(
            $managerApp,
            'GET',
            '/sub/admin/documents?scope=property&id=' . $propertyId,
        )->getBody();
        self::assertStringContainsString('Manual PDF vigente', $manualCurrent);
        self::assertStringNotContainsString('Manual JSON anterior', $manualCurrent);
        $manualHistory = (string) $this->request($managerApp, 'GET', '/sub/admin/documents/' . $manualPdfId)->getBody();
        self::assertStringContainsString('Manual JSON anterior', $manualHistory);
        self::assertStringContainsString('Manual PDF vigente', $manualHistory);
        $manualDownload = $this->request($managerApp, 'GET', '/sub/admin/documents/' . $manualPdfId . '/file');
        self::assertSame(200, $manualDownload->getStatusCode());
        self::assertSame($this->pdfBytes(), (string) $manualDownload->getBody());
    }

    public function testInvalidDocumentSubmissionRendersEscapedFieldErrorAndPreservesSafeValues(): void
    {
        $this->insertUser('manager@example.com', 'ManagerPassword123', 'admin', ['documents.view', 'documents.manage']);
        $propertyId = $this->insertProperty();
        $app = $this->app('/sub');
        $this->login($app, 'manager@example.com', 'ManagerPassword123', '/sub');

        $response = $this->request($app, 'POST', '/sub/admin/documents', [
            'scope' => 'property',
            'scope_id' => (string) $propertyId,
            'type' => 'manual_convivencia',
            'title' => '<script>alert("demo")</script>',
            'content_json' => '{invalid json',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $html = (string) $response->getBody();
        self::assertStringContainsString('El contenido debe ser JSON válido.', $html);
        self::assertStringContainsString('aria-describedby="document-content_json-error"', $html);
        self::assertStringContainsString('id="document-content_json-error"', $html);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;demo&quot;)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert("demo")</script>', $html);
        self::assertDoesNotMatchRegularExpression('/<input[^>]+type="file"[^>]+value=/i', $html);
    }

    public function testUnexpectedDocumentStorageFailureReturnsSanitizedServerError(): void
    {
        $this->insertUser('manager@example.com', 'ManagerPassword123', 'admin', ['documents.view', 'documents.manage']);
        $propertyId = $this->insertProperty();
        $filePath = $this->directory . '/not-a-directory';
        file_put_contents($filePath, 'occupied');
        $app = $this->app('/sub', $filePath);
        $this->login($app, 'manager@example.com', 'ManagerPassword123', '/sub');

        $response = $this->request($app, 'POST', '/sub/admin/documents', [
            'scope' => 'property',
            'scope_id' => (string) $propertyId,
            'type' => 'property_document',
            'title' => 'Archivo de prueba',
        ], ['document' => $this->upload($this->pdfBytes(), 'test.pdf')]);

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('text/html', $response->getHeaderLine('Content-Type'));
        self::assertStringNotContainsString('SQLSTATE', (string) $response->getBody());
        self::assertStringNotContainsString('not-a-directory', (string) $response->getBody());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn());
    }

    public function testManageOnlyValidationResponseDoesNotRevealCurrentDocuments(): void
    {
        $this->insertUser('manager-only@example.com', 'ManagerPassword123', 'admin', ['documents.manage']);
        $propertyId = $this->insertProperty();
        $actorId = (int) $this->pdo->query("SELECT id FROM users WHERE email = 'manager-only@example.com'")->fetchColumn();
        (new \AlquilaPro\Documents\DocumentService($this->pdo, $this->documentDirectory))->createContentVersion(
            $propertyId,
            null,
            'manual_convivencia',
            'Documento no visible para gestor de escritura',
            ['sections' => []],
            null,
            $actorId,
            time(),
        );
        $app = $this->app('/sub');
        $this->login($app, 'manager-only@example.com', 'ManagerPassword123', '/sub');

        $response = $this->request($app, 'POST', '/sub/admin/documents', [
            'scope' => 'property',
            'scope_id' => (string) $propertyId,
            'type' => 'manual_convivencia',
            'title' => 'Nuevo título',
            'content_json' => '{invalid json',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringNotContainsString('Documento no visible para gestor de escritura', (string) $response->getBody());
    }

    private function app(string $basePath = '', ?string $documentDirectory = null): App
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
            'document_directory' => $documentDirectory ?? $this->documentDirectory,
            'pdo' => $this->pdo,
        ]);
    }

    /** @param array<string,mixed> $body
     *  @param array<string,UploadedFile> $uploads
     */
    private function request(App $app, string $method, string $path, array $body = [], array $uploads = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );
        if (strtoupper($method) === 'POST') {
            $body['csrf_token'] = (new Csrf())->token();
            $request = $request->withParsedBody($body);
        }
        if ($uploads !== []) {
            $request = $request->withUploadedFiles($uploads);
        }
        return $app->handle($request);
    }

    private function login(App $app, string $email, string $password, string $basePath): void
    {
        self::assertSame(303, $this->request($app, 'POST', $basePath . '/login', [
            'email' => $email,
            'password' => $password,
        ])->getStatusCode());
    }

    private function insertUser(string $email, string $password, string $role, array $permissions = []): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :name, :email, :password, 1, :permissions, 0)',
        );
        $statement->execute([
            'role' => $role,
            'name' => ucfirst($role) . ' Document User',
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'permissions' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);
    }

    private function insertProperty(): int
    {
        $this->pdo->exec("INSERT INTO properties (name, address, city, sector, is_active) VALUES ('Docs property', '10 Street', 'Medellin', 'North', 1)");
        return (int) $this->pdo->lastInsertId();
    }

    private function upload(string $bytes, string $name, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_document_route_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create document upload fixture.');
        }
        $this->temporaryFiles[] = $path;
        return new UploadedFile($path, $name, 'application/pdf', strlen($bytes), $error, false);
    }

    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    }
}
