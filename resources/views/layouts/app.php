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
$isTenant = ($user['role'] ?? null) === 'tenant';
$homeHref = $isTenant ? '/tenant' : '/';
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';
$basePrefix = rtrim($basePath, '/');
if ($basePrefix !== '' && ($requestPath === $basePrefix || str_starts_with($requestPath, $basePrefix . '/'))) {
    $requestPath = substr($requestPath, strlen($basePrefix)) ?: '/';
}
$requestPath = rtrim($requestPath, '/') ?: '/';
$navigation = array_map(
    static function (array $item) use ($requestPath): array {
        $href = rtrim((string) $item['href'], '/') ?: '/';
        $item['current'] = $requestPath === $href || str_starts_with($requestPath, $href . '/');

        return $item;
    },
    $navigation,
);
$groupOrder = ['Operación' => 0, 'Finanzas' => 1, 'Gestión' => 2, 'Administración' => 3];
usort(
    $navigation,
    static function (array $left, array $right) use ($groupOrder): int {
        $groupOrderComparison = ($groupOrder[$left['group']] ?? 99) <=> ($groupOrder[$right['group']] ?? 99);

        return $groupOrderComparison !== 0
            ? $groupOrderComparison
            : strcmp((string) $left['label'], (string) $right['label']);
    },
);
$navigationIcon = require __DIR__ . '/../partials/icons.php';
$isCandidateSurface = !$isTenant && in_array($view ?? '', ['candidate_detail', 'candidates'], true);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= $escape($pageTitle !== '' ? $pageTitle . ' · ' . $branding['app_name'] : $branding['app_name']) ?></title>
    <link rel="stylesheet" href="<?= $escape($basePath) ?>/assets/app.css">
</head>
<body class="app-page<?= $isCandidateSurface ? ' candidate-surface' : '' ?>" style="--brand-primary: <?= $escape($branding['primary_color']) ?>;">
<div class="app-shell" data-f3-app-shell>
    <header class="topbar">
        <a class="topbar__brand" href="<?= $escape($basePath . $homeHref) ?>">
            <?php if ($branding['logo_path'] !== null): ?>
                <img class="topbar__logo" src="<?= $escape($basePath) ?>/brand/logo" alt="<?= $escape($branding['app_name']) ?>">
            <?php else: ?>
                <?php if ($isCandidateSurface): ?><span class="candidate-brand-mark"><?= $navigationIcon('home') ?></span><?php endif; ?><?= $escape($branding['app_name']) ?>
            <?php endif; ?>
        </a>
        <span class="topbar__context"><?= $escape($pageTitle !== '' ? $pageTitle : $branding['app_name']) ?></span>
        <a class="topbar__account" href="<?= $escape($basePath) ?>/account" aria-label="Abrir cuenta"><?php if ($isCandidateSurface): ?><span class="candidate-account-avatar"><?= $escape($initials) ?></span><span class="candidate-account-copy"><strong><?= $escape($user['full_name'] ?? '') ?></strong><small><?= $escape(match ($user['role'] ?? '') { 'owner' => 'Propietario', 'admin' => 'Administrador', 'gestor' => 'Gestor', default => 'Cuenta' }) ?></small></span><?php else: ?><?= $navigationIcon('account') ?><span><?= $escape($initials) ?></span><?php endif; ?></a>
    </header>

    <nav class="desktop-sidebar" aria-label="Navegación de escritorio">
        <a class="desktop-sidebar__brand" href="<?= $escape($basePath . $homeHref) ?>">
            <?php if ($branding['logo_path'] !== null): ?>
                <img class="topbar__logo" src="<?= $escape($basePath) ?>/brand/logo" alt="<?= $escape($branding['app_name']) ?>">
            <?php else: ?>
                <?php if ($isCandidateSurface): ?><span class="candidate-brand-mark"><?= $navigationIcon('home') ?></span><?php endif; ?><?= $escape($branding['app_name']) ?>
            <?php endif; ?>
        </a>
        <a class="desktop-sidebar__link desktop-sidebar__home" href="<?= $escape($basePath . $homeHref) ?>"<?= $activeNav === 'home' ? ' aria-current="page"' : '' ?>>
            <?= $navigationIcon('home') ?><span>Inicio</span>
        </a>
        <div class="desktop-sidebar__items">
            <?php $currentGroup = null; ?>
            <?php foreach ($navigation as $item): ?>
                <?php if ($currentGroup !== $item['group']): ?>
                    <p class="desktop-sidebar__group-label"><?= $escape($item['group']) ?></p>
                    <?php $currentGroup = $item['group']; ?>
                <?php endif; ?>
                <a class="desktop-sidebar__link" href="<?= $escape($basePath . $item['href']) ?>" data-nav-key="<?= $escape($item['key']) ?>"<?= ($item['current'] ?? false) ? ' aria-current="page"' : '' ?>>
                    <?= $navigationIcon((string) $item['icon']) ?><span><?= $escape($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <a class="desktop-sidebar__link desktop-sidebar__account" href="<?= $escape($basePath) ?>/account" aria-label="Abrir cuenta"<?= $activeNav === 'account' ? ' aria-current="page"' : '' ?>>
            <?= $navigationIcon('account') ?><span>Cuenta</span>
        </a>
    </nav>

    <main class="app-main">
        <?= $content ?>
    </main>

    <nav class="bottom-nav"<?= $isTenant ? ' bottom-nav--tenant' : '' ?> aria-label="Navegación principal">
        <a class="bottom-nav__item" href="<?= $escape($basePath . $homeHref) ?>"<?= $activeNav === 'home' ? ' aria-current="page"' : '' ?>>
            <?= $navigationIcon('home') ?><span>Inicio</span>
        </a>
        <?php if (!$isTenant): ?>
            <button class="bottom-nav__apps" type="button" data-apps-open aria-controls="apps-dialog" aria-haspopup="dialog" aria-label="Abrir aplicaciones" aria-expanded="false"<?= $activeNav === 'apps' ? ' aria-current="page"' : '' ?>>
                <?= $navigationIcon('apps') ?><span>Apps</span>
            </button>
        <?php endif; ?>
        <a class="bottom-nav__item" href="<?= $escape($basePath) ?>/account"<?= $activeNav === 'account' ? ' aria-current="page"' : '' ?>>
            <?= $navigationIcon('account') ?><span>Cuenta</span>
        </a>
    </nav>

    <?php if (!$isTenant): ?>
        <dialog class="apps-dialog" id="apps-dialog" aria-labelledby="apps-dialog-title">
            <div class="apps-dialog__head">
                <h2 id="apps-dialog-title">Aplicaciones</h2>
                <form method="dialog"><button class="button button--quiet" type="submit">Cerrar</button></form>
            </div>
            <div class="apps-grid">
                <?php foreach ($navigation as $item): ?>
                    <a class="app-card" href="<?= $escape($basePath . $item['href']) ?>" data-nav-key="<?= $escape($item['key']) ?>">
                        <?= $navigationIcon((string) $item['icon']) ?>
                        <span class="app-card__copy"><strong><?= $escape($item['label']) ?></strong><span><?= $escape($item['group']) ?></span></span>
                    </a>
                <?php endforeach; ?>
                <?php if ($navigation === []): ?>
                    <p class="empty-state">No tienes aplicaciones disponibles.</p>
                <?php endif; ?>
            </div>
        </dialog>
    <?php endif; ?>
</div>
<script src="<?= $escape($basePath) ?>/assets/app.js" defer></script>
</body>
</html>
