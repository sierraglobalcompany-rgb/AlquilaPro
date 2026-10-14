<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Memberships;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class MembershipSchemaTest extends TestCase
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

    public function testMembershipSchemaHasExactlyTwoTablesAndImmutableSnapshotColumns(): void
    {
        $tables = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() "
            . "AND (table_name LIKE 'digital_%' OR table_name LIKE 'service_membership%' "
            . "OR table_name LIKE 'membership_%' OR table_name LIKE 'payment_provider%') ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['digital_services', 'service_memberships'], $tables);

        self::assertSame([
            'id', 'name', 'description', 'current_price', 'duration_days', 'active',
            'created_by_user_id', 'created_at', 'updated_at',
        ], $this->columns('digital_services'));
        self::assertSame([
            'id', 'service_id', 'lease_id', 'accepted_by_user_id', 'price_snapshot',
            'duration_days_snapshot', 'starts_on', 'ends_on', 'accepted_at', 'renews_membership_id',
            'ended_at', 'charge_decision', 'charge_decided_by_user_id', 'charge_decided_at', 'charge_id',
        ], $this->columns('service_memberships'));

        $foreignKeys = $this->pdo->query(
            "SELECT column_name, referenced_table_name FROM information_schema.key_column_usage "
            . "WHERE table_schema = DATABASE() AND table_name = 'service_memberships' "
            . 'AND referenced_table_name IS NOT NULL ORDER BY column_name',
        )->fetchAll(PDO::FETCH_NUM);
        self::assertSame([
            ['accepted_by_user_id', 'users'],
            ['charge_decided_by_user_id', 'users'],
            ['charge_id', 'charges'],
            ['lease_id', 'leases'],
            ['renews_membership_id', 'service_memberships'],
            ['service_id', 'digital_services'],
        ], $foreignKeys);

        $charge = $this->pdo->query(
            "SELECT is_nullable, column_key FROM information_schema.columns WHERE table_schema = DATABASE() "
            . "AND table_name = 'service_memberships' AND column_name = 'charge_id'",
        )->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['is_nullable' => 'YES', 'column_key' => 'UNI'], $charge);

        $indexes = $this->pdo->query(
            "SELECT DISTINCT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() "
            . "AND table_name = 'service_memberships' ORDER BY index_name",
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([
            'PRIMARY',
            'service_memberships_accepted_by_user_index',
            'service_memberships_charge_decided_by_user_index',
            'service_memberships_charge_unique',
            'service_memberships_lease_service_dates_index',
            'service_memberships_pending_charge_index',
            'service_memberships_renewal_index',
            'service_memberships_service_fk_index',
        ], $indexes);
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT column_name FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position',
        );
        $statement->execute(['table' => $table]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }
}
