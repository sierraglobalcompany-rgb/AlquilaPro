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
use Slim\Psr7\UploadedFile;

final class InsurancePolicyServiceTest extends TestCase
{
    private PDO $pdo;
    private string $documentDirectory;
    /** @var list<string> */
    private array $temporaryFiles = [];

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
        $this->documentDirectory = sys_get_temp_dir() . '/alquilapro-insurance-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        if (is_dir($this->documentDirectory)) {
            foreach (glob($this->documentDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (is_file($path) || is_link($path)) {
                    @unlink($path);
                }
            }
            @rmdir($this->documentDirectory);
        }
    }

    public function testCreatePolicyValidatesCoverageAndDerivesInclusiveStatus(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], [
            'provider_name' => '  Aseguradora Uno  ',
            'product_name' => ' Hogar protegido ',
            'policy_number' => ' P-100 ',
            'coverage_start' => '2026-11-01',
            'coverage_end' => '2027-10-31',
            'coverage' => [[
                'name' => 'Daños por agua',
                'limit_amount' => '2500000',
                'deductible_amount' => '250000.5',
                'notes' => '  Deducible por evento  ',
            ]],
            'internal_notes' => ' Renovación anual ',
        ], $lease['actor_id'], strtotime('2026-10-14 12:00:00 UTC'));

        self::assertSame('Aseguradora Uno', $policy['provider_name']);
        self::assertSame('Hogar protegido', $policy['product_name']);
        self::assertSame('P-100', $policy['policy_number']);
        self::assertSame('upcoming', $policy['status']);
        self::assertSame('2500000.00', $policy['coverage'][0]['limit_amount']);
        self::assertSame('250000.50', $policy['coverage'][0]['deductible_amount']);
        self::assertSame('Deducible por evento', $policy['coverage'][0]['notes']);
        self::assertSame('Renovación anual', $policy['internal_notes']);
        self::assertSame('active', $service->findPolicy($policy['id'], '2026-11-01')['status']);
        self::assertSame('active', $service->findPolicy($policy['id'], '2027-10-31')['status']);
        self::assertSame('expired', $service->findPolicy($policy['id'], '2027-11-01')['status']);
        self::assertArrayNotHasKey('status', $this->policyRow($policy['id']));
    }

    public function testRejectsInvalidCoverageAndCrossLeaseSupersedes(): void
    {
        $firstLease = $this->createLease();
        $secondLease = $this->createLease();
        $service = $this->service();
        $prior = $service->createPolicy($secondLease['lease_id'], $this->policyInput(), $secondLease['actor_id'], 1791980000);

        foreach ([
            [['name' => ' Agua ', 'limit_amount' => null, 'deductible_amount' => null, 'notes' => null], ['name' => 'agua', 'limit_amount' => null, 'deductible_amount' => null, 'notes' => null]],
            [['name' => 'Daño', 'limit_amount' => '0', 'deductible_amount' => null, 'notes' => null]],
            [['name' => 'Daño', 'limit_amount' => '10.001', 'deductible_amount' => null, 'notes' => null]],
            [['name' => str_repeat('x', 191), 'limit_amount' => null, 'deductible_amount' => null, 'notes' => null]],
            [],
        ] as $coverage) {
            $input = $this->policyInput();
            $input['coverage'] = $coverage;
            $this->assertRejected(fn () => $service->createPolicy(
                $firstLease['lease_id'], $input, $firstLease['actor_id'], 1791980001,
            ));
        }

        $input = $this->policyInput();
        $input['supersedes_policy_id'] = $prior['id'];
        $this->assertRejected(fn () => $service->createPolicy(
            $firstLease['lease_id'], $input, $firstLease['actor_id'], 1791980002,
        ));

        $input = $this->policyInput();
        $input['coverage_start'] = '2027-02-01';
        $input['coverage_end'] = '2027-01-31';
        $this->assertRejected(fn () => $service->createPolicy(
            $firstLease['lease_id'], $input, $firstLease['actor_id'], 1791980003,
        ));
    }

    public function testRenewalCreatesNewPolicyAndCancellationIsSingleAssignment(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $prior = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $renewalInput = $this->policyInput();
        $renewalInput['policy_number'] = 'P-200';
        $renewalInput['supersedes_policy_id'] = $prior['id'];
        $renewal = $service->createPolicy($lease['lease_id'], $renewalInput, $lease['actor_id'], 1791980001);
        self::assertNotSame($prior['id'], $renewal['id']);
        self::assertSame($prior['id'], $renewal['supersedes_policy_id']);
        self::assertSame('active', $service->findPolicy($prior['id'], '2026-10-14')['status']);
        $annotated = $service->updatePolicyNotes($prior['id'], ' Nota interna corregida ', 1791980002);
        self::assertSame('Nota interna corregida', $annotated['internal_notes']);
        self::assertSame($prior['policy_number'], $annotated['policy_number']);
        self::assertSame($prior['coverage'], $annotated['coverage']);

        $cancelled = $service->cancelPolicy($prior['id'], $lease['actor_id'], '  Póliza reemplazada  ', strtotime('2026-10-14 15:00:00 UTC'));
        self::assertSame('cancelled', $cancelled['status']);
        self::assertSame('Póliza reemplazada', $cancelled['cancel_reason']);
        $this->assertRejected(fn () => $service->cancelPolicy(
            $prior['id'], $lease['actor_id'], 'Otro motivo', strtotime('2026-10-14 16:00:00 UTC'),
        ));
        $stored = $this->policyRow($prior['id']);
        self::assertSame('Póliza reemplazada', $stored['cancel_reason']);
        self::assertSame('2026-10-14 15:00:00', $stored['cancelled_at']);
    }

    public function testPolicyDocumentsArePrivateVerifiedAndFrozenAfterCancellation(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $pdf = $this->pdfBytes();
        $withDocument = $service->addPolicyDocument(
            $policy['id'], '  Carátula  ', $this->upload($pdf, 'caratula.pdf', 'image/jpeg'), 1791980001,
        );
        self::assertCount(1, $withDocument['documents']);
        $document = $withDocument['documents'][0];
        self::assertSame('Carátula', $document['label']);
        self::assertSame('application/pdf', $document['mime']);
        self::assertSame(hash('sha256', $pdf), $document['sha256']);
        self::assertSame(strlen($pdf), $document['size_bytes']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $document['document_id']);
        self::assertArrayNotHasKey('stored_name', $document);
        self::assertArrayNotHasKey('path', $document);
        self::assertSame($pdf, $service->readPolicyDocument($policy['id'], $document['document_id'])['bytes']);

        $raw = json_decode((string) $this->policyRow($policy['id'])['documents_json'], true, 512, JSON_THROW_ON_ERROR);
        self::assertMatchesRegularExpression('/\Ainsurance-[a-f0-9]{32}\.pdf\z/', $raw[0]['stored_name']);
        self::assertNotSame($document['document_id'], substr($raw[0]['stored_name'], strlen('insurance-'), 32));
        $storedPath = $this->documentDirectory . DIRECTORY_SEPARATOR . $raw[0]['stored_name'];
        self::assertSame($pdf, file_get_contents($storedPath));
        file_put_contents($storedPath, $pdf . 'tampered');
        self::assertNull($service->readPolicyDocument($policy['id'], $document['document_id']));

        $removed = $service->removePolicyDocument($policy['id'], $document['document_id'], 1791980002);
        self::assertCount(0, $removed['documents']);
        self::assertNull($service->readPolicyDocument($policy['id'], $document['document_id']));

        $service->cancelPolicy($policy['id'], $lease['actor_id'], 'Cancelada para probar evidencia', 1791980003);
        $afterCancellation = $service->addPolicyDocument(
            $policy['id'], 'Anexo posterior', $this->upload($pdf, 'anexo.pdf', 'application/pdf'), 1791980004,
        );
        self::assertCount(1, $afterCancellation['documents']);
        $this->assertRejected(fn () => $service->removePolicyDocument(
            $policy['id'], $afterCancellation['documents'][0]['document_id'], 1791980005,
        ));
        self::assertSame($pdf, $service->readPolicyDocument(
            $policy['id'], $afterCancellation['documents'][0]['document_id'],
        )['bytes']);
    }

    public function testPolicyDocumentRejectsInvalidLabelsMimeAndOversizedBytes(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        foreach (['', str_repeat('x', 191)] as $label) {
            $this->assertRejected(fn () => $service->addPolicyDocument(
                $policy['id'], $label, $this->upload($this->pdfBytes(), 'file.pdf', 'application/pdf'), 1791980001,
            ));
        }
        $this->assertRejected(fn () => $service->addPolicyDocument(
            $policy['id'], 'Ejecutable', $this->upload("\x00\x01\x02", 'file.bin', 'application/octet-stream'), 1791980002,
        ));
        $tooLarge = "%PDF-1.4\n" . str_repeat('x', 5 * 1024 * 1024);
        $this->assertRejected(fn () => $service->addPolicyDocument(
            $policy['id'], 'Grande', $this->upload($tooLarge, 'large.pdf', 'application/pdf'), 1791980003,
        ));
        self::assertSame([], $service->findPolicy($policy['id'], '2026-10-14')['documents']);
    }

    public function testMalformedDocumentMetadataCannotBeOverwrittenByUpload(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $corruptJson = '{invalid';
        $update = $this->pdo->prepare('UPDATE insurance_policies SET documents_json = :json WHERE id = :id');
        $update->execute(['json' => $corruptJson, 'id' => $policy['id']]);

        $this->assertRejected(fn () => $service->addPolicyDocument(
            $policy['id'], 'Nuevo documento', $this->upload($this->pdfBytes(), 'new.pdf', 'application/pdf'), 1791980001,
        ));
        self::assertSame($corruptJson, $this->policyRow($policy['id'])['documents_json']);
        self::assertSame([], glob($this->documentDirectory . DIRECTORY_SEPARATOR . 'insurance-*') ?: []);
    }

    public function testConcurrentPolicyUploadsPreserveBothMetadataEntries(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent upload test requires proc_open.');
        }
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM insurance_policies WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $policy['id']]);
        self::assertSame($policy['id'], (int) $lock->fetchColumn());

        $processes = [];
        $workerPath = dirname(__DIR__, 2) . '/Support/InsuranceConcurrencyWorker.php';
        try {
            foreach (['Póliza A', 'Póliza B'] as $position => $label) {
                $ready = sys_get_temp_dir() . '/insurance-worker-' . bin2hex(random_bytes(8)) . '.ready';
                $command = [
                    PHP_BINARY,
                    $workerPath,
                    'policy',
                    (string) $policy['id'],
                    '1791980001',
                    $this->documentDirectory,
                    $label,
                    base64_encode($this->pdfBytes()),
                    $ready,
                ];
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start an insurance upload worker.');
                }
                fclose($pipes[0]);
                $processes[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready, 'position' => $position];
            }

            $deadline = microtime(true) + 12;
            do {
                $ready = count(array_filter($processes, static fn (array $worker): bool => is_file($worker['ready']))) === 2;
                $files = glob($this->documentDirectory . DIRECTORY_SEPARATOR . 'insurance-*') ?: [];
                if ($ready && count($files) === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertTrue($ready, 'Both upload workers must reach their service call.');
            self::assertCount(2, $files, 'Both private files must be written before the policy row is released.');
            usleep(150000);
            foreach ($processes as $worker) {
                self::assertTrue(proc_get_status($worker['process'])['running'], 'Upload worker should be waiting for the held policy lock.');
            }
            $this->pdo->commit();

            foreach ($processes as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                $exitCode = proc_close($worker['process']);
                @unlink($worker['ready']);
                $result = json_decode((string) $stdout, true);
                self::assertSame(0, $exitCode, 'Worker process failed. ' . (string) $stderr);
                self::assertIsArray($result, 'Worker returned invalid JSON. ' . (string) $stderr);
                self::assertTrue($result['ok'] ?? false, (string) ($result['error'] ?? $stderr));
            }
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($processes as $worker) {
                if (is_resource($worker['process'])) {
                    @proc_terminate($worker['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (isset($worker['pipes'][$pipeNumber]) && is_resource($worker['pipes'][$pipeNumber])) {
                            fclose($worker['pipes'][$pipeNumber]);
                        }
                    }
                    @proc_close($worker['process']);
                }
                @unlink($worker['ready']);
            }
        }

        self::assertCount(2, $service->findPolicy($policy['id'], '2026-10-14')['documents']);
    }

    public function testConcurrentClaimUploadsPreserveBothMetadataEntries(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent claim upload test requires proc_open.');
        }
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $claim = $service->createClaim($policy['id'], $this->claimInput('2026-10-10'), null, $lease['actor_id'], '2026-10-14', 1791980001);
        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM insurance_claims WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $claim['id']]);
        self::assertSame($claim['id'], (int) $lock->fetchColumn());

        $processes = [];
        $workerPath = dirname(__DIR__, 2) . '/Support/InsuranceConcurrencyWorker.php';
        try {
            foreach (['Siniestro A', 'Siniestro B'] as $label) {
                $ready = sys_get_temp_dir() . '/insurance-claim-worker-' . bin2hex(random_bytes(8)) . '.ready';
                $command = [
                    PHP_BINARY,
                    $workerPath,
                    'claim',
                    (string) $claim['id'],
                    '1791980002',
                    $this->documentDirectory,
                    $label,
                    base64_encode($this->pdfBytes()),
                    $ready,
                ];
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start an insurance claim upload worker.');
                }
                fclose($pipes[0]);
                $processes[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
            }
            $deadline = microtime(true) + 12;
            do {
                $allReady = count(array_filter($processes, static fn (array $worker): bool => is_file($worker['ready']))) === 2;
                $files = glob($this->documentDirectory . DIRECTORY_SEPARATOR . 'insurance-*') ?: [];
                if ($allReady && count($files) === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertTrue($allReady, 'Both claim upload workers must reach their service call.');
            self::assertCount(2, $files, 'Both private claim files must be written before the claim row is released.');
            usleep(150000);
            foreach ($processes as $worker) {
                self::assertTrue(proc_get_status($worker['process'])['running'], 'Claim upload worker should wait for its row lock.');
            }
            $this->pdo->commit();
            foreach ($processes as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                $exitCode = proc_close($worker['process']);
                @unlink($worker['ready']);
                $result = json_decode((string) $stdout, true);
                self::assertSame(0, $exitCode, 'Claim upload worker failed. ' . (string) $stderr);
                self::assertIsArray($result, 'Claim upload worker returned invalid JSON. ' . (string) $stderr);
                self::assertTrue($result['ok'] ?? false, (string) ($result['error'] ?? $stderr));
            }
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($processes as $worker) {
                if (is_resource($worker['process'])) {
                    @proc_terminate($worker['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (isset($worker['pipes'][$pipeNumber]) && is_resource($worker['pipes'][$pipeNumber])) {
                            fclose($worker['pipes'][$pipeNumber]);
                        }
                    }
                    @proc_close($worker['process']);
                }
                @unlink($worker['ready']);
            }
        }
        self::assertCount(2, $service->findClaim($claim['id'])['documents']);
    }

    public function testClaimCreationAndPolicyDocumentRemovalSerializeOnPolicyRow(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Policy/claim concurrency test requires proc_open.');
        }
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $withDocument = $service->addPolicyDocument(
            $policy['id'], 'Evidencia póliza', $this->upload($this->pdfBytes(), 'policy.pdf', 'application/pdf'), 1791980001,
        );
        $documentId = $withDocument['documents'][0]['document_id'];
        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM insurance_policies WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $policy['id']]);
        self::assertSame($policy['id'], (int) $lock->fetchColumn());

        $processes = [];
        try {
            foreach ([
                ['create-claim', '2026-10-10'],
                ['remove-policy-document', $documentId],
            ] as [$operation, $argument]) {
                $ready = sys_get_temp_dir() . '/insurance-policy-claim-' . bin2hex(random_bytes(8)) . '.ready';
                $command = [
                    PHP_BINARY,
                    dirname(__DIR__, 2) . '/Support/InsurancePolicyClaimRaceWorker.php',
                    $operation,
                    (string) $policy['id'],
                    $argument,
                    (string) $lease['actor_id'],
                    '1791980002',
                    $ready,
                ];
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start a policy/claim race worker.');
                }
                fclose($pipes[0]);
                $processes[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready, 'operation' => $operation];
            }
            $deadline = microtime(true) + 12;
            do {
                $allReady = count(array_filter($processes, static fn (array $worker): bool => is_file($worker['ready']))) === 2;
                if ($allReady) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertTrue($allReady, 'Both policy/claim workers must reach their service call.');
            usleep(150000);
            foreach ($processes as $worker) {
                self::assertTrue(proc_get_status($worker['process'])['running'], 'Policy/claim worker should wait for the policy lock.');
            }
            $this->pdo->commit();

            $results = [];
            foreach ($processes as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                proc_close($worker['process']);
                @unlink($worker['ready']);
                $result = json_decode((string) $stdout, true);
                self::assertIsArray($result, 'Worker returned invalid JSON. ' . (string) $stderr);
                $results[$worker['operation']] = $result;
            }
            self::assertTrue($results['create-claim']['ok'] ?? false, (string) ($results['create-claim']['error'] ?? 'Claim creation failed.'));
            $claimCount = count($service->listClaims($policy['id']));
            self::assertSame(1, $claimCount);
            $documentCount = count($service->findPolicy($policy['id'], '2026-10-14')['documents']);
            self::assertContains($documentCount, [0, 1]);
            if (($results['remove-policy-document']['ok'] ?? false) === false) {
                self::assertSame(1, $documentCount);
                self::assertStringContainsString('reclamaciones', (string) $results['remove-policy-document']['error']);
            } else {
                self::assertSame(0, $documentCount);
            }
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($processes as $worker) {
                if (is_resource($worker['process'])) {
                    @proc_terminate($worker['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (isset($worker['pipes'][$pipeNumber]) && is_resource($worker['pipes'][$pipeNumber])) {
                            fclose($worker['pipes'][$pipeNumber]);
                        }
                    }
                    @proc_close($worker['process']);
                }
                @unlink($worker['ready']);
            }
        }
    }

    public function testCancellationWinningConcurrentCreateClaimRejectsPostCancellationIncident(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent cancellation/claim test requires proc_open.');
        }
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM insurance_policies WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $policy['id']]);
        self::assertSame($policy['id'], (int) $lock->fetchColumn());

        $processes = [];
        try {
            foreach ([
                ['cancel-policy', 'Cierre concurrente', strtotime('2026-10-13 03:00:00 UTC')],
                ['create-claim', '2026-10-13', strtotime('2026-10-14 12:00:00 UTC')],
            ] as [$operation, $argument, $now]) {
                $ready = sys_get_temp_dir() . '/insurance-cancel-claim-' . bin2hex(random_bytes(8)) . '.ready';
                $command = [
                    PHP_BINARY,
                    dirname(__DIR__, 2) . '/Support/InsurancePolicyClaimRaceWorker.php',
                    $operation,
                    (string) $policy['id'],
                    $argument,
                    (string) $lease['actor_id'],
                    (string) $now,
                    $ready,
                ];
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start a cancellation/claim race worker.');
                }
                fclose($pipes[0]);
                $processes[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready, 'operation' => $operation];
                $deadline = microtime(true) + 8;
                do {
                    $isReady = is_file($ready);
                    if ($isReady) {
                        break;
                    }
                    usleep(10000);
                } while (microtime(true) < $deadline);
                self::assertTrue($isReady, $operation . ' worker must reach its service call.');
                usleep(120000);
                self::assertTrue(proc_get_status($process)['running'], $operation . ' worker should queue on the policy row.');
            }
            $this->pdo->commit();

            $results = [];
            foreach ($processes as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                proc_close($worker['process']);
                @unlink($worker['ready']);
                $result = json_decode((string) $stdout, true);
                self::assertIsArray($result, 'Cancellation/claim worker returned invalid JSON. ' . (string) $stderr);
                $results[$worker['operation']] = $result;
            }
            self::assertTrue($results['cancel-policy']['ok'] ?? false, (string) ($results['cancel-policy']['error'] ?? 'Cancellation failed.'));
            self::assertFalse($results['create-claim']['ok'] ?? true, 'A claim after the winning cancellation must revalidate its incident date.');
            self::assertStringContainsString('cancelada', (string) $results['create-claim']['error']);
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($processes as $worker) {
                if (is_resource($worker['process'])) {
                    @proc_terminate($worker['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (isset($worker['pipes'][$pipeNumber]) && is_resource($worker['pipes'][$pipeNumber])) {
                            fclose($worker['pipes'][$pipeNumber]);
                        }
                    }
                    @proc_close($worker['process']);
                }
                @unlink($worker['ready']);
            }
        }
        self::assertSame([], $service->listClaims($policy['id']));
    }

    public function testConcurrentTerminalTransitionsAllowOnlyOneWinner(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent transition test requires proc_open.');
        }
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $claim = $service->createClaim($policy['id'], $this->claimInput('2026-10-10'), null, $lease['actor_id'], '2026-10-14', 1791980001);
        $service->transitionClaim($claim['id'], 'filed', ['filed_on' => '2026-10-14'], $lease['actor_id'], '2026-10-14', 1791980002);
        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM insurance_claims WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $claim['id']]);
        self::assertSame($claim['id'], (int) $lock->fetchColumn());

        $processes = [];
        try {
            foreach (['approved', 'rejected'] as $target) {
                $ready = sys_get_temp_dir() . '/insurance-transition-' . bin2hex(random_bytes(8)) . '.ready';
                $command = [
                    PHP_BINARY,
                    dirname(__DIR__, 2) . '/Support/InsuranceTransitionConcurrencyWorker.php',
                    (string) $claim['id'],
                    $target,
                    (string) $lease['actor_id'],
                    '1791980003',
                    $ready,
                ];
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start an insurance transition worker.');
                }
                fclose($pipes[0]);
                $processes[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
            }
            $deadline = microtime(true) + 12;
            do {
                $allReady = count(array_filter($processes, static fn (array $worker): bool => is_file($worker['ready']))) === 2;
                if ($allReady) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertTrue($allReady, 'Both transition workers must reach their service call.');
            usleep(150000);
            foreach ($processes as $worker) {
                self::assertTrue(proc_get_status($worker['process'])['running'], 'Transition worker should wait for the claim row.');
            }
            $this->pdo->commit();

            $results = [];
            foreach ($processes as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                proc_close($worker['process']);
                @unlink($worker['ready']);
                $result = json_decode((string) $stdout, true);
                self::assertIsArray($result, 'Transition worker returned invalid JSON. ' . (string) $stderr);
                $results[] = $result;
            }
            self::assertSame(1, count(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true)));
            self::assertSame(1, count(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? true) === false)));
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($processes as $worker) {
                if (is_resource($worker['process'])) {
                    @proc_terminate($worker['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (isset($worker['pipes'][$pipeNumber]) && is_resource($worker['pipes'][$pipeNumber])) {
                            fclose($worker['pipes'][$pipeNumber]);
                        }
                    }
                    @proc_close($worker['process']);
                }
                @unlink($worker['ready']);
            }
        }
        self::assertContains($service->findClaim($claim['id'])['status'], ['approved', 'rejected']);
    }

    public function testTerminalTransitionWinsQueuedRaceAgainstChecklistUpdate(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent checklist/transition test requires proc_open.');
        }
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $claimInput = $this->claimInput('2026-10-10');
        $claimInput['checklist'] = [['key' => 'proof', 'label' => 'Soporte', 'required' => true, 'completed' => false]];
        $claim = $service->createClaim($policy['id'], $claimInput, null, $lease['actor_id'], '2026-10-14', 1791980001);
        $service->transitionClaim($claim['id'], 'filed', ['filed_on' => '2026-10-14'], $lease['actor_id'], '2026-10-14', 1791980002);
        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM insurance_claims WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $claim['id']]);
        self::assertSame($claim['id'], (int) $lock->fetchColumn());

        $processes = [];
        try {
            foreach ([['transition', '1791980003'], ['checklist', '1791980004']] as [$operation, $now]) {
                $ready = sys_get_temp_dir() . '/insurance-checklist-transition-' . bin2hex(random_bytes(8)) . '.ready';
                $command = [
                    PHP_BINARY,
                    dirname(__DIR__, 2) . '/Support/InsuranceChecklistTransitionWorker.php',
                    $operation,
                    (string) $claim['id'],
                    (string) $lease['actor_id'],
                    $now,
                    $ready,
                ];
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start a checklist/transition worker.');
                }
                fclose($pipes[0]);
                $processes[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready, 'operation' => $operation];
                $deadline = microtime(true) + 8;
                do {
                    $isReady = is_file($ready);
                    if ($isReady) {
                        break;
                    }
                    usleep(10000);
                } while (microtime(true) < $deadline);
                self::assertTrue($isReady, $operation . ' worker must reach its service call.');
                usleep(120000);
                self::assertTrue(proc_get_status($process)['running'], $operation . ' worker should be queued on the claim row.');
            }
            $this->pdo->commit();

            $results = [];
            foreach ($processes as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                proc_close($worker['process']);
                @unlink($worker['ready']);
                $result = json_decode((string) $stdout, true);
                self::assertIsArray($result, 'Checklist/transition worker returned invalid JSON. ' . (string) $stderr);
                $results[$worker['operation']] = $result;
            }
            self::assertTrue($results['transition']['ok'] ?? false, (string) ($results['transition']['error'] ?? 'Terminal transition failed.'));
            self::assertFalse($results['checklist']['ok'] ?? true, 'Checklist update queued after terminal transition must fail.');
            self::assertStringContainsString('bloqueado', (string) $results['checklist']['error']);
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($processes as $worker) {
                if (is_resource($worker['process'])) {
                    @proc_terminate($worker['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (isset($worker['pipes'][$pipeNumber]) && is_resource($worker['pipes'][$pipeNumber])) {
                            fclose($worker['pipes'][$pipeNumber]);
                        }
                    }
                    @proc_close($worker['process']);
                }
                @unlink($worker['ready']);
            }
        }
        self::assertSame('approved', $service->findClaim($claim['id'])['status']);
        self::assertFalse($service->findClaim($claim['id'])['checklist'][0]['completed']);
    }

    public function testConcurrentCancellationKeepsOneActorTimeAndReason(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Concurrent cancellation test requires proc_open.');
        }
        $lease = $this->createLease();
        $otherActor = $this->insertUser('admin');
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $this->pdo->beginTransaction();
        $lock = $this->pdo->prepare('SELECT id FROM insurance_policies WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $policy['id']]);
        self::assertSame($policy['id'], (int) $lock->fetchColumn());

        $processes = [];
        try {
            foreach ([
                [$lease['actor_id'], 'Solicitud A', 1791980001],
                [$otherActor, 'Solicitud B', 1791980002],
            ] as [$actorId, $reason, $now]) {
                $ready = sys_get_temp_dir() . '/insurance-cancel-worker-' . bin2hex(random_bytes(8)) . '.ready';
                $command = [
                    PHP_BINARY,
                    dirname(__DIR__, 2) . '/Support/InsuranceCancelConcurrencyWorker.php',
                    (string) $policy['id'],
                    (string) $actorId,
                    $reason,
                    (string) $now,
                    $ready,
                ];
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                if (!is_resource($process)) {
                    self::fail('Unable to start an insurance cancellation worker.');
                }
                fclose($pipes[0]);
                $processes[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
            }
            $deadline = microtime(true) + 12;
            do {
                $allReady = count(array_filter($processes, static fn (array $worker): bool => is_file($worker['ready']))) === 2;
                if ($allReady) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertTrue($allReady, 'Both cancellation workers must reach their service call.');
            usleep(150000);
            foreach ($processes as $worker) {
                self::assertTrue(proc_get_status($worker['process'])['running'], 'Cancellation worker should wait for the policy row.');
            }
            $this->pdo->commit();

            $results = [];
            foreach ($processes as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                proc_close($worker['process']);
                @unlink($worker['ready']);
                $result = json_decode((string) $stdout, true);
                self::assertIsArray($result, 'Worker returned invalid JSON. ' . (string) $stderr);
                $results[] = $result;
            }
            self::assertSame(1, count(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true)));
            self::assertSame(1, count(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? true) === false)));
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($processes as $worker) {
                if (is_resource($worker['process'])) {
                    @proc_terminate($worker['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (isset($worker['pipes'][$pipeNumber]) && is_resource($worker['pipes'][$pipeNumber])) {
                            fclose($worker['pipes'][$pipeNumber]);
                        }
                    }
                    @proc_close($worker['process']);
                }
                @unlink($worker['ready']);
            }
        }

        $stored = $this->policyRow($policy['id']);
        self::assertContains((int) $stored['cancelled_by_user_id'], [$lease['actor_id'], $otherActor]);
        self::assertContains($stored['cancel_reason'], ['Solicitud A', 'Solicitud B']);
        self::assertContains($stored['cancelled_at'], [gmdate('Y-m-d H:i:s', 1791980001), gmdate('Y-m-d H:i:s', 1791980002)]);
        self::assertSame('cancelled', $service->findPolicy($policy['id'], '2026-10-14')['status']);
    }

    public function testCreateClaimEnforcesCoverageAndCancellationLocalDate(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $valid = $service->createClaim($policy['id'], $this->claimInput('2026-10-14'), null, $lease['actor_id'], '2026-10-14', 1791980001);
        self::assertSame('draft', $valid['status']);
        self::assertSame('100000.00', $valid['claimed_amount']);
        self::assertSame([], $valid['checklist']);
        self::assertNull($valid['filed_on']);

        foreach (['2026-09-30', '2026-10-15', '2027-10-01'] as $incidentOn) {
            $this->assertRejected(fn () => $service->createClaim(
                $policy['id'], $this->claimInput($incidentOn), null, $lease['actor_id'], '2026-10-14', 1791980002,
            ));
        }
        $this->assertRejected(fn () => $service->createClaim(
            $policy['id'], $this->claimInput('2026-10-14', '0'), null, $lease['actor_id'], '2026-10-14', 1791980003,
        ));

        $cancelled = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980004);
        $service->cancelPolicy($cancelled['id'], $lease['actor_id'], 'Cierre nocturno', strtotime('2026-10-14 03:00:00 UTC'));
        $historical = $service->createClaim(
            $cancelled['id'], $this->claimInput('2026-10-13'), null, $lease['actor_id'], '2026-10-14', 1791980005,
        );
        self::assertSame('draft', $historical['status']);
        $this->assertRejected(fn () => $service->createClaim(
            $cancelled['id'], $this->claimInput('2026-10-14'), null, $lease['actor_id'], '2026-10-14', 1791980006,
        ));
    }

    public function testClaimTransitionsValidateDatesAndFreezeTerminalFieldsAndChecklist(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $input = $this->claimInput('2026-10-10');
        $input['checklist'] = [['key' => 'police_report', 'label' => 'Informe', 'required' => true, 'completed' => false]];
        $claim = $service->createClaim($policy['id'], $input, null, $lease['actor_id'], '2026-10-14', 1791980001);

        foreach ([
            ['filed', ['filed_on' => '2026-10-09']],
            ['filed', ['filed_on' => '2026-10-15']],
            ['filed', []],
            ['approved', ['filed_on' => '2026-10-14', 'resolved_on' => '2026-10-14']],
        ] as [$target, $details]) {
            $this->assertRejected(fn () => $service->transitionClaim(
                $claim['id'], $target, $details, $lease['actor_id'], '2026-10-14', 1791980002,
            ));
        }
        $this->assertRejected(fn () => $service->updateClaimChecklist(
            $claim['id'], [
                ['key' => 'same', 'label' => 'Uno', 'required' => true, 'completed' => false],
                ['key' => 'same', 'label' => 'Dos', 'required' => false, 'completed' => false],
            ], 1791980002,
        ));
        $this->assertRejected(fn () => $service->updateClaimChecklist(
            $claim['id'], [['key' => 'invalid key', 'label' => 'Uno', 'required' => true, 'completed' => false]], 1791980002,
        ));
        $this->assertRejected(fn () => $service->updateClaimChecklist(
            $claim['id'], [['key' => 'required', 'label' => 'Uno', 'required' => 'yes', 'completed' => false]], 1791980002,
        ));

        $filed = $service->transitionClaim(
            $claim['id'], 'filed', ['filed_on' => '2026-10-14', 'radicado' => ' R-100 '],
            $lease['actor_id'], '2026-10-14', 1791980003,
        );
        self::assertSame('filed', $filed['status']);
        self::assertSame('R-100', $filed['radicado']);
        $review = $service->transitionClaim(
            $claim['id'], 'in_review', [], $lease['actor_id'], '2026-10-14', 1791980004,
        );
        self::assertSame('2026-10-14', $review['filed_on']);
        self::assertSame('R-100', $review['radicado']);
        $this->assertRejected(fn () => $service->transitionClaim(
            $claim['id'], 'approved', ['resolved_on' => '2026-10-13'], $lease['actor_id'], '2026-10-14', 1791980005,
        ));
        $approved = $service->transitionClaim(
            $claim['id'], 'approved', ['resolved_on' => '2026-10-14', 'resolution_notes' => 'Aceptada parcialmente'],
            $lease['actor_id'], '2026-10-14', 1791980006,
        );
        self::assertSame('approved', $approved['status']);
        self::assertSame('Aceptada parcialmente', $approved['resolution_notes']);
        $this->assertRejected(fn () => $service->transitionClaim(
            $claim['id'], 'rejected', ['resolved_on' => '2026-10-14'], $lease['actor_id'], '2026-10-14', 1791980007,
        ));
        $this->assertRejected(fn () => $service->updateClaimChecklist(
            $claim['id'], [['key' => 'police_report', 'label' => 'Informe', 'required' => true, 'completed' => true]], 1791980008,
        ));
        $this->assertRejected(fn () => $service->updateDraftClaim(
            $claim['id'], ['description' => 'Alterar terminal'], '2026-10-14', 1791980008,
        ));
    }

    public function testDraftClaimCanBeCorrectedButChecklistIsStrictlyValidated(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $claim = $service->createClaim($policy['id'], $this->claimInput('2026-10-10'), null, $lease['actor_id'], '2026-10-14', 1791980001);
        $updated = $service->updateDraftClaim(
            $claim['id'], ['incident_on' => '2026-10-11', 'description' => '  Daño corregido  ', 'claimed_amount' => '200000.5'],
            '2026-10-14', 1791980002,
        );
        self::assertSame('2026-10-11', $updated['incident_on']);
        self::assertSame('Daño corregido', $updated['description']);
        self::assertSame('200000.50', $updated['claimed_amount']);
        $checklist = $service->updateClaimChecklist($claim['id'], [
            ['key' => 'invoice-1', 'label' => 'Factura', 'required' => true, 'completed' => true],
        ], 1791980003);
        self::assertSame('invoice-1', $checklist['checklist'][0]['key']);
    }

    public function testClaimDocumentsCanBeRemovedOnlyInDraftAndRemainAppendOnlyAfterFiling(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput(), $lease['actor_id'], 1791980000);
        $initial = $service->createClaim(
            $policy['id'],
            $this->claimInput('2026-10-10') + ['document_label' => 'Factura inicial'],
            $this->upload($this->pdfBytes(), 'invoice.pdf', 'application/pdf'),
            $lease['actor_id'], '2026-10-14', 1791980001,
        );
        self::assertCount(1, $initial['documents']);
        $initialDocument = $initial['documents'][0];
        self::assertArrayNotHasKey('stored_name', $initialDocument);
        self::assertSame($this->pdfBytes(), $service->readClaimDocument($initial['id'], $initialDocument['document_id'])['bytes']);
        $removed = $service->removeClaimDocument($initial['id'], $initialDocument['document_id'], 1791980002);
        self::assertCount(0, $removed['documents']);

        $withEvidence = $service->addClaimDocument(
            $initial['id'], 'Informe', $this->upload($this->pdfBytes(), 'report.pdf', 'application/pdf'), 1791980003,
        );
        $filed = $service->transitionClaim(
            $initial['id'], 'filed', ['filed_on' => '2026-10-14'], $lease['actor_id'], '2026-10-14', 1791980004,
        );
        self::assertCount(1, $filed['documents']);
        $this->assertRejected(fn () => $service->removeClaimDocument(
            $initial['id'], $withEvidence['documents'][0]['document_id'], 1791980005,
        ));
        $appended = $service->addClaimDocument(
            $initial['id'], 'Comunicación', $this->upload($this->pdfBytes(), 'message.pdf', 'application/pdf'), 1791980006,
        );
        self::assertCount(2, $appended['documents']);
    }

    public function testTenantPolicySummaryMasksNumberAndOmitsInternalAndClaimData(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $shortInput = $this->policyInput();
        $shortInput['policy_number'] = '1234';
        $shortInput['internal_notes'] = 'Solo staff';
        $shortInput['coverage'][0]['notes'] = 'Deducible especial';
        $service->createPolicy($lease['lease_id'], $shortInput, $lease['actor_id'], 1791980000);
        $longInput = $this->policyInput();
        $longInput['policy_number'] = 'POL-12345';
        $service->createPolicy($lease['lease_id'], $longInput, $lease['actor_id'], 1791980001);

        $summary = $service->tenantPolicySummary($lease['lease_id'], '2026-10-14');
        self::assertCount(2, $summary);
        self::assertSame('••••2345', $summary[0]['policy_number']);
        self::assertSame('••••', $summary[1]['policy_number']);
        self::assertSame([
            'name' => 'Responsabilidad civil',
            'limit_amount' => '1000000.00',
            'deductible_amount' => null,
        ], $summary[0]['coverage'][0]);
        foreach ($summary as $policySummary) {
            self::assertSame([
                'provider_name', 'product_name', 'policy_number', 'coverage_start', 'coverage_end', 'status', 'coverage',
            ], array_keys($policySummary));
        }
        self::assertStringNotContainsString('Solo staff', json_encode($summary, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('Deducible especial', json_encode($summary, JSON_THROW_ON_ERROR));
    }

    private function service(): InsuranceService
    {
        return new InsuranceService($this->pdo, $this->documentDirectory);
    }

    private function policyInput(): array
    {
        return [
            'provider_name' => 'Aseguradora Uno',
            'product_name' => 'Hogar protegido',
            'policy_number' => 'P-100',
            'coverage_start' => '2026-10-01',
            'coverage_end' => '2027-09-30',
            'coverage' => [['name' => 'Responsabilidad civil', 'limit_amount' => '1000000.00', 'deductible_amount' => null, 'notes' => null]],
        ];
    }

    private function claimInput(string $incidentOn, ?string $claimedAmount = '100000'): array
    {
        return [
            'incident_on' => $incidentOn,
            'description' => '  Daño en tubería  ',
            'claimed_amount' => $claimedAmount,
            'checklist' => [],
        ];
    }

    private function policyRow(int $policyId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM insurance_policies WHERE id = :id');
        $statement->execute(['id' => $policyId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function assertRejected(callable $operation): void
    {
        $rejected = false;
        try {
            $operation();
        } catch (RuntimeException) {
            $rejected = true;
        }
        self::assertTrue($rejected, 'Expected policy operation to be rejected.');
    }

    private function upload(string $bytes, string $clientName, string $clientType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_insurance_upload_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create insurance document upload fixture.');
        }
        $this->temporaryFiles[] = $path;
        return new UploadedFile($path, $clientName, $clientType, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    }

    private function createLease(): array
    {
        $suffix = bin2hex(random_bytes(5));
        $actor = $this->pdo->prepare('INSERT INTO users (role, full_name, email, password_hash) VALUES (\'admin\', :name, :email, :hash)');
        $actor->execute(['name' => 'Admin ' . $suffix, 'email' => 'admin-' . $suffix . '@example.test', 'hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT)]);
        $actorId = (int) $this->pdo->lastInsertId();
        $tenant = $this->pdo->prepare('INSERT INTO users (role, full_name, email, password_hash) VALUES (\'tenant\', :name, :email, :hash)');
        $tenant->execute(['name' => 'Tenant ' . $suffix, 'email' => 'tenant-' . $suffix . '@example.test', 'hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT)]);
        $tenantId = (int) $this->pdo->lastInsertId();
        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city) VALUES (:name, \'Calle 1\', \'Bogotá\')');
        $property->execute(['name' => 'Property ' . $suffix]);
        $unit = $this->pdo->prepare('INSERT INTO units (property_id, public_key, type, code, status) VALUES (:property, :key, \'apartment\', :code, \'occupied\')');
        $unit->execute(['property' => (int) $this->pdo->lastInsertId(), 'key' => bin2hex(random_bytes(12)), 'code' => 'APT-' . substr($suffix, 0, 5)]);
        $unitId = (int) $this->pdo->lastInsertId();
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit, \'cc\', :number, :name, \'3001234567\', :email, \'approved\', :token, \'2027-01-01 00:00:00\', \'2026-10-13 10:00:00\')',
        );
        $candidate->execute(['unit' => $unitId, 'number' => 'DOC-' . $suffix, 'name' => 'Candidate ' . $suffix, 'email' => 'candidate-' . $suffix . '@example.test', 'token' => hash('sha256', $suffix)]);
        $candidateId = (int) $this->pdo->lastInsertId();
        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type) VALUES (:name, \'urban_housing\')');
        $template->execute(['name' => 'Template ' . $suffix]);
        $version = $this->pdo->prepare('INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) VALUES (:template, 1, \'Contrato\', \'[]\')');
        $version->execute(['template' => (int) $this->pdo->lastInsertId()]);
        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, start_date, end_date, duration_text) '
            . 'VALUES (:candidate, :unit, :version, \'urban_housing\', \'finalized\', 1500000, 0, \'2026-10-01\', \'2027-09-30\', \'Doce meses\')',
        );
        $contract->execute(['candidate' => $candidateId, 'unit' => $unitId, 'version' => (int) $this->pdo->lastInsertId()]);
        $lease = $this->pdo->prepare('INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) VALUES (:contract, :tenant, :actor, \'2026-10-01 12:00:00\')');
        $lease->execute(['contract' => (int) $this->pdo->lastInsertId(), 'tenant' => $tenantId, 'actor' => $actorId]);

        return ['lease_id' => (int) $this->pdo->lastInsertId(), 'actor_id' => $actorId];
    }

    private function insertUser(string $role): int
    {
        $suffix = bin2hex(random_bytes(5));
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash) VALUES (:role, :name, :email, :hash)',
        );
        $statement->execute([
            'role' => $role,
            'name' => 'Actor ' . $suffix,
            'email' => 'actor-' . $suffix . '@example.test',
            'hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
