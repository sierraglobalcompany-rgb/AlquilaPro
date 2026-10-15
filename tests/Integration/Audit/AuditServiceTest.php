<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Audit;

use AlquilaPro\Audit\AuditService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuditServiceTest extends TestCase
{
    private PDO $pdo;
    private int $actorId;

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
            $safe = str_replace('`', '``', (string) $table);
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safe));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
        $this->actorId = $this->insertActor();
    }

    public function testAuditServiceContractExists(): void
    {
        self::assertTrue(class_exists(AuditService::class), 'F22 requires AuditService.');
        if (!class_exists(AuditService::class)) {
            return;
        }
        self::assertTrue(method_exists(AuditService::class, 'record'));
        self::assertTrue(method_exists(AuditService::class, 'listRecent'));
        self::assertFalse(method_exists(AuditService::class, 'update'));
        self::assertFalse(method_exists(AuditService::class, 'delete'));
    }

    public function testRecordStoresBoundedSafeEvidenceAndInvalidIpBecomesNull(): void
    {
        $service = $this->serviceOrSkip();
        $service->record(
            $this->actorId,
            'lease.ended',
            'lease',
            321,
            'Arrendamiento finalizado',
            '203.0.113.8',
            ['state' => 'ended', 'flags' => ['manual', 'staff']],
        );
        $service->record(
            null,
            'appearance.updated',
            'appearance',
            null,
            'Apariencia actualizada',
            'not-an-ip',
        );

        $rows = $service->listRecent(10);
        self::assertCount(2, $rows);
        self::assertSame('appearance.updated', $rows[0]['action']);
        self::assertNull($rows[0]['ip_address']);
        self::assertSame('lease.ended', $rows[1]['action']);
        self::assertSame('203.0.113.8', $rows[1]['ip_address']);
        self::assertSame(['state' => 'ended', 'flags' => ['manual', 'staff']], $rows[1]['metadata']);
    }

    public function testUnsafeOrOversizeMetadataFailsWithoutWritingRow(): void
    {
        $service = $this->serviceOrSkip();
        foreach ([
            ['password' => 'secret'],
            ['token_value' => 'abc'],
            ['nested' => [['bad' => 'object']]],
            ['oversize' => str_repeat('x', 2100)],
        ] as $metadata) {
            $this->assertRejected(fn () => $service->record(
                $this->actorId,
                'user.active_changed',
                'user',
                $this->actorId,
                'Estado de usuario actualizado',
                '2001:db8::1',
                $metadata,
            ));
        }
        self::assertSame(0, $this->auditCount());
    }

    public function testInvalidKeysSummaryEntityAndListLimitFailClosed(): void
    {
        $service = $this->serviceOrSkip();
        foreach ([
            fn () => $service->record($this->actorId, 'Bad Action', 'user', 1, 'Resumen', null),
            fn () => $service->record($this->actorId, 'user.created', 'Bad Entity', 1, 'Resumen', null),
            fn () => $service->record($this->actorId, 'user.created', 'user', 0, 'Resumen', null),
            fn () => $service->record($this->actorId, 'user.created', 'user', 1, '', null),
            fn () => $service->record($this->actorId, 'user.created', 'user', 1, str_repeat('x', 256), null),
            fn () => $service->listRecent(0),
            fn () => $service->listRecent(101),
        ] as $operation) {
            $this->assertRejected($operation);
        }
        self::assertSame(0, $this->auditCount());
    }

    private function serviceOrSkip(): AuditService
    {
        if (!class_exists(AuditService::class)) {
            self::markTestSkipped('F22 AuditService is not implemented yet.');
        }
        return new AuditService($this->pdo);
    }

    private function insertActor(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) VALUES (?, ?, ?, ?, 1)',
        );
        $statement->execute([
            'owner',
            'Audit Owner',
            'audit-owner-' . bin2hex(random_bytes(4)) . '@example.com',
            password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function auditCount(): int
    {
        try {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (RuntimeException) {
            self::assertTrue(true);
            return;
        }
        self::fail('Expected F22 audit operation to fail closed.');
    }
}
