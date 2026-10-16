<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$formatCount = static function (mixed $value): string {
    $number = (float) $value;

    return $number === floor($number)
        ? (string) (int) $number
        : rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');
};
$unit = is_array($unit ?? null) ? $unit : [];
$photoPositions = is_array($photoPositions ?? null) ? $photoPositions : [];
$videoLinks = is_array($videoLinks ?? null) ? $videoLinks : [];
$isApartment = ($unit['type'] ?? null) === 'apartment';
$publicKey = (string) ($unit['public_key'] ?? '');
$linkedParkingCount = max(0, (int) ($unit['linked_parking_count'] ?? 0));
?>
<article class="public-detail">
    <div class="public-section">
        <header>
            <p class="eyebrow"><?= $isApartment ? 'Apartamento' : 'Parqueadero' ?></p>
            <h1><?= $escape($unit['property_name'] ?? '') ?></h1>
            <p><?= $escape($unit['city'] ?? '') ?><?= ($unit['sector'] ?? null) !== null ? ' · ' . $escape($unit['sector']) : '' ?></p>
            <?php if (($unit['location_label'] ?? null) !== null): ?>
                <p><?= $escape($unit['location_label']) ?></p>
            <?php endif; ?>
            <p><strong>Disponible</strong></p>
        </header>

        <?php if ($photoPositions !== []): ?>
            <section class="public-detail__gallery">
                <h2>Fotos</h2>
                <div class="public-carousel">
                    <div class="public-detail__gallery-track">
                        <div class="public-carousel__track">
                            <?php foreach ($photoPositions as $position): ?>
                                <figure class="public-property-card" id="photo-<?= $escape($position) ?>">
                                    <div class="public-property-card__media">
                                        <img
                                            src="<?= $escape($basePath) ?>/catalog/<?= $escape($publicKey) ?>/photo/<?= $escape($position) ?>"
                                            alt="Foto <?= $escape($position) ?> de <?= $escape($unit['property_name'] ?? '') ?>"
                                            loading="lazy"
                                        >
                                    </div>
                                    <figcaption class="public-property-card__body">Foto <?= $escape($position) ?> de <?= $escape($unit['property_name'] ?? '') ?></figcaption>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if (count($photoPositions) > 1): ?>
                    <nav aria-label="Seleccionar foto">
                        <div class="public-property-card__thumbnails">
                            <?php foreach ($photoPositions as $position): ?>
                                <a href="#photo-<?= $escape($position) ?>" aria-label="Ver foto <?= $escape($position) ?>">
                                    <img
                                        src="<?= $escape($basePath) ?>/catalog/<?= $escape($publicKey) ?>/photo/<?= $escape($position) ?>"
                                        alt=""
                                        loading="lazy"
                                    >
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="surface">
            <h2>Canon mensual</h2>
            <p><strong>$<?= $escape(number_format((float) ($unit['monthly_price'] ?? 0), 0, ',', '.')) ?></strong></p>
            <?php if ($isApartment && ($unit['administration_fee'] ?? null) !== null && (float) $unit['administration_fee'] > 0): ?>
                <p>Administración: $<?= $escape(number_format((float) $unit['administration_fee'], 0, ',', '.')) ?></p>
            <?php endif; ?>

            <div class="public-detail__facts">
                <div class="public-property-card__facts">
                    <?php if ($isApartment): ?>
                        <span><?= $escape($unit['bedrooms'] ?? 0) ?> habitaciones</span>
                        <span><?= $escape($formatCount($unit['bathrooms'] ?? 0)) ?> baños</span>
                        <?php if (($unit['area_m2'] ?? null) !== null): ?><span><?= $escape($unit['area_m2']) ?> m²</span><?php endif; ?>
                        <?php if (($unit['tower'] ?? null) !== null): ?><span>Torre <?= $escape($unit['tower']) ?></span><?php endif; ?>
                        <?php if (($unit['floor'] ?? null) !== null): ?><span>Piso <?= $escape($unit['floor']) ?></span><?php endif; ?>
                        <?php if ($linkedParkingCount > 0): ?>
                            <span><?= $escape($linkedParkingCount) ?> parqueadero<?= $linkedParkingCount === 1 ? '' : 's' ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (($unit['parking_level'] ?? null) !== null): ?><span>Nivel <?= $escape($unit['parking_level']) ?></span><?php endif; ?>
                        <?php if (($unit['dimensions'] ?? null) !== null): ?><span><?= $escape($unit['dimensions']) ?></span><?php endif; ?>
                        <?php if (($unit['vehicle_type'] ?? null) !== null): ?><span><?= $escape($unit['vehicle_type']) ?></span><?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="surface">
            <h2>Descripción</h2>
            <p><?= $escape($unit['description'] ?? '') ?></p>
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

        <div class="button-row">
            <a class="button" href="<?= $escape($basePath) ?>/apply/<?= $escape($publicKey) ?>">Aplicar</a>
            <a class="button button--secondary" href="<?= $escape($basePath) ?>/catalog">Volver al catálogo</a>
            <a class="text-link" href="<?= $escape($basePath) ?>/">Volver al inicio</a>
        </div>
    </div>
</article>