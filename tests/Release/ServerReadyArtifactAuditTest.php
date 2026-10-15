<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Release;

use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ServerReadyArtifactAuditTest extends TestCase
{
    public function testCandidateZipPassesStaticAuditAndExtractsCleanly(): void
    {
        $directory = getenv('SERVER_READY_ARTIFACT_DIR');
        if (!is_string($directory) || $directory === '') {
            self::markTestSkipped('Set SERVER_READY_ARTIFACT_DIR to audit a built candidate.');
        }

        $package = 'ALQUILA_PRO_V1_SERVER_READY.zip';
        $zipPath = $directory . DIRECTORY_SEPARATOR . $package;
        $checksumPath = substr($zipPath, 0, -4) . '.sha256';
        $manifestPath = substr($zipPath, 0, -4) . '.manifest.json';
        self::assertFileExists($zipPath);
        self::assertFileExists($checksumPath);
        self::assertFileExists($manifestPath);

        $archiveSha = hash_file('sha256', $zipPath);
        self::assertIsString($archiveSha);
        self::assertSame($archiveSha . '  ' . $package . "\n", file_get_contents($checksumPath));

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $manifest['format_version'] ?? null);
        self::assertSame($package, $manifest['package'] ?? null);
        self::assertSame('1.0.0', $manifest['version'] ?? null);
        self::assertMatchesRegularExpression('/^[a-f0-9]{40,64}$/', (string) ($manifest['source_sha'] ?? ''));
        $expectedSourceSha = getenv('SERVER_READY_SOURCE_SHA');
        if (is_string($expectedSourceSha) && $expectedSourceSha !== '') {
            self::assertSame($expectedSourceSha, $manifest['source_sha']);
        }
        self::assertSame($archiveSha, $manifest['archive_sha256'] ?? null);
        self::assertSame(filesize($zipPath), $manifest['archive_size_bytes'] ?? null);

        $zip = new ZipArchive();
        self::assertSame(true, $zip->open($zipPath));
        $archiveFiles = [];
        $archiveDirectories = [];
        $seenPaths = [];
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = (string) $zip->getNameIndex($index);
                $directoryEntry = str_ends_with($entry, '/');
                $path = $directoryEntry ? substr($entry, 0, -1) : $entry;
                self::assertNotSame('', $path, 'Archive entries cannot be empty or absolute.');
                self::assertStringNotContainsString('\\', $entry, 'ZIP paths must use forward slashes.');
                self::assertStringNotContainsString("\0", $entry);
                self::assertDoesNotMatchRegularExpression('/^[a-z]:/i', $path);
                foreach (explode('/', $path) as $segment) {
                    self::assertNotSame('', $segment, "Empty path segment in {$entry}.");
                    self::assertNotSame('.', $segment, "Dot path segment in {$entry}.");
                    self::assertNotSame('..', $segment, "Traversal path segment in {$entry}.");
                }

                $folded = strtolower($path);
                self::assertArrayNotHasKey($folded, $seenPaths, "Duplicate or case-conflicting ZIP path: {$entry}.");
                $seenPaths[$folded] = $path;

                self::assertDoesNotMatchRegularExpression(
                    '~(?:^|/)(?:\.git|\.github|\.codex-state|tests|node_modules|screenshots|logs|plans)(?:/|$)|(?:^|/)\.env(?:$|\.)~i',
                    $path,
                    "Forbidden package path: {$path}.",
                );
                self::assertNotSame('config/database.php', strtolower($path));

                if ($directoryEntry) {
                    $archiveDirectories[] = $path;
                } else {
                    self::assertFalse(str_starts_with(strtolower($path), 'storage/'), "User data cannot ship from storage: {$path}.");
                    $contents = $zip->getFromIndex($index);
                    self::assertIsString($contents);
                    self::assertDoesNotMatchRegularExpression('/-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/i', $contents);
                    self::assertDoesNotMatchRegularExpression('/\bAKIA[0-9A-Z]{16}\b/', $contents);
                    self::assertDoesNotMatchRegularExpression('/\b(?:ghp|github_pat|glpat|xox[baprs])-[A-Za-z0-9_-]{20,}\b/', $contents);
                    $archiveFiles[] = $path;
                }
            }

            foreach ([
                '.htaccess', 'index.php', 'composer.json', 'composer.lock', 'config/database.php.example',
                'vendor/autoload.php', 'vendor/composer/installed.json', 'docs/release/VERSION',
                'docs/release/SERVER_READY_FILE_POLICY.md', 'docs/release/RELEASE_NOTES_V1.md',
            ] as $required) {
                self::assertArrayHasKey(strtolower($required), $seenPaths, "Required package path missing: {$required}.");
            }

            $expectedStorageDirectories = [
                'storage/app', 'storage/branding', 'storage/unit-media', 'storage/candidate-documents',
                'storage/contracts', 'storage/payment-method-qrs', 'storage/payment-receipts',
                'storage/obligation-supports', 'storage/insurance-documents', 'storage/maintenance-photos',
            ];
            foreach ($expectedStorageDirectories as $storageDirectory) {
                self::assertContains($storageDirectory, $archiveDirectories);
            }

            $lock = json_decode((string) $zip->getFromName('composer.lock'), true, 512, JSON_THROW_ON_ERROR);
            $installed = json_decode((string) $zip->getFromName('vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
            $installedPackages = $installed['packages'] ?? $installed;
            $devPackageNames = array_column($lock['packages-dev'] ?? [], 'name');
            $installedPackageNames = array_column($installedPackages, 'name');
            self::assertNotEmpty($devPackageNames, 'The lock file should make the no-dev audit meaningful.');
            self::assertSame([], array_values(array_intersect($devPackageNames, $installedPackageNames)));

            $manifestFiles = $manifest['files'] ?? null;
            self::assertIsArray($manifestFiles);
            $manifestFileNames = array_keys($manifestFiles);
            sort($archiveFiles, SORT_STRING);
            sort($manifestFileNames, SORT_STRING);
            self::assertSame($manifestFileNames, $archiveFiles, 'ZIP file entries must match the manifest exactly.');

            $manifestDirectories = $manifest['directories'] ?? null;
            self::assertIsArray($manifestDirectories);
            sort($archiveDirectories, SORT_STRING);
            sort($manifestDirectories, SORT_STRING);
            self::assertSame($manifestDirectories, $archiveDirectories, 'ZIP directories must match the manifest exactly.');
        } finally {
            $zip->close();
        }

        $extractRoot = sys_get_temp_dir() . '/alquilapro_server_ready_audit_' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($extractRoot, 0700, true));
        try {
            $extractZip = new ZipArchive();
            self::assertSame(true, $extractZip->open($zipPath));
            try {
                self::assertTrue($extractZip->extractTo($extractRoot));
            } finally {
                $extractZip->close();
            }

            self::assertSame('1.0.0', trim((string) file_get_contents($extractRoot . '/docs/release/VERSION')));
            foreach ($manifest['files'] as $relativePath => $metadata) {
                $extractedFile = $extractRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                self::assertFileExists($extractedFile, "Missing extracted file {$relativePath}.");
                self::assertSame($metadata['size_bytes'], filesize($extractedFile), "Size mismatch for {$relativePath}.");
                self::assertSame($metadata['sha256'], hash_file('sha256', $extractedFile), "Hash mismatch for {$relativePath}.");
            }
            foreach ($manifest['directories'] as $relativePath) {
                self::assertDirectoryExists($extractRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
            }

            $autoload = $extractRoot . '/vendor/autoload.php';
            $result = $this->runProcess([PHP_BINARY, '-r', 'require $argv[1]; echo "production-autoload-ok";', $autoload]);
            self::assertSame(0, $result['exit_code'], $result['output']);
            self::assertSame('production-autoload-ok', trim($result['output']));
        } finally {
            $this->removeTree($extractRoot);
        }
    }

    private function runProcess(array $command): array
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit_code' => proc_close($process), 'output' => (string) $stdout . (string) $stderr];
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            if (file_exists($path) || is_link($path)) {
                unlink($path);
            }
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }
        rmdir($path);
    }
}
