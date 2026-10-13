<?php

declare(strict_types=1);

namespace AlquilaPro\Candidates;

use PDO;
use PDOException;
use RuntimeException;

final class ScreeningService
{
    private const STATUSES = ['pending', 'in_progress', 'approved', 'rejected'];
    private const TERMINAL_STATUSES = ['approved', 'rejected'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly CandidateService $candidates,
    ) {
    }

    public function findForCandidate(int $candidateId): ?array
    {
        if ($candidateId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM screening_processes WHERE candidate_id = :candidate_id LIMIT 1',
        );
        $statement->execute(['candidate_id' => $candidateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalize($row) : null;
    }

    public function createForCandidate(
        int $candidateId,
        int $responsibleUserId,
        array $input,
        int $now,
    ): array {
        $this->assertCandidateEligible($candidateId);
        if ($responsibleUserId <= 0) {
            throw new RuntimeException('El responsable del estudio no es válido.');
        }
        if ($this->findForCandidate($candidateId) !== null) {
            throw new RuntimeException('El candidato ya tiene un estudio de aseguradora.');
        }

        $data = $this->validatedFields($input, null);

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO screening_processes ('
                . 'candidate_id, provider, product, status, contact_email, portal_url, result, notes, '
                . 'support_reference, responsible_user_id'
                . ') VALUES ('
                . ':candidate_id, :provider, :product, :status, :contact_email, :portal_url, :result, :notes, '
                . ':support_reference, :responsible_user_id'
                . ')',
            );
            $statement->execute([
                'candidate_id' => $candidateId,
                'provider' => $data['provider'],
                'product' => $data['product'],
                'status' => 'pending',
                'contact_email' => $data['contact_email'],
                'portal_url' => $data['portal_url'],
                'result' => $data['result'],
                'notes' => $data['notes'],
                'support_reference' => $data['support_reference'],
                'responsible_user_id' => $responsibleUserId,
            ]);
        } catch (PDOException) {
            throw new RuntimeException('No fue posible crear el estudio de aseguradora.');
        }

        $created = $this->findForCandidate($candidateId);
        if ($created === null) {
            throw new RuntimeException('No fue posible crear el estudio de aseguradora.');
        }

        return $created;
    }

    public function updateForCandidate(
        int $candidateId,
        array $input,
        int $now,
    ): array {
        $this->assertCandidateEligible($candidateId);

        $current = $this->findForCandidate($candidateId);
        if ($current === null) {
            throw new RuntimeException('El candidato no tiene un estudio de aseguradora.');
        }
        if (in_array($current['status'], self::TERMINAL_STATUSES, true)) {
            throw new RuntimeException('El estudio de aseguradora ya está cerrado.');
        }

        $targetStatus = array_key_exists('status', $input)
            ? trim((string) $input['status'])
            : (string) $current['status'];
        $this->assertTransition((string) $current['status'], $targetStatus);

        $data = $this->validatedFields($input, $current);
        if (in_array($targetStatus, self::TERMINAL_STATUSES, true) && $data['result'] === null) {
            throw new RuntimeException('El resultado es obligatorio para cerrar el estudio.');
        }

        $startedAt = $current['started_at'];
        if ($startedAt === null && $targetStatus !== 'pending') {
            $startedAt = $this->dateTime($now);
        }

        $resultAt = $current['result_at'];
        if (in_array($targetStatus, self::TERMINAL_STATUSES, true)) {
            $resultAt = $this->dateTime($now);
        }

        $statement = $this->pdo->prepare(
            'UPDATE screening_processes SET '
            . 'provider = :provider, product = :product, status = :status, contact_email = :contact_email, '
            . 'portal_url = :portal_url, result = :result, notes = :notes, support_reference = :support_reference, '
            . 'started_at = :started_at, result_at = :result_at, updated_at = :updated_at '
            . 'WHERE candidate_id = :candidate_id',
        );
        $statement->execute([
            'provider' => $data['provider'],
            'product' => $data['product'],
            'status' => $targetStatus,
            'contact_email' => $data['contact_email'],
            'portal_url' => $data['portal_url'],
            'result' => $data['result'],
            'notes' => $data['notes'],
            'support_reference' => $data['support_reference'],
            'started_at' => $startedAt,
            'result_at' => $resultAt,
            'updated_at' => $this->dateTime($now),
            'candidate_id' => $candidateId,
        ]);

        $updated = $this->findForCandidate($candidateId);
        if ($updated === null) {
            throw new RuntimeException('No fue posible actualizar el estudio de aseguradora.');
        }

        return $updated;
    }

    public function publicForCandidate(int $candidateId): ?array
    {
        $screening = $this->findForCandidate($candidateId);
        if ($screening === null) {
            return null;
        }

        $candidate = $this->candidates->findCandidate($candidateId);
        $canUsePortal = $candidate !== null
            && $candidate['status'] === 'pending_screening'
            && in_array($screening['status'], ['pending', 'in_progress'], true);
        $terminal = in_array($screening['status'], self::TERMINAL_STATUSES, true);

        return [
            'provider' => $screening['provider'],
            'product' => $screening['product'],
            'status' => $screening['status'],
            'contact_email' => $screening['contact_email'],
            'portal_url' => $canUsePortal ? $screening['portal_url'] : null,
            'result' => $terminal ? $screening['result'] : null,
            'started_at' => $screening['started_at'],
            'result_at' => $screening['result_at'],
        ];
    }

    private function assertCandidateEligible(int $candidateId): void
    {
        $candidate = $this->candidates->findCandidate($candidateId);
        if ($candidate === null || $candidate['status'] !== 'pending_screening') {
            throw new RuntimeException('El candidato no está disponible para estudio de aseguradora.');
        }
    }

    private function assertTransition(string $current, string $target): void
    {
        if (!in_array($target, self::STATUSES, true)) {
            throw new RuntimeException('La transición del estudio no es válida.');
        }

        $allowed = match ($current) {
            'pending' => ['pending', 'in_progress', 'approved', 'rejected'],
            'in_progress' => ['in_progress', 'approved', 'rejected'],
            default => [],
        };

        if (!in_array($target, $allowed, true)) {
            throw new RuntimeException('La transición del estudio no es válida.');
        }
    }

    /** @return array{provider:string,product:?string,contact_email:?string,portal_url:?string,result:?string,notes:?string,support_reference:?string} */
    private function validatedFields(array $input, ?array $current): array
    {
        return [
            'provider' => array_key_exists('provider', $input)
                ? $this->requiredText($input['provider'], 120, 'El proveedor es obligatorio.')
                : $this->requiredText($current['provider'] ?? null, 120, 'El proveedor es obligatorio.'),
            'product' => array_key_exists('product', $input)
                ? $this->optionalText($input['product'], 190)
                : ($current['product'] ?? null),
            'contact_email' => array_key_exists('contact_email', $input)
                ? $this->optionalEmail($input['contact_email'])
                : ($current['contact_email'] ?? null),
            'portal_url' => array_key_exists('portal_url', $input)
                ? $this->optionalPortalUrl($input['portal_url'])
                : ($current['portal_url'] ?? null),
            'result' => array_key_exists('result', $input)
                ? $this->optionalText($input['result'])
                : ($current['result'] ?? null),
            'notes' => array_key_exists('notes', $input)
                ? $this->optionalText($input['notes'])
                : ($current['notes'] ?? null),
            'support_reference' => array_key_exists('support_reference', $input)
                ? $this->optionalText($input['support_reference'], 255)
                : ($current['support_reference'] ?? null),
        ];
    }

    private function requiredText(mixed $value, int $maxLength, string $message): string
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > $maxLength) {
            throw new RuntimeException($message);
        }

