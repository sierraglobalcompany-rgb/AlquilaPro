<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\UI;

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

final class ShellRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;

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

        $this->directory = sys_get_temp_dir() . '/alquila_shell_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-11T20:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        foreach (glob($this->directory . '/branding/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory . '/branding');
        @rmdir($this->directory);
    }

    public function testInstalledRootWithoutSessionRendersPublicLanding(): void
    {
        $response = $this->request($this->app(), 'GET', '/');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-public-shell', $body);
        self::assertStringContainsString('href="/catalog"', $body);
        self::assertStringContainsString('href="/login"', $body);
        self::assertStringNotContainsString('data-f3-app-shell', $body);
    }

    public function testAuthenticatedRootRendersSemanticMobileShell(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner', [], false, 'Owner Principal');
        $app = $this->app();
        $this->login($app, 'owner@example.com', 'OwnerPassword123');

        $response = $this->request($app, 'GET', '/');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<header', $body);
        self::assertStringContainsString('<main', $body);
        self::assertStringContainsString('<nav', $body);
        self::assertStringContainsString('Inicio', $body);
        self::assertStringContainsString('Apps', $body);
        self::assertStringContainsString('Cuenta', $body);
        self::assertStringContainsString('<dialog', $body);
        self::assertStringContainsString('aria-current="page"', $body);
        self::assertStringContainsString('Cartera', $body);
        self::assertStringContainsString('href="/admin/maintenance"', $body);
        self::assertStringContainsString('>Mantenimiento</strong>', $body);
        self::assertStringContainsString('Propiedades', $body);
    }

    public function testInsuranceAppCardRequiresItsDedicatedPermission(): void
    {
        $this->insertUser('insurance-view@example.com', 'StaffPassword123', 'gestor', ['insurance.view']);
        $app = $this->app();
        $this->login($app, 'insurance-view@example.com', 'StaffPassword123');

        $visible = (string) $this->request($app, 'GET', '/')->getBody();
        self::assertStringContainsString('href="/admin/insurance"', $visible);
        self::assertStringContainsString('>Seguros</strong>', $visible);

        $_SESSION = [];
        $this->insertUser('insurance-hidden@example.com', 'StaffPassword123', 'gestor', []);
        $hiddenApp = $this->app();
        $this->login($hiddenApp, 'insurance-hidden@example.com', 'StaffPassword123');

        $hidden = (string) $this->request($hiddenApp, 'GET', '/')->getBody();
        self::assertStringNotContainsString('/admin/insurance', $hidden);
        self::assertStringNotContainsString('Seguros', $hidden);
    }

    public function testAccountRequiresLoginAndRendersCurrentUser(): void
    {
        $userId = $this->insertUser('admin@example.com', 'AdminPassword123', 'admin', ['users.view'], false, 'Natalia Admin');
        self::assertGreaterThan(0, $userId);
        $app = $this->app();

        $blocked = $this->request($app, 'GET', '/account');
        self::assertSame(302, $blocked->getStatusCode());
        self::assertSame('/login', $blocked->getHeaderLine('Location'));

        $this->login($app, 'admin@example.com', 'AdminPassword123');
        $response = $this->request($app, 'GET', '/account');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Natalia Admin', $body);
        self::assertStringContainsString('admin@example.com', $body);
        self::assertStringContainsString('admin', $body);
        self::assertStringContainsString('/account/password', $body);
        self::assertStringContainsString('name="csrf_token"', $body);
        self::assertStringContainsString('aria-current="page"', $body);
    }

    public function testMustChangePasswordCannotReachShell(): void
    {
        $this->insertUser(
            'mustchange@example.com',
            'OldPassword123',
            'admin',
            ['users.view'],
            true,
            'Debe Cambiar',
        );
        $app = $this->app();

        $login = $this->request($app, 'POST', '/login', [
            'email' => 'mustchange@example.com',
            'password' => 'OldPassword123',
        ]);
        self::assertSame(303, $login->getStatusCode());
        self::assertSame('/account/password', $login->getHeaderLine('Location'));

        $root = $this->request($app, 'GET', '/');
        self::assertSame(302, $root->getStatusCode());
        self::assertSame('/account/password', $root->getHeaderLine('Location'));

        $account = $this->request($app, 'GET', '/account');
        self::assertSame(302, $account->getStatusCode());
        self::assertSame('/account/password', $account->getHeaderLine('Location'));
    }

    public function testTenantRootKeepsPasswordGateThenRedirectsToPrivatePortal(): void
    {
        $this->insertUser(
            'tenant-forced@example.com',
            'TenantPassword123',
            'tenant',
            [],
            true,
            'Tenant Forced',
        );
        $forcedApp = $this->app('/sub');
        $this->login($forcedApp, 'tenant-forced@example.com', 'TenantPassword123', '/sub');

        $forcedRoot = $this->request($forcedApp, 'GET', '/sub/');
        self::assertSame(302, $forcedRoot->getStatusCode());
        self::assertSame('/sub/account/password', $forcedRoot->getHeaderLine('Location'));

        $_SESSION = [];
        $this->insertUser(
            'tenant-ready@example.com',
            'TenantPassword123',
            'tenant',
            [],
            false,
            'Tenant Ready',
        );
        $readyApp = $this->app('/sub');
        $this->login($readyApp, 'tenant-ready@example.com', 'TenantPassword123', '/sub');

        $readyRoot = $this->request($readyApp, 'GET', '/sub/');
        self::assertSame(302, $readyRoot->getStatusCode());
        self::assertSame('/sub/tenant', $readyRoot->getHeaderLine('Location'));
    }

    public function testTenantShellHasOnlyTenantHomeAndAccountEvenWithPollutedPermissions(): void
    {
        $this->insertUser(
            'tenant-polluted@example.com',
            'TenantPassword123',
            'tenant',
            ['users.view', 'users.manage', 'properties.view', 'candidates.view', 'contracts.view', 'contracts.manage', 'finance.view', 'finance.manage', 'insurance.view', 'insurance.manage', 'maintenance.view', 'maintenance.manage'],
            false,
            'Tenant Polluted',
        );
        $app = $this->app('/sub');
        $this->login($app, 'tenant-polluted@example.com', 'TenantPassword123', '/sub');

        $response = $this->request($app, 'GET', '/sub/tenant');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-f3-app-shell', $body);
        self::assertStringContainsString('href="/sub/tenant"', $body);
        self::assertStringContainsString('href="/sub/account"', $body);
        self::assertStringContainsString('Inicio', $body);
        self::assertStringContainsString('Cuenta', $body);
        self::assertStringNotContainsString('data-apps-open', $body);
        self::assertStringNotContainsString('apps-dialog', $body);
        self::assertStringNotContainsString('>Apps<', $body);
        self::assertStringNotContainsString('/sub/admin/', $body);
        self::assertStringNotContainsString('/sub/admin/finance', $body);
        self::assertStringNotContainsString('/sub/admin/insurance', $body);
        self::assertStringNotContainsString('/sub/admin/maintenance', $body);
        self::assertStringNotContainsString('Seguros', $body);
        self::assertStringNotContainsString('Cartera', $body);
        self::assertStringNotContainsString('No tienes aplicaciones disponibles', $body);

        self::assertSame(403, $this->request($app, 'GET', '/sub/admin/users')->getStatusCode());
    }

    public function testShellUsesConfiguredBrandingAndSafeFallbacks(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $this->upsertSetting('app.name', 'Mi Arriendo');
        $this->upsertSetting('brand.primary_color', '#335577');
        $app = $this->app();
        $this->login($app, 'owner@example.com', 'OwnerPassword123');

        $configured = (string) $this->request($app, 'GET', '/')->getBody();
        self::assertStringContainsString('Mi Arriendo', $configured);
        self::assertStringContainsString('#335577', $configured);

        $this->upsertSetting('app.name', '   ');
        $this->upsertSetting('brand.primary_color', 'not-a-color');
        $fallback = (string) $this->request($app, 'GET', '/')->getBody();
        self::assertStringContainsString('Alquila Pro', $fallback);
        self::assertStringContainsString('#1E6A5A', $fallback);
    }

    public function testShellRespectsSubfolderBasePath(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $app = $this->app('/miapp');
        $this->login($app, 'owner@example.com', 'OwnerPassword123', '/miapp');

        $response = $this->request($app, 'GET', '/miapp/');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('href="/miapp/"', $body);
        self::assertStringContainsString('href="/miapp/account"', $body);
        self::assertStringContainsString('href="/miapp/assets/app.css"', $body);
        self::assertStringContainsString('src="/miapp/assets/app.js"', $body);
        self::assertStringNotContainsString('/miapp/miapp/', $body);
    }

    public function testAppsLauncherShowsOnlyRealAuthorizedApps(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $app = $this->app();
        $this->login($app, 'owner@example.com', 'OwnerPassword123');

        $ownerBody = (string) $this->request($app, 'GET', '/')->getBody();
        self::assertStringContainsString('/admin/users', $ownerBody);
        self::assertStringContainsString('Usuarios', $ownerBody);
        self::assertStringContainsString('/admin/appearance', $ownerBody);
        self::assertStringContainsString('Apariencia', $ownerBody);

        $_SESSION = [];
        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['users.view', 'finance.view'], false, 'Viewer');
        $viewerApp = $this->app();
        $this->login($viewerApp, 'viewer@example.com', 'ViewerPassword123');
        $viewerBody = (string) $this->request($viewerApp, 'GET', '/')->getBody();

        self::assertStringContainsString('/admin/users', $viewerBody);
        self::assertStringContainsString('Usuarios', $viewerBody);
        self::assertStringContainsString('/admin/finance', $viewerBody);
        self::assertStringContainsString('Cartera', $viewerBody);
        self::assertStringNotContainsString('/admin/appearance', $viewerBody);
        self::assertStringNotContainsString('Apariencia', $viewerBody);
    }

    public function testRestrictedUserGetsEmptyAppsStateWithoutFutureModules(): void
    {
        $this->insertUser('restricted@example.com', 'RestrictedPass123', 'gestor', [], false, 'Gestor Limitado');
        $app = $this->app();
        $this->login($app, 'restricted@example.com', 'RestrictedPass123');

        $response = $this->request($app, 'GET', '/');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('No tienes aplicaciones disponibles', $body);
        self::assertStringNotContainsString('/admin/users', $body);
        self::assertStringNotContainsString('Cartera', $body);
        self::assertStringNotContainsString('Solicitudes', $body);
        self::assertStringNotContainsString('Propiedades', $body);
    }

    public function testUserAdministrationUsesAppShellCardsAndManagePermissionShowsCreateForm(): void
    {
        $this->insertUser(
            'manager@example.com',
            'ManagerPassword123',
            'admin',
            ['users.view', 'users.manage'],
            false,
            'Manager Admin',
        );
        $this->insertUser('listed@example.com', 'ListedPassword123', 'gestor', [], false, 'Usuario Listado');
        $app = $this->app();
        $this->login($app, 'manager@example.com', 'ManagerPassword123');

        $response = $this->request($app, 'GET', '/admin/users');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-f3-app-shell', $body);
        self::assertStringContainsString('aria-current="page"', $body);
        self::assertStringContainsString('class="user-card"', $body);
        self::assertStringContainsString('Usuario Listado', $body);
        self::assertStringContainsString('Agregar usuario', $body);
        self::assertStringContainsString('name="temporary_password"', $body);
        self::assertStringNotContainsString('<table', $body);
    }

    public function testViewOnlyUserCanSeeCardsButNotCreateForm(): void
    {
        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['users.view'], false, 'Viewer Admin');
        $this->insertUser('listed@example.com', 'ListedPassword123', 'gestor', [], false, 'Usuario Listado');
        $app = $this->app();
        $this->login($app, 'viewer@example.com', 'ViewerPassword123');

        $response = $this->request($app, 'GET', '/admin/users');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-f3-app-shell', $body);
        self::assertStringContainsString('class="user-card"', $body);
        self::assertStringContainsString('Usuario Listado', $body);
        self::assertStringNotContainsString('Agregar usuario', $body);
        self::assertStringNotContainsString('name="temporary_password"', $body);
        self::assertStringNotContainsString('<table', $body);
    }

    private function app(string $basePath = ''): App
    {
        $brandingDirectory = $this->directory . '/branding';
        if (!is_dir($brandingDirectory)) {
            mkdir($brandingDirectory, 0700, true);
        }

        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com' . $basePath,
            'base_path' => $basePath,
            'logo_directory' => $brandingDirectory,
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

    private function insertUser(
        string $email,
        string $password,
        string $role,
        array $permissions = [],
        bool $mustChangePassword = false,
        string $name = 'Test User',
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, :must_change_password)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => $name,
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertSetting(string $key, string $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        );
        $statement->execute(['setting_key' => $key, 'setting_value' => $value]);
    }
}
