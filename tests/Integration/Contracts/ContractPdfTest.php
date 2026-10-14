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

final class ContractPdfTest extends TestCase
{
    private PDO $pdo;
    private string $root;
    private string $contractDirectory;
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

        $this->root = sys_get_temp_dir() . '/alquila_contract_pdf_' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
        $this->contractDirectory = $this->root . '/contracts';
        $this->userId = $this->insertUser();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testDraftGeneratesImmutableSnapshotPdfHashAndExactResolvedValues(): void
    {
        $service = $this->service();
        $this->requirePdfSurface($service);
        $fixture = $this->eligibleFixture('<script>Ana</script>');
        $draft = $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput([
                'manual_variables' => ['custom.note' => '<b>Acuerdo especial</b>'],
                'tasks' => [['label' => 'Tarea mutable', 'done' => false]],
                'annexes' => [['title' => 'Inventario', 'note' => '<i>Entregado</i>']],
            ]),
            1791864000,
        );

        // A pinned draft remains valid even if its template is later disabled.
        $this->pdo->prepare('UPDATE contract_templates SET is_active = 0 WHERE id = :id')
            ->execute(['id' => $fixture['template_id']]);

        $generated = $service->generate((int) $draft['id'], 1791864060);

        self::assertSame('generated', $generated['status']);
        self::assertSame('2026-10-13 04:01:00', $generated['generated_at']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.pdf$/D', (string) $generated['pdf_stored_name']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', (string) $generated['pdf_sha256']);
        self::assertGreaterThan(1000, (int) $generated['pdf_size_bytes']);

        $resolved = json_decode((string) $generated['resolved_variables_json'], true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('<script>Ana</script>', $resolved['candidate.full_name']);
        self::assertSame('1750000.00', $resolved['contract.monthly_rent']);
        self::assertSame('<b>Acuerdo especial</b>', $resolved['custom.note']);

        $snapshot = (string) $generated['snapshot_html'];
        self::assertStringNotContainsString('BORRADOR', $snapshot);
        self::assertStringContainsString('&lt;script&gt;Ana&lt;/script&gt;', $snapshot);
        self::assertStringContainsString('&lt;b&gt;Acuerdo especial&lt;/b&gt;', $snapshot);
        self::assertStringContainsString('&lt;i&gt;Entregado&lt;/i&gt;', $snapshot);
        self::assertStringNotContainsString('Tarea mutable', $snapshot, 'Mutable tasks must not enter immutable PDF evidence.');
        self::assertStringNotContainsString('<script>', $snapshot);

        $pdf = $service->privatePdf((int) $draft['id']);
        self::assertNotNull($pdf);
        $expectedPdfPath = realpath($this->contractDirectory . '/' . $generated['pdf_stored_name']);
        $actualPdfPath = realpath($pdf['path']);
        self::assertIsString($expectedPdfPath);
        self::assertIsString($actualPdfPath);
        self::assertSame($expectedPdfPath, $actualPdfPath);
        self::assertSame($generated['pdf_stored_name'], $pdf['stored_name']);
        self::assertSame((int) $generated['pdf_size_bytes'], $pdf['size_bytes']);
        self::assertSame($generated['pdf_sha256'], $pdf['sha256']);
        self::assertFileExists($pdf['path']);
        $bytes = file_get_contents($pdf['path']);
        self::assertIsString($bytes);
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertSame(strlen($bytes), $pdf['size_bytes']);
        self::assertSame(hash('sha256', $bytes), $pdf['sha256']);

        $candidate = (new CandidateService($this->pdo))->findCandidate($fixture['candidate_id']);
        $unit = (new PropertyService($this->pdo))->findUnit($fixture['unit_id']);
        self::assertSame('pending_screening', $candidate['status']);
        self::assertSame('available', $unit['status']);

        $this->assertRejected(fn () => $service->generate((int) $draft['id'], 1791864120));
        $this->assertRejected(fn () => $service->updateDraft((int) $draft['id'], $this->validInput(), 1791864120));
    }

    public function testGenerateRevalidatesMutableSourcesAndCanonicalCandidateUnit(): void
    {
        $service = $this->service();
        $this->requirePdfSurface($service);

        foreach (['candidate', 'screening', 'unit', 'property', 'candidate_unit'] as $case) {
            $fixture = $this->eligibleFixture('Caso ' . $case);
            $draft = $service->createDraft(
                $fixture['candidate_id'],
                $fixture['version_id'],
                $this->userId,
                $this->validInput(),
                1791864000,
            );

            if ($case === 'candidate') {
                $this->pdo->prepare("UPDATE candidates SET status = 'withdrawn' WHERE id = :id")
                    ->execute(['id' => $fixture['candidate_id']]);
            } elseif ($case === 'screening') {
                $this->pdo->prepare("UPDATE screening_processes SET status = 'rejected' WHERE candidate_id = :id")
                    ->execute(['id' => $fixture['candidate_id']]);
            } elseif ($case === 'unit') {
                $this->pdo->prepare("UPDATE units SET status = 'occupied' WHERE id = :id")
                    ->execute(['id' => $fixture['unit_id']]);
            } elseif ($case === 'property') {
                $this->pdo->prepare('UPDATE properties SET is_active = 0 WHERE id = :id')
                    ->execute(['id' => $fixture['property_id']]);
            } else {
                $otherUnit = $this->insertUnit($fixture['property_id']);
                $this->pdo->prepare('UPDATE candidates SET unit_id = :unit_id WHERE id = :id')->execute([
                    'unit_id' => $otherUnit,
                    'id' => $fixture['candidate_id'],
                ]);
            }

            $this->assertRejected(fn () => $service->generate((int) $draft['id'], 1791864060));
            $stored = $service->findContract((int) $draft['id']);
            self::assertNotNull($stored);
            self::assertSame('draft', $stored['status']);
            self::assertNull($stored['snapshot_html']);
            self::assertNull($stored['pdf_stored_name']);
        }

        self::assertSame([], glob($this->contractDirectory . '/*.pdf') ?: []);
    }

