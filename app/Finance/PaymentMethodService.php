<?php

declare(strict_types=1);

namespace AlquilaPro\Finance;

use PDO;
use RuntimeException;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

final class PaymentMethodService
{
    private const TYPES = ['bank_account', 'breb_key', 'breb_qr', 'wallet', 'manual'];
    private const BREB_KEY_TYPES = ['identity_document', 'mobile', 'email', 'alphanumeric', 'merchant_code'];
    private const MAX_QR_BYTES = 2 * 1024 * 1024;
    private const QR_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $qrDirectory,
    ) {
    }

    public function create(array $input, ?UploadedFileInterface $qr): array
    {
        $data = $this->normalizeInput($input);
        if ($data['type'] !== 'breb_qr' && $qr !== null) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $storedQr = null;
        if ($data['type'] === 'breb_qr') {
            if ($qr === null) {
                throw new RuntimeException('No fue posible guardar el medio de pago.');
            }
            $storedQr = $this->storeQrUpload($qr);
            $data = array_replace($data, $storedQr['metadata']);
        }

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO payment_methods '
                . '(property_id, type, label, recipient_name, institution_name, account_type, account_number, '
                . 'breb_key_type, breb_key_value, wallet_identifier, instructions, qr_stored_name, qr_mime_type, '
                . 'qr_sha256, qr_size_bytes, sort_order, is_active) '
                . 'VALUES (:property_id, :type, :label, :recipient_name, :institution_name, :account_type, :account_number, '
                . ':breb_key_type, :breb_key_value, :wallet_identifier, :instructions, :qr_stored_name, :qr_mime_type, '
                . ':qr_sha256, :qr_size_bytes, :sort_order, :is_active)',
            );
            $statement->execute($data);
        } catch (Throwable $exception) {
            if ($storedQr !== null) {
                $this->removeQrFile($storedQr['stored_name']);
            }
            throw new RuntimeException('No fue posible guardar el medio de pago.', 0, $exception);
        }

        $method = $this->find((int) $this->pdo->lastInsertId());
        if ($method === null) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        return $method;
    }

    public function update(int $id, array $input, ?UploadedFileInterface $qr, bool $removeQr): array
    {
        $current = $this->find($id);
        if ($id <= 0 || $current === null) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $data = $this->normalizeInput($input);
        if ($data['type'] !== 'breb_qr' && $qr !== null) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $oldStoredName = is_string($current['qr_stored_name']) ? $current['qr_stored_name'] : null;
        $storedQr = null;
        if ($data['type'] === 'breb_qr') {
            if ($qr !== null) {
                $storedQr = $this->storeQrUpload($qr);
                $data = array_replace($data, $storedQr['metadata']);
            } elseif ($removeQr) {
                throw new RuntimeException('No fue posible guardar el medio de pago.');
            } else {
                if ($this->privateQr($id) === null) {
                    throw new RuntimeException('No fue posible guardar el medio de pago.');
                }
                $data['qr_stored_name'] = $current['qr_stored_name'];
                $data['qr_mime_type'] = $current['qr_mime_type'];
                $data['qr_sha256'] = $current['qr_sha256'];
                $data['qr_size_bytes'] = (int) $current['qr_size_bytes'];
            }
        }

        try {
            $statement = $this->pdo->prepare(
                'UPDATE payment_methods SET '
                . 'property_id = :property_id, type = :type, label = :label, recipient_name = :recipient_name, '
                . 'institution_name = :institution_name, account_type = :account_type, account_number = :account_number, '
                . 'breb_key_type = :breb_key_type, breb_key_value = :breb_key_value, wallet_identifier = :wallet_identifier, '
                . 'instructions = :instructions, qr_stored_name = :qr_stored_name, qr_mime_type = :qr_mime_type, '
                . 'qr_sha256 = :qr_sha256, qr_size_bytes = :qr_size_bytes, sort_order = :sort_order, is_active = :is_active, '
                . 'updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            );
            $statement->execute($data + ['id' => $id]);
        } catch (Throwable $exception) {
            if ($storedQr !== null) {
                $this->removeQrFile($storedQr['stored_name']);
            }
            throw new RuntimeException('No fue posible guardar el medio de pago.', 0, $exception);
        }

        $newStoredName = $data['qr_stored_name'];
        if ($oldStoredName !== null && $oldStoredName !== $newStoredName) {
            $this->removeQrFile($oldStoredName);
        }

        $method = $this->find($id);
        if ($method === null) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        return $method;
    }

    public function listAll(): array
    {
        $rows = $this->pdo->query('SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC')
            ->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    public function listForProperty(int $propertyId): array
    {
        if ($propertyId <= 0) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM payment_methods '
            . 'WHERE is_active = 1 AND (property_id IS NULL OR property_id = :property_id) '
            . 'ORDER BY sort_order ASC, id ASC',
        );
        $statement->execute(['property_id' => $propertyId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT * FROM payment_methods WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /** @return array{path:string,mime:string,size_bytes:int}|null */
    public function privateQr(int $id): ?array
    {
        try {
            $method = $this->find($id);
        } catch (Throwable) {
            return null;
        }
        if ($method === null || $method['type'] !== 'breb_qr') {
            return null;
        }

        $storedName = $method['qr_stored_name'];
        $storedMime = $method['qr_mime_type'];
        $storedHash = $method['qr_sha256'];
        $storedSize = $method['qr_size_bytes'];
        if (!is_string($storedName)
            || !is_string($storedMime)
            || !is_string($storedHash)
            || !is_int($storedSize)
            || !isset(self::QR_MIME_EXTENSIONS[$storedMime])
            || preg_match('/^[a-f0-9]{64}$/i', $storedHash) !== 1
        ) {
            return null;
        }

        $path = $this->qrPath($storedName, true);
        if ($path === null) {
            return null;
        }

        $size = @filesize($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $hash = @hash_file('sha256', $path);
        if (!is_int($size)
            || $size < 1
            || $size > self::MAX_QR_BYTES
            || $size !== $storedSize
            || !is_string($mime)
            || $mime !== $storedMime
            || pathinfo($storedName, PATHINFO_EXTENSION) !== self::QR_MIME_EXTENSIONS[$mime]
            || !is_string($hash)
            || !hash_equals(strtolower($storedHash), $hash)
        ) {
            return null;
        }

        return ['path' => $path, 'mime' => $mime, 'size_bytes' => $size];
    }

    private function normalizeInput(array $input): array
    {
        $type = $this->requiredText($input, 'type', 32);
        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $data = [
            'property_id' => $this->normalizePropertyId($input['property_id'] ?? null),
            'type' => $type,
            'label' => $this->requiredText($input, 'label', 120),
            'recipient_name' => in_array($type, ['bank_account', 'breb_key', 'breb_qr', 'wallet'], true)
                ? $this->optionalText($input, 'recipient_name', 190)
                : null,
            'institution_name' => in_array($type, ['bank_account', 'breb_key', 'breb_qr', 'wallet'], true)
                ? $this->optionalText($input, 'institution_name', 190)
                : null,
            'account_type' => $type === 'bank_account' ? $this->optionalText($input, 'account_type', 40) : null,
            'account_number' => $type === 'bank_account' ? $this->optionalText($input, 'account_number', 120) : null,
            'breb_key_type' => $type === 'breb_key' ? $this->optionalText($input, 'breb_key_type', 40) : null,
            'breb_key_value' => $type === 'breb_key' ? $this->optionalText($input, 'breb_key_value', 190) : null,
            'wallet_identifier' => $type === 'wallet' ? $this->optionalText($input, 'wallet_identifier', 190) : null,
            'instructions' => $this->optionalText($input, 'instructions', 1000),
            'qr_stored_name' => null,
            'qr_mime_type' => null,
            'qr_sha256' => null,
            'qr_size_bytes' => null,
            'sort_order' => $this->normalizeUnsignedInteger($input['sort_order'] ?? 1, 4_294_967_295),
            'is_active' => $this->normalizeActive($input['is_active'] ?? 1),
        ];

        if ($type === 'bank_account') {
            $this->requireValue($data, 'recipient_name');
            $this->requireValue($data, 'institution_name');
            $this->requireValue($data, 'account_type');
            $this->requireValue($data, 'account_number');
        } elseif ($type === 'breb_key') {
            $this->validateBreBKey($data);
        } elseif ($type === 'wallet') {
            $this->requireValue($data, 'institution_name');
            $this->requireValue($data, 'wallet_identifier');
        } elseif ($type === 'manual') {
            $this->requireValue($data, 'instructions');
        }

        return $data;
    }

    private function validateBreBKey(array $data): void
    {
        $keyType = $this->requireValue($data, 'breb_key_type');
        $keyValue = $this->requireValue($data, 'breb_key_value');
        if (!in_array($keyType, self::BREB_KEY_TYPES, true)) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $valid = match ($keyType) {
            'identity_document' => preg_match('/^[a-z0-9]{1,18}$/i', $keyValue) === 1,
            'mobile' => preg_match('/^3[0-9]{9}$/', $keyValue) === 1,
            'email' => $this->validBreBEmail($keyValue),
            'alphanumeric' => preg_match('/^@[a-z0-9]{5,20}$/i', $keyValue) === 1,
            'merchant_code' => preg_match('/^00[0-9]{8}$/', $keyValue) === 1,
            default => false,
        };

        if (!$valid) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }
    }

    private function validBreBEmail(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        [$localPart, $domain] = explode('@', $value, 2);

        return mb_strlen($localPart, 'UTF-8') <= 30 && mb_strlen($domain, 'UTF-8') <= 61;
    }

    private function normalizePropertyId(mixed $value): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $propertyId = $this->normalizeUnsignedInteger($value, PHP_INT_MAX);
        $statement = $this->pdo->prepare('SELECT id FROM properties WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $propertyId]);
        if ($statement->fetchColumn() === false) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        return $propertyId;
    }

    private function normalizeUnsignedInteger(mixed $value, int $maximum): int
    {
        if (!is_int($value) && !is_string($value)) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $digits = trim((string) $value);
        if (preg_match('/^[0-9]+$/', $digits) !== 1) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $digits = ltrim($digits, '0');
        if ($digits === '') {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $maximumString = (string) $maximum;
        if (strlen($digits) > strlen($maximumString)
            || (strlen($digits) === strlen($maximumString) && strcmp($digits, $maximumString) > 0)) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        return (int) $digits;
    }

    private function normalizeActive(mixed $value): int
    {
        if ($value === true || $value === 1 || $value === '1') {
            return 1;
        }
        if ($value === false || $value === 0 || $value === '0') {
            return 0;
        }

        throw new RuntimeException('No fue posible guardar el medio de pago.');
    }

    private function requiredText(array $input, string $field, int $maximumLength): string
    {
        $value = $this->optionalText($input, $field, $maximumLength);
        if ($value === null) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        return $value;
    }

    private function optionalText(array $input, string $field, int $maximumLength): ?string
    {
        if (!array_key_exists($field, $input) || $input[$field] === null) {
            return null;
        }
        if (!is_string($input[$field])) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $value = trim($input[$field]);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value, 'UTF-8') > $maximumLength) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        return $value;
    }

    private function requireValue(array $data, string $field): string
    {
        $value = $data[$field] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        return $value;
    }

    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['property_id'] = $row['property_id'] === null ? null : (int) $row['property_id'];
        $row['sort_order'] = (int) $row['sort_order'];
        $row['is_active'] = (int) $row['is_active'];
        $row['qr_size_bytes'] = $row['qr_size_bytes'] === null ? null : (int) $row['qr_size_bytes'];

        return $row;
    }

    /** @return array{stored_name:string,path:string,metadata:array{qr_stored_name:string,qr_mime_type:string,qr_sha256:string,qr_size_bytes:int}} */
    private function storeQrUpload(UploadedFileInterface $upload): array
    {
        $validated = $this->validateQrUpload($upload);
        $this->ensureQrDirectory();

        $storedName = 'qr-' . bin2hex(random_bytes(16)) . '.' . self::QR_MIME_EXTENSIONS[$validated['mime']];
        $path = $this->qrPath($storedName, false);
        if ($path === null) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        try {
            $upload->moveTo($path);
            @chmod($path, 0600);
            clearstatcache(true, $path);
            $storedSize = @filesize($path);
            $storedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            $storedHash = @hash_file('sha256', $path);
            if (!is_int($storedSize)
                || $storedSize !== $validated['size_bytes']
                || $storedMime !== $validated['mime']
                || !is_string($storedHash)
                || !hash_equals($validated['sha256'], $storedHash)
            ) {
                throw new RuntimeException('No fue posible guardar el medio de pago.');
            }
        } catch (Throwable $exception) {
            if (is_file($path)) {
                @unlink($path);
            }
            throw new RuntimeException('No fue posible guardar el medio de pago.', 0, $exception);
        }

        return [
            'stored_name' => $storedName,
            'path' => $path,
            'metadata' => [
                'qr_stored_name' => $storedName,
                'qr_mime_type' => $validated['mime'],
                'qr_sha256' => $validated['sha256'],
                'qr_size_bytes' => $validated['size_bytes'],
            ],
        ];
    }

    /** @return array{mime:string,size_bytes:int,sha256:string} */
    private function validateQrUpload(UploadedFileInterface $upload): array
    {
        try {
            if ($upload->getError() !== UPLOAD_ERR_OK) {
                throw new RuntimeException('No fue posible guardar el medio de pago.');
            }

            $stream = $upload->getStream();
            $reportedSize = $stream->getSize();
            if (!is_int($reportedSize) || $reportedSize < 1 || $reportedSize > self::MAX_QR_BYTES) {
                throw new RuntimeException('No fue posible guardar el medio de pago.');
            }
            $stream->rewind();
            $bytes = $stream->getContents();
        } catch (Throwable $exception) {
            throw new RuntimeException('No fue posible guardar el medio de pago.', 0, $exception);
        }

        $size = strlen($bytes);
        if ($size !== $reportedSize || $size < 1 || $size > self::MAX_QR_BYTES) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!is_string($mime) || !isset(self::QR_MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        return ['mime' => $mime, 'size_bytes' => $size, 'sha256' => hash('sha256', $bytes)];
    }

    private function ensureQrDirectory(): void
    {
        if (!is_dir($this->qrDirectory)
            && !@mkdir($this->qrDirectory, 0700, true)
            && !is_dir($this->qrDirectory)
        ) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }
        @chmod($this->qrDirectory, 0700);
    }

    private function qrPath(string $storedName, bool $mustExist): ?string
    {
        if (preg_match('/^qr-[a-f0-9]{32}\.(?:jpg|png|webp)$/', $storedName) !== 1) {
            return null;
        }

        $directory = realpath($this->qrDirectory);
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
        $directoryPrefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with(strtolower($resolved), strtolower($directoryPrefix))) {
            return null;
        }

        return $resolved;
    }

    private function removeQrFile(string $storedName): void
    {
        $path = $this->qrPath($storedName, true);
        if ($path !== null && !@unlink($path)) {
            error_log('Payment method QR cleanup failed.');
        }
    }
}
