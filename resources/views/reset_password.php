<?php

declare(strict_types=1);
?>
<h1>Restablecer contraseña</h1>
<p class="hint">Define una nueva contraseña segura para tu cuenta.</p>
<?php if (is_string($error ?? null) && $error !== ''): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="<?= htmlspecialchars((string) $action, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars((string) ($token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <label for="new_password">Nueva contraseña</label>
    <input id="new_password" type="password" name="new_password" minlength="12" autocomplete="new-password" required>
    <button type="submit">Restablecer contraseña</button>
</form>
