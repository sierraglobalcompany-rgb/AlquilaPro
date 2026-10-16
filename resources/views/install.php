<?php

declare(strict_types=1);

/** @var array{ok: bool, checks: array<string, array<string, mixed>>} $runtime */
/** @var string|null $error */
/** @var int $step */
/** @var string $csrfToken */
/** @var string $assetHref */
/** @var string $continuePath */
/** @var string $testDatabasePath */
/** @var string $backDatabasePath */
/** @var string $installPath */
/** @var string $loginPath */
/** @var array<string, mixed> $viewState */

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$label = static fn (bool $ok): string => $ok ? 'OK' : 'FALTA';
$steps = [
    'Requisitos del servidor',
    'Base de datos',
    'Empresa y administración',
    'Propietario',
    'Resumen protegido',
    'Instalación',
    'Acceso',
];
$database = is_array($viewState['database'] ?? null) ? $viewState['database'] : [];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Instalar Alquila Pro</title>
    <link rel="stylesheet" href="<?= $escape($assetHref) ?>">
</head>
<body>
<main class="auth-shell auth-shell--installer">
    <section class="surface">
        <h1>Instalar Alquila Pro</h1>
        <p class="hint">Configuración inicial segura del sistema.</p>
        <?php if ($step < 6): ?>
            <p class="hint">Paso <?= $step ?> de 7: <?= $escape($steps[$step]) ?></p>
            <ol aria-label="Pasos de instalación">
                <?php foreach ($steps as $index => $stepLabel): ?>
                    <li<?= $index === $step ? ' aria-current="step"' : '' ?>><?= $escape($stepLabel) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="hint">Paso 6 de 7: <?= $escape($steps[6]) ?></p>
        <?php endif; ?>
    </section>

    <?php if ($step === 0): ?>
        <section class="surface">
            <h2>Requisitos del servidor</h2>
            <ul>
                <?php foreach ($runtime['checks'] as $name => $check): ?>
                    <li><?= $escape($name) ?>: <?= $label((bool) ($check['ok'] ?? false)) ?></li>
                <?php endforeach; ?>
            </ul>
            <p><?= $runtime['ok'] ? 'El servidor está listo para continuar.' : 'Corrige los requisitos faltantes y vuelve a cargar esta página.' ?></p>
            <form method="post" action="<?= $escape($continuePath) ?>" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <button type="submit" <?= $runtime['ok'] ? '' : 'disabled' ?>>Continuar</button>
            </form>
        </section>
    <?php elseif ($step === 1): ?>
        <form method="post" action="<?= $escape($testDatabasePath) ?>" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <section class="surface">
                <h2>Base de datos</h2>
                <label for="db_host">Servidor</label>
                <input id="db_host" name="db_host" value="<?= $escape($database['host'] ?? '127.0.0.1') ?>" required>

                <label for="db_port">Puerto</label>
                <input id="db_port" name="db_port" inputmode="numeric" value="<?= $escape($database['port'] ?? '3306') ?>" required>

                <label for="db_name">Base de datos</label>
                <input id="db_name" name="db_name" value="<?= $escape($database['database'] ?? '') ?>" required>

                <label for="db_user">Usuario</label>
                <input id="db_user" name="db_user" value="<?= $escape($database['username'] ?? '') ?>" required>

                <label for="db_password">Contraseña</label>
                <input id="db_password" name="db_password" type="password" autocomplete="new-password">
                <p class="hint">No se envía en la URL ni se vuelve a mostrar; se conserva temporalmente en la sesión del servidor.</p>

                <button type="submit" <?= $runtime['ok'] ? '' : 'disabled' ?>>Probar conexión</button>
            </section>
        </form>
    <?php elseif ($step === 2): ?>
        <form method="post" action="<?= $escape($continuePath) ?>" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <section class="surface">
                <h2>Empresa y administración</h2>
                <label for="app_name">Nombre de la empresa o sistema</label>
                <input id="app_name" name="app_name" value="<?= $escape($viewState['app_name'] ?? 'Alquila Pro') ?>" required>
                <button type="submit">Continuar</button>
            </section>
        </form>
        <form method="post" action="<?= $escape($backDatabasePath) ?>" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <button type="submit">Cambiar base de datos</button>
        </form>
    <?php elseif ($step === 3): ?>
        <form method="post" action="<?= $escape($continuePath) ?>" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <section class="surface">
                <h2>Propietario inicial</h2>
                <label for="owner_name">Nombre completo</label>
                <input id="owner_name" name="owner_name" value="<?= $escape($viewState['owner_name'] ?? '') ?>" required>

                <label for="owner_email">Correo</label>
                <input id="owner_email" name="owner_email" type="email" value="<?= $escape($viewState['owner_email'] ?? '') ?>" required>

                <label for="owner_password">Contraseña inicial</label>
                <input id="owner_password" name="owner_password" type="password" minlength="6" autocomplete="new-password" required>
                <p class="hint">Usa al menos 6 caracteres. La contraseña no se vuelve a mostrar.</p>

                <button type="submit">Continuar</button>
            </section>
        </form>
        <form method="post" action="<?= $escape($backDatabasePath) ?>" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <button type="submit">Cambiar base de datos</button>
        </form>
    <?php elseif ($step === 4 || $step === 5): ?>
        <section class="surface">
            <h2><?= $step === 4 ? 'Resumen protegido' : 'Confirmar instalación' ?></h2>
            <dl>
                <dt>Empresa</dt>
                <dd><?= $escape($viewState['app_name'] ?? '') ?></dd>
                <dt>Propietario</dt>
                <dd><?= $escape($viewState['owner_name'] ?? '') ?> (<?= $escape($viewState['owner_email'] ?? '') ?>)</dd>
                <dt>Servidor de base de datos</dt>
                <dd><?= $escape($database['host'] ?? '') ?>:<?= $escape($database['port'] ?? '') ?></dd>
                <dt>Base de datos</dt>
                <dd><?= $escape($database['database'] ?? '') ?></dd>
                <dt>Usuario de base de datos</dt>
                <dd><?= $escape($database['username'] ?? '') ?></dd>
                <dt>Contraseñas</dt>
                <dd>Protegidas; no se muestran</dd>
            </dl>
            <?php if ($step === 4): ?>
                <form method="post" action="<?= $escape($continuePath) ?>" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <button type="submit">Continuar a la instalación</button>
                </form>
            <?php else: ?>
                <p>El paso final aplicará las migraciones y guardará la configuración inicial.</p>
                <form method="post" action="<?= $escape($installPath) ?>" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <button type="submit" <?= $runtime['ok'] ? '' : 'disabled' ?>>Instalar y migrar</button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= $escape($backDatabasePath) ?>" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <button type="submit">Cambiar base de datos</button>
            </form>
        </section>
    <?php else: ?>
        <section class="surface">
            <h2>Instalación completada</h2>
            <p>Alquila Pro está instalado. Ya puedes iniciar sesión con la cuenta de propietario.</p>
            <a href="<?= $escape($loginPath) ?>">Ir al inicio de sesión</a>
        </section>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <section class="surface error" role="alert"><strong><?= $escape($error) ?></strong></section>
    <?php endif; ?>
</main>
</body>
</html>