<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Memberships;

use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Memberships\MembershipService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MembershipServiceTest extends TestCase
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
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', str_replace('`', '``', (string) $table)));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $user = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (\'owner\', \'Membership Actor\', :email, :hash, 1)',
        );
        $user->execute([
            'email' => 'membership-actor-' . bin2hex(random_bytes(5)) . '@example.test',
            'hash' => password_hash('MembershipPassword123!', PASSWORD_DEFAULT),
        ]);
        $this->actorId = (int) $this->pdo->lastInsertId();
    }

    public function testCatalogCreatesFindsListsAndUpdatesWithoutDeletingHistory(): void
    {
        $service = $this->service();
        $first = $service->createService(
            '  Asistencia digital  ', '  Soporte complementario  ', '00120.5', 30, $this->actorId, 1_800_000_000,
        );

        self::assertSame('Asistencia digital', $first['name']);
        self::assertSame('Soporte complementario', $first['description']);
        self::assertSame('120.50', $first['current_price']);
        self::assertSame(30, $first['duration_days']);
        self::assertTrue($first['active']);
        self::assertSame($this->actorId, $first['created_by_user_id']);
        self::assertSame(gmdate('Y-m-d H:i:s', 1_800_000_000), $first['created_at']);
        self::assertSame($first, $service->findService((int) $first['id']));
        self::assertSame([$first['id']], array_column($service->listServices(), 'id'));
        self::assertSame([$first['id']], array_column($service->listServices(true), 'id'));

        $updated = $service->updateService(
            (int) $first['id'], 'Asistencia Plus', 'Soporte ampliado', '250', 60, false, $this->actorId, 1_800_000_010,
        );
        self::assertSame('250.00', $updated['current_price']);
        self::assertSame(60, $updated['duration_days']);
        self::assertFalse($updated['active']);
        self::assertSame('2027-01-15 08:00:10', $updated['updated_at']);
        self::assertSame([], $service->listServices(true));
        self::assertSame([$first['id']], array_column($service->listServices(), 'id'));
        self::assertNull($service->findService(0));
        self::assertNull($service->findService(PHP_INT_MAX));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM digital_services')->fetchColumn());
    }

    public function testCatalogAcceptsFreeAndMaximumPriceAndRejectsInvalidInput(): void
    {
        $service = $this->service();
        $free = $service->createService('Gratis', 'Servicio sin costo', '0', 1, $this->actorId, 1_800_000_000);
        self::assertSame('0.00', $free['current_price']);
        $maximum = $service->createService(
            'Premium', 'Servicio premium', '9999999999.99', 365, $this->actorId, 1_800_000_001,
        );
        self::assertSame('9999999999.99', $maximum['current_price']);

        foreach ([
            ['', 'Descripción válida', '1', 30],
            ['   ', 'Descripción válida', '1', 30],
            [str_repeat('n', 121), 'Descripción válida', '1', 30],
            ['Servicio', '', '1', 30],
            ['Servicio', str_repeat('d', 1001), '1', 30],
            ['Servicio', 'Descripción válida', '-1', 30],
            ['Servicio', 'Descripción válida', '1.234', 30],
            ['Servicio', 'Descripción válida', '10000000000.00', 30],
            ['Servicio', 'Descripción válida', '1e2', 30],
            ['Servicio', 'Descripción válida', '1', 0],
            ['Servicio', 'Descripción válida', '1', 4_294_967_296],
        ] as [$name, $description, $price, $days]) {
            $this->assertRejected(fn () => $service->createService(
                $name, $description, $price, $days, $this->actorId, 1_800_000_002,
            ));
        }
        $this->assertRejected(fn () => $service->createService(
            'Servicio', 'Descripción válida', '1', 30, 0, 1_800_000_003,
        ));
        $this->assertRejected(fn () => $service->updateService(
            PHP_INT_MAX, 'Servicio', 'Descripción válida', '1', 30, true, $this->actorId, 1_800_000_004,
        ));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM digital_services')->fetchColumn());
    }

    private function service(): MembershipService
    {
        return new MembershipService(
            $this->pdo,
            new FinanceService($this->pdo, new PaymentMethodService($this->pdo, sys_get_temp_dir()), sys_get_temp_dir()),
        );
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected invalid catalog input to be rejected.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }
}
