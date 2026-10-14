<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Memberships;

use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Memberships\MembershipService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MembershipServiceTest extends TestCase
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
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', str_replace('`', '``', (string) $table)));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $user = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (\'owner\', \'Membership Actor\', :email, :hash, 1)',
        );
        $user->execute([
            'email' => 'membership-actor-' . bin2hex(random_bytes(5)) . '@example.test',
            'hash' => password_hash('MembershipPassword123!', PASSWORD_DEFAULT),
        ]);
        $this->actorId = (int) $this->pdo->lastInsertId();
    }

    public function testCatalogCreatesFindsListsAndUpdatesWithoutDeletingHistory(): void
    {
        $service = $this->service();
        $first = $service->createService(
            '  Asistencia digital  ', '  Soporte complementario  ', '00120.5', 30, $this->actorId, 1_800_000_000,
        );

        self::assertSame('Asistencia digital', $first['name']);
        self::assertSame('Soporte complementario', $first['description']);
        self::assertSame('120.50', $first['current_price']);
        self::assertSame(30, $first['duration_days']);
        self::assertTrue($first['active']);
        self::assertSame($this->actorId, $first['created_by_user_id']);
        self::assertSame(gmdate('Y-m-d H:i:s', 1_800_000_000), $first['created_at']);
        self::assertSame($first, $service->findService((int) $first['id']));
        self::assertSame([$first['id']], array_column($service->listServices(), 'id'));
        self::assertSame([$first['id']], array_column($service->listServices(true), 'id'));

        $updated = $service->updateService(
            (int) $first['id'], 'Asistencia Plus', 'Soporte ampliado', '250', 60, false, $this->actorId, 1_800_000_010,
        );
        self::assertSame('250.00', $updated['current_price']);
        self::assertSame(60, $updated['duration_days']);
        self::assertFalse($updated['active']);
        self::assertSame('2027-01-15 08:00:10', $updated['updated_at']);
        self::assertSame([], $service->listServices(true));
        self::assertSame([$first['id']], array_column($service->listServices(), 'id'));
        self::assertNull($service->findService(0));
        self::assertNull($service->findService(PHP_INT_MAX));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM digital_services')->fetchColumn());
    }

    public function testCatalogAcceptsFreeAndMaximumPriceAndRejectsInvalidInput(): void
    {
        $service = $this->service();
        $free = $service->createService('Gratis', 'Servicio sin costo', '0', 1, $this->actorId, 1_800_000_000);
        self::assertSame('0.00', $free['current_price']);
        $maximum = $service->createService(
            'Premium', 'Servicio premium', '9999999999.99', 365, $this->actorId, 1_800_000_001,
        );
        self::assertSame('9999999999.99', $maximum['current_price']);

        foreach ([
            ['', 'Descripción válida', '1', 30],
            ['   ', 'Descripción válida', '1', 30],
            [str_repeat('n', 121), 'Descripción válida', '1', 30],
            ['Servicio', '', '1', 30],
            ['Servicio', str_repeat('d', 1001), '1', 30],
            ['Servicio', 'Descripción válida', '-1', 30],
            ['Servicio', 'Descripción válida', '1.234', 30],
            ['Servicio', 'Descripción válida', '10000000000.00', 30],
            ['Servicio', 'Descripción válida', '1e2', 30],
            ['Servicio', 'Descripción válida', '1', 0],
            ['Servicio', 'Descripción válida', '1', 4_294_967_296],
        ] as [$name, $description, $price, $days]) {
            $this->assertRejected(fn () => $service->createService(
                $name, $description, $price, $days, $this->actorId, 1_800_000_002,
            ));
        }
        $this->assertRejected(fn () => $service->createService(
            'Servicio', 'Descripción válida', '1', 30, 0, 1_800_000_003,
        ));
        $this->assertRejected(fn () => $service->updateService(
            PHP_INT_MAX, 'Servicio', 'Descripción válida', '1', 30, true, $this->actorId, 1_800_000_004,
        ));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM digital_services')->fetchColumn());
    }

    public function testAcceptanceSnapshotsTermsAndSeparatesPaidDecisionFromFreeService(): void
    {
        $service = $this->service();
        [$tenantId, $leaseId] = $this->createTenantLease();
        $paidService = $service->createService(
            'Asistencia', 'Soporte digital', '45.50', 10, $this->actorId, 1_800_000_000,
        );
        $freeService = $service->createService(
            'Guía', 'Orientación digital', '0', 4, $this->actorId, 1_800_000_001,
        );
        $chargeCount = (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn();

        $paid = $this->acceptWithCurrentOffer($service, (int) $paidService['id'], $leaseId, $tenantId, '2026-10-14', 1_800_000_010);
        $free = $this->acceptWithCurrentOffer($service, (int) $freeService['id'], $leaseId, $tenantId, '2026-10-14', 1_800_000_011);

        self::assertSame('45.50', $paid['price_snapshot']);
        self::assertSame(10, $paid['duration_days_snapshot']);
        self::assertSame('2026-10-14', $paid['starts_on']);
        self::assertSame('2026-10-23', $paid['ends_on']);
        self::assertSame('pending', $paid['charge_outcome']);
        self::assertTrue($paid['charge_decision_required']);
        self::assertSame('0.00', $free['price_snapshot']);
        self::assertSame('not_required', $free['charge_outcome']);
        self::assertFalse($free['charge_decision_required']);
        self::assertNull($free['charge_decision']);
        self::assertNull($free['charge_id']);
        self::assertSame($chargeCount, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
        self::assertSame([$free['id'], $paid['id']], array_column($service->listForLease($leaseId), 'id'));
        self::assertSame([$paid['id']], array_column($service->listPendingChargeDecisions(), 'id'));

        $service->updateService(
            (int) $paidService['id'], 'Asistencia Plus', 'Soporte ampliado', '90', 30, true, $this->actorId, 1_800_000_020,
        );
        self::assertSame('45.50', $service->findMembership((int) $paid['id'])['price_snapshot']);
        self::assertSame(10, $service->findMembership((int) $paid['id'])['duration_days_snapshot']);
        $this->assertRejected(fn () => $this->acceptWithCurrentOffer($service,
            (int) $paidService['id'], $leaseId, $this->actorId, '2026-10-14', 1_800_000_021,
        ));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM service_memberships')->fetchColumn());
    }

    public function testRenewalCreatesANewContiguousSnapshotAndCannotBeRepeatedForTheSameSource(): void
    {
        $service = $this->service();
        [$tenantId, $leaseId] = $this->createTenantLease();
        $catalogService = $service->createService(
            'Almacenamiento', 'Espacio adicional', '12.00', 3, $this->actorId, 1_800_000_000,
        );
        $first = $this->acceptWithCurrentOffer($service, (int) $catalogService['id'], $leaseId, $tenantId, '2026-10-14', 1_800_000_010);
        $service->updateService(
            (int) $catalogService['id'], 'Almacenamiento Plus', 'Más espacio', '20.00', 5, true, $this->actorId, 1_800_000_020,
        );

        $renewed = $this->renewWithCurrentOffer($service, (int) $first['id'], $tenantId, '2026-10-14', 1_800_000_030);

        self::assertNotSame($first['id'], $renewed['id']);
        self::assertSame($first['id'], $renewed['renews_membership_id']);
        self::assertSame('12.00', $service->findMembership((int) $first['id'])['price_snapshot']);
        self::assertSame('2026-10-17', $renewed['starts_on']);
        self::assertSame('2026-10-21', $renewed['ends_on']);
        self::assertSame('20.00', $renewed['price_snapshot']);
        self::assertSame(5, $renewed['duration_days_snapshot']);
        $this->assertRejected(fn () => $this->renewWithCurrentOffer($service, (int) $first['id'], $tenantId, '2026-10-14', 1_800_000_031));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM service_memberships')->fetchColumn());
    }

    public function testEndingMembershipPreservesSnapshotAndReleasesItsDateRange(): void
    {
        $service = $this->service();
        [$tenantId, $leaseId] = $this->createTenantLease();
        $catalog = $service->createService('Acceso', 'Acceso digital', '5.00', 30, $this->actorId, 1_800_000_000);
        $membership = $this->acceptWithCurrentOffer($service, (int) $catalog['id'], $leaseId, $tenantId, '2026-10-14', 1_800_000_010);

        $ended = $service->end((int) $membership['id'], $this->actorId, 1_800_000_020);
        self::assertSame('2027-01-15 08:00:20', $ended['ended_at']);
        self::assertSame($membership['price_snapshot'], $ended['price_snapshot']);
        self::assertSame($membership['starts_on'], $ended['starts_on']);
        $this->assertRejected(fn () => $service->end((int) $membership['id'], $this->actorId, 1_800_000_021));

        $replacement = $this->acceptWithCurrentOffer($service, (int) $catalog['id'], $leaseId, $tenantId, '2026-10-14', 1_800_000_030);
        self::assertNotSame($membership['id'], $replacement['id']);
        self::assertNull($replacement['ended_at']);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM service_memberships')->fetchColumn());
    }

    public function testAcceptanceRejectsOverlappingMembershipAndInvalidDate(): void
    {
        $service = $this->service();
        [$tenantId, $leaseId] = $this->createTenantLease();
        $catalog = $service->createService('Protección', 'Soporte', '5.00', 30, $this->actorId, 1_800_000_000);
        $this->acceptWithCurrentOffer($service, (int) $catalog['id'], $leaseId, $tenantId, '2026-10-14', 1_800_000_010);

        $this->assertRejected(fn () => $this->acceptWithCurrentOffer($service,
            (int) $catalog['id'], $leaseId, $tenantId, '2026-10-20', 1_800_000_020,
        ));
        $this->assertRejected(fn () => $this->acceptWithCurrentOffer($service,
            (int) $catalog['id'], $leaseId, $tenantId, '2026-02-30', 1_800_000_030,
        ));
        $this->assertRejected(fn () => $this->acceptWithCurrentOffer($service,
            (int) $catalog['id'], $leaseId, $tenantId, '0999-12-31', 1_800_000_031,
        ));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM service_memberships')->fetchColumn());
    }

    public function testCreateDecisionUsesSnapshotThroughFinanceServiceAndIsTerminal(): void
    {
        $service = $this->service();
        [$tenantId, $leaseId] = $this->createTenantLease();
        $catalog = $service->createService('Asistencia', 'Soporte digital', '30.25', 10, $this->actorId, 1_800_000_000);
        $membership = $this->acceptWithCurrentOffer($service, (int) $catalog['id'], $leaseId, $tenantId, '2026-10-14', 1_800_000_010);
        $service->updateService((int) $catalog['id'], 'Asistencia Plus', 'Soporte ampliado', '90', 30, true, $this->actorId, 1_800_000_020);

        $decided = $service->decideCharge(
            (int) $membership['id'], 'create', '2026-10-20', ' Servicio digital ', $this->actorId, 1_800_000_030,
        );

        self::assertSame('create', $decided['charge_decision']);
        self::assertSame('created', $decided['charge_outcome']);
        self::assertFalse($decided['charge_decision_required']);
        self::assertSame($this->actorId, $decided['charge_decided_by_user_id']);
        self::assertSame('2027-01-15 08:00:30', $decided['charge_decided_at']);
        self::assertNotNull($decided['charge_id']);
        $charge = $this->pdo->query('SELECT * FROM charges WHERE id = ' . (int) $decided['charge_id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame('service', $charge['type']);
        self::assertSame('30.25', $charge['amount']);
        self::assertSame('2026-10-20', $charge['due_date']);
        self::assertSame('Servicio digital', $charge['description']);
        self::assertSame($leaseId, (int) $charge['lease_id']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
        $this->assertRejected(fn () => $service->decideCharge(
            (int) $membership['id'], 'create', '2026-10-20', 'Duplicado', $this->actorId, 1_800_000_031,
        ));
        $this->assertRejected(fn () => $service->decideCharge(
            (int) $membership['id'], 'skip', null, null, $this->actorId, 1_800_000_032,
        ));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
    }

    public function testSkipDecisionAndFreeMembershipNeverCreateCharges(): void
    {
        $service = $this->service();
        [$tenantId, $leaseId] = $this->createTenantLease();
        $paidCatalog = $service->createService('Asistencia', 'Soporte digital', '15', 10, $this->actorId, 1_800_000_000);
        $freeCatalog = $service->createService('Guía', 'Guía básica', '0', 10, $this->actorId, 1_800_000_001);
        $paid = $this->acceptWithCurrentOffer($service, (int) $paidCatalog['id'], $leaseId, $tenantId, '2026-10-14', 1_800_000_010);
        $free = $this->acceptWithCurrentOffer($service, (int) $freeCatalog['id'], $leaseId, $tenantId, '2026-10-14', 1_800_000_011);

        $this->assertRejected(fn () => $service->decideCharge(
            (int) $free['id'], 'skip', null, null, $this->actorId, 1_800_000_020,
        ));
        $skipped = $service->decideCharge((int) $paid['id'], 'skip', null, null, $this->actorId, 1_800_000_030);

        self::assertSame('skip', $skipped['charge_decision']);
        self::assertSame('skipped', $skipped['charge_outcome']);
        self::assertNull($skipped['charge_id']);
        self::assertFalse($skipped['charge_decision_required']);
        self::assertSame([], $service->listPendingChargeDecisions());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
        $this->assertRejected(fn () => $service->decideCharge(
            (int) $paid['id'], 'skip', null, null, $this->actorId, 1_800_000_031,
        ));
    }

    public function testCreateDecisionValidatesDueDateAndDescriptionWithoutChangingPendingState(): void
    {
        $service = $this->service();
        [$tenantId, $leaseId] = $this->createTenantLease();
        $catalog = $service->createService('Asistencia', 'Soporte digital', '15', 10, $this->actorId, 1_800_000_000);
        $membership = $this->acceptWithCurrentOffer($service, (int) $catalog['id'], $leaseId, $tenantId, '2026-10-14', 1_800_000_010);

        foreach ([
            [null, 'Servicio'],
            ['2026-02-30', 'Servicio'],
            ['2026-10-20', null],
            ['2026-10-20', '  '],
            ['2026-10-20', str_repeat('x', 501)],
        ] as [$dueDate, $description]) {
            $this->assertRejected(fn () => $service->decideCharge(
                (int) $membership['id'], 'create', $dueDate, $description, $this->actorId, 1_800_000_020,
            ));
        }
        self::assertSame('pending', $service->findMembership((int) $membership['id'])['charge_outcome']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
    }

    public function testCreateDecisionRollsBackFinanceChargeWhenMembershipLinkFails(): void
    {
        $service = $this->service();
        [$tenantId, $leaseId] = $this->createTenantLease();
        $catalog = $service->createService('Asistencia', 'Soporte digital', '15', 10, $this->actorId, 1_800_000_000);
        $membership = $this->acceptWithCurrentOffer($service, (int) $catalog['id'], $leaseId, $tenantId, '2026-10-14', 1_800_000_010);
        $this->pdo->exec(
            "CREATE TRIGGER membership_force_decision_failure BEFORE UPDATE ON service_memberships "
            . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced F20 link failure'",
        );

        try {
            $service->decideCharge(
                (int) $membership['id'], 'create', '2026-10-20', 'Servicio digital', $this->actorId, 1_800_000_020,
            );
            self::fail('Expected the membership link to fail.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        } catch (\PDOException) {
            self::assertTrue(true);
        }

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
        self::assertNull($service->findMembership((int) $membership['id'])['charge_decision']);
        self::assertNull($service->findMembership((int) $membership['id'])['charge_id']);
    }

    private function acceptWithCurrentOffer(
        MembershipService $service,
        int $serviceId,
        int $leaseId,
        int $tenantId,
        string $today,
        int $now,
    ): array {
        $offer = $service->findService($serviceId);
        self::assertIsArray($offer);
        return $service->accept(
            $serviceId,
            $leaseId,
            $tenantId,
            (string) $offer['current_price'],
            (int) $offer['duration_days'],
            $today,
            $now,
        );
    }

    private function renewWithCurrentOffer(
        MembershipService $service,
        int $membershipId,
        int $tenantId,
        string $today,
        int $now,
    ): array {
        $membership = $service->findMembership($membershipId);
        self::assertIsArray($membership);
        $offer = $service->findService((int) $membership['service_id']);
        self::assertIsArray($offer);
        return $service->renew(
            $membershipId,
            $tenantId,
            (string) $offer['current_price'],
            (int) $offer['duration_days'],
            $today,
            $now,
        );
    }

    private function service(): MembershipService
    {
        return new MembershipService(
            $this->pdo,
            new FinanceService($this->pdo, new PaymentMethodService($this->pdo, sys_get_temp_dir()), sys_get_temp_dir()),
        );
    }

    /** @return array{int,int} tenant id, lease id */
    private function createTenantLease(): array
    {
        $suffix = bin2hex(random_bytes(5));
        $tenant = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (\'tenant\', \'Membership Tenant\', :email, :hash, 1)',
        );
        $tenant->execute([
            'email' => 'membership-tenant-' . $suffix . '@example.test',
            'hash' => password_hash('MembershipTenant123!', PASSWORD_DEFAULT),
        ]);
        $tenantId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_at) VALUES (:contract, :tenant, \'2026-10-01 12:00:00\')',
        );
        $lease->execute(['contract' => random_int(1_000_000, 2_000_000_000), 'tenant' => $tenantId]);
        $leaseId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        return [$tenantId, $leaseId];
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected invalid catalog input to be rejected.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }
}
