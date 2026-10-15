<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tenantMode = (bool) ($tenantMode ?? false);
$preview = is_array($preview ?? null) ? $preview : null;
$liquidation = is_array($liquidation ?? null) ? $liquidation : null;
$tenantLiquidation = is_array($tenantLiquidation ?? null) ? $tenantLiquidation : null;
$canViewFinance = (bool) ($canViewFinance ?? false);
$canManage = (bool) ($canManage ?? false);
$bonusEnabled = (bool) ($bonusEnabled ?? false);
?>
<section class="surface">
    <p class="eyebrow"><?= $tenantMode ? 'Portal del inquilino' : 'Contratos' ?></p>
    <h1>Liquidación contractual</h1>
    <?php if ($tenantMode): ?>
        <p class="hint">Cierre final del arrendamiento #<?= $escape($lease['id']) ?>.</p>
        <a class="button button--quiet" href="<?= $escape($basePath) ?>/tenant/leases/<?= $escape($lease['id']) ?>">Volver al arrendamiento</a>
    <?php else: ?>
        <p class="hint">Arrendamiento #<?= $escape($lease['id']) ?> · <?= $lease['is_active'] ? 'Activo' : 'Finalizado' ?></p>
        <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/contracts/<?= $escape($lease['contract_id']) ?>">Volver al contrato</a>
    <?php endif; ?>
</section>

