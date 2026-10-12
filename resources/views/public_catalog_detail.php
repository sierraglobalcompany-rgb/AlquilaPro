<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$unit = is_array($unit ?? null) ? $unit : [];
$videoLinks = is_array($videoLinks ?? null) ? $videoLinks : [];
$isApartment = ($unit['type'] ?? null) === 'apartment';
?>
<article>
    <p class="eyebrow"><?= $isApartment ? 'Apartamento' : 'Parqueadero' ?></p>
    <h1><?= $escape($unit['property_name'] ?? '') ?></h1>
    <p><?= $escape($unit['city'] ?? '') ?><?= ($unit['sector'] ?? null) !== null ? ' · ' . $escape($unit['sector']) : '' ?></p>
    <?php if (($unit['location_label'] ?? null) !== null): ?>
        <p><?= $escape($unit['location_label']) ?></p>
    <?php endif; ?>
    <p><strong>Disponible</strong></p>

    <section class="surface">
        <h2>Canon mensual</h2>
        <p><strong>$<?= $escape(number_format((float) ($unit['monthly_price'] ?? 0), 0, ',', '.')) ?></strong></p>
        <?php if ($isApartment && ($unit['administration_fee'] ?? null) !== null && (float) $unit['administration_fee'] > 0): ?>
            <p>Administración: $<?= $escape(number_format((float) $unit['administration_fee'], 0, ',', '.')) ?></p>
        <?php endif; ?>
    </section>

    <section class="surface">
        <h2>Descripción</h2>
        <p><?= $escape($unit['description'] ?? '') ?></p>
    </section>

    <section class="surface">
        <h2>Características</h2>
        <?php if ($isApartment): ?>
            <p>
                <?= $escape($unit['bedrooms'] ?? 0) ?> habitaciones ·
                <?= $escape($unit['bathrooms'] ?? 0) ?> baños
                <?= ($unit['area_m2'] ?? null) !== null ? ' · ' . $escape($unit['area_m2']) . ' m²' : '' ?>
            </p>
            <?php if (($unit['tower'] ?? null) !== null): ?><p>Torre: <?= $escape($unit['tower']) ?></p><?php endif; ?>
            <?php if (($unit['floor'] ?? null) !== null): ?><p>Piso: <?= $escape($unit['floor']) ?></p><?php endif; ?>
        <?php else: ?>
            <?php if (($unit['parking_level'] ?? null) !== null): ?><p>Nivel: <?= $escape($unit['parking_level']) ?></p><?php endif; ?>
            <?php if (($unit['dimensions'] ?? null) !== null): ?><p>Dimensiones: <?= $escape($unit['dimensions']) ?></p><?php endif; ?>
            <?php if (($unit['vehicle_type'] ?? null) !== null): ?><p>Vehículo: <?= $escape($unit['vehicle_type']) ?></p><?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="surface">
        <h2>Requisitos</h2>
        <p><?= $escape($unit['requirements'] ?? '') ?></p>
    </section>

    <?php if ($videoLinks !== []): ?>
        <section class="surface">
            <h2>Videos</h2>
            <?php foreach ($videoLinks as $videoUrl): ?>
                <p><a href="<?= $escape($videoUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $escape($videoUrl) ?></a></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <p><a href="<?= $escape($basePath) ?>/catalog">Volver al catálogo</a></p>
    <p><a href="<?= $escape($basePath) ?>/">Volver al inicio</a></p>
</article>
