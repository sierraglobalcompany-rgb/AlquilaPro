<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isApartment = (string) $lease['unit_type'] === 'apartment';
$isParking = (string) $lease['unit_type'] === 'parking';
$chargeStateLabels = [
    'pending' => 'Pendiente',
    'overdue' => 'Vencido',
    'voided' => 'Anulado',
];
$chargeTypeLabels = [
    'rent' => 'Canon mensual',
    'administration' => 'Administración',
    'service' => 'Servicio',
    'fine' => 'Multa',
    'repair' => 'Reparación',
    'other' => 'Otro',
];
$paymentMethodTypeLabels = [
    'bank_account' => 'Cuenta bancaria',
    'breb_key' => 'Llave Bre-B',
    'breb_qr' => 'Código QR Bre-B',
    'wallet' => 'Billetera digital',
    'manual' => 'Instrucciones manuales',
];
$brebKeyTypeLabels = [
    'identity_document' => 'Documento de identidad',
    'mobile' => 'Número celular',
    'email' => 'Correo electrónico',
    'alphanumeric' => 'Llave alfanumérica',
    'merchant_code' => 'Código de comercio',
];
?>
<section class="surface">
    <p class="eyebrow">Portal del inquilino</p>
    <h1>Arrendamiento #<?= $escape($lease['id']) ?></h1>
    <p class="hint">Estado: <?= $lease['is_active'] ? 'Activo' : 'Finalizado' ?></p>
    <a class="button button--quiet" href="<?= $escape($basePath) ?>/tenant">Volver</a>
    <a class="button button--quiet" href="<?= $escape($basePath) ?>/tenant/leases/<?= $escape($lease['id']) ?>/contract">Descargar contrato</a>
</section>

