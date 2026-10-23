<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Candidates\ScreeningService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ScreeningServiceTest extends TestCase
{
    private const NOW = 1791813600; // 2026-10-12 14:00:00 UTC

    private PDO $pdo;
    private CandidateService $candidates;

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
        $this->candidates = new CandidateService($this->pdo);
    }

    public function testCreateRequiresPendingScreeningAndPersistsSinglePendingProcess(): void
    {
        $responsibleUserId = $this->seedUser('screening-owner-1@example.com');
        $newCandidate = $this->seedCandidate('SC-1001', 'new');
        $service = $this->service();

        try {
            $service->createForCandidate($newCandidate, $responsibleUserId, ['provider' => 'SURA'], self::NOW);
            self::fail('A non-pending-screening candidate must be rejected.');
        } catch (RuntimeException) {
            self::assertSame(0, $this->screeningCount($newCandidate));
        }

        $candidateId = $this->seedCandidate('SC-1002', 'pending_screening');
        $created = $service->createForCandidate($candidateId, $responsibleUserId, [
            'provider' => '  SURA  ',
            'product' => 'Seguro de arrendamiento',
            'contact_email' => 'screening@example.com',
            'portal_url' => 'https://example.com/study/abc',
            'notes' => 'Nota privada',
            'support_reference' => 'RAD-1002',
            'status' => 'approved',
        ], self::NOW);

        self::assertSame($candidateId, $created['candidate_id']);
        self::assertSame('SURA', $created['provider']);
        self::assertSame('pending', $created['status']);
        self::assertSame($responsibleUserId, $created['responsible_user_id']);
        self::assertNull($created['started_at']);
        self::assertNull($created['result_at']);
        self::assertNull($created['updated_at']);
        self::assertSame(1, $this->screeningCount($candidateId));

        try {
            $service->createForCandidate($candidateId, $responsibleUserId, ['provider' => 'Otro'], self::NOW + 1);
            self::fail('A second process for the same candidate must be rejected.');
        } catch (RuntimeException) {
            self::assertSame(1, $this->screeningCount($candidateId));
            self::assertSame('SURA', $service->findForCandidate($candidateId)['provider']);
        }
    }

    public function testCreateValidatesProviderEmailLengthsAndHttpsPortalWithoutNetworkIo(): void
    {
        $responsibleUserId = $this->seedUser('screening-owner-2@example.com');
        $candidateId = $this->seedCandidate('SC-2001', 'pending_screening');
        $service = $this->service();

        $invalidInputs = [
            ['provider' => ''],
            ['provider' => str_repeat('P', 121)],
            ['provider' => 'SURA', 'product' => str_repeat('X', 191)],
            ['provider' => 'SURA', 'contact_email' => 'not-an-email'],
            ['provider' => 'SURA', 'support_reference' => str_repeat('R', 256)],
            ['provider' => 'SURA', 'portal_url' => '//example.com/study'],
            ['provider' => 'SURA', 'portal_url' => '/relative/study'],
            ['provider' => 'SURA', 'portal_url' => 'http://example.com/study'],
            ['provider' => 'SURA', 'portal_url' => 'javascript:alert(1)'],
            ['provider' => 'SURA', 'portal_url' => 'https:///missing-host'],
            ['provider' => 'SURA', 'portal_url' => 'https://example.com/' . str_repeat('a', 2030)],
        ];

        foreach ($invalidInputs as $input) {
            try {
                $service->createForCandidate($candidateId, $responsibleUserId, $input, self::NOW);
                self::fail('Invalid screening input must be rejected.');
            } catch (RuntimeException) {
                self::assertSame(0, $this->screeningCount($candidateId));
            }
        }

        $validUrl = 'https://screening.example.test/path?a=1';
        $created = $service->createForCandidate($candidateId, $responsibleUserId, [
            'provider' => 'SURA',
            'contact_email' => 'contact@example.test',
            'portal_url' => '  ' . $validUrl . '  ',
        ], self::NOW);

        self::assertSame($validUrl, $created['portal_url']);
        self::assertSame('contact@example.test', $created['contact_email']);
    }

    public function testPendingAndInProgressMetadataEditsPreserveLifecycleTimestamps(): void
    {
        $responsibleUserId = $this->seedUser('screening-owner-3@example.com');
        $candidateId = $this->seedCandidate('SC-3001', 'pending_screening');
        $service = $this->service();
        $service->createForCandidate($candidateId, $responsibleUserId, ['provider' => 'SURA'], self::NOW);

        $pending = $service->updateForCandidate($candidateId, [
            'status' => 'pending',
            'product' => 'Producto A',
            'notes' => 'Pendiente revisión',
        ], self::NOW + 10);
        self::assertSame('pending', $pending['status']);
        self::assertSame('Producto A', $pending['product']);
        self::assertNull($pending['started_at']);
        self::assertNull($pending['result_at']);
        self::assertSame($this->timestamp(self::NOW + 10), $pending['updated_at']);

        $started = $service->updateForCandidate($candidateId, ['status' => 'in_progress'], self::NOW + 20);
        self::assertSame('in_progress', $started['status']);
        self::assertSame($this->timestamp(self::NOW + 20), $started['started_at']);
        self::assertNull($started['result_at']);

        $edited = $service->updateForCandidate($candidateId, [
            'status' => 'in_progress',
            'product' => 'Producto B',
            'portal_url' => 'https://example.com/new-link',
        ], self::NOW + 30);
        self::assertSame('Producto B', $edited['product']);
        self::assertSame($this->timestamp(self::NOW + 20), $edited['started_at']);
        self::assertSame($this->timestamp(self::NOW + 30), $edited['updated_at']);
    }

    public function testDirectTerminalAndInProgressTerminalRequireResultAndSetExactTimestamps(): void
    {
        $responsibleUserId = $this->seedUser('screening-owner-4@example.com');
        $service = $this->service();

        $directCandidate = $this->seedCandidate('SC-4001', 'pending_screening');
        $service->createForCandidate($directCandidate, $responsibleUserId, ['provider' => 'SURA'], self::NOW);
        try {
            $service->updateForCandidate($directCandidate, ['status' => 'approved', 'result' => '   '], self::NOW + 10);
            self::fail('Terminal status requires a non-empty public result.');
        } catch (RuntimeException) {
            self::assertSame('pending', $service->findForCandidate($directCandidate)['status']);
        }

        $approved = $service->updateForCandidate($directCandidate, [
            'status' => 'approved',
            'result' => 'Aprobado por aseguradora',
        ], self::NOW + 20);
        self::assertSame('approved', $approved['status']);
        self::assertSame('Aprobado por aseguradora', $approved['result']);
        self::assertSame($this->timestamp(self::NOW + 20), $approved['started_at']);
        self::assertSame($this->timestamp(self::NOW + 20), $approved['result_at']);

        $progressCandidate = $this->seedCandidate('SC-4002', 'pending_screening');
        $service->createForCandidate($progressCandidate, $responsibleUserId, ['provider' => 'SURA'], self::NOW + 1);
        $service->updateForCandidate($progressCandidate, ['status' => 'in_progress'], self::NOW + 30);
        $rejected = $service->updateForCandidate($progressCandidate, [
            'status' => 'rejected',
            'result' => 'No aprobado por aseguradora',
        ], self::NOW + 40);
        self::assertSame('rejected', $rejected['status']);
        self::assertSame($this->timestamp(self::NOW + 30), $rejected['started_at']);
        self::assertSame($this->timestamp(self::NOW + 40), $rejected['result_at']);
    }

    public function testInvalidTransitionsAndTerminalTamperingFailWithoutMutation(): void
    {
        $responsibleUserId = $this->seedUser('screening-owner-5@example.com');
        $candidateId = $this->seedCandidate('SC-5001', 'pending_screening');
        $service = $this->service();
        $service->createForCandidate($candidateId, $responsibleUserId, [
            'provider' => 'SURA',
            'notes' => 'Original',
        ], self::NOW);

        try {
            $service->updateForCandidate($candidateId, ['status' => 'unknown'], self::NOW + 1);
            self::fail('Unknown status must be rejected.');
        } catch (RuntimeException) {
            self::assertSame('pending', $service->findForCandidate($candidateId)['status']);
        }

        $terminal = $service->updateForCandidate($candidateId, [
            'status' => 'approved',
            'result' => 'Resultado final',
        ], self::NOW + 10);
        $snapshot = $this->screeningRow($candidateId);

        foreach ([
            ['status' => 'pending'],
            ['status' => 'in_progress'],
            ['status' => 'approved', 'notes' => 'Tampered'],
            ['status' => 'approved', 'portal_url' => 'https://example.com/other'],
            ['status' => 'approved', 'result' => 'Resultado cambiado'],
        ] as $input) {
            try {
                $service->updateForCandidate($candidateId, $input, self::NOW + 20);
                self::fail('Terminal screening must be immutable.');
            } catch (RuntimeException) {
                self::assertSame($snapshot, $this->screeningRow($candidateId));
            }
        }

        self::assertSame('approved', $terminal['status']);
    }

    public function testCandidateWithdrawalBlocksMutationAndPreservesExistingProcess(): void
    {
        $responsibleUserId = $this->seedUser('screening-owner-6@example.com');
        $candidateId = $this->seedCandidate('SC-6001', 'pending_screening');
        $service = $this->service();
        $service->createForCandidate($candidateId, $responsibleUserId, [
            'provider' => 'SURA',
            'product' => 'Antes del retiro',
        ], self::NOW);
        $snapshot = $this->screeningRow($candidateId);

        $this->candidates->transitionStatus($candidateId, 'withdrawn');

        try {
            $service->updateForCandidate($candidateId, [
                'status' => 'in_progress',
                'product' => 'No debe persistir',
            ], self::NOW + 10);
            self::fail('Withdrawn candidate must block screening mutation.');
        } catch (RuntimeException) {
            self::assertSame($snapshot, $this->screeningRow($candidateId));
            self::assertSame('withdrawn', $this->candidates->findCandidate($candidateId)['status']);
        }
    }

    public function testScreeningNeverChangesCandidateOrUnitStatus(): void
    {
        $responsibleUserId = $this->seedUser('screening-owner-7@example.com');
        $candidateId = $this->seedCandidate('SC-7001', 'pending_screening');
        $service = $this->service();
        $unitId = (int) $this->candidates->findCandidate($candidateId)['unit_id'];
        $unitStatus = $this->unitStatus($unitId);

        $service->createForCandidate($candidateId, $responsibleUserId, ['provider' => 'SURA'], self::NOW);
        $service->updateForCandidate($candidateId, ['status' => 'in_progress'], self::NOW + 10);
        $service->updateForCandidate($candidateId, [
            'status' => 'approved',
            'result' => 'Aprobado',
        ], self::NOW + 20);

        self::assertSame('pending_screening', $this->candidates->findCandidate($candidateId)['status']);
        self::assertSame($unitStatus, $this->unitStatus($unitId));
    }

    public function testPublicProjectionIsExactAllowlistAndOmitsPrivateFields(): void
    {
        $responsibleUserId = $this->seedUser('screening-owner-8@example.com');
        $candidateId = $this->seedCandidate('SC-8001', 'pending_screening');
        $service = $this->service();
        $service->createForCandidate($candidateId, $responsibleUserId, [
            'provider' => 'SURA',
            'product' => 'Producto visible',
            'contact_email' => 'visible@example.com',
            'portal_url' => 'https://example.com/visible',
            'notes' => 'PRIVATE-NOTES-SENTINEL',
            'support_reference' => 'PRIVATE-SUPPORT-SENTINEL',
        ], self::NOW);
        $service->updateForCandidate($candidateId, ['status' => 'in_progress'], self::NOW + 10);

        $public = $service->publicForCandidate($candidateId);
        self::assertNotNull($public);
        self::assertSame([
            'provider',
            'product',
            'status',
            'contact_email',
            'portal_url',
            'result',
            'started_at',
            'result_at',
        ], array_keys($public));
        self::assertSame('SURA', $public['provider']);
        self::assertSame('in_progress', $public['status']);
        self::assertArrayNotHasKey('id', $public);
        self::assertArrayNotHasKey('candidate_id', $public);
        self::assertArrayNotHasKey('responsible_user_id', $public);
        self::assertArrayNotHasKey('notes', $public);
        self::assertArrayNotHasKey('support_reference', $public);
        self::assertNull($service->publicForCandidate(999999999));
    }

    private function service(): ScreeningService
    {
        self::assertTrue(class_exists(ScreeningService::class), 'ScreeningService must exist for F6.2.');

        return new ScreeningService($this->pdo, $this->candidates);
    }

    private function seedUser(string $email): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1)',
        );
        $statement->execute([
            'role' => 'admin',
            'full_name' => 'Responsable Screening',
            'email' => $email,
            'password_hash' => password_hash('TemporaryPassword123', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function seedCandidate(string $documentNumber, string $targetStatus): int
    {
        $publicKey = substr(hash('sha256', $documentNumber), 0, 24);
        $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) '
            . 'VALUES (:name, :address, :city, :sector, 1)',
        )->execute([
            'name' => 'Propiedad ' . $documentNumber,
            'address' => 'Calle 1',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, status, is_public, monthly_price, description, requirements) '
            . 'VALUES (:property_id, :public_key, :type, :code, :status, 1, :price, :description, :requirements)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'type' => 'apartment',
            'code' => 'APT-' . substr($publicKey, 0, 6),
            'status' => 'available',
            'price' => '1800000.00',
            'description' => 'Apartamento disponible',
            'requirements' => 'Documentos básicos',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', 'cover.jpg', 'image/jpeg', 0, 1)",
        )->execute(['unit_id' => $unitId]);

        $started = $this->candidates->startCandidate($publicKey, [
            'document_type' => 'cc',
            'document_number' => $documentNumber,
            'full_name' => 'Candidato ' . $documentNumber,
            'phone' => '3001234567',
            'email' => strtolower($documentNumber) . '@example.com',
            'consent' => true,
        ], self::NOW);
        $candidateId = (int) $started['candidate_id'];

        if ($targetStatus === 'new') {
            return $candidateId;
        }

        $this->candidates->updateByToken($started['continuation_token'], [
            'occupants_count' => '1',
            'pets_declared' => '0',
        ], self::NOW + 1);
        $this->candidates->submitByToken($started['continuation_token'], self::NOW + 2);
        if ($targetStatus === 'verification') {
            return $candidateId;
        }

        $this->candidates->transitionStatus($candidateId, 'pending_screening');
        if ($targetStatus === 'pending_screening') {
            return $candidateId;
        }

        if ($targetStatus === 'withdrawn') {
            $this->candidates->transitionStatus($candidateId, 'withdrawn');
            return $candidateId;
        }

        throw new RuntimeException('Unsupported test candidate status.');
    }

    private function screeningCount(int $candidateId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM screening_processes WHERE candidate_id = :candidate_id');
        $statement->execute(['candidate_id' => $candidateId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string,mixed> */
    private function screeningRow(int $candidateId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM screening_processes WHERE candidate_id = :candidate_id');
        $statement->execute(['candidate_id' => $candidateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    private function unitStatus(int $unitId): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM units WHERE id = :id');
        $statement->execute(['id' => $unitId]);

        return (string) $statement->fetchColumn();
    }

    private function timestamp(int $time): string
    {
        return gmdate('Y-m-d H:i:s', $time);
    }
}
