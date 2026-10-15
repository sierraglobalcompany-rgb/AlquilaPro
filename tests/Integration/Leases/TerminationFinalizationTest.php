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
use RuntimeException;

final class TerminationFinalizationTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private int $actorId;
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
        $this->directory = sys_get_temp_dir() . '/alquilapro-f21-final-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $fixture = $this->leaseFixture();
        $this->actorId = $fixture['actor_id'];
        $this->leaseId = $fixture['lease_id'];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
    }

    public function testFinalizationContractMethodsExist(): void
    {
        self::assertTrue(
            method_exists(TerminationService::class, 'liquidationForLease'),
            'F21 Task 4 requires TerminationService::liquidationForLease().',
        );
        self::assertTrue(
            method_exists(TerminationService::class, 'finalizeLiquidation'),
            'F21 Task 4 requires TerminationService::finalizeLiquidation().',
        );
    }

    public function testActiveLeaseRejectsWithoutLiquidation(): void
    {
        $this->requireFinalizationMethods();
        self::assertNull($this->service()->liquidationForLease($this->leaseId));

        $this->assertRuntimeFailure(fn () => $this->service()->finalizeLiquidation(
            $this->leaseId,
            ['property_returned' => true, 'keys_returned_count' => 2],
            null,
            $this->actorId,
            '2026-10-14',
            1_800_100_000,
        ));

        self::assertSame(0, $this->liquidationCount($this->leaseId));
    }

    public function testEndedLeaseFinalizesOneImmutableSnapshotWithoutFinancialWrites(): void
    {
        $this->requireFinalizationMethods();
        $service = $this->service();
        $service->configureBonus($this->leaseId, '500.00', 'Bono aprobado', $this->actorId, 1_800_100_010);
        $service->decideBonus($this->leaseId, 'approved', $this->actorId, 1_800_100_011);
        $this->endLease($this->leaseId, 1_800_100_012);

        $chargesBefore = $this->tableCount('charges');
        $paymentsBefore = $this->tableCount('payments');
        $liquidation = $service->finalizeLiquidation(
            $this->leaseId,
            [
                'property_returned' => true,
                'keys_returned_count' => 3,
                'notes' => '  Entrega final confirmada  ',
            ],
            '  Cierre contractual confirmado  ',
            $this->actorId,
            '2026-10-14',
            1_800_100_020,
        );

        self::assertSame($this->leaseId, $liquidation['lease_id']);
        self::assertSame('Cierre contractual confirmado', $liquidation['summary_note']);
        self::assertSame($this->actorId, $liquidation['finalized_by_user_id']);
        self::assertSame(1, $liquidation['snapshot']['schema_version']);
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
            'finalized_context',
        ] as $section) {
            self::assertArrayHasKey($section, $liquidation['snapshot']);
        }
        self::assertSame('Entrega final confirmada', $liquidation['snapshot']['handover']['notes']);
        self::assertSame('approved', $liquidation['snapshot']['bonus']['decision']);
        self::assertSame('500.00', $liquidation['snapshot']['bonus']['amount']);
        self::assertSame($this->actorId, $liquidation['snapshot']['finalized_context']['finalized_by_user_id']);
        self::assertSame($liquidation['finalized_at'], $liquidation['snapshot']['finalized_context']['finalized_at']);
        self::assertSame(1, $this->liquidationCount($this->leaseId));
        self::assertSame($chargesBefore, $this->tableCount('charges'));
        self::assertSame($paymentsBefore, $this->tableCount('payments'));

        $readBack = $service->liquidationForLease($this->leaseId);
        self::assertSame($liquidation, $readBack);
        $frozenSnapshot = $readBack['snapshot'];

        $this->assertRuntimeFailure(fn () => $service->finalizeLiquidation(
            $this->leaseId,
            ['property_returned' => false, 'keys_returned_count' => 0],
            'No debe reemplazar',
            $this->actorId,
            '2026-10-15',
            1_800_100_021,
        ));
        self::assertSame(1, $this->liquidationCount($this->leaseId));
        self::assertSame($frozenSnapshot, $service->liquidationForLease($this->leaseId)['snapshot']);
    }

    public function testCriticalPreviewBlockerRejectsFinalization(): void
    {
        $this->requireFinalizationMethods();
        $service = $this->service();
        $service->configureBonus($this->leaseId, '300.00', 'Pendiente', $this->actorId, 1_800_100_030);
        $this->endLease($this->leaseId, 1_800_100_031);

        $preview = $service->preview($this->leaseId, '2026-10-14');
        self::assertContains('bonus_decision_pending', $preview['blockers']);

        $this->assertRuntimeFailure(fn () => $service->finalizeLiquidation(
            $this->leaseId,
            [],
            null,
            $this->actorId,
            '2026-10-14',
            1_800_100_032,
        ));
        self::assertSame(0, $this->liquidationCount($this->leaseId));
    }

    public function testSummaryNoteIsBoundedTrimmedAndBlankBecomesNull(): void
    {
        $this->requireFinalizationMethods();
        $this->endLease($this->leaseId, 1_800_100_040);

        $this->assertRuntimeFailure(fn () => $this->service()->finalizeLiquidation(
            $this->leaseId,
            [],
            str_repeat('x', 2001),
            $this->actorId,
            '2026-10-14',
            1_800_100_041,
        ));
        self::assertSame(0, $this->liquidationCount($this->leaseId));

        $other = $this->leaseFixture();
        $this->endLease($other['lease_id'], 1_800_100_042, $other['actor_id']);
        $liquidation = $this->service()->finalizeLiquidation(
            $other['lease_id'],
            [],
            '   ',
            $other['actor_id'],
            '2026-10-14',
            1_800_100_043,
        );
        self::assertNull($liquidation['summary_note']);
    }

    public function testDeniedBonusUsesZeroInformationalAmountInSnapshot(): void
    {
        $this->requireFinalizationMethods();
        $service = $this->service();
        $service->configureBonus($this->leaseId, '750.00', null, $this->actorId, 1_800_100_050);
        $service->decideBonus($this->leaseId, 'denied', $this->actorId, 1_800_100_051);
        $this->endLease($this->leaseId, 1_800_100_052);

        $liquidation = $service->finalizeLiquidation(
            $this->leaseId,
            [],
            null,
            $this->actorId,
            '2026-10-14',
            1_800_100_053,
        );
        self::assertSame('denied', $liquidation['snapshot']['bonus']['decision']);
        self::assertSame('0.00', $liquidation['snapshot']['bonus']['amount']);
    }

    private function requireFinalizationMethods(): void
    {
        if (!method_exists(TerminationService::class, 'liquidationForLease')
            || !method_exists(TerminationService::class, 'finalizeLiquidation')
        ) {
            self::markTestSkipped('F21 Task 4 finalization methods are not implemented yet.');
        }
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

    private function leaseService(): LeaseService
    {
        return new LeaseService($this->pdo, new UserService($this->pdo));
    }

    private function endLease(int $leaseId, int $now, ?int $actorId = null): void
    {
        $ended = $this->leaseService()->end($leaseId, $actorId ?? $this->actorId, $now);
        self::assertFalse($ended['is_active']);
    }

    private function assertRuntimeFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected F21 liquidation operation to fail closed.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }

    private function liquidationCount(int $leaseId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM lease_liquidations WHERE lease_id = :lease_id');
        $statement->execute(['lease_id' => $leaseId]);
        return (int) $statement->fetchColumn();
    }

    private function tableCount(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }

    /** @return array{lease_id:int,actor_id:int} */
    private function leaseFixture(): array
    {
        $actorId = $this->insertUser('owner', 'Owner Final F21', 'owner-final-' . bin2hex(random_bytes(3)) . '@example.com');
        $tenantId = $this->insertUser('tenant', 'Tenant Final F21', 'tenant-final-' . bin2hex(random_bytes(3)) . '@example.com');

        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio Final F21 ' . bin2hex(random_bytes(2)),
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
            'code' => 'F21-FINAL-' . bin2hex(random_bytes(3)),
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type, is_active, created_by_user_id) VALUES (:name, \'urban_housing\', 1, :actor)',
        );
        $template->execute(['name' => 'Template Final F21 ' . bin2hex(random_bytes(3)), 'actor' => $actorId]);
        $templateId = (int) $this->pdo->lastInsertId();

        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json, created_by_user_id) '
            . 'VALUES (:template, 1, \'Contrato Final F21\', \'[]\', :actor)',
        );
        $version->execute(['template' => $templateId, 'actor' => $actorId]);
        $versionId = (int) $this->pdo->lastInsertId();

        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, current_address, status, continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit, \'cc\', :document, \'Tenant Final F21\', \'3001112233\', :email, \'Carrera 10 # 11-12\', \'pending_screening\', :token, \'2027-01-01 00:00:00\', \'2026-10-14 10:00:00\')',
        );
        $candidate->execute([
            'unit' => $unitId,
            'document' => 'F21-F-' . bin2hex(random_bytes(4)),
            'email' => 'candidate-final-' . bin2hex(random_bytes(3)) . '@example.com',
            'token' => bin2hex(random_bytes(32)),
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, start_date, end_date, duration_text, resolved_variables_json, snapshot_html, pdf_stored_name, pdf_sha256, pdf_size_bytes, created_by_user_id, finalized_by_user_id, generated_at, finalized_at) '
            . 'VALUES (:candidate, :unit, :version, \'urban_housing\', \'finalized\', \'1750000.00\', \'250000.00\', \'2026-11-01\', \'2027-10-31\', \'Doce meses\', \'{}\', \'<article>F21 Final</article>\', :pdf_name, :pdf_hash, 1234, :created_actor, :finalized_actor, \'2026-10-14 10:10:00\', \'2026-10-14 10:20:00\')',
        );
        $contract->execute([
            'candidate' => $candidateId,
            'unit' => $unitId,
            'version' => $versionId,
            'pdf_name' => str_repeat('e', 32) . '.pdf',
            'pdf_hash' => str_repeat('f', 64),
            'created_actor' => $actorId,
            'finalized_actor' => $actorId,
        ]);
        $contractId = (int) $this->pdo->lastInsertId();

        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) '
            . 'VALUES (:contract, :tenant, :actor, \'2026-10-14 11:00:00\')',
        );
        $lease->execute(['contract' => $contractId, 'tenant' => $tenantId, 'actor' => $actorId]);

        return ['lease_id' => (int) $this->pdo->lastInsertId(), 'actor_id' => $actorId];
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
