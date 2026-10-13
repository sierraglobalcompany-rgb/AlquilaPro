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

final class ContractDraftTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_contract_draft_' . bin2hex(random_bytes(6));
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

    public function testEligibleCandidateCreatesOneDraftUsingCanonicalCandidateUnit(): void
    {
        $service = $this->service();
        $this->requireDraftSurface($service);
        $fixture = $this->eligibleFixture();

        $created = $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(['unit_id' => 999999]),
            1791864000,
        );

        self::assertSame('draft', $created['status']);
        self::assertSame($fixture['candidate_id'], $created['candidate_id']);
        self::assertSame($fixture['unit_id'], $created['unit_id']);
        self::assertSame('urban_housing', $created['document_type']);
        self::assertSame('1750000.00', $created['monthly_rent']);
        self::assertSame('250000.00', $created['administration_fee']);
        self::assertSame('2026-11-01', $created['start_date']);
        self::assertSame('2027-10-31', $created['end_date']);
        self::assertSame('Doce meses exactos', $created['duration_text']);

        $unit = (new PropertyService($this->pdo))->findUnit($fixture['unit_id']);
        self::assertNotNull($unit);
        self::assertSame('1500000.00', $unit['monthly_price']);
        self::assertSame('220000.00', $unit['administration_fee']);

        $candidate = (new CandidateService($this->pdo))->findCandidate($fixture['candidate_id']);
        self::assertNotNull($candidate);
        self::assertSame('pending_screening', $candidate['status']);

        $this->assertRejected(fn () => $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(),
            1791864001,
        ));
        self::assertCount(1, $service->listForCandidate($fixture['candidate_id']));
    }

    public function testEligibilityFailsClosedForCandidateScreeningUnitPropertyTemplateAndFinalizedState(): void
    {
        $service = $this->service();
        $this->requireDraftSurface($service);
        $fixture = $this->eligibleFixture();

        foreach (['new', 'incomplete', 'verification', 'withdrawn'] as $status) {
            $this->pdo->prepare('UPDATE candidates SET status = :status WHERE id = :id')->execute([
                'status' => $status,
                'id' => $fixture['candidate_id'],
            ]);
            $this->assertRejected(fn () => $service->createDraft(
                $fixture['candidate_id'],
                $fixture['version_id'],
                $this->userId,
                $this->validInput(),
                1791864000,
            ));
        }

        $this->pdo->prepare("UPDATE candidates SET status = 'pending_screening' WHERE id = :id")
            ->execute(['id' => $fixture['candidate_id']]);
        foreach (['pending', 'in_progress', 'rejected'] as $status) {
            $this->pdo->prepare('UPDATE screening_processes SET status = :status WHERE candidate_id = :candidate_id')->execute([
                'status' => $status,
                'candidate_id' => $fixture['candidate_id'],
            ]);
            $this->assertRejected(fn () => $service->createDraft(
                $fixture['candidate_id'],
                $fixture['version_id'],
                $this->userId,
                $this->validInput(),
                1791864000,
            ));
        }

        $this->pdo->prepare("UPDATE screening_processes SET status = 'approved' WHERE candidate_id = :candidate_id")
            ->execute(['candidate_id' => $fixture['candidate_id']]);
        foreach (['occupied', 'inactive', 'maintenance'] as $status) {
            $this->pdo->prepare('UPDATE units SET status = :status WHERE id = :id')->execute([
                'status' => $status,
                'id' => $fixture['unit_id'],
            ]);
            $this->assertRejected(fn () => $service->createDraft(
                $fixture['candidate_id'],
                $fixture['version_id'],
                $this->userId,
                $this->validInput(),
                1791864000,
            ));
        }

        $this->pdo->prepare("UPDATE units SET status = 'available' WHERE id = :id")
            ->execute(['id' => $fixture['unit_id']]);
        $this->pdo->prepare('UPDATE properties SET is_active = 0 WHERE id = :id')
            ->execute(['id' => $fixture['property_id']]);
        $this->assertRejected(fn () => $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(),
            1791864000,
        ));

        $this->pdo->prepare('UPDATE properties SET is_active = 1 WHERE id = :id')
            ->execute(['id' => $fixture['property_id']]);
        $this->pdo->prepare('UPDATE contract_templates SET is_active = 0 WHERE id = :id')
            ->execute(['id' => $fixture['template_id']]);
        $this->assertRejected(fn () => $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(),
            1791864000,
        ));

        $this->pdo->prepare('UPDATE contract_templates SET is_active = 1 WHERE id = :id')
            ->execute(['id' => $fixture['template_id']]);
        $parkingTemplate = $service->createTemplate($this->userId, [
            'name' => 'Parqueadero',
            'document_type' => 'independent_parking',
        ]);
        $parkingVersion = $service->createTemplateVersion((int) $parkingTemplate['id'], $this->userId, [
            'body_template' => 'Parqueadero {{candidate.full_name}}',
            'variables' => [],
        ]);
        $this->assertRejected(fn () => $service->createDraft(
            $fixture['candidate_id'],
            (int) $parkingVersion['id'],
            $this->userId,
            $this->validInput(),
            1791864000,
        ));

        $this->insertContract($fixture, 'finalized');
        $this->assertRejected(fn () => $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(),
            1791864000,
        ));
    }

    public function testMissingCandidateOrScreeningRejectsAndEligibilityProbeIsBoolean(): void
    {
        $service = $this->service();
        $this->requireDraftSurface($service);
        self::assertFalse($service->canCreateDraftForCandidate(999999));

        $fixture = $this->eligibleFixture();
        self::assertTrue($service->canCreateDraftForCandidate($fixture['candidate_id']));
        $this->pdo->prepare('DELETE FROM screening_processes WHERE candidate_id = :candidate_id')
            ->execute(['candidate_id' => $fixture['candidate_id']]);
        self::assertFalse($service->canCreateDraftForCandidate($fixture['candidate_id']));
        $this->assertRejected(fn () => $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(),
            1791864000,
        ));
    }

    public function testDraftValidationRejectsUnsafeEconomicsDatesManualTasksAndAnnexShapes(): void
    {
        $service = $this->service();
        $this->requireDraftSurface($service);

        $invalid = [
            ['monthly_rent' => '0'],
            ['monthly_rent' => '10000000000.00'],
            ['monthly_rent' => '1.234'],
            ['administration_fee' => '-1'],
            ['start_date' => '2026-02-30'],
            ['end_date' => '2026-10-01'],
            ['duration_text' => ''],
            ['duration_text' => str_repeat('x', 121)],
            ['manual_variables' => []],
            ['manual_variables' => ['custom.note' => str_repeat('x', 4001)]],
            ['manual_variables' => ['custom.note' => 'ok', 'custom.unknown' => 'no']],
            ['tasks' => array_fill(0, 31, ['label' => 'Tarea', 'done' => false])],
            ['tasks' => [['label' => '', 'done' => false]]],
            ['tasks' => [['label' => 'Tarea', 'done' => 'false']]],
            ['annexes' => array_fill(0, 21, ['title' => 'Anexo', 'note' => null])],
            ['annexes' => [['title' => '', 'note' => null]]],
        ];

        foreach ($invalid as $case) {
            $fixture = $this->eligibleFixture();
            $this->assertRejected(fn () => $service->createDraft(
                $fixture['candidate_id'],
                $fixture['version_id'],
                $this->userId,
                $this->validInput($case),
                1791864000,
            ));
        }
    }

    public function testDraftCanUpdateContractSnapshotFieldsWithoutMutatingCommercialUnitValues(): void
    {
        $service = $this->service();
        $this->requireDraftSurface($service);
        $fixture = $this->eligibleFixture();
        $draft = $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(),
            1791864000,
        );

        $updated = $service->updateDraft((int) $draft['id'], $this->validInput([
            'monthly_rent' => '1800000',
            'administration_fee' => null,
            'start_date' => '2026-12-01',
            'end_date' => '2027-11-30',
            'duration_text' => '12 meses confirmados manualmente',
            'manual_variables' => ['custom.note' => 'Actualizada'],
            'tasks' => [['label' => 'Verificar firma', 'done' => true]],
            'annexes' => [['title' => 'Inventario', 'note' => 'Pendiente']],
        ]), 1791864010);

        self::assertSame('1800000.00', $updated['monthly_rent']);
        self::assertNull($updated['administration_fee']);
        self::assertSame('2026-12-01', $updated['start_date']);
        self::assertSame('2027-11-30', $updated['end_date']);

        $unit = (new PropertyService($this->pdo))->findUnit($fixture['unit_id']);
        self::assertNotNull($unit);
        self::assertSame('1500000.00', $unit['monthly_price']);
        self::assertSame('220000.00', $unit['administration_fee']);

        $this->pdo->prepare("UPDATE contracts SET status = 'generated' WHERE id = :id")
            ->execute(['id' => (int) $draft['id']]);
        $this->assertRejected(fn () => $service->updateDraft(
            (int) $draft['id'],
            $this->validInput(),
            1791864020,
        ));
    }

    public function testPreviewIsSafeDraftOnlyAndPinnedDraftSurvivesTemplateDeactivation(): void
    {
        $service = $this->service();
        $this->requireDraftSurface($service);
        $fixture = $this->eligibleFixture(
            candidateName: '<script>alert("candidate")</script>',
            propertyName: '<b>Edificio Norte</b>',
        );
        $draft = $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput([
                'manual_variables' => ['custom.note' => '<img src=x onerror=alert(1)>'],
                'tasks' => [['label' => '<svg onload=alert(1)>', 'done' => false]],
                'annexes' => [['title' => '<i>Anexo</i>', 'note' => '<script>annex()</script>']],
            ]),
            1791864000,
        );

        $this->pdo->prepare('UPDATE contract_templates SET is_active = 0 WHERE id = :id')
            ->execute(['id' => $fixture['template_id']]);

        $html = $service->previewHtml((int) $draft['id']);
        self::assertStringContainsString('BORRADOR', $html);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;candidate&quot;)&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;b&gt;Edificio Norte&lt;/b&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringContainsString('&lt;svg onload=alert(1)&gt;', $html);
        self::assertStringContainsString('&lt;i&gt;Anexo&lt;/i&gt;', $html);
        self::assertStringContainsString('2026-11-01', $html);
        self::assertStringContainsString('2027-10-31', $html);
        self::assertStringContainsString('Doce meses exactos', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<img ', $html);
        self::assertStringNotContainsString('<svg ', $html);
        self::assertStringNotContainsString('<i>Anexo</i>', $html);

        $stored = $service->findContract((int) $draft['id']);
        self::assertNotNull($stored);
        self::assertSame('draft', $stored['status']);
        self::assertNull($stored['snapshot_html']);
        self::assertNull($stored['pdf_stored_name']);
        self::assertSame([], glob($this->directory . '/*.pdf') ?: []);
    }

    private function requireDraftSurface(ContractService $service): void
    {
        foreach ([
            'listContracts',
            'listForCandidate',
            'canCreateDraftForCandidate',
            'findContract',
            'createDraft',
            'updateDraft',
            'previewHtml',
        ] as $method) {
            self::assertTrue(method_exists($service, $method), 'F7.3 missing ContractService::' . $method);
        }
    }

    /** @return array{property_id:int,unit_id:int,candidate_id:int,template_id:int,version_id:int} */
    private function eligibleFixture(
        string $unitType = 'apartment',
        string $candidateName = 'Ana Pérez',
        string $propertyName = 'Edificio Central',
    ): array {
        $propertyId = $this->insertProperty($propertyName);
        $unitId = $this->insertUnit($propertyId, $unitType);
        $candidateId = $this->insertCandidate($unitId, $candidateName);
        $this->insertScreening($candidateId, 'approved');

        $service = $this->service();
        $documentType = $unitType === 'parking' ? 'independent_parking' : 'urban_housing';
        $template = $service->createTemplate($this->userId, [
            'name' => 'Plantilla ' . bin2hex(random_bytes(4)),
            'document_type' => $documentType,
        ]);
        $version = $service->createTemplateVersion((int) $template['id'], $this->userId, [
            'body_template' => implode("\n", [
                'Contrato para {{candidate.full_name}}',
                'Documento {{candidate.document_number}}',
                'Inmueble {{property.name}} - {{property.address}}',
                'Unidad {{unit.code}}',
                'Canon {{contract.monthly_rent}}',
                'Administración {{contract.administration_fee}}',
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
            'full_name' => 'Owner Contracts',
            'email' => 'contracts-' . bin2hex(random_bytes(4)) . '@example.com',
            'password_hash' => password_hash('OwnerPassword123', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertProperty(string $name): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) '
            . 'VALUES (:name, :address, :city, :sector, 1)',
        );
        $statement->execute([
            'name' => $name,
            'address' => 'Calle 10 # 20-30',
            'city' => 'Medellín',
            'sector' => 'La América',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertUnit(int $propertyId, string $type): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, monthly_price, administration_fee, unit_number, parking_level) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, :status, :monthly_price, :administration_fee, :unit_number, :parking_level)',
        );
        $statement->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'type' => $type,
            'code' => strtoupper(substr($type, 0, 1)) . '-' . bin2hex(random_bytes(3)),
            'location_label' => $type === 'parking' ? 'Sótano 1' : 'Piso 4',
            'status' => 'available',
            'monthly_price' => '1500000.00',
            'administration_fee' => '220000.00',
            'unit_number' => $type === 'apartment' ? '401' : null,
            'parking_level' => $type === 'parking' ? 'S1' : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertCandidate(int $unitId, string $name): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO candidates '
            . '(unit_id, document_type, document_number, full_name, phone, email, current_address, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :current_address, :status, '
            . ':token_hash, :expires_at, :consent_at)',
        );
        $document = strtoupper(bin2hex(random_bytes(5)));
        $statement->execute([
            'unit_id' => $unitId,
            'document_type' => 'cc',
            'document_number' => $document,
            'full_name' => $name,
            'phone' => '3001234567',
            'email' => strtolower($document) . '@example.com',
            'current_address' => 'Carrera 50 # 40-20',
            'status' => 'pending_screening',
            'token_hash' => bin2hex(random_bytes(32)),
            'expires_at' => '2027-01-01 00:00:00',
            'consent_at' => '2026-10-12 20:00:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertScreening(int $candidateId, string $status): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO screening_processes '
            . '(candidate_id, provider, status, result, responsible_user_id, result_at) '
            . 'VALUES (:candidate_id, :provider, :status, :result, :responsible_user_id, :result_at)',
        );
        $statement->execute([
            'candidate_id' => $candidateId,
            'provider' => 'Aseguradora Demo',
            'status' => $status,
            'result' => $status === 'approved' ? 'Aprobado' : null,
            'responsible_user_id' => $this->userId,
            'result_at' => in_array($status, ['approved', 'rejected'], true) ? '2026-10-12 21:00:00' : null,
        ]);
    }

    /** @param array{candidate_id:int,unit_id:int,version_id:int} $fixture */
    private function insertContract(array $fixture, string $status): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO contracts '
            . '(candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, '
            . 'start_date, end_date, duration_text, created_by_user_id) '
            . 'VALUES (:candidate_id, :unit_id, :template_version_id, :document_type, :status, :monthly_rent, '
            . ':administration_fee, :start_date, :end_date, :duration_text, :created_by_user_id)',
        );
        $statement->execute([
            'candidate_id' => $fixture['candidate_id'],
            'unit_id' => $fixture['unit_id'],
            'template_version_id' => $fixture['version_id'],
            'document_type' => 'urban_housing',
            'status' => $status,
            'monthly_rent' => '1750000.00',
            'administration_fee' => '250000.00',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'duration_text' => 'Doce meses exactos',
            'created_by_user_id' => $this->userId,
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
