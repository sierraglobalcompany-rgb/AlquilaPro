<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\ObligationService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ObligationFinancialIntegrationTest extends TestCase
{
    private PDO $pdo;
    private string $supportDirectory;

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
        $this->supportDirectory = sys_get_temp_dir() . '/alquilapro-obligation-finance-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->supportDirectory)) {
            foreach (glob($this->supportDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (is_file($path) || is_link($path)) {
                    @unlink($path);
                }
            }
            @rmdir($this->supportDirectory);
        }
    }

    public function testUtilityAndFineGenerateCanonicalChargesWithExplicitTenantDueDate(): void
    {
        $lease = $this->createLease();
        $service = $this->obligations();
        $utility = $service->create($lease['lease_id'], [
            'type' => 'utility',
            'service_name' => 'Acueducto',
            'description' => '  Factura <b>acueducto</b> octubre  ',
            'amount' => '001250.5',
            'period_month' => '2026-10-01',
            'due_on' => '2026-10-11',
        ], null, $lease['actor_id'], 1792000000);
        $fine = $service->create($lease['lease_id'], [
            'type' => 'fine_ph',
            'description' => '  Multa por ruido  ',
            'amount' => '75.25',
        ], null, $lease['actor_id'], 1792000001);

        $utilityLinked = $service->generateCharge($utility['id'], '2026-10-25', $lease['actor_id']);
        $fineLinked = $service->generateCharge($fine['id'], '2026-11-02', $lease['actor_id']);
        $charges = $this->finance()->listForLease($lease['lease_id'], '2026-10-14');
        $utilityCharge = $this->chargeById($charges, $utilityLinked['charge_id']);
        $fineCharge = $this->chargeById($charges, $fineLinked['charge_id']);

        self::assertSame('service', $utilityCharge['type']);
        self::assertSame('1250.50', $utilityCharge['amount']);
        self::assertSame('2026-10-25', $utilityCharge['due_date'], 'External due_on must not become tenant charge due_date.');
        self::assertSame('2026-10-01', $utilityCharge['period_month']);
        self::assertSame('Factura acueducto octubre', $utilityCharge['description']);
        self::assertSame('fine', $fineCharge['type']);
        self::assertSame('75.25', $fineCharge['amount']);
        self::assertSame('2026-11-02', $fineCharge['due_date']);
        self::assertNull($fineCharge['period_month']);
        self::assertSame('Multa por ruido', $fineCharge['description']);
        self::assertSame($utilityCharge['id'], $utilityLinked['charge_id']);
        self::assertTrue($utilityLinked['is_charged']);
        self::assertSame(2, count($charges));

        $this->assertRejected(fn () => $service->generateCharge($utility['id'], '2026-10-26', $lease['actor_id']));
        $this->assertRejected(fn () => $service->generateCharge($fine['id'], '2026-11-03', $lease['actor_id']));
        self::assertSame(2, count($this->finance()->listForLease($lease['lease_id'], '2026-10-14')));

        $administration = $service->create($lease['lease_id'], [
            'type' => 'administration_ph', 'description' => 'Administración octubre',
            'period_month' => '2026-10-01', 'amount' => '200000',
        ], null, $lease['actor_id'], 1792000002);
        $this->assertRejected(fn () => $service->generateCharge($administration['id'], '2026-10-25', $lease['actor_id']));
        self::assertSame(2, count($this->finance()->listForLease($lease['lease_id'], '2026-10-14')));
    }

    public function testGenerateChargeFailureRollsBackCanonicalChargeAndLinkTogether(): void
    {
        $lease = $this->createLease();
        $service = $this->obligations();
        $obligation = $service->create($lease['lease_id'], [
            'type' => 'utility', 'service_name' => 'Gas', 'description' => 'Factura gas', 'amount' => '30.00',
        ], null, $lease['actor_id'], 1792000000);
        $this->pdo->exec(
            "CREATE TRIGGER reject_obligation_charge_link BEFORE UPDATE ON lease_obligations "
            . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced link failure'",
        );

        $this->assertRejected(fn () => $service->generateCharge($obligation['id'], '2026-10-20', $lease['actor_id']));

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
        self::assertNull($this->obligationRow($obligation['id'])['charge_id']);
        self::assertFalse($service->find($obligation['id'], '2026-10-14')['is_charged']);
    }

    public function testAdministrationLinkAcceptsOnlyCanonicalSameLeasePeriodCharge(): void
    {
        $lease = $this->createLease();
        $otherLease = $this->createLease();
        $service = $this->obligations();
        $finance = $this->finance();
        $createdCharges = $finance->createMonthlyCharges($lease['lease_id'], '2026-10-01', '2026-10-15', $lease['actor_id']);
        $adminCharge = $this->findChargeByType($createdCharges, 'administration');
        $obligation = $service->create($lease['lease_id'], [
            'type' => 'administration_ph', 'description' => 'Cuota PH externa',
            'period_month' => '2026-10-01', 'amount' => '195000.00',
        ], null, $lease['actor_id'], 1792000000);

        $linked = $service->linkAdministrationCharge($obligation['id'], $adminCharge['id'], $lease['actor_id']);
        self::assertSame($adminCharge['id'], $linked['charge_id']);
        self::assertSame('195000.00', $linked['amount'], 'External amount remains independent of F9 amount.');
        self::assertTrue($linked['is_charged']);

        $second = $service->create($lease['lease_id'], [
            'type' => 'administration_ph', 'description' => 'Otra obligación PH',
            'period_month' => '2026-10-01', 'amount' => '200000',
        ], null, $lease['actor_id'], 1792000001);
        $this->assertRejected(fn () => $service->linkAdministrationCharge($second['id'], $adminCharge['id'], $lease['actor_id']));

        $wrongPeriod = $finance->createMonthlyCharges($lease['lease_id'], '2026-11-01', '2026-11-15', $lease['actor_id']);
        $this->assertRejected(fn () => $service->linkAdministrationCharge(
            $second['id'], $this->findChargeByType($wrongPeriod, 'administration')['id'], $lease['actor_id'],
        ));
        $otherCharges = $finance->createMonthlyCharges($otherLease['lease_id'], '2026-10-01', '2026-10-15', $otherLease['actor_id']);
        $this->assertRejected(fn () => $service->linkAdministrationCharge(
            $second['id'], $this->findChargeByType($otherCharges, 'administration')['id'], $lease['actor_id'],
        ));
        $manual = $finance->createManualCharge($lease['lease_id'], 'service', '20', '2026-10-15', 'Servicio', null, $lease['actor_id']);
        $this->assertRejected(fn () => $service->linkAdministrationCharge($second['id'], $manual['id'], $lease['actor_id']));

        $voidedSet = $finance->createMonthlyCharges($lease['lease_id'], '2026-12-01', '2026-12-15', $lease['actor_id']);
        $voidedAdmin = $this->findChargeByType($voidedSet, 'administration');
        $finance->voidCharge($voidedAdmin['id'], $lease['actor_id'], 'Corrección', 1792000100);
        $this->assertRejected(fn () => $service->linkAdministrationCharge($second['id'], $voidedAdmin['id'], $lease['actor_id']));

        $this->assertRejected(fn () => $service->linkAdministrationCharge($obligation['id'], $manual['id'], $lease['actor_id']));
    }

    public function testConcurrentGenerateCallsSerializeOnOneObligation(): void
    {
        $lease = $this->createLease();
        $obligation = $this->obligations()->create($lease['lease_id'], [
            'type' => 'utility', 'service_name' => 'Energía', 'description' => 'Factura energía', 'amount' => '100',
        ], null, $lease['actor_id'], 1792000000);
        $workers = $this->runWhileLocked('lease_obligations', $obligation['id'], [
            ['generate', $obligation['id'], '2026-10-20', $lease['actor_id'], 1792000100],
            ['generate', $obligation['id'], '2026-10-20', $lease['actor_id'], 1792000200],
        ]);

        self::assertSame(1, count(array_filter($workers, static fn (array $result): bool => $result['ok'])));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
        $row = $this->obligationRow($obligation['id']);
        self::assertNotNull($row['charge_id']);
        self::assertSame('0', (string) $this->pdo->query(
            'SELECT COUNT(*) FROM charges WHERE id <> ' . (int) $row['charge_id'],
        )->fetchColumn());
    }

    public function testConcurrentGenerateAndVoidCannotLeaveActiveChargeOnVoidedObligation(): void
    {
        $lease = $this->createLease();
        $obligation = $this->obligations()->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Multa concurrente', 'amount' => '80',
        ], null, $lease['actor_id'], 1792000000);
        $workers = $this->runWhileLocked('lease_obligations', $obligation['id'], [
            ['generate', $obligation['id'], '2026-10-20', $lease['actor_id'], 1792000100],
            ['void', $obligation['id'], 'Duplicada', $lease['actor_id'], 1792000200],
        ]);

        self::assertSame(1, count(array_filter($workers, static fn (array $result): bool => $result['ok'])));
        $row = $this->obligationRow($obligation['id']);
        $activeCharges = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM charges WHERE id = ' . (int) ($row['charge_id'] ?? 0) . ' AND voided_at IS NULL',
        )->fetchColumn();
        self::assertFalse($row['voided_at'] !== null && $activeCharges > 0);
        self::assertTrue($row['voided_at'] !== null || $activeCharges === 1);
    }

    public function testConcurrentAdministrationLinksCannotReuseOneCharge(): void
    {
        $lease = $this->createLease();
        $service = $this->obligations();
        $charges = $this->finance()->createMonthlyCharges($lease['lease_id'], '2026-10-01', '2026-10-15', $lease['actor_id']);
        $adminCharge = $this->findChargeByType($charges, 'administration');
        $first = $service->create($lease['lease_id'], [
            'type' => 'administration_ph', 'description' => 'PH uno', 'period_month' => '2026-10-01', 'amount' => '100',
        ], null, $lease['actor_id'], 1792000000);
        $second = $service->create($lease['lease_id'], [
            'type' => 'administration_ph', 'description' => 'PH dos', 'period_month' => '2026-10-01', 'amount' => '200',
        ], null, $lease['actor_id'], 1792000001);
        $workers = $this->runWhileLocked('charges', $adminCharge['id'], [
            ['link', $first['id'], (string) $adminCharge['id'], $lease['actor_id'], 1792000100],
            ['link', $second['id'], (string) $adminCharge['id'], $lease['actor_id'], 1792000200],
        ]);

        self::assertSame(1, count(array_filter($workers, static fn (array $result): bool => $result['ok'])));
        self::assertSame(1, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM lease_obligations WHERE charge_id = ' . (int) $adminCharge['id'],
        )->fetchColumn());
    }

    private function obligations(): ObligationService
    {
        return new ObligationService($this->pdo, $this->finance(), $this->supportDirectory);
    }

    private function finance(): FinanceService
    {
        $paymentMethods = new PaymentMethodService($this->pdo, $this->supportDirectory . '-qrs');

        return new FinanceService($this->pdo, $paymentMethods, $this->supportDirectory . '-receipts');
    }

    /** @return list<array<string,mixed>> */
    private function runWhileLocked(string $table, int $rowId, array $jobs): array
    {
        if (!in_array($table, ['lease_obligations', 'charges'], true)) {
            self::fail('Unsupported test lock table.');
        }
        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM `' . $table . '` WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $rowId]);
        self::assertNotFalse($lock->fetchColumn());
        $processes = [];
        try {
            foreach ($jobs as $job) {
                $marker = sys_get_temp_dir() . '/obligation-worker-' . bin2hex(random_bytes(8)) . '.ready';
                $command = [PHP_BINARY, dirname(__DIR__, 2) . '/Support/ObligationConcurrencyWorker.php', ...$job, $marker];
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start the concurrency worker.');
                }
                fclose($pipes[0]);
                $processes[] = ['process' => $process, 'pipes' => $pipes, 'marker' => $marker];
            }

            $deadline = microtime(true) + 10;
            do {
                $allReady = count(array_filter($processes, static fn (array $item): bool => is_file($item['marker']))) === count($processes);
                if ($allReady) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            self::assertTrue($allReady, 'Concurrency workers did not reach their database operation.');
            usleep(250000);
            foreach ($processes as $item) {
                self::assertTrue(proc_get_status($item['process'])['running'], 'Worker should be waiting on the held row lock.');
            }
            $this->pdo->commit();

            $results = [];
            foreach ($processes as $item) {
                $stdout = stream_get_contents($item['pipes'][1]);
                $stderr = stream_get_contents($item['pipes'][2]);
                fclose($item['pipes'][1]);
                fclose($item['pipes'][2]);
                $exitCode = proc_close($item['process']);
                @unlink($item['marker']);
                $decoded = json_decode((string) $stdout, true);
                self::assertIsArray($decoded, 'Worker returned invalid JSON. ' . (string) $stderr);
                self::assertSame(0, $exitCode, 'Worker process failed. ' . (string) $stderr);
                $results[] = $decoded;
            }

            return $results;
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($processes as $item) {
                if (is_resource($item['process'])) {
                    @proc_terminate($item['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (isset($item['pipes'][$pipeNumber]) && is_resource($item['pipes'][$pipeNumber])) {
                            fclose($item['pipes'][$pipeNumber]);
                        }
                    }
                    @proc_close($item['process']);
                }
                @unlink($item['marker']);
            }
        }
    }

    private function createLease(): array
    {
        $suffix = bin2hex(random_bytes(5));
        $tenantId = $this->insertUser('tenant', 'Tenant ' . $suffix, 'tenant-' . $suffix . '@example.com');
        $actorId = $this->insertUser('admin', 'Admin ' . $suffix, 'admin-' . $suffix . '@example.com');
        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city) VALUES (:name, :address, :city)');
        $property->execute(['name' => 'Property ' . $suffix, 'address' => 'Calle 1', 'city' => 'Bogotá']);
        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) VALUES (:property_id, :public_key, :type, :code, :status)',
        );
        $unit->execute([
            'property_id' => (int) $this->pdo->lastInsertId(), 'public_key' => bin2hex(random_bytes(12)),
            'type' => 'apartment', 'code' => 'APT-' . strtoupper(substr($suffix, 0, 6)), 'status' => 'occupied',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :status, :token_hash, :expires_at, :consent_at)',
        );
        $candidate->execute([
            'unit_id' => $unitId, 'document_type' => 'cc', 'document_number' => 'DOC-' . strtoupper($suffix),
            'full_name' => 'Candidate ' . $suffix, 'phone' => '3001234567', 'email' => 'candidate-' . $suffix . '@example.com',
            'status' => 'approved', 'token_hash' => hash('sha256', 'token-' . $suffix),
            'expires_at' => '2027-01-01 00:00:00', 'consent_at' => '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();
        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type) VALUES (:name, :document_type)');
        $template->execute(['name' => 'Template ' . $suffix, 'document_type' => 'urban_housing']);
        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) VALUES (:template_id, 1, :body, :variables)',
        );
        $version->execute(['template_id' => (int) $this->pdo->lastInsertId(), 'body' => 'Contrato', 'variables' => '[]']);
        $versionId = (int) $this->pdo->lastInsertId();
        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, start_date, end_date, duration_text) '
            . 'VALUES (:candidate_id, :unit_id, :version_id, :document_type, :status, :rent, :fee, :start_date, :end_date, :duration)',
        );
        $contract->execute([
            'candidate_id' => $candidateId, 'unit_id' => $unitId, 'version_id' => $versionId,
            'document_type' => 'urban_housing', 'status' => 'finalized', 'rent' => '1500000.00',
            'fee' => '200000.00', 'start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'duration' => 'Doce meses',
        ]);
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) VALUES (:contract_id, :user_id, :actor_id, :activated_at)',
        );
        $lease->execute([
            'contract_id' => (int) $this->pdo->lastInsertId(), 'user_id' => $tenantId,
            'actor_id' => $actorId, 'activated_at' => '2026-10-01 12:00:00',
        ]);

        return ['lease_id' => (int) $this->pdo->lastInsertId(), 'actor_id' => $actorId];
    }

    private function insertUser(string $role, string $name, string $email): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash) VALUES (:role, :name, :email, :password_hash)',
        );
        $statement->execute([
            'role' => $role, 'name' => $name, 'email' => $email,
            'password_hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function obligationRow(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM lease_obligations WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    private function chargeById(array $charges, int $id): array
    {
        foreach ($charges as $charge) {
            if ((int) $charge['id'] === $id) {
                return $charge;
            }
        }
        self::fail('Expected canonical charge was not found.');
    }

    private function findChargeByType(array $charges, string $type): array
    {
        foreach ($charges as $charge) {
            if ($charge['type'] === $type) {
                return $charge;
            }
        }
        self::fail('Expected canonical ' . $type . ' charge was not created.');
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (RuntimeException) {
            return;
        }
        self::fail('Expected invalid financial integration to be rejected.');
    }
}
