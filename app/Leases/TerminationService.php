<?php

declare(strict_types=1);

namespace AlquilaPro\Leases;

use AlquilaPro\Community\CommunityService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\ObligationService;
use AlquilaPro\Insurance\InsuranceService;
use AlquilaPro\Memberships\MembershipService;
use PDO;
use RuntimeException;
use Throwable;

final readonly class TerminationService
{
    public function __construct(
        private PDO $pdo,
        private LeaseService $leases,
        private FinanceService $finance,
        private ObligationService $obligations,
        private InsuranceService $insurance,
        private DocumentService $documents,
        private CommunityService $community,
        private MembershipService $memberships,
        private bool $bonusEnabled,
    ) {
    }

    public function bonusForLease(int $leaseId): ?array
    {
        if ($leaseId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, lease_id, amount, note, decision, created_by_user_id, created_at, updated_at, '
            . 'decided_by_user_id, decided_at '
            . 'FROM good_conduct_bonuses WHERE lease_id = :lease_id LIMIT 1',
        );
        $statement->execute(['lease_id' => $leaseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeBonus($row) : null;
    }

    public function configureBonus(
        int $leaseId,
        string $amount,
        ?string $note,
        int $actorUserId,
        int $now,
    ): array {
        $this->requireBonusEnabled();
        if ($leaseId <= 0 || $actorUserId <= 0 || $now <= 0) {
            throw new RuntimeException('No fue posible configurar el bono.');
        }

        $normalizedAmount = $this->normalizeAmount($amount);
        $normalizedNote = $this->normalizeNote($note);
        $changedAt = gmdate('Y-m-d H:i:s', $now);

        try {
            $this->pdo->beginTransaction();
            $this->lockLease($leaseId);
            $bonus = $this->lockBonus($leaseId);

            if ($bonus === null) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO good_conduct_bonuses '
                    . '(lease_id, amount, note, decision, created_by_user_id, created_at) '
                    . 'VALUES (:lease_id, :amount, :note, NULL, :actor, :created_at)',
                );
                $insert->execute([
                    'lease_id' => $leaseId,
                    'amount' => $normalizedAmount,
                    'note' => $normalizedNote,
                    'actor' => $actorUserId,
                    'created_at' => $changedAt,
                ]);
            } else {
                if ($bonus['decision'] !== null) {
                    throw new RuntimeException('El bono ya tiene una decisión final.');
                }

                $update = $this->pdo->prepare(
                    'UPDATE good_conduct_bonuses SET amount = :amount, note = :note, updated_at = :updated_at '
                    . 'WHERE id = :id AND decision IS NULL',
                );
                $update->execute([
                    'amount' => $normalizedAmount,
                    'note' => $normalizedNote,
                    'updated_at' => $changedAt,
                    'id' => (int) $bonus['id'],
                ]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('El bono ya tiene una decisión final.');
                }
            }

            $stored = $this->bonusForLease($leaseId);
            if ($stored === null) {
                throw new RuntimeException('No fue posible configurar el bono.');
            }

            $this->pdo->commit();
            return $stored;
        } catch (RuntimeException $exception) {
            $this->rollbackIfNeeded();
            throw $exception;
        } catch (Throwable) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible configurar el bono.');
        }
    }

    public function decideBonus(int $leaseId, string $decision, int $actorUserId, int $now): array
    {
        $this->requireBonusEnabled();
        if (
            $leaseId <= 0
            || $actorUserId <= 0
            || $now <= 0
            || !in_array($decision, ['approved', 'denied'], true)
        ) {
            throw new RuntimeException('No fue posible decidir el bono.');
        }

        $decidedAt = gmdate('Y-m-d H:i:s', $now);

        try {
            $this->pdo->beginTransaction();
            $this->lockLease($leaseId);
            $bonus = $this->lockBonus($leaseId);
            if ($bonus === null) {
                throw new RuntimeException('El bono no existe.');
            }
            if ($bonus['decision'] !== null) {
                throw new RuntimeException('El bono ya tiene una decisión final.');
            }

            $update = $this->pdo->prepare(
                'UPDATE good_conduct_bonuses '
                . 'SET decision = :decision, decided_by_user_id = :actor, decided_at = :decided_at '
                . 'WHERE id = :id AND decision IS NULL',
            );
            $update->execute([
                'decision' => $decision,
                'actor' => $actorUserId,
                'decided_at' => $decidedAt,
                'id' => (int) $bonus['id'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('El bono ya tiene una decisión final.');
            }

            $stored = $this->bonusForLease($leaseId);
            if ($stored === null || $stored['decision'] !== $decision) {
                throw new RuntimeException('No fue posible decidir el bono.');
            }

            $this->pdo->commit();
            return $stored;
        } catch (RuntimeException $exception) {
            $this->rollbackIfNeeded();
            throw $exception;
        } catch (Throwable) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible decidir el bono.');
        }
    }

    public function preview(int $leaseId, string $today, array $handover = []): array
    {
        $lease = $this->leases->find($leaseId);
        if ($lease === null) {
            throw new RuntimeException('El arrendamiento no existe.');
        }

        $documents = $this->documents->listCurrentForLease($leaseId);
        $inventories = ['initial' => [], 'final' => []];
        $currentDocuments = [];
        foreach ($documents as $document) {
            $evidence = $this->documentEvidence($document);
            if ($document['type'] === 'inventory_initial') {
                $inventories['initial'][] = $evidence;
            } elseif ($document['type'] === 'inventory_final') {
                $inventories['final'][] = $evidence;
            } else {
                $currentDocuments[] = $evidence;
            }
        }

        $claimEvidence = [];
        foreach ($this->insurance->listPoliciesForLease($leaseId, $today) as $policy) {
            $claims = array_map(
                fn (array $claim): array => $this->claimEvidence($claim),
                $this->insurance->listClaims((int) $policy['id']),
            );
            $claimEvidence[] = [
                'policy' => $this->policyEvidence($policy),
                'claims' => $claims,
            ];
        }

        $payments = array_map(
            fn (array $payment): array => $this->paymentEvidence($payment),
            $this->finance->listPaymentsForLease($leaseId),
        );
        $memberships = $this->memberships->listForLease($leaseId);
        $bonus = $this->bonusEnabled ? $this->bonusForLease($leaseId) : null;
        $blockers = [];

        foreach ($payments as $payment) {
            if ($payment['state'] === 'pending_reconciliation') {
                $blockers[] = 'payment_pending_reconciliation';
                break;
            }
        }
        foreach ($memberships as $membership) {
            if ($membership['charge_decision_required'] === true) {
                $blockers[] = 'membership_charge_decision_pending';
                break;
            }
        }
        if ($bonus !== null && $bonus['decision'] === null) {
            $blockers[] = 'bonus_decision_pending';
        }
        if ($inventories['initial'] !== [] && $inventories['final'] === []) {
            $blockers[] = 'final_inventory_missing';
        }

        return [
            'lease' => $lease,
            'contract' => [
                'id' => $lease['contract_id'],
                'document_type' => $lease['document_type'],
                'start_date' => $lease['start_date'],
                'end_date' => $lease['end_date'],
                'duration_text' => $lease['duration_text'],
                'monthly_rent' => $lease['monthly_rent'],
                'administration_fee' => $lease['administration_fee'],
            ],
            'handover' => $this->normalizeHandover($handover),
            'finance' => [
                'summary' => $this->finance->summaryForLease($leaseId, $today),
                'charges' => $this->finance->listForLease($leaseId, $today),
                'payments' => $payments,
            ],
            'obligations' => array_map(
                fn (array $obligation): array => $this->obligationEvidence($obligation),
                $this->obligations->listForLease($leaseId, $today),
            ),
            'claims' => $claimEvidence,
            'inventories' => $inventories,
            'current_documents' => $currentDocuments,
            'community' => array_map(
                fn (array $incident): array => $this->communityEvidence($incident),
                $this->community->listForLease($leaseId),
            ),
            'memberships' => $memberships,
            'bonus' => $bonus,
            'blockers' => $blockers,
        ];
    }

    private function requireBonusEnabled(): void
    {
        if (!$this->bonusEnabled) {
            throw new RuntimeException('El bono de buen comportamiento está deshabilitado.');
        }
    }

    private function lockLease(int $leaseId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM leases WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $leaseId]);
        if ($statement->fetchColumn() === false) {
            throw new RuntimeException('El arrendamiento no existe.');
        }
    }

    private function lockBonus(int $leaseId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, lease_id, amount, note, decision, created_by_user_id, created_at, updated_at, '
            . 'decided_by_user_id, decided_at '
            . 'FROM good_conduct_bonuses WHERE lease_id = :lease_id LIMIT 1 FOR UPDATE',
        );
        $statement->execute(['lease_id' => $leaseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeBonus($row) : null;
    }

    private function normalizeAmount(string $amount): string
    {
        $value = trim($amount);
        if (preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/', $value, $matches) !== 1) {
            throw new RuntimeException('El valor del bono no es válido.');
        }

        $whole = $matches[1];
        $fraction = str_pad($matches[2] ?? '', 2, '0');
        $normalized = $whole . '.' . $fraction;
        if ($normalized === '0.00') {
            throw new RuntimeException('El valor del bono no es válido.');
        }

        return $normalized;
    }

    private function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $normalized = trim($note);
        if ($normalized === '') {
            return null;
        }
        if (mb_strlen($normalized) > 1000) {
            throw new RuntimeException('La nota del bono es demasiado larga.');
        }

        return $normalized;
    }

    private function normalizeHandover(array $handover): array
    {
        $allowed = ['property_returned', 'keys_returned_count', 'notes'];
        if (array_diff(array_keys($handover), $allowed) !== []) {
            throw new RuntimeException('La entrega contiene campos no permitidos.');
        }

        $propertyReturned = $handover['property_returned'] ?? null;
        if ($propertyReturned !== null && !is_bool($propertyReturned)) {
            throw new RuntimeException('La entrega física no es válida.');
        }

        $keysReturned = $handover['keys_returned_count'] ?? null;
        if ($keysReturned !== null && (!is_int($keysReturned) || $keysReturned < 0 || $keysReturned > 100)) {
            throw new RuntimeException('La cantidad de llaves no es válida.');
        }

        $notes = $handover['notes'] ?? null;
        if ($notes !== null && !is_string($notes)) {
            throw new RuntimeException('La nota de entrega no es válida.');
        }
        $notes = $notes === null ? null : trim($notes);
        if ($notes === '') {
            $notes = null;
        }
        if ($notes !== null && mb_strlen($notes) > 2000) {
            throw new RuntimeException('La nota de entrega es demasiado larga.');
        }

        return [
            'property_returned' => $propertyReturned,
            'keys_returned_count' => $keysReturned,
            'notes' => $notes,
        ];
    }

    private function documentEvidence(array $document): array
    {
        return [
            'id' => (int) $document['id'],
            'type' => (string) $document['type'],
            'title' => (string) $document['title'],
            'mime_type' => $document['mime_type'] === null ? null : (string) $document['mime_type'],
            'sha256' => $document['sha256'] === null ? null : (string) $document['sha256'],
            'size_bytes' => $document['size_bytes'] === null ? null : (int) $document['size_bytes'],
            'supersedes_document_id' => $document['supersedes_document_id'] === null
                ? null
                : (int) $document['supersedes_document_id'],
            'created_at' => (string) $document['created_at'],
        ];
    }

    private function paymentEvidence(array $payment): array
    {
        return [
            'id' => (int) $payment['id'],
            'amount' => (string) $payment['amount'],
            'paid_on' => (string) $payment['paid_on'],
            'paid_time' => $payment['paid_time'] === null ? null : (string) $payment['paid_time'],
            'reported_at' => (string) $payment['reported_at'],
            'state' => (string) $payment['state'],
            'has_receipt' => (bool) $payment['has_receipt'],
            'receipt_mime_type' => $payment['receipt_mime_type'] === null ? null : (string) $payment['receipt_mime_type'],
            'reconciled_at' => $payment['reconciled_at'] === null ? null : (string) $payment['reconciled_at'],
            'rejected_at' => $payment['rejected_at'] === null ? null : (string) $payment['rejected_at'],
            'rejection_reason' => $payment['rejection_reason'] === null ? null : (string) $payment['rejection_reason'],
            'allocations' => $payment['allocations'],
        ];
    }

    private function obligationEvidence(array $obligation): array
    {
        return [
            'id' => (int) $obligation['id'],
            'type' => (string) $obligation['type'],
            'service_name' => $obligation['service_name'] === null ? null : (string) $obligation['service_name'],
            'description' => (string) $obligation['description'],
            'period_month' => $obligation['period_month'] === null ? null : (string) $obligation['period_month'],
            'issued_on' => $obligation['issued_on'] === null ? null : (string) $obligation['issued_on'],
            'due_on' => $obligation['due_on'] === null ? null : (string) $obligation['due_on'],
            'amount' => (string) $obligation['amount'],
            'external_reference' => $obligation['external_reference'] === null
                ? null
                : (string) $obligation['external_reference'],
            'charge_id' => $obligation['charge_id'] === null ? null : (int) $obligation['charge_id'],
            'settled_at' => $obligation['settled_at'] === null ? null : (string) $obligation['settled_at'],
            'voided_at' => $obligation['voided_at'] === null ? null : (string) $obligation['voided_at'],
            'external_state' => (string) $obligation['external_state'],
            'has_source_support' => (bool) $obligation['has_source_support'],
            'has_settlement_support' => (bool) $obligation['has_settlement_support'],
        ];
    }

    private function policyEvidence(array $policy): array
    {
        return [
            'id' => (int) $policy['id'],
            'provider_name' => (string) $policy['provider_name'],
            'product_name' => (string) $policy['product_name'],
            'policy_number' => (string) $policy['policy_number'],
            'coverage_start' => (string) $policy['coverage_start'],
            'coverage_end' => (string) $policy['coverage_end'],
            'coverage' => $policy['coverage'],
            'documents' => $policy['documents'],
            'status' => (string) $policy['status'],
        ];
    }

    private function claimEvidence(array $claim): array
    {
        return [
            'id' => (int) $claim['id'],
            'policy_id' => (int) $claim['policy_id'],
            'incident_on' => (string) $claim['incident_on'],
            'description' => (string) $claim['description'],
            'claimed_amount' => $claim['claimed_amount'] === null ? null : (string) $claim['claimed_amount'],
            'status' => (string) $claim['status'],
            'radicado' => $claim['radicado'] === null ? null : (string) $claim['radicado'],
            'filed_on' => $claim['filed_on'] === null ? null : (string) $claim['filed_on'],
            'resolved_on' => $claim['resolved_on'] === null ? null : (string) $claim['resolved_on'],
            'resolution_notes' => $claim['resolution_notes'] === null ? null : (string) $claim['resolution_notes'],
            'checklist' => $claim['checklist'],
            'documents' => $claim['documents'],
        ];
    }

    private function communityEvidence(array $incident): array
    {
        return [
            'id' => (int) $incident['id'],
            'category' => (string) $incident['category'],
            'occurred_on' => (string) $incident['occurred_on'],
            'description' => (string) $incident['description'],
            'status' => (string) $incident['status'],
            'resolution_notes' => $incident['resolution_notes'] === null
                ? null
                : (string) $incident['resolution_notes'],
            'closed_at' => $incident['closed_at'] === null ? null : (string) $incident['closed_at'],
            'voided_at' => $incident['voided_at'] === null ? null : (string) $incident['voided_at'],
            'void_reason' => $incident['void_reason'] === null ? null : (string) $incident['void_reason'],
        ];
    }

    private function normalizeBonus(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'lease_id' => (int) $row['lease_id'],
            'amount' => (string) $row['amount'],
            'note' => $row['note'] === null ? null : (string) $row['note'],
            'decision' => $row['decision'] === null ? null : (string) $row['decision'],
            'created_by_user_id' => (int) $row['created_by_user_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
            'decided_by_user_id' => $row['decided_by_user_id'] === null ? null : (int) $row['decided_by_user_id'],
            'decided_at' => $row['decided_at'] === null ? null : (string) $row['decided_at'],
        ];
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
