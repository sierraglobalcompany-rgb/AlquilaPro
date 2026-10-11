<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\Foundation;

use AlquilaPro\Foundation\DatabaseConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    public function testBuildsValidDatabaseConfiguration(): void
    {
        if (!class_exists(DatabaseConfig::class)) {
            self::fail('DatabaseConfig must exist before this behavior can pass.');
        }

        $config = DatabaseConfig::fromArray([
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'alquila_test',
            'username' => 'root',
            'password' => 'secret',
        ]);

        self::assertSame('127.0.0.1', $config->host);
        self::assertSame(3306, $config->port);
        self::assertSame('alquila_test', $config->database);
        self::assertSame('root', $config->username);
        self::assertSame('secret', $config->password);
        self::assertSame('mysql:host=127.0.0.1;port=3306;dbname=alquila_test;charset=utf8mb4', $config->dsn());
    }

    public function testRejectsPortOutsideValidRange(): void
    {
        if (!class_exists(DatabaseConfig::class)) {
            self::fail('DatabaseConfig must exist before this behavior can pass.');
        }

        $this->expectException(InvalidArgumentException::class);

        DatabaseConfig::fromArray([
            'host' => 'localhost',
            'port' => '70000',
            'database' => 'alquila_test',
            'username' => 'root',
            'password' => '',
        ]);
    }

    public function testRejectsUnsafeDatabaseName(): void
    {
        if (!class_exists(DatabaseConfig::class)) {
            self::fail('DatabaseConfig must exist before this behavior can pass.');
        }

        $this->expectException(InvalidArgumentException::class);

        DatabaseConfig::fromArray([
            'host' => 'localhost',
            'port' => '3306',
            'database' => 'alquila-test;drop',
            'username' => 'root',
            'password' => '',
        ]);
    }

    public function testRequiresHostAndUsername(): void
    {
        if (!class_exists(DatabaseConfig::class)) {
            self::fail('DatabaseConfig must exist before this behavior can pass.');
        }

        $this->expectException(InvalidArgumentException::class);

        DatabaseConfig::fromArray([
            'host' => '',
            'port' => '3306',
            'database' => 'alquila_test',
            'username' => '',
            'password' => '',
        ]);
    }
}
