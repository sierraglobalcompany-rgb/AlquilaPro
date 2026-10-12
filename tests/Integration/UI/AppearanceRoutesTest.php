<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\UI;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\UI\Branding;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class AppearanceRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $logoDirectory;
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

        $this->directory = sys_get_temp_dir() . '/alquila_appearance_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->logoDirectory = $this->directory . '/branding';
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
        foreach (glob($this->logoDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->logoDirectory);
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testAppearanceRequiresAuthentication(): void
    {
        $response = $this->request($this->app(), 'GET', '/admin/appearance');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testAppearanceIsOwnerOnly(): void
    {
        $this->insertUser('admin@example.com', 'AdminPassword123', 'admin', ['users.view', 'users.manage', 'users.permissions']);
        $app = $this->app();
        $this->login($app, 'admin@example.com', 'AdminPassword123');

        self::assertSame(403, $this->request($app, 'GET', '/admin/appearance')->getStatusCode());
        self::assertSame(403, $this->request($app, 'POST', '/admin/appearance', [
            'app_name' => 'No Autorizado',
            'primary_color' => '#112233',
        ])->getStatusCode());
    }

    public function testOwnerCanUpdateAppNameAndPrimaryColor(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $app = $this->app();
        $this->login($app, 'owner@example.com', 'OwnerPassword123');

        $response = $this->request($app, 'POST', '/admin/appearance', [
            'app_name' => 'Mi Arriendo',
            'primary_color' => '#335577',
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/appearance?saved=1', $response->getHeaderLine('Location'));
        self::assertSame([
            'app_name' => 'Mi Arriendo',
            'primary_color' => '#335577',
            'logo_path' => null,
        ], (new Branding($this->pdo, $this->logoDirectory))->current());

        $page = $this->request($app, 'GET', '/admin/appearance?saved=1');
        $body = (string) $page->getBody();
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Cambios guardados', $body);
        self::assertStringContainsString('value="Mi Arriendo"', $body);
        self::assertStringContainsString('value="#335577"', $body);
    }

    public function testInvalidColorReturns422WithoutMutation(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $branding = new Branding($this->pdo, $this->logoDirectory);
        $branding->update('Marca Segura', '#224466');
        $expected = $branding->current();
        $app = $this->app();
        $this->login($app, 'owner@example.com', 'OwnerPassword123');

        $response = $this->request($app, 'POST', '/admin/appearance', [
            'app_name' => 'Marca Rota',
            'primary_color' => 'red',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('color primario no es válido', (string) $response->getBody());
        self::assertSame($expected, $branding->current());
    }

    public function testValidPngUploadPersistsAndLogoRouteServesActualMime(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $app = $this->app();
        $this->login($app, 'owner@example.com', 'OwnerPassword123');
        $bytes = $this->pngBytes();

        $response = $this->request(
            $app,
            'POST',
            '/admin/appearance',
            ['app_name' => 'Alquila Pro', 'primary_color' => '#1E6A5A'],
            ['logo' => $this->upload($bytes, '../../evil.php', 'image/svg+xml')],
        );

        self::assertSame(303, $response->getStatusCode());
        $branding = new Branding($this->pdo, $this->logoDirectory);
        self::assertMatchesRegularExpression('/^logo-[a-f0-9]{32}\.png$/', (string) $branding->current()['logo_path']);

        $logo = $this->request($app, 'GET', '/brand/logo');
        self::assertSame(200, $logo->getStatusCode());
        self::assertSame('image/png', $logo->getHeaderLine('Content-Type'));
        self::assertSame($bytes, (string) $logo->getBody());
    }

    public function testSvgAndOversizedLogoAreRejected(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $branding = new Branding($this->pdo, $this->logoDirectory);
        $branding->update('Marca Segura', '#225544');
        $expected = $branding->current();
        $app = $this->app();
        $this->login($app, 'owner@example.com', 'OwnerPassword123');

        $svg = $this->request(
            $app,
            'POST',
            '/admin/appearance',
            ['app_name' => 'Marca Atacada', 'primary_color' => '#112233'],
            ['logo' => $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'logo.png', 'image/png')],
        );
        self::assertSame(422, $svg->getStatusCode());
        self::assertSame($expected, $branding->current());

        $oversized = $this->request(
            $app,
            'POST',
            '/admin/appearance',
            ['app_name' => 'Marca Grande', 'primary_color' => '#112233'],
            ['logo' => $this->upload(str_repeat('A', 2_097_153), 'logo.png', 'image/png')],
        );
        self::assertSame(422, $oversized->getStatusCode());
        self::assertSame($expected, $branding->current());
    }

    public function testAppearanceRespectsForcedPasswordChange(): void
    {
        $this->insertUser('owner@example.com', 'OldPassword123', 'owner', [], true);
        $app = $this->app();

        $login = $this->request($app, 'POST', '/login', [
            'email' => 'owner@example.com',
            'password' => 'OldPassword123',
        ]);
        self::assertSame('/account/password', $login->getHeaderLine('Location'));

        $response = $this->request($app, 'GET', '/admin/appearance');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/account/password', $response->getHeaderLine('Location'));
    }

    public function testAppearanceRespectsSubfolderPaths(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $app = $this->app('/miapp');
        $this->login($app, 'owner@example.com', 'OwnerPassword123', '/miapp');

        $response = $this->request($app, 'GET', '/miapp/admin/appearance');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('action="/miapp/admin/appearance"', $body);
        self::assertStringContainsString('href="/miapp/admin/appearance"', $body);
        self::assertStringNotContainsString('/miapp/miapp/', $body);
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
            'logo_directory' => $this->logoDirectory,
            'pdo' => $this->pdo,
        ]);
    }

    private function login(App $app, string $email, string $password, string $basePath = ''): void
    {
        $response = $this->request($app, 'POST', $basePath . '/login', [
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

    private function upload(string $bytes, string $clientName, string $clientType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_appearance_upload_');
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
