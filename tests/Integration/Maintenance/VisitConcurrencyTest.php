<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Maintenance;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Maintenance\VisitService;
use AlquilaPro\Tests\Support\MaintenanceRequestFixture;
use PDO;
use PHPUnit\Framework\TestCase;

final class VisitConcurrencyTest extends TestCase
{
    use MaintenanceRequestFixture;

    private PDO $pdo;
    private string $directory;
    /** @var list<array{process:resource,pipes:array<int,resource>,ready:string}> */
    private array $workers = [];

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
        $this->directory = sys_get_temp_dir() . '/alquila_visit_race_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if (is_resource($worker['process'])) {
                @proc_terminate($worker['process']);
                foreach ([1, 2] as $pipe) {
                    if (isset($worker['pipes'][$pipe]) && is_resource($worker['pipes'][$pipe])) {
                        fclose($worker['pipes'][$pipe]);
                    }
                }
                @proc_close($worker['process']);
            }
            @unlink($worker['ready']);
        }
        @rmdir($this->directory);
    }

    public function testConcurrentSelectsHaveOneWinner(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $now = time();
        $start = gmdate('Y-m-d H:i:s', $now + 86400);
        $service = new VisitService($this->pdo);
        $visit = $service->propose($fixture['request_id'], [
            ['start_at' => $start, 'duration_minutes' => 60],
        ], null, null, $fixture['actor_id'], $now);
        $this->lockVisit($visit['id']);
        $results = $this->runQueuedWorkers([
            ['select', $visit['id'], $fixture['tenant_id'], $now + 1, $start],
            ['select', $visit['id'], $fixture['tenant_id'], $now + 1, $start],
        ]);

        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok']));
        self::assertSame('scheduled', $service->find($visit['id'])['status']);
        self::assertSame($start, $service->find($visit['id'])['selected_start_at']);
    }

    public function testSelectAndReprogramRaceNeverLeavesAStaleSelection(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $now = time();
        $first = gmdate('Y-m-d H:i:s', $now + 86400);
        $second = gmdate('Y-m-d H:i:s', $now + 172800);
        $service = new VisitService($this->pdo);
        $visit = $service->propose($fixture['request_id'], [
            ['start_at' => $first, 'duration_minutes' => 60],
        ], null, null, $fixture['actor_id'], $now);
        $this->lockVisit($visit['id']);
        $results = $this->runQueuedWorkers([
            ['select', $visit['id'], $fixture['tenant_id'], $now + 1, $first],
            ['reprogram', $visit['id'], $fixture['actor_id'], $now + 1, json_encode([
                ['start_at' => $second, 'duration_minutes' => 90],
            ], JSON_THROW_ON_ERROR)],
        ]);

        self::assertNotEmpty(array_filter($results, static fn (array $result): bool => $result['ok']));
        $current = $service->find($visit['id']);
        self::assertSame('proposed', $current['status']);
        self::assertNull($current['selected_start_at']);
        self::assertSame([$second], array_column($current['proposed_slots'], 'start_at'));
    }

    public function testCompleteAndCancelRaceHasOneTerminalWinner(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $now = time();
        $start = gmdate('Y-m-d H:i:s', $now + 86400);
        $service = new VisitService($this->pdo);
        $visit = $service->scheduleDirect(
            $fixture['request_id'], $start, 60, null, null, $fixture['actor_id'], $now,
        );
        $this->lockVisit($visit['id']);
        $results = $this->runQueuedWorkers([
            ['complete', $visit['id'], $fixture['actor_id'], $now + 1, ''],
            ['cancel', $visit['id'], $fixture['actor_id'], $now + 1, ''],
        ]);

        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok']));
        self::assertContains($service->find($visit['id'])['status'], ['completed', 'cancelled']);
    }

    public function testReprogramAndCompleteRaceKeepsOneConsistentState(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $now = time();
        $start = gmdate('Y-m-d H:i:s', $now + 86400);
        $replacement = gmdate('Y-m-d H:i:s', $now + 172800);
        $service = new VisitService($this->pdo);
        $visit = $service->scheduleDirect(
            $fixture['request_id'], $start, 60, null, null, $fixture['actor_id'], $now,
        );
        $this->lockVisit($visit['id']);
        $results = $this->runQueuedWorkers([
            ['reprogram', $visit['id'], $fixture['actor_id'], $now + 1, json_encode([
                ['start_at' => $replacement, 'duration_minutes' => 90],
            ], JSON_THROW_ON_ERROR)],
            ['complete', $visit['id'], $fixture['actor_id'], $now + 1, ''],
        ]);

        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok']));
        $current = $service->find($visit['id']);
        self::assertContains($current['status'], ['proposed', 'completed']);
        if ($current['status'] === 'proposed') {
            self::assertNull($current['selected_start_at']);
            self::assertSame([$replacement], array_column($current['proposed_slots'], 'start_at'));
        } else {
            self::assertSame($start, $current['selected_start_at']);
        }
    }

    private function lockVisit(int $visitId): void
    {
        $this->pdo->beginTransaction();
        $statement = $this->pdo->prepare('SELECT id FROM maintenance_visits WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $visitId]);
        self::assertSame($visitId, (int) $statement->fetchColumn());
    }

    /** @param list<list<int|string>> $operations @return list<array<string,mixed>> */
    private function runQueuedWorkers(array $operations): array
    {
        $this->workers = [];
        $workerPath = dirname(__DIR__, 2) . '/Support/VisitConcurrencyWorker.php';
        try {
            foreach ($operations as $operation) {
                $ready = $this->directory . '/worker-' . bin2hex(random_bytes(6)) . '.ready';
                $command = array_merge([PHP_BINARY, $workerPath], array_map('strval', $operation), [$ready]);
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start visit worker.');
                }
                fclose($pipes[0]);
                $this->workers[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
            }
            $deadline = microtime(true) + 12;
            do {
                $allReady = count(array_filter(
                    $this->workers,
                    static fn (array $worker): bool => is_file($worker['ready']),
                )) === count($this->workers);
                if ($allReady) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertTrue($allReady, 'Each worker should reach its service operation while the visit row is locked.');
            usleep(150000);
            foreach ($this->workers as $worker) {
                self::assertTrue(proc_get_status($worker['process'])['running']);
            }
            $this->pdo->commit();

            $results = [];
            foreach ($this->workers as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                $exitCode = proc_close($worker['process']);
                @unlink($worker['ready']);
                $result = json_decode((string) $stdout, true);
                self::assertSame(0, $exitCode, 'Visit worker failed: ' . (string) $stderr);
                self::assertIsArray($result, 'Visit worker returned invalid JSON. ' . (string) $stderr);
                $results[] = $result;
            }
            $this->workers = [];
            return $results;
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }
}
