<?php

declare(strict_types=1);

namespace AlquilaPro\Notifications;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final readonly class NotificationService
{
    private const TEMPLATES = [
        'candidate_created',
        'candidate_submitted',
        'charge_due',
        'obligation_due',
        'policy_expiry',
        'maintenance_visit',
    ];
    private const MAX_BATCH = 100;
    private const MAX_ATTEMPTS = 3;
    private const CLAIM_LEASE_SECONDS = 600;
    private const RETRY_DELAY_SECONDS = 300;
    private const TERMINAL_RECIPIENT_RETENTION_DAYS = 90;
    private const SAFE_TRANSPORT_ERROR = 'transport_error';
    private const SAFE_INVALID_ERROR = 'invalid_payload';
    private const SAFE_EXHAUSTED_ERROR = 'attempts_exhausted';

    /** @param Closure(string,string,string,string,string):void $transport */
    public function __construct(private PDO $pdo, private Closure $transport)
    {
    }

    /** @param array<string|int,mixed> $payload */
    public function queue(
        string $type,
        string $email,
        string $name,
        string $subject,
        string $templateKey,
        array $payload,
        string $scheduledAt,
        string $dedupeKey,
    ): ?int {
        $type = $this->token($type, 80);
        $templateKey = $this->token($templateKey, 100);
        if (!in_array($templateKey, self::TEMPLATES, true)) {
            throw $this->failure();
        }
        $payload = $this->templatePayload($payload);
        $email = trim($email);
        $name = trim($name);
        $subject = trim($subject);
        $dedupeKey = trim($dedupeKey);
        $scheduledAt = $this->timestamp($scheduledAt);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || mb_strlen($email, 'UTF-8') > 254
            || mb_strlen($name, 'UTF-8') > 120
            || mb_strlen($subject, 'UTF-8') < 1
            || mb_strlen($subject, 'UTF-8') > 200
            || preg_match('/[\x00-\x1F\x7F]/', $name . $subject) === 1
            || $dedupeKey === ''
            || mb_strlen($dedupeKey, 'UTF-8') > 191
            || preg_match('/[\x00-\x1F\x7F]/', $dedupeKey) === 1
        ) {
            throw $this->failure();
        }

