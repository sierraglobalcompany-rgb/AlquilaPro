<?php

declare(strict_types=1);

namespace AlquilaPro\Candidates;

use PDO;
use PDOException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

final class CandidateDocumentService
{
    private const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;

    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $documentDirectory,
    ) {
    }

    public function listRequirementsForUnit(int $unitId, bool $activeOnly = false): array
    {
        if ($unitId <= 0) {
            return [];
        }

        $sql = 'SELECT * FROM unit_application_requirements WHERE unit_id = :unit_id';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['unit_id' => $unitId]);

        return array_map($this->normalizeRequirement(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function createRequirement(int $unitId, array $input): array
    {
        $this->requireUnit($unitId);

        $code = $this->requiredText($input['code'] ?? null, 80, 'El código del requisito es obligatorio.');
        $label = $this->requiredText($input['label'] ?? null, 190, 'El nombre del requisito es obligatorio.');
        $description = $this->optionalText($input['description'] ?? null);
        $isRequired = array_key_exists('is_required', $input)
            ? $this->strictBoolean($input['is_required'])
            : true;
        $isActive = array_key_exists('is_active', $input)
            ? $this->strictBoolean($input['is_active'])
            : true;
        $sortOrder = array_key_exists('sort_order', $input)
            ? $this->nonNegativeInteger($input['sort_order'], 'El orden del requisito no es válido.')
            : $this->nextRequirementOrder($unitId);

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO unit_application_requirements '
                . '(unit_id, code, label, description, is_required, is_active, sort_order) '
                . 'VALUES (:unit_id, :code, :label, :description, :is_required, :is_active, :sort_order)',
            );
            $statement->execute([
                'unit_id' => $unitId,
                'code' => $code,
                'label' => $label,
                'description' => $description,
                'is_required' => $isRequired ? 1 : 0,
                'is_active' => $isActive ? 1 : 0,
                'sort_order' => $sortOrder,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('No fue posible crear el requisito.', 0, $exception);
        }

        $requirement = $this->requirementById($unitId, (int) $this->pdo->lastInsertId());
        if ($requirement === null) {
            throw new RuntimeException('No fue posible confirmar el requisito guardado.');
        }

        return $requirement;
    }

    public function updateRequirement(int $unitId, int $requirementId, array $input): array
    {
        $current = $this->requirementById($unitId, $requirementId);
        if ($current === null) {
            throw new RuntimeException('El requisito no pertenece a esta unidad.');
        }

        if (array_key_exists('code', $input)) {
            $requestedCode = $this->requiredText($input['code'], 80, 'El código del requisito es obligatorio.');
            if ($requestedCode !== $current['code']) {
                throw new RuntimeException('El código del requisito no se puede cambiar.');
            }
        }

        $label = array_key_exists('label', $input)
            ? $this->requiredText($input['label'], 190, 'El nombre del requisito es obligatorio.')
            : $current['label'];
        $description = array_key_exists('description', $input)
            ? $this->optionalText($input['description'])
            : $current['description'];
        $isRequired = array_key_exists('is_required', $input)
            ? $this->strictBoolean($input['is_required'])
            : $current['is_required'];
        $isActive = array_key_exists('is_active', $input)
            ? $this->strictBoolean($input['is_active'])
            : $current['is_active'];
        $sortOrder = array_key_exists('sort_order', $input)
            ? $this->nonNegativeInteger($input['sort_order'], 'El orden del requisito no es válido.')
            : $current['sort_order'];

        $statement = $this->pdo->prepare(
            'UPDATE unit_application_requirements SET '
            . 'label = :label, description = :description, is_required = :is_required, '
            . 'is_active = :is_active, sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP '
            . 'WHERE id = :id AND unit_id = :unit_id',
        );
        $statement->execute([
            'label' => $label,
            'description' => $description,
            'is_required' => $isRequired ? 1 : 0,
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
            'id' => $requirementId,
            'unit_id' => $unitId,
        ]);

        $updated = $this->requirementById($unitId, $requirementId);
        if ($updated === null) {
            throw new RuntimeException('No fue posible actualizar el requisito.');
        }

        return $updated;
    }

    public function reorderRequirements(int $unitId, array $orderedRequirementIds): void
    {
        $this->requireUnit($unitId);

        $normalized = [];
        foreach ($orderedRequirementIds as $requirementId) {
            if (is_int($requirementId)) {
                $id = $requirementId;
            } elseif (is_string($requirementId) && ctype_digit($requirementId)) {
                $id = (int) $requirementId;
            } else {
                throw new RuntimeException('El orden de requisitos no es válido.');
            }

            if ($id <= 0 || isset($normalized[$id])) {
                throw new RuntimeException('El orden de requisitos contiene IDs inválidos o duplicados.');
            }
            $normalized[$id] = true;
        }
        $orderedIds = array_keys($normalized);

        $statement = $this->pdo->prepare(
            'SELECT id FROM unit_application_requirements WHERE unit_id = :unit_id ORDER BY id ASC',
        );
        $statement->execute(['unit_id' => $unitId]);
        $ownedIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));

        $expected = $ownedIds;
        $provided = $orderedIds;
        sort($expected, SORT_NUMERIC);
        sort($provided, SORT_NUMERIC);
        if ($provided !== $expected) {
            throw new RuntimeException('El orden debe incluir exactamente los requisitos de esta unidad.');
        }

        try {
            $this->pdo->beginTransaction();
            $update = $this->pdo->prepare(
                'UPDATE unit_application_requirements SET sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP '
                . 'WHERE id = :id AND unit_id = :unit_id',
            );
            foreach ($orderedIds as $index => $requirementId) {
                $update->execute([
                    'sort_order' => $index + 1,
                    'id' => $requirementId,
                    'unit_id' => $unitId,
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible actualizar el orden de requisitos.', 0, $exception);
        }
    }

    public function listDocumentsForCandidate(int $candidateId): array
    {
        if ($candidateId <= 0) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT d.*, r.code AS requirement_code, r.label AS requirement_label '
            . 'FROM candidate_documents d '
            . 'INNER JOIN unit_application_requirements r ON r.id = d.requirement_id '
            . 'WHERE d.candidate_id = :candidate_id '
            . 'ORDER BY r.sort_order ASC, r.id ASC',
        );
        $statement->execute(['candidate_id' => $candidateId]);

        return array_map($this->normalizeDocument(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function addOrReplaceDocument(
        int $candidateId,
        string $requirementCode,
        UploadedFileInterface $upload,
    ): array {
        $candidate = $this->candidateRow($candidateId);
        if ($candidate === null) {
            throw new RuntimeException('La solicitud no es válida.');
        }
        if (!in_array((string) $candidate['status'], ['new', 'incomplete'], true)) {
            throw new RuntimeException('La solicitud ya no admite documentos.');
        }

        $requirementCode = trim($requirementCode);
        if ($requirementCode === '') {
            throw new RuntimeException('El requisito documental no es válido.');
        }

        $requirementStatement = $this->pdo->prepare(
            'SELECT * FROM unit_application_requirements '
            . 'WHERE unit_id = :unit_id AND code = :code AND is_active = 1 LIMIT 1',
        );
        $requirementStatement->execute([
            'unit_id' => (int) $candidate['unit_id'],
            'code' => $requirementCode,
        ]);
        $requirementRow = $requirementStatement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($requirementRow)) {
            throw new RuntimeException('El requisito documental no es válido.');
        }
        $requirement = $this->normalizeRequirement($requirementRow);

        [$bytes, $mime, $extension] = $this->validatedUpload($upload);
        $this->ensureDirectory();

        $storedName = 'document-' . bin2hex(random_bytes(16)) . '.' . $extension;
        $newPath = $this->documentPath($storedName, false);
        if ($newPath === null) {
            throw new RuntimeException('No fue posible preparar el almacenamiento del documento.');
        }

        $existing = $this->documentForRequirement($candidateId, (int) $requirement['id']);
        $oldPath = null;
        if ($existing !== null) {
            $oldPath = $this->documentPath((string) $existing['stored_name'], true);
        }

        $originalName = $this->originalName($upload->getClientFilename());
        $newDocument = null;

        try {
            $upload->moveTo($newPath);
            @chmod($newPath, 0600);

            if (!is_file($newPath) || filesize($newPath) !== strlen($bytes)) {
                throw new RuntimeException('No fue posible confirmar el archivo guardado.');
            }

            $this->pdo->beginTransaction();
            if ($existing === null) {
                $statement = $this->pdo->prepare(
                    'INSERT INTO candidate_documents '
                    . '(candidate_id, requirement_id, stored_name, original_name, mime_type, size_bytes) '
                    . 'VALUES (:candidate_id, :requirement_id, :stored_name, :original_name, :mime_type, :size_bytes)',
                );
                $statement->execute([
                    'candidate_id' => $candidateId,
                    'requirement_id' => $requirement['id'],
                    'stored_name' => $storedName,
                    'original_name' => $originalName,
                    'mime_type' => $mime,
                    'size_bytes' => strlen($bytes),
                ]);
                $documentId = (int) $this->pdo->lastInsertId();
            } else {
                $documentId = (int) $existing['id'];
                $statement = $this->pdo->prepare(
                    'UPDATE candidate_documents SET '
                    . 'stored_name = :stored_name, original_name = :original_name, mime_type = :mime_type, '
                    . 'size_bytes = :size_bytes, updated_at = CURRENT_TIMESTAMP '
                    . 'WHERE id = :id AND candidate_id = :candidate_id AND requirement_id = :requirement_id',
                );
                $statement->execute([
                    'stored_name' => $storedName,
                    'original_name' => $originalName,
                    'mime_type' => $mime,
                    'size_bytes' => strlen($bytes),
                    'id' => $documentId,
                    'candidate_id' => $candidateId,
                    'requirement_id' => $requirement['id'],
                ]);
            }

            $newDocument = $this->findDocument($candidateId, $documentId);
            if ($newDocument === null || $newDocument['stored_name'] !== $storedName) {
                throw new RuntimeException('No fue posible confirmar el documento guardado.');
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            @unlink($newPath);
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible guardar el documento.', 0, $exception);
        }

        if ($oldPath !== null && $oldPath !== $newPath && is_file($oldPath) && !@unlink($oldPath)) {
            throw new RuntimeException('El nuevo documento quedó guardado, pero no fue posible retirar el archivo anterior.');
        }

        return $newDocument;
    }

    public function privateDocument(int $candidateId, int $documentId): ?array
    {
        $document = $this->findDocument($candidateId, $documentId);
        if ($document === null) {
            return null;
        }

        $path = $this->documentPath((string) $document['stored_name'], true);
        if ($path === null || !is_file($path)) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $size = filesize($path);
        if (!is_string($mime)
            || !isset(self::MIME_EXTENSIONS[$mime])
            || $mime !== $document['mime_type']
            || !is_int($size)
            || $size !== $document['size_bytes']
        ) {
            return null;
        }

        return [
            'path' => $path,
            'mime' => $mime,
            'stored_name' => $document['stored_name'],
            'original_name' => $document['original_name'],
            'size_bytes' => $document['size_bytes'],
        ];
    }

    private function validatedUpload(UploadedFileInterface $upload): array
    {
        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No fue posible recibir el documento.');
        }

        $stream = $upload->getStream();
        $size = $stream->getSize();
        if ($size === null || $size < 1 || $size > self::MAX_DOCUMENT_BYTES) {
            throw new RuntimeException('El documento supera 10 MiB o está vacío.');
        }

        $stream->rewind();
        $bytes = $stream->getContents();
        if (strlen($bytes) !== $size) {
            throw new RuntimeException('No fue posible validar el documento completo.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('El formato del documento no es válido.');
        }

        return [$bytes, $mime, self::MIME_EXTENSIONS[$mime]];
    }

    private function requirementById(int $unitId, int $requirementId): ?array
    {
        if ($unitId <= 0 || $requirementId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM unit_application_requirements WHERE id = :id AND unit_id = :unit_id LIMIT 1',
        );
        $statement->execute([
            'id' => $requirementId,
            'unit_id' => $unitId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeRequirement($row) : null;
    }

    private function documentForRequirement(int $candidateId, int $requirementId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.*, r.code AS requirement_code, r.label AS requirement_label '
            . 'FROM candidate_documents d '
            . 'INNER JOIN unit_application_requirements r ON r.id = d.requirement_id '
            . 'WHERE d.candidate_id = :candidate_id AND d.requirement_id = :requirement_id LIMIT 1',
        );
        $statement->execute([
            'candidate_id' => $candidateId,
            'requirement_id' => $requirementId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeDocument($row) : null;
    }

    private function findDocument(int $candidateId, int $documentId): ?array
    {
        if ($candidateId <= 0 || $documentId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT d.*, r.code AS requirement_code, r.label AS requirement_label '
            . 'FROM candidate_documents d '
            . 'INNER JOIN unit_application_requirements r ON r.id = d.requirement_id '
            . 'WHERE d.id = :id AND d.candidate_id = :candidate_id LIMIT 1',
        );
        $statement->execute([
            'id' => $documentId,
            'candidate_id' => $candidateId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeDocument($row) : null;
    }

    private function candidateRow(int $candidateId): ?array
    {
        if ($candidateId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT id, unit_id, status FROM candidates WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $candidateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function requireUnit(int $unitId): void
    {
        if ($unitId <= 0) {
            throw new RuntimeException('La unidad no existe.');
        }

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM units WHERE id = :id');
        $statement->execute(['id' => $unitId]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('La unidad no existe.');
        }
    }

    private function nextRequirementOrder(int $unitId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM unit_application_requirements WHERE unit_id = :unit_id',
        );
        $statement->execute(['unit_id' => $unitId]);

        return (int) $statement->fetchColumn();
    }

    private function normalizeRequirement(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'unit_id' => (int) $row['unit_id'],
            'code' => (string) $row['code'],
            'label' => (string) $row['label'],
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'is_required' => (bool) $row['is_required'],
            'is_active' => (bool) $row['is_active'],
            'sort_order' => (int) $row['sort_order'],
        ];
    }

    private function normalizeDocument(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'candidate_id' => (int) $row['candidate_id'],
            'requirement_id' => (int) $row['requirement_id'],
            'requirement_code' => isset($row['requirement_code']) ? (string) $row['requirement_code'] : null,
            'requirement_label' => isset($row['requirement_label']) ? (string) $row['requirement_label'] : null,
            'stored_name' => (string) $row['stored_name'],
            'original_name' => $row['original_name'] !== null ? (string) $row['original_name'] : null,
            'mime_type' => (string) $row['mime_type'],
            'size_bytes' => (int) $row['size_bytes'],
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

    private function optionalText(mixed $value, int $maxLength = 65535): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $maxLength) {
            throw new RuntimeException('El valor ingresado es demasiado largo.');
        }

        return $text;
    }

    private function strictBoolean(mixed $value): bool
    {
        if (in_array($value, [1, '1', true], true)) {
            return true;
        }
        if (in_array($value, [0, '0', false], true)) {
            return false;
        }

        throw new RuntimeException('El valor booleano no es válido.');
    }

    private function nonNegativeInteger(mixed $value, string $message): int
    {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $number = (int) $value;
        } else {
            throw new RuntimeException($message);
        }

        if ($number < 0) {
            throw new RuntimeException($message);
        }

        return $number;
    }

    private function originalName(?string $clientFilename): ?string
    {
        if ($clientFilename === null) {
            return null;
        }

        $name = trim(str_replace('\\', '/', $clientFilename));
        $name = basename($name);
        if ($name === '') {
            return null;
        }

        return mb_substr($name, 0, 255);
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->documentDirectory)
            && !mkdir($this->documentDirectory, 0700, true)
            && !is_dir($this->documentDirectory)
        ) {
            throw new RuntimeException('No fue posible preparar el almacenamiento de documentos.');
        }

        @chmod($this->documentDirectory, 0700);
    }

    private function documentPath(string $storedName, bool $mustExist): ?string
    {
        if (preg_match('/^document-[a-f0-9]{32}\.(?:pdf|jpg|png)$/', $storedName) !== 1) {
            return null;
        }

        $path = rtrim($this->documentDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;
        if ($mustExist && !is_file($path)) {
            return null;
        }

        return $path;
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
