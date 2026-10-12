<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cambiar contraseña · Alquila Pro</title></head>
<body>
<main>
    <h1>Cambiar contraseña</h1>
    <?php if (is_string($error ?? null) && $error !== ''): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" action="<?= htmlspecialchars((string) $action, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label>Contraseña actual <input type="password" name="current_password" autocomplete="current-password" required></label>
        <label>Nueva contraseña <input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label>
        <button type="submit">Guardar contraseña</button>
    </form>
</main>
</body>
</html>
