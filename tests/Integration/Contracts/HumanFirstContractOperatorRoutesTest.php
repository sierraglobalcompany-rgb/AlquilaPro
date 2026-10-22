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

final class HumanFirstContractOperatorRoutesTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_hf_contract_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-16T18:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->removeDirectory($this->directory);
    }

    public function testApprovedCandidateLaunchesContextualContractFormWithoutRawCandidateId(): void
    {
        $managerId = $this->insertUser(
            'hf-contract-context@example.com',
            'ManagerPassword123',
            'admin',
            ['candidates.view', 'candidates.manage', 'contracts.view', 'contracts.manage'],
        );
        $fixture = $this->eligibleContractFixture($managerId, 'Candidato Humano');
        $app = $this->app('/sub');
        $this->login($app, 'hf-contract-context@example.com', 'ManagerPassword123', '/sub');

        $candidateHtml = (string) $this->request(
            $app,
            'GET',
            '/sub/admin/candidates/' . $fixture['candidate_id'] . '?section=contracts',
        )->getBody();
        self::assertStringContainsString(
            'href="/sub/admin/contracts?candidate_id=' . $fixture['candidate_id'] . '"',
            $candidateHtml,
        );

        $context = $this->request(
            $app,
            'GET',
            '/sub/admin/contracts?candidate_id=' . $fixture['candidate_id'],
        );
        self::assertSame(200, $context->getStatusCode());
        $contextHtml = (string) $context->getBody();
        self::assertStringContainsString('Candidato Humano', $contextHtml);
        self::assertStringContainsString($fixture['unit_code'], $contextHtml);
        self::assertStringContainsString('name="candidate_id" value="' . $fixture['candidate_id'] . '"', $contextHtml);
        self::assertStringContainsString('type="hidden"', $contextHtml);
        self::assertStringNotContainsString('type="number" min="1" step="1" name="candidate_id"', $contextHtml);

        $form = $this->request(
            $app,
            'GET',
            '/sub/admin/contracts?candidate_id=' . $fixture['candidate_id']
                . '&template_version_id=' . $fixture['version_id'],
        );
        self::assertSame(200, $form->getStatusCode());
        $formHtml = (string) $form->getBody();
        self::assertStringContainsString('name="manual_variables[custom.note]"', $formHtml);
        self::assertStringContainsString('Nota especial', $formHtml);
        self::assertStringContainsString('name="tasks[0][label]"', $formHtml);
        self::assertStringContainsString('name="annexes[0][title]"', $formHtml);
        self::assertStringNotContainsString('Variables manuales JSON', $formHtml);
        self::assertStringNotContainsString('Tareas JSON', $formHtml);
        self::assertStringNotContainsString('Anexos JSON', $formHtml);
    }

    public function testCandidateContextRequiresCandidateViewPermission(): void
    {
        $managerId = $this->insertUser(
            'hf-contract-no-candidate-view@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $fixture = $this->eligibleContractFixture($managerId, 'Candidato Restringido');
        $app = $this->app('/sub');
        $this->login($app, 'hf-contract-no-candidate-view@example.com', 'ManagerPassword123', '/sub');

        $response = $this->request(
            $app,
            'GET',
            '/sub/admin/contracts?candidate_id=' . $fixture['candidate_id'],
        );

        self::assertSame(403, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringNotContainsString('Candidato Restringido', $body);
        self::assertStringNotContainsString($fixture['unit_code'], $body);
    }

    public function testOperatorCreatesDraftFromStructuredContractFields(): void
    {
        $managerId = $this->insertUser(
            'hf-contract-create@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $fixture = $this->eligibleContractFixture($managerId, 'Candidato Creación');
        $app = $this->app('/sub');
        $this->login($app, 'hf-contract-create@example.com', 'ManagerPassword123', '/sub');

        $create = $this->request($app, 'POST', '/sub/admin/contracts', [
            'candidate_id' => (string) $fixture['candidate_id'],
            'template_version_id' => (string) $fixture['version_id'],
            'monthly_rent' => '1750000',
            'administration_fee' => '250000',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'duration_text' => 'Doce meses exactos',
            'manual_variables' => ['custom.note' => 'Nota humana'],
            'tasks' => [
                ['label' => 'Revisar firma', 'done' => '0'],
            ],
            'annexes' => [
                ['title' => 'Inventario', 'note' => 'Entregado al inicio'],
            ],
        ]);

        self::assertSame(303, $create->getStatusCode());
        $contractId = (int) $this->pdo->query('SELECT id FROM contracts ORDER BY id DESC LIMIT 1')->fetchColumn();
        self::assertGreaterThan(0, $contractId);
        self::assertSame('/sub/admin/contracts/' . $contractId, $create->getHeaderLine('Location'));

        $row = $this->pdo->query(
            'SELECT manual_variables_json, tasks_json, annexes_json FROM contracts WHERE id = ' . $contractId,
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(['custom.note' => 'Nota humana'], json_decode((string) $row['manual_variables_json'], true));
        self::assertSame(
            [['label' => 'Revisar firma', 'done' => false]],
            json_decode((string) $row['tasks_json'], true),
        );
        self::assertSame(
            [['title' => 'Inventario', 'note' => 'Entregado al inicio']],
            json_decode((string) $row['annexes_json'], true),
        );
    }

    public function testDraftEditUsesStructuredFieldsAndPreservesCanonicalContractData(): void
    {
        $managerId = $this->insertUser(
            'hf-contract-edit@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $fixture = $this->eligibleContractFixture($managerId, 'Candidato Edición');
        $draft = $this->service()->createDraft(
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
                'tasks' => [['label' => 'Firma inicial', 'done' => false]],
                'annexes' => [['title' => 'Inventario inicial', 'note' => 'Pendiente']],
            ],
            1791864000,
        );
        $contractId = (int) $draft['id'];
        $app = $this->app();
        $this->login($app, 'hf-contract-edit@example.com', 'ManagerPassword123');

        $detail = $this->request($app, 'GET', '/admin/contracts/' . $contractId);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('name="manual_variables[custom.note]"', $html);
        self::assertStringContainsString('value="Inicial"', $html);
        self::assertStringContainsString('name="tasks[0][label]"', $html);
        self::assertStringContainsString('value="Firma inicial"', $html);
        self::assertStringContainsString('name="annexes[0][title]"', $html);
        self::assertStringContainsString('value="Inventario inicial"', $html);
        self::assertStringNotContainsString('Variables manuales JSON', $html);
        self::assertStringNotContainsString('Tareas JSON', $html);
        self::assertStringNotContainsString('Anexos JSON', $html);

        $save = $this->request($app, 'POST', '/admin/contracts/' . $contractId, [
            'action' => 'save',
            'template_version_id' => (string) $fixture['version_id'],
            'monthly_rent' => '1800000',
            'administration_fee' => '',
            'start_date' => '2026-12-01',
            'end_date' => '2027-11-30',
            'duration_text' => '12 meses confirmados',
            'manual_variables' => ['custom.note' => 'Actualizada'],
            'tasks' => [['label' => 'Confirmar firma', 'done' => '1']],
            'annexes' => [['title' => 'Inventario final', 'note' => 'Listo']],
        ]);
        self::assertSame(303, $save->getStatusCode());

        $row = $this->pdo->query(
            'SELECT manual_variables_json, tasks_json, annexes_json FROM contracts WHERE id = ' . $contractId,
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(['custom.note' => 'Actualizada'], json_decode((string) $row['manual_variables_json'], true));
        self::assertSame(
            [['label' => 'Confirmar firma', 'done' => true]],
            json_decode((string) $row['tasks_json'], true),
        );
        self::assertSame(
            [['title' => 'Inventario final', 'note' => 'Listo']],
            json_decode((string) $row['annexes_json'], true),
        );
    }

    public function testGeneratedTaskUpdateUsesStructuredFieldsInsteadOfJson(): void
    {
        $managerId = $this->insertUser(
            'hf-contract-tasks@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $fixture = $this->eligibleContractFixture($managerId, 'Candidato Tareas');
        $draft = $this->service()->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $managerId,
            [
                'monthly_rent' => '1750000',
                'administration_fee' => null,
                'start_date' => '2026-11-01',
                'end_date' => '2027-10-31',
                'duration_text' => 'Doce meses exactos',
                'manual_variables' => ['custom.note' => 'Generada'],
                'tasks' => [['label' => 'Entregar llaves', 'done' => false]],
                'annexes' => [],
            ],
            1791864000,
        );
        $contractId = (int) $draft['id'];
        $this->pdo->exec("UPDATE contracts SET status = 'generated' WHERE id = " . $contractId);

        $app = $this->app();
        $this->login($app, 'hf-contract-tasks@example.com', 'ManagerPassword123');
        $detail = $this->request($app, 'GET', '/admin/contracts/' . $contractId);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('name="tasks[0][label]"', $html);
        self::assertStringContainsString('value="Entregar llaves"', $html);
        self::assertStringNotContainsString('Tareas JSON', $html);

        $update = $this->request($app, 'POST', '/admin/contracts/' . $contractId, [
            'action' => 'update_tasks',
            'tasks' => [['label' => 'Entregar llaves', 'done' => '1']],
        ]);
        self::assertSame(303, $update->getStatusCode());
        self::assertSame(
            [['label' => 'Entregar llaves', 'done' => true]],
            json_decode((string) $this->pdo->query(
                'SELECT tasks_json FROM contracts WHERE id = ' . $contractId,
            )->fetchColumn(), true),
        );
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
    ): void {
        $response = $this->request($app, 'POST', $basePath . '/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame($basePath . '/', $response->getHeaderLine('Location'));
    }

    private function insertUser(string $email, string $password, string $role, array $permissions = []): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, 0)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => 'Human First Contract User',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{property_id:int,unit_id:int,unit_code:string,candidate_id:int,template_id:int,version_id:int} */
    private function eligibleContractFixture(int $userId, string $candidateName): array
    {
        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) '
            . 'VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio Human First',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Medellín',
            'sector' => 'La América',
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unitCode = 'APT-' . strtoupper(bin2hex(random_bytes(3)));
        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, monthly_price, administration_fee, unit_number) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, :status, :monthly_price, :administration_fee, :unit_number)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'type' => 'apartment',
            'code' => $unitCode,
            'location_label' => 'Piso 4',
            'status' => 'available',
            'monthly_price' => '1500000.00',
            'administration_fee' => '220000.00',
            'unit_number' => '401',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $document = strtoupper(bin2hex(random_bytes(5)));
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates '
            . '(unit_id, document_type, document_number, full_name, phone, email, current_address, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :current_address, :status, '
            . ':token_hash, :expires_at, :consent_at)',
        );
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
            'consent_at' => '2026-10-16 18:00:00',
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
            'result_at' => '2026-10-16 18:10:00',
        ]);

        $service = $this->service();
        $template = $service->createTemplate($userId, [
            'name' => 'Plantilla Human First ' . bin2hex(random_bytes(3)),
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
                ['key' => 'custom.note', 'label' => 'Nota especial', 'required' => true],
            ],
        ]);

        return [
            'property_id' => $propertyId,
            'unit_id' => $unitId,
            'unit_code' => $unitCode,
            'candidate_id' => $candidateId,
            'template_id' => (int) $template['id'],
            'version_id' => (int) $version['id'],
        ];
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $items = scandir($directory);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
