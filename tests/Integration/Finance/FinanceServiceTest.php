<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FinanceServiceTest extends TestCase
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
            $safeTable = str_replace('`', '``', (string) $table);
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safeTable));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
    }

    public function testManualChargePersistsNormalizedValuesOnAnEndedLeaseWithoutChangingAuthorities(): void
    {
        $fixture = $this->createLease(ended: true);
        $before = $this->authoritySnapshot();

        $result = $this->service()->createManualCharge(
            $fixture['lease_id'],
            'service',
            '001200000.5',
            '2026-10-14',
            '  Reparación de calentador  ',
            '2026-10-01',
            $fixture['actor_id'],
        );

        self::assertIsInt($result['id']);
        self::assertSame($fixture['lease_id'], $result['lease_id']);
        $charge = $this->charge((int) $result['id']);
        self::assertSame('service', $charge['type']);
        self::assertSame('1200000.50', $charge['amount']);
        self::assertSame('Reparación de calentador', $charge['description']);
        self::assertSame('2026-10-01', $charge['period_month']);
        self::assertSame('2026-10-14', $charge['due_date']);
        self::assertSame($fixture['actor_id'], (int) $charge['created_by_user_id']);
        self::assertSame($before, $this->authoritySnapshot());
    }

    public function testManualTypeAllowlistAcceptsOnlyTheFourApprovedTypes(): void
    {
        $fixture = $this->createLease();
        foreach (['service', 'fine', 'repair', 'other'] as $type) {
            $result = $this->service()->createManualCharge(
                $fixture['lease_id'],
                $type,
                '1',
                '2026-10-14',
                'Concepto ' . $type,
                null,
                $fixture['actor_id'],
            );
            self::assertSame($type, $this->charge((int) $result['id'])['type']);
        }
        self::assertSame(4, $this->chargeCount($fixture['lease_id']));
    }

    public function testManualPathRejectsRentAdministrationAndUnsupportedTypes(): void
    {
        $fixture = $this->createLease();
        foreach (['rent', 'administration', 'membership', 'utility', 'unknown', ''] as $type) {
            $this->assertRejected(fn () => $this->service()->createManualCharge(
                $fixture['lease_id'], $type, '10.00', '2026-10-14', 'Concepto', null, $fixture['actor_id'],
            ));
        }
        self::assertSame(0, $this->chargeCount($fixture['lease_id']));
    }

    public function testMoneyBoundaryNormalizesMaximumAndRejectsInvalidOrOutOfRangeValues(): void
    {
        $fixture = $this->createLease();
        $maximum = $this->service()->createManualCharge(
            $fixture['lease_id'], 'fine', '9999999999.99', '2026-10-14', 'Concepto válido', null, $fixture['actor_id'],
        );
        self::assertSame('9999999999.99', $this->charge((int) $maximum['id'])['amount']);

        foreach (['', '0', '0.00', '-1', '+1', '1.234', '.50', '1.', 'abc', '10000000000.00', '9999999999.999'] as $amount) {
            $this->assertRejected(fn () => $this->service()->createManualCharge(
                $fixture['lease_id'], 'fine', $amount, '2026-10-14', 'Concepto válido', null, $fixture['actor_id'],
            ));
        }
        self::assertSame(1, $this->chargeCount($fixture['lease_id']));
    }

    public function testManualChargeRequiresTrimmedDescriptionAndValidMonthPeriod(): void
    {
        $fixture = $this->createLease();
        foreach ([null, '', '   '] as $description) {
            $this->assertRejected(fn () => $this->service()->createManualCharge(
                $fixture['lease_id'], 'service', '10', '2026-10-14', $description ?? '', null, $fixture['actor_id'],
            ));
        }

        foreach (['2026-10-02', '2026-02-30', '2026-13-01', '2026-2-01', 'not-a-date'] as $period) {
            $this->assertRejected(fn () => $this->service()->createManualCharge(
                $fixture['lease_id'], 'service', '10', '2026-10-14', 'Concepto válido', $period, $fixture['actor_id'],
            ));
        }

        $result = $this->service()->createManualCharge(
            $fixture['lease_id'], 'service', '10', '2026-10-14', '  Concepto válido  ', null, $fixture['actor_id'],
        );
        $charge = $this->charge((int) $result['id']);
        self::assertNull($charge['period_month']);
        self::assertSame('Concepto válido', $charge['description']);
        self::assertSame(1, $this->chargeCount($fixture['lease_id']));
    }

    public function testManualChargeRequiresPositiveIdsAndAnExistingLease(): void
    {
        $fixture = $this->createLease();
        $this->assertRejected(fn () => $this->service()->createManualCharge(
            0, 'service', '10.00', '2026-10-14', 'Concepto válido', null, $fixture['actor_id'],
        ));
        $this->assertRejected(fn () => $this->service()->createManualCharge(
            $fixture['lease_id'], 'service', '10.00', '2026-10-14', 'Concepto válido', null, 0,
        ));
        $this->assertRejected(fn () => $this->service()->createManualCharge(
            PHP_INT_MAX, 'service', '10.00', '2026-10-14', 'Concepto válido', null, $fixture['actor_id'],
        ));
        self::assertSame(0, $this->chargeCount($fixture['lease_id']));
    }

    public function testManualChargeRejectsMalformedDueDates(): void
    {
        $fixture = $this->createLease();
        foreach (['2026-02-30', '2026-13-01', '2026-10-1', 'not-a-date'] as $dueDate) {
            $this->assertRejected(fn () => $this->service()->createManualCharge(
                $fixture['lease_id'], 'service', '10.00', $dueDate, 'Concepto válido', null, $fixture['actor_id'],
            ));
        }
        self::assertSame(0, $this->chargeCount($fixture['lease_id']));
    }

    public function testListForLeaseDerivesStateAndDaysOverdueAndReturnsOnlySafeLeaseProjection(): void
    {
        $fixture = $this->createLease();
        $otherLease = $this->createLease();
        $voided = $this->insertCharge($fixture['lease_id'], 'repair', '2026-09-01', '2026-09-01', '10.00', 'Reparación anulada', '2026-09-03 12:00:00');
        $overdue = $this->insertCharge($fixture['lease_id'], 'fine', null, '2026-10-12', '20.00', 'Multa vencida');
        $dueToday = $this->insertCharge($fixture['lease_id'], 'service', null, '2026-10-13', '30.00', 'Servicio hoy');
        $future = $this->insertCharge($fixture['lease_id'], 'other', null, '2026-10-14', '40.00', 'Otro futuro');
        $this->insertCharge($otherLease['lease_id'], 'fine', null, '2026-10-10', '99.00', 'Otro lease');

        $charges = $this->service()->listForLease($fixture['lease_id'], '2026-10-13');

        self::assertSame([$voided, $overdue, $dueToday, $future], array_column($charges, 'id'));
        self::assertSame([$fixture['contract_id']], array_values(array_unique(array_column($charges, 'contract_id'))));
        self::assertSame(['voided', 'overdue', 'pending', 'pending'], array_column($charges, 'state'));
        self::assertSame([0, 1, 0, 0], array_column($charges, 'days_overdue'));
        self::assertSame([$fixture['lease_id']], array_values(array_unique(array_column($charges, 'lease_id'))));
        foreach ($charges as $charge) {
            foreach ([
                'created_by_user_id',
                'created_at',
                'voided_by_user_id',
                'voided_at',
                'void_reason',
                'status',
                'balance',
            ] as $privateOrDerivedField) {
                self::assertArrayNotHasKey($privateOrDerivedField, $charge);
            }
        }
    }

    public function testVoidChargeStoresActorTimeAndReasonOnceAndPreservesAuthorityRows(): void
    {
        $fixture = $this->createLease();
        $chargeId = $this->insertCharge(
            $fixture['lease_id'], 'fine', null, '2026-10-12', '125000.25', 'Cargo observado',
        );
        $before = $this->authoritySnapshot();
        $now = 1791900000;

        $voided = $this->service()->voidCharge($chargeId, $fixture['actor_id'], '  Duplicado  ', $now);

        self::assertSame($chargeId, $voided['id']);
        self::assertSame($fixture['actor_id'], $voided['voided_by_user_id']);
        self::assertSame(gmdate('Y-m-d H:i:s', $now), $voided['voided_at']);
        self::assertSame('Duplicado', $voided['void_reason']);
        self::assertSame(1, $this->chargeCount($fixture['lease_id']));
        self::assertSame($before, $this->authoritySnapshot());

        $this->assertRejected(fn () => $this->service()->voidCharge(
            $chargeId, $fixture['actor_id'], 'Segundo intento', $now + 60,
        ));

        $stored = $this->charge($chargeId);
        self::assertSame($fixture['actor_id'], (int) $stored['voided_by_user_id']);
        self::assertSame(gmdate('Y-m-d H:i:s', $now), $stored['voided_at']);
        self::assertSame('Duplicado', $stored['void_reason']);
        self::assertSame(1, $this->chargeCount($fixture['lease_id']));
        self::assertSame('voided', $this->service()->listForLease($fixture['lease_id'], '2026-10-13')[0]['state']);
    }

    public function testVoidChargeRejectsBlankReasonAndInvalidChargeOrActor(): void
    {
        $fixture = $this->createLease();
        $chargeId = $this->insertCharge(
            $fixture['lease_id'], 'service', null, '2026-10-20', '50.00', 'Servicio',
        );

        foreach (['', '   '] as $reason) {
            $this->assertRejected(fn () => $this->service()->voidCharge(
                $chargeId, $fixture['actor_id'], $reason, 1791900000,
            ));
        }
        $this->assertRejected(fn () => $this->service()->voidCharge(
            0, $fixture['actor_id'], 'Motivo', 1791900000,
        ));
        $this->assertRejected(fn () => $this->service()->voidCharge(
            $chargeId, 0, 'Motivo', 1791900000,
        ));
        $this->assertRejected(fn () => $this->service()->voidCharge(
            PHP_INT_MAX, $fixture['actor_id'], 'Motivo', 1791900000,
        ));

        self::assertNull($this->charge($chargeId)['voided_at']);
        self::assertSame(1, $this->chargeCount($fixture['lease_id']));
    }

    public function testSummaryForLeaseUsesExactAmountsStrictOverdueBoundaryAndIgnoresVoidedCharges(): void
    {
        $fixture = $this->createLease();
        $empty = $this->createLease();
        $this->insertCharge($fixture['lease_id'], 'rent', null, '2026-10-12', '9999999999.99', 'Renta vencida');
        $this->insertCharge($fixture['lease_id'], 'service', null, '2026-10-13', '0.01', 'Vence hoy');
        $this->insertCharge($fixture['lease_id'], 'repair', null, '2026-10-14', '25.00', 'Vence después');
        $this->insertCharge(
            $fixture['lease_id'], 'fine', null, '2026-10-01', '80.00', 'Anulado', '2026-10-02 12:00:00',
        );

        self::assertSame([
            'outstanding' => '10000000025.00',
            'overdue' => '9999999999.99',
            'oldest_due_date' => '2026-10-12',
        ], $this->service()->summaryForLease($fixture['lease_id'], '2026-10-13'));
        self::assertSame([
            'outstanding' => '0.00',
            'overdue' => '0.00',
            'oldest_due_date' => null,
        ], $this->service()->summaryForLease($empty['lease_id'], '2026-10-13'));

        $columns = $this->pdo->query('SHOW COLUMNS FROM charges')->fetchAll(PDO::FETCH_COLUMN);
        foreach (['status', 'balance', 'outstanding_amount', 'paid_amount', 'remaining_amount'] as $persistedSummary) {
            self::assertNotContains($persistedSummary, $columns);
        }
    }

    public function testPortfolioAggregatesOneCanonicalContextRowPerLeaseWithChargeHistory(): void
    {
        $first = $this->createLease();
        $second = $this->createLease();
        $withoutHistory = $this->createLease();
        $this->insertCharge($first['lease_id'], 'rent', null, '2026-10-12', '100.10', 'Canon vencido');
        $this->insertCharge($first['lease_id'], 'service', null, '2026-10-13', '20.20', 'Servicio hoy');
        $this->insertCharge(
            $first['lease_id'], 'fine', null, '2026-10-01', '500.00', 'Anulado', '2026-10-02 12:00:00',
        );
        $this->insertCharge(
            $second['lease_id'], 'repair', null, '2026-10-01', '75.55', 'Todo anulado', '2026-10-02 12:00:00',
        );

        $portfolio = $this->service()->portfolio('2026-10-13');

        self::assertCount(2, $portfolio);
        $byLease = [];
        foreach ($portfolio as $row) {
            self::assertArrayNotHasKey($row['lease_id'], $byLease);
            $byLease[$row['lease_id']] = $row;
        }
        self::assertArrayNotHasKey($withoutHistory['lease_id'], $byLease);
        self::assertSame([
            'outstanding' => '120.30',
            'overdue' => '100.10',
            'oldest_due_date' => '2026-10-12',
        ], array_intersect_key($byLease[$first['lease_id']], array_flip(['outstanding', 'overdue', 'oldest_due_date'])));
        self::assertSame([
            'outstanding' => '0.00',
            'overdue' => '0.00',
            'oldest_due_date' => null,
        ], array_intersect_key($byLease[$second['lease_id']], array_flip(['outstanding', 'overdue', 'oldest_due_date'])));
        self::assertSame($first['tenant_id'], $byLease[$first['lease_id']]['tenant_id']);
        self::assertSame($second['tenant_id'], $byLease[$second['lease_id']]['tenant_id']);
        self::assertSame($first['unit_id'], $byLease[$first['lease_id']]['unit_id']);
        self::assertSame($second['unit_id'], $byLease[$second['lease_id']]['unit_id']);
        self::assertNotSame($byLease[$first['lease_id']]['property_name'], $byLease[$second['lease_id']]['property_name']);
    }

    public function testMonthlyChargesSnapshotRentAndAdministrationFromTheContractOnAnEndedLease(): void
    {
        $fixture = $this->createLease(ended: true);
        $before = $this->authoritySnapshot();

        $created = $this->service()->createMonthlyCharges(
            $fixture['lease_id'], '2026-10-01', '2026-10-05', $fixture['actor_id'],
        );

        self::assertSame(['rent', 'administration'], array_column($created, 'type'));
        self::assertSame(['1500000.00', '200000.00'], array_column($created, 'amount'));
        self::assertSame(['2026-10-01', '2026-10-01'], array_column($created, 'period_month'));
        self::assertSame(['2026-10-05', '2026-10-05'], array_column($created, 'due_date'));
        self::assertSame($before, $this->authoritySnapshot());

        $updateContract = $this->pdo->prepare(
            'UPDATE contracts SET monthly_rent = :rent, administration_fee = :fee WHERE id = :id',
        );
        $updateContract->execute(['rent' => '3100000.00', 'fee' => '0.00', 'id' => $fixture['contract_id']]);
        $stored = $this->pdo->prepare(
            'SELECT type, amount FROM charges WHERE lease_id = :lease_id ORDER BY type',
        );
        $stored->execute(['lease_id' => $fixture['lease_id']]);
        self::assertSame([
            ['type' => 'administration', 'amount' => '200000.00'],
            ['type' => 'rent', 'amount' => '1500000.00'],
        ], $stored->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testMonthlyPeriodIncludesContractStartAndEndMonthsAndRejectsOutsideOrMalformedValues(): void
    {
        $fixture = $this->createLease();
        $this->service()->createMonthlyCharges($fixture['lease_id'], '2026-10-01', '2026-10-05', $fixture['actor_id']);
        $this->service()->createMonthlyCharges($fixture['lease_id'], '2027-09-01', '2027-09-05', $fixture['actor_id']);
        self::assertSame(4, $this->chargeCount($fixture['lease_id']));

        foreach (['2026-09-01', '2027-10-01', '2026-10-02', '2026-02-30', '2026-2-01'] as $period) {
            $this->assertRejected(fn () => $this->service()->createMonthlyCharges(
                $fixture['lease_id'], $period, '2026-10-05', $fixture['actor_id'],
            ));
        }
        $this->assertRejected(fn () => $this->service()->createMonthlyCharges(
            $fixture['lease_id'], '2026-11-01', '2026-02-30', $fixture['actor_id'],
        ));
        self::assertSame(4, $this->chargeCount($fixture['lease_id']));
    }

    public function testMonthlyGenerationOmitsNullOrZeroAdministrationFee(): void
    {
        $nullFee = $this->createLease();
        $setNull = $this->pdo->prepare('UPDATE contracts SET administration_fee = NULL WHERE id = :id');
        $setNull->execute(['id' => $nullFee['contract_id']]);
        $nullRows = $this->service()->createMonthlyCharges(
            $nullFee['lease_id'], '2026-10-01', '2026-10-05', $nullFee['actor_id'],
        );
        self::assertSame(['rent'], array_column($nullRows, 'type'));

        $zeroFee = $this->createLease();
        $setZero = $this->pdo->prepare('UPDATE contracts SET administration_fee = :fee WHERE id = :id');
        $setZero->execute(['fee' => '0.00', 'id' => $zeroFee['contract_id']]);
        $zeroRows = $this->service()->createMonthlyCharges(
            $zeroFee['lease_id'], '2026-10-01', '2026-10-05', $zeroFee['actor_id'],
        );
        self::assertSame(['rent'], array_column($zeroRows, 'type'));
    }

    public function testMonthlyDuplicateAndPartialCorrectionPreserveValidActiveCharges(): void
    {
        $fixture = $this->createLease();
        $created = $this->service()->createMonthlyCharges(
            $fixture['lease_id'], '2026-10-01', '2026-10-05', $fixture['actor_id'],
        );
        $this->assertRejected(fn () => $this->service()->createMonthlyCharges(
            $fixture['lease_id'], '2026-10-01', '2026-10-05', $fixture['actor_id'],
        ));
        self::assertSame(2, $this->chargeCount($fixture['lease_id']));

        $voidType = $this->pdo->prepare(
            'UPDATE charges SET voided_by_user_id = :actor, voided_at = :voided_at, void_reason = :reason '
            . 'WHERE lease_id = :lease_id AND period_month = :period AND type = :type',
        );
        $voidType->execute([
            'actor' => $fixture['actor_id'],
            'voided_at' => '2026-10-13 12:00:00',
            'reason' => 'Corrección de prueba',
            'lease_id' => $fixture['lease_id'],
            'period' => '2026-10-01',
            'type' => 'administration',
        ]);
        $adminCorrection = $this->service()->createMonthlyCharges(
            $fixture['lease_id'], '2026-10-01', '2026-10-05', $fixture['actor_id'],
        );
        self::assertSame(['administration'], array_column($adminCorrection, 'type'));

        $voidType->execute([
            'actor' => $fixture['actor_id'],
            'voided_at' => '2026-10-13 12:01:00',
            'reason' => 'Corrección de prueba',
            'lease_id' => $fixture['lease_id'],
            'period' => '2026-10-01',
            'type' => 'rent',
        ]);
        $rentCorrection = $this->service()->createMonthlyCharges(
            $fixture['lease_id'], '2026-10-01', '2026-10-05', $fixture['actor_id'],
        );
        self::assertSame(['rent'], array_column($rentCorrection, 'type'));

        $voidBoth = $this->pdo->prepare(
            'UPDATE charges SET voided_by_user_id = :actor, voided_at = :voided_at, void_reason = :reason '
            . 'WHERE lease_id = :lease_id AND period_month = :period AND voided_at IS NULL',
        );
        $voidBoth->execute([
            'actor' => $fixture['actor_id'],
            'voided_at' => '2026-10-13 12:02:00',
            'reason' => 'Corrección de prueba',
            'lease_id' => $fixture['lease_id'],
            'period' => '2026-10-01',
        ]);
        $bothCorrections = $this->service()->createMonthlyCharges(
            $fixture['lease_id'], '2026-10-01', '2026-10-05', $fixture['actor_id'],
        );
        self::assertSame(['rent', 'administration'], array_column($bothCorrections, 'type'));
        self::assertSame(6, $this->chargeCount($fixture['lease_id']));
        self::assertSame(['rent', 'administration'], array_column($created, 'type'));
    }

    public function testMonthlyInsertFailureRollsBackOnlyNewRowsAndPreservesExistingActiveCharge(): void
    {
        $fixture = $this->createLease();
        $rentId = $this->insertCharge(
            $fixture['lease_id'], 'rent', '2026-10-01', '2026-10-05', '1500000.00', 'Canon mensual',
        );
        $this->pdo->exec(
            "CREATE TRIGGER fail_monthly_administration BEFORE INSERT ON charges FOR EACH ROW "
            . "BEGIN IF NEW.type = 'administration' THEN SIGNAL SQLSTATE '45000' "
            . "SET MESSAGE_TEXT = 'forced F9 rollback test'; END IF; END",
        );

        $this->assertRejected(fn () => $this->service()->createMonthlyCharges(
            $fixture['lease_id'], '2026-10-01', '2026-10-05', $fixture['actor_id'],
        ));

        self::assertSame(1, $this->chargeCount($fixture['lease_id']));
        self::assertSame('rent', $this->charge($rentId)['type']);
        self::assertNull($this->charge($rentId)['voided_at']);
    }

    public function testMonthlySecondInsertFailureRollsBackBothNewChargesForThePeriod(): void
    {
        $fixture = $this->createLease();
        $this->pdo->exec(
            "CREATE TRIGGER fail_monthly_administration BEFORE INSERT ON charges FOR EACH ROW "
            . "BEGIN IF NEW.type = 'administration' THEN SET @f9_administration_insert_attempted = 1; "
            . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced F9 rollback test'; END IF; END",
        );

        try {
            $this->assertRejected(fn () => $this->service()->createMonthlyCharges(
                $fixture['lease_id'], '2026-10-01', '2026-10-05', $fixture['actor_id'],
            ));

            self::assertSame(1, (int) $this->pdo->query('SELECT @f9_administration_insert_attempted')->fetchColumn());

            $byType = $this->pdo->prepare(
                'SELECT type, COUNT(*) AS total FROM charges '
                . 'WHERE lease_id = :lease_id AND period_month = :period_month '
                . "AND type IN ('rent', 'administration') GROUP BY type",
            );
            $byType->execute(['lease_id' => $fixture['lease_id'], 'period_month' => '2026-10-01']);
            self::assertSame([], $byType->fetchAll(PDO::FETCH_ASSOC));

            $periodCount = $this->pdo->prepare(
                'SELECT COUNT(*) FROM charges WHERE lease_id = :lease_id AND period_month = :period_month',
            );
            $periodCount->execute(['lease_id' => $fixture['lease_id'], 'period_month' => '2026-10-01']);
            self::assertSame(0, (int) $periodCount->fetchColumn());
        } finally {
            $this->pdo->exec('DROP TRIGGER IF EXISTS fail_monthly_administration');
        }
    }

    public function testMonthlyGenerationLocksLeaseBeforeActiveChargeReadAndInsert(): void
    {
        $fixture = $this->createLease();
        $host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
        $port = getenv('TEST_DB_PORT') ?: '3306';
        $database = getenv('TEST_DB_NAME') ?: 'alquila_test';
        $username = getenv('TEST_DB_USER') ?: 'root';
        $password = getenv('TEST_DB_PASSWORD') ?: 'root';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $pdo = new class($dsn, $username, $password) extends PDO {
            public array $events = [];

            public function __construct(string $dsn, string $username, string $password)
            {
                parent::__construct($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            }

            public function beginTransaction(): bool
            {
                $this->events[] = ['kind' => 'begin'];

                return parent::beginTransaction();
            }

            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                $this->events[] = ['kind' => 'prepare', 'sql' => $query];

                return parent::prepare($query, $options);
            }

            public function commit(): bool
            {
                $this->events[] = ['kind' => 'commit'];

                return parent::commit();
            }
        };

        (new FinanceService(
            $pdo,
            new PaymentMethodService($pdo, sys_get_temp_dir()),
            sys_get_temp_dir(),
        ))->createMonthlyCharges(
            $fixture['lease_id'], '2026-10-01', '2026-10-05', $fixture['actor_id'],
        );

        $eventIndex = static function (array $events, callable $matches): int {
            foreach ($events as $index => $event) {
                if ($matches($event)) {
                    return $index;
                }
            }

            throw new RuntimeException('Expected monthly SQL operation was not observed.');
        };
        $lock = $eventIndex($pdo->events, static fn (array $event): bool =>
            ($event['kind'] ?? '') === 'prepare'
            && str_contains($event['sql'], 'FROM leases')
            && str_contains($event['sql'], 'FOR UPDATE'));
        $contract = $eventIndex($pdo->events, static fn (array $event): bool =>
            ($event['kind'] ?? '') === 'prepare' && str_contains($event['sql'], 'FROM contracts'));
        $activeCharges = $eventIndex($pdo->events, static fn (array $event): bool =>
            ($event['kind'] ?? '') === 'prepare'
            && str_contains($event['sql'], 'FROM charges')
            && str_contains($event['sql'], 'voided_at IS NULL'));
        $insert = $eventIndex($pdo->events, static fn (array $event): bool =>
            ($event['kind'] ?? '') === 'prepare' && str_contains($event['sql'], 'INSERT INTO charges'));
        $commit = $eventIndex($pdo->events, static fn (array $event): bool => ($event['kind'] ?? '') === 'commit');

        self::assertSame('begin', $pdo->events[0]['kind']);
        self::assertLessThan($contract, $lock);
        self::assertLessThan($activeCharges, $contract);
        self::assertLessThan($insert, $activeCharges);
        self::assertLessThan($commit, $insert);
    }

    private function service(): FinanceService
    {
        return new FinanceService(
            $this->pdo,
            new PaymentMethodService($this->pdo, sys_get_temp_dir()),
            sys_get_temp_dir(),
        );
    }

    /** @return array{lease_id:int,contract_id:int,unit_id:int,candidate_id:int,tenant_id:int,actor_id:int} */
    private function createLease(bool $ended = false): array
    {
        $suffix = bin2hex(random_bytes(5));
        $tenantId = $this->insertUser('tenant', 'Tenant ' . $suffix, 'tenant-' . $suffix . '@example.com');
        $actorId = $this->insertUser('admin', 'Finance Admin ' . $suffix, 'finance-' . $suffix . '@example.com');

        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city) VALUES (:name, :address, :city)',
        );
        $property->execute(['name' => 'Property ' . $suffix, 'address' => 'Calle 1', 'city' => 'Bogotá']);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) '
            . 'VALUES (:property_id, :public_key, :type, :code, :status)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'type' => 'apartment',
            'code' => 'APT-' . strtoupper(substr($suffix, 0, 6)),
            'status' => 'occupied',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates '
            . '(unit_id, document_type, document_number, full_name, phone, email, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :status, '
            . ':token_hash, :expires_at, :consent_at)',
        );
        $candidate->execute([
            'unit_id' => $unitId,
            'document_type' => 'cc',
            'document_number' => 'DOC-' . strtoupper($suffix),
            'full_name' => 'Tenant Candidate ' . $suffix,
            'phone' => '3001234567',
            'email' => 'candidate-' . $suffix . '@example.com',
            'status' => 'approved',
            'token_hash' => hash('sha256', 'token-' . $suffix),
            'expires_at' => '2027-01-01 00:00:00',
            'consent_at' => '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type) VALUES (:name, :document_type)',
        );
        $template->execute(['name' => 'Template ' . $suffix, 'document_type' => 'urban_housing']);
        $templateId = (int) $this->pdo->lastInsertId();

        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) '
            . 'VALUES (:template_id, 1, :body_template, :variables_json)',
        );
        $version->execute(['template_id' => $templateId, 'body_template' => 'Contrato', 'variables_json' => '[]']);
        $versionId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts '
            . '(candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, '
            . 'administration_fee, start_date, end_date, duration_text) '
            . 'VALUES (:candidate_id, :unit_id, :version_id, :document_type, :status, :rent, :fee, :start_date, :end_date, :duration)',
        );
        $contract->execute([
            'candidate_id' => $candidateId,
            'unit_id' => $unitId,
            'version_id' => $versionId,
            'document_type' => 'urban_housing',
            'status' => 'finalized',
            'rent' => '1500000.00',
            'fee' => '200000.00',
            'start_date' => '2026-10-01',
            'end_date' => '2027-09-30',
            'duration' => 'Doce meses',
        ]);
        $contractId = (int) $this->pdo->lastInsertId();

        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at, ended_by_user_id, ended_at) '
            . 'VALUES (:contract_id, :user_id, :actor_id, :activated_at, :ended_by, :ended_at)',
        );
        $lease->execute([
            'contract_id' => $contractId,
            'user_id' => $tenantId,
            'actor_id' => $actorId,
            'activated_at' => '2026-10-01 12:00:00',
            'ended_by' => $ended ? $actorId : null,
            'ended_at' => $ended ? '2026-10-10 12:00:00' : null,
        ]);

        return [
            'lease_id' => (int) $this->pdo->lastInsertId(),
            'contract_id' => $contractId,
            'unit_id' => $unitId,
            'candidate_id' => $candidateId,
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
        ];
    }

    private function insertUser(string $role, string $name, string $email): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash) '
            . 'VALUES (:role, :name, :email, :password_hash)',
        );
        $statement->execute([
            'role' => $role,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function charge(int $chargeId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM charges WHERE id = :id');
        $statement->execute(['id' => $chargeId]);
        $charge = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($charge);

        return $charge;
    }

    private function chargeCount(int $leaseId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM charges WHERE lease_id = :lease_id');
        $statement->execute(['lease_id' => $leaseId]);

        return (int) $statement->fetchColumn();
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (RuntimeException) {
            return;
        }

        self::fail('Expected invalid manual charge input to be rejected.');
    }

    private function authoritySnapshot(): array
    {
        $snapshot = [];
        foreach (['users', 'candidates', 'units', 'contracts', 'leases'] as $table) {
            $snapshot[$table] = $this->pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        }

        $snapshot['screening_processes'] = $this->pdo->query('SELECT * FROM screening_processes ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC);

        return $snapshot;
    }

    private function insertCharge(
        int $leaseId,
        string $type,
        ?string $periodMonth,
        string $dueDate,
        string $amount,
        string $description,
        ?string $voidedAt = null,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO charges '
            . '(lease_id, type, period_month, due_date, amount, description, created_by_user_id, '
            . 'voided_by_user_id, voided_at, void_reason) '
            . 'VALUES (:lease_id, :type, :period, :due_date, :amount, :description, :creator, :voider, :voided_at, :reason)',
        );
        $statement->execute([
            'lease_id' => $leaseId,
            'type' => $type,
            'period' => $periodMonth,
            'due_date' => $dueDate,
            'amount' => $amount,
            'description' => $description,
            'creator' => 1,
            'voider' => $voidedAt === null ? null : 1,
            'voided_at' => $voidedAt,
            'reason' => $voidedAt === null ? null : 'Cargado como prueba de anulacion',
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
