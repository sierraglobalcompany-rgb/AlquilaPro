<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="surface">
    <p class="eyebrow">Portal del inquilino</p>
    <h1>Mis arrendamientos</h1>
    <p class="hint">Consulta tus arrendamientos activos y finalizados.</p>
</section>

<section class="surface">
    <?php if ($leases === []): ?>
        <p class="hint">No tienes arrendamientos asociados.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($leases as $lease): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($lease['property_name']) ?></strong>
                        <span class="status-pill"><?= $lease['is_active'] ? 'Activo' : 'Finalizado' ?></span>
                    </div>
                    <div>Unidad: <?= $escape($lease['unit_code']) ?></div>
                    <div>Tipo: <?= $escape($lease['unit_type']) ?></div>
                    <div>Inicio: <?= $escape($lease['start_date']) ?></div>
                    <div>Fin: <?= $escape($lease['end_date']) ?></div>
                    <a class="button button--quiet" href="<?= $escape($basePath) ?>/tenant/leases/<?= $escape($lease['id']) ?>">Ver arrendamiento</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
