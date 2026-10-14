<?php

declare(strict_types=1);

use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Maintenance\MaintenanceService;
use Slim\Psr7\UploadedFile;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/vendor/autoload.php';

$readyFile = (string) $argv[array_key_last($argv)];
$operation = (string) ($argv[1] ?? '');
$requestId = (int) ($argv[2] ?? 0);
$actorId = (int) ($argv[3] ?? 0);
$photoDirectory = (string) ($argv[4] ?? '');
$temporaryPath = null;

try {
    $pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
        'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('TEST_DB_PORT') ?: '3306',
        'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
        'username' => getenv('TEST_DB_USER') ?: 'root',
        'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
    ]));
    $methods = new PaymentMethodService($pdo, $photoDirectory . '/receipts');
    $finance = new FinanceService($pdo, $methods, $photoDirectory . '/receipts');
    $service = new MaintenanceService($pdo, $finance, $photoDirectory);
    file_put_contents($readyFile, 'ready');

    switch ($operation) {
        case 'photo':
            $encoded = (string) ($argv[5] ?? '');
            $bytes = base64_decode($encoded, true);
            $temporaryPath = tempnam(sys_get_temp_dir(), 'maintenance-photo-worker-');
            if (!is_string($bytes) || $temporaryPath === false || file_put_contents($temporaryPath, $bytes) === false) {
                throw new RuntimeException('Unable to prepare worker photo.');
            }
            $service->addPhoto(
                $requestId,
                new UploadedFile($temporaryPath, 'photo.png', 'image/png', strlen($bytes), UPLOAD_ERR_OK, false),
                $actorId,
                1791980000,
            );
            break;
        case 'charge':
            $service->createRepairCharge(
                $requestId,
                (string) ($argv[5] ?? ''),
                (string) ($argv[6] ?? ''),
                (string) ($argv[7] ?? ''),
                $actorId,
            );
            break;
        case 'cancel':
            $service->transitionStatus(
                $requestId,
                'cancelled',
                $actorId,
                (int) ($argv[5] ?? 0),
                null,
                'Cancelada durante prueba de concurrencia',
            );
            break;
        case 'transition':
            $service->transitionStatus(
                $requestId,
                (string) ($argv[5] ?? ''),
                $actorId,
                (int) ($argv[6] ?? 0),
            );
            break;
        default:
            throw new RuntimeException('Unsupported maintenance worker operation.');
    }

    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} finally {
    if (is_string($temporaryPath) && is_file($temporaryPath)) {
        @unlink($temporaryPath);
    }
}
