<?php

declare(strict_types=1);

$escapeLoginForm = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<form method="post" action="<?= $escapeLoginForm($action ?? '') ?>">
    <input type="hidden" name="csrf_token" value="<?= $escapeLoginForm($csrfToken ?? '') ?>">
    <label for="auth_identifier">Usuario, correo o cédula</label>
    <input id="auth_identifier" type="text" name="identifier" autocomplete="username" required>
    <label for="auth_password">Contraseña</label>
    <input id="auth_password" type="password" name="password" autocomplete="current-password" required>
    <button type="submit">Iniciar sesión</button>
</form>
