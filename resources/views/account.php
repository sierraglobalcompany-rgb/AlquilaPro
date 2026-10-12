<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="surface account-card">
    <p class="eyebrow">Cuenta</p>
    <h1><?= $escape($user['full_name'] ?? '') ?></h1>
    <dl class="account-details">
        <div><dt>Correo</dt><dd><?= $escape($user['email'] ?? '') ?></dd></div>
        <div><dt>Rol</dt><dd><?= $escape($user['role'] ?? '') ?></dd></div>
    </dl>
    <a class="button button--secondary" href="<?= $escape($basePath) ?>/account/password">Cambiar contraseña</a>
    <form method="post" action="<?= $escape($basePath) ?>/logout">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken ?? '') ?>">
        <button type="submit">Cerrar sesión</button>
    </form>
</section>
