<?php

declare(strict_types=1);

namespace AlquilaPro\Maintenance;

use AlquilaPro\Finance\FinanceService;
use PDO;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

final readonly class MaintenanceService
{
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    private const STATUSES = ['open', 'in_progress', 'resolved', 'cancelled'];
    private const DAMAGE_CLASSIFICATIONS = [
        'normal_wear', 'tenant_damage', 'owner_responsibility', 'third_party', 'not_applicable',
    ];
    private const MAX_PHOTO_BYTES = 5 * 1024 * 1024;
    private const PHOTO_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private string $photoDirectory;

    public function __construct(
        private PDO $pdo,
        private FinanceService $finance,
        string $photoDirectory,
    ) {
        $directory = rtrim(trim($photoDirectory), '/\\');
        if ($directory === '') {
            throw new RuntimeException('No fue posible preparar las fotos de mantenimiento.');
        }
        $this->photoDirectory = $directory;
    }

    /** @return array<string, mixed> */
    public function create(int $leaseId, string $category, string $priority, string $description, int $actorUserId): array
    {
        $category = trim($category);
        $description = trim($description);
        if ($leaseId <= 0 || $actorUserId <= 0
            || mb_strlen($category, 'UTF-8') < 1 || mb_strlen($category, 'UTF-8') > 80
            || !in_array($priority, self::PRIORITIES, true)
            || $description === '' || mb_strlen($description, 'UTF-8') > 5000
        ) {
            throw new RuntimeException('No fue posible crear la solicitud de mantenimiento.');
        }

        try {
            $lease = $this->pdo->prepare('SELECT id FROM leases WHERE id = :id LIMIT 1');
            $lease->execute(['id' => $leaseId]);
            if ($lease->fetchColumn() === false) {
                throw new RuntimeException('No fue posible crear la solicitud de mantenimiento.');
            }
            $insert = $this->pdo->prepare(
                'INSERT INTO maintenance_requests '
                . '(lease_id, category, priority, description, photos_json, created_by_user_id) '
                . 'VALUES (:lease_id, :category, :priority, :description, :photos_json, :actor_id)',
            );
            $insert->execute([
                'lease_id' => $leaseId,
                'category' => $category,
                'priority' => $priority,
                'description' => $description,
                'photos_json' => '[]',
                'actor_id' => $actorUserId,
            ]);
            $request = $this->find((int) $this->pdo->lastInsertId());
            if ($request === null) {
                throw new RuntimeException('No fue posible crear la solicitud de mantenimiento.');
            }
            return $request;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('No fue posible crear la solicitud de mantenimiento.');
        }
    }

    /** @return array<string, mixed>|null */
    public function find(int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT * FROM maintenance_requests WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $requestId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function listForLease(int $leaseId): array
    {
        if ($leaseId <= 0) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT * FROM maintenance_requests WHERE lease_id = :lease_id ORDER BY created_at DESC, id DESC',
        );
        $statement->execute(['lease_id' => $leaseId]);
        return array_map(fn (array $row): array => $this->normalizeRow($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function listForStaff(?string $status = null): array
    {
        if ($status !== null && !in_array($status, self::STATUSES, true)) {
            return [];
        }
        $sql = 'SELECT * FROM maintenance_requests';
        if ($status !== null) {
            $sql .= ' WHERE status = :status';
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 200';
        $statement = $this->pdo->prepare($sql);
        if ($status !== null) {
            $statement->execute(['status' => $status]);
        } else {
            $statement->execute();
        }
        return array_map(fn (array $row): array => $this->normalizeRow($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array{id:int,full_name:string,role:string}> */
    public function listActiveStaff(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, full_name, role FROM users WHERE is_active = 1 AND role IN ('owner', 'admin', 'gestor') ORDER BY full_name, id",
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
            'role' => (string) $row['role'],
        ], $rows);
    }

    public function assign(int $requestId, ?int $assigneeUserId, int $actorUserId, int $now): void
    {
        if ($requestId <= 0 || $actorUserId <= 0 || $now <= 0 || ($assigneeUserId !== null && $assigneeUserId <= 0)) {
            throw new RuntimeException('No fue posible asignar la solicitud.');
        }
        $this->mutateLocked($requestId, function (array $request) use ($assigneeUserId, $now): array {
            if ($assigneeUserId !== null) {
                $staff = $this->pdo->prepare(
                    "SELECT id FROM users WHERE id = :id AND is_active = 1 AND role IN ('owner', 'admin', 'gestor') LIMIT 1",
                );
                $staff->execute(['id' => $assigneeUserId]);
                if ($staff->fetchColumn() === false) {
                    throw new RuntimeException('No fue posible asignar la solicitud.');
                }
            }
            $update = $this->pdo->prepare(
                'UPDATE maintenance_requests SET assigned_user_id = :assigned, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute([
                'assigned' => $assigneeUserId,
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => (int) $request['id'],
            ]);
            return $request;
        });
    }

    public function transitionStatus(
        int $requestId,
        string $targetStatus,
        int $actorUserId,
        int $now,
        ?string $notes = null,
        ?string $cancelReason = null,
    ): void {
        $notes = $notes === null ? null : trim($notes);
        $cancelReason = $cancelReason === null ? null : trim($cancelReason);
        if ($requestId <= 0 || $actorUserId <= 0 || $now <= 0 || !in_array($targetStatus, self::STATUSES, true)
            || ($notes !== null && mb_strlen($notes, 'UTF-8') > 1000)
            || ($cancelReason !== null && mb_strlen($cancelReason, 'UTF-8') > 500)
            || ($targetStatus === 'cancelled' && ($cancelReason === null || $cancelReason === ''))
        ) {
            throw new RuntimeException('No fue posible actualizar el estado de la solicitud.');
        }

        $this->mutateLocked($requestId, function (array $request) use (
            $targetStatus,
            $actorUserId,
            $now,
            $notes,
            $cancelReason,
        ): array {
            $transitions = [
                'open' => ['in_progress', 'resolved', 'cancelled'],
                'in_progress' => ['resolved', 'cancelled'],
            ];
            if (!in_array($targetStatus, $transitions[(string) $request['status']] ?? [], true)) {
                throw new RuntimeException('No fue posible actualizar el estado de la solicitud.');
            }
            if ($targetStatus === 'cancelled' && $request['charge_id'] !== null) {
                throw new RuntimeException('No fue posible actualizar el estado de la solicitud.');
            }
            if (in_array($targetStatus, ['resolved', 'cancelled'], true)) {
                $activeVisit = $this->pdo->prepare(
                    'SELECT 1 FROM maintenance_visits WHERE maintenance_request_id = :request_id '
                    . 'AND status IN (\'proposed\', \'scheduled\') LIMIT 1',
                );
                $activeVisit->execute(['request_id' => (int) $request['id']]);
                if ($activeVisit->fetchColumn() !== false) {
                    throw new RuntimeException('No fue posible actualizar el estado de la solicitud.');
                }
            }
            $timestamp = gmdate('Y-m-d H:i:s', $now);
            if ($targetStatus === 'resolved') {
                $update = $this->pdo->prepare(
                    'UPDATE maintenance_requests SET status = :status, resolution_notes = :notes, '
                    . 'resolved_by_user_id = :actor_id, resolved_at = :resolved_at, updated_at = :updated_at WHERE id = :id',
                );
                $update->execute([
                    'status' => $targetStatus,
                    'notes' => $notes,
                    'actor_id' => $actorUserId,
                    'resolved_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'id' => (int) $request['id'],
                ]);
            } elseif ($targetStatus === 'cancelled') {
                $update = $this->pdo->prepare(
                    'UPDATE maintenance_requests SET status = :status, cancelled_by_user_id = :actor_id, '
                    . 'cancelled_at = :cancelled_at, cancel_reason = :cancel_reason, updated_at = :updated_at WHERE id = :id',
                );
                $update->execute([
                    'status' => $targetStatus,
                    'actor_id' => $actorUserId,
                    'cancelled_at' => $timestamp,
                    'cancel_reason' => $cancelReason,
                    'updated_at' => $timestamp,
                    'id' => (int) $request['id'],
                ]);
            } else {
                $update = $this->pdo->prepare(
                    'UPDATE maintenance_requests SET status = :status, updated_at = :updated_at WHERE id = :id',
                );
                $update->execute([
                    'status' => $targetStatus,
                    'updated_at' => $timestamp,
                    'id' => (int) $request['id'],
                ]);
            }
            return $request;
        });
    }

    public function classifyDamage(int $requestId, string $classification, int $now): void
    {
        if ($requestId <= 0 || $now <= 0 || !in_array($classification, self::DAMAGE_CLASSIFICATIONS, true)) {
            throw new RuntimeException('No fue posible clasificar la solicitud.');
        }
        $this->mutateLocked($requestId, function (array $request) use ($classification, $now): array {
            if ($request['charge_id'] !== null) {
                throw new RuntimeException('No fue posible clasificar la solicitud.');
            }
            $update = $this->pdo->prepare(
                'UPDATE maintenance_requests SET damage_classification = :classification, updated_at = :updated_at '
                . 'WHERE id = :id',
            );
            $update->execute([
                'classification' => $classification,
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => (int) $request['id'],
            ]);
            return $request;
        });
    }

    /** @return array<string, mixed> */
    public function addPhoto(int $requestId, UploadedFileInterface $upload, int $actorUserId, int $now): array
    {
        if ($requestId <= 0 || $actorUserId <= 0 || $now <= 0) {
            throw new RuntimeException('No fue posible guardar la foto.');
        }
        [$bytes, $mime] = $this->readImageUpload($upload);
        $photoId = bin2hex(random_bytes(16));
        $storedName = bin2hex(random_bytes(32)) . '.' . self::PHOTO_EXTENSIONS[$mime];
        $directory = $this->preparePhotoDirectory();
        $path = $directory . DIRECTORY_SEPARATOR . $storedName;
        $handle = @fopen($path, 'xb');
        if (!is_resource($handle)) {
            throw new RuntimeException('No fue posible guardar la foto.');
        }
        try {
            $written = 0;
            $length = strlen($bytes);
            while ($written < $length) {
                $count = fwrite($handle, substr($bytes, $written));
                if (!is_int($count) || $count <= 0) {
                    throw new RuntimeException('No fue posible guardar la foto.');
                }
                $written += $count;
            }
            fflush($handle);
            @chmod($path, 0600);
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($path);
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible guardar la foto.');
        }
        fclose($handle);

        $metadata = [
            'photo_id' => $photoId,
            'stored_name' => $storedName,
            'mime' => $mime,
            'sha256' => hash('sha256', $bytes),
            'size_bytes' => strlen($bytes),
            'uploaded_by_user_id' => $actorUserId,
            'created_at' => gmdate('Y-m-d H:i:s', $now),
        ];
        try {
            $this->mutateLocked($requestId, function (array $request) use ($metadata, $now): array {
                $photos = $request['photos'];
                $photos[] = $metadata;
                $update = $this->pdo->prepare(
                    'UPDATE maintenance_requests SET photos_json = :photos_json, updated_at = :updated_at WHERE id = :id',
                );
                $update->execute([
                    'photos_json' => json_encode($photos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                    'updated_at' => gmdate('Y-m-d H:i:s', $now),
                    'id' => (int) $request['id'],
                ]);
                return $request;
            });
        } catch (Throwable $exception) {
            @unlink($path);
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible guardar la foto.');
        }
        return $metadata;
    }

    /** @return array{bytes:string,mime:string}|null */
    public function privatePhoto(int $requestId, string $photoId): ?array
    {
        if ($requestId <= 0 || preg_match('/\A[a-f0-9]{32}\z/', $photoId) !== 1) {
            return null;
        }
        try {
            $request = $this->find($requestId);
        } catch (RuntimeException) {
            return null;
        }
        if ($request === null) {
            return null;
        }
        foreach ($request['photos'] as $photo) {
            if (!is_array($photo) || !hash_equals((string) ($photo['photo_id'] ?? ''), $photoId)) {
                continue;
            }
            $storedName = $photo['stored_name'] ?? null;
            $mime = $photo['mime'] ?? null;
            $expectedHash = $photo['sha256'] ?? null;
            $expectedSize = $photo['size_bytes'] ?? null;
            if (!is_string($storedName) || preg_match('/\A[a-f0-9]{64}\.(?:png|jpg|webp)\z/', $storedName) !== 1
                || !is_string($mime) || !isset(self::PHOTO_EXTENSIONS[$mime])
                || !is_string($expectedHash) || preg_match('/\A[a-f0-9]{64}\z/', $expectedHash) !== 1
                || !is_int($expectedSize) || $expectedSize < 1 || $expectedSize > self::MAX_PHOTO_BYTES
            ) {
                return null;
            }
            $directory = realpath($this->photoDirectory);
            if ($directory === false || !is_dir($directory) || !is_readable($directory)) {
                return null;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $storedName;
            if (is_link($path)) {
                return null;
            }
            $handle = @fopen($path, 'rb');
            if (!is_resource($handle)) {
                return null;
            }
            $stat = fstat($handle);
            $bytes = is_array($stat) && (int) ($stat['size'] ?? -1) === $expectedSize
                ? stream_get_contents($handle, self::MAX_PHOTO_BYTES + 1)
                : false;
            fclose($handle);
            if (!is_string($bytes) || strlen($bytes) !== $expectedSize
                || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $mime
                || !hash_equals($expectedHash, hash('sha256', $bytes))
            ) {
                return null;
            }
            return ['bytes' => $bytes, 'mime' => $mime];
        }
        return null;
    }

    /** @return array<string, mixed> */
    public function createRepairCharge(
        int $requestId,
        string $amount,
        string $dueDate,
        string $description,
        int $actorUserId,
    ): array {
        if ($requestId <= 0 || $actorUserId <= 0 || $this->pdo->inTransaction()) {
            throw new RuntimeException('No fue posible registrar el cargo de reparación.');
        }
        try {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('No fue posible registrar el cargo de reparación.');
            }
            $lock = $this->pdo->prepare('SELECT * FROM maintenance_requests WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $requestId]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || $row['status'] === 'cancelled'
                || $row['damage_classification'] !== 'tenant_damage' || $row['charge_id'] !== null
            ) {
                throw new RuntimeException('No fue posible registrar el cargo de reparación.');
            }
            $charge = $this->finance->createManualCharge(
                (int) $row['lease_id'], 'repair', $amount, $dueDate, $description, null, $actorUserId,
            );
            $update = $this->pdo->prepare(
                'UPDATE maintenance_requests SET charge_id = :charge_id, updated_at = CURRENT_TIMESTAMP '
                . 'WHERE id = :id AND charge_id IS NULL AND status <> \'cancelled\'',
            );
            $update->execute(['charge_id' => (int) $charge['id'], 'id' => $requestId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('No fue posible registrar el cargo de reparación.');
            }
            $this->pdo->commit();
            return $charge;
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible registrar el cargo de reparación.');
        }
    }

    /** @return array{0:string,1:string} */
    private function readImageUpload(UploadedFileInterface $upload): array
    {
        if ($upload->getError() !== UPLOAD_ERR_OK || ($upload->getSize() ?? 0) > self::MAX_PHOTO_BYTES) {
            throw new RuntimeException('No fue posible guardar la foto.');
        }
        $stream = $upload->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $bytes = '';
        while (!$stream->eof() && strlen($bytes) <= self::MAX_PHOTO_BYTES) {
            $chunk = $stream->read(min(8192, self::MAX_PHOTO_BYTES + 1 - strlen($bytes)));
            if ($chunk === '') {
                break;
            }
            $bytes .= $chunk;
        }
        if ($bytes === '' || strlen($bytes) > self::MAX_PHOTO_BYTES) {
            throw new RuntimeException('No fue posible guardar la foto.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!is_string($mime) || !isset(self::PHOTO_EXTENSIONS[$mime])) {
            throw new RuntimeException('No fue posible guardar la foto.');
        }
        return [$bytes, $mime];
    }

    /** @param callable(array<string, mixed>):array $operation */
    private function mutateLocked(int $requestId, callable $operation): void
    {
        if ($requestId <= 0 || $this->pdo->inTransaction()) {
            throw new RuntimeException('No fue posible actualizar la solicitud.');
        }
        try {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('No fue posible actualizar la solicitud.');
            }
            $statement = $this->pdo->prepare('SELECT * FROM maintenance_requests WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $requestId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new RuntimeException('No fue posible actualizar la solicitud.');
            }
            $request = $this->normalizeRow($row);
            if (in_array($request['status'], ['resolved', 'cancelled'], true)) {
                throw new RuntimeException('No fue posible actualizar la solicitud.');
            }
            $operation($request);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('No fue posible actualizar la solicitud.');
        }
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'lease_id' => (int) $row['lease_id'],
            'category' => (string) $row['category'],
            'priority' => (string) $row['priority'],
            'description' => (string) $row['description'],
            'photos' => $this->decodePhotos($row['photos_json'] ?? null),
            'status' => (string) $row['status'],
            'assigned_user_id' => $row['assigned_user_id'] === null ? null : (int) $row['assigned_user_id'],
            'damage_classification' => $row['damage_classification'] === null ? null : (string) $row['damage_classification'],
            'resolution_notes' => $row['resolution_notes'] === null ? null : (string) $row['resolution_notes'],
            'charge_id' => $row['charge_id'] === null ? null : (int) $row['charge_id'],
            'created_by_user_id' => $row['created_by_user_id'] === null ? null : (int) $row['created_by_user_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
            'resolved_by_user_id' => $row['resolved_by_user_id'] === null ? null : (int) $row['resolved_by_user_id'],
            'resolved_at' => $row['resolved_at'] === null ? null : (string) $row['resolved_at'],
            'cancelled_by_user_id' => $row['cancelled_by_user_id'] === null ? null : (int) $row['cancelled_by_user_id'],
            'cancelled_at' => $row['cancelled_at'] === null ? null : (string) $row['cancelled_at'],
            'cancel_reason' => $row['cancel_reason'] === null ? null : (string) $row['cancel_reason'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function decodePhotos(mixed $value): array
    {
        if (!is_string($value)) {
            throw new RuntimeException('No fue posible leer las fotos de mantenimiento.');
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('No fue posible leer las fotos de mantenimiento.');
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new RuntimeException('No fue posible leer las fotos de mantenimiento.');
        }
        return $decoded;
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function preparePhotoDirectory(): string
    {
        if (!is_dir($this->photoDirectory)
            && !@mkdir($this->photoDirectory, 0700, true)
            && !is_dir($this->photoDirectory)
        ) {
            throw new RuntimeException('No fue posible preparar las fotos de mantenimiento.');
        }
        @chmod($this->photoDirectory, 0700);
        $resolved = realpath($this->photoDirectory);
        if ($resolved === false || !is_dir($resolved) || !is_writable($resolved)) {
            throw new RuntimeException('No fue posible preparar las fotos de mantenimiento.');
        }
        return $resolved;
    }
}
