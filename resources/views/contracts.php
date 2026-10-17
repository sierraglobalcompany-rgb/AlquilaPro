<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$draftContext = is_array($draftContext ?? null) ? $draftContext : null;
$selectedVersionId = is_int($selectedVersionId ?? null) ? $selectedVersionId : null;
$manualVariableDeclarations = is_array($manualVariableDeclarations ?? null) ? $manualVariableDeclarations : [];
$candidateRequested = (bool) ($candidateRequested ?? false);
$typeLabel = static fn (string $type): string => match ($type) {
    'urban_housing' => 'Vivienda urbana',
    'independent_parking' => 'Parqueadero independiente',
    default => $type,
};
?>
<section class="surface">
    <p class="eyebrow">Apps</p>
    <h1>Contratos</h1>
    <p class="hint">Crea borradores desde un candidato con estudio aprobado y revisa su vista previa antes de generar documentos.</p>
    <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/contract-templates">Administrar plantillas</a>
</section>

<?php if ($canManage): ?>
    <section class="surface" data-contract-create>
        <h2>Nuevo borrador</h2>
        <?php if ($draftContext === null): ?>
            <?php if ($candidateRequested): ?>
                <p class="empty-state">Este candidato no está disponible para crear un contrato. Revisa su estudio, unidad y contratos existentes.</p>
            <?php else: ?>
                <p class="hint">Abre un candidato con estudio aprobado y usa “Crear contrato” desde su expediente. Así no necesitas copiar IDs ni buscar la unidad manualmente.</p>
                <a class="button" href="<?= $escape($basePath) ?>/admin/candidates">Ir a candidatos</a>
            <?php endif; ?>
        <?php else: ?>
            <article class="user-card">
                <div class="user-card__head">
                    <strong><?= $escape($draftContext['candidate_name']) ?></strong>
                    <span class="status-pill">Listo para contrato</span>
                </div>
                <div>Documento: <?= $escape(strtoupper((string) $draftContext['candidate_document_type'])) ?> <?= $escape($draftContext['candidate_document_number']) ?></div>
                <div>Inmueble: <?= $escape($draftContext['property_name']) ?></div>
                <div>Unidad: <?= $escape($draftContext['unit_code']) ?></div>
                <div>Tipo de contrato: <?= $escape($typeLabel((string) $draftContext['document_type'])) ?></div>
            </article>

            <?php if ($versionOptions === []): ?>
                <p class="empty-state">No hay una versión contractual activa compatible con esta unidad.</p>
            <?php elseif ($selectedVersionId === null): ?>
                <form method="get" action="<?= $escape($basePath) ?>/admin/contracts" class="settings-form">
                    <input type="hidden" name="candidate_id" value="<?= (int) $draftContext['candidate_id'] ?>">
                    <label class="field">
                        <span>Plantilla</span>
                        <select name="template_version_id" required>
                            <option value="">Selecciona una plantilla</option>
                            <?php foreach ($versionOptions as $option): ?>
                                <option value="<?= (int) $option['id'] ?>">
                                    <?= $escape($option['template_name']) ?> · v<?= (int) $option['version_number'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button" type="submit">Continuar</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= $escape($basePath) ?>/admin/contracts" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="candidate_id" value="<?= (int) $draftContext['candidate_id'] ?>">
                    <input type="hidden" name="template_version_id" value="<?= (int) $selectedVersionId ?>">
                    <div class="field-grid">
                        <label class="field">
                            <span>Canon mensual</span>
                            <input type="text" inputmode="decimal" name="monthly_rent" value="<?= $escape($draftContext['monthly_rent']) ?>" required>
                        </label>
                        <label class="field">
                            <span>Administración</span>
                            <input type="text" inputmode="decimal" name="administration_fee" value="<?= $escape($draftContext['administration_fee'] ?? '') ?>">
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

                    <?php if ($manualVariableDeclarations !== []): ?>
                        <fieldset class="settings-form">
                            <legend>Datos adicionales del contrato</legend>
                            <?php foreach ($manualVariableDeclarations as $declaration): ?>
                                <?php
                                $key = (string) ($declaration['key'] ?? '');
                                $label = (string) ($declaration['label'] ?? $key);
                                $required = (bool) ($declaration['required'] ?? false);
                                ?>
                                <label class="field">
                                    <span><?= $escape($label) ?><?= $required ? ' *' : '' ?></span>
                                    <input type="text" name="manual_variables[<?= $escape($key) ?>]" maxlength="4000"<?= $required ? ' required' : '' ?>>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endif; ?>

                    <fieldset class="settings-form">
                        <legend>Tareas iniciales</legend>
                        <label class="field">
                            <span>Tarea (opcional)</span>
                            <input type="text" name="tasks[0][label]" maxlength="190">
                        </label>
                        <input type="hidden" name="tasks[0][done]" value="0">
                        <label><input type="checkbox" name="tasks[0][done]" value="1"> Marcar como completada</label>
                    </fieldset>

                    <fieldset class="settings-form">
                        <legend>Anexos iniciales</legend>
                        <label class="field">
                            <span>Título del anexo (opcional)</span>
                            <input type="text" name="annexes[0][title]" maxlength="190">
                        </label>
                        <label class="field">
                            <span>Nota</span>
                            <textarea name="annexes[0][note]" rows="3"></textarea>
                        </label>
                    </fieldset>

                    <button class="button" type="submit">Crear borrador</button>
                </form>
            <?php endif; ?>
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
                <div><?= $escape($contract['unit_code'] ?? '') ?> · <?= $escape($typeLabel((string) $contract['document_type'])) ?></div>
                <div><?= $escape($contract['template_name'] ?? '') ?> · v<?= $escape($contract['template_version_number'] ?? '') ?></div>
                <div>Canon: <?= $escape($contract['monthly_rent']) ?></div>
                <div><?= $escape($contract['start_date']) ?> → <?= $escape($contract['end_date']) ?></div>
                <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/contracts/<?= $escape($contract['id']) ?>">Ver contrato</a>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>