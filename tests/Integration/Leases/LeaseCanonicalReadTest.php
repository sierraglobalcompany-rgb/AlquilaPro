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

final class LeaseCanonicalReadTest extends TestCase
{
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

    public function testFindReturnsCanonicalLeaseProjectionAndNullForInvalidOrMissingIds(): void
    {
        if (!method_exists(LeaseService::class, 'find')) {
            self::fail('LeaseService::find() must exist as the canonical lease-id read.');
        }

        $fixture = $this->leaseFixture();
        $service = new LeaseService($this->pdo, new UserService($this->pdo));

        self::assertSame(
            $service->findByContract($fixture['contract_id']),
            $service->find($fixture['lease_id']),
        );
        self::assertNull($service->find(0));
        self::assertNull($service->find(-1));
        self::assertNull($service->find(999999999));
    }

    /** @return array{lease_id:int,contract_id:int} */
    private function leaseFixture(): array
    {
        $ownerId = $this->insertUser('owner', 'Owner Canonical', 'owner-canonical-' . bin2hex(random_bytes(3)) . '@example.com');
        $tenantId = $this->insertUser('tenant', 'Tenant Canonical', 'tenant-canonical-' . bin2hex(random_bytes(3)) . '@example.com');

        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio Canonical ' . bin2hex(random_bytes(2)),
            'address' => 'Calle 50 # 10-20',
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
            'code' => 'CR-' . bin2hex(random_bytes(3)),
            'status' => 'occupied',
            'monthly_price' => '1500000.00',
            'administration_fee' => '200000.00',
            'unit_number' => '501',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type, is_active, created_by_user_id) '
            . 'VALUES (:name, :document_type, 1, :created_by_user_id)',
        );
        $template->execute([
            'name' => 'Template Canonical ' . bin2hex(random_bytes(3)),
            'document_type' => 'urban_housing',
            'created_by_user_id' => $ownerId,
        ]);
        $templateId = (int) $this->pdo->lastInsertId();

        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json, created_by_user_id) '
            . 'VALUES (:template_id, 1, :body_template, :variables_json, :created_by_user_id)',
        );
        $version->execute([
            'template_id' => $templateId,
            'body_template' => 'Contrato canonical',
            'variables_json' => '[]',
            'created_by_user_id' => $ownerId,
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
            'document_number' => 'CAN-' . bin2hex(random_bytes(4)),
            'full_name' => 'Tenant Canonical',
            'phone' => '3001112233',
            'email' => 'candidate-canonical-' . bin2hex(random_bytes(3)) . '@example.com',
            'current_address' => 'Carrera 10 # 11-12',
            'status' => 'pending_screening',
            'token_hash' => bin2hex(random_bytes(32)),
            'expires_at' => '2027-01-01 00:00:00',
            'consent_at' => '2026-10-14 10:00:00',
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
            'snapshot_html' => '<article>Canonical lease</article>',
            'pdf_stored_name' => str_repeat('c', 32) . '.pdf',
            'pdf_sha256' => str_repeat('d', 64),
            'pdf_size_bytes' => 1234,
            'created_by_user_id' => $ownerId,
            'finalized_by_user_id' => $ownerId,
            'generated_at' => '2026-10-14 10:10:00',
            'finalized_at' => '2026-10-14 10:20:00',
        ]);
        $contractId = (int) $this->pdo->lastInsertId();

        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) '
            . 'VALUES (:contract_id, :user_id, :actor_id, :activated_at)',
        );
        $lease->execute([
            'contract_id' => $contractId,
            'user_id' => $tenantId,
            'actor_id' => $ownerId,
            'activated_at' => '2026-10-14 11:00:00',
        ]);

        return [
            'lease_id' => (int) $this->pdo->lastInsertId(),
            'contract_id' => $contractId,
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
}
