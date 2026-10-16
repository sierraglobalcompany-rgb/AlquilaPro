<?php

declare(strict_types=1);

namespace AlquilaPro\Auth;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final readonly class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;
    private const RESET_MINUTES = 30;
    private const MIN_PASSWORD_LENGTH = 12;

    public function __construct(private PDO $pdo)
    {
    }

    public function authenticate(string $identifier, string $password, DateTimeImmutable $now): ?array
    {
        $user = $this->findAuthenticationRecord(trim($identifier));

        if ($user === null || (int) $user['is_active'] !== 1) {
            return null;
        }

        if ($this->isLocked($user, $now)) {
            return null;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $this->recordFailure((int) $user['id'], (int) $user['failed_login_attempts'], $now);

            return null;
        }

        $statement = $this->pdo->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = :last_login_at '
            . 'WHERE id = :id',
        );
        $statement->execute([
            'id' => (int) $user['id'],
            'last_login_at' => $this->formatDate($now),
        ]);

        return $this->findUser((int) $user['id']);
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT password_hash FROM users WHERE id = :id AND is_active = 1 LIMIT 1',
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row) || !password_verify($currentPassword, (string) $row['password_hash'])) {
            return false;
        }

        $update = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, must_change_password = 0, '
            . 'failed_login_attempts = 0, locked_until = NULL, reset_token_hash = NULL, reset_expires_at = NULL, '
            . 'updated_at = CURRENT_TIMESTAMP WHERE id = :id',
        );
        $update->execute([
            'id' => $userId,
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);

        return $update->rowCount() === 1;
    }

    public function issueResetToken(int $userId, DateTimeImmutable $now): string
    {
        $statement = $this->pdo->prepare('SELECT id FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
        $statement->execute(['id' => $userId]);

        if ($statement->fetchColumn() === false) {
            throw new RuntimeException('El usuario no está disponible para restablecer la contraseña.');
        }

        $token = bin2hex(random_bytes(32));
        $update = $this->pdo->prepare(
            'UPDATE users SET reset_token_hash = :token_hash, reset_expires_at = :expires_at WHERE id = :id',
        );
        $update->execute([
            'id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $this->formatDate($now->modify(sprintf('+%d minutes', self::RESET_MINUTES))),
        ]);

        return $token;
    }

    public function resetPassword(string $token, string $newPassword, DateTimeImmutable $now): bool
    {
        if ($token === '' || strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT id FROM users WHERE reset_token_hash = :token_hash AND reset_expires_at >= :now '
            . 'AND is_active = 1 LIMIT 1',
        );
        $statement->execute([
            'token_hash' => hash('sha256', $token),
            'now' => $this->formatDate($now),
        ]);
        $userId = $statement->fetchColumn();

        if ($userId === false) {
            return false;
        }

        $update = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, must_change_password = 0, '
            . 'failed_login_attempts = 0, locked_until = NULL, reset_token_hash = NULL, reset_expires_at = NULL, '
            . 'updated_at = CURRENT_TIMESTAMP WHERE id = :id AND reset_token_hash = :token_hash',
        );
        $update->execute([
            'id' => (int) $userId,
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'token_hash' => hash('sha256', $token),
        ]);

        return $update->rowCount() === 1;
    }

    public function findUser(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, role, full_name, email, is_active, permissions_json, must_change_password, '
            . 'failed_login_attempts, locked_until, last_login_at FROM users WHERE id = :id LIMIT 1',
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'role' => (string) $row['role'],
            'full_name' => (string) $row['full_name'],
            'email' => $this->normalizeEmail((string) $row['email']),
            'is_active' => (int) $row['is_active'] === 1,
            'permissions' => $this->decodePermissions($row['permissions_json']),
            'must_change_password' => (int) $row['must_change_password'] === 1,
            'failed_login_attempts' => (int) $row['failed_login_attempts'],
            'locked_until' => $row['locked_until'],
            'last_login_at' => $row['last_login_at'],
        ];
    }

    private function findAuthenticationRecord(string $identifier): ?array
    {
        if ($identifier === '') {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, password_hash, is_active, failed_login_attempts, locked_until '
            . 'FROM users WHERE email = :email OR username = :username OR document_number = :document_number LIMIT 2',
        );
        $statement->execute([
            'email' => $this->normalizeEmail($identifier),
            'username' => $identifier,
            'document_number' => $identifier,
        ]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return count($rows) === 1 && is_array($rows[0]) ? $rows[0] : null;
    }

    private function recordFailure(int $userId, int $currentAttempts, DateTimeImmutable $now): void
    {
        $attempts = $currentAttempts + 1;
        $lockedUntil = $attempts >= self::MAX_FAILED_ATTEMPTS
            ? $this->formatDate($now->modify(sprintf('+%d minutes', self::LOCK_MINUTES)))
            : null;

        $statement = $this->pdo->prepare(
            'UPDATE users SET failed_login_attempts = :attempts, locked_until = :locked_until WHERE id = :id',
        );
        $statement->execute([
            'id' => $userId,
            'attempts' => $attempts,
            'locked_until' => $lockedUntil,
        ]);
    }

    private function isLocked(array $user, DateTimeImmutable $now): bool
    {
        if ($user['locked_until'] === null) {
            return false;
        }

        return new DateTimeImmutable((string) $user['locked_until']) > $now;
    }

    private function decodePermissions(mixed $encoded): array
    {
        if (!is_string($encoded) || $encoded === '') {
            return [];
        }

        $permissions = json_decode($encoded, true);

        if (!is_array($permissions)) {
            return [];
        }

        return array_values(array_unique(array_filter($permissions, 'is_string')));
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function formatDate(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
