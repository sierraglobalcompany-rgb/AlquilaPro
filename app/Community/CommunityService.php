<?php

declare(strict_types=1);

namespace AlquilaPro\Community;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final readonly class CommunityService
{
    private const STATUSES = ['open', 'reviewed', 'closed', 'voided'];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function create(
        int $leaseId,
        string $category,
        string $occurredOn,
        string $description,
        int $actorUserId,
        int $now,
    ): array {
        $category = trim($category);
        $description = trim($description);
        if ($leaseId <= 0 || $actorUserId <= 0 || $now <= 0
            || mb_strlen($category, 'UTF-8') < 1 || mb_strlen($category, 'UTF-8') > 80
            || !$this->isValidDate($occurredOn)
            || $description === '' || mb_strlen($description, 'UTF-8') > 5000
        ) {
            throw new RuntimeException('No fue posible crear el incidente.');
        }

        try {
            $timestamp = gmdate('Y-m-d H:i:s', $now);
            $insert = $this->pdo->prepare(
                'INSERT INTO community_incidents '
                . '(lease_id, category, occurred_on, description, status, created_by_user_id, created_at, updated_at) '
                . 'VALUES (:lease_id, :category, :occurred_on, :description, \'open\', :actor_id, :created_at, :updated_at)',
            );
            $insert->execute([
                'lease_id' => $leaseId,
                'category' => $category,
                'occurred_on' => $occurredOn,
                'description' => $description,
                'actor_id' => $actorUserId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $incident = $this->find((int) $this->pdo->lastInsertId());
            if ($incident === null) {
                throw new RuntimeException('No fue posible crear el incidente.');
            }
            return $incident;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('No fue posible crear el incidente.');
        }
    }

    /** @return array<string, mixed>|null */
    public function find(int $incidentId): ?array
    {
        if ($incidentId <= 0) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT * FROM community_incidents WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $incidentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function listForLease(int $leaseId): array
    {
        if ($leaseId <= 0) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT * FROM community_incidents WHERE lease_id = :lease_id ORDER BY occurred_on DESC, id DESC',
        );
        $statement->execute(['lease_id' => $leaseId]);
        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @return list<array<string, mixed>> */
    public function listForStaff(?string $status = null): array
    {
        if ($status !== null && !in_array($status, self::STATUSES, true)) {
            return [];
        }
        $sql = 'SELECT * FROM community_incidents';
        if ($status !== null) {
            $sql .= ' WHERE status = :status';
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 200';
        $statement = $this->pdo->prepare($sql);
        if ($status === null) {
            $statement->execute();
        } else {
            $statement->execute(['status' => $status]);
        }
        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @return array<string, mixed> */
    public function markReviewed(int $incidentId, int $actorUserId, int $now): array
    {
        if ($incidentId <= 0 || $actorUserId <= 0 || $now <= 0) {
            throw new RuntimeException('No fue posible actualizar el incidente.');
        }
        return $this->mutateLocked($incidentId, function (array $incident) use ($now): void {
            if ($incident['status'] !== 'open') {
                throw new RuntimeException('No fue posible actualizar el incidente.');
            }
            $update = $this->pdo->prepare(
                'UPDATE community_incidents SET status = \'reviewed\', updated_at = :updated_at WHERE id = :id',
            );
            $update->execute(['updated_at' => gmdate('Y-m-d H:i:s', $now), 'id' => $incident['id']]);
        });
    }

    /** @return array<string, mixed> */
    public function close(int $incidentId, string $resolutionNotes, int $actorUserId, int $now): array
    {
        $resolutionNotes = trim($resolutionNotes);
        if ($incidentId <= 0 || $actorUserId <= 0 || $now <= 0
            || $resolutionNotes === '' || mb_strlen($resolutionNotes, 'UTF-8') > 5000
        ) {
            throw new RuntimeException('No fue posible cerrar el incidente.');
        }
        return $this->mutateLocked($incidentId, function (array $incident) use (
            $resolutionNotes,
            $actorUserId,
            $now,
        ): void {
            if (!in_array($incident['status'], ['open', 'reviewed'], true)) {
                throw new RuntimeException('No fue posible cerrar el incidente.');
            }
            $timestamp = gmdate('Y-m-d H:i:s', $now);
            $update = $this->pdo->prepare(
                'UPDATE community_incidents SET status = \'closed\', resolution_notes = :notes, '
                . 'closed_by_user_id = :actor_id, closed_at = :closed_at, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute([
                'notes' => $resolutionNotes,
                'actor_id' => $actorUserId,
                'closed_at' => $timestamp,
                'updated_at' => $timestamp,
                'id' => $incident['id'],
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function void(int $incidentId, string $reason, int $actorUserId, int $now): array
    {
        $reason = trim($reason);
        if ($incidentId <= 0 || $actorUserId <= 0 || $now <= 0
            || $reason === '' || mb_strlen($reason, 'UTF-8') > 1000
        ) {
            throw new RuntimeException('No fue posible anular el incidente.');
        }
        return $this->mutateLocked($incidentId, function (array $incident) use (
            $reason,
            $actorUserId,
            $now,
        ): void {
            if ($incident['status'] === 'voided') {
                throw new RuntimeException('No fue posible anular el incidente.');
            }
            $timestamp = gmdate('Y-m-d H:i:s', $now);
            $update = $this->pdo->prepare(
                'UPDATE community_incidents SET status = \'voided\', voided_by_user_id = :actor_id, '
                . 'voided_at = :voided_at, void_reason = :reason, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute([
                'actor_id' => $actorUserId,
                'voided_at' => $timestamp,
                'reason' => $reason,
                'updated_at' => $timestamp,
                'id' => $incident['id'],
            ]);
        });
    }

    /** @param callable(array<string, mixed>):void $operation @return array<string, mixed> */
    private function mutateLocked(int $incidentId, callable $operation): array
    {
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('No fue posible actualizar el incidente.');
        }
        try {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('No fue posible actualizar el incidente.');
            }
            $select = $this->pdo->prepare('SELECT * FROM community_incidents WHERE id = :id FOR UPDATE');
            $select->execute(['id' => $incidentId]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new RuntimeException('No fue posible actualizar el incidente.');
            }
            $operation($this->normalizeRow($row));

            $refresh = $this->pdo->prepare('SELECT * FROM community_incidents WHERE id = :id LIMIT 1');
            $refresh->execute(['id' => $incidentId]);
            $updatedRow = $refresh->fetch(PDO::FETCH_ASSOC);
            if (!is_array($updatedRow) || !$this->pdo->commit()) {
                throw new RuntimeException('No fue posible actualizar el incidente.');
            }
            return $this->normalizeRow($updatedRow);
        } catch (RuntimeException $exception) {
            $this->rollbackIfNeeded();
            throw $exception;
        } catch (Throwable) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible actualizar el incidente.');
        }
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'lease_id' => (int) $row['lease_id'],
            'category' => (string) $row['category'],
            'occurred_on' => (string) $row['occurred_on'],
            'description' => (string) $row['description'],
            'status' => (string) $row['status'],
            'resolution_notes' => $row['resolution_notes'] === null ? null : (string) $row['resolution_notes'],
            'created_by_user_id' => (int) $row['created_by_user_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
            'closed_by_user_id' => $row['closed_by_user_id'] === null ? null : (int) $row['closed_by_user_id'],
            'closed_at' => $row['closed_at'] === null ? null : (string) $row['closed_at'],
            'voided_by_user_id' => $row['voided_by_user_id'] === null ? null : (int) $row['voided_by_user_id'],
            'voided_at' => $row['voided_at'] === null ? null : (string) $row['voided_at'],
            'void_reason' => $row['void_reason'] === null ? null : (string) $row['void_reason'],
        ];
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        return $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsed->format('Y-m-d') === $date;
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
