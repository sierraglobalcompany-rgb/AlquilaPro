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

final class VisitSecurityRegressionTest extends TestCase
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

    public function testSelectionRejectsAnyTimeOutsideTheCurrentProposal(): void
    {
        $fixture = $this->createMaintenanceRequest($this->pdo);
        $service = new VisitService($this->pdo);
        $now = time();
        $allowed = gmdate('Y-m-d H:i:s', $now + 86400);
        $unlisted = gmdate('Y-m-d H:i:s', $now + 172800);
        $visit = $service->propose($fixture['request_id'], [[
            'start_at' => $allowed,
            'duration_minutes' => 60,
        ]], 'Privado', '3001234567', $fixture['actor_id'], $now);

        try {
            $service->selectSlot($visit['id'], $unlisted, $fixture['tenant_id'], $now + 1);
            self::fail('A tenant must not select a time the staff did not propose.');
        } catch (RuntimeException) {
            self::assertSame('proposed', $service->find($visit['id'])['status']);
            self::assertNull($service->find($visit['id'])['selected_start_at']);
        }

        $selected = $service->selectSlot($visit['id'], $allowed, $fixture['tenant_id'], $now + 2);
        self::assertSame('scheduled', $selected['status']);
        self::assertSame($allowed, $selected['selected_start_at']);
        self::assertSame('Privado', $selected['technician_name']);
    }
}