<section class="surface">
    <h2>Cartera</h2>
    <div class="users-grid">
        <article class="user-card">
            <div>Saldo pendiente: $<?= $escape($financeSummary['outstanding']) ?></div>
            <div>Saldo vencido: $<?= $escape($financeSummary['overdue']) ?></div>
            <div>Vencimiento pendiente más antiguo: <?= $escape($financeSummary['oldest_due_date'] ?? '—') ?></div>
        </article>
    </div>
    <?php if ($charges === []): ?>
        <p class="empty-state">No hay cargos registrados para este arrendamiento.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($charges as $charge): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($chargeTypeLabels[(string) $charge['type']] ?? 'Cargo') ?></strong>
                        <span class="status-pill" data-charge-state="<?= $escape($charge['state']) ?>">
                            <?= $escape($chargeStateLabels[$charge['state']] ?? 'Cargo') ?>
                        </span>
                    </div>
                    <?php if ($charge['description'] !== null): ?>
                        <div><?= $escape($charge['description']) ?></div>
                    <?php endif; ?>
                    <?php if ($charge['period_month'] !== null): ?>
                        <div>Periodo: <?= $escape(substr($charge['period_month'], 0, 7)) ?></div>
                    <?php endif; ?>
                    <div>Vencimiento: <?= $escape($charge['due_date']) ?></div>
                    <div>Valor: $<?= $escape($charge['amount']) ?></div>
                    <?php if ($charge['days_overdue'] > 0): ?>
                        <div>Días vencidos: <?= $escape($charge['days_overdue']) ?></div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="surface">
    <h2>Cómo pagar</h2>
    <?php if ($paymentMethods === []): ?>
        <p class="empty-state">No hay medios de pago disponibles para esta propiedad.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($paymentMethods as $method): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($method['label']) ?></strong>
                        <span class="status-pill"><?= $escape($paymentMethodTypeLabels[$method['type']] ?? 'Medio de pago') ?></span>
                    </div>
                    <?php if (is_string($method['recipient_name']) && $method['recipient_name'] !== ''): ?>
                        <div>Titular: <?= $escape($method['recipient_name']) ?></div>
                    <?php endif; ?>
                    <?php if (is_string($method['institution_name']) && $method['institution_name'] !== ''): ?>
                        <div>Entidad: <?= $escape($method['institution_name']) ?></div>
                    <?php endif; ?>
                    <?php if (is_string($method['account_type']) && $method['account_type'] !== ''): ?>
                        <div>Tipo de cuenta: <?= $escape($method['account_type']) ?></div>
                    <?php endif; ?>
                    <?php if (is_string($method['account_number']) && $method['account_number'] !== ''): ?>
                        <div>Número de cuenta: <?= $escape($method['account_number']) ?></div>
                    <?php endif; ?>
                    <?php if (is_string($method['breb_key_type']) && $method['breb_key_type'] !== ''): ?>
                        <div>Tipo de llave: <?= $escape($brebKeyTypeLabels[$method['breb_key_type']] ?? '') ?></div>
                    <?php endif; ?>
                    <?php if (is_string($method['breb_key_value']) && $method['breb_key_value'] !== ''): ?>
                        <div>Llave: <?= $escape($method['breb_key_value']) ?></div>
                    <?php endif; ?>
                    <?php if (is_string($method['wallet_identifier']) && $method['wallet_identifier'] !== ''): ?>
                        <div>Identificador: <?= $escape($method['wallet_identifier']) ?></div>
                    <?php endif; ?>
                    <?php if (is_string($method['instructions']) && $method['instructions'] !== ''): ?>
                        <div><?= nl2br($escape($method['instructions'])) ?></div>
                    <?php endif; ?>
                    <?php if ($method['type'] === 'breb_qr' && $method['qr_url'] !== null): ?>
                        <img src="<?= $escape($method['qr_url']) ?>" alt="Código QR de <?= $escape($method['label']) ?>" loading="lazy">
                    <?php elseif ($method['type'] === 'breb_qr'): ?>
                        <p class="hint">El código QR no está disponible.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="surface">
    <h2>Inmueble</h2>
    <div class="users-grid">
        <article class="user-card">
            <div class="user-card__head">
                <strong><?= $escape($lease['property_name']) ?></strong>
                <span class="status-pill"><?= $lease['is_active'] ? 'Activo' : 'Finalizado' ?></span>
            </div>
            <div>Unidad: <?= $escape($lease['unit_code']) ?></div>
            <?php if ($lease['unit_location_label'] !== null): ?>
                <div>Ubicación: <?= $escape($lease['unit_location_label']) ?></div>
            <?php endif; ?>
            <div>Dirección: <?= $escape($lease['property_address']) ?></div>
            <div>Ciudad: <?= $escape($lease['property_city']) ?></div>
            <?php if ($lease['property_sector'] !== null): ?>
                <div>Sector: <?= $escape($lease['property_sector']) ?></div>
            <?php endif; ?>
            <?php if ($isApartment): ?>
                <?php if ($lease['unit_number'] !== null): ?><div>Apartamento: <?= $escape($lease['unit_number']) ?></div><?php endif; ?>
                <?php if ($lease['tower'] !== null): ?><div>Torre: <?= $escape($lease['tower']) ?></div><?php endif; ?>
                <?php if ($lease['floor'] !== null): ?><div>Piso: <?= $escape($lease['floor']) ?></div><?php endif; ?>
            <?php elseif ($isParking && $lease['parking_level'] !== null): ?>
                <div>Nivel de parqueadero: <?= $escape($lease['parking_level']) ?></div>
            <?php endif; ?>
        </article>
    </div>
</section>

<section class="surface">
    <h2>Contrato</h2>
    <div class="users-grid">
        <article class="user-card">
            <div>Canon mensual: <?= $escape($lease['monthly_rent']) ?></div>
            <div>Administración: <?= $lease['administration_fee'] === null ? 'No aplica' : $escape($lease['administration_fee']) ?></div>
            <div>Inicio: <?= $escape($lease['start_date']) ?></div>
            <div>Fin: <?= $escape($lease['end_date']) ?></div>
            <div>Duración: <?= $escape($lease['duration_text']) ?></div>
            <div>Activado: <?= $escape($lease['activated_at']) ?></div>
            <?php if ($lease['ended_at'] !== null): ?>
                <div>Finalizado: <?= $escape($lease['ended_at']) ?></div>
            <?php endif; ?>
        </article>
    </div>
</section>
