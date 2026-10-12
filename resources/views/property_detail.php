<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="page-stack" aria-labelledby="property-title">
    <a class="text-link" href="<?= $escape($basePath) ?>/admin/properties">← Propiedades</a>

    <header class="page-heading">
        <div>
            <p class="eyebrow">Propiedad</p>
            <h1 id="property-title"><?= $escape($property['name']) ?></h1>
            <p><?= $escape($property['city']) ?><?= $property['sector'] !== null ? ' · ' . $escape($property['sector']) : '' ?></p>
        </div>
        <span class="status-pill"><?= $property['is_active'] ? 'Activa' : 'Inactiva' ?></span>
    </header>

    <article class="content-card content-card--stacked">
        <h2>Información</h2>
        <dl class="detail-list">
            <div><dt>Dirección</dt><dd><?= $escape($property['address']) ?></dd></div>
            <?php if ($property['administration_name'] !== null): ?>
                <div><dt>Administración</dt><dd><?= $escape($property['administration_name']) ?></dd></div>
            <?php endif; ?>
            <?php if ($property['administration_contact'] !== null): ?>
                <div><dt>Contacto</dt><dd><?= $escape($property['administration_contact']) ?></dd></div>
            <?php endif; ?>
            <?php if ($property['notes'] !== null): ?>
                <div><dt>Notas</dt><dd><?= nl2br($escape($property['notes'])) ?></dd></div>
            <?php endif; ?>
        </dl>
    </article>

    <section aria-labelledby="units-title">
        <div class="section-heading">
            <h2 id="units-title">Unidades</h2>
            <span><?= count($units) ?></span>
        </div>

        <?php if ($units === []): ?>
            <p class="empty-state">Esta propiedad aún no tiene unidades.</p>
        <?php else: ?>
            <div class="card-list">
                <?php foreach ($units as $unit): ?>
                    <article class="content-card">
                        <div>
                            <h3><?= $escape($unit['code']) ?></h3>
                            <p><?= $unit['type'] === 'apartment' ? 'Apartamento' : 'Parqueadero' ?> · <?= $escape($unit['status']) ?></p>
                            <?php if ($unit['monthly_price'] !== null): ?>
                                <p class="muted">$<?= number_format((float) $unit['monthly_price'], 0, ',', '.') ?></p>
                            <?php endif; ?>
                        </div>
                        <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>">Ver</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
