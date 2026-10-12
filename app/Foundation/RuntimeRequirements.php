<?php

declare(strict_types=1);

namespace AlquilaPro\Foundation;

final class RuntimeRequirements
{
    public static function evaluate(string $phpVersion, array $extensions, bool $storageWritable): array
    {
        $extensions = array_map(
            static fn (mixed $extension): string => strtolower((string) $extension),
            $extensions,
        );

        $phpOk = version_compare($phpVersion, '8.3.0', '>=')
            && version_compare($phpVersion, '8.6.0', '<');

        $checks = [
            'php' => ['ok' => $phpOk, 'value' => $phpVersion],
            'pdo' => ['ok' => in_array('pdo', $extensions, true)],
            'pdo_mysql' => ['ok' => in_array('pdo_mysql', $extensions, true)],
            'mbstring' => ['ok' => in_array('mbstring', $extensions, true)],
            'fileinfo' => ['ok' => in_array('fileinfo', $extensions, true)],
            'storage' => ['ok' => $storageWritable],
        ];

        $ok = !in_array(false, array_column($checks, 'ok'), true);

        return ['ok' => $ok, 'checks' => $checks];
    }
}
