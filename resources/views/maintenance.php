<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$requests = $requests ?? [];
$visits = $visits ?? [];
$staff = $staff ?? [];
$selectedRequest = $selectedRequest ?? null;
$canManage = $canManage ?? false;
$statusLabels = [
    'open' => 'Abierta',
    'in_progress' => 'En proceso',
    'resolved' => 'Resuelta',
    'cancelled' => 'Cancelada',
];
$priorityLabels = [
    'low' => 'Baja',
    'medium' => 'Media',
    'high' => 'Alta',
    'urgent' => 'Urgente',
];
$damageLabels = [
    'normal_wear' => 'Desgaste normal',
    'tenant_damage' => 'Daño atribuible al inquilino',
    'owner_responsibility' => 'Responsabilidad del propietario',
    'third_party' => 'Tercero',
    'not_applicable' => 'No aplica',
];
$visitStatusLabels = [
    'proposed' => 'Propuesta',
    'scheduled' => 'Programada',
    'completed' => 'Completada',
    'cancelled' => 'Cancelada',
];
$activeRequest = is_array($selectedRequest)
    && !in_array($selectedRequest['status'], ['resolved', 'cancelled'], true);
?>
<section class="surface">
    <p class="eyebrow">Operación</p>
    <h1><?= $escape($pageTitle ?? 'Mantenimiento') ?></h1>
    <p class="hint">Solicitudes de mantenimiento de los arrendamientos.</p>
    <p><a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/maintenance">Ver todas las solicitudes</a></p>
</section>

<?php if ($canManage && $selectedRequest === null): ?>
    <section class="surface">
        <h2>Crear solicitud</h2>
        <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/maintenance">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <label>Arrendamiento ID<input name="lease_id" inputmode="numeric" pattern="[1-9][0-9]*" required></label>
            <label>Categoría<input name="category" maxlength="80" required></label>
            <label>Prioridad
                <select name="priority">
                    <?php foreach ($priorityLabels as $value => $label): ?>
                        <option value="<?= $escape($value) ?>"<?= $value === 'medium' ? ' selected' : '' ?>><?= $escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Descripción<textarea name="description" maxlength="5000" required></textarea></label>
            <button class="button" type="submit">Crear solicitud</button>
        </form>
    </section>
<?php endif; ?>

<?php if (is_array($selectedRequest)): ?>
    <?php $record = $selectedRequest; ?>
    <section class="surface">
        <h2>Solicitud #<?= $escape($record['id']) ?></h2>
        <div class="users-grid">
            <article class="user-card">
                <div class="user-card__head">
                    <strong><?= $escape($record['category']) ?></strong>
                    <span class="status-pill"><?= $escape($statusLabels[$record['status']] ?? $record['status']) ?></span>
                </div>
                <div>Arrendamiento: #<?= $escape($record['lease_id']) ?></div>
                <div>Prioridad: <?= $escape($priorityLabels[$record['priority']] ?? $record['priority']) ?></div>
                <p><?= nl2br($escape($record['description'])) ?></p>
                <?php if ($record['damage_classification'] !== null): ?>
                    <div>Clasificación: <?= $escape($damageLabels[$record['damage_classification']] ?? $record['damage_classification']) ?></div>
                <?php endif; ?>
                <?php if ($record['resolution_notes'] !== null): ?><div>Resolución: <?= $escape($record['resolution_notes']) ?></div><?php endif; ?>
                <?php if ($record['charge_id'] !== null): ?><div>Cargo de reparación: #<?= $escape($record['charge_id']) ?></div><?php endif; ?>
            </article>
        </div>
        <?php if ($record['photos'] !== []): ?>
            <h3>Fotos</h3>
            <div class="users-grid">
                <?php foreach ($record['photos'] as $photo): ?>
                    <?php if (is_array($photo) && preg_match('/\A[a-f0-9]{32}\z/', (string) ($photo['photo_id'] ?? '')) === 1): ?>
                        <article class="user-card">
                            <a href="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/photos/<?= $escape($photo['photo_id']) ?>">
                                <img src="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/photos/<?= $escape($photo['photo_id']) ?>" alt="Foto de mantenimiento" loading="lazy">
                            </a>
                            <div><?= $escape($photo['created_at'] ?? '') ?></div>
                        </article>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

