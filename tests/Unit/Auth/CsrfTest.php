<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\Auth;

use AlquilaPro\Auth\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTokenIsStableUntilRotatedAndValidationUsesSessionValue(): void
    {
        $csrf = $this->csrf();

        $first = $csrf->token();
        $second = $csrf->token();

        self::assertSame(64, strlen($first));
        self::assertSame($first, $second);
        self::assertTrue($csrf->validate($first));
        self::assertFalse($csrf->validate(null));
        self::assertFalse($csrf->validate($first . 'x'));

        $csrf->rotate();
        $rotated = $csrf->token();

        self::assertNotSame($first, $rotated);
        self::assertFalse($csrf->validate($first));
        self::assertTrue($csrf->validate($rotated));
    }

    private function csrf(): Csrf
    {
        if (!class_exists(Csrf::class)) {
            self::fail('Csrf must exist before Task 3 can pass.');
        }

        return new Csrf();
    }
}
