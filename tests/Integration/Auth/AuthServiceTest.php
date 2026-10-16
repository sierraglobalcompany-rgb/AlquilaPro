<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Auth;

use AlquilaPro\Auth\AuthService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
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

        foreach (['unit_media', 'units', 'properties', 'settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }

        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
    }

    public function testAuthenticatesActiveUserAndReturnsSafeNormalizedRecord(): void
    {
        $id = $this->insertUser('Admin@Example.com', 'CorrectHorseBattery1', 'admin', ['users.view']);
        $service = $this->service();
        $now = new DateTimeImmutable('2026-10-11 18:00:00');

        $user = $service->authenticate('  ADMIN@example.com ', 'CorrectHorseBattery1', $now);

        self::assertNotNull($user);
        self::assertSame($id, $user['id']);
        self::assertSame('admin@example.com', $user['email']);
        self::assertSame(['users.view'], $user['permissions']);
        self::assertFalse($user['must_change_password']);
        self::assertArrayNotHasKey('password_hash', $user);

        $row = $this->pdo->query('SELECT failed_login_attempts, locked_until, last_login_at FROM users WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertSame(0, (int) $row['failed_login_attempts']);
        self::assertNull($row['locked_until']);
        self::assertSame('2026-10-11 18:00:00', $row['last_login_at']);
    }

    public function testAuthenticatesWithEmailUsernameOrDocumentNumber(): void
    {
        $id = $this->insertUser(
            'identity@example.com',
            'IdentityPassword123',
            'admin',
            [],
            false,
            'andres.sierra',
            '1037654321',
        );
        $service = $this->service();
        $now = new DateTimeImmutable('2026-10-11 18:00:00');

        foreach (['IDENTITY@example.com', 'ANDRES.SIERRA', '1037654321'] as $identifier) {
            $user = $service->authenticate($identifier, 'IdentityPassword123', $now);
            self::assertNotNull($user, 'Identifier must authenticate: ' . $identifier);
            self::assertSame($id, $user['id']);
        }
    }

    public function testAmbiguousCrossFieldIdentifierFailsClosed(): void
    {
        $this->insertUser(
            'first@example.com',
            'FirstPassword123',
            'admin',
            [],
            false,
            'shared-identity',
            '10000001',
        );
        $this->insertUser(
            'second@example.com',
            'SecondPassword123',
            'admin',
            [],
            false,
            'second-user',
            'shared-identity',
        );

        $service = $this->service();
        $now = new DateTimeImmutable('2026-10-11 18:00:00');

        self::assertNull($service->authenticate('shared-identity', 'FirstPassword123', $now));
        self::assertNull($service->authenticate('shared-identity', 'SecondPassword123', $now));
    }

    public function testFiveFailedAttemptsLockExistingUserForFifteenMinutes(): void
    {
        $id = $this->insertUser('locked@example.com', 'CorrectHorseBattery1');
        $service = $this->service();
        $now = new DateTimeImmutable('2026-10-11 18:00:00');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            self::assertNull($service->authenticate('locked@example.com', 'wrong-password', $now));
        }

        $row = $this->pdo->query('SELECT failed_login_attempts, locked_until FROM users WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertSame(5, (int) $row['failed_login_attempts']);
        self::assertSame('2026-10-11 18:15:00', $row['locked_until']);
        self::assertNull($service->authenticate('locked@example.com', 'CorrectHorseBattery1', $now->modify('+1 minute')));

        $user = $service->authenticate('locked@example.com', 'CorrectHorseBattery1', $now->modify('+16 minutes'));
        self::assertNotNull($user);
        self::assertSame(0, $user['failed_login_attempts']);
    }

    public function testInactiveUserAndUnknownEmailCannotAuthenticate(): void
    {
        $id = $this->insertUser('inactive@example.com', 'CorrectHorseBattery1');
        $this->pdo->exec('UPDATE users SET is_active = 0 WHERE id = ' . $id);
        $service = $this->service();
        $now = new DateTimeImmutable('2026-10-11 18:00:00');

        self::assertNull($service->authenticate('inactive@example.com', 'CorrectHorseBattery1', $now));
        self::assertNull($service->authenticate('missing@example.com', 'CorrectHorseBattery1', $now));
    }

    public function testChangePasswordRequiresCurrentPasswordAndMinimumLength(): void
    {
        $id = $this->insertUser('change@example.com', 'OldPassword123', 'admin', [], true);
        $service = $this->service();

        self::assertFalse($service->changePassword($id, 'wrong-current', 'NewPassword123'));
        self::assertFalse($service->changePassword($id, 'OldPassword123', 'short'));
        self::assertTrue($service->changePassword($id, 'OldPassword123', 'NewPassword123'));

        $row = $this->pdo->query('SELECT password_hash, must_change_password FROM users WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertTrue(password_verify('NewPassword123', $row['password_hash']));
        self::assertSame(0, (int) $row['must_change_password']);
        self::assertNotNull($service->authenticate('change@example.com', 'NewPassword123', new DateTimeImmutable('2026-10-11 18:00:00')));
    }

    public function testResetTokenIsHashedExpiresAndCanOnlyBeUsedOnce(): void
    {
        $id = $this->insertUser('reset@example.com', 'OldPassword123', 'gestor', [], true);
        $service = $this->service();
        $now = new DateTimeImmutable('2026-10-11 18:00:00');

        $token = $service->issueResetToken($id, $now);
        self::assertSame(64, strlen($token));

        $row = $this->pdo->query('SELECT reset_token_hash, reset_expires_at FROM users WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertSame(hash('sha256', $token), $row['reset_token_hash']);
        self::assertNotSame($token, $row['reset_token_hash']);
        self::assertSame('2026-10-11 18:30:00', $row['reset_expires_at']);

        self::assertFalse($service->resetPassword($token . 'x', 'ResetPassword123', $now->modify('+5 minutes')));
        self::assertTrue($service->resetPassword($token, 'ResetPassword123', $now->modify('+5 minutes')));
        self::assertFalse($service->resetPassword($token, 'AnotherPassword123', $now->modify('+6 minutes')));

        $updated = $this->pdo->query('SELECT reset_token_hash, reset_expires_at, must_change_password FROM users WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertNull($updated['reset_token_hash']);
        self::assertNull($updated['reset_expires_at']);
        self::assertSame(0, (int) $updated['must_change_password']);
        self::assertNotNull($service->authenticate('reset@example.com', 'ResetPassword123', $now->modify('+6 minutes')));
    }

    public function testExpiredResetTokenIsRejected(): void
    {
        $id = $this->insertUser('expired@example.com', 'OldPassword123');
        $service = $this->service();
        $now = new DateTimeImmutable('2026-10-11 18:00:00');
        $token = $service->issueResetToken($id, $now);

        self::assertFalse($service->resetPassword($token, 'ResetPassword123', $now->modify('+31 minutes')));
    }

    private function service(): AuthService
    {
        if (!class_exists(AuthService::class)) {
            self::fail('AuthService must exist before Task 2 can pass.');
        }

        return new AuthService($this->pdo);
    }

    private function insertUser(
        string $email,
        string $password,
        string $role = 'admin',
        array $permissions = [],
        bool $mustChangePassword = false,
        ?string $username = null,
        ?string $documentNumber = null,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, username, document_number, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :username, :document_number, :password_hash, 1, :permissions_json, :must_change_password)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => 'Test User',
            'email' => strtolower($email),
            'username' => $username === null ? null : strtolower($username),
            'document_number' => $documentNumber === null ? null : strtolower($documentNumber),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
