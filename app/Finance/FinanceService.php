<?php

declare(strict_types=1);

namespace AlquilaPro\Finance;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final readonly class FinanceService
{
    private const MANUAL_TYPES = ['service', 'fine', 'repair', 'other'];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{id:int,lease_id:int,type:string,period_month:?string,due_date:string,amount:string,description:string,created_by_user_id:int} */
    public function createManualCharge(
        int $leaseId,
        string $type,
        string $amount,
        string $dueDate,
        string $description,
        ?string $periodMonth,
        int $actorUserId,
    ): array {
        if ($leaseId <= 0 || $actorUserId <= 0 || !in_array($type, self::MANUAL_TYPES, true)) {
            throw new RuntimeException('No fue posible registrar el cargo.');
        }

        $normalizedAmount = $this->normalizeAmount($amount);
        $normalizedDueDate = $this->normalizeDate($dueDate);
        $normalizedPeriod = $this->normalizePeriod($periodMonth);
        $normalizedDescription = trim($description);
        if ($normalizedDescription === '' || mb_strlen($normalizedDescription, 'UTF-8') > 500) {
            throw new RuntimeException('No fue posible registrar el cargo.');
        }

        try {
            $lease = $this->pdo->prepare('SELECT id FROM leases WHERE id = :id LIMIT 1');
            $lease->execute(['id' => $leaseId]);
            if ($lease->fetchColumn() === false) {
                throw new RuntimeException('No fue posible registrar el cargo.');
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO charges '
                . '(lease_id, type, period_month, due_date, amount, description, created_by_user_id) '
                . 'VALUES (:lease_id, :type, :period_month, :due_date, :amount, :description, :created_by_user_id)',
            );
            $insert->execute([
                'lease_id' => $leaseId,
                'type' => $type,
                'period_month' => $normalizedPeriod,
                'due_date' => $normalizedDueDate,
                'amount' => $normalizedAmount,
                'description' => $normalizedDescription,
                'created_by_user_id' => $actorUserId,
            ]);

            return [
                'id' => (int) $this->pdo->lastInsertId(),
                'lease_id' => $leaseId,
                'type' => $type,
                'period_month' => $normalizedPeriod,
                'due_date' => $normalizedDueDate,
                'amount' => $normalizedAmount,
                'description' => $normalizedDescription,
                'created_by_user_id' => $actorUserId,
            ];
        } catch (Throwable) {
            throw new RuntimeException('No fue posible registrar el cargo.');
        }
    }

    /** @return list<array{id:int,lease_id:int,contract_id:int,type:string,period_month:?string,due_date:string,amount:string,description:?string,state:string,days_overdue:int}> */
    public function listForLease(int $leaseId, string $today): array
    {
        if ($leaseId <= 0) {
            return [];
        }

        $todayDate = $this->parseDate($today);
        $statement = $this->pdo->prepare(
            'SELECT ch.id, ch.lease_id, l.contract_id, ch.type, ch.period_month, ch.due_date, '
            . 'ch.amount, ch.description, ch.voided_at '
            . 'FROM charges ch INNER JOIN leases l ON l.id = ch.lease_id '
            . 'WHERE ch.lease_id = :lease_id ORDER BY ch.due_date ASC, ch.id ASC',
        );
        $statement->execute(['lease_id' => $leaseId]);

        $charges = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dueDate = $this->parseDate((string) $row['due_date']);
            $voided = $row['voided_at'] !== null;
            $overdue = !$voided && $dueDate < $todayDate;

            $charges[] = [
                'id' => (int) $row['id'],
                'lease_id' => (int) $row['lease_id'],
                'contract_id' => (int) $row['contract_id'],
                'type' => (string) $row['type'],
                'period_month' => $row['period_month'] === null ? null : (string) $row['period_month'],
                'due_date' => (string) $row['due_date'],
                'amount' => (string) $row['amount'],
                'description' => $row['description'] === null ? null : (string) $row['description'],
                'state' => $voided ? 'voided' : ($overdue ? 'overdue' : 'pending'),
                'days_overdue' => $overdue ? (int) $dueDate->diff($todayDate)->format('%a') : 0,
            ];
        }

