<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$nameParts = preg_split('/\s+/', trim((string) ($user['full_name'] ?? ''))) ?: [];
$initials = '';
foreach (array_slice($nameParts, 0, 2) as $part) {
    if ($part !== '') {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
$initials = $initials !== '' ? $initials : 'AP';
$canViewUsers = \AlquilaPro\Auth\Permissions::allows($user, 'users.view');
$canViewProperties = \AlquilaPro\Auth\Permissions::allows($user, 'properties.view');
$canViewCandidates = \AlquilaPro\Auth\Permissions::allows($user, 'candidates.view');
$canViewContracts = \AlquilaPro\Auth\Permissions::allows($user, 'contracts.view');
$isOwner = ($user['role'] ?? null) === 'owner';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= $escape($pageTitle !== '' ? $pageTitle . ' · ' . $branding['app_name'] : $branding['app_name']) ?></title>
    <link rel="stylesheet" href="<?= $escape($basePath) ?>/assets/app.css">
</head>
<body class="app-page" style="--brand-primary: <?= $escape($branding['primary_color']) ?>;">
<div class="app-shell" data-f3-app-shell>
    <header class="topbar">
        <a class="topbar__brand" href="<?= $escape($basePath) ?>/">
            <?php if ($branding['logo_path'] !== null): ?>
                <img class="topbar__logo" src="<?= $escape($basePath) ?>/brand/logo" alt="<?= $escape($branding['app_name']) ?>">
            <?php else: ?>
                <?= $escape($branding['app_name']) ?>
            <?php endif; ?>
        </a>
        <a class="topbar__account" href="<?= $escape($basePath) ?>/account" aria-label="Abrir cuenta"><?= $escape($initials) ?></a>
    </header>

    <main class="app-main">
        <?= $content ?>
    </main>

    <nav class="bottom-nav" aria-label="Navegación principal">
        <a class="bottom-nav__item" href="<?= $escape($basePath) ?>/"<?= $activeNav === 'home' ? ' aria-current="page"' : '' ?>>Inicio</a>
        <button class="bottom-nav__apps" type="button" data-apps-open aria-controls="apps-dialog" aria-haspopup="dialog"<?= $activeNav === 'apps' ? ' aria-current="page"' : '' ?>>Apps</button>
        <a class="bottom-nav__item" href="<?= $escape($basePath) ?>/account"<?= $activeNav === 'account' ? ' aria-current="page"' : '' ?>>Cuenta</a>
    </nav>

    <dialog class="apps-dialog" id="apps-dialog" aria-labelledby="apps-dialog-title">
        <div class="apps-dialog__head">
            <h2 id="apps-dialog-title">Apps</h2>
            <form method="dialog"><button class="button button--quiet" type="submit">Cerrar</button></form>
        </div>
        <div class="apps-grid">
            <?php if ($canViewUsers): ?>
                <a class="app-card" href="<?= $escape($basePath) ?>/admin/users">
                    <strong>Usuarios</strong>
                    <span>Administración de accesos</span>
                </a>
            <?php endif; ?>
            <?php if ($canViewProperties): ?>
                <a class="app-card" href="<?= $escape($basePath) ?>/admin/properties">
                    <strong>Propiedades</strong>
                    <span>Inventario y publicación</span>
                </a>
            <?php endif; ?>
            <?php if ($canViewCandidates): ?>
                <a class="app-card" href="<?= $escape($basePath) ?>/admin/candidates">
                    <strong>Candidatos</strong>
                    <span>Candidaturas y documentación</span>
                </a>
            <?php endif; ?>
            <?php if ($canViewContracts): ?>
                <a class="app-card" href="<?= $escape($basePath) ?>/admin/contract-templates">
                    <strong>Contratos</strong>
                    <span>Plantillas y versiones</span>
                </a>
            <?php endif; ?>
            <?php if ($isOwner): ?>
                <a class="app-card" href="<?= $escape($basePath) ?>/admin/appearance">
                    <strong>Apariencia</strong>
                    <span>Nombre, color y logo</span>
                </a>
            <?php endif; ?>
            <?php if (!$canViewUsers && !$canViewProperties && !$canViewCandidates && !$canViewContracts && !$isOwner): ?>
                <p class="empty-state">No tienes aplicaciones disponibles.</p>
            <?php endif; ?>
        </div>
    </dialog>
</div>
<script src="<?= $escape($basePath) ?>/assets/app.js" defer></script>
</body>
</html>