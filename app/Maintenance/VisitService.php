<?php

declare(strict_types=1);

namespace AlquilaPro\Maintenance;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final readonly class VisitService
{
    private const ACTIVE_REQUEST_STATUSES = ['open', 'in_progress'];
    private const VISIT_STATUSES = ['proposed', 'scheduled', 'completed', 'cancelled'];

    public function __construct(private PDO $pdo)
    {
    }

    /** @param list<array{start_at:string,duration_minutes:int}> $slots @return array<string,mixed> */
    public function propose(
        int $requestId,
        array $slots,
        ?string $technicianName,
        ?string $technicianContact,
        int $actorUserId,
        int $now,
    ): array {
        $normalizedSlots = $this->validateSlots($slots, $now);
        [$technicianName, $technicianContact] = $this->validateTechnician($technicianName, $technicianContact);
        if ($requestId <= 0 || $actorUserId <= 0 || $now <= 0) {
            throw $this->failure();
        }

        return $this->transaction(function () use (
            $requestId,
            $normalizedSlots,
            $technicianName,
            $technicianContact,
            $actorUserId,
            $now,
        ): array {
            $this->lockActiveRequest($requestId);
            $operationNow = $this->effectiveNow($now);
            $normalizedSlots = $this->validateSlots($normalizedSlots, $operationNow);
            $insert = $this->pdo->prepare(
                'INSERT INTO maintenance_visits '
                . '(maintenance_request_id, proposed_slots_json, technician_name, technician_contact, status, '
                . 'created_by_user_id, created_at, updated_at) '
                . 'VALUES (:request_id, :slots, :technician_name, :technician_contact, \'proposed\', '
                . ':actor_id, :created_at, :updated_at)',
            );
            $timestamp = gmdate('Y-m-d H:i:s', $operationNow);
            $insert->execute([
                'request_id' => $requestId,
                'slots' => $this->encodeSlots($normalizedSlots),
                'technician_name' => $technicianName,
                'technician_contact' => $technicianContact,
                'actor_id' => $actorUserId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            return $this->readVisit((int) $this->pdo->lastInsertId()) ?? throw $this->failure();
        });
    }

    /** @return array<string,mixed> */
    public function scheduleDirect(
        int $requestId,
        string $startAt,
        int $durationMinutes,
        ?string $technicianName,
        ?string $technicianContact,
        int $actorUserId,
        int $now,
    ): array {
        $startAt = $this->validateFutureDate($startAt, $now);
        $this->validateDuration($durationMinutes);
        [$technicianName, $technicianContact] = $this->validateTechnician($technicianName, $technicianContact);
        if ($requestId <= 0 || $actorUserId <= 0 || $now <= 0) {
            throw $this->failure();
        }

        return $this->transaction(function () use (
            $requestId,
            $startAt,
            $durationMinutes,
            $technicianName,
            $technicianContact,
            $actorUserId,
            $now,
        ): array {
            $this->lockActiveRequest($requestId);
            $operationNow = $this->effectiveNow($now);
            $startAt = $this->validateFutureDate($startAt, $operationNow);
            $timestamp = gmdate('Y-m-d H:i:s', $operationNow);
            $insert = $this->pdo->prepare(
                'INSERT INTO maintenance_visits '
                . '(maintenance_request_id, proposed_slots_json, selected_start_at, duration_minutes, '
                . 'technician_name, technician_contact, status, created_by_user_id, created_at, updated_at) '
                . 'VALUES (:request_id, \'[]\', :start_at, :duration, :technician_name, :technician_contact, '
                . '\'scheduled\', :actor_id, :created_at, :updated_at)',
            );
            $insert->execute([
                'request_id' => $requestId,
                'start_at' => $startAt,
                'duration' => $durationMinutes,
                'technician_name' => $technicianName,
                'technician_contact' => $technicianContact,
                'actor_id' => $actorUserId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            return $this->readVisit((int) $this->pdo->lastInsertId()) ?? throw $this->failure();
        });
    }

    /** @return array<string,mixed>|null */
    public function find(int $visitId): ?array
    {
        if ($visitId <= 0) {
            return null;
        }
        return $this->readVisit($visitId);
    }

    /** @return list<array<string,mixed>> */
    public function listForRequest(int $requestId): array
    {
        if ($requestId <= 0) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT * FROM maintenance_visits WHERE maintenance_request_id = :request_id '
            . 'ORDER BY created_at DESC, id DESC',
        );
        $statement->execute(['request_id' => $requestId]);
        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @return array<string,mixed> */
    public function selectSlot(int $visitId, string $startAt, int $actorUserId, int $now): array
    {
        if ($visitId <= 0 || $actorUserId <= 0 || $now <= 0) {
            throw $this->failure();
        }

        return $this->transaction(function () use ($visitId, $startAt, $actorUserId, $now): array {
            $visit = $this->lockVisit($visitId);
            if ($visit['status'] !== 'proposed') {
                throw $this->failure();
            }
            $this->lockActiveRequest((int) $visit['maintenance_request_id']);
            $operationNow = $this->effectiveNow($now);
            $selected = null;
            foreach ($visit['proposed_slots'] as $slot) {
                if (hash_equals((string) $slot['start_at'], $startAt)) {
                    $selected = $slot;
                    break;
                }
            }
            if (!is_array($selected) || $this->validateFutureDate($startAt, $operationNow) !== $startAt) {
                throw $this->failure();
            }
            $update = $this->pdo->prepare(
                'UPDATE maintenance_visits SET selected_start_at = :start_at, duration_minutes = :duration, '
                . 'status = \'scheduled\', updated_at = :updated_at WHERE id = :id AND status = \'proposed\'',
            );
            $update->execute([
                'start_at' => $startAt,
                'duration' => (int) $selected['duration_minutes'],
                'updated_at' => gmdate('Y-m-d H:i:s', $operationNow),
                'id' => $visitId,
            ]);
            if ($update->rowCount() !== 1) {
                throw $this->failure();
            }
            return $this->readVisit($visitId) ?? throw $this->failure();
        });
    }

    /** @param list<array{start_at:string,duration_minutes:int}> $slots @return array<string,mixed> */
    public function reprogram(int $visitId, array $slots, int $actorUserId, int $now): array
    {
        $normalizedSlots = $this->validateSlots($slots, $now);
        if ($visitId <= 0 || $actorUserId <= 0 || $now <= 0) {
            throw $this->failure();
        }

        return $this->transaction(function () use ($visitId, $normalizedSlots, $actorUserId, $now): array {
            $visit = $this->lockVisit($visitId);
            if (!in_array($visit['status'], ['proposed', 'scheduled'], true)) {
                throw $this->failure();
            }
            $this->lockActiveRequest((int) $visit['maintenance_request_id']);
            $operationNow = $this->effectiveNow($now);
            $normalizedSlots = $this->validateSlots($normalizedSlots, $operationNow);
            $update = $this->pdo->prepare(
                'UPDATE maintenance_visits SET proposed_slots_json = :slots, selected_start_at = NULL, '
                . 'duration_minutes = NULL, status = \'proposed\', updated_at = :updated_at '
                . 'WHERE id = :id AND status IN (\'proposed\', \'scheduled\')',
            );
            $update->execute([
                'slots' => $this->encodeSlots($normalizedSlots),
                'updated_at' => gmdate('Y-m-d H:i:s', $operationNow),
                'id' => $visitId,
            ]);
            if ($update->rowCount() !== 1) {
                throw $this->failure();
            }
            return $this->readVisit($visitId) ?? throw $this->failure();
        });
    }

    /** @return array<string,mixed> */
    public function complete(int $visitId, ?string $resultNotes, int $actorUserId, int $now): array
    {
        $resultNotes = $resultNotes === null ? null : trim($resultNotes);
        if ($visitId <= 0 || $actorUserId <= 0 || $now <= 0
            || ($resultNotes !== null && mb_strlen($resultNotes, 'UTF-8') > 1000)
        ) {
            throw $this->failure();
        }

        return $this->transaction(function () use ($visitId, $resultNotes, $actorUserId, $now): array {
            $visit = $this->lockVisit($visitId);
            if ($visit['status'] !== 'scheduled') {
                throw $this->failure();
            }
            $update = $this->pdo->prepare(
                'UPDATE maintenance_visits SET status = \'completed\', result_notes = :notes, '
                . 'completed_by_user_id = :actor_id, completed_at = :completed_at, updated_at = :updated_at '
                . 'WHERE id = :id AND status = \'scheduled\'',
            );
            $timestamp = gmdate('Y-m-d H:i:s', $now);
            $update->execute([
                'notes' => $resultNotes === '' ? null : $resultNotes,
                'actor_id' => $actorUserId,
                'completed_at' => $timestamp,
                'updated_at' => $timestamp,
                'id' => $visitId,
            ]);
            if ($update->rowCount() !== 1) {
                throw $this->failure();
            }
            return $this->readVisit($visitId) ?? throw $this->failure();
        });
    }

    /** @return array<string,mixed> */
    public function cancel(int $visitId, string $reason, int $actorUserId, int $now): array
    {
        $reason = trim($reason);
        if ($visitId <= 0 || $actorUserId <= 0 || $now <= 0 || $reason === ''
            || mb_strlen($reason, 'UTF-8') > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $reason) === 1
        ) {
            throw $this->failure();
        }

        return $this->transaction(function () use ($visitId, $reason, $actorUserId, $now): array {
            $visit = $this->lockVisit($visitId);
            if (!in_array($visit['status'], ['proposed', 'scheduled'], true)) {
                throw $this->failure();
            }
            $update = $this->pdo->prepare(
                'UPDATE maintenance_visits SET status = \'cancelled\', cancelled_by_user_id = :actor_id, '
                . 'cancelled_at = :cancelled_at, cancel_reason = :reason, updated_at = :updated_at '
                . 'WHERE id = :id AND status IN (\'proposed\', \'scheduled\')',
            );
            $timestamp = gmdate('Y-m-d H:i:s', $now);
            $update->execute([
                'actor_id' => $actorUserId,
                'cancelled_at' => $timestamp,
                'reason' => $reason,
                'updated_at' => $timestamp,
                'id' => $visitId,
            ]);
            if ($update->rowCount() !== 1) {
                throw $this->failure();
            }
            return $this->readVisit($visitId) ?? throw $this->failure();
        });
    }

    /** @return list<array{start_at:string,duration_minutes:int}> */
    private function validateSlots(array $slots, int $now): array
    {
        if ($now <= 0 || !array_is_list($slots) || count($slots) < 1 || count($slots) > 10) {
            throw $this->failure();
        }
        $normalized = [];
        $previous = null;
        foreach ($slots as $slot) {
            if (!is_array($slot) || array_keys($slot) !== ['start_at', 'duration_minutes']
                || !is_string($slot['start_at'] ?? null) || !is_int($slot['duration_minutes'] ?? null)
            ) {
                throw $this->failure();
            }
            $startAt = $this->validateFutureDate($slot['start_at'], $now);
            $this->validateDuration($slot['duration_minutes']);
            if ($previous !== null && strcmp($previous, $startAt) >= 0) {
                throw $this->failure();
            }
            $previous = $startAt;
            $normalized[] = ['start_at' => $startAt, 'duration_minutes' => $slot['duration_minutes']];
        }
        return $normalized;
    }

    private function validateFutureDate(string $value, int $now): string
    {
        if ($now <= 0) {
            throw $this->failure();
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d H:i:s') !== $value || $date->getTimestamp() <= $now
        ) {
            throw $this->failure();
        }
        return $date->format('Y-m-d H:i:s');
    }

    private function validateDuration(int $duration): void
    {
        if ($duration < 1 || $duration > 65535) {
            throw $this->failure();
        }
    }

    private function effectiveNow(int $providedNow): int
    {
        return max($providedNow, time());
    }

    /** @return array{0:?string,1:?string} */
    private function validateTechnician(?string $name, ?string $contact): array
    {
        $name = $name === null ? null : trim($name);
        $contact = $contact === null ? null : trim($contact);
        $name = $name === '' ? null : $name;
        $contact = $contact === '' ? null : $contact;
        if (($name !== null && ($name === '' || mb_strlen($name, 'UTF-8') > 120
                || preg_match('/[\x00-\x1F\x7F]/', $name) === 1))
            || ($contact !== null && ($contact === '' || mb_strlen($contact, 'UTF-8') > 200
                || preg_match('/[\x00-\x1F\x7F]/', $contact) === 1))
        ) {
            throw $this->failure();
        }
        return [$name, $contact];
    }

    private function lockActiveRequest(int $requestId): void
    {
        $statement = $this->pdo->prepare(
            'SELECT status FROM maintenance_requests WHERE id = :id LIMIT 1 FOR UPDATE',
        );
        $statement->execute(['id' => $requestId]);
        $status = $statement->fetchColumn();
        if (!is_string($status) || !in_array($status, self::ACTIVE_REQUEST_STATUSES, true)) {
            throw $this->failure();
        }
    }

    /** @return array<string,mixed> */
    private function lockVisit(int $visitId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM maintenance_visits WHERE id = :id LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $visitId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw $this->failure();
        }
        return $this->normalizeRow($row);
    }

    /** @return array<string,mixed>|null */
    private function readVisit(int $visitId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM maintenance_visits WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $visitId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        try {
            $slots = json_decode((string) $row['proposed_slots_json'], true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw $this->failure();
        }
        if (!is_array($slots) || !array_is_list($slots)) {
            throw $this->failure();
        }
        return [
            'id' => (int) $row['id'],
            'maintenance_request_id' => (int) $row['maintenance_request_id'],
            'proposed_slots' => $slots,
            'selected_start_at' => $row['selected_start_at'] === null ? null : (string) $row['selected_start_at'],
            'duration_minutes' => $row['duration_minutes'] === null ? null : (int) $row['duration_minutes'],
            'technician_name' => $row['technician_name'] === null ? null : (string) $row['technician_name'],
            'technician_contact' => $row['technician_contact'] === null ? null : (string) $row['technician_contact'],
            'status' => in_array((string) $row['status'], self::VISIT_STATUSES, true)
                ? (string) $row['status'] : throw $this->failure(),
            'result_notes' => $row['result_notes'] === null ? null : (string) $row['result_notes'],
            'created_by_user_id' => $row['created_by_user_id'] === null ? null : (int) $row['created_by_user_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
            'completed_by_user_id' => $row['completed_by_user_id'] === null ? null : (int) $row['completed_by_user_id'],
            'completed_at' => $row['completed_at'] === null ? null : (string) $row['completed_at'],
            'cancelled_by_user_id' => $row['cancelled_by_user_id'] === null ? null : (int) $row['cancelled_by_user_id'],
            'cancelled_at' => $row['cancelled_at'] === null ? null : (string) $row['cancelled_at'],
            'cancel_reason' => $row['cancel_reason'] === null ? null : (string) $row['cancel_reason'],
        ];
    }

    /** @param list<array{start_at:string,duration_minutes:int}> $slots */
    private function encodeSlots(array $slots): string
    {
        try {
            return json_encode($slots, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (Throwable) {
            throw $this->failure();
        }
    }

    private function transaction(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            throw $this->failure();
        }
        try {
            if (!$this->pdo->beginTransaction()) {
                throw $this->failure();
            }
            $result = $operation();
            if (!$this->pdo->commit()) {
                throw $this->failure();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            if ($exception instanceof PDOException) {
                throw $this->failure();
            }
            throw $this->failure();
        }
    }

    private function failure(): RuntimeException
    {
        return new RuntimeException('No fue posible actualizar la visita.');
    }
}
