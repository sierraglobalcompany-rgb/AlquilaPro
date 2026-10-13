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

final class ContractFinalizationRoutesTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_contract_finalize_routes_' . bin2hex(random_bytes(6));
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

    public function testCandidateEligibilityRequiresActiveCompatibleTemplateVersion(): void
    {
        $ownerId = $this->insertUser(
            'eligibility-owner@example.com',
            'OwnerPassword123',
            'owner',
            [],
        );
        $candidateId = $this->candidateFixture($ownerId, 'Elegibilidad F7.5');
        $service = $this->service();

        self::assertFalse(
            $service->canCreateDraftForCandidate($candidateId),
            'Candidate CTA must stay hidden until a usable compatible template version exists.',
        );

        $parking = $service->createTemplate($ownerId, [
            'name' => 'Parqueadero no compatible',
            'document_type' => 'independent_parking',
        ]);
        $service->createTemplateVersion((int) $parking['id'], $ownerId, [
            'body_template' => 'Contrato {{candidate.full_name}}',
            'variables' => [],
        ]);
        self::assertFalse($service->canCreateDraftForCandidate($candidateId));

        $housing = $service->createTemplate($ownerId, [
            'name' => 'Vivienda compatible',
            'document_type' => 'urban_housing',
        ]);
        self::assertFalse($service->canCreateDraftForCandidate($candidateId));

        $service->createTemplateVersion((int) $housing['id'], $ownerId, [
            'body_template' => 'Contrato {{candidate.full_name}}',
            'variables' => [],
        ]);
        self::assertTrue($service->canCreateDraftForCandidate($candidateId));

        $service->setTemplateActive((int) $housing['id'], false);
        self::assertFalse($service->canCreateDraftForCandidate($candidateId));
    }

    public function testGeneratedContractRoutesAllowTaskUpdateAndFinalizeThenBecomeReadOnly(): void
    {
        $managerId = $this->insertUser(
            'finalize-manager@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $draft = $this->draftFixture($managerId, 'Contrato Finalizable');
        $contractId = (int) $draft['id'];
        $this->service()->generate($contractId, 1791865000);

        $app = $this->app('/sub');
        $this->login($app, 'finalize-manager@example.com', 'ManagerPassword123', '/sub');

        $detail = $this->request($app, 'GET', '/sub/admin/contracts/' . $contractId);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('/sub/admin/contracts/' . $contractId . '/pdf', $html);
        self::assertStringContainsString('value="update_tasks"', $html);
        self::assertStringContainsString('value="finalize"', $html);
        self::assertStringNotContainsString('value="save"', $html);

        $updated = $this->request($app, 'POST', '/sub/admin/contracts/' . $contractId, [
            'action' => 'update_tasks',
            'tasks_json' => json_encode([
                ['label' => 'Firma física recibida', 'done' => true],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertSame(303, $updated->getStatusCode());
        self::assertSame('/sub/admin/contracts/' . $contractId, $updated->getHeaderLine('Location'));
        $tasks = json_decode(
            (string) $this->pdo->query('SELECT tasks_json FROM contracts WHERE id = ' . $contractId)->fetchColumn(),
            true,
            64,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame([['label' => 'Firma física recibida', 'done' => true]], $tasks);

        $finalized = $this->request($app, 'POST', '/sub/admin/contracts/' . $contractId, [
            'action' => 'finalize',
        ]);
        self::assertSame(303, $finalized->getStatusCode());
        self::assertSame('finalized', $this->contractStatus($contractId));

        $finalDetail = $this->request($app, 'GET', '/sub/admin/contracts/' . $contractId);
        $finalHtml = (string) $finalDetail->getBody();
        self::assertStringContainsString('/sub/admin/contracts/' . $contractId . '/pdf', $finalHtml);
        self::assertStringNotContainsString('value="update_tasks"', $finalHtml);
        self::assertStringNotContainsString('value="finalize"', $finalHtml);
        self::assertStringNotContainsString('value="save"', $finalHtml);
        self::assertStringNotContainsString('value="generate"', $finalHtml);
    }

    public function testCandidateDetailShowsContractContextOnlyToContractViewerAndHonorsBasePath(): void
    {
        $ownerId = $this->insertUser(
            'context-owner@example.com',
            'OwnerPassword123',
            'owner',
            [],
        );
        $candidateId = $this->candidateFixture($ownerId, 'Candidata Contexto');
        $service = $this->service();
        $template = $service->createTemplate($ownerId, [
            'name' => 'Vivienda contexto',
            'document_type' => 'urban_housing',
        ]);
        $version = $service->createTemplateVersion((int) $template['id'], $ownerId, [
            'body_template' => 'Contrato {{candidate.full_name}}',
            'variables' => [],
        ]);

        $this->insertUser(
            'candidate-only@example.com',
            'ViewerPassword123',
            'admin',
            ['candidates.view'],
        );
        $candidateOnlyApp = $this->app('/sub');
        $this->login($candidateOnlyApp, 'candidate-only@example.com', 'ViewerPassword123', '/sub');
        $hidden = $this->request($candidateOnlyApp, 'GET', '/sub/admin/candidates/' . $candidateId);
        self::assertSame(200, $hidden->getStatusCode());
        self::assertStringNotContainsString('data-contract-context', (string) $hidden->getBody());

        $_SESSION = [];
        $this->insertUser(
            'candidate-contract-viewer@example.com',
            'ViewerPassword123',
            'admin',
            ['candidates.view', 'contracts.view'],
        );
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'candidate-contract-viewer@example.com', 'ViewerPassword123', '/sub');
        $eligible = $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId);
        $eligibleHtml = (string) $eligible->getBody();
        self::assertStringContainsString('data-contract-context', $eligibleHtml);
        self::assertStringContainsString('/sub/admin/contracts', $eligibleHtml);
        self::assertStringContainsString('Crear contrato', $eligibleHtml);

        $draft = $service->createDraft(
            $candidateId,
            (int) $version['id'],
            $ownerId,
            $this->validInput(),
            1791865100,
        );
        $draftDetail = $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId);
        $draftHtml = (string) $draftDetail->getBody();
        self::assertStringContainsString('/sub/admin/contracts/' . (int) $draft['id'], $draftHtml);
        self::assertStringNotContainsString('Crear contrato', $draftHtml);

        $service->generate((int) $draft['id'], 1791865110);
        $generatedDetail = $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId);
        self::assertStringContainsString(
            '/sub/admin/contracts/' . (int) $draft['id'],
            (string) $generatedDetail->getBody(),
        );

        $service->finalize((int) $draft['id'], $ownerId, 1791865120);
        $finalDetail = $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId);
        $finalHtml = (string) $finalDetail->getBody();
        self::assertStringContainsString('/sub/admin/contracts/' . (int) $draft['id'], $finalHtml);
        self::assertStringContainsString('finalized', $finalHtml);
        self::assertStringNotContainsString('Crear contrato', $finalHtml);
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

    private function candidateFixture(int $userId, string $candidateName): int
    {
        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio F7.5 Contexto',
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
            'code' => 'CTX-' . bin2hex(random_bytes(3)),
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

        return $candidateId;
    }

    /** @return array<string,mixed> */
    private function draftFixture(int $userId, string $candidateName): array
    {
        $candidateId = $this->candidateFixture($userId, $candidateName);
        $service = $this->service();
        $template = $service->createTemplate($userId, [
            'name' => 'Plantilla F7.5 Ruta ' . bin2hex(random_bytes(4)),
            'document_type' => 'urban_housing',
        ]);
        $version = $service->createTemplateVersion((int) $template['id'], $userId, [
            'body_template' => 'Contrato {{candidate.full_name}}',
            'variables' => [],
        ]);

        return $service->createDraft(
            $candidateId,
            (int) $version['id'],
            $userId,
            $this->validInput(),
            1791864000,
        );
    }

    private function validInput(): array
    {
        return [
            'monthly_rent' => '1750000',
            'administration_fee' => '250000',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'duration_text' => 'Doce meses exactos',
            'manual_variables' => [],
            'tasks' => [],
            'annexes' => [],
        ];
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
        array $permissions,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => 'F7.5 Route User',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($directory);
    }
}
