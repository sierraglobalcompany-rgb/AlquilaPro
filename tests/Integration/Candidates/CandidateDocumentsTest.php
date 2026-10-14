<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Candidates\CandidateDocumentService;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\UploadedFile;

final class CandidateDocumentsTest extends TestCase
{
    private const NOW = 1791817200;

    private PDO $pdo;
    private string $documentDirectory;
    private array $temporaryFiles = [];

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

        $this->documentDirectory = sys_get_temp_dir() . '/alquila_candidate_documents_' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        foreach (glob($this->documentDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->documentDirectory);
    }

    public function testRequirementsUseUnitScopedImmutableCodeAndExactOwnedOrder(): void
    {
        $unit = $this->seedUnit(str_repeat('a', 24), 'DOC-A');
        $other = $this->seedUnit(str_repeat('b', 24), 'DOC-B');
        $service = $this->documents();

        $first = $service->createRequirement($unit['id'], [
            'code' => 'identity',
            'label' => 'Documento de identidad',
            'description' => 'Ambas caras',
            'is_required' => 1,
            'is_active' => 1,
        ]);
        $second = $service->createRequirement($unit['id'], [
            'code' => 'income',
            'label' => 'Soporte de ingresos',
            'is_required' => 0,
            'is_active' => 1,
        ]);
        $foreign = $service->createRequirement($other['id'], [
            'code' => 'foreign',
            'label' => 'No pertenece',
        ]);

        self::assertSame(['identity', 'income'], array_column($service->listRequirementsForUnit($unit['id']), 'code'));

        $updated = $service->updateRequirement($unit['id'], $first['id'], [
            'label' => 'Identidad actualizada',
            'description' => 'Documento legible',
            'is_required' => 0,
            'is_active' => 0,
        ]);
        self::assertSame('identity', $updated['code']);
        self::assertSame('Identidad actualizada', $updated['label']);
        self::assertFalse($updated['is_required']);
        self::assertFalse($updated['is_active']);
        self::assertSame(['income'], array_column($service->listRequirementsForUnit($unit['id'], true), 'code'));

        try {
            $service->updateRequirement($unit['id'], $first['id'], ['code' => 'changed-code']);
            self::fail('Requirement code must be immutable.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }

        foreach ([
            [$first['id'], $first['id']],
            [$first['id'], $foreign['id']],
            [$first['id']],
        ] as $invalidOrder) {
            try {
                $service->reorderRequirements($unit['id'], $invalidOrder);
                self::fail('Requirement order must contain the exact owned set once.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        $service->reorderRequirements($unit['id'], [$second['id'], $first['id']]);
        $ordered = $service->listRequirementsForUnit($unit['id']);
        self::assertSame([$second['id'], $first['id']], array_column($ordered, 'id'));
        self::assertSame([1, 2], array_column($ordered, 'sort_order'));
    }

    public function testRequirementCodeMustBeRouteSafeMachineKey(): void
    {
        $unit = $this->seedUnit(str_repeat('5', 24), 'DOC-K');
        $service = $this->documents();

        foreach (['identity proof', 'identity/proof', 'identity?proof', 'identity#proof', '../identity'] as $invalidCode) {
            try {
                $service->createRequirement($unit['id'], [
                    'code' => $invalidCode,
                    'label' => 'Inválido',
                ]);
                self::fail('Requirement code must be safe for one public route segment.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('código', mb_strtolower($exception->getMessage()));
            }
        }

        $valid = $service->createRequirement($unit['id'], [
            'code' => 'INCOME_proof-2026',
            'label' => 'Válido',
        ]);
        self::assertSame('INCOME_proof-2026', $valid['code']);
    }

    public function testPdfJpegAndPngUploadsUseRealMimeGeneratedNamesAndSafeReplacement(): void
    {
        $unit = $this->seedUnit(str_repeat('c', 24), 'DOC-C');
        $candidate = $this->startCandidate($unit, 'DOC-1001');
        $service = $this->documents();

        $pdfRequirement = $service->createRequirement($unit['id'], ['code' => 'pdf', 'label' => 'PDF']);
        $pngRequirement = $service->createRequirement($unit['id'], ['code' => 'png', 'label' => 'PNG']);
        $jpegRequirement = $service->createRequirement($unit['id'], ['code' => 'jpeg', 'label' => 'JPEG']);

        $pdf = $service->addOrReplaceDocument(
            $candidate['candidate_id'],
            'pdf',
            $this->upload($this->pdfBytes(), '../../danger.php', 'text/plain'),
        );
        $png = $service->addOrReplaceDocument(
            $candidate['candidate_id'],
            'png',
            $this->upload($this->pngBytes(), 'image.svg', 'image/svg+xml'),
        );
        $jpeg = $service->addOrReplaceDocument(
            $candidate['candidate_id'],
            'jpeg',
            $this->upload($this->jpegBytes(), 'photo.php', 'application/x-php'),
        );

        self::assertSame('application/pdf', $pdf['mime_type']);
        self::assertSame('image/png', $png['mime_type']);
        self::assertSame('image/jpeg', $jpeg['mime_type']);
        self::assertMatchesRegularExpression('/^document-[a-f0-9]{32}\.pdf$/', $pdf['stored_name']);
        self::assertMatchesRegularExpression('/^document-[a-f0-9]{32}\.png$/', $png['stored_name']);
        self::assertMatchesRegularExpression('/^document-[a-f0-9]{32}\.jpg$/', $jpeg['stored_name']);
        self::assertStringNotContainsString('danger', $pdf['stored_name']);
        self::assertFileExists($this->documentDirectory . '/' . $pdf['stored_name']);
        self::assertFileExists($this->documentDirectory . '/' . $png['stored_name']);
        self::assertFileExists($this->documentDirectory . '/' . $jpeg['stored_name']);
        self::assertSame(3, count($service->listDocumentsForCandidate($candidate['candidate_id'])));

        $private = $service->privateDocument($candidate['candidate_id'], $pdf['id']);
        self::assertNotNull($private);
        self::assertSame('application/pdf', $private['mime']);
        $expectedDocumentPath = realpath($this->documentDirectory . '/' . $pdf['stored_name']);
        $actualDocumentPath = realpath($private['path']);
        self::assertIsString($expectedDocumentPath);
        self::assertIsString($actualDocumentPath);
        self::assertSame($expectedDocumentPath, $actualDocumentPath);

        $oldPath = $this->documentDirectory . '/' . $pdf['stored_name'];
        $replacement = $service->addOrReplaceDocument(
            $candidate['candidate_id'],
            'pdf',
            $this->upload($this->pngBytes(), 'replacement.png', 'image/png'),
        );

        self::assertSame($pdfRequirement['id'], $replacement['requirement_id']);
        self::assertSame('image/png', $replacement['mime_type']);
        self::assertFileDoesNotExist($oldPath);
        self::assertFileExists($this->documentDirectory . '/' . $replacement['stored_name']);
        self::assertSame(3, count($service->listDocumentsForCandidate($candidate['candidate_id'])));
        self::assertNotSame($pngRequirement['id'], $jpegRequirement['id']);
    }

    public function testUnsafeOrOversizedUploadIsRejectedWithoutReplacingPriorValidDocument(): void
    {
        $unit = $this->seedUnit(str_repeat('d', 24), 'DOC-D');
        $candidate = $this->startCandidate($unit, 'DOC-2001');
        $service = $this->documents();
        $service->createRequirement($unit['id'], ['code' => 'identity', 'label' => 'Identidad']);

        $valid = $service->addOrReplaceDocument(
            $candidate['candidate_id'],
            'identity',
            $this->upload($this->pdfBytes(), 'identity.pdf', 'application/pdf'),
        );
        $validPath = $this->documentDirectory . '/' . $valid['stored_name'];

        $invalidUploads = [
            $this->upload('', 'empty.pdf', 'application/pdf'),
            $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'fake.pdf', 'application/pdf'),
            $this->upload('<?php echo "owned"; ?>', 'fake.jpg', 'image/jpeg'),
            $this->upload(str_repeat('A', 10 * 1024 * 1024 + 1), 'too-big.pdf', 'application/pdf'),
        ];

        foreach ($invalidUploads as $upload) {
            try {
                $service->addOrReplaceDocument($candidate['candidate_id'], 'identity', $upload);
                self::fail('Unsafe candidate document must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }

            $documents = $service->listDocumentsForCandidate($candidate['candidate_id']);
            self::assertCount(1, $documents);
            self::assertSame($valid['stored_name'], $documents[0]['stored_name']);
            self::assertFileExists($validPath);
        }
    }

    public function testUploadRequiresExistingCandidateAndActiveRequirementFromCurrentUnit(): void
    {
        $unit = $this->seedUnit(str_repeat('e', 24), 'DOC-E');
        $other = $this->seedUnit(str_repeat('f', 24), 'DOC-F');
        $candidate = $this->startCandidate($unit, 'DOC-3001');
        $service = $this->documents();

        $service->createRequirement($unit['id'], [
            'code' => 'inactive',
            'label' => 'Inactivo',
            'is_active' => 0,
        ]);
        $service->createRequirement($other['id'], [
            'code' => 'foreign',
            'label' => 'Ajeno',
        ]);
        $service->createRequirement($unit['id'], [
            'code' => 'valid',
            'label' => 'Válido',
        ]);

        foreach ([
            [$candidate['candidate_id'], 'inactive'],
            [$candidate['candidate_id'], 'foreign'],
            [999999, 'valid'],
            [$candidate['candidate_id'], 'missing'],
        ] as [$candidateId, $code]) {
            try {
                $service->addOrReplaceDocument(
                    $candidateId,
                    $code,
                    $this->upload($this->pdfBytes(), 'document.pdf', 'application/pdf'),
                );
                self::fail('Upload must be bound to an existing candidate and active current-unit requirement.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM candidate_documents')->fetchColumn());
        self::assertSame([], glob($this->documentDirectory . '/*') ?: []);
    }

    public function testPrivateDocumentRejectsCrossCandidateAndTamperedFile(): void
    {
        $unit = $this->seedUnit(str_repeat('1', 24), 'DOC-G');
        $first = $this->startCandidate($unit, 'DOC-4001');
        $second = $this->startCandidate($unit, 'DOC-4002');
        $service = $this->documents();
        $service->createRequirement($unit['id'], ['code' => 'identity', 'label' => 'Identidad']);

        $document = $service->addOrReplaceDocument(
            $first['candidate_id'],
            'identity',
            $this->upload($this->pdfBytes(), 'identity.pdf', 'application/pdf'),
        );

        self::assertNull($service->privateDocument($second['candidate_id'], $document['id']));
        self::assertNotNull($service->privateDocument($first['candidate_id'], $document['id']));

        $path = $this->documentDirectory . '/' . $document['stored_name'];
        file_put_contents($path, '<?php echo "tampered"; ?>');
        self::assertNull($service->privateDocument($first['candidate_id'], $document['id']));
    }

    public function testSubmitRequiresOnlyCurrentActiveRequiredDocumentsAndSurvivesUnitUnpublish(): void
    {
        $unit = $this->seedUnit(str_repeat('2', 24), 'DOC-H');
        $documents = $this->documents();
        $candidateService = new CandidateService($this->pdo, candidateDocumentService: $documents);
        $candidate = $candidateService->startCandidate(
            $unit['public_key'],
            $this->candidateInput('DOC-5001'),
            self::NOW,
        );

        $documents->createRequirement($unit['id'], [
            'code' => 'required',
            'label' => 'Obligatorio',
            'is_required' => 1,
            'is_active' => 1,
        ]);
        $documents->createRequirement($unit['id'], [
            'code' => 'optional',
            'label' => 'Opcional',
            'is_required' => 0,
            'is_active' => 1,
        ]);
        $documents->createRequirement($unit['id'], [
            'code' => 'inactive-required',
            'label' => 'Obligatorio inactivo',
            'is_required' => 1,
            'is_active' => 0,
        ]);

        try {
            $candidateService->submitByToken($candidate['continuation_token'], self::NOW + 1);
            self::fail('Submit must fail while a current active required document is missing.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('document', mb_strtolower($exception->getMessage()));
        }

        $documents->addOrReplaceDocument(
            $candidate['candidate_id'],
            'required',
            $this->upload($this->pdfBytes(), 'required.pdf', 'application/pdf'),
        );
        $this->pdo->exec('UPDATE units SET is_public = 0 WHERE id = ' . $unit['id']);

        $submitted = $candidateService->submitByToken($candidate['continuation_token'], self::NOW + 2);
        self::assertSame('verification', $submitted['status']);
        self::assertNotNull($submitted['submitted_at']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM candidates')->fetchColumn());
    }

    public function testSubmitRejectsRequiredDocumentWhosePhysicalFileWasDeleted(): void
    {
        $unit = $this->seedUnit(str_repeat('3', 24), 'DOC-I');
        $documents = $this->documents();
        $candidateService = new CandidateService($this->pdo, candidateDocumentService: $documents);
        $candidate = $candidateService->startCandidate(
            $unit['public_key'],
            $this->candidateInput('DOC-6001'),
            self::NOW,
        );
        $documents->createRequirement($unit['id'], [
            'code' => 'identity',
            'label' => 'Identidad',
            'is_required' => 1,
            'is_active' => 1,
        ]);
        $document = $documents->addOrReplaceDocument(
            $candidate['candidate_id'],
            'identity',
            $this->upload($this->pdfBytes(), 'identity.pdf', 'application/pdf'),
        );
        $path = $this->documentDirectory . '/' . $document['stored_name'];
        self::assertFileExists($path);
        self::assertTrue(unlink($path));
        self::assertFileDoesNotExist($path);

        try {
            $candidateService->submitByToken($candidate['continuation_token'], self::NOW + 1);
            self::fail('Submit must reject a required document whose physical file is missing.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('document', mb_strtolower($exception->getMessage()));
        }

        $stored = $candidateService->findCandidate($candidate['candidate_id']);
        self::assertNotNull($stored);
        self::assertSame('new', $stored['status']);
        self::assertNull($stored['submitted_at']);
    }

    public function testSubmitRejectsRequiredDocumentWhosePhysicalFileWasTampered(): void
    {
        $unit = $this->seedUnit(str_repeat('4', 24), 'DOC-J');
        $documents = $this->documents();
        $candidateService = new CandidateService($this->pdo, candidateDocumentService: $documents);
        $candidate = $candidateService->startCandidate(
            $unit['public_key'],
            $this->candidateInput('DOC-7001'),
            self::NOW,
        );
        $documents->createRequirement($unit['id'], [
            'code' => 'identity',
            'label' => 'Identidad',
            'is_required' => 1,
            'is_active' => 1,
        ]);
        $document = $documents->addOrReplaceDocument(
            $candidate['candidate_id'],
            'identity',
            $this->upload($this->pdfBytes(), 'identity.pdf', 'application/pdf'),
        );
        $path = $this->documentDirectory . '/' . $document['stored_name'];
        self::assertFileExists($path);
        self::assertNotFalse(file_put_contents($path, '<?php echo "tampered"; ?>'));

        try {
            $candidateService->submitByToken($candidate['continuation_token'], self::NOW + 1);
            self::fail('Submit must reject a required document whose physical file was tampered.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('document', mb_strtolower($exception->getMessage()));
        }

        $stored = $candidateService->findCandidate($candidate['candidate_id']);
        self::assertNotNull($stored);
        self::assertSame('new', $stored['status']);
        self::assertNull($stored['submitted_at']);
    }

    private function documents(): CandidateDocumentService
    {
        self::assertTrue(
            class_exists(CandidateDocumentService::class),
            'CandidateDocumentService must exist for F5.5.',
        );

        return new CandidateDocumentService($this->pdo, $this->documentDirectory);
    }

    /** @return array{id:int,public_key:string} */
    private function seedUnit(string $publicKey, string $code): array
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO properties (name, address, city, sector, is_active) VALUES "
            . "(:name, 'Dirección privada', 'Medellín', 'Calasanz', 1)",
        );
        $statement->execute(['name' => 'Propiedad ' . $code]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $statement = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, :status, 1, :monthly_price, :description, :requirements)',
        );
        $statement->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'type' => 'apartment',
            'code' => $code,
            'location_label' => 'Calasanz',
            'status' => 'available',
            'monthly_price' => '1800000.00',
            'description' => 'Apartamento disponible',
            'requirements' => 'Documentos básicos',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', 'cover.jpg', 'image/jpeg', 0, 1)",
        )->execute(['unit_id' => $unitId]);

        return ['id' => $unitId, 'public_key' => $publicKey];
    }

    /** @return array{candidate_id:int,continuation_token:string} */
    private function startCandidate(array $unit, string $documentNumber): array
    {
        return (new CandidateService($this->pdo))->startCandidate(
            $unit['public_key'],
            $this->candidateInput($documentNumber),
            self::NOW,
        );
    }

    /** @return array<string,mixed> */
    private function candidateInput(string $documentNumber): array
    {
        return [
            'document_type' => 'cc',
            'document_number' => $documentNumber,
            'full_name' => 'Candidato Documentos',
            'phone' => '3001234567',
            'email' => strtolower($documentNumber) . '@example.com',
            'consent' => true,
        ];
    }

    private function upload(string $bytes, string $clientName, string $clientType, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_candidate_upload_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create candidate upload fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $clientName, $clientType, strlen($bytes), $error, false);
    }

    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }

    private function jpegBytes(): string
    {
        $bytes = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EH//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EH//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EH//2Q==',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }
}
