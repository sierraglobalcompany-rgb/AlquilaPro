<?php

declare(strict_types=1);

namespace AlquilaPro\Memberships;

use AlquilaPro\Finance\FinanceService;
use PDO;
use RuntimeException;
use Throwable;

final readonly class MembershipService
{
    private const MAX_DURATION_DAYS = 4_294_967_295;

    public function __construct(
        private PDO $pdo,
        private FinanceService $finance,
    ) {
    }

    /** @return array<string,mixed> */
    public function createService(
        string $name,
        string $description,
        string $price,
        int $durationDays,
        int $actorUserId,
        int $now,
    ): array {
        [$name, $description, $price] = $this->validateService($name, $description, $price, $durationDays, $actorUserId, $now);
        $timestamp = gmdate('Y-m-d H:i:s', $now);
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO digital_services '
                . '(name, description, current_price, duration_days, active, created_by_user_id, created_at, updated_at) '
                . 'VALUES (:name, :description, :price, :duration_days, 1, :actor_id, :created_at, :updated_at)',
            );
            $statement->execute([
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'duration_days' => $durationDays,
                'actor_id' => $actorUserId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $service = $this->findService((int) $this->pdo->lastInsertId());
            if ($service === null) {
                throw new RuntimeException('No fue posible crear el servicio.');
            }
            return $service;
        } catch (Throwable) {
            throw new RuntimeException('No fue posible crear el servicio.');
        }
    }

    /** @return array<string,mixed> */
    public function updateService(
        int $serviceId,
        string $name,
        string $description,
        string $price,
        int $durationDays,
        bool $active,
        int $actorUserId,
        int $now,
    ): array {
        [$name, $description, $price] = $this->validateService($name, $description, $price, $durationDays, $actorUserId, $now);
        if ($serviceId <= 0) {
            throw new RuntimeException('No fue posible actualizar el servicio.');
        }
        try {
            $statement = $this->pdo->prepare(
                'UPDATE digital_services SET name = :name, description = :description, current_price = :price, '
                . 'duration_days = :duration_days, active = :active, updated_at = :updated_at WHERE id = :id',
            );
            $statement->execute([
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'duration_days' => $durationDays,
                'active' => $active ? 1 : 0,
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
                'id' => $serviceId,
            ]);
            $service = $this->findService($serviceId);
            if ($service === null) {
                throw new RuntimeException('No fue posible actualizar el servicio.');
            }
            return $service;
        } catch (Throwable) {
            throw new RuntimeException('No fue posible actualizar el servicio.');
        }
    }

    /** @return array<string,mixed>|null */
    public function findService(int $serviceId): ?array
    {
        if ($serviceId <= 0) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT * FROM digital_services WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $serviceId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeService($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public function listServices(bool $activeOnly = false): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM digital_services ' . ($activeOnly ? 'WHERE active = 1 ' : '') . 'ORDER BY name ASC, id ASC',
        );
        $statement->execute();
        return array_map(
            fn (array $row): array => $this->normalizeService($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @return array{string,string,string} */
    private function validateService(
        string $name,
        string $description,
        string $price,
        int $durationDays,
        int $actorUserId,
        int $now,
    ): array {
        $name = trim($name);
        $description = trim($description);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120
            || preg_match('/[\x00-\x1F\x7F]/', $name) === 1
            || $description === '' || mb_strlen($description, 'UTF-8') > 1000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $description) === 1
            || $durationDays < 1 || $durationDays > self::MAX_DURATION_DAYS
            || $actorUserId <= 0 || $now <= 0
        ) {
            throw new RuntimeException('Los datos del servicio no son válidos.');
        }
        return [$name, $description, $this->normalizePrice($price)];
    }

    private function normalizePrice(string $price): string
    {
        $price = trim($price);
        if (!preg_match('/\A\d+(?:\.\d{1,2})?\z/', $price)) {
            throw new RuntimeException('El precio del servicio no es válido.');
        }
        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 10) {
            throw new RuntimeException('El precio del servicio no es válido.');
        }
        return $whole . '.' . str_pad($fraction, 2, '0');
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeService(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'description' => (string) $row['description'],
            'current_price' => (string) $row['current_price'],
            'duration_days' => (int) $row['duration_days'],
            'active' => (bool) $row['active'],
            'created_by_user_id' => $row['created_by_user_id'] === null ? null : (int) $row['created_by_user_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
        ];
    }
}