<?php if ($visits !== [] && (!$canManage || !$activeRequest)): ?>
    <section class="surface">
        <h2>Visitas</h2>
        <div class="users-grid">
            <?php foreach ($visits as $visit): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong>Visita #<?= $escape($visit['id']) ?></strong>
                        <span class="status-pill"><?= $escape($visitStatusLabels[$visit['status']] ?? $visit['status']) ?></span>
                    </div>
                    <?php if ($visit['selected_start_at'] !== null): ?><p>Fecha: <?= $escape($visit['selected_start_at']) ?> · <?= $escape($visit['duration_minutes']) ?> min</p><?php endif; ?>
                    <?php if ($visit['technician_name'] !== null): ?><p>Técnico: <?= $escape($visit['technician_name']) ?></p><?php endif; ?>
                    <?php if ($visit['technician_contact'] !== null): ?><p>Contacto: <?= $escape($visit['technician_contact']) ?></p><?php endif; ?>
                    <?php if ($visit['result_notes'] !== null): ?><p>Resultado: <?= nl2br($escape($visit['result_notes'])) ?></p><?php endif; ?>
                    <?php if ($visit['cancel_reason'] !== null): ?><p>Motivo de cancelación: <?= $escape($visit['cancel_reason']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($canManage && $activeRequest): ?>
        <section class="surface">
            <h2>Gestionar solicitud</h2>
            <h3>Agenda de visitas</h3>
            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/visits">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="operation" value="propose">
                <label>Opciones de visita · una por línea: fecha y hora UTC|duración en minutos
                    <textarea name="slots" maxlength="4096" rows="3" placeholder="2026-10-20 14:00:00|60" required></textarea>
                </label>
                <label>Técnico<input name="technician_name" maxlength="120"></label>
                <label>Contacto del técnico<input name="technician_contact" maxlength="200"></label>
                <button class="button button--quiet" type="submit">Proponer horarios</button>
            </form>
            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/visits">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="operation" value="direct">
                <label>Fecha y hora UTC<input name="start_at" placeholder="2026-10-20 14:00:00" required></label>
                <label>Duración en minutos<input type="number" name="duration_minutes" min="1" max="65535" required></label>
                <label>Técnico<input name="technician_name" maxlength="120"></label>
                <label>Contacto del técnico<input name="technician_contact" maxlength="200"></label>
                <button class="button button--quiet" type="submit">Programar directamente</button>
            </form>
            <?php if ($visits === []): ?>
                <p class="empty-state">Todavía no hay visitas para esta solicitud.</p>
            <?php else: ?>
                <div class="users-grid">
                    <?php foreach ($visits as $visit): ?>
                        <article class="user-card">
                            <div class="user-card__head">
                                <strong>Visita #<?= $escape($visit['id']) ?></strong>
                                <span class="status-pill"><?= $escape($visitStatusLabels[$visit['status']] ?? $visit['status']) ?></span>
                            </div>
                            <?php if ($visit['selected_start_at'] !== null): ?><p>Fecha: <?= $escape($visit['selected_start_at']) ?> · <?= $escape($visit['duration_minutes']) ?> min</p><?php endif; ?>
                            <?php if ($visit['technician_name'] !== null): ?><p>Técnico: <?= $escape($visit['technician_name']) ?></p><?php endif; ?>
                            <?php if ($visit['technician_contact'] !== null): ?><p>Contacto: <?= $escape($visit['technician_contact']) ?></p><?php endif; ?>
                            <?php if ($visit['result_notes'] !== null): ?><p>Resultado: <?= nl2br($escape($visit['result_notes'])) ?></p><?php endif; ?>
                            <?php if ($visit['cancel_reason'] !== null): ?><p>Motivo de cancelación: <?= $escape($visit['cancel_reason']) ?></p><?php endif; ?>
                            <?php if (in_array($visit['status'], ['proposed', 'scheduled'], true)): ?>
                                <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/visits/<?= $escape($visit['id']) ?>/reprogram">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <label>Reprogramar · opciones fecha y hora UTC|duración
                                        <textarea name="slots" maxlength="4096" rows="2" required></textarea>
                                    </label>
                                    <button class="button button--quiet" type="submit">Reprogramar</button>
                                </form>
                                <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/visits/<?= $escape($visit['id']) ?>/complete">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <label>Resultado<textarea name="result_notes" maxlength="1000"></textarea></label>
                                    <button class="button button--quiet" type="submit">Completar visita</button>
                                </form>
                                <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/visits/<?= $escape($visit['id']) ?>/cancel">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <label>Motivo de cancelación<input name="reason" maxlength="500" required></label>
                                    <button class="button button--quiet" type="submit">Cancelar visita</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/assignment">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Responsable
                    <select name="assigned_user_id">
                        <option value="">Sin asignar</option>
                        <?php foreach ($staff as $member): ?>
                            <option value="<?= $escape($member['id']) ?>"<?= $record['assigned_user_id'] === $member['id'] ? ' selected' : '' ?>><?= $escape($member['full_name']) ?> · <?= $escape($member['role']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button button--quiet" type="submit">Guardar asignación</button>
            </form>

            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/classification">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Clasificación del daño
                    <select name="damage_classification" required>
                        <?php foreach ($damageLabels as $value => $label): ?>
                            <option value="<?= $escape($value) ?>"<?= $record['damage_classification'] === $value ? ' selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button button--quiet" type="submit">Guardar clasificación</button>
            </form>

            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/status">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Nuevo estado
                    <select name="status" required>
                        <?php if ($record['status'] === 'open'): ?><option value="in_progress">En proceso</option><?php endif; ?>
                        <option value="resolved">Resuelta</option>
                        <option value="cancelled">Cancelada</option>
                    </select>
                </label>
                <label>Notas de resolución<textarea name="resolution_notes" maxlength="1000"></textarea></label>
                <label>Motivo de cancelación<input name="cancel_reason" maxlength="500"></label>
                <button class="button button--quiet" type="submit">Actualizar estado</button>
            </form>

            <form class="stack-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/photos">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Agregar foto<input type="file" name="photo" accept="image/png,image/jpeg,image/webp" required></label>
                <button class="button button--quiet" type="submit">Guardar foto</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($canManage && $record['status'] !== 'cancelled' && $record['damage_classification'] === 'tenant_damage' && $record['charge_id'] === null): ?>
        <section class="surface">
            <h2>Registrar cargo de reparación</h2>
            <p class="hint">El cargo se crea solo al enviar este formulario.</p>
            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>/repair-charge">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Valor<input name="amount" inputmode="decimal" required></label>
                <label>Fecha de vencimiento<input type="date" name="due_date" required></label>
                <label>Descripción<input name="description" maxlength="500" required></label>
                <button class="button" type="submit">Crear cargo de reparación</button>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php if (!is_array($selectedRequest)): ?>
    <section class="surface">
        <h2>Solicitudes</h2>
        <?php if ($requests === []): ?>
            <p class="empty-state">No hay solicitudes para mostrar.</p>
        <?php else: ?>
            <div class="users-grid">
                <?php foreach ($requests as $record): ?>
                    <article class="user-card">
                        <div class="user-card__head">
                            <strong><?= $escape($record['category']) ?> · #<?= $escape($record['id']) ?></strong>
                            <span class="status-pill"><?= $escape($statusLabels[$record['status']] ?? $record['status']) ?></span>
                        </div>
                        <div>Arrendamiento #<?= $escape($record['lease_id']) ?> · <?= $escape($priorityLabels[$record['priority']] ?? $record['priority']) ?></div>
                        <div><?= $escape($record['created_at']) ?></div>
                        <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/maintenance/<?= $escape($record['id']) ?>">Ver detalle</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
