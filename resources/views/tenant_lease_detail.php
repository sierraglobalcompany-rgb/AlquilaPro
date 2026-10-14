<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$obligations = $obligations ?? [];
$insurancePolicies = $insurancePolicies ?? [];
$maintenanceRequests = $maintenanceRequests ?? [];
$isApartment = (string) $lease['unit_type'] === 'apartment';
$isParking = (string) $lease['unit_type'] === 'parking';
$chargeStateLabels = [
    'pending' => 'Pendiente',
    'overdue' => 'Vencido',
    'voided' => 'Anulado',
    'reported' => 'Pago reportado',
    'proof_received' => 'Comprobante recibido',
    'partial' => 'Pago parcial',
    'paid' => 'Pagado',
];
$paymentStateLabels = [
    'pending_reconciliation' => 'Pendiente de conciliación',
    'reconciled' => 'Conciliado',
    'rejected' => 'Rechazado',
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
$obligationTypeLabels = [
    'utility' => 'Servicio',
    'administration_ph' => 'Administración PH',
    'fine_ph' => 'Multa PH',
];
$externalStateLabels = [
    'open' => 'Abierta',
    'overdue_external' => 'Vencida externamente',
    'settled_external' => 'Atendida externamente',
    'voided' => 'Anulada',
];
$visitStatusLabels = [
    'proposed' => 'Horarios disponibles',
    'scheduled' => 'Programada',
    'completed' => 'Completada',
    'cancelled' => 'Cancelada',
];
?>
<section class="surface">
    <p class="eyebrow">Portal del inquilino</p>
    <h1>Arrendamiento #<?= $escape($lease['id']) ?></h1>
    <p class="hint">Estado: <?= $lease['is_active'] ? 'Activo' : 'Finalizado' ?></p>
    <a class="button button--quiet" href="<?= $escape($basePath) ?>/tenant">Volver</a>
    <a class="button button--quiet" href="<?= $escape($basePath) ?>/tenant/leases/<?= $escape($lease['id']) ?>/contract">Descargar contrato</a>
</section>

<?php if ($insurancePolicies !== []): ?>
    <section class="surface">
        <h2>Seguro del arrendamiento</h2>
        <div class="users-grid">
            <?php foreach ($insurancePolicies as $policy): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($policy['provider_name']) ?> · <?= $escape($policy['product_name']) ?></strong>
                        <span class="status-pill"><?= $escape($policy['status']) ?></span>
                    </div>
                    <div>Póliza: <?= $escape($policy['policy_number']) ?></div>
                    <div>Vigencia: <?= $escape($policy['coverage_start']) ?> a <?= $escape($policy['coverage_end']) ?></div>
                    <?php if ($policy['coverage'] !== []): ?>
                        <div class="users-grid">
                            <?php foreach ($policy['coverage'] as $coverage): ?>
                                <div>
                                    <strong><?= $escape($coverage['name']) ?></strong>
                                    <div>Límite: <?= $escape($coverage['limit_amount'] ?? '—') ?></div>
                                    <div>Deducible: <?= $escape($coverage['deductible_amount'] ?? '—') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="surface">
    <h2>Mantenimiento</h2>
    <p class="hint">Reporta novedades relacionadas con este arrendamiento.</p>
    <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/tenant/leases/<?= $escape($lease['id']) ?>/maintenance">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <label>Categoría<input name="category" maxlength="80" required></label>
        <label>Prioridad
            <select name="priority">
                <option value="low">Baja</option>
                <option value="medium" selected>Media</option>
                <option value="high">Alta</option>
                <option value="urgent">Urgente</option>
            </select>
        </label>
        <label>Descripción<textarea name="description" maxlength="5000" required></textarea></label>
        <button class="button" type="submit">Crear solicitud</button>
    </form>
    <?php if ($maintenanceRequests === []): ?>
        <p class="empty-state">Aún no hay solicitudes de mantenimiento.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($maintenanceRequests as $maintenanceRequest): ?>
                <?php $terminal = in_array($maintenanceRequest['status'], ['resolved', 'cancelled'], true); ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($maintenanceRequest['category']) ?> · #<?= $escape($maintenanceRequest['id']) ?></strong>
                        <span class="status-pill"><?= $escape([
                            'open' => 'Abierta', 'in_progress' => 'En proceso',
                            'resolved' => 'Resuelta', 'cancelled' => 'Cancelada',
                        ][$maintenanceRequest['status']] ?? $maintenanceRequest['status']) ?></span>
                    </div>
                    <div>Prioridad: <?= $escape([
                        'low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'urgent' => 'Urgente',
                    ][$maintenanceRequest['priority']] ?? $maintenanceRequest['priority']) ?></div>
                    <p><?= nl2br($escape($maintenanceRequest['description'])) ?></p>
                    <?php if ($maintenanceRequest['resolution_notes'] !== null): ?>
                        <div>Resolución: <?= $escape($maintenanceRequest['resolution_notes']) ?></div>
                    <?php endif; ?>
                    <?php foreach ($maintenanceRequest['visits'] as $visit): ?>
                        <div class="visit-summary">
                            <strong><?= $escape($visitStatusLabels[$visit['status']] ?? $visit['status']) ?></strong>
                            <?php if ($visit['selected_start_at'] !== null): ?>
                                <div><?= $escape($visit['selected_start_at']) ?> · <?= $escape($visit['duration_minutes']) ?> min</div>
                            <?php endif; ?>
                            <?php if (!$terminal && $visit['status'] === 'proposed'): ?>
                                <?php foreach ($visit['proposed_slots'] as $slot): ?>
                                    <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/tenant/leases/<?= $escape($lease['id']) ?>/maintenance/<?= $escape($maintenanceRequest['id']) ?>/visits/<?= $escape($visit['id']) ?>/select">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="start_at" value="<?= $escape($slot['start_at']) ?>">
                                        <button class="button button--quiet" type="submit">Seleccionar horario · <?= $escape($slot['start_at']) ?> · <?= $escape($slot['duration_minutes']) ?> min</button>
                                    </form>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($maintenanceRequest['photos'] as $photo): ?>
                        <?php if (is_array($photo) && preg_match('/\A[a-f0-9]{32}\z/', (string) ($photo['photo_id'] ?? '')) === 1): ?>
                            <p><a href="<?= $escape($maintenanceRequest['photo_base_url']) ?><?= $escape($photo['photo_id']) ?>">Ver foto <?= $escape($photo['created_at'] ?? '') ?></a></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!$terminal): ?>
                        <form class="stack-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/tenant/leases/<?= $escape($lease['id']) ?>/maintenance/<?= $escape($maintenanceRequest['id']) ?>/photos">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <label>Agregar foto<input type="file" name="photo" accept="image/png,image/jpeg,image/webp" required></label>
                            <button class="button button--quiet" type="submit">Guardar foto</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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
                    <div>Valor del cargo: $<?= $escape($charge['amount']) ?></div>
                    <div>Saldo pendiente: $<?= $escape($charge['outstanding']) ?></div>
                    <?php if ($charge['days_overdue'] > 0): ?>
                        <div>Días vencidos: <?= $escape($charge['days_overdue']) ?></div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="surface">
    <h2>Mis obligaciones externas</h2>
    <p class="hint">Valores y vencimientos informados por el proveedor o la propiedad horizontal.</p>
    <?php if ($obligations === []): ?>
        <p class="empty-state">No hay obligaciones externas registradas para este arrendamiento.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($obligations as $obligation): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($obligationTypeLabels[$obligation['type']] ?? 'Obligación') ?><?= $obligation['service_name'] === null ? '' : ' · ' . $escape($obligation['service_name']) ?></strong>
                        <span class="status-pill" data-obligation-state="<?= $escape($obligation['external_state']) ?>">
                            <?= $escape($externalStateLabels[$obligation['external_state']] ?? 'Obligación') ?>
                        </span>
                    </div>
                    <div><?= $escape($obligation['description']) ?></div>
                    <?php if ($obligation['period_month'] !== null): ?>
                        <div>Periodo externo: <?= $escape(substr((string) $obligation['period_month'], 0, 7)) ?></div>
                    <?php endif; ?>
                    <?php if ($obligation['issued_on'] !== null): ?>
                        <div>Fecha de emisión: <?= $escape($obligation['issued_on']) ?></div>
                    <?php endif; ?>
                    <?php if ($obligation['due_on'] !== null): ?>
                        <div>Vencimiento externo: <?= $escape($obligation['due_on']) ?></div>
                    <?php endif; ?>
                    <div>Valor externo: $<?= $escape($obligation['amount']) ?></div>
                    <?php if ($obligation['is_charged']): ?>
                        <div>
                            Cargo financiero vinculado: <?= $escape($chargeTypeLabels[$obligation['linked_charge_type'] ?? ''] ?? 'Cargo') ?>
                            · <?= $escape($chargeStateLabels[$obligation['linked_charge_state'] ?? ''] ?? 'Estado no disponible') ?>
                            <?php if ($obligation['linked_charge_due_date'] !== null): ?>
                                · vence <?= $escape($obligation['linked_charge_due_date']) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($obligation['source_support_url'] !== null): ?>
                        <a class="button button--quiet" href="<?= $escape($obligation['source_support_url']) ?>">Ver soporte de origen</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="surface">
    <h2>Reportar pago</h2>
    <?php
    $reportableCharges = array_values(array_filter(
        $charges,
        static fn (array $charge): bool => $charge['state'] !== 'voided' && $charge['outstanding'] !== '0.00',
    ));
    ?>
    <?php if ($reportableCharges === []): ?>
        <p class="empty-state">No hay saldos pendientes para reportar.</p>
    <?php elseif ($paymentMethods === []): ?>
        <p class="empty-state">No hay medios de pago disponibles para esta propiedad.</p>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/tenant/leases/<?= $escape($lease['id']) ?>/payments">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <label>Medio de pago
                <select name="payment_method_id" required>
                    <option value="">Selecciona un medio</option>
                    <?php foreach ($paymentMethods as $method): ?>
                        <option value="<?= $escape($method['id']) ?>"><?= $escape($method['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Monto pagado
                <input type="number" name="amount" min="0.01" step="0.01" required>
            </label>
            <label>Fecha del pago
                <input type="date" name="paid_on" max="<?= $escape($today) ?>" required>
            </label>
            <label>Hora aproximada (opcional)
                <input type="time" name="paid_time">
            </label>
            <h3>Asignar a cargos pendientes</h3>
            <div class="users-grid">
                <?php foreach ($reportableCharges as $charge): ?>
                    <label class="user-card">
                        Cargo de <?= $escape($chargeTypeLabels[(string) $charge['type']] ?? 'Cargo') ?>
                        · vence <?= $escape($charge['due_date']) ?>
                        · saldo $<?= $escape($charge['outstanding']) ?>
                        <input type="number" name="allocations[<?= $escape($charge['id']) ?>]"
                               min="0.01" max="<?= $escape($charge['outstanding']) ?>" step="0.01">
                    </label>
                <?php endforeach; ?>
            </div>
            <label>Comprobante (PNG, JPEG, WebP o PDF; máximo 5 MiB)
                <input type="file" name="receipt" accept="image/png,image/jpeg,image/webp,application/pdf">
            </label>
            <button class="button" type="submit">Enviar reporte</button>
        </form>
    <?php endif; ?>
</section>

<section class="surface">
    <h2>Mis reportes de pago</h2>
    <?php if ($paymentHistory === []): ?>
        <p class="empty-state">Aún no has reportado pagos.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($paymentHistory as $payment): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong>$<?= $escape($payment['amount']) ?></strong>
                        <span class="status-pill" data-payment-state="<?= $escape($payment['state']) ?>">
                            <?= $escape($paymentStateLabels[$payment['state']] ?? 'Pago') ?>
                        </span>
                    </div>
                    <div>Fecha del pago: <?= $escape($payment['paid_on']) ?><?= $payment['paid_time'] === null ? '' : ' · ' . $escape($payment['paid_time']) ?></div>
                    <div>Medio: <?= $escape($payment['payment_method_snapshot']['label'] ?? 'Medio de pago') ?></div>
                    <?php if ($payment['state'] === 'rejected' && $payment['rejection_reason'] !== null): ?>
                        <div>Motivo: <?= $escape($payment['rejection_reason']) ?></div>
                    <?php endif; ?>
                    <?php if ($payment['has_receipt']): ?>
                        <a class="button button--quiet" href="<?= $escape($basePath) ?>/tenant/leases/<?= $escape($lease['id']) ?>/payments/<?= $escape($payment['id']) ?>/receipt">Ver comprobante</a>
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
