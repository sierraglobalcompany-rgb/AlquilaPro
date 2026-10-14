<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Properties;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Properties\PropertyService;
use AlquilaPro\Properties\UnitMediaService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\UploadedFile;

final class UnitMediaServiceTest extends TestCase
{
    private PDO $pdo;
    private string $mediaDirectory;
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
        foreach (['unit_media', 'units', 'properties', 'settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->mediaDirectory = sys_get_temp_dir() . '/alquila_unit_media_' . bin2hex(random_bytes(6));
        mkdir($this->mediaDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        foreach (glob($this->mediaDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->mediaDirectory);
    }

    public function testValidPhotoUsesActualMimeGeneratedNameAndCanBeListedPrivately(): void
    {
        $unitId = $this->unitId();
        $service = $this->service();
        $upload = $this->upload($this->pngBytes(), '../../shell.php', 'image/svg+xml');

        $media = $service->addPhoto($unitId, $upload);

        self::assertGreaterThan(0, $media['id']);
        self::assertSame($unitId, $media['unit_id']);
        self::assertSame('photo', $media['type']);
        self::assertSame('image/png', $media['mime_type']);
        self::assertMatchesRegularExpression('/^photo-[a-f0-9]{32}\.png$/', $media['stored_name']);
        self::assertStringNotContainsString('shell', $media['stored_name']);
        self::assertFileExists($this->mediaDirectory . '/' . $media['stored_name']);

        $listed = $service->listForUnit($unitId);
        self::assertCount(1, $listed);
        self::assertSame($media['id'], $listed[0]['id']);

        $private = $service->privatePhoto($unitId, $media['id']);
        self::assertNotNull($private);
        self::assertSame('image/png', $private['mime']);
        self::assertSame($media['stored_name'], $private['stored_name']);
        $expectedPath = realpath($this->mediaDirectory . DIRECTORY_SEPARATOR . $media['stored_name']);
        self::assertIsString($expectedPath);
        self::assertSame($expectedPath, realpath($private['path']));
    }

    public function testPublicPhotoDisappearsWhenPublishedUnitLosesCompleteness(): void
    {
        $properties = new PropertyService($this->pdo);
        $property = $properties->createProperty([
            'name' => 'Propiedad pública',
            'address' => 'Calle 1 # 2-3',
            'city' => 'Medellín',
        ]);
        $unit = $properties->createUnit($property['id'], [
            'type' => 'apartment',
            'code' => 'A-PUBLIC',
            'status' => 'available',
            'monthly_price' => 1800000,
            'description' => 'Apartamento listo para publicar',
            'requirements' => 'Ingresos demostrables',
        ]);

        $service = $this->service();
        $service->addPhoto(
            $unit['id'],
            $this->upload($this->pngBytes(), 'public.png', 'image/png'),
        );

        $properties->updateUnit($unit['id'], [
            'type' => 'apartment',
            'code' => 'A-PUBLIC',
            'status' => 'available',
            'monthly_price' => 1800000,
            'description' => 'Apartamento listo para publicar',
            'requirements' => 'Ingresos demostrables',
            'is_public' => 1,
        ]);
        self::assertSame([1], $service->publicPhotoPositions($unit['public_key']));
        self::assertNotNull($service->publicPhoto($unit['public_key'], 1));

        $properties->updateUnit($unit['id'], [
            'type' => 'apartment',
            'code' => 'A-PUBLIC',
            'status' => 'available',
            'monthly_price' => null,
            'description' => 'Apartamento listo para publicar',
            'requirements' => 'Ingresos demostrables',
        ]);

        self::assertFalse($properties->isUnitPublicable($unit['id']));
        self::assertSame([], $service->publicPhotoPositions($unit['public_key']));
        self::assertNull($service->publicPhoto($unit['public_key'], 1));
    }

    public function testPhotoRejectsNoFileSvgPhpBytesAndOversizedPayloadWithoutRowsOrFiles(): void
    {
        $unitId = $this->unitId();
        $service = $this->service();

        $cases = [
            $this->upload('', 'none.png', 'image/png', UPLOAD_ERR_NO_FILE),
            $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'photo.png', 'image/png'),
            $this->upload('<?php echo "owned"; ?>', 'photo.jpg', 'image/jpeg'),
            $this->upload(str_repeat('A', 10 * 1024 * 1024 + 1), 'photo.png', 'image/png'),
        ];

        foreach ($cases as $upload) {
            try {
                $service->addPhoto($unitId, $upload);
                self::fail('Unsafe photo upload must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM unit_media')->fetchColumn());
        self::assertSame([], glob($this->mediaDirectory . '/*') ?: []);
    }

    public function testPhotoRejectsMissingUnitAndPersistenceFailureLeavesNoOrphanFile(): void
    {
        $service = $this->service();

        try {
            $service->addPhoto(999999, $this->upload($this->pngBytes(), 'missing.png', 'image/png'));
            self::fail('Missing unit must reject photo upload.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
        self::assertSame([], glob($this->mediaDirectory . '/*') ?: []);

        $unitId = $this->unitId();
        $this->pdo->exec('DROP TABLE unit_media');

        try {
            $service->addPhoto($unitId, $this->upload($this->pngBytes(), 'db-failure.png', 'image/png'));
            self::fail('Persistence failure must surface safely.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }

        self::assertSame([], glob($this->mediaDirectory . '/*') ?: []);
    }

    public function testRemovingPhotoDeletesRowAndFileAndCannotCrossUnitBoundary(): void
    {
        $unitId = $this->unitId('A-1');
        $otherUnitId = $this->unitId('A-2');
        $service = $this->service();
        $media = $service->addPhoto($unitId, $this->upload($this->pngBytes(), 'photo.png', 'image/png'));
        $path = $this->mediaDirectory . '/' . $media['stored_name'];
        self::assertFileExists($path);

        try {
            $service->remove($otherUnitId, $media['id']);
            self::fail('Media from another unit must not be removable.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
        self::assertFileExists($path);

        $service->remove($unitId, $media['id']);
        self::assertFileDoesNotExist($path);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM unit_media')->fetchColumn());
        self::assertNull($service->privatePhoto($unitId, $media['id']));
    }

    public function testVideoLinkRequiresHttpsAndStoresOnlyExternalUrl(): void
    {
        $unitId = $this->unitId();
        $service = $this->service();

        $video = $service->addVideoLink($unitId, '  https://video.example.com/watch?v=abc123  ');
        self::assertSame('video_link', $video['type']);
        self::assertSame('https://video.example.com/watch?v=abc123', $video['external_url']);
        self::assertNull($video['stored_name']);
        self::assertNull($video['mime_type']);

        foreach (['', 'http://video.example.com/watch', 'javascript:alert(1)', '//video.example.com/watch'] as $invalid) {
            try {
                $service->addVideoLink($unitId, $invalid);
                self::fail('Only absolute HTTPS video links may be stored.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        self::assertCount(1, $service->listForUnit($unitId));
    }

    public function testCoverIsUniquePhotoAndRejectsVideoOrForeignMedia(): void
    {
        $unitId = $this->unitId('A-10');
        $otherUnitId = $this->unitId('A-20');
        $service = $this->service();
        $first = $service->addPhoto($unitId, $this->upload($this->pngBytes(), 'first.png', 'image/png'));
        $second = $service->addPhoto($unitId, $this->upload($this->pngBytes(), 'second.png', 'image/png'));
        $video = $service->addVideoLink($unitId, 'https://video.example.com/one');
        $foreign = $service->addPhoto($otherUnitId, $this->upload($this->pngBytes(), 'foreign.png', 'image/png'));

        $service->setCover($unitId, $first['id']);
        $listed = $service->listForUnit($unitId);
        self::assertTrue($listed[0]['is_cover']);
        self::assertFalse($listed[1]['is_cover']);

        $service->setCover($unitId, $second['id']);
        $listed = $service->listForUnit($unitId);
        self::assertFalse($listed[0]['is_cover']);
        self::assertTrue($listed[1]['is_cover']);
        self::assertSame(1, array_sum(array_map(static fn (array $media): int => $media['is_cover'] ? 1 : 0, $listed)));

        foreach ([$video['id'], $foreign['id']] as $invalidId) {
            try {
                $service->setCover($unitId, $invalidId);
                self::fail('Cover must be a photo owned by the unit.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testReorderRequiresExactOwnedMediaSetWithoutDuplicates(): void
    {
        $unitId = $this->unitId('A-30');
        $otherUnitId = $this->unitId('A-40');
        $service = $this->service();
        $first = $service->addPhoto($unitId, $this->upload($this->pngBytes(), 'first.png', 'image/png'));
        $second = $service->addPhoto($unitId, $this->upload($this->pngBytes(), 'second.png', 'image/png'));
        $video = $service->addVideoLink($unitId, 'https://video.example.com/two');
        $foreign = $service->addPhoto($otherUnitId, $this->upload($this->pngBytes(), 'foreign.png', 'image/png'));

        $service->reorder($unitId, [$video['id'], $second['id'], $first['id']]);
        $listed = $service->listForUnit($unitId);
        self::assertSame([$video['id'], $second['id'], $first['id']], array_column($listed, 'id'));
        self::assertSame([1, 2, 3], array_column($listed, 'sort_order'));

        foreach ([
            [$first['id'], $first['id'], $second['id']],
            [$first['id'], $second['id']],
            [$first['id'], $second['id'], $foreign['id']],
        ] as $invalidOrder) {
            try {
                $service->reorder($unitId, $invalidOrder);
                self::fail('Reorder must contain each owned media id exactly once.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        self::assertSame(
            [$video['id'], $second['id'], $first['id']],
            array_column($service->listForUnit($unitId), 'id'),
        );
    }

    private function service(): UnitMediaService
    {
        if (!class_exists(UnitMediaService::class)) {
            self::fail('UnitMediaService must exist before Task 3 photo GREEN can pass.');
        }

        return new UnitMediaService($this->pdo, $this->mediaDirectory);
    }

    private function unitId(string $code = 'A-101'): int
    {
        $properties = new PropertyService($this->pdo);
        $property = $properties->createProperty([
            'name' => 'Propiedad Media ' . $code,
            'address' => 'Calle 1 # 2-3',
            'city' => 'Medellín',
        ]);
        $unit = $properties->createUnit($property['id'], [
            'type' => 'apartment',
            'code' => $code,
            'status' => 'available',
        ]);

        return $unit['id'];
    }

    private function upload(string $bytes, string $clientName, string $clientType, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_media_upload_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create upload fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $clientName, $clientType, strlen($bytes), $error, false);
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
