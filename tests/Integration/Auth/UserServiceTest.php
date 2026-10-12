<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Auth;

use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\UserService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UserServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
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
    }

    public function testOwnerBypassesPermissionsAndSanitizerKeepsOnlyKnownUniqueValues(): void
    {
        $permissions = $this->permissions();

        self::assertTrue($permissions::allows(['role' => 'owner', 'permissions' => []], 'users.manage'));
        self::assertTrue($permissions::allows(['role' => 'admin', 'permissions' => ['users.view']], 'users.view'));
        self::assertFalse($permissions::allows(['role' => 'admin', 'permissions' => ['users.view']], 'users.manage'));
        self::assertTrue($permissions::allows(['role' => 'gestor', 'permissions' => ['users.permissions']], 'users.permissions'));
        self::assertTrue($permissions::allows(['role' => 'gestor', 'permissions' => ['properties.view']], 'properties.view'));
        self::assertFalse($permissions::allows(['role' => 'gestor', 'permissions' => []], 'users.view'));

        self::assertSame(
            ['users.view', 'users.manage', 'users.permissions', 'properties.view', 'properties.manage'],
            $permissions::sanitize([
                'users.view',
                'users.manage',
                'users.permissions',
                'properties.view',
                'properties.manage',
                'unknown.permission',
                'properties.view',
                123,
            ]),
        );
    }

    public function testCreatesManagedUserWithTemporaryPasswordAndNormalizedUniqueEmail(): void
    {
        $service = $this->service();

        $id = $service->create(
            '  Natalia Admin  ',
            '  Natalia@Example.COM ',
            'admin',
            'TemporaryPass123',
            ['users.view', 'unknown.permission', 'users.manage'],
        );

        $row = $this->pdo->query('SELECT * FROM users WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('Natalia Admin', $row['full_name']);
        self::assertSame('natalia@example.com', $row['email']);
        self::assertSame('admin', $row['role']);
        self::assertSame(1, (int) $row['is_active']);
        self::assertSame(1, (int) $row['must_change_password']);
        self::assertTrue(password_verify('TemporaryPass123', $row['password_hash']));
        self::assertSame(['users.view', 'users.manage'], json_decode((string) $row['permissions_json'], true));

        $users = $service->listUsers();
        self::assertCount(1, $users);
        self::assertSame($id, $users[0]['id']);
        self::assertSame(['users.view', 'users.manage'], $users[0]['permissions']);
        self::assertArrayNotHasKey('password_hash', $users[0]);

        $this->expectException(RuntimeException::class);
        $service->create('Other Admin', 'NATALIA@example.com', 'admin', 'AnotherPass123', []);
    }

    public function testRejectsUnsupportedRoleAndShortTemporaryPassword(): void
    {
        $service = $this->service();

        try {
            $service->create('Owner Two', 'owner2@example.com', 'owner', 'TemporaryPass123', []);
            self::fail('Only admin or gestor may be created from UserService.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $service->create('Short Password', 'short@example.com', 'gestor', 'short', []);
    }

    public function testCanDeactivateManagedUserButCannotDeactivateLastActiveOwner(): void
    {
        $ownerId = $this->insertOwner();
        $service = $this->service();
        $adminId = $service->create('Admin', 'admin@example.com', 'admin', 'TemporaryPass123', ['users.view']);

        $service->setActive($adminId, false);
        self::assertSame(0, (int) $this->pdo->query('SELECT is_active FROM users WHERE id = ' . $adminId)->fetchColumn());

        $this->expectException(RuntimeException::class);
        $service->setActive($ownerId, false);
    }

    public function testUpdatesPermissionsUsingOnlyKnownValues(): void
    {
        $service = $this->service();
        $userId = $service->create('Gestor', 'gestor@example.com', 'gestor', 'TemporaryPass123', []);

        $service->setPermissions($userId, ['users.permissions', 'unknown.permission', 'users.view', 'users.permissions']);

        $encoded = $this->pdo->query('SELECT permissions_json FROM users WHERE id = ' . $userId)->fetchColumn();
        self::assertSame(['users.permissions', 'users.view'], json_decode((string) $encoded, true));

        $listed = $service->listUsers();
        self::assertSame(['users.permissions', 'users.view'], $listed[0]['permissions']);
    }

    private function service(): UserService
    {
        if (!class_exists(UserService::class)) {
            self::fail('UserService must exist before Task 4 can pass.');
        }

        return new UserService($this->pdo);
    }

    private function permissions(): Permissions
    {
        if (!class_exists(Permissions::class)) {
            self::fail('Permissions must exist before Task 4 can pass.');
        }

        return new Permissions();
    }

    private function insertOwner(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (\'owner\', \'Owner\', \'owner@example.com\', :password_hash, 1)',
        );
        $statement->execute(['password_hash' => password_hash('OwnerPassword123', PASSWORD_DEFAULT)]);

        return (int) $this->pdo->lastInsertId();
    }
}
