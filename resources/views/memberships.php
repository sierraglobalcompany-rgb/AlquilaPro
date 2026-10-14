<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$services = $services ?? [];
$pendingMemberships = $pendingMemberships ?? [];
$history = $history ?? [];
$today = (string) ($today ?? gmdate('Y-m-d'));
$canManage = (bool) ($canManage ?? false);
?>
<section class="surface">
    <p class="eyebrow">Apps</p>
    <h1>Servicios digitales</h1>
    <p class="hint">Administra el catálogo y las decisiones de cargo de los servicios aceptados por los inquilinos.</p>
</section>

<?php if ($canManage): ?>
    <section class="surface">
        <h2>Crear servicio</h2>
        <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/memberships/services">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <label>Nombre<input name="name" maxlength="120" required></label>
            <label>Descripción<textarea name="description" maxlength="1000" required></textarea></label>
            <label>Precio<input name="current_price" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" required></label>
            <label>Duración en días<input name="duration_days" type="number" min="1" step="1" required></label>
            <button class="button" type="submit">Crear servicio</button>
        </form>
    </section>
<?php endif; ?>

<section class="surface">
    <h2>Catálogo</h2>
    <?php if ($services === []): ?>
        <p class="empty-state">Aún no hay servicios digitales.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($services as $service): ?>
                <article class="user-card">
                    <?php if ($canManage): ?>
                        <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/memberships/services/<?= $escape($service['id']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <label>Nombre<input name="name" maxlength="120" value="<?= $escape($service['name']) ?>" required></label>
                            <label>Descripción<textarea name="description" maxlength="1000" required><?= $escape($service['description']) ?></textarea></label>
                            <label>Precio<input name="current_price" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" value="<?= $escape($service['current_price']) ?>" required></label>
                            <label>Duración en días<input name="duration_days" type="number" min="1" step="1" value="<?= $escape($service['duration_days']) ?>" required></label>
                            <label><input type="checkbox" name="active" value="1"<?= $service['active'] ? ' checked' : '' ?>> Disponible para nuevos contratos</label>
                            <button class="button button--quiet" type="submit">Guardar servicio</button>
                        </form>
                    <?php else: ?>
                        <strong><?= $escape($service['name']) ?></strong>
                        <p><?= nl2br($escape($service['description'])) ?></p>
                        <div><?= $escape($service['current_price']) ?> · <?= $escape($service['duration_days']) ?> días</div>
                        <span class="status-pill"><?= $service['active'] ? 'Activo' : 'Inactivo' ?></span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="surface">
    <h2>Decisiones de cargo pendientes</h2>
    <?php if ($pendingMemberships === []): ?>
        <p class="empty-state">No hay decisiones pendientes.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($pendingMemberships as $membership): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($membership['service_name'] ?? ('Servicio #' . $membership['service_id'])) ?></strong>
                        <span class="status-pill">Pendiente de decisión</span>
                    </div>
                    <div>Arrendamiento #<?= $escape($membership['lease_id']) ?></div>
                    <div>Precio aceptado: <?= $escape($membership['price_snapshot']) ?></div>
                    <div>Vigencia: <?= $escape($membership['starts_on']) ?> a <?= $escape($membership['ends_on']) ?></div>
                    <?php if ($canManage): ?>
                        <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/memberships/<?= $escape($membership['id']) ?>/decision">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="decision" value="create">
                            <label>Vencimiento del cargo<input name="due_date" type="date" value="<?= $escape($today) ?>" required></label>
                            <label>Descripción del cargo<input name="description" maxlength="500" value="Servicio digital: <?= $escape($membership['service_name'] ?? 'Servicio') ?>" required></label>
                            <button class="button" type="submit">Crear cargo</button>
                        </form>
                        <form method="post" action="<?= $escape($basePath) ?>/admin/memberships/<?= $escape($membership['id']) ?>/decision">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="decision" value="skip">
                            <button class="button button--quiet" type="submit">No crear cargo</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="surface">
    <h2>Historial reciente</h2>
    <?php if ($history === []): ?>
        <p class="empty-state">Aún no hay membresías registradas.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($history as $membership): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($membership['service_name'] ?? ('Servicio #' . $membership['service_id'])) ?></strong>
                        <span class="status-pill"><?= $escape($membership['charge_outcome']) ?></span>
                    </div>
                    <div>Arrendamiento #<?= $escape($membership['lease_id']) ?></div>
                    <div>Precio: <?= $escape($membership['price_snapshot']) ?></div>
                    <div>Vigencia: <?= $escape($membership['starts_on']) ?> a <?= $escape($membership['ends_on']) ?></div>
                    <div>Aceptado: <?= $escape($membership['accepted_at']) ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
