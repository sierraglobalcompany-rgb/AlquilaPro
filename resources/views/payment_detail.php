<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$stateLabels = [
    'pending_reconciliation' => 'Pendiente de conciliación',
    'reconciled' => 'Conciliado',
    'rejected' => 'Rechazado',
];
$chargeById = [];
foreach ($charges as $charge) {
    $chargeById[(int) $charge['id']] = $charge;
}
?>
<section class="surface">
    <p class="eyebrow">Cartera</p>
    <h1>Revisión de pago #<?= $escape($payment['id']) ?></h1>
    <p><a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/finance">Volver a cartera</a></p>
    <div class="users-grid">
        <article class="user-card">
            <div>Inquilino: <?= $escape($payment['tenant_name'] ?? '') ?></div>
            <div>Inmueble / unidad: <?= $escape($payment['property_name'] ?? '') ?> · <?= $escape($payment['unit_code'] ?? '') ?></div>
            <div>Arrendamiento: #<?= $escape($payment['lease_id']) ?></div>
            <div>Monto: $<?= $escape($payment['amount']) ?></div>
            <div>Fecha del pago: <?= $escape($payment['paid_on']) ?><?= $payment['paid_time'] === null ? '' : ' · ' . $escape($payment['paid_time']) ?></div>
            <div>Medio reportado: <?= $escape($payment['payment_method_snapshot']['label'] ?? 'Medio de pago') ?></div>
            <div>Estado: <?= $escape($stateLabels[$payment['state']] ?? 'Pago') ?></div>
            <div>Reportado: <?= $escape($payment['reported_at']) ?></div>
            <?php if ($payment['has_receipt']): ?>
                <p><a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/finance/payments/<?= $escape($payment['id']) ?>/receipt">Ver comprobante</a></p>
            <?php else: ?>
                <div>Sin comprobante</div>
            <?php endif; ?>
        </article>
    </div>
</section>

<section class="surface">
    <h2>Asignaciones propuestas</h2>
    <?php if ($payment['allocations'] === []): ?>
        <p class="empty-state">No hay asignaciones registradas.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($payment['allocations'] as $allocation): ?>
                <?php $charge = $chargeById[(int) $allocation['charge_id']] ?? null; ?>
                <article class="user-card">
                    <div>Cargo #<?= $escape($allocation['charge_id']) ?><?= $charge === null ? '' : ' · ' . $escape($charge['type']) ?></div>
                    <div>Propuesto: $<?= $escape($allocation['amount']) ?></div>
                    <div>Saldo disponible: $<?= $escape($charge['outstanding'] ?? '0.00') ?></div>
                    <?php if ($charge !== null && $charge['is_overdue']): ?>
                        <div>Vencido: <?= $escape($charge['days_overdue']) ?> días</div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($payment['duplicate_warnings'] !== []): ?>
    <section class="surface">
        <h2>Posible reporte duplicado</h2>
        <ul>
            <?php foreach ($payment['duplicate_warnings'] as $warning): ?>
                <li>
                    <a href="<?= $escape($basePath) ?>/admin/finance/payments/<?= $escape($warning['payment_id']) ?>">Revisar pago #<?= $escape($warning['payment_id']) ?></a>
                    · <?= $escape($warning['kind'] === 'receipt_hash' ? 'Comprobante idéntico' : 'Datos similares') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($payment['state'] === 'rejected'): ?>
    <section class="surface">
        <h2>Motivo de rechazo</h2>
        <p><?= $escape($payment['rejection_reason'] ?? '') ?></p>
    </section>
<?php endif; ?>

<?php if ($payment['state'] === 'pending_reconciliation' && $canManage): ?>
    <section class="surface">
        <h2>Conciliar pago</h2>
        <p>Las asignaciones finales deben sumar $<?= $escape($payment['amount']) ?> y no exceder el saldo disponible de cada cargo.</p>
        <form method="post" action="<?= $escape($basePath) ?>/admin/finance/payments/<?= $escape($payment['id']) ?>/reconcile">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <div class="users-grid">
                <?php foreach ($charges as $charge): ?>
                    <?php if ($charge['state'] === 'voided' || $charge['outstanding'] === '0.00') { continue; } ?>
                    <?php
                    $proposed = '';
                    foreach ($payment['allocations'] as $allocation) {
                        if ((int) $allocation['charge_id'] === (int) $charge['id']) {
                            $proposed = (string) $allocation['amount'];
                            break;
                        }
                    }
                    ?>
                    <label class="user-card">
                        Cargo #<?= $escape($charge['id']) ?> · saldo $<?= $escape($charge['outstanding']) ?>
                        <input type="number" min="0.01" max="<?= $escape($charge['outstanding']) ?>" step="0.01"
                               name="allocations[<?= $escape($charge['id']) ?>]" value="<?= $escape($proposed) ?>">
                    </label>
                <?php endforeach; ?>
            </div>
            <button class="button" type="submit">Conciliar pago</button>
        </form>
        <form method="post" action="<?= $escape($basePath) ?>/admin/finance/payments/<?= $escape($payment['id']) ?>/reject">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <label>Motivo del rechazo
                <textarea name="reason" maxlength="500" required></textarea>
            </label>
            <button class="button button--quiet" type="submit">Rechazar reporte</button>
        </form>
    </section>
<?php endif; ?>
