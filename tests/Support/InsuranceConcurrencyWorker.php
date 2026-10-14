<?php

declare(strict_types=1);

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Insurance\InsuranceService;
use Slim\Psr7\UploadedFile;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/vendor/autoload.php';

[$script, $ownerType, $ownerId, $now, $documentDirectory, $label, $encodedBytes, $readyFile] = $argv;
$temporaryPath = tempnam(sys_get_temp_dir(), 'insurance-concurrent-upload-');
try {
    $bytes = base64_decode($encodedBytes, true);
    if ($temporaryPath === false || !is_string($bytes) || file_put_contents($temporaryPath, $bytes) === false) {
        throw new RuntimeException('Unable to prepare worker upload.');
    }
    $pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
        'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('TEST_DB_PORT') ?: '3306',
        'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
        'username' => getenv('TEST_DB_USER') ?: 'root',
        'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
    ]));
    $service = new InsuranceService($pdo, $documentDirectory);
    file_put_contents($readyFile, 'ready');
    $upload = new UploadedFile($temporaryPath, $label . '.pdf', 'application/pdf', strlen($bytes), UPLOAD_ERR_OK, false);
    $owner = match ($ownerType) {
        'policy' => $service->addPolicyDocument((int) $ownerId, $label, $upload, (int) $now),
        'claim' => $service->addClaimDocument((int) $ownerId, $label, $upload, (int) $now),
        default => throw new RuntimeException('Unsupported document owner.'),
    };
    echo json_encode(['ok' => true, 'documents' => count($owner['documents'])], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} finally {
    if (is_string($temporaryPath) && is_file($temporaryPath)) {
        @unlink($temporaryPath);
    }
}
