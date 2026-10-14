<?php

declare(strict_types=1);

namespace AlquilaPro\Auth;

final class Permissions
{
    private const CATALOG = [
        'users.view',
        'users.manage',
        'users.permissions',
        'properties.view',
        'properties.manage',
        'candidates.view',
        'candidates.manage',
        'contracts.view',
        'contracts.manage',
        'finance.view',
        'finance.manage',
        'insurance.view',
        'insurance.manage',
        'maintenance.view',
        'maintenance.manage',
    ];

    public static function allows(array $user, string $permission): bool
    {
        if (($user['role'] ?? null) === 'owner') {
            return true;
        }

        if (($user['role'] ?? null) === 'tenant') {
            return false;
        }

        $permissions = is_array($user['permissions'] ?? null)
            ? self::sanitize($user['permissions'])
            : [];

        return in_array($permission, $permissions, true);
    }

    public static function sanitize(array $permissions): array
    {
        $sanitized = [];

        foreach ($permissions as $permission) {
            if (!is_string($permission) || !in_array($permission, self::CATALOG, true)) {
                continue;
            }

            if (!in_array($permission, $sanitized, true)) {
                $sanitized[] = $permission;
            }
        }

        return $sanitized;
    }
}
