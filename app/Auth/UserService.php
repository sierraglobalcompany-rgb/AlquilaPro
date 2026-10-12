<?php

declare(strict_types=1);

namespace AlquilaPro\Auth;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

final readonly class UserService
{
    private const MIN_PASSWORD_LENGTH = 12;
    private const ALLOWED_ROLES = ['admin', 'gestor'];

    public function __construct(private PDO $pdo)
    {
    }

    public function listUsers(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, role, full_name, email, is_active, permissions_json, must_change_password, created_at, updated_at '
            . 'FROM users ORDER BY id ASC',
        );

        $users = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $this->normalizeUser($row);
        }

        return $users;
    }

    public function create(
        string $name,
        string $email,
        string $role,
        string $temporaryPassword,
        array $permissions,
    ): int {
        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo no es válido.');
        }

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            throw new InvalidArgumentException('El rol no es válido.');
        }

        if (strlen($temporaryPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('La contraseña temporal debe tener al menos 12 caracteres.');
        }

        $sanitizedPermissions = Permissions::sanitize($permissions);

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO users '
                . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
                . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, 1)',
            );
            $statement->execute([
                'role' => $role,
                'full_name' => $name,
                'email' => $email,
                'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                'permissions_json' => $this->encodePermissions($sanitizedPermissions),
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new RuntimeException('El correo ya está registrado.');
            }

            throw new RuntimeException('No fue posible crear el usuario.');
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function setActive(int $userId, bool $active): void
    {
        $statement = $this->pdo->prepare('SELECT role, is_active FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($user)) {
            throw new RuntimeException('El usuario no existe.');
        }

        if ((int) $user['is_active'] === ($active ? 1 : 0)) {
            return;
        }

        if (!$active && $user['role'] === 'owner') {
            $activeOwners = (int) $this->pdo
                ->query("SELECT COUNT(*) FROM users WHERE role = 'owner' AND is_active = 1")
                ->fetchColumn();

            if ($activeOwners <= 1) {
                throw new RuntimeException('No se puede desactivar el último propietario activo.');
            }
        }

        $update = $this->pdo->prepare(
            'UPDATE users SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
        );
        $update->execute([
            'id' => $userId,
            'is_active' => $active ? 1 : 0,
        ]);
    }

    public function setPermissions(int $userId, array $permissions): void
    {
        $exists = $this->pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $exists->execute(['id' => $userId]);

        if ($exists->fetchColumn() === false) {
            throw new RuntimeException('El usuario no existe.');
        }

        $update = $this->pdo->prepare(
            'UPDATE users SET permissions_json = :permissions_json, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
        );
        $update->execute([
            'id' => $userId,
            'permissions_json' => $this->encodePermissions(Permissions::sanitize($permissions)),
        ]);
    }

    private function normalizeUser(array $row): array
    {
        $permissions = [];
        if (is_string($row['permissions_json'] ?? null) && $row['permissions_json'] !== '') {
            $decoded = json_decode($row['permissions_json'], true);
            if (is_array($decoded)) {
                $permissions = Permissions::sanitize($decoded);
            }
        }

        return [
            'id' => (int) $row['id'],
            'role' => (string) $row['role'],
            'full_name' => (string) $row['full_name'],
            'email' => strtolower((string) $row['email']),
            'is_active' => (int) $row['is_active'] === 1,
            'permissions' => $permissions,
            'must_change_password' => (int) $row['must_change_password'] === 1,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    private function encodePermissions(array $permissions): ?string
    {
        return $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR);
    }
}