        return $charges;
    }

    /** @return list<array{id:int,lease_id:int,type:string,period_month:string,due_date:string,amount:string,description:null}> */
    public function createMonthlyCharges(int $leaseId, string $periodMonth, string $dueDate, int $actorUserId): array
    {
        if ($leaseId <= 0 || $actorUserId <= 0 || $this->pdo->inTransaction()) {
            throw new RuntimeException('No fue posible generar los cargos mensuales.');
        }

        try {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('No fue posible generar los cargos mensuales.');
            }

            $leaseLock = $this->pdo->prepare(
                'SELECT id, contract_id FROM leases WHERE id = :id FOR UPDATE',
            );
            $leaseLock->execute(['id' => $leaseId]);
            $lease = $leaseLock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($lease)) {
                throw new RuntimeException('No fue posible generar los cargos mensuales.');
            }

            $contractStatement = $this->pdo->prepare(
                'SELECT monthly_rent, administration_fee, start_date, end_date '
                . 'FROM contracts WHERE id = :id LIMIT 1',
            );
            $contractStatement->execute(['id' => (int) $lease['contract_id']]);
            $contract = $contractStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($contract)) {
                throw new RuntimeException('No fue posible generar los cargos mensuales.');
            }

            $period = $this->normalizePeriod($periodMonth);
            if ($period === null) {
                throw new RuntimeException('No fue posible generar los cargos mensuales.');
            }
            $due = $this->normalizeDate($dueDate);
            $firstContractMonth = $this->parseDate((string) $contract['start_date'])->format('Y-m-01');
            $lastContractMonth = $this->parseDate((string) $contract['end_date'])->format('Y-m-01');
            if ($period < $firstContractMonth || $period > $lastContractMonth) {
                throw new RuntimeException('No fue posible generar los cargos mensuales.');
            }

            $expectedAmounts = ['rent' => $this->normalizeAmount((string) $contract['monthly_rent'])];
            if (
                $contract['administration_fee'] !== null
                && $this->isPositiveAmount((string) $contract['administration_fee'])
            ) {
                $expectedAmounts['administration'] = $this->normalizeAmount((string) $contract['administration_fee']);
            }

            $active = $this->pdo->prepare(
                'SELECT type FROM charges '
                . 'WHERE lease_id = :lease_id AND period_month = :period_month '
                . "AND type IN ('rent', 'administration') AND voided_at IS NULL",
            );
            $active->execute(['lease_id' => $leaseId, 'period_month' => $period]);
            $activeTypes = array_fill_keys(array_map('strval', $active->fetchAll(PDO::FETCH_COLUMN)), true);

            $missing = [];
            foreach ($expectedAmounts as $type => $amount) {
                if (!isset($activeTypes[$type])) {
                    $missing[$type] = $amount;
                }
            }
            if ($missing === []) {
                throw new RuntimeException('Los cargos de este período ya están registrados.');
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO charges '
                . '(lease_id, type, period_month, due_date, amount, created_by_user_id) '
                . 'VALUES (:lease_id, :type, :period_month, :due_date, :amount, :created_by_user_id)',
            );
            $created = [];
            foreach ($missing as $type => $amount) {
                $insert->execute([
                    'lease_id' => $leaseId,
                    'type' => $type,
                    'period_month' => $period,
                    'due_date' => $due,
                    'amount' => $amount,
                    'created_by_user_id' => $actorUserId,
                ]);
                $created[] = [
                    'id' => (int) $this->pdo->lastInsertId(),
                    'lease_id' => $leaseId,
                    'type' => $type,
                    'period_month' => $period,
                    'due_date' => $due,
                    'amount' => $amount,
                    'description' => null,
                ];
            }

            $this->pdo->commit();

            return $created;
        } catch (RuntimeException $exception) {
            $this->rollbackIfNeeded();
            throw $exception;
        } catch (Throwable) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible generar los cargos mensuales.');
        }
    }

    /** @return array<string, int|string|null> */
    public function voidCharge(int $chargeId, int $actorUserId, string $reason, int $now): array
    {
        if ($chargeId <= 0 || $actorUserId <= 0 || $this->pdo->inTransaction()) {
            throw new RuntimeException('No fue posible anular el cargo.');
        }

        $normalizedReason = trim($reason);

        try {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('No fue posible anular el cargo.');
            }

            $chargeStatement = $this->pdo->prepare('SELECT * FROM charges WHERE id = :id FOR UPDATE');
            $chargeStatement->execute(['id' => $chargeId]);
            $charge = $chargeStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($charge) || $charge['voided_at'] !== null) {
                throw new RuntimeException('No fue posible anular el cargo.');
            }
            if ($normalizedReason === '' || mb_strlen($normalizedReason, 'UTF-8') > 500) {
                throw new RuntimeException('No fue posible anular el cargo.');
            }

            $voidedAt = gmdate('Y-m-d H:i:s', $now);
            $update = $this->pdo->prepare(
                'UPDATE charges SET voided_by_user_id = :actor_id, voided_at = :voided_at, '
                . 'void_reason = :reason WHERE id = :id AND voided_at IS NULL',
            );
            $update->execute([
                'actor_id' => $actorUserId,
                'voided_at' => $voidedAt,
                'reason' => $normalizedReason,
                'id' => $chargeId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('No fue posible anular el cargo.');
            }

            $this->pdo->commit();

            $charge['voided_by_user_id'] = $actorUserId;
            $charge['voided_at'] = $voidedAt;
            $charge['void_reason'] = $normalizedReason;

            return $charge;
        } catch (RuntimeException $exception) {
            $this->rollbackIfNeeded();
            throw $exception;
        } catch (Throwable) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible anular el cargo.');
        }
    }

    /** @return array{outstanding:string,overdue:string,oldest_due_date:?string} */
    public function summaryForLease(int $leaseId, string $today): array
    {
        $this->parseDate($today);
        if ($leaseId <= 0) {
            return ['outstanding' => '0.00', 'overdue' => '0.00', 'oldest_due_date' => null];
        }

        $statement = $this->pdo->prepare(
            'SELECT ' . $this->chargeSummaryExpressions('ch') . ' '
            . 'FROM charges ch WHERE ch.lease_id = :lease_id',
        );
        $statement->execute(['lease_id' => $leaseId, 'today' => $today]);
        $summary = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($summary)) {
            return ['outstanding' => '0.00', 'overdue' => '0.00', 'oldest_due_date' => null];
        }

        return [
            'outstanding' => $this->normalizeTotal($summary['outstanding'] ?? null),
            'overdue' => $this->normalizeTotal($summary['overdue'] ?? null),
            'oldest_due_date' => $summary['oldest_due_date'] === null
                ? null
                : (string) $summary['oldest_due_date'],
        ];
    }

    /** @return list<array{lease_id:int,tenant_id:int,tenant_name:string,contract_id:int,unit_id:int,unit_code:string,property_id:int,property_name:string,property_address:string,property_city:string,outstanding:string,overdue:string,oldest_due_date:?string}> */
    public function portfolio(string $today): array
    {
        $this->parseDate($today);
        $statement = $this->pdo->prepare(
            'SELECT l.id AS lease_id, l.user_id AS tenant_id, tenant.full_name AS tenant_name, '
            . 'c.id AS contract_id, unit.id AS unit_id, unit.code AS unit_code, '
            . 'property.id AS property_id, property.name AS property_name, '
            . 'property.address AS property_address, property.city AS property_city, '
            . $this->chargeSummaryExpressions('ch') . ' '
            . 'FROM charges ch '
            . 'INNER JOIN leases l ON l.id = ch.lease_id '
            . 'INNER JOIN contracts c ON c.id = l.contract_id '
            . 'INNER JOIN users tenant ON tenant.id = l.user_id '
            . 'INNER JOIN units unit ON unit.id = c.unit_id '
            . 'INNER JOIN properties property ON property.id = unit.property_id '
            . 'GROUP BY l.id, l.user_id, tenant.full_name, c.id, unit.id, unit.code, '
            . 'property.id, property.name, property.address, property.city '
            . 'ORDER BY property.name, unit.code, tenant.full_name, l.id',
        );
        $statement->execute(['today' => $today]);

        $portfolio = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $portfolio[] = [
                'lease_id' => (int) $row['lease_id'],
                'tenant_id' => (int) $row['tenant_id'],
                'tenant_name' => (string) $row['tenant_name'],
                'contract_id' => (int) $row['contract_id'],
                'unit_id' => (int) $row['unit_id'],
                'unit_code' => (string) $row['unit_code'],
                'property_id' => (int) $row['property_id'],
                'property_name' => (string) $row['property_name'],
                'property_address' => (string) $row['property_address'],
                'property_city' => (string) $row['property_city'],
                'outstanding' => $this->normalizeTotal($row['outstanding'] ?? null),
                'overdue' => $this->normalizeTotal($row['overdue'] ?? null),
                'oldest_due_date' => $row['oldest_due_date'] === null
                    ? null
                    : (string) $row['oldest_due_date'],
            ];
        }

        return $portfolio;
    }

    private function normalizeAmount(string $amount): string
    {
        $amount = trim($amount);
        if (!preg_match('/\A\d+(?:\.\d{1,2})?\z/', $amount)) {
            throw new RuntimeException('No fue posible registrar el cargo.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 10) {
            throw new RuntimeException('No fue posible registrar el cargo.');
        }

        $normalized = $whole . '.' . str_pad($fraction, 2, '0');
        if ($normalized === '0.00') {
            throw new RuntimeException('No fue posible registrar el cargo.');
        }

        return $normalized;
    }

    private function isPositiveAmount(string $amount): bool
    {
        $amount = trim($amount);
        if (!preg_match('/\A\d+(?:\.\d{1,2})?\z/', $amount)) {
            return false;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ltrim($whole, '0') !== '' || trim($fraction, '0') !== '';
    }

    private function chargeSummaryExpressions(string $alias): string
    {
        return 'COALESCE(SUM(CASE WHEN ' . $alias . '.voided_at IS NULL '
            . 'THEN ' . $alias . '.amount ELSE 0 END), 0.00) AS outstanding, '
            . 'COALESCE(SUM(CASE WHEN ' . $alias . '.voided_at IS NULL '
            . 'AND ' . $alias . '.due_date < :today THEN ' . $alias . '.amount ELSE 0 END), 0.00) AS overdue, '
            . 'MIN(CASE WHEN ' . $alias . '.voided_at IS NULL THEN ' . $alias . '.due_date ELSE NULL END) '
            . 'AS oldest_due_date';
    }

    private function normalizeTotal(mixed $amount): string
    {
        $amount = $amount === null ? '0' : (string) $amount;
        if (!preg_match('/\A\d+(?:\.\d{1,2})?\z/', $amount)) {
            throw new RuntimeException('No fue posible calcular el saldo.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0');

        return ($whole === '' ? '0' : $whole) . '.' . str_pad($fraction, 2, '0');
    }

    private function normalizeDate(string $date): string
    {
        $this->parseDate($date);

        return $date;
    }

    private function normalizePeriod(?string $periodMonth): ?string
    {
        if ($periodMonth === null) {
            return null;
        }
        if (!preg_match('/\A\d{4}-\d{2}-01\z/', $periodMonth)) {
            throw new RuntimeException('No fue posible registrar el cargo.');
        }

        $this->parseDate($periodMonth);

        return $periodMonth;
    }

    private function parseDate(string $date): DateTimeImmutable
    {
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) {
            throw new RuntimeException('No fue posible registrar el cargo.');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $parsed === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date
            || (int) $parsed->format('Y') < 1000
        ) {
            throw new RuntimeException('No fue posible registrar el cargo.');
        }

        return $parsed;
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
