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
        <div class="entity-list">
            <?php foreach ($portfolio as $lease): ?>
                <article class="entity-card">
                    <div class="entity-card__main">
                        <h2><?= $escape($lease['tenant_name']) ?></h2>
                        <p><?= $escape($lease['property_name']) ?> · <?= $escape($lease['unit_code']) ?></p>
                        <p class="hint"><?= $escape($lease['property_address']) ?>, <?= $escape($lease['property_city']) ?></p>
                    </div>
                    <dl class="summary-list">
                        <div><dt>Arrendamiento</dt><dd>#<?= $escape($lease['lease_id']) ?></dd></div>
                        <div><dt>Saldo pendiente</dt><dd>$<?= $escape($lease['outstanding']) ?></dd></div>
                        <div><dt>Saldo vencido</dt><dd>$<?= $escape($lease['overdue']) ?></dd></div>
                        <div><dt>Vencimiento pendiente más antiguo</dt><dd><?= $escape($lease['oldest_due_date'] ?? '—') ?></dd></div>
                    </dl>
                    <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/contracts/<?= $escape($lease['contract_id']) ?>">Abrir contrato</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
