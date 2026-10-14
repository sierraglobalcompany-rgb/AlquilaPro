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
use Slim\Psr7\UploadedFile;

final class PaymentReconciliationTest extends TestCase
{
    private PDO $pdo;
    private string $receiptDirectory;
    private array $temporaryFiles = [];

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
        $this->receiptDirectory = sys_get_temp_dir() . '/alquilapro-reconcile-receipts-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        foreach (glob($this->receiptDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }
        if (is_dir($this->receiptDirectory)) {
            @rmdir($this->receiptDirectory);
        }
    }

    public function testReconcileAndRejectAreTerminalAndPreserveFirstActorTimeAndReason(): void
    {
        $fixture = $this->createLease();
        $chargeId = $this->insertCharge($fixture['lease_id'], '100.00');
        $first = $this->report($fixture, $chargeId, '20.00');
        $reconciledAt = $this->databaseDateTime($this->now());

        $reconciled = $this->service()->reconcilePayment(
            (int) $first['id'], [['charge_id' => $chargeId, 'amount' => '20']], $fixture['actor_id'], $this->now(),
        );
        self::assertSame('reconciled', $reconciled['state']);
        $firstSnapshot = $this->payment((int) $first['id']);
        self::assertSame($fixture['actor_id'], (int) $firstSnapshot['reconciled_by_user_id']);
        self::assertSame($reconciledAt, $firstSnapshot['reconciled_at']);

        $this->assertRejected(fn () => $this->service()->rejectPayment(
            (int) $first['id'], 'Rechazo tardío', $fixture['other_actor_id'], $this->now() + 60,
        ));
        $this->assertRejected(fn () => $this->service()->reconcilePayment(
            (int) $first['id'], [['charge_id' => $chargeId, 'amount' => '20']],
            $fixture['other_actor_id'], $this->now() + 120,
        ));
        self::assertSame($firstSnapshot, $this->payment((int) $first['id']));

        $second = $this->report($fixture, $chargeId, '10.00', $this->upload($this->pngBytes(), 'proof.png', 'image/png'));
        $rejectedAt = $this->databaseDateTime($this->now() + 180);
        $this->assertRejected(fn () => $this->service()->rejectPayment(
            (int) $second['id'], '   ', $fixture['other_actor_id'], $this->now() + 180,
        ));
        self::assertSame('pending_reconciliation', $this->service()->findPayment((int) $second['id'])['state']);
        $rejected = $this->service()->rejectPayment(
            (int) $second['id'], '  El soporte no coincide.  ', $fixture['other_actor_id'], $this->now() + 180,
        );
        self::assertSame('rejected', $rejected['state']);
        $secondSnapshot = $this->payment((int) $second['id']);
        self::assertSame($fixture['other_actor_id'], (int) $secondSnapshot['rejected_by_user_id']);
        self::assertSame($rejectedAt, $secondSnapshot['rejected_at']);
        self::assertSame('El soporte no coincide.', $secondSnapshot['rejection_reason']);
        self::assertNotNull($this->service()->privatePaymentReceipt((int) $second['id']));

        $this->assertRejected(fn () => $this->service()->reconcilePayment(
            (int) $second['id'], [['charge_id' => $chargeId, 'amount' => '10']], $fixture['actor_id'], $this->now() + 240,
        ));
        $this->assertRejected(fn () => $this->service()->rejectPayment(
            (int) $second['id'], 'Segundo rechazo', $fixture['actor_id'], $this->now() + 300,
        ));
        self::assertSame($secondSnapshot, $this->payment((int) $second['id']));
        self::assertSame('80.00', $this->service()->listForLease($fixture['lease_id'], '2026-10-13')[0]['outstanding']);
    }

    public function testFinalAllocationsAreValidatedAtomicallyAndMayReplaceTenantProposal(): void
    {
        $fixture = $this->createLease();
        $other = $this->createLease();
        $firstCharge = $this->insertCharge($fixture['lease_id'], '100.00');
        $secondCharge = $this->insertCharge($fixture['lease_id'], '100.00');
        $otherCharge = $this->insertCharge($other['lease_id'], '100.00');
        $voidedCharge = $this->insertCharge($fixture['lease_id'], '100.00', true);
        $payment = $this->report($fixture, $firstCharge, '40.00');
        $original = $this->payment((int) $payment['id']);
        $originalAllocations = $this->allocations((int) $payment['id']);
        $invalidSets = [
            [['charge_id' => $otherCharge, 'amount' => '40']],
            [['charge_id' => $voidedCharge, 'amount' => '40']],
            [['charge_id' => $firstCharge, 'amount' => '100.01']],
            [['charge_id' => $firstCharge, 'amount' => '20'], ['charge_id' => $firstCharge, 'amount' => '20']],
            [['charge_id' => $firstCharge, 'amount' => '0']],
            [['charge_id' => $firstCharge, 'amount' => '39']],
        ];

        foreach ($invalidSets as $allocationSet) {
            $this->assertRejected(fn () => $this->service()->reconcilePayment(
                (int) $payment['id'], $allocationSet, $fixture['actor_id'], $this->now(),
            ));
            self::assertSame($original, $this->payment((int) $payment['id']));
            self::assertSame($originalAllocations, $this->allocations((int) $payment['id']));
        }

        $this->pdo->prepare('UPDATE payment_methods SET is_active = 0 WHERE id = ?')
            ->execute([(int) $original['payment_method_id']]);

        $reconciled = $this->service()->reconcilePayment(
            (int) $payment['id'], [
                ['charge_id' => $firstCharge, 'amount' => '15'],
                ['charge_id' => $secondCharge, 'amount' => '25'],
            ], $fixture['actor_id'], $this->now(),
        );
        self::assertSame('reconciled', $reconciled['state']);
        self::assertSame([
            ['charge_id' => $firstCharge, 'amount' => '15.00'],
            ['charge_id' => $secondCharge, 'amount' => '25.00'],
        ], $this->allocations((int) $payment['id']));

        $after = $this->payment((int) $payment['id']);
        foreach ([
            'lease_id', 'payment_method_id', 'amount', 'paid_on', 'paid_time',
            'payment_method_snapshot_json', 'receipt_stored_name', 'receipt_mime_type',
            'receipt_sha256', 'receipt_size_bytes',
        ] as $immutable) {
            self::assertSame($original[$immutable], $after[$immutable], $immutable);
        }
    }

    public function testOnlyReconciledAllocationsChangeSharedChargeAndPortfolioProjections(): void
    {
        $fixture = $this->createLease();
        $overdueCharge = $this->insertCharge($fixture['lease_id'], '100.00', false, '2026-10-12');
        $futureCharge = $this->insertCharge($fixture['lease_id'], '50.00', false, '2026-10-20');
        $pending = $this->report($fixture, $overdueCharge, '30.00');
        $proof = $this->report(
            $fixture,
            $futureCharge,
            '10.00',
            $this->upload($this->pngBytes(), 'proof.png', 'image/png'),
        );

        $charges = $this->chargesById($fixture['lease_id']);
        self::assertSame('reported', $charges[$overdueCharge]['state']);
        self::assertSame('100.00', $charges[$overdueCharge]['outstanding']);
        self::assertTrue($charges[$overdueCharge]['is_overdue']);
        self::assertSame(1, $charges[$overdueCharge]['days_overdue']);
        self::assertSame('proof_received', $charges[$futureCharge]['state']);
        self::assertSame('50.00', $charges[$futureCharge]['outstanding']);
        self::assertSame('150.00', $this->service()->summaryForLease($fixture['lease_id'], '2026-10-13')['outstanding']);

        $this->service()->rejectPayment((int) $pending['id'], 'No coincide', $fixture['actor_id'], $this->now());
        self::assertSame('overdue', $this->chargesById($fixture['lease_id'])[$overdueCharge]['state']);

        $partial = $this->report($fixture, $overdueCharge, '25.00');
        $this->service()->reconcilePayment(
            (int) $partial['id'], [['charge_id' => $overdueCharge, 'amount' => '25']],
            $fixture['actor_id'], $this->now(),
        );
        $charges = $this->chargesById($fixture['lease_id']);
        self::assertSame('partial', $charges[$overdueCharge]['state']);
        self::assertSame('25.00', $charges[$overdueCharge]['allocated']);
        self::assertSame('75.00', $charges[$overdueCharge]['outstanding']);
        self::assertTrue($charges[$overdueCharge]['is_overdue']);
        self::assertSame(1, $charges[$overdueCharge]['days_overdue']);
        self::assertSame([
            'outstanding' => '125.00',
            'overdue' => '75.00',
            'oldest_due_date' => '2026-10-12',
        ], $this->service()->summaryForLease($fixture['lease_id'], '2026-10-13'));
        self::assertSame('125.00', $this->portfolioRow($fixture['lease_id'])['outstanding']);

        $final = $this->report($fixture, $overdueCharge, '75.00');
        $this->service()->reconcilePayment(
            (int) $final['id'], [['charge_id' => $overdueCharge, 'amount' => '75']],
            $fixture['actor_id'], $this->now(),
        );
        $paid = $this->chargesById($fixture['lease_id'])[$overdueCharge];
        self::assertSame('paid', $paid['state']);
        self::assertSame('0.00', $paid['outstanding']);
        self::assertFalse($paid['is_overdue']);
        self::assertSame(0, $paid['days_overdue']);
        self::assertSame('50.00', $this->service()->summaryForLease($fixture['lease_id'], '2026-10-13')['outstanding']);
        self::assertSame('proof_received', $this->chargesById($fixture['lease_id'])[$futureCharge]['state']);
        self::assertSame('pending_reconciliation', $proof['state']);
    }

    public function testReconciliationLocksPaymentThenChargesInDeterministicOrderBeforeRecomputing(): void
    {
        $fixture = $this->createLease();
        $firstCharge = $this->insertCharge($fixture['lease_id'], '100.00');
        $secondCharge = $this->insertCharge($fixture['lease_id'], '100.00');
        $payment = $this->report($fixture, $firstCharge, '20.00');
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
        $service = new FinanceService($pdo, new PaymentMethodService($pdo, sys_get_temp_dir()));
        $service->reconcilePayment(
            (int) $payment['id'], [
                ['charge_id' => $secondCharge, 'amount' => '10'],
                ['charge_id' => $firstCharge, 'amount' => '10'],
            ], $fixture['actor_id'], $this->now(),
        );
        $index = static function (array $events, callable $matches): int {
            foreach ($events as $position => $event) {
                if ($matches($event)) {
                    return $position;
                }
            }
            throw new RuntimeException('Expected reconciliation SQL operation was not observed.');
        };
        $paymentLock = $index($pdo->events, static fn (array $event): bool =>
            ($event['kind'] ?? '') === 'prepare' && str_contains($event['sql'], 'FROM payments')
            && str_contains($event['sql'], 'FOR UPDATE'));
        $chargeLock = $index($pdo->events, static fn (array $event): bool =>
            ($event['kind'] ?? '') === 'prepare' && str_contains($event['sql'], 'FROM charges')
            && str_contains($event['sql'], 'FOR UPDATE'));
        $outstanding = $index($pdo->events, static fn (array $event): bool =>
            ($event['kind'] ?? '') === 'prepare' && str_contains($event['sql'], 'payment_allocations'));
        $allocationInsert = $index($pdo->events, static fn (array $event): bool =>
            ($event['kind'] ?? '') === 'prepare' && str_contains($event['sql'], 'INSERT INTO payment_allocations'));
        $terminalUpdate = $index($pdo->events, static fn (array $event): bool =>
            ($event['kind'] ?? '') === 'prepare' && str_contains($event['sql'], 'UPDATE payments'));
        $commit = $index($pdo->events, static fn (array $event): bool => ($event['kind'] ?? '') === 'commit');

        self::assertSame('begin', $pdo->events[0]['kind']);
        self::assertLessThan($chargeLock, $paymentLock);
        self::assertLessThan($outstanding, $chargeLock);
        self::assertLessThan($allocationInsert, $outstanding);
        self::assertLessThan($terminalUpdate, $allocationInsert);
        self::assertLessThan($commit, $terminalUpdate);
    }

    public function testConcurrentReconciliationsCannotOverAllocateAndReconcileRejectHasOneWinner(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('The local PHP runtime does not support subprocess concurrency tests.');
        }
        $fixture = $this->createLease();
        $chargeId = $this->insertCharge($fixture['lease_id'], '100.00');
        $first = $this->report($fixture, $chargeId, '100.00');
        $second = $this->report($fixture, $chargeId, '100.00');
        $sameChargeResults = $this->runWorkers([
            ['action' => 'reconcile', 'payment_id' => (int) $first['id'], 'allocations' => [['charge_id' => $chargeId, 'amount' => '100']], 'actor_id' => $fixture['actor_id']],
            ['action' => 'reconcile', 'payment_id' => (int) $second['id'], 'allocations' => [['charge_id' => $chargeId, 'amount' => '100']], 'actor_id' => $fixture['other_actor_id']],
        ]);
        self::assertSame(1, count(array_filter($sameChargeResults, static fn (string $result): bool => $result === 'ok')));
        self::assertSame(1, count(array_filter($sameChargeResults, static fn (string $result): bool => str_starts_with($result, 'error:'))));
        self::assertSame('0.00', $this->chargesById($fixture['lease_id'])[$chargeId]['outstanding']);
        self::assertSame(1, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM payments WHERE lease_id = ' . $fixture['lease_id'] . ' AND reconciled_at IS NOT NULL',
        )->fetchColumn());

        $doubleActionCharge = $this->insertCharge($fixture['lease_id'], '10.00');
        $doubleAction = $this->report($fixture, $doubleActionCharge, '10.00');
        $doubleActionResults = $this->runWorkers([
            ['action' => 'reconcile', 'payment_id' => (int) $doubleAction['id'], 'allocations' => [['charge_id' => $doubleActionCharge, 'amount' => '10']], 'actor_id' => $fixture['actor_id']],
            ['action' => 'reconcile', 'payment_id' => (int) $doubleAction['id'], 'allocations' => [['charge_id' => $doubleActionCharge, 'amount' => '10']], 'actor_id' => $fixture['other_actor_id']],
        ]);
        self::assertSame(1, count(array_filter($doubleActionResults, static fn (string $result): bool => $result === 'ok')));
        self::assertSame(1, count(array_filter($doubleActionResults, static fn (string $result): bool => str_starts_with($result, 'error:'))));
        self::assertSame('reconciled', $this->service()->findPayment((int) $doubleAction['id'])['state']);

        $otherCharge = $this->insertCharge($fixture['lease_id'], '25.00');
        $terminalRace = $this->report($fixture, $otherCharge, '25.00');
        $terminalResults = $this->runWorkers([
            ['action' => 'reconcile', 'payment_id' => (int) $terminalRace['id'], 'allocations' => [['charge_id' => $otherCharge, 'amount' => '25']], 'actor_id' => $fixture['actor_id']],
            ['action' => 'reject', 'payment_id' => (int) $terminalRace['id'], 'reason' => 'No coincide', 'actor_id' => $fixture['other_actor_id']],
        ]);
        self::assertSame(1, count(array_filter($terminalResults, static fn (string $result): bool => $result === 'ok')));
        self::assertSame(1, count(array_filter($terminalResults, static fn (string $result): bool => str_starts_with($result, 'error:'))));
        $terminal = $this->service()->findPayment((int) $terminalRace['id']);
        self::assertContains($terminal['state'], ['reconciled', 'rejected']);
        self::assertFalse($terminal['state'] === 'reconciled' && $terminal['state'] === 'rejected');
    }

    private function service(): FinanceService
    {
        return new FinanceService(
            $this->pdo,
            new PaymentMethodService($this->pdo, sys_get_temp_dir()),
            $this->receiptDirectory,
        );
    }

    private function report(array $fixture, int $chargeId, string $amount, mixed $receipt = null): array
    {
        $methodId = $this->insertPaymentMethod();

        return $this->service()->reportPayment(
            $fixture['lease_id'], $methodId, $amount, '2026-10-12', '08:00',
            [['charge_id' => $chargeId, 'amount' => $amount]], $receipt, $this->now(),
        );
    }

    private function createLease(): array
    {
        $suffix = bin2hex(random_bytes(5));
        $tenantId = $this->insertUser('tenant', 'Tenant ' . $suffix, 'tenant-' . $suffix . '@example.com');
        $actorId = $this->insertUser('admin', 'Admin ' . $suffix, 'admin-' . $suffix . '@example.com');
        $otherActorId = $this->insertUser('admin', 'Other Admin ' . $suffix, 'other-admin-' . $suffix . '@example.com');
        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city) VALUES (?, ?, ?)');
        $property->execute(['Property ' . $suffix, 'Calle 1', 'Bogotá']);
        $propertyId = (int) $this->pdo->lastInsertId();
        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) VALUES (?, ?, ?, ?, ?)',
        );
        $unit->execute([$propertyId, bin2hex(random_bytes(12)), 'apartment', 'APT-' . strtoupper($suffix), 'occupied']);
        $unitId = (int) $this->pdo->lastInsertId();
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $candidate->execute([
            $unitId, 'cc', 'DOC-' . $suffix, 'Candidate ' . $suffix, '3001234567',
            'candidate-' . $suffix . '@example.com', 'approved', hash('sha256', $suffix),
            '2027-01-01 00:00:00', '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();
        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type) VALUES (?, ?)');
        $template->execute(['Template ' . $suffix, 'urban_housing']);
        $templateId = (int) $this->pdo->lastInsertId();
        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) '
            . 'VALUES (?, 1, ?, ?)',
        );
        $version->execute([$templateId, 'Contrato', '[]']);
        $versionId = (int) $this->pdo->lastInsertId();
        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, '
            . 'administration_fee, start_date, end_date, duration_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $contract->execute([
            $candidateId, $unitId, $versionId, 'urban_housing', 'finalized',
            '1500000.00', '200000.00', '2026-10-01', '2027-09-30', 'Doce meses',
        ]);
        $contractId = (int) $this->pdo->lastInsertId();
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) VALUES (?, ?, ?, ?)',
        );
        $lease->execute([$contractId, $tenantId, $actorId, '2026-10-01 12:00:00']);

        return [
            'lease_id' => (int) $this->pdo->lastInsertId(),
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'other_actor_id' => $otherActorId,
            'property_id' => $propertyId,
        ];
    }

    private function insertUser(string $role, string $name, string $email): int
    {
        $statement = $this->pdo->prepare('INSERT INTO users (role, full_name, email, password_hash) VALUES (?, ?, ?, ?)');
        $statement->execute([$role, $name, $email, password_hash('TemporaryPass123', PASSWORD_DEFAULT)]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertPaymentMethod(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO payment_methods (type, label, recipient_name) VALUES (?, ?, ?)',
        );
        $statement->execute(['manual', 'Transferencia', 'Arrendador']);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertCharge(int $leaseId, string $amount, bool $voided = false, string $dueDate = '2026-10-20'): int
    {
        $creator = (int) $this->pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
        $statement = $this->pdo->prepare(
            'INSERT INTO charges (lease_id, type, due_date, amount, description, created_by_user_id, '
            . 'voided_by_user_id, voided_at, void_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $statement->execute([
            $leaseId, 'rent', $dueDate, $amount, 'Cargo prueba', $creator,
            $voided ? $creator : null, $voided ? '2026-10-11 12:00:00' : null, $voided ? 'Anulado' : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function payment(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM payments WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    private function allocations(int $paymentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT charge_id, amount FROM payment_allocations WHERE payment_id = ? ORDER BY charge_id',
        );
        $statement->execute([$paymentId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'charge_id' => (int) $row['charge_id'],
            'amount' => (string) $row['amount'],
        ], $rows);
    }

    private function chargesById(int $leaseId): array
    {
        $rows = $this->service()->listForLease($leaseId, '2026-10-13');
        $result = [];
        foreach ($rows as $row) {
            $result[$row['id']] = $row;
        }

        return $result;
    }

    private function portfolioRow(int $leaseId): array
    {
        foreach ($this->service()->portfolio('2026-10-13') as $row) {
            if ($row['lease_id'] === $leaseId) {
                return $row;
            }
        }
        self::fail('Expected lease to appear in the finance portfolio.');
    }

    private function upload(string $bytes, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_reconcile_receipt_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create receipt fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, $mime, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (RuntimeException) {
            return;
        }
        self::fail('Expected invalid payment transition to be rejected.');
    }

    private function now(): int
    {
        return (new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('America/Bogota')))->getTimestamp();
    }

    private function databaseDateTime(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone('America/Bogota'))
            ->format('Y-m-d H:i:s');
    }

    /** @param list<array{action:string,payment_id:int,allocations?:array,reason?:string,actor_id:int}> $workers
     *  @return list<string>
     */
    private function runWorkers(array $workers): array
    {
        $directory = sys_get_temp_dir() . '/alquilapro-payment-race-' . bin2hex(random_bytes(8));
        if (!mkdir($directory, 0700, true)) {
            self::fail('Unable to create concurrency fixture directory.');
        }
        $script = $directory . DIRECTORY_SEPARATOR . 'worker.php';
        $go = $directory . DIRECTORY_SEPARATOR . 'go';
        $workerSource = <<<'PHP'
<?php
declare(strict_types=1);
$root = $argv[1];
require $root . '/vendor/autoload.php';
$pdo = \AlquilaPro\Foundation\DatabaseConnector::connect(\AlquilaPro\Foundation\DatabaseConfig::fromArray([
    'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('TEST_DB_PORT') ?: '3306',
    'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
    'username' => getenv('TEST_DB_USER') ?: 'root',
    'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
]));
$ready = $argv[2];
$go = $argv[3];
$result = $argv[4];
$action = $argv[5];
$paymentId = (int) $argv[6];
$allocations = json_decode(base64_decode($argv[7], true), true, 512, JSON_THROW_ON_ERROR);
$actorId = (int) $argv[8];
$now = (int) $argv[9];
file_put_contents($ready, 'ready');
$deadline = microtime(true) + 10;
while (!is_file($go) && microtime(true) < $deadline) { usleep(1000); }
try {
    $service = new \AlquilaPro\Finance\FinanceService($pdo);
    if ($action === 'reject') {
        $service->rejectPayment($paymentId, (string) $argv[10], $actorId, $now);
    } else {
        $service->reconcilePayment($paymentId, $allocations, $actorId, $now);
    }
    file_put_contents($result, 'ok');
} catch (\Throwable $exception) {
    file_put_contents($result, 'error:' . $exception::class . ':' . $exception->getMessage());
}
PHP;
        file_put_contents($script, $workerSource);
        $processes = [];
        try {
            foreach ($workers as $position => $worker) {
                $ready = $directory . DIRECTORY_SEPARATOR . 'ready-' . $position;
                $result = $directory . DIRECTORY_SEPARATOR . 'result-' . $position;
                $arguments = [
                    PHP_BINARY, $script, dirname(__DIR__, 3), $ready, $go,
                    $result, $worker['action'], (string) $worker['payment_id'],
                    base64_encode(json_encode($worker['allocations'] ?? [], JSON_THROW_ON_ERROR)),
                    (string) $worker['actor_id'], (string) $this->now(), $worker['reason'] ?? '',
                ];
                $pipes = [];
                $process = proc_open($arguments, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start a reconciliation worker.');
                }
                fclose($pipes[0]);
                $processes[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready, 'result' => $result];
            }

            $deadline = microtime(true) + 10;
            do {
                $allReady = true;
                foreach ($processes as $process) {
                    $allReady = $allReady && is_file($process['ready']);
                }
                if ($allReady) {
                    break;
                }
                usleep(1000);
            } while (microtime(true) < $deadline);
            self::assertTrue($allReady, 'Both independent database connections must reach the race barrier.');
            file_put_contents($go, 'go');

            $results = [];
            foreach ($processes as $process) {
                $deadline = microtime(true) + 15;
                while (!is_file($process['result']) && microtime(true) < $deadline) {
                    usleep(1000);
                }
                self::assertFileExists($process['result']);
                $results[] = (string) file_get_contents($process['result']);
                stream_get_contents($process['pipes'][1]);
                stream_get_contents($process['pipes'][2]);
                fclose($process['pipes'][1]);
                fclose($process['pipes'][2]);
                proc_close($process['process']);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process['process'])) {
                    proc_terminate($process['process']);
                    foreach ([1, 2] as $index) {
                        if (is_resource($process['pipes'][$index])) {
                            fclose($process['pipes'][$index]);
                        }
                    }
                    proc_close($process['process']);
                }
            }
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($directory);
        }
    }
}
