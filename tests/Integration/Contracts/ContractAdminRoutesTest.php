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

final class ContractAdminRoutesTest extends TestCase
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
            'contracts',
            'contract_template_versions',
            'contract_templates',
            'screening_processes',
            'candidate_documents',
            'unit_application_requirements',
            'candidate_references',
            'candidates',
            'unit_media',
            'units',
            'properties',
            'settings',
            'users',
        ] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->directory = sys_get_temp_dir() . '/alquila_contract_admin_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-12T23:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory . '/branding');
        @rmdir($this->directory . '/unit-media');
        @rmdir($this->directory . '/candidate-documents');
        @rmdir($this->directory . '/contracts');
        @rmdir($this->directory);
    }

    public function testTemplateReadsReuseAuthViewForcedPasswordOwnerAndLauncherWithBasePath(): void
    {
        $guestApp = $this->app('/sub');
        $guest = $this->request($guestApp, 'GET', '/sub/admin/contract-templates');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $this->insertUser('blocked@example.com', 'BlockedPassword123', 'admin', []);
        $blockedApp = $this->app('/sub');
        $this->login($blockedApp, 'blocked@example.com', 'BlockedPassword123', '/sub');
        self::assertSame(403, $this->request($blockedApp, 'GET', '/sub/admin/contract-templates')->getStatusCode());

        $_SESSION = [];
        $this->insertUser('forced@example.com', 'ForcedPassword123', 'admin', ['contracts.view'], true);
        $forcedApp = $this->app('/sub');
        $this->login($forcedApp, 'forced@example.com', 'ForcedPassword123', '/sub', '/sub/account/password');
        $forced = $this->request($forcedApp, 'GET', '/sub/admin/contract-templates');
        self::assertSame(302, $forced->getStatusCode());
        self::assertSame('/sub/account/password', $forced->getHeaderLine('Location'));

        $_SESSION = [];
        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['contracts.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'viewer@example.com', 'ViewerPassword123', '/sub');
        $viewer = $this->request($viewerApp, 'GET', '/sub/admin/contract-templates');
        self::assertSame(200, $viewer->getStatusCode());
        self::assertStringContainsString('Plantillas de contrato', (string) $viewer->getBody());
        self::assertStringNotContainsString('data-contract-template-create', (string) $viewer->getBody());

        $launcher = (string) $this->request($viewerApp, 'GET', '/sub/')->getBody();
        self::assertStringContainsString('href="/sub/admin/contract-templates"', $launcher);
        self::assertStringContainsString('Contratos', $launcher);

        $_SESSION = [];
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $ownerApp = $this->app('/sub');
        $this->login($ownerApp, 'owner@example.com', 'OwnerPassword123', '/sub');
        self::assertSame(200, $this->request($ownerApp, 'GET', '/sub/admin/contract-templates')->getStatusCode());
    }

    public function testManagerCanCreateToggleAndVersionWhileViewerCannotMutate(): void
    {
        $managerId = $this->insertUser(
            'manager@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $app = $this->app('/sub');
        $this->login($app, 'manager@example.com', 'ManagerPassword123', '/sub');

        $create = $this->request($app, 'POST', '/sub/admin/contract-templates', [
            'name' => '<script>alert(1)</script>',
            'document_type' => 'urban_housing',
        ]);
        self::assertSame(303, $create->getStatusCode());
        $templateId = (int) $this->pdo->query('SELECT id FROM contract_templates LIMIT 1')->fetchColumn();
        self::assertGreaterThan(0, $templateId);
        self::assertSame('/sub/admin/contract-templates/' . $templateId, $create->getHeaderLine('Location'));

        $detail = $this->request($app, 'GET', '/sub/admin/contract-templates/' . $templateId);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('action="/sub/admin/contract-templates/' . $templateId . '"', $html);
        self::assertStringContainsString('name="body_template"', $html);

        $version = $this->request($app, 'POST', '/sub/admin/contract-templates/' . $templateId, [
            'action' => 'new_version',
            'body_template' => 'Contrato {{candidate.full_name}} / {{custom.note}}',
            'variables_json' => '[{"key":"custom.note","label":"<b>Nota</b>","required":true}]',
        ]);
        self::assertSame(303, $version->getStatusCode());
        self::assertSame(1, $this->versionCount($templateId));
        self::assertSame($managerId, (int) $this->pdo->query(
            'SELECT created_by_user_id FROM contract_template_versions WHERE template_id = ' . $templateId,
        )->fetchColumn());

        $versionHtml = (string) $this->request($app, 'GET', '/sub/admin/contract-templates/' . $templateId)->getBody();
        self::assertStringContainsString('&lt;b&gt;Nota&lt;/b&gt;', $versionHtml);
        self::assertStringNotContainsString('<b>Nota</b>', $versionHtml);

        $toggle = $this->request($app, 'POST', '/sub/admin/contract-templates/' . $templateId, [
            'action' => 'toggle',
            'active' => '0',
        ]);
        self::assertSame(303, $toggle->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query(
            'SELECT is_active FROM contract_templates WHERE id = ' . $templateId,
        )->fetchColumn());

        self::assertSame(422, $this->request($app, 'POST', '/sub/admin/contract-templates/' . $templateId, [
            'action' => 'delete',
        ])->getStatusCode());
        self::assertSame(1, $this->versionCount($templateId));

        $_SESSION = [];
        $this->insertUser('readonly@example.com', 'ReadonlyPassword123', 'admin', ['contracts.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'readonly@example.com', 'ReadonlyPassword123', '/sub');
        self::assertSame(403, $this->request($viewerApp, 'POST', '/sub/admin/contract-templates/' . $templateId, [
            'action' => 'toggle',
            'active' => '1',
        ])->getStatusCode());
    }

    public function testTemplateMutationsRequireGlobalCsrfAndMalformedVariablesFailClosed(): void
    {
        $managerId = $this->insertUser(
            'csrf@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $service = $this->service();
        $template = $service->createTemplate($managerId, [
            'name' => 'CSRF base',
            'document_type' => 'urban_housing',
        ]);
        $templateId = (int) $template['id'];

        $app = $this->app();
        $this->login($app, 'csrf@example.com', 'ManagerPassword123');
        $withoutCsrf = $this->request($app, 'POST', '/admin/contract-templates/' . $templateId, [
            'action' => 'new_version',
            'body_template' => 'Contrato {{candidate.full_name}}',
            'variables_json' => '[]',
        ], false);
        self::assertSame(403, $withoutCsrf->getStatusCode());
        self::assertSame(0, $this->versionCount($templateId));

        $malformed = $this->request($app, 'POST', '/admin/contract-templates/' . $templateId, [
            'action' => 'new_version',
            'body_template' => 'Contrato {{candidate.full_name}}',
            'variables_json' => '{not-json}',
        ]);
        self::assertSame(422, $malformed->getStatusCode());
        self::assertSame(0, $this->versionCount($templateId));
    }

    public function testContractDraftRoutesReuseAuthPermissionsBasePathAndSafePreview(): void
    {
        $managerId = $this->insertUser(
            'draft-manager@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $fixture = $this->eligibleContractFixture($managerId, '<script>alert("candidate")</script>');

        $guestApp = $this->app('/sub');
        $guest = $this->request($guestApp, 'GET', '/sub/admin/contracts');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $app = $this->app('/sub');
        $this->login($app, 'draft-manager@example.com', 'ManagerPassword123', '/sub');
        $index = $this->request($app, 'GET', '/sub/admin/contracts');
        self::assertSame(200, $index->getStatusCode());
        $indexHtml = (string) $index->getBody();
        self::assertStringContainsString('Contratos', $indexHtml);
        self::assertStringContainsString('Ir a candidatos', $indexHtml);
        self::assertStringContainsString('href="/sub/admin/candidates"', $indexHtml);
        self::assertStringNotContainsString('name="candidate_id"', $indexHtml);
        self::assertStringNotContainsString('name="template_version_id"', $indexHtml);

        $create = $this->request($app, 'POST', '/sub/admin/contracts', [
            'candidate_id' => (string) $fixture['candidate_id'],
            'template_version_id' => (string) $fixture['version_id'],
            'unit_id' => '999999',
            'monthly_rent' => '1750000',
            'administration_fee' => '250000',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'duration_text' => 'Doce meses exactos',
            'manual_variables_json' => '{"custom.note":"<img src=x onerror=alert(1)>"}',
            'tasks_json' => '[{"label":"Revisar firma","done":false}]',
            'annexes_json' => '[{"title":"Inventario","note":"<script>annex()</script>"}]',
        ]);
        self::assertSame(303, $create->getStatusCode());
        $contractId = (int) $this->pdo->query('SELECT id FROM contracts LIMIT 1')->fetchColumn();
        self::assertGreaterThan(0, $contractId);
        self::assertSame('/sub/admin/contracts/' . $contractId, $create->getHeaderLine('Location'));
        self::assertSame($fixture['unit_id'], (int) $this->pdo->query(
            'SELECT unit_id FROM contracts WHERE id = ' . $contractId,
        )->fetchColumn());

        $detail = $this->request($app, 'GET', '/sub/admin/contracts/' . $contractId);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('BORRADOR', $html);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;candidate&quot;)&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringContainsString('&lt;script&gt;annex()&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert("candidate")</script>', $html);
        self::assertStringContainsString('action="/sub/admin/contracts/' . $contractId . '"', $html);
        self::assertStringContainsString('name="action" value="save"', $html);
    }

    public function testContractDraftMutationRequiresManageCsrfSaveAllowlistAndValidJson(): void
    {
        $managerId = $this->insertUser(
            'draft-csrf@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $fixture = $this->eligibleContractFixture($managerId, 'Candidato Ruta');
        $service = $this->service();
        $draft = $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $managerId,
            [
                'monthly_rent' => '1750000',
                'administration_fee' => '250000',
                'start_date' => '2026-11-01',
                'end_date' => '2027-10-31',
                'duration_text' => 'Doce meses exactos',
                'manual_variables' => ['custom.note' => 'Inicial'],
                'tasks' => [],
                'annexes' => [],
            ],
            1791864000,
        );
        $contractId = (int) $draft['id'];

        $app = $this->app();
        $this->login($app, 'draft-csrf@example.com', 'ManagerPassword123');
        self::assertSame(403, $this->request($app, 'POST', '/admin/contracts/' . $contractId, [
            'action' => 'save',
            'monthly_rent' => '1800000',
        ], false)->getStatusCode());

        self::assertSame(422, $this->request($app, 'POST', '/admin/contracts/' . $contractId, [
            'action' => 'delete',
        ])->getStatusCode());
        self::assertSame(422, $this->request($app, 'POST', '/admin/contracts/' . $contractId, [
            'action' => 'save',
            'monthly_rent' => '1800000',
            'administration_fee' => '',
            'start_date' => '2026-12-01',
            'end_date' => '2027-11-30',
            'duration_text' => '12 meses',
            'manual_variables_json' => '{bad-json}',
            'tasks_json' => '[]',
            'annexes_json' => '[]',
        ])->getStatusCode());

        $save = $this->request($app, 'POST', '/admin/contracts/' . $contractId, [
            'action' => 'save',
            'template_version_id' => (string) $fixture['version_id'],
            'monthly_rent' => '1800000',
            'administration_fee' => '',
            'start_date' => '2026-12-01',
            'end_date' => '2027-11-30',
            'duration_text' => '12 meses confirmados',
            'manual_variables_json' => '{"custom.note":"Actualizada"}',
            'tasks_json' => '[{"label":"Confirmar firma","done":true}]',
            'annexes_json' => '[]',
        ]);
        self::assertSame(303, $save->getStatusCode());
        self::assertSame('1800000.00', (string) $this->pdo->query(
            'SELECT monthly_rent FROM contracts WHERE id = ' . $contractId,
        )->fetchColumn());

        $_SESSION = [];
        $this->insertUser('draft-viewer@example.com', 'ViewerPassword123', 'admin', ['contracts.view']);
        $viewerApp = $this->app();
        $this->login($viewerApp, 'draft-viewer@example.com', 'ViewerPassword123');
        $viewerDetail = (string) $this->request($viewerApp, 'GET', '/admin/contracts/' . $contractId)->getBody();
        self::assertStringNotContainsString('name="action" value="save"', $viewerDetail);
        self::assertSame(403, $this->request($viewerApp, 'POST', '/admin/contracts/' . $contractId, [
            'action' => 'save',
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

    private function login(
        App $app,
        string $email,
        string $password,
        string $basePath = '',
        string $expectedLocation = '/',
    ): void {
        $response = $this->request($app, 'POST', $basePath . '/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame($expectedLocation === '/' ? $basePath . '/' : $expectedLocation, $response->getHeaderLine('Location'));
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
            'full_name' => 'Contract Route User',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{property_id:int,unit_id:int,candidate_id:int,template_id:int,version_id:int} */
    private function eligibleContractFixture(int $userId, string $candidateName): array
    {
        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) '
            . 'VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio Ruta',
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
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :current_address, :status, '
            . ':token_hash, :expires_at, :consent_at)',
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
            'name' => 'Plantilla Ruta ' . bin2hex(random_bytes(3)),
            'document_type' => 'urban_housing',
        ]);
        $version = $service->createTemplateVersion((int) $template['id'], $userId, [
            'body_template' => implode("\n", [
                'Contrato {{candidate.full_name}}',
                'Unidad {{unit.code}}',
                'Canon {{contract.monthly_rent}}',
                'Inicio {{contract.start_date}}',
                'Fin {{contract.end_date}}',
                'Duración {{contract.duration}}',
                'Nota {{custom.note}}',
            ]),
            'variables' => [
                ['key' => 'custom.note', 'label' => 'Nota', 'required' => true],
            ],
        ]);

        return [
            'property_id' => $propertyId,
            'unit_id' => $unitId,
            'candidate_id' => $candidateId,
            'template_id' => (int) $template['id'],
            'version_id' => (int) $version['id'],
        ];
    }

    private function versionCount(int $templateId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM contract_template_versions WHERE template_id = :template_id',
        );
        $statement->execute(['template_id' => $templateId]);

        return (int) $statement->fetchColumn();
    }
}
