<?php

declare(strict_types=1);

use AlquilaPro\Foundation\BaseUrlDetector;

require __DIR__ . '/vendor/autoload.php';

$baseUrl = BaseUrlDetector::fromServer($_SERVER);
$basePath = (string) (parse_url($baseUrl, PHP_URL_PATH) ?: '');
$factory = require __DIR__ . '/bootstrap/app.php';

$app = $factory([
    'lock_path' => __DIR__ . '/storage/app/installed.lock',
    'database_config_path' => __DIR__ . '/config/database.php',
    'migration_directory' => __DIR__ . '/database/migrations',
    'base_url' => $baseUrl,
    'base_path' => $basePath,
]);

$app->run();
