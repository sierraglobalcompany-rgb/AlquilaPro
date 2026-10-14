<?php

declare(strict_types=1);

use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Memberships\MembershipService;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/vendor/autoload.php';

$action = (string) ($argv[1] ?? '');
$readyFile = (string) $argv[array_key_last($argv)];

try {
    $pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
        'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('TEST_DB_PORT') ?: '3306',
        'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
        'username' => getenv('TEST_DB_USER') ?: 'root',
        'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
    ]));
    $service = new MembershipService(
        $pdo,
        new FinanceService($pdo, new PaymentMethodService($pdo, sys_get_temp_dir()), sys_get_temp_dir()),
    );
    file_put_contents($readyFile, 'ready');

    if ($action === 'accept') {
        $service->accept((int) $argv[2], (int) $argv[3], (int) $argv[4], (string) $argv[5], (int) $argv[6]);
    } elseif ($action === 'renew') {
        $service->renew((int) $argv[2], (int) $argv[3], (string) $argv[4], (int) $argv[5]);
    } elseif ($action === 'decide') {
        $service->decideCharge(
            (int) $argv[2],
            (string) $argv[3],
            ($argv[4] ?? '') === '' ? null : (string) $argv[4],
            ($argv[5] ?? '') === '' ? null : (string) $argv[5],
            (int) $argv[6],
            (int) $argv[7],
        );
    } else {
        throw new RuntimeException('Unsupported membership worker action.');
    }

    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    echo json_encode(['ok' => false], JSON_THROW_ON_ERROR);
}
