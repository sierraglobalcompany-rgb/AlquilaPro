<?php

declare(strict_types=1);

namespace AlquilaPro\Insurance;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

final class InsuranceService
{
    private const MAX_DOCUMENT_BYTES = 5 * 1024 * 1024;
    private const DOCUMENT_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $documentDirectory,
    ) {
    }

    public function createPolicy(int $leaseId, array $input, int $actorUserId, int $now): array
    {
        if ($leaseId <= 0 || $actorUserId <= 0) {
            throw new RuntimeException('No fue posible registrar la póliza.');
        }
        $data = $this->normalizePolicyInput($input);

        try {
            $this->pdo->beginTransaction();
            $lease = $this->pdo->prepare('SELECT id FROM leases WHERE id = :id LIMIT 1');
            $lease->execute(['id' => $leaseId]);
            if ($lease->fetchColumn() === false) {
                throw new RuntimeException('El arrendamiento no existe.');
            }
            if ($data['supersedes_policy_id'] !== null) {
                $prior = $this->pdo->prepare('SELECT lease_id FROM insurance_policies WHERE id = :id LIMIT 1');
                $prior->execute(['id' => $data['supersedes_policy_id']]);
                if ((int) $prior->fetchColumn() !== $leaseId) {
                    throw new RuntimeException('La póliza anterior debe pertenecer al mismo arrendamiento.');
                }
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO insurance_policies '
                . '(lease_id, provider_name, product_name, policy_number, coverage_start, coverage_end, coverage_snapshot_json, '
                . 'documents_json, supersedes_policy_id, internal_notes, created_by_user_id, created_at) '
                . 'VALUES (:lease_id, :provider_name, :product_name, :policy_number, :coverage_start, :coverage_end, '
                . ':coverage_snapshot_json, :documents_json, :supersedes_policy_id, :internal_notes, :created_by_user_id, :created_at)',
            );
            $insertData = $data;
            unset($insertData['coverage']);
            $insert->execute($insertData + [
                'lease_id' => $leaseId,
                'coverage_snapshot_json' => json_encode($data['coverage'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'documents_json' => '[]',
                'created_by_user_id' => $actorUserId,
                'created_at' => gmdate('Y-m-d H:i:s', $now),
            ]);
            $policyId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible registrar la póliza.', 0, $exception);
        }

        $policy = $this->findPolicy($policyId, $this->localDate($now));
        if ($policy === null) {
            throw new RuntimeException('No fue posible confirmar la póliza.');
        }
        return $policy;
    }

    public function findPolicy(int $policyId, string $today): ?array
    {
        if ($policyId <= 0 || !$this->isDate($today)) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT * FROM insurance_policies WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $policyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $coverage = $this->decodeJsonList((string) $row['coverage_snapshot_json']);
        $documents = $this->decodeJsonList((string) $row['documents_json']);
        $status = 'expired';
        if ($row['cancelled_at'] !== null) {
            $status = 'cancelled';
        } elseif ($today < (string) $row['coverage_start']) {
            $status = 'upcoming';
        } elseif ($today <= (string) $row['coverage_end']) {
            $status = 'active';
        }

        return [
            'id' => (int) $row['id'],
            'lease_id' => (int) $row['lease_id'],
            'provider_name' => (string) $row['provider_name'],
            'product_name' => (string) $row['product_name'],
            'policy_number' => (string) $row['policy_number'],
            'coverage_start' => (string) $row['coverage_start'],
            'coverage_end' => (string) $row['coverage_end'],
            'coverage' => $coverage,
            'documents' => array_map($this->publicDocumentMetadata(...), $documents),
            'supersedes_policy_id' => $row['supersedes_policy_id'] === null ? null : (int) $row['supersedes_policy_id'],
            'internal_notes' => $row['internal_notes'] === null ? null : (string) $row['internal_notes'],
            'created_by_user_id' => $row['created_by_user_id'] === null ? null : (int) $row['created_by_user_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
            'cancelled_by_user_id' => $row['cancelled_by_user_id'] === null ? null : (int) $row['cancelled_by_user_id'],
            'cancelled_at' => $row['cancelled_at'] === null ? null : (string) $row['cancelled_at'],
            'cancel_reason' => $row['cancel_reason'] === null ? null : (string) $row['cancel_reason'],
            'status' => $status,
        ];
    }

    public function updatePolicyNotes(int $policyId, ?string $notes, int $now): array
    {
        if ($policyId <= 0) {
            throw new RuntimeException('No fue posible actualizar las notas.');
        }
        $normalized = $notes === null ? null : trim($notes);
        if ($normalized === '') {
            $normalized = null;
        }
        if ($normalized !== null && mb_strlen($normalized) > 1000) {
            throw new RuntimeException('Las notas internas no pueden superar 1000 caracteres.');
        }
        $update = $this->pdo->prepare(
            'UPDATE insurance_policies SET internal_notes = :notes, updated_at = :updated_at WHERE id = :id',
        );
        $update->execute([
            'notes' => $normalized,
            'updated_at' => gmdate('Y-m-d H:i:s', $now),
            'id' => $policyId,
        ]);
        $policy = $this->findPolicy($policyId, $this->localDate($now));
        if ($policy === null) {
            throw new RuntimeException('La póliza no existe.');
        }
        return $policy;
    }

    public function cancelPolicy(int $policyId, int $actorUserId, string $reason, int $now): array
    {
        $reason = trim($reason);
        if ($policyId <= 0 || $actorUserId <= 0 || $reason === '' || mb_strlen($reason) > 500) {
            throw new RuntimeException('La cancelación requiere un motivo válido.');
        }
        try {
            $this->pdo->beginTransaction();
            $policy = $this->lockPolicy($policyId);
            if (!is_array($policy)) {
                throw new RuntimeException('La póliza no existe.');
            }
            if ($policy['cancelled_at'] !== null) {
                throw new RuntimeException('La póliza ya fue cancelada.');
            }
            $update = $this->pdo->prepare(
                'UPDATE insurance_policies SET cancelled_by_user_id = :actor, cancelled_at = :cancelled_at, '
                . 'cancel_reason = :reason, updated_at = :updated_at WHERE id = :id AND cancelled_at IS NULL',
            );
            $timestamp = gmdate('Y-m-d H:i:s', $now);
            $update->execute([
                'actor' => $actorUserId,
                'cancelled_at' => $timestamp,
                'reason' => $reason,
                'updated_at' => $timestamp,
                'id' => $policyId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('La póliza ya fue cancelada.');
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible cancelar la póliza.', 0, $exception);
        }

        $result = $this->findPolicy($policyId, $this->localDate($now));
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar la cancelación.');
        }
        return $result;
    }

    public function addPolicyDocument(
        int $policyId,
        string $label,
        UploadedFileInterface $upload,
        int $now,
    ): array {
        $label = trim($label);
        if ($policyId <= 0 || $label === '' || mb_strlen($label) > 190) {
            throw new RuntimeException('La etiqueta del documento no es válida.');
        }
        $validated = $this->validateUpload($upload);
        $validated['label'] = $label;
        $stored = $this->storeDocument($validated);
        try {
            $this->pdo->beginTransaction();
            $policy = $this->lockPolicy($policyId);
            if (!is_array($policy)) {
                throw new RuntimeException('La póliza no existe.');
            }
            $documents = $this->policyDocuments($policy);
            foreach ($documents as $document) {
                if ($document['document_id'] === $stored['metadata']['document_id']) {
                    throw new RuntimeException('No fue posible asignar el identificador del documento.');
                }
            }
            $documents[] = $stored['metadata'];
            $update = $this->pdo->prepare(
                'UPDATE insurance_policies SET documents_json = :documents, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute([
                'documents' => json_encode($documents, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $policyId,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            $this->removeStoredDocument($stored['metadata']['stored_name']);
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible guardar el documento de póliza.', 0, $exception);
        }

        $result = $this->findPolicy($policyId, $this->localDate($now));
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar el documento de póliza.');
        }
        return $result;
    }

    public function removePolicyDocument(int $policyId, string $documentId, int $now): array
    {
        if ($policyId <= 0 || preg_match('/\A[a-f0-9]{32}\z/', $documentId) !== 1) {
            throw new RuntimeException('El documento no existe.');
        }
        $removedName = null;
        try {
            $this->pdo->beginTransaction();
            $policy = $this->lockPolicy($policyId);
            if (!is_array($policy)) {
                throw new RuntimeException('La póliza no existe.');
            }
            if ($policy['cancelled_at'] !== null) {
                throw new RuntimeException('La evidencia de una póliza cancelada es inmutable.');
            }
            $claim = $this->pdo->prepare('SELECT id FROM insurance_claims WHERE policy_id = :id LIMIT 1');
            $claim->execute(['id' => $policyId]);
            if ($claim->fetchColumn() !== false) {
                throw new RuntimeException('La evidencia de una póliza con reclamaciones es inmutable.');
            }
            $documents = $this->policyDocuments($policy);
            $found = false;
            foreach ($documents as $index => $document) {
                if ($document['document_id'] === $documentId) {
                    $removedName = $document['stored_name'];
                    unset($documents[$index]);
                    $found = true;
                    break;
                }
            }
            if (!$found || !is_string($removedName)) {
                throw new RuntimeException('El documento no existe.');
            }
            $update = $this->pdo->prepare(
                'UPDATE insurance_policies SET documents_json = :documents, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute([
                'documents' => json_encode(array_values($documents), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $policyId,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible retirar el documento de póliza.', 0, $exception);
        }

        $this->removeStoredDocument($removedName);
        $result = $this->findPolicy($policyId, $this->localDate($now));
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar el cambio documental.');
        }
        return $result;
    }

    public function readPolicyDocument(int $policyId, string $documentId): ?array
    {
        if ($policyId <= 0 || preg_match('/\A[a-f0-9]{32}\z/', $documentId) !== 1) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT documents_json FROM insurance_policies WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $policyId]);
        $json = $statement->fetchColumn();
        return is_string($json) ? $this->readStoredDocument($json, $documentId) : null;
    }

    public function addClaimDocument(int $claimId, string $label, UploadedFileInterface $upload, int $now): array
    {
        $label = trim($label);
        if ($claimId <= 0 || $label === '' || mb_strlen($label) > 190) {
            throw new RuntimeException('La etiqueta del documento no es válida.');
        }
        $validated = $this->validateUpload($upload);
        $validated['label'] = $label;
        $stored = $this->storeDocument($validated);
        try {
            $this->pdo->beginTransaction();
            $claim = $this->lockClaim($claimId);
            if (!is_array($claim)) {
                throw new RuntimeException('La reclamación no existe.');
            }
            $documents = $this->claimDocuments($claim);
            foreach ($documents as $document) {
                if ($document['document_id'] === $stored['metadata']['document_id']) {
                    throw new RuntimeException('No fue posible asignar el identificador del documento.');
                }
            }
            $documents[] = $stored['metadata'];
            $update = $this->pdo->prepare(
                'UPDATE insurance_claims SET documents_json = :documents, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute([
                'documents' => json_encode($documents, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $claimId,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            $this->removeStoredDocument($stored['metadata']['stored_name']);
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible guardar el documento de reclamación.', 0, $exception);
        }
        $result = $this->findClaim($claimId);
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar el documento de reclamación.');
        }
        return $result;
    }

    public function removeClaimDocument(int $claimId, string $documentId, int $now): array
    {
        if ($claimId <= 0 || preg_match('/\A[a-f0-9]{32}\z/', $documentId) !== 1) {
            throw new RuntimeException('El documento no existe.');
        }
        $removedName = null;
        try {
            $this->pdo->beginTransaction();
            $claim = $this->lockClaim($claimId);
            if (!is_array($claim) || $claim['status'] !== 'draft') {
                throw new RuntimeException('Los documentos solo se pueden retirar mientras la reclamación está en borrador.');
            }
            $documents = $this->claimDocuments($claim);
            $found = false;
            foreach ($documents as $index => $document) {
                if ($document['document_id'] === $documentId) {
                    $removedName = $document['stored_name'];
                    unset($documents[$index]);
                    $found = true;
                    break;
                }
            }
            if (!$found || !is_string($removedName)) {
                throw new RuntimeException('El documento no existe.');
            }
            $update = $this->pdo->prepare(
                'UPDATE insurance_claims SET documents_json = :documents, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute([
                'documents' => json_encode(array_values($documents), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $claimId,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible retirar el documento de reclamación.', 0, $exception);
        }
        $this->removeStoredDocument($removedName);
        $result = $this->findClaim($claimId);
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar el cambio documental.');
        }
        return $result;
    }

    public function readClaimDocument(int $claimId, string $documentId): ?array
    {
        if ($claimId <= 0 || preg_match('/\A[a-f0-9]{32}\z/', $documentId) !== 1) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT documents_json FROM insurance_claims WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $claimId]);
        $json = $statement->fetchColumn();
        return is_string($json) ? $this->readStoredDocument($json, $documentId) : null;
    }

    public function listPolicies(string $today): array
    {
        if (!$this->isDate($today)) {
            throw new RuntimeException('La fecha de consulta no es válida.');
        }
        $ids = $this->pdo->query('SELECT id FROM insurance_policies ORDER BY coverage_start DESC, id DESC')
            ->fetchAll(PDO::FETCH_COLUMN);
        $policies = [];
        foreach ($ids as $id) {
            $policy = $this->findPolicy((int) $id, $today);
            if ($policy !== null) {
                $policies[] = $policy;
            }
        }
        return $policies;
    }

    public function tenantPolicySummary(int $leaseId, string $today): array
    {
        if ($leaseId <= 0 || !$this->isDate($today)) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT id FROM insurance_policies WHERE lease_id = :lease_id ORDER BY coverage_start DESC, id DESC',
        );
        $statement->execute(['lease_id' => $leaseId]);
        $summary = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $policyId) {
            $policy = $this->findPolicy((int) $policyId, $today);
            if ($policy === null) {
                continue;
            }
            $number = (string) $policy['policy_number'];
            $maskedNumber = mb_strlen($number) <= 4 ? '••••' : '••••' . mb_substr($number, -4);
            $coverage = array_map(static fn (array $item): array => [
                'name' => (string) ($item['name'] ?? ''),
                'limit_amount' => $item['limit_amount'] ?? null,
                'deductible_amount' => $item['deductible_amount'] ?? null,
            ], array_filter($policy['coverage'], 'is_array'));
            $summary[] = [
                'provider_name' => $policy['provider_name'],
                'product_name' => $policy['product_name'],
                'policy_number' => $maskedNumber,
                'coverage_start' => $policy['coverage_start'],
                'coverage_end' => $policy['coverage_end'],
                'status' => $policy['status'],
                'coverage' => array_values($coverage),
            ];
        }
        return $summary;
    }

    public function createClaim(
        int $policyId,
        array $input,
        ?UploadedFileInterface $initialDocument,
        int $actorUserId,
        string $today,
        int $now,
    ): array {
        if ($policyId <= 0 || $actorUserId <= 0 || !$this->isDate($today)) {
            throw new RuntimeException('No fue posible registrar la reclamación.');
        }
        $data = $this->normalizeClaimDraft($input);
        $stored = null;
        if ($initialDocument !== null) {
            $label = $this->requiredText($input['document_label'] ?? null, 190, 'La etiqueta del documento');
            $validated = $this->validateUpload($initialDocument);
            $validated['label'] = $label;
            $stored = $this->storeDocument($validated);
        }
        try {
            $this->pdo->beginTransaction();
            $policy = $this->lockPolicy($policyId);
            if (!is_array($policy)) {
                throw new RuntimeException('La póliza no existe.');
            }
            $this->validateIncidentDate($policy, $data['incident_on'], $today);
            $documents = $stored === null ? [] : [$stored['metadata']];
            $insert = $this->pdo->prepare(
                'INSERT INTO insurance_claims '
                . '(policy_id, incident_on, description, claimed_amount, status, radicado, filed_on, resolved_on, '
                . 'resolution_notes, checklist_snapshot_json, documents_json, created_by_user_id, created_at) '
                . 'VALUES (:policy_id, :incident_on, :description, :claimed_amount, \'draft\', NULL, NULL, NULL, NULL, '
                . ':checklist, :documents, :actor, :created_at)',
            );
            $insert->execute([
                'policy_id' => $policyId,
                'incident_on' => $data['incident_on'],
                'description' => $data['description'],
                'claimed_amount' => $data['claimed_amount'],
                'checklist' => json_encode($data['checklist'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'documents' => json_encode($documents, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'actor' => $actorUserId,
                'created_at' => gmdate('Y-m-d H:i:s', $now),
            ]);
            $claimId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($stored !== null) {
                $this->removeStoredDocument($stored['metadata']['stored_name']);
            }
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible registrar la reclamación.', 0, $exception);
        }
        $claim = $this->findClaim($claimId);
        if ($claim === null) {
            throw new RuntimeException('No fue posible confirmar la reclamación.');
        }
        return $claim;
    }

    public function findClaim(int $claimId): ?array
    {
        if ($claimId <= 0) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT * FROM insurance_claims WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $claimId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $documents = $this->decodeJsonList((string) $row['documents_json']);
        return [
            'id' => (int) $row['id'],
            'policy_id' => (int) $row['policy_id'],
            'incident_on' => (string) $row['incident_on'],
            'description' => (string) $row['description'],
            'claimed_amount' => $row['claimed_amount'] === null ? null : (string) $row['claimed_amount'],
            'status' => (string) $row['status'],
            'radicado' => $row['radicado'] === null ? null : (string) $row['radicado'],
            'filed_on' => $row['filed_on'] === null ? null : (string) $row['filed_on'],
            'resolved_on' => $row['resolved_on'] === null ? null : (string) $row['resolved_on'],
            'resolution_notes' => $row['resolution_notes'] === null ? null : (string) $row['resolution_notes'],
            'checklist' => $this->decodeJsonList((string) $row['checklist_snapshot_json']),
            'documents' => array_map($this->publicDocumentMetadata(...), $documents),
            'created_by_user_id' => $row['created_by_user_id'] === null ? null : (int) $row['created_by_user_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
        ];
    }

    public function listClaims(int $policyId): array
    {
        if ($policyId <= 0) {
            return [];
        }
        $statement = $this->pdo->prepare('SELECT id FROM insurance_claims WHERE policy_id = :id ORDER BY incident_on DESC, id DESC');
        $statement->execute(['id' => $policyId]);
        $claims = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $claim = $this->findClaim((int) $id);
            if ($claim !== null) {
                $claims[] = $claim;
            }
        }
        return $claims;
    }

    public function updateDraftClaim(int $claimId, array $input, string $today, int $now): array
    {
        if ($claimId <= 0 || !$this->isDate($today)) {
            throw new RuntimeException('No fue posible actualizar el borrador.');
        }
        try {
            $this->pdo->beginTransaction();
            $claim = $this->lockClaim($claimId);
            if (!is_array($claim) || $claim['status'] !== 'draft') {
                throw new RuntimeException('Solo se puede editar una reclamación en borrador.');
            }
            $policy = $this->lockPolicy((int) $claim['policy_id']);
            if (!is_array($policy)) {
                throw new RuntimeException('La póliza de la reclamación no existe.');
            }
            $existing = [
                'incident_on' => (string) $claim['incident_on'],
                'description' => (string) $claim['description'],
                'claimed_amount' => $claim['claimed_amount'] === null ? null : (string) $claim['claimed_amount'],
                'checklist' => $this->decodeJsonList((string) $claim['checklist_snapshot_json']),
            ];
            $data = $this->normalizeClaimDraft(array_replace($existing, $input));
            $this->validateIncidentDate($policy, $data['incident_on'], $today);
            $update = $this->pdo->prepare(
                'UPDATE insurance_claims SET incident_on = :incident_on, description = :description, '
                . 'claimed_amount = :claimed_amount, checklist_snapshot_json = :checklist, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute([
                'incident_on' => $data['incident_on'],
                'description' => $data['description'],
                'claimed_amount' => $data['claimed_amount'],
                'checklist' => json_encode($data['checklist'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $claimId,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible actualizar el borrador.', 0, $exception);
        }
        $result = $this->findClaim($claimId);
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar el borrador.');
        }
        return $result;
    }

    public function updateClaimChecklist(int $claimId, array $checklist, int $now): array
    {
        $normalized = $this->normalizeChecklist($checklist);
        try {
            $this->pdo->beginTransaction();
            $claim = $this->lockClaim($claimId);
            if (!is_array($claim) || in_array($claim['status'], ['approved', 'partial', 'rejected'], true)) {
                throw new RuntimeException('El checklist está bloqueado para esta reclamación.');
            }
            $update = $this->pdo->prepare(
                'UPDATE insurance_claims SET checklist_snapshot_json = :checklist, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute([
                'checklist' => json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $claimId,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible actualizar el checklist.', 0, $exception);
        }
        $result = $this->findClaim($claimId);
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar el checklist.');
        }
        return $result;
    }

    public function transitionClaim(
        int $claimId,
        string $targetStatus,
        array $input,
        int $actorUserId,
        string $today,
        int $now,
    ): array {
        $allowedStatuses = ['draft', 'filed', 'in_review', 'approved', 'partial', 'rejected'];
        if ($claimId <= 0 || $actorUserId <= 0 || !$this->isDate($today) || !in_array($targetStatus, $allowedStatuses, true)) {
            throw new RuntimeException('La transición de reclamación no es válida.');
        }
        try {
            $this->pdo->beginTransaction();
            $claim = $this->lockClaim($claimId);
            if (!is_array($claim)) {
                throw new RuntimeException('La reclamación no existe.');
            }
            $transitions = [
                'draft' => ['filed'],
                'filed' => ['in_review', 'approved', 'partial', 'rejected'],
                'in_review' => ['approved', 'partial', 'rejected'],
                'approved' => [],
                'partial' => [],
                'rejected' => [],
            ];
            if (!in_array($targetStatus, $transitions[(string) $claim['status']] ?? [], true)) {
                throw new RuntimeException('La transición de estado no está permitida.');
            }
            $filedOn = array_key_exists('filed_on', $input) ? $input['filed_on'] : $claim['filed_on'];
            $radicado = array_key_exists('radicado', $input) ? $input['radicado'] : $claim['radicado'];
            $resolvedOn = array_key_exists('resolved_on', $input) ? $input['resolved_on'] : null;
            $resolutionNotes = array_key_exists('resolution_notes', $input) ? $input['resolution_notes'] : null;
            $filedOn = $this->normalizeClaimDate($filedOn, 'La fecha de radicación');
            $radicado = $this->normalizeRadicado($radicado);
            $terminal = in_array($targetStatus, ['approved', 'partial', 'rejected'], true);
            if ($targetStatus === 'filed' || $targetStatus === 'in_review' || $terminal) {
                if ($filedOn === null || $filedOn < (string) $claim['incident_on'] || $filedOn > $today) {
                    throw new RuntimeException('La fecha de radicación no es válida.');
                }
            }
            if ($terminal) {
                $resolvedOn = $this->normalizeClaimDate($resolvedOn, 'La fecha de resolución');
                if ($resolvedOn === null || $resolvedOn < $filedOn || $resolvedOn > $today) {
                    throw new RuntimeException('La fecha de resolución no es válida.');
                }
                $resolutionNotes = $this->normalizeOptionalText($resolutionNotes, 1000, 'Las notas de resolución');
            } else {
                if ($resolvedOn !== null || $resolutionNotes !== null) {
                    throw new RuntimeException('Solo un estado terminal admite datos de resolución.');
                }
                $resolvedOn = null;
                $resolutionNotes = null;
            }
            $update = $this->pdo->prepare(
                'UPDATE insurance_claims SET status = :status, filed_on = :filed_on, radicado = :radicado, '
                . 'resolved_on = :resolved_on, resolution_notes = :resolution_notes, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute([
                'status' => $targetStatus,
                'filed_on' => $filedOn,
                'radicado' => $radicado,
                'resolved_on' => $resolvedOn,
                'resolution_notes' => $resolutionNotes,
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $claimId,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible cambiar el estado de la reclamación.', 0, $exception);
        }
        $result = $this->findClaim($claimId);
        if ($result === null) {
            throw new RuntimeException('No fue posible confirmar el estado de la reclamación.');
        }
        return $result;
    }

    private function normalizePolicyInput(array $input): array
    {
        $provider = $this->requiredText($input['provider_name'] ?? null, 190, 'La aseguradora');
        $product = $this->requiredText($input['product_name'] ?? null, 190, 'El producto');
        $number = $this->requiredText($input['policy_number'] ?? null, 190, 'El número de póliza');
        $start = $input['coverage_start'] ?? null;
        $end = $input['coverage_end'] ?? null;
        if (!is_string($start) || !is_string($end) || !$this->isDate($start) || !$this->isDate($end) || $start > $end) {
            throw new RuntimeException('El período de cobertura no es válido.');
        }
        if (!isset($input['coverage']) || !is_array($input['coverage']) || !array_is_list($input['coverage']) || $input['coverage'] === []) {
            throw new RuntimeException('La póliza requiere al menos una cobertura.');
        }
        $coverage = [];
        $names = [];
        foreach ($input['coverage'] as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('La cobertura no es válida.');
            }
            $name = $this->requiredText($item['name'] ?? null, 190, 'El nombre de cobertura');
            $normalizedName = mb_strtolower(preg_replace('/\s+/u', ' ', $name) ?? $name);
            if (isset($names[$normalizedName])) {
                throw new RuntimeException('No se permiten coberturas duplicadas.');
            }
            $names[$normalizedName] = true;
            $notes = $item['notes'] ?? null;
            if ($notes !== null && !is_string($notes)) {
                throw new RuntimeException('Las notas de cobertura no son válidas.');
            }
            $notes = $notes === null ? null : trim($notes);
            if ($notes === '' || $notes === null) {
                $notes = null;
            } elseif (mb_strlen($notes) > 500) {
                throw new RuntimeException('Las notas de cobertura superan 500 caracteres.');
            }
            $coverage[] = [
                'name' => $name,
                'limit_amount' => $this->normalizePositiveAmount($item['limit_amount'] ?? null),
                'deductible_amount' => $this->normalizePositiveAmount($item['deductible_amount'] ?? null),
                'notes' => $notes,
            ];
        }

        $supersedes = $input['supersedes_policy_id'] ?? null;
        if ($supersedes !== null && (!is_int($supersedes) && !(is_string($supersedes) && ctype_digit($supersedes)))) {
            throw new RuntimeException('La referencia a la póliza anterior no es válida.');
        }
        $supersedes = $supersedes === null ? null : (int) $supersedes;
        if ($supersedes !== null && $supersedes <= 0) {
            throw new RuntimeException('La referencia a la póliza anterior no es válida.');
        }
        $notes = $input['internal_notes'] ?? null;
        if ($notes !== null && !is_string($notes)) {
            throw new RuntimeException('Las notas internas no son válidas.');
        }
        $notes = $notes === null ? null : trim($notes);
        if ($notes === '') {
            $notes = null;
        }
        if ($notes !== null && mb_strlen($notes) > 1000) {
            throw new RuntimeException('Las notas internas no pueden superar 1000 caracteres.');
        }

        return [
            'provider_name' => $provider,
            'product_name' => $product,
            'policy_number' => $number,
            'coverage_start' => $start,
            'coverage_end' => $end,
            'coverage' => $coverage,
            'supersedes_policy_id' => $supersedes,
            'internal_notes' => $notes,
        ];
    }

    private function normalizeClaimDraft(array $input): array
    {
        $incidentOn = $input['incident_on'] ?? null;
        if (!is_string($incidentOn) || !$this->isDate($incidentOn)) {
            throw new RuntimeException('La fecha del incidente no es válida.');
        }
        $description = $this->requiredText($input['description'] ?? null, 1000, 'La descripción');
        $amount = $this->normalizePositiveAmount($input['claimed_amount'] ?? null, 'importe reclamado');
        $checklist = $this->normalizeChecklist($input['checklist'] ?? []);
        return [
            'incident_on' => $incidentOn,
            'description' => $description,
            'claimed_amount' => $amount,
            'checklist' => $checklist,
        ];
    }

    private function normalizeChecklist(mixed $checklist): array
    {
        if (!is_array($checklist) || !array_is_list($checklist)) {
            throw new RuntimeException('El checklist no tiene un formato válido.');
        }
        $normalized = [];
        $keys = [];
        foreach ($checklist as $item) {
            if (!is_array($item)
                || array_diff(array_keys($item), ['key', 'label', 'required', 'completed']) !== []
                || !is_string($item['key'] ?? null)
                || !is_string($item['label'] ?? null)
                || !is_bool($item['required'] ?? null)
                || !is_bool($item['completed'] ?? null)
            ) {
                throw new RuntimeException('Un elemento del checklist no es válido.');
            }
            $key = trim($item['key']);
            $label = trim($item['label']);
            if ($key === '' || mb_strlen($key) > 64 || preg_match('/\A[a-z0-9_-]+\z/', $key) !== 1
                || $label === '' || mb_strlen($label) > 190
            ) {
                throw new RuntimeException('Un elemento del checklist no es válido.');
            }
            if (isset($keys[$key])) {
                throw new RuntimeException('Las claves del checklist deben ser únicas.');
            }
            $keys[$key] = true;
            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'required' => $item['required'],
                'completed' => $item['completed'],
            ];
        }
        return $normalized;
    }

    private function validateIncidentDate(array $policy, string $incidentOn, string $today): void
    {
        if (!$this->isDate($today)
            || $incidentOn > $today
            || $incidentOn < (string) $policy['coverage_start']
            || $incidentOn > (string) $policy['coverage_end']
        ) {
            throw new RuntimeException('El incidente debe estar dentro de la vigencia de la póliza y no ser futuro.');
        }
        if ($policy['cancelled_at'] !== null && $incidentOn > $this->localCancellationDate((string) $policy['cancelled_at'])) {
            throw new RuntimeException('La póliza cancelada no cubre incidentes posteriores a su fecha local de cancelación.');
        }
    }

    private function localCancellationDate(string $cancelledAt): string
    {
        return (new DateTimeImmutable($cancelledAt, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('America/Bogota'))
            ->format('Y-m-d');
    }

    private function normalizeClaimDate(mixed $date, string $label): ?string
    {
        if ($date === null) {
            return null;
        }
        if (!is_string($date) || !$this->isDate($date)) {
            throw new RuntimeException($label . ' no es válida.');
        }
        return $date;
    }

    private function normalizeRadicado(mixed $radicado): ?string
    {
        if ($radicado === null) {
            return null;
        }
        if (!is_string($radicado)) {
            throw new RuntimeException('El radicado no es válido.');
        }
        $radicado = trim($radicado);
        if ($radicado === '') {
            return null;
        }
        if (mb_strlen($radicado) > 190) {
            throw new RuntimeException('El radicado no puede superar 190 caracteres.');
        }
        return $radicado;
    }

    private function normalizeOptionalText(mixed $value, int $maxLength, string $label): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new RuntimeException($label . ' no son válidas.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maxLength) {
            throw new RuntimeException($label . ' superan el límite permitido.');
        }
        return $value;
    }

    /** @return list<array<string, mixed>> */
    private function claimDocuments(array $claim): array
    {
        try {
            $documents = json_decode((string) ($claim['documents_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Los metadatos documentales de la reclamación no son válidos.', 0, $exception);
        }
        if (!is_array($documents) || !array_is_list($documents)) {
            throw new RuntimeException('Los metadatos documentales de la reclamación no son válidos.');
        }
        foreach ($documents as $document) {
            if (!is_array($document) || !$this->validDocumentMetadata($document)) {
                throw new RuntimeException('Los metadatos documentales de la reclamación no son válidos.');
            }
        }
        return $documents;
    }

    private function readStoredDocument(string $json, string $documentId): ?array
    {
        $documents = $this->decodeJsonList($json);
        foreach ($documents as $document) {
            if (!is_array($document) || ($document['document_id'] ?? null) !== $documentId || !$this->validDocumentMetadata($document)) {
                continue;
            }
            $path = $this->documentPath($document['stored_name'], true);
            if ($path === null) {
                return null;
            }
            clearstatcache(true, $path);
            $size = @filesize($path);
            if (!is_int($size)
                || $size <= 0
                || $size > self::MAX_DOCUMENT_BYTES
                || $size !== $document['size_bytes']
            ) {
                return null;
            }

            $handle = @fopen($path, 'rb');
            if ($handle === false) {
                return null;
            }
            try {
                $stat = @fstat($handle);
                $openedSize = is_array($stat) ? ($stat['size'] ?? null) : null;
                if (!is_int($openedSize)
                    || $openedSize <= 0
                    || $openedSize > self::MAX_DOCUMENT_BYTES
                    || $openedSize !== $size
                    || $openedSize !== $document['size_bytes']
                ) {
                    return null;
                }
                $bytes = @stream_get_contents($handle, self::MAX_DOCUMENT_BYTES + 1);
            } finally {
                fclose($handle);
            }

            if (!is_string($bytes)
                || strlen($bytes) !== $openedSize
                || strlen($bytes) > self::MAX_DOCUMENT_BYTES
            ) {
                return null;
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            $sha256 = hash('sha256', $bytes);
            if ($mime !== $document['mime'] || !hash_equals($document['sha256'], $sha256)) {
                return null;
            }
            return ['bytes' => $bytes, 'mime' => $mime, 'size_bytes' => $size];
        }
        return null;
    }

    private function normalizePositiveAmount(mixed $amount, string $label = 'importe de cobertura'): ?string
    {
        if ($amount === null) {
            return null;
        }
        if (!is_string($amount) || preg_match('/\A[0-9]{1,64}(?:\.[0-9]{1,2})?\z/', trim($amount)) !== 1) {
            throw new RuntimeException('El ' . $label . ' debe ser un decimal válido.');
        }
        [$whole, $fraction] = array_pad(explode('.', trim($amount), 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($fraction, 2, '0');
        if (strlen($whole) > 10 || ($whole === '0' && $fraction === '00')) {
            throw new RuntimeException('El ' . $label . ' debe estar entre 0.01 y 9999999999.99.');
        }
        return $whole . '.' . $fraction;
    }

    private function requiredText(mixed $value, int $maxLength, string $label): string
    {
        if (!is_string($value)) {
            throw new RuntimeException($label . ' es obligatorio.');
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new RuntimeException($label . ' no es válido.');
        }
        return $value;
    }

    private function isDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function localDate(int $timestamp): string
    {
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone('America/Bogota'))
            ->format('Y-m-d');
    }

    /** @return list<array<string, mixed>> */
    private function decodeJsonList(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }
        return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
    }

    private function publicDocumentMetadata(array $document): array
    {
        return [
            'document_id' => (string) ($document['document_id'] ?? ''),
            'label' => (string) ($document['label'] ?? ''),
            'mime' => (string) ($document['mime'] ?? ''),
            'sha256' => (string) ($document['sha256'] ?? ''),
            'size_bytes' => (int) ($document['size_bytes'] ?? 0),
        ];
    }

    private function validateUpload(UploadedFileInterface $upload): array
    {
        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No fue posible recibir el documento.');
        }
        $reportedSize = $upload->getSize();
        if (is_int($reportedSize) && ($reportedSize < 1 || $reportedSize > self::MAX_DOCUMENT_BYTES)) {
            throw new RuntimeException('El documento supera el tamaño permitido.');
        }
        try {
            $stream = $upload->getStream();
            if (!$stream->isReadable()) {
                throw new RuntimeException('No fue posible leer el documento.');
            }
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $bytes = $stream->getContents();
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible leer el documento.', 0, $exception);
        }
        $size = strlen($bytes);
        if ($size < 1 || $size > self::MAX_DOCUMENT_BYTES || (is_int($reportedSize) && $reportedSize !== $size)) {
            throw new RuntimeException('El documento no cumple el tamaño permitido.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!is_string($mime) || !isset(self::DOCUMENT_EXTENSIONS[$mime])) {
            throw new RuntimeException('El formato del documento no es válido.');
        }
        return ['bytes' => $bytes, 'mime' => $mime, 'size_bytes' => $size, 'sha256' => hash('sha256', $bytes)];
    }

    private function storeDocument(array $validated): array
    {
        $this->ensureDocumentDirectory();
        $extension = self::DOCUMENT_EXTENSIONS[$validated['mime']];
        $storedName = 'insurance-' . bin2hex(random_bytes(16)) . '.' . $extension;
        $documentId = bin2hex(random_bytes(16));
        while (hash_equals($documentId, substr($storedName, strlen('insurance-'), 32))) {
            $documentId = bin2hex(random_bytes(16));
        }
        $path = $this->documentPath($storedName, false);
        if ($path === null) {
            throw new RuntimeException('No fue posible preparar el documento privado.');
        }
        try {
            $written = @file_put_contents($path, $validated['bytes'], LOCK_EX);
            @chmod($path, 0600);
            clearstatcache(true, $path);
            $size = @filesize($path);
            $mime = is_file($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
            $sha256 = @hash_file('sha256', $path);
            if ($written !== $validated['size_bytes']
                || !is_int($size)
                || $size !== $validated['size_bytes']
                || $mime !== $validated['mime']
                || !is_string($sha256)
                || !hash_equals($validated['sha256'], $sha256)
            ) {
                throw new RuntimeException('No fue posible verificar el documento guardado.');
            }
        } catch (Throwable $exception) {
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible guardar el documento privado.', 0, $exception);
        }
        return [
            'stored_name' => $storedName,
            'metadata' => [
                'document_id' => $documentId,
                'label' => $validated['label'] ?? '',
                'stored_name' => $storedName,
                'mime' => $validated['mime'],
                'sha256' => $validated['sha256'],
                'size_bytes' => $validated['size_bytes'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function policyDocuments(array $policy): array
    {
        try {
            $documents = json_decode((string) ($policy['documents_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Los metadatos documentales de la póliza no son válidos.', 0, $exception);
        }
        if (!is_array($documents) || !array_is_list($documents)) {
            throw new RuntimeException('Los metadatos documentales de la póliza no son válidos.');
        }
        foreach ($documents as $document) {
            if (!is_array($document) || !$this->validDocumentMetadata($document)) {
                throw new RuntimeException('Los metadatos documentales de la póliza no son válidos.');
            }
        }
        return $documents;
    }

    private function validDocumentMetadata(array $document): bool
    {
        return is_string($document['document_id'] ?? null)
            && preg_match('/\A[a-f0-9]{32}\z/', $document['document_id']) === 1
            && is_string($document['label'] ?? null)
            && $document['label'] !== ''
            && mb_strlen($document['label']) <= 190
            && is_string($document['stored_name'] ?? null)
            && preg_match('/\Ainsurance-[a-f0-9]{32}\.(?:png|jpg|webp|pdf)\z/', $document['stored_name']) === 1
            && $document['document_id'] !== substr($document['stored_name'], strlen('insurance-'), 32)
            && is_string($document['mime'] ?? null)
            && in_array($document['mime'], array_keys(self::DOCUMENT_EXTENSIONS), true)
            && is_string($document['sha256'] ?? null)
            && preg_match('/\A[a-f0-9]{64}\z/', $document['sha256']) === 1
            && is_int($document['size_bytes'] ?? null)
            && $document['size_bytes'] > 0
            && $document['size_bytes'] <= self::MAX_DOCUMENT_BYTES;
    }

    private function ensureDocumentDirectory(): void
    {
        if (!is_dir($this->documentDirectory)
            && !@mkdir($this->documentDirectory, 0700, true)
            && !is_dir($this->documentDirectory)
        ) {
            throw new RuntimeException('No fue posible preparar el almacenamiento privado.');
        }
        if (is_link($this->documentDirectory)) {
            throw new RuntimeException('El almacenamiento privado no es seguro.');
        }
        @chmod($this->documentDirectory, 0700);
        $directory = realpath($this->documentDirectory);
        $projectRoot = realpath(dirname(__DIR__, 2));
        $webRoot = is_string($projectRoot) ? realpath($projectRoot . DIRECTORY_SEPARATOR . 'public') : false;
        if (!is_string($directory) || (is_string($webRoot) && $this->isWithinDirectory($directory, $webRoot))) {
            throw new RuntimeException('El documento debe almacenarse fuera del directorio público.');
        }
    }

    private function documentPath(string $storedName, bool $mustExist): ?string
    {
        if (preg_match('/\Ainsurance-[a-f0-9]{32}\.(?:png|jpg|webp|pdf)\z/', $storedName) !== 1) {
            return null;
        }
        $directory = realpath($this->documentDirectory);
        if (!is_string($directory)) {
            return null;
        }
        $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;
        if (!$mustExist) {
            return file_exists($candidate) || is_link($candidate) ? null : $candidate;
        }
        if (is_link($candidate) || !is_file($candidate)) {
            return null;
        }
        $resolved = realpath($candidate);
        return is_string($resolved) && $this->isWithinDirectory($resolved, $directory) ? $resolved : null;
    }

    private function isWithinDirectory(string $path, string $directory): bool
    {
        $prefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        return strcasecmp($path, rtrim($directory, DIRECTORY_SEPARATOR)) === 0
            || strncasecmp($path, $prefix, strlen($prefix)) === 0;
    }

    private function removeStoredDocument(string $storedName): void
    {
        $path = $this->documentPath($storedName, true);
        if ($path !== null && !@unlink($path)) {
            error_log('Insurance document cleanup failed.');
        }
    }

    private function lockPolicy(int $policyId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM insurance_policies WHERE id = :id LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $policyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockClaim(int $claimId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM insurance_claims WHERE id = :id LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $claimId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
