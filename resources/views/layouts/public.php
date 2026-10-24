<?php

declare(strict_types=1);

/** @var string $content */
/** @var array{app_name:string, primary_color:string, logo_path:?string} $branding */
/** @var string $basePath */
/** @var string $pageTitle */

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$title = $pageTitle !== '' ? $pageTitle . ' · ' . $branding['app_name'] : $branding['app_name'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= $escape($title) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $escape($basePath) ?>/assets/favicon.svg">
    <link rel="stylesheet" href="<?= $escape($basePath) ?>/assets/app.css">
</head>
<body class="public-page" style="--brand-primary: <?= $escape($branding['primary_color']) ?>;">
<div class="public-shell" data-public-shell>
    <main class="public-main">
        <a class="auth-brand" href="<?= $escape($basePath) ?>/" aria-label="<?= $escape($branding['app_name']) ?>">
            <?php if ($branding['logo_path'] !== null): ?>
                <img class="auth-brand__logo" src="<?= $escape($basePath) ?>/brand/logo" alt="<?= $escape($branding['app_name']) ?>">
            <?php else: ?>
                <span class="auth-brand__mark" aria-hidden="true">A</span>
                <span><?= $escape($branding['app_name']) ?></span>
            <?php endif; ?>
        </a>
        <?= $content ?>
    </main>
</div>
<script src="<?= $escape($basePath) ?>/assets/app.js" defer></script>
</body>
</html>
