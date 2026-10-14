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

final class DocumentStorageSecurityTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $documentDirectory;
    /** @var list<string> */
    private array $uploads = [];
    private int $actorId;
    private int $propertyId;

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

        $this->directory = sys_get_temp_dir() . '/alquila_document_security_' . bin2hex(random_bytes(6));
        $this->documentDirectory = $this->directory . '/private-documents';
        mkdir($this->directory, 0700, true);
        $user = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash) VALUES (\'owner\', \'Document Owner\', \'storage-owner@example.test\', :hash)',
        );
        $user->execute(['hash' => password_hash('StoragePass123!', PASSWORD_DEFAULT)]);
        $this->actorId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO properties (name, address, city) VALUES ('Storage House', 'Calle 1', 'Bogotá')");
        $this->propertyId = (int) $this->pdo->lastInsertId();
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

    public function testMimeComesFromBytesAndPrivateReadReturnsVerifiedBoundedContents(): void
    {
        $service = $this->service();
        $document = $service->createFileVersion(
            $this->propertyId,
            null,
            'property_document',
            'Documento privado',
            $this->upload($this->pngBytes(), 'payload.pdf', 'application/pdf'),
            null,
            $this->actorId,
            1_800_000_000,
        );
        $private = $service->privateFile($document['id']);

        self::assertSame('image/png', $document['mime_type']);
        self::assertMatchesRegularExpression('/\Adocument-[a-f0-9]{32}\.png\z/', $document['stored_name']);
        self::assertSame(strlen($this->pngBytes()), $document['size_bytes']);
        self::assertSame(hash('sha256', $this->pngBytes()), $document['sha256']);
        self::assertIsArray($private);
        self::assertSame($this->pngBytes(), $private['contents']);
        self::assertSame('image/png', $private['mime']);
        self::assertArrayNotHasKey('path', $private);
    }

    public function testOversizedAndUnsupportedUploadsAreRejectedBeforeMetadataIsCreated(): void
    {
        $service = $this->service();
        $this->assertRejected(fn () => $service->createFileVersion(
            $this->propertyId,
            null,
            'property_document',
            'Demasiado grande',
            $this->upload(str_repeat('x', 10 * 1024 * 1024 + 1), 'large.pdf', 'application/pdf'),
            null,
            $this->actorId,
            1_800_000_000,
        ));
        $this->assertRejected(fn () => $service->createFileVersion(
            $this->propertyId,
            null,
            'property_document',
            'Tipo no permitido',
            $this->upload('not a file format', 'note.txt', 'text/plain'),
            null,
            $this->actorId,
            1_800_000_001,
        ));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn());
        self::assertSame([], glob($this->documentDirectory . '/*') ?: []);
    }

    public function testMissingFileFailsClosed(): void
    {
        $service = $this->service();
        $document = $this->createDocument($service);
        $this->removeStoredFile($document);
        self::assertNull($service->privateFile($document['id']));
    }

    public function testTamperedBytesFailHashVerification(): void
    {
        $service = $this->service();
        $document = $this->createDocument($service);
        $path = $this->documentDirectory . DIRECTORY_SEPARATOR . $document['stored_name'];
        $tampered = 'x' . substr($this->pngBytes(), 1);
        self::assertSame(strlen($this->pngBytes()), file_put_contents($path, $tampered));
        self::assertNull($service->privateFile($document['id']));
    }

    public function testPhysicalOversizeFailsBeforeContentRead(): void
    {
        $service = $this->service();
        $document = $this->createDocument($service);
        $path = $this->documentDirectory . DIRECTORY_SEPARATOR . $document['stored_name'];
        $handle = fopen($path, 'ab');
        self::assertIsResource($handle);
        self::assertTrue(ftruncate($handle, 10 * 1024 * 1024 + 1));
        fclose($handle);
        self::assertNull($service->privateFile($document['id']));
    }

    public function testSymlinkedStoredFileFailsClosed(): void
    {
        $service = $this->service();
        $document = $this->createDocument($service);
        $path = $this->documentDirectory . DIRECTORY_SEPARATOR . $document['stored_name'];
        $external = $this->directory . '/outside.png';
        self::assertSame(strlen($this->pngBytes()), file_put_contents($external, $this->pngBytes()));
        $this->uploads[] = $external;
        self::assertTrue(unlink($path));
        if (!@symlink($external, $path)) {
            $this->markTestSkipped('This host does not permit creating a file symlink.');
        }
        self::assertNull($service->privateFile($document['id']));
    }

    private function service(): DocumentService
    {
        return new DocumentService($this->pdo, $this->documentDirectory);
    }

    /** @return array<string,mixed> */
    private function createDocument(DocumentService $service): array
    {
        return $service->createFileVersion(
            $this->propertyId,
            null,
            'property_document',
            'Documento para verificar',
            $this->upload($this->pngBytes(), 'document.png', 'image/png'),
            null,
            $this->actorId,
            1_800_000_000,
        );
    }

    private function removeStoredFile(array $document): void
    {
        $path = $this->documentDirectory . DIRECTORY_SEPARATOR . $document['stored_name'];
        self::assertTrue(unlink($path));
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected invalid or oversized document upload to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
    }

    private function upload(string $bytes, string $filename, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_document_security_upload_');
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
