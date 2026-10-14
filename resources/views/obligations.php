<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$obligations = $obligations ?? [];
$leaseOptions = $leaseOptions ?? [];
$chargeOptionsByLease = $chargeOptionsByLease ?? [];
$canManage = $canManage ?? false;
$typeLabels = [
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
$chargeStateLabels = [
    'pending' => 'Pendiente',
    'overdue' => 'Vencido',
    'voided' => 'Anulado',
    'reported' => 'Pago reportado',
    'proof_received' => 'Comprobante recibido',
    'partial' => 'Pago parcial',
    'paid' => 'Pagado',
];
$formTypes = static function (string $selected) use ($escape): string {
    $html = '';
    foreach (['utility' => 'Servicio', 'administration_ph' => 'Administración PH', 'fine_ph' => 'Multa PH'] as $type => $label) {
        $html .= '<option value="' . $escape($type) . '"' . ($selected === $type ? ' selected' : '') . '>' . $escape($label) . '</option>';
    }

    return $html;
};
?>
<section class="surface">
    <p class="eyebrow">Finanzas</p>
    <h1>Servicios y obligaciones</h1>
    <p class="hint">Registro externo de servicios, administración PH y multas PH, vinculado al motor financiero existente.</p>
    <p><a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/finance">Volver a cartera</a></p>
</section>

<?php if ($canManage): ?>
    <section class="surface">
        <h2>Registrar obligación</h2>
        <?php if ($leaseOptions === []): ?>
            <p class="empty-state">No hay arrendamientos disponibles.</p>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/finance/obligations" data-obligation-create-form>
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Arrendamiento
                    <select name="lease_id" required>
                        <?php foreach ($leaseOptions as $leaseOption): ?>
                            <option value="<?= $escape($leaseOption['lease_id']) ?>">
                                <?= $escape($leaseOption['property_name']) ?> · <?= $escape($leaseOption['unit_code']) ?> · <?= $escape($leaseOption['tenant_name']) ?> · <?= $leaseOption['is_active'] ? 'Activo' : 'Finalizado' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Tipo
                    <select name="type" required><?= $formTypes('utility') ?></select>
                </label>
                <label>Servicio (solo para tipo servicio)
                    <input type="text" name="service_name" maxlength="120">
                </label>
                <label>Descripción
                    <textarea name="description" maxlength="500" required></textarea>
                </label>
                <label>Valor externo
                    <input type="text" name="amount" inputmode="decimal" maxlength="13" required>
                </label>
                <label>Periodo (primer día del mes)
                    <input type="date" name="period_month">
                </label>
                <label>Fecha de emisión
                    <input type="date" name="issued_on">
                </label>
                <label>Vencimiento externo
                    <input type="date" name="due_on">
                </label>
                <label>Referencia externa (visible solo al equipo)
                    <input type="text" name="external_reference" maxlength="190">
                </label>
                <label>Soporte de origen (PNG, JPEG, WebP o PDF; máximo 5 MiB)
                    <input type="file" name="source_support" accept="image/png,image/jpeg,image/webp,application/pdf">
                </label>
                <button class="button" type="submit">Guardar obligación</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="surface">
    <h2>Obligaciones registradas</h2>
    <?php if ($obligations === []): ?>
        <p class="empty-state">No hay obligaciones registradas.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($obligations as $obligation): ?>
                <?php
                $id = (int) $obligation['id'];
                $leaseId = (int) $obligation['lease_id'];
                $mutable = $obligation['voided_at'] === null
                    && $obligation['settled_at'] === null
                    && $obligation['charge_id'] === null;
                $linkedCharge = $obligation['linked_charge'];
                $chargeState = is_array($linkedCharge) ? (string) $linkedCharge['state'] : null;
                $canVoid = $obligation['voided_at'] === null
                    && ($obligation['charge_id'] === null || $chargeState === 'voided');
                $chargeOptionsKey = $leaseId . ':' . (string) $obligation['period_month'];
                $matchingAdministrationCharges = $chargeOptionsByLease[$chargeOptionsKey] ?? [];
                ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($obligation['tenant_name']) ?> · <?= $escape($obligation['property_name']) ?> · <?= $escape($obligation['unit_code']) ?></strong>
                        <span class="status-pill" data-obligation-state="<?= $escape($obligation['external_state']) ?>">
                            <?= $escape($externalStateLabels[$obligation['external_state']] ?? 'Obligación') ?>
                        </span>
                    </div>
                    <div>Arrendamiento #<?= $escape($leaseId) ?><?= $obligation['lease_ended_at'] === null ? '' : ' · Finalizado' ?></div>
                    <div>Tipo: <?= $escape($typeLabels[$obligation['type']] ?? 'Obligación') ?><?= $obligation['service_name'] === null ? '' : ' · ' . $escape($obligation['service_name']) ?></div>
                    <div><?= $escape($obligation['description']) ?></div>
                    <div>Valor externo: $<?= $escape($obligation['amount']) ?></div>
                    <?php if ($obligation['period_month'] !== null): ?><div>Periodo: <?= $escape(substr((string) $obligation['period_month'], 0, 7)) ?></div><?php endif; ?>
                    <?php if ($obligation['issued_on'] !== null): ?><div>Emisión: <?= $escape($obligation['issued_on']) ?></div><?php endif; ?>
                    <?php if ($obligation['due_on'] !== null): ?><div>Vencimiento externo: <?= $escape($obligation['due_on']) ?></div><?php endif; ?>
                    <?php if ($obligation['external_reference'] !== null): ?><div>Referencia externa: <?= $escape($obligation['external_reference']) ?></div><?php endif; ?>
                    <div>Vínculo financiero: <?= $obligation['is_charged'] ? 'Sí' : 'No' ?></div>
                    <?php if (is_array($linkedCharge)): ?>
                        <div>
                            Cargo #<?= $escape($linkedCharge['id']) ?> · <?= $escape($chargeStateLabels[$chargeState] ?? 'Estado no disponible') ?>
                            · vence <?= $escape($linkedCharge['due_date']) ?> · $<?= $escape($linkedCharge['amount']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($obligation['has_source_support']): ?>
                        <p><a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/finance/obligations/<?= $escape($id) ?>/source-support">Ver soporte de origen</a></p>
                    <?php endif; ?>
                    <?php if ($obligation['has_settlement_support']): ?>
                        <p><a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/finance/obligations/<?= $escape($id) ?>/settlement-support">Ver soporte de satisfacción</a></p>
                    <?php endif; ?>

                    <?php if ($canManage && $mutable): ?>
                        <form method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/finance/obligations/<?= $escape($id) ?>">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <label>Tipo
                                <select name="type" required><?= $formTypes((string) $obligation['type']) ?></select>
                            </label>
                            <label>Servicio<input type="text" name="service_name" maxlength="120" value="<?= $escape($obligation['service_name']) ?>"></label>
                            <label>Descripción<textarea name="description" maxlength="500" required><?= $escape($obligation['description']) ?></textarea></label>
                            <label>Valor externo<input type="text" name="amount" inputmode="decimal" maxlength="13" value="<?= $escape($obligation['amount']) ?>" required></label>
                            <label>Periodo<input type="date" name="period_month" value="<?= $escape($obligation['period_month']) ?>"></label>
                            <label>Fecha de emisión<input type="date" name="issued_on" value="<?= $escape($obligation['issued_on']) ?>"></label>
                            <label>Vencimiento externo<input type="date" name="due_on" value="<?= $escape($obligation['due_on']) ?>"></label>
                            <label>Referencia externa<input type="text" name="external_reference" maxlength="190" value="<?= $escape($obligation['external_reference']) ?>"></label>
                            <label>Reemplazar soporte de origen<input type="file" name="source_support" accept="image/png,image/jpeg,image/webp,application/pdf"></label>
                            <?php if ($obligation['has_source_support']): ?>
                                <label><input type="checkbox" name="remove_source_support" value="1"> Quitar soporte de origen</label>
                            <?php endif; ?>
                            <button class="button button--quiet" type="submit">Guardar cambios</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canManage && $obligation['voided_at'] === null && $obligation['settled_at'] === null): ?>
                        <form method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/finance/obligations/<?= $escape($id) ?>/settle">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <label>Soporte de satisfacción externa (opcional)
                                <input type="file" name="settlement_support" accept="image/png,image/jpeg,image/webp,application/pdf">
                            </label>
                            <button class="button button--quiet" type="submit">Marcar atendida externamente</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canManage && $obligation['voided_at'] === null && $obligation['charge_id'] === null && in_array($obligation['type'], ['utility', 'fine_ph'], true)): ?>
                        <form method="post" action="<?= $escape($basePath) ?>/admin/finance/obligations/<?= $escape($id) ?>/generate-charge">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <label>Vencimiento del cargo al inquilino<input type="date" name="charge_due_date" required></label>
                            <button class="button button--quiet" type="submit">Generar cargo financiero</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canManage && $obligation['voided_at'] === null && $obligation['charge_id'] === null && $obligation['type'] === 'administration_ph'): ?>
                        <?php if ($matchingAdministrationCharges === []): ?>
                            <p class="hint">No hay un cargo F9 de administración activo para el mismo periodo.</p>
                        <?php else: ?>
                            <form method="post" action="<?= $escape($basePath) ?>/admin/finance/obligations/<?= $escape($id) ?>/link-administration-charge">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <label>Cargo F9 del mismo periodo
                                    <select name="charge_id" required>
                                        <?php foreach ($matchingAdministrationCharges as $charge): ?>
                                            <option value="<?= $escape($charge['id']) ?>">#<?= $escape($charge['id']) ?> · <?= $escape($charge['due_date']) ?> · $<?= $escape($charge['amount']) ?> · <?= $escape($chargeStateLabels[$charge['state']] ?? 'Cargo') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button class="button button--quiet" type="submit">Vincular cargo de administración</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($canManage && $canVoid): ?>
                        <form method="post" action="<?= $escape($basePath) ?>/admin/finance/obligations/<?= $escape($id) ?>/void">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <label>Motivo de anulación<input type="text" name="reason" maxlength="500" required></label>
                            <button class="button button--quiet" type="submit">Anular obligación</button>
                        </form>
                    <?php elseif ($canManage && $obligation['voided_at'] === null && $obligation['charge_id'] !== null): ?>
                        <p class="hint">Anule primero el cargo financiero vinculado para poder anular esta obligación.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
