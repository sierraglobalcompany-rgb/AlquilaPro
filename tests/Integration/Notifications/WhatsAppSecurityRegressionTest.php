<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Notifications;

use AlquilaPro\Auth\Permissions;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class WhatsAppSecurityRegressionTest extends TestCase
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

    public function testWhatsAppArchitectureHasOneTableOneServiceAndOnePermission(): void
    {
        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables "
            . "WHERE table_schema = DATABASE() AND table_name LIKE 'whatsapp_%' ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['whatsapp_actions'], $tables);

        $columns = $this->pdo->query(
            "SELECT column_name FROM information_schema.columns "
            . "WHERE table_schema = DATABASE() AND table_name = 'whatsapp_actions' ORDER BY ordinal_position",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([
            'id', 'context_type', 'context_id', 'recipient_phone_snapshot', 'template_key', 'message_snapshot',
            'url_snapshot', 'created_by_user_id', 'created_at', 'opened_by_user_id', 'opened_at',
        ], $columns);
        foreach (['sent', 'delivered', 'read', 'provider_message_id'] as $forbidden) {
            self::assertDoesNotMatchRegularExpression(
                '/\b' . preg_quote($forbidden, '/') . '\b/i',
                (string) file_get_contents(dirname(__DIR__, 3) . '/database/migrations/017_create_whatsapp_actions.sql'),
            );
        }

        $notificationFiles = array_map('basename', glob(dirname(__DIR__, 3) . '/app/Notifications/*.php') ?: []);
        sort($notificationFiles);
        self::assertSame(['NotificationService.php', 'WhatsAppService.php', 'routes.php'], $notificationFiles);
        self::assertSame(
            ['whatsapp.use'],
            Permissions::sanitize([
                'whatsapp.use', 'whatsapp.send', 'whatsapp.manage', 'whatsapp.view', 'whatsapp.provider',
            ]),
        );
    }

    public function testWhatsAppAddsNoProviderDependencyOrDeliveryStateStrings(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'), true);
        self::assertIsArray($composer);
        $runtimePackages = array_values(array_filter(
            array_keys($composer['require'] ?? []),
            static fn (string $package): bool => $package !== 'php',
        ));
        sort($runtimePackages);
        self::assertSame(['dompdf/dompdf', 'phpmailer/phpmailer', 'slim/psr7', 'slim/slim', 'smalot/pdfparser'], $runtimePackages);

        $service = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Notifications/WhatsAppService.php');
        self::assertDoesNotMatchRegularExpression(
            '/\b(?:sent_at|delivered_at|read_at|provider_message_id|webhook|twilio)\b/i',
            $service,
        );

        $routes = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Notifications/routes.php');
        preg_match_all('/\$app->(get|post)\(\'([^\']+)\'/i', $routes, $matches, PREG_SET_ORDER);
        $registered = array_map(
            static fn (array $match): array => [strtoupper($match[1]), $match[2]],
            $matches,
        );
        self::assertSame([
            ['POST', '/cron/notifications'],
            ['GET', '/admin/whatsapp'],
            ['POST', '/admin/whatsapp'],
            ['POST', '/admin/whatsapp/{id}/open'],
        ], $registered);
        self::assertStringContainsString('Permissions::allows($user, \'whatsapp.use\')', $routes);
    }

    public function testWhatsAppProviderSurfaceCannotEmbedCandidateContinuationCapabilities(): void
    {
        $service = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Notifications/WhatsAppService.php');
        $routes = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Notifications/routes.php');
        $surface = $service . "\n" . $routes;

        self::assertStringNotContainsString('continuation_token', $surface);
        self::assertStringNotContainsString('/apply/continue/', $surface);
        self::assertStringNotContainsString('token=', $surface);
    }
}
