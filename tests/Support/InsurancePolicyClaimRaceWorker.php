<?php

declare(strict_types=1);

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Insurance\InsuranceService;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/vendor/autoload.php';

[$script, $operation, $policyId, $argument, $actorId, $now, $readyFile] = $argv;
try {
    $pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
        'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('TEST_DB_PORT') ?: '3306',
        'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
        'username' => getenv('TEST_DB_USER') ?: 'root',
        'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
    ]));
    $service = new InsuranceService($pdo, sys_get_temp_dir() . '/insurance-policy-claim-race-documents');
    file_put_contents($readyFile, 'ready');
    if ($operation === 'create-claim') {
        $claim = $service->createClaim(
            (int) $policyId,
            ['incident_on' => (string) $argument, 'description' => 'Incidente concurrente', 'claimed_amount' => null, 'checklist' => []],
            null,
            (int) $actorId,
            '2026-10-14',
            (int) $now,
        );
        echo json_encode(['ok' => true, 'claim_id' => $claim['id']], JSON_THROW_ON_ERROR);
    } elseif ($operation === 'remove-policy-document') {
        $policy = $service->removePolicyDocument((int) $policyId, (string) $argument, (int) $now);
        echo json_encode(['ok' => true, 'document_count' => count($policy['documents'])], JSON_THROW_ON_ERROR);
    } elseif ($operation === 'cancel-policy') {
        $policy = $service->cancelPolicy((int) $policyId, (int) $actorId, (string) $argument, (int) $now);
        echo json_encode(['ok' => true, 'status' => $policy['status']], JSON_THROW_ON_ERROR);
    } else {
        throw new RuntimeException('Unsupported policy/claim race operation.');
    }
} catch (RuntimeException $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(1);
}
