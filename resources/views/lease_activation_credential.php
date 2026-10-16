<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="surface">
    <p class="eyebrow">Arrendamientos</p>
    <h1>Arrendamiento activado</h1>
    <p>Usuario / cédula: <strong data-tenant-identifier><?= $escape($temporaryPassword) ?></strong></p>
    <p>Contraseña inicial: <code data-temporary-password><?= $escape($temporaryPassword) ?></code></p>
    <p class="hint">El inquilino puede cambiar su contraseña después si lo desea.</p>
    <p>Correo de contacto: <strong><?= $escape($lease['tenant_email']) ?></strong></p>
    <a class="button" href="<?= $escape($basePath) ?>/admin/contracts/<?= $escape($lease['contract_id']) ?>">Volver al contrato</a>
</section>
