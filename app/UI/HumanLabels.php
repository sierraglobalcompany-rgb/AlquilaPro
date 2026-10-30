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

    public static function candidateStatusDescription(string $status): string
    {
        return match ($status) {
            'new' => 'La solicitud fue iniciada y aún puede completarse.',
            'incomplete' => 'La solicitud tiene información pendiente por completar.',
            'verification' => 'La solicitud fue enviada y está en verificación.',
            'pending_screening' => 'La solicitud está pendiente del estudio de aseguradora.',
            'withdrawn' => 'La solicitud fue retirada del proceso.',
            default => 'Consulta el estado actual de la solicitud.',
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

    public static function contractStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'Borrador',
            'generated' => 'Generado',
            'finalized' => 'Finalizado',
            default => $status,
        };
    }
}