        return $text;
    }

    private function optionalText(mixed $value, ?int $maxLength = null): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if ($maxLength !== null && mb_strlen($text) > $maxLength) {
            throw new RuntimeException('El valor supera la longitud permitida.');
        }

        return $text;
    }

    private function optionalEmail(mixed $value): ?string
    {
        $email = $this->optionalText($value, 190);
        if ($email === null) {
            return null;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('El correo de contacto no es válido.');
        }

        return $email;
    }

    private function optionalPortalUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }
        if (mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('La URL del estudio no es válida.');
        }

        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || trim((string) ($parts['host'] ?? '')) === '') {
            throw new RuntimeException('La URL del estudio debe usar HTTPS.');
        }

        return $url;
    }

    /** @return array<string,mixed> */
    private function normalize(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'candidate_id' => (int) $row['candidate_id'],
            'provider' => (string) $row['provider'],
            'product' => $row['product'] === null ? null : (string) $row['product'],
            'status' => (string) $row['status'],
            'contact_email' => $row['contact_email'] === null ? null : (string) $row['contact_email'],
            'portal_url' => $row['portal_url'] === null ? null : (string) $row['portal_url'],
            'result' => $row['result'] === null ? null : (string) $row['result'],
            'notes' => $row['notes'] === null ? null : (string) $row['notes'],
            'support_reference' => $row['support_reference'] === null ? null : (string) $row['support_reference'],
            'responsible_user_id' => $row['responsible_user_id'] === null ? null : (int) $row['responsible_user_id'],
            'started_at' => $row['started_at'] === null ? null : (string) $row['started_at'],
            'result_at' => $row['result_at'] === null ? null : (string) $row['result_at'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
        ];
    }

    private function dateTime(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
