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

    <?php if (!empty($error)): ?>
        <div class="form-error" role="alert"><?= $escape($error) ?></div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <article class="content-card content-card--stacked">
            <h2>Nueva propiedad</h2>
            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/properties">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Nombre<input name="name" required maxlength="150"></label>
                <label>Dirección<input name="address" required maxlength="255"></label>
                <label>Ciudad<input name="city" required maxlength="120"></label>
                <label>Sector<input name="sector" maxlength="120"></label>
                <label>Administración<input name="administration_name" maxlength="150"></label>
                <label>Contacto de administración<input name="administration_contact" maxlength="190"></label>
                <label>Notas<textarea name="notes" rows="3"></textarea></label>
                <button class="button" type="submit">Crear propiedad</button>
            </form>
        </article>
    <?php endif; ?>

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
                        <p class="muted">Disponibles: <?= (int) $property['unit_counts']['available'] ?> · En proceso: <?= (int) $property['unit_counts']['in_process'] ?> · Ocupadas: <?= (int) $property['unit_counts']['occupied'] ?> · Inactivas: <?= (int) $property['unit_counts']['inactive'] ?> · Mantenimiento: <?= (int) $property['unit_counts']['maintenance'] ?></p>
                    </div>
                    <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/properties/<?= (int) $property['id'] ?>">Ver</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
