<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusLabels = [
    'available' => 'Disponible',
    'in_process' => 'En proceso',
    'occupied' => 'Ocupada',
    'inactive' => 'Inactiva',
    'maintenance' => 'Mantenimiento',
];
?>
<section class="page-stack" aria-labelledby="unit-title">
    <a class="text-link" href="<?= $escape($basePath) ?>/admin/properties/<?= (int) $property['id'] ?>">← <?= $escape($property['name']) ?></a>

    <header class="page-heading">
        <div>
            <p class="eyebrow"><?= $unit['type'] === 'apartment' ? 'Apartamento' : 'Parqueadero' ?></p>
            <h1 id="unit-title"><?= $escape($unit['code']) ?></h1>
            <p><?= $escape($unit['location_label'] ?? $property['name']) ?></p>
        </div>
        <span class="status-pill"><?= $escape($statusLabels[$unit['status']] ?? $unit['status']) ?></span>
    </header>

    <article class="content-card content-card--stacked">
        <h2>Información</h2>
        <dl class="detail-list">
            <div><dt>Publicación</dt><dd><?= $unit['is_public'] ? 'Publicada' : 'Privada' ?></dd></div>
            <?php if ($unit['monthly_price'] !== null): ?>
                <div><dt>Precio mensual</dt><dd>$<?= number_format((float) $unit['monthly_price'], 0, ',', '.') ?></dd></div>
            <?php endif; ?>
            <?php if ($unit['description'] !== null): ?>
                <div><dt>Descripción</dt><dd><?= nl2br($escape($unit['description'])) ?></dd></div>
            <?php endif; ?>
            <?php if ($unit['requirements'] !== null): ?>
                <div><dt>Requisitos</dt><dd><?= nl2br($escape($unit['requirements'])) ?></dd></div>
            <?php endif; ?>
            <?php if ($unit['type'] === 'apartment'): ?>
                <?php if ($unit['area_m2'] !== null): ?><div><dt>Área</dt><dd><?= $escape($unit['area_m2']) ?> m²</dd></div><?php endif; ?>
                <?php if ($unit['bedrooms'] !== null): ?><div><dt>Habitaciones</dt><dd><?= (int) $unit['bedrooms'] ?></dd></div><?php endif; ?>
                <?php if ($unit['bathrooms'] !== null): ?><div><dt>Baños</dt><dd><?= $escape($unit['bathrooms']) ?></dd></div><?php endif; ?>
            <?php else: ?>
                <?php if ($unit['parking_level'] !== null): ?><div><dt>Nivel</dt><dd><?= $escape($unit['parking_level']) ?></dd></div><?php endif; ?>
                <?php if ($unit['dimensions'] !== null): ?><div><dt>Dimensiones</dt><dd><?= $escape($unit['dimensions']) ?></dd></div><?php endif; ?>
                <?php if ($unit['vehicle_type'] !== null): ?><div><dt>Vehículo</dt><dd><?= $escape($unit['vehicle_type']) ?></dd></div><?php endif; ?>
            <?php endif; ?>
            <?php if ($unit['private_notes'] !== null): ?>
                <div><dt>Notas privadas</dt><dd><?= nl2br($escape($unit['private_notes'])) ?></dd></div>
            <?php endif; ?>
        </dl>
    </article>

    <section aria-labelledby="media-title">
        <div class="section-heading">
            <h2 id="media-title">Multimedia</h2>
            <span><?= count($media) ?></span>
        </div>

        <?php if ($media === []): ?>
            <p class="empty-state">Esta unidad aún no tiene fotos o videos.</p>
        <?php else: ?>
            <div class="card-list">
                <?php foreach ($media as $item): ?>
                    <article class="content-card">
                        <div>
                            <h3><?= $item['type'] === 'photo' ? 'Foto' : 'Video' ?></h3>
                            <p class="muted"><?= $item['is_cover'] ? 'Portada · ' : '' ?>Orden <?= (int) $item['sort_order'] ?></p>
                        </div>
                        <?php if ($item['type'] === 'photo'): ?>
                            <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/media/<?= (int) $item['id'] ?>">Ver foto</a>
                        <?php elseif ($item['external_url'] !== null): ?>
                            <a class="button button--quiet" href="<?= $escape($item['external_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir video</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
