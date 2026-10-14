<?php

declare(strict_types=1);

namespace AlquilaPro\Finance;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

final readonly class FinanceService
{
    private const MANUAL_TYPES = ['service', 'fine', 'repair', 'other'];
    private const PAYMENT_METHOD_SNAPSHOT_FIELDS = [
        'type',
        'label',
        'recipient_name',
        'institution_name',
        'account_type',
        'account_number',
        'breb_key_type',
        'breb_key_value',
        'wallet_identifier',
        'instructions',
        'property_id',
    ];
    private const MAX_PAYMENT_RECEIPT_BYTES = 5 * 1024 * 1024;
    private const PAYMENT_RECEIPT_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    private PaymentMethodService $paymentMethods;

    public function __construct(
        private PDO $pdo,
        ?PaymentMethodService $paymentMethods = null,
        private string $receiptDirectory = '',
    ) {
        $this->paymentMethods = $paymentMethods ?? new PaymentMethodService($pdo, '');
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

    /** @return list<array{id:int,lease_id:int,contract_id:int,type:string,period_month:?string,due_date:string,amount:string,description:?string,state:string,allocated:string,outstanding:string,is_overdue:bool,days_overdue:int}> */
    public function listForLease(int $leaseId, string $today): array
    {
        if ($leaseId <= 0) {
            return [];
        }

        $todayDate = $this->parseDate($today);
        $statement = $this->pdo->prepare(
            'SELECT ch.id, ch.lease_id, l.contract_id, ch.type, ch.period_month, ch.due_date, '
            . 'ch.amount, ch.description, ch.voided_at, '
            . $this->reconciledAllocationExpression('ch') . ' AS allocated, '
            . $this->chargeOutstandingExpression('ch') . ' AS outstanding, '
            . 'EXISTS (SELECT 1 FROM payment_allocations pa INNER JOIN payments p ON p.id = pa.payment_id '
            . 'WHERE pa.charge_id = ch.id AND p.reconciled_at IS NULL AND p.rejected_at IS NULL '
            . 'AND p.receipt_stored_name IS NOT NULL) AS has_pending_receipt, '
            . 'EXISTS (SELECT 1 FROM payment_allocations pa INNER JOIN payments p ON p.id = pa.payment_id '
            . 'WHERE pa.charge_id = ch.id AND p.reconciled_at IS NULL AND p.rejected_at IS NULL) AS has_pending_report '
            . 'FROM charges ch INNER JOIN leases l ON l.id = ch.lease_id '
            . 'WHERE ch.lease_id = :lease_id ORDER BY ch.due_date ASC, ch.id ASC',
        );
        $statement->execute(['lease_id' => $leaseId]);

        $charges = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dueDate = $this->parseDate((string) $row['due_date']);
            $voided = $row['voided_at'] !== null;
            $allocated = $this->normalizeTotal($row['allocated'] ?? null);
            $outstanding = $this->normalizeTotal($row['outstanding'] ?? null);
            $allocatedCents = $this->paymentAmountToCents($allocated);
            $outstandingCents = $this->paymentAmountToCents($outstanding);
            $overdue = !$voided && $outstandingCents > 0 && $dueDate < $todayDate;
            $state = match (true) {
                $voided => 'voided',
                $outstandingCents === 0 => 'paid',
                $allocatedCents > 0 => 'partial',
                (bool) $row['has_pending_receipt'] => 'proof_received',
                (bool) $row['has_pending_report'] => 'reported',
                $overdue => 'overdue',
                default => 'pending',
            };

            $charges[] = [
                'id' => (int) $row['id'],
                'lease_id' => (int) $row['lease_id'],
                'contract_id' => (int) $row['contract_id'],
                'type' => (string) $row['type'],
                'period_month' => $row['period_month'] === null ? null : (string) $row['period_month'],
                'due_date' => (string) $row['due_date'],
                'amount' => (string) $row['amount'],
                'description' => $row['description'] === null ? null : (string) $row['description'],
                'state' => $state,
                'allocated' => $allocated,
                'outstanding' => $outstanding,
                'is_overdue' => $overdue,
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
            . 'charge_summary.outstanding, charge_summary.overdue, charge_summary.oldest_due_date '
            . 'FROM (SELECT ch.lease_id, ' . $this->chargeSummaryExpressions('ch') . ' '
            . 'FROM charges ch GROUP BY ch.lease_id) charge_summary '
            . 'INNER JOIN leases l ON l.id = charge_summary.lease_id '
            . 'INNER JOIN contracts c ON c.id = l.contract_id '
            . 'INNER JOIN users tenant ON tenant.id = l.user_id '
            . 'INNER JOIN units unit ON unit.id = c.unit_id '
            . 'INNER JOIN properties property ON property.id = unit.property_id '
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

    /**
     * @param list<array{charge_id:int,amount:string}> $allocations
     * @return array<string,mixed>
     */
    public function reportPayment(
        int $leaseId,
        int $paymentMethodId,
        string $amount,
        string $paidOn,
        ?string $paidTime,
        array $allocations,
        mixed $receipt,
        int $now,
    ): array {
        if ($leaseId <= 0 || $paymentMethodId <= 0 || $now <= 0) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        $amount = $this->normalizePaymentAmount($amount);
        $amountCents = $this->paymentAmountToCents($amount);
        $paidOn = $this->normalizePaymentDate($paidOn, $now);
        $paidTime = $this->normalizePaymentTime($paidTime);

        $propertyStatement = $this->pdo->prepare(
            'SELECT property.id AS property_id FROM leases l '
            . 'INNER JOIN contracts c ON c.id = l.contract_id '
            . 'INNER JOIN units u ON u.id = c.unit_id '
            . 'INNER JOIN properties property ON property.id = u.property_id '
            . 'WHERE l.id = :lease_id LIMIT 1',
        );
        $propertyStatement->execute(['lease_id' => $leaseId]);
        $propertyId = $propertyStatement->fetchColumn();
        if ($propertyId === false) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        $paymentMethod = null;
        foreach ($this->paymentMethods->listForProperty((int) $propertyId) as $candidate) {
            if ((int) ($candidate['id'] ?? 0) === $paymentMethodId) {
                $paymentMethod = $candidate;
                break;
            }
        }
        if ($paymentMethod === null) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        $snapshot = [];
        foreach (self::PAYMENT_METHOD_SNAPSHOT_FIELDS as $field) {
            $value = $paymentMethod[$field] ?? null;
            if ($value !== null && (is_string($value) || is_int($value))) {
                $snapshot[$field] = $value;
            }
        }
        try {
            $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $exception) {
            throw new RuntimeException('No fue posible reportar el pago.', 0, $exception);
        }

        $normalizedAllocations = $this->validateProposedAllocations($leaseId, $allocations, $amountCents);
        $storedReceipt = $receipt === null ? null : $this->storePaymentReceipt($receipt);
        $reportedAt = (new DateTimeImmutable('@' . $now))
            ->setTimezone(new DateTimeZone('America/Bogota'))
            ->format('Y-m-d H:i:s');

        try {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('No fue posible reportar el pago.');
            }
            $insertPayment = $this->pdo->prepare(
                'INSERT INTO payments '
                . '(lease_id, payment_method_id, amount, paid_on, paid_time, payment_method_snapshot_json, reported_at, '
                . 'receipt_stored_name, receipt_mime_type, receipt_sha256, receipt_size_bytes) '
                . 'VALUES (:lease_id, :payment_method_id, :amount, :paid_on, :paid_time, :snapshot, :reported_at, '
                . ':receipt_name, :receipt_mime, :receipt_sha256, :receipt_size)',
            );
            $insertPayment->execute([
                'lease_id' => $leaseId,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'paid_on' => $paidOn,
                'paid_time' => $paidTime,
                'snapshot' => $snapshotJson,
                'reported_at' => $reportedAt,
                'receipt_name' => $storedReceipt['stored_name'] ?? null,
                'receipt_mime' => $storedReceipt['mime'] ?? null,
                'receipt_sha256' => $storedReceipt['sha256'] ?? null,
                'receipt_size' => $storedReceipt['size_bytes'] ?? null,
            ]);
            $paymentId = (int) $this->pdo->lastInsertId();
            $insertAllocation = $this->pdo->prepare(
                'INSERT INTO payment_allocations (payment_id, charge_id, amount) '
                . 'VALUES (:payment_id, :charge_id, :amount)',
            );
            foreach ($normalizedAllocations as $allocation) {
                $insertAllocation->execute([
                    'payment_id' => $paymentId,
                    'charge_id' => $allocation['charge_id'],
                    'amount' => $allocation['amount'],
                ]);
            }
            if (!$this->pdo->commit()) {
                throw new RuntimeException('No fue posible reportar el pago.');
            }
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($storedReceipt !== null) {
                $this->removePaymentReceipt($storedReceipt['stored_name']);
            }
            throw new RuntimeException('No fue posible reportar el pago.', 0, $exception);
        }

        $payment = $this->findPayment($paymentId);
        if ($payment === null) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        return $payment;
    }

    /** @return list<array<string,mixed>> */
    public function listPaymentsForLease(int $leaseId): array
    {
        if ($leaseId <= 0) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT * FROM payments WHERE lease_id = :lease_id ORDER BY reported_at DESC, id DESC',
        );
        $statement->execute(['lease_id' => $leaseId]);

        return array_map(fn (array $row): array => $this->paymentProjection($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string,mixed>> */
    public function listPendingPayments(): array
    {
        $statement = $this->pdo->query(
            'SELECT p.*, tenant.full_name AS tenant_name, property.name AS property_name, unit.code AS unit_code '
            . 'FROM payments p '
            . 'INNER JOIN leases l ON l.id = p.lease_id '
            . 'INNER JOIN contracts c ON c.id = l.contract_id '
            . 'INNER JOIN units unit ON unit.id = c.unit_id '
            . 'INNER JOIN properties property ON property.id = unit.property_id '
            . 'INNER JOIN users tenant ON tenant.id = l.user_id '
            . 'WHERE p.reconciled_at IS NULL AND p.rejected_at IS NULL '
            . 'ORDER BY p.reported_at ASC, p.id ASC',
        );
        $payments = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $payment = $this->paymentProjection($row);
            $payment['duplicate_warnings'] = $this->duplicateWarnings($row);
            $payments[] = $payment;
        }

        return $payments;
    }

    /** @return array<string,mixed>|null */
    public function findPayment(int $paymentId): ?array
    {
        if ($paymentId <= 0) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT p.*, tenant.full_name AS tenant_name, property.name AS property_name, unit.code AS unit_code '
            . 'FROM payments p '
            . 'INNER JOIN leases l ON l.id = p.lease_id '
            . 'INNER JOIN contracts c ON c.id = l.contract_id '
            . 'INNER JOIN units unit ON unit.id = c.unit_id '
            . 'INNER JOIN properties property ON property.id = unit.property_id '
            . 'INNER JOIN users tenant ON tenant.id = l.user_id '
            . 'WHERE p.id = :id LIMIT 1',
        );
        $statement->execute(['id' => $paymentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $payment = $this->paymentProjection($row);
        $payment['duplicate_warnings'] = $this->duplicateWarnings($row);

        return $payment;
    }

    /** @param list<array{charge_id:int,amount:string}> $finalAllocations
     *  @return array<string,mixed>
     */
    public function reconcilePayment(int $paymentId, array $finalAllocations, int $actorUserId, int $now): array
    {
        if ($paymentId <= 0 || $actorUserId <= 0 || $now <= 0 || $this->pdo->inTransaction()) {
            throw new RuntimeException('No fue posible conciliar el pago.');
        }

        try {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('No fue posible conciliar el pago.');
            }
            $paymentLock = $this->pdo->prepare('SELECT * FROM payments WHERE id = :id FOR UPDATE');
            $paymentLock->execute(['id' => $paymentId]);
            $payment = $paymentLock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($payment) || $payment['reconciled_at'] !== null || $payment['rejected_at'] !== null) {
                throw new RuntimeException('No fue posible conciliar el pago.');
            }
            $leaseStatement = $this->pdo->prepare('SELECT id FROM leases WHERE id = :id LIMIT 1');
            $leaseStatement->execute(['id' => (int) $payment['lease_id']]);
            if ($leaseStatement->fetchColumn() === false) {
                throw new RuntimeException('No fue posible conciliar el pago.');
            }

            if (!array_is_list($finalAllocations) || $finalAllocations === []) {
                throw new RuntimeException('No fue posible conciliar el pago.');
            }
            $chargeIds = [];
            foreach ($finalAllocations as $allocation) {
                if (!is_array($allocation) || !isset($allocation['charge_id'])
                    || !is_int($allocation['charge_id']) || $allocation['charge_id'] <= 0
                ) {
                    throw new RuntimeException('No fue posible conciliar el pago.');
                }
                $chargeIds[] = $allocation['charge_id'];
            }
            $chargeIds = array_values(array_unique($chargeIds));
            sort($chargeIds, SORT_NUMERIC);
            $lockParameters = [];
            $placeholders = [];
            foreach ($chargeIds as $index => $chargeId) {
                $name = 'charge_' . $index;
                $placeholders[] = ':' . $name;
                $lockParameters[$name] = $chargeId;
            }
            $chargeLock = $this->pdo->prepare(
                'SELECT id, lease_id, amount, voided_at FROM charges WHERE id IN (' . implode(', ', $placeholders) . ') '
                . 'ORDER BY id ASC FOR UPDATE',
            );
            $chargeLock->execute($lockParameters);
            $lockedCharges = $chargeLock->fetchAll(PDO::FETCH_ASSOC);
            if (count($lockedCharges) !== count($chargeIds)) {
                throw new RuntimeException('No fue posible conciliar el pago.');
            }

            $lockedById = [];
            foreach ($lockedCharges as $lockedCharge) {
                $lockedById[(int) $lockedCharge['id']] = $lockedCharge;
            }
            $normalizedAllocations = $this->validateFinalAllocationsAgainstLockedCharges(
                (int) $payment['lease_id'],
                $finalAllocations,
                $this->paymentAmountToCents((string) $payment['amount']),
                $lockedById,
            );
            usort($normalizedAllocations, static fn (array $first, array $second): int =>
                $first['charge_id'] <=> $second['charge_id']);
            $existingAllocations = $this->pdo->prepare(
                'SELECT charge_id, amount FROM payment_allocations WHERE payment_id = :payment_id ORDER BY charge_id ASC',
            );
            $existingAllocations->execute(['payment_id' => $paymentId]);
            $existing = array_map(static fn (array $allocation): array => [
                'charge_id' => (int) $allocation['charge_id'],
                'amount' => (string) $allocation['amount'],
            ], $existingAllocations->fetchAll(PDO::FETCH_ASSOC));
            if ($existing !== $normalizedAllocations) {
                $delete = $this->pdo->prepare('DELETE FROM payment_allocations WHERE payment_id = :payment_id');
                $delete->execute(['payment_id' => $paymentId]);
                $insert = $this->pdo->prepare(
                    'INSERT INTO payment_allocations (payment_id, charge_id, amount) '
                    . 'VALUES (:payment_id, :charge_id, :amount)',
                );
                foreach ($normalizedAllocations as $allocation) {
                    $insert->execute([
                        'payment_id' => $paymentId,
                        'charge_id' => $allocation['charge_id'],
                        'amount' => $allocation['amount'],
                    ]);
                }
            }

            $terminal = $this->pdo->prepare(
                'UPDATE payments SET reconciled_by_user_id = :actor_id, reconciled_at = :reconciled_at '
                . 'WHERE id = :id AND reconciled_at IS NULL AND rejected_at IS NULL',
            );
            $terminal->execute([
                'actor_id' => $actorUserId,
                'reconciled_at' => $this->paymentDateTime($now),
                'id' => $paymentId,
            ]);
            if ($terminal->rowCount() !== 1 || !$this->pdo->commit()) {
                throw new RuntimeException('No fue posible conciliar el pago.');
            }
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible conciliar el pago.', 0, $exception);
        }

        $result = $this->findPayment($paymentId);
        if ($result === null) {
            throw new RuntimeException('No fue posible conciliar el pago.');
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public function rejectPayment(int $paymentId, string $reason, int $actorUserId, int $now): array
    {
        if ($paymentId <= 0 || $actorUserId <= 0 || $now <= 0 || $this->pdo->inTransaction()) {
            throw new RuntimeException('No fue posible rechazar el pago.');
        }

        try {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('No fue posible rechazar el pago.');
            }
            $paymentLock = $this->pdo->prepare(
                'SELECT id, reconciled_at, rejected_at FROM payments WHERE id = :id FOR UPDATE',
            );
            $paymentLock->execute(['id' => $paymentId]);
            $payment = $paymentLock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($payment) || $payment['reconciled_at'] !== null || $payment['rejected_at'] !== null) {
                throw new RuntimeException('No fue posible rechazar el pago.');
            }
            $normalizedReason = trim($reason);
            if ($normalizedReason === '' || mb_strlen($normalizedReason, 'UTF-8') > 500) {
                throw new RuntimeException('No fue posible rechazar el pago.');
            }
            $terminal = $this->pdo->prepare(
                'UPDATE payments SET rejected_by_user_id = :actor_id, rejected_at = :rejected_at, '
                . 'rejection_reason = :reason WHERE id = :id AND reconciled_at IS NULL AND rejected_at IS NULL',
            );
            $terminal->execute([
                'actor_id' => $actorUserId,
                'rejected_at' => $this->paymentDateTime($now),
                'reason' => $normalizedReason,
                'id' => $paymentId,
            ]);
            if ($terminal->rowCount() !== 1 || !$this->pdo->commit()) {
                throw new RuntimeException('No fue posible rechazar el pago.');
            }
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible rechazar el pago.', 0, $exception);
        }

        $result = $this->findPayment($paymentId);
        if ($result === null) {
            throw new RuntimeException('No fue posible rechazar el pago.');
        }

        return $result;
    }

    /** @return array{path:string,mime:string,sha256:string,size_bytes:int}|null */
    public function privatePaymentReceipt(int $paymentId): ?array
    {
        if ($paymentId <= 0) {
            return null;
        }
        try {
            $statement = $this->pdo->prepare(
                'SELECT receipt_stored_name, receipt_mime_type, receipt_sha256, receipt_size_bytes '
                . 'FROM payments WHERE id = :id LIMIT 1',
            );
            $statement->execute(['id' => $paymentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return null;
        }
        if (!is_array($row)
            || !is_string($row['receipt_stored_name'])
            || !is_string($row['receipt_mime_type'])
            || !is_string($row['receipt_sha256'])
            || !isset(self::PAYMENT_RECEIPT_EXTENSIONS[$row['receipt_mime_type']])
            || preg_match('/\A[a-f0-9]{64}\z/i', $row['receipt_sha256']) !== 1
            || !is_numeric($row['receipt_size_bytes'])
        ) {
            return null;
        }
        $storedSize = (int) $row['receipt_size_bytes'];
        if ($storedSize < 1 || $storedSize > self::MAX_PAYMENT_RECEIPT_BYTES) {
            return null;
        }
        $path = $this->paymentReceiptPath($row['receipt_stored_name'], true);
        if ($path === null) {
            return null;
        }

        $actualSize = @filesize($path);
        $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $actualHash = @hash_file('sha256', $path);
        if (!is_int($actualSize)
            || $actualSize !== $storedSize
            || !is_string($actualMime)
            || $actualMime !== $row['receipt_mime_type']
            || pathinfo($row['receipt_stored_name'], PATHINFO_EXTENSION) !== self::PAYMENT_RECEIPT_EXTENSIONS[$actualMime]
            || !is_string($actualHash)
            || !hash_equals(strtolower($row['receipt_sha256']), $actualHash)
        ) {
            return null;
        }

        return [
            'path' => $path,
            'mime' => $actualMime,
            'sha256' => strtolower($row['receipt_sha256']),
            'size_bytes' => $actualSize,
        ];
    }

    /** @param list<array{charge_id:int,amount:string}> $allocations
     *  @return list<array{charge_id:int,amount:string}>
     */
    private function validateProposedAllocations(int $leaseId, array $allocations, int $paymentCents): array
    {
        if (!array_is_list($allocations) || $allocations === []) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        $seen = [];
        $normalized = [];
        $totalCents = 0;
        foreach ($allocations as $allocation) {
            if (!is_array($allocation)
                || !isset($allocation['charge_id'], $allocation['amount'])
                || !is_int($allocation['charge_id'])
                || $allocation['charge_id'] <= 0
                || !is_string($allocation['amount'])
                || isset($seen[$allocation['charge_id']])
            ) {
                throw new RuntimeException('No fue posible reportar el pago.');
            }
            $chargeId = $allocation['charge_id'];
            $seen[$chargeId] = true;
            $allocationAmount = $this->normalizePaymentAmount($allocation['amount']);
            $allocationCents = $this->paymentAmountToCents($allocationAmount);

            $charge = $this->pdo->prepare(
                'SELECT ch.id, ch.amount, ch.voided_at, ' . $this->chargeOutstandingExpression('ch') . ' AS outstanding '
                . 'FROM charges ch WHERE ch.id = :id AND ch.lease_id = :lease_id LIMIT 1',
            );
            $charge->execute(['id' => $chargeId, 'lease_id' => $leaseId]);
            $chargeRow = $charge->fetch(PDO::FETCH_ASSOC);
            if (!is_array($chargeRow) || $chargeRow['voided_at'] !== null) {
                throw new RuntimeException('No fue posible reportar el pago.');
            }

            $outstandingCents = $this->paymentAmountToCents($this->normalizeTotal($chargeRow['outstanding']));
            if ($allocationCents <= 0 || $allocationCents > $outstandingCents) {
                throw new RuntimeException('No fue posible reportar el pago.');
            }
            $totalCents += $allocationCents;
            $normalized[] = ['charge_id' => $chargeId, 'amount' => $allocationAmount];
        }

        if ($totalCents !== $paymentCents) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        return $normalized;
    }

    /** @param array<int,array{id:mixed,lease_id:mixed,amount:mixed,voided_at:mixed}> $lockedCharges
     *  @return list<array{charge_id:int,amount:string}>
     */
    private function validateFinalAllocationsAgainstLockedCharges(
        int $leaseId,
        array $allocations,
        int $paymentCents,
        array $lockedCharges,
    ): array {
        $seen = [];
        $normalized = [];
        $totalCents = 0;
        foreach ($allocations as $allocation) {
            if (!is_array($allocation)
                || !isset($allocation['charge_id'], $allocation['amount'])
                || !is_int($allocation['charge_id'])
                || $allocation['charge_id'] <= 0
                || !is_string($allocation['amount'])
                || isset($seen[$allocation['charge_id']])
            ) {
                throw new RuntimeException('No fue posible conciliar el pago.');
            }
            $chargeId = $allocation['charge_id'];
            $seen[$chargeId] = true;
            $charge = $lockedCharges[$chargeId] ?? null;
            if (!is_array($charge)
                || (int) $charge['lease_id'] !== $leaseId
                || $charge['voided_at'] !== null
            ) {
                throw new RuntimeException('No fue posible conciliar el pago.');
            }
            $amount = $this->normalizePaymentAmount($allocation['amount']);
            $amountCents = $this->paymentAmountToCents($amount);
            $reconciled = $this->pdo->prepare(
                'SELECT pa.amount FROM payment_allocations pa '
                . 'INNER JOIN payments p ON p.id = pa.payment_id '
                . 'WHERE pa.charge_id = :charge_id AND p.reconciled_at IS NOT NULL AND p.rejected_at IS NULL '
                . 'LOCK IN SHARE MODE',
            );
            $reconciled->execute(['charge_id' => $chargeId]);
            $alreadyAllocatedCents = 0;
            foreach ($reconciled->fetchAll(PDO::FETCH_COLUMN) as $allocated) {
                $alreadyAllocatedCents += $this->paymentAmountToCents((string) $allocated);
            }
            $outstandingCents = max(
                0,
                $this->paymentAmountToCents((string) $charge['amount']) - $alreadyAllocatedCents,
            );
            if ($amountCents <= 0 || $amountCents > $outstandingCents) {
                throw new RuntimeException('No fue posible conciliar el pago.');
            }
            $totalCents += $amountCents;
            $normalized[] = ['charge_id' => $chargeId, 'amount' => $amount];
        }
        if ($totalCents !== $paymentCents) {
            throw new RuntimeException('No fue posible conciliar el pago.');
        }

        return $normalized;
    }

    /** @return array<string,mixed> */
    private function paymentProjection(array $row): array
    {
        $snapshot = json_decode((string) $row['payment_method_snapshot_json'], true);
        if (!is_array($snapshot)) {
            $snapshot = [];
        }
        $allocations = $this->pdo->prepare(
            'SELECT charge_id, amount FROM payment_allocations WHERE payment_id = :payment_id ORDER BY charge_id ASC',
        );
        $allocations->execute(['payment_id' => (int) $row['id']]);
        $state = $row['reconciled_at'] !== null
            ? 'reconciled'
            : ($row['rejected_at'] !== null ? 'rejected' : 'pending_reconciliation');

        $projection = [
            'id' => (int) $row['id'],
            'lease_id' => (int) $row['lease_id'],
            'payment_method_id' => (int) $row['payment_method_id'],
            'amount' => $this->normalizeTotal($row['amount']),
            'paid_on' => (string) $row['paid_on'],
            'paid_time' => $row['paid_time'] === null ? null : (string) $row['paid_time'],
            'payment_method_snapshot' => $snapshot,
            'reported_at' => (string) $row['reported_at'],
            'state' => $state,
            'has_receipt' => $row['receipt_stored_name'] !== null,
            'receipt_mime_type' => $row['receipt_mime_type'] === null ? null : (string) $row['receipt_mime_type'],
            'reconciled_by_user_id' => $row['reconciled_by_user_id'] === null
                ? null
                : (int) $row['reconciled_by_user_id'],
            'reconciled_at' => $row['reconciled_at'] === null ? null : (string) $row['reconciled_at'],
            'rejected_by_user_id' => $row['rejected_by_user_id'] === null ? null : (int) $row['rejected_by_user_id'],
            'rejected_at' => $row['rejected_at'] === null ? null : (string) $row['rejected_at'],
            'rejection_reason' => $row['rejection_reason'] === null ? null : (string) $row['rejection_reason'],
            'allocations' => array_map(static fn (array $allocation): array => [
                'charge_id' => (int) $allocation['charge_id'],
                'amount' => (string) $allocation['amount'],
            ], $allocations->fetchAll(PDO::FETCH_ASSOC)),
        ];
        foreach (['tenant_name', 'property_name', 'unit_code'] as $contextField) {
            if (isset($row[$contextField])) {
                $projection[$contextField] = (string) $row[$contextField];
            }
        }

        return $projection;
    }

    /** @return array{stored_name:string,mime:string,sha256:string,size_bytes:int} */
    private function storePaymentReceipt(mixed $receipt): array
    {
        if (!$receipt instanceof UploadedFileInterface
            || $receipt->getError() !== UPLOAD_ERR_OK
            || trim($this->receiptDirectory) === ''
        ) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        $reportedSize = $receipt->getSize();
        if (!is_int($reportedSize) || $reportedSize < 1 || $reportedSize > self::MAX_PAYMENT_RECEIPT_BYTES) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }
        try {
            $stream = $receipt->getStream();
            $stream->rewind();
            $bytes = $stream->getContents();
        } catch (Throwable $exception) {
            throw new RuntimeException('No fue posible reportar el pago.', 0, $exception);
        }
        $size = strlen($bytes);
        if ($size !== $reportedSize || $size < 1 || $size > self::MAX_PAYMENT_RECEIPT_BYTES) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!is_string($mime) || !isset(self::PAYMENT_RECEIPT_EXTENSIONS[$mime])) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }
        $this->ensurePaymentReceiptDirectory();
        $storedName = 'receipt-' . bin2hex(random_bytes(16)) . '.' . self::PAYMENT_RECEIPT_EXTENSIONS[$mime];
        $target = $this->paymentReceiptPath($storedName, false);
        if ($target === null) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        try {
            $receipt->moveTo($target);
            @chmod($target, 0600);
            $actualSize = @filesize($target);
            $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->file($target);
            $actualHash = @hash_file('sha256', $target);
            $sha256 = hash('sha256', $bytes);
            if (!is_int($actualSize)
                || $actualSize !== $size
                || $actualMime !== $mime
                || !is_string($actualHash)
                || !hash_equals($sha256, $actualHash)
            ) {
                throw new RuntimeException('No fue posible reportar el pago.');
            }
        } catch (Throwable $exception) {
            $this->removePaymentReceipt($storedName);
            throw new RuntimeException('No fue posible reportar el pago.', 0, $exception);
        }

        return ['stored_name' => $storedName, 'mime' => $mime, 'sha256' => $sha256, 'size_bytes' => $size];
    }

    /** @return list<array{payment_id:int,kind:string,signal:string}> */
    private function duplicateWarnings(array $payment): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, amount, payment_method_id, paid_on, paid_time, receipt_sha256 '
            . 'FROM payments WHERE lease_id = :lease_id AND id <> :payment_id ORDER BY id DESC',
        );
        $statement->execute([
            'lease_id' => (int) $payment['lease_id'],
            'payment_id' => (int) $payment['id'],
        ]);
        $warnings = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
            $sameReceipt = is_string($payment['receipt_sha256'])
                && $payment['receipt_sha256'] !== ''
                && is_string($candidate['receipt_sha256'])
                && hash_equals(strtolower((string) $payment['receipt_sha256']), strtolower($candidate['receipt_sha256']));
            $sameDetails = (string) $candidate['amount'] === (string) $payment['amount']
                && (int) $candidate['payment_method_id'] === (int) $payment['payment_method_id']
                && (string) $candidate['paid_on'] === (string) $payment['paid_on'];
            if (!$sameReceipt && !$sameDetails) {
                continue;
            }

            if ($sameReceipt) {
                $warnings[] = [
                    'payment_id' => (int) $candidate['id'],
                    'kind' => 'receipt_hash',
                    'signal' => 'high',
                ];
            } else {
                $signal = 'possible';
                if ($payment['paid_time'] !== null && $candidate['paid_time'] !== null
                    && $this->paymentTimesAreNear((string) $payment['paid_time'], (string) $candidate['paid_time'])) {
                    $signal = 'elevated';
                }
                $warnings[] = [
                    'payment_id' => (int) $candidate['id'],
                    'kind' => 'payment_details',
                    'signal' => $signal,
                ];
            }
        }

        return $warnings;
    }

    private function paymentTimesAreNear(string $first, string $second): bool
    {
        $firstSeconds = $this->paymentTimeToSeconds($first);
        $secondSeconds = $this->paymentTimeToSeconds($second);
        $distance = abs($firstSeconds - $secondSeconds);

        return min($distance, 86400 - $distance) <= 600;
    }

    private function paymentTimeToSeconds(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));

        return ($parts[0] * 3600) + ($parts[1] * 60) + ($parts[2] ?? 0);
    }

    private function ensurePaymentReceiptDirectory(): void
    {
        if (!is_dir($this->receiptDirectory)
            && !@mkdir($this->receiptDirectory, 0700, true)
            && !is_dir($this->receiptDirectory)
        ) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }
        @chmod($this->receiptDirectory, 0700);
    }

    private function paymentReceiptPath(string $storedName, bool $mustExist): ?string
    {
        if (preg_match('/\Areceipt-[a-f0-9]{32}\.(?:jpg|png|webp|pdf)\z/', $storedName) !== 1) {
            return null;
        }
        $directory = realpath($this->receiptDirectory);
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
        if (!is_string($resolved)) {
            return null;
        }
        $prefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with(strtolower($resolved), strtolower($prefix))) {
            return null;
        }

        return $resolved;
    }

    private function removePaymentReceipt(string $storedName): void
    {
        $path = $this->paymentReceiptPath($storedName, true);
        if ($path !== null && !@unlink($path)) {
            error_log('Payment receipt cleanup failed.');
        }
    }

    private function normalizePaymentAmount(string $amount): string
    {
        $amount = trim($amount);
        if (!preg_match('/\A\d+(?:\.\d{1,2})?\z/', $amount)) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 10) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }
        $normalized = $whole . '.' . str_pad($fraction, 2, '0');
        if ($normalized === '0.00') {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        return $normalized;
    }

    private function paymentAmountToCents(string $amount): int
    {
        if (!preg_match('/\A(\d+)\.(\d{2})\z/', $amount, $matches)) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        return ((int) $matches[1] * 100) + (int) $matches[2];
    }

    private function normalizePaymentDate(string $date, int $now): string
    {
        try {
            $normalized = $this->normalizeDate($date);
            $today = (new DateTimeImmutable('@' . $now))
                ->setTimezone(new DateTimeZone('America/Bogota'))
                ->format('Y-m-d');
            if ($normalized > $today) {
                throw new RuntimeException('No fue posible reportar el pago.');
            }
        } catch (RuntimeException $exception) {
            throw new RuntimeException('No fue posible reportar el pago.', 0, $exception);
        }

        return $normalized;
    }

    private function normalizePaymentTime(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }
        $time = trim($time);
        if (!preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?\z/', $time)) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }

        return strlen($time) === 5 ? $time . ':00' : $time;
    }

    private function paymentDateTime(int $timestamp): string
    {
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone('America/Bogota'))
            ->format('Y-m-d H:i:s');
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
        $outstanding = $this->chargeOutstandingExpression($alias);

        return 'COALESCE(SUM(' . $outstanding . '), 0.00) AS outstanding, '
            . 'COALESCE(SUM(CASE WHEN ' . $alias . '.due_date < :today THEN ' . $outstanding
            . ' ELSE 0 END), 0.00) AS overdue, '
            . 'MIN(CASE WHEN ' . $outstanding . ' > 0 THEN ' . $alias . '.due_date ELSE NULL END) AS oldest_due_date';
    }

    private function reconciledAllocationExpression(string $alias): string
    {
        return '(SELECT COALESCE(SUM(pa.amount), 0.00) FROM payment_allocations pa '
            . 'INNER JOIN payments p ON p.id = pa.payment_id '
            . 'WHERE pa.charge_id = ' . $alias . '.id '
            . 'AND p.reconciled_at IS NOT NULL AND p.rejected_at IS NULL)';
    }

    private function chargeOutstandingExpression(string $alias): string
    {
        return '(CASE WHEN ' . $alias . '.voided_at IS NULL THEN GREATEST(0.00, ' . $alias . '.amount - '
            . $this->reconciledAllocationExpression($alias) . ') ELSE 0.00 END)';
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
