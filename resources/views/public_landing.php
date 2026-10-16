<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$featuredUnits = is_array($featuredUnits ?? null) ? array_values($featuredUnits) : [];
$csrfToken = (string) ($csrfToken ?? '');
?>
<section class="public-section" aria-labelledby="public-properties-title">
    <div class="public-section-heading">
        <h1 id="public-properties-title">Propiedades disponibles</h1>
        <a href="<?= $escape($basePath) ?>/catalog">Ver todas</a>
    </div>

    <?php if ($featuredUnits === []): ?>
        <p class="public-empty" data-public-empty>No hay propiedades disponibles por ahora.</p>
    <?php else: ?>
        <div class="public-carousel" data-public-carousel>
            <div class="public-carousel__track">
                <?php foreach ($featuredUnits as $unit): ?>
                    <?php
                    $publicKey = (string) ($unit['public_key'] ?? '');
                    $positions = is_array($unit['photo_positions'] ?? null)
                        ? array_slice(array_values($unit['photo_positions']), 0, 5)
                        : [];
                    $coverPosition = (int) ($positions[0] ?? 1);
                    $price = $unit['monthly_price'] !== null
                        ? '$' . number_format((float) $unit['monthly_price'], 0, ',', '.')
                        : null;
                    $location = implode(' · ', array_values(array_filter([
                        (string) ($unit['city'] ?? ''),
                        (string) ($unit['sector'] ?? ''),
                    ], static fn (string $value): bool => $value !== '')));
                    ?>
                    <article class="public-property-card" data-public-featured-unit>
                        <a class="public-property-card__media" href="<?= $escape($basePath) ?>/catalog/<?= $escape($publicKey) ?>">
                            <img
                                src="<?= $escape($basePath) ?>/catalog/<?= $escape($publicKey) ?>/photo/<?= $escape($coverPosition) ?>"
                                alt="<?= $escape($unit['property_name'] ?? 'Propiedad disponible') ?>"
                                loading="<?= $coverPosition === 1 ? 'eager' : 'lazy' ?>"
                            >
                        </a>

                        <?php if (count($positions) > 1): ?>
                            <div class="public-property-card__thumbnails" aria-label="Fotos disponibles">
                                <?php foreach ($positions as $position): ?>
                                    <img
                                        src="<?= $escape($basePath) ?>/catalog/<?= $escape($publicKey) ?>/photo/<?= $escape($position) ?>"
                                        alt=""
                                        loading="lazy"
                                    >
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="public-property-card__body">
                            <p class="eyebrow"><?= $escape(($unit['type'] ?? '') === 'parking' ? 'Parqueadero' : 'Apartamento') ?></p>
                            <h2><?= $escape($unit['property_name'] ?? 'Propiedad disponible') ?></h2>
                            <?php if ($location !== ''): ?><p><?= $escape($location) ?></p><?php endif; ?>
                            <?php if ($price !== null): ?><p><strong><?= $escape($price) ?> / mes</strong></p><?php endif; ?>

                            <div class="public-property-card__facts">
                                <?php if (($unit['type'] ?? null) === 'apartment'): ?>
                                    <?php if ($unit['bedrooms'] !== null): ?><span><?= $escape($unit['bedrooms']) ?> hab.</span><?php endif; ?>
                                    <?php if ($unit['bathrooms'] !== null): ?><span><?= $escape($unit['bathrooms']) ?> baños</span><?php endif; ?>
                                    <?php if ($unit['area_m2'] !== null): ?><span><?= $escape($unit['area_m2']) ?> m²</span><?php endif; ?>
                                    <?php if ($unit['floor'] !== null): ?><span>Piso <?= $escape($unit['floor']) ?></span><?php endif; ?>
                                    <?php if (($unit['linked_parking_count'] ?? 0) > 0): ?><span><?= $escape($unit['linked_parking_count']) ?> parqueadero(s)</span><?php endif; ?>
                                <?php else: ?>
                                    <?php if ($unit['parking_level'] !== null): ?><span>Nivel <?= $escape($unit['parking_level']) ?></span><?php endif; ?>
                                    <?php if ($unit['dimensions'] !== null): ?><span><?= $escape($unit['dimensions']) ?></span><?php endif; ?>
                                    <?php if ($unit['vehicle_type'] !== null): ?><span><?= $escape($unit['vehicle_type']) ?></span><?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <a class="button" href="<?= $escape($basePath) ?>/catalog/<?= $escape($publicKey) ?>">Ver detalle</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="public-login-panel" aria-labelledby="public-login-title">
    <h2 id="public-login-title">Iniciar sesión</h2>
    <?php
    $action = (string) $basePath . '/login';
    require __DIR__ . '/partials/login_form.php';
    ?>
</section>
