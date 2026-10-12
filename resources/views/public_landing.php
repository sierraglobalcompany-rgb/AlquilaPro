<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$availabilityCount = max(0, (int) ($availabilityCount ?? 0));
?>
<div class="auth-copy">
    <p class="eyebrow">Arriendos disponibles</p>
    <h1>Encuentra tu próximo espacio</h1>
    <p>Consulta apartamentos y parqueaderos disponibles sin exponer información privada de propietarios o residentes.</p>
    <?php if ($availabilityCount > 0): ?>
        <p><strong><?= $escape($availabilityCount) ?> <?= $availabilityCount === 1 ? 'unidad disponible' : 'unidades disponibles' ?> ahora</strong></p>
    <?php endif; ?>
</div>

<div class="stack-form">
    <a class="button" href="<?= $escape($basePath) ?>/catalog">Ver catálogo</a>
    <a class="button button--quiet" href="<?= $escape($basePath) ?>/login">Iniciar sesión</a>
</div>
