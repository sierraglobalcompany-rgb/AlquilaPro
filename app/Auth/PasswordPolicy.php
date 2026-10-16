<?php

declare(strict_types=1);

namespace AlquilaPro\Auth;

final class PasswordPolicy
{
    public const MIN_LENGTH = 6;

    public static function accepts(string $password): bool
    {
        return strlen($password) >= self::MIN_LENGTH;
    }
}
