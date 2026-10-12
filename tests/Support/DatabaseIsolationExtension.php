<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Support;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use PDO;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Throwable;

final class DatabaseIsolationExtension implements Extension
{
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        $facade->registerSubscriber(new DatabaseIsolationFinishedSubscriber());
    }
}

final class DatabaseIsolationFinishedSubscriber implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        try {
            $pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
                'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
                'port' => getenv('TEST_DB_PORT') ?: '3306',
                'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
                'username' => getenv('TEST_DB_USER') ?: 'root',
                'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
            ]));

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $safeTable = str_replace('`', '``', (string) $table);
                $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safeTable));
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable) {
            // Individual tests own database-connectivity assertions; cleanup must not mask their result.
        }
    }
}
