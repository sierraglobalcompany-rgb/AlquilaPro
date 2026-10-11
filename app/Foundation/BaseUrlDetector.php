<?php

declare(strict_types=1);

namespace AlquilaPro\Foundation;

final class BaseUrlDetector
{
    public static function fromServer(array $server): string
    {
        $https = isset($server['HTTPS'])
            && !in_array(strtolower((string) $server['HTTPS']), ['', '0', 'off'], true);

        $scheme = $https ? 'https' : 'http';
        $host = trim((string) ($server['HTTP_HOST'] ?? ''));
        $scriptName = (string) ($server['SCRIPT_NAME'] ?? '/index.php');
        $directory = str_replace('\\', '/', dirname($scriptName));
        $basePath = in_array($directory, ['/', '.', '\\'], true) ? '' : '/' . trim($directory, '/');

        return $scheme . '://' . $host . $basePath;
    }
}
