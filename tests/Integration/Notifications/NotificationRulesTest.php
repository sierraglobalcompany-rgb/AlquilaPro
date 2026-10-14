<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Notifications;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Notifications\NotificationService;
use PDO;
use PHPUnit\Framework\TestCase;

final class NotificationRulesTest extends TestCase
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
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', str_replace('`', '``', (string) $table)));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
    }

    public function testGeneratesDeduplicatedRemindersForDueFinancialInsuranceAndVisitRecords(): void
    {
        $today = '2026-10-14';
        $now = $today . ' 09:00:00';
        $fixture = $this->createLease();

        $chargeId = $this->insertCharge($fixture['lease_id'], 'rent', '100000.00', $today);
        $paidChargeId = $this->insertCharge($fixture['lease_id'], 'fee', '50000.00', $today);
        $this->fullyPayCharge($fixture, $paidChargeId, '50000.00');
        $this->insertCharge($fixture['lease_id'], 'fee', '25000.00', $today, $now);

        $this->insertObligation($fixture['lease_id'], '2026-10-13', null, null);
        $this->insertObligation($fixture['lease_id'], $today, $now, null);
        $linkedObligationId = $this->insertObligation($fixture['lease_id'], $today, null, $chargeId);

        $supersededPolicyId = $this->insertPolicy($fixture['lease_id'], $today);
        $this->insertPolicy($fixture['lease_id'], $today);
        $this->insertPolicy($fixture['lease_id'], $today, $now);
        $this->insertPolicy($fixture['lease_id'], '2027-10-14', null, $supersededPolicyId);

        $requestId = $this->insertMaintenanceRequest($fixture['lease_id']);
        $previousLocalDateVisitId = $this->insertVisit($requestId, $today . ' 02:00:00', 'scheduled');
        $sameLocalDateVisitId = $this->insertVisit($requestId, $today . ' 14:00:00', 'scheduled');
        $lateLocalDateVisitId = $this->insertVisit($requestId, '2026-10-15 02:00:00', 'scheduled');
        $this->insertVisit($requestId, $today . ' 15:00:00', 'cancelled');

        $sentInline = 0;
        $service = new NotificationService($this->pdo, static function () use (&$sentInline): void {
            $sentInline++;
        });

        self::assertSame(5, $service->generateReminders($today, $now));
        self::assertSame(0, $service->generateReminders($today, $now));
        self::assertSame(0, $sentInline);

        $jobs = $this->pdo->query(
            'SELECT type, template_key, recipient_email, dedupe_key, payload_json '
            . 'FROM notification_jobs ORDER BY type, dedupe_key',
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(5, $jobs);
        self::assertSame([
            'charge_due',
            'maintenance_visit',
            'maintenance_visit',
            'obligation_due',
            'policy_expiry',
        ], array_column($jobs, 'type'));
        foreach ($jobs as $job) {
            self::assertSame('tenant@example.test', $job['recipient_email']);
            self::assertStringContainsString($today, $job['dedupe_key']);
            self::assertMatchesRegularExpression(
                '/\A\d{4}-\d{2}-\d{2}\z/',
                (string) json_decode($job['payload_json'], true)['event_date'],
            );
            self::assertSame(['event_date'], array_keys(json_decode($job['payload_json'], true)));
        }
        $visitPayloads = array_map(
            static fn (array $job): array => json_decode($job['payload_json'], true),
            array_values(array_filter($jobs, static fn (array $job): bool => $job['template_key'] === 'maintenance_visit')),
        );
        self::assertCount(2, $visitPayloads);
        self::assertSame([ $today, $today ], array_column($visitPayloads, 'event_date'));
        $visitKeys = array_column(
            array_values(array_filter($jobs, static fn (array $job): bool => $job['template_key'] === 'maintenance_visit')),
            'dedupe_key',
        );
        self::assertContains('visit:' . $sameLocalDateVisitId . ':scheduled:' . $today . ':v1', $visitKeys);
        self::assertContains('visit:' . $lateLocalDateVisitId . ':scheduled:' . $today . ':v1', $visitKeys);
        self::assertNotContains('visit:' . $previousLocalDateVisitId . ':scheduled:' . $today . ':v1', $visitKeys);
        self::assertNotSame(0, $linkedObligationId);
    }

    public function testCandidateEventsAreAllowlistedIdempotentAndRequireSubmission(): void
    {
        $fixture = $this->createLease();
        $sentInline = 0;
        $service = new NotificationService($this->pdo, static function () use (&$sentInline): void {
            $sentInline++;
        });
        $now = '2026-10-14 09:00:00';

        self::assertNotNull($service->queueCandidateEvent($fixture['candidate_id'], 'created', $now));
        self::assertNull($service->queueCandidateEvent($fixture['candidate_id'], 'created', $now));
        try {
            $service->queueCandidateEvent($fixture['candidate_id'], 'submitted', $now);
            self::fail('A candidate cannot receive a submission notice before submitting.');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        }

        $this->pdo->prepare('UPDATE candidates SET submitted_at = :submitted WHERE id = :id')->execute([
            'submitted' => $now,
            'id' => $fixture['candidate_id'],
        ]);
        self::assertNotNull($service->queueCandidateEvent($fixture['candidate_id'], 'submitted', $now));
        self::assertNull($service->queueCandidateEvent($fixture['candidate_id'], 'submitted', $now));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM notification_jobs')->fetchColumn());
        self::assertSame(0, $sentInline);
    }

    /** @return array{lease_id:int,tenant_id:int,property_id:int,candidate_id:int} */
    private function createLease(): array
    {
        $user = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (\'tenant\', \'Tenant Fixture\', \'tenant@example.test\', \'hash\', 1)',
        );
        $user->execute();
        $tenantId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec(
            "INSERT INTO properties (name, address, city) VALUES ('Notification Property', 'Calle 1', 'Bogotá')",
        );
        $propertyId = (int) $this->pdo->lastInsertId();
        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) '
            . 'VALUES (:property, :public_key, \'apartment\', \'APT-REM\', \'occupied\')',
        );
        $unit->execute(['property' => $propertyId, 'public_key' => bin2hex(random_bytes(12))]);
        $unitId = (int) $this->pdo->lastInsertId();

        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit, \'cc\', :number, \'Candidate Fixture\', \'3001234567\', \'candidate@example.test\', '
            . '\'approved\', :token, \'2027-01-01 00:00:00\', \'2026-10-01 00:00:00\')',
        );
        $candidate->execute(['unit' => $unitId, 'number' => bin2hex(random_bytes(6)), 'token' => hash('sha256', 'token')]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO contract_templates (name, document_type) VALUES ('Reminder Template', 'urban_housing')");
        $templateId = (int) $this->pdo->lastInsertId();
        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) '
            . 'VALUES (:template, 1, \'Contract\', \'[]\')',
        );
        $version->execute(['template' => $templateId]);
        $versionId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, '
            . 'start_date, end_date, duration_text) '
            . 'VALUES (:candidate, :unit, :version, \'urban_housing\', \'finalized\', 100000, '
            . '\'2026-10-01\', \'2027-09-30\', \'12 months\')',
        );
        $contract->execute(['candidate' => $candidateId, 'unit' => $unitId, 'version' => $versionId]);
        $contractId = (int) $this->pdo->lastInsertId();
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_at) VALUES (:contract, :tenant, \'2026-10-01 12:00:00\')',
        );
        $lease->execute(['contract' => $contractId, 'tenant' => $tenantId]);

        return [
            'lease_id' => (int) $this->pdo->lastInsertId(),
            'tenant_id' => $tenantId,
            'property_id' => $propertyId,
            'candidate_id' => $candidateId,
        ];
    }

    private function insertCharge(int $leaseId, string $type, string $amount, string $dueDate, ?string $voidedAt = null): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO charges (lease_id, type, due_date, amount, voided_at) '
            . 'VALUES (:lease, :type, :due, :amount, :voided)',
        );
        $statement->execute([
            'lease' => $leaseId,
            'type' => $type,
            'due' => $dueDate,
            'amount' => $amount,
            'voided' => $voidedAt,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function fullyPayCharge(array $fixture, int $chargeId, string $amount): void
    {
        $method = $this->pdo->prepare(
            'INSERT INTO payment_methods (property_id, type, label) VALUES (:property, \'cash\', \'Cash\')',
        );
        $method->execute(['property' => $fixture['property_id']]);
        $paymentMethodId = (int) $this->pdo->lastInsertId();
        $payment = $this->pdo->prepare(
            'INSERT INTO payments (lease_id, payment_method_id, amount, paid_on, payment_method_snapshot_json, '
            . 'reconciled_at) VALUES (:lease, :method, :amount, \'2026-10-14\', \'{}\', \'2026-10-14 08:00:00\')',
        );
        $payment->execute(['lease' => $fixture['lease_id'], 'method' => $paymentMethodId, 'amount' => $amount]);
        $allocation = $this->pdo->prepare(
            'INSERT INTO payment_allocations (payment_id, charge_id, amount) VALUES (:payment, :charge, :amount)',
        );
        $allocation->execute(['payment' => (int) $this->pdo->lastInsertId(), 'charge' => $chargeId, 'amount' => $amount]);
    }

    private function insertObligation(int $leaseId, string $dueOn, ?string $settledAt, ?int $chargeId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO lease_obligations (lease_id, type, description, due_on, amount, charge_id, settled_at) '
            . 'VALUES (:lease, \'service\', \'Utility bill\', :due, 10000, :charge, :settled)',
        );
        $statement->execute([
            'lease' => $leaseId,
            'due' => $dueOn,
            'charge' => $chargeId,
            'settled' => $settledAt,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function insertPolicy(
        int $leaseId,
        string $coverageEnd,
        ?string $cancelledAt = null,
        ?int $supersedesPolicyId = null,
    ): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO insurance_policies (lease_id, provider_name, product_name, policy_number, coverage_start, '
            . 'coverage_end, coverage_snapshot_json, documents_json, cancelled_at, supersedes_policy_id) '
            . 'VALUES (:lease, \'Insurer\', \'Home\', :policy, \'2026-01-01\', :coverage_end, \'{}\', \'[]\', '
            . ':cancelled, :supersedes)',
        );
        $statement->execute([
            'lease' => $leaseId,
            'policy' => 'POL-' . bin2hex(random_bytes(4)),
            'coverage_end' => $coverageEnd,
            'cancelled' => $cancelledAt,
            'supersedes' => $supersedesPolicyId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function insertMaintenanceRequest(int $leaseId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO maintenance_requests (lease_id, category, description, photos_json) '
            . 'VALUES (:lease, \'Plumbing\', \'Leaking tap\', \'[]\')',
        );
        $statement->execute(['lease' => $leaseId]);
        return (int) $this->pdo->lastInsertId();
    }

    private function insertVisit(int $requestId, string $selectedStartAt, string $status): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO maintenance_visits (maintenance_request_id, proposed_slots_json, selected_start_at, '
            . 'duration_minutes, status) VALUES (:request, \'[]\', :starts, 60, :status)',
        );
        $statement->execute(['request' => $requestId, 'starts' => $selectedStartAt, 'status' => $status]);
        return (int) $this->pdo->lastInsertId();
    }
}
