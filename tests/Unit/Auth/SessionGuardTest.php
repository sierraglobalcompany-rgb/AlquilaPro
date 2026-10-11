<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\Auth;

use AlquilaPro\Auth\SessionGuard;
use PHPUnit\Framework\TestCase;

final class SessionGuardTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testLoginStoresUserAndSessionExpiresAfterThirtyMinutesOfInactivity(): void
    {
        $guard = $this->guard();
        $guard->login([
            'id' => 7,
            'role' => 'admin',
            'must_change_password' => true,
        ], 1_000);

        self::assertSame(7, $guard->userId(1_000));
        self::assertTrue($guard->mustChangePassword());
        self::assertNull($guard->userId(2_801));
        self::assertFalse($guard->mustChangePassword());
    }

    public function testActivityRefreshesSlidingTimeout(): void
    {
        $guard = $this->guard();
        $guard->login(['id' => 8, 'role' => 'gestor', 'must_change_password' => false], 1_000);

        self::assertSame(8, $guard->userId(2_700));
        self::assertSame(8, $guard->userId(4_400));
        self::assertNull($guard->userId(6_201));
    }

    public function testLogoutClearsAuthenticationState(): void
    {
        $guard = $this->guard();
        $guard->login(['id' => 9, 'role' => 'owner', 'must_change_password' => false], 1_000);
        $guard->logout();

        self::assertNull($guard->userId(1_001));
        self::assertFalse($guard->mustChangePassword());
    }

    private function guard(): SessionGuard
    {
        if (!class_exists(SessionGuard::class)) {
            self::fail('SessionGuard must exist before Task 3 can pass.');
        }

        return new SessionGuard();
    }
}
