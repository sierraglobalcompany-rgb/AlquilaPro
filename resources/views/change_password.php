<?php

declare(strict_types=1);
?>
<h1>Cambiar contraseña</h1>
<p class="hint">Actualiza la contraseña de acceso a tu cuenta.</p>
<?php if (is_string($error ?? null) && $error !== ''): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="<?= htmlspecialchars((string) $action, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <label for="current_password">Contraseña actual</label>
    <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
    <label for="new_password">Nueva contraseña</label>
    <input id="new_password" type="password" name="new_password" minlength="6" autocomplete="new-password" required>
    <button type="submit">Guardar contraseña</button>
</form>
