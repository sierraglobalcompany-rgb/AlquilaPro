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
use RuntimeException;

final class VisitServiceTest extends TestCase
{
    use MaintenanceRequestFixture;

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

    public function testProposalRequiresBoundedSortedFutureSlotsAndPositiveDurations(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $service = new VisitService($this->pdo);
        $now = time();
        $first = gmdate('Y-m-d H:i:s', $now + 86400);
        $second = gmdate('Y-m-d H:i:s', $now + 172800);
        $visit = $service->propose($fixture['request_id'], [
            ['start_at' => $first, 'duration_minutes' => 60],
            ['start_at' => $second, 'duration_minutes' => 90],
        ], 'Técnico', '3001234567', $fixture['actor_id'], $now);

        self::assertSame('proposed', $visit['status']);
        self::assertNull($visit['selected_start_at']);
        self::assertSame([$first, $second], array_column($visit['proposed_slots'], 'start_at'));
        self::assertNull($visit['duration_minutes']);
        $this->assertRejected(fn () => $service->propose($fixture['request_id'], [
            ['start_at' => $first, 'duration_minutes' => 60],
            ['start_at' => $first, 'duration_minutes' => 60],
        ], null, null, $fixture['actor_id'], $now));
        $this->assertRejected(fn () => $service->propose($fixture['request_id'], [
            ['start_at' => gmdate('Y-m-d H:i:s', $now - 60), 'duration_minutes' => 60],
        ], null, null, $fixture['actor_id'], $now));
        $this->assertRejected(fn () => $service->propose($fixture['request_id'], [
            ['start_at' => $first, 'duration_minutes' => 0],
        ], null, null, $fixture['actor_id'], $now));
        $this->assertRejected(fn () => $service->propose($fixture['request_id'], array_fill(0, 11, [
            'start_at' => $first,
            'duration_minutes' => 60,
        ]), null, null, $fixture['actor_id'], $now));
    }

    public function testSelectionReprogramCompletionAndCancellationTransitions(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $service = new VisitService($this->pdo);
        $now = time();
        $first = gmdate('Y-m-d H:i:s', $now + 86400);
        $second = gmdate('Y-m-d H:i:s', $now + 172800);
        $visit = $service->propose($fixture['request_id'], [
            ['start_at' => $first, 'duration_minutes' => 60],
            ['start_at' => $second, 'duration_minutes' => 90],
        ], 'Técnico', '3001234567', $fixture['actor_id'], $now);
        $selected = $service->selectSlot($visit['id'], $first, $fixture['tenant_id'], $now + 1);
        self::assertSame('scheduled', $selected['status']);
        self::assertSame($first, $selected['selected_start_at']);
        self::assertSame(60, $selected['duration_minutes']);

        $reprogrammed = $service->reprogram($visit['id'], [
            ['start_at' => $second, 'duration_minutes' => 90],
        ], $fixture['actor_id'], $now + 2);
        self::assertSame('proposed', $reprogrammed['status']);
        self::assertNull($reprogrammed['selected_start_at']);
        self::assertNull($reprogrammed['duration_minutes']);

        $service->selectSlot($visit['id'], $second, $fixture['tenant_id'], $now + 3);
        $completed = $service->complete($visit['id'], 'Visita finalizada', $fixture['actor_id'], $now + 4);
        self::assertSame('completed', $completed['status']);
        self::assertSame('Visita finalizada', $completed['result_notes']);
        self::assertSame($fixture['actor_id'], $completed['completed_by_user_id']);
        $this->assertRejected(fn () => $service->cancel($visit['id'], 'No asistir', $fixture['actor_id'], $now + 5));

        $cancelledRequest = $this->createMaintenanceRequest($this->pdo);
        $cancelVisit = $service->propose($cancelledRequest['request_id'], [
            ['start_at' => $first, 'duration_minutes' => 60],
        ], null, null, $cancelledRequest['actor_id'], $now);
        $cancelled = $service->cancel($cancelVisit['id'], 'Solicitud retirada', $cancelledRequest['actor_id'], $now + 6);
        self::assertSame('cancelled', $cancelled['status']);
        self::assertSame('Solicitud retirada', $cancelled['cancel_reason']);
        $this->assertRejected(fn () => $service->complete(
            $cancelVisit['id'], null, $cancelledRequest['actor_id'], $now + 7,
        ));
    }

    public function testStaffCanReplaceAnUnselectedProposal(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $service = new VisitService($this->pdo);
        $now = time();
        $first = gmdate('Y-m-d H:i:s', $now + 86400);
        $second = gmdate('Y-m-d H:i:s', $now + 172800);
        $visit = $service->propose($fixture['request_id'], [
            ['start_at' => $first, 'duration_minutes' => 60],
        ], null, null, $fixture['actor_id'], $now);

        $updated = $service->reprogram($visit['id'], [
            ['start_at' => $second, 'duration_minutes' => 90],
        ], $fixture['actor_id'], $now + 1);

        self::assertSame('proposed', $updated['status']);
        self::assertSame([$second], array_column($updated['proposed_slots'], 'start_at'));
        self::assertNull($updated['selected_start_at']);
    }

    public function testDirectScheduleAndTerminalRequestGuard(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $service = new VisitService($this->pdo);
        $now = time();
        $start = gmdate('Y-m-d H:i:s', $now + 86400);
        $visit = $service->scheduleDirect(
            $fixture['request_id'], $start, 45, 'Técnico', '3001234567', $fixture['actor_id'], $now,
        );
        self::assertSame('scheduled', $visit['status']);
        self::assertSame(45, $visit['duration_minutes']);
        self::assertSame($visit['id'], $service->find($visit['id'])['id']);
        self::assertCount(1, $service->listForRequest($fixture['request_id']));

        $this->pdo->prepare('UPDATE maintenance_requests SET status = \'resolved\' WHERE id = :id')
            ->execute(['id' => $fixture['request_id']]);
        $this->assertRejected(fn () => $service->scheduleDirect(
            $fixture['request_id'], gmdate('Y-m-d H:i:s', $now + 259200), 45, null, null,
            $fixture['actor_id'], $now + 1,
        ));
    }

    public function testTechnicianFieldsRejectUnsafeOrOversizedValues(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $service = new VisitService($this->pdo);
        $now = time();
        $start = gmdate('Y-m-d H:i:s', $now + 86400);
        $slots = [['start_at' => $start, 'duration_minutes' => 60]];
        $this->assertRejected(fn () => $service->propose(
            $fixture['request_id'], $slots, str_repeat('x', 121), null, $fixture['actor_id'], $now,
        ));
        $this->assertRejected(fn () => $service->propose(
            $fixture['request_id'], $slots, null, "3001234567\nBcc:evil@example.test", $fixture['actor_id'], $now,
        ));
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected visit operation to be rejected.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }
}
