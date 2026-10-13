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
}
