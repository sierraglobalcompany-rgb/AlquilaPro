<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="surface">
    <p class="eyebrow">Administración</p>
    <h1>Cartera</h1>
    <p class="hint">Cargos y saldos pendientes por arrendamiento.</p>

    <?php if ($portfolio === []): ?>
        <p class="empty-state">No hay cargos registrados.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($portfolio as $lease): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($lease['tenant_name']) ?></strong>
                        <span class="status-pill">Arrendamiento #<?= $escape($lease['lease_id']) ?></span>
                    </div>
                    <div>Propiedad / unidad: <?= $escape($lease['property_name']) ?> · <?= $escape($lease['unit_code']) ?></div>
                    <div class="hint"><?= $escape($lease['property_address']) ?>, <?= $escape($lease['property_city']) ?></div>
                    <div>Saldo pendiente: $<?= $escape($lease['outstanding']) ?></div>
                    <div>Saldo vencido: $<?= $escape($lease['overdue']) ?></div>
                    <div>Vencimiento pendiente más antiguo: <?= $escape($lease['oldest_due_date'] ?? '—') ?></div>
                    <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/contracts/<?= $escape($lease['contract_id']) ?>">Abrir contrato</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
