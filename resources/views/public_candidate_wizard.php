<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$candidate = is_array($candidate ?? null) ? $candidate : [];
$wizardSteps = is_array($wizardSteps ?? null) ? array_values($wizardSteps) : [];
$step = (string) ($step ?? 'personal');
$stepIndex = is_int($stepIndex ?? null) ? $stepIndex : 1;
$previousStep = is_string($previousStep ?? null) ? $previousStep : null;
$nextStep = is_string($nextStep ?? null) ? $nextStep : null;
$editable = ($editable ?? false) === true;
$action = (string) ($action ?? '');
$referencesAction = (string) ($referencesAction ?? '');
$unitAction = (string) ($unitAction ?? '');
$submitAction = (string) ($submitAction ?? '');
$documentActionBase = rtrim((string) ($documentActionBase ?? ''), '/');
$applicationRequirements = is_array($applicationRequirements ?? null) ? array_values($applicationRequirements) : [];
$documentsByRequirement = is_array($documentsByRequirement ?? null) ? $documentsByRequirement : [];
$availableUnits = is_array($availableUnits ?? null) ? $availableUnits : [];
$references = is_array($candidate['references'] ?? null) ? array_values($candidate['references']) : [];
$screening = is_array($screening ?? null) ? $screening : null;
$csrfToken = (string) ($csrfToken ?? '');
$error = is_string($error ?? null) ? $error : null;
$continuePath = rtrim((string) ($basePath ?? ''), '/') . '/apply/continue/' . rawurlencode((string) ($token ?? ''));
$hasDocuments = $documentsByRequirement !== [];
$screeningStatusLabel = match ((string) ($screening['status'] ?? '')) {
    'pending' => 'Pendiente',
    'in_progress' => 'En estudio',
    'approved' => 'Aprobado',
    'rejected' => 'No aprobado',
    default => (string) ($screening['status'] ?? ''),
};
?>
<section data-wizard-step="<?= $escape($step) ?>">
    <p class="eyebrow">Solicitud de arrendamiento</p>
    <h1><?= $escape($wizardSteps[$stepIndex]['label'] ?? 'Continúa tu solicitud') ?></h1>
    <p class="hint">Paso <?= $escape($stepIndex + 1) ?> de <?= $escape(count($wizardSteps)) ?> · Este enlace personal vence según las condiciones de la solicitud.</p>

    <nav aria-label="Progreso de la solicitud">
        <ol>
            <?php foreach ($wizardSteps as $index => $wizardStep): ?>
                <?php
                $key = (string) ($wizardStep['key'] ?? '');
                $label = (string) ($wizardStep['label'] ?? '');
                $current = $key === $step;
                $canOpen = $editable || $key === 'review';
                ?>
                <li <?= $current ? 'aria-current="step"' : '' ?>>
                    <?php if ($key !== 'start' && $canOpen && !$current): ?>
                        <a href="<?= $escape($continuePath . '/' . rawurlencode($key)) ?>"><?= $escape($label) ?></a>
                    <?php else: ?>
                        <?= $escape($label) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <?php if ($error !== null): ?>
        <p role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <?php if ($step === 'personal'): ?>
        <section class="surface">
            <h2>Identificación</h2>
            <dl>
                <dt>Tipo de documento</dt><dd><?= $escape($candidate['document_type'] ?? '') ?></dd>
                <dt>Número de documento</dt><dd><?= $escape($candidate['document_number'] ?? '') ?></dd>
            </dl>
        </section>
        <?php if ($editable): ?>
            <form class="stack-form" method="post" action="<?= $escape($action) ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="wizard_step" value="personal">
                <label>Nombre completo<input name="full_name" maxlength="190" required autocomplete="name" value="<?= $escape($candidate['full_name'] ?? '') ?>"></label>
                <label>Fecha de nacimiento<input type="date" name="birth_date" value="<?= $escape($candidate['birth_date'] ?? '') ?>"></label>
                <label>Celular o WhatsApp<input name="phone" maxlength="50" required autocomplete="tel" value="<?= $escape($candidate['phone'] ?? '') ?>"></label>
                <label>Correo electrónico<input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= $escape($candidate['email'] ?? '') ?>"></label>
                <label>Dirección actual<input name="current_address" maxlength="255" autocomplete="street-address" value="<?= $escape($candidate['current_address'] ?? '') ?>"></label>
                <button type="submit">Guardar y continuar</button>
            </form>

            <?php if ($availableUnits !== [] && !$hasDocuments): ?>
                <section>
                    <h2>Unidad de interés</h2>
                    <form class="stack-form" method="post" action="<?= $escape($unitAction) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="wizard_step" value="personal">
                        <label>Cambiar a una unidad disponible
                            <select name="public_key" required>
                                <option value="">Selecciona una unidad</option>
                                <?php foreach ($availableUnits as $unit): ?>
                                    <?php $label = trim((string) ($unit['property_name'] ?? 'Unidad') . ' — ' . (string) ($unit['location_label'] ?? ''), " —\t\n\r\0\x0B"); ?>
                                    <option value="<?= $escape($unit['public_key'] ?? '') ?>"><?= $escape($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit">Cambiar unidad</button>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($step === 'work'): ?>
        <?php if ($editable): ?>
            <form class="stack-form" method="post" action="<?= $escape($action) ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="wizard_step" value="work">
                <label>Estado laboral<input name="employment_status" maxlength="80" value="<?= $escape($candidate['employment_status'] ?? '') ?>"></label>
                <label>Empresa<input name="employer_name" maxlength="190" value="<?= $escape($candidate['employer_name'] ?? '') ?>"></label>
                <label>Ocupación<input name="occupation" maxlength="190" value="<?= $escape($candidate['occupation'] ?? '') ?>"></label>
                <label>Ingreso mensual<input type="number" name="monthly_income" min="0" step="0.01" value="<?= $escape($candidate['monthly_income'] ?? '') ?>"></label>
                <label>Notas laborales<textarea name="employment_notes"><?= $escape($candidate['employment_notes'] ?? '') ?></textarea></label>
                <p class="hint">Si agregas información laboral, completa también el estado laboral antes de enviar.</p>
                <button type="submit">Guardar y continuar</button>
            </form>
        <?php endif; ?>

    <?php elseif ($step === 'occupants'): ?>
        <?php if ($editable): ?>
            <form class="stack-form" method="post" action="<?= $escape($action) ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="wizard_step" value="occupants">
                <label>Número de ocupantes<input type="number" name="occupants_count" min="0" max="65535" step="1" value="<?= $escape($candidate['occupants_count'] ?? '') ?>"></label>
                <label>Notas sobre ocupantes<textarea name="occupants_notes"><?= $escape($candidate['occupants_notes'] ?? '') ?></textarea></label>
                <fieldset>
                    <legend>¿Tienes mascotas?</legend>
                    <label><input type="radio" name="pets_declared" value="1" <?= !empty($candidate['pets_declared']) ? 'checked' : '' ?>> Sí</label>
                    <label><input type="radio" name="pets_declared" value="0" <?= empty($candidate['pets_declared']) ? 'checked' : '' ?>> No</label>
                </fieldset>
                <label>Notas sobre mascotas<textarea name="pets_notes"><?= $escape($candidate['pets_notes'] ?? '') ?></textarea></label>
                <button type="submit">Guardar y continuar</button>
            </form>
        <?php endif; ?>

    <?php elseif ($step === 'references'): ?>
        <?php if ($editable): ?>
            <form class="stack-form" method="post" action="<?= $escape($referencesAction) ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="wizard_step" value="references">
                <p class="hint">Puedes guardar ninguna, una o varias referencias. Una fila completamente vacía se ignora.</p>
                <?php foreach ($references as $index => $reference): ?>
                    <fieldset>
                        <legend>Referencia <?= $escape($index + 1) ?></legend>
                        <label>Tipo<input name="references[<?= $escape($index) ?>][reference_type]" maxlength="40" value="<?= $escape($reference['reference_type'] ?? '') ?>"></label>
                        <label>Nombre<input name="references[<?= $escape($index) ?>][name]" maxlength="190" value="<?= $escape($reference['name'] ?? '') ?>"></label>
                        <label>Relación<input name="references[<?= $escape($index) ?>][relationship]" maxlength="120" value="<?= $escape($reference['relationship'] ?? '') ?>"></label>
                        <label>Teléfono<input name="references[<?= $escape($index) ?>][phone]" maxlength="50" value="<?= $escape($reference['phone'] ?? '') ?>"></label>
                        <label>Correo<input type="email" name="references[<?= $escape($index) ?>][email]" maxlength="190" value="<?= $escape($reference['email'] ?? '') ?>"></label>
                    </fieldset>
                <?php endforeach; ?>
                <?php $newIndex = count($references); ?>
                <fieldset>
                    <legend>Agregar referencia</legend>
                    <label>Tipo<input name="references[<?= $newIndex ?>][reference_type]" maxlength="40"></label>
                    <label>Nombre<input name="references[<?= $newIndex ?>][name]" maxlength="190"></label>
                    <label>Relación<input name="references[<?= $newIndex ?>][relationship]" maxlength="120"></label>
                    <label>Teléfono<input name="references[<?= $newIndex ?>][phone]" maxlength="50"></label>
                    <label>Correo<input type="email" name="references[<?= $newIndex ?>][email]" maxlength="190"></label>
                </fieldset>
                <button type="submit">Guardar y continuar</button>
            </form>
        <?php endif; ?>

    <?php elseif ($step === 'documents'): ?>
        <?php if ($applicationRequirements === []): ?>
            <p class="hint">Esta unidad no tiene documentos adicionales configurados.</p>
        <?php else: ?>
            <?php foreach ($applicationRequirements as $requirement): ?>
                <?php $code = (string) ($requirement['code'] ?? ''); $hasDocument = !empty($documentsByRequirement[$code]); ?>
                <article class="surface">
                    <h2><?= $escape($requirement['label'] ?? '') ?></h2>
                    <?php if (($requirement['description'] ?? null) !== null): ?><p><?= nl2br($escape($requirement['description'])) ?></p><?php endif; ?>
                    <p class="hint"><?= !empty($requirement['is_required']) ? 'Obligatorio' : 'Opcional' ?> · <?= $hasDocument ? 'Adjunto' : 'Pendiente' ?></p>
                    <?php if ($editable): ?>
                        <form class="stack-form" method="post" enctype="multipart/form-data" action="<?= $escape($documentActionBase . '/' . rawurlencode($code)) ?>">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="wizard_step" value="documents">
                            <label><?= $hasDocument ? 'Reemplazar documento' : 'Adjuntar documento' ?><input type="file" name="document" accept="application/pdf,image/jpeg,image/png" required></label>
                            <button type="submit"><?= $hasDocument ? 'Reemplazar' : 'Subir documento' ?></button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php elseif ($step === 'review'): ?>
        <section class="surface">
            <h2>Datos personales</h2>
            <dl>
                <dt>Tipo de documento</dt><dd><?= $escape($candidate['document_type'] ?? '') ?></dd>
                <dt>Número de documento</dt><dd><?= $escape($candidate['document_number'] ?? '') ?></dd>
                <dt>Nombre</dt><dd><?= $escape($candidate['full_name'] ?? '') ?></dd>
                <dt>Nacimiento</dt><dd><?= $escape($candidate['birth_date'] ?? '') ?></dd>
                <dt>Teléfono</dt><dd><?= $escape($candidate['phone'] ?? '') ?></dd>
                <dt>Correo</dt><dd><?= $escape($candidate['email'] ?? '') ?></dd>
                <dt>Dirección</dt><dd><?= $escape($candidate['current_address'] ?? '') ?></dd>
            </dl>
        </section>
        <section class="surface">
            <h2>Trabajo y ocupantes</h2>
            <dl>
                <dt>Estado laboral</dt><dd><?= $escape($candidate['employment_status'] ?? '') ?></dd>
                <dt>Empresa</dt><dd><?= $escape($candidate['employer_name'] ?? '') ?></dd>
                <dt>Ocupación</dt><dd><?= $escape($candidate['occupation'] ?? '') ?></dd>
                <dt>Ingreso mensual</dt><dd><?= $escape($candidate['monthly_income'] ?? '') ?></dd>
                <dt>Ocupantes</dt><dd><?= $escape($candidate['occupants_count'] ?? '') ?></dd>
                <dt>Mascotas</dt><dd><?= !empty($candidate['pets_declared']) ? 'Sí' : 'No' ?></dd>
            </dl>
        </section>
        <section class="surface">
            <h2>Referencias</h2>
            <?php if ($references === []): ?>
                <p>No se agregaron referencias.</p>
            <?php else: ?>
                <?php foreach ($references as $reference): ?>
                    <p><?= $escape($reference['name'] ?? '') ?> · <?= $escape($reference['phone'] ?? '') ?><?= !empty($reference['email']) ? ' · ' . $escape($reference['email']) : '' ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <section class="surface">
            <h2>Documentos</h2>
            <?php if ($applicationRequirements === []): ?>
                <p>No hay documentos adicionales configurados.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($applicationRequirements as $requirement): ?>
                        <?php $code = (string) ($requirement['code'] ?? ''); ?>
                        <li><?= $escape($requirement['label'] ?? '') ?>: <?= !empty($documentsByRequirement[$code]) ? 'Adjunto' : 'Pendiente' ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php if ($editable): ?>
            <p class="hint">Antes de enviar, verifica tus datos y adjunta todos los documentos obligatorios.</p>
            <form method="post" action="<?= $escape($submitAction) ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <button type="submit">Enviar solicitud</button>
            </form>
        <?php else: ?>
            <p>Esta solicitud ya fue enviada y solo puede consultarse.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($screening !== null): ?>
        <section>
            <h2>Estudio de aseguradora</h2>
            <dl>
                <dt>Proveedor</dt><dd><?= $escape($screening['provider'] ?? '') ?></dd>
                <?php if (($screening['product'] ?? null) !== null): ?><dt>Producto</dt><dd><?= $escape($screening['product']) ?></dd><?php endif; ?>
                <dt>Estado</dt><dd><?= $escape($screeningStatusLabel) ?></dd>
                <?php if (($screening['contact_email'] ?? null) !== null): ?><dt>Contacto</dt><dd><?= $escape($screening['contact_email']) ?></dd><?php endif; ?>
                <?php if (($screening['started_at'] ?? null) !== null): ?><dt>Inicio</dt><dd><?= $escape($screening['started_at']) ?></dd><?php endif; ?>
                <?php if (($screening['result'] ?? null) !== null): ?><dt>Resultado</dt><dd><?= $escape($screening['result']) ?></dd><?php endif; ?>
                <?php if (($screening['result_at'] ?? null) !== null): ?><dt>Fecha de resultado</dt><dd><?= $escape($screening['result_at']) ?></dd><?php endif; ?>
            </dl>
            <?php if (($screening['portal_url'] ?? null) !== null): ?>
                <p><a class="button button--quiet" href="<?= $escape($screening['portal_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir estudio</a></p>
            <?php elseif (in_array((string) ($screening['status'] ?? ''), ['pending', 'in_progress'], true)): ?>
                <p class="hint">No hay enlace externo disponible para este estudio.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <nav aria-label="Navegación del paso">
        <?php if ($previousStep !== null): ?><a class="button button--quiet" href="<?= $escape($continuePath . '/' . rawurlencode($previousStep)) ?>">Volver</a><?php endif; ?>
        <?php if ($nextStep !== null && $editable): ?><a class="button button--quiet" href="<?= $escape($continuePath . '/' . rawurlencode($nextStep)) ?>">Continuar</a><?php endif; ?>
    </nav>
    <p><a href="<?= $escape($basePath ?? '') ?>/catalog">Volver al catálogo</a></p>
</section>
