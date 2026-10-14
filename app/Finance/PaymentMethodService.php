<?php

declare(strict_types=1);

namespace AlquilaPro\Finance;

use PDO;
use PDOException;
use RuntimeException;
use Psr\Http\Message\UploadedFileInterface;

final class PaymentMethodService
{
    private const TYPES = ['bank_account', 'breb_key', 'breb_qr', 'wallet', 'manual'];
    private const BREB_KEY_TYPES = ['identity_document', 'mobile', 'email', 'alphanumeric', 'merchant_code'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $qrDirectory,
    ) {
    }

    public function create(array $input, ?UploadedFileInterface $qr): array
    {
        $data = $this->normalizeInput($input);
        if ($data['type'] === 'breb_qr' || $qr !== null) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO payment_methods '
            . '(property_id, type, label, recipient_name, institution_name, account_type, account_number, '
            . 'breb_key_type, breb_key_value, wallet_identifier, instructions, sort_order, is_active) '
            . 'VALUES (:property_id, :type, :label, :recipient_name, :institution_name, :account_type, :account_number, '
            . ':breb_key_type, :breb_key_value, :wallet_identifier, :instructions, :sort_order, :is_active)',
        );

        try {
            $statement->execute($data);
        } catch (PDOException $exception) {
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
        if ($id <= 0 || $this->find($id) === null) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $data = $this->normalizeInput($input);
        if ($data['type'] === 'breb_qr' || $qr !== null) {
            throw new RuntimeException('No fue posible guardar el medio de pago.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE payment_methods SET '
            . 'property_id = :property_id, type = :type, label = :label, recipient_name = :recipient_name, '
            . 'institution_name = :institution_name, account_type = :account_type, account_number = :account_number, '
            . 'breb_key_type = :breb_key_type, breb_key_value = :breb_key_value, wallet_identifier = :wallet_identifier, '
            . 'instructions = :instructions, sort_order = :sort_order, is_active = :is_active, updated_at = CURRENT_TIMESTAMP '
            . 'WHERE id = :id',
        );

        try {
            $statement->execute($data + ['id' => $id]);
        } catch (PDOException $exception) {
            throw new RuntimeException('No fue posible guardar el medio de pago.', 0, $exception);
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
}
