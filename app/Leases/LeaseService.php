<?php

declare(strict_types=1);

namespace AlquilaPro\Leases;

use AlquilaPro\Auth\UserService;
use PDO;
use RuntimeException;
use Throwable;

final readonly class LeaseService
{
    public function __construct(
        private PDO $pdo,
        private UserService $users,
    ) {
    }

    /** @return array{lease:array,temporary_password:string} */
    public function activate(int $contractId, int $activatedByUserId, int $now): array
    {
        if ($contractId <= 0 || $activatedByUserId <= 0) {
            throw new RuntimeException('No fue posible activar el arrendamiento.');
        }

        try {
            $this->pdo->beginTransaction();

            $contractLock = $this->pdo->prepare(
                'SELECT candidate_id, unit_id, status FROM contracts WHERE id = :id FOR UPDATE',
            );
            $contractLock->execute(['id' => $contractId]);
            $contract = $contractLock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($contract) || (string) $contract['status'] !== 'finalized') {
                throw new RuntimeException('Solo un contrato finalizado puede activar un arrendamiento.');
            }

            $unitId = (int) $contract['unit_id'];
            $unitLock = $this->pdo->prepare(
                'SELECT id, property_id, status FROM units WHERE id = :id FOR UPDATE',
            );
            $unitLock->execute(['id' => $unitId]);
            $unit = $unitLock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($unit) || !in_array((string) $unit['status'], ['available', 'in_process'], true)) {
                throw new RuntimeException('La unidad no está disponible para activar el arrendamiento.');
            }

            $existingContractLease = $this->pdo->prepare(
                'SELECT id FROM leases WHERE contract_id = :contract_id LIMIT 1',
            );
            $existingContractLease->execute(['contract_id' => $contractId]);
            if ($existingContractLease->fetchColumn() !== false) {
                throw new RuntimeException('El contrato ya tiene un arrendamiento.');
            }

            $activeUnitLease = $this->pdo->prepare(
                'SELECT l.id FROM leases l '
                . 'INNER JOIN contracts c ON c.id = l.contract_id '
                . 'WHERE c.unit_id = :unit_id AND l.ended_at IS NULL LIMIT 1',
            );
            $activeUnitLease->execute(['unit_id' => $unitId]);
            if ($activeUnitLease->fetchColumn() !== false) {
                throw new RuntimeException('La unidad ya tiene un arrendamiento activo.');
            }

            $candidateStatement = $this->pdo->prepare(
                'SELECT id, unit_id, full_name, email FROM candidates WHERE id = :id LIMIT 1',
            );
            $candidateStatement->execute(['id' => (int) $contract['candidate_id']]);
            $candidate = $candidateStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($candidate) || (int) $candidate['unit_id'] !== $unitId) {
                throw new RuntimeException('El candidato ya no corresponde a la unidad contractual.');
            }

            $identity = $this->users->createTenantIdentity(
                (string) $candidate['full_name'],
                (string) $candidate['email'],
            );

            $activatedAt = gmdate('Y-m-d H:i:s', $now);
            $insert = $this->pdo->prepare(
                'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) '
                . 'VALUES (:contract_id, :user_id, :activated_by_user_id, :activated_at)',
            );
            $insert->execute([
                'contract_id' => $contractId,
                'user_id' => $identity['id'],
                'activated_by_user_id' => $activatedByUserId,
                'activated_at' => $activatedAt,
            ]);

            $occupy = $this->pdo->prepare(
                "UPDATE units SET status = 'occupied', updated_at = CURRENT_TIMESTAMP "
                . "WHERE id = :id AND status IN ('available', 'in_process')",
            );
            $occupy->execute(['id' => $unitId]);
            if ($occupy->rowCount() !== 1) {
                throw new RuntimeException('No fue posible ocupar la unidad.');
            }

            $lease = $this->findByContract($contractId);
            if ($lease === null) {
                throw new RuntimeException('No fue posible activar el arrendamiento.');
            }

            $this->pdo->commit();

            return [
                'lease' => $lease,
                'temporary_password' => $identity['temporary_password'],
            ];
        } catch (RuntimeException $exception) {
            $this->rollbackIfNeeded();
            throw $exception;
        } catch (Throwable) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible activar el arrendamiento.');
        }
    }

    public function end(int $leaseId, int $endedByUserId, int $now): array
    {
        if ($leaseId <= 0 || $endedByUserId <= 0) {
            throw new RuntimeException('No fue posible finalizar el arrendamiento.');
        }

        try {
            $this->pdo->beginTransaction();

            $lock = $this->pdo->prepare(
                'SELECT contract_id, ended_at FROM leases WHERE id = :id FOR UPDATE',
            );
            $lock->execute(['id' => $leaseId]);
            $lease = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($lease)) {
                throw new RuntimeException('El arrendamiento no existe.');
            }
            if ($lease['ended_at'] !== null) {
                throw new RuntimeException('El arrendamiento ya fue finalizado.');
            }

            $update = $this->pdo->prepare(
                'UPDATE leases SET ended_by_user_id = :ended_by_user_id, ended_at = :ended_at '
                . 'WHERE id = :id AND ended_at IS NULL',
            );
            $update->execute([
                'ended_by_user_id' => $endedByUserId,
                'ended_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $leaseId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('El arrendamiento ya fue finalizado.');
            }

            $this->pdo->commit();

            $ended = $this->findByContract((int) $lease['contract_id']);
            if ($ended === null || $ended['is_active']) {
                throw new RuntimeException('No fue posible finalizar el arrendamiento.');
            }

            return $ended;
        } catch (RuntimeException $exception) {
            $this->rollbackIfNeeded();
            throw $exception;
        } catch (Throwable) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible finalizar el arrendamiento.');
        }
    }

    public function findByContract(int $contractId): ?array
    {
        if ($contractId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare($this->leaseSelect() . ' WHERE l.contract_id = :contract_id LIMIT 1');
        $statement->execute(['contract_id' => $contractId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeLease($row) : null;
    }

    public function find(int $leaseId): ?array
    {
        if ($leaseId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare($this->leaseSelect() . ' WHERE l.id = :id LIMIT 1');
        $statement->execute(['id' => $leaseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeLease($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $statement = $this->pdo->prepare(
            $this->leaseSelect()
            . ' WHERE l.user_id = :user_id '
            . 'ORDER BY CASE WHEN l.ended_at IS NULL THEN 0 ELSE 1 END, l.activated_at DESC, l.id DESC',
        );
        $statement->execute(['user_id' => $userId]);

        return array_map(
            fn (array $row): array => $this->normalizeLease($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    public function findForUser(int $leaseId, int $userId): ?array
    {
        if ($leaseId <= 0 || $userId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            $this->leaseSelect() . ' WHERE l.id = :id AND l.user_id = :user_id LIMIT 1',
        );
        $statement->execute([
            'id' => $leaseId,
            'user_id' => $userId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeLease($row) : null;
    }

    private function leaseSelect(): string
    {
        return 'SELECT l.id, l.contract_id, l.user_id, l.activated_by_user_id, l.activated_at, '
            . 'l.ended_by_user_id, l.ended_at, '
            . 'c.candidate_id, c.unit_id, c.document_type, c.monthly_rent, c.administration_fee, '
            . 'c.start_date, c.end_date, c.duration_text, '
            . 'tu.full_name AS tenant_name, tu.email AS tenant_email, '
            . 'un.property_id, un.type AS unit_type, un.code AS unit_code, '
            . 'un.location_label AS unit_location_label, un.unit_number, un.tower, un.floor, un.parking_level, '
            . 'p.name AS property_name, p.address AS property_address, p.city AS property_city, p.sector AS property_sector '
            . 'FROM leases l '
            . 'INNER JOIN contracts c ON c.id = l.contract_id '
            . 'INNER JOIN users tu ON tu.id = l.user_id '
            . 'INNER JOIN units un ON un.id = c.unit_id '
            . 'INNER JOIN properties p ON p.id = un.property_id';
    }

    private function normalizeLease(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'contract_id' => (int) $row['contract_id'],
            'user_id' => (int) $row['user_id'],
            'activated_by_user_id' => $row['activated_by_user_id'] === null ? null : (int) $row['activated_by_user_id'],
            'activated_at' => (string) $row['activated_at'],
            'ended_by_user_id' => $row['ended_by_user_id'] === null ? null : (int) $row['ended_by_user_id'],
            'ended_at' => $row['ended_at'] === null ? null : (string) $row['ended_at'],
            'is_active' => $row['ended_at'] === null,
            'candidate_id' => (int) $row['candidate_id'],
            'unit_id' => (int) $row['unit_id'],
            'document_type' => (string) $row['document_type'],
            'monthly_rent' => (string) $row['monthly_rent'],
            'administration_fee' => $row['administration_fee'] === null ? null : (string) $row['administration_fee'],
            'start_date' => (string) $row['start_date'],
            'end_date' => (string) $row['end_date'],
            'duration_text' => (string) $row['duration_text'],
            'tenant_name' => (string) $row['tenant_name'],
            'tenant_email' => strtolower((string) $row['tenant_email']),
            'property_id' => (int) $row['property_id'],
            'unit_type' => (string) $row['unit_type'],
            'unit_code' => (string) $row['unit_code'],
            'unit_location_label' => $row['unit_location_label'] === null ? null : (string) $row['unit_location_label'],
            'unit_number' => $row['unit_number'] === null ? null : (string) $row['unit_number'],
            'tower' => $row['tower'] === null ? null : (string) $row['tower'],
            'floor' => $row['floor'] === null ? null : (string) $row['floor'],
            'parking_level' => $row['parking_level'] === null ? null : (string) $row['parking_level'],
            'property_name' => (string) $row['property_name'],
            'property_address' => (string) $row['property_address'],
            'property_city' => (string) $row['property_city'],
            'property_sector' => $row['property_sector'] === null ? null : (string) $row['property_sector'],
        ];
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
