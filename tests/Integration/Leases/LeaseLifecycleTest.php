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

final class LeaseLifecycleTest extends TestCase
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
        $this->actorId = $this->insertUser('owner', 'Owner Lifecycle', 'owner-lifecycle@example.com');
    }

    public function testEndingActiveLeaseRecordsActorOnceAndKeepsUnitUserContractIntact(): void
    {
        $fixture = $this->activeLeaseFixture();
        $endActorId = $this->insertUser('admin', 'Admin End', 'admin-end@example.com');
        $unitBefore = $this->row('units', $fixture['unit_id']);
        $tenantBefore = $this->row('users', $fixture['tenant_user_id']);
        $contractBefore = $this->row('contracts', $fixture['contract_id']);
        $endedAt = 1791876000;

        $ended = $this->service()->end($fixture['lease_id'], $endActorId, $endedAt);

        self::assertSame($fixture['lease_id'], $ended['id']);
        self::assertFalse($ended['is_active']);
        self::assertSame($endActorId, $ended['ended_by_user_id']);
        self::assertSame(gmdate('Y-m-d H:i:s', $endedAt), $ended['ended_at']);
        self::assertSame($unitBefore, $this->row('units', $fixture['unit_id']));
        self::assertSame($tenantBefore, $this->row('users', $fixture['tenant_user_id']));
        self::assertSame($contractBefore, $this->row('contracts', $fixture['contract_id']));

        $historical = $this->service()->findByContract($fixture['contract_id']);
        self::assertIsArray($historical);
        self::assertFalse($historical['is_active']);
        self::assertSame($endActorId, $historical['ended_by_user_id']);
        self::assertSame(gmdate('Y-m-d H:i:s', $endedAt), $historical['ended_at']);
    }

    public function testSecondEndFailsClosedWithoutOverwritingOriginalActorOrTime(): void
    {
        $fixture = $this->activeLeaseFixture('second-end@example.com');
        $firstActorId = $this->insertUser('admin', 'First End', 'first-end@example.com');
        $secondActorId = $this->insertUser('admin', 'Second End', 'second-end-actor@example.com');
        $service = $this->service();

        $first = $service->end($fixture['lease_id'], $firstActorId, 1791876100);

        try {
            $service->end($fixture['lease_id'], $secondActorId, 1791876200);
            self::fail('Un arrendamiento terminado no puede terminarse de nuevo.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }

        $after = $service->findByContract($fixture['contract_id']);
        self::assertIsArray($after);
        self::assertSame($firstActorId, $after['ended_by_user_id']);
        self::assertSame($first['ended_at'], $after['ended_at']);
        self::assertSame('occupied', $this->row('units', $fixture['unit_id'])['status']);
        self::assertSame(1, (int) $this->row('users', $fixture['tenant_user_id'])['is_active']);
    }

    public function testMissingLeaseCannotBeEnded(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service()->end(999999, $this->actorId, 1791876300);
    }

    private function service(): LeaseService
    {
        return new LeaseService($this->pdo, new UserService($this->pdo));
    }

    /** @return array{lease_id:int,contract_id:int,unit_id:int,tenant_user_id:int} */
    private function activeLeaseFixture(string $tenantEmail = 'tenant-lifecycle@example.com'): array
    {
        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio Lifecycle',
            'address' => 'Calle 20 # 30-40',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status, monthly_price, administration_fee, unit_number) '
            . 'VALUES (:property_id, :public_key, :type, :code, :status, :monthly_price, :administration_fee, :unit_number)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'type' => 'apartment',
            'code' => 'L-401-' . bin2hex(random_bytes(2)),
            'status' => 'available',
            'monthly_price' => '1500000.00',
            'administration_fee' => '200000.00',
            'unit_number' => '401',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type, is_active, created_by_user_id) '
            . 'VALUES (:name, :document_type, 1, :created_by_user_id)',
        );
        $template->execute([
            'name' => 'Template Lifecycle ' . bin2hex(random_bytes(3)),
            'document_type' => 'urban_housing',
            'created_by_user_id' => $this->actorId,
        ]);
        $templateId = (int) $this->pdo->lastInsertId();

        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json, created_by_user_id) '
            . 'VALUES (:template_id, 1, :body_template, :variables_json, :created_by_user_id)',
        );
        $version->execute([
            'template_id' => $templateId,
            'body_template' => 'Contrato lifecycle',
            'variables_json' => '[]',
            'created_by_user_id' => $this->actorId,
        ]);
        $versionId = (int) $this->pdo->lastInsertId();

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
            'document_number' => 'LC-' . bin2hex(random_bytes(4)),
            'full_name' => 'Tenant Lifecycle',
            'phone' => '3001112233',
            'email' => $tenantEmail,
            'current_address' => 'Carrera 10 # 11-12',
            'status' => 'pending_screening',
            'token_hash' => bin2hex(random_bytes(32)),
            'expires_at' => '2027-01-01 00:00:00',
            'consent_at' => '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts '
            . '(candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, '
            . 'start_date, end_date, duration_text, resolved_variables_json, snapshot_html, pdf_stored_name, pdf_sha256, '
            . 'pdf_size_bytes, created_by_user_id, finalized_by_user_id, generated_at, finalized_at) '
            . 'VALUES (:candidate_id, :unit_id, :template_version_id, :document_type, :status, :monthly_rent, '
            . ':administration_fee, :start_date, :end_date, :duration_text, :resolved_variables_json, :snapshot_html, '
            . ':pdf_stored_name, :pdf_sha256, :pdf_size_bytes, :created_by_user_id, :finalized_by_user_id, '
            . ':generated_at, :finalized_at)',
        );
        $contract->execute([
            'candidate_id' => $candidateId,
            'unit_id' => $unitId,
            'template_version_id' => $versionId,
            'document_type' => 'urban_housing',
            'status' => 'finalized',
            'monthly_rent' => '1750000.00',
            'administration_fee' => '250000.00',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'duration_text' => 'Doce meses exactos',
            'resolved_variables_json' => '{}',
            'snapshot_html' => '<article>Frozen lifecycle</article>',
            'pdf_stored_name' => str_repeat('c', 32) . '.pdf',
            'pdf_sha256' => str_repeat('d', 64),
            'pdf_size_bytes' => 1234,
            'created_by_user_id' => $this->actorId,
            'finalized_by_user_id' => $this->actorId,
            'generated_at' => '2026-10-13 10:10:00',
            'finalized_at' => '2026-10-13 10:20:00',
        ]);
        $contractId = (int) $this->pdo->lastInsertId();

        $activation = $this->service()->activate($contractId, $this->actorId, 1791875900);

        return [
            'lease_id' => (int) $activation['lease']['id'],
            'contract_id' => $contractId,
            'unit_id' => $unitId,
            'tenant_user_id' => (int) $activation['lease']['user_id'],
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

    private function row(string $table, int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM `' . $table . '` WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }
}
