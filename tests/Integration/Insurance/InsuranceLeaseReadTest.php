<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Insurance;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Insurance\InsuranceService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InsuranceLeaseReadTest extends TestCase
{
    private PDO $pdo;
    private string $documentDirectory;

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
        $this->documentDirectory = sys_get_temp_dir() . '/alquilapro-f21-insurance-read-' . bin2hex(random_bytes(6));
    }

    public function testLeaseScopedPolicyReadReturnsOnlyOwnedPoliciesInStableOrder(): void
    {
        $older = $this->seedPolicy(101, 'P-OLD', '2026-01-01', '2026-12-31');
        $this->seedPolicy(202, 'P-OTHER', '2026-09-01', '2027-08-31');
        $newer = $this->seedPolicy(101, 'P-NEW', '2026-11-01', '2027-10-31');

        $service = new InsuranceService($this->pdo, $this->documentDirectory);
        $policies = $service->listPoliciesForLease(101, '2026-10-14');

        self::assertSame([$newer, $older], array_column($policies, 'id'));
        self::assertSame([101, 101], array_column($policies, 'lease_id'));
        self::assertSame(['upcoming', 'active'], array_column($policies, 'status'));
        self::assertCount(1, $service->listPoliciesForLease(202, '2026-10-14'));
        self::assertSame([], $service->listPoliciesForLease(0, '2026-10-14'));
    }

    public function testLeaseScopedPolicyReadRejectsInvalidDate(): void
    {
        $this->expectException(RuntimeException::class);
        (new InsuranceService($this->pdo, $this->documentDirectory))
            ->listPoliciesForLease(101, '2026-02-30');
    }

    private function seedPolicy(int $leaseId, string $number, string $start, string $end): int
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO insurance_policies '
                . '(lease_id, provider_name, product_name, policy_number, coverage_start, coverage_end, '
                . 'coverage_snapshot_json, documents_json, created_at) '
                . 'VALUES (:lease_id, :provider, :product, :number, :start, :end, :coverage, :documents, :created_at)',
            );
            $statement->execute([
                'lease_id' => $leaseId,
                'provider' => 'Aseguradora F21',
                'product' => 'Producto F21',
                'number' => $number,
                'start' => $start,
                'end' => $end,
                'coverage' => '[{"name":"Cobertura","limit_amount":null,"deductible_amount":null,"notes":null}]',
                'documents' => '[]',
                'created_at' => '2026-10-14 12:00:00',
            ]);
            return (int) $this->pdo->lastInsertId();
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
