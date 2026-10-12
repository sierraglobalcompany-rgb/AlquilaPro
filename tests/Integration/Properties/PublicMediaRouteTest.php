<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Properties;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Properties\PropertyService;
use AlquilaPro\Properties\UnitMediaService;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class PublicMediaRouteTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $mediaDirectory;
    private string $lockPath;
    private array $temporaryFiles = [];

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
        foreach (['unit_media', 'units', 'properties', 'settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_public_media_' . bin2hex(random_bytes(6));
        $this->mediaDirectory = $this->directory . '/unit-media';
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-12T00:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        foreach (glob($this->mediaDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->mediaDirectory);
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testPublicPhotoUsesPublicKeyAndPositionWithoutLeakingMediaId(): void
    {
        [$publicKey, $unitId, $mediaId, $bytes] = $this->publishedUnit();
        $app = $this->app();

        $detail = $this->request($app, '/catalog/' . $publicKey);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('/catalog/' . $publicKey . '/photo/1', $html);
        self::assertStringNotContainsString('/catalog/media/' . $mediaId, $html);

        $response = $this->request($app, '/catalog/' . $publicKey . '/photo/1');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame($bytes, (string) $response->getBody());

        self::assertSame(404, $this->request($app, '/catalog/' . $publicKey . '/photo/0')->getStatusCode());
        self::assertSame(404, $this->request($app, '/catalog/' . $publicKey . '/photo/99')->getStatusCode());
        self::assertGreaterThan(0, $unitId);
    }

    public function testPublicPhotoDisappearsImmediatelyWhenUnitStopsBeingPublicable(): void
    {
        [$publicKey, $unitId] = $this->publishedUnit();
        $app = $this->app();
        $path = '/catalog/' . $publicKey . '/photo/1';

        self::assertSame(200, $this->request($app, $path)->getStatusCode());

        $this->pdo->prepare('UPDATE units SET status = :status WHERE id = :id')
            ->execute(['status' => 'occupied', 'id' => $unitId]);
        self::assertSame(404, $this->request($app, $path)->getStatusCode());

        $this->pdo->prepare('UPDATE units SET status = :status WHERE id = :id')
            ->execute(['status' => 'available', 'id' => $unitId]);
        $this->pdo->prepare(
            'UPDATE properties p INNER JOIN units u ON u.property_id = p.id SET p.is_active = 0 WHERE u.id = :id',
        )->execute(['id' => $unitId]);
        self::assertSame(404, $this->request($app, $path)->getStatusCode());
    }

    public function testPublicPhotoRespectsSubfolderBasePath(): void
    {
        [$publicKey] = $this->publishedUnit();
        $app = $this->app('/miapp');

        $detail = $this->request($app, '/miapp/catalog/' . $publicKey);
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString(
            '/miapp/catalog/' . $publicKey . '/photo/1',
            (string) $detail->getBody(),
        );

        self::assertSame(
            200,
            $this->request($app, '/miapp/catalog/' . $publicKey . '/photo/1')->getStatusCode(),
        );
    }

    private function publishedUnit(): array
    {
        $properties = new PropertyService($this->pdo);
        $property = $properties->createProperty([
            'name' => 'Propiedad pública media',
            'address' => 'Dirección privada 123',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);
        $unit = $properties->createUnit($property['id'], [
            'type' => 'apartment',
            'code' => 'MEDIA-' . bin2hex(random_bytes(3)),
            'status' => 'available',
            'monthly_price' => 1800000,
            'description' => 'Descripción pública completa',
            'requirements' => 'Requisitos públicos completos',
        ]);

        $bytes = $this->pngBytes();
        $media = (new UnitMediaService($this->pdo, $this->mediaDirectory))->addPhoto(
            $unit['id'],
            $this->upload($bytes),
        );

        $unit = $properties->updateUnit($unit['id'], [
            'type' => 'apartment',
            'code' => $unit['code'],
            'status' => 'available',
            'monthly_price' => 1800000,
            'description' => 'Descripción pública completa',
            'requirements' => 'Requisitos públicos completos',
            'is_public' => 1,
        ]);

        return [$unit['public_key'], $unit['id'], $media['id'], $bytes];
    }

    private function app(string $basePath = ''): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com' . $basePath,
            'base_path' => $basePath,
            'logo_directory' => $this->directory . '/branding',
            'unit_media_directory' => $this->mediaDirectory,
            'pdo' => $this->pdo,
        ]);
    }

    private function request(App $app, string $path): \Psr\Http\Message\ResponseInterface
    {
        return $app->handle(
            (new ServerRequestFactory())->createServerRequest(
                'GET',
                'https://alquilapro.sierraglobalcompany.com' . $path,
            ),
        );
    }

    private function upload(string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_public_media_upload_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create upload fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, 'photo.png', 'image/png', strlen($bytes), UPLOAD_ERR_OK, false);
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
