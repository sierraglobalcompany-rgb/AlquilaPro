<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$units = is_array($units ?? null) ? $units : [];
$filters = is_array($filters ?? null) ? $filters : [];
$error = is_string($error ?? null) ? $error : null;
$selectedType = (string) ($filters['type'] ?? '');
$city = (string) ($filters['city'] ?? '');
$sector = (string) ($filters['sector'] ?? '');
?>
<section>
    <p class="eyebrow">Catálogo</p>
    <h1>Unidades disponibles</h1>
    <p class="hint">Consulta apartamentos y parqueaderos publicados actualmente.</p>

    <form method="get" action="<?= $escape($basePath) ?>/catalog">
        <label>
            Tipo
            <select name="type">
                <option value=""<?= $selectedType === '' ? ' selected' : '' ?>>Todos</option>
                <option value="apartment"<?= $selectedType === 'apartment' ? ' selected' : '' ?>>Apartamentos</option>
                <option value="parking"<?= $selectedType === 'parking' ? ' selected' : '' ?>>Parqueaderos</option>
            </select>
        </label>
        <label>
            Ciudad
            <input name="city" value="<?= $escape($city) ?>" maxlength="120">
        </label>
        <label>
            Sector
            <input name="sector" value="<?= $escape($sector) ?>" maxlength="120">
        </label>
        <button type="submit">Filtrar</button>
    </form>

    <?php if ($error !== null): ?>
        <p role="alert"><?= $escape($error) ?></p>
    <?php elseif ($units === []): ?>
        <p>No hay unidades disponibles con estos filtros.</p>
    <?php else: ?>
        <div class="stack">
            <?php foreach ($units as $unit): ?>
                <article class="surface">
                    <img
                        src="<?= $escape($basePath) ?>/catalog/<?= $escape($unit['public_key']) ?>/photo/1"
                        alt="Foto de <?= $escape($unit['property_name']) ?>"
                        loading="lazy"
                    >
                    <p class="eyebrow"><?= $unit['type'] === 'apartment' ? 'Apartamento' : 'Parqueadero' ?></p>
                    <h2><?= $escape($unit['property_name']) ?></h2>
                    <p><?= $escape($unit['city']) ?><?= $unit['sector'] !== null ? ' · ' . $escape($unit['sector']) : '' ?></p>
                    <?php if ($unit['location_label'] !== null): ?>
                        <p><?= $escape($unit['location_label']) ?></p>
                    <?php endif; ?>
                    <p><strong>$<?= $escape(number_format((float) $unit['monthly_price'], 0, ',', '.')) ?>/mes</strong></p>
                    <?php if ($unit['type'] === 'apartment' && $unit['administration_fee'] !== null && (float) $unit['administration_fee'] > 0): ?>
                        <p>Administración: $<?= $escape(number_format((float) $unit['administration_fee'], 0, ',', '.')) ?></p>
                    <?php endif; ?>
                    <?php if ($unit['type'] === 'apartment'): ?>
                        <p><?= $escape($unit['bedrooms'] ?? 0) ?> hab. · <?= $escape($unit['bathrooms'] ?? 0) ?> baños<?= $unit['area_m2'] !== null ? ' · ' . $escape($unit['area_m2']) . ' m²' : '' ?></p>
                    <?php else: ?>
                        <p><?= $unit['parking_level'] !== null ? 'Nivel ' . $escape($unit['parking_level']) : 'Parqueadero' ?><?= $unit['vehicle_type'] !== null ? ' · ' . $escape($unit['vehicle_type']) : '' ?></p>
                    <?php endif; ?>
                    <a href="<?= $escape($basePath) ?>/catalog/<?= $escape($unit['public_key']) ?>">Ver detalles</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p><a href="<?= $escape($basePath) ?>/">Volver al inicio</a></p>
</section>