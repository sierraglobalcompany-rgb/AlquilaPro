<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Iniciar sesión · Alquila Pro</title></head>
<body>
<main>
    <h1>Iniciar sesión</h1>
    <?php if (is_string($error ?? null) && $error !== ''): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" action="<?= htmlspecialchars((string) $action, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label>Correo <input type="email" name="email" autocomplete="username" required></label>
        <label>Contraseña <input type="password" name="password" autocomplete="current-password" required></label>
        <button type="submit">Entrar</button>
    </form>
</main>
</body>
</html>
