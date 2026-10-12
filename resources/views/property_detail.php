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

    <?php if (!empty($error)): ?>
        <div class="form-error" role="alert"><?= $escape($error) ?></div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <article class="content-card content-card--stacked">
            <h2>Editar propiedad</h2>
            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/properties/<?= (int) $property['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Nombre<input name="name" required maxlength="150" value="<?= $escape($property['name']) ?>"></label>
                <label>Dirección<input name="address" required maxlength="255" value="<?= $escape($property['address']) ?>"></label>
                <label>Ciudad<input name="city" required maxlength="120" value="<?= $escape($property['city']) ?>"></label>
                <label>Sector<input name="sector" maxlength="120" value="<?= $escape($property['sector'] ?? '') ?>"></label>
                <label>Administración<input name="administration_name" maxlength="150" value="<?= $escape($property['administration_name'] ?? '') ?>"></label>
                <label>Contacto de administración<input name="administration_contact" maxlength="190" value="<?= $escape($property['administration_contact'] ?? '') ?>"></label>
                <label>Notas<textarea name="notes" rows="3"><?= $escape($property['notes'] ?? '') ?></textarea></label>
                <button class="button" type="submit">Guardar cambios</button>
            </form>
            <form method="post" action="<?= $escape($basePath) ?>/admin/properties/<?= (int) $property['id'] ?>/status">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="active" value="<?= $property['is_active'] ? '0' : '1' ?>">
                <button class="button button--quiet" type="submit"><?= $property['is_active'] ? 'Marcar inactiva' : 'Reactivar propiedad' ?></button>
            </form>
        </article>
    <?php else: ?>
        <article class="content-card content-card--stacked">
            <h2>Información</h2>
            <dl class="detail-list">
                <div><dt>Dirección</dt><dd><?= $escape($property['address']) ?></dd></div>
                <?php if ($property['administration_name'] !== null): ?><div><dt>Administración</dt><dd><?= $escape($property['administration_name']) ?></dd></div><?php endif; ?>
                <?php if ($property['administration_contact'] !== null): ?><div><dt>Contacto</dt><dd><?= $escape($property['administration_contact']) ?></dd></div><?php endif; ?>
                <?php if ($property['notes'] !== null): ?><div><dt>Notas</dt><dd><?= nl2br($escape($property['notes'])) ?></dd></div><?php endif; ?>
            </dl>
        </article>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <article class="content-card content-card--stacked">
            <h2>Nueva unidad</h2>
            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/properties/<?= (int) $property['id'] ?>/units">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Tipo
                    <select name="type" required>
                        <option value="apartment">Apartamento</option>
                        <option value="parking">Parqueadero</option>
                    </select>
                </label>
                <label>Código<input name="code" required maxlength="80"></label>
                <label>Ubicación interna<input name="location_label" maxlength="190"></label>
                <label>Estado
                    <select name="status" required>
                        <option value="available">Disponible</option>
                        <option value="in_process">En proceso</option>
                        <option value="occupied">Ocupada</option>
                        <option value="inactive">Inactiva</option>
                        <option value="maintenance">Mantenimiento</option>
                    </select>
                </label>
                <label>Precio mensual<input type="number" name="monthly_price" min="0" step="0.01"></label>
                <label>Descripción<textarea name="description" rows="3"></textarea></label>
                <label>Requisitos<textarea name="requirements" rows="3"></textarea></label>
                <label>Notas privadas<textarea name="private_notes" rows="3"></textarea></label>
                <label>Número apartamento<input name="unit_number" maxlength="50"></label>
                <label>Torre<input name="tower" maxlength="80"></label>
                <label>Piso<input name="floor" maxlength="50"></label>
                <label>Área m²<input type="number" name="area_m2" min="0" step="0.01"></label>
                <label>Habitaciones<input type="number" name="bedrooms" min="0" step="1"></label>
                <label>Baños<input type="number" name="bathrooms" min="0" step="0.5"></label>
                <label>Administración mensual<input type="number" name="administration_fee" min="0" step="0.01"></label>
                <label>Matrícula<input name="registry_number" maxlength="120"></label>
                <label>Nivel parqueadero<input name="parking_level" maxlength="80"></label>
                <label>Dimensiones parqueadero<input name="dimensions" maxlength="120"></label>
                <label>Tipo de vehículo<input name="vehicle_type" maxlength="80"></label>
                <label>Apartamento relacionado
                    <select name="linked_apartment_id">
                        <option value="">Sin relación</option>
                        <?php foreach ($units as $candidate): ?>
                            <?php if ($candidate['type'] === 'apartment'): ?>
                                <option value="<?= (int) $candidate['id'] ?>"><?= $escape($candidate['code']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button" type="submit">Crear unidad</button>
            </form>
        </article>
    <?php endif; ?>

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
                            <?php if ($unit['monthly_price'] !== null): ?><p class="muted">$<?= number_format((float) $unit['monthly_price'], 0, ',', '.') ?></p><?php endif; ?>
                        </div>
                        <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/units/<?= (int) $unit['id'] ?>">Ver</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
