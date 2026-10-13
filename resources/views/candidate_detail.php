<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$allowedTransitions = match ((string) $candidate['status']) {
    'new' => ['incomplete', 'withdrawn'],
    'incomplete' => ['withdrawn'],
    'verification' => ['pending_screening', 'withdrawn'],
    'pending_screening' => ['withdrawn'],
    default => [],
};
$screening = is_array($screening ?? null) ? $screening : null;
$screeningStatus = (string) ($screening['status'] ?? '');
$screeningTerminal = in_array($screeningStatus, ['approved', 'rejected'], true);
$screeningEditable = $canManageCandidates
    && (string) $candidate['status'] === 'pending_screening'
    && !$screeningTerminal;
$screeningTransitions = match ($screeningStatus) {
    'pending' => ['pending', 'in_progress', 'approved', 'rejected'],
    'in_progress' => ['in_progress', 'approved', 'rejected'],
    default => [],
};
?>
<section class="surface">
    <p class="eyebrow"><a href="<?= $escape($basePath) ?>/admin/candidates">Candidatos</a></p>
    <h1><?= $escape($candidate['full_name']) ?></h1>
    <p class="hint">Expediente privado de candidatura.</p>
</section>

<section class="surface">
    <h2>Solicitud</h2>
    <dl>
        <dt>Estado</dt><dd><?= $escape($candidate['status']) ?></dd>
        <dt>Documento</dt><dd><?= $escape(strtoupper((string) $candidate['document_type'])) ?> <?= $escape($candidate['document_number']) ?></dd>
        <dt>Unidad</dt><dd><?= $escape($candidate['property_name'] ?? '') ?> · <?= $escape($candidate['unit_code'] ?? $candidate['unit_id']) ?></dd>
        <dt>Teléfono</dt><dd><?= $escape($candidate['phone']) ?></dd>
        <dt>Correo</dt><dd><?= $escape($candidate['email']) ?></dd>
        <dt>Fecha de nacimiento</dt><dd><?= $escape($candidate['birth_date'] ?? '') ?></dd>
        <dt>Dirección actual</dt><dd><?= $escape($candidate['current_address'] ?? '') ?></dd>
        <dt>Consentimiento</dt><dd><?= $escape($candidate['consent_at']) ?></dd>
        <dt>Creado</dt><dd><?= $escape($candidate['created_at']) ?></dd>
        <dt>Enviado</dt><dd><?= $escape($candidate['submitted_at'] ?? '') ?></dd>
        <dt>Actualizado</dt><dd><?= $escape($candidate['updated_at'] ?? '') ?></dd>
    </dl>
</section>

<section class="surface">
    <h2>Información laboral</h2>
    <dl>
        <dt>Estado laboral</dt><dd><?= $escape($candidate['employment_status'] ?? '') ?></dd>
        <dt>Empresa</dt><dd><?= $escape($candidate['employer_name'] ?? '') ?></dd>
        <dt>Ocupación</dt><dd><?= $escape($candidate['occupation'] ?? '') ?></dd>
        <dt>Ingreso mensual</dt><dd><?= $escape($candidate['monthly_income'] ?? '') ?></dd>
        <dt>Notas</dt><dd><?= $escape($candidate['employment_notes'] ?? '') ?></dd>
    </dl>
</section>

<section class="surface">
    <h2>Ocupantes y mascotas</h2>
    <dl>
        <dt>Ocupantes</dt><dd><?= $escape($candidate['occupants_count'] ?? '') ?></dd>
        <dt>Detalle de ocupantes</dt><dd><?= $escape($candidate['occupants_notes'] ?? '') ?></dd>
        <dt>Mascotas</dt><dd><?= $candidate['pets_declared'] ? 'Sí' : 'No' ?></dd>
        <dt>Detalle de mascotas</dt><dd><?= $escape($candidate['pets_notes'] ?? '') ?></dd>
    </dl>
</section>

