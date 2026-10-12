<?php

declare(strict_types=1);

namespace AlquilaPro\Properties;

use PDO;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

final class UnitMediaService
{
    private const MAX_PHOTO_BYTES = 10 * 1024 * 1024;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $mediaDirectory,
    ) {
    }

    public function addPhoto(int $unitId, UploadedFileInterface $upload): array
    {
        $this->requireUnit($unitId);

        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No fue posible recibir la foto.');
        }

        $stream = $upload->getStream();
        $size = $stream->getSize();
        if ($size === null || $size < 1 || $size > self::MAX_PHOTO_BYTES) {
            throw new RuntimeException('La foto supera 10 MiB o está vacía.');
        }

        $stream->rewind();
        $bytes = $stream->getContents();
        if (strlen($bytes) !== $size) {
            throw new RuntimeException('No fue posible validar la foto completa.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('El formato de la foto no es válido.');
        }

        $this->ensureDirectory();
        $storedName = 'photo-' . bin2hex(random_bytes(16)) . '.' . self::MIME_EXTENSIONS[$mime];
        $path = $this->mediaPath($storedName, false);
        if ($path === null) {
            throw new RuntimeException('No fue posible preparar el almacenamiento de la foto.');
        }

        try {
            $upload->moveTo($path);
            @chmod($path, 0600);

            $orderStatement = $this->pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM unit_media WHERE unit_id = :unit_id',
            );
            $orderStatement->execute(['unit_id' => $unitId]);
            $sortOrder = (int) $orderStatement->fetchColumn();

            $statement = $this->pdo->prepare(
                "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, external_url, sort_order, is_cover) "
                . "VALUES (:unit_id, 'photo', :stored_name, :mime_type, NULL, :sort_order, 0)",
            );
            $statement->execute([
                'unit_id' => $unitId,
                'stored_name' => $storedName,
                'mime_type' => $mime,
                'sort_order' => $sortOrder,
            ]);
        } catch (Throwable $exception) {
            @unlink($path);
            throw new RuntimeException('No fue posible guardar la foto.', 0, $exception);
        }

        $media = $this->findMedia($unitId, (int) $this->pdo->lastInsertId());
        if ($media === null) {
            @unlink($path);
            throw new RuntimeException('No fue posible confirmar la foto guardada.');
        }

        return $media;
    }

    public function remove(int $unitId, int $mediaId): void
    {
        $media = $this->findMedia($unitId, $mediaId);
        if ($media === null) {
            throw new RuntimeException('El archivo multimedia no pertenece a esta unidad.');
        }

        $path = null;
        if ($media['type'] === 'photo' && is_string($media['stored_name'])) {
            $path = $this->mediaPath($media['stored_name'], true);
        }

        $statement = $this->pdo->prepare('DELETE FROM unit_media WHERE id = :id AND unit_id = :unit_id');
        $statement->execute(['id' => $mediaId, 'unit_id' => $unitId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('No fue posible eliminar el archivo multimedia.');
        }

        if ($path !== null && is_file($path) && !@unlink($path)) {
            throw new RuntimeException('No fue posible eliminar el archivo de la foto.');
        }
    }

    public function listForUnit(int $unitId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM unit_media WHERE unit_id = :unit_id ORDER BY sort_order ASC, id ASC',
        );
        $statement->execute(['unit_id' => $unitId]);

        return array_map($this->normalizeMedia(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function privatePhoto(int $unitId, int $mediaId): ?array
    {
        $media = $this->findMedia($unitId, $mediaId);
        if ($media === null || $media['type'] !== 'photo' || !is_string($media['stored_name'])) {
            return null;
        }

        $path = $this->mediaPath($media['stored_name'], true);
        if ($path === null || !is_file($path)) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime]) || $mime !== $media['mime_type']) {
            return null;
        }

        return [
            'path' => $path,
            'mime' => $mime,
            'stored_name' => $media['stored_name'],
        ];
    }

    public function publicPhoto(int $mediaId): ?array
    {
        if ($mediaId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT m.unit_id FROM unit_media m '
            . 'INNER JOIN units u ON u.id = m.unit_id '
            . 'INNER JOIN properties p ON p.id = u.property_id '
            . "WHERE m.id = :id AND m.type = 'photo' AND p.is_active = 1 "
            . "AND u.status = 'available' AND u.is_public = 1 LIMIT 1",
        );
        $statement->execute(['id' => $mediaId]);
        $unitId = $statement->fetchColumn();
        if ($unitId === false) {
            return null;
        }

        return $this->privatePhoto((int) $unitId, $mediaId);
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

    private function findMedia(int $unitId, int $mediaId): ?array
    {
        if ($unitId <= 0 || $mediaId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM unit_media WHERE id = :id AND unit_id = :unit_id LIMIT 1',
        );
        $statement->execute(['id' => $mediaId, 'unit_id' => $unitId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeMedia($row) : null;
    }

    private function normalizeMedia(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'unit_id' => (int) $row['unit_id'],
            'type' => (string) $row['type'],
            'stored_name' => $row['stored_name'] !== null ? (string) $row['stored_name'] : null,
            'mime_type' => $row['mime_type'] !== null ? (string) $row['mime_type'] : null,
            'external_url' => $row['external_url'] !== null ? (string) $row['external_url'] : null,
            'sort_order' => (int) $row['sort_order'],
            'is_cover' => (bool) $row['is_cover'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->mediaDirectory)
            && !mkdir($this->mediaDirectory, 0700, true)
            && !is_dir($this->mediaDirectory)
        ) {
            throw new RuntimeException('No fue posible preparar el almacenamiento de fotos.');
        }
    }

    private function mediaPath(string $storedName, bool $mustExist): ?string
    {
        if (preg_match('/^photo-[a-f0-9]{32}\.(?:jpg|png|webp)$/', $storedName) !== 1) {
            return null;
        }

        $path = rtrim($this->mediaDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;
        if ($mustExist && !is_file($path)) {
            return null;
        }

        return $path;
    }
}
