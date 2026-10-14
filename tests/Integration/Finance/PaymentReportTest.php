<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\UploadedFile;

final class PaymentReportTest extends TestCase
{
    private PDO $pdo;
    private string $receiptDirectory;
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
            $safeTable = str_replace('`', '``', (string) $table);
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safeTable));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
        $this->receiptDirectory = sys_get_temp_dir() . '/alquilapro-payment-receipts-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        foreach (glob($this->receiptDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }
        if (is_dir($this->receiptDirectory)) {
            @rmdir($this->receiptDirectory);
        }
    }

    public function testReportPersistsPendingPaymentWithCanonicalSnapshotAndLeaseProjection(): void
    {
        $fixture = $this->createLease();
        $chargeId = $this->insertCharge($fixture['lease_id'], '100000.00');
        $methodId = $this->insertPaymentMethod(null, 'Cuenta de ahorros', true);
        $now = $this->now();

        $reported = $this->service()->reportPayment(
            $fixture['lease_id'], $methodId, '001250.5', '2026-10-12', '08:04',
            [['charge_id' => $chargeId, 'amount' => '1250.50']], null, $now,
        );

        self::assertSame('pending_reconciliation', $reported['state']);
        self::assertSame('1250.50', $reported['amount']);
        self::assertSame('2026-10-12', $reported['paid_on']);
        self::assertSame('08:04:00', $reported['paid_time']);
        self::assertSame('pending_reconciliation', $reported['state']);
        self::assertSame([['charge_id' => $chargeId, 'amount' => '1250.50']], $reported['allocations']);
        self::assertSame('Cuenta de ahorros', $reported['payment_method_snapshot']['label']);
        self::assertSame('Banco Demo', $reported['payment_method_snapshot']['institution_name']);
        self::assertSame('1234567890', $reported['payment_method_snapshot']['account_number']);
        self::assertArrayNotHasKey('qr_stored_name', $reported['payment_method_snapshot']);
        self::assertArrayNotHasKey('qr_sha256', $reported['payment_method_snapshot']);
        self::assertArrayNotHasKey('password', $reported['payment_method_snapshot']);

        $rows = $this->service()->listPaymentsForLease($fixture['lease_id']);
        self::assertCount(1, $rows);
        self::assertSame($reported['id'], $rows[0]['id']);
        self::assertSame('pending_reconciliation', $rows[0]['state']);

        $stored = $this->payment((int) $reported['id']);
        self::assertSame('1250.50', $stored['amount']);
        self::assertSame('2026-10-12', $stored['paid_on']);
        self::assertSame('08:04:00', $stored['paid_time']);
        self::assertSame($this->databaseDateTime($now), $stored['reported_at']);
        self::assertNull($stored['reconciled_at']);
        self::assertNull($stored['rejected_at']);
    }

    public function testEndedLeaseCanReportWhileItHasOutstandingCharges(): void
    {
        $fixture = $this->createLease(ended: true);
        $chargeId = $this->insertCharge($fixture['lease_id'], '50000.00');
        $methodId = $this->insertPaymentMethod(null, 'Global', true);

        $reported = $this->service()->reportPayment(
            $fixture['lease_id'], $methodId, '50000', '2026-10-10', null,
            [['charge_id' => $chargeId, 'amount' => '50000']], null, $this->now(),
        );

        self::assertSame('pending_reconciliation', $reported['state']);
        self::assertSame($fixture['lease_id'], $reported['lease_id']);
    }

    public function testReportRejectsInvalidMoneyDatesTimesAndAllocationSums(): void
    {
        $fixture = $this->createLease();
        $chargeId = $this->insertCharge($fixture['lease_id'], '100000.00');
        $methodId = $this->insertPaymentMethod(null, 'Global', true);
        $validAllocations = [['charge_id' => $chargeId, 'amount' => '10.00']];

        foreach (['', '0', '0.00', '-1', '+1', '1.234', '.50', '1.', '10000000000.00'] as $amount) {
            $this->assertRejected(fn () => $this->service()->reportPayment(
                $fixture['lease_id'], $methodId, $amount, '2026-10-12', null,
                $validAllocations, null, $this->now(),
            ));
        }
        foreach (['2026-10-14', '2026-02-30', '2026-13-01', '2026-10-2', 'bad'] as $date) {
            $this->assertRejected(fn () => $this->service()->reportPayment(
                $fixture['lease_id'], $methodId, '10', $date, null,
                $validAllocations, null, $this->now(),
            ));
        }
        foreach (['25:00', '12:60', '1:20', 'not-time'] as $time) {
            $this->assertRejected(fn () => $this->service()->reportPayment(
                $fixture['lease_id'], $methodId, '10', '2026-10-12', $time,
                $validAllocations, null, $this->now(),
            ));
        }
        $this->assertRejected(fn () => $this->service()->reportPayment(
            $fixture['lease_id'], $methodId, '11', '2026-10-12', null,
            $validAllocations, null, $this->now(),
        ));

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
    }

    public function testReportRequiresCurrentlyActiveMethodApplicableToLeaseProperty(): void
    {
        $first = $this->createLease();
        $second = $this->createLease();
        $chargeId = $this->insertCharge($first['lease_id'], '100000.00');
        $otherPropertyMethod = $this->insertPaymentMethod($second['property_id'], 'Otro inmueble', true);
        $inactiveMethod = $this->insertPaymentMethod(null, 'Inactivo', false);

        foreach ([$otherPropertyMethod, $inactiveMethod] as $methodId) {
            $this->assertRejected(fn () => $this->service()->reportPayment(
                $first['lease_id'], $methodId, '10', '2026-10-12', null,
                [['charge_id' => $chargeId, 'amount' => '10']], null, $this->now(),
            ));
        }

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
    }

    public function testProposedAllocationsMustBeUniqueSameLeaseNonvoidedAndWithinOutstanding(): void
    {
        $fixture = $this->createLease();
        $other = $this->createLease();
        $chargeId = $this->insertCharge($fixture['lease_id'], '100.00');
        $otherChargeId = $this->insertCharge($other['lease_id'], '100.00');
        $voidedChargeId = $this->insertCharge($fixture['lease_id'], '100.00', true);
        $methodId = $this->insertPaymentMethod(null, 'Global', true);

        $invalidAllocations = [
            [['charge_id' => $otherChargeId, 'amount' => '10']],
            [['charge_id' => $voidedChargeId, 'amount' => '10']],
            [['charge_id' => $chargeId, 'amount' => '100.01']],
            [['charge_id' => $chargeId, 'amount' => '5'], ['charge_id' => $chargeId, 'amount' => '5']],
            [['charge_id' => $chargeId, 'amount' => '0']],
        ];
        foreach ($invalidAllocations as $allocations) {
            $this->assertRejected(fn () => $this->service()->reportPayment(
                $fixture['lease_id'], $methodId, '10', '2026-10-12', null,
                $allocations, null, $this->now(),
            ));
        }

        $this->service()->reportPayment(
            $fixture['lease_id'], $methodId, '100', '2026-10-12', null,
            [['charge_id' => $chargeId, 'amount' => '100']], null, $this->now(),
        );
        $secondPending = $this->service()->reportPayment(
            $fixture['lease_id'], $methodId, '100', '2026-10-12', null,
            [['charge_id' => $chargeId, 'amount' => '100']], null, $this->now(),
        );
        self::assertSame('pending_reconciliation', $secondPending['state']);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
    }

    public function testReceiptFormatsAreStoredPrivatelyAndIntegrityCheckedFromBytes(): void
    {
        $fixture = $this->createLease();
        $chargeId = $this->insertCharge($fixture['lease_id'], '100000.00');
        $methodId = $this->insertPaymentMethod(null, 'Global', true);
        $files = [
            ['png', $this->pngBytes(), 'image/png'],
            ['jpg', $this->jpegBytes(), 'image/jpeg'],
            ['webp', $this->webpBytes(), 'image/webp'],
            ['pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n", 'application/pdf'],
        ];

        foreach ($files as [$extension, $bytes, $mime]) {
            $payment = $this->service()->reportPayment(
                $fixture['lease_id'], $methodId, '10', '2026-10-12', null,
                [['charge_id' => $chargeId, 'amount' => '10']],
                $this->upload($bytes, 'receipt.' . $extension, $mime), $this->now(),
            );
            self::assertTrue($payment['has_receipt']);
            self::assertArrayNotHasKey('receipt_stored_name', $payment);
            $stored = $this->payment((int) $payment['id']);
            self::assertSame($mime, $stored['receipt_mime_type']);
            self::assertSame(hash('sha256', $bytes), $stored['receipt_sha256']);
            self::assertSame(strlen($bytes), (int) $stored['receipt_size_bytes']);
            $private = $this->service()->privatePaymentReceipt((int) $payment['id']);
            self::assertIsArray($private);
            self::assertSame($mime, $private['mime']);
            self::assertSame(strlen($bytes), $private['size_bytes']);
            self::assertFileExists($private['path']);
        }

        $lastPayment = (int) $this->pdo->query('SELECT MAX(id) FROM payments')->fetchColumn();
        $private = $this->service()->privatePaymentReceipt($lastPayment);
        self::assertIsArray($private);
        file_put_contents($private['path'], 'tampered bytes');
        self::assertNull($this->service()->privatePaymentReceipt($lastPayment));
        unlink($private['path']);
        self::assertNull($this->service()->privatePaymentReceipt($lastPayment));
    }

    public function testReceiptRejectsEmptyOversizeUnsupportedAndSpoofedUploads(): void
    {
        $fixture = $this->createLease();
        $chargeId = $this->insertCharge($fixture['lease_id'], '100000.00');
        $methodId = $this->insertPaymentMethod(null, 'Global', true);
        $invalidUploads = [
            $this->upload('', 'empty.png', 'image/png'),
            $this->upload($this->pngBytes() . str_repeat('x', (5 * 1024 * 1024) + 1), 'large.png', 'image/png'),
            $this->upload('plain text', 'notes.txt', 'text/plain'),
            $this->upload($this->pngBytes(), 'photo.png', 'application/pdf'),
            $this->upload($this->pngBytes(), 'photo.pdf', 'image/png'),
        ];

        foreach ($invalidUploads as $upload) {
            $this->assertRejected(fn () => $this->service()->reportPayment(
                $fixture['lease_id'], $methodId, '10', '2026-10-12', null,
                [['charge_id' => $chargeId, 'amount' => '10']], $upload, $this->now(),
            ));
        }

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        self::assertSame([], $this->receiptFiles());
    }

    public function testReceiptFileIsRemovedWhenPaymentTransactionFails(): void
    {
        $fixture = $this->createLease();
        $chargeId = $this->insertCharge($fixture['lease_id'], '100000.00');
        $methodId = $this->insertPaymentMethod(null, 'Global', true);
        $this->pdo->exec(
            "CREATE TRIGGER fail_payment_allocation BEFORE INSERT ON payment_allocations "
            . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced allocation failure'",
        );

        try {
            $this->assertRejected(fn () => $this->service()->reportPayment(
                $fixture['lease_id'], $methodId, '10', '2026-10-12', null,
                [['charge_id' => $chargeId, 'amount' => '10']],
                $this->upload($this->pngBytes(), 'receipt.png', 'image/png'), $this->now(),
            ));
        } finally {
            $this->pdo->exec('DROP TRIGGER IF EXISTS fail_payment_allocation');
        }

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        self::assertSame([], $this->receiptFiles());
    }

    public function testDuplicateWarningsAreDerivedLeaseScopedAndNeverBlockReports(): void
    {
        $fixture = $this->createLease();
        $other = $this->createLease();
        $chargeId = $this->insertCharge($fixture['lease_id'], '100000.00');
        $otherChargeId = $this->insertCharge($other['lease_id'], '100000.00');
        $methodId = $this->insertPaymentMethod(null, 'Global', true);
        $first = $this->service()->reportPayment(
            $fixture['lease_id'], $methodId, '10', '2026-10-12', '08:00',
            [['charge_id' => $chargeId, 'amount' => '10']], null, $this->now(),
        );
        $second = $this->service()->reportPayment(
            $fixture['lease_id'], $methodId, '10', '2026-10-12', '08:05',
            [['charge_id' => $chargeId, 'amount' => '10']], null, $this->now(),
        );
        self::assertSame('pending_reconciliation', $second['state']);
        self::assertSame((int) $first['id'], $second['duplicate_warnings'][0]['payment_id']);
        self::assertSame('payment_details', $second['duplicate_warnings'][0]['kind']);
        self::assertSame('elevated', $second['duplicate_warnings'][0]['signal']);

        $otherPayment = $this->service()->reportPayment(
            $other['lease_id'], $methodId, '10', '2026-10-12', '08:00',
            [['charge_id' => $otherChargeId, 'amount' => '10']], null, $this->now(),
        );
        self::assertSame([], $otherPayment['duplicate_warnings']);

        $receipt = $this->service()->reportPayment(
            $fixture['lease_id'], $methodId, '12', '2026-10-12', null,
            [['charge_id' => $chargeId, 'amount' => '12']],
            $this->upload($this->pngBytes(), 'receipt.png', 'image/png'), $this->now(),
        );
        $this->pdo->prepare(
            'UPDATE payments SET rejected_by_user_id = :actor, rejected_at = :at, rejection_reason = :reason WHERE id = :id',
        )->execute([
            'actor' => $fixture['actor_id'], 'at' => $this->databaseDateTime($this->now()),
            'reason' => 'Reporte rechazado para probar coincidencia', 'id' => $receipt['id'],
        ]);
        $sameReceipt = $this->service()->reportPayment(
            $fixture['lease_id'], $methodId, '13', '2026-10-11', null,
            [['charge_id' => $chargeId, 'amount' => '13']],
            $this->upload($this->pngBytes(), 'another-receipt.png', 'image/png'), $this->now(),
        );
        self::assertSame('pending_reconciliation', $sameReceipt['state']);
        self::assertSame((int) $receipt['id'], $sameReceipt['duplicate_warnings'][0]['payment_id']);
        self::assertSame('receipt_hash', $sameReceipt['duplicate_warnings'][0]['kind']);
        self::assertSame('high', $sameReceipt['duplicate_warnings'][0]['signal']);

        $columns = $this->pdo->query('SHOW COLUMNS FROM payments')->fetchAll(PDO::FETCH_COLUMN);
        self::assertNotContains('is_duplicate', $columns);
        self::assertNotContains('duplicate_of_id', $columns);
        self::assertNotContains('duplicate_score', $columns);
    }

    private function service(): FinanceService
    {
        return new FinanceService(
            $this->pdo,
            new PaymentMethodService($this->pdo, sys_get_temp_dir()),
            $this->receiptDirectory,
        );
    }

    /** @return array{lease_id:int,tenant_id:int,actor_id:int,property_id:int} */
    private function createLease(bool $ended = false): array
    {
        $suffix = bin2hex(random_bytes(5));
        $tenantId = $this->insertUser('tenant', 'Tenant ' . $suffix, 'tenant-' . $suffix . '@example.com');
        $actorId = $this->insertUser('admin', 'Admin ' . $suffix, 'admin-' . $suffix . '@example.com');
        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city) VALUES (?, ?, ?)');
        $property->execute(['Property ' . $suffix, 'Calle 1', 'Bogotá']);
        $propertyId = (int) $this->pdo->lastInsertId();
        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) VALUES (?, ?, ?, ?, ?)',
        );
        $unit->execute([$propertyId, bin2hex(random_bytes(12)), 'apartment', 'APT-' . strtoupper($suffix), 'occupied']);
        $unitId = (int) $this->pdo->lastInsertId();
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $candidate->execute([
            $unitId, 'cc', 'DOC-' . $suffix, 'Candidate ' . $suffix, '3001234567',
            'candidate-' . $suffix . '@example.com', 'approved', hash('sha256', $suffix),
            '2027-01-01 00:00:00', '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();
        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type) VALUES (?, ?)');
        $template->execute(['Template ' . $suffix, 'urban_housing']);
        $templateId = (int) $this->pdo->lastInsertId();
        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) '
            . 'VALUES (?, 1, ?, ?)',
        );
        $version->execute([$templateId, 'Contrato', '[]']);
        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, '
            . 'administration_fee, start_date, end_date, duration_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $contract->execute([
            $candidateId, $unitId, (int) $this->pdo->lastInsertId(), 'urban_housing', 'finalized',
            '1500000.00', '200000.00', '2026-10-01', '2027-09-30', 'Doce meses',
        ]);
        $contractId = (int) $this->pdo->lastInsertId();
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at, ended_by_user_id, ended_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?)',
        );
        $lease->execute([
            $contractId, $tenantId, $actorId, '2026-10-01 12:00:00', $ended ? $actorId : null,
            $ended ? '2026-10-10 12:00:00' : null,
        ]);

        return [
            'lease_id' => (int) $this->pdo->lastInsertId(),
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'property_id' => $propertyId,
        ];
    }

    private function insertUser(string $role, string $name, string $email): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash) VALUES (?, ?, ?, ?)',
        );
        $statement->execute([$role, $name, $email, password_hash('TemporaryPass123', PASSWORD_DEFAULT)]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertCharge(int $leaseId, string $amount, bool $voided = false): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO charges (lease_id, type, due_date, amount, description, created_by_user_id, '
            . 'voided_by_user_id, voided_at, void_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $statement->execute([
            $leaseId, 'rent', '2026-10-05', $amount, 'Cargo prueba', 1,
            $voided ? 1 : null, $voided ? '2026-10-06 12:00:00' : null, $voided ? 'Anulado' : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertPaymentMethod(?int $propertyId, string $label, bool $active): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO payment_methods (property_id, type, label, recipient_name, institution_name, account_type, '
            . 'account_number, instructions, qr_stored_name, qr_sha256, is_active) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $statement->execute([
            $propertyId, 'bank_account', $label, 'Arrendador Demo', 'Banco Demo', 'savings',
            '1234567890', 'Referencia de pago', 'secret-qr.png', str_repeat('a', 64), $active ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function payment(int $paymentId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM payments WHERE id = ?');
        $statement->execute([$paymentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (RuntimeException) {
            return;
        }

        self::fail('Expected invalid payment report input to be rejected.');
    }

    private function now(): int
    {
        return (new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('America/Bogota')))->getTimestamp();
    }

    private function databaseDateTime(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone('America/Bogota'))
            ->format('Y-m-d H:i:s');
    }

    private function upload(string $bytes, string $clientName, string $clientMime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_payment_receipt_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create receipt upload fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $clientName, $clientMime, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function receiptFiles(): array
    {
        return array_values(glob($this->receiptDirectory . DIRECTORY_SEPARATOR . '*') ?: []);
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }

    private function jpegBytes(): string
    {
        $bytes = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAwE/EAH/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAwE/EAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EAH/2Q==',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }

    private function webpBytes(): string
    {
        $bytes = base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v89WAAAAA==', true);
        self::assertIsString($bytes);

        return $bytes;
    }
}
