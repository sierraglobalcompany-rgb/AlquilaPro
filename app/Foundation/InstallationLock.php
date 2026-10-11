<?php

declare(strict_types=1);

namespace AlquilaPro\Foundation;

use JsonException;
use RuntimeException;

final class InstallationLock
{
    public function __construct(private readonly string $path)
    {
    }

    public function isInstalled(): bool
    {
        return file_exists($this->path);
    }

    public function write(array $metadata): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible preparar el bloqueo de instalación.');
        }

        $safe = [
            'schema_version' => (string) ($metadata['schema_version'] ?? '1'),
            'installed_at' => (string) ($metadata['installed_at'] ?? date(DATE_ATOM)),
        ];

        try {
            $json = json_encode($safe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new RuntimeException('No fue posible crear el bloqueo de instalación.');
        }

        $temporary = $this->path . '.tmp';
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('No fue posible crear el bloqueo de instalación.');
        }

        @chmod($temporary, 0600);

        if (!rename($temporary, $this->path)) {
            @unlink($temporary);
            throw new RuntimeException('No fue posible activar el bloqueo de instalación.');
        }
    }
}
