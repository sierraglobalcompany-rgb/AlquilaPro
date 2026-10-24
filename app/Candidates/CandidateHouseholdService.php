<?php

declare(strict_types=1);

namespace AlquilaPro\Candidates;

use PDO;
use PDOException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

final class CandidateHouseholdService
{
    private const MAX_PHOTO_BYTES = 5 * 1024 * 1024;
    private const MAX_IMAGE_EDGE = 6000;
    private const MAX_IMAGE_PIXELS = 24_000_000;
    private const RECORDS = [
        'occupant' => [
            'table' => 'candidate_household_occupants',
            'fields' => ['full_name', 'relationship', 'age_years', 'occupation_or_activity', 'notes'],
            'photo_prefix' => 'occupant',
        ],
        'pet' => [
            'table' => 'candidate_household_pets',
            'fields' => ['name', 'species', 'breed', 'size_category', 'age_years', 'vaccination_declaration', 'notes'],
            'photo_prefix' => 'pet',
        ],
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $mediaDirectory,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function listOccupants(int $candidateId): array
    {
        return $this->listRecords('occupant', $candidateId);
    }

    /** @return list<array<string,mixed>> */
    public function listPets(int $candidateId): array
    {
        return $this->listRecords('pet', $candidateId);
    }

    /** @return array<string,mixed> */
    public function saveRecord(
        string $kind,
        int $candidateId,
        ?int $recordId,
        array $input,
        ?UploadedFileInterface $photo,
        int $now,
    ): array {
        $this->definition($kind);
        $data = $this->normalizeInput($kind, $input);
        $photoConsent = $this->hasPhotoConsent($input['photo_consent'] ?? null);
        if ($candidateId <= 0 || ($recordId !== null && $recordId <= 0) || $now < 0) {
            throw $this->failure('Los datos del registro del hogar no son válidos.');
        }

        return $this->mutate(function (array &$createdFiles, array &$retiredFiles) use (
            $kind,
            $candidateId,
            $recordId,
            $data,
            $photo,
            $input,
            $photoConsent,
            $now,
        ): array {
            $this->assertCandidateExists($candidateId);
            return $this->saveRecordWithinMutation(
                $kind,
                $candidateId,
                $recordId,
                $data,
                $photo,
                $photoConsent,
                !empty($input['remove_photo']),
                $now,
                $createdFiles,
                $retiredFiles,
            );
        });
    }

    public function deleteRecord(string $kind, int $candidateId, int $recordId): void
    {
        $definition = $this->definition($kind);
        if ($candidateId <= 0 || $recordId <= 0) {
            throw $this->failure('El registro del hogar no existe.');
        }

        $this->mutate(function (array &$createdFiles, array &$retiredFiles) use (
            $definition,
            $candidateId,
            $recordId,
        ): void {
            $statement = $this->pdo->prepare(
                'SELECT photo_stored_name FROM ' . $definition['table']
                . ' WHERE id = :id AND candidate_id = :candidate_id LIMIT 1 FOR UPDATE',
            );
            $statement->execute(['id' => $recordId, 'candidate_id' => $candidateId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw $this->failure('El registro del hogar no existe.');
            }
            $delete = $this->pdo->prepare(
                'DELETE FROM ' . $definition['table'] . ' WHERE id = :id AND candidate_id = :candidate_id',
            );
            $delete->execute(['id' => $recordId, 'candidate_id' => $candidateId]);
            if (is_string($row['photo_stored_name'] ?? null)) {
                $retiredFiles[] = $row['photo_stored_name'];
            }
        });
    }

    /**
     * Saves the candidate's household answer and all submitted rows as one database transaction.
     * The callback must update the existing CandidateService-owned candidate fields.
     *
     * @param array<array-key,mixed> $occupants
     * @param array<array-key,mixed> $pets
     * @param array<string,mixed> $uploads
     */
    public function saveWizard(
        int $candidateId,
        array $occupants,
        array $pets,
        array $uploads,
        callable $saveCandidate,
        int $now,
    ): void {
        if ($candidateId <= 0 || $now < 0 || count($occupants) > 25 || count($pets) > 25) {
            throw $this->failure('Los registros del hogar no son válidos.');
        }

        $this->mutate(function (array &$createdFiles, array &$retiredFiles) use (
            $candidateId,
            $occupants,
            $pets,
            $uploads,
            $saveCandidate,
            $now,
        ): void {
            $this->assertCandidateExists($candidateId);
            $candidate = $saveCandidate();
            $this->syncRecords('occupant', $candidateId, $occupants, $uploads['occupants'] ?? [], $now, $createdFiles, $retiredFiles);
            $this->syncRecords('pet', $candidateId, $pets, $uploads['pets'] ?? [], $now, $createdFiles, $retiredFiles);
            if (is_array($candidate) && empty($candidate['pets_declared']) && $this->listPets($candidateId) !== []) {
                throw $this->failure('La respuesta No requiere retirar las fichas de mascotas antes de continuar.');
            }
        });
    }

    /** @return array{path:string,mime:string,size_bytes:int} */
    public function privatePhoto(int $candidateId, string $kind, int $recordId): ?array
    {
        $definition = self::RECORDS[$kind] ?? null;
        if ($definition === null || $candidateId <= 0 || $recordId <= 0) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT photo_stored_name, photo_mime_type, photo_size_bytes, photo_sha256, photo_consent_at FROM '
            . $definition['table'] . ' WHERE id = :id AND candidate_id = :candidate_id LIMIT 1',
        );
        $statement->execute(['id' => $recordId, 'candidate_id' => $candidateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !is_string($row['photo_stored_name'] ?? null)) {
            return null;
        }
        $path = $this->photoPath($row['photo_stored_name']);
        if ($path === null || !is_file($path) || is_link($path)) {
            return null;
        }
        $size = filesize($path);
        $sha256 = hash_file('sha256', $path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_int($size)
            || $size !== (int) $row['photo_size_bytes']
            || !is_string($sha256)
            || !hash_equals((string) $row['photo_sha256'], $sha256)
            || !is_string($mime)
            || $mime !== $row['photo_mime_type']
            || !is_string($row['photo_consent_at'] ?? null)
            || !in_array($mime, ['image/jpeg', 'image/png'], true)
        ) {
            return null;
        }

        return ['path' => $path, 'mime' => $mime, 'size_bytes' => $size];
    }

    /** @return list<array<string,mixed>> */
    private function listRecords(string $kind, int $candidateId): array
    {
        $definition = $this->definition($kind);
        if ($candidateId <= 0) {
            return [];
        }
        $fields = implode(', ', $definition['fields']);
        $statement = $this->pdo->prepare(
            'SELECT id, ' . $fields . ', (photo_stored_name IS NOT NULL) AS has_photo FROM '
            . $definition['table'] . ' WHERE candidate_id = :candidate_id ORDER BY id ASC',
        );
        $statement->execute(['candidate_id' => $candidateId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['has_photo'] = (bool) $row['has_photo'];
            if ($row['age_years'] !== null) {
                $row['age_years'] = (int) $row['age_years'];
            }
        }
        unset($row);
        return $rows;
    }

    /** @param array<string,mixed> $data
     *  @param list<string> $createdFiles
     *  @param list<string> $retiredFiles
     *  @return array<string,mixed>
     */
    private function saveRecordWithinMutation(
        string $kind,
        int $candidateId,
        ?int $recordId,
        array $data,
        ?UploadedFileInterface $photo,
        bool $photoConsent,
        bool $removePhoto,
        int $now,
        array &$createdFiles,
        array &$retiredFiles,
    ): array {
        $definition = $this->definition($kind);
        $existing = null;
        if ($recordId !== null) {
            $existing = $this->recordRow($definition['table'], $candidateId, $recordId);
            if ($existing === null) {
                throw $this->failure('No fue posible actualizar el registro del hogar.');
            }
        }

        $newPhoto = $this->readPhoto($photo);
        if ($newPhoto !== null) {
            if (!$photoConsent) {
                throw $this->failure('Confirma que cuentas con autorización para guardar esta fotografía.');
            }
            $stored = $this->writePhoto($newPhoto);
            $createdFiles[] = $stored['photo_stored_name'];
            if (is_string($existing['photo_stored_name'] ?? null)) {
                $retiredFiles[] = $existing['photo_stored_name'];
            }
            $photoData = [...$stored, 'photo_consent_at' => $this->dateTime($now)];
        } elseif ($removePhoto) {
            if (is_string($existing['photo_stored_name'] ?? null)) {
                $retiredFiles[] = $existing['photo_stored_name'];
            }
            $photoData = $this->emptyPhotoData();
        } else {
            $photoData = [
                'photo_stored_name' => $existing['photo_stored_name'] ?? null,
                'photo_mime_type' => $existing['photo_mime_type'] ?? null,
                'photo_size_bytes' => $existing['photo_size_bytes'] ?? null,
                'photo_sha256' => $existing['photo_sha256'] ?? null,
                'photo_consent_at' => $existing['photo_consent_at'] ?? null,
            ];
        }

        if ($existing === null) {
            $columns = [...array_keys($data), ...array_keys($photoData), 'created_at'];
            $params = [...$data, ...$photoData, 'created_at' => $this->dateTime($now), 'candidate_id' => $candidateId];
            $statement = $this->pdo->prepare(
                'INSERT INTO ' . $definition['table'] . ' (candidate_id, ' . implode(', ', $columns) . ') VALUES '
                . '(:candidate_id, :' . implode(', :', [...array_keys($data), ...array_keys($photoData)]) . ', :created_at)',
            );
            $statement->execute($params);
            $recordId = (int) $this->pdo->lastInsertId();
        } else {
            $updated = [...$data, ...$photoData, 'updated_at' => $this->dateTime($now)];
            $assignments = [];
            foreach (array_keys($updated) as $column) {
                $assignments[] = $column . ' = :' . $column;
            }
            $statement = $this->pdo->prepare(
                'UPDATE ' . $definition['table'] . ' SET ' . implode(', ', $assignments)
                . ' WHERE id = :id AND candidate_id = :candidate_id',
            );
            $statement->execute([...$updated, 'id' => $recordId, 'candidate_id' => $candidateId]);
        }

        $row = $this->recordRow($definition['table'], $candidateId, $recordId);
        if ($row === null) {
            throw $this->failure('No fue posible confirmar el registro del hogar.');
        }
        return $this->publicRow($row);
    }

    /** @param array<array-key,mixed> $rows
     *  @param mixed $uploads
     *  @param list<string> $createdFiles
     *  @param list<string> $retiredFiles
     */
    private function syncRecords(
        string $kind,
        int $candidateId,
        array $rows,
        mixed $uploads,
        int $now,
        array &$createdFiles,
        array &$retiredFiles,
    ): void {
        if (count($rows) > 25 || !is_array($uploads)) {
            throw $this->failure('Los registros del hogar no son válidos.');
        }
        $definition = $this->definition($kind);
        $existingStatement = $this->pdo->prepare(
            'SELECT id FROM ' . $definition['table'] . ' WHERE candidate_id = :candidate_id FOR UPDATE',
        );
        $existingStatement->execute(['candidate_id' => $candidateId]);
        $existingIds = array_fill_keys(array_map('intval', $existingStatement->fetchAll(PDO::FETCH_COLUMN)), true);
        $keptIds = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw $this->failure('Los registros del hogar no son válidos.');
            }
            $recordId = trim((string) ($row['id'] ?? '')) === '' ? null : filter_var($row['id'], FILTER_VALIDATE_INT);
            if ($recordId !== null && (!is_int($recordId) || $recordId <= 0)) {
                throw $this->failure('El identificador del registro no es válido.');
            }
            $input = $row;
            unset($input['id'], $input['photo']);
            if ($recordId === null && $this->isEmptyInput($kind, $input)) {
                continue;
            }
            if ($recordId !== null) {
                $keptIds[$recordId] = true;
            }
            $file = is_array($uploads[$index] ?? null) ? ($uploads[$index]['photo'] ?? null) : null;
            if ($file !== null && !$file instanceof UploadedFileInterface) {
                throw $this->failure('La fotografía no es válida.');
            }
            $data = $this->normalizeInput($kind, $input);
            $this->saveRecordWithinMutation(
                $kind,
                $candidateId,
                $recordId,
                $data,
                $file,
                $this->hasPhotoConsent($row['photo_consent'] ?? null),
                !empty($row['remove_photo']),
                $now,
                $createdFiles,
                $retiredFiles,
            );
        }

        foreach (array_keys($existingIds) as $existingId) {
            if (isset($keptIds[$existingId])) {
                continue;
            }
            $row = $this->recordRow($definition['table'], $candidateId, $existingId);
            if ($row === null) {
                continue;
            }
            $delete = $this->pdo->prepare(
                'DELETE FROM ' . $definition['table'] . ' WHERE id = :id AND candidate_id = :candidate_id',
            );
            $delete->execute(['id' => $existingId, 'candidate_id' => $candidateId]);
            if (is_string($row['photo_stored_name'] ?? null)) {
                $retiredFiles[] = $row['photo_stored_name'];
            }
        }
    }

    /** @param array<string,mixed> $input
     *  @return array<string,mixed>
     */
    private function normalizeInput(string $kind, array $input): array
    {
        if ($kind === 'occupant') {
            return [
                'full_name' => $this->requiredText($input['full_name'] ?? null, 190, 'El nombre del ocupante es obligatorio.'),
                'relationship' => $this->requiredText($input['relationship'] ?? null, 100, 'La relación con el solicitante es obligatoria.'),
                'age_years' => $this->optionalInteger($input['age_years'] ?? null, 120),
                'occupation_or_activity' => $this->optionalText($input['occupation_or_activity'] ?? null, 190),
                'notes' => $this->optionalText($input['notes'] ?? null, 5000),
            ];
        }

        if ($kind === 'pet') {
            $vaccination = trim((string) ($input['vaccination_declaration'] ?? ''));
            if (!in_array($vaccination, ['', 'declared_vaccinated', 'declared_not_vaccinated'], true)) {
                throw $this->failure('La declaración de vacunación no es válida.');
            }
            return [
                'name' => $this->requiredText($input['name'] ?? null, 190, 'El nombre y la especie de la mascota son obligatorios.'),
                'species' => $this->requiredText($input['species'] ?? null, 80, 'El nombre y la especie de la mascota son obligatorios.'),
                'breed' => $this->optionalText($input['breed'] ?? null, 120),
                'size_category' => $this->optionalText($input['size_category'] ?? null, 32),
                'age_years' => $this->optionalInteger($input['age_years'] ?? null, 100),
                'vaccination_declaration' => $vaccination === '' ? null : $vaccination,
                'notes' => $this->optionalText($input['notes'] ?? null, 5000),
            ];
        }

        $this->definition($kind);
        throw $this->failure('El tipo de registro del hogar no es válido.');
    }

    private function isEmptyInput(string $kind, array $input): bool
    {
        $fields = $this->definition($kind)['fields'];
        foreach ($fields as $field) {
            if (trim((string) ($input[$field] ?? '')) !== '') {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private function publicRow(array $row): array
    {
        $out = [];
        foreach (['id', ...self::RECORDS[$this->kindForTable((string) $row['__table'])]['fields']] as $field) {
            $out[$field] = $row[$field] ?? null;
        }
        $out['has_photo'] = is_string($row['photo_stored_name'] ?? null);
        if ($out['age_years'] !== null) {
            $out['age_years'] = (int) $out['age_years'];
        }
        return $out;
    }

    private function kindForTable(string $table): string
    {
        foreach (self::RECORDS as $kind => $definition) {
            if ($definition['table'] === $table) {
                return $kind;
            }
        }
        throw $this->failure('El tipo de registro del hogar no es válido.');
    }

    /** @return array<string,mixed>|null */
    private function recordRow(string $table, int $candidateId, int $recordId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM ' . $table . ' WHERE id = :id AND candidate_id = :candidate_id LIMIT 1 FOR UPDATE',
        );
        $statement->execute(['id' => $recordId, 'candidate_id' => $candidateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $row['__table'] = $table;
        return $row;
    }

    private function assertCandidateExists(int $candidateId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM candidates WHERE id = :id LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $candidateId]);
        if ($statement->fetchColumn() === false) {
            throw $this->failure('El candidato no existe.');
        }
    }

    /** @return array{table:string,fields:list<string>,photo_prefix:string} */
    private function definition(string $kind): array
    {
        $definition = self::RECORDS[$kind] ?? null;
        if (!is_array($definition)) {
            throw $this->failure('El tipo de registro del hogar no es válido.');
        }
        return $definition;
    }

    /** @return array<string,mixed>|null */
    private function readPhoto(?UploadedFileInterface $upload): ?array
    {
        if ($upload === null || $upload->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw $this->failure('No fue posible recibir la fotografía.');
        }
        $stream = $upload->getStream();
        $declaredSize = $stream->getSize();
        if ($declaredSize === null || $declaredSize < 1 || $declaredSize > self::MAX_PHOTO_BYTES) {
            throw $this->failure('La fotografía debe pesar máximo 5 MiB.');
        }
        $stream->rewind();
        $bytes = $stream->getContents();
        if (strlen($bytes) !== $declaredSize) {
            throw $this->failure('No fue posible validar la fotografía completa.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $image = @getimagesizefromstring($bytes);
        $expectedImageType = match ($mime) {
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            default => null,
        };
        if (!is_string($mime)
            || $expectedImageType === null
            || !is_array($image)
            || ($image[2] ?? null) !== $expectedImageType
            || ($mime === 'image/png' && !str_starts_with($bytes, "\x89PNG\r\n\x1a\n"))
            || ($mime === 'image/jpeg' && !str_starts_with($bytes, "\xFF\xD8\xFF"))
        ) {
            throw $this->failure('La fotografía debe ser una imagen PNG o JPEG válida.');
        }
        $width = (int) $image[0];
        $height = (int) $image[1];
        if ($width < 1 || $height < 1 || $width > self::MAX_IMAGE_EDGE || $height > self::MAX_IMAGE_EDGE
            || ($width * $height) > self::MAX_IMAGE_PIXELS
        ) {
            throw $this->failure('Las dimensiones de la fotografía no son válidas.');
        }

        $bytes = $this->stripImageMetadata($bytes, $mime);
        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'extension' => $mime === 'image/jpeg' ? 'jpg' : 'png',
            'size_bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
        ];
    }

    private function stripImageMetadata(string $bytes, string $mime): string
    {
        if (!function_exists('imagecreatefromstring')) {
            return $bytes;
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw $this->failure('La fotografía no se pudo decodificar.');
        }
        ob_start();
        $encoded = $mime === 'image/png'
            ? imagepng($image, null, 6)
            : imagejpeg($image, null, 90);
        $normalized = ob_get_clean();
        unset($image);
        if (!$encoded || !is_string($normalized) || $normalized === '') {
            throw $this->failure('No fue posible normalizar la fotografía.');
        }
        return $normalized;
    }

    /** @param array<string,mixed> $photo
     *  @return array{photo_stored_name:string,photo_mime_type:string,photo_size_bytes:int,photo_sha256:string}
     */
    private function writePhoto(array $photo): array
    {
        $directory = $this->ensureDirectory();
        $storedName = 'candidate-household-' . bin2hex(random_bytes(16)) . '.' . $photo['extension'];
        $path = $directory . DIRECTORY_SEPARATOR . $storedName;
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw $this->failure('No fue posible guardar la fotografía privada.');
        }
        $length = strlen($photo['bytes']);
        $offset = 0;
        try {
            while ($offset < $length) {
                $written = fwrite($handle, substr($photo['bytes'], $offset));
                if (!is_int($written) || $written < 1) {
                    throw $this->failure('No fue posible guardar la fotografía privada.');
                }
                $offset += $written;
            }
            fflush($handle);
        } catch (Throwable $exception) {
            fclose($handle);
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
            throw $exception;
        }
        fclose($handle);
        @chmod($path, 0600);
        $stat = @stat($path);
        if (!is_array($stat) || (int) $stat['size'] !== $length || is_link($path)) {
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
            throw $this->failure('No fue posible verificar la fotografía privada.');
        }
        return [
            'photo_stored_name' => $storedName,
            'photo_mime_type' => $photo['mime'],
            'photo_size_bytes' => $length,
            'photo_sha256' => $photo['sha256'],
        ];
    }

    private function ensureDirectory(): string
    {
        if ($this->mediaDirectory === '' || is_link($this->mediaDirectory)) {
            throw $this->failure('El almacenamiento privado no es válido.');
        }
        if (!is_dir($this->mediaDirectory)
            && !mkdir($this->mediaDirectory, 0700, true)
            && !is_dir($this->mediaDirectory)
        ) {
            throw $this->failure('No fue posible preparar el almacenamiento privado.');
        }
        if (is_link($this->mediaDirectory)) {
            throw $this->failure('El almacenamiento privado no es válido.');
        }
        @chmod($this->mediaDirectory, 0700);
        $real = realpath($this->mediaDirectory);
        if (!is_string($real) || !is_dir($real)) {
            throw $this->failure('El almacenamiento privado no es válido.');
        }
        return $real;
    }

    private function photoPath(string $storedName): ?string
    {
        if (preg_match('/\Acandidate-household-[a-f0-9]{32}\.(?:jpg|png)\z/', $storedName) !== 1
            || $this->mediaDirectory === '' || is_link($this->mediaDirectory)
        ) {
            return null;
        }
        $directory = realpath($this->mediaDirectory);
        if (!is_string($directory) || !is_dir($directory)) {
            return null;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $storedName;
        if (is_link($path)) {
            return null;
        }
        $real = realpath($path);
        return is_string($real) && dirname($real) === $directory ? $real : null;
    }

    /** @return array{photo_stored_name:null,photo_mime_type:null,photo_size_bytes:null,photo_sha256:null,photo_consent_at:null} */
    private function emptyPhotoData(): array
    {
        return [
            'photo_stored_name' => null,
            'photo_mime_type' => null,
            'photo_size_bytes' => null,
            'photo_sha256' => null,
            'photo_consent_at' => null,
        ];
    }

    private function hasPhotoConsent(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }

    /** @param list<string> $createdFiles
     *  @param list<string> $retiredFiles
     */
    private function mutate(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            throw $this->failure('La operación del hogar no puede anidarse.');
        }
        $createdFiles = [];
        $retiredFiles = [];
        $this->pdo->beginTransaction();
        try {
            $result = $operation($createdFiles, $retiredFiles);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($createdFiles as $storedName) {
                $path = $this->photoPath($storedName);
                if ($path !== null && is_file($path) && !is_link($path)) {
                    @unlink($path);
                }
            }
            if ($exception instanceof PDOException) {
                throw $this->failure('No fue posible guardar la información del hogar.', $exception);
            }
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw $this->failure('No fue posible guardar la información del hogar.', $exception);
        }

        foreach (array_unique($retiredFiles) as $storedName) {
            $path = $this->photoPath($storedName);
            if ($path !== null && is_file($path) && !is_link($path)) {
                @unlink($path);
            }
        }
        return $result;
    }

    private function requiredText(mixed $value, int $maxLength, string $message): string
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text, 'UTF-8') > $maxLength || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text) === 1) {
            throw $this->failure($message);
        }
        return $text;
    }

    private function optionalText(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text, 'UTF-8') > $maxLength || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text) === 1) {
            throw $this->failure('Uno de los datos del hogar supera el límite permitido.');
        }
        return $text;
    }

    private function optionalInteger(mixed $value, int $maximum): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $text = trim((string) $value);
        if (preg_match('/\A\d{1,3}\z/D', $text) !== 1 || (int) $text > $maximum) {
            throw $this->failure('La edad aproximada no es válida.');
        }
        return (int) $text;
    }

    private function dateTime(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function failure(string $message, ?Throwable $previous = null): RuntimeException
    {
        return new RuntimeException($message, 0, $previous);
    }
}
