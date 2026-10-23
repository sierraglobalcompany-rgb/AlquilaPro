<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CandidateServiceTest extends TestCase
{
    private const NOW = 1791813600; // 2026-10-12 14:00:00 UTC

    private PDO $pdo;

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
    }

    public function testPublicableUnitStartsNewCandidateWithHashedSevenDayToken(): void
    {
        $unit = $this->seedUnit('aaaaaaaaaaaaaaaaaaaaaaaa', true);
        $service = $this->service();

        $started = $service->startCandidate($unit['public_key'], $this->candidateInput(' CC ', ' AbC123 '), self::NOW);

        self::assertGreaterThan(0, $started['candidate_id']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $started['continuation_token']);

        $row = $this->candidateRow((int) $started['candidate_id']);
        self::assertSame($unit['id'], (int) $row['unit_id']);
        self::assertSame('cc', $row['document_type']);
        self::assertSame('ABC123', $row['document_number']);
        self::assertSame('new', $row['status']);
        self::assertSame(hash('sha256', $started['continuation_token']), $row['continuation_token_hash']);
        self::assertNotSame($started['continuation_token'], $row['continuation_token_hash']);
        self::assertSame(gmdate('Y-m-d H:i:s', self::NOW + 604800), $row['continuation_token_expires_at']);
        self::assertSame(gmdate('Y-m-d H:i:s', self::NOW), $row['consent_at']);

        $resolved = $service->findByToken($started['continuation_token'], self::NOW + 10);
        self::assertNotNull($resolved);
        self::assertSame((int) $started['candidate_id'], $resolved['id']);
        self::assertArrayNotHasKey('continuation_token_hash', $resolved);
    }

    public function testInvalidExpiredAndUnpublishedNewCandidateFailClosed(): void
    {
        $unit = $this->seedUnit('bbbbbbbbbbbbbbbbbbbbbbbb', true);
        $service = $this->service();
        $started = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '1001'), self::NOW);

        self::assertNull($service->findByToken('not-a-token', self::NOW));
        self::assertNotNull($service->findByToken($started['continuation_token'], self::NOW + 604799));
        self::assertNull($service->findByToken($started['continuation_token'], self::NOW + 604800));

        $this->pdo->exec('UPDATE units SET is_public = 0 WHERE id = ' . $unit['id']);
        self::assertNotNull($service->findByToken($started['continuation_token'], self::NOW + 20));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('La unidad no está disponible para nuevas solicitudes.');
        $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '1002'), self::NOW + 30);
    }

    public function testNormalizedDuplicateIdentityDoesNotCreateSecondRowOrLeakCandidateData(): void
    {
        $unit = $this->seedUnit('cccccccccccccccccccccccc', true);
        $service = $this->service();
        $started = $service->startCandidate($unit['public_key'], $this->candidateInput(' CC ', ' XyZ-900 '), self::NOW);

        try {
            $service->startCandidate(
                $unit['public_key'],
                $this->candidateInput('cc', 'xyz-900', 'Otro Nombre', 'otro@example.com'),
                self::NOW + 1,
            );
            self::fail('Duplicate identity must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('No fue posible iniciar la solicitud.', $exception->getMessage());
            self::assertStringNotContainsString((string) $started['candidate_id'], $exception->getMessage());
            self::assertStringNotContainsString($started['continuation_token'], $exception->getMessage());
            self::assertStringNotContainsString('Otro Nombre', $exception->getMessage());
            self::assertStringNotContainsString('otro@example.com', $exception->getMessage());
        }

        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM candidates')->fetchColumn());
    }

    public function testPublicUpdateCannotChangeIdentityAndSubmittedCandidateCannotEdit(): void
    {
        $unit = $this->seedUnit('dddddddddddddddddddddddd', true);
        $service = $this->service();
        $started = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '2001'), self::NOW);

        try {
            $service->updateByToken($started['continuation_token'], ['document_number' => '9999'], self::NOW + 10);
            self::fail('Public identity must be immutable.');
        } catch (RuntimeException $exception) {
            self::assertSame('La identidad del candidato no se puede cambiar.', $exception->getMessage());
        }

        $updated = $service->updateByToken($started['continuation_token'], [
            'full_name' => 'Nombre Actualizado',
            'phone' => '3001112233',
            'email' => 'actualizado@example.com',
            'current_address' => 'Calle 10 # 20-30',
            'occupants_count' => '1',
            'pets_declared' => '0',
        ], self::NOW + 20);
        self::assertSame('Nombre Actualizado', $updated['full_name']);
        self::assertSame('2001', $updated['document_number']);

        $submitted = $service->submitByToken($started['continuation_token'], self::NOW + 30);
        self::assertSame('verification', $submitted['status']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('La solicitud ya no admite cambios.');
        $service->updateByToken($started['continuation_token'], ['phone' => '3009998877'], self::NOW + 40);
    }

    public function testRegenerateTokenInvalidatesPreviousRawToken(): void
    {
        $unit = $this->seedUnit('eeeeeeeeeeeeeeeeeeeeeeee', true);
        $service = $this->service();
        $started = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '3001'), self::NOW);

        $newToken = $service->regenerateToken((int) $started['candidate_id'], self::NOW + 100);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $newToken);
        self::assertNotSame($started['continuation_token'], $newToken);
        self::assertNull($service->findByToken($started['continuation_token'], self::NOW + 101));
        self::assertNotNull($service->findByToken($newToken, self::NOW + 101));

        $row = $this->candidateRow((int) $started['candidate_id']);
        self::assertSame(hash('sha256', $newToken), $row['continuation_token_hash']);
        self::assertSame(gmdate('Y-m-d H:i:s', self::NOW + 100 + 604800), $row['continuation_token_expires_at']);
    }

    public function testSubmitUsesLaborStartedRuleAndOnlySubmitCanReachVerification(): void
    {
        $unit = $this->seedUnit('ffffffffffffffffffffffff', true);
        $service = $this->service();
        $started = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '4001'), self::NOW);

        try {
            $service->transitionStatus((int) $started['candidate_id'], 'verification');
            self::fail('Administrative transition must not simulate public submit.');
        } catch (RuntimeException $exception) {
            self::assertSame('La transición de estado no es válida.', $exception->getMessage());
        }

        $service->updateByToken($started['continuation_token'], [
            'employer_name' => 'Empresa Uno',
            'occupants_count' => '1',
            'pets_declared' => '0',
        ], self::NOW + 10);
        try {
            $service->submitByToken($started['continuation_token'], self::NOW + 20);
            self::fail('Started labor data requires employment_status.');
        } catch (RuntimeException $exception) {
            self::assertSame('Debes completar el estado laboral antes de enviar.', $exception->getMessage());
        }

        $service->updateByToken($started['continuation_token'], [
            'employment_status' => 'Empleado',
            'occupants_count' => '1',
            'pets_declared' => '0',
        ], self::NOW + 30);
        $submitted = $service->submitByToken($started['continuation_token'], self::NOW + 40);

        self::assertSame('verification', $submitted['status']);
        self::assertSame(gmdate('Y-m-d H:i:s', self::NOW + 40), $submitted['submitted_at']);
        self::assertSame('verification', $service->findCandidate((int) $started['candidate_id'])['status']);
        self::assertCount(1, $service->listCandidates());
    }

    public function testCanonicalAdministrativeTransitionsAreExactAndWithdrawnIsTerminal(): void
    {
        $unit = $this->seedUnit('111111111111111111111111', true);
        $service = $this->service();

        $first = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '5001'), self::NOW);
        self::assertSame('incomplete', $service->transitionStatus((int) $first['candidate_id'], 'incomplete')['status']);
        self::assertSame('withdrawn', $service->transitionStatus((int) $first['candidate_id'], 'withdrawn')['status']);

        foreach (['new', 'incomplete', 'verification', 'pending_screening'] as $status) {
            try {
                $service->transitionStatus((int) $first['candidate_id'], $status);
                self::fail('Withdrawn must be terminal in F5.');
            } catch (RuntimeException $exception) {
                self::assertSame('La transición de estado no es válida.', $exception->getMessage());
            }
        }

        $second = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '5002'), self::NOW + 1);
        $service->updateByToken($second['continuation_token'], [
            'occupants_count' => '1',
            'pets_declared' => '0',
        ], self::NOW + 2);
        $service->submitByToken($second['continuation_token'], self::NOW + 3);
        self::assertSame(
            'pending_screening',
            $service->transitionStatus((int) $second['candidate_id'], 'pending_screening')['status'],
        );
        self::assertSame('withdrawn', $service->transitionStatus((int) $second['candidate_id'], 'withdrawn')['status']);

        $third = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '5003'), self::NOW + 3);
        self::assertSame('withdrawn', $service->transitionStatus((int) $third['candidate_id'], 'withdrawn')['status']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('La transición de estado no es válida.');
        $service->transitionStatus((int) $third['candidate_id'], 'approved');
    }

    public function testFindByTokenIncludesOnlyPublicReferenceFieldsWithoutInternalIds(): void
    {
        $unit = $this->seedUnit('222222222222222222222222', true);
        $service = $this->service();
        $started = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '6001'), self::NOW);

        $service->replaceReferencesByToken($started['continuation_token'], [[
            'reference_type' => 'personal',
            'name' => 'Referencia Visible',
            'relationship' => 'Amiga',
            'phone' => '3005556677',
            'email' => 'ref@example.com',
        ]], self::NOW + 1);

        $resolved = $service->findByToken($started['continuation_token'], self::NOW + 2);
        self::assertNotNull($resolved);
        self::assertSame('incomplete', $resolved['status']);
        self::assertCount(1, $resolved['references']);
        self::assertSame([
            'reference_type' => 'personal',
            'name' => 'Referencia Visible',
            'relationship' => 'Amiga',
            'phone' => '3005556677',
            'email' => 'ref@example.com',
        ], $resolved['references'][0]);
        self::assertArrayNotHasKey('id', $resolved['references'][0]);
        self::assertArrayNotHasKey('candidate_id', $resolved['references'][0]);
    }

    private function service(): CandidateService
    {
        self::assertTrue(class_exists(CandidateService::class), 'CandidateService must exist for F5.2.');

        return new CandidateService($this->pdo);
    }

    /** @return array{id:int,public_key:string} */
    private function seedUnit(string $publicKey, bool $publicable): array
    {
        $this->pdo->exec(
            "INSERT INTO properties (name, address, city, sector, is_active) VALUES "
            . "('Propiedad F5', 'Calle 1', 'Medellín', 'Calasanz', 1)",
        );
        $propertyId = (int) $this->pdo->lastInsertId();

        $statement = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, status, is_public, monthly_price, description, requirements) '
            . 'VALUES (:property_id, :public_key, :type, :code, :status, :is_public, :monthly_price, :description, :requirements)',
        );
        $statement->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'type' => 'apartment',
            'code' => 'APT-' . substr($publicKey, 0, 6),
            'status' => 'available',
            'is_public' => $publicable ? 1 : 0,
            'monthly_price' => '1800000.00',
            'description' => 'Apartamento disponible',
            'requirements' => 'Documentos básicos',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $media = $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', 'cover.jpg', 'image/jpeg', 0, 1)",
        );
        $media->execute(['unit_id' => $unitId]);

        return ['id' => $unitId, 'public_key' => $publicKey];
    }

    /** @return array<string,mixed> */
    private function candidateInput(
        string $documentType,
        string $documentNumber,
        string $fullName = 'Andrés Candidato',
        string $email = 'candidate@example.com',
    ): array {
        return [
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'full_name' => $fullName,
            'phone' => '3001234567',
            'email' => $email,
            'consent' => true,
        ];
    }

    /** @return array<string,mixed> */
    private function candidateRow(int $candidateId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM candidates WHERE id = :id');
        $statement->execute(['id' => $candidateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }
}
