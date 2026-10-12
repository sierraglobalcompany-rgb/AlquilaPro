<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$candidate = is_array($candidate ?? null) ? $candidate : [];
$action = (string) ($action ?? '');
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
                Notas laborales
                <textarea name="employment_notes"><?= $escape($candidate['employment_notes'] ?? '') ?></textarea>
            </label>

            <button type="submit">Guardar cambios</button>
        </form>
    <?php else: ?>
        <p>Esta solicitud ya no admite edición pública.</p>
    <?php endif; ?>

    <p><a href="<?= $escape($basePath) ?>/catalog">Volver al catálogo</a></p>
</section>
