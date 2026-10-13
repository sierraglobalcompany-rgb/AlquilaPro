<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Leases;

use AlquilaPro\Auth\UserService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Leases\LeaseService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LeaseActivationTest extends TestCase
{
    private PDO $pdo;
    private int $actorId;

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
        $this->actorId = $this->insertUser('owner', 'Owner F8', 'owner-f8@example.com');
    }

    public function testOnlyFinalizedContractCanActivateLease(): void
    {
        foreach (['draft', 'generated'] as $status) {
            $fixture = $this->fixture(contractStatus: $status, candidateEmail: $status . '@example.com');
            $this->assertRejected(fn () => $this->service()->activate($fixture['contract_id'], $this->actorId, 1791870000));
            self::assertSame(0, $this->count('leases'));
            self::assertSame('available', $this->unitStatus($fixture['unit_id']));
            self::assertSame(1, $this->count('users'));
        }
    }

    public function testAvailableAndInProcessUnitsActivateToOccupiedWithDerivedProjection(): void
    {
        foreach (['available', 'in_process'] as $index => $unitStatus) {
            $fixture = $this->fixture(
                unitStatus: $unitStatus,
                candidateEmail: 'tenant-' . $unitStatus . '@example.com',
                suffix: (string) $index,
            );

            $result = $this->service()->activate($fixture['contract_id'], $this->actorId, 1791870100 + $index);

            self::assertGreaterThan(0, $result['lease']['id']);
            self::assertSame($fixture['contract_id'], $result['lease']['contract_id']);
            self::assertSame($this->actorId, $result['lease']['activated_by_user_id']);
            self::assertSame(gmdate('Y-m-d H:i:s', 1791870100 + $index), $result['lease']['activated_at']);
            self::assertTrue($result['lease']['is_active']);
            self::assertNull($result['lease']['ended_at']);
            self::assertSame($fixture['candidate_id'], $result['lease']['candidate_id']);
            self::assertSame($fixture['unit_id'], $result['lease']['unit_id']);
            self::assertSame($fixture['property_id'], $result['lease']['property_id']);
            self::assertSame('Tenant F8 ' . $index, $result['lease']['tenant_name']);
            self::assertSame('tenant-' . $unitStatus . '@example.com', $result['lease']['tenant_email']);
            self::assertSame('A-' . $index, $result['lease']['unit_code']);
            self::assertSame('Edificio F8 ' . $index, $result['lease']['property_name']);
            self::assertSame('1750000.00', $result['lease']['monthly_rent']);
            self::assertSame('250000.00', $result['lease']['administration_fee']);
            self::assertSame('2026-11-01', $result['lease']['start_date']);
            self::assertSame('2027-10-31', $result['lease']['end_date']);
            self::assertSame('Doce meses exactos', $result['lease']['duration_text']);
            self::assertGreaterThanOrEqual(12, strlen($result['temporary_password']));
            self::assertSame('occupied', $this->unitStatus($fixture['unit_id']));

            $tenant = $this->pdo->query(
                'SELECT role, email, password_hash, permissions_json, must_change_password FROM users WHERE id = '
                . (int) $result['lease']['user_id'],
            )->fetch(PDO::FETCH_ASSOC);
            self::assertIsArray($tenant);
            self::assertSame('tenant', $tenant['role']);
            self::assertSame('tenant-' . $unitStatus . '@example.com', $tenant['email']);
            self::assertNull($tenant['permissions_json']);
            self::assertSame(1, (int) $tenant['must_change_password']);
            self::assertTrue(password_verify($result['temporary_password'], (string) $tenant['password_hash']));

            $found = $this->service()->findByContract($fixture['contract_id']);
            self::assertIsArray($found);
            self::assertSame($result['lease']['id'], $found['id']);
            self::assertSame($result['lease']['tenant_email'], $found['tenant_email']);
        }
    }

    public function testOccupiedInactiveAndMaintenanceUnitsAreRejectedWithoutSideEffects(): void
    {
        foreach (['occupied', 'inactive', 'maintenance'] as $index => $status) {
            $fixture = $this->fixture(
                unitStatus: $status,
                candidateEmail: 'blocked-' . $status . '@example.com',
                suffix: 'b' . $index,
            );
            $usersBefore = $this->count('users');

            $this->assertRejected(fn () => $this->service()->activate($fixture['contract_id'], $this->actorId, 1791870200));

            self::assertSame(0, $this->count('leases'));
            self::assertSame($usersBefore, $this->count('users'));
            self::assertSame($status, $this->unitStatus($fixture['unit_id']));
        }
    }

    public function testSameContractCannotActivateTwice(): void
    {
        $fixture = $this->fixture(candidateEmail: 'once@example.com');
        $service = $this->service();
        $first = $service->activate($fixture['contract_id'], $this->actorId, 1791870300);
        $usersAfterFirst = $this->count('users');

        $this->assertRejected(fn () => $service->activate($fixture['contract_id'], $this->actorId, 1791870310));

        self::assertSame(1, $this->count('leases'));
        self::assertSame($usersAfterFirst, $this->count('users'));
        self::assertSame($first['lease']['id'], $service->findByContract($fixture['contract_id'])['id']);
    }

    public function testTwoFinalizedContractsForSameUnitCanProduceOnlyOneActiveLease(): void
    {
        $first = $this->fixture(candidateEmail: 'first-unit@example.com', suffix: 'shared');
        $second = $this->candidateContractForUnit(
            $first['unit_id'],
            $first['version_id'],
            'second-unit@example.com',
            'SECOND-SHARED',
            'finalized',
        );
        $service = $this->service();

        $service->activate($first['contract_id'], $this->actorId, 1791870400);
        $this->assertRejected(fn () => $service->activate($second['contract_id'], $this->actorId, 1791870410));

        $active = (int) $this->pdo->query('SELECT COUNT(*) FROM leases WHERE ended_at IS NULL')->fetchColumn();
        self::assertSame(1, $active);
        self::assertSame('occupied', $this->unitStatus($first['unit_id']));
        self::assertSame(2, $this->count('users'));
    }

    public function testEmailCollisionRollsBackWithoutLeaseOrOccupation(): void
    {
        $fixture = $this->fixture(candidateEmail: 'collision@example.com');
        $this->insertUser('admin', 'Existing Staff', 'collision@example.com');
        $usersBefore = $this->count('users');

        $this->assertRejected(fn () => $this->service()->activate($fixture['contract_id'], $this->actorId, 1791870500));

        self::assertSame($usersBefore, $this->count('users'));
        self::assertSame(0, $this->count('leases'));
        self::assertSame('available', $this->unitStatus($fixture['unit_id']));
    }

    public function testFailureAfterTenantCreationRollsBackUserLeaseAndUnit(): void
    {
        $fixture = $this->fixture(candidateEmail: 'rollback@example.com');
        $usersBefore = $this->count('users');
        $this->pdo->exec(
            "CREATE TRIGGER leases_force_failure BEFORE INSERT ON leases FOR EACH ROW "
            . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced lease insert failure'",
        );

        try {
            $this->assertRejected(fn () => $this->service()->activate($fixture['contract_id'], $this->actorId, 1791870600));
        } finally {
            $this->pdo->exec('DROP TRIGGER IF EXISTS leases_force_failure');
        }

        self::assertSame($usersBefore, $this->count('users'));
        self::assertSame(0, $this->count('leases'));
        self::assertSame('available', $this->unitStatus($fixture['unit_id']));
    }

    public function testActivationDoesNotMutateCandidateScreeningOrFrozenContractEvidence(): void
    {
        $fixture = $this->fixture(candidateEmail: 'immutable@example.com');
        $candidateBefore = $this->row('candidates', $fixture['candidate_id']);
        $screeningBefore = $this->row('screening_processes', $fixture['screening_id']);
        $contractBefore = $this->row('contracts', $fixture['contract_id']);

        $this->service()->activate($fixture['contract_id'], $this->actorId, 1791870700);

        self::assertSame($candidateBefore, $this->row('candidates', $fixture['candidate_id']));
        self::assertSame($screeningBefore, $this->row('screening_processes', $fixture['screening_id']));
        self::assertSame($contractBefore, $this->row('contracts', $fixture['contract_id']));
    }

    public function testUnitLockPrecedesTenantAndLeaseSideEffectsStructurally(): void
    {
        $path = dirname(__DIR__, 3) . '/app/Leases/LeaseService.php';
        self::assertFileExists($path);
        $source = (string) file_get_contents($path);

        $unitLock = strpos($source, 'FROM units WHERE id = :id FOR UPDATE');
        $tenantWrite = strpos($source, 'createTenantIdentity');
        $leaseWrite = strpos($source, 'INSERT INTO leases');
        self::assertNotFalse($unitLock);
        self::assertNotFalse($tenantWrite);
        self::assertNotFalse($leaseWrite);
        self::assertLessThan($tenantWrite, $unitLock, 'Unit lock must precede tenant identity creation.');
        self::assertLessThan($leaseWrite, $unitLock, 'Unit lock must precede lease insert.');
    }

    private function service(): LeaseService
    {
        if (!class_exists(LeaseService::class)) {
            self::fail('LeaseService must exist before Task 3 can pass.');
        }

        return new LeaseService($this->pdo, new UserService($this->pdo));
    }

    /** @return array{property_id:int,unit_id:int,candidate_id:int,screening_id:int,version_id:int,contract_id:int} */
    private function fixture(
        string $contractStatus = 'finalized',
        string $unitStatus = 'available',
        string $candidateEmail = 'tenant@example.com',
        string $suffix = '1',
    ): array {
        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio F8 ' . $suffix,
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
            'code' => 'A-' . $suffix,
            'location_label' => 'Piso 4',
            'status' => $unitStatus,
            'monthly_price' => '1500000.00',
            'administration_fee' => '220000.00',
            'unit_number' => '401',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type, is_active, created_by_user_id) '
            . 'VALUES (:name, :document_type, 1, :created_by_user_id)',
        );
        $template->execute([
            'name' => 'Template F8 ' . $suffix,
            'document_type' => 'urban_housing',
            'created_by_user_id' => $this->actorId,
        ]);
        $templateId = (int) $this->pdo->lastInsertId();

        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions '
            . '(template_id, version_number, body_template, variables_json, created_by_user_id) '
            . 'VALUES (:template_id, 1, :body_template, :variables_json, :created_by_user_id)',
        );
        $version->execute([
            'template_id' => $templateId,
            'body_template' => 'Contrato F8',
            'variables_json' => '[]',
            'created_by_user_id' => $this->actorId,
        ]);
        $versionId = (int) $this->pdo->lastInsertId();

        $candidateContract = $this->candidateContractForUnit(
            $unitId,
            $versionId,
            $candidateEmail,
            'DOC-' . $suffix . '-' . bin2hex(random_bytes(3)),
            $contractStatus,
            $suffix,
        );

        return [
            'property_id' => $propertyId,
            'unit_id' => $unitId,
            'candidate_id' => $candidateContract['candidate_id'],
            'screening_id' => $candidateContract['screening_id'],
            'version_id' => $versionId,
            'contract_id' => $candidateContract['contract_id'],
        ];
    }

    /** @return array{candidate_id:int,screening_id:int,contract_id:int} */
    private function candidateContractForUnit(
        int $unitId,
        int $versionId,
        string $email,
        string $document,
        string $contractStatus,
        string $nameSuffix = '2',
    ): array {
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates '
            . '(unit_id, document_type, document_number, full_name, phone, email, current_address, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :current_address, '
            . ':status, :token_hash, :expires_at, :consent_at)',
        );
        $candidate->execute([
            'unit_id' => $unitId,
            'document_type' => 'cc',
            'document_number' => $document,
            'full_name' => 'Tenant F8 ' . $nameSuffix,
            'phone' => '3001234567',
            'email' => $email,
            'current_address' => 'Carrera 50 # 40-20',
            'status' => 'pending_screening',
            'token_hash' => bin2hex(random_bytes(32)),
            'expires_at' => '2027-01-01 00:00:00',
            'consent_at' => '2026-10-13 09:00:00',
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
            'responsible_user_id' => $this->actorId,
            'result_at' => '2026-10-13 09:30:00',
        ]);
        $screeningId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts '
            . '(candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, '
            . 'start_date, end_date, duration_text, manual_variables_json, tasks_json, annexes_json, resolved_variables_json, '
            . 'snapshot_html, pdf_stored_name, pdf_sha256, pdf_size_bytes, created_by_user_id, finalized_by_user_id, '
            . 'generated_at, finalized_at) '
            . 'VALUES (:candidate_id, :unit_id, :template_version_id, :document_type, :status, :monthly_rent, '
            . ':administration_fee, :start_date, :end_date, :duration_text, :manual_variables_json, :tasks_json, '
            . ':annexes_json, :resolved_variables_json, :snapshot_html, :pdf_stored_name, :pdf_sha256, :pdf_size_bytes, '
            . ':created_by_user_id, :finalized_by_user_id, :generated_at, :finalized_at)',
        );
        $contract->execute([
            'candidate_id' => $candidateId,
            'unit_id' => $unitId,
            'template_version_id' => $versionId,
            'document_type' => 'urban_housing',
            'status' => $contractStatus,
            'monthly_rent' => '1750000.00',
            'administration_fee' => '250000.00',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'duration_text' => 'Doce meses exactos',
            'manual_variables_json' => '{}',
            'tasks_json' => '[]',
            'annexes_json' => '[]',
            'resolved_variables_json' => '{"candidate.full_name":"Tenant F8"}',
            'snapshot_html' => '<article>Frozen F8 evidence</article>',
            'pdf_stored_name' => str_repeat('a', 32) . '.pdf',
            'pdf_sha256' => str_repeat('b', 64),
            'pdf_size_bytes' => 1234,
            'created_by_user_id' => $this->actorId,
            'finalized_by_user_id' => $contractStatus === 'finalized' ? $this->actorId : null,
            'generated_at' => $contractStatus === 'draft' ? null : '2026-10-13 09:40:00',
            'finalized_at' => $contractStatus === 'finalized' ? '2026-10-13 09:50:00' : null,
        ]);

        return [
            'candidate_id' => $candidateId,
            'screening_id' => $screeningId,
            'contract_id' => (int) $this->pdo->lastInsertId(),
        ];
    }

    private function insertUser(string $role, string $name, string $email): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => $name,
            'email' => strtolower($email),
            'password_hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function count(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }

    private function unitStatus(int $unitId): string
    {
        return (string) $this->pdo->query('SELECT status FROM units WHERE id = ' . $unitId)->fetchColumn();
    }

    private function row(string $table, int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM `' . $table . '` WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    private function assertRejected(callable $callback): void
    {
        try {
            $callback();
            self::fail('La activación debía rechazarse.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }
}
