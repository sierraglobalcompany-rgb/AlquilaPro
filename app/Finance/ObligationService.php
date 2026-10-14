<?php

declare(strict_types=1);

namespace AlquilaPro\Finance;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

final readonly class ObligationService
{
    private const TYPES = ['utility', 'administration_ph', 'fine_ph'];
    private const MAX_SUPPORT_BYTES = 5 * 1024 * 1024;
    private const SUPPORT_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    private const SUPPORT_FIELDS = [
        'source' => [
            'stored_name' => 'source_support_stored_name',
            'mime_type' => 'source_support_mime_type',
            'sha256' => 'source_support_sha256',
            'size_bytes' => 'source_support_size_bytes',
        ],
        'settlement' => [
            'stored_name' => 'settlement_support_stored_name',
            'mime_type' => 'settlement_support_mime_type',
            'sha256' => 'settlement_support_sha256',
            'size_bytes' => 'settlement_support_size_bytes',
        ],
    ];

    public function __construct(
        private PDO $pdo,
        private FinanceService $financeService,
        private string $supportDirectory,
    ) {
    }

    public function create(
        int $leaseId,
        array $input,
        ?UploadedFileInterface $sourceSupport,
        int $actorUserId,
        int $now,
    ): array {
        if ($leaseId <= 0 || $actorUserId <= 0) {
            throw new RuntimeException('No fue posible registrar la obligación.');
        }

        $data = $this->normalizeInput($input);
        $stored = null;
        try {
            $this->pdo->beginTransaction();
            $lease = $this->pdo->prepare('SELECT id FROM leases WHERE id = :id LIMIT 1');
            $lease->execute(['id' => $leaseId]);
            if ($lease->fetchColumn() === false) {
                throw new RuntimeException('No fue posible registrar la obligación.');
            }

            $metadata = $this->emptySupportMetadata('source');
            if ($sourceSupport !== null) {
                $stored = $this->storeSupport($this->validatedUpload($sourceSupport));
                $metadata = $stored['metadata'];
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO lease_obligations '
                . '(lease_id, type, service_name, description, period_month, issued_on, due_on, amount, external_reference, '
                . 'source_support_stored_name, source_support_mime_type, source_support_sha256, source_support_size_bytes, '
                . 'created_by_user_id, created_at) '
                . 'VALUES (:lease_id, :type, :service_name, :description, :period_month, :issued_on, :due_on, :amount, :external_reference, '
                . ':source_name, :source_mime, :source_sha256, :source_size, :actor_id, :created_at)',
            );
            $insert->execute($data + [
                'lease_id' => $leaseId,
                'source_name' => $metadata['source_support_stored_name'],
                'source_mime' => $metadata['source_support_mime_type'],
                'source_sha256' => $metadata['source_support_sha256'],
                'source_size' => $metadata['source_support_size_bytes'],
                'actor_id' => $actorUserId,
                'created_at' => gmdate('Y-m-d H:i:s', $now),
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if (is_array($stored)) {
                $this->removeStoredFile((string) $stored['stored_name']);
            }
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible registrar la obligación.', 0, $exception);
        }

        $result = $this->find($id, gmdate('Y-m-d', $now));
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar la obligación.');
        }

        return $result;
    }

    public function update(
        int $obligationId,
        array $input,
        ?UploadedFileInterface $newSourceSupport,
        bool $removeSourceSupport,
        int $now,
    ): array {
        if ($obligationId <= 0 || ($newSourceSupport !== null && $removeSourceSupport)) {
            throw new RuntimeException('No fue posible actualizar la obligación.');
        }

        $stored = null;
        $oldStoredName = null;
        try {
            $this->pdo->beginTransaction();
            $row = $this->lockObligation($obligationId);
            if (!is_array($row)) {
                throw new RuntimeException('La obligación no existe.');
            }
            $this->requireMutable($row);
            $data = $this->normalizeInput(array_replace($this->inputFromRow($row), $input));
            $oldStoredName = $row['source_support_stored_name'] === null
                ? null
                : (string) $row['source_support_stored_name'];
            $metadata = $this->supportMetadataFromRow($row, 'source');
            if ($newSourceSupport !== null) {
                $stored = $this->storeSupport($this->validatedUpload($newSourceSupport));
                $metadata = $stored['metadata'];
            } elseif ($removeSourceSupport) {
                $metadata = $this->emptySupportMetadata('source');
            }

            $update = $this->pdo->prepare(
                'UPDATE lease_obligations SET type = :type, service_name = :service_name, description = :description, '
                . 'period_month = :period_month, issued_on = :issued_on, due_on = :due_on, amount = :amount, '
                . 'external_reference = :external_reference, source_support_stored_name = :source_name, '
                . 'source_support_mime_type = :source_mime, source_support_sha256 = :source_sha256, '
                . 'source_support_size_bytes = :source_size, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute($data + [
                'source_name' => $metadata['source_support_stored_name'],
                'source_mime' => $metadata['source_support_mime_type'],
                'source_sha256' => $metadata['source_support_sha256'],
                'source_size' => $metadata['source_support_size_bytes'],
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $obligationId,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if (is_array($stored)) {
                $this->removeStoredFile((string) $stored['stored_name']);
            }
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible actualizar la obligación.', 0, $exception);
        }

        $newStoredName = is_array($stored) ? (string) $stored['stored_name'] : null;
        if (($removeSourceSupport || $newStoredName !== null)
            && $oldStoredName !== null
            && $oldStoredName !== $newStoredName
        ) {
            $this->removeStoredFile($oldStoredName);
        }

        $result = $this->find($obligationId, gmdate('Y-m-d', $now));
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar la obligación.');
        }

        return $result;
    }

    public function find(int $obligationId, string $today): ?array
    {
        $this->parseDate($today);
        if ($obligationId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT * FROM lease_obligations WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $obligationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeRow($row, $today) : null;
    }

    /** @return list<array<string,mixed>> */
    public function listForLease(int $leaseId, string $today): array
    {
        $this->parseDate($today);
        if ($leaseId <= 0) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM lease_obligations WHERE lease_id = :lease_id '
            . 'ORDER BY due_on IS NULL ASC, due_on ASC, id ASC',
        );
        $statement->execute(['lease_id' => $leaseId]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row, $today),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @return list<array<string,mixed>> */
    public function listAll(string $today): array
    {
        $this->parseDate($today);
        $statement = $this->pdo->query(
            'SELECT o.*, l.contract_id, l.ended_at AS lease_ended_at, tenant.full_name AS tenant_name, '
            . 'property.name AS property_name, property.address AS property_address, property.city AS property_city, '
            . 'unit.code AS unit_code '
            . 'FROM lease_obligations o INNER JOIN leases l ON l.id = o.lease_id '
            . 'INNER JOIN users tenant ON tenant.id = l.user_id '
            . 'INNER JOIN contracts c ON c.id = l.contract_id '
            . 'INNER JOIN units unit ON unit.id = c.unit_id '
            . 'INNER JOIN properties property ON property.id = unit.property_id '
            . 'ORDER BY o.due_on IS NULL ASC, o.due_on ASC, o.id ASC',
        );

        return array_map(
            fn (array $row): array => $this->normalizeRow($row, $today),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @return list<array{lease_id:int,tenant_name:string,property_name:string,unit_code:string,is_active:bool}> */
    public function listLeaseOptions(): array
    {
        $statement = $this->pdo->query(
            'SELECT l.id AS lease_id, tenant.full_name AS tenant_name, property.name AS property_name, '
            . 'unit.code AS unit_code, l.ended_at '
            . 'FROM leases l INNER JOIN users tenant ON tenant.id = l.user_id '
            . 'INNER JOIN contracts c ON c.id = l.contract_id '
            . 'INNER JOIN units unit ON unit.id = c.unit_id '
            . 'INNER JOIN properties property ON property.id = unit.property_id '
            . 'ORDER BY l.ended_at IS NOT NULL ASC, property.name ASC, unit.code ASC, tenant.full_name ASC, l.id ASC',
        );
        $options = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $options[] = [
                'lease_id' => (int) $row['lease_id'],
                'tenant_name' => (string) $row['tenant_name'],
                'property_name' => (string) $row['property_name'],
                'unit_code' => (string) $row['unit_code'],
                'is_active' => $row['ended_at'] === null,
            ];
        }

        return $options;
    }

    public function privateSourceSupport(int $obligationId): ?array
    {
        return $this->privateSupport($obligationId, 'source');
    }

    public function privateSettlementSupport(int $obligationId): ?array
    {
        return $this->privateSupport($obligationId, 'settlement');
    }

    public function settleExternal(
        int $obligationId,
        int $actorUserId,
        ?UploadedFileInterface $settlementSupport,
        int $now,
    ): array {
        if ($obligationId <= 0 || $actorUserId <= 0) {
            throw new RuntimeException('No fue posible registrar la satisfacción externa.');
        }

        $stored = null;
        try {
            $this->pdo->beginTransaction();
            $row = $this->lockObligation($obligationId);
            if (!is_array($row)) {
                throw new RuntimeException('La obligación no existe.');
            }
            if ($row['voided_at'] !== null || $row['settled_at'] !== null) {
                throw new RuntimeException('La obligación ya no admite cambios.');
            }

            $metadata = $this->emptySupportMetadata('settlement');
            if ($settlementSupport !== null) {
                $stored = $this->storeSupport($this->validatedUpload($settlementSupport));
                $metadata = $stored['metadata'];
                $metadata = $this->renameSupportMetadata($metadata, 'source', 'settlement');
            }

            $update = $this->pdo->prepare(
                'UPDATE lease_obligations SET settled_by_user_id = :actor_id, settled_at = :settled_at, '
                . 'settlement_support_stored_name = :stored_name, settlement_support_mime_type = :mime_type, '
                . 'settlement_support_sha256 = :sha256, settlement_support_size_bytes = :size_bytes '
                . 'WHERE id = :id AND settled_at IS NULL AND voided_at IS NULL',
            );
            $update->execute([
                'actor_id' => $actorUserId,
                'settled_at' => gmdate('Y-m-d H:i:s', $now),
                'stored_name' => $metadata['settlement_support_stored_name'],
                'mime_type' => $metadata['settlement_support_mime_type'],
                'sha256' => $metadata['settlement_support_sha256'],
                'size_bytes' => $metadata['settlement_support_size_bytes'],
                'id' => $obligationId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('La obligación ya no admite cambios.');
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if (is_array($stored)) {
                $this->removeStoredFile((string) $stored['stored_name']);
            }
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible registrar la satisfacción externa.', 0, $exception);
        }

        $result = $this->find($obligationId, gmdate('Y-m-d', $now));
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar la satisfacción externa.');
        }

        return $result;
    }

    public function void(int $obligationId, int $actorUserId, string $reason, int $now): array
    {
        $reason = trim($reason);
        if ($obligationId <= 0 || $actorUserId <= 0 || $reason === '' || mb_strlen($reason, 'UTF-8') > 500) {
            throw new RuntimeException('No fue posible anular la obligación.');
        }

        try {
            $this->pdo->beginTransaction();
            $row = $this->lockObligation($obligationId);
            if (!is_array($row)) {
                throw new RuntimeException('La obligación no existe.');
            }
            if ($row['voided_at'] !== null) {
                throw new RuntimeException('La obligación ya fue anulada.');
            }
            if ($row['charge_id'] !== null) {
                $charge = $this->pdo->prepare('SELECT voided_at FROM charges WHERE id = :id FOR UPDATE');
                $charge->execute(['id' => (int) $row['charge_id']]);
                $chargeVoidedAt = $charge->fetchColumn();
                if ($chargeVoidedAt === false || $chargeVoidedAt === null) {
                    throw new RuntimeException('Anule primero el cargo financiero vinculado.');
                }
            }

            $update = $this->pdo->prepare(
                'UPDATE lease_obligations SET voided_by_user_id = :actor_id, voided_at = :voided_at, void_reason = :reason '
                . 'WHERE id = :id AND voided_at IS NULL',
            );
            $update->execute([
                'actor_id' => $actorUserId,
                'voided_at' => gmdate('Y-m-d H:i:s', $now),
                'reason' => $reason,
                'id' => $obligationId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('La obligación ya fue anulada.');
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible anular la obligación.', 0, $exception);
        }

        $result = $this->find($obligationId, gmdate('Y-m-d', $now));
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar la anulación.');
        }

        return $result;
    }

    public function generateCharge(int $obligationId, string $chargeDueDate, int $actorUserId): array
    {
        if ($obligationId <= 0 || $actorUserId <= 0) {
            throw new RuntimeException('No fue posible generar el cargo financiero.');
        }
        $this->parseDate($chargeDueDate);

        try {
            $this->pdo->beginTransaction();
            $row = $this->lockObligation($obligationId);
            if (!is_array($row)
                || !in_array((string) $row['type'], ['utility', 'fine_ph'], true)
                || $row['voided_at'] !== null
                || $row['charge_id'] !== null
            ) {
                throw new RuntimeException('La obligación no admite generar otro cargo.');
            }

            $description = $this->chargeDescription((string) $row['description']);
            $type = $row['type'] === 'utility' ? 'service' : 'fine';
            $charge = $this->financeService->createManualCharge(
                (int) $row['lease_id'],
                $type,
                (string) $row['amount'],
                $chargeDueDate,
                $description,
                $row['period_month'] === null ? null : (string) $row['period_month'],
                $actorUserId,
            );

            $link = $this->pdo->prepare(
                'UPDATE lease_obligations SET charge_id = :charge_id, updated_at = CURRENT_TIMESTAMP '
                . 'WHERE id = :id AND charge_id IS NULL AND voided_at IS NULL',
            );
            $link->execute(['charge_id' => (int) $charge['id'], 'id' => $obligationId]);
            if ($link->rowCount() !== 1) {
                throw new RuntimeException('No fue posible vincular el cargo a la obligación.');
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible generar el cargo financiero.', 0, $exception);
        }

        $result = $this->find($obligationId, $this->todayInBogota());
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar el cargo financiero.');
        }

        return $result;
    }

    public function linkAdministrationCharge(int $obligationId, int $chargeId, int $actorUserId): array
    {
        if ($obligationId <= 0 || $chargeId <= 0 || $actorUserId <= 0) {
            throw new RuntimeException('No fue posible vincular el cargo de administración.');
        }

        try {
            $this->pdo->beginTransaction();
            $row = $this->lockObligation($obligationId);
            if (!is_array($row)
                || $row['type'] !== 'administration_ph'
                || $row['voided_at'] !== null
                || $row['charge_id'] !== null
            ) {
                throw new RuntimeException('La obligación no admite vincular ese cargo.');
            }

            $chargeStatement = $this->pdo->prepare(
                'SELECT id, lease_id, type, period_month, voided_at FROM charges WHERE id = :id FOR UPDATE',
            );
            $chargeStatement->execute(['id' => $chargeId]);
            $charge = $chargeStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($charge)
                || (int) $charge['lease_id'] !== (int) $row['lease_id']
                || $charge['type'] !== 'administration'
                || $charge['voided_at'] !== null
                || $charge['period_month'] === null
                || (string) $charge['period_month'] !== (string) $row['period_month']
            ) {
                throw new RuntimeException('El cargo no corresponde a esta obligación de administración.');
            }

            $link = $this->pdo->prepare(
                'UPDATE lease_obligations SET charge_id = :charge_id, updated_at = CURRENT_TIMESTAMP '
                . 'WHERE id = :id AND charge_id IS NULL AND voided_at IS NULL',
            );
            $link->execute(['charge_id' => $chargeId, 'id' => $obligationId]);
            if ($link->rowCount() !== 1) {
                throw new RuntimeException('No fue posible vincular el cargo a la obligación.');
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible vincular el cargo de administración.', 0, $exception);
        }

        $result = $this->find($obligationId, $this->todayInBogota());
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar el vínculo del cargo.');
        }

        return $result;
    }

    private function chargeDescription(string $description): string
    {
        $visible = strip_tags($description);
        $visible = html_entity_decode($visible, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $visible = strip_tags($visible);
        $visible = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $visible) ?? '';
        $visible = preg_replace('/\s+/u', ' ', trim($visible)) ?? '';
        if ($visible === '' || mb_strlen($visible, 'UTF-8') > 500) {
            throw new RuntimeException('La descripción no puede usarse para generar un cargo.');
        }

        return $visible;
    }

    private function todayInBogota(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
    }

    private function normalizeInput(array $input): array
    {
        $allowed = ['type', 'service_name', 'description', 'period_month', 'issued_on', 'due_on', 'amount', 'external_reference'];
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }

        $type = $input['type'] ?? null;
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }
        $serviceName = $this->optionalText($input, 'service_name', 120);
        if (($type === 'utility' && $serviceName === null)
            || ($type !== 'utility' && $serviceName !== null)
        ) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }

        $description = $this->requiredText($input, 'description', 500);
        $amount = $this->normalizeAmount($input['amount'] ?? null);
        $periodMonth = $this->optionalDate($input, 'period_month', true);
        $issuedOn = $this->optionalDate($input, 'issued_on');
        $dueOn = $this->optionalDate($input, 'due_on');
        if ($type === 'administration_ph' && $periodMonth === null) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }
        if ($issuedOn !== null && $dueOn !== null && $issuedOn > $dueOn) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }

        return [
            'type' => $type,
            'service_name' => $serviceName,
            'description' => $description,
            'period_month' => $periodMonth,
            'issued_on' => $issuedOn,
            'due_on' => $dueOn,
            'amount' => $amount,
            'external_reference' => $this->optionalText($input, 'external_reference', 190),
        ];
    }

    private function normalizeAmount(mixed $value): string
    {
        if (!is_string($value)) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }
        $amount = trim($value);
        if (preg_match('/\A\d+(?:\.\d{1,2})?\z/', $amount) !== 1) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 10) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }
        $normalized = $whole . '.' . str_pad($fraction, 2, '0');
        if ($normalized === '0.00') {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }

        return $normalized;
    }

    private function optionalDate(array $input, string $field, bool $period = false): ?string
    {
        $value = $input[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (!is_string($value)) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }
        $value = trim($value);
        if ($period && preg_match('/\A\d{4}-\d{2}-01\z/', $value) !== 1) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }
        $this->parseDate($value);

        return $value;
    }

    private function parseDate(string $date): DateTimeImmutable
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
            throw new RuntimeException('No fue posible validar la fecha.');
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date
            || (int) $parsed->format('Y') < 1000
        ) {
            throw new RuntimeException('No fue posible validar la fecha.');
        }

        return $parsed;
    }

    private function requiredText(array $input, string $field, int $maximumLength): string
    {
        $value = $this->optionalText($input, $field, $maximumLength);
        if ($value === null) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }

        return $value;
    }

    private function optionalText(array $input, string $field, int $maximumLength): ?string
    {
        if (!array_key_exists($field, $input) || $input[$field] === null) {
            return null;
        }
        if (!is_string($input[$field])) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }
        $value = trim($input[$field]);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value, 'UTF-8') > $maximumLength) {
            throw new RuntimeException('No fue posible guardar la obligación.');
        }

        return $value;
    }

    private function normalizeRow(array $row, string $today): array
    {
        $voided = $row['voided_at'] !== null;
        $settled = $row['settled_at'] !== null;
        $overdue = !$voided
            && !$settled
            && $row['due_on'] !== null
            && (string) $row['due_on'] < $today;
        $row['id'] = (int) $row['id'];
        $row['lease_id'] = (int) $row['lease_id'];
        $row['amount'] = (string) $row['amount'];
        $row['charge_id'] = $row['charge_id'] === null ? null : (int) $row['charge_id'];
        $row['created_by_user_id'] = $row['created_by_user_id'] === null ? null : (int) $row['created_by_user_id'];
        $row['settled_by_user_id'] = $row['settled_by_user_id'] === null ? null : (int) $row['settled_by_user_id'];
        $row['voided_by_user_id'] = $row['voided_by_user_id'] === null ? null : (int) $row['voided_by_user_id'];
        foreach (['source_support_size_bytes', 'settlement_support_size_bytes'] as $field) {
            $row[$field] = $row[$field] === null ? null : (int) $row[$field];
        }
        $row['is_charged'] = $row['charge_id'] !== null;
        $row['external_overdue'] = $overdue;
        $row['external_state'] = match (true) {
            $voided => 'voided',
            $settled => 'settled_external',
            $overdue => 'overdue_external',
            default => 'open',
        };
        $row['has_source_support'] = $row['source_support_stored_name'] !== null;
        $row['has_settlement_support'] = $row['settlement_support_stored_name'] !== null;

        return $row;
    }

    private function inputFromRow(array $row): array
    {
        return [
            'type' => (string) $row['type'],
            'service_name' => $row['service_name'] === null ? null : (string) $row['service_name'],
            'description' => (string) $row['description'],
            'period_month' => $row['period_month'] === null ? null : (string) $row['period_month'],
            'issued_on' => $row['issued_on'] === null ? null : (string) $row['issued_on'],
            'due_on' => $row['due_on'] === null ? null : (string) $row['due_on'],
            'amount' => (string) $row['amount'],
            'external_reference' => $row['external_reference'] === null ? null : (string) $row['external_reference'],
        ];
    }

    private function requireMutable(array $row): void
    {
        if ($row['voided_at'] !== null || $row['charge_id'] !== null || $row['settled_at'] !== null) {
            throw new RuntimeException('La obligación ya no admite cambios.');
        }
    }

    private function lockObligation(int $obligationId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM lease_obligations WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $obligationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function privateSupport(int $obligationId, string $kind): ?array
    {
        if ($obligationId <= 0 || !isset(self::SUPPORT_FIELDS[$kind])) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT * FROM lease_obligations WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $obligationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $fields = self::SUPPORT_FIELDS[$kind];
        $storedName = $row[$fields['stored_name']];
        $mimeType = $row[$fields['mime_type']];
        $sha256 = $row[$fields['sha256']];
        $sizeBytes = $row[$fields['size_bytes']];
        if ($storedName === null && $mimeType === null && $sha256 === null && $sizeBytes === null) {
            return null;
        }
        if (!is_string($storedName) || !is_string($mimeType) || !is_string($sha256) || $sizeBytes === null) {
            return null;
        }

        $path = $this->supportPath($storedName, true);
        if ($path === null) {
            return null;
        }
        clearstatcache(true, $path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $size = @filesize($path);
        $hash = @hash_file('sha256', $path);
        if (!is_string($mime)
            || !isset(self::SUPPORT_EXTENSIONS[$mime])
            || $mime !== $mimeType
            || pathinfo($storedName, PATHINFO_EXTENSION) !== self::SUPPORT_EXTENSIONS[$mime]
            || !is_int($size)
            || $size < 1
            || $size > self::MAX_SUPPORT_BYTES
            || $size !== (int) $sizeBytes
            || !is_string($hash)
            || preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1
            || !hash_equals($sha256, $hash)
        ) {
            return null;
        }

        return ['path' => $path, 'mime' => $mime, 'size_bytes' => $size];
    }

    private function validatedUpload(UploadedFileInterface $upload): array
    {
        try {
            if ($upload->getError() !== UPLOAD_ERR_OK) {
                throw new RuntimeException('No fue posible recibir el soporte.');
            }
            $stream = $upload->getStream();
            $reportedSize = $stream->getSize();
            if (!is_int($reportedSize) || $reportedSize < 1 || $reportedSize > self::MAX_SUPPORT_BYTES) {
                throw new RuntimeException('No fue posible recibir el soporte.');
            }
            $stream->rewind();
            $bytes = $stream->getContents();
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible recibir el soporte.', 0, $exception);
        }

        $size = strlen($bytes);
        if ($size !== $reportedSize || $size < 1 || $size > self::MAX_SUPPORT_BYTES) {
            throw new RuntimeException('No fue posible validar el soporte completo.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!is_string($mime) || !isset(self::SUPPORT_EXTENSIONS[$mime])) {
            throw new RuntimeException('El formato del soporte no es válido.');
        }

        return ['bytes' => $bytes, 'mime' => $mime, 'size_bytes' => $size, 'sha256' => hash('sha256', $bytes)];
    }

    private function storeSupport(array $validated): array
    {
        $this->ensureSupportDirectory();
        $extension = self::SUPPORT_EXTENSIONS[$validated['mime']];
        $storedName = 'obligation-' . bin2hex(random_bytes(16)) . '.' . $extension;
        $path = $this->supportPath($storedName, false);
        if ($path === null) {
            throw new RuntimeException('No fue posible guardar el soporte.');
        }

        try {
            $written = @file_put_contents($path, $validated['bytes'], LOCK_EX);
            @chmod($path, 0600);
            clearstatcache(true, $path);
            $size = @filesize($path);
            $mime = is_file($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
            $hash = @hash_file('sha256', $path);
            if ($written !== $validated['size_bytes']
                || !is_int($size)
                || $size !== $validated['size_bytes']
                || $mime !== $validated['mime']
                || !is_string($hash)
                || !hash_equals($validated['sha256'], $hash)
            ) {
                throw new RuntimeException('No fue posible verificar el soporte guardado.');
            }
        } catch (Throwable $exception) {
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible guardar el soporte.', 0, $exception);
        }

        return [
            'stored_name' => $storedName,
            'metadata' => [
                'source_support_stored_name' => $storedName,
                'source_support_mime_type' => $validated['mime'],
                'source_support_sha256' => $validated['sha256'],
                'source_support_size_bytes' => $validated['size_bytes'],
            ],
        ];
    }

    private function ensureSupportDirectory(): void
    {
        if (!is_dir($this->supportDirectory)
            && !@mkdir($this->supportDirectory, 0700, true)
            && !is_dir($this->supportDirectory)
        ) {
            throw new RuntimeException('No fue posible preparar el almacenamiento privado.');
        }
        if (is_link($this->supportDirectory)) {
            throw new RuntimeException('El almacenamiento privado no es seguro.');
        }
        @chmod($this->supportDirectory, 0700);
        $directory = realpath($this->supportDirectory);
        $projectRoot = realpath(dirname(__DIR__, 2));
        $webRoot = is_string($projectRoot) ? realpath($projectRoot . DIRECTORY_SEPARATOR . 'public') : false;
        if (!is_string($directory)
            || (is_string($webRoot) && $this->isWithinDirectory($directory, $webRoot))
        ) {
            throw new RuntimeException('El soporte debe almacenarse fuera del directorio público.');
        }
    }

    private function supportPath(string $storedName, bool $mustExist): ?string
    {
        if (preg_match('/\Aobligation-[a-f0-9]{32}\.(?:png|jpg|webp|pdf)\z/', $storedName) !== 1) {
            return null;
        }
        $directory = realpath($this->supportDirectory);
        if (!is_string($directory)) {
            return null;
        }
        $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;
        if (!$mustExist) {
            return file_exists($candidate) ? null : $candidate;
        }
        if (is_link($candidate) || !is_file($candidate)) {
            return null;
        }
        $resolved = realpath($candidate);
        if (!is_string($resolved) || !$this->isWithinDirectory($resolved, $directory)) {
            return null;
        }

        return $resolved;
    }

    private function isWithinDirectory(string $path, string $directory): bool
    {
        $prefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $path = rtrim($path, DIRECTORY_SEPARATOR);

        return strcasecmp($path, rtrim($directory, DIRECTORY_SEPARATOR)) === 0
            || strncasecmp($path, $prefix, strlen($prefix)) === 0;
    }

    private function removeStoredFile(string $storedName): void
    {
        $path = $this->supportPath($storedName, true);
        if ($path !== null && !@unlink($path)) {
            error_log('Obligation support cleanup failed.');
        }
    }

    private function emptySupportMetadata(string $kind): array
    {
        $prefix = $kind === 'source' ? 'source' : 'settlement';

        return [
            $prefix . '_support_stored_name' => null,
            $prefix . '_support_mime_type' => null,
            $prefix . '_support_sha256' => null,
            $prefix . '_support_size_bytes' => null,
        ];
    }

    private function supportMetadataFromRow(array $row, string $kind): array
    {
        $result = [];
        foreach (self::SUPPORT_FIELDS[$kind] as $field) {
            $result[$field] = $row[$field];
        }

        return $result;
    }

    private function renameSupportMetadata(array $metadata, string $from, string $to): array
    {
        $result = [];
        foreach (self::SUPPORT_FIELDS[$from] as $suffix => $sourceField) {
            $result[self::SUPPORT_FIELDS[$to][$suffix]] = $metadata[$sourceField];
        }

        return $result;
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
