<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\UI;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\UI\Branding;
use PDO;
use PHPUnit\Framework\TestCase;

final class BrandingTest extends TestCase
{
    private PDO $pdo;
    private string $logoDirectory;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]));

        foreach (['settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->logoDirectory = sys_get_temp_dir() . '/alquila_branding_' . bin2hex(random_bytes(6));
        mkdir($this->logoDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logoDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->logoDirectory);
    }

    public function testCurrentUsesSafeDefaultsWhenSettingsAreMissingOrMalformed(): void
    {
        $branding = new Branding($this->pdo, $this->logoDirectory);

        self::assertSame([
            'app_name' => 'Alquila Pro',
            'primary_color' => '#1E6A5A',
            'logo_path' => null,
        ], $branding->current());

        $this->upsert('app.name', '   ');
        $this->upsert('brand.primary_color', 'javascript:red');
        $this->upsert('brand.logo_path', '../../config/database.php');

        self::assertSame([
            'app_name' => 'Alquila Pro',
            'primary_color' => '#1E6A5A',
            'logo_path' => null,
        ], $branding->current());
    }

    private function upsert(string $key, string $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        );
        $statement->execute(['setting_key' => $key, 'setting_value' => $value]);
    }
}