        try {
            $payloadJson = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (Throwable) {
            throw $this->failure();
        }
        if (strlen($payloadJson) > 8192) {
            throw $this->failure();
        }

        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO notification_jobs '
                . '(type, recipient_email, recipient_name, subject, template_key, payload_json, scheduled_at, dedupe_key) '
                . 'VALUES (:type, :email, :name, :subject, :template_key, :payload, :scheduled_at, :dedupe_key)',
            );
            $insert->execute([
                'type' => $type,
                'email' => $email,
                'name' => $name,
                'subject' => $subject,
                'template_key' => $templateKey,
                'payload' => $payloadJson,
                'scheduled_at' => $scheduledAt,
                'dedupe_key' => $dedupeKey,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            return $id > 0 ? $id : throw $this->failure();
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return null;
            }
            throw $this->failure();
        }
    }

    public function generateReminders(string $today, string $now): int
    {
        $today = $this->day($today);
        $now = $this->timestamp($now);
        $queued = 0;

        $charges = $this->pdo->prepare(
            'SELECT c.id, c.due_date, u.email, u.full_name FROM charges c '
            . 'JOIN leases l ON l.id = c.lease_id AND l.ended_at IS NULL '
            . 'JOIN users u ON u.id = l.user_id AND u.is_active = 1 '
            . 'WHERE c.voided_at IS NULL AND c.due_date <= :today '
            . 'AND c.amount > COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa '
            . 'JOIN payments p ON p.id = pa.payment_id AND p.reconciled_at IS NOT NULL AND p.rejected_at IS NULL '
            . 'WHERE pa.charge_id = c.id), 0) ORDER BY c.id',
        );
        $charges->execute(['today' => $today]);
        foreach ($charges->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = (string) $row['due_date'];
            $queued += $this->queueReminder(
                'charge_due', (string) $row['email'], (string) $row['full_name'],
                'charge_due', 'Recordatorio de cargo pendiente', $date, $now,
                'charge:' . (int) $row['id'] . ':due:' . $today . ':v1',
            );
        }

        $obligations = $this->pdo->prepare(
            'SELECT o.id, o.due_on, u.email, u.full_name FROM lease_obligations o '
            . 'JOIN leases l ON l.id = o.lease_id AND l.ended_at IS NULL '
            . 'JOIN users u ON u.id = l.user_id AND u.is_active = 1 '
            . 'WHERE o.due_on IS NOT NULL AND o.due_on <= :today AND o.settled_at IS NULL '
            . 'AND o.voided_at IS NULL AND o.charge_id IS NULL ORDER BY o.id',
        );
        $obligations->execute(['today' => $today]);
        foreach ($obligations->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = (string) $row['due_on'];
            $queued += $this->queueReminder(
                'obligation_due', (string) $row['email'], (string) $row['full_name'],
                'obligation_due', 'Recordatorio de obligación pendiente', $date, $now,
                'obligation:' . (int) $row['id'] . ':due:' . $today . ':v1',
            );
        }

        $policies = $this->pdo->prepare(
            'SELECT p.id, p.coverage_end, u.email, u.full_name FROM insurance_policies p '
            . 'JOIN leases l ON l.id = p.lease_id AND l.ended_at IS NULL '
            . 'JOIN users u ON u.id = l.user_id AND u.is_active = 1 '
            . 'WHERE p.coverage_end = :today AND p.cancelled_at IS NULL '
            . 'AND NOT EXISTS (SELECT 1 FROM insurance_policies replacement '
            . 'WHERE replacement.supersedes_policy_id = p.id AND replacement.cancelled_at IS NULL) '
            . 'ORDER BY p.id',
        );
        $policies->execute(['today' => $today]);
        foreach ($policies->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = (string) $row['coverage_end'];
            $queued += $this->queueReminder(
                'policy_expiry', (string) $row['email'], (string) $row['full_name'],
                'policy_expiry', 'Recordatorio de vencimiento de póliza', $date, $now,
                'policy:' . (int) $row['id'] . ':expiry:' . $today . ':v1',
            );
        }

        $visits = $this->pdo->prepare(
            'SELECT v.id, v.selected_start_at, u.email, u.full_name FROM maintenance_visits v '
            . 'JOIN maintenance_requests r ON r.id = v.maintenance_request_id AND r.status IN (\'open\', \'in_progress\') '
            . 'JOIN leases l ON l.id = r.lease_id AND l.ended_at IS NULL '
            . 'JOIN users u ON u.id = l.user_id AND u.is_active = 1 '
            . 'WHERE v.status = \'scheduled\' AND v.selected_start_at >= :day_start '
            . 'AND v.selected_start_at < :next_day ORDER BY v.id',
        );
        $localStart = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $today . ' 00:00:00',
            new DateTimeZone('America/Bogota'),
        );
        if ($localStart === false) {
            throw $this->failure();
        }
        $utc = new DateTimeZone('UTC');
        $visits->execute([
            'day_start' => $localStart->setTimezone($utc)->format('Y-m-d H:i:s'),
            'next_day' => $localStart->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
        ]);
        foreach ($visits->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $visitStart = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                (string) $row['selected_start_at'],
                $utc,
            );
            if ($visitStart === false) {
                throw $this->failure();
            }
            $date = $visitStart->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d');
            $queued += $this->queueReminder(
                'maintenance_visit', (string) $row['email'], (string) $row['full_name'],
                'maintenance_visit', 'Recordatorio de visita de mantenimiento', $date, $now,
                'visit:' . (int) $row['id'] . ':scheduled:' . $today . ':v1',
            );
        }

        return $queued;
    }

    public function queueCandidateEvent(int $candidateId, string $eventKey, string $scheduledAt): ?int
    {
        if ($candidateId <= 0 || !in_array($eventKey, ['created', 'submitted'], true)) {
            throw $this->failure();
        }
        $candidateQuery = $this->pdo->prepare(
            'SELECT full_name, email, submitted_at FROM candidates WHERE id = :id LIMIT 1',
        );
        $candidateQuery->execute(['id' => $candidateId]);
        $candidate = $candidateQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($candidate) || ($eventKey === 'submitted' && $candidate['submitted_at'] === null)) {
            throw $this->failure();
        }

        $template = $eventKey === 'created' ? 'candidate_created' : 'candidate_submitted';
        $subject = $eventKey === 'created'
            ? 'Recibimos tu solicitud de arrendamiento'
            : 'Tu solicitud fue enviada para revisión';
        return $this->queue(
            'candidate_' . $eventKey,
            (string) $candidate['email'],
            (string) $candidate['full_name'],
            $subject,
            $template,
            ['event_date' => substr($this->timestamp($scheduledAt), 0, 10)],
            $scheduledAt,
            'candidate:' . $candidateId . ':' . $eventKey . ':v1',
        );
    }

    private function queueReminder(
        string $type,
        string $email,
        string $name,
        string $template,
        string $subject,
        string $eventDate,
        string $scheduledAt,
        string $dedupeKey,
    ): int {
        return $this->queue(
            $type,
            $email,
            $name,
            $subject,
            $template,
            ['event_date' => $eventDate],
            $scheduledAt,
            $dedupeKey,
        ) === null ? 0 : 1;
    }

    public function redactTerminalRecipients(string $now): int
    {
        $now = $this->timestamp($now);
        $cutoff = gmdate(
            'Y-m-d H:i:s',
            $this->timestampEpoch($now) - (self::TERMINAL_RECIPIENT_RETENTION_DAYS * 86400),
        );

        try {
            $statement = $this->pdo->prepare(
                'UPDATE notification_jobs SET recipient_email = NULL, recipient_name = NULL '
                . 'WHERE (recipient_email IS NOT NULL OR recipient_name IS NOT NULL) '
                . 'AND ((sent_at IS NOT NULL AND sent_at < :sent_cutoff) '
                . 'OR (failed_at IS NOT NULL AND failed_at < :failed_cutoff))',
            );
            $statement->execute([
                'sent_cutoff' => $cutoff,
                'failed_cutoff' => $cutoff,
            ]);
            return $statement->rowCount();
        } catch (Throwable) {
            throw $this->failure();
        }
    }

    /** @return array{sent:int,failed:int} */
    public function processDue(int $limit, string $now): array
    {
        if ($limit < 1 || $limit > self::MAX_BATCH) {
            throw $this->failure();
        }
        $now = $this->timestamp($now);
        $counts = ['sent' => 0, 'failed' => 0];

        for ($processed = 0; $processed < $limit; $processed++) {
            $claim = $this->claimDue(1, $now);
            $counts['failed'] += $claim['failed'];
            if ($claim['ids'] === []) {
                break;
            }
            $id = $claim['ids'][0];
            $job = $this->readJob($id);
            if ($job === null) {
                continue;
            }
            try {
                $payload = json_decode((string) $job['payload_json'], true, 16, JSON_THROW_ON_ERROR);
                if (!is_array($payload)) {
                    throw $this->failure();
                }
                [$htmlBody, $textBody] = $this->renderBodies(
                    (string) $job['template_key'],
                    $payload,
                );
            } catch (Throwable) {
                $this->recordInvalidPayload($id, $now);
                $counts['failed']++;
                continue;
            }

            if (!$this->beginAttempt($id, $now)) {
                continue;
            }
            try {
                ($this->transport)(
                    (string) $job['recipient_email'],
                    (string) $job['recipient_name'],
                    (string) $job['subject'],
                    $htmlBody,
                    $textBody,
                );
            } catch (Throwable) {
                $this->recordFailure($id, $now);
                $counts['failed']++;
                continue;
            }

            $this->recordSent($id, $now);
            $counts['sent']++;
        }

        return $counts;
    }

    /** @return array{ids:list<int>,failed:int} */
    private function claimDue(int $limit, string $now): array
    {
        if ($this->pdo->inTransaction()) {
            throw $this->failure();
        }
        try {
            if (!$this->pdo->beginTransaction()) {
                throw $this->failure();
            }
            $eligibleBefore = gmdate(
                'Y-m-d H:i:s',
                $this->timestampEpoch($now) - self::RETRY_DELAY_SECONDS,
            );
            $exhausted = $this->pdo->prepare(
                'UPDATE notification_jobs SET failed_at = :failed_at, last_error = :error, claim_until = NULL '
                . 'WHERE sent_at IS NULL AND failed_at IS NULL AND attempt_count >= :max_attempts '
                . 'AND (claim_until IS NULL OR claim_until <= :expired_at)',
            );
            $exhausted->execute([
                'failed_at' => $now,
                'expired_at' => $now,
                'error' => self::SAFE_EXHAUSTED_ERROR,
                'max_attempts' => self::MAX_ATTEMPTS,
            ]);
            $failedCount = $exhausted->rowCount();
            $claimUntil = gmdate(
                'Y-m-d H:i:s',
                $this->timestampEpoch($now) + self::CLAIM_LEASE_SECONDS,
            );
            $select = $this->pdo->prepare(
                'SELECT id FROM notification_jobs WHERE sent_at IS NULL AND failed_at IS NULL '
                . 'AND attempt_count < :max_attempts AND scheduled_at <= :scheduled_before '
                . 'AND (last_attempt_at IS NULL OR last_attempt_at <= :eligible_before) '
                . 'AND (claim_until IS NULL OR claim_until <= :claim_expired_before) '
                . 'ORDER BY scheduled_at, id LIMIT :batch_limit FOR UPDATE SKIP LOCKED',
            );
            $select->bindValue(':max_attempts', self::MAX_ATTEMPTS, PDO::PARAM_INT);
            $select->bindValue(':scheduled_before', $now);
            $select->bindValue(':claim_expired_before', $now);
            $select->bindValue(':eligible_before', $eligibleBefore);
            $select->bindValue(':batch_limit', $limit, PDO::PARAM_INT);
            $select->execute();
            $ids = array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN));

            $claim = $this->pdo->prepare(
                'UPDATE notification_jobs SET claim_until = :claim_until '
                . 'WHERE id = :id AND sent_at IS NULL AND failed_at IS NULL '
                . 'AND attempt_count < :max_attempts AND (claim_until IS NULL OR claim_until <= :now)',
            );
            $claimed = [];
            foreach ($ids as $id) {
                $claim->execute([
                    'claim_until' => $claimUntil,
                    'now' => $now,
                    'id' => $id,
                    'max_attempts' => self::MAX_ATTEMPTS,
                ]);
                if ($claim->rowCount() === 1) {
                    $claimed[] = $id;
                }
            }
            if (!$this->pdo->commit()) {
                throw $this->failure();
            }
            return ['ids' => $claimed, 'failed' => $failedCount];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw $this->failure();
        }
    }

    /** @return array<string,mixed>|null */
    private function readJob(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM notification_jobs WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function recordSent(int $id, string $now): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE notification_jobs SET sent_at = :now, last_error = NULL, claim_until = NULL '
            . 'WHERE id = :id AND sent_at IS NULL AND failed_at IS NULL',
        );
        $statement->execute(['now' => $now, 'id' => $id]);
        if ($statement->rowCount() !== 1) {
            throw $this->failure();
        }
    }

    private function recordFailure(int $id, string $now): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE notification_jobs SET '
            . 'failed_at = CASE WHEN attempt_count >= :max_attempts THEN :now ELSE NULL END, '
            . 'last_error = :safe_error, claim_until = NULL '
            . 'WHERE id = :id AND sent_at IS NULL AND failed_at IS NULL',
        );
        $statement->execute([
            'max_attempts' => self::MAX_ATTEMPTS,
            'now' => $now,
            'safe_error' => self::SAFE_TRANSPORT_ERROR,
            'id' => $id,
        ]);
    }

    private function beginAttempt(int $id, string $now): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE notification_jobs SET attempt_count = attempt_count + 1, last_attempt_at = :attempt_at '
            . 'WHERE id = :id AND sent_at IS NULL AND failed_at IS NULL '
            . 'AND attempt_count < :max_attempts AND claim_until > :lease_valid_at',
        );
        $statement->execute([
            'attempt_at' => $now,
            'lease_valid_at' => $now,
            'id' => $id,
            'max_attempts' => self::MAX_ATTEMPTS,
        ]);
        return $statement->rowCount() === 1;
    }

    private function recordInvalidPayload(int $id, string $now): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE notification_jobs SET failed_at = :now, last_error = :error, claim_until = NULL '
            . 'WHERE id = :id AND sent_at IS NULL AND failed_at IS NULL',
        );
        $statement->execute([
            'now' => $now,
            'error' => self::SAFE_INVALID_ERROR,
            'id' => $id,
        ]);
    }

    /** @return array{0:string,1:string} */
    private function renderBodies(string $templateKey, array $payload): array
    {
        if (!in_array($templateKey, self::TEMPLATES, true)) {
            throw $this->failure();
        }
        $eventDate = (string) $this->templatePayload($payload)['event_date'];
        $message = match ($templateKey) {
            'candidate_created' => 'Recibimos tu solicitud. Puedes continuar el proceso desde el enlace que aparece en AlquilaPro.',
            'candidate_submitted' => 'Tu solicitud fue enviada para revisión. El equipo de AlquilaPro continuará el proceso.',
            'charge_due' => 'Tienes un cargo de arrendamiento pendiente con vencimiento el ' . $eventDate . '.',
            'obligation_due' => 'Tienes una obligación del arrendamiento pendiente con vencimiento el ' . $eventDate . '.',
            'policy_expiry' => 'La cobertura de tu póliza de seguro vence el ' . $eventDate . '.',
            'maintenance_visit' => 'Tienes una visita de mantenimiento programada para el ' . $eventDate . '.',
        };
        $html = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return [
            '<!doctype html><html><body><p>' . $html . '</p></body></html>',
            $message,
        ];
    }

    private function day(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw $this->failure();
        }
        return $date->format('Y-m-d');
    }

    /** @param array<string|int,mixed> $payload
     *  @return array{event_date:string}
     */
    private function templatePayload(array $payload): array
    {
        if (array_keys($payload) !== ['event_date'] || !is_string($payload['event_date'] ?? null)) {
            throw $this->failure();
        }
        return ['event_date' => $this->day($payload['event_date'])];
    }

    private function token(string $value, int $maxLength): string
    {
        $value = trim($value);
        if (mb_strlen($value, 'UTF-8') > $maxLength
            || preg_match('/\A[a-z][a-z0-9_.-]*\z/D', $value) !== 1
        ) {
            throw $this->failure();
        }
        return $value;
    }

    private function timestamp(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d H:i:s') !== $value
        ) {
            throw $this->failure();
        }
        return $date->format('Y-m-d H:i:s');
    }

    private function timestampEpoch(string $value): int
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        if ($date === false) {
            throw $this->failure();
        }
        return $date->getTimestamp();
    }

    private function failure(): RuntimeException
    {
        return new RuntimeException('No fue posible procesar la notificación.');
    }
}
