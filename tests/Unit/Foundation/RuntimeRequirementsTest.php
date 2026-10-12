<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\Foundation;

use AlquilaPro\Foundation\RuntimeRequirements;
use PHPUnit\Framework\TestCase;

final class RuntimeRequirementsTest extends TestCase
{
    public function testRejectsPhpBelowSupportedRange(): void
    {
        if (!class_exists(RuntimeRequirements::class)) {
            self::fail('RuntimeRequirements must exist before this behavior can pass.');
        }

        $result = RuntimeRequirements::evaluate('8.2.29', ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo'], true);

        self::assertFalse($result['ok']);
        self::assertFalse($result['checks']['php']['ok']);
    }

    public function testRejectsPhpAboveValidatedRange(): void
    {
        if (!class_exists(RuntimeRequirements::class)) {
            self::fail('RuntimeRequirements must exist before this behavior can pass.');
        }

        $result = RuntimeRequirements::evaluate('8.6.0', ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo'], true);

        self::assertFalse($result['ok']);
        self::assertFalse($result['checks']['php']['ok']);
    }

    public function testRejectsMissingPdoMysql(): void
    {
        if (!class_exists(RuntimeRequirements::class)) {
            self::fail('RuntimeRequirements must exist before this behavior can pass.');
        }

        $result = RuntimeRequirements::evaluate('8.4.0', ['pdo', 'mbstring', 'fileinfo'], true);

        self::assertFalse($result['ok']);
        self::assertFalse($result['checks']['pdo_mysql']['ok']);
    }

    public function testRejectsMissingFileinfo(): void
    {
        if (!class_exists(RuntimeRequirements::class)) {
            self::fail('RuntimeRequirements must exist before this behavior can pass.');
        }

        $result = RuntimeRequirements::evaluate('8.4.0', ['pdo', 'pdo_mysql', 'mbstring'], true);

        self::assertFalse($result['ok']);
        self::assertFalse($result['checks']['fileinfo']['ok']);
    }

    public function testRejectsUnwritableStorage(): void
    {
        if (!class_exists(RuntimeRequirements::class)) {
            self::fail('RuntimeRequirements must exist before this behavior can pass.');
        }

        $result = RuntimeRequirements::evaluate('8.4.0', ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo'], false);

        self::assertFalse($result['ok']);
        self::assertFalse($result['checks']['storage']['ok']);
    }

    public function testAcceptsSupportedCompleteEnvironment(): void
    {
        if (!class_exists(RuntimeRequirements::class)) {
            self::fail('RuntimeRequirements must exist before this behavior can pass.');
        }

        $result = RuntimeRequirements::evaluate('8.5.1', ['PDO', 'pdo_mysql', 'mbstring', 'fileinfo'], true);

        self::assertTrue($result['ok']);
        self::assertTrue($result['checks']['php']['ok']);
        self::assertTrue($result['checks']['pdo']['ok']);
        self::assertTrue($result['checks']['pdo_mysql']['ok']);
        self::assertTrue($result['checks']['mbstring']['ok']);
        self::assertTrue($result['checks']['fileinfo']['ok']);
        self::assertTrue($result['checks']['storage']['ok']);
    }
}