<?php if ($tenantMode && $tenantLiquidation !== null): ?>
    <section class="surface">
        <h2>Cierre final</h2>
        <div>Finalizado: <?= $escape($tenantLiquidation['finalized_at']) ?></div>
        <?php if ($tenantLiquidation['summary_note'] !== null): ?>
            <p><?= nl2br($escape($tenantLiquidation['summary_note'])) ?></p>
        <?php endif; ?>
        <h3>Entrega</h3>
        <div>Inmueble entregado: <?= $tenantLiquidation['handover']['property_returned'] === true ? 'Sí' : ($tenantLiquidation['handover']['property_returned'] === false ? 'No' : 'Sin registrar') ?></div>
        <div>Llaves entregadas: <?= $tenantLiquidation['handover']['keys_returned_count'] === null ? 'Sin registrar' : $escape($tenantLiquidation['handover']['keys_returned_count']) ?></div>
        <?php if ($tenantLiquidation['handover']['notes'] !== null): ?>
            <p><?= nl2br($escape($tenantLiquidation['handover']['notes'])) ?></p>
        <?php endif; ?>
    </section>

    <section class="surface">
        <h2>Resumen financiero del cierre</h2>
        <?php if ($tenantLiquidation['finance'] === null): ?>
            <p class="empty-state">No hay resumen financiero disponible.</p>
        <?php else: ?>
            <div>Saldo pendiente: $<?= $escape($tenantLiquidation['finance']['outstanding']) ?></div>
            <div>Saldo vencido: $<?= $escape($tenantLiquidation['finance']['overdue']) ?></div>
            <div>Vencimiento pendiente más antiguo: <?= $escape($tenantLiquidation['finance']['oldest_due_date'] ?? '—') ?></div>
        <?php endif; ?>
    </section>

    <?php if ($tenantLiquidation['bonus'] !== null): ?>
        <section class="surface">
            <h2>Bono de buen cumplimiento</h2>
            <div>Decisión: <?= $escape($tenantLiquidation['bonus']['decision']) ?></div>
            <div>Valor informativo: $<?= $escape($tenantLiquidation['bonus']['amount']) ?></div>
            <?php if ($tenantLiquidation['bonus']['note'] !== null): ?>
                <p><?= nl2br($escape($tenantLiquidation['bonus']['note'])) ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($tenantLiquidation['obligations'] !== []): ?>
        <section class="surface">
            <h2>Obligaciones registradas en el cierre</h2>
            <div class="users-grid">
                <?php foreach ($tenantLiquidation['obligations'] as $obligation): ?>
                    <article class="user-card">
                        <strong><?= $escape($obligation['type']) ?></strong>
                        <p><?= nl2br($escape($obligation['description'])) ?></p>
                        <div>Monto: $<?= $escape($obligation['amount']) ?></div>
                        <div>Estado: <?= $escape($obligation['external_state']) ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($tenantLiquidation['claims'] !== []): ?>
        <section class="surface">
            <h2>Seguros y reclamaciones registradas en el cierre</h2>
            <div class="users-grid">
                <?php foreach ($tenantLiquidation['claims'] as $policy): ?>
                    <article class="user-card">
                        <strong><?= $escape($policy['provider_name']) ?> · <?= $escape($policy['product_name']) ?></strong>
                        <div>Estado de póliza: <?= $escape($policy['status']) ?></div>
                        <?php foreach ($policy['claims'] as $claim): ?>
                            <p><?= $escape($claim['incident_on']) ?> · <?= $escape($claim['status']) ?> · <?= nl2br($escape($claim['description'])) ?></p>
                        <?php endforeach; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($tenantLiquidation['community'] !== []): ?>
        <section class="surface">
            <h2>Convivencia registrada en el cierre</h2>
            <div class="users-grid">
                <?php foreach ($tenantLiquidation['community'] as $incident): ?>
                    <article class="user-card">
                        <strong><?= $escape($incident['category']) ?> · <?= $escape($incident['occurred_on']) ?></strong>
                        <div>Estado: <?= $escape($incident['status']) ?></div>
                        <p><?= nl2br($escape($incident['description'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($tenantLiquidation['memberships'] !== []): ?>
        <section class="surface">
            <h2>Servicios digitales registrados en el cierre</h2>
            <div class="users-grid">
                <?php foreach ($tenantLiquidation['memberships'] as $membership): ?>
                    <article class="user-card">
                        <strong><?= $escape($membership['service_name']) ?></strong>
                        <div>Precio: $<?= $escape($membership['price_snapshot']) ?></div>
                        <div>Vigencia: <?= $escape($membership['starts_on']) ?> a <?= $escape($membership['ends_on']) ?></div>
                        <div>Resultado del cargo: <?= $escape($membership['charge_outcome']) ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php elseif (!$tenantMode && $preview !== null): ?>
    <section class="surface">
        <h2>Estado del cierre</h2>
        <?php if ($liquidation !== null): ?>
            <p class="status-pill">Liquidación finalizada</p>
            <div>Finalizada: <?= $escape($liquidation['finalized_at']) ?></div>
            <?php if ($liquidation['summary_note'] !== null): ?>
                <p><?= nl2br($escape($liquidation['summary_note'])) ?></p>
            <?php endif; ?>
        <?php elseif ($preview['blockers'] === []): ?>
            <p class="status-pill">Sin bloqueos críticos derivados</p>
        <?php else: ?>
            <p class="hint">La liquidación no puede finalizar mientras existan estos bloqueos:</p>
            <ul>
                <?php foreach ($preview['blockers'] as $blocker): ?>
                    <li><?= $escape($blocker) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="surface">
        <h2>Contexto contractual</h2>
        <div>Tipo: <?= $escape($preview['contract']['document_type']) ?></div>
        <div>Inicio: <?= $escape($preview['contract']['start_date']) ?></div>
        <div>Fin: <?= $escape($preview['contract']['end_date']) ?></div>
        <div>Duración: <?= $escape($preview['contract']['duration_text']) ?></div>
    </section>

    <?php if ($canViewFinance && is_array($preview['finance'] ?? null)): ?>
        <section class="surface">
            <h2>Detalle financiero</h2>
            <div>Saldo pendiente: $<?= $escape($preview['finance']['summary']['outstanding']) ?></div>
            <div>Saldo vencido: $<?= $escape($preview['finance']['summary']['overdue']) ?></div>
            <?php if ($preview['finance']['charges'] === []): ?>
                <p class="empty-state">No hay cargos registrados.</p>
            <?php else: ?>
                <div class="users-grid">
                    <?php foreach ($preview['finance']['charges'] as $charge): ?>
                        <article class="user-card">
                            <strong><?= $escape($charge['type']) ?> · $<?= $escape($charge['amount']) ?></strong>
                            <div>Estado: <?= $escape($charge['state']) ?></div>
                            <div>Vencimiento: <?= $escape($charge['due_date']) ?></div>
                            <?php if ($charge['description'] !== null): ?><p><?= $escape($charge['description']) ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="surface">
        <h2>Bono de buen cumplimiento</h2>
        <?php if (!$bonusEnabled): ?>
            <p class="hint">La función de bono está deshabilitada.</p>
        <?php elseif ($preview['bonus'] === null): ?>
            <p class="empty-state">No hay bono configurado.</p>
        <?php else: ?>
            <div>Valor: $<?= $escape($preview['bonus']['amount']) ?></div>
            <div>Decisión: <?= $escape($preview['bonus']['decision'] ?? 'Pendiente') ?></div>
            <?php if ($preview['bonus']['note'] !== null): ?><p><?= nl2br($escape($preview['bonus']['note'])) ?></p><?php endif; ?>
        <?php endif; ?>

        <?php if ($canManage && $bonusEnabled && ($preview['bonus']['decision'] ?? null) === null): ?>
            <form method="post" action="<?= $escape($basePath) ?>/admin/leases/<?= $escape($lease['id']) ?>/bonus" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label class="field"><span>Valor</span><input type="text" name="amount" inputmode="decimal" value="<?= $escape($preview['bonus']['amount'] ?? '') ?>" required></label>
                <label class="field"><span>Nota</span><textarea name="note" maxlength="1000" rows="3"><?= $escape($preview['bonus']['note'] ?? '') ?></textarea></label>
                <button class="button" type="submit"><?= $preview['bonus'] === null ? 'Configurar bono' : 'Actualizar bono' ?></button>
            </form>
            <?php if ($preview['bonus'] !== null): ?>
                <form method="post" action="<?= $escape($basePath) ?>/admin/leases/<?= $escape($lease['id']) ?>/bonus/decision" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <button class="button" type="submit" name="decision" value="approved">Aprobar bono</button>
                    <button class="button button--quiet" type="submit" name="decision" value="denied">Negar bono</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($canManage && $canViewFinance && $liquidation === null): ?>
        <section class="surface">
            <h2>Finalizar liquidación</h2>
            <?php if ($lease['is_active']): ?>
                <p class="hint">Primero debe finalizarse el arrendamiento mediante el flujo contractual existente.</p>
            <?php else: ?>
                <form method="post" action="<?= $escape($basePath) ?>/admin/leases/<?= $escape($lease['id']) ?>/liquidation" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <label class="field"><span><input type="checkbox" name="property_returned" value="1"> Inmueble entregado</span></label>
                    <label class="field"><span>Cantidad de llaves entregadas</span><input type="number" min="0" max="100" name="keys_returned_count" value="0" required></label>
                    <label class="field"><span>Nota de entrega</span><textarea name="handover_notes" maxlength="2000" rows="3"></textarea></label>
                    <label class="field"><span>Nota resumen de liquidación</span><textarea name="summary_note" maxlength="2000" rows="3"></textarea></label>
                    <button class="button" type="submit">Finalizar liquidación</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
