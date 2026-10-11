<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Foundation;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\Installer;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InstallerTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $configPath;
    private string $migrationDirectory;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnector::connect($this->validDatabaseConfig());
        foreach (['settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }

        $this->directory = sys_get_temp_dir() . '/alquila_installer_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        $this->configPath = $this->directory . '/database.php';
        $this->migrationDirectory = dirname(__DIR__, 3) . '/database/migrations';
    }

    protected function tearDown(): void
    {
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @unlink($this->configPath);
        @unlink($this->configPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testRejectsInvalidOwnerDataWithoutCreatingLock(): void
    {
        if (!class_exists(Installer::class)) {
            self::fail('Installer must exist before this behavior can pass.');
        }

        $installer = $this->installer();
        $input = $this->validInput();
        $input['owner_email'] = 'not-an-email';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Los datos del propietario no son válidos.');

        try {
            $installer->install($input);
        } finally {
            self::assertFileDoesNotExist($this->lockPath);
        }
    }

    public function testDatabaseFailureLeavesInstallationUnlockedAndRetryable(): void
    {
        if (!class_exists(Installer::class)) {
            self::fail('Installer must exist before this behavior can pass.');
        }

        $installer = $this->installer();
        $input = $this->validInput();
        $input['database']['password'] = 'wrong-password';

        try {
            $installer->install($input);
            self::fail('Expected database connection failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('No fue posible conectar con la base de datos.', $exception->getMessage());
            self::assertFileDoesNotExist($this->lockPath);
            self::assertFileDoesNotExist($this->configPath);
        }
    }

    public function testCompletesInstallationAndCreatesExactlyOneOwner(): void
    {
        if (!class_exists(Installer::class)) {
            self::fail('Installer must exist before this behavior can pass.');
        }

        $installer = $this->installer();
        $result = $installer->install($this->validInput());

        self::assertSame('Alquila Pro', $result['app_name']);
        self::assertSame('https://alquilapro.sierraglobalcompany.com', $result['base_url']);
        self::assertTrue((new InstallationLock($this->lockPath))->isInstalled());
        self::assertFileExists($this->configPath);

        $owner = $this->pdo->query("SELECT role, full_name, email, password_hash, is_active FROM users WHERE role = 'owner'")->fetch();
        self::assertIsArray($owner);
        self::assertSame('owner', $owner['role']);
        self::assertSame('Propietario Principal', $owner['full_name']);
        self::assertSame('owner@example.com', $owner['email']);
        self::assertSame(1, (int) $owner['is_active']);
        self::assertTrue(password_verify('correct-horse-battery', $owner['password_hash']));
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn());

        $settings = $this->pdo->query('SELECT setting_key, setting_value FROM settings ORDER BY setting_key')->fetchAll(PDO::FETCH_KEY_PAIR);
        self::assertSame('Alquila Pro', $settings['app.name']);
        self::assertSame('https://alquilapro.sierraglobalcompany.com', $settings['app.base_url']);

        $database = require $this->configPath;
        self::assertSame('127.0.0.1', $database['host']);
        self::assertSame('alquila_test', $database['database']);
        self::assertSame('root', $database['username']);
        self::assertSame('root', $database['password']);
    }

    public function testSecondInstallationIsRefused(): void
    {
        if (!class_exists(Installer::class)) {
            self::fail('Installer must exist before this behavior can pass.');
        }

        $installer = $this->installer();
        $installer->install($this->validInput());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Alquila Pro ya está instalado.');

        $installer->install($this->validInput());
    }

    private function installer(): Installer
    {
        return new Installer(
            new InstallationLock($this->lockPath),
            $this->migrationDirectory,
            $this->configPath,
        );
    }

    private function validInput(): array
    {
        return [
            'database' => [
                'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
                'port' => getenv('TEST_DB_PORT') ?: '3306',
                'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
                'username' => getenv('TEST_DB_USER') ?: 'root',
                'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
            ],
            'app_name' => 'Alquila Pro',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com',
            'owner_name' => 'Propietario Principal',
            'owner_email' => 'owner@example.com',
            'owner_password' => 'correct-horse-battery',
        ];
    }

    private function validDatabaseConfig(): DatabaseConfig
    {
        return DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]);
    }
}
