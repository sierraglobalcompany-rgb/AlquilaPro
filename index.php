<?php

declare(strict_types=1);

use AlquilaPro\Foundation\BaseUrlDetector;

require __DIR__ . '/vendor/autoload.php';

$baseUrl = BaseUrlDetector::fromServer($_SERVER);
$basePath = (string) (parse_url($baseUrl, PHP_URL_PATH) ?: '');
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$healthPath = ($basePath === '' ? '' : rtrim($basePath, '/')) . '/health';
$notificationCronPath = ($basePath === '' ? '' : rtrim($basePath, '/')) . '/cron/notifications';

if ($requestPath !== $healthPath
    && $requestPath !== $notificationCronPath
    && session_status() !== PHP_SESSION_ACTIVE
) {
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
$environmentValue = static function (string $key): string {
    $value = getenv($key);
    return $value === false ? '' : $value;
};

$app = $factory([
    'lock_path' => __DIR__ . '/storage/app/installed.lock',
    'database_config_path' => __DIR__ . '/config/database.php',
    'migration_directory' => __DIR__ . '/database/migrations',
    'base_url' => $baseUrl,
    'base_path' => $basePath,
    'document_directory' => $environmentValue('ALQUILAPRO_DOCUMENT_DIRECTORY'),
    'good_conduct_bonus_enabled' => filter_var(
        $environmentValue('ALQUILAPRO_GOOD_CONDUCT_BONUS_ENABLED'),
        FILTER_VALIDATE_BOOLEAN,
    ),
    'notification_settings' => [
        'host' => $environmentValue('ALQUILAPRO_SMTP_HOST'),
        'port' => $environmentValue('ALQUILAPRO_SMTP_PORT'),
        'username' => $environmentValue('ALQUILAPRO_SMTP_USERNAME'),
        'password' => $environmentValue('ALQUILAPRO_SMTP_PASSWORD'),
        'encryption' => $environmentValue('ALQUILAPRO_SMTP_ENCRYPTION'),
        'from_email' => $environmentValue('ALQUILAPRO_SMTP_FROM_EMAIL'),
        'from_name' => $environmentValue('ALQUILAPRO_SMTP_FROM_NAME'),
        'cron_secret' => $environmentValue('ALQUILAPRO_CRON_SECRET'),
    ],
]);

$app->run();
