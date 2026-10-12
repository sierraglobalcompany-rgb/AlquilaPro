<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Auth;

use AlquilaPro\Auth\AuthService;
use AlquilaPro\Auth\Csrf;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AuthRoutesTest extends TestCase
{
    private PDO $pdo;
    private App $app;
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

        foreach (['settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_auth_routes_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-11T18:00:00-05:00',
        ]);

        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        $this->app = $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com',
            'base_path' => '',
            'pdo' => $this->pdo,
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testLoginLogoutAndProtectedUsersRoute(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner', [], false, 'Owner Principal');

        $blocked = $this->request('GET', '/admin/users');
        self::assertSame(302, $blocked->getStatusCode());
        self::assertSame('/login', $blocked->getHeaderLine('Location'));

        $loginPage = $this->request('GET', '/login');
        self::assertSame(200, $loginPage->getStatusCode());
        self::assertStringContainsString('Iniciar sesión', (string) $loginPage->getBody());

        $login = $this->request('POST', '/login', [
            'email' => 'OWNER@example.com',
            'password' => 'OwnerPassword123',
        ]);
        self::assertSame(303, $login->getStatusCode());
        self::assertSame('/', $login->getHeaderLine('Location'));

        $users = $this->request('GET', '/admin/users');
        self::assertSame(200, $users->getStatusCode());
        self::assertStringContainsString('owner@example.com', (string) $users->getBody());

        $logout = $this->request('POST', '/logout');
        self::assertSame(303, $logout->getStatusCode());
        self::assertSame('/login', $logout->getHeaderLine('Location'));

        self::assertSame(302, $this->request('GET', '/admin/users')->getStatusCode());
    }

    public function testSuccessfulLoginRotatesCsrfToken(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $csrf = new Csrf();
        $before = $csrf->token();

        $login = $this->request('POST', '/login', [
            'email' => 'owner@example.com',
            'password' => 'OwnerPassword123',
        ]);

        self::assertSame(303, $login->getStatusCode());
        self::assertNotSame($before, $csrf->token());
    }

    public function testOwnerCanCreateUpdatePermissionsAndDeactivateManagedUser(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $this->login('owner@example.com', 'OwnerPassword123');

        $create = $this->request('POST', '/admin/users', [
            'full_name' => 'Natalia Admin',
            'email' => 'natalia@example.com',
            'role' => 'admin',
            'temporary_password' => 'TemporaryPass123',
            'permissions' => ['users.view'],
        ]);
        self::assertSame(303, $create->getStatusCode());
        self::assertSame('/admin/users', $create->getHeaderLine('Location'));

        $userId = (int) $this->pdo->query("SELECT id FROM users WHERE email = 'natalia@example.com'")->fetchColumn();
        self::assertGreaterThan(0, $userId);

        $permissions = $this->request('POST', '/admin/users/' . $userId . '/permissions', [
            'permissions' => ['users.view', 'users.permissions'],
        ]);
        self::assertSame(303, $permissions->getStatusCode());

        $encoded = $this->pdo->query('SELECT permissions_json FROM users WHERE id = ' . $userId)->fetchColumn();
        self::assertSame(['users.view', 'users.permissions'], json_decode((string) $encoded, true));

        $status = $this->request('POST', '/admin/users/' . $userId . '/status', ['active' => '0']);
        self::assertSame(303, $status->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT is_active FROM users WHERE id = ' . $userId)->fetchColumn());
    }

    public function testUserWithoutManagePermissionReceivesForbidden(): void
    {
        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['users.view']);
        $this->login('viewer@example.com', 'ViewerPassword123');

        $response = $this->request('POST', '/admin/users', [
            'full_name' => 'Should Not Exist',
            'email' => 'blocked@example.com',
            'role' => 'gestor',
            'temporary_password' => 'TemporaryPass123',
        ]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE email = 'blocked@example.com'")->fetchColumn());
    }

    public function testManagePermissionCannotEscalatePermissionsDuringUserCreation(): void
    {
        $this->insertUser(
            'manager@example.com',
            'ManagerPassword123',
            'admin',
            ['users.view', 'users.manage'],
        );
        $this->login('manager@example.com', 'ManagerPassword123');

        $allowed = $this->request('POST', '/admin/users', [
            'full_name' => 'Basic Gestor',
            'email' => 'basic@example.com',
            'role' => 'gestor',
            'temporary_password' => 'TemporaryPass123',
        ]);
        self::assertSame(303, $allowed->getStatusCode());

        $blocked = $this->request('POST', '/admin/users', [
            'full_name' => 'Escalated Gestor',
            'email' => 'escalated@example.com',
            'role' => 'gestor',
            'temporary_password' => 'TemporaryPass123',
            'permissions' => ['users.permissions'],
        ]);

        self::assertSame(403, $blocked->getStatusCode());
        self::assertSame(
            0,
            (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE email = 'escalated@example.com'")->fetchColumn(),
        );
    }

    public function testMustChangePasswordForcesAccountPasswordUntilChanged(): void
    {
        $this->insertUser(
            'mustchange@example.com',
            'OldPassword123',
            'admin',
            ['users.view'],
            true,
        );

        $login = $this->request('POST', '/login', [
            'email' => 'mustchange@example.com',
            'password' => 'OldPassword123',
        ]);
        self::assertSame(303, $login->getStatusCode());
        self::assertSame('/account/password', $login->getHeaderLine('Location'));

        $blocked = $this->request('GET', '/admin/users');
        self::assertSame(302, $blocked->getStatusCode());
        self::assertSame('/account/password', $blocked->getHeaderLine('Location'));

        self::assertSame(200, $this->request('GET', '/account/password')->getStatusCode());

        $changed = $this->request('POST', '/account/password', [
            'current_password' => 'OldPassword123',
            'new_password' => 'NewPassword123',
        ]);
        self::assertSame(303, $changed->getStatusCode());
        self::assertSame('/', $changed->getHeaderLine('Location'));

        self::assertSame(200, $this->request('GET', '/admin/users')->getStatusCode());
    }

    public function testOwnerCanIssueManualResetLinkAndTokenCanBeConsumedOnce(): void
    {
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $targetId = $this->insertUser('target@example.com', 'TargetPassword123', 'gestor');
        $this->login('owner@example.com', 'OwnerPassword123');

        $issued = $this->request('POST', '/admin/users/' . $targetId . '/reset-link');
        self::assertSame(200, $issued->getStatusCode());
        $body = (string) $issued->getBody();
        self::assertMatchesRegularExpression('/\/reset-password\?token=([a-f0-9]{64})/', $body);
        preg_match('/token=([a-f0-9]{64})/', $body, $matches);
        $token = $matches[1] ?? '';
        self::assertSame(64, strlen($token));

        $resetPage = $this->request('GET', '/reset-password?token=' . $token);
        self::assertSame(200, $resetPage->getStatusCode());
        self::assertStringContainsString($token, (string) $resetPage->getBody());

        $reset = $this->request('POST', '/reset-password', [
            'token' => $token,
            'new_password' => 'ReplacementPass123',
        ]);
        self::assertSame(303, $reset->getStatusCode());
        self::assertSame('/login', $reset->getHeaderLine('Location'));

        $auth = new AuthService($this->pdo);
        self::assertNotNull($auth->authenticate('target@example.com', 'ReplacementPass123', new \DateTimeImmutable()));
        self::assertFalse($auth->resetPassword($token, 'AnotherPass123', new \DateTimeImmutable()));
    }

    private function login(string $email, string $password): void
    {
        $response = $this->request('POST', '/login', ['email' => $email, 'password' => $password]);
        self::assertSame(303, $response->getStatusCode());
    }

    private function request(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
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

        return $this->app->handle($request);
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
}
