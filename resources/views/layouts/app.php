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
$iconPaths = [
    'home' => '<path d="m3 10 9-7 9 7"></path><path d="M5 9v12h14V9"></path><path d="M9 21v-7h6v7"></path>',
    'apps' => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect>',
    'account' => '<circle cx="12" cy="8" r="4"></circle><path d="M5 21a7 7 0 0 1 14 0"></path>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="10" cy="7" r="4"></circle><path d="M20 21v-2a4 4 0 0 0-3-3.87"></path><path d="M17 3.13a4 4 0 0 1 0 7.75"></path>',
    'building' => '<path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01M16 13v.01M16 16v.01M16 19v.01"></path>',
    'user-search' => '<circle cx="10" cy="8" r="4"></circle><path d="M3 21v-2a7 7 0 0 1 12.5-4.3"></path><circle cx="18" cy="18" r="3"></circle><path d="m20.2 20.2 1.8 1.8"></path>',
    'file-signature' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6M8 13h8M8 17h4"></path><path d="m15 17 2-2 2 2"></path>',
    'wallet' => '<rect x="3" y="5" width="18" height="15" rx="2"></rect><path d="M3 9h18"></path><path d="M16 14h.01"></path><path d="M7 5V3h11"></path>',
    'shield' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"></path><path d="m9 12 2 2 4-4"></path>',
    'wrench' => '<path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3 18l3 3 5.3-5.3a5 5 0 0 0 6.4-6.4L14 13l-3-3z"></path>',
    'message-circle' => '<path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 21l2.1-5.4A8.5 8.5 0 1 1 21 11.5z"></path>',
    'files' => '<path d="M8 7V3h12v14h-4"></path><rect x="4" y="7" width="12" height="14" rx="1"></rect><path d="M7 11h6M7 15h6"></path>',
    'home-alert' => '<path d="m3 10 9-7 9 7"></path><path d="M5 9v12h14V9"></path><path d="M12 11v4"></path><path d="M12 18h.01"></path>',
    'badge-check' => '<circle cx="12" cy="12" r="9"></circle><path d="m8 12 2.5 2.5L16 9"></path>',
    'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path><path d="M3 3v5h5M12 7v5l3 2"></path>',
    'palette' => '<path d="M12 3a9 9 0 1 0 0 18h1.2a2 2 0 0 0 1.4-3.4 1.8 1.8 0 0 1 1.3-3.1H19a2 2 0 0 0 2-2A9 9 0 0 0 12 3z"></path><circle cx="7.5" cy="11" r=".8"></circle><circle cx="10" cy="7.5" r=".8"></circle><circle cx="15" cy="8" r=".8"></circle>',
];
$navigationIcon = static function (string $name) use ($iconPaths): string {
    $paths = $iconPaths[$name] ?? '<circle cx="12" cy="12" r="9"></circle><path d="M12 8v8M8 12h8"></path>';

    return '<svg class="nav-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
};
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
        <a class="topbar__brand" href="<?= $escape($basePath . $homeHref) ?>">
            <?php if ($branding['logo_path'] !== null): ?>
                <img class="topbar__logo" src="<?= $escape($basePath) ?>/brand/logo" alt="<?= $escape($branding['app_name']) ?>">
            <?php else: ?>
                <?= $escape($branding['app_name']) ?>
            <?php endif; ?>
        </a>
        <span class="topbar__context"><?= $escape($pageTitle !== '' ? $pageTitle : $branding['app_name']) ?></span>
        <a class="topbar__account" href="<?= $escape($basePath) ?>/account" aria-label="Abrir cuenta"><?= $navigationIcon('account') ?><span><?= $escape($initials) ?></span></a>
    </header>

    <nav class="desktop-sidebar" aria-label="Navegación de escritorio">
        <a class="desktop-sidebar__brand" href="<?= $escape($basePath . $homeHref) ?>">
            <?php if ($branding['logo_path'] !== null): ?>
                <img class="topbar__logo" src="<?= $escape($basePath) ?>/brand/logo" alt="<?= $escape($branding['app_name']) ?>">
            <?php else: ?>
                <?= $escape($branding['app_name']) ?>
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
