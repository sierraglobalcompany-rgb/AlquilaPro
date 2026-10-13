<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Contracts;

use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Candidates\ScreeningService;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Properties\PropertyService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContractFinalizationTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private int $userId;

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
            'users',
        ] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->directory = sys_get_temp_dir() . '/alquila_contract_finalize_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->userId = $this->insertUser();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testTasksCanChangeOnDraftAndGeneratedWithoutMutatingFrozenEvidence(): void
    {
        $service = $this->service();
        $this->requireFinalizationSurface($service);
        $fixture = $this->fixture();

        $draft = $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(),
            1791864000,
        );

        $draftUpdated = $service->updateTasks((int) $draft['id'], [
            ['label' => 'Validar documentos', 'done' => true],
        ], 1791864010);
        self::assertSame('draft', $draftUpdated['status']);
        self::assertSame(
            [['label' => 'Validar documentos', 'done' => true]],
            json_decode((string) $draftUpdated['tasks_json'], true, 64, JSON_THROW_ON_ERROR),
        );

        $generated = $service->generate((int) $draft['id'], 1791864020);
        $frozen = [
            'monthly_rent' => $generated['monthly_rent'],
            'administration_fee' => $generated['administration_fee'],
            'annexes_json' => $generated['annexes_json'],
            'resolved_variables_json' => $generated['resolved_variables_json'],
            'snapshot_html' => $generated['snapshot_html'],
            'pdf_stored_name' => $generated['pdf_stored_name'],
            'pdf_sha256' => $generated['pdf_sha256'],
            'pdf_size_bytes' => $generated['pdf_size_bytes'],
            'generated_at' => $generated['generated_at'],
        ];

        $updated = $service->updateTasks((int) $generated['id'], [
            ['label' => 'Firma física recibida', 'done' => false],
        ], 1791864030);

        self::assertSame('generated', $updated['status']);
        self::assertSame(
            [['label' => 'Firma física recibida', 'done' => false]],
            json_decode((string) $updated['tasks_json'], true, 64, JSON_THROW_ON_ERROR),
        );
        foreach ($frozen as $key => $value) {
            self::assertSame($value, $updated[$key], 'updateTasks mutated frozen field ' . $key);
        }

        $this->assertRejected(fn () => $service->updateTasks(
            (int) $generated['id'],
            [['label' => 'Inválida', 'done' => 'yes']],
            1791864040,
        ));
    }

    public function testFinalizeRequiresGeneratedAndIsTerminalWithoutCreatingF8State(): void
    {
        $service = $this->service();
        $this->requireFinalizationSurface($service);
        $fixture = $this->fixture();
        $draft = $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(),
            1791864000,
        );

        $this->assertRejected(fn () => $service->finalize((int) $draft['id'], $this->userId, 1791864100));
        $generated = $service->generate((int) $draft['id'], 1791864110);

        $candidateBefore = (new CandidateService($this->pdo))->findCandidate($fixture['candidate_id']);
        $unitBefore = (new PropertyService($this->pdo))->findUnit($fixture['unit_id']);
        $usersBefore = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        $finalized = $service->finalize((int) $generated['id'], $this->userId, 1791864120);

        self::assertSame('finalized', $finalized['status']);
        self::assertSame($this->userId, $finalized['finalized_by_user_id']);
        self::assertSame(gmdate('Y-m-d H:i:s', 1791864120), $finalized['finalized_at']);
        self::assertSame($generated['snapshot_html'], $finalized['snapshot_html']);
        self::assertSame($generated['pdf_sha256'], $finalized['pdf_sha256']);
        self::assertSame($generated['pdf_size_bytes'], $finalized['pdf_size_bytes']);

        $candidateAfter = (new CandidateService($this->pdo))->findCandidate($fixture['candidate_id']);
        $unitAfter = (new PropertyService($this->pdo))->findUnit($fixture['unit_id']);
        self::assertSame($candidateBefore['status'], $candidateAfter['status']);
        self::assertSame($unitBefore['status'], $unitAfter['status']);
        self::assertSame($usersBefore, (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());

        $this->assertRejected(fn () => $service->finalize((int) $generated['id'], $this->userId, 1791864130));
        $this->assertRejected(fn () => $service->updateTasks(
            (int) $generated['id'],
            [['label' => 'No debe cambiar', 'done' => true]],
            1791864140,
        ));
        $this->assertRejected(fn () => $service->updateDraft(
            (int) $generated['id'],
            $this->validInput(),
            1791864150,
        ));
        $this->assertRejected(fn () => $service->generate((int) $generated['id'], 1791864160));
    }

    public function testWithdrawnCandidateBlocksFinalizeAndCandidateLockPreventsDoubleFinalized(): void
    {
        $service = $this->service();
        $this->requireFinalizationSurface($service);
        $fixture = $this->fixture();

        $firstDraft = $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(),
            1791864000,
        );
        $first = $service->generate((int) $firstDraft['id'], 1791864010);

        $secondDraft = $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(['monthly_rent' => '1800000']),
            1791864020,
        );
        $second = $service->generate((int) $secondDraft['id'], 1791864030);

        $this->pdo->prepare("UPDATE candidates SET status = 'withdrawn' WHERE id = :id")
            ->execute(['id' => $fixture['candidate_id']]);
        $this->assertRejected(fn () => $service->finalize((int) $first['id'], $this->userId, 1791864040));
        self::assertSame('generated', $service->findContract((int) $first['id'])['status']);

        $this->pdo->prepare("UPDATE candidates SET status = 'pending_screening' WHERE id = :id")
            ->execute(['id' => $fixture['candidate_id']]);
        $service->finalize((int) $first['id'], $this->userId, 1791864050);
        $this->assertRejected(fn () => $service->finalize((int) $second['id'], $this->userId, 1791864060));

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM contracts WHERE candidate_id = :candidate_id AND status = 'finalized'");
        $count->execute(['candidate_id' => $fixture['candidate_id']]);
        self::assertSame(1, (int) $count->fetchColumn());
        self::assertSame('generated', $service->findContract((int) $second['id'])['status']);
    }

    private function requireFinalizationSurface(ContractService $service): void
    {
        self::assertTrue(method_exists($service, 'updateTasks'), 'F7.5 missing ContractService::updateTasks');
        self::assertTrue(method_exists($service, 'finalize'), 'F7.5 missing ContractService::finalize');
    }

    /** @return array{property_id:int,unit_id:int,candidate_id:int,template_id:int,version_id:int} */
    private function fixture(): array
    {
        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio F7.5',
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
            'code' => 'A-' . bin2hex(random_bytes(3)),
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
            'full_name' => 'Ana F7.5',
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
            'responsible_user_id' => $this->userId,
            'result_at' => '2026-10-12 21:00:00',
        ]);

        $service = $this->service();
        $template = $service->createTemplate($this->userId, [
            'name' => 'Plantilla F7.5 ' . bin2hex(random_bytes(4)),
            'document_type' => 'urban_housing',
        ]);
        $version = $service->createTemplateVersion((int) $template['id'], $this->userId, [
            'body_template' => "Contrato {{candidate.full_name}}\nCanon {{contract.monthly_rent}}\nNota {{custom.note}}",
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

    private function validInput(array $overrides = []): array
    {
        return array_replace([
            'monthly_rent' => '1750000',
            'administration_fee' => '250000',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'duration_text' => 'Doce meses exactos',
            'manual_variables' => ['custom.note' => 'Sin observaciones'],
            'tasks' => [['label' => 'Revisar documentos', 'done' => false]],
            'annexes' => [['title' => 'Inventario inicial', 'note' => 'Se adjunta al proceso']],
        ], $overrides);
    }

    private function service(): ContractService
    {
        $candidates = new CandidateService($this->pdo);

        return new ContractService(
            $this->pdo,
            $candidates,
            new ScreeningService($this->pdo, $candidates),
            new PropertyService($this->pdo),
            $this->directory,
        );
    }

    private function insertUser(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1)',
        );
        $statement->execute([
            'role' => 'owner',
            'full_name' => 'Owner Finalization',
            'email' => 'finalize-' . bin2hex(random_bytes(4)) . '@example.com',
            'password_hash' => password_hash('OwnerPassword123', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function assertRejected(callable $callback): void
    {
        try {
            $callback();
            self::fail('La operación contractual debía rechazarse.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }
}
