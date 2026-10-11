<?php

declare(strict_types=1);

namespace AlquilaPro\Auth;

final class SessionGuard
{
    private const SESSION_KEY = '_alquilapro_auth';
    private const TIMEOUT_SECONDS = 1800;

    public function login(array $user, int $now): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'user_id' => (int) $user['id'],
            'must_change_password' => (bool) ($user['must_change_password'] ?? false),
            'last_activity' => $now,
        ];
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    public function userId(int $now): ?int
    {
        $auth = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($auth) || !isset($auth['user_id'], $auth['last_activity'])) {
            return null;
        }

        if (($now - (int) $auth['last_activity']) > self::TIMEOUT_SECONDS) {
            $this->logout();

            return null;
        }

        $_SESSION[self::SESSION_KEY]['last_activity'] = $now;

        return (int) $auth['user_id'];
    }

    public function mustChangePassword(): bool
    {
        return (bool) ($_SESSION[self::SESSION_KEY]['must_change_password'] ?? false);
    }
}
