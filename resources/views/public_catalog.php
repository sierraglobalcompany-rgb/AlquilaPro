<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$formatCount = static function (mixed $value): string {
    $number = (float) $value;

    return $number === floor($number)
        ? (string) (int) $number
        : rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');
};
$units = is_array($units ?? null) ? $units : [];
$filters = is_array($filters ?? null) ? $filters : [];
$error = is_string($error ?? null) ? $error : null;
$selectedType = (string) ($filters['type'] ?? '');
$city = (string) ($filters['city'] ?? '');
$sector = (string) ($filters['sector'] ?? '');
?>
<section class="public-section">
    <div>
        <p class="eyebrow">Catálogo</p>
        <h1>Unidades disponibles</h1>
        <p class="hint">Consulta apartamentos y parqueaderos publicados actualmente.</p>
    </div>

    <form class="stack-form" method="get" action="<?= $escape($basePath) ?>/catalog">
        <div class="field-grid">
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
        </div>
        <button type="submit">Filtrar</button>
    </form>

    <?php if ($error !== null): ?>
        <p role="alert"><?= $escape($error) ?></p>
    <?php elseif ($units === []): ?>
        <div class="public-empty">No hay unidades disponibles con estos filtros.</div>
    <?php else: ?>
        <div class="public-catalog-grid">
            <div class="stack">
                <?php foreach ($units as $unit): ?>
                    <?php
                    $isApartment = ($unit['type'] ?? null) === 'apartment';
                    $detailUrl = $basePath . '/catalog/' . (string) $unit['public_key'];
                    $linkedParkingCount = max(0, (int) ($unit['linked_parking_count'] ?? 0));
                    ?>
                    <article class="public-property-card">
                        <a class="public-property-card__media" href="<?= $escape($detailUrl) ?>">
                            <img
                                src="<?= $escape($basePath) ?>/catalog/<?= $escape($unit['public_key']) ?>/photo/1"
                                alt="Foto de <?= $escape($unit['property_name']) ?>"
                                loading="lazy"
                            >
                        </a>
                        <div class="public-property-card__body">
                            <p class="eyebrow"><?= $isApartment ? 'Apartamento' : 'Parqueadero' ?></p>
                            <h2><?= $escape($unit['property_name']) ?></h2>
                            <p><?= $escape($unit['city']) ?><?= $unit['sector'] !== null ? ' · ' . $escape($unit['sector']) : '' ?></p>
                            <?php if ($unit['location_label'] !== null): ?>
                                <p><?= $escape($unit['location_label']) ?></p>
                            <?php endif; ?>
                            <p><strong>$<?= $escape(number_format((float) $unit['monthly_price'], 0, ',', '.')) ?>/mes</strong></p>

                            <div class="public-property-card__facts">
                                <?php if ($isApartment): ?>
                                    <span><?= $escape($unit['bedrooms'] ?? 0) ?> hab.</span>
                                    <span><?= $escape($formatCount($unit['bathrooms'] ?? 0)) ?> baños</span>
                                    <?php if (($unit['area_m2'] ?? null) !== null): ?><span><?= $escape($unit['area_m2']) ?> m²</span><?php endif; ?>
                                    <?php if (($unit['floor'] ?? null) !== null): ?><span>Piso <?= $escape($unit['floor']) ?></span><?php endif; ?>
                                    <?php if ($linkedParkingCount > 0): ?>
                                        <span><?= $escape($linkedParkingCount) ?> parqueadero<?= $linkedParkingCount === 1 ? '' : 's' ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (($unit['parking_level'] ?? null) !== null): ?><span>Nivel <?= $escape($unit['parking_level']) ?></span><?php endif; ?>
                                    <?php if (($unit['vehicle_type'] ?? null) !== null): ?><span><?= $escape($unit['vehicle_type']) ?></span><?php endif; ?>
                                    <?php if (($unit['dimensions'] ?? null) !== null): ?><span><?= $escape($unit['dimensions']) ?></span><?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($isApartment && $unit['administration_fee'] !== null && (float) $unit['administration_fee'] > 0): ?>
                                <p class="hint">Administración: $<?= $escape(number_format((float) $unit['administration_fee'], 0, ',', '.')) ?></p>
                            <?php endif; ?>

                            <a class="button" href="<?= $escape($detailUrl) ?>">Ver detalle</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <a class="text-link" href="<?= $escape($basePath) ?>/">Volver al inicio</a>
</section>