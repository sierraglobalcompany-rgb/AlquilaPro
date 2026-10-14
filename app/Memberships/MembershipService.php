<?php

declare(strict_types=1);

namespace AlquilaPro\Memberships;

use AlquilaPro\Finance\FinanceService;
use DomainException;
use PDO;
use RuntimeException;
use Throwable;

final readonly class MembershipService
{
    private const MAX_DURATION_DAYS = 4_294_967_295;

    public function __construct(
        private PDO $pdo,
        private FinanceService $finance,
    ) {
    }

    /** @return array<string,mixed> */
    public function createService(
        string $name,
        string $description,
        string $price,
        int $durationDays,
        int $actorUserId,
        int $now,
    ): array {
        [$name, $description, $price] = $this->validateService($name, $description, $price, $durationDays, $actorUserId, $now);
        $timestamp = gmdate('Y-m-d H:i:s', $now);
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO digital_services '
                . '(name, description, current_price, duration_days, active, created_by_user_id, created_at, updated_at) '
                . 'VALUES (:name, :description, :price, :duration_days, 1, :actor_id, :created_at, :updated_at)',
            );
            $statement->execute([
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'duration_days' => $durationDays,
                'actor_id' => $actorUserId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $service = $this->findService((int) $this->pdo->lastInsertId());
            if ($service === null) {
                throw new RuntimeException('No fue posible crear el servicio.');
            }
            return $service;
        } catch (Throwable) {
            throw new RuntimeException('No fue posible crear el servicio.');
        }
    }

    /** @return array<string,mixed> */
    public function updateService(
        int $serviceId,
        string $name,
        string $description,
        string $price,
        int $durationDays,
        bool $active,
        int $actorUserId,
        int $now,
    ): array {
        [$name, $description, $price] = $this->validateService($name, $description, $price, $durationDays, $actorUserId, $now);
        if ($serviceId <= 0) {
            throw new RuntimeException('No fue posible actualizar el servicio.');
        }
        try {
            $statement = $this->pdo->prepare(
                'UPDATE digital_services SET name = :name, description = :description, current_price = :price, '
                . 'duration_days = :duration_days, active = :active, updated_at = :updated_at WHERE id = :id',
            );
            $statement->execute([
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'duration_days' => $durationDays,
                'active' => $active ? 1 : 0,
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $serviceId,
            ]);
            $service = $this->findService($serviceId);
            if ($service === null) {
                throw new RuntimeException('No fue posible actualizar el servicio.');
            }
            return $service;
        } catch (Throwable) {
            throw new RuntimeException('No fue posible actualizar el servicio.');
        }
    }

    /** @return array<string,mixed>|null */
    public function findService(int $serviceId): ?array
    {
        if ($serviceId <= 0) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT * FROM digital_services WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $serviceId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeService($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public function listServices(bool $activeOnly = false): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM digital_services ' . ($activeOnly ? 'WHERE active = 1 ' : '') . 'ORDER BY name ASC, id ASC',
        );
        $statement->execute();
        return array_map(
            fn (array $row): array => $this->normalizeService($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @return array<string,mixed> */
    public function accept(
        int $serviceId,
        int $leaseId,
        int $tenantUserId,
        string $expectedPrice,
        int $expectedDurationDays,
        string $today,
        int $now,
    ): array
    {
        $this->validateMembershipRequest($serviceId, $leaseId, $tenantUserId, $today, $now);
        return $this->inMembershipTransaction(function () use (
            $serviceId,
            $leaseId,
            $tenantUserId,
            $expectedPrice,
            $expectedDurationDays,
            $today,
            $now,
        ): array {
            $this->lockTenantLease($leaseId, $tenantUserId);
            $service = $this->lockActiveService($serviceId);
            $this->assertOfferMatches($service, $expectedPrice, $expectedDurationDays);
            $endsOn = $this->endDate($today, (int) $service['duration_days']);
            $this->assertNoOverlap($serviceId, $leaseId, $today, $endsOn);

            $statement = $this->pdo->prepare(
                'INSERT INTO service_memberships '
                . '(service_id, lease_id, accepted_by_user_id, price_snapshot, duration_days_snapshot, starts_on, ends_on, accepted_at) '
                . 'VALUES (:service_id, :lease_id, :tenant_id, :price, :duration, :starts_on, :ends_on, :accepted_at)',
            );
            $statement->execute([
                'service_id' => $serviceId,
                'lease_id' => $leaseId,
                'tenant_id' => $tenantUserId,
                'price' => $service['current_price'],
                'duration' => $service['duration_days'],
                'starts_on' => $today,
                'ends_on' => $endsOn,
                'accepted_at' => gmdate('Y-m-d H:i:s', $now),
            ]);
            return $this->requireMembership((int) $this->pdo->lastInsertId());
        });
    }

    /** @return array<string,mixed> */
    public function renew(
        int $membershipId,
        int $tenantUserId,
        string $expectedPrice,
        int $expectedDurationDays,
        string $today,
        int $now,
    ): array
    {
        $this->validateDate($today);
        if ($membershipId <= 0 || $tenantUserId <= 0 || $now <= 0) {
            throw new RuntimeException('No fue posible renovar el servicio.');
        }
        $lookup = $this->pdo->prepare('SELECT lease_id FROM service_memberships WHERE id = :id LIMIT 1');
        $lookup->execute(['id' => $membershipId]);
        $leaseId = $lookup->fetchColumn();
        if ($leaseId === false) {
            throw new RuntimeException('No fue posible renovar el servicio.');
        }
        $leaseId = (int) $leaseId;

        return $this->inMembershipTransaction(function () use (
            $membershipId,
            $tenantUserId,
            $expectedPrice,
            $expectedDurationDays,
            $today,
            $now,
            $leaseId,
        ): array {
            $this->lockTenantLease($leaseId, $tenantUserId);
            $sourceStatement = $this->pdo->prepare(
                'SELECT * FROM service_memberships WHERE id = :id AND lease_id = :lease_id LIMIT 1 FOR UPDATE',
            );
            $sourceStatement->execute(['id' => $membershipId, 'lease_id' => $leaseId]);
            $source = $sourceStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($source)) {
                throw new RuntimeException('No fue posible renovar el servicio.');
            }

            $renewalStatement = $this->pdo->prepare(
                'SELECT id FROM service_memberships WHERE renews_membership_id = :id LIMIT 1 FOR UPDATE',
            );
            $renewalStatement->execute(['id' => $membershipId]);
            if ($renewalStatement->fetchColumn() !== false) {
                throw new RuntimeException('Esta membresía ya tiene una renovación.');
            }

            $serviceId = (int) $source['service_id'];
            $service = $this->lockActiveService($serviceId);
            $this->assertOfferMatches($service, $expectedPrice, $expectedDurationDays);
            $stillActive = $source['ended_at'] === null && (string) $source['ends_on'] >= $today;
            $startsOn = $stillActive ? $this->endDate((string) $source['ends_on'], 2) : $today;
            $endsOn = $this->endDate($startsOn, (int) $service['duration_days']);
            $this->assertNoOverlap($serviceId, $leaseId, $startsOn, $endsOn);

            $insert = $this->pdo->prepare(
                'INSERT INTO service_memberships '
                . '(service_id, lease_id, accepted_by_user_id, price_snapshot, duration_days_snapshot, starts_on, ends_on, accepted_at, renews_membership_id) '
                . 'VALUES (:service_id, :lease_id, :tenant_id, :price, :duration, :starts_on, :ends_on, :accepted_at, :source_id)',
            );
            $insert->execute([
                'service_id' => $serviceId,
                'lease_id' => $leaseId,
                'tenant_id' => $tenantUserId,
                'price' => $service['current_price'],
                'duration' => $service['duration_days'],
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'accepted_at' => gmdate('Y-m-d H:i:s', $now),
                'source_id' => $membershipId,
            ]);
            return $this->requireMembership((int) $this->pdo->lastInsertId());
        });
    }

    /** @return array<string,mixed> */
    public function end(int $membershipId, int $actorUserId, int $now): array
    {
        if ($membershipId <= 0 || $actorUserId <= 0 || $now <= 0) {
            throw new RuntimeException('No fue posible finalizar el servicio.');
        }
        $lookup = $this->pdo->prepare('SELECT lease_id FROM service_memberships WHERE id = :id LIMIT 1');
        $lookup->execute(['id' => $membershipId]);
        $leaseId = $lookup->fetchColumn();
        if ($leaseId === false) {
            throw new RuntimeException('No fue posible finalizar el servicio.');
        }
        $leaseId = (int) $leaseId;

        return $this->inMembershipTransaction(function () use ($membershipId, $actorUserId, $now, $leaseId): array {
            $lease = $this->pdo->prepare('SELECT id FROM leases WHERE id = :id LIMIT 1 FOR UPDATE');
            $lease->execute(['id' => $leaseId]);
            if ($lease->fetchColumn() === false) {
                throw new RuntimeException('No fue posible finalizar el servicio.');
            }
            $membership = $this->pdo->prepare(
                'SELECT id, ended_at FROM service_memberships WHERE id = :id AND lease_id = :lease_id LIMIT 1 FOR UPDATE',
            );
            $membership->execute(['id' => $membershipId, 'lease_id' => $leaseId]);
            $row = $membership->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || $row['ended_at'] !== null) {
                throw new RuntimeException('La membresía ya está finalizada o no existe.');
            }
            $update = $this->pdo->prepare('UPDATE service_memberships SET ended_at = :ended_at WHERE id = :id');
            $update->execute(['ended_at' => gmdate('Y-m-d H:i:s', $now), 'id' => $membershipId]);
            return $this->requireMembership($membershipId);
        });
    }

    /** @return array<string,mixed>|null */
    public function findMembership(int $membershipId): ?array
    {
        if ($membershipId <= 0) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT sm.*, ds.name AS service_name FROM service_memberships sm '
            . 'INNER JOIN digital_services ds ON ds.id = sm.service_id WHERE sm.id = :id LIMIT 1',
        );
        $statement->execute(['id' => $membershipId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeMembership($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public function listForLease(int $leaseId): array
    {
        if ($leaseId <= 0) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT sm.*, ds.name AS service_name FROM service_memberships sm '
            . 'INNER JOIN digital_services ds ON ds.id = sm.service_id '
            . 'WHERE sm.lease_id = :lease_id ORDER BY sm.accepted_at DESC, sm.id DESC',
        );
        $statement->execute(['lease_id' => $leaseId]);
        return array_map(
            fn (array $row): array => $this->normalizeMembership($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @return list<array<string,mixed>> */
    public function listPendingChargeDecisions(): array
    {
        $statement = $this->pdo->query(
            "SELECT sm.*, ds.name AS service_name FROM service_memberships sm "
            . "INNER JOIN digital_services ds ON ds.id = sm.service_id "
            . "WHERE sm.price_snapshot > 0 AND sm.charge_decision IS NULL ORDER BY sm.accepted_at ASC, sm.id ASC",
        );
        return array_map(
            fn (array $row): array => $this->normalizeMembership($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @return list<array<string,mixed>> */
    public function listRecentMemberships(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $statement = $this->pdo->query(
            'SELECT sm.*, ds.name AS service_name FROM service_memberships sm '
            . 'INNER JOIN digital_services ds ON ds.id = sm.service_id '
            . 'ORDER BY sm.accepted_at DESC, sm.id DESC LIMIT ' . $limit,
        );
        return array_map(
            fn (array $row): array => $this->normalizeMembership($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @return array<string,mixed> */
    public function decideCharge(
        int $membershipId,
        string $decision,
        ?string $dueDate,
        ?string $description,
        int $actorUserId,
        int $now,
    ): array {
        if ($membershipId <= 0 || $actorUserId <= 0 || $now <= 0 || !in_array($decision, ['create', 'skip'], true)) {
            throw new RuntimeException('La decisión del cargo no es válida.');
        }
        if ($decision === 'create') {
            $description = $description === null ? null : trim($description);
            if ($dueDate === null || $description === null || $description === ''
                || mb_strlen($description, 'UTF-8') > 500
            ) {
                throw new RuntimeException('La fecha y descripción del cargo son obligatorias.');
            }
        }

        try {
            $lookup = $this->pdo->prepare('SELECT lease_id FROM service_memberships WHERE id = :id LIMIT 1');
            $lookup->execute(['id' => $membershipId]);
            $leaseId = $lookup->fetchColumn();
            if ($leaseId === false) {
                throw new RuntimeException('La membresía no requiere una decisión o ya fue procesada.');
            }
            $leaseId = (int) $leaseId;

            return $this->inMembershipTransaction(function () use (
                $membershipId,
                $leaseId,
                $decision,
                $dueDate,
                $description,
                $actorUserId,
                $now,
            ): array {
                $leaseLock = $this->pdo->prepare('SELECT id FROM leases WHERE id = :lease_id LIMIT 1 FOR UPDATE');
                $leaseLock->execute(['lease_id' => $leaseId]);
                if ($leaseLock->fetchColumn() === false) {
                    throw new RuntimeException('La membresía no requiere una decisión o ya fue procesada.');
                }

                $statement = $this->pdo->prepare(
                    'SELECT * FROM service_memberships WHERE id = :id AND lease_id = :lease_id LIMIT 1 FOR UPDATE',
                );
                $statement->execute(['id' => $membershipId, 'lease_id' => $leaseId]);
                $membership = $statement->fetch(PDO::FETCH_ASSOC);
                if (!is_array($membership) || (string) $membership['price_snapshot'] === '0.00'
                    || $membership['charge_decision'] !== null || $membership['charge_id'] !== null
                ) {
                    throw new RuntimeException('La membresía no requiere una decisión o ya fue procesada.');
                }

                $chargeId = null;
                if ($decision === 'create') {
                    $charge = $this->finance->createManualCharge(
                        (int) $membership['lease_id'],
                        'service',
                        (string) $membership['price_snapshot'],
                        (string) $dueDate,
                        (string) $description,
                        null,
                        $actorUserId,
                    );
                    $chargeId = $charge['id'];
                }

                $update = $this->pdo->prepare(
                    'UPDATE service_memberships SET charge_decision = :decision, charge_decided_by_user_id = :actor_id, '
                    . 'charge_decided_at = :decided_at, charge_id = :charge_id WHERE id = :id AND charge_decision IS NULL',
                );
                $update->execute([
                    'decision' => $decision,
                    'actor_id' => $actorUserId,
                    'decided_at' => gmdate('Y-m-d H:i:s', $now),
                    'charge_id' => $chargeId,
                    'id' => $membershipId,
                ]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('La decisión del cargo ya fue procesada.');
                }
                return $this->requireMembership($membershipId);
            });
        } catch (RuntimeException $error) {
            throw $error;
        } catch (Throwable) {
            throw new RuntimeException('No fue posible registrar la decisión del cargo.');
        }
    }

    /** @param callable():array<string,mixed> $operation @return array<string,mixed> */
    private function inMembershipTransaction(callable $operation): array
    {
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('No se puede iniciar una operación de membresía dentro de otra transacción.');
        }
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function lockTenantLease(int $leaseId, int $tenantUserId): void
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM leases WHERE id = :lease_id AND user_id = :tenant_id AND ended_at IS NULL LIMIT 1 FOR UPDATE',
        );
        $statement->execute(['lease_id' => $leaseId, 'tenant_id' => $tenantUserId]);
        if ($statement->fetchColumn() === false) {
            throw new RuntimeException('El lease no pertenece al inquilino o no está activo.');
        }
    }

    /** @return array<string,mixed> */
    private function lockActiveService(int $serviceId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM digital_services WHERE id = :id AND active = 1 LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $serviceId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('El servicio no está disponible.');
        }
        return $this->normalizeService($row);
    }

    /** @param array<string,mixed> $service */
    private function assertOfferMatches(array $service, string $expectedPrice, int $expectedDurationDays): void
    {
        try {
            $expectedPrice = $this->normalizePrice($expectedPrice);
        } catch (RuntimeException) {
            throw new DomainException('Las condiciones del servicio cambiaron; recarga la página antes de continuar.');
        }
        if ($expectedDurationDays <= 0
            || $expectedPrice !== $service['current_price']
            || $expectedDurationDays !== (int) $service['duration_days']
        ) {
            throw new DomainException('Las condiciones del servicio cambiaron; recarga la página antes de continuar.');
        }
    }

    private function assertNoOverlap(int $serviceId, int $leaseId, string $startsOn, string $endsOn): void
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM service_memberships '
            . 'WHERE service_id = :service_id AND lease_id = :lease_id AND ended_at IS NULL '
            . 'AND starts_on <= :ends_on AND ends_on >= :starts_on LIMIT 1 FOR UPDATE',
        );
        $statement->execute([
            'service_id' => $serviceId,
            'lease_id' => $leaseId,
            'ends_on' => $endsOn,
            'starts_on' => $startsOn,
        ]);
        if ($statement->fetchColumn() !== false) {
            throw new RuntimeException('Ya existe una membresía activa de este servicio para el período solicitado.');
        }
    }

    /** @return array<string,mixed> */
    private function requireMembership(int $membershipId): array
    {
        $membership = $this->findMembership($membershipId);
        if ($membership === null) {
            throw new RuntimeException('No fue posible cargar la membresía creada.');
        }
        return $membership;
    }

    private function validateMembershipRequest(int $serviceId, int $leaseId, int $tenantUserId, string $today, int $now): void
    {
        $this->validateDate($today);
        if ($serviceId <= 0 || $leaseId <= 0 || $tenantUserId <= 0 || $now <= 0) {
            throw new RuntimeException('Los datos de la membresía no son válidos.');
        }
    }

    private function validateDate(string $date): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date || $date < '1000-01-01' || $date > '9999-12-31'
        ) {
            throw new RuntimeException('La fecha de la membresía no es válida.');
        }
    }

    private function endDate(string $startsOn, int $durationDays): string
    {
        $this->validateDate($startsOn);
        try {
            $date = (new \DateTimeImmutable($startsOn))->modify('+' . ($durationDays - 1) . ' days');
        } catch (Throwable) {
            throw new RuntimeException('El período de la membresía excede el rango permitido.');
        }
        $endsOn = $date->format('Y-m-d');
        if (strlen($endsOn) !== 10 || $endsOn < $startsOn) {
            throw new RuntimeException('El período de la membresía excede el rango permitido.');
        }
        return $endsOn;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeMembership(array $row): array
    {
        $price = (string) $row['price_snapshot'];
        $decision = $row['charge_decision'] === null ? null : (string) $row['charge_decision'];
        $chargeOutcome = $price === '0.00'
            ? 'not_required'
            : match ($decision) {
                'create' => 'created',
                'skip' => 'skipped',
                default => 'pending',
            };
        $normalized = [
            'id' => (int) $row['id'],
            'service_id' => (int) $row['service_id'],
            'lease_id' => (int) $row['lease_id'],
            'accepted_by_user_id' => (int) $row['accepted_by_user_id'],
            'price_snapshot' => $price,
            'duration_days_snapshot' => (int) $row['duration_days_snapshot'],
            'starts_on' => (string) $row['starts_on'],
            'ends_on' => (string) $row['ends_on'],
            'accepted_at' => (string) $row['accepted_at'],
            'renews_membership_id' => $row['renews_membership_id'] === null ? null : (int) $row['renews_membership_id'],
            'ended_at' => $row['ended_at'] === null ? null : (string) $row['ended_at'],
            'charge_decision' => $decision,
            'charge_decided_by_user_id' => $row['charge_decided_by_user_id'] === null ? null : (int) $row['charge_decided_by_user_id'],
            'charge_decided_at' => $row['charge_decided_at'] === null ? null : (string) $row['charge_decided_at'],
            'charge_id' => $row['charge_id'] === null ? null : (int) $row['charge_id'],
            'charge_decision_required' => $price !== '0.00' && $decision === null,
            'charge_outcome' => $chargeOutcome,
        ];
        if (isset($row['service_name'])) {
            $normalized['service_name'] = (string) $row['service_name'];
        }
        return $normalized;
    }

    /** @return array{string,string,string} */
    private function validateService(
        string $name,
        string $description,
        string $price,
        int $durationDays,
        int $actorUserId,
        int $now,
    ): array {
        $name = trim($name);
        $description = trim($description);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120
            || preg_match('/[\x00-\x1F\x7F]/', $name) === 1
            || $description === '' || mb_strlen($description, 'UTF-8') > 1000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $description) === 1
            || $durationDays < 1 || $durationDays > self::MAX_DURATION_DAYS
            || $actorUserId <= 0 || $now <= 0
        ) {
            throw new RuntimeException('Los datos del servicio no son válidos.');
        }
        return [$name, $description, $this->normalizePrice($price)];
    }

    private function normalizePrice(string $price): string
    {
        $price = trim($price);
        if (!preg_match('/\A\d+(?:\.\d{1,2})?\z/', $price)) {
            throw new RuntimeException('El precio del servicio no es válido.');
        }
        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 10) {
            throw new RuntimeException('El precio del servicio no es válido.');
        }
        return $whole . '.' . str_pad($fraction, 2, '0');
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeService(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'description' => (string) $row['description'],
            'current_price' => (string) $row['current_price'],
            'duration_days' => (int) $row['duration_days'],
            'active' => (bool) $row['active'],
            'created_by_user_id' => $row['created_by_user_id'] === null ? null : (int) $row['created_by_user_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
        ];
    }
}
