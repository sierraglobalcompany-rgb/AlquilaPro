<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="surface">
    <p class="eyebrow">Apps</p>
    <h1>Apariencia</h1>
    <p class="hint">Personaliza el nombre, color principal y logo de la aplicación.</p>
</section>

<section class="surface">
    <?php if ($saved): ?>
        <p class="notice notice--success" role="status">Cambios guardados.</p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <p class="error" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= $escape($basePath) ?>/admin/appearance" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

        <label>Nombre de la aplicación
            <input name="app_name" value="<?= $escape($currentBranding['app_name']) ?>" maxlength="80" required>
        </label>

        <label>Color principal
            <input type="color" name="primary_color" value="<?= $escape($currentBranding['primary_color']) ?>" required>
        </label>

        <label>Logo opcional
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
        </label>
        <p class="hint">PNG, JPEG o WebP. Máximo 2 MiB.</p>

        <?php if ($currentBranding['logo_path'] !== null): ?>
            <div class="brand-preview">
                <img src="<?= $escape($basePath) ?>/brand/logo" alt="Logo actual de <?= $escape($currentBranding['app_name']) ?>">
            </div>
        <?php else: ?>
            <p class="hint">Actualmente se usa el nombre de la aplicación como marca.</p>
        <?php endif; ?>

        <button type="submit">Guardar apariencia</button>
    </form>
</section>
