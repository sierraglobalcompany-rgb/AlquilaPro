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

final class MembershipConcurrencyTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private int $actorId;
    private int $tenantId;
    private int $leaseId;
    private int $serviceId;

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

        $this->directory = sys_get_temp_dir() . '/alquilapro-membership-workers-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->actorId = $this->insertUser('admin', 'membership-admin');
        $this->tenantId = $this->insertUser('tenant', 'membership-tenant');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_at) VALUES (:contract, :tenant, \'2026-10-01 12:00:00\')',
        );
        $lease->execute(['contract' => random_int(1_000_000, 2_000_000_000), 'tenant' => $this->tenantId]);
        $this->leaseId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $catalog = $this->service()->createService('Servicio', 'Servicio concurrente', '25.00', 10, $this->actorId, 1_800_000_000);
        $this->serviceId = (int) $catalog['id'];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
    }

    public function testConcurrentAcceptsOnOneLeaseHaveOneWinner(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent membership test requires proc_open.');
        }
        $results = $this->runWithLeaseLocked([
            ['accept', $this->serviceId, $this->leaseId, $this->tenantId, '25.00', 10, '2026-10-14', 1_800_000_010],
            ['accept', $this->serviceId, $this->leaseId, $this->tenantId, '25.00', 10, '2026-10-14', 1_800_000_011],
        ]);
        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok']));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM service_memberships')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
    }

    public function testConcurrentRenewalsOfOneMembershipHaveOneWinner(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent membership test requires proc_open.');
        }
        $first = $this->service()->accept(
            $this->serviceId, $this->leaseId, $this->tenantId, '25.00', 10, '2026-10-14', 1_800_000_010,
        );
        $results = $this->runWithLeaseLocked([
            ['renew', (int) $first['id'], $this->tenantId, '25.00', 10, '2026-10-14', 1_800_000_020],
            ['renew', (int) $first['id'], $this->tenantId, '25.00', 10, '2026-10-14', 1_800_000_021],
        ]);
        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok']));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM service_memberships')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM service_memberships WHERE renews_membership_id = ' . (int) $first['id'],
        )->fetchColumn());
    }

    public function testConcurrentCreateDecisionsCreateExactlyOneFinanceCharge(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent membership test requires proc_open.');
        }
        $membership = $this->paidMembership();
        $results = $this->runWithMembershipLocked((int) $membership['id'], [
            ['decide', (int) $membership['id'], 'create', '2026-10-20', 'Servicio digital', $this->actorId, 1_800_000_030],
            ['decide', (int) $membership['id'], 'create', '2026-10-20', 'Servicio digital', $this->actorId, 1_800_000_031],
        ]);

        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok']));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
        $stored = $this->service()->findMembership((int) $membership['id']);
        self::assertSame('create', $stored['charge_decision']);
        self::assertNotNull($stored['charge_id']);
    }

    public function testConcurrentCreateAndSkipHaveOneTerminalWinner(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent membership test requires proc_open.');
        }
        $membership = $this->paidMembership();
        $results = $this->runWithMembershipLocked((int) $membership['id'], [
            ['decide', (int) $membership['id'], 'create', '2026-10-20', 'Servicio digital', $this->actorId, 1_800_000_030],
            ['decide', (int) $membership['id'], 'skip', '', '', $this->actorId, 1_800_000_031],
        ]);

        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok']));
        $stored = $this->service()->findMembership((int) $membership['id']);
        self::assertContains($stored['charge_decision'], ['create', 'skip']);
        self::assertSame($stored['charge_decision'] === 'create' ? 1 : 0, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
        self::assertSame($stored['charge_decision'] === 'create', $stored['charge_id'] !== null);
    }

    public function testConcurrentSkipDecisionsHaveOneStableWinnerAndNoCharge(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent membership test requires proc_open.');
        }
        $membership = $this->paidMembership();
        $results = $this->runWithMembershipLocked((int) $membership['id'], [
            ['decide', (int) $membership['id'], 'skip', '', '', $this->actorId, 1_800_000_030],
            ['decide', (int) $membership['id'], 'skip', '', '', $this->actorId, 1_800_000_031],
        ]);

        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok']));
        $stored = $this->service()->findMembership((int) $membership['id']);
        self::assertSame('skip', $stored['charge_decision']);
        self::assertNull($stored['charge_id']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
    }

    public function testConcurrentCreateDecisionAndRenewalDoNotDeadlock(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent membership test requires proc_open.');
        }
        $membership = $this->paidMembership();
        $this->pdo->exec(
            'CREATE TRIGGER membership_pause_charge BEFORE INSERT ON charges FOR EACH ROW DO SLEEP(10)',
        );
        $workers = [];
        try {
            $workers[] = $this->startWorker([
                'decide', (int) $membership['id'], 'create', '2026-10-20', 'Servicio digital', $this->actorId, 1_800_000_030,
            ]);
            $this->waitForPausedChargeInsert($workers[0]);
            $workers[] = $this->startWorker([
                'renew', (int) $membership['id'], $this->tenantId, '25.00', 10, '2026-10-14', 1_800_000_031,
            ]);

            $results = array_map(fn (array $worker): array => $this->finishWorker($worker), $workers);
            self::assertSame([true, true], array_column($results, 'ok'), json_encode($results));
            self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
            self::assertSame('create', $this->service()->findMembership((int) $membership['id'])['charge_decision']);
            self::assertSame(1, (int) $this->pdo->query(
                'SELECT COUNT(*) FROM service_memberships WHERE renews_membership_id = ' . (int) $membership['id'],
            )->fetchColumn());
        } finally {
            $this->pdo->exec('DROP TRIGGER IF EXISTS membership_pause_charge');
            foreach ($workers as $worker) {
                if (is_resource($worker['process'])) {
                    @proc_terminate($worker['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (isset($worker['pipes'][$pipeNumber]) && is_resource($worker['pipes'][$pipeNumber])) {
                            fclose($worker['pipes'][$pipeNumber]);
                        }
                    }
                    @proc_close($worker['process']);
                }
                @unlink($worker['ready']);
            }
        }
    }

    /** @param list<list<int|string>> $operations @return list<array{ok:bool}> */
    private function runWithLeaseLocked(array $operations): array
    {
        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM leases WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $this->leaseId]);
        self::assertSame($this->leaseId, (int) $lock->fetchColumn());
        $workers = [];
        try {
            $workerPath = dirname(__DIR__, 2) . '/Support/MembershipConcurrencyWorker.php';
            foreach ($operations as $operation) {
                $ready = $this->directory . '/worker-' . bin2hex(random_bytes(6)) . '.ready';
                $command = array_merge([PHP_BINARY, $workerPath], array_map('strval', $operation), [$ready]);
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start membership concurrency worker.');
                }
                fclose($pipes[0]);
                $workers[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
            }
            $deadline = microtime(true) + 12;
            do {
                $allReady = count(array_filter($workers, static fn (array $worker): bool => is_file($worker['ready']))) === count($workers);
                if ($allReady) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            self::assertTrue($allReady, 'Membership workers did not reach the lease lock.');
            usleep(250000);
            foreach ($workers as $worker) {
                self::assertTrue(proc_get_status($worker['process'])['running']);
            }
            $this->pdo->commit();

            $results = [];
            foreach ($workers as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                self::assertSame(0, proc_close($worker['process']), 'Membership worker failed: ' . (string) $stderr);
                $result = json_decode((string) $stdout, true);
                self::assertIsArray($result, 'Membership worker returned invalid JSON.');
                $results[] = $result;
            }
            return $results;
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($workers as $worker) {
                if (is_resource($worker['process'])) {
                    @proc_terminate($worker['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (isset($worker['pipes'][$pipeNumber]) && is_resource($worker['pipes'][$pipeNumber])) {
                            fclose($worker['pipes'][$pipeNumber]);
                        }
                    }
                    @proc_close($worker['process']);
                }
                @unlink($worker['ready']);
            }
        }
    }

    private function service(): MembershipService
    {
        return new MembershipService(
            $this->pdo,
            new FinanceService($this->pdo, new PaymentMethodService($this->pdo, sys_get_temp_dir()), sys_get_temp_dir()),
        );
    }

    /** @param list<int|string> $operation @return array{process:resource|false,pipes:array<int,resource>,ready:string} */
    private function startWorker(array $operation): array
    {
        $workerPath = dirname(__DIR__, 2) . '/Support/MembershipConcurrencyWorker.php';
        $ready = $this->directory . '/worker-' . bin2hex(random_bytes(6)) . '.ready';
        $command = array_merge([PHP_BINARY, $workerPath], array_map('strval', $operation), [$ready]);
        $pipes = [];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
        );
        self::assertIsResource($process, 'Unable to start membership concurrency worker.');
        fclose($pipes[0]);
        return ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
    }

    /** @param array{process:resource|false,pipes:array<int,resource>,ready:string} $worker @return array{ok:bool} */
    private function finishWorker(array $worker): array
    {
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);
        self::assertSame(0, proc_close($worker['process']), 'Membership worker failed: ' . (string) $stderr);
        $result = json_decode((string) $stdout, true);
        self::assertIsArray($result, 'Membership worker returned invalid JSON.');
        return $result;
    }

    /** @param array{process:resource|false,pipes:array<int,resource>,ready:string} $worker */
    private function waitForPausedChargeInsert(array $worker): void
    {
        $deadline = microtime(true) + 8;
        $lastProcessList = [];
        do {
            if (is_file($worker['ready'])) {
                $status = proc_get_status($worker['process']);
                if (!$status['running']) {
                    $stdout = stream_get_contents($worker['pipes'][1]);
                    $stderr = stream_get_contents($worker['pipes'][2]);
                    self::fail('Charge worker exited before the barrier: ' . (string) $stdout . ' ' . (string) $stderr);
                }
                $lastProcessList = $this->pdo->query('SHOW FULL PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($lastProcessList as $process) {
                    if (($process['State'] ?? null) === 'User sleep'
                        && str_contains((string) ($process['Info'] ?? ''), 'SLEEP(10)')
                    ) {
                        return;
                    }
                }
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);
        self::fail('Charge insert did not reach the concurrency barrier: ' . json_encode($lastProcessList));
    }

    /** @return array<string,mixed> */
    private function paidMembership(): array
    {
        $membershipService = $this->service();
        $catalog = $membershipService->createService('Servicio de prueba', 'Servicio para concurrencia', '25.00', 10, $this->actorId, 1_800_000_000);
        return $membershipService->accept(
            (int) $catalog['id'], $this->leaseId, $this->tenantId,
            (string) $catalog['current_price'], (int) $catalog['duration_days'], '2026-10-14', 1_800_000_010,
        );
    }

    /** @param list<list<int|string>> $operations @return list<array{ok:bool}> */
    private function runWithMembershipLocked(int $membershipId, array $operations): array
    {
        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM service_memberships WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $membershipId]);
        self::assertSame($membershipId, (int) $lock->fetchColumn());
        $workers = [];
        try {
            $workerPath = dirname(__DIR__, 2) . '/Support/MembershipConcurrencyWorker.php';
            foreach ($operations as $operation) {
                $ready = $this->directory . '/worker-' . bin2hex(random_bytes(6)) . '.ready';
                $command = array_merge([PHP_BINARY, $workerPath], array_map('strval', $operation), [$ready]);
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start membership decision concurrency worker.');
                }
                fclose($pipes[0]);
                $workers[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
            }
            $deadline = microtime(true) + 12;
            do {
                $allReady = count(array_filter($workers, static fn (array $worker): bool => is_file($worker['ready']))) === count($workers);
                if ($allReady) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            self::assertTrue($allReady, 'Membership decision workers did not reach the row lock.');
            usleep(250000);
            foreach ($workers as $worker) {
                self::assertTrue(proc_get_status($worker['process'])['running']);
            }
            $this->pdo->commit();

            $results = [];
            foreach ($workers as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                self::assertSame(0, proc_close($worker['process']), 'Membership decision worker failed: ' . (string) $stderr);
                $result = json_decode((string) $stdout, true);
                self::assertIsArray($result, 'Membership decision worker returned invalid JSON.');
                $results[] = $result;
            }
            return $results;
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($workers as $worker) {
                if (is_resource($worker['process'])) {
                    @proc_terminate($worker['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (isset($worker['pipes'][$pipeNumber]) && is_resource($worker['pipes'][$pipeNumber])) {
                            fclose($worker['pipes'][$pipeNumber]);
                        }
                    }
                    @proc_close($worker['process']);
                }
                @unlink($worker['ready']);
            }
        }
    }

    private function insertUser(string $role, string $prefix): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) VALUES (:role, :name, :email, :hash, 1)',
        );
        $statement->execute([
            'role' => $role,
            'name' => $prefix,
            'email' => $prefix . '-' . bin2hex(random_bytes(5)) . '@example.test',
            'hash' => password_hash('MembershipConcurrency123!', PASSWORD_DEFAULT),
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
