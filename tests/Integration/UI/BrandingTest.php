<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\UI;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\UI\Branding;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\UploadedFile;

final class BrandingTest extends TestCase
{
    private PDO $pdo;
    private string $logoDirectory;
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]));

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['unit_media', 'units', 'properties', 'settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->logoDirectory = sys_get_temp_dir() . '/alquila_branding_' . bin2hex(random_bytes(6));
        mkdir($this->logoDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
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

    public function testUpdatePersistsNameAndNormalizedPrimaryColor(): void
    {
        $branding = new Branding($this->pdo, $this->logoDirectory);

        $result = $branding->update('  Mi Arriendo  ', '#a1b2c3');

        self::assertSame('Mi Arriendo', $result['app_name']);
        self::assertSame('#A1B2C3', $result['primary_color']);
        self::assertNull($result['logo_path']);
        self::assertSame($result, $branding->current());
    }

    public function testUpdateRejectsInvalidNameOrColorWithoutMutatingSettings(): void
    {
        $branding = new Branding($this->pdo, $this->logoDirectory);
        $this->upsert('app.name', 'Marca Anterior');
        $this->upsert('brand.primary_color', '#224466');
        $expected = $branding->current();

        try {
            $branding->update('', '#112233');
            self::fail('Empty app name must be rejected.');
        } catch (RuntimeException) {
        }
        self::assertSame($expected, $branding->current());

        try {
            $branding->update('Nueva Marca', 'red');
            self::fail('Invalid primary color must be rejected.');
        } catch (RuntimeException) {
        }
        self::assertSame($expected, $branding->current());
    }

    public function testValidPngLogoUsesGeneratedNameAndCanBeResolved(): void
    {
        $branding = new Branding($this->pdo, $this->logoDirectory);
        $upload = $this->upload($this->pngBytes(), '../../evil.php', 'image/svg+xml');

        $current = $branding->update('Alquila Pro', '#1e6a5a', $upload);

        self::assertMatchesRegularExpression('/^logo-[a-f0-9]{32}\.png$/', (string) $current['logo_path']);
        self::assertStringNotContainsString('evil', (string) $current['logo_path']);
        $resolved = $branding->logoFile();
        self::assertNotNull($resolved);
        self::assertSame('image/png', $resolved['mime']);
        self::assertFileExists($resolved['path']);
        self::assertSame(basename($resolved['path']), $current['logo_path']);
    }

    public function testSvgMisleadingMimeAndOversizedLogoAreRejected(): void
    {
        $branding = new Branding($this->pdo, $this->logoDirectory);
        $branding->update('Marca Segura', '#225544', $this->upload($this->pngBytes(), 'safe.png', 'image/png'));
        $expected = $branding->current();
        $expectedFile = $branding->logoFile();
        self::assertNotNull($expectedFile);

        try {
            $branding->update(
                'Marca Atacada',
                '#112233',
                $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'logo.png', 'image/png'),
            );
            self::fail('SVG bytes disguised as PNG must be rejected.');
        } catch (RuntimeException) {
        }
        self::assertSame($expected, $branding->current());
        self::assertFileExists($expectedFile['path']);

        try {
            $branding->update(
                'Marca Grande',
                '#112233',
                $this->upload(str_repeat('A', 2_097_153), 'logo.png', 'image/png'),
            );
            self::fail('Logo over 2 MiB must be rejected.');
        } catch (RuntimeException) {
        }
        self::assertSame($expected, $branding->current());
        self::assertFileExists($expectedFile['path']);
    }

    public function testReplacingLogoRemovesPreviousGeneratedFile(): void
    {
        $branding = new Branding($this->pdo, $this->logoDirectory);
        $first = $branding->update('Alquila Pro', '#1E6A5A', $this->upload($this->pngBytes(), 'first.png', 'image/png'));
        $firstFile = $branding->logoFile();
        self::assertNotNull($firstFile);
        self::assertFileExists($firstFile['path']);

        $second = $branding->update('Alquila Pro', '#1E6A5A', $this->upload($this->pngBytes(), 'second.png', 'image/png'));
        $secondFile = $branding->logoFile();

        self::assertNotSame($first['logo_path'], $second['logo_path']);
        self::assertFileDoesNotExist($firstFile['path']);
        self::assertNotNull($secondFile);
        self::assertFileExists($secondFile['path']);
    }

    private function upsert(string $key, string $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        );
        $statement->execute(['setting_key' => $key, 'setting_value' => $value]);
    }

    private function upload(string $bytes, string $clientName, string $clientType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_upload_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create upload fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $clientName, $clientType, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }
}