<section class="surface">
    <h2>Referencias</h2>
    <?php if (($candidate['references'] ?? []) === []): ?>
        <p class="empty-state">No hay referencias registradas.</p>
    <?php else: ?>
        <?php foreach ($candidate['references'] as $reference): ?>
            <article class="user-card">
                <strong><?= $escape($reference['name']) ?></strong>
                <div><?= $escape($reference['reference_type'] ?? '') ?></div>
                <div><?= $escape($reference['relationship'] ?? '') ?></div>
                <div><?= $escape($reference['phone']) ?></div>
                <div><?= $escape($reference['email'] ?? '') ?></div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="surface">
    <h2>Documentos</h2>
    <?php if ($documents === []): ?>
        <p class="empty-state">No hay documentos adjuntos.</p>
    <?php else: ?>
        <?php foreach ($documents as $document): ?>
            <article class="user-card">
                <strong><?= $escape($document['requirement_label']) ?></strong>
                <div><?= $escape($document['original_name'] ?? 'Documento') ?></div>
                <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>/documents/<?= $escape($document['id']) ?>">Abrir documento</a>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="surface">
    <h2>Estudio de aseguradora</h2>
    <?php if ($screening === null): ?>
        <?php if ($screeningEditable): ?>
            <form method="post" action="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>/screening">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Proveedor
                    <input type="text" name="provider" value="SURA" maxlength="120" required>
                </label>
                <label>Producto
                    <input type="text" name="product" maxlength="190">
                </label>
                <label>Correo de contacto
                    <input type="email" name="contact_email" maxlength="190">
                </label>
                <label>URL del estudio
                    <input type="url" name="portal_url" maxlength="2048">
                </label>
                <label>Notas internas
                    <textarea name="notes"></textarea>
                </label>
                <label>Soporte / radicado
                    <input type="text" name="support_reference" maxlength="255">
                </label>
                <button type="submit">Crear estudio</button>
            </form>
        <?php else: ?>
            <p class="empty-state">No hay estudio de aseguradora registrado.</p>
        <?php endif; ?>
    <?php elseif ($screeningEditable): ?>
        <form method="post" action="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>/screening">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <label>Proveedor
                <input type="text" name="provider" value="<?= $escape($screening['provider']) ?>" maxlength="120" required>
            </label>
            <label>Producto
                <input type="text" name="product" value="<?= $escape($screening['product'] ?? '') ?>" maxlength="190">
            </label>
            <label>Estado
                <select name="status" required>
                    <?php foreach ($screeningTransitions as $status): ?>
                        <option value="<?= $escape($status) ?>"<?= $status === $screeningStatus ? ' selected' : '' ?>><?= $escape($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Correo de contacto
                <input type="email" name="contact_email" value="<?= $escape($screening['contact_email'] ?? '') ?>" maxlength="190">
            </label>
            <label>URL del estudio
                <input type="url" name="portal_url" value="<?= $escape($screening['portal_url'] ?? '') ?>" maxlength="2048">
            </label>
            <label>Resultado
                <textarea name="result"><?= $escape($screening['result'] ?? '') ?></textarea>
            </label>
            <label>Notas internas
                <textarea name="notes"><?= $escape($screening['notes'] ?? '') ?></textarea>
            </label>
            <label>Soporte / radicado
                <input type="text" name="support_reference" value="<?= $escape($screening['support_reference'] ?? '') ?>" maxlength="255">
            </label>
            <button type="submit">Actualizar estudio</button>
        </form>
    <?php else: ?>
        <dl>
            <dt>Proveedor</dt><dd><?= $escape($screening['provider']) ?></dd>
            <dt>Producto</dt><dd><?= $escape($screening['product'] ?? '') ?></dd>
            <dt>Estado</dt><dd><?= $escape($screening['status']) ?></dd>
            <dt>Correo de contacto</dt><dd><?= $escape($screening['contact_email'] ?? '') ?></dd>
            <dt>URL</dt><dd><?= $escape($screening['portal_url'] ?? '') ?></dd>
            <dt>Resultado</dt><dd><?= $escape($screening['result'] ?? '') ?></dd>
            <dt>Notas</dt><dd><?= $escape($screening['notes'] ?? '') ?></dd>
            <dt>Soporte / radicado</dt><dd><?= $escape($screening['support_reference'] ?? '') ?></dd>
            <dt>Inicio</dt><dd><?= $escape($screening['started_at'] ?? '') ?></dd>
            <dt>Resultado registrado</dt><dd><?= $escape($screening['result_at'] ?? '') ?></dd>
            <dt>Creado</dt><dd><?= $escape($screening['created_at'] ?? '') ?></dd>
            <dt>Actualizado</dt><dd><?= $escape($screening['updated_at'] ?? '') ?></dd>
        </dl>
    <?php endif; ?>
</section>

<?php if ($canManageCandidates): ?>
    <section class="surface">
        <h2>Administrar</h2>
        <?php if ($allowedTransitions !== []): ?>
            <form method="post" action="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>/status">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Nuevo estado
                    <select name="status" required>
                        <?php foreach ($allowedTransitions as $status): ?>
                            <option value="<?= $escape($status) ?>"><?= $escape($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Actualizar estado</button>
            </form>
        <?php endif; ?>

        <form method="post" action="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>/token/regenerate">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <button type="submit">Regenerar enlace de continuación</button>
        </form>
    </section>
<?php endif; ?>
