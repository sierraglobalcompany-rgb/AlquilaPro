<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$incidents = is_array($incidents ?? null) ? $incidents : [];
$legalNotices = is_array($legalNotices ?? null) ? $legalNotices : [];
$statusLabels = ['open' => 'Abierto', 'reviewed' => 'En revisión', 'closed' => 'Cerrado', 'voided' => 'Anulado'];
$propertyId = is_int($propertyId ?? null) ? $propertyId : null;
?>
<section class="surface">
    <p class="eyebrow">Administración</p>
    <h1>Convivencia y avisos legales</h1>
    <form class="stack-form" method="get" action="<?= $escape($basePath) ?>/admin/community">
        <label>Filtrar estado
            <select name="status">
                <option value="">Todos</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= $escape($value) ?>" <?= ($statusFilter ?? null) === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>ID de propiedad para avisos legales<input type="number" name="property_id" min="1" value="<?= $escape($propertyId ?? '') ?>"></label>
        <button class="button button--quiet" type="submit">Consultar</button>
    </form>
</section>

<?php if ($canManage): ?>
    <section class="surface">
        <h2>Registrar incidente</h2>
        <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/community/incidents">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <label>ID de arrendamiento<input type="number" name="lease_id" min="1" required></label>
            <label>Categoría<input name="category" maxlength="80" required></label>
            <label>Fecha del incidente<input type="date" name="occurred_on" required></label>
            <label>Descripción<textarea name="description" maxlength="5000" required></textarea></label>
            <button class="button" type="submit">Crear incidente</button>
        </form>
    </section>
<?php endif; ?>

<section class="surface">
    <h2>Incidentes</h2>
    <?php if ($incidents === []): ?>
        <p class="empty-state">No hay incidentes para el filtro seleccionado.</p>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($incidents as $incident): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($incident['category']) ?> · #<?= $escape($incident['id']) ?></strong>
                        <span class="status-pill"><?= $escape($statusLabels[$incident['status']] ?? $incident['status']) ?></span>
                    </div>
                    <div>Arrendamiento #<?= $escape($incident['lease_id']) ?> · Fecha: <?= $escape($incident['occurred_on']) ?></div>
                    <p><?= nl2br($escape($incident['description'])) ?></p>
                    <?php if ($incident['resolution_notes'] !== null): ?>
                        <div>Resolución: <?= nl2br($escape($incident['resolution_notes'])) ?></div>
                    <?php endif; ?>
                    <?php if ($incident['void_reason'] !== null): ?>
                        <div>Motivo de anulación: <?= nl2br($escape($incident['void_reason'])) ?></div>
                    <?php endif; ?>
                    <?php foreach ($incident['evidence'] as $evidence): ?>
                        <div class="visit-summary">
                            <strong><?= $escape($evidence['title']) ?></strong>
                            <?php foreach ($evidence['versions'] as $version): ?>
                                <div>Versión #<?= $escape($version['id']) ?> · <?= $escape($version['created_at']) ?>
                                    <?php if (is_string($version['file_url'])): ?>
                                        · <a href="<?= $escape($version['file_url']) ?>">Descargar evidencia</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($canManage): ?>
                        <?php if ($incident['status'] === 'open'): ?>
                            <form method="post" action="<?= $escape($basePath) ?>/admin/community/incidents/<?= $escape($incident['id']) ?>/review">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <button class="button button--quiet" type="submit">Marcar en revisión</button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array($incident['status'], ['open', 'reviewed'], true)): ?>
                            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/community/incidents/<?= $escape($incident['id']) ?>/close">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <label>Resolución<textarea name="resolution_notes" maxlength="5000" required></textarea></label>
                                <button class="button button--quiet" type="submit">Cerrar incidente</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($incident['status'] !== 'voided'): ?>
                            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/community/incidents/<?= $escape($incident['id']) ?>/void">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <label>Motivo de anulación<textarea name="void_reason" maxlength="1000" required></textarea></label>
                                <button class="button button--quiet" type="submit">Anular incidente</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($incident['status'] !== 'voided'): ?>
                            <form class="stack-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/community/incidents/<?= $escape($incident['id']) ?>/evidence">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <label>Título de evidencia<input name="title" maxlength="190" required></label>
                                <label>Archivo privado (PNG, JPEG, WebP o PDF; máximo 10 MiB)<input type="file" name="evidence" accept="application/pdf,image/png,image/jpeg,image/webp" required></label>
                                <label>Versión anterior
                                    <select name="supersedes_document_id">
                                        <option value="">Evidencia nueva</option>
                                        <?php foreach ($incident['evidence'] as $currentEvidence): ?>
                                            <option value="<?= $escape($currentEvidence['id']) ?>"><?= $escape($currentEvidence['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button class="button button--quiet" type="submit">Guardar evidencia</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="surface">
    <h2>Avisos legales de la propiedad</h2>
    <?php if ($propertyId === null): ?>
        <p class="empty-state">Indica el ID de una propiedad para consultar o versionar sus avisos legales.</p>
    <?php else: ?>
        <?php if ($legalNotices === []): ?>
            <p class="empty-state">No hay avisos legales vigentes para esta propiedad.</p>
        <?php else: ?>
            <div class="users-grid">
                <?php foreach ($legalNotices as $notice): ?>
                    <article class="user-card">
                        <div class="user-card__head">
                            <strong><?= $escape($notice['title']) ?></strong>
                            <span class="status-pill">Versión #<?= $escape($notice['id']) ?></span>
                        </div>
                        <div><?= $escape($notice['content_json']['source_label']) ?> · <?= $escape($notice['created_at']) ?></div>
                        <?php if (is_string($notice['content_json']['effective_on'])): ?>
                            <div>Vigente desde: <?= $escape($notice['content_json']['effective_on']) ?></div>
                        <?php endif; ?>
                        <p><?= nl2br($escape($notice['content_json']['notice_text'])) ?></p>
                        <?php if (is_string($notice['content_json']['source_url'])): ?>
                            <a href="<?= $escape($notice['content_json']['source_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir fuente</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/community/legal-notices">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="property_id" value="<?= $escape($propertyId) ?>">
                <label>Título<input name="title" maxlength="190" required></label>
                <label>Fuente<input name="source_label" maxlength="190" required></label>
                <label>URL de fuente<input type="url" name="source_url" maxlength="2048"></label>
                <label>Vigente desde<input type="date" name="effective_on"></label>
                <label>Texto del aviso<textarea name="notice_text" maxlength="10000" required></textarea></label>
                <label>Versión anterior
                    <select name="supersedes_document_id">
                        <option value="">Aviso nuevo</option>
                        <?php foreach ($legalNotices as $notice): ?>
                            <option value="<?= $escape($notice['id']) ?>"><?= $escape($notice['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button" type="submit">Guardar aviso legal</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
