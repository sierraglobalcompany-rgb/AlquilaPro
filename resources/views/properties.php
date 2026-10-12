<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="page-stack" aria-labelledby="properties-title">
    <header class="page-heading">
        <div>
            <p class="eyebrow">Inventario</p>
            <h1 id="properties-title">Propiedades</h1>
            <p>Administra los inmuebles y sus unidades desde una sola fuente.</p>
        </div>
    </header>

    <?php if ($properties === []): ?>
        <p class="empty-state">Aún no hay propiedades registradas.</p>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($properties as $property): ?>
                <article class="content-card">
                    <div>
                        <h2><?= $escape($property['name']) ?></h2>
                        <p><?= $escape($property['city']) ?><?= $property['sector'] !== null ? ' · ' . $escape($property['sector']) : '' ?></p>
                        <p class="muted"><?= $property['is_active'] ? 'Activa' : 'Inactiva' ?></p>
                    </div>
                    <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/properties/<?= (int) $property['id'] ?>">Ver</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
