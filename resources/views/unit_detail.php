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
$propertyUnits = is_array($propertyUnits ?? null) ? $propertyUnits : [];
$applicationRequirements = is_array($applicationRequirements ?? null) ? array_values($applicationRequirements) : [];
$canManageApplicationRequirements = (bool) ($canManageApplicationRequirements ?? false);
$canViewPropertyDetails = (bool) ($canViewPropertyDetails ?? $canManage ?? false);
$mediaIds = array_map(static fn (array $item): int => (int) $item['id'], $media);
$requirementIds = array_map(static fn (array $item): int => (int) $item['id'], $applicationRequirements);
?>
<section class="page-stack" aria-labelledby="unit-title">
    <?php if ($canViewPropertyDetails): ?>
        <a class="text-link" href="<?= $escape($basePath) ?>/admin/properties/<?= (int) $property['id'] ?>">← <?= $escape($property['name']) ?></a>
    <?php endif; ?>

    <header class="page-heading">
        <div>
            <p class="eyebrow"><?= $unit['type'] === 'apartment' ? 'Apartamento' : 'Parqueadero' ?></p>
            <h1 id="unit-title"><?= $escape($unit['code']) ?></h1>
            <p><?= $escape($unit['location_label'] ?? $property['name']) ?></p>
        </div>
        <span class="status-pill"><?= $escape($statusLabels[$unit['status']] ?? $unit['status']) ?></span>
    </header>

    <?php if (!empty($error)): ?>
        <div class="form-error" role="alert"><?= $escape($error) ?></div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <article class="content-card content-card--stacked">
            <h2>Editar unidad</h2>
            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <p><strong>Tipo:</strong> <?= $unit['type'] === 'apartment' ? 'Apartamento' : 'Parqueadero' ?></p>
                <input type="hidden" name="type" value="<?= $escape($unit['type']) ?>">
                <label>Código<input name="code" required maxlength="80" value="<?= $escape($unit['code']) ?>"></label>
                <label>Ubicación interna<input name="location_label" maxlength="190" value="<?= $escape($unit['location_label'] ?? '') ?>"></label>
                <label>Estado
                    <select name="status" required>
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <option value="<?= $escape($value) ?>"<?= $unit['status'] === $value ? ' selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Precio mensual<input type="number" name="monthly_price" min="0" step="0.01" value="<?= $escape($unit['monthly_price'] ?? '') ?>"></label>
                <label>Descripción<textarea name="description" rows="3"><?= $escape($unit['description'] ?? '') ?></textarea></label>
                <label>Requisitos<textarea name="requirements" rows="3"><?= $escape($unit['requirements'] ?? '') ?></textarea></label>
                <label>Notas privadas<textarea name="private_notes" rows="3"><?= $escape($unit['private_notes'] ?? '') ?></textarea></label>

                <?php if ($unit['type'] === 'apartment'): ?>
                    <label>Número apartamento<input name="unit_number" maxlength="50" value="<?= $escape($unit['unit_number'] ?? '') ?>"></label>
                    <label>Torre<input name="tower" maxlength="80" value="<?= $escape($unit['tower'] ?? '') ?>"></label>
                    <label>Piso<input name="floor" maxlength="50" value="<?= $escape($unit['floor'] ?? '') ?>"></label>
                    <label>Área m²<input type="number" name="area_m2" min="0" step="0.01" value="<?= $escape($unit['area_m2'] ?? '') ?>"></label>
                    <label>Habitaciones<input type="number" name="bedrooms" min="0" step="1" value="<?= $escape($unit['bedrooms'] ?? '') ?>"></label>
                    <label>Baños<input type="number" name="bathrooms" min="0" step="0.5" value="<?= $escape($unit['bathrooms'] ?? '') ?>"></label>
                    <label>Administración mensual<input type="number" name="administration_fee" min="0" step="0.01" value="<?= $escape($unit['administration_fee'] ?? '') ?>"></label>
                    <label>Matrícula<input name="registry_number" maxlength="120" value="<?= $escape($unit['registry_number'] ?? '') ?>"></label>
                <?php else: ?>
                    <label>Nivel parqueadero<input name="parking_level" maxlength="80" value="<?= $escape($unit['parking_level'] ?? '') ?>"></label>
                    <label>Dimensiones parqueadero<input name="dimensions" maxlength="120" value="<?= $escape($unit['dimensions'] ?? '') ?>"></label>
                    <label>Tipo de vehículo<input name="vehicle_type" maxlength="80" value="<?= $escape($unit['vehicle_type'] ?? '') ?>"></label>
                    <label>Apartamento relacionado
                        <select name="linked_apartment_id">
                            <option value="">Sin relación</option>
                            <?php foreach ($propertyUnits as $candidate): ?>
                                <?php if ($candidate['type'] === 'apartment' && $candidate['id'] !== $unit['id']): ?>
                                    <option value="<?= (int) $candidate['id'] ?>"<?= $unit['linked_apartment_id'] === $candidate['id'] ? ' selected' : '' ?>><?= $escape($candidate['code']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>

                <input type="hidden" name="is_public" value="0">
                <label class="check-row"><input type="checkbox" name="is_public" value="1"<?= $unit['is_public'] ? ' checked' : '' ?>> Publicar en catálogo</label>
                <p class="muted">Para activar la publicación debe tener precio, descripción, requisitos y al menos una foto.</p>
                <button class="button" type="submit">Guardar unidad</button>
            </form>
        </article>

        <article class="content-card content-card--stacked">
            <h2>Agregar multimedia</h2>
            <form class="stack-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/media/photo">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Foto JPEG, PNG o WebP<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
                <button class="button" type="submit">Subir foto</button>
            </form>
            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/media/video">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Enlace HTTPS de video<input type="url" name="url" required maxlength="1000" placeholder="https://..."></label>
                <button class="button button--quiet" type="submit">Agregar video</button>
            </form>
        </article>
    <?php elseif ($canViewPropertyDetails): ?>
        <article class="content-card content-card--stacked">
            <h2>Información</h2>
            <dl class="detail-list">
                <div><dt>Publicación</dt><dd><?= $unit['is_public'] ? 'Publicada' : 'Privada' ?></dd></div>
                <?php if ($unit['monthly_price'] !== null): ?><div><dt>Precio mensual</dt><dd>$<?= number_format((float) $unit['monthly_price'], 0, ',', '.') ?></dd></div><?php endif; ?>
                <?php if ($unit['description'] !== null): ?><div><dt>Descripción</dt><dd><?= nl2br($escape($unit['description'])) ?></dd></div><?php endif; ?>
                <?php if ($unit['requirements'] !== null): ?><div><dt>Requisitos</dt><dd><?= nl2br($escape($unit['requirements'])) ?></dd></div><?php endif; ?>
                <?php if ($unit['private_notes'] !== null): ?><div><dt>Notas privadas</dt><dd><?= nl2br($escape($unit['private_notes'])) ?></dd></div><?php endif; ?>
            </dl>
        </article>
    <?php endif; ?>

    <?php if ($canManageApplicationRequirements): ?>
        <section aria-labelledby="requirements-title">
            <div class="section-heading">
                <h2 id="requirements-title">Requisitos documentales</h2>
                <span><?= count($applicationRequirements) ?></span>
            </div>

            <article class="content-card content-card--stacked">
                <h3>Agregar requisito</h3>
                <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/application-requirements">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <label>Código<input name="code" required maxlength="80" pattern="[A-Za-z0-9_-]+"></label>
                    <label>Nombre<input name="label" required maxlength="190"></label>
                    <label>Descripción<textarea name="description" rows="2"></textarea></label>
                    <input type="hidden" name="is_required" value="0">
                    <label class="check-row"><input type="checkbox" name="is_required" value="1" checked> Obligatorio</label>
                    <input type="hidden" name="is_active" value="0">
                    <label class="check-row"><input type="checkbox" name="is_active" value="1" checked> Activo</label>
                    <button class="button" type="submit">Agregar requisito</button>
                </form>
            </article>

            <?php if ($applicationRequirements === []): ?>
                <p class="empty-state">Esta unidad aún no tiene requisitos documentales configurados.</p>
            <?php else: ?>
                <div class="card-list">
                    <?php foreach ($applicationRequirements as $index => $requirement): ?>
                        <article class="content-card content-card--stacked">
                            <div>
                                <h3><?= $escape($requirement['label']) ?></h3>
                                <p class="muted">Código: <?= $escape($requirement['code']) ?> · <?= $requirement['is_required'] ? 'Obligatorio' : 'Opcional' ?> · <?= $requirement['is_active'] ? 'Activo' : 'Inactivo' ?></p>
                                <?php if ($requirement['description'] !== null): ?><p><?= nl2br($escape($requirement['description'])) ?></p><?php endif; ?>
                            </div>

                            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/application-requirements/<?= (int) $requirement['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <label>Nombre<input name="label" required maxlength="190" value="<?= $escape($requirement['label']) ?>"></label>
                                <label>Descripción<textarea name="description" rows="2"><?= $escape($requirement['description'] ?? '') ?></textarea></label>
                                <input type="hidden" name="is_required" value="0">
                                <label class="check-row"><input type="checkbox" name="is_required" value="1"<?= $requirement['is_required'] ? ' checked' : '' ?>> Obligatorio</label>
                                <input type="hidden" name="is_active" value="0">
                                <label class="check-row"><input type="checkbox" name="is_active" value="1"<?= $requirement['is_active'] ? ' checked' : '' ?>> Activo</label>
                                <button class="button button--quiet" type="submit">Guardar requisito</button>
                            </form>

                            <div class="button-row">
                                <?php if ($index > 0): ?>
                                    <?php $up = $requirementIds; [$up[$index - 1], $up[$index]] = [$up[$index], $up[$index - 1]]; ?>
                                    <form method="post" action="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/application-requirements/order">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <?php foreach ($up as $orderedId): ?><input type="hidden" name="requirement_ids[]" value="<?= (int) $orderedId ?>"><?php endforeach; ?>
                                        <button class="button button--quiet" type="submit" aria-label="Mover requisito arriba">↑</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($index < count($applicationRequirements) - 1): ?>
                                    <?php $down = $requirementIds; [$down[$index], $down[$index + 1]] = [$down[$index + 1], $down[$index]]; ?>
                                    <form method="post" action="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/application-requirements/order">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <?php foreach ($down as $orderedId): ?><input type="hidden" name="requirement_ids[]" value="<?= (int) $orderedId ?>"><?php endforeach; ?>
                                        <button class="button button--quiet" type="submit" aria-label="Mover requisito abajo">↓</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($canViewPropertyDetails): ?>
        <section aria-labelledby="media-title">
            <div class="section-heading">
                <h2 id="media-title">Multimedia</h2>
                <span><?= count($media) ?></span>
            </div>

            <?php if ($media === []): ?>
                <p class="empty-state">Esta unidad aún no tiene fotos o videos.</p>
            <?php else: ?>
                <div class="card-list">
                    <?php foreach ($media as $index => $item): ?>
                        <article class="content-card content-card--stacked">
                            <div>
                                <h3><?= $item['type'] === 'photo' ? 'Foto' : 'Video' ?></h3>
                                <p class="muted"><?= $item['is_cover'] ? 'Portada · ' : '' ?>Orden <?= (int) $item['sort_order'] ?></p>
                            </div>
                            <div class="button-row">
                                <?php if ($item['type'] === 'photo'): ?>
                                    <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/media/<?= (int) $item['id'] ?>">Ver foto</a>
                                <?php elseif ($item['external_url'] !== null): ?>
                                    <a class="button button--quiet" href="<?= $escape($item['external_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir video</a>
                                <?php endif; ?>

                                <?php if ($canManage && $item['type'] === 'photo' && !$item['is_cover']): ?>
                                    <form method="post" action="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/media/<?= (int) $item['id'] ?>/cover">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <button class="button button--quiet" type="submit">Usar de portada</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($canManage && $index > 0): ?>
                                    <?php $up = $mediaIds; [$up[$index - 1], $up[$index]] = [$up[$index], $up[$index - 1]]; ?>
                                    <form method="post" action="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/media/order">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <?php foreach ($up as $orderedId): ?><input type="hidden" name="media_ids[]" value="<?= (int) $orderedId ?>"><?php endforeach; ?>
                                        <button class="button button--quiet" type="submit" aria-label="Mover arriba">↑</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($canManage && $index < count($media) - 1): ?>
                                    <?php $down = $mediaIds; [$down[$index], $down[$index + 1]] = [$down[$index + 1], $down[$index]]; ?>
                                    <form method="post" action="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/media/order">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <?php foreach ($down as $orderedId): ?><input type="hidden" name="media_ids[]" value="<?= (int) $orderedId ?>"><?php endforeach; ?>
                                        <button class="button button--quiet" type="submit" aria-label="Mover abajo">↓</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($canManage): ?>
                                    <form method="post" action="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>/media/<?= (int) $item['id'] ?>/delete">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <button class="button button--quiet" type="submit">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
