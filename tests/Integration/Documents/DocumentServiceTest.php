<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Documents;

use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\UploadedFile;

final class DocumentServiceTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $documentDirectory;
    /** @var list<string> */
    private array $uploads = [];
    private int $actorId;
    private int $propertyId;
    private int $otherPropertyId;
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
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', str_replace('`', '``', (string) $table)));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_documents_' . bin2hex(random_bytes(6));
        $this->documentDirectory = $this->directory . '/private-documents';
        mkdir($this->directory, 0700, true);
        $this->actorId = $this->insertUser('documents-owner@example.test', 'owner');
        $this->propertyId = $this->insertProperty('Document Property A');
        $this->otherPropertyId = $this->insertProperty('Document Property B');
        $this->leaseId = $this->insertLease($this->propertyId);
    }

    protected function tearDown(): void
    {
        foreach ($this->uploads as $upload) {
            if (is_file($upload) || is_link($upload)) {
                @unlink($upload);
            }
        }
        foreach (glob($this->documentDirectory . '/*') ?: [] as $storedFile) {
            if (is_file($storedFile) || is_link($storedFile)) {
                @unlink($storedFile);
            }
        }
        @rmdir($this->documentDirectory);
        @rmdir($this->directory);
    }

    public function testCreatesFileAndContentVersionsAndKeepsRowsImmutable(): void
    {
        $service = $this->service();
        $manual = $service->createContentVersion(
            $this->propertyId,
            null,
            'manual_convivencia',
            'Manual v1',
            ['sections' => [['title' => 'Ruido', 'body' => 'Mantener niveles moderados.']]],
            null,
            $this->actorId,
            1_800_000_000,
        );
        $manualV2 = $service->createContentVersion(
            $this->propertyId,
            null,
            'manual_convivencia',
            'Manual v2',
            ['sections' => [['title' => 'Ruido', 'body' => 'Respetar los horarios de descanso.']]],
            $manual['id'],
            $this->actorId,
            1_800_000_100,
        );
        $file = $service->createFileVersion(
            $this->propertyId,
            null,
            'property_document',
            'Reglamento',
            $this->upload($this->pngBytes(), 'reglamento.png', 'image/png'),
            null,
            $this->actorId,
            1_800_000_200,
        );

        self::assertSame($manual['id'], $manualV2['supersedes_document_id']);
        self::assertSame('Manual v1', $service->find($manual['id'])['title']);
        self::assertSame('Mantener niveles moderados.', $service->find($manual['id'])['content_json']['sections'][0]['body']);
        self::assertSame('image/png', $file['mime_type']);
        self::assertSame(hash('sha256', $this->pngBytes()), $file['sha256']);
        self::assertSame(strlen($this->pngBytes()), $file['size_bytes']);
        self::assertMatchesRegularExpression('/\Adocument-[a-f0-9]{32}\.png\z/', $file['stored_name']);
        self::assertFileExists($this->documentDirectory . DIRECTORY_SEPARATOR . $file['stored_name']);

        $current = $service->listCurrentForProperty($this->propertyId);
        self::assertSame([$manualV2['id'], $file['id']], array_column($current, 'id'));
        self::assertSame([$manual['id'], $manualV2['id']], array_column($service->history($manual['id']), 'id'));
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn());
    }

    public function testManualPdfUsesTheSameCurrentVersionChainAsStructuredManual(): void
    {
        $service = $this->service();
        $json = $service->createContentVersion(
            $this->propertyId,
            null,
            'manual_convivencia',
            'Manual estructurado',
            ['sections' => [['title' => 'Ruido', 'body' => 'Respetar los horarios de descanso.']]],
            null,
            $this->actorId,
            1_800_000_000,
        );
        $pdfBytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n";
        $pdf = $service->createFileVersion(
            $this->propertyId,
            null,
            'manual_convivencia',
            'Manual PDF vigente',
            $this->upload($pdfBytes, 'manual.pdf', 'application/pdf'),
            (int) $json['id'],
            $this->actorId,
            1_800_000_100,
        );

        self::assertSame($json['id'], $pdf['supersedes_document_id']);
        self::assertSame('application/pdf', $pdf['mime_type']);
        self::assertSame([$pdf['id']], array_column(
            array_values(array_filter($service->listCurrentForProperty($this->propertyId), static fn (array $row): bool => $row['type'] === 'manual_convivencia')),
            'id',
        ));
        self::assertSame([$json['id'], $pdf['id']], array_column($service->history((int) $json['id']), 'id'));
        self::assertSame($pdfBytes, $service->privateFile((int) $pdf['id'])['contents']);
    }

    public function testRejectsInvalidTypeScopeTitleAndCrossLineageVersions(): void
    {
        $service = $this->service();
        $this->assertRejected(fn () => $service->createContentVersion(
            null, null, 'manual_convivencia', 'Manual', ['body' => 'x'], null, $this->actorId, 1_800_000_000,
        ));
        $this->assertRejected(fn () => $service->createContentVersion(
            $this->propertyId, $this->leaseId, 'manual_convivencia', 'Manual', ['body' => 'x'], null, $this->actorId, 1_800_000_000,
        ));
        $this->assertRejected(fn () => $service->createContentVersion(
            $this->propertyId, null, 'inventory_initial', 'Inventory', ['items' => []], null, $this->actorId, 1_800_000_000,
        ));
        $this->assertRejected(fn () => $service->createContentVersion(
            $this->propertyId, null, 'unknown', 'Unknown', ['body' => 'x'], null, $this->actorId, 1_800_000_000,
        ));
        $this->assertRejected(fn () => $service->createContentVersion(
            $this->propertyId, null, 'manual_convivencia', "bad\nname", ['body' => 'x'], null, $this->actorId, 1_800_000_000,
        ));
        $this->assertRejected(fn () => $service->createContentVersion(
            $this->propertyId, null, 'manual_convivencia', str_repeat('x', 191), ['body' => 'x'], null, $this->actorId, 1_800_000_000,
        ));

        $original = $service->createContentVersion(
            $this->propertyId, null, 'manual_convivencia', 'Manual A', ['body' => 'Original'], null, $this->actorId, 1_800_000_000,
        );
        $service->createContentVersion(
            $this->propertyId, null, 'manual_convivencia', 'Manual B', ['body' => 'Updated'], $original['id'], $this->actorId, 1_800_000_001,
        );
        $this->assertRejected(fn () => $service->createContentVersion(
            $this->otherPropertyId, null, 'manual_convivencia', 'Foreign', ['body' => 'No'], $original['id'], $this->actorId, 1_800_000_002,
        ));
        $this->assertRejected(fn () => $service->createContentVersion(
            $this->propertyId, null, 'other', 'Wrong type', ['body' => 'No'], $original['id'], $this->actorId, 1_800_000_003,
        ));
        $this->assertRejected(fn () => $service->createContentVersion(
            $this->propertyId, null, 'manual_convivencia', 'Branch', ['body' => 'No'], $original['id'], $this->actorId, 1_800_000_004,
        ));
        $this->assertRejected(fn () => $service->createContentVersion(
            $this->propertyId, null, 'manual_convivencia', 'Missing parent', ['body' => 'No'], 999999, $this->actorId, 1_800_000_005,
        ));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn());
    }

    public function testCurrentLeafQueriesAndHistoryStayWithinPropertyAndLeaseScopes(): void
    {
        $service = $this->service();
        $manual = $service->createContentVersion(
            $this->propertyId, null, 'manual_convivencia', 'Manual v1', ['sections' => []], null, $this->actorId, 1_800_000_000,
        );
        $currentManual = $service->createContentVersion(
            $this->propertyId, null, 'manual_convivencia', 'Manual v2', ['sections' => []], $manual['id'], $this->actorId, 1_800_000_001,
        );
        $inventory = $service->createContentVersion(
            null, $this->leaseId, 'inventory_initial', 'Inventory v1', ['general_notes' => null, 'items' => []], null, $this->actorId, 1_800_000_002,
        );
        $currentInventory = $service->createContentVersion(
            null, $this->leaseId, 'inventory_initial', 'Inventory v2', ['general_notes' => null, 'items' => []], $inventory['id'], $this->actorId, 1_800_000_003,
        );

        self::assertSame([$currentManual['id']], array_column($service->listCurrentForProperty($this->propertyId), 'id'));
        self::assertSame([$currentInventory['id']], array_column($service->listCurrentForLease($this->leaseId), 'id'));
        self::assertSame([$inventory['id'], $currentInventory['id']], array_column($service->history($currentInventory['id']), 'id'));
        self::assertSame([], $service->history(999999));
        self::assertSame([], $service->listCurrentForProperty(0));
        self::assertSame([], $service->listCurrentForLease(0));
    }

    public function testInventoryContentIsNormalizedAndRejectsUnknownOrUnboundedShapes(): void
    {
        $service = $this->service();
        $inventory = $service->createContentVersion(
            null,
            $this->leaseId,
            'inventory_initial',
            'Inventario inicial',
            [
                'general_notes' => '  Revisar al entregar.  ',
                'items' => [[
                    'area' => '  Sala  ',
                    'item' => '  Ventana principal  ',
                    'condition' => '  Buen estado  ',
                    'quantity' => 2,
                    'notes' => '  Sin rayones.  ',
                ]],
            ],
            null,
            $this->actorId,
            1_800_000_000,
        );

        self::assertSame('Revisar al entregar.', $inventory['content_json']['general_notes']);
        self::assertSame([
            'area' => 'Sala',
            'item' => 'Ventana principal',
            'condition' => 'Buen estado',
            'quantity' => 2,
            'notes' => 'Sin rayones.',
        ], $inventory['content_json']['items'][0]);

        $invalidContent = [
            ['items' => []],
            ['general_notes' => null, 'items' => [], 'extra' => 'no'],
            ['general_notes' => null, 'items' => 'not-a-list'],
            ['general_notes' => str_repeat('n', 4001), 'items' => []],
            ['general_notes' => null, 'items' => [['area' => 'Sala', 'item' => 'Mesa', 'condition' => 'Ok', 'quantity' => 1, 'notes' => null, 'extra' => true]]],
            ['general_notes' => null, 'items' => [['area' => '', 'item' => 'Mesa', 'condition' => 'Ok', 'quantity' => 1, 'notes' => null]]],
            ['general_notes' => null, 'items' => [['area' => 'Sala', 'item' => 'Mesa', 'condition' => 'Ok', 'quantity' => -1, 'notes' => null]]],
            ['general_notes' => null, 'items' => [['area' => 'Sala', 'item' => 'Mesa', 'condition' => 'Ok', 'quantity' => '2', 'notes' => null]]],
            ['general_notes' => null, 'items' => array_fill(0, 201, ['area' => 'Sala', 'item' => 'Mesa', 'condition' => 'Ok', 'quantity' => null, 'notes' => null])],
        ];
        foreach ($invalidContent as $content) {
            $this->assertRejected(fn () => $service->createContentVersion(
                null, $this->leaseId, 'inventory_final', 'Inventario final', $content, null, $this->actorId, 1_800_000_001,
            ));
        }
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn());
    }

    private function service(): DocumentService
    {
        if (!class_exists(DocumentService::class)) {
            self::fail('DocumentService must exist before its core tests can pass.');
        }
        return new DocumentService($this->pdo, $this->documentDirectory);
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected invalid document input or lineage to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
    }

    private function insertUser(string $email, string $role): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash) VALUES (:role, :name, :email, :hash)',
        );
        $statement->execute([
            'role' => $role,
            'name' => ucfirst($role) . ' Documents',
            'email' => $email,
            'hash' => password_hash('DocumentsPass123!', PASSWORD_DEFAULT),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function insertProperty(string $name): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city) VALUES (:name, \'Calle 1\', \'Bogotá\')',
        );
        $statement->execute(['name' => $name]);
        return (int) $this->pdo->lastInsertId();
    }

    private function insertLease(int $propertyId): int
    {
        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) '
            . 'VALUES (:property_id, :public_key, \'apartment\', \'DOC-1\', \'available\')',
        );
        $unit->execute(['property_id' => $propertyId, 'public_key' => bin2hex(random_bytes(12))]);
        $unitId = (int) $this->pdo->lastInsertId();
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, \'cc\', \'DOC-1\', \'Lease Candidate\', \'3001234567\', \'lease-doc@example.test\', '
            . '\'submitted\', :token_hash, \'2027-01-01 00:00:00\', \'2026-10-14 12:00:00\')',
        );
        $candidate->execute(['unit_id' => $unitId, 'token_hash' => hash('sha256', random_bytes(32))]);
        $candidateId = (int) $this->pdo->lastInsertId();
        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type, is_active) VALUES (\'Documents test contract\', \'urban_housing\', 1)',
        );
        $template->execute();
        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) '
            . 'VALUES (:template_id, 1, \'contract body\', \'[]\')',
        );
        $version->execute(['template_id' => $this->pdo->lastInsertId()]);
        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, '
            . 'start_date, end_date, duration_text) VALUES (:candidate_id, :unit_id, :version_id, \'urban_housing\', '
            . '\'finalized\', 1500000, \'2026-10-01\', \'2027-09-30\', \'Doce meses\')',
        );
        $contract->execute([
            'candidate_id' => $candidateId,
            'unit_id' => $unitId,
            'version_id' => $this->pdo->lastInsertId(),
        ]);
        $contractId = (int) $this->pdo->lastInsertId();
        $tenantId = $this->insertUser('lease-doc-tenant@example.test', 'tenant');
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) '
            . 'VALUES (:contract_id, :tenant_id, :actor_id, \'2026-10-01 12:00:00\')',
        );
        $lease->execute(['contract_id' => $contractId, 'tenant_id' => $tenantId, 'actor_id' => $this->actorId]);
        return (int) $this->pdo->lastInsertId();
    }

    private function upload(string $bytes, string $filename, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_document_upload_');
        if ($path === false || file_put_contents($path, $bytes) !== strlen($bytes)) {
            self::fail('Unable to create document upload fixture.');
        }
        $this->uploads[] = $path;
        return new UploadedFile($path, $filename, $mime, strlen($bytes), UPLOAD_ERR_OK, false);
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
}
