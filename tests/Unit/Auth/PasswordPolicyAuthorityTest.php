<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\Auth;

use AlquilaPro\Auth\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyAuthorityTest extends TestCase
{
    public function testCanonicalMinimumIsSixCharacters(): void
    {
        self::assertTrue(class_exists(PasswordPolicy::class), 'PasswordPolicy must be the single password-length authority.');
        self::assertFalse(PasswordPolicy::accepts('12345'));
        self::assertTrue(PasswordPolicy::accepts('123456'));
    }

    public function testAllPasswordOwnersReuseCanonicalPolicyWithoutLocalMinimums(): void
    {
        $root = dirname(__DIR__, 3);
        $owners = [
            $root . '/app/Auth/AuthService.php',
            $root . '/app/Auth/UserService.php',
            $root . '/app/Foundation/Installer.php',
        ];

        foreach ($owners as $path) {
            self::assertFileExists($path);
            $source = (string) file_get_contents($path);
            self::assertStringContainsString('PasswordPolicy::accepts', $source, basename($path) . ' must reuse PasswordPolicy.');
            self::assertStringNotContainsString('MIN_PASSWORD_LENGTH', $source, basename($path) . ' must not own a duplicate minimum.');
        }
    }
}
