<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="surface">
    <p class="eyebrow">Apps</p>
    <h1>Contratos</h1>
    <p class="hint">Crea borradores solo para candidatos con estudio aprobado y revisa su vista previa antes de generar documentos.</p>
    <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/contract-templates">Administrar plantillas</a>
</section>

<?php if ($canManage): ?>
    <section class="surface" data-contract-create>
        <h2>Nuevo borrador</h2>
        <?php if ($versionOptions === []): ?>
            <p class="empty-state">No hay versiones activas disponibles. Crea primero una versión de plantilla.</p>
        <?php else: ?>
            <form method="post" action="<?= $escape($basePath) ?>/admin/contracts" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <div class="field-grid">
                    <label class="field">
                        <span>ID del candidato</span>
                        <input type="number" min="1" step="1" name="candidate_id" required>
                    </label>
                    <label class="field">
                        <span>Versión de plantilla</span>
                        <select name="template_version_id" required>
                            <option value="">Selecciona una versión</option>
                            <?php foreach ($versionOptions as $option): ?>
                                <option value="<?= $escape($option['id']) ?>">
                                    <?= $escape($option['template_name']) ?> · v<?= $escape($option['version_number']) ?> · <?= $escape($option['document_type']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Canon mensual</span>
                        <input type="text" inputmode="decimal" name="monthly_rent" required>
                    </label>
                    <label class="field">
                        <span>Administración</span>
                        <input type="text" inputmode="decimal" name="administration_fee">
                    </label>
                    <label class="field">
                        <span>Fecha de inicio</span>
                        <input type="date" name="start_date" required>
                    </label>
                    <label class="field">
                        <span>Fecha de finalización</span>
                        <input type="date" name="end_date" required>
                    </label>
                </div>
                <label class="field">
                    <span>Duración acordada</span>
                    <input type="text" maxlength="120" name="duration_text" required>
                </label>
                <label class="field">
                    <span>Variables manuales JSON</span>
                    <textarea name="manual_variables_json" rows="4">{}</textarea>
                </label>
                <label class="field">
                    <span>Tareas JSON</span>
                    <textarea name="tasks_json" rows="4">[]</textarea>
                </label>
                <label class="field">
                    <span>Anexos JSON</span>
                    <textarea name="annexes_json" rows="4">[]</textarea>
                </label>
                <button class="button" type="submit">Crear borrador</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($contracts === []): ?>
    <section class="surface">
        <p class="empty-state">Aún no hay contratos registrados.</p>
    </section>
<?php else: ?>
    <section class="users-grid" aria-label="Contratos registrados">
        <?php foreach ($contracts as $contract): ?>
            <article class="user-card">
                <div class="user-card__head">
                    <strong>Contrato #<?= $escape($contract['id']) ?></strong>
                    <span class="status-pill"><?= $escape($contract['status']) ?></span>
                </div>
                <div><?= $escape($contract['candidate_name'] ?? '') ?></div>
                <div><?= $escape($contract['unit_code'] ?? '') ?> · <?= $escape($contract['document_type']) ?></div>
                <div><?= $escape($contract['template_name'] ?? '') ?> · v<?= $escape($contract['template_version_number'] ?? '') ?></div>
                <div>Canon: <?= $escape($contract['monthly_rent']) ?></div>
                <div><?= $escape($contract['start_date']) ?> → <?= $escape($contract['end_date']) ?></div>
                <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/contracts/<?= $escape($contract['id']) ?>">Ver contrato</a>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>