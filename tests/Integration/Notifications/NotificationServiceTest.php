<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Notifications;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Notifications\NotificationService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotificationServiceTest extends TestCase
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

    public function testQueueIsValidatedIdempotentAndDoesNotSendInline(): void
    {
        $sendCount = 0;
        $service = new NotificationService($this->pdo, static function () use (&$sendCount): void {
            $sendCount++;
        });
        $now = gmdate('Y-m-d H:i:s', time());
        $arguments = [
            'candidate_created',
            'tenant@example.test',
            'Ana',
            'Bienvenida',
            'candidate_created',
            ['event_date' => substr($now, 0, 10)],
            $now,
            'candidate:17:created:v1',
        ];

        $id = $service->queue(...$arguments);
        self::assertGreaterThan(0, $id);
        self::assertNull($service->queue(...$arguments));
        self::assertSame(0, $sendCount);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM notification_jobs')->fetchColumn());
        self::assertSame('pending', $this->derivedState((int) $id));

        $this->assertRejected(fn () => $service->queue(
            'candidate_created', 'not-an-email', 'Ana', 'Bienvenida', 'candidate_created', [], $now,
            'candidate:18:created:v1',
        ));
    }

    public function testProcessDueUsesInjectedTransportAndDerivesSentState(): void
    {
        $sent = [];
        $transport = static function (
            string $email,
            string $name,
            string $subject,
            string $htmlBody,
            string $textBody,
        ) use (&$sent): void {
            $sent[] = compact('email', 'name', 'subject', 'htmlBody', 'textBody');
        };
        $service = new NotificationService($this->pdo, $transport);
        $now = gmdate('Y-m-d H:i:s', time());
        $id = $service->queue(
            'candidate_created', 'tenant@example.test', 'Ana', 'Bienvenida', 'candidate_created',
            ['event_date' => gmdate('Y-m-d')], gmdate('Y-m-d H:i:s', time() - 60), 'candidate:17:created:v1',
        );

        self::assertSame(['sent' => 1, 'failed' => 0], $service->processDue(10, $now));
        self::assertSame(1, count($sent));
        self::assertSame('tenant@example.test', $sent[0]['email']);
        self::assertSame('Bienvenida', $sent[0]['subject']);
        self::assertSame('sent', $this->derivedState((int) $id));
        self::assertSame(1, (int) $this->row((int) $id)['attempt_count']);
    }

    public function testClaimsOnlyConsumeAttemptsWhenSentAndProtectAgainstLateConcurrentCron(): void
    {
        $nowEpoch = time();
        $now = gmdate('Y-m-d H:i:s', $nowEpoch);
        $firstId = $this->queueJob(31, $nowEpoch);
        $secondId = $this->queueJob(32, $nowEpoch);
        $observedAttempts = [];
        $lateRun = null;
        $secondarySends = 0;
        $primarySends = 0;
        $secondary = new NotificationService($this->pdo, static function () use (&$secondarySends): void {
            $secondarySends++;
        });
        $primary = new NotificationService($this->pdo, function () use (
            &$observedAttempts,
            &$lateRun,
            &$primarySends,
            $secondary,
            $nowEpoch,
        ): void {
            if ($primarySends === 0) {
                $observedAttempts = array_map(
                    'intval',
                    $this->pdo->query('SELECT attempt_count FROM notification_jobs ORDER BY id')->fetchAll(PDO::FETCH_COLUMN),
                );
                $lateRun = $secondary->processDue(10, gmdate('Y-m-d H:i:s', $nowEpoch + 301));
            }
            $primarySends++;
        });

        self::assertSame(['sent' => 1, 'failed' => 0], $primary->processDue(10, $now));
        self::assertSame([1, 0], $observedAttempts, 'The unprocessed second job must not consume an attempt.');
        self::assertSame(['sent' => 1, 'failed' => 0], $lateRun);
        self::assertSame(1, $secondarySends, 'A later cron may process an unclaimed job but cannot duplicate the leased send.');
        self::assertSame(1, $primarySends);
        self::assertSame(1, (int) $this->row($firstId)['attempt_count']);
        self::assertSame(1, (int) $this->row($secondId)['attempt_count']);
    }

    public function testExpiredExhaustedClaimBecomesTerminalInsteadOfStayingPending(): void
    {
        $nowEpoch = time();
        $id = $this->queueJob(33, $nowEpoch);
        $now = gmdate('Y-m-d H:i:s', $nowEpoch);
        $this->pdo->prepare(
            'UPDATE notification_jobs SET attempt_count = 3, last_attempt_at = :last_attempt, claim_until = :expired '
            . 'WHERE id = :id',
        )->execute([
            'last_attempt' => gmdate('Y-m-d H:i:s', $nowEpoch - 900),
            'expired' => gmdate('Y-m-d H:i:s', $nowEpoch - 1),
            'id' => $id,
        ]);
        $sent = 0;
        $service = new NotificationService($this->pdo, static function () use (&$sent): void {
            $sent++;
        });

        self::assertSame(['sent' => 0, 'failed' => 1], $service->processDue(10, $now));
        self::assertSame(0, $sent);
        self::assertSame('failed', $this->derivedState($id));
        self::assertSame(3, (int) $this->row($id)['attempt_count']);
    }

    public function testTransportFailuresAreSanitizedRetriedAndCapped(): void
    {
        $service = new NotificationService($this->pdo, static function (): void {
            throw new RuntimeException('SMTP password=secret-value');
        });
        $nowEpoch = time();
        $id = $service->queue(
            'candidate_created', 'tenant@example.test', 'Ana', 'Bienvenida', 'candidate_created',
            ['event_date' => gmdate('Y-m-d')], gmdate('Y-m-d H:i:s', $nowEpoch - 60), 'candidate:18:created:v1',
        );
        $now = gmdate('Y-m-d H:i:s', $nowEpoch);

        self::assertSame(['sent' => 0, 'failed' => 1], $service->processDue(10, $now));
        $first = $this->row((int) $id);
        self::assertSame(1, (int) $first['attempt_count']);
        self::assertSame('transport_error', $first['last_error']);
        self::assertStringNotContainsString('secret-value', (string) $first['last_error']);
        self::assertNull($first['failed_at']);
        self::assertSame('pending', $this->derivedState((int) $id));

        self::assertSame(['sent' => 0, 'failed' => 0], $service->processDue(10, $now));
        self::assertSame(1, (int) $this->row((int) $id)['attempt_count']);

        $secondNow = gmdate('Y-m-d H:i:s', $nowEpoch + 300);
        self::assertSame(['sent' => 0, 'failed' => 1], $service->processDue(10, $secondNow));
        self::assertSame(2, (int) $this->row((int) $id)['attempt_count']);

        $thirdNow = gmdate('Y-m-d H:i:s', $nowEpoch + 600);
        self::assertSame(['sent' => 0, 'failed' => 1], $service->processDue(10, $thirdNow));
        self::assertSame(3, (int) $this->row((int) $id)['attempt_count']);
        self::assertNotNull($this->row((int) $id)['failed_at']);
        self::assertSame('failed', $this->derivedState((int) $id));
        self::assertSame(['sent' => 0, 'failed' => 0], $service->processDue(10, $thirdNow));
    }

    /** @return array<string,mixed> */
    private function row(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM notification_jobs WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        return $row;
    }

    private function queueJob(int $candidateId, int $nowEpoch): int
    {
        $service = new NotificationService($this->pdo, static function (): void {
        });
        $id = $service->queue(
            'candidate_created',
            'tenant@example.test',
            'Ana',
            'Solicitud recibida',
            'candidate_created',
            ['event_date' => gmdate('Y-m-d', $nowEpoch)],
            gmdate('Y-m-d H:i:s', $nowEpoch - 60),
            'candidate:' . $candidateId . ':created:v1',
        );
        self::assertNotNull($id);
        return $id;
    }

    private function derivedState(int $id): string
    {
        $row = $this->row($id);
        if ($row['sent_at'] !== null) {
            return 'sent';
        }
        return $row['failed_at'] !== null ? 'failed' : 'pending';
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected notification queue operation to be rejected.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }
}
