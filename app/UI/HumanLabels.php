<?php

declare(strict_types=1);

namespace AlquilaPro\UI;

final class HumanLabels
{
    public static function role(string $role): string
    {
        return match ($role) {
            'owner' => 'Propietario',
            'admin' => 'Administrador',
            'gestor' => 'Gestor',
            'tenant' => 'Inquilino',
            default => $role,
        };
    }

    public static function candidateStatus(string $status): string
    {
        return match ($status) {
            'new' => 'Nueva',
            'incomplete' => 'Incompleta',
            'verification' => 'En verificación',
            'pending_screening' => 'Pendiente de estudio',
            'withdrawn' => 'Retirada',
            default => $status,
        };
    }

    public static function screeningStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'in_progress' => 'En estudio',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            default => $status,
        };
    }
}
