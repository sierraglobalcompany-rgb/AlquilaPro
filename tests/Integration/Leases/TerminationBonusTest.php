<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Leases;

use AlquilaPro\Auth\UserService;
use AlquilaPro\Community\CommunityService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\ObligationService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Insurance\InsuranceService;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\Leases\TerminationService;
use AlquilaPro\Memberships\MembershipService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TerminationBonusTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private int $actorId;
    private int $leaseId;

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
        $this->directory = sys_get_temp_dir() . '/alquilapro-f21-bonus-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $fixture = $this->leaseFixture();
        $this->actorId = $fixture['actor_id'];
        $this->leaseId = $fixture['lease_id'];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
    }

    public function testFeatureDisabledRejectsNewBonusWithoutCreatingRow(): void
    {
        $before = $this->bonusCount($this->leaseId);

        try {
            $this->service(false)->configureBonus($this->leaseId, '1000.00', 'Buen manejo', $this->actorId, 1_800_000_000);
            self::fail('Disabled bonus feature must reject mutations.');
        } catch (RuntimeException) {
            self::assertSame($before, $this->bonusCount($this->leaseId));
        }
    }

    public function testConfigureCreatesOnePendingRowNormalizesMoneyAndAllowsPendingEdit(): void
    {
        $first = $this->service(true)->configureBonus(
            $this->leaseId,
            '1250.5',
            '  Buen manejo  ',
            $this->actorId,
            1_800_000_010,
        );

        self::assertSame($this->leaseId, $first['lease_id']);
        self::assertSame('1250.50', $first['amount']);
        self::assertSame('Buen manejo', $first['note']);
        self::assertNull($first['decision']);
        self::assertSame($this->actorId, $first['created_by_user_id']);
        self::assertSame(1, $this->bonusCount($this->leaseId));

        $second = $this->service(true)->configureBonus(
            $this->leaseId,
            '900.00',
            null,
            $this->actorId,
            1_800_000_020,
        );

        self::assertSame($first['id'], $second['id']);
        self::assertSame('900.00', $second['amount']);
        self::assertNull($second['note']);
        self::assertNull($second['decision']);
        self::assertSame(1, $this->bonusCount($this->leaseId));
    }

    public function testConfigureRejectsInvalidAmountOversizedNoteAndMissingLease(): void
    {
        foreach (['0', '-1', 'abc', '1.999', '10000000000.00'] as $amount) {
            $this->assertRuntimeFailure(fn () => $this->service(true)->configureBonus(
                $this->leaseId,
                $amount,
                null,
                $this->actorId,
                1_800_000_030,
            ));
        }

        $this->assertRuntimeFailure(fn () => $this->service(true)->configureBonus(
            $this->leaseId,
            '1.00',
            str_repeat('x', 1001),
            $this->actorId,
            1_800_000_031,
        ));
        $this->assertRuntimeFailure(fn () => $this->service(true)->configureBonus(
            999999999,
            '1.00',
            null,
            $this->actorId,
            1_800_000_032,
        ));
    }

    public function testApprovedAndDeniedDecisionsAreTerminalAndNeverWriteFinance(): void
    {
        $chargesBefore = $this->tableCount('charges');
        $paymentsBefore = $this->tableCount('payments');

        $this->service(true)->configureBonus($this->leaseId, '500.00', 'Aprobable', $this->actorId, 1_800_000_040);
        $approved = $this->service(true)->decideBonus($this->leaseId, 'approved', $this->actorId, 1_800_000_041);
        self::assertSame('approved', $approved['decision']);
        self::assertSame($this->actorId, $approved['decided_by_user_id']);
        self::assertNotNull($approved['decided_at']);

        $this->assertRuntimeFailure(fn () => $this->service(true)->configureBonus(
            $this->leaseId,
            '600.00',
            'No debe cambiar',
            $this->actorId,
            1_800_000_042,
        ));
        $this->assertRuntimeFailure(fn () => $this->service(true)->decideBonus(
            $this->leaseId,
            'denied',
            $this->actorId,
            1_800_000_043,
        ));

        $other = $this->leaseFixture();
        $this->service(true)->configureBonus($other['lease_id'], '300.00', null, $this->actorId, 1_800_000_044);
        $denied = $this->service(true)->decideBonus($other['lease_id'], 'denied', $this->actorId, 1_800_000_045);
        self::assertSame('denied', $denied['decision']);

        self::assertSame($chargesBefore, $this->tableCount('charges'));
        self::assertSame($paymentsBefore, $this->tableCount('payments'));
    }

    public function testDecisionRequiresPendingBonusAndApprovedOrDeniedValue(): void
    {
        $this->assertRuntimeFailure(fn () => $this->service(true)->decideBonus(
            $this->leaseId,
            'approved',
            $this->actorId,
            1_800_000_050,
        ));
        $this->service(true)->configureBonus($this->leaseId, '250.00', null, $this->actorId, 1_800_000_051);
        $this->assertRuntimeFailure(fn () => $this->service(true)->decideBonus(
            $this->leaseId,
            'pending',
            $this->actorId,
            1_800_000_052,
        ));
    }

    public function testConcurrentDecisionsHaveOneTerminalWinner(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent bonus test requires proc_open.');
        }

        $this->service(true)->configureBonus($this->leaseId, '700.00', null, $this->actorId, 1_800_000_060);
        $chargesBefore = $this->tableCount('charges');
        $paymentsBefore = $this->tableCount('payments');

        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM good_conduct_bonuses WHERE lease_id = :lease_id FOR UPDATE');
        $lock->execute(['lease_id' => $this->leaseId]);
        self::assertNotFalse($lock->fetchColumn());

        $workers = [
            $this->startWorker('approved', 1_800_000_061),
            $this->startWorker('denied', 1_800_000_062),
        ];

        try {
            $this->waitUntilWorkersAreReady($workers);
            $this->pdo->commit();
            $results = array_map(fn (array $worker): array => $this->finishWorker($worker), $workers);
            self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok']));

            $stored = $this->service(true)->bonusForLease($this->leaseId);
            self::assertIsArray($stored);
            self::assertContains($stored['decision'], ['approved', 'denied']);
            self::assertSame(1, $this->bonusCount($this->leaseId));
            self::assertSame($chargesBefore, $this->tableCount('charges'));
            self::assertSame($paymentsBefore, $this->tableCount('payments'));
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($workers as $worker) {
                if (is_resource($worker['process'])) {
                    @proc_terminate($worker['process']);
                }
                @unlink($worker['ready']);
            }
        }
    }

    private function service(bool $bonusEnabled): TerminationService
    {
        if (!class_exists(TerminationService::class)) {
            self::fail('TerminationService must exist for the F21 bonus workflow.');
        }

        $paymentMethods = new PaymentMethodService($this->pdo, $this->directory);
        $finance = new FinanceService($this->pdo, $paymentMethods, $this->directory);

        return new TerminationService(
            $this->pdo,
            new LeaseService($this->pdo, new UserService($this->pdo)),
            $finance,
            new ObligationService($this->pdo, $finance, $this->directory),
            new InsuranceService($this->pdo, $this->directory),
            new DocumentService($this->pdo, $this->directory),
            new CommunityService($this->pdo),
            new MembershipService($this->pdo, $finance),
            $bonusEnabled,
        );
    }

    /** @return array{process:resource,pipes:array,ready:string} */
    private function startWorker(string $decision, int $now): array
    {
        $ready = $this->directory . '/worker-' . bin2hex(random_bytes(6)) . '.ready';
        $workerPath = dirname(__DIR__, 2) . '/Support/TerminationBonusConcurrencyWorker.php';
        $command = [
            PHP_BINARY,
            $workerPath,
            (string) $this->leaseId,
            $decision,
            (string) $this->actorId,
            (string) $now,
            $ready,
        ];
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
        if (!is_resource($process)) {
            self::fail('Unable to start F21 bonus concurrency worker.');
        }
        fclose($pipes[0]);
        return ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
    }

    /** @param list<array{process:resource,pipes:array,ready:string}> $workers */
    private function waitUntilWorkersAreReady(array $workers): void
    {
        $deadline = microtime(true) + 12;
        do {
            $ready = count(array_filter($workers, static fn (array $worker): bool => is_file($worker['ready']))) === count($workers);
            if ($ready) {
                return;
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        self::fail('F21 bonus workers did not reach the lock boundary.');
    }

    /** @param array{process:resource,pipes:array,ready:string} $worker @return array{ok:bool} */
    private function finishWorker(array $worker): array
    {
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);
        proc_close($worker['process']);
        @unlink($worker['ready']);
        $result = json_decode((string) $stdout, true);
        self::assertIsArray($result, (string) $stderr);
        return ['ok' => (bool) ($result['ok'] ?? false)];
    }

    private function assertRuntimeFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected F21 bonus operation to fail closed.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }

    private function bonusCount(int $leaseId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM good_conduct_bonuses WHERE lease_id = :lease_id');
        $statement->execute(['lease_id' => $leaseId]);
        return (int) $statement->fetchColumn();
    }

    private function tableCount(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }

    /** @return array{lease_id:int,actor_id:int} */
    private function leaseFixture(): array
    {
        $actorId = $this->insertUser('owner', 'Owner F21', 'owner-f21-' . bin2hex(random_bytes(3)) . '@example.com');
        $tenantId = $this->insertUser('tenant', 'Tenant F21', 'tenant-f21-' . bin2hex(random_bytes(3)) . '@example.com');

        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)');
        $property->execute(['name' => 'Edificio F21 ' . bin2hex(random_bytes(2)), 'address' => 'Calle 50 # 10-20', 'city' => 'Medellín', 'sector' => 'Calasanz']);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status, monthly_price, administration_fee, unit_number) '
            . 'VALUES (:property_id, :public_key, \'apartment\', :code, \'occupied\', \'1500000.00\', \'200000.00\', \'501\')',
        );
        $unit->execute(['property_id' => $propertyId, 'public_key' => bin2hex(random_bytes(12)), 'code' => 'F21-' . bin2hex(random_bytes(3))]);
        $unitId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type, is_active, created_by_user_id) VALUES (:name, \'urban_housing\', 1, :actor)');
        $template->execute(['name' => 'Template F21 ' . bin2hex(random_bytes(3)), 'actor' => $actorId]);
        $templateId = (int) $this->pdo->lastInsertId();

        $version = $this->pdo->prepare('INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json, created_by_user_id) VALUES (:template, 1, \'Contrato F21\', \'[]\', :actor)');
        $version->execute(['template' => $templateId, 'actor' => $actorId]);
        $versionId = (int) $this->pdo->lastInsertId();

        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, current_address, status, continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit, \'cc\', :document, \'Tenant F21\', \'3001112233\', :email, \'Carrera 10 # 11-12\', \'pending_screening\', :token, \'2027-01-01 00:00:00\', \'2026-10-14 10:00:00\')',
        );
        $candidate->execute([
            'unit' => $unitId,
            'document' => 'F21-' . bin2hex(random_bytes(4)),
            'email' => 'candidate-f21-' . bin2hex(random_bytes(3)) . '@example.com',
            'token' => bin2hex(random_bytes(32)),
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, start_date, end_date, duration_text, resolved_variables_json, snapshot_html, pdf_stored_name, pdf_sha256, pdf_size_bytes, created_by_user_id, finalized_by_user_id, generated_at, finalized_at) '
            . 'VALUES (:candidate, :unit, :version, \'urban_housing\', \'finalized\', \'1750000.00\', \'250000.00\', \'2026-11-01\', \'2027-10-31\', \'Doce meses\', \'{}\', \'<article>F21</article>\', :pdf_name, :pdf_hash, 1234, :actor, :actor, \'2026-10-14 10:10:00\', \'2026-10-14 10:20:00\')',
        );
        $contract->execute([
            'candidate' => $candidateId,
            'unit' => $unitId,
            'version' => $versionId,
            'pdf_name' => str_repeat('c', 32) . '.pdf',
            'pdf_hash' => str_repeat('d', 64),
            'actor' => $actorId,
        ]);
        $contractId = (int) $this->pdo->lastInsertId();

        $lease = $this->pdo->prepare('INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) VALUES (:contract, :tenant, :actor, \'2026-10-14 11:00:00\')');
        $lease->execute(['contract' => $contractId, 'tenant' => $tenantId, 'actor' => $actorId]);

        return ['lease_id' => (int) $this->pdo->lastInsertId(), 'actor_id' => $actorId];
    }

    private function insertUser(string $role, string $name, string $email): int
    {
        $statement = $this->pdo->prepare('INSERT INTO users (role, full_name, email, password_hash, is_active) VALUES (:role, :name, :email, :hash, 1)');
        $statement->execute([
            'role' => $role,
            'name' => $name,
            'email' => strtolower($email),
            'hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
