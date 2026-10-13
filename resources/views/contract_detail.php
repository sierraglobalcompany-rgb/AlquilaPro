<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$manualJson = $contract['manual_variables_json'] ?? '{}';
$tasksJson = $contract['tasks_json'] ?? '[]';
$annexesJson = $contract['annexes_json'] ?? '[]';
?>
<section class="surface">
    <p class="eyebrow">Contratos</p>
    <h1>Contrato #<?= $escape($contract['id']) ?></h1>
    <p class="hint">Estado: <?= $escape($contract['status']) ?></p>
    <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/contracts">Volver a contratos</a>
</section>

<section class="surface">
    <h2>Resumen</h2>
    <div class="users-grid">
        <article class="user-card">
            <div class="user-card__head">
                <strong><?= $escape($contract['candidate_name'] ?? '') ?></strong>
                <span class="status-pill"><?= $escape($contract['status']) ?></span>
            </div>
            <div>Unidad: <?= $escape($contract['unit_code'] ?? '') ?></div>
            <div>Tipo: <?= $escape($contract['document_type']) ?></div>
            <div>Plantilla: <?= $escape($contract['template_name'] ?? '') ?> · v<?= $escape($contract['template_version_number'] ?? '') ?></div>
            <div>Canon: <?= $escape($contract['monthly_rent']) ?></div>
            <div>Administración: <?= $contract['administration_fee'] === null ? 'No aplica' : $escape($contract['administration_fee']) ?></div>
            <div>Inicio: <?= $escape($contract['start_date']) ?></div>
            <div>Fin: <?= $escape($contract['end_date']) ?></div>
            <div>Duración: <?= $escape($contract['duration_text']) ?></div>
        </article>
    </div>
</section>

<?php if ($previewHtml !== null): ?>
    <section class="surface">
        <h2>Vista previa</h2>
        <p class="hint">Esta vista previa no genera PDF ni congela el contrato.</p>
        <?= $previewHtml ?>
    </section>
<?php endif; ?>

<?php if ($canManage && $contract['status'] === 'draft'): ?>
    <section class="surface">
        <h2>Editar borrador</h2>
        <form method="post" action="<?= $escape($basePath) ?>/admin/contracts/<?= $escape($contract['id']) ?>" class="settings-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <div class="field-grid">
                <label class="field">
                    <span>Versión de plantilla</span>
                    <select name="template_version_id" required>
                        <?php foreach ($versionOptions as $option): ?>
                            <option value="<?= $escape($option['id']) ?>"<?= (int) $option['id'] === (int) $contract['template_version_id'] ? ' selected' : '' ?>>
                                <?= $escape($option['template_name']) ?> · v<?= $escape($option['version_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Canon mensual</span>
                    <input type="text" inputmode="decimal" name="monthly_rent" value="<?= $escape($contract['monthly_rent']) ?>" required>
                </label>
                <label class="field">
                    <span>Administración</span>
                    <input type="text" inputmode="decimal" name="administration_fee" value="<?= $escape($contract['administration_fee'] ?? '') ?>">
                </label>
                <label class="field">
                    <span>Fecha de inicio</span>
                    <input type="date" name="start_date" value="<?= $escape($contract['start_date']) ?>" required>
                </label>
                <label class="field">
                    <span>Fecha de finalización</span>
                    <input type="date" name="end_date" value="<?= $escape($contract['end_date']) ?>" required>
                </label>
            </div>
            <label class="field">
                <span>Duración acordada</span>
                <input type="text" maxlength="120" name="duration_text" value="<?= $escape($contract['duration_text']) ?>" required>
            </label>
            <label class="field">
                <span>Variables manuales JSON</span>
                <textarea name="manual_variables_json" rows="4"><?= $escape($manualJson) ?></textarea>
            </label>
            <label class="field">
                <span>Tareas JSON</span>
                <textarea name="tasks_json" rows="4"><?= $escape($tasksJson) ?></textarea>
            </label>
            <label class="field">
                <span>Anexos JSON</span>
                <textarea name="annexes_json" rows="4"><?= $escape($annexesJson) ?></textarea>
            </label>
            <button class="button" type="submit">Guardar borrador</button>
        </form>
    </section>
<?php endif; ?>