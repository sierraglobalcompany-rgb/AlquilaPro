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
