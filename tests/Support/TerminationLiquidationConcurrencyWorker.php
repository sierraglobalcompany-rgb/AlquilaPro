<?php

declare(strict_types=1);

use AlquilaPro\Auth\UserService;
use AlquilaPro\Community\CommunityService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\ObligationService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Insurance\InsuranceService;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\Leases\TerminationService;
use AlquilaPro\Memberships\MembershipService;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/vendor/autoload.php';

$leaseId = (int) ($argv[1] ?? 0);
$actorId = (int) ($argv[2] ?? 0);
$now = (int) ($argv[3] ?? 0);
$readyFile = (string) ($argv[4] ?? '');

try {
    $pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
        'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('TEST_DB_PORT') ?: '3306',
        'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
        'username' => getenv('TEST_DB_USER') ?: 'root',
        'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
    ]));
    $directory = sys_get_temp_dir();
    $paymentMethods = new PaymentMethodService($pdo, $directory);
    $finance = new FinanceService($pdo, $paymentMethods, $directory);
    $service = new TerminationService(
        $pdo,
        new LeaseService($pdo, new UserService($pdo)),
        $finance,
        new ObligationService($pdo, $finance, $directory),
        new InsuranceService($pdo, $directory),
        new DocumentService($pdo, $directory),
        new CommunityService($pdo),
        new MembershipService($pdo, $finance),
        true,
    );

    file_put_contents($readyFile, 'ready');
    $liquidation = $service->finalizeLiquidation($leaseId, [], null, $actorId, '2026-10-14', $now);
    echo json_encode(['ok' => true, 'id' => (int) $liquidation['id']], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    echo json_encode(['ok' => false, 'id' => null], JSON_THROW_ON_ERROR);
}
