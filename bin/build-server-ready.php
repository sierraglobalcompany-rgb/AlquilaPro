<?php

declare(strict_types=1);

const PACKAGE = 'ALQUILA_PRO_V1_SERVER_READY.zip';
const CHECKSUM = 'ALQUILA_PRO_V1_SERVER_READY.sha256';
const MANIFEST = 'ALQUILA_PRO_V1_SERVER_READY.manifest.json';
const VERSION = '1.0.0';

try {
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (!preg_match('/^--(source|output|version)=(.+)$/', $argument, $match) || isset($options[$match[1]])) {
            throw new RuntimeException(
                'Usage: php bin/build-server-ready.php --source=<repo> --output=<dir> --version=<version>',
            );
        }
        $options[$match[1]] = $match[2];
    }
    if (count($options) !== 3 || !isset($options['source'], $options['output'], $options['version'])) {
        throw new RuntimeException(
            'Usage: php bin/build-server-ready.php --source=<repo> --output=<dir> --version=<version>',
        );
    }
    buildPackage($options['source'], $options['output'], $options['version']);
    fwrite(STDOUT, 'Built ' . PACKAGE . PHP_EOL);
} catch (Throwable $error) {
    fwrite(STDERR, 'Server-ready build failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

function buildPackage(string $sourceArgument, string $outputArgument, string $version): void
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('Enable PHP ZipArchive to build the local release package.');
    }
    if ($version !== VERSION) {
        throw new RuntimeException('Requested version must match the frozen docs/release/VERSION value ' . VERSION . '.');
    }

    $source = realpath($sourceArgument);
    if ($source === false || !is_dir($source)) {
        throw new RuntimeException('Source directory does not exist.');
    }
    $gitRoot = trim(runProcess(['git', '-C', $source, 'rev-parse', '--show-toplevel'], $source));
    if (!samePath($gitRoot, $source)) {
        throw new RuntimeException('Source must be the root of a Git repository.');
    }
    if (trim(runProcess(['git', '-C', $source, 'status', '--porcelain', '--untracked-files=all'], $source)) !== '') {
        throw new RuntimeException('Source must have a clean Git working tree before packaging.');
    }

    $sourceSha = strtolower(trim(runProcess(['git', '-C', $source, 'rev-parse', 'HEAD'], $source)));
    if (!preg_match('/^[a-f0-9]{40,64}$/', $sourceSha)) {
        throw new RuntimeException('Could not resolve the source commit SHA.');
    }
    if (trim((string) @file_get_contents($source . '/docs/release/VERSION')) !== $version) {
        throw new RuntimeException('Requested version must match docs/release/VERSION.');
    }

    $output = resolveOutput($outputArgument, $source);
    $stage = sys_get_temp_dir() . '/alquilapro_server_ready_' . bin2hex(random_bytes(8));
    if (!mkdir($stage, 0700, true)) {
        throw new RuntimeException('Could not create a fresh staging directory.');
    }

    $artifacts = [$output . '/' . PACKAGE, $output . '/' . CHECKSUM, $output . '/' . MANIFEST];
    try {
        foreach (['index.php', '.htaccess', 'composer.json', 'composer.lock', 'config/database.php.example'] as $path) {
            $from = $source . '/' . $path;
            if (!is_file($from) || is_link($from)) {
                throw new RuntimeException('Required allowlisted source file is missing or unsafe: ' . $path);
            }
            makeDirectory(dirname($stage . '/' . $path));
            if (!copy($from, $stage . '/' . $path)) {
                throw new RuntimeException('Could not stage required file: ' . $path);
            }
        }

        foreach (['app', 'bootstrap', 'assets', 'resources', 'docs/release', 'database/migrations'] as $path) {
            $from = $source . '/' . $path;
            if (!is_dir($from) || is_link($from)) {
                throw new RuntimeException('Required allowlisted source directory is missing or unsafe: ' . $path);
            }
            copyDirectory($from, $stage . '/' . $path);
        }
        foreach ([
            'docs/release/SERVER_READY_FILE_POLICY.md',
            'docs/release/RELEASE_NOTES_V1.md',
            'docs/release/VERSION',
        ] as $path) {
            if (!is_file($source . '/' . $path)) {
                throw new RuntimeException('Required release metadata is missing: ' . $path);
            }
        }
        foreach ([
            'storage/app', 'storage/branding', 'storage/unit-media', 'storage/candidate-documents',
            'storage/contracts', 'storage/payment-method-qrs', 'storage/payment-receipts',
            'storage/obligation-supports', 'storage/insurance-documents', 'storage/maintenance-photos',
        ] as $path) {
            makeDirectory($stage . '/' . $path);
        }

        installProductionDependencies($stage);
        removeVendorTestDirectories($stage . '/vendor');

        $files = [];
        $directories = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stage, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                throw new RuntimeException('Staging may contain no symlinks.');
            }
            $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($entry->getPathname(), strlen($stage) + 1));
            if ($entry->isDir()) {
                $directories[] = $path;
                continue;
            }
            if (!$entry->isFile()) {
                throw new RuntimeException('Staging may contain only regular files and directories.');
            }
            $sha = hash_file('sha256', $entry->getPathname());
            $size = filesize($entry->getPathname());
            if (!is_string($sha) || !is_int($size)) {
                throw new RuntimeException('Could not hash staged file: ' . $path);
            }
            $files[$path] = ['sha256' => $sha, 'size_bytes' => $size];
        }
        ksort($files, SORT_STRING);
        sort($directories, SORT_STRING);

        if (!is_dir($output) && !mkdir($output, 0700, true) && !is_dir($output)) {
            throw new RuntimeException('Could not create the output directory.');
        }
        $zip = new ZipArchive();
        if ($zip->open($artifacts[0], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP archive.');
        }
        try {
            foreach ($directories as $directory) {
                if (!$zip->addEmptyDir($directory)) {
                    throw new RuntimeException('Could not add ZIP directory: ' . $directory);
                }
            }
            foreach (array_keys($files) as $path) {
                $localPath = $stage . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
                if (!$zip->addFile($localPath, $path)) {
                    throw new RuntimeException('Could not add ZIP file: ' . $path);
                }
            }
        } catch (Throwable $error) {
            $zip->close();
            throw $error;
        }
        if (!$zip->close()) {
            throw new RuntimeException('Could not finalize the ZIP archive.');
        }

        $archiveSha = hash_file('sha256', $artifacts[0]);
        $archiveSize = filesize($artifacts[0]);
        if (!is_string($archiveSha) || !is_int($archiveSize)) {
            throw new RuntimeException('Could not calculate the ZIP hash and size.');
        }
        $manifest = json_encode([
            'format_version' => 1,
            'package' => PACKAGE,
            'version' => $version,
            'source_sha' => $sourceSha,
            'archive_sha256' => $archiveSha,
            'archive_size_bytes' => $archiveSize,
            'files' => $files,
            'directories' => $directories,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($artifacts[1], $archiveSha . '  ' . PACKAGE . "\n", LOCK_EX) === false
            || file_put_contents($artifacts[2], $manifest, LOCK_EX) === false
        ) {
            throw new RuntimeException('Could not write the ZIP checksum and manifest.');
        }
    } catch (Throwable $error) {
        foreach ($artifacts as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        throw $error;
    } finally {
        removeTree($stage);
    }
}

function resolveOutput(string $argument, string $source): string
{
    $output = realpath($argument);
    if ($output !== false && !is_dir($output)) {
        throw new RuntimeException('Output path must be a directory.');
    }
    if ($output === false) {
        $parent = realpath(dirname($argument));
        if ($parent === false || !is_dir($parent)) {
            throw new RuntimeException('Output directory parent must already exist.');
        }
        $output = $parent . DIRECTORY_SEPARATOR . basename($argument);
    }
    if (pathWithin($source, $output)) {
        throw new RuntimeException('Output directory must be outside the source repository.');
    }
    if (is_dir($output) && array_diff(scandir($output) ?: [], ['.', '..']) !== []) {
        throw new RuntimeException('Output directory must be empty to prevent stale artifacts.');
    }

    return $output;
}

function installProductionDependencies(string $stage): void
{
    $log = tempnam(sys_get_temp_dir(), 'alquila_composer_');
    if (!is_string($log)) {
        throw new RuntimeException('Could not create a temporary Composer log.');
    }
    $environment = [];
    foreach (['PATH', 'HOME', 'APPDATA', 'COMPOSER_HOME', 'COMPOSER_CACHE_DIR', 'COMSPEC', 'PATHEXT',
        'SystemRoot', 'TEMP', 'TMP', 'USERPROFILE', 'HOMEDRIVE', 'HOMEPATH'] as $name
    ) {
        $value = getenv($name);
        if (is_string($value)) {
            $environment[$name] = $value;
        }
    }
    $environment['COMPOSER_ALLOW_SUPERUSER'] = '1';
    $process = proc_open(
        'composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress',
        [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
        $pipes,
        $stage,
        $environment,
    );
    if (!is_resource($process)) {
        @unlink($log);
        throw new RuntimeException('Could not start Composer.');
    }
    fclose($pipes[0]);
    $exitCode = proc_close($process);
    $output = is_file($log) ? (string) file_get_contents($log) : '';
    @unlink($log);
    if ($exitCode !== 0) {
        throw new RuntimeException('Composer production install failed: ' . substr(trim($output), -4000));
    }
    if (!is_file($stage . '/vendor/autoload.php')) {
        throw new RuntimeException('Composer did not create the production autoloader.');
    }
}

function removeVendorTestDirectories(string $vendor): void
{
    $testDirectories = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($vendor, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && strtolower($entry->getBasename()) === 'tests') {
            $testDirectories[] = $entry->getPathname();
        }
    }
    foreach ($testDirectories as $directory) {
        removeTree($directory);
    }
}

function copyDirectory(string $source, string $target): void
{
    makeDirectory($target);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isLink()) {
            throw new RuntimeException('Symlinks are not allowed in packaged source directories.');
        }
        $relative = substr($entry->getPathname(), strlen($source) + 1);
        $destination = $target . DIRECTORY_SEPARATOR . $relative;
        if ($entry->isDir()) {
            makeDirectory($destination);
        } elseif ($entry->isFile()) {
            makeDirectory(dirname($destination));
            if (!copy($entry->getPathname(), $destination)) {
                throw new RuntimeException('Could not copy allowlisted source file.');
            }
        } else {
            throw new RuntimeException('Unsupported source entry in package allowlist.');
        }
    }
}

function runProcess(array $command, string $directory): string
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start required local command: ' . $command[0]);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Required local command failed: ' . $command[0] . ' ' . trim((string) $stderr));
    }

    return (string) $stdout;
}

function makeDirectory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Could not create a staging directory.');
    }
}

function removeTree(string $path): void
{
    if (is_link($path) || !is_dir($path)) {
        if (file_exists($path) || is_link($path)) {
            @unlink($path);
        }
        return;
    }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
        removeTree($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}

function pathWithin(string $root, string $path): bool
{
    $normalize = static fn (string $value): string => str_replace('\\', '/', rtrim($value, '/\\'));
    $root = $normalize($root);
    $path = $normalize($path);
    if (PHP_OS_FAMILY === 'Windows') {
        $root = strtolower($root);
        $path = strtolower($path);
    }

    return $path === $root || str_starts_with($path, $root . '/');
}

function samePath(string $left, string $right): bool
{
    $normalize = static fn (string $value): string => str_replace('\\', '/', rtrim($value, '/\\'));
    $left = $normalize($left);
    $right = $normalize($right);
    if (PHP_OS_FAMILY === 'Windows') {
        $left = strtolower($left);
        $right = strtolower($right);
    }

    return $left === $right;
}
