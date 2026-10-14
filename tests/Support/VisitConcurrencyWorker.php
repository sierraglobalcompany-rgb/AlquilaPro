<?php

declare(strict_types=1);

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Maintenance\VisitService;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/vendor/autoload.php';

$operation = (string) ($argv[1] ?? '');
$visitId = (int) ($argv[2] ?? 0);
$actorId = (int) ($argv[3] ?? 0);
$now = (int) ($argv[4] ?? 0);
$payload = (string) ($argv[5] ?? '');
$readyFile = (string) $argv[array_key_last($argv)];

try {
    $pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
        'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('TEST_DB_PORT') ?: '3306',
        'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
        'username' => getenv('TEST_DB_USER') ?: 'root',
        'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
    ]));
    $service = new VisitService($pdo);
    file_put_contents($readyFile, 'ready');

    switch ($operation) {
        case 'propose':
            $slots = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($slots)) {
                throw new RuntimeException('Invalid test payload.');
            }
            $service->propose($visitId, $slots, null, null, $actorId, $now);
            break;
        case 'direct':
            $schedule = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($schedule) || !is_string($schedule['start_at'] ?? null)
                || !is_int($schedule['duration_minutes'] ?? null)
            ) {
                throw new RuntimeException('Invalid test payload.');
            }
            $service->scheduleDirect(
                $visitId,
                $schedule['start_at'],
                $schedule['duration_minutes'],
                null,
                null,
                $actorId,
                $now,
            );
            break;
        case 'select':
            $service->selectSlot($visitId, $payload, $actorId, $now);
            break;
        case 'reprogram':
            $slots = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($slots)) {
                throw new RuntimeException('Invalid test payload.');
            }
            $service->reprogram($visitId, $slots, $actorId, $now);
            break;
        case 'complete':
            $service->complete($visitId, 'Completada en prueba concurrente', $actorId, $now);
            break;
        case 'cancel':
            $service->cancel($visitId, 'Cancelada en prueba concurrente', $actorId, $now);
            break;
        default:
            throw new RuntimeException('Unsupported visit worker operation.');
    }

    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
