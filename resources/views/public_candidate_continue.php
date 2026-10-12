<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$candidate = is_array($candidate ?? null) ? $candidate : [];
$action = (string) ($action ?? '');
$referencesAction = (string) ($referencesAction ?? '');
$unitAction = (string) ($unitAction ?? '');
$availableUnits = is_array($availableUnits ?? null) ? $availableUnits : [];
$references = is_array($candidate['references'] ?? null) ? array_values($candidate['references']) : [];
$csrfToken = (string) ($csrfToken ?? '');
$error = is_string($error ?? null) ? $error : null;
$editable = in_array((string) ($candidate['status'] ?? ''), ['new', 'incomplete'], true);
?>
<section>
    <p class="eyebrow">Solicitud de arrendamiento</p>
    <h1>Continúa tu solicitud</h1>
    <p class="hint">Este enlace es personal. Guarda los cambios para continuar más adelante mientras esté vigente.</p>

    <?php if ($error !== null): ?>
        <p role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <?php if ($editable): ?>
        <form method="post" action="<?= $escape($action) ?>">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

            <label>
                Nombre completo
                <input name="full_name" maxlength="190" required autocomplete="name" value="<?= $escape($candidate['full_name'] ?? '') ?>">
            </label>
            <label>
                Fecha de nacimiento
                <input type="date" name="birth_date" value="<?= $escape($candidate['birth_date'] ?? '') ?>">
            </label>
            <label>
                Celular o WhatsApp
                <input name="phone" maxlength="50" required autocomplete="tel" value="<?= $escape($candidate['phone'] ?? '') ?>">
            </label>
            <label>
                Correo electrónico
                <input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= $escape($candidate['email'] ?? '') ?>">
            </label>
            <label>
                Dirección actual
                <input name="current_address" maxlength="255" autocomplete="street-address" value="<?= $escape($candidate['current_address'] ?? '') ?>">
            </label>
            <label>
                Estado laboral
                <input name="employment_status" maxlength="80" value="<?= $escape($candidate['employment_status'] ?? '') ?>">
            </label>
            <label>
                Empresa
                <input name="employer_name" maxlength="190" value="<?= $escape($candidate['employer_name'] ?? '') ?>">
            </label>
            <label>
                Ocupación
                <input name="occupation" maxlength="190" value="<?= $escape($candidate['occupation'] ?? '') ?>">
            </label>
            <label>
                Ingreso mensual
                <input type="number" name="monthly_income" min="0" step="0.01" value="<?= $escape($candidate['monthly_income'] ?? '') ?>">
            </label>
            <label>
                Notas laborales
                <textarea name="employment_notes"><?= $escape($candidate['employment_notes'] ?? '') ?></textarea>
            </label>
            <label>
                Número de ocupantes
                <input type="number" name="occupants_count" min="0" max="65535" step="1" value="<?= $escape($candidate['occupants_count'] ?? '') ?>">
            </label>
            <label>
                Notas sobre ocupantes
                <textarea name="occupants_notes"><?= $escape($candidate['occupants_notes'] ?? '') ?></textarea>
            </label>
            <fieldset>
                <legend>¿Tienes mascotas?</legend>
                <label>
                    <input type="radio" name="pets_declared" value="1" <?= !empty($candidate['pets_declared']) ? 'checked' : '' ?>>
                    Sí
                </label>
                <label>
                    <input type="radio" name="pets_declared" value="0" <?= empty($candidate['pets_declared']) ? 'checked' : '' ?>>
                    No
                </label>
            </fieldset>
            <label>
                Notas sobre mascotas
                <textarea name="pets_notes"><?= $escape($candidate['pets_notes'] ?? '') ?></textarea>
            </label>

            <button type="submit">Guardar cambios</button>
        </form>

        <section>
            <h2>Referencias</h2>
            <p class="hint">Puedes guardar ninguna, una o varias referencias. Una fila completamente vacía se ignora.</p>
            <form method="post" action="<?= $escape($referencesAction) ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

                <?php foreach ($references as $index => $reference): ?>
                    <fieldset>
                        <legend>Referencia <?= $index + 1 ?></legend>
                        <label>
                            Tipo
                            <input name="references[<?= $index ?>][reference_type]" maxlength="40" value="<?= $escape($reference['reference_type'] ?? '') ?>">
                        </label>
                        <label>
                            Nombre
                            <input name="references[<?= $index ?>][name]" maxlength="190" value="<?= $escape($reference['name'] ?? '') ?>">
                        </label>
                        <label>
                            Relación
                            <input name="references[<?= $index ?>][relationship]" maxlength="120" value="<?= $escape($reference['relationship'] ?? '') ?>">
                        </label>
                        <label>
                            Teléfono
                            <input name="references[<?= $index ?>][phone]" maxlength="50" value="<?= $escape($reference['phone'] ?? '') ?>">
                        </label>
                        <label>
                            Correo
                            <input type="email" name="references[<?= $index ?>][email]" maxlength="190" value="<?= $escape($reference['email'] ?? '') ?>">
                        </label>
                    </fieldset>
                <?php endforeach; ?>

                <?php $newIndex = count($references); ?>
                <fieldset>
                    <legend>Agregar referencia</legend>
                    <label>
                        Tipo
                        <input name="references[<?= $newIndex ?>][reference_type]" maxlength="40">
                    </label>
                    <label>
                        Nombre
                        <input name="references[<?= $newIndex ?>][name]" maxlength="190">
                    </label>
                    <label>
                        Relación
                        <input name="references[<?= $newIndex ?>][relationship]" maxlength="120">
                    </label>
                    <label>
                        Teléfono
                        <input name="references[<?= $newIndex ?>][phone]" maxlength="50">
                    </label>
                    <label>
                        Correo
                        <input type="email" name="references[<?= $newIndex ?>][email]" maxlength="190">
                    </label>
                </fieldset>

                <button type="submit">Guardar referencias</button>
            </form>
        </section>

        <section>
            <h2>Unidad de interés</h2>
            <?php if ($availableUnits !== []): ?>
                <form method="post" action="<?= $escape($unitAction) ?>">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <label>
                        Cambiar a una unidad disponible
                        <select name="public_key" required>
                            <option value="">Selecciona una unidad</option>
                            <?php foreach ($availableUnits as $unit): ?>
                                <?php
                                $label = trim(
                                    (string) ($unit['property_name'] ?? 'Unidad')
                                    . ' — '
                                    . (string) ($unit['location_label'] ?? ''),
                                    " —\t\n\r\0\x0B",
                                );
                                ?>
                                <option value="<?= $escape($unit['public_key'] ?? '') ?>"><?= $escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit">Cambiar unidad</button>
                </form>
            <?php else: ?>
                <p>No hay unidades publicables para cambiar en este momento.</p>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <p>Esta solicitud ya no admite edición pública.</p>
    <?php endif; ?>

    <p><a href="<?= $escape($basePath) ?>/catalog">Volver al catálogo</a></p>
</section>
