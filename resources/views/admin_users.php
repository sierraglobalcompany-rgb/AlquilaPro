<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administradores · Alquila Pro</title></head>
<body>
<main>
    <h1>Administradores</h1>
    <section>
        <?php foreach ($users as $user): ?>
            <article>
                <strong><?= htmlspecialchars((string) $user['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <div><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></div>
                <div><?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?> · <?= $user['is_active'] ? 'Activo' : 'Inactivo' ?></div>
            </article>
        <?php endforeach; ?>
    </section>

    <h2>Agregar usuario</h2>
    <form method="post" action="<?= htmlspecialchars($basePath . '/admin/users', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label>Nombre <input name="full_name" required></label>
        <label>Correo <input type="email" name="email" required></label>
        <label>Rol <select name="role"><option value="admin">Administrador</option><option value="gestor">Gestor</option></select></label>
        <label>Contraseña temporal <input type="password" name="temporary_password" minlength="12" required></label>
        <button type="submit">Crear usuario</button>
    </form>
</main>
</body>
</html>
