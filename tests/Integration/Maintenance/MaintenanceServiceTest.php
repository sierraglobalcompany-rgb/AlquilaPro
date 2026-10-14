<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Maintenance;

use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Maintenance\MaintenanceService;
use AlquilaPro\Maintenance\VisitService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\UploadedFile;

final class MaintenanceServiceTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $photoDirectory;
    private array $workers = [];
    private array $uploadPaths = [];

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
        $this->directory = sys_get_temp_dir() . '/alquila_maintenance_' . bin2hex(random_bytes(6));
        $this->photoDirectory = $this->directory . '/photos';
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if (is_resource($worker['process'])) {
                @proc_terminate($worker['process']);
                foreach ([1, 2] as $pipe) {
                    if (isset($worker['pipes'][$pipe]) && is_resource($worker['pipes'][$pipe])) {
                        fclose($worker['pipes'][$pipe]);
                    }
                }
                @proc_close($worker['process']);
            }
            @unlink($worker['ready']);
        }
        foreach (glob($this->photoDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->photoDirectory);
        foreach ($this->uploadPaths as $path) {
            @unlink($path);
        }
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir($this->directory);
    }

    public function testLifecycleAssignmentRulesAndTerminalImmutability(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $request = $service->create($lease['lease_id'], '  Plomería  ', 'urgent', '  Fuga en cocina  ', $lease['actor_id']);
        self::assertSame('Plomería', $request['category']);
        self::assertSame('Fuga en cocina', $request['description']);
        self::assertSame('open', $request['status']);
        self::assertSame([], $request['photos']);
        self::assertCount(1, $service->listForLease($lease['lease_id']));
        $this->assertRejected(fn () => $service->create($lease['lease_id'], ' ', 'medium', 'Descripción', $lease['actor_id']));
        $this->assertRejected(fn () => $service->create($lease['lease_id'], 'Categoría', 'critical', 'Descripción', $lease['actor_id']));

        $tenantId = $this->insertUser('tenant');
        $inactiveStaffId = $this->insertUser('admin', false);
        $nonStaffId = $this->insertUser('candidate');
        $this->assertRejected(fn () => $service->assign($request['id'], $tenantId, $lease['actor_id'], 1791980000));
        $this->assertRejected(fn () => $service->assign($request['id'], $inactiveStaffId, $lease['actor_id'], 1791980000));
        $this->assertRejected(fn () => $service->assign($request['id'], $nonStaffId, $lease['actor_id'], 1791980000));
        $service->assign($request['id'], $lease['actor_id'], $lease['actor_id'], 1791980000);
        self::assertSame($lease['actor_id'], $service->find($request['id'])['assigned_user_id']);

        $service->transitionStatus($request['id'], 'in_progress', $lease['actor_id'], 1791980100);
        $service->transitionStatus($request['id'], 'resolved', $lease['actor_id'], 1791980200, 'Llave reemplazada');
        $resolved = $service->find($request['id']);
        self::assertSame('resolved', $resolved['status']);
        self::assertSame('Llave reemplazada', $resolved['resolution_notes']);
        self::assertSame($lease['actor_id'], $resolved['resolved_by_user_id']);
        self::assertSame(gmdate('Y-m-d H:i:s', 1791980200), $resolved['resolved_at']);

        $this->assertRejected(fn () => $service->transitionStatus($request['id'], 'in_progress', $lease['actor_id'], 1791980300));
        $this->assertRejected(fn () => $service->assign($request['id'], null, $lease['actor_id'], 1791980300));
        $this->assertRejected(fn () => $service->classifyDamage($request['id'], 'tenant_damage', 1791980300));
        $this->assertRejected(fn () => $service->addPhoto($request['id'], $this->upload($this->pngBytes()), $lease['actor_id'], 1791980300));
    }

    public function testRepairChargeUsesCanonicalFinancePathAndIsConditional(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $request = $service->create($lease['lease_id'], 'Pintura', 'high', 'Daño en pared', $lease['actor_id']);

        $this->assertRejected(fn () => $service->createRepairCharge(
            $request['id'], '85000', '2026-11-01', 'Reparación de pared', $lease['actor_id'],
        ));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());

        $service->classifyDamage($request['id'], 'tenant_damage', 1791980100);
        $charge = $service->createRepairCharge(
            $request['id'], '85000', '2026-11-01', 'Reparación de pared', $lease['actor_id'],
        );
        self::assertSame($lease['lease_id'], $charge['lease_id']);
        self::assertSame('repair', $charge['type']);
        self::assertSame('85000.00', $charge['amount']);
        self::assertSame($charge['id'], $service->find($request['id'])['charge_id']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM charges WHERE type = \'repair\'')->fetchColumn());

        $this->assertRejected(fn () => $service->createRepairCharge(
            $request['id'], '85000', '2026-11-01', 'Cargo duplicado', $lease['actor_id'],
        ));
        $this->assertRejected(fn () => $service->classifyDamage($request['id'], 'owner_responsibility', 1791980150));
        $this->assertRejected(fn () => $service->transitionStatus(
            $request['id'], 'cancelled', $lease['actor_id'], 1791980200, null, 'Cancelación posterior',
        ));
        self::assertSame('open', $service->find($request['id'])['status']);
    }

    public function testTerminalRequestCannotKeepAnActiveVisit(): void
    {
        $lease = $this->createLease();
        $maintenance = $this->service();
        $request = $maintenance->create($lease['lease_id'], 'Plomería', 'high', 'Revisar fuga', $lease['actor_id']);
        $visits = new VisitService($this->pdo);
        $now = time();
        $visit = $visits->scheduleDirect(
            $request['id'],
            gmdate('Y-m-d H:i:s', $now + 86400),
            60,
            null,
            null,
            $lease['actor_id'],
            $now,
        );

        $this->assertRejected(fn () => $maintenance->transitionStatus(
            $request['id'], 'resolved', $lease['actor_id'], $now + 1, 'Trabajo terminado',
        ));
        $this->assertRejected(fn () => $maintenance->transitionStatus(
            $request['id'], 'cancelled', $lease['actor_id'], $now + 2, null, 'Solicitud cancelada',
        ));
        self::assertSame('open', $maintenance->find($request['id'])['status']);
        self::assertSame('scheduled', $visits->find($visit['id'])['status']);

        $visits->cancel($visit['id'], 'No se requiere la visita', $lease['actor_id'], $now + 3);
        $maintenance->transitionStatus($request['id'], 'resolved', $lease['actor_id'], $now + 4);
        self::assertSame('resolved', $maintenance->find($request['id'])['status']);
    }

    public function testPrivatePhotosVerifyMimeHashSizeAndMissingFiles(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $request = $service->create($lease['lease_id'], 'Humedad', 'medium', 'Mancha en techo', $lease['actor_id']);
        $service->addPhoto($request['id'], $this->upload($this->pngBytes()), $lease['actor_id'], 1791980000);
        $stored = $service->find($request['id']);
        self::assertCount(1, $stored['photos']);
        $photo = $stored['photos'][0];
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $photo['photo_id']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\.png\z/', $photo['stored_name']);
        self::assertSame('image/png', $photo['mime']);
        self::assertSame(strlen($this->pngBytes()), $photo['size_bytes']);
        self::assertSame($this->pngBytes(), $service->privatePhoto($request['id'], $photo['photo_id'])['bytes']);
        self::assertNull($service->privatePhoto($request['id'], str_repeat('a', 32)));

        file_put_contents($this->photoDirectory . DIRECTORY_SEPARATOR . $photo['stored_name'], 'tampered');
        self::assertNull($service->privatePhoto($request['id'], $photo['photo_id']));

        $service->addPhoto($request['id'], $this->upload($this->pngBytes()), $lease['actor_id'], 1791980001);
        $second = $service->find($request['id'])['photos'][1];
        @unlink($this->photoDirectory . DIRECTORY_SEPARATOR . $second['stored_name']);
        self::assertNull($service->privatePhoto($request['id'], $second['photo_id']));
        $this->assertRejected(fn () => $service->addPhoto(
            $request['id'], $this->upload('not an image'), $lease['actor_id'], 1791980002,
        ));
        $this->assertRejected(fn () => $service->addPhoto(
            $request['id'], $this->upload(str_repeat('x', 5 * 1024 * 1024 + 1)), $lease['actor_id'], 1791980003,
        ));
    }

    public function testConcurrentPhotoAppendsPreserveBothEntries(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent photo test requires proc_open.');
        }
        $lease = $this->createLease();
        $service = $this->service();
        $request = $service->create($lease['lease_id'], 'Ventana', 'medium', 'Revisar ventana', $lease['actor_id']);
        $this->lockRequest($request['id']);
        $results = $this->runQueuedWorkers([
            ['photo', (string) $request['id'], (string) $lease['actor_id'], $this->photoDirectory, base64_encode($this->pngBytes())],
            ['photo', (string) $request['id'], (string) $lease['actor_id'], $this->photoDirectory, base64_encode($this->pngBytes())],
        ]);
        self::assertCount(2, array_filter($results, static fn (array $result): bool => $result['ok'] ?? false));
        self::assertCount(2, $service->find($request['id'])['photos']);
    }

    public function testConcurrentChargeGenerationCreatesOneCharge(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent charge test requires proc_open.');
        }
        $lease = $this->createLease();
        $service = $this->service();
        $request = $service->create($lease['lease_id'], 'Puerta', 'high', 'Reparar puerta', $lease['actor_id']);
        $service->classifyDamage($request['id'], 'tenant_damage', 1791980000);
        $this->lockRequest($request['id']);
        $results = $this->runQueuedWorkers([
            ['charge', (string) $request['id'], (string) $lease['actor_id'], $this->photoDirectory, '50000', '2026-11-01', 'Reparación puerta'],
            ['charge', (string) $request['id'], (string) $lease['actor_id'], $this->photoDirectory, '50000', '2026-11-01', 'Reparación puerta'],
        ]);
        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok'] ?? false));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM charges WHERE type = \'repair\'')->fetchColumn());
        self::assertNotNull($service->find($request['id'])['charge_id']);
    }

    public function testChargeAndCancellationRaceHasExactlyOneWinner(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent cancellation test requires proc_open.');
        }
        $lease = $this->createLease();
        $service = $this->service();
        $request = $service->create($lease['lease_id'], 'Piso', 'high', 'Reparar piso', $lease['actor_id']);
        $service->classifyDamage($request['id'], 'tenant_damage', 1791980000);
        $this->lockRequest($request['id']);
        $results = $this->runQueuedWorkers([
            ['charge', (string) $request['id'], (string) $lease['actor_id'], $this->photoDirectory, '50000', '2026-11-01', 'Reparación piso'],
            ['cancel', (string) $request['id'], (string) $lease['actor_id'], $this->photoDirectory, '1791980010'],
        ]);
        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok'] ?? false));
        $final = $service->find($request['id']);
        if ($final['status'] === 'cancelled') {
            self::assertNull($final['charge_id']);
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM charges WHERE type = \'repair\'')->fetchColumn());
        } else {
            self::assertNotNull($final['charge_id']);
            self::assertSame('open', $final['status']);
            self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM charges WHERE type = \'repair\'')->fetchColumn());
        }
    }

    public function testTerminalTransitionWinsAgainstConcurrentProgressTransition(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent transition test requires proc_open.');
        }
        $lease = $this->createLease();
        $service = $this->service();
        $request = $service->create($lease['lease_id'], 'Techo', 'medium', 'Revisar techo', $lease['actor_id']);
        $this->lockRequest($request['id']);
        $results = $this->runQueuedWorkers([
            ['transition', (string) $request['id'], (string) $lease['actor_id'], $this->photoDirectory, 'resolved', '1791980010'],
            ['transition', (string) $request['id'], (string) $lease['actor_id'], $this->photoDirectory, 'in_progress', '1791980011'],
        ]);
        self::assertGreaterThanOrEqual(1, count(array_filter($results, static fn (array $result): bool => $result['ok'] ?? false)));
        self::assertSame('resolved', $service->find($request['id'])['status']);
    }

    private function service(): MaintenanceService
    {
        $methods = new PaymentMethodService($this->pdo, $this->directory . '/receipts');
        $finance = new FinanceService($this->pdo, $methods, $this->directory . '/receipts');
        return new MaintenanceService($this->pdo, $finance, $this->photoDirectory);
    }

    /** @return array{lease_id:int,actor_id:int,tenant_id:int} */
    private function createLease(): array
    {
        $suffix = bin2hex(random_bytes(5));
        $actorId = $this->insertUser('admin');
        $tenantId = $this->insertUser('tenant');
        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city) VALUES (:name, \'Calle 1\', \'Bogotá\')');
        $property->execute(['name' => 'Property ' . $suffix]);
        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) '
            . 'VALUES (:property, :key, \'apartment\', :code, \'occupied\')',
        );
        $unit->execute(['property' => (int) $this->pdo->lastInsertId(), 'key' => bin2hex(random_bytes(12)), 'code' => 'APT-' . substr($suffix, 0, 5)]);
        $unitId = (int) $this->pdo->lastInsertId();
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit, \'cc\', :number, :name, \'3001234567\', :email, \'approved\', :token, '
            . '\'2027-01-01 00:00:00\', \'2026-10-13 10:00:00\')',
        );
        $candidate->execute([
            'unit' => $unitId,
            'number' => 'DOC-' . $suffix,
            'name' => 'Candidate ' . $suffix,
            'email' => 'candidate-' . $suffix . '@example.test',
            'token' => hash('sha256', $suffix),
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();
        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type) VALUES (:name, \'urban_housing\')');
        $template->execute(['name' => 'Template ' . $suffix]);
        $templateId = (int) $this->pdo->lastInsertId();
        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) '
            . 'VALUES (:template, 1, \'Contrato\', \'[]\')',
        );
        $version->execute(['template' => $templateId]);
        $versionId = (int) $this->pdo->lastInsertId();
        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, '
            . 'administration_fee, start_date, end_date, duration_text) '
            . 'VALUES (:candidate, :unit, :version, \'urban_housing\', \'finalized\', 1500000, 0, '
            . '\'2026-10-01\', \'2027-09-30\', \'Doce meses\')',
        );
        $contract->execute([
            'candidate' => $candidateId,
            'unit' => $unitId,
            'version' => $versionId,
        ]);
        $contractId = (int) $this->pdo->lastInsertId();
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) '
            . 'VALUES (:contract, :tenant, :actor, \'2026-10-01 12:00:00\')',
        );
        $lease->execute(['contract' => $contractId, 'tenant' => $tenantId, 'actor' => $actorId]);
        return ['lease_id' => (int) $this->pdo->lastInsertId(), 'actor_id' => $actorId, 'tenant_id' => $tenantId];
    }

    private function insertUser(string $role, bool $active = true): int
    {
        $suffix = bin2hex(random_bytes(5));
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (:role, :name, :email, :hash, :active)',
        );
        $statement->execute([
            'role' => $role,
            'name' => ucfirst($role) . ' ' . $suffix,
            'email' => $role . '-' . $suffix . '@example.test',
            'hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
            'active' => $active ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function upload(string $bytes): UploadedFile
    {
        $path = tempnam($this->directory, 'upload-');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to prepare upload fixture.');
        }
        $this->uploadPaths[] = $path;
        return new UploadedFile($path, 'photo.png', 'image/png', strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        self::assertIsString($bytes);
        return $bytes;
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected maintenance operation to be rejected.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }

    private function lockRequest(int $requestId): void
    {
        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM maintenance_requests WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $requestId]);
        self::assertSame($requestId, (int) $lock->fetchColumn());
    }

    /** @param list<list<string>> $workerArguments @return list<array<string, mixed>> */
    private function runQueuedWorkers(array $workerArguments): array
    {
        $this->workers = [];
        $workerPath = dirname(__DIR__, 2) . '/Support/MaintenanceConcurrencyWorker.php';
        try {
            foreach ($workerArguments as $arguments) {
                $ready = $this->directory . '/worker-' . bin2hex(random_bytes(6)) . '.ready';
                $command = array_merge([PHP_BINARY, $workerPath], $arguments, [$ready]);
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start maintenance worker.');
                }
                fclose($pipes[0]);
                $this->workers[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
            }
            $deadline = microtime(true) + 12;
            do {
                $allReady = count(array_filter($this->workers, static fn (array $worker): bool => is_file($worker['ready']))) === count($this->workers);
                if ($allReady) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertTrue($allReady, 'Each worker should reach its service operation before releasing the row lock.');
            usleep(150000);
            foreach ($this->workers as $worker) {
                $status = proc_get_status($worker['process']);
                self::assertTrue(
                    $status['running'],
                    'Worker should be waiting on the maintenance row lock; status=' . json_encode($status),
                );
            }
            $this->pdo->commit();

            $results = [];
            foreach ($this->workers as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                $exitCode = proc_close($worker['process']);
                @unlink($worker['ready']);
                $result = json_decode((string) $stdout, true);
                self::assertSame(0, $exitCode, 'Worker process failed. ' . (string) $stderr);
                self::assertIsArray($result, 'Worker returned invalid JSON. ' . (string) $stderr);
                $results[] = $result;
            }
            $this->workers = [];
            return $results;
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }
}
