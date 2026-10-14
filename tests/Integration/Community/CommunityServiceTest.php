<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Community;

use AlquilaPro\Community\CommunityService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CommunityServiceTest extends TestCase
{
    private PDO $pdo;
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
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec('DELETE FROM documents');
        $this->pdo->exec('DELETE FROM community_incidents');
        $this->pdo->exec('DELETE FROM service_memberships');
        $this->pdo->exec('DELETE FROM digital_services');
        $this->pdo->exec('DELETE FROM leases');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $user = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (\'owner\', \'Community Actor\', \'community-actor@example.com\', :hash, 1)',
        );
        $user->execute(['hash' => password_hash('CommunityPassword123', PASSWORD_DEFAULT)]);
        $this->actorId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_at) VALUES (:contract_id, :user_id, :activated_at)',
        );
        $lease->execute([
            'contract_id' => random_int(1_000_000, 2_000_000_000),
            'user_id' => $this->actorId,
            'activated_at' => '2026-10-14 10:00:00',
        ]);
        $this->leaseId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testIncidentCanBeReviewedClosedAndVoidedOnlyForward(): void
    {
        $service = new CommunityService($this->pdo);
        $incident = $service->create(
            $this->leaseId,
            'Ruido en zona común',
            '2026-10-13',
            'Se reportó ruido fuera del horario permitido.',
            $this->actorId,
            1_800_000_000,
        );
        self::assertSame('open', $incident['status']);
        self::assertSame($this->leaseId, $incident['lease_id']);
        self::assertSame($this->actorId, $incident['created_by_user_id']);
        self::assertNull($incident['resolution_notes']);
        self::assertSame($incident, $service->find((int) $incident['id']));
        self::assertCount(1, $service->listForLease($this->leaseId));
        self::assertCount(1, $service->listForStaff());
        self::assertCount(1, $service->listForStaff('open'));
        self::assertSame([], $service->listForStaff('invalid'));

        $reviewed = $service->markReviewed((int) $incident['id'], $this->actorId, 1_800_000_010);
        self::assertSame('reviewed', $reviewed['status']);
        self::assertSame('2027-01-15 08:00:10', $reviewed['updated_at']);
        $this->assertRejected(fn () => $service->markReviewed((int) $incident['id'], $this->actorId, 1_800_000_011));

        $closed = $service->close(
            (int) $incident['id'],
            'Se conversó con las personas involucradas y se acordó evitar nuevas molestias.',
            $this->actorId,
            1_800_000_020,
        );
        self::assertSame('closed', $closed['status']);
        self::assertSame($this->actorId, $closed['closed_by_user_id']);
        self::assertSame('2027-01-15 08:00:20', $closed['closed_at']);
        self::assertSame($closed['closed_at'], $closed['updated_at']);
        $this->assertRejected(fn () => $service->close((int) $incident['id'], 'Repetido', $this->actorId, 1_800_000_021));
        $this->assertRejected(fn () => $service->markReviewed((int) $incident['id'], $this->actorId, 1_800_000_022));

        $voided = $service->void((int) $incident['id'], 'Se confirmó que el reporte se registró por error.', $this->actorId, 1_800_000_030);
        self::assertSame('voided', $voided['status']);
        self::assertSame($closed['resolution_notes'], $voided['resolution_notes']);
        self::assertSame($this->actorId, $voided['voided_by_user_id']);
        self::assertSame('2027-01-15 08:00:30', $voided['voided_at']);
        $this->assertRejected(fn () => $service->void((int) $incident['id'], 'Repetido', $this->actorId, 1_800_000_031));
        $this->assertRejected(fn () => $service->close((int) $incident['id'], 'No reabrir.', $this->actorId, 1_800_000_032));
    }

    public function testIncidentInputAndLifecycleTextAreBoundedAndValidated(): void
    {
        $service = new CommunityService($this->pdo);
        $valid = fn (array $override = []): array => array_replace([
            'lease_id' => $this->leaseId,
            'category' => 'Ruido',
            'occurred_on' => '2026-10-14',
            'description' => 'Descripción válida del incidente.',
            'actor_id' => $this->actorId,
            'now' => 1_800_000_100,
        ], $override);
        $create = fn (array $input): array => $service->create(
            $input['lease_id'],
            $input['category'],
            $input['occurred_on'],
            $input['description'],
            $input['actor_id'],
            $input['now'],
        );

        foreach ([
            $valid(['lease_id' => 0]),
            $valid(['category' => '   ']),
            $valid(['category' => str_repeat('c', 81)]),
            $valid(['occurred_on' => '2026-02-30']),
            $valid(['occurred_on' => '14/10/2026']),
            $valid(['description' => '   ']),
            $valid(['description' => str_repeat('d', 5001)]),
            $valid(['actor_id' => 0]),
        ] as $input) {
            $this->assertRejected(fn () => $create($input));
        }

        $incident = $create($valid());
        $this->assertRejected(fn () => $service->close((int) $incident['id'], ' ', $this->actorId, 1_800_000_110));
        $this->assertRejected(fn () => $service->close((int) $incident['id'], str_repeat('r', 5001), $this->actorId, 1_800_000_111));
        $this->assertRejected(fn () => $service->void((int) $incident['id'], ' ', $this->actorId, 1_800_000_112));
        $this->assertRejected(fn () => $service->void((int) $incident['id'], str_repeat('v', 1001), $this->actorId, 1_800_000_113));
        self::assertSame('open', $service->find((int) $incident['id'])['status']);
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected the community operation to be rejected.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }
}
