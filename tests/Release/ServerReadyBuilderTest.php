<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Release;

use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ServerReadyBuilderTest extends TestCase
{
    private string $temporaryRoot;
    private string $builderPath;

    protected function setUp(): void
    {
        $this->temporaryRoot = sys_get_temp_dir() . '/alquilapro_server_ready_builder_' . bin2hex(random_bytes(8));
        mkdir($this->temporaryRoot, 0700, true);
        $this->builderPath = dirname(__DIR__, 2) . '/bin/build-server-ready.php';
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->temporaryRoot);
    }

    public function testBuilderRejectsDirtySourceWithoutProducingArtifacts(): void
    {
        $source = $this->createCommittedSource();
        file_put_contents($source . '/untracked-change.txt', 'must reject dirty input');
        $output = $this->temporaryRoot . '/output-dirty';

        $result = $this->runProcess([
            PHP_BINARY,
            $this->builderPath,
            '--source=' . $source,
            '--output=' . $output,
            '--version=1.0.0',
        ], dirname(__DIR__, 2));

        self::assertNotSame(0, $result['exit_code']);
        self::assertStringContainsString('clean', strtolower($result['output']));
        self::assertFileDoesNotExist($output . '/ALQUILA_PRO_V1_SERVER_READY.zip');
    }

    public function testBuilderCreatesAllowlistedZipAndSourceVersionFileHashes(): void
    {
        $source = $this->createCommittedSource();
        $sourceSha = trim($this->runProcess(['git', 'rev-parse', 'HEAD'], $source)['output']);
        $output = $this->temporaryRoot . '/output-clean';

        $result = $this->runProcess([
            PHP_BINARY,
            $this->builderPath,
            '--source=' . $source,
            '--output=' . $output,
            '--version=1.0.0',
        ], dirname(__DIR__, 2));

        self::assertSame(0, $result['exit_code'], $result['output']);
        $expectedArtifacts = [
            'ALQUILA_PRO_V1_SERVER_READY.manifest.json',
            'ALQUILA_PRO_V1_SERVER_READY.sha256',
            'ALQUILA_PRO_V1_SERVER_READY.zip',
        ];
        $actualArtifacts = array_values(array_diff(scandir($output) ?: [], ['.', '..']));
        sort($expectedArtifacts);
        sort($actualArtifacts);
        self::assertSame($expectedArtifacts, $actualArtifacts);

        $zipPath = $output . '/ALQUILA_PRO_V1_SERVER_READY.zip';
        $zip = new ZipArchive();
        self::assertSame(true, $zip->open($zipPath));
        try {
            $entries = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entries[] = (string) $zip->getNameIndex($index);
            }

            foreach ([
                'index.php', '.htaccess', 'app/App.php', 'bootstrap/app.php', 'assets/app.css',
                'database/migrations/001.sql', 'resources/views/home.php', 'config/database.php.example',
                'composer.json', 'composer.lock', 'vendor/autoload.php', 'storage/app/',
                'storage/unit-media/', 'docs/release/VERSION',
            ] as $requiredEntry) {
                self::assertContains($requiredEntry, $entries, "Missing ZIP entry {$requiredEntry}.");
            }

            foreach ([
                '.env', 'config/database.php', 'config/database.php.tmp', 'tests/Leak.php',
                'node_modules/dev.txt', 'screenshots/qa.png', 'storage/private/customer.txt',
                'docs/plans/internal.md', 'vendor/poison.php',
            ] as $forbiddenEntry) {
                self::assertNotContains($forbiddenEntry, $entries, "Forbidden ZIP entry {$forbiddenEntry}.");
            }

            foreach ($entries as $entry) {
                self::assertFalse(str_starts_with($entry, '/'));
                self::assertFalse(str_contains($entry, '../'));
                self::assertFalse(str_starts_with($entry, 'vendor/fixture/dev-only/'));
            }

            $appContents = $zip->getFromName('app/App.php');
            self::assertIsString($appContents);
        } finally {
            $zip->close();
        }

        $manifest = json_decode(
            (string) file_get_contents($output . '/ALQUILA_PRO_V1_SERVER_READY.manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame('1.0.0', $manifest['version']);
        self::assertSame($sourceSha, $manifest['source_sha']);
        self::assertArrayHasKey('files', $manifest);
        self::assertArrayHasKey('app/App.php', $manifest['files']);
        self::assertSame(hash('sha256', $appContents), $manifest['files']['app/App.php']['sha256']);
        self::assertSame(hash_file('sha256', $zipPath), $manifest['archive_sha256']);

        $checksum = trim((string) file_get_contents($output . '/ALQUILA_PRO_V1_SERVER_READY.sha256'));
        self::assertSame(hash_file('sha256', $zipPath) . '  ALQUILA_PRO_V1_SERVER_READY.zip', $checksum);
    }

    public function testBuilderRejectsVersionThatDiffersFromFrozenSourceVersion(): void
    {
        $source = $this->createCommittedSource();
        $output = $this->temporaryRoot . '/output-wrong-version';

        $result = $this->runProcess([
            PHP_BINARY,
            $this->builderPath,
            '--source=' . $source,
            '--output=' . $output,
            '--version=9.9.9',
        ], dirname(__DIR__, 2));

        self::assertNotSame(0, $result['exit_code']);
        self::assertStringContainsString('VERSION', $result['output']);
        self::assertFileDoesNotExist($output . '/ALQUILA_PRO_V1_SERVER_READY.zip');
    }

    private function createCommittedSource(): string
    {
        $source = $this->temporaryRoot . '/source-' . bin2hex(random_bytes(4));
        mkdir($source, 0700, true);
        $files = [
            'index.php' => "<?php require __DIR__ . '/vendor/autoload.php';\n",
            '.htaccess' => "Options -Indexes\n",
            'composer.json' => json_encode([
                'name' => 'alquilapro/server-ready-fixture',
                'type' => 'project',
                'require' => ['php' => '>=8.3 <8.6'],
                'require-dev' => ['fixture/dev-only' => '1.0.0'],
                'repositories' => [[
                    'type' => 'path',
                    'url' => 'dev-package',
                    'options' => ['symlink' => false],
                ]],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
            'config/database.php.example' => "<?php return ['password' => 'SET_A_PRIVATE_PASSWORD'];\n",
            'docs/release/VERSION' => "1.0.0\n",
            'docs/release/RELEASE_NOTES_V1.md' => "# Fixture 1.0.0\n",
            'docs/release/SERVER_READY_FILE_POLICY.md' => "Fixture allowlist\n",
            'app/App.php' => "<?php declare(strict_types=1);\n",
            'bootstrap/app.php' => "<?php return static fn (): bool => true;\n",
            'assets/app.css' => "body { color: #17211f; }\n",
            'database/migrations/001.sql' => "CREATE TABLE fixture (id INT PRIMARY KEY);\n",
            'resources/views/home.php' => "<main>fixture</main>\n",
            '.env' => "APP_SECRET=fixture-secret\n",
            'config/database.php' => "<?php return ['password' => 'fixture-secret'];\n",
            'config/database.php.tmp' => "fixture-secret\n",
            'tests/Leak.php' => "<?php // forbidden test content\n",
            'node_modules/dev.txt' => "forbidden development dependency\n",
            'screenshots/qa.png' => "fake screenshot bytes\n",
            'storage/private/customer.txt' => "private customer data\n",
            'docs/plans/internal.md' => "internal plan\n",
            'vendor/poison.php' => "<?php // source vendor must not ship\n",
            'dev-package/composer.json' => json_encode([
                'name' => 'fixture/dev-only',
                'version' => '1.0.0',
                'type' => 'library',
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
        ];

        foreach ($files as $relativePath => $contents) {
            $path = $source . '/' . $relativePath;
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            file_put_contents($path, $contents);
        }

        $this->runSuccessfulProcess(['composer', 'update', '--no-install', '--no-interaction', '--no-progress'], $source);
        $this->runSuccessfulProcess(['git', 'init', '--quiet'], $source);
        $this->runSuccessfulProcess(['git', 'config', 'user.email', 'release-fixture@example.invalid'], $source);
        $this->runSuccessfulProcess(['git', 'config', 'user.name', 'Release Fixture'], $source);
        $this->runSuccessfulProcess(['git', 'add', '-A'], $source);
        $this->runSuccessfulProcess(['git', 'commit', '--quiet', '-m', 'fixture source'], $source);

        return $source;
    }

    /** @param list<string> $command
     *  @return array{exit_code: int, output: string}
     */
    private function runProcess(array|string $command, string $workingDirectory): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, $workingDirectory);
        self::assertIsResource($process, 'Could not start command: ' . (is_array($command) ? implode(' ', $command) : $command));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit_code' => proc_close($process), 'output' => (string) $stdout . (string) $stderr];
    }

    /** @param list<string> $command */
    private function runSuccessfulProcess(array $command, string $workingDirectory): void
    {
        if ($command[0] === 'composer') {
            $command = 'composer ' . implode(' ', array_slice($command, 1));
        }
        $result = $this->runProcess($command, $workingDirectory);
        self::assertSame(0, $result['exit_code'], $result['output']);
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->removeTree($path . '/' . $entry);
        }
        rmdir($path);
    }
}