    public function testStorageFailureLeavesDraftAndNoPartialPdf(): void
    {
        $fixture = $this->eligibleFixture('Fallo almacenamiento');
        $service = $this->service();
        $this->requirePdfSurface($service);
        $draft = $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(),
            1791864000,
        );

        $badStorage = $this->root . '/not-a-directory';
        file_put_contents($badStorage, 'blocked');
        $failing = $this->service($badStorage);
        $this->assertRejected(fn () => $failing->generate((int) $draft['id'], 1791864060));

        $stored = $service->findContract((int) $draft['id']);
        self::assertNotNull($stored);
        self::assertSame('draft', $stored['status']);
        self::assertNull($stored['snapshot_html']);
        self::assertNull($stored['pdf_stored_name']);
        self::assertSame([], glob($this->root . '/not-a-directory*pdf') ?: []);
    }

    public function testPrivatePdfFailsClosedForTamperingUnsafeNameAndOversize(): void
    {
        $service = $this->service();
        $this->requirePdfSurface($service);
        $fixture = $this->eligibleFixture('Integridad PDF');
        $draft = $service->createDraft(
            $fixture['candidate_id'],
            $fixture['version_id'],
            $this->userId,
            $this->validInput(),
            1791864000,
        );
        $generated = $service->generate((int) $draft['id'], 1791864060);
        $pdf = $service->privatePdf((int) $draft['id']);
        self::assertNotNull($pdf);

        file_put_contents($pdf['path'], 'tampered', FILE_APPEND);
        self::assertNull($service->privatePdf((int) $draft['id']));

        $this->pdo->prepare("UPDATE contracts SET pdf_stored_name = '../escape.pdf' WHERE id = :id")
            ->execute(['id' => (int) $draft['id']]);
        self::assertNull($service->privatePdf((int) $draft['id']));

        $oversizedName = bin2hex(random_bytes(16)) . '.pdf';
        $oversizedPath = $this->contractDirectory . '/' . $oversizedName;
        $oversizedBytes = '%PDF-' . str_repeat('x', 10 * 1024 * 1024);
        file_put_contents($oversizedPath, $oversizedBytes);
        $this->pdo->prepare(
            'UPDATE contracts SET pdf_stored_name = :name, pdf_size_bytes = :size, pdf_sha256 = :hash WHERE id = :id',
        )->execute([
            'name' => $oversizedName,
            'size' => strlen($oversizedBytes),
            'hash' => hash('sha256', $oversizedBytes),
            'id' => (int) $draft['id'],
        ]);
        self::assertNull($service->privatePdf((int) $draft['id']));
        self::assertSame('generated', $generated['status']);
    }

    private function requirePdfSurface(ContractService $service): void
    {
        self::assertTrue(method_exists($service, 'generate'), 'F7.4 missing ContractService::generate.');
        self::assertTrue(method_exists($service, 'privatePdf'), 'F7.4 missing ContractService::privatePdf.');
    }

    /** @return array{property_id:int,unit_id:int,candidate_id:int,template_id:int,version_id:int} */
    private function eligibleFixture(string $candidateName): array
    {
        $propertyId = $this->insertProperty();
        $unitId = $this->insertUnit($propertyId);
        $candidateId = $this->insertCandidate($unitId, $candidateName);
        $this->insertScreening($candidateId);

        $service = $this->service();
        $template = $service->createTemplate($this->userId, [
            'name' => 'Plantilla PDF ' . bin2hex(random_bytes(4)),
            'document_type' => 'urban_housing',
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
            'tasks' => [],
            'annexes' => [],
        ], $overrides);
    }

    private function service(?string $directory = null): ContractService
    {
        $candidates = new CandidateService($this->pdo);

        return new ContractService(
            $this->pdo,
            $candidates,
            new ScreeningService($this->pdo, $candidates),
            new PropertyService($this->pdo),
            $directory ?? $this->contractDirectory,
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
            'full_name' => 'Owner Contract PDF',
            'email' => 'pdf-' . bin2hex(random_bytes(4)) . '@example.com',
            'password_hash' => password_hash('OwnerPassword123', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertProperty(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) '
            . 'VALUES (:name, :address, :city, :sector, 1)',
        );
        $statement->execute([
            'name' => 'Edificio PDF',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Medellín',
            'sector' => 'La América',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertUnit(int $propertyId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, monthly_price, administration_fee, unit_number) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, :status, :monthly_price, :administration_fee, :unit_number)',
        );
        $statement->execute([
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

    private function insertScreening(int $candidateId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO screening_processes '
            . '(candidate_id, provider, status, result, responsible_user_id, result_at) '
            . 'VALUES (:candidate_id, :provider, :status, :result, :responsible_user_id, :result_at)',
        );
        $statement->execute([
            'candidate_id' => $candidateId,
            'provider' => 'Aseguradora Demo',
            'status' => 'approved',
            'result' => 'Aprobado',
            'responsible_user_id' => $this->userId,
            'result_at' => '2026-10-12 21:00:00',
        ]);
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
