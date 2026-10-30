<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Candidates\CandidateDocumentService;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class CandidatesSecurityRegressionTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $documentDirectory;
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
        foreach (['candidate_documents', 'candidate_references', 'unit_application_requirements', 'candidates', 'unit_media', 'units', 'properties', 'users'] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->directory = sys_get_temp_dir() . '/alquila_candidate_security_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->documentDirectory = $this->directory . '/candidate-documents';
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-12T00:00:00-05:00',
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
        @rmdir($this->directory);
    }

    public function testCapabilityTokensFailClosedAfterMalformedExpiredAndRotationAndNeverReachErrorLogs(): void
    {
        $unit = $this->seedUnit('SEC-TOKEN');
        $documents = new CandidateDocumentService($this->pdo, $this->documentDirectory);
        $service = new CandidateService($this->pdo, candidateDocumentService: $documents);
        $now = time();
        $started = $service->startCandidate($unit['public_key'], $this->candidateInput('SEC-TOKEN-1'), $now);
        $expired = $service->startCandidate($unit['public_key'], $this->candidateInput('SEC-TOKEN-2'), $now);
        $app = $this->app('/sub');

        $app->get('/apply/continue/{token}/explode/test', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
        ): ResponseInterface {
            throw new RuntimeException('forced security regression exception');
        });

        self::assertSame(404, $this->request($app, 'GET', '/sub/apply/continue/short')->getStatusCode());
        self::assertSame(404, $this->request($app, 'GET', '/sub/apply/continue/' . str_repeat('z', 64))->getStatusCode());
        $this->pdo->prepare('UPDATE candidates SET continuation_token_expires_at = :expired WHERE id = :id')->execute([
            'expired' => '2000-01-01 00:00:00',
            'id' => $expired['candidate_id'],
        ]);
        self::assertSame(404, $this->request($app, 'GET', '/sub/apply/continue/' . $expired['continuation_token'])->getStatusCode());

        $newToken = $service->regenerateToken((int) $started['candidate_id'], $now + 1);
        self::assertSame(404, $this->request($app, 'GET', '/sub/apply/continue/' . $started['continuation_token'])->getStatusCode());
        self::assertSame(200, $this->request($app, 'GET', '/sub/apply/continue/' . $newToken . '/personal')->getStatusCode());

        $logPath = $this->directory . '/error.log';
        $oldErrorLog = ini_get('error_log');
        $oldLogErrors = ini_get('log_errors');
        ini_set('log_errors', '1');
        ini_set('error_log', $logPath);
        try {
            self::assertSame(500, $this->request($app, 'GET', '/sub/apply/continue/' . $newToken . '/explode/test')->getStatusCode());
        } finally {
            ini_set('error_log', is_string($oldErrorLog) ? $oldErrorLog : '');
            ini_set('log_errors', is_string($oldLogErrors) ? $oldLogErrors : '1');
        }

        $log = is_file($logPath) ? (string) file_get_contents($logPath) : '';
        self::assertNotSame('', $log);
        self::assertStringNotContainsString($newToken, $log, 'Raw continuation capability token must never be logged.');
        self::assertStringContainsString('/sub/apply/continue/[redacted]/explode/test', $log);
    }

    public function testDuplicateIdentityIsGenericAndThrottle429DoesNotMutate(): void
    {
        $unit = $this->seedUnit('SEC-DUP');
        $app = $this->app();
        $csrf = (new Csrf())->token();
        $first = $this->request($app, 'POST', '/apply/' . $unit['public_key'], $this->candidateInput('SEC-DUP-1') + ['csrf_token' => $csrf]);
        self::assertSame(303, $first->getStatusCode());
        $rawToken = basename($first->getHeaderLine('Location'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $rawToken);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $duplicate = $this->request($app, 'POST', '/apply/' . $unit['public_key'], [
                ...$this->candidateInput('SEC-DUP-1', 'Persona Intrusa', 'intrusa@example.com'),
                'csrf_token' => $csrf,
            ]);
            self::assertSame(422, $duplicate->getStatusCode());
            $html = (string) $duplicate->getBody();
            self::assertStringContainsString('No fue posible iniciar la solicitud.', $html);
            self::assertStringNotContainsString($rawToken, $html);
            self::assertStringNotContainsString('Candidato Seguro', $html);
            self::assertStringNotContainsString('seguro@example.com', $html);
            self::assertStringNotContainsString('duplicad', mb_strtolower($html));
        }

        $blocked = $this->request($app, 'POST', '/apply/' . $unit['public_key'], [
            ...$this->candidateInput('SEC-DUP-1', 'Persona Intrusa', 'intrusa@example.com'),
            'csrf_token' => $csrf,
        ]);
        self::assertSame(429, $blocked->getStatusCode());
        self::assertStringContainsString('Demasiados intentos', (string) $blocked->getBody());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM candidates')->fetchColumn());
        self::assertSame('Candidato Seguro', $this->candidateByDocument('SEC-DUP-1')['full_name']);
    }

    public function testEveryF5PostSurfaceUsesGlobalCsrfAndPublicHtmlKeepsInternalsPrivateWithBasePath(): void
    {
        $unit = $this->seedUnit('SEC-CSRF');
        $other = $this->seedUnit('SEC-CSRF-TARGET');
        $documents = new CandidateDocumentService($this->pdo, $this->documentDirectory);
        $requirement = $documents->createRequirement($unit['id'], [
            'code' => 'identity',
            'label' => 'Identidad',
            'is_required' => 0,
        ]);
        $service = new CandidateService($this->pdo, candidateDocumentService: $documents);
        $started = $service->startCandidate($unit['public_key'], $this->candidateInput('SEC-CSRF-1'), time());
        $candidateId = (int) $started['candidate_id'];

        $this->insertUser('manager@example.com', 'ManagerPassword123', ['candidates.view', 'candidates.manage']);
        $app = $this->app('/sub');
        self::assertSame(303, $this->request($app, 'POST', '/sub/login', [
            'csrf_token' => (new Csrf())->token(),
            'email' => 'manager@example.com',
            'password' => 'ManagerPassword123',
        ])->getStatusCode());

        $beforeCandidate = $this->candidateByDocument('SEC-CSRF-1');
        $beforeRequirementCount = (int) $this->pdo->query('SELECT COUNT(*) FROM unit_application_requirements')->fetchColumn();
        $postCases = [
            ['/sub/apply/' . $unit['public_key'], $this->candidateInput('SEC-CSRF-NEW')],
            ['/sub/apply/continue/' . $started['continuation_token'], ['phone' => '3999999999']],
            ['/sub/apply/continue/' . $started['continuation_token'] . '/references', ['references' => []]],
            ['/sub/apply/continue/' . $started['continuation_token'] . '/unit', ['public_key' => $other['public_key']]],
            ['/sub/apply/continue/' . $started['continuation_token'] . '/documents/identity', []],
            ['/sub/apply/continue/' . $started['continuation_token'] . '/submit', []],
            ['/sub/admin/units/' . $unit['id'] . '/application-requirements', ['code' => 'new-code', 'label' => 'Nuevo']],
            ['/sub/admin/units/' . $unit['id'] . '/application-requirements/' . $requirement['id'], ['label' => 'Mutado']],
            ['/sub/admin/units/' . $unit['id'] . '/application-requirements/order', ['requirement_ids' => [$requirement['id']]]],
            ['/sub/admin/candidates/' . $candidateId . '/status', ['status' => 'withdrawn']],
            ['/sub/admin/candidates/' . $candidateId . '/token/regenerate', []],
        ];
        foreach ($postCases as [$path, $body]) {
            self::assertSame(403, $this->request($app, 'POST', $path, $body)->getStatusCode(), $path);
        }

        self::assertSame($beforeCandidate, $this->candidateByDocument('SEC-CSRF-1'));
        self::assertSame($beforeRequirementCount, (int) $this->pdo->query('SELECT COUNT(*) FROM unit_application_requirements')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM candidates')->fetchColumn());

        $continue = $this->request($app, 'GET', '/sub/apply/continue/' . $started['continuation_token'] . '/personal');
        self::assertSame(200, $continue->getStatusCode());
        $html = (string) $continue->getBody();
        self::assertStringContainsString('action="/sub/apply/continue/' . $started['continuation_token'] . '"', $html);
        foreach (['continuation_token_hash', 'candidate_id', 'document_id', 'stored_name', 'storage/', $this->documentDirectory] as $internal) {
            self::assertStringNotContainsString($internal, $html);
        }
        self::assertStringNotContainsString('/sub/sub/', $html);

        $admin = $this->request($app, 'GET', '/sub/admin/candidates/' . $candidateId);
        self::assertSame(200, $admin->getStatusCode());
        self::assertStringContainsString('/sub/admin/candidates/' . $candidateId, (string) $admin->getBody());
        self::assertStringNotContainsString('/sub/sub/', (string) $admin->getBody());
    }

    public function testPrivateDocumentsRejectCrossCandidatePathTraversalAndFakeMime(): void
    {
        $unit = $this->seedUnit('SEC-DOC');
        $documents = new CandidateDocumentService($this->pdo, $this->documentDirectory);
        $documents->createRequirement($unit['id'], ['code' => 'identity', 'label' => 'Identidad', 'is_required' => 0]);
        $service = new CandidateService($this->pdo, candidateDocumentService: $documents);
        $first = $service->startCandidate($unit['public_key'], $this->candidateInput('SEC-DOC-1'), time());
        $second = $service->startCandidate($unit['public_key'], $this->candidateInput('SEC-DOC-2'), time());
        $document = $documents->addOrReplaceDocument(
            (int) $first['candidate_id'],
            'identity',
            $this->upload($this->pdfBytes(), 'identity.pdf', 'application/pdf'),
        );

        self::assertNotNull($documents->privateDocument((int) $first['candidate_id'], (int) $document['id']));
        self::assertNull($documents->privateDocument((int) $second['candidate_id'], (int) $document['id']));
        $this->pdo->prepare('UPDATE candidate_documents SET stored_name = :stored_name WHERE id = :id')->execute([
            'stored_name' => '../escape.pdf',
            'id' => $document['id'],
        ]);
        self::assertNull($documents->privateDocument((int) $first['candidate_id'], (int) $document['id']));
        $this->pdo->prepare('UPDATE candidate_documents SET stored_name = :stored_name, mime_type = :mime WHERE id = :id')->execute([
            'stored_name' => $document['stored_name'],
            'mime' => 'image/png',
            'id' => $document['id'],
        ]);
        self::assertNull($documents->privateDocument((int) $first['candidate_id'], (int) $document['id']));
    }

    public function testCandidateDocumentStorageIsBlockedFromDirectWebAccess(): void
    {
        $rules = file_get_contents(dirname(__DIR__, 3) . '/.htaccess');
        self::assertIsString($rules);
        self::assertMatchesRegularExpression('/\\bstorage\\b/i', $rules);
        self::assertMatchesRegularExpression('/\\[F(?:,|\\])/', $rules);
        self::assertDoesNotMatchRegularExpression('/\\bassets\\b/i', $rules);
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
            'candidate_document_directory' => $this->documentDirectory,
            'pdo' => $this->pdo,
        ]);
    }

    private function request(App $app, string $method, string $path, array $body = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://alquilapro.sierraglobalcompany.com' . $path);
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }
        return $app->handle($request);
    }

    /** @return array{id:int,public_key:string} */
    private function seedUnit(string $suffix): array
    {
        $property = $this->pdo->prepare(
            "INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, 'Privada', 'Medellín', 'Calasanz', 1)",
        );
        $property->execute(['name' => 'Propiedad ' . $suffix]);
        $propertyId = (int) $this->pdo->lastInsertId();
        $publicKey = bin2hex(random_bytes(12));
        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements) '
            . "VALUES (:property_id, :public_key, 'apartment', :code, 'Ubicación pública', 'available', 1, 1800000, 'Descripción', 'Documentos')",
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'code' => substr($suffix, 0, 20) . '-' . substr($publicKey, 0, 6),
        ]);
        $unitId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) VALUES (:unit_id, 'photo', :stored_name, 'image/jpeg', 1, 1)",
        )->execute(['unit_id' => $unitId, 'stored_name' => 'cover-' . $unitId . '.jpg']);
        return ['id' => $unitId, 'public_key' => $publicKey];
    }

    /** @return array<string,mixed> */
    private function candidateInput(
        string $documentNumber,
        string $fullName = 'Candidato Seguro',
        string $email = 'seguro@example.com',
    ): array {
        return [
            'document_type' => 'cc',
            'document_number' => $documentNumber,
            'full_name' => $fullName,
            'phone' => '3001234567',
            'email' => $email,
            'consent' => '1',
        ];
    }

    private function insertUser(string $email, string $password, array $permissions): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO users (role, full_name, email, password_hash, is_active, permissions_json) "
            . "VALUES ('admin', 'Manager Seguridad', :email, :password_hash, 1, :permissions_json)",
        );
        $statement->execute([
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);
    }

    /** @return array<string,mixed> */
    private function candidateByDocument(string $documentNumber): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM candidates WHERE document_number = :document_number LIMIT 1');
        $statement->execute(['document_number' => $documentNumber]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        return $row;
    }

    private function upload(string $bytes, string $clientName, string $clientType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_security_upload_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create upload fixture.');
        }
        $this->temporaryFiles[] = $path;
        return new UploadedFile($path, $clientName, $clientType, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function pdfBytes(): string
    {
        return \AlquilaPro\Tests\Support\PdfFixtures::validPdf();
    }
}
