<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Notifications;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Notifications\NotificationService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class NotificationPrivacyRetentionTest extends TestCase
{
    private const CRON_SECRET = 'privacy-retention-cron-secret-long-enough-2026';
    private const NOW = '2026-10-15 12:00:00';
    private const CUTOFF = '2026-07-17 12:00:00';

    private PDO $pdo;
    private string $directory;
    private string $lockPath;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]));
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $safe = str_replace('`', '``', (string) $table);
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safe));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_notification_privacy_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '22',
            'installed_at' => '2026-10-15T00:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testTerminalRecipientsOlderThanNinetyDaysAreRedactedOnly(): void
    {
        $service = $this->service();
        $oldSent = $this->queueAndTerminalize($service, 1, sentAt: '2026-07-17 11:59:59');
        $oldFailed = $this->queueAndTerminalize($service, 2, failedAt: '2026-07-16 12:00:00');
        $exactCutoff = $this->queueAndTerminalize($service, 3, sentAt: self::CUTOFF);
        $newFailed = $this->queueAndTerminalize($service, 4, failedAt: '2026-07-17 12:00:01');
        $pending = $this->queueJob($service, 5);

        $before = $this->rowsById([$oldSent, $oldFailed, $exactCutoff, $newFailed, $pending]);

        self::assertSame(2, $service->redactTerminalRecipients(self::NOW));
        self::assertSame(0, $service->redactTerminalRecipients(self::NOW), 'Redaction must be idempotent.');

        $after = $this->rowsById([$oldSent, $oldFailed, $exactCutoff, $newFailed, $pending]);
        foreach ([$oldSent, $oldFailed] as $id) {
            self::assertNull($after[$id]['recipient_email']);
            self::assertNull($after[$id]['recipient_name']);
        }
        foreach ([$exactCutoff, $newFailed, $pending] as $id) {
            self::assertSame($before[$id]['recipient_email'], $after[$id]['recipient_email']);
            self::assertSame($before[$id]['recipient_name'], $after[$id]['recipient_name']);
        }

        foreach ($before as $id => $row) {
            unset($row['recipient_email'], $row['recipient_name']);
            $operational = $after[$id];
            unset($operational['recipient_email'], $operational['recipient_name']);
            self::assertSame($row, $operational, 'Retention may redact recipient snapshots only.');
        }
    }

    public function testExistingProtectedCronRunsRetentionAndReturnsOnlySafeCount(): void
    {
        $service = $this->service();
        $dueId = $this->queueJobAt($service, 10, gmdate('Y-m-d H:i:s', time() - 60));
        $oldId = $this->queueJobAt($service, 11, gmdate('Y-m-d H:i:s', time() - 120));
        $this->pdo->prepare(
            'UPDATE notification_jobs SET sent_at = :sent_at, attempt_count = 1 WHERE id = :id',
        )->execute([
            'sent_at' => gmdate('Y-m-d H:i:s', time() - (91 * 86400)),
            'id' => $oldId,
        ]);

        $response = $this->requestCron($this->app($service));
        $payload = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $payload['generated']);
        self::assertSame(1, $payload['sent']);
        self::assertSame(0, $payload['failed']);
        self::assertSame(1, $payload['redacted']);
        self::assertSame(['failed', 'generated', 'redacted', 'sent'], array_values(array_unique(array_keys($payload))));
        self::assertNull($this->row($oldId)['recipient_email']);
        self::assertNull($this->row($oldId)['recipient_name']);
        self::assertSame('recipient10@example.test', $this->row($dueId)['recipient_email']);
        self::assertStringNotContainsString('recipient11@example.test', (string) $response->getBody());
        self::assertStringNotContainsString(self::CRON_SECRET, (string) $response->getBody());
    }

    private function service(): NotificationService
    {
        return new NotificationService($this->pdo, static function (): void {
        });
    }

    private function queueAndTerminalize(
        NotificationService $service,
        int $suffix,
        ?string $sentAt = null,
        ?string $failedAt = null,
    ): int {
        $id = $this->queueJob($service, $suffix);
        $statement = $this->pdo->prepare(
            'UPDATE notification_jobs SET sent_at = :sent_at, failed_at = :failed_at, '
            . 'attempt_count = :attempt_count, last_error = :last_error WHERE id = :id',
        );
        $statement->execute([
            'sent_at' => $sentAt,
            'failed_at' => $failedAt,
            'attempt_count' => $failedAt === null ? 1 : 3,
            'last_error' => $failedAt === null ? null : 'attempts_exhausted',
            'id' => $id,
        ]);
        return $id;
    }

    private function queueJob(NotificationService $service, int $suffix): int
    {
        return $this->queueJobAt($service, $suffix, '2026-01-01 00:00:00');
    }

    private function queueJobAt(NotificationService $service, int $suffix, string $scheduledAt): int
    {
        $id = $service->queue(
            'candidate_created',
            'recipient' . $suffix . '@example.test',
            'Recipient ' . $suffix,
            'Solicitud recibida ' . $suffix,
            'candidate_created',
            ['event_date' => substr($scheduledAt, 0, 10)],
            $scheduledAt,
            'privacy-retention:' . $suffix . ':' . bin2hex(random_bytes(4)),
        );
        self::assertNotNull($id);
        return (int) $id;
    }

    /** @param list<int> $ids @return array<int,array<string,mixed>> */
    private function rowsById(array $ids): array
    {
        $rows = [];
        foreach ($ids as $id) {
            $rows[$id] = $this->row($id);
        }
        return $rows;
    }

    /** @return array<string,mixed> */
    private function row(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT type, recipient_email, recipient_name, subject, template_key, payload_json, scheduled_at, dedupe_key, '
            . 'attempt_count, last_attempt_at, claim_until, sent_at, failed_at, last_error, created_at '
            . 'FROM notification_jobs WHERE id = :id LIMIT 1',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        return $row;
    }

    private function app(NotificationService $service): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.test',
            'base_path' => '',
            'pdo' => $this->pdo,
            'notifications' => $service,
            'notification_cron_secret' => self::CRON_SECRET,
        ]);
    }

    private function requestCron(App $app): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://alquilapro.test/cron/notifications')
            ->withHeader('X-AlquilaPro-Cron-Secret', self::CRON_SECRET);
        return $app->handle($request);
    }
}
