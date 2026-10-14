<?php

declare(strict_types=1);

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Insurance\InsuranceService;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/vendor/autoload.php';

[$script, $operation, $claimId, $actorId, $now, $readyFile] = $argv;
try {
    $pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
        'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('TEST_DB_PORT') ?: '3306',
        'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
        'username' => getenv('TEST_DB_USER') ?: 'root',
        'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
    ]));
    $service = new InsuranceService($pdo, sys_get_temp_dir() . '/insurance-checklist-worker-documents');
    file_put_contents($readyFile, 'ready');
    if ($operation === 'transition') {
        $claim = $service->transitionClaim(
            (int) $claimId,
            'approved',
            ['resolved_on' => '2026-10-14'],
            (int) $actorId,
            '2026-10-14',
            (int) $now,
        );
    } elseif ($operation === 'checklist') {
        $claim = $service->updateClaimChecklist((int) $claimId, [
            ['key' => 'proof', 'label' => 'Soporte', 'required' => true, 'completed' => true],
        ], (int) $now);
    } else {
        throw new RuntimeException('Unsupported checklist/transition race operation.');
    }
    echo json_encode(['ok' => true, 'status' => $claim['status'], 'completed' => $claim['checklist'][0]['completed']], JSON_THROW_ON_ERROR);
} catch (RuntimeException $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(1);
}
