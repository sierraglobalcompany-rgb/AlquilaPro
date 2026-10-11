<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Foundation;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseConnectorTest extends TestCase
{
    public function testConnectsToMariaDbWithValidCredentials(): void
    {
        if (!class_exists(DatabaseConfig::class) || !class_exists(DatabaseConnector::class)) {
            self::fail('DatabaseConfig and DatabaseConnector must exist before this behavior can pass.');
        }

        $config = DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]);

        $pdo = DatabaseConnector::connect($config);

        self::assertSame('1', (string) $pdo->query('SELECT 1')->fetchColumn());
    }

    public function testConnectionFailureIsSanitized(): void
    {
        if (!class_exists(DatabaseConfig::class) || !class_exists(DatabaseConnector::class)) {
            self::fail('DatabaseConfig and DatabaseConnector must exist before this behavior can pass.');
        }

        $config = DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => 'definitely-wrong-secret',
        ]);

        try {
            DatabaseConnector::connect($config);
            self::fail('Expected connection failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('No fue posible conectar con la base de datos.', $exception->getMessage());
            self::assertStringNotContainsString('definitely-wrong-secret', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}
