<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\ObligationService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\UploadedFile;

final class ObligationServiceTest extends TestCase
{
    private PDO $pdo;
    private string $supportDirectory;

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
            $safeTable = str_replace('`', '``', (string) $table);
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safeTable));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->supportDirectory = sys_get_temp_dir() . '/alquilapro-obligations-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->supportDirectory)) {
            foreach (glob($this->supportDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (is_file($path) || is_link($path)) {
                    @unlink($path);
                }
            }
            @rmdir($this->supportDirectory);
        }
    }

    public function testCreateValidatesTypesFieldsMoneyDatesAndExistingEndedLease(): void
    {
        $lease = $this->createLease(ended: true);
        $service = $this->service();

        $created = $service->create($lease['lease_id'], [
            'type' => 'utility',
            'service_name' => ' Acueducto ',
            'description' => ' Factura octubre ',
            'period_month' => '2026-10-01',
            'issued_on' => '2026-10-02',
            'due_on' => '2026-10-20',
            'amount' => '0001200.5',
            'external_reference' => '  REF-1  ',
        ], null, $lease['actor_id'], 1792000000);

        self::assertSame($lease['lease_id'], $created['lease_id']);
        self::assertSame('utility', $created['type']);
        self::assertSame('Acueducto', $created['service_name']);
        self::assertSame('1200.50', $created['amount']);
        self::assertSame('REF-1', $created['external_reference']);
        self::assertSame('open', $created['external_state']);
        self::assertFalse($created['is_charged']);
        self::assertFalse($created['external_overdue']);

        $administration = $service->create($lease['lease_id'], [
            'type' => 'administration_ph',
            'description' => 'Administración PH octubre',
            'period_month' => '2026-10-01',
            'amount' => '200000',
        ], null, $lease['actor_id'], 1792000001);
        self::assertNull($administration['service_name']);

        $fine = $service->create($lease['lease_id'], [
            'type' => 'fine_ph',
            'description' => 'Multa por ruido',
            'amount' => '25.00',
        ], null, $lease['actor_id'], 1792000002);
        self::assertSame('25.00', $fine['amount']);

        foreach ([
            ['type' => 'utility', 'description' => 'Concepto', 'amount' => '1'],
            ['type' => 'utility', 'service_name' => 'Gas', 'description' => 'Concepto', 'amount' => '0'],
            ['type' => 'utility', 'service_name' => 'Gas', 'description' => 'Concepto', 'amount' => '1.001'],
            ['type' => 'utility', 'service_name' => 'Gas', 'description' => 'Concepto', 'amount' => '10000000000.00'],
            ['type' => 'utility', 'service_name' => 'Gas', 'description' => 'Concepto', 'amount' => '1', 'period_month' => '2026-10-02'],
            ['type' => 'utility', 'service_name' => 'Gas', 'description' => 'Concepto', 'amount' => '1', 'issued_on' => '2026-02-30'],
            ['type' => 'utility', 'service_name' => 'Gas', 'description' => 'Concepto', 'amount' => '1', 'issued_on' => '2026-10-20', 'due_on' => '2026-10-19'],
            ['type' => 'electricity', 'description' => 'Concepto', 'amount' => '1'],
            ['type' => 'administration_ph', 'description' => 'Concepto', 'amount' => '1'],
            ['type' => 'administration_ph', 'description' => 'Concepto', 'amount' => '1', 'period_month' => '2026-10-01', 'service_name' => 'Administración'],
            ['type' => 'fine_ph', 'description' => 'Concepto', 'amount' => '1', 'service_name' => 'Multa'],
        ] as $invalidInput) {
            $this->assertRejected(fn () => $service->create(
                $lease['lease_id'], $invalidInput, null, $lease['actor_id'], 1792000003,
            ));
        }

        $this->assertRejected(fn () => $service->create(
            999999, ['type' => 'fine_ph', 'description' => 'Concepto', 'amount' => '1'], null, $lease['actor_id'], 1792000004,
        ));
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM lease_obligations')->fetchColumn());
    }

    public function testFindAndListsDeriveExternalStateAndOrderWithoutBalances(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $overdue = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Atrasada', 'amount' => '10', 'due_on' => '2026-10-13',
        ], null, $lease['actor_id'], 1792000000);
        $future = $service->create($lease['lease_id'], [
            'type' => 'utility', 'service_name' => 'Gas', 'description' => 'Futura', 'amount' => '20', 'due_on' => '2026-10-20',
        ], null, $lease['actor_id'], 1792000001);
        $noDueDate = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Sin fecha', 'amount' => '30',
        ], null, $lease['actor_id'], 1792000002);
        $settled = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Atendida', 'amount' => '40', 'due_on' => '2026-10-01',
        ], null, $lease['actor_id'], 1792000003);
        $this->pdo->prepare('UPDATE lease_obligations SET settled_at = :settled_at WHERE id = :id')
            ->execute(['settled_at' => '2026-10-10 12:00:00', 'id' => $settled['id']]);
        $voided = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Anulada', 'amount' => '50', 'due_on' => '2026-10-01',
        ], null, $lease['actor_id'], 1792000004);
        $this->pdo->prepare('UPDATE lease_obligations SET voided_at = :voided_at WHERE id = :id')
            ->execute(['voided_at' => '2026-10-11 12:00:00', 'id' => $voided['id']]);

        $projection = $service->find($overdue['id'], '2026-10-14');
        self::assertSame('overdue_external', $projection['external_state']);
        self::assertTrue($projection['external_overdue']);
        self::assertSame('10.00', $projection['amount']);
        self::assertArrayNotHasKey('balance', $projection);
        self::assertArrayNotHasKey('paid_amount', $projection);

        self::assertSame(
            [$settled['id'], $voided['id'], $overdue['id'], $future['id'], $noDueDate['id']],
            array_column($service->listForLease($lease['lease_id'], '2026-10-14'), 'id'),
        );
        self::assertSame('settled_external', $service->find($settled['id'], '2026-10-14')['external_state']);
        self::assertFalse($service->find($settled['id'], '2026-10-14')['external_overdue']);
        self::assertSame('voided', $service->find($voided['id'], '2026-10-14')['external_state']);
        self::assertFalse($service->find($voided['id'], '2026-10-14')['external_overdue']);
        self::assertSame(5, count($service->listAll('2026-10-14')));
        self::assertNull($service->find(999999, '2026-10-14'));
    }

    public function testUpdateIsAllowedOnlyBeforeChargeSettlementOrVoid(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $obligation = $service->create($lease['lease_id'], [
            'type' => 'utility',
            'service_name' => 'Gas',
            'description' => 'Anterior',
            'amount' => '10.00',
        ], null, $lease['actor_id'], 1792000000);

        $updated = $service->update($obligation['id'], [
            'type' => 'utility',
            'service_name' => 'Energía',
            'description' => 'Corregida',
            'amount' => '12.50',
            'period_month' => '2026-10-01',
        ], null, false, 1792000100);
        self::assertSame('Corregida', $updated['description']);
        self::assertSame('12.50', $updated['amount']);

        $this->pdo->prepare('UPDATE lease_obligations SET charge_id = :charge_id WHERE id = :id')
            ->execute(['charge_id' => $this->insertCharge($lease['lease_id'], $lease['actor_id']), 'id' => $obligation['id']]);
        $this->assertRejected(fn () => $service->update(
            $obligation['id'], ['type' => 'utility', 'service_name' => 'Gas', 'description' => 'Cambio', 'amount' => '1'], null, false, 1792000200,
        ));

        $second = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Multa', 'amount' => '15',
        ], null, $lease['actor_id'], 1792000300);
        $service->settleExternal($second['id'], $lease['actor_id'], null, 1792000400);
        $this->assertRejected(fn () => $service->update(
            $second['id'], ['type' => 'fine_ph', 'description' => 'Cambio', 'amount' => '1'], null, false, 1792000500,
        ));

        $third = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Anular', 'amount' => '15',
        ], null, $lease['actor_id'], 1792000600);
        $service->void($third['id'], $lease['actor_id'], 'Duplicada', 1792000700);
        $this->assertRejected(fn () => $service->update(
            $third['id'], ['type' => 'fine_ph', 'description' => 'Cambio', 'amount' => '1'], null, false, 1792000800,
        ));
    }

    public function testSupportsAcceptAllowedByteMimesAndReadIntegrityChecks(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $samples = [
            'image/png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/y0kAAAAASUVORK5CYII=', true),
            'image/jpeg' => "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9",
            'image/webp' => 'RIFF' . pack('V', 12) . 'WEBP' . 'VP8 ' . pack('V', 0),
            'application/pdf' => "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF",
        ];

        foreach ($samples as $mime => $bytes) {
            self::assertIsString($bytes);
            $created = $service->create($lease['lease_id'], [
                'type' => 'fine_ph', 'description' => 'Soporte ' . $mime, 'amount' => '10.00',
            ], $this->upload($bytes, $mime), $lease['actor_id'], 1792000000);
            self::assertTrue($created['has_source_support']);
            $support = $service->privateSourceSupport($created['id']);
            self::assertIsArray($support);
            self::assertSame($mime, $support['mime']);
            self::assertSame(strlen($bytes), $support['size_bytes']);
            self::assertFileExists($support['path']);
            self::assertStringStartsWith(sys_get_temp_dir(), $support['path']);
            self::assertArrayNotHasKey('stored_name', $support);
            self::assertSame(hash('sha256', $bytes), $this->obligationRow($created['id'])['source_support_sha256']);
        }
    }

    public function testSupportsRejectSpoofedUnsupportedEmptyAndOversizedFiles(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        foreach ([
            $this->upload('plain text', 'image/png'),
            $this->upload('plain text', 'text/plain'),
            $this->upload('', 'application/pdf'),
            $this->upload(str_repeat('x', 5 * 1024 * 1024 + 1), 'application/pdf'),
        ] as $upload) {
            $this->assertRejected(fn () => $service->create($lease['lease_id'], [
                'type' => 'fine_ph', 'description' => 'No válido', 'amount' => '10',
            ], $upload, $lease['actor_id'], 1792000000));
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM lease_obligations')->fetchColumn());
    }

    public function testMutableSourceSupportReplacementAndRemovalPreserveFileAuthority(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $firstBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/y0kAAAAASUVORK5CYII=', true);
        $replacementBytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        $created = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Aviso original', 'amount' => '10',
        ], $this->upload((string) $firstBytes, 'image/png'), $lease['actor_id'], 1792000000);
        $oldName = (string) $this->obligationRow($created['id'])['source_support_stored_name'];
        $oldPath = $this->supportDirectory . DIRECTORY_SEPARATOR . $oldName;

        $updated = $service->update(
            $created['id'], ['description' => 'Aviso corregido'], $this->upload($replacementBytes, 'application/pdf'), false, 1792000100,
        );
        self::assertSame('Aviso corregido', $updated['description']);
        self::assertFileDoesNotExist($oldPath);
        $replacement = $service->privateSourceSupport($created['id']);
        self::assertIsArray($replacement);
        self::assertSame('application/pdf', $replacement['mime']);
        self::assertSame(strlen($replacementBytes), $replacement['size_bytes']);

        $replacementPath = $replacement['path'];
        $service->update($created['id'], [], null, true, 1792000200);
        self::assertFileDoesNotExist($replacementPath);
        self::assertNull($service->privateSourceSupport($created['id']));
        self::assertFalse($service->find($created['id'], '2026-10-14')['has_source_support']);
    }

    public function testSettlementSupportIsPrivateOneTimeAndFrozenWithActorAndTime(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $created = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Multa', 'amount' => '10',
        ], null, $lease['actor_id'], 1792000000);
        $bytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";

        $settled = $service->settleExternal(
            $created['id'], $lease['actor_id'], $this->upload($bytes, 'application/pdf'), 1792000100,
        );
        self::assertSame('settled_external', $settled['external_state']);
        self::assertSame($lease['actor_id'], $settled['settled_by_user_id']);
        self::assertSame(gmdate('Y-m-d H:i:s', 1792000100), $settled['settled_at']);
        self::assertNotNull($service->privateSettlementSupport($created['id']));
        $before = $this->obligationRow($created['id']);
        $this->assertRejected(fn () => $service->settleExternal(
            $created['id'], $lease['actor_id'], $this->upload('%PDF-1.4\n%%EOF', 'application/pdf'), 1792000200,
        ));
        self::assertSame($before['settled_by_user_id'], $this->obligationRow($created['id'])['settled_by_user_id']);
        self::assertSame($before['settled_at'], $this->obligationRow($created['id'])['settled_at']);
        self::assertSame($before['settlement_support_stored_name'], $this->obligationRow($created['id'])['settlement_support_stored_name']);

        $withoutSupport = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Atendida sin documento', 'amount' => '15',
        ], null, $lease['actor_id'], 1792000300);
        $second = $service->settleExternal($withoutSupport['id'], $lease['actor_id'], null, 1792000400);
        self::assertSame('settled_external', $second['external_state']);
        self::assertNull($service->privateSettlementSupport($withoutSupport['id']));
    }

    public function testSourceSupportCannotBeReplacedOrRemovedAfterChargeOrSettlement(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/y0kAAAAASUVORK5CYII=', true);
        $pdf = '%PDF-1.4' . "\n%%EOF";

        $charged = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Con cargo', 'amount' => '10',
        ], $this->upload($png, 'image/png'), $lease['actor_id'], 1792000000);
        $this->pdo->prepare('UPDATE lease_obligations SET charge_id = :charge_id WHERE id = :id')
            ->execute(['charge_id' => $this->insertCharge($lease['lease_id'], $lease['actor_id']), 'id' => $charged['id']]);
        $chargedPath = $service->privateSourceSupport($charged['id'])['path'];
        $this->assertRejected(fn () => $service->update($charged['id'], [], null, true, 1792000100));
        $this->assertRejected(fn () => $service->update(
            $charged['id'], [], $this->upload($pdf, 'application/pdf'), false, 1792000200,
        ));
        self::assertFileExists($chargedPath);

        $settled = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Atendida', 'amount' => '15',
        ], $this->upload($png, 'image/png'), $lease['actor_id'], 1792000300);
        $service->settleExternal($settled['id'], $lease['actor_id'], null, 1792000400);
        $settledPath = $service->privateSourceSupport($settled['id'])['path'];
        $this->assertRejected(fn () => $service->update($settled['id'], [], null, true, 1792000500));
        $this->assertRejected(fn () => $service->update(
            $settled['id'], [], $this->upload($pdf, 'application/pdf'), false, 1792000600,
        ));
        self::assertFileExists($settledPath);
    }

    public function testSupportIntegrityFailureAndDatabaseFailureCleanOnlyNewFiles(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/y0kAAAAASUVORK5CYII=', true);
        $created = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Integridad', 'amount' => '10',
        ], $this->upload($png, 'image/png'), $lease['actor_id'], 1792000000);
        $support = $service->privateSourceSupport($created['id']);
        self::assertIsArray($support);
        file_put_contents($support['path'], 'tampered');
        self::assertNull($service->privateSourceSupport($created['id']));

        $newPath = $this->supportDirectory;
        $this->assertRejected(fn () => $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'FK failure', 'amount' => '10',
        ], $this->upload($png, 'image/png'), 999999, 1792000100));
        $remaining = glob($newPath . DIRECTORY_SEPARATOR . '*') ?: [];
        self::assertCount(1, $remaining, 'Only the pre-existing tampered file should remain.');

        $this->pdo->exec(
            "CREATE TRIGGER reject_obligation_update BEFORE UPDATE ON lease_obligations "
            . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced test failure'",
        );
        $before = $this->obligationRow($created['id']);
        $this->assertRejected(fn () => $service->update(
            $created['id'], ['description' => 'Cambio rechazado'], $this->upload('%PDF-1.4\n%%EOF', 'application/pdf'), false, 1792000200,
        ));
        $after = $this->obligationRow($created['id']);
        self::assertSame($before['source_support_stored_name'], $after['source_support_stored_name']);
        self::assertSame($before['description'], $after['description']);
        self::assertCount(1, glob($newPath . DIRECTORY_SEPARATOR . '*') ?: [], 'Failed update must remove its new file.');
    }

    public function testMissingTamperedOrVoidedSourceSupportFailsClosed(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $bytes = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/y0kAAAAASUVORK5CYII=', true);
        $created = $service->create($lease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Sin archivo', 'amount' => '10',
        ], $this->upload($bytes, 'image/png'), $lease['actor_id'], 1792000000);
        $support = $service->privateSourceSupport($created['id']);
        self::assertIsArray($support);
        unlink($support['path']);
        self::assertNull($service->privateSourceSupport($created['id']));

        $restored = $service->update(
            $created['id'], [], $this->upload($bytes, 'image/png'), false, 1792000100,
        );
        self::assertTrue($restored['has_source_support']);
        $service->void($created['id'], $lease['actor_id'], 'Duplicada', 1792000200);
        $this->assertRejected(fn () => $service->update(
            $created['id'], [], $this->upload('%PDF-1.4\n%%EOF', 'application/pdf'), false, 1792000300,
        ));
        self::assertNotNull($service->privateSourceSupport($created['id']));
    }

    private function service(): ObligationService
    {
        $paymentMethods = new PaymentMethodService($this->pdo, $this->supportDirectory . '-qrs');
        $finance = new FinanceService($this->pdo, $paymentMethods, $this->supportDirectory . '-receipts');

        return new ObligationService($this->pdo, $finance, $this->supportDirectory);
    }

    /** @return array{lease_id:int,actor_id:int} */
    private function createLease(bool $ended = false): array
    {
        $suffix = bin2hex(random_bytes(5));
        $tenantId = $this->insertUser('tenant', 'Tenant ' . $suffix, 'tenant-' . $suffix . '@example.com');
        $actorId = $this->insertUser('admin', 'Admin ' . $suffix, 'admin-' . $suffix . '@example.com');

        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city) VALUES (:name, :address, :city)');
        $property->execute(['name' => 'Property ' . $suffix, 'address' => 'Calle 1', 'city' => 'Bogotá']);
        $propertyId = (int) $this->pdo->lastInsertId();
        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) VALUES (:property_id, :public_key, :type, :code, :status)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'type' => 'apartment',
            'code' => 'APT-' . strtoupper(substr($suffix, 0, 6)),
            'status' => 'occupied',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :status, :token_hash, :expires_at, :consent_at)',
        );
        $candidate->execute([
            'unit_id' => $unitId,
            'document_type' => 'cc',
            'document_number' => 'DOC-' . strtoupper($suffix),
            'full_name' => 'Candidate ' . $suffix,
            'phone' => '3001234567',
            'email' => 'candidate-' . $suffix . '@example.com',
            'status' => 'approved',
            'token_hash' => hash('sha256', 'token-' . $suffix),
            'expires_at' => '2027-01-01 00:00:00',
            'consent_at' => '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();
        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type) VALUES (:name, :document_type)');
        $template->execute(['name' => 'Template ' . $suffix, 'document_type' => 'urban_housing']);
        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) VALUES (:template_id, 1, :body, :variables)',
        );
        $version->execute(['template_id' => (int) $this->pdo->lastInsertId(), 'body' => 'Contrato', 'variables' => '[]']);
        $versionId = (int) $this->pdo->lastInsertId();
        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, start_date, end_date, duration_text) '
            . 'VALUES (:candidate_id, :unit_id, :version_id, :document_type, :status, :rent, :fee, :start_date, :end_date, :duration)',
        );
        $contract->execute([
            'candidate_id' => $candidateId,
            'unit_id' => $unitId,
            'version_id' => $versionId,
            'document_type' => 'urban_housing',
            'status' => 'finalized',
            'rent' => '1500000.00',
            'fee' => '200000.00',
            'start_date' => '2026-10-01',
            'end_date' => '2027-09-30',
            'duration' => 'Doce meses',
        ]);
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at, ended_by_user_id, ended_at) '
            . 'VALUES (:contract_id, :user_id, :actor_id, :activated_at, :ended_by, :ended_at)',
        );
        $lease->execute([
            'contract_id' => (int) $this->pdo->lastInsertId(),
            'user_id' => $tenantId,
            'actor_id' => $actorId,
            'activated_at' => '2026-10-01 12:00:00',
            'ended_by' => $ended ? $actorId : null,
            'ended_at' => $ended ? '2026-10-10 12:00:00' : null,
        ]);

        return ['lease_id' => (int) $this->pdo->lastInsertId(), 'actor_id' => $actorId];
    }

    private function insertUser(string $role, string $name, string $email): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash) VALUES (:role, :name, :email, :password_hash)',
        );
        $statement->execute([
            'role' => $role,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertCharge(int $leaseId, int $actorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO charges (lease_id, type, due_date, amount, description, created_by_user_id) '
            . 'VALUES (:lease_id, :type, :due_date, :amount, :description, :creator)',
        );
        $statement->execute([
            'lease_id' => $leaseId,
            'type' => 'service',
            'due_date' => '2026-10-20',
            'amount' => '10.00',
            'description' => 'Cargo de prueba',
            'creator' => $actorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function obligationRow(int $obligationId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM lease_obligations WHERE id = :id');
        $statement->execute(['id' => $obligationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    private function upload(string $bytes, string $mime, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'obligation-upload-');
        if ($path === false) {
            self::fail('Unable to create an upload fixture.');
        }
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'support.' . ($mime === 'application/pdf' ? 'pdf' : 'img'), $mime, strlen($bytes), $error, false);
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (RuntimeException) {
            return;
        }

        self::fail('Expected invalid obligation operation to be rejected.');
    }
}
