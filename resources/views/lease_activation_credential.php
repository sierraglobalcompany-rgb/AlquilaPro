<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="surface">
    <p class="eyebrow">Arrendamientos</p>
    <h1>Arrendamiento activado</h1>
    <p>Correo de acceso: <strong><?= $escape($lease['tenant_email']) ?></strong></p>
    <p class="hint">Esta contraseña temporal se muestra una sola vez. El inquilino deberá cambiarla al ingresar.</p>
    <p><code data-temporary-password><?= $escape($temporaryPassword) ?></code></p>
    <a class="button" href="<?= $escape($basePath) ?>/admin/contracts/<?= $escape($lease['contract_id']) ?>">Volver al contrato</a>
</section>
