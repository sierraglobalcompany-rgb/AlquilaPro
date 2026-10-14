<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Notifications;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class NotificationSecurityRegressionTest extends TestCase
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
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
    }

    public function testNotificationArchitectureStaysWithinTheApprovedBudget(): void
    {
        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables "
            . "WHERE table_schema = DATABASE() AND table_name LIKE 'notification_%' ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['notification_jobs'], $tables);

        $columns = $this->pdo->query(
            "SELECT column_name FROM information_schema.columns "
            . "WHERE table_schema = DATABASE() AND table_name = 'notification_jobs' ORDER BY ordinal_position",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertNotContains('status', $columns);

        $notificationFiles = glob(dirname(__DIR__, 3) . '/app/Notifications/*.php') ?: [];
        $notificationFiles = array_map('basename', $notificationFiles);
        sort($notificationFiles);
        self::assertSame(['NotificationService.php', 'routes.php'], $notificationFiles);

        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'), true);
        self::assertIsArray($composer);
        $mailDependencies = array_values(array_filter(
            array_keys($composer['require'] ?? []),
            static fn (string $package): bool => str_contains(strtolower($package), 'mail'),
        ));
        self::assertSame(['phpmailer/phpmailer'], $mailDependencies);
    }

    public function testCandidateRoutesOnlyQueueEventsAndNeverInvokeEmailTransport(): void
    {
        $candidateRoutes = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Candidates/routes.php');
        $candidateService = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Candidates/CandidateService.php');

        self::assertDoesNotMatchRegularExpression('/\bPHPMailer\b|\bisSMTP\s*\(|\bmail\s*\(/i', $candidateRoutes);
        self::assertStringNotContainsString('queueCandidateEvent(', $candidateRoutes);
        self::assertSame(2, substr_count($candidateService, 'queueCandidateEvent('));
    }
}
