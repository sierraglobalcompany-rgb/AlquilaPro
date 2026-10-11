<?php

declare(strict_types=1);

namespace AlquilaPro\Auth;

final class Csrf
{
    private const SESSION_KEY = '_alquilapro_csrf';

    public function token(): string
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    public function validate(?string $token): bool
    {
        $stored = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($token)
            && is_string($stored)
            && hash_equals($stored, $token);
    }

    public function rotate(): void
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }
}
