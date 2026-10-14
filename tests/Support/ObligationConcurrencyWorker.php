<?php

declare(strict_types=1);

use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\ObligationService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/vendor/autoload.php';

[$script, $operation, $obligationId, $argument, $actorId, $now, $readyFile] = $argv;
$obligationId = (int) $obligationId;
$actorId = (int) $actorId;
$now = (int) $now;
$supportDirectory = sys_get_temp_dir() . '/obligation-concurrency-worker-' . bin2hex(random_bytes(8));

try {
    $pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
        'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('TEST_DB_PORT') ?: '3306',
        'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
        'username' => getenv('TEST_DB_USER') ?: 'root',
        'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
    ]));
    $paymentMethods = new PaymentMethodService($pdo, $supportDirectory . '-qrs');
    $finance = new FinanceService($pdo, $paymentMethods, $supportDirectory . '-receipts');
    $obligations = new ObligationService($pdo, $finance, $supportDirectory);
    file_put_contents($readyFile, 'ready');

    $result = match ($operation) {
        'generate' => $obligations->generateCharge($obligationId, (string) $argument, $actorId),
        'void' => $obligations->void($obligationId, $actorId, (string) $argument, $now),
        'link' => $obligations->linkAdministrationCharge($obligationId, (int) $argument, $actorId),
        default => throw new RuntimeException('Unsupported test operation.'),
    };
    echo json_encode(['ok' => true, 'charge_id' => $result['charge_id'] ?? null], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
