<?php

declare(strict_types=1);

namespace AlquilaPro\Documents;

use JsonException;
use PDO;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

final class DocumentService
{
    private const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;
    private const TYPE_SCOPE = [
        'property_document' => 'property',
        'lease_document' => 'lease',
        'manual_convivencia' => 'property',
        'inventory_initial' => 'lease',
        'inventory_final' => 'lease',
        'other' => 'either',
    ];
    private const FILE_TYPES = ['property_document', 'lease_document', 'other'];
    private const CONTENT_TYPES = ['manual_convivencia', 'inventory_initial', 'inventory_final', 'other'];
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $documentDirectory,
    ) {
    }

    /** @return array<string,mixed> */
    public function createFileVersion(
        ?int $propertyId,
        ?int $leaseId,
        string $type,
        string $title,
        UploadedFileInterface $file,
        ?int $supersedesDocumentId,
        int $actorUserId,
        int $now,
    ): array {
        $input = $this->validateInput($propertyId, $leaseId, $type, $title, $supersedesDocumentId, $actorUserId, $now);
        $title = trim($title);
        if (!in_array($type, self::FILE_TYPES, true)) {
            throw $this->failure('El tipo de documento requiere contenido estructurado.');
        }

        $upload = $this->readUpload($file);
        $storedName = 'document-' . bin2hex(random_bytes(16)) . '.' . $upload['extension'];
        $path = $this->writeStoredFile($storedName, $upload['bytes']);
        try {
            return $this->insertVersion(
                $input,
                $type,
                $title,
                null,
                [
                    'stored_name' => $storedName,
                    'mime_type' => $upload['mime'],
                    'sha256' => hash('sha256', $upload['bytes']),
                    'size_bytes' => strlen($upload['bytes']),
                ],
                $supersedesDocumentId,
                $actorUserId,
                $now,
            );
        } catch (Throwable $exception) {
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $content
     *  @return array<string,mixed>
     */
    public function createContentVersion(
        ?int $propertyId,
        ?int $leaseId,
        string $type,
        string $title,
        array $content,
        ?int $supersedesDocumentId,
        int $actorUserId,
        int $now,
    ): array {
        $input = $this->validateInput($propertyId, $leaseId, $type, $title, $supersedesDocumentId, $actorUserId, $now);
        $title = trim($title);
        if (!in_array($type, self::CONTENT_TYPES, true)) {
            throw $this->failure('El tipo de documento requiere un archivo.');
        }

        try {
            $json = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('El contenido del documento no es válido.', 0, $exception);
        }

        return $this->insertVersion(
            $input,
            $type,
            $title,
            $json,
            null,
            $supersedesDocumentId,
            $actorUserId,
            $now,
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $documentId): ?array
    {
        if ($documentId <= 0) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $documentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $normalized = $this->normalizeRow($row);
        return $normalized === [] ? null : $normalized;
    }

    /** @return list<array<string,mixed>> */
    public function listCurrentForProperty(int $propertyId): array
    {
        if ($propertyId <= 0) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT document.* FROM documents document '
            . 'WHERE document.property_id = :property_id AND NOT EXISTS ('
            . 'SELECT 1 FROM documents child WHERE child.supersedes_document_id = document.id '
            . 'AND child.property_id <=> document.property_id AND child.lease_id <=> document.lease_id '
            . 'AND child.type = document.type) '
            . 'ORDER BY document.type ASC, document.created_at DESC, document.id DESC',
        );
        $statement->execute(['property_id' => $propertyId]);
        return $this->normalizeRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string,mixed>> */
    public function listCurrentForLease(int $leaseId): array
    {
        if ($leaseId <= 0) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT document.* FROM documents document '
            . 'WHERE document.lease_id = :lease_id AND NOT EXISTS ('
            . 'SELECT 1 FROM documents child WHERE child.supersedes_document_id = document.id '
            . 'AND child.property_id <=> document.property_id AND child.lease_id <=> document.lease_id '
            . 'AND child.type = document.type) '
            . 'ORDER BY document.type ASC, document.created_at DESC, document.id DESC',
        );
        $statement->execute(['lease_id' => $leaseId]);
        return $this->normalizeRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string,mixed>> */
    public function history(int $documentId): array
    {
        $target = $this->find($documentId);
        if ($target === null) {
            return [];
        }

        $scope = $this->rowScope($target);
        $ancestors = [];
        $visited = [];
        $cursor = $target;
        while (true) {
            $id = (int) $cursor['id'];
            if (isset($visited[$id])) {
                return [];
            }
            $visited[$id] = true;
            $ancestors[] = $cursor;
            $parentId = $cursor['supersedes_document_id'];
            if ($parentId === null) {
                break;
            }
            $parent = $this->find((int) $parentId);
            if ($parent === null || $parent['type'] !== $target['type'] || $this->rowScope($parent) !== $scope) {
                return [];
            }
            $cursor = $parent;
        }

        $chain = array_reverse($ancestors);
        $current = $chain[array_key_last($chain)];
        while (true) {
            $statement = $this->pdo->prepare(
                'SELECT * FROM documents WHERE supersedes_document_id = :parent_id '
                . 'AND type = :type AND property_id <=> :property_id AND lease_id <=> :lease_id '
                . 'ORDER BY id ASC LIMIT 2',
            );
            $statement->execute([
                'parent_id' => $current['id'],
                'type' => $target['type'],
                'property_id' => $scope['property_id'],
                'lease_id' => $scope['lease_id'],
            ]);
            $children = $statement->fetchAll(PDO::FETCH_ASSOC);
            if ($children === []) {
                break;
            }
            if (count($children) !== 1) {
                return [];
            }
            $child = $this->normalizeRow($children[0]);
            $childId = (int) $child['id'];
            if (isset($visited[$childId])) {
                return [];
            }
            $visited[$childId] = true;
            $chain[] = $child;
            $current = $child;
        }
        return $chain;
    }

    /** @return array<string,mixed>|null */
    public function privateFile(int $documentId): ?array
    {
        $document = $this->find($documentId);
        if ($document === null || !is_string($document['stored_name'])
            || !is_string($document['mime_type']) || !is_string($document['sha256'])
            || !is_int($document['size_bytes']) || $document['size_bytes'] < 1
            || $document['size_bytes'] > self::MAX_DOCUMENT_BYTES
        ) {
            return null;
        }

        $expectedExtension = self::MIME_EXTENSIONS[$document['mime_type']] ?? null;
        $path = $this->documentPath($document['stored_name']);
        if ($expectedExtension === null || $path === null
            || !str_ends_with($document['stored_name'], '.' . $expectedExtension)
            || is_link($path) || !is_file($path)
        ) {
            return null;
        }

        $before = @lstat($path);
        if (!is_array($before) || (($before['mode'] & 0170000) !== 0100000)
            || (int) $before['size'] !== $document['size_bytes']
        ) {
            return null;
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            $opened = fstat($handle);
            if (!is_array($opened) || (int) $opened['dev'] !== (int) $before['dev']
                || (int) $opened['ino'] !== (int) $before['ino']
                || (($opened['mode'] & 0170000) !== 0100000)
                || (int) $opened['size'] !== $document['size_bytes']
            ) {
                return null;
            }
            $bytes = stream_get_contents($handle, self::MAX_DOCUMENT_BYTES + 1);
        } finally {
            fclose($handle);
        }
        if (!is_string($bytes) || strlen($bytes) !== $document['size_bytes']
            || !hash_equals($document['sha256'], hash('sha256', $bytes))
        ) {
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!is_string($mime) || $mime !== $document['mime_type']) {
            return null;
        }

        return [
            'contents' => $bytes,
            'mime' => $mime,
            'size_bytes' => $document['size_bytes'],
            'sha256' => $document['sha256'],
        ];
    }

    /** @param array<string,mixed> $input
     *  @param array<string,mixed>|null $file
     *  @return array<string,mixed>
     */
    private function insertVersion(
        array $input,
        string $type,
        string $title,
        ?string $contentJson,
        ?array $file,
        ?int $supersedesDocumentId,
        int $actorUserId,
        int $now,
    ): array {
        try {
            $this->pdo->beginTransaction();
            $this->assertScopeExists($input);
            $this->assertCanSupersede($supersedesDocumentId, $input, $type);
            $statement = $this->pdo->prepare(
                'INSERT INTO documents '
                . '(property_id, lease_id, type, title, content_json, stored_name, mime_type, sha256, size_bytes, '
                . 'supersedes_document_id, created_by_user_id, created_at) '
                . 'VALUES (:property_id, :lease_id, :type, :title, :content_json, :stored_name, :mime_type, :sha256, '
                . ':size_bytes, :supersedes_document_id, :actor_id, :created_at)',
            );
            $statement->execute([
                'property_id' => $input['property_id'],
                'lease_id' => $input['lease_id'],
                'type' => $type,
                'title' => $title,
                'content_json' => $contentJson,
                'stored_name' => $file['stored_name'] ?? null,
                'mime_type' => $file['mime_type'] ?? null,
                'sha256' => $file['sha256'] ?? null,
                'size_bytes' => $file['size_bytes'] ?? null,
                'supersedes_document_id' => $supersedesDocumentId,
                'actor_id' => $actorUserId,
                'created_at' => gmdate('Y-m-d H:i:s', $now),
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $saved = $this->find($id);
            if ($saved === null) {
                throw $this->failure('No fue posible confirmar el documento guardado.');
            }
            $this->pdo->commit();
            return $saved;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible guardar el documento.', 0, $exception);
        }
    }

    /** @return array{property_id:?int,lease_id:?int} */
    private function validateInput(
        ?int $propertyId,
        ?int $leaseId,
        string $type,
        string $title,
        ?int $supersedesDocumentId,
        int $actorUserId,
        int $now,
    ): array {
        $scopeType = self::TYPE_SCOPE[$type] ?? null;
        $title = trim($title);
        if ($scopeType === null || $title === '' || mb_strlen($title, 'UTF-8') > 190
            || preg_match('/[\x00-\x1F\x7F]/', $title) === 1
            || $actorUserId <= 0 || $now < 0
            || ($supersedesDocumentId !== null && $supersedesDocumentId <= 0)
            || (($propertyId === null) === ($leaseId === null))
            || ($propertyId !== null && $propertyId <= 0)
            || ($leaseId !== null && $leaseId <= 0)
            || ($scopeType === 'property' && ($propertyId === null || $leaseId !== null))
            || ($scopeType === 'lease' && ($leaseId === null || $propertyId !== null))
        ) {
            throw $this->failure('El tipo, alcance, título o auditoría del documento no es válido.');
        }
        return ['property_id' => $propertyId, 'lease_id' => $leaseId];
    }

    /** @param array{property_id:?int,lease_id:?int} $scope */
    private function assertScopeExists(array $scope): void
    {
        $table = $scope['property_id'] !== null ? 'properties' : 'leases';
        $id = $scope['property_id'] ?? $scope['lease_id'];
        $statement = $this->pdo->prepare('SELECT id FROM ' . $table . ' WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        if ($statement->fetchColumn() === false) {
            throw $this->failure('El alcance del documento no existe.');
        }
    }

    /** @param array{property_id:?int,lease_id:?int} $scope */
    private function assertCanSupersede(?int $documentId, array $scope, string $type): void
    {
        if ($documentId === null) {
            return;
        }
        $statement = $this->pdo->prepare(
            'SELECT id, property_id, lease_id, type, supersedes_document_id '
            . 'FROM documents WHERE id = :id LIMIT 1 FOR UPDATE',
        );
        $statement->execute(['id' => $documentId]);
        $parent = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($parent) || $parent['type'] !== $type
            || $this->rowScope($parent) !== $scope
        ) {
            throw $this->failure('La versión anterior debe tener el mismo tipo y alcance.');
        }

        $child = $this->pdo->prepare(
            'SELECT id FROM documents WHERE supersedes_document_id = :id LIMIT 1 FOR UPDATE',
        );
        $child->execute(['id' => $documentId]);
        if ($child->fetchColumn() !== false) {
            throw $this->failure('Solo se puede crear una versión desde la versión actual.');
        }

        $visited = [];
        $cursor = $parent;
        while (true) {
            $currentId = (int) $cursor['id'];
            if (isset($visited[$currentId])) {
                throw $this->failure('La cadena de versiones no es válida.');
            }
            $visited[$currentId] = true;
            if ($cursor['supersedes_document_id'] === null) {
                break;
            }
            $ancestorStatement = $this->pdo->prepare(
                'SELECT id, property_id, lease_id, type, supersedes_document_id '
                . 'FROM documents WHERE id = :id LIMIT 1',
            );
            $ancestorStatement->execute(['id' => (int) $cursor['supersedes_document_id']]);
            $ancestor = $ancestorStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($ancestor) || $ancestor['type'] !== $type || $this->rowScope($ancestor) !== $scope) {
                throw $this->failure('La cadena de versiones no conserva el tipo y alcance.');
            }
            $cursor = $ancestor;
        }
    }

    /** @param array<string,mixed> $row
     *  @return array{property_id:?int,lease_id:?int}
     */
    private function rowScope(array $row): array
    {
        return [
            'property_id' => $row['property_id'] === null ? null : (int) $row['property_id'],
            'lease_id' => $row['lease_id'] === null ? null : (int) $row['lease_id'],
        ];
    }

    /** @return array{bytes:string,mime:string,extension:string} */
    private function readUpload(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw $this->failure('No fue posible recibir el archivo.');
        }
        try {
            $stream = $file->getStream();
            $declaredSize = $stream->getSize();
            if ($declaredSize !== null && ($declaredSize < 1 || $declaredSize > self::MAX_DOCUMENT_BYTES)) {
                throw $this->failure('El archivo supera el tamaño permitido o está vacío.');
            }
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $bytes = '';
            while (!$stream->eof()) {
                $remaining = self::MAX_DOCUMENT_BYTES + 1 - strlen($bytes);
                $chunk = $stream->read(min(65536, $remaining));
                if ($chunk === '' && !$stream->eof()) {
                    throw $this->failure('No fue posible leer el archivo completo.');
                }
                $bytes .= $chunk;
                if (strlen($bytes) > self::MAX_DOCUMENT_BYTES) {
                    throw $this->failure('El archivo supera el tamaño permitido.');
                }
            }
            if ($bytes === '' || ($declaredSize !== null && strlen($bytes) !== $declaredSize)) {
                throw $this->failure('El tamaño recibido no coincide con el archivo.');
            }
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('No fue posible validar el archivo.', 0, $exception);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extension = is_string($mime) ? (self::MIME_EXTENSIONS[$mime] ?? null) : null;
        if (!is_string($mime) || $extension === null) {
            throw $this->failure('El formato del archivo no está permitido.');
        }
        return ['bytes' => $bytes, 'mime' => $mime, 'extension' => $extension];
    }

    private function writeStoredFile(string $storedName, string $bytes): string
    {
        $this->ensureDirectory();
        $path = rtrim($this->documentDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw $this->failure('No fue posible guardar el archivo privado.');
        }
        try {
            $written = 0;
            $length = strlen($bytes);
            while ($written < $length) {
                $count = fwrite($handle, substr($bytes, $written, 65536));
                if (!is_int($count) || $count <= 0) {
                    throw $this->failure('No fue posible guardar el archivo completo.');
                }
                $written += $count;
            }
            if (!fflush($handle) || $written !== $length) {
                throw $this->failure('No fue posible confirmar el archivo guardado.');
            }
        } catch (Throwable $exception) {
            fclose($handle);
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible guardar el archivo.', 0, $exception);
        }
        fclose($handle);
        @chmod($path, 0600);
        $stat = @stat($path);
        if (!is_array($stat) || (int) $stat['size'] !== $length) {
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
            throw $this->failure('No fue posible verificar el archivo guardado.');
        }
        return $path;
    }

    private function ensureDirectory(): void
    {
        if ($this->documentDirectory === '' || is_link($this->documentDirectory)) {
            throw $this->failure('El almacenamiento privado no es válido.');
        }
        if (!is_dir($this->documentDirectory)
            && !mkdir($this->documentDirectory, 0700, true)
            && !is_dir($this->documentDirectory)
        ) {
            throw $this->failure('No fue posible preparar el almacenamiento privado.');
        }
        if (is_link($this->documentDirectory)) {
            throw $this->failure('El almacenamiento privado no es válido.');
        }
        @chmod($this->documentDirectory, 0700);
    }

    private function documentPath(string $storedName): ?string
    {
        if (preg_match('/\Adocument-[a-f0-9]{32}\.(?:pdf|jpg|png|webp)\z/', $storedName) !== 1
            || is_link($this->documentDirectory)
        ) {
            return null;
        }
        $directory = realpath($this->documentDirectory);
        if (!is_string($directory) || !is_dir($directory)) {
            return null;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $storedName;
        if (is_link($path)) {
            return null;
        }
        $resolved = realpath($path);
        if (!is_string($resolved) || dirname($resolved) !== $directory) {
            return null;
        }
        return $resolved;
    }

    /** @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        foreach (['id', 'property_id', 'lease_id', 'size_bytes', 'supersedes_document_id', 'created_by_user_id'] as $key) {
            $row[$key] = $row[$key] === null ? null : (int) $row[$key];
        }
        foreach (['type', 'title', 'stored_name', 'mime_type', 'sha256', 'created_at'] as $key) {
            $row[$key] = $row[$key] === null ? null : (string) $row[$key];
        }
        if ($row['content_json'] !== null) {
            try {
                $content = json_decode((string) $row['content_json'], true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return [];
            }
            if (!is_array($content)) {
                return [];
            }
            $row['content_json'] = $content;
        }
        return $row;
    }

    /** @param list<array<string,mixed>> $rows
     *  @return list<array<string,mixed>>
     */
    private function normalizeRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $document = $this->normalizeRow($row);
            if ($document !== []) {
                $normalized[] = $document;
            }
        }
        return $normalized;
    }

    private function failure(string $message = 'No fue posible guardar el documento.'): RuntimeException
    {
        return new RuntimeException($message);
    }
}
