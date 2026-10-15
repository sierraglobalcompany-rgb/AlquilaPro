<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Audit;

use AlquilaPro\Audit\AuditService;
use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class MembershipAuditOutcomeAuthorityTest extends TestCase
{
    private PDO $pdo;
    private int $actorId;

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

        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, must_change_password) '
            . "VALUES ('owner', 'Membership Audit Owner', :email, :password_hash, 1, 0)",
        );
        $statement->execute([
            'email' => 'membership-audit-' . bin2hex(random_bytes(4)) . '@example.com',
            'password_hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);
        $this->actorId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testMembershipAuditActionUsesReturnedChargeOutcomeInsteadOfRequestDecision(): void
    {
        $memberships = new class {
            public array $call = [];

            public function decideCharge(
                int $membershipId,
                string $decision,
                ?string $dueDate,
                ?string $description,
                int $actorId,
                int $now,
            ): array {
                $this->call = compact('membershipId', 'decision', 'dueDate', 'description', 'actorId', 'now');

                return ['charge_outcome' => 'skipped', 'charge_id' => null];
            }
        };

        $app = AppFactory::create();
        $actorId = $this->actorId;
        $register = require dirname(__DIR__, 3) . '/app/Memberships/routes.php';
        $register($app, [
            'memberships' => $memberships,
            'leases' => new class {},
            'audit' => new AuditService($this->pdo),
            'session' => new SessionGuard(),
            'csrf' => new Csrf(),
            'current_user' => static fn (): array => [
                'id' => $actorId,
                'role' => 'owner',
                'permissions' => [],
                'must_change_password' => false,
            ],
            'view' => new class {
                public function renderApp(string $template, array $data): string
                {
                    return '';
                }
            },
            'base_path' => '',
        ]);

        $request = (new ServerRequestFactory())->createServerRequest(
            'POST',
            'https://alquilapro.sierraglobalcompany.com/admin/memberships/81/decision',
            ['REMOTE_ADDR' => '203.0.113.81'],
        )->withParsedBody([
            'decision' => 'create',
            'due_date' => '2026-10-31',
            'description' => 'Cargo solicitado por el formulario.',
        ]);
        $response = $app->handle($request);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('create', $memberships->call['decision']);

        $row = $this->pdo->query(
            "SELECT action, entity_type, entity_id, metadata_json FROM audit_log ORDER BY id DESC LIMIT 1",
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('membership.charge_skipped', $row['action']);
        self::assertSame('membership', $row['entity_type']);
        self::assertSame(81, (int) $row['entity_id']);
        self::assertNull($row['metadata_json']);
    }
}
