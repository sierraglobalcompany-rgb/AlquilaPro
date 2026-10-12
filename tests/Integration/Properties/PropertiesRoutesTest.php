<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Properties;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class PropertiesRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $mediaDirectory;
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

        $this->directory = sys_get_temp_dir() . '/alquila_properties_routes_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->mediaDirectory = $this->directory . '/unit-media';
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-11T21:00:00-05:00',
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

    public function testPrivatePropertiesRequiresAuthentication(): void
    {
        $response = $this->request($this->app(), 'GET', '/admin/properties');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testForcedPasswordChangeBlocksPrivateProperties(): void
    {
        $this->insertUser('owner@example.com', 'OldPassword123', 'owner', [], true);
        $app = $this->app();

        $login = $this->request($app, 'POST', '/login', [
            'email' => 'owner@example.com',
            'password' => 'OldPassword123',
        ]);
        self::assertSame('/account/password', $login->getHeaderLine('Location'));

        $response = $this->request($app, 'GET', '/admin/properties');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/account/password', $response->getHeaderLine('Location'));
    }

    public function testViewPermissionAllowsReadButNotMutation(): void
    {
        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['properties.view']);
        $app = $this->app();
        $this->login($app, 'viewer@example.com', 'ViewerPassword123');

        self::assertSame(200, $this->request($app, 'GET', '/admin/properties')->getStatusCode());
        self::assertSame(403, $this->request($app, 'POST', '/admin/properties', [
            'name' => 'No autorizado',
            'address' => 'Calle 1',
            'city' => 'Medellín',
        ])->getStatusCode());
    }

    public function testUserWithoutViewPermissionIsForbiddenAndOwnerBypasses(): void
    {
        $this->insertUser('admin@example.com', 'AdminPassword123', 'admin', ['users.view']);
        $app = $this->app();
        $this->login($app, 'admin@example.com', 'AdminPassword123');
        self::assertSame(403, $this->request($app, 'GET', '/admin/properties')->getStatusCode());

        $_SESSION = [];
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $ownerApp = $this->app();
        $this->login($ownerApp, 'owner@example.com', 'OwnerPassword123');
        self::assertSame(200, $this->request($ownerApp, 'GET', '/admin/properties')->getStatusCode());
    }

    public function testManagerCanCreateEditAndToggleProperty(): void
    {
        $this->insertUser('manager@example.com', 'ManagerPassword123', 'admin', ['properties.view', 'properties.manage']);
        $app = $this->app();
        $this->login($app, 'manager@example.com', 'ManagerPassword123');

        $create = $this->request($app, 'POST', '/admin/properties', [
            'name' => 'Edificio Norte',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);
        self::assertSame(303, $create->getStatusCode());
        $propertyId = (int) $this->pdo->query("SELECT id FROM properties WHERE name = 'Edificio Norte'")->fetchColumn();
        self::assertGreaterThan(0, $propertyId);
        self::assertSame('/admin/properties/' . $propertyId, $create->getHeaderLine('Location'));

        $update = $this->request($app, 'POST', '/admin/properties/' . $propertyId, [
            'name' => 'Edificio Norte Renovado',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Medellín',
            'sector' => 'La América',
        ]);
        self::assertSame(303, $update->getStatusCode());
        self::assertSame('Edificio Norte Renovado', (string) $this->pdo->query('SELECT name FROM properties WHERE id = ' . $propertyId)->fetchColumn());

        $status = $this->request($app, 'POST', '/admin/properties/' . $propertyId . '/status', ['active' => '0']);
        self::assertSame(303, $status->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT is_active FROM properties WHERE id = ' . $propertyId)->fetchColumn());
    }

    public function testManagerCanCreateEditAndPublishUnitAfterPhoto(): void
    {
        $this->insertUser('manager@example.com', 'ManagerPassword123', 'admin', ['properties.view', 'properties.manage']);
        $propertyId = $this->insertProperty('Edificio Central');
        $app = $this->app();
        $this->login($app, 'manager@example.com', 'ManagerPassword123');

        $create = $this->request($app, 'POST', '/admin/properties/' . $propertyId . '/units', [
            'type' => 'apartment',
            'code' => 'A-201',
            'status' => 'available',
            'monthly_price' => '1800000',
            'description' => 'Apartamento iluminado',
            'requirements' => 'Ingresos demostrables',
            'bedrooms' => '3',
            'bathrooms' => '2',
        ]);
        self::assertSame(303, $create->getStatusCode());
        $unitId = (int) $this->pdo->query("SELECT id FROM units WHERE code = 'A-201'")->fetchColumn();
        self::assertGreaterThan(0, $unitId);
        self::assertSame('/admin/units/' . $unitId, $create->getHeaderLine('Location'));

        $photo = $this->request(
            $app,
            'POST',
            '/admin/units/' . $unitId . '/media/photo',
            [],
            ['photo' => $this->upload($this->pngBytes(), 'apartment.png', 'image/png')],
        );
        self::assertSame(303, $photo->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM unit_media WHERE unit_id = $unitId AND type = 'photo'")->fetchColumn());

        $update = $this->request($app, 'POST', '/admin/units/' . $unitId, [
            'type' => 'apartment',
            'code' => 'A-201',
            'status' => 'available',
            'monthly_price' => '1850000',
            'description' => 'Apartamento iluminado y renovado',
            'requirements' => 'Ingresos demostrables',
            'bedrooms' => '3',
            'bathrooms' => '2',
            'is_public' => '1',
        ]);
        self::assertSame(303, $update->getStatusCode());
        $row = $this->pdo->query('SELECT monthly_price, is_public FROM units WHERE id = ' . $unitId)->fetch(PDO::FETCH_ASSOC);
        self::assertSame('1850000.00', $row['monthly_price']);
        self::assertSame(1, (int) $row['is_public']);
    }

    public function testManagerCanManageVideoCoverOrderAndDeleteMedia(): void
    {
        $this->insertUser('manager@example.com', 'ManagerPassword123', 'admin', ['properties.view', 'properties.manage']);
        $propertyId = $this->insertProperty('Edificio Multimedia');
        $unitId = $this->insertUnit($propertyId, 'A-301');
        $app = $this->app();
        $this->login($app, 'manager@example.com', 'ManagerPassword123');

        $photo = $this->request(
            $app,
            'POST',
            '/admin/units/' . $unitId . '/media/photo',
            [],
            ['photo' => $this->upload($this->pngBytes(), 'photo.png', 'image/png')],
        );
        self::assertSame(303, $photo->getStatusCode());
        $photoId = (int) $this->pdo->query("SELECT id FROM unit_media WHERE unit_id = $unitId AND type = 'photo'")->fetchColumn();

        $video = $this->request($app, 'POST', '/admin/units/' . $unitId . '/media/video', [
            'url' => 'https://video.example.com/tour',
        ]);
        self::assertSame(303, $video->getStatusCode());
        $videoId = (int) $this->pdo->query("SELECT id FROM unit_media WHERE unit_id = $unitId AND type = 'video_link'")->fetchColumn();

        self::assertSame(303, $this->request($app, 'POST', '/admin/units/' . $unitId . '/media/' . $photoId . '/cover')->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT is_cover FROM unit_media WHERE id = ' . $photoId)->fetchColumn());

        self::assertSame(303, $this->request($app, 'POST', '/admin/units/' . $unitId . '/media/order', [
            'media_ids' => [$videoId, $photoId],
        ])->getStatusCode());
        $ordered = $this->pdo->query('SELECT id FROM unit_media WHERE unit_id = ' . $unitId . ' ORDER BY sort_order ASC')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([$videoId, $photoId], array_map('intval', $ordered));

        self::assertSame(303, $this->request($app, 'POST', '/admin/units/' . $unitId . '/media/' . $videoId . '/delete')->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM unit_media WHERE id = ' . $videoId)->fetchColumn());
    }

    public function testInvalidPropertyMutationReturns422WithoutRow(): void
    {
        $this->insertUser('manager@example.com', 'ManagerPassword123', 'admin', ['properties.view', 'properties.manage']);
        $app = $this->app();
        $this->login($app, 'manager@example.com', 'ManagerPassword123');

        $response = $this->request($app, 'POST', '/admin/properties', [
            'name' => '',
            'address' => 'Calle 1',
            'city' => 'Medellín',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('nombre de la propiedad', (string) $response->getBody());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn());
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

    private function login(App $app, string $email, string $password): void
    {
        $response = $this->request($app, 'POST', '/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(303, $response->getStatusCode());
    }

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        array $uploads = [],
    ): ResponseInterface {
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
        if ($uploads !== []) {
            $request = $request->withUploadedFiles($uploads);
        }

        return $app->handle($request);
    }

    private function insertUser(
        string $email,
        string $password,
        string $role,
        array $permissions = [],
        bool $mustChangePassword = false,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, :must_change_password)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => ucfirst($role) . ' Test',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertProperty(string $name): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector) VALUES (:name, :address, :city, :sector)',
        );
        $statement->execute([
            'name' => $name,
            'address' => 'Calle 1 # 2-3',
            'city' => 'Medellín',
            'sector' => 'Calasanz',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertUnit(int $propertyId, string $code): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO units (property_id, public_key, type, code, status) VALUES (:property_id, :public_key, 'apartment', :code, 'available')",
        );
        $statement->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'code' => $code,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upload(string $bytes, string $clientName, string $clientType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_property_upload_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create upload fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $clientName, $clientType, strlen($bytes), UPLOAD_ERR_OK, false);
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
