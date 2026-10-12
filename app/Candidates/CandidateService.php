<?php

declare(strict_types=1);

namespace AlquilaPro\Candidates;

use AlquilaPro\Properties\PropertyService;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class CandidateService
{
    private const EDITABLE_STATUSES = ['new', 'incomplete'];

    private readonly PropertyService $propertyService;

    public function __construct(
        private readonly PDO $pdo,
        private readonly int $tokenTtlSeconds = 604800,
        private readonly ?CandidateDocumentService $candidateDocumentService = null,
    ) {
        $this->propertyService = new PropertyService($pdo);
    }

    /** @return array{candidate_id:int, continuation_token:string} */
    public function startCandidate(string $publicKey, array $input, int $now): array
    {
        $data = $this->validateStartInput($input);
        $rawToken = $this->newToken();
        $tokenHash = hash('sha256', $rawToken);

        $this->pdo->beginTransaction();
        try {
            if ($this->propertyService->findPublicUnit($publicKey) === null) {
                throw new RuntimeException('La unidad no está disponible para nuevas solicitudes.');
            }

            $unitId = $this->unitIdByPublicKey($publicKey);
            if ($unitId === null) {
                throw new RuntimeException('La unidad no está disponible para nuevas solicitudes.');
            }

            $duplicate = $this->pdo->prepare(
                'SELECT 1 FROM candidates WHERE document_type = :document_type AND document_number = :document_number LIMIT 1',
            );
            $duplicate->execute([
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
            ]);
            if ($duplicate->fetchColumn() !== false) {
                throw new RuntimeException('No fue posible iniciar la solicitud.');
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO candidates ('
                . 'unit_id, document_type, document_number, full_name, phone, email, status, '
                . 'continuation_token_hash, continuation_token_expires_at, consent_at'
                . ') VALUES ('
                . ':unit_id, :document_type, :document_number, :full_name, :phone, :email, :status, '
                . ':continuation_token_hash, :continuation_token_expires_at, :consent_at'
                . ')',
            );
            $statement->execute([
                'unit_id' => $unitId,
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'status' => 'new',
                'continuation_token_hash' => $tokenHash,
                'continuation_token_expires_at' => $this->dateTime($now + $this->tokenTtlSeconds),
                'consent_at' => $this->dateTime($now),
            ]);

            $candidateId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            return [
                'candidate_id' => $candidateId,
                'continuation_token' => $rawToken,
            ];
        } catch (PDOException $exception) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible iniciar la solicitud.');
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            throw $exception;
        }
    }

    public function findByToken(string $rawToken, int $now): ?array
    {
        $row = $this->candidateRowByToken($rawToken, $now);
        if ($row === null) {
            return null;
        }

        $candidate = $this->normalizeCandidate($row);
        $candidate['references'] = $this->referencesForCandidate((int) $row['id']);

        return $candidate;
    }

    public function updateByToken(string $rawToken, array $input, int $now): array
    {
        $row = $this->candidateRowByToken($rawToken, $now);
        if ($row === null) {
            throw new RuntimeException('El enlace de continuación no es válido.');
        }
        $this->assertPublicEditable($row);

        if (array_key_exists('document_type', $input)
            && $this->normalizeDocumentType((string) $input['document_type']) !== $row['document_type']) {
            throw new RuntimeException('La identidad del candidato no se puede cambiar.');
        }
        if (array_key_exists('document_number', $input)
            && $this->normalizeDocumentNumber((string) $input['document_number']) !== $row['document_number']) {
            throw new RuntimeException('La identidad del candidato no se puede cambiar.');
        }

        $data = [
            'full_name' => array_key_exists('full_name', $input)
                ? $this->requiredText($input['full_name'], 190, 'El nombre es obligatorio.')
                : $row['full_name'],
            'birth_date' => array_key_exists('birth_date', $input)
                ? $this->optionalDate($input['birth_date'])
                : $row['birth_date'],
            'phone' => array_key_exists('phone', $input)
                ? $this->requiredText($input['phone'], 50, 'El teléfono es obligatorio.')
                : $row['phone'],
            'email' => array_key_exists('email', $input)
                ? $this->validEmail($input['email'])
                : $row['email'],
            'current_address' => array_key_exists('current_address', $input)
                ? $this->optionalText($input['current_address'], 255)
                : $row['current_address'],
            'employment_status' => array_key_exists('employment_status', $input)
                ? $this->optionalText($input['employment_status'], 80)
                : $row['employment_status'],
            'employer_name' => array_key_exists('employer_name', $input)
                ? $this->optionalText($input['employer_name'], 190)
                : $row['employer_name'],
            'occupation' => array_key_exists('occupation', $input)
                ? $this->optionalText($input['occupation'], 190)
                : $row['occupation'],
            'monthly_income' => array_key_exists('monthly_income', $input)
                ? $this->optionalDecimal($input['monthly_income'])
                : $row['monthly_income'],
            'employment_notes' => array_key_exists('employment_notes', $input)
                ? $this->optionalText($input['employment_notes'])
                : $row['employment_notes'],
            'occupants_count' => array_key_exists('occupants_count', $input)
                ? $this->optionalInteger($input['occupants_count'])
                : $row['occupants_count'],
            'occupants_notes' => array_key_exists('occupants_notes', $input)
                ? $this->optionalText($input['occupants_notes'])
                : $row['occupants_notes'],
            'pets_declared' => array_key_exists('pets_declared', $input)
                ? $this->strictBoolean($input['pets_declared'])
                : (bool) $row['pets_declared'],
            'pets_notes' => array_key_exists('pets_notes', $input)
                ? $this->optionalText($input['pets_notes'])
                : $row['pets_notes'],
            'status' => $this->partialStatus((string) $row['status']),
            'updated_at' => $this->dateTime($now),
            'id' => (int) $row['id'],
        ];

        $statement = $this->pdo->prepare(
            'UPDATE candidates SET '
            . 'full_name = :full_name, birth_date = :birth_date, phone = :phone, email = :email, '
            . 'current_address = :current_address, employment_status = :employment_status, '
            . 'employer_name = :employer_name, occupation = :occupation, monthly_income = :monthly_income, '
            . 'employment_notes = :employment_notes, occupants_count = :occupants_count, occupants_notes = :occupants_notes, '
            . 'pets_declared = :pets_declared, pets_notes = :pets_notes, status = :status, updated_at = :updated_at '
            . 'WHERE id = :id',
        );
        $statement->execute([
            ...$data,
            'pets_declared' => $data['pets_declared'] ? 1 : 0,
        ]);

        $candidate = $this->findCandidate((int) $row['id']);
        if ($candidate === null) {
            throw new RuntimeException('No fue posible actualizar la solicitud.');
        }

        return $candidate;
    }

    public function replaceReferencesByToken(string $rawToken, array $references, int $now): array
    {
        $row = $this->candidateRowByToken($rawToken, $now);
        if ($row === null) {
            throw new RuntimeException('El enlace de continuación no es válido.');
        }
        $this->assertPublicEditable($row);

        $normalized = [];
        foreach ($references as $reference) {
            if (!is_array($reference)) {
                throw new RuntimeException('Las referencias no son válidas.');
            }

            $isBlank = true;
            foreach (['reference_type', 'name', 'relationship', 'phone', 'email'] as $field) {
                if (trim((string) ($reference[$field] ?? '')) !== '') {
                    $isBlank = false;
                    break;
                }
            }
            if ($isBlank) {
                continue;
            }

            $normalized[] = [
                'reference_type' => $this->optionalText($reference['reference_type'] ?? null, 40),
                'name' => $this->requiredText($reference['name'] ?? null, 190, 'El nombre de la referencia es obligatorio.'),
                'relationship' => $this->optionalText($reference['relationship'] ?? null, 120),
                'phone' => $this->requiredText($reference['phone'] ?? null, 50, 'El teléfono de la referencia es obligatorio.'),
                'email' => $this->optionalEmail($reference['email'] ?? null),
            ];
        }

        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare('DELETE FROM candidate_references WHERE candidate_id = :candidate_id');
            $delete->execute(['candidate_id' => (int) $row['id']]);

            $insert = $this->pdo->prepare(
                'INSERT INTO candidate_references '
                . '(candidate_id, reference_type, name, relationship, phone, email, updated_at) '
                . 'VALUES (:candidate_id, :reference_type, :name, :relationship, :phone, :email, :updated_at)',
            );
            foreach ($normalized as $reference) {
                $insert->execute([
                    'candidate_id' => (int) $row['id'],
                    ...$reference,
                    'updated_at' => $this->dateTime($now),
                ]);
            }

            $this->markPartialProgress((int) $row['id'], (string) $row['status'], $now);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            throw $exception;
        }

        $candidate = $this->findByToken($rawToken, $now);
        if ($candidate === null) {
            throw new RuntimeException('No fue posible guardar las referencias.');
        }

        return $candidate;
    }

    public function changeUnitByToken(string $rawToken, string $publicKey, int $now): array
    {
        $row = $this->candidateRowByToken($rawToken, $now);
        if ($row === null) {
            throw new RuntimeException('El enlace de continuación no es válido.');
        }
        $this->assertPublicEditable($row);
        if ($row['submitted_at'] !== null) {
            throw new RuntimeException('La unidad ya no se puede cambiar.');
        }

        $this->pdo->beginTransaction();
        try {
            $documents = $this->pdo->prepare(
                'SELECT COUNT(*) FROM candidate_documents WHERE candidate_id = :candidate_id',
            );
            $documents->execute(['candidate_id' => (int) $row['id']]);
            if ((int) $documents->fetchColumn() > 0) {
                throw new RuntimeException('La unidad ya no se puede cambiar.');
            }

            if ($this->propertyService->findPublicUnit($publicKey) === null) {
                throw new RuntimeException('La nueva unidad no está disponible.');
            }
            $unitId = $this->unitIdByPublicKey($publicKey);
            if ($unitId === null) {
                throw new RuntimeException('La nueva unidad no está disponible.');
            }

            $statement = $this->pdo->prepare(
                'UPDATE candidates SET unit_id = :unit_id, status = :status, updated_at = :updated_at WHERE id = :id',
            );
            $statement->execute([
                'unit_id' => $unitId,
                'status' => $this->partialStatus((string) $row['status']),
                'updated_at' => $this->dateTime($now),
                'id' => (int) $row['id'],
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            throw $exception;
        }

        $candidate = $this->findCandidate((int) $row['id']);
        if ($candidate === null) {
            throw new RuntimeException('No fue posible cambiar la unidad.');
        }

        return $candidate;
    }

    public function submitByToken(string $rawToken, int $now): array
    {
        $row = $this->candidateRowByToken($rawToken, $now);
        if ($row === null) {
            throw new RuntimeException('El enlace de continuación no es válido.');
        }
        $this->assertPublicEditable($row);

        $this->assertBasicCompleteness($row);
        $this->assertLaborCompleteness($row);
        $this->assertDocumentCompleteness($row);

        $statement = $this->pdo->prepare(
            "UPDATE candidates SET status = 'verification', submitted_at = :submitted_at, updated_at = :updated_at WHERE id = :id",
        );
        $timestamp = $this->dateTime($now);
        $statement->execute([
            'submitted_at' => $timestamp,
            'updated_at' => $timestamp,
            'id' => (int) $row['id'],
        ]);

        $candidate = $this->findCandidate((int) $row['id']);
        if ($candidate === null) {
            throw new RuntimeException('No fue posible enviar la solicitud.');
        }

        return $candidate;
    }

    public function listCandidates(): array
    {
        $rows = $this->pdo->query(
            'SELECT c.*, u.code AS unit_code, p.name AS property_name '
            . 'FROM candidates c '
            . 'INNER JOIN units u ON u.id = c.unit_id '
            . 'INNER JOIN properties p ON p.id = u.property_id '
            . 'ORDER BY c.id ASC',
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map($this->normalizeCandidate(...), $rows);
    }

    public function findCandidate(int $candidateId): ?array
    {
        if ($candidateId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT c.*, u.code AS unit_code, p.name AS property_name '
            . 'FROM candidates c '
            . 'INNER JOIN units u ON u.id = c.unit_id '
            . 'INNER JOIN properties p ON p.id = u.property_id '
            . 'WHERE c.id = :id LIMIT 1',
        );
        $statement->execute(['id' => $candidateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $candidate = $this->normalizeCandidate($row);
        $candidate['references'] = $this->referencesForCandidate($candidateId);

        return $candidate;
    }

    public function transitionStatus(int $candidateId, string $status): array
    {
        $candidate = $this->findCandidate($candidateId);
        if ($candidate === null) {
            throw new RuntimeException('El candidato no existe.');
        }

        $allowed = match ($candidate['status']) {
            'new' => ['incomplete', 'withdrawn'],
            'incomplete' => ['withdrawn'],
            'verification' => ['pending_screening', 'withdrawn'],
            'pending_screening' => ['withdrawn'],
            'withdrawn' => [],
            default => [],
        };

        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('La transición de estado no es válida.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE candidates SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
        );
        $statement->execute([
            'status' => $status,
            'id' => $candidateId,
        ]);

        $updated = $this->findCandidate($candidateId);
        if ($updated === null) {
            throw new RuntimeException('No fue posible actualizar el estado.');
        }

        return $updated;
    }

    public function regenerateToken(int $candidateId, int $now): string
    {
        if ($this->findCandidate($candidateId) === null) {
            throw new RuntimeException('El candidato no existe.');
        }

        $rawToken = $this->newToken();
        $statement = $this->pdo->prepare(
            'UPDATE candidates SET continuation_token_hash = :hash, continuation_token_expires_at = :expires_at, '
            . 'updated_at = :updated_at WHERE id = :id',
        );
        $statement->execute([
            'hash' => hash('sha256', $rawToken),
            'expires_at' => $this->dateTime($now + $this->tokenTtlSeconds),
            'updated_at' => $this->dateTime($now),
            'id' => $candidateId,
        ]);

        return $rawToken;
    }

    private function candidateRowByToken(string $rawToken, int $now): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $rawToken)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM candidates WHERE continuation_token_hash = :hash LIMIT 1',
        );
        $statement->execute(['hash' => hash('sha256', $rawToken)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $expiresAt = strtotime((string) $row['continuation_token_expires_at'] . ' UTC');
        if ($expiresAt === false || $expiresAt <= $now) {
            return null;
        }

        return $row;
    }

    private function unitIdByPublicKey(string $publicKey): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM units WHERE public_key = :public_key LIMIT 1');
        $statement->execute(['public_key' => trim($publicKey)]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @return array{document_type:string,document_number:string,full_name:string,phone:string,email:string} */
    private function validateStartInput(array $input): array
    {
        $documentType = $this->normalizeDocumentType((string) ($input['document_type'] ?? ''));
        if ($documentType === '' || mb_strlen($documentType) > 24) {
            throw new RuntimeException('El tipo de documento es obligatorio.');
        }

        $documentNumber = $this->normalizeDocumentNumber((string) ($input['document_number'] ?? ''));
        if ($documentNumber === '' || mb_strlen($documentNumber) > 64) {
            throw new RuntimeException('El número de documento es obligatorio.');
        }

        if (!in_array($input['consent'] ?? null, [true, 1, '1'], true)) {
            throw new RuntimeException('Debes aceptar el tratamiento de datos.');
        }

        return [
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'full_name' => $this->requiredText($input['full_name'] ?? null, 190, 'El nombre es obligatorio.'),
            'phone' => $this->requiredText($input['phone'] ?? null, 50, 'El teléfono es obligatorio.'),
            'email' => $this->validEmail($input['email'] ?? null),
        ];
    }

    private function normalizeDocumentType(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function normalizeDocumentNumber(string $value): string
    {
        return mb_strtoupper(trim($value));
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
            throw new RuntimeException('El valor ingresado es demasiado largo.');
        }

        return $text;
    }

    private function optionalEmail(mixed $value): ?string
    {
        $email = mb_strtolower(trim((string) $value));
        if ($email === '') {
            return null;
        }
        if (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('El correo de la referencia no es válido.');
        }

        return $email;
    }

    private function optionalDecimal(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $text = trim((string) $value);
        if (!is_numeric($text) || !is_finite((float) $text) || (float) $text < 0 || (float) $text > 9999999999.99) {
            throw new RuntimeException('El ingreso mensual no es válido.');
        }

        return $text;
    }

    private function optionalInteger(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0 || (int) $value > 65535) {
            throw new RuntimeException('La cantidad de ocupantes no es válida.');
        }

        return (int) $value;
    }

    private function strictBoolean(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }

        throw new RuntimeException('La declaración de mascotas no es válida.');
    }

    private function optionalDate(mixed $value): ?string
    {
        $date = trim((string) $value);
        if ($date === '') {
            return null;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)
            || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw new RuntimeException('La fecha de nacimiento no es válida.');
        }

        return $date;
    }

    private function validEmail(mixed $value): string
    {
        $email = mb_strtolower(trim((string) $value));
        if ($email === '' || mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('El correo no es válido.');
        }

        return $email;
    }

    private function assertPublicEditable(array $row): void
    {
        if (!in_array((string) $row['status'], self::EDITABLE_STATUSES, true)) {
            throw new RuntimeException('La solicitud ya no admite cambios.');
        }
    }

    private function partialStatus(string $status): string
    {
        return $status === 'new' ? 'incomplete' : $status;
    }

    private function markPartialProgress(int $candidateId, string $status, int $now): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE candidates SET status = :status, updated_at = :updated_at WHERE id = :id',
        );
        $statement->execute([
            'status' => $this->partialStatus($status),
            'updated_at' => $this->dateTime($now),
            'id' => $candidateId,
        ]);
    }

    /** @return list<array{reference_type:?string,name:string,relationship:?string,phone:string,email:?string}> */
    private function referencesForCandidate(int $candidateId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT reference_type, name, relationship, phone, email '
            . 'FROM candidate_references WHERE candidate_id = :candidate_id ORDER BY id ASC',
        );
        $statement->execute(['candidate_id' => $candidateId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assertBasicCompleteness(array $row): void
    {
        if (trim((string) $row['full_name']) === ''
            || trim((string) $row['phone']) === ''
            || filter_var((string) $row['email'], FILTER_VALIDATE_EMAIL) === false
            || trim((string) $row['consent_at']) === '') {
            throw new RuntimeException('Debes completar los datos básicos antes de enviar.');
        }
    }

    private function assertLaborCompleteness(array $row): void
    {
        $started = false;
        foreach (['employment_status', 'employer_name', 'occupation', 'monthly_income', 'employment_notes'] as $field) {
            if ($row[$field] !== null && trim((string) $row[$field]) !== '') {
                $started = true;
                break;
            }
        }

        if ($started && trim((string) ($row['employment_status'] ?? '')) === '') {
            throw new RuntimeException('Debes completar el estado laboral antes de enviar.');
        }
    }

    private function assertDocumentCompleteness(array $row): void
    {
        $statement = $this->pdo->prepare(
            'SELECT d.id AS document_id FROM unit_application_requirements r '
            . 'LEFT JOIN candidate_documents d '
            . 'ON d.requirement_id = r.id AND d.candidate_id = :candidate_id '
            . 'WHERE r.unit_id = :unit_id AND r.is_required = 1 AND r.is_active = 1 '
            . 'ORDER BY r.id ASC',
        );
        $statement->execute([
            'candidate_id' => (int) $row['id'],
            'unit_id' => (int) $row['unit_id'],
        ]);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $requiredDocument) {
            $documentId = $requiredDocument['document_id'] === null
                ? null
                : (int) $requiredDocument['document_id'];

            if ($documentId === null
                || $this->candidateDocumentService === null
                || $this->candidateDocumentService->privateDocument((int) $row['id'], $documentId) === null) {
                throw new RuntimeException('Debes adjuntar los documentos obligatorios antes de enviar.');
            }
        }
    }

    private function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function dateTime(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function normalizeCandidate(array $row): array
    {
        unset($row['continuation_token_hash']);

        $row['id'] = (int) $row['id'];
        $row['unit_id'] = (int) $row['unit_id'];
        $row['pets_declared'] = (bool) $row['pets_declared'];
        $row['occupants_count'] = $row['occupants_count'] === null ? null : (int) $row['occupants_count'];

        return $row;
    }
}
