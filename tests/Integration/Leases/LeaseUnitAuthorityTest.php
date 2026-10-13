<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Leases;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Properties\PropertyService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LeaseUnitAuthorityTest extends TestCase
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

    public function testActiveLeaseBlocksEveryStatusExitFromOccupiedButAllowsOtherEdits(): void
    {
        $fixture = $this->fixture();
        $service = new PropertyService($this->pdo);

        foreach (['available', 'in_process', 'inactive', 'maintenance'] as $status) {
            try {
                $service->updateUnit($fixture['unit_id'], $this->unitInput($fixture['code'], $status));
                self::fail('Active lease must block status transition to ' . $status . '.');
            } catch (RuntimeException) {
                self::assertSame('occupied', $service->findUnit($fixture['unit_id'])['status']);
            }
        }

        $updated = $service->updateUnit(
            $fixture['unit_id'],
            $this->unitInput($fixture['code'] . '-EDIT', 'occupied', 'Torre Sur'),
        );
        self::assertSame('occupied', $updated['status']);
        self::assertSame($fixture['code'] . '-EDIT', $updated['code']);
        self::assertSame('Torre Sur', $updated['location_label']);
    }

    public function testEndedLeaseRestoresExistingManualStatusTransitionsWithoutAutoRelease(): void
    {
        $fixture = $this->fixture();
        $service = new PropertyService($this->pdo);

        $this->pdo->prepare(
            'UPDATE leases SET ended_by_user_id = :actor_id, ended_at = :ended_at WHERE id = :id',
        )->execute([
            'actor_id' => $fixture['owner_id'],
            'ended_at' => '2026-10-13 11:00:00',
            'id' => $fixture['lease_id'],
        ]);

        self::assertSame('occupied', $service->findUnit($fixture['unit_id'])['status']);

        $updated = $service->updateUnit(
            $fixture['unit_id'],
            $this->unitInput($fixture['code'], 'maintenance'),
        );
        self::assertSame('maintenance', $updated['status']);
    }

    public function testActiveLeasedUnitCannotBecomePublicThroughTheExistingEditor(): void
    {
        $fixture = $this->fixture(publicReady: true);
        $service = new PropertyService($this->pdo);

        try {
            $service->updateUnit(
                $fixture['unit_id'],
                array_merge($this->unitInput($fixture['code'], 'available'), ['is_public' => '1']),
            );
            self::fail('An active leased unit must not become available/public.');
        } catch (RuntimeException) {
            self::assertSame('occupied', $service->findUnit($fixture['unit_id'])['status']);
        }

        self::assertCount(0, $service->listPublicUnits(null, 'Medellín', 'Calasanz'));
    }

    public function testUnitEditorSerializesStatusCheckWithLeaseActivation(): void
    {
        $path = dirname(__DIR__, 3) . '/app/Properties/PropertyService.php';
        self::assertFileExists($path);
        $source = (string) file_get_contents($path);

        $start = strpos($source, 'public function updateUnit(');
        $end = strpos($source, 'public function findUnit(', $start === false ? 0 : $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $method = substr($source, $start, $end - $start);

        $transaction = strpos($method, '$this->pdo->beginTransaction()');
        $unitLock = strpos($method, 'FROM units WHERE id = :id FOR UPDATE');
        $leaseCheck = strpos($method, 'FROM leases l');
        $unitUpdate = strpos($method, 'UPDATE units SET');
        $commit = strpos($method, '$this->pdo->commit()');

        self::assertNotFalse($transaction, 'The editor must update the unit inside a transaction.');
        self::assertNotFalse($unitLock, 'The editor must lock the canonical unit row.');
        self::assertNotFalse($leaseCheck, 'The editor must check the active lease while serialized on the unit.');
        self::assertNotFalse($unitUpdate, 'The editor must update the canonical unit row.');
        self::assertNotFalse($commit, 'The editor must commit only after the guarded update.');
        self::assertLessThan($unitLock, $transaction);
        self::assertLessThan($leaseCheck, $unitLock);
        self::assertLessThan($unitUpdate, $leaseCheck);
        self::assertLessThan($commit, $unitUpdate);
    }

    /** @return array{owner_id:int,unit_id:int,lease_id:int,code:string} */
    private function fixture(bool $publicReady = false): array
    {
        $ownerId = $this->insertUser('owner', 'Owner Unit Guard', 'owner-unit-' . bin2hex(random_bytes(3)) . '@example.com');
        $tenantId = $this->insertUser('tenant', 'Tenant Unit Guard', 'tenant-unit-' . bin2hex(random_bytes(3)) . '@example.com');

        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio Unit Guard',
            'address' => 'Calle 30 # 40-50',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();
        $code = 'UG-' . strtoupper(bin2hex(random_bytes(2)));

        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements, unit_number) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, :status, :is_public, :monthly_price, :description, :requirements, :unit_number)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'type' => 'apartment',
            'code' => $code,
            'location_label' => 'Torre Norte',
            'status' => 'occupied',
            'is_public' => $publicReady ? 1 : 0,
            'monthly_price' => '1800000.00',
            'description' => 'Unidad completa',
            'requirements' => 'Ingresos demostrables',
            'unit_number' => '502',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        if ($publicReady) {
            $photo = $this->pdo->prepare(
                "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
                . "VALUES (:unit_id, 'photo', :stored_name, 'image/png', 1, 1)",
            );
            $photo->execute([
                'unit_id' => $unitId,
                'stored_name' => 'photo-' . bin2hex(random_bytes(16)) . '.png',
            ]);
        }

        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type, is_active, created_by_user_id) '
            . 'VALUES (:name, :document_type, 1, :created_by_user_id)',
        );
        $template->execute([
            'name' => 'Template Unit Guard ' . bin2hex(random_bytes(3)),
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
            'body_template' => 'Contrato unit guard',
            'variables_json' => '[]',
            'created_by_user_id' => $ownerId,
        ]);
        $versionId = (int) $this->pdo->lastInsertId();

        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates '
            . '(unit_id, document_type, document_number, full_name, phone, email, current_address, status, continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :current_address, :status, :token_hash, :expires_at, :consent_at)',
        );
        $candidate->execute([
            'unit_id' => $unitId,
            'document_type' => 'cc',
            'document_number' => 'UG-' . bin2hex(random_bytes(4)),
            'full_name' => 'Tenant Unit Guard',
            'phone' => '3009998877',
            'email' => 'candidate-unit-' . bin2hex(random_bytes(3)) . '@example.com',
            'current_address' => 'Carrera 1 # 2-3',
            'status' => 'pending_screening',
            'token_hash' => bin2hex(random_bytes(32)),
            'expires_at' => '2027-01-01 00:00:00',
            'consent_at' => '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts '
            . '(candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, start_date, end_date, duration_text, created_by_user_id, finalized_by_user_id, generated_at, finalized_at) '
            . 'VALUES (:candidate_id, :unit_id, :template_version_id, :document_type, :status, :monthly_rent, :administration_fee, :start_date, :end_date, :duration_text, :created_by_user_id, :finalized_by_user_id, :generated_at, :finalized_at)',
        );
        $contract->execute([
            'candidate_id' => $candidateId,
            'unit_id' => $unitId,
            'template_version_id' => $versionId,
            'document_type' => 'urban_housing',
            'status' => 'finalized',
            'monthly_rent' => '1800000.00',
            'administration_fee' => null,
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'duration_text' => '12 meses',
            'created_by_user_id' => $ownerId,
            'finalized_by_user_id' => $ownerId,
            'generated_at' => '2026-10-13 10:10:00',
            'finalized_at' => '2026-10-13 10:20:00',
        ]);
        $contractId = (int) $this->pdo->lastInsertId();

        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) '
            . 'VALUES (:contract_id, :user_id, :activated_by_user_id, :activated_at)',
        );
        $lease->execute([
            'contract_id' => $contractId,
            'user_id' => $tenantId,
            'activated_by_user_id' => $ownerId,
            'activated_at' => '2026-10-13 10:30:00',
        ]);

        return [
            'owner_id' => $ownerId,
            'unit_id' => $unitId,
            'lease_id' => (int) $this->pdo->lastInsertId(),
            'code' => $code,
        ];
    }

    private function unitInput(string $code, string $status, string $location = 'Torre Norte'): array
    {
        return [
            'type' => 'apartment',
            'code' => $code,
            'status' => $status,
            'location_label' => $location,
            'monthly_price' => 1800000,
            'description' => 'Unidad completa',
            'requirements' => 'Ingresos demostrables',
            'unit_number' => '502',
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
