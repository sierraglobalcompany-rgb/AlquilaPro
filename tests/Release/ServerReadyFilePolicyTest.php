<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Release;

use PHPUnit\Framework\TestCase;

final class ServerReadyFilePolicyTest extends TestCase
{
    private string $repositoryRoot;

    protected function setUp(): void
    {
        $this->repositoryRoot = dirname(__DIR__, 2);
    }

    public function testReleasePolicyListsRuntimeContentsAndForbiddenPayloads(): void
    {
        $policy = $this->readRequiredFile('docs/release/SERVER_READY_FILE_POLICY.md');

        foreach ([
            'index.php', 'app/', 'bootstrap/', 'assets/', 'resources/', 'database/migrations/',
            'composer.json', 'composer.lock', 'vendor/', '--no-dev', '.htaccess',
            'config/database.php.example', 'storage/', 'RELEASE_NOTES_V1.md', 'VERSION',
            '.sha256', '.manifest.json',
        ] as $requiredPath) {
            self::assertStringContainsString($requiredPath, $policy, "Policy must include {$requiredPath}.");
        }

        foreach ([
            '.git/', '.github/', 'tests/', '.env', 'config/database.php', 'config/database.php.tmp',
            '.phpunit.cache', 'node_modules/', 'Playwright', 'screenshots/', 'credencial de prueba',
            'datos privados', 'logs/', 'dependencias de desarrollo',
        ] as $forbiddenPattern) {
            self::assertStringContainsString($forbiddenPattern, $policy, "Policy must exclude {$forbiddenPattern}.");
        }
    }

    public function testFrozenVersionMatchesTheReleaseNotes(): void
    {
        $version = trim($this->readRequiredFile('docs/release/VERSION'));
        $notes = $this->readRequiredFile('docs/release/RELEASE_NOTES_V1.md');

        self::assertSame('1.0.0', $version);
        self::assertStringContainsString($version, $notes);
        self::assertStringContainsString('ALQUILA_PRO_V1_SERVER_READY.zip', $notes);
    }

    public function testDatabaseExampleUsesOnlyReplaceablePlaceholders(): void
    {
        $examplePath = $this->repositoryRoot . '/config/database.php.example';
        self::assertFileExists($examplePath);
        $example = require $examplePath;

        self::assertIsArray($example);
        foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
            self::assertArrayHasKey($key, $example);
        }
        self::assertSame('YOUR_DATABASE_HOST', $example['host']);
        self::assertSame(3306, $example['port']);
        self::assertSame('YOUR_DATABASE_NAME', $example['database']);
        self::assertSame('YOUR_DATABASE_USER', $example['username']);
        self::assertSame('SET_A_PRIVATE_PASSWORD', $example['password']);
        self::assertNotSame('root', $example['username']);
        self::assertNotSame('root', $example['password']);
        self::assertNotSame('alquila_test', $example['database']);
    }

    private function readRequiredFile(string $relativePath): string
    {
        $path = $this->repositoryRoot . '/' . $relativePath;
        self::assertFileExists($path);

        $contents = file_get_contents($path);
        self::assertNotFalse($contents);

        return $contents;
    }
}
