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

final class NotificationCronRoutesTest extends TestCase
{
    private const CRON_SECRET = 'cron-secret-for-test-that-is-long-enough-2026';

    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private int $jobId;
    /** @var list<string> */
    private array $sentTo = [];

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
        $this->directory = sys_get_temp_dir() . '/alquila_notification_cron_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-14T00:00:00-05:00',
        ]);

        $now = gmdate('Y-m-d H:i:s');
        $service = $this->notificationService();
        $this->jobId = (int) $service->queue(
            'candidate_created',
            'recipient@example.test',
            'Recipient',
            'Solicitud recibida',
            'candidate_created',
            ['event_date' => substr($now, 0, 10)],
            gmdate('Y-m-d H:i:s', time() - 60),
            'candidate:42:created:v1',
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testMissingOrWrongSecretCannotMutateOrSendJobs(): void
    {
        $app = $this->app();

        foreach ([[null, false], ['incorrect-secret', true]] as [$secret, $includeCsrf]) {
            $response = $this->request($app, $secret, '/cron/notifications', $includeCsrf);
            self::assertSame(403, $response->getStatusCode());
            self::assertSame(0, $this->attemptCount());
            self::assertSame([], $this->sentTo);
        }
    }

    public function testValidCronSecretAllowsExactCsrfExemptionAndReturnsOnlySafeCounts(): void
    {
        $response = $this->request($this->app(), self::CRON_SECRET);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['generated' => 0, 'sent' => 1, 'failed' => 0], json_decode((string) $response->getBody(), true));
        self::assertSame(['recipient@example.test'], $this->sentTo);
        self::assertSame(1, $this->attemptCount());
        self::assertSame('sent', $this->jobState());
        self::assertStringNotContainsString('recipient@example.test', (string) $response->getBody());
        self::assertStringNotContainsString(self::CRON_SECRET, (string) $response->getBody());
    }

    public function testCsrfExemptionDoesNotExtendToTrailingSlashOrOtherPostRoutes(): void
    {
        $app = $this->app();
        self::assertSame(403, $this->request($app, self::CRON_SECRET, '/cron/notifications/')->getStatusCode());
        $otherPost = (new ServerRequestFactory())->createServerRequest('POST', 'https://alquilapro.test/login');
        self::assertSame(403, $app->handle($otherPost)->getStatusCode());
        self::assertSame(0, $this->attemptCount());
        self::assertSame([], $this->sentTo);
    }

    public function testProductionBootstrapRejectsMissingSmtpConfiguration(): void
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        try {
            $factory([
                'lock_path' => $this->lockPath,
                'database_config_path' => $this->directory . '/database.php',
                'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
                'base_url' => 'https://alquilapro.test',
                'pdo' => $this->pdo,
                'notification_settings' => [],
            ]);
            self::fail('Production bootstrap must require SMTP and cron settings.');
        } catch (\RuntimeException $exception) {
            self::assertSame('La configuración SMTP de notificaciones no es válida.', $exception->getMessage());
        }
    }

    public function testValidProductionSmtpSettingsRegisterTheCronEndpoint(): void
    {
        $this->pdo->exec('DELETE FROM notification_jobs');
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        $app = $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.test',
            'pdo' => $this->pdo,
            'notification_settings' => [
                'host' => 'smtp.example.test',
                'port' => '587',
                'username' => 'mailer@example.test',
                'password' => 'smtp-test-password',
                'encryption' => 'tls',
                'from_email' => 'noreply@example.test',
                'from_name' => 'AlquilaPro',
                'cron_secret' => self::CRON_SECRET,
            ],
        ]);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://alquilapro.test/cron/notifications')
            ->withHeader('X-AlquilaPro-Cron-Secret', self::CRON_SECRET);

        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['generated' => 0, 'sent' => 0, 'failed' => 0], json_decode((string) $response->getBody(), true));
    }

    private function app(): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.test',
            'base_path' => '',
            'pdo' => $this->pdo,
            'notifications' => $this->notificationService(),
            'notification_cron_secret' => self::CRON_SECRET,
        ]);
    }

    private function notificationService(): NotificationService
    {
        return new NotificationService($this->pdo, function (string $email): void {
            $this->sentTo[] = $email;
        });
    }

    private function request(
        App $app,
        ?string $secret,
        string $path = '/cron/notifications',
        bool $includeCsrf = false,
    ): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://alquilapro.test' . $path);
        if ($includeCsrf) {
            $request = $request->withParsedBody(['csrf_token' => (new \AlquilaPro\Auth\Csrf())->token()]);
        }
        if ($secret !== null) {
            $request = $request->withHeader('X-AlquilaPro-Cron-Secret', $secret);
        }
        return $app->handle($request);
    }

    private function attemptCount(): int
    {
        $query = $this->pdo->prepare('SELECT attempt_count FROM notification_jobs WHERE id = :id');
        $query->execute(['id' => $this->jobId]);
        return (int) $query->fetchColumn();
    }

    private function jobState(): string
    {
        $query = $this->pdo->prepare('SELECT sent_at, failed_at FROM notification_jobs WHERE id = :id');
        $query->execute(['id' => $this->jobId]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        return $row['sent_at'] !== null ? 'sent' : ($row['failed_at'] !== null ? 'failed' : 'pending');
    }
}
