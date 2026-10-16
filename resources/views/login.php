<?php

declare(strict_types=1);
?>
<h1>Iniciar sesión</h1>
<p class="hint">Ingresa para administrar Alquila Pro.</p>
<?php if (is_string($error ?? null) && $error !== ''): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="<?= htmlspecialchars((string) $action, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <label for="auth_identifier">Usuario, correo o cédula</label>
    <input id="auth_identifier" type="text" name="identifier" autocomplete="username" required>
    <label for="auth_password">Contraseña</label>
    <input id="auth_password" type="password" name="password" autocomplete="current-password" required>
    <button type="submit">Entrar</button>
</form>
