<?php

declare(strict_types=1);

use AlquilaPro\Foundation\BaseUrlDetector;

require __DIR__ . '/vendor/autoload.php';

$baseUrl = BaseUrlDetector::fromServer($_SERVER);
$basePath = (string) (parse_url($baseUrl, PHP_URL_PATH) ?: '');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('alquilapro_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $basePath === '' ? '/' : rtrim($basePath, '/') . '/',
        'secure' => str_starts_with($baseUrl, 'https://'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$factory = require __DIR__ . '/bootstrap/app.php';

$app = $factory([
    'lock_path' => __DIR__ . '/storage/app/installed.lock',
    'database_config_path' => __DIR__ . '/config/database.php',
    'migration_directory' => __DIR__ . '/database/migrations',
    'base_url' => $baseUrl,
    'base_path' => $basePath,
]);

$app->run();
