<?php

declare(strict_types=1);

namespace AlquilaPro\Foundation;

use PDO;
use Throwable;
use RuntimeException;

final class MigrationRunner
{
    public static function migrate(PDO $pdo, string $migrationDirectory): array
    {
        if (!is_dir($migrationDirectory) || !is_readable($migrationDirectory)) {
            throw new RuntimeException('No fue posible leer las migraciones.');
        }

        self::ensureTrackingTable($pdo);

        $files = glob(rtrim($migrationDirectory, '/\\') . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        natsort($files);
        $files = array_values($files);

        $alreadyApplied = $pdo
            ->query('SELECT migration FROM schema_migrations')
            ->fetchAll(PDO::FETCH_COLUMN);
        $alreadyApplied = array_fill_keys(array_map('strval', $alreadyApplied), true);

        $appliedNow = [];
        $record = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');

        foreach ($files as $file) {
            $migration = basename($file);

            if (isset($alreadyApplied[$migration])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("No fue posible aplicar la migración {$migration}.");
            }

            try {
                $pdo->exec($sql);
                $record->execute(['migration' => $migration]);
            } catch (Throwable) {
                throw new RuntimeException("No fue posible aplicar la migración {$migration}.");
            }

            $appliedNow[] = $migration;
        }

        return [
            'applied' => count($appliedNow),
            'migrations' => $appliedNow,
        ];
    }

    private static function ensureTrackingTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'migration VARCHAR(255) NOT NULL PRIMARY KEY,'
            . 'applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }
}
