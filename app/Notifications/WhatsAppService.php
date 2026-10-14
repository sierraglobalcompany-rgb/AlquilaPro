<?php

declare(strict_types=1);

namespace AlquilaPro\Notifications;

use PDO;
use PDOException;
use RuntimeException;

final readonly class WhatsAppService
{
    private const TEMPLATES = [
        'candidate_follow_up' => [
            'context' => 'candidate',
            'variables' => ['name'],
            'message' => 'Hola {name}, te escribimos sobre tu solicitud de arrendamiento en AlquilaPro.',
        ],
        'lease_contact' => [
            'context' => 'lease',
            'variables' => ['name'],
            'message' => 'Hola {name}, nos comunicamos contigo sobre tu arrendamiento en AlquilaPro.',
        ],
        'maintenance_follow_up' => [
            'context' => 'maintenance_request',
            'variables' => ['name', 'category'],
            'message' => 'Hola {name}, te contactamos sobre la solicitud de mantenimiento de {category}.',
        ],
    ];
    private const MAX_LIST = 100;

    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,mixed> $variables
     *  @return array<string,mixed>
     */
    public function prepare(
        string $contextType,
        int $contextId,
        string $recipientPhone,
        string $templateKey,
        array $variables,
        int $actorUserId,
        int $now,
    ): array {
        if (!in_array($contextType, ['candidate', 'lease', 'maintenance_request'], true)
            || $contextId <= 0
            || $actorUserId <= 0
            || $now < 0
        ) {
            throw $this->failure();
        }

        $template = self::TEMPLATES[$templateKey] ?? null;
        if (!is_array($template) || $template['context'] !== $contextType) {
            throw $this->failure();
        }

        $expectedKeys = $template['variables'];
        $actualKeys = array_keys($variables);
        sort($expectedKeys);
        sort($actualKeys);
        if ($actualKeys !== $expectedKeys) {
            throw $this->failure();
        }

        $replacements = [];
        foreach ($template['variables'] as $key) {
            $value = $variables[$key] ?? null;
            if (!is_string($value)) {
                throw $this->failure();
            }
            $value = trim($value);
            if ($value === '' || mb_strlen($value, 'UTF-8') > 190 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw $this->failure();
            }
            $replacements['{' . $key . '}'] = $value;
        }

        $phone = $this->normalizePhone($recipientPhone);
        $message = strtr($template['message'], $replacements);
        $url = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
        $createdAt = gmdate('Y-m-d H:i:s', $now);

        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO whatsapp_actions '
                . '(context_type, context_id, recipient_phone_snapshot, template_key, message_snapshot, url_snapshot, '
                . 'created_by_user_id, created_at) '
                . 'VALUES (:context_type, :context_id, :phone, :template_key, :message, :url, :actor_id, :created_at)',
            );
            $insert->execute([
                'context_type' => $contextType,
                'context_id' => $contextId,
                'phone' => $phone,
                'template_key' => $templateKey,
                'message' => $message,
                'url' => $url,
                'actor_id' => $actorUserId,
                'created_at' => $createdAt,
            ]);
        } catch (PDOException) {
            throw $this->failure();
        }

        $id = (int) $this->pdo->lastInsertId();
        return $this->find($id) ?? throw $this->failure();
    }

    /** @return array<string,mixed>|null */
    public function find(int $actionId): ?array
    {
        if ($actionId <= 0) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT * FROM whatsapp_actions WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $actionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /** @return array<string,mixed> */
    public function markOpened(int $actionId, int $actorUserId, int $now): array
    {
        if ($actionId <= 0 || $actorUserId <= 0 || $now < 0) {
            throw $this->failure();
        }
        try {
            $statement = $this->pdo->prepare(
                'UPDATE whatsapp_actions SET opened_by_user_id = :actor_id, opened_at = :opened_at '
                . 'WHERE id = :id AND opened_at IS NULL',
            );
            $statement->execute([
                'actor_id' => $actorUserId,
                'opened_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $actionId,
            ]);
        } catch (PDOException) {
            throw $this->failure();
        }

        return $this->find($actionId) ?? throw $this->failure();
    }

    /** @return list<array<string,mixed>> */
    public function listRecent(int $limit = 100): array
    {
        if ($limit < 1 || $limit > self::MAX_LIST) {
            throw $this->failure();
        }
        $statement = $this->pdo->prepare(
            'SELECT * FROM whatsapp_actions ORDER BY created_at DESC, id DESC LIMIT :row_limit',
        );
        $statement->bindValue(':row_limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '' || preg_match('/\A\+?[0-9 ()-]+\z/D', $phone) !== 1) {
            throw $this->failure();
        }
        $hasInternationalPrefix = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone);
        if (!is_string($digits)) {
            throw $this->failure();
        }
        if (!$hasInternationalPrefix && preg_match('/\A3[0-9]{9}\z/D', $digits) === 1) {
            $digits = '57' . $digits;
        }
        if (strlen($digits) < 8 || strlen($digits) > 15 || $digits[0] === '0') {
            throw $this->failure();
        }
        return $digits;
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private function normalizeRow(array $row): array
    {
        foreach (['id', 'context_id', 'created_by_user_id', 'opened_by_user_id'] as $field) {
            $row[$field] = $row[$field] === null ? null : (int) $row[$field];
        }
        foreach ([
            'context_type', 'recipient_phone_snapshot', 'template_key', 'message_snapshot', 'url_snapshot',
            'created_at', 'opened_at',
        ] as $field) {
            $row[$field] = $row[$field] === null ? null : (string) $row[$field];
        }
        return $row;
    }

    private function failure(): RuntimeException
    {
        return new RuntimeException('No fue posible preparar la acción de WhatsApp.');
    }
}
