<?php

declare(strict_types=1);

/** @var array{ok: bool, checks: array<string, array<string, mixed>>} $runtime */
/** @var string|null $error */
/** @var string $action */

$label = static fn (bool $ok): string => $ok ? 'OK' : 'FALTA';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalar Alquila Pro</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; background: #f5f5f3; color: #20211f; }
        body { margin: 0; }
        main { width: min(680px, calc(100% - 32px)); margin: 32px auto; }
        section { background: #fff; border: 1px solid #e3e4df; border-radius: 18px; padding: 20px; margin-bottom: 16px; }
        h1, h2 { margin-top: 0; }
        label { display: block; font-weight: 650; margin: 14px 0 6px; }
        input { box-sizing: border-box; width: 100%; min-height: 46px; border: 1px solid #c9cbc4; border-radius: 12px; padding: 10px 12px; font: inherit; }
        button { width: 100%; min-height: 48px; margin-top: 18px; border: 0; border-radius: 12px; font: inherit; font-weight: 700; cursor: pointer; }
        ul { padding-left: 20px; }
        .error { border-color: #bd4242; }
        .error strong { color: #8a2222; }
        .hint { color: #62645f; font-size: .94rem; }
    </style>
</head>
<body>
<main>
    <section>
        <h1>Instalar Alquila Pro</h1>
        <p class="hint">Configuración inicial del sistema.</p>
    </section>

    <section>
        <h2>Diagnóstico del servidor</h2>
        <ul>
            <?php foreach ($runtime['checks'] as $name => $check): ?>
                <li><?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') ?>: <?= $label((bool) ($check['ok'] ?? false)) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($error !== null): ?>
        <section class="error"><strong><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></strong></section>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
        <section>
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

        <section>
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
