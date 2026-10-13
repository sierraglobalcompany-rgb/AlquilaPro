<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Contracts;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Candidates\ScreeningService;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Properties\PropertyService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ContractPdfRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;

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
        foreach ([
            'contracts', 'contract_template_versions', 'contract_templates', 'screening_processes',
            'candidate_documents', 'unit_application_requirements', 'candidate_references', 'candidates',
            'unit_media', 'units', 'properties', 'settings', 'users',
        ] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->directory = sys_get_temp_dir() . '/alquila_contract_pdf_routes_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-13T00:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->removeDirectory($this->directory);
    }

    public function testGenerateRouteRequiresManageCsrfAndDownloadUsesViewBasePathAndPrivateHeaders(): void
    {
        $managerId = $this->insertUser(
            'pdf-manager@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $draft = $this->draftFixture($managerId, 'Candidato PDF Ruta');
        $contractId = (int) $draft['id'];

        $app = $this->app('/sub');
        $this->login($app, 'pdf-manager@example.com', 'ManagerPassword123', '/sub');

        $withoutCsrf = $this->request($app, 'POST', '/sub/admin/contracts/' . $contractId, [
            'action' => 'generate',
        ], false);
        self::assertSame(403, $withoutCsrf->getStatusCode());
        self::assertSame('draft', $this->contractStatus($contractId));

        $generated = $this->request($app, 'POST', '/sub/admin/contracts/' . $contractId, [
            'action' => 'generate',
        ]);
        self::assertSame(303, $generated->getStatusCode());
        self::assertSame('/sub/admin/contracts/' . $contractId, $generated->getHeaderLine('Location'));
        self::assertSame('generated', $this->contractStatus($contractId));

        $_SESSION = [];
        $this->insertUser('pdf-viewer@example.com', 'ViewerPassword123', 'admin', ['contracts.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'pdf-viewer@example.com', 'ViewerPassword123', '/sub');
        $pdf = $this->request($viewerApp, 'GET', '/sub/admin/contracts/' . $contractId . '/pdf');

        self::assertSame(200, $pdf->getStatusCode());
        self::assertSame('application/pdf', $pdf->getHeaderLine('Content-Type'));
        self::assertSame(
            'attachment; filename="contrato-' . $contractId . '.pdf"',
            $pdf->getHeaderLine('Content-Disposition'),
        );
        self::assertSame('private, no-store', $pdf->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $pdf->getHeaderLine('X-Content-Type-Options'));
        self::assertStringStartsWith('%PDF-', (string) $pdf->getBody());
        self::assertStringNotContainsString(
            (string) $this->pdo->query('SELECT pdf_stored_name FROM contracts WHERE id = ' . $contractId)->fetchColumn(),
            $pdf->getHeaderLine('Content-Disposition'),
        );

        $secondManagerId = $this->insertUser(
            'pdf-manager-2@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $secondDraft = $this->draftFixture($secondManagerId, 'Candidato PDF Viewer');
        self::assertSame(403, $this->request($viewerApp, 'POST', '/sub/admin/contracts/' . (int) $secondDraft['id'], [
            'action' => 'generate',
        ])->getStatusCode());
        self::assertSame('draft', $this->contractStatus((int) $secondDraft['id']));

        $_SESSION = [];
        $this->insertUser('pdf-blocked@example.com', 'BlockedPassword123', 'admin', []);
        $blockedApp = $this->app('/sub');
        $this->login($blockedApp, 'pdf-blocked@example.com', 'BlockedPassword123', '/sub');
        self::assertSame(
            403,
            $this->request($blockedApp, 'GET', '/sub/admin/contracts/' . $contractId . '/pdf')->getStatusCode(),
        );
    }

    public function testPdfRouteFailsClosedForDraftMissingAndTamperedEvidence(): void
    {
        $managerId = $this->insertUser(
            'pdf-integrity@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $draft = $this->draftFixture($managerId, 'Candidato Integridad Ruta');
        $contractId = (int) $draft['id'];

        $app = $this->app();
        $this->login($app, 'pdf-integrity@example.com', 'ManagerPassword123');
        self::assertSame(404, $this->request($app, 'GET', '/admin/contracts/' . $contractId . '/pdf')->getStatusCode());
        self::assertSame(404, $this->request($app, 'GET', '/admin/contracts/999999/pdf')->getStatusCode());

        $this->service()->generate($contractId, 1791864060);
        $pdf = $this->service()->privatePdf($contractId);
        self::assertNotNull($pdf);
        file_put_contents($pdf['path'], 'tampered', FILE_APPEND);

        $tampered = $this->request($app, 'GET', '/admin/contracts/' . $contractId . '/pdf');
        self::assertSame(404, $tampered->getStatusCode());
        self::assertSame('', (string) $tampered->getBody());
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
            'pdo' => $this->pdo,
        ]);
    }

    private function service(): ContractService
    {
        $candidates = new CandidateService($this->pdo);

        return new ContractService(
            $this->pdo,
            $candidates,
            new ScreeningService($this->pdo, $candidates),
            new PropertyService($this->pdo),
            $this->directory . '/contracts',
        );
    }

    /** @return array<string,mixed> */
    private function draftFixture(int $userId, string $candidateName): array
    {
        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio PDF Ruta',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Medellín',
            'sector' => 'La América',
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, monthly_price, administration_fee, unit_number) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, :status, :monthly_price, :administration_fee, :unit_number)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'type' => 'apartment',
            'code' => 'APT-' . bin2hex(random_bytes(3)),
            'location_label' => 'Piso 4',
            'status' => 'available',
            'monthly_price' => '1500000.00',
            'administration_fee' => '220000.00',
            'unit_number' => '401',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates '
            . '(unit_id, document_type, document_number, full_name, phone, email, current_address, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :current_address, '
            . ':status, :token_hash, :expires_at, :consent_at)',
        );
        $document = strtoupper(bin2hex(random_bytes(5)));
        $candidate->execute([
            'unit_id' => $unitId,
            'document_type' => 'cc',
            'document_number' => $document,
            'full_name' => $candidateName,
            'phone' => '3001234567',
            'email' => strtolower($document) . '@example.com',
            'current_address' => 'Carrera 50 # 40-20',
            'status' => 'pending_screening',
            'token_hash' => bin2hex(random_bytes(32)),
            'expires_at' => '2027-01-01 00:00:00',
            'consent_at' => '2026-10-12 20:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $screening = $this->pdo->prepare(
            'INSERT INTO screening_processes '
            . '(candidate_id, provider, status, result, responsible_user_id, result_at) '
            . 'VALUES (:candidate_id, :provider, :status, :result, :responsible_user_id, :result_at)',
        );
        $screening->execute([
            'candidate_id' => $candidateId,
            'provider' => 'Aseguradora Demo',
            'status' => 'approved',
            'result' => 'Aprobado',
            'responsible_user_id' => $userId,
            'result_at' => '2026-10-12 21:00:00',
        ]);

        $service = $this->service();
        $template = $service->createTemplate($userId, [
            'name' => 'Plantilla PDF Ruta ' . bin2hex(random_bytes(4)),
            'document_type' => 'urban_housing',
        ]);
        $version = $service->createTemplateVersion((int) $template['id'], $userId, [
            'body_template' => "Contrato {{candidate.full_name}}\nCanon {{contract.monthly_rent}}\nNota {{custom.note}}",
            'variables' => [
                ['key' => 'custom.note', 'label' => 'Nota', 'required' => true],
            ],
        ]);

        return $service->createDraft(
            $candidateId,
            (int) $version['id'],
            $userId,
            [
                'monthly_rent' => '1750000',
                'administration_fee' => '250000',
                'start_date' => '2026-11-01',
                'end_date' => '2027-10-31',
                'duration_text' => 'Doce meses exactos',
                'manual_variables' => ['custom.note' => 'Sin observaciones'],
                'tasks' => [],
                'annexes' => [],
            ],
            1791864000,
        );
    }

    private function contractStatus(int $contractId): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM contracts WHERE id = :id');
        $statement->execute(['id' => $contractId]);

        return (string) $statement->fetchColumn();
    }

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
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

        return $app->handle($request);
    }

    private function login(App $app, string $email, string $password, string $basePath = ''): void
    {
        $response = $this->request($app, 'POST', $basePath . '/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame($basePath . '/', $response->getHeaderLine('Location'));
    }

    private function insertUser(
        string $email,
        string $password,
        string $role,
        array $permissions = [],
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => 'Contract PDF Route User',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            @unlink($directory);
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
