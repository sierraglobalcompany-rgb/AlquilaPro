<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Notifications;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Notifications\WhatsAppService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WhatsAppServiceTest extends TestCase
{
    private PDO $pdo;
    private int $actorId;
    private int $otherActorId;

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
        $this->actorId = $this->createActor('Actor One', 'whatsapp-one@example.test');
        $this->otherActorId = $this->createActor('Actor Two', 'whatsapp-two@example.test');
    }

    public function testPrepareStoresColombianPhoneMessageAndSafeWaMeSnapshot(): void
    {
        $now = time();
        $service = $this->service();
        $action = $service->prepare(
            'candidate', 41, '(300) 123-4567', 'candidate_follow_up', ['name' => 'Ana María'], $this->actorId, $now,
        );

        self::assertGreaterThan(0, $action['id']);
        self::assertSame('candidate', $action['context_type']);
        self::assertSame(41, $action['context_id']);
        self::assertSame('573001234567', $action['recipient_phone_snapshot']);
        self::assertSame('candidate_follow_up', $action['template_key']);
        self::assertSame(
            'Hola Ana María, te escribimos sobre tu solicitud de arrendamiento en AlquilaPro.',
            $action['message_snapshot'],
        );
        self::assertSame(
            'https://wa.me/573001234567?text=' . rawurlencode($action['message_snapshot']),
            $action['url_snapshot'],
        );
        self::assertSame($this->actorId, $action['created_by_user_id']);
        self::assertSame(gmdate('Y-m-d H:i:s', $now), $action['created_at']);
        self::assertNull($action['opened_by_user_id']);
        self::assertNull($action['opened_at']);
    }

    public function testAcceptsInternationalNumberAndRejectsMalformedPhoneInput(): void
    {
        $service = $this->service();
        $international = $service->prepare(
            'candidate', 42, '+57 300 123 4567', 'candidate_follow_up', ['name' => 'Ana'], $this->actorId, time(),
        );
        self::assertSame('573001234567', $international['recipient_phone_snapshot']);

        foreach (['', '   ', 'javascript:alert(1)', '3001234567?text=bad', '+0123456789', '0123456789', '1234567', '1234567890123456'] as $phone) {
            try {
                $service->prepare(
                    'candidate', 43, $phone, 'candidate_follow_up', ['name' => 'Ana'], $this->actorId, time(),
                );
            } catch (RuntimeException $exception) {
                self::assertSame('No fue posible preparar la acción de WhatsApp.', $exception->getMessage());
                continue;
            }
            self::fail('Malformed phone input must be rejected.');
        }
    }

    public function testTemplatesRequireTheirExactContextAndVariableKeys(): void
    {
        $service = $this->service();
        $this->assertRejected(fn () => $service->prepare(
            'lease', 1, '3001234567', 'candidate_follow_up', ['name' => 'Ana'], $this->actorId, time(),
        ));
        $this->assertRejected(fn () => $service->prepare(
            'candidate', 1, '3001234567', 'candidate_follow_up', [], $this->actorId, time(),
        ));
        $this->assertRejected(fn () => $service->prepare(
            'candidate', 1, '3001234567', 'candidate_follow_up', ['name' => 'Ana', 'extra' => 'ignored'], $this->actorId, time(),
        ));
        $this->assertRejected(fn () => $service->prepare(
            'candidate', 1, '3001234567', 'unknown_template', ['name' => 'Ana'], $this->actorId, time(),
        ));
        $this->assertRejected(fn () => $service->prepare(
            'unknown_context', 1, '3001234567', 'candidate_follow_up', ['name' => 'Ana'], $this->actorId, time(),
        ));
    }

    public function testMaintenanceTemplateRequiresItsAllowlistedCategoryVariable(): void
    {
        $action = $this->service()->prepare(
            'maintenance_request',
            71,
            '3001234567',
            'maintenance_follow_up',
            ['name' => 'Lucía', 'category' => 'Plomería'],
            $this->actorId,
            time(),
        );

        self::assertSame('maintenance_request', $action['context_type']);
        self::assertSame(
            'Hola Lucía, te contactamos sobre la solicitud de mantenimiento de Plomería.',
            $action['message_snapshot'],
        );
    }

    public function testMarkOpenedRecordsFirstActorAndTimeWithoutDeliveryState(): void
    {
        $service = $this->service();
        $prepared = $service->prepare(
            'lease', 52, '3001234567', 'lease_contact', ['name' => 'Carlos'], $this->actorId, 1_792_000_000,
        );
        $opened = $service->markOpened((int) $prepared['id'], $this->otherActorId, 1_792_000_100);
        self::assertSame($this->otherActorId, $opened['opened_by_user_id']);
        self::assertSame(gmdate('Y-m-d H:i:s', 1_792_000_100), $opened['opened_at']);

        $reopened = $service->markOpened((int) $prepared['id'], $this->actorId, 1_792_000_200);
        self::assertSame($this->otherActorId, $reopened['opened_by_user_id']);
        self::assertSame($opened['opened_at'], $reopened['opened_at']);
        self::assertArrayNotHasKey('sent_at', $reopened);
        self::assertArrayNotHasKey('delivered_at', $reopened);
        self::assertArrayNotHasKey('read_at', $reopened);
    }

    public function testFindAndRecentHistoryReturnOnlyRequestedBoundedRows(): void
    {
        $service = $this->service();
        $ids = [];
        foreach ([60, 61, 62] as $contextId) {
            $row = $service->prepare(
                'candidate', $contextId, '3001234567', 'candidate_follow_up', ['name' => 'Ana'],
                $this->actorId, 1_792_000_000 + $contextId,
            );
            $ids[] = (int) $row['id'];
        }

        self::assertNull($service->find(999999));
        self::assertSame($ids[2], $service->find($ids[2])['id']);
        self::assertSame([$ids[2], $ids[1]], array_column($service->listRecent(2), 'id'));
        $this->assertRejected(fn () => $service->listRecent(101));
        $this->assertRejected(fn () => $service->listRecent(0));
    }

    private function service(): WhatsAppService
    {
        self::assertTrue(class_exists(WhatsAppService::class), 'WhatsAppService must exist for these behaviors.');
        return new WhatsAppService($this->pdo);
    }

    private function createActor(string $name, string $email): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (\'owner\', :name, :email, :password_hash, 1)',
        );
        $statement->execute(['name' => $name, 'email' => $email, 'password_hash' => password_hash('test', PASSWORD_DEFAULT)]);
        return (int) $this->pdo->lastInsertId();
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (RuntimeException $exception) {
            self::assertSame('No fue posible preparar la acción de WhatsApp.', $exception->getMessage());
            return;
        }
        self::fail('Invalid WhatsApp action input must be rejected.');
    }
}
