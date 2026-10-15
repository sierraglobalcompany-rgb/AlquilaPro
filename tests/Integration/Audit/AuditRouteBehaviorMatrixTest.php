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
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AuditRouteBehaviorMatrixTest extends TestCase
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
            . "VALUES ('owner', 'Audit Matrix Owner', :email, :password_hash, 1, 0)",
        );
        $statement->execute([
            'email' => 'audit-matrix-' . bin2hex(random_bytes(4)) . '@example.com',
            'password_hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);
        $this->actorId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testUiOwnerRecordsAppearanceMutationWithRemoteAddr(): void
    {
        $branding = new class {
            public array $last = [];

            public function update(string $name, string $color, mixed $logo = null): void
            {
                $this->last = [$name, $color, $logo];
            }

            public function current(): array
            {
                return ['app_name' => 'Alquila Pro', 'primary_color' => '#1E6A5A', 'logo_path' => null];
            }

            public function logoFile(): ?array
            {
                return null;
            }
        };
        $app = $this->ownerApp('app/UI/routes.php', ['branding' => $branding]);

        $response = $this->request($app, 'POST', '/admin/appearance', [
            'app_name' => 'Audit Matrix',
            'primary_color' => '#112233',
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Audit Matrix', '#112233', null], $branding->last);
        $this->assertAudit('appearance.updated', 'appearance', null, null);
    }

    public function testContractsOwnerRecordsFinalizeAfterSuccessfulMutation(): void
    {
        $contracts = new class {
            public array $finalized = [];

            public function finalize(int $id, int $actorId, int $now): void
            {
                $this->finalized = [$id, $actorId, $now];
            }
        };
        $app = $this->ownerApp('app/Contracts/routes.php', [
            'contracts' => $contracts,
            'leases' => new class {},
            'finance' => new class {},
        ]);

        $response = $this->request($app, 'POST', '/admin/contracts/41', ['action' => 'finalize']);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(41, $contracts->finalized[0]);
        self::assertSame($this->actorId, $contracts->finalized[1]);
        $this->assertAudit('contract.finalized', 'contract', 41, null);
    }

    public function testLeasesOwnerRecordsEndUsingReturnedCanonicalLease(): void
    {
        $leases = new class {
            public function end(int $id, int $actorId, int $now): array
            {
                return ['id' => $id, 'contract_id' => 73, 'ended_by_user_id' => $actorId];
            }
        };
        $app = $this->ownerApp('app/Leases/routes.php', [
            'leases' => $leases,
            'contracts' => new class {},
            'finance' => new class {},
            'obligations' => new class {},
            'payment_methods' => new class {},
            'insurance' => new class {},
            'maintenance' => new class {},
            'visits' => new class {},
            'documents' => new class {},
            'community' => new class {},
            'memberships' => new class {},
            'termination' => new class {},
            'good_conduct_bonus_enabled' => false,
        ]);

        $response = $this->request($app, 'POST', '/admin/leases/52/end');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/contracts/73', $response->getHeaderLine('Location'));
        $this->assertAudit('lease.ended', 'lease', 52, null);
    }

    public function testFinanceOwnerRecordsRejectedPaymentWithoutReasonLeak(): void
    {
        $finance = new class {
            public array $rejected = [];

            public function rejectPayment(int $id, string $reason, int $actorId, int $now): void
            {
                $this->rejected = [$id, $reason, $actorId, $now];
            }
        };
        $app = $this->ownerApp('app/Finance/routes.php', [
            'finance' => $finance,
            'obligations' => new class {},
            'payment_methods' => new class {},
            'properties' => new class {},
        ]);
        $secretReason = 'motivo-privado-' . bin2hex(random_bytes(8));

        $response = $this->request($app, 'POST', '/admin/finance/payments/61/reject', [
            'reason' => $secretReason,
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame([61, $secretReason, $this->actorId], array_slice($finance->rejected, 0, 3));
        $row = $this->assertAudit('payment.rejected', 'payment', 61, null);
        self::assertStringNotContainsString($secretReason, implode('|', array_map(
            static fn (mixed $value): string => $value === null ? '' : (string) $value,
            $row,
        )));
    }

    public function testDocumentsOwnerRecordsSafeScopeMetadataOnly(): void
    {
        $documents = new class {
            public array $created = [];

            public function createContentVersion(
                ?int $propertyId,
                ?int $leaseId,
                string $type,
                string $title,
                array $content,
                ?int $supersedes,
                int $actorId,
                int $now,
            ): array {
                $this->created = compact(
                    'propertyId',
                    'leaseId',
                    'type',
                    'title',
                    'content',
                    'supersedes',
                    'actorId',
                    'now',
                );
                return ['id' => 71];
            }
        };
        $app = $this->ownerApp('app/Documents/routes.php', [
            'documents' => $documents,
            'leases' => new class {},
        ]);
        $payloadSecret = 'document-body-' . bin2hex(random_bytes(8));

        $response = $this->request($app, 'POST', '/admin/documents', [
            'scope' => 'property',
            'scope_id' => '17',
            'type' => 'manual_convivencia',
            'title' => 'Manual seguro',
            'content_json' => json_encode(['rules' => [$payloadSecret]], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['rules' => [$payloadSecret]], $documents->created['content']);
        $row = $this->assertAudit('document.version_created', 'document', 71, [
            'type' => 'manual_convivencia',
            'scope_kind' => 'property',
            'scope_id' => 17,
        ]);
        self::assertStringNotContainsString($payloadSecret, (string) $row['metadata_json']);
    }

    public function testMembershipOwnerRecordsTerminalChargeOutcomeWithoutDescriptionLeak(): void
    {
        $memberships = new class {
            public array $decision = [];

            public function decideCharge(
                int $membershipId,
                string $decision,
                ?string $dueDate,
                ?string $description,
                int $actorId,
                int $now,
            ): array {
                $this->decision = compact('membershipId', 'decision', 'dueDate', 'description', 'actorId', 'now');
                return ['charge_outcome' => 'skipped', 'charge_id' => null];
            }
        };
        $app = $this->ownerApp('app/Memberships/routes.php', [
            'memberships' => $memberships,
            'leases' => new class {},
        ]);
        $privateDescription = 'membership-private-' . bin2hex(random_bytes(8));

        $response = $this->request($app, 'POST', '/admin/memberships/81/decision', [
            'decision' => 'skip',
            'description' => $privateDescription,
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('skip', $memberships->decision['decision']);
        $row = $this->assertAudit('membership.charge_skipped', 'membership', 81, null);
        self::assertStringNotContainsString($privateDescription, implode('|', array_map(
            static fn (mixed $value): string => $value === null ? '' : (string) $value,
            $row,
        )));
    }

    private function ownerApp(string $routeFile, array $extraDeps): App
    {
        $app = AppFactory::create();
        $actor = [
            'id' => $this->actorId,
            'role' => 'owner',
            'permissions' => [],
            'must_change_password' => false,
        ];
        $view = new class {
            public function renderApp(string $template, array $data): string
            {
                return '';
            }

            public function renderAuth(string $template, array $data): string
            {
                return '';
            }
        };
        $deps = array_merge([
            'audit' => new AuditService($this->pdo),
            'session' => new SessionGuard(),
            'csrf' => new Csrf(),
            'current_user' => static fn (): array => $actor,
            'view' => $view,
            'base_path' => '',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com',
        ], $extraDeps);

        $register = require dirname(__DIR__, 3) . '/' . $routeFile;
        $register($app, $deps);

        return $app;
    }

    /** @param array<string,mixed> $body */
    private function request(App $app, string $method, string $path, array $body = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
            ['REMOTE_ADDR' => '203.0.113.55'],
        );
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }

        return $app->handle($request);
    }

    /** @param array<string,mixed>|null $metadata */
    private function assertAudit(
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $metadata,
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT actor_user_id, action, entity_type, entity_id, summary, ip_address, metadata_json '
            . 'FROM audit_log WHERE action = :action ORDER BY id ASC',
        );
        $statement->execute(['action' => $action]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $rows, $action . ' must write exactly one audit row.');
        $row = $rows[0];
        self::assertSame($this->actorId, (int) $row['actor_user_id']);
        self::assertSame($action, $row['action']);
        self::assertSame($entityType, $row['entity_type']);
        self::assertSame($entityId, $row['entity_id'] === null ? null : (int) $row['entity_id']);
        self::assertSame('203.0.113.55', $row['ip_address']);
        if ($metadata === null) {
            self::assertNull($row['metadata_json']);
        } else {
            self::assertSame(
                $metadata,
                json_decode((string) $row['metadata_json'], true, 32, JSON_THROW_ON_ERROR),
            );
        }

        return $row;
    }
}
