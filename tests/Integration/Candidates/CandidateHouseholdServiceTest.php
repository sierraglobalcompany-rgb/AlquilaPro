<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Candidates\CandidateHouseholdService;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\UploadedFile;

final class CandidateHouseholdServiceTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private CandidateHouseholdService $household;
    /** @var list<string> */
    private array $temporaryUploads = [];

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
        $this->directory = sys_get_temp_dir() . '/alquila_household_' . bin2hex(random_bytes(6));
        $this->household = new CandidateHouseholdService($this->pdo, $this->directory);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryUploads as $path) {
            @unlink($path);
        }
        if (is_dir($this->directory)) {
            foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (is_file($path) && !is_link($path)) {
                    @unlink($path);
                }
            }
            @rmdir($this->directory);
        }
    }

    public function testStoresRealOccupantsAndPetsWithCandidateScopedOwnership(): void
    {
        $candidateId = $this->candidateId();
        $otherCandidateId = $this->candidateId();

        $occupant = $this->household->saveRecord('occupant', $candidateId, null, [
            'full_name' => 'Acompañante Real',
            'relationship' => 'Hija',
            'age_years' => '8',
            'occupation_or_activity' => 'Estudiante',
            'notes' => 'Dato declarado por la familia.',
        ], null, time());
        $pet = $this->household->saveRecord('pet', $candidateId, null, [
            'name' => 'Luna',
            'species' => 'Gato',
            'vaccination_declaration' => '',
        ], null, time());

        self::assertSame('Acompañante Real', $this->household->listOccupants($candidateId)[0]['full_name']);
        self::assertSame(8, $this->household->listOccupants($candidateId)[0]['age_years']);
        self::assertSame('Luna', $this->household->listPets($candidateId)[0]['name']);
        self::assertNull($this->household->listPets($candidateId)[0]['vaccination_declaration']);

        try {
            $this->household->saveRecord('pet', $otherCandidateId, (int) $pet['id'], [
                'name' => 'Luna',
                'species' => 'Gato',
            ], null, time());
            self::fail('A pet belonging to another candidate must not be editable by its id.');
        } catch (RuntimeException $exception) {
            self::assertSame('No fue posible actualizar el registro del hogar.', $exception->getMessage());
        }

        self::assertSame('Luna', $this->household->listPets($candidateId)[0]['name']);
        self::assertSame([], $this->household->listOccupants($otherCandidateId));
        self::assertSame('Hija', $this->household->listOccupants($candidateId)[0]['relationship']);
    }

    public function testConcurrentHouseholdWriteWaitsForCandidateOwnerTransaction(): void
    {
        $candidateId = $this->candidateId();
        $otherConnection = DatabaseConnector::connect(DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]));
        $otherConnection->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->prepare('SELECT id FROM candidates WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $candidateId]);
            try {
                (new CandidateHouseholdService($otherConnection, $this->directory . '/concurrent'))->saveRecord(
                    'occupant',
                    $candidateId,
                    null,
                    ['full_name' => 'Escritura concurrente', 'relationship' => 'Familiar'],
                    null,
                    time(),
                );
                self::fail('A household write must wait while its candidate owner is locked.');
            } catch (RuntimeException $exception) {
                self::assertSame('No fue posible guardar la información del hogar.', $exception->getMessage());
            }
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
        self::assertSame([], $this->household->listOccupants($candidateId));
    }

    public function testRequiresRealPetNameAndSpeciesAndValidHouseholdValues(): void
    {
        $candidateId = $this->candidateId();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El nombre y la especie de la mascota son obligatorios.');
        $this->household->saveRecord('pet', $candidateId, null, ['name' => '', 'species' => ''], null, time());
    }

    public function testWizardCandidateAndHouseholdChangesRollbackTogetherOnInvalidRow(): void
    {
        $candidateId = $this->candidateId();
        try {
            $this->household->saveWizard(
                $candidateId,
                [['full_name' => 'Acompañante sin relación']],
                [],
                [],
                function () use ($candidateId): array {
                    $this->pdo->prepare('UPDATE candidates SET occupants_count = 2 WHERE id = :id')->execute(['id' => $candidateId]);
                    return ['pets_declared' => false];
                },
                time(),
            );
            self::fail('An invalid occupant must roll back the candidate update in the same transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('La relación con el solicitante es obligatoria.', $exception->getMessage());
        }

        $count = $this->pdo->query('SELECT occupants_count FROM candidates WHERE id = ' . $candidateId)->fetchColumn();
        self::assertNull($count);
        self::assertSame([], $this->household->listOccupants($candidateId));
    }

    public function testStoresPrivatePhotoPerPetAndRejectsTamperedBytes(): void
    {
        $candidateId = $this->candidateId();
        $pet = $this->household->saveRecord('pet', $candidateId, null, [
            'name' => 'Luna',
            'species' => 'Gato',
            'photo_consent' => '1',
        ], $this->upload($this->pngBytes(), 'pet.jpg', 'image/jpeg'), time());

        self::assertTrue($pet['has_photo']);
        $photo = $this->household->privatePhoto($candidateId, 'pet', (int) $pet['id']);
        self::assertNotNull($photo);
        self::assertSame('image/png', $photo['mime']);
        self::assertSame(filesize($photo['path']), $photo['size_bytes']);
        self::assertNull($this->household->privatePhoto($this->candidateId(), 'pet', (int) $pet['id']));

        file_put_contents($photo['path'], 'tampered');
        self::assertNull($this->household->privatePhoto($candidateId, 'pet', (int) $pet['id']));
    }

    public function testPhotoRequiresAndRecordsExplicitConsent(): void
    {
        $candidateId = $this->candidateId();
        try {
            $this->household->saveRecord('pet', $candidateId, null, [
                'name' => 'Luna',
                'species' => 'Gato',
            ], $this->upload($this->pngBytes(), 'pet.png', 'image/png'), time());
            self::fail('A household photo must not be stored without explicit consent.');
        } catch (RuntimeException $exception) {
            self::assertSame('Confirma que cuentas con autorización para guardar esta fotografía.', $exception->getMessage());
        }

        $pet = $this->household->saveRecord('pet', $candidateId, null, [
            'name' => 'Luna',
            'species' => 'Gato',
            'photo_consent' => '1',
        ], $this->upload($this->pngBytes(), 'pet.png', 'image/png'), time());
        $statement = $this->pdo->prepare('SELECT photo_consent_at FROM candidate_household_pets WHERE id = :id');
        $statement->execute(['id' => $pet['id']]);
        self::assertNotNull($statement->fetchColumn());
    }

    public function testRejectsForgedImageBytesAndRemovesPreviousFileAfterReplacement(): void
    {
        $candidateId = $this->candidateId();
        try {
            $this->household->saveRecord('pet', $candidateId, null, [
                'name' => 'Luna',
                'species' => 'Gato',
            ], $this->upload('not an image', 'pet.png', 'image/png'), time());
            self::fail('A claimed image MIME must not override the file signature.');
        } catch (RuntimeException $exception) {
            self::assertSame('La fotografía debe ser una imagen PNG o JPEG válida.', $exception->getMessage());
        }

        $pet = $this->household->saveRecord('pet', $candidateId, null, [
            'name' => 'Luna',
            'species' => 'Gato',
                'photo_consent' => '1',
        ], $this->upload($this->pngBytes(), 'pet.png', 'image/png'), time());
        $old = $this->household->privatePhoto($candidateId, 'pet', (int) $pet['id']);
        self::assertNotNull($old);

        $this->household->saveRecord('pet', $candidateId, (int) $pet['id'], [
            'name' => 'Luna',
            'species' => 'Gato',
            'photo_consent' => '1',
        ], $this->upload($this->pngBytes(), 'new-pet.png', 'image/png'), time());

        self::assertFileDoesNotExist($old['path']);
        $replacement = $this->household->privatePhoto($candidateId, 'pet', (int) $pet['id']);
        self::assertNotNull($replacement);
        self::assertSame('image/png', $replacement['mime']);
    }

    private function upload(string $bytes, string $filename, string $clientType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_household_upload_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create image upload fixture.');
        }
        $this->temporaryUploads[] = $path;
        return new UploadedFile($path, $filename, $clientType, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function pngBytes(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    }

    private function jpegBytes(): string
    {
        return (string) base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EH//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EH//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EH//2Q==', true);
    }

    private function candidateId(): int
    {
        $property = $this->pdo->prepare(
            "INSERT INTO properties (name, address, city, sector, is_active) VALUES ('Household', 'Dirección', 'Medellín', 'Centro', 1)",
        );
        $property->execute();
        $propertyId = (int) $this->pdo->lastInsertId();
        $publicKey = bin2hex(random_bytes(12));
        $unit = $this->pdo->prepare(
            "INSERT INTO units (property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements, private_notes) VALUES (:property_id, :public_key, 'apartment', 'H-01', 'Centro', 'available', 1, 1800000, 'Test', 'Documentos básicos', '')",
        );
        $unit->execute(['property_id' => $propertyId, 'public_key' => $publicKey]);
        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) VALUES (LAST_INSERT_ID(), 'photo', 'cover.jpg', 'image/jpeg', 0, 1)",
        )->execute();

        return (new CandidateService($this->pdo))->startCandidate($publicKey, [
            'document_type' => 'cc',
            'document_number' => 'HH-' . bin2hex(random_bytes(6)),
            'full_name' => 'Solicitante Real',
            'phone' => '3001234567',
            'email' => 'household-' . bin2hex(random_bytes(4)) . '@example.test',
            'consent' => true,
        ], time())['candidate_id'];
    }
}
