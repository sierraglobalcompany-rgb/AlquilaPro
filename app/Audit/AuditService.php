<?php

declare(strict_types=1);

namespace AlquilaPro\Audit;

use PDO;
use RuntimeException;
use Throwable;

final readonly class AuditService
{
    private const MAX_METADATA_BYTES = 2048;
    private const MAX_LIST_ITEMS = 20;
    private const MAX_METADATA_STRING = 512;

    public function __construct(private PDO $pdo)
    {
    }

    public function record(
        ?int $actorUserId,
        string $action,
        string $entityType,
        ?int $entityId,
        string $summary,
        ?string $remoteIp,
        array $metadata = [],
    ): void {
        if ($actorUserId !== null && $actorUserId <= 0) {
            throw $this->failure();
        }
        $action = $this->machineKey($action, 80);
        $entityType = $this->machineKey($entityType, 80);
        if ($entityId !== null && $entityId <= 0) {
            throw $this->failure();
        }
        $summary = trim($summary);
        if ($summary === '' || mb_strlen($summary, 'UTF-8') > 255
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $summary) === 1
        ) {
            throw $this->failure();
        }

        $ipAddress = null;
        if ($remoteIp !== null) {
            $candidate = trim($remoteIp);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                $ipAddress = $candidate;
            }
        }

        $metadataJson = $this->metadataJson($metadata);

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO audit_log '
                . '(actor_user_id, occurred_at, action, entity_type, entity_id, summary, ip_address, metadata_json) '
                . 'VALUES (:actor_user_id, CURRENT_TIMESTAMP, :action, :entity_type, :entity_id, :summary, :ip_address, :metadata_json)',
            );
            $statement->execute([
                'actor_user_id' => $actorUserId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'summary' => $summary,
                'ip_address' => $ipAddress,
                'metadata_json' => $metadataJson,
            ]);
            if ($statement->rowCount() !== 1) {
                throw $this->failure();
            }
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw $this->failure();
        }
    }

    /** @return list<array<string,mixed>> */
    public function listRecent(int $limit = 100): array
    {
        if ($limit < 1 || $limit > 100) {
            throw $this->failure();
        }

        try {
            $statement = $this->pdo->prepare(
                'SELECT id, actor_user_id, occurred_at, action, entity_type, entity_id, summary, ip_address, metadata_json '
                . 'FROM audit_log ORDER BY occurred_at DESC, id DESC LIMIT :row_limit',
            );
            $statement->bindValue(':row_limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $rows = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[] = $this->normalizeRow($row);
            }
            return $rows;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw $this->failure();
        }
    }

    private function machineKey(string $value, int $maxLength): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength
            || preg_match('/\A[a-z][a-z0-9_.-]*\z/D', $value) !== 1
        ) {
            throw $this->failure();
        }
        return $value;
    }

    private function metadataJson(array $metadata): ?string
    {
        if ($metadata === []) {
            return null;
        }
        if (array_is_list($metadata)) {
            throw $this->failure();
        }

        $normalized = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw $this->failure();
            }
            $key = $this->machineKey($key, 80);
            if (preg_match('/(?:password|passphrase|token|secret|cookie|session|document|receipt|sql|trace)/i', $key) === 1) {
                throw $this->failure();
            }
            $normalized[$key] = $this->metadataValue($value);
        }

        try {
            $json = json_encode(
                $normalized,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (Throwable) {
            throw $this->failure();
        }
        if (strlen($json) > self::MAX_METADATA_BYTES) {
            throw $this->failure();
        }
        return $json;
    }

    private function metadataValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            if (mb_strlen($value, 'UTF-8') > self::MAX_METADATA_STRING
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1
            ) {
                throw $this->failure();
            }
            return $value;
        }
        if (!is_array($value) || !array_is_list($value) || count($value) > self::MAX_LIST_ITEMS) {
            throw $this->failure();
        }
        $result = [];
        foreach ($value as $item) {
            if (is_array($item) || is_object($item) || is_resource($item)) {
                throw $this->failure();
            }
            $result[] = $this->metadataValue($item);
        }
        return $result;
    }

    private function normalizeRow(array $row): array
    {
        $metadata = [];
        if ($row['metadata_json'] !== null) {
            try {
                $decoded = json_decode((string) $row['metadata_json'], true, 32, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                throw $this->failure();
            }
            if (!is_array($decoded)) {
                throw $this->failure();
            }
            $metadata = $decoded;
        }

        return [
            'id' => (int) $row['id'],
            'actor_user_id' => $row['actor_user_id'] === null ? null : (int) $row['actor_user_id'],
            'occurred_at' => (string) $row['occurred_at'],
            'action' => (string) $row['action'],
            'entity_type' => (string) $row['entity_type'],
            'entity_id' => $row['entity_id'] === null ? null : (int) $row['entity_id'],
            'summary' => (string) $row['summary'],
            'ip_address' => $row['ip_address'] === null ? null : (string) $row['ip_address'],
            'metadata' => $metadata,
        ];
    }

    private function failure(): RuntimeException
    {
        return new RuntimeException('No fue posible registrar la auditoría.');
    }
}
