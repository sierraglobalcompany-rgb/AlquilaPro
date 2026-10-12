<?php

declare(strict_types=1);

namespace AlquilaPro\Properties;

use PDO;
use RuntimeException;

final class PropertyService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createProperty(array $input): array
    {
        $data = $this->validateProperty($input);

        $statement = $this->pdo->prepare(
            'INSERT INTO properties '
            . '(name, address, city, sector, administration_name, administration_contact, notes) '
            . 'VALUES (:name, :address, :city, :sector, :administration_name, :administration_contact, :notes)',
        );
        $statement->execute($data);

        $property = $this->findProperty((int) $this->pdo->lastInsertId());
        if ($property === null) {
            throw new RuntimeException('No fue posible crear la propiedad.');
        }

        return $property;
    }

    public function updateProperty(int $propertyId, array $input): array
    {
        if ($this->findProperty($propertyId) === null) {
            throw new RuntimeException('La propiedad no existe.');
        }

        $data = $this->validateProperty($input);
        $data['id'] = $propertyId;

        $statement = $this->pdo->prepare(
            'UPDATE properties SET '
            . 'name = :name, address = :address, city = :city, sector = :sector, '
            . 'administration_name = :administration_name, administration_contact = :administration_contact, '
            . 'notes = :notes, updated_at = CURRENT_TIMESTAMP '
            . 'WHERE id = :id',
        );
        $statement->execute($data);

        $property = $this->findProperty($propertyId);
        if ($property === null) {
            throw new RuntimeException('No fue posible actualizar la propiedad.');
        }

        return $property;
    }

    public function setPropertyActive(int $propertyId, bool $active): void
    {
        if ($this->findProperty($propertyId) === null) {
            throw new RuntimeException('La propiedad no existe.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE properties SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
        );
        $statement->execute([
            'is_active' => $active ? 1 : 0,
            'id' => $propertyId,
        ]);
    }

    public function listProperties(): array
    {
        $rows = $this->pdo->query('SELECT * FROM properties ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

        return array_map($this->normalizeProperty(...), $rows);
    }

    public function findProperty(int $propertyId): ?array
    {
        if ($propertyId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $propertyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeProperty($row) : null;
    }

    private function validateProperty(array $input): array
    {
        $name = $this->requiredText($input, 'name', 150, 'El nombre de la propiedad es obligatorio.');
        $address = $this->requiredText($input, 'address', 255, 'La dirección de la propiedad es obligatoria.');
        $city = $this->requiredText($input, 'city', 120, 'La ciudad de la propiedad es obligatoria.');

        return [
            'name' => $name,
            'address' => $address,
            'city' => $city,
            'sector' => $this->optionalText($input, 'sector', 120),
            'administration_name' => $this->optionalText($input, 'administration_name', 150),
            'administration_contact' => $this->optionalText($input, 'administration_contact', 190),
            'notes' => $this->optionalText($input, 'notes'),
        ];
    }

    private function requiredText(array $input, string $key, int $maxLength, string $message): string
    {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new RuntimeException($message);
        }

        return $value;
    }

    private function optionalText(array $input, string $key, ?int $maxLength = null): ?string
    {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value === '') {
            return null;
        }

        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            throw new RuntimeException('Uno de los datos de la propiedad supera la longitud permitida.');
        }

        return $value;
    }

    private function normalizeProperty(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'address' => (string) $row['address'],
            'city' => (string) $row['city'],
            'sector' => $row['sector'] !== null ? (string) $row['sector'] : null,
            'administration_name' => $row['administration_name'] !== null ? (string) $row['administration_name'] : null,
            'administration_contact' => $row['administration_contact'] !== null ? (string) $row['administration_contact'] : null,
            'notes' => $row['notes'] !== null ? (string) $row['notes'] : null,
            'is_active' => (bool) $row['is_active'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
        ];
    }
}
