<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="surface hero-card">
    <p class="eyebrow">Inicio</p>
    <h1>Hola, <?= $escape($user['full_name'] ?? '') ?></h1>
    <p class="hint">Tu espacio de administración está listo. Usa Apps para entrar a las funciones disponibles para tu cuenta.</p>
    <p class="role-pill"><?= $escape($user['role'] ?? '') ?></p>
</section>
