<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Community;

use AlquilaPro\Community\CommunityService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\UploadedFile;

final class CommunityDocumentsTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $documentDirectory;
    /** @var list<string> */
    private array $uploads = [];
    private int $actorId;
    private int $propertyId;
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

        $this->directory = sys_get_temp_dir() . '/alquila_community_documents_' . bin2hex(random_bytes(6));
        $this->documentDirectory = $this->directory . '/private-documents';
        mkdir($this->directory, 0700, true);

        $user = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (\'owner\', \'Community Documents Actor\', :email, :hash, 1)',
        );
        $user->execute([
            'email' => 'community-documents-' . bin2hex(random_bytes(5)) . '@example.test',
            'hash' => password_hash('CommunityDocumentsPass123!', PASSWORD_DEFAULT),
        ]);
        $this->actorId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO properties (name, address, city) VALUES ('Community Property', 'Calle 2', 'Bogotá')");
        $this->propertyId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_at) VALUES (:contract_id, :user_id, :activated_at)',
        );
        $lease->execute([
            'contract_id' => random_int(1_000_000, 2_000_000_000),
            'user_id' => $this->actorId,
            'activated_at' => '2026-10-14 10:00:00',
        ]);
        $this->leaseId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
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

    public function testDocumentsEnforceExactlyOneOfPropertyLeaseOrIncidentScope(): void
    {
        $service = $this->service();
        $incident = $this->createIncident();
        $propertyDocument = $service->createContentVersion(
            $this->propertyId, null, 'other', 'Property content', ['body' => 'Property'], null, $this->actorId, 1_800_000_000,
        );
        $leaseDocument = $service->createContentVersion(
            null, $this->leaseId, 'other', 'Lease content', ['body' => 'Lease'], null, $this->actorId, 1_800_000_001,
        );
        $incidentDocument = $service->createIncidentFileVersion(
            (int) $incident['id'],
            'Incident evidence',
            $this->upload($this->pngBytes()),
            null,
            $this->actorId,
            1_800_000_002,
        );

        self::assertSame($this->propertyId, $propertyDocument['property_id']);
        self::assertNull($propertyDocument['lease_id']);
        self::assertNull($propertyDocument['community_incident_id']);
        self::assertSame($this->leaseId, $leaseDocument['lease_id']);
        self::assertNull($leaseDocument['property_id']);
        self::assertNull($leaseDocument['community_incident_id']);
        self::assertSame((int) $incident['id'], $incidentDocument['community_incident_id']);
        self::assertNull($incidentDocument['property_id']);
        self::assertNull($incidentDocument['lease_id']);

        $this->assertInvalidDocumentScope(null, null, null);
        $this->assertInvalidDocumentScope($this->propertyId, $this->leaseId, null);
        $this->assertInvalidDocumentScope($this->propertyId, null, (int) $incident['id']);
        $this->assertInvalidDocumentScope(null, $this->leaseId, (int) $incident['id']);
    }

    public function testIncidentEvidenceUsesPrivateFileIntegrityAndIncidentScopedVersioning(): void
    {
        $community = new CommunityService($this->pdo);
        $firstIncident = $this->createIncident();
        $secondIncident = $community->create(
            $this->leaseId,
            'Daño en zona común',
            '2026-10-14',
            'Se encontró daño en una puerta.',
            $this->actorId,
            1_800_000_010,
        );
        $service = $this->service();
        $first = $service->createIncidentFileVersion(
            (int) $firstIncident['id'], 'Evidencia v1', $this->upload($this->pngBytes()), null, $this->actorId, 1_800_000_020,
        );
        $second = $service->createIncidentFileVersion(
            (int) $firstIncident['id'], 'Evidencia v2', $this->upload($this->pngBytes()), (int) $first['id'], $this->actorId, 1_800_000_030,
        );

        self::assertSame('incident_evidence', $second['type']);
        self::assertSame((int) $firstIncident['id'], $second['community_incident_id']);
        self::assertSame([$second['id']], array_column($service->listCurrentForIncident((int) $firstIncident['id']), 'id'));
        self::assertSame([], $service->listCurrentForIncident((int) $secondIncident['id']));
        self::assertSame([$first['id'], $second['id']], array_column($service->history((int) $second['id']), 'id'));
        self::assertSame($this->pngBytes(), $service->privateFile((int) $second['id'])['contents']);

        $this->assertRejected(fn () => $service->createIncidentFileVersion(
            (int) $secondIncident['id'], 'Foreign lineage', $this->upload($this->pngBytes()), (int) $second['id'],
            $this->actorId, 1_800_000_040,
        ));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn());
        self::assertCount(2, glob($this->documentDirectory . '/*') ?: []);

        $path = $this->documentDirectory . DIRECTORY_SEPARATOR . $second['stored_name'];
        self::assertSame(strlen($this->pngBytes()), file_put_contents($path, 'x' . substr($this->pngBytes(), 1)));
        self::assertNull($service->privateFile((int) $second['id']));
    }

    public function testLegalNoticeContentIsValidatedAndVersionedAsPropertyDocument(): void
    {
        $service = $this->service();
        $content = [
            'source_label' => '  Alcaldía de Bogotá  ',
            'source_url' => 'https://example.test/legal/notice',
            'effective_on' => '2026-10-14',
            'notice_text' => '  Aviso legal vigente para residentes.  ',
        ];
        $first = $service->createContentVersion(
            $this->propertyId, null, 'legal_notice', 'Aviso legal v1', $content, null, $this->actorId, 1_800_000_000,
        );
        $second = $service->createContentVersion(
            $this->propertyId,
            null,
            'legal_notice',
            'Aviso legal v2',
            ['source_label' => 'Entidad', 'notice_text' => 'Versión corregida.'],
            (int) $first['id'],
            $this->actorId,
            1_800_000_001,
        );

        self::assertSame('legal_notice', $second['type']);
        self::assertSame([
            'source_label' => 'Alcaldía de Bogotá',
            'source_url' => 'https://example.test/legal/notice',
            'effective_on' => '2026-10-14',
            'notice_text' => 'Aviso legal vigente para residentes.',
        ], $first['content_json']);
        self::assertSame([
            'source_label' => 'Entidad',
            'source_url' => null,
            'effective_on' => null,
            'notice_text' => 'Versión corregida.',
        ], $second['content_json']);
        self::assertSame([$second['id']], array_column($service->listCurrentForProperty($this->propertyId), 'id'));
        self::assertSame([$first['id'], $second['id']], array_column($service->history((int) $second['id']), 'id'));

        foreach ([
            ['source_label' => 'Entidad', 'notice_text' => 'Texto', 'extra' => true],
            ['source_label' => 'Entidad', 'source_url' => 'javascript:alert(1)', 'notice_text' => 'Texto'],
            ['source_label' => 'Entidad', 'effective_on' => '2026-02-30', 'notice_text' => 'Texto'],
            ['source_label' => ' ', 'notice_text' => 'Texto'],
            ['source_label' => 'Entidad', 'notice_text' => '  '],
            ['source_label' => str_repeat('s', 191), 'notice_text' => 'Texto'],
            ['source_label' => 'Entidad', 'notice_text' => str_repeat('t', 10001)],
        ] as $invalid) {
            $this->assertRejected(fn () => $service->createContentVersion(
                $this->propertyId, null, 'legal_notice', 'Aviso inválido', $invalid, null, $this->actorId, 1_800_000_002,
            ));
        }
        $this->assertRejected(fn () => $service->createContentVersion(
            null, $this->leaseId, 'legal_notice', 'Aviso en lease', $content, null, $this->actorId, 1_800_000_003,
        ));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn());
    }

    private function service(): DocumentService
    {
        return new DocumentService($this->pdo, $this->documentDirectory);
    }

    /** @return array<string, mixed> */
    private function createIncident(): array
    {
        return (new CommunityService($this->pdo))->create(
            $this->leaseId,
            'Ruido',
            '2026-10-13',
            'Reporte de ruido en zona común.',
            $this->actorId,
            1_800_000_000,
        );
    }

    private function assertInvalidDocumentScope(?int $propertyId, ?int $leaseId, ?int $incidentId): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO documents (property_id, lease_id, community_incident_id, type, title, content_json, '
            . 'created_by_user_id, created_at) VALUES (:property_id, :lease_id, :incident_id, \'other\', '
            . '\'Invalid scope\', \'{}\', :actor_id, \'2027-01-15 08:00:00\')',
        );
        try {
            $insert->execute([
                'property_id' => $propertyId,
                'lease_id' => $leaseId,
                'incident_id' => $incidentId,
                'actor_id' => $this->actorId,
            ]);
            self::fail('A document with zero or multiple scopes must be rejected by the database.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected invalid document input or cross-scope lineage to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
    }

    private function upload(string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_community_document_');
        if ($path === false || file_put_contents($path, $bytes) !== strlen($bytes)) {
            self::fail('Unable to create community document upload fixture.');
        }
        $this->uploads[] = $path;
        return new UploadedFile($path, 'evidence.png', 'image/png', strlen($bytes), UPLOAD_ERR_OK, false);
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
