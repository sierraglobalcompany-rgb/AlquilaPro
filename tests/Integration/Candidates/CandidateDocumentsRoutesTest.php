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
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class CandidateDocumentsRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $documentDirectory;
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

        $this->directory = sys_get_temp_dir() . '/alquila_candidate_document_routes_' . bin2hex(random_bytes(6));
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

    public function testCandidateManagerCanManageRequirementsInsideExistingUnitContext(): void
    {
        $this->insertUser('candidate-manager@example.com', 'CandidatePassword123', 'admin', ['candidates.manage']);
        $propertyId = $this->insertProperty('Edificio Requisitos');
        $unit = $this->insertUnit($propertyId, 'A-501');
        $app = $this->app('/sub');
        $this->login($app, 'candidate-manager@example.com', 'CandidatePassword123', '/sub');

        $detail = $this->request($app, 'GET', '/sub/admin/units/' . $unit['id']);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('Requisitos documentales', $html);
        self::assertStringContainsString('action="/sub/admin/units/' . $unit['id'] . '/application-requirements"', $html);
        self::assertStringNotContainsString('Guardar unidad', $html);

        $first = $this->request($app, 'POST', '/sub/admin/units/' . $unit['id'] . '/application-requirements', [
            'code' => 'identity',
            'label' => '<script>alert(1)</script>',
            'description' => '<b>Ambas caras</b>',
            'is_required' => '1',
            'is_active' => '1',
        ]);
        self::assertSame(303, $first->getStatusCode());
        self::assertSame('/sub/admin/units/' . $unit['id'], $first->getHeaderLine('Location'));

        $second = $this->request($app, 'POST', '/sub/admin/units/' . $unit['id'] . '/application-requirements', [
            'code' => 'income',
            'label' => 'Ingresos',
            'is_required' => '0',
            'is_active' => '1',
        ]);
        self::assertSame(303, $second->getStatusCode());

        $requirements = $this->pdo->query(
            'SELECT id, code FROM unit_application_requirements WHERE unit_id = ' . $unit['id'] . ' ORDER BY id ASC',
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $requirements);
        $identityId = (int) $requirements[0]['id'];
        $incomeId = (int) $requirements[1]['id'];

        $rendered = (string) $this->request($app, 'GET', '/sub/admin/units/' . $unit['id'])->getBody();
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $rendered);
        self::assertStringContainsString('&lt;b&gt;Ambas caras&lt;/b&gt;', $rendered);
        self::assertStringNotContainsString('<script>alert(1)</script>', $rendered);

        $update = $this->request(
            $app,
            'POST',
            '/sub/admin/units/' . $unit['id'] . '/application-requirements/' . $identityId,
            [
                'label' => 'Identidad actualizada',
                'description' => 'Legible',
                'is_required' => '1',
                'is_active' => '0',
            ],
        );
        self::assertSame(303, $update->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query(
            'SELECT is_active FROM unit_application_requirements WHERE id = ' . $identityId,
        )->fetchColumn());

        $reorder = $this->request(
            $app,
            'POST',
            '/sub/admin/units/' . $unit['id'] . '/application-requirements/order',
            ['requirement_ids' => [$incomeId, $identityId]],
        );
        self::assertSame(303, $reorder->getStatusCode());
        $order = $this->pdo->query(
            'SELECT id FROM unit_application_requirements WHERE unit_id = ' . $unit['id'] . ' ORDER BY sort_order ASC, id ASC',
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([$incomeId, $identityId], array_map('intval', $order));

        self::assertSame(
            404,
            $this->request(
                $app,
                'POST',
                '/sub/admin/units/' . $unit['id'] . '/application-requirements/' . $identityId . '/delete',
            )->getStatusCode(),
        );
    }

    public function testRequirementMutationsRequireCandidatesManageCsrfAndHonorPasswordGate(): void
    {
        $propertyId = $this->insertProperty('Edificio Permisos');
        $unit = $this->insertUnit($propertyId, 'A-502');

        $this->insertUser(
            'property-manager@example.com',
            'PropertyPassword123',
            'admin',
            ['properties.view', 'properties.manage'],
        );
        $app = $this->app();
        $this->login($app, 'property-manager@example.com', 'PropertyPassword123');
        self::assertSame(403, $this->request($app, 'POST', '/admin/units/' . $unit['id'] . '/application-requirements', [
            'code' => 'identity',
            'label' => 'Identidad',
        ])->getStatusCode());
        self::assertSame(0, $this->requirementCount());

        $_SESSION = [];
        $this->insertUser(
            'candidate-manager@example.com',
            'CandidatePassword123',
            'admin',
            ['candidates.manage'],
        );
        $candidateApp = $this->app();
        $this->login($candidateApp, 'candidate-manager@example.com', 'CandidatePassword123');
        self::assertSame(403, $this->request(
            $candidateApp,
            'POST',
            '/admin/units/' . $unit['id'] . '/application-requirements',
            ['code' => 'identity', 'label' => 'Identidad'],
            [],
            false,
        )->getStatusCode());
        self::assertSame(0, $this->requirementCount());

        $_SESSION = [];
        $this->insertUser(
            'forced@example.com',
            'ForcedPassword123',
            'admin',
            ['candidates.manage'],
            true,
        );
        $forcedApp = $this->app();
        $login = $this->request($forcedApp, 'POST', '/login', [
            'email' => 'forced@example.com',
            'password' => 'ForcedPassword123',
        ]);
        self::assertSame('/account/password', $login->getHeaderLine('Location'));
        $blocked = $this->request($forcedApp, 'GET', '/admin/units/' . $unit['id']);
        self::assertSame(302, $blocked->getStatusCode());
        self::assertSame('/account/password', $blocked->getHeaderLine('Location'));

        $_SESSION = [];
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $ownerApp = $this->app();
        $this->login($ownerApp, 'owner@example.com', 'OwnerPassword123');
        self::assertSame(303, $this->request($ownerApp, 'POST', '/admin/units/' . $unit['id'] . '/application-requirements', [
            'code' => 'identity',
            'label' => 'Identidad',
        ])->getStatusCode());
        self::assertSame(1, $this->requirementCount());
    }

    public function testPublicContinueShowsActiveRequirementsAndNoInternalDocumentIds(): void
    {
        $unit = $this->insertPublicableUnit('A-601', str_repeat('a', 24));
        $documents = $this->documents();
        $documents->createRequirement($unit['id'], [
            'code' => 'identity',
            'label' => '<strong>Documento identidad</strong>',
            'description' => '<em>Ambas caras</em>',
            'is_required' => 1,
            'is_active' => 1,
        ]);
        $documents->createRequirement($unit['id'], [
            'code' => 'inactive',
            'label' => 'No mostrar',
            'is_required' => 1,
            'is_active' => 0,
        ]);
        $started = (new CandidateService($this->pdo))->startCandidate(
            $unit['public_key'],
            $this->candidateInput('DOC-ROUTES-1'),
            time(),
        );

        $app = $this->app('/sub');
        $path = '/sub/apply/continue/' . $started['continuation_token'];
        $response = $this->request($app, 'GET', $path);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('Documentos', $html);
        self::assertStringContainsString('&lt;strong&gt;Documento identidad&lt;/strong&gt;', $html);
        self::assertStringContainsString('&lt;em&gt;Ambas caras&lt;/em&gt;', $html);
        self::assertStringNotContainsString('No mostrar', $html);
        self::assertStringContainsString(
            'action="' . $path . '/documents/identity"',
            $html,
        );
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertStringContainsString('action="' . $path . '/submit"', $html);
        foreach (['candidate_id', 'requirement_id', 'document_id', 'stored_name', 'storage/', $this->documentDirectory] as $secret) {
            self::assertStringNotContainsString($secret, $html);
        }
        self::assertStringNotContainsString('/sub/sub/', $html);
    }

    public function testPublicUploadAndSubmitUseSharedStorageAndBasePath(): void
    {
        $unit = $this->insertPublicableUnit('A-602', str_repeat('b', 24));
        $documents = $this->documents();
        $documents->createRequirement($unit['id'], [
            'code' => 'identity',
            'label' => 'Identidad',
            'is_required' => 1,
            'is_active' => 1,
        ]);
        $started = (new CandidateService($this->pdo))->startCandidate(
            $unit['public_key'],
            $this->candidateInput('DOC-ROUTES-2'),
            time(),
        );
        $app = $this->app('/sub');
        $continuePath = '/sub/apply/continue/' . $started['continuation_token'];

        $missing = $this->request($app, 'POST', $continuePath . '/submit');
        self::assertSame(422, $missing->getStatusCode());
        self::assertSame('new', $this->candidateStatus($started['candidate_id']));

        $withoutCsrf = $this->request(
            $app,
            'POST',
            $continuePath . '/documents/identity',
            [],
            ['document' => $this->upload($this->pdfBytes(), 'identity.pdf', 'application/pdf')],
            false,
        );
        self::assertSame(403, $withoutCsrf->getStatusCode());
        self::assertSame(0, $this->documentCount());

        $upload = $this->request(
            $app,
            'POST',
            $continuePath . '/documents/identity',
            [],
            ['document' => $this->upload($this->pdfBytes(), 'identity.pdf', 'application/pdf')],
        );
        self::assertSame(303, $upload->getStatusCode());
        self::assertSame($continuePath, $upload->getHeaderLine('Location'));
        self::assertSame(1, $this->documentCount());
        $storedName = (string) $this->pdo->query('SELECT stored_name FROM candidate_documents LIMIT 1')->fetchColumn();
        self::assertFileExists($this->documentDirectory . '/' . $storedName);

        $submit = $this->request($app, 'POST', $continuePath . '/submit');
        self::assertSame(303, $submit->getStatusCode());
        self::assertSame($continuePath, $submit->getHeaderLine('Location'));
        self::assertSame('verification', $this->candidateStatus($started['candidate_id']));

        $after = $this->request(
            $app,
            'POST',
            $continuePath . '/documents/identity',
            [],
            ['document' => $this->upload($this->pdfBytes(), 'again.pdf', 'application/pdf')],
        );
        self::assertSame(422, $after->getStatusCode());
        self::assertSame(1, $this->documentCount());
    }

    public function testPublicDocumentUploadSharesExistingContinueThrottle(): void
    {
        $unit = $this->insertPublicableUnit('A-603', str_repeat('c', 24));
        $documents = $this->documents();
        $documents->createRequirement($unit['id'], [
            'code' => 'identity',
            'label' => 'Identidad',
            'is_required' => 0,
            'is_active' => 1,
        ]);
        $started = (new CandidateService($this->pdo))->startCandidate(
            $unit['public_key'],
            $this->candidateInput('DOC-ROUTES-3'),
            time(),
        );
        $app = $this->app();
        $continuePath = '/apply/continue/' . $started['continuation_token'];

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->request($app, 'POST', $continuePath, [
                'phone' => '30000000' . str_pad((string) $attempt, 2, '0', STR_PAD_LEFT),
            ]);
            self::assertSame(303, $response->getStatusCode());
        }

        $blocked = $this->request(
            $app,
            'POST',
            $continuePath . '/documents/identity',
            [],
            ['document' => $this->upload($this->pdfBytes(), 'identity.pdf', 'application/pdf')],
        );
        self::assertSame(429, $blocked->getStatusCode());
        self::assertStringContainsString('Demasiados intentos', (string) $blocked->getBody());
        self::assertSame(0, $this->documentCount());
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

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        array $uploads = [],
        bool $withCsrf = true,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );

        if (strtoupper($method) === 'POST' && $withCsrf) {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }
        if ($uploads !== []) {
            $request = $request->withUploadedFiles($uploads);
        }

        return $app->handle($request);
    }

    private function login(App $app, string $email, string $password, string $basePath = ''): void
    {
        $response = $this->request($app, 'POST', $basePath . '/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(303, $response->getStatusCode());
    }

    private function documents(): CandidateDocumentService
    {
        return new CandidateDocumentService($this->pdo, $this->documentDirectory);
    }

    private function insertUser(
        string $email,
        string $password,
        string $role,
        array $permissions = [],
        bool $mustChangePassword = false,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, :must_change_password)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => ucfirst($role) . ' Test',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertProperty(string $name): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector) VALUES (:name, :address, :city, :sector)',
        );
        $statement->execute([
            'name' => $name,
            'address' => 'Calle 1 # 2-3',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{id:int,public_key:string} */
    private function insertUnit(int $propertyId, string $code): array
    {
        $publicKey = bin2hex(random_bytes(12));
        $statement = $this->pdo->prepare(
            "INSERT INTO units (property_id, public_key, type, code, status) VALUES (:property_id, :public_key, 'apartment', :code, 'available')",
        );
        $statement->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'code' => $code,
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'public_key' => $publicKey];
    }

    /** @return array{id:int,public_key:string} */
    private function insertPublicableUnit(string $code, string $publicKey): array
    {
        $propertyId = $this->insertProperty('Propiedad ' . $code);
        $statement = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements) '
            . "VALUES (:property_id, :public_key, 'apartment', :code, 'Calasanz', 'available', 1, '1800000.00', 'Disponible', 'Documentos')",
        );
        $statement->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'code' => $code,
        ]);
        $unitId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', 'cover.jpg', 'image/jpeg', 0, 1)",
        )->execute(['unit_id' => $unitId]);

        return ['id' => $unitId, 'public_key' => $publicKey];
    }

    /** @return array<string,mixed> */
    private function candidateInput(string $documentNumber): array
    {
        return [
            'document_type' => 'cc',
            'document_number' => $documentNumber,
            'full_name' => 'Candidato Rutas',
            'phone' => '3001234567',
            'email' => strtolower($documentNumber) . '@example.com',
            'consent' => true,
        ];
    }

    private function upload(string $bytes, string $clientName, string $clientType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_candidate_route_upload_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create candidate document upload fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $clientName, $clientType, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    }

    private function requirementCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM unit_application_requirements')->fetchColumn();
    }

    private function documentCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM candidate_documents')->fetchColumn();
    }

    private function candidateStatus(int $candidateId): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM candidates WHERE id = :id');
        $statement->execute(['id' => $candidateId]);

        return (string) $statement->fetchColumn();
    }
}
