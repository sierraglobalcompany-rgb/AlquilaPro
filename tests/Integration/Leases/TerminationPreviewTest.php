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

    public function testPreviewClassifiesOnlyCriticalDerivedBlockers(): void
    {
        $this->seedCriticalAndNonBlockingContext();

        $preview = $this->service()->preview($this->leaseId, '2026-10-14');

        foreach ([
            'payment_pending_reconciliation',
            'membership_charge_decision_pending',
            'bonus_decision_pending',
            'final_inventory_missing',
        ] as $blocker) {
            self::assertContains($blocker, $preview['blockers']);
        }

        foreach ([
            'outstanding_balance',
            'community_incident_open',
            'insurance_claim_open',
        ] as $nonBlocker) {
            self::assertNotContains($nonBlocker, $preview['blockers']);
        }

        self::assertSame('250000.00', $preview['finance']['summary']['outstanding']);
        self::assertSame('open', $preview['community'][0]['status']);
        self::assertSame('filed', $preview['claims'][0]['claims'][0]['status']);
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

    private function seedCriticalAndNonBlockingContext(): void
    {
        $lease = $this->pdo->prepare(
            'SELECT user_id, activated_by_user_id FROM leases WHERE id = :id LIMIT 1',
        );
        $lease->execute(['id' => $this->leaseId]);
        $leaseRow = $lease->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($leaseRow);
        $tenantId = (int) $leaseRow['user_id'];
        $actorId = (int) $leaseRow['activated_by_user_id'];

        $paymentMethod = $this->pdo->prepare(
            'INSERT INTO payment_methods (type, label, sort_order, is_active) '
            . 'VALUES (\'cash\', :label, 1, 1)',
        );
        $paymentMethod->execute(['label' => 'F21 pending payment ' . bin2hex(random_bytes(2))]);
        $paymentMethodId = (int) $this->pdo->lastInsertId();

        $payment = $this->pdo->prepare(
            'INSERT INTO payments '
            . '(lease_id, payment_method_id, amount, paid_on, payment_method_snapshot_json, reported_at) '
            . 'VALUES (:lease_id, :method_id, \'100000.00\', \'2026-10-13\', \'{}\', \'2026-10-13 12:00:00\')',
        );
        $payment->execute(['lease_id' => $this->leaseId, 'method_id' => $paymentMethodId]);

        $service = $this->pdo->prepare(
            'INSERT INTO digital_services '
            . '(name, description, current_price, duration_days, active, created_by_user_id, created_at) '
            . 'VALUES (:name, \'Servicio F21 pendiente\', \'45000.00\', 30, 1, :actor, \'2026-10-01 09:00:00\')',
        );
        $service->execute([
            'name' => 'Servicio F21 ' . bin2hex(random_bytes(2)),
            'actor' => $actorId,
        ]);
        $serviceId = (int) $this->pdo->lastInsertId();

        $membership = $this->pdo->prepare(
            'INSERT INTO service_memberships '
            . '(service_id, lease_id, accepted_by_user_id, price_snapshot, duration_days_snapshot, starts_on, ends_on, accepted_at) '
            . 'VALUES (:service_id, :lease_id, :tenant_id, \'45000.00\', 30, \'2026-10-01\', \'2026-10-30\', \'2026-10-01 09:05:00\')',
        );
        $membership->execute([
            'service_id' => $serviceId,
            'lease_id' => $this->leaseId,
            'tenant_id' => $tenantId,
        ]);

        $bonus = $this->pdo->prepare(
            'INSERT INTO good_conduct_bonuses '
            . '(lease_id, amount, note, decision, created_by_user_id, created_at) '
            . 'VALUES (:lease_id, \'120000.00\', \'Pendiente de decisión\', NULL, :actor, \'2026-10-14 08:00:00\')',
        );
        $bonus->execute(['lease_id' => $this->leaseId, 'actor' => $actorId]);

        $inventory = $this->pdo->prepare(
            'INSERT INTO documents '
            . '(property_id, lease_id, community_incident_id, type, title, content_json, created_by_user_id, created_at) '
            . 'VALUES (NULL, :lease_id, NULL, \'inventory_initial\', \'Inventario inicial F21\', :content, :actor, \'2026-10-01 08:00:00\')',
        );
        $inventory->execute([
            'lease_id' => $this->leaseId,
            'content' => '{"general_notes":null,"items":[]}',
            'actor' => $actorId,
        ]);

        $charge = $this->pdo->prepare(
            'INSERT INTO charges '
            . '(lease_id, type, due_date, amount, description, created_by_user_id) '
            . 'VALUES (:lease_id, \'other\', \'2026-10-10\', \'250000.00\', \'Saldo pendiente no bloqueante\', :actor)',
        );
        $charge->execute(['lease_id' => $this->leaseId, 'actor' => $actorId]);

        $incident = $this->pdo->prepare(
            'INSERT INTO community_incidents '
            . '(lease_id, category, occurred_on, description, status, created_by_user_id, created_at, updated_at) '
            . 'VALUES (:lease_id, \'noise\', \'2026-10-12\', \'Incidente abierto no bloqueante\', \'open\', :actor, '
            . '\'2026-10-12 08:00:00\', \'2026-10-12 08:00:00\')',
        );
        $incident->execute(['lease_id' => $this->leaseId, 'actor' => $actorId]);

        $policy = $this->pdo->prepare(
            'INSERT INTO insurance_policies '
            . '(lease_id, provider_name, product_name, policy_number, coverage_start, coverage_end, coverage_snapshot_json, '
            . 'documents_json, created_by_user_id, created_at) '
            . 'VALUES (:lease_id, \'Aseguradora F21\', \'Hogar\', :number, \'2026-01-01\', \'2026-12-31\', '
            . '\'[]\', \'[]\', :actor, \'2026-01-01 08:00:00\')',
        );
        $policy->execute([
            'lease_id' => $this->leaseId,
            'number' => 'F21-' . bin2hex(random_bytes(3)),
            'actor' => $actorId,
        ]);
        $policyId = (int) $this->pdo->lastInsertId();

        $claim = $this->pdo->prepare(
            'INSERT INTO insurance_claims '
            . '(policy_id, incident_on, description, claimed_amount, status, radicado, filed_on, checklist_snapshot_json, '
            . 'documents_json, created_by_user_id, created_at) '
            . 'VALUES (:policy_id, \'2026-10-11\', \'Claim abierto no bloqueante\', \'300000.00\', \'filed\', :radicado, '
            . '\'2026-10-12\', \'[]\', \'[]\', :actor, \'2026-10-12 09:00:00\')',
        );
        $claim->execute([
            'policy_id' => $policyId,
            'radicado' => 'RAD-' . bin2hex(random_bytes(3)),
            'actor' => $actorId,
        ]);
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
