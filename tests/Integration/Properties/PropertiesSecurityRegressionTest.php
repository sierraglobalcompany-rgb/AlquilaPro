<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Properties;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Properties\PropertyService;
use AlquilaPro\Properties\UnitMediaService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class PropertiesSecurityRegressionTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_properties_security_' . bin2hex(random_bytes(6));
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

    public function testPrivatePropertiesHonorSubfolderForGuardAndMutationRedirect(): void
    {
        $this->insertManager();
        $app = $this->app('/miapp');

        $guest = $this->request($app, 'GET', '/miapp/admin/properties');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/miapp/login', $guest->getHeaderLine('Location'));

        $this->login($app, '/miapp', 'manager@example.com', 'ManagerPassword123');
        self::assertSame(200, $this->request($app, 'GET', '/miapp/admin/properties')->getStatusCode());

        $create = $this->request($app, 'POST', '/miapp/admin/properties', [
            'name' => 'Propiedad Subfolder',
            'address' => 'Calle privada 1',
            'city' => 'Medellín',
        ]);
        self::assertSame(303, $create->getStatusCode());
        self::assertMatchesRegularExpression('#^/miapp/admin/properties/[0-9]+$#', $create->getHeaderLine('Location'));
    }

    public function testPrivatePhotoPreviewCannotIdorMediaFromAnotherUnit(): void
    {
        $this->insertManager();
        $properties = new PropertyService($this->pdo);
        $property = $properties->createProperty([
            'name' => 'Propiedad IDOR',
            'address' => 'Calle privada 2',
            'city' => 'Medellín',
        ]);
        $first = $properties->createUnit($property['id'], [
            'type' => 'apartment',
            'code' => 'IDOR-A',
            'status' => 'available',
        ]);
        $second = $properties->createUnit($property['id'], [
            'type' => 'apartment',
            'code' => 'IDOR-B',
            'status' => 'available',
        ]);
        $media = (new UnitMediaService($this->pdo, $this->mediaDirectory))->addPhoto(
            $second['id'],
            $this->upload($this->pngBytes()),
        );

        $app = $this->app();
        $this->login($app, '', 'manager@example.com', 'ManagerPassword123');

        $owned = $this->request($app, 'GET', '/admin/units/' . $second['id'] . '/media/' . $media['id']);
        self::assertSame(200, $owned->getStatusCode());
        self::assertSame('image/png', $owned->getHeaderLine('Content-Type'));

        $foreign = $this->request($app, 'GET', '/admin/units/' . $first['id'] . '/media/' . $media['id']);
        self::assertSame(404, $foreign->getStatusCode());
        self::assertSame('', (string) $foreign->getBody());
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

    private function login(App $app, string $basePath, string $email, string $password): void
    {
        $response = $this->request($app, 'POST', $basePath . '/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(303, $response->getStatusCode());
    }

    private function request(App $app, string $method, string $path, array $body = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );
        if (strtoupper($method) === 'POST') {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }

        return $app->handle($request);
    }

    private function insertManager(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . "VALUES ('admin', 'Manager Seguridad', 'manager@example.com', :password_hash, 1, :permissions_json, 0)",
        );
        $statement->execute([
            'password_hash' => password_hash('ManagerPassword123', PASSWORD_DEFAULT),
            'permissions_json' => json_encode(['properties.view', 'properties.manage'], JSON_THROW_ON_ERROR),
        ]);
    }

    private function upload(string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_security_upload_');
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
