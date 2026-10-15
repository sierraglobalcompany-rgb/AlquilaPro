<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Leases;

use AlquilaPro\Auth\UserService;
use AlquilaPro\Community\CommunityService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\ObligationService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Insurance\InsuranceService;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\Leases\TerminationService;
use AlquilaPro\Memberships\MembershipService;
use PDO;
use PHPUnit\Framework\TestCase;

final class TerminationPreviewTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private int $leaseId;

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
        $this->directory = sys_get_temp_dir() . '/alquilapro-f21-preview-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->leaseId = $this->leaseFixture();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
    }

    public function testPreviewIsDerivedReturnsCanonicalSectionsAndPersistsNothing(): void
    {
        $beforeBonuses = $this->countRows('good_conduct_bonuses');
        $beforeLiquidations = $this->countRows('lease_liquidations');
        $beforeCharges = $this->countRows('charges');
        $beforePayments = $this->countRows('payments');

        $preview = $this->service()->preview($this->leaseId, '2026-10-14', [
            'property_returned' => true,
            'keys_returned_count' => 2,
            'notes' => '  Entrega física confirmada  ',
        ]);

        foreach ([
            'lease',
            'contract',
            'handover',
            'finance',
            'obligations',
            'claims',
            'inventories',
            'current_documents',
            'community',
            'memberships',
            'bonus',
            'blockers',
        ] as $section) {
            self::assertArrayHasKey($section, $preview);
        }

        self::assertSame($this->leaseId, $preview['lease']['id']);
        self::assertSame([
            'property_returned' => true,
            'keys_returned_count' => 2,
            'notes' => 'Entrega física confirmada',
        ], $preview['handover']);
        self::assertIsArray($preview['blockers']);

        self::assertSame($beforeBonuses, $this->countRows('good_conduct_bonuses'));
        self::assertSame($beforeLiquidations, $this->countRows('lease_liquidations'));
        self::assertSame($beforeCharges, $this->countRows('charges'));
        self::assertSame($beforePayments, $this->countRows('payments'));
    }

    private function service(): TerminationService
    {
        $paymentMethods = new PaymentMethodService($this->pdo, $this->directory);
        $finance = new FinanceService($this->pdo, $paymentMethods, $this->directory);

        return new TerminationService(
            $this->pdo,
            new LeaseService($this->pdo, new UserService($this->pdo)),
            $finance,
            new ObligationService($this->pdo, $finance, $this->directory),
            new InsuranceService($this->pdo, $this->directory),
            new DocumentService($this->pdo, $this->directory),
            new CommunityService($this->pdo),
            new MembershipService($this->pdo, $finance),
            true,
        );
    }

    private function countRows(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }

    private function leaseFixture(): int
    {
        $actorId = $this->insertUser('owner', 'Owner Preview F21', 'owner-preview-' . bin2hex(random_bytes(3)) . '@example.com');
        $tenantId = $this->insertUser('tenant', 'Tenant Preview F21', 'tenant-preview-' . bin2hex(random_bytes(3)) . '@example.com');

        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio Preview F21 ' . bin2hex(random_bytes(2)),
            'address' => 'Calle 50 # 10-20',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status, monthly_price, administration_fee, unit_number) '
            . 'VALUES (:property_id, :public_key, \'apartment\', :code, \'occupied\', \'1500000.00\', \'200000.00\', \'501\')',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'code' => 'F21-PREVIEW-' . bin2hex(random_bytes(3)),
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type, is_active, created_by_user_id) VALUES (:name, \'urban_housing\', 1, :actor)',
        );
        $template->execute(['name' => 'Template Preview F21 ' . bin2hex(random_bytes(3)), 'actor' => $actorId]);
        $templateId = (int) $this->pdo->lastInsertId();

        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json, created_by_user_id) '
            . 'VALUES (:template, 1, \'Contrato Preview F21\', \'[]\', :actor)',
        );
        $version->execute(['template' => $templateId, 'actor' => $actorId]);
        $versionId = (int) $this->pdo->lastInsertId();

        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, current_address, status, continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit, \'cc\', :document, \'Tenant Preview F21\', \'3001112233\', :email, \'Carrera 10 # 11-12\', \'pending_screening\', :token, \'2027-01-01 00:00:00\', \'2026-10-14 10:00:00\')',
        );
        $candidate->execute([
            'unit' => $unitId,
            'document' => 'F21-P-' . bin2hex(random_bytes(4)),
            'email' => 'candidate-preview-' . bin2hex(random_bytes(3)) . '@example.com',
            'token' => bin2hex(random_bytes(32)),
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, start_date, end_date, duration_text, resolved_variables_json, snapshot_html, pdf_stored_name, pdf_sha256, pdf_size_bytes, created_by_user_id, finalized_by_user_id, generated_at, finalized_at) '
            . 'VALUES (:candidate, :unit, :version, \'urban_housing\', \'finalized\', \'1750000.00\', \'250000.00\', \'2026-11-01\', \'2027-10-31\', \'Doce meses\', \'{}\', \'<article>F21 Preview</article>\', :pdf_name, :pdf_hash, 1234, :created_actor, :finalized_actor, \'2026-10-14 10:10:00\', \'2026-10-14 10:20:00\')',
        );
        $contract->execute([
            'candidate' => $candidateId,
            'unit' => $unitId,
            'version' => $versionId,
            'pdf_name' => str_repeat('c', 32) . '.pdf',
            'pdf_hash' => str_repeat('d', 64),
            'created_actor' => $actorId,
            'finalized_actor' => $actorId,
        ]);
        $contractId = (int) $this->pdo->lastInsertId();

        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) '
            . 'VALUES (:contract, :tenant, :actor, \'2026-10-14 11:00:00\')',
        );
        $lease->execute(['contract' => $contractId, 'tenant' => $tenantId, 'actor' => $actorId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertUser(string $role, string $name, string $email): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) VALUES (:role, :name, :email, :hash, 1)',
        );
        $statement->execute([
            'role' => $role,
            'name' => $name,
            'email' => strtolower($email),
            'hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
