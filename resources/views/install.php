<?php

declare(strict_types=1);

/** @var array{ok: bool, checks: array<string, array<string, mixed>>} $runtime */
/** @var string|null $error */
/** @var string $action */
/** @var string $csrfToken */
/** @var string $assetHref */

$label = static fn (bool $ok): string => $ok ? 'OK' : 'FALTA';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Instalar Alquila Pro</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetHref, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<main class="auth-shell auth-shell--installer">
    <section class="surface">
        <h1>Instalar Alquila Pro</h1>
        <p class="hint">Configuración inicial del sistema.</p>
    </section>

    <section class="surface">
        <h2>Diagnóstico del servidor</h2>
        <ul>
            <?php foreach ($runtime['checks'] as $name => $check): ?>
                <li><?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') ?>: <?= $label((bool) ($check['ok'] ?? false)) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($error !== null): ?>
        <section class="surface error"><strong><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></strong></section>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <section class="surface">
            <h2>Base de datos</h2>
            <label for="db_host">Servidor</label>
            <input id="db_host" name="db_host" value="localhost" required>

            <label for="db_port">Puerto</label>
            <input id="db_port" name="db_port" inputmode="numeric" value="3306" required>

            <label for="db_name">Base de datos</label>
            <input id="db_name" name="db_name" required>

            <label for="db_user">Usuario</label>
            <input id="db_user" name="db_user" required>

            <label for="db_password">Contraseña</label>
            <input id="db_password" name="db_password" type="password">
        </section>

        <section class="surface">
            <h2>Propietario inicial</h2>
            <label for="app_name">Nombre del sistema</label>
            <input id="app_name" name="app_name" value="Alquila Pro" required>

            <label for="owner_name">Nombre completo</label>
            <input id="owner_name" name="owner_name" required>

            <label for="owner_email">Correo</label>
            <input id="owner_email" name="owner_email" type="email" required>

            <label for="owner_password">Contraseña inicial</label>
            <input id="owner_password" name="owner_password" type="password" minlength="12" required>

            <button type="submit" <?= $runtime['ok'] ? '' : 'disabled' ?>>Instalar Alquila Pro</button>
        </section>
    </form>
</main>
</body>
</html>
