<?php

declare(strict_types=1);

namespace AlquilaPro\Properties;

use PDO;
use PDOException;
use RuntimeException;

final class PropertyService
{
    private const UNIT_TYPES = ['apartment', 'parking'];
    private const UNIT_STATUSES = ['available', 'in_process', 'occupied', 'inactive', 'maintenance'];

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
        $rows = $this->pdo->query(
            'SELECT p.*, '
            . 'COALESCE(c.available_count, 0) AS available_count, '
            . 'COALESCE(c.in_process_count, 0) AS in_process_count, '
            . 'COALESCE(c.occupied_count, 0) AS occupied_count, '
            . 'COALESCE(c.inactive_count, 0) AS inactive_count, '
            . 'COALESCE(c.maintenance_count, 0) AS maintenance_count '
            . 'FROM properties p LEFT JOIN ('
            . 'SELECT property_id, '
            . "SUM(status = 'available') AS available_count, "
            . "SUM(status = 'in_process') AS in_process_count, "
            . "SUM(status = 'occupied') AS occupied_count, "
            . "SUM(status = 'inactive') AS inactive_count, "
            . "SUM(status = 'maintenance') AS maintenance_count "
            . 'FROM units GROUP BY property_id'
            . ') c ON c.property_id = p.id ORDER BY p.id ASC',
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): array {
            $property = $this->normalizeProperty($row);
            $property['unit_counts'] = [
                'available' => (int) $row['available_count'],
                'in_process' => (int) $row['in_process_count'],
                'occupied' => (int) $row['occupied_count'],
                'inactive' => (int) $row['inactive_count'],
                'maintenance' => (int) $row['maintenance_count'],
            ];

            return $property;
        }, $rows);
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

    public function createUnit(int $propertyId, array $input): array
    {
        $property = $this->findProperty($propertyId);
        if ($property === null) {
            throw new RuntimeException('La propiedad no existe.');
        }
        if (!$property['is_active']) {
            throw new RuntimeException('No se pueden crear unidades en una propiedad inactiva.');
        }

        if (trim((string) ($input['public_key'] ?? '')) !== '') {
            throw new RuntimeException('El identificador público es generado por el sistema.');
        }

        $data = $this->validateUnit($propertyId, $input, null);
        $data['property_id'] = $propertyId;
        $data['public_key'] = bin2hex(random_bytes(12));

        $statement = $this->pdo->prepare(
            'INSERT INTO units ('
            . 'property_id, public_key, type, code, location_label, status, is_public, monthly_price, '
            . 'description, requirements, private_notes, unit_number, tower, floor, area_m2, bedrooms, bathrooms, '
            . 'administration_fee, registry_number, parking_level, dimensions, vehicle_type, linked_apartment_id'
            . ') VALUES ('
            . ':property_id, :public_key, :type, :code, :location_label, :status, 0, :monthly_price, '
            . ':description, :requirements, :private_notes, :unit_number, :tower, :floor, :area_m2, :bedrooms, :bathrooms, '
            . ':administration_fee, :registry_number, :parking_level, :dimensions, :vehicle_type, :linked_apartment_id'
            . ')',
        );

        try {
            $statement->execute($data);
        } catch (PDOException $exception) {
            throw new RuntimeException('No fue posible crear la unidad. Verifica que el código no esté repetido.');
        }

        $unit = $this->findUnit((int) $this->pdo->lastInsertId());
        if ($unit === null) {
            throw new RuntimeException('No fue posible crear la unidad.');
        }

        return $unit;
    }

    public function updateUnit(int $unitId, array $input): array
    {
        $existing = $this->findUnit($unitId);
        if ($existing === null) {
            throw new RuntimeException('La unidad no existe.');
        }

        if (trim((string) ($input['public_key'] ?? '')) !== '') {
            throw new RuntimeException('El identificador público es generado por el sistema.');
        }

        $requestedType = trim((string) ($input['type'] ?? ''));
        if (in_array($requestedType, self::UNIT_TYPES, true) && $requestedType !== $existing['type']) {
            throw new RuntimeException('El tipo de la unidad no se puede cambiar.');
        }

        $data = $this->validateUnit($existing['property_id'], $input, $unitId);
        $data['is_public'] = $this->normalizePublicFlag($input, $existing['is_public']);
        if (!$existing['is_public'] && $data['is_public']) {
            $this->assertPublicationComplete($unitId, $data);
        }
        $data['id'] = $unitId;

        $statement = $this->pdo->prepare(
            'UPDATE units SET '
            . 'type = :type, code = :code, location_label = :location_label, status = :status, '
            . 'is_public = :is_public, monthly_price = :monthly_price, description = :description, requirements = :requirements, '
            . 'private_notes = :private_notes, unit_number = :unit_number, tower = :tower, floor = :floor, '
            . 'area_m2 = :area_m2, bedrooms = :bedrooms, bathrooms = :bathrooms, '
            . 'administration_fee = :administration_fee, registry_number = :registry_number, '
            . 'parking_level = :parking_level, dimensions = :dimensions, vehicle_type = :vehicle_type, '
            . 'linked_apartment_id = :linked_apartment_id, updated_at = CURRENT_TIMESTAMP '
            . 'WHERE id = :id',
        );

        try {
            $statement->execute($data);
        } catch (PDOException $exception) {
            throw new RuntimeException('No fue posible actualizar la unidad. Verifica que el código no esté repetido.');
        }

        $unit = $this->findUnit($unitId);
        if ($unit === null) {
            throw new RuntimeException('No fue posible actualizar la unidad.');
        }

        return $unit;
    }

    public function findUnit(int $unitId): ?array
    {
        if ($unitId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT * FROM units WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $unitId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeUnit($row) : null;
    }

    public function listUnitsForProperty(int $propertyId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM units WHERE property_id = :property_id ORDER BY id ASC');
        $statement->execute(['property_id' => $propertyId]);

        return array_map($this->normalizeUnit(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listPublicUnits(?string $type, ?string $city, ?string $sector): array
    {
        if ($type !== null && !in_array($type, self::UNIT_TYPES, true)) {
            throw new RuntimeException('El tipo de unidad no es válido.');
        }

        $where = ['p.is_active = 1', "u.status = 'available'", 'u.is_public = 1'];
        $params = [];

        if ($type !== null) {
            $where[] = 'u.type = :type';
            $params['type'] = $type;
        }

        $city = $this->normalizeFilter($city);
        if ($city !== null) {
            $where[] = 'p.city = :city';
            $params['city'] = $city;
        }

        $sector = $this->normalizeFilter($sector);
        if ($sector !== null) {
            $where[] = 'p.sector = :sector';
            $params['sector'] = $sector;
        }

        $statement = $this->pdo->prepare(
            'SELECT u.*, p.name AS property_name, p.city AS property_city, p.sector AS property_sector, '
            . "EXISTS (SELECT 1 FROM unit_media pm WHERE pm.unit_id = u.id AND pm.type = 'photo') AS has_photo "
            . 'FROM units u INNER JOIN properties p ON p.id = u.property_id '
            . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY u.id ASC',
        );
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->publicationFieldsComplete($row, (bool) $row['has_photo']),
        ));

        return array_map($this->normalizePublicUnit(...), $rows);
    }

    public function findPublicUnit(string $publicKey): ?array
    {
        $publicKey = trim($publicKey);
        if (!preg_match('/^[a-f0-9]{24}$/', $publicKey)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT u.*, p.name AS property_name, p.city AS property_city, p.sector AS property_sector, '
            . "EXISTS (SELECT 1 FROM unit_media pm WHERE pm.unit_id = u.id AND pm.type = 'photo') AS has_photo "
            . 'FROM units u INNER JOIN properties p ON p.id = u.property_id '
            . "WHERE u.public_key = :public_key AND p.is_active = 1 AND u.status = 'available' AND u.is_public = 1 "
            . 'LIMIT 1',
        );
        $statement->execute(['public_key' => $publicKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !$this->publicationFieldsComplete($row, (bool) $row['has_photo'])) {
            return null;
        }

        return $this->normalizePublicUnit($row);
    }

    public function isUnitPublicable(int $unitId): bool
    {
        $unit = $this->findUnit($unitId);
        if ($unit === null || !$unit['is_public'] || $unit['status'] !== 'available') {
            return false;
        }

        $property = $this->findProperty($unit['property_id']);

        return $property !== null
            && $property['is_active']
            && $this->publicationDataComplete($unitId, $unit);
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

    private function validateUnit(int $propertyId, array $input, ?int $unitId): array
    {
        $type = trim((string) ($input['type'] ?? ''));
        if (!in_array($type, self::UNIT_TYPES, true)) {
            throw new RuntimeException('El tipo de unidad no es válido.');
        }

        $status = trim((string) ($input['status'] ?? ''));
        if (!in_array($status, self::UNIT_STATUSES, true)) {
            throw new RuntimeException('El estado de la unidad no es válido.');
        }

        $data = [
            'type' => $type,
            'code' => $this->requiredText($input, 'code', 80, 'El código de la unidad es obligatorio.'),
            'location_label' => $this->optionalText($input, 'location_label', 190),
            'status' => $status,
            'monthly_price' => $this->optionalDecimal($input, 'monthly_price'),
            'description' => $this->optionalText($input, 'description'),
            'requirements' => $this->optionalText($input, 'requirements'),
            'private_notes' => $this->optionalText($input, 'private_notes'),
            'unit_number' => null,
            'tower' => null,
            'floor' => null,
            'area_m2' => null,
            'bedrooms' => null,
            'bathrooms' => null,
            'administration_fee' => null,
            'registry_number' => null,
            'parking_level' => null,
            'dimensions' => null,
            'vehicle_type' => null,
            'linked_apartment_id' => null,
        ];

        if ($type === 'apartment') {
            $data['unit_number'] = $this->optionalText($input, 'unit_number', 50);
            $data['tower'] = $this->optionalText($input, 'tower', 80);
            $data['floor'] = $this->optionalText($input, 'floor', 50);
            $data['area_m2'] = $this->optionalDecimal($input, 'area_m2');
            $data['bedrooms'] = $this->optionalInteger($input, 'bedrooms');
            $data['bathrooms'] = $this->optionalDecimal($input, 'bathrooms');
            $data['administration_fee'] = $this->optionalDecimal($input, 'administration_fee');
            $data['registry_number'] = $this->optionalText($input, 'registry_number', 120);
        } else {
            $data['parking_level'] = $this->optionalText($input, 'parking_level', 80);
            $data['dimensions'] = $this->optionalText($input, 'dimensions', 120);
            $data['vehicle_type'] = $this->optionalText($input, 'vehicle_type', 80);
            $data['linked_apartment_id'] = $this->validateLinkedApartment(
                $propertyId,
                $input['linked_apartment_id'] ?? null,
                $unitId,
            );
        }

        return $data;
    }

    private function normalizePublicFlag(array $input, bool $default): bool
    {
        if (!array_key_exists('is_public', $input)) {
            return $default;
        }

        $value = $input['is_public'];
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }

        throw new RuntimeException('El estado de publicación no es válido.');
    }

    private function assertPublicationComplete(int $unitId, array $data): void
    {
        if (!$this->publicationDataComplete($unitId, $data)) {
            throw new RuntimeException(
                'Para publicar la unidad debes definir precio, descripción, requisitos y al menos una foto.',
            );
        }
    }

    private function publicationDataComplete(int $unitId, array $data): bool
    {
        return $this->publicationFieldsComplete($data, $this->hasPhoto($unitId));
    }

    private function publicationFieldsComplete(array $data, bool $hasPhoto): bool
    {
        return $data['monthly_price'] !== null
            && (float) $data['monthly_price'] > 0
            && trim((string) ($data['description'] ?? '')) !== ''
            && trim((string) ($data['requirements'] ?? '')) !== ''
            && $hasPhoto;
    }

    private function hasPhoto(int $unitId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM unit_media WHERE unit_id = :unit_id AND type = 'photo' LIMIT 1",
        );
        $statement->execute(['unit_id' => $unitId]);

        return $statement->fetchColumn() !== false;
    }

    private function validateLinkedApartment(int $propertyId, mixed $value, ?int $unitId): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
            throw new RuntimeException('El apartamento relacionado no es válido.');
        }

        $linkedId = (int) $value;
        if ($unitId !== null && $linkedId === $unitId) {
            throw new RuntimeException('Una unidad no puede relacionarse consigo misma.');
        }

        $linked = $this->findUnit($linkedId);
        if ($linked === null || $linked['type'] !== 'apartment' || $linked['property_id'] !== $propertyId) {
            throw new RuntimeException('El parqueadero debe relacionarse con un apartamento de la misma propiedad.');
        }

        return $linkedId;
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
            throw new RuntimeException('Uno de los datos supera la longitud permitida.');
        }

        return $value;
    }

    private function optionalDecimal(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value) || (float) $value < 0) {
            throw new RuntimeException('Los valores numéricos de la unidad no pueden ser negativos.');
        }

        return (string) $value;
    }

    private function optionalInteger(array $input, string $key): ?int
    {
        $value = $input[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            throw new RuntimeException('Los valores enteros de la unidad no pueden ser negativos.');
        }

        return (int) $value;
    }

    private function normalizeFilter(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
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

    private function normalizeUnit(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'property_id' => (int) $row['property_id'],
            'public_key' => (string) $row['public_key'],
            'type' => (string) $row['type'],
            'code' => (string) $row['code'],
            'location_label' => $row['location_label'] !== null ? (string) $row['location_label'] : null,
            'status' => (string) $row['status'],
            'is_public' => (bool) $row['is_public'],
            'monthly_price' => $row['monthly_price'] !== null ? (string) $row['monthly_price'] : null,
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'requirements' => $row['requirements'] !== null ? (string) $row['requirements'] : null,
            'private_notes' => $row['private_notes'] !== null ? (string) $row['private_notes'] : null,
            'unit_number' => $row['unit_number'] !== null ? (string) $row['unit_number'] : null,
            'tower' => $row['tower'] !== null ? (string) $row['tower'] : null,
            'floor' => $row['floor'] !== null ? (string) $row['floor'] : null,
            'area_m2' => $row['area_m2'] !== null ? (string) $row['area_m2'] : null,
            'bedrooms' => $row['bedrooms'] !== null ? (int) $row['bedrooms'] : null,
            'bathrooms' => $row['bathrooms'] !== null ? (string) $row['bathrooms'] : null,
            'administration_fee' => $row['administration_fee'] !== null ? (string) $row['administration_fee'] : null,
            'registry_number' => $row['registry_number'] !== null ? (string) $row['registry_number'] : null,
            'parking_level' => $row['parking_level'] !== null ? (string) $row['parking_level'] : null,
            'dimensions' => $row['dimensions'] !== null ? (string) $row['dimensions'] : null,
            'vehicle_type' => $row['vehicle_type'] !== null ? (string) $row['vehicle_type'] : null,
            'linked_apartment_id' => $row['linked_apartment_id'] !== null ? (int) $row['linked_apartment_id'] : null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
        ];
    }

    private function normalizePublicUnit(array $row): array
    {
        return [
            'public_key' => (string) $row['public_key'],
            'type' => (string) $row['type'],
            'location_label' => $row['location_label'] !== null ? (string) $row['location_label'] : null,
            'monthly_price' => $row['monthly_price'] !== null ? (string) $row['monthly_price'] : null,
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'requirements' => $row['requirements'] !== null ? (string) $row['requirements'] : null,
            'tower' => $row['tower'] !== null ? (string) $row['tower'] : null,
            'floor' => $row['floor'] !== null ? (string) $row['floor'] : null,
            'area_m2' => $row['area_m2'] !== null ? (string) $row['area_m2'] : null,
            'bedrooms' => $row['bedrooms'] !== null ? (int) $row['bedrooms'] : null,
            'bathrooms' => $row['bathrooms'] !== null ? (string) $row['bathrooms'] : null,
            'administration_fee' => $row['administration_fee'] !== null ? (string) $row['administration_fee'] : null,
            'parking_level' => $row['parking_level'] !== null ? (string) $row['parking_level'] : null,
            'dimensions' => $row['dimensions'] !== null ? (string) $row['dimensions'] : null,
            'vehicle_type' => $row['vehicle_type'] !== null ? (string) $row['vehicle_type'] : null,
            'property_name' => (string) $row['property_name'],
            'city' => (string) $row['property_city'],
            'sector' => $row['property_sector'] !== null ? (string) $row['property_sector'] : null,
        ];
    }
}
