<?php

declare(strict_types=1);

$iconPaths = [
    'paw' => '<ellipse cx="7" cy="7" rx="2" ry="3"></ellipse><ellipse cx="17" cy="7" rx="2" ry="3"></ellipse><ellipse cx="3.5" cy="12" rx="1.5" ry="2.5"></ellipse><ellipse cx="20.5" cy="12" rx="1.5" ry="2.5"></ellipse><path d="M7 16c1-1 2-5 5-5s4 4 5 5c3 5-1 6-5 4-4 2-8 1-5-4z"></path>',
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
    'briefcase' => '<rect x="3" y="7" width="18" height="14" rx="2"></rect><path d="M8 7V3h8v4M3 12h18M10 12v3h4v-3"></path>',
    'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M7 3v4M17 3v4M3 11h18M7 15h.01M12 15h.01M17 15h.01"></path>',
    'map-pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"></path><circle cx="12" cy="10" r="3"></circle>',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 6 9 7 9-7"></path>',
    'phone' => '<path d="M7 3H3v4a14 14 0 0 0 14 14h4v-4l-5-2-2 2a10 10 0 0 1-7-7l2-2z"></path>',
    'settings' => '<path d="m9 3-1 3-3 1-2 5 2 5 3 1 1 3h6l1-3 3-1 2-5-2-5-3-1-1-3z"></path><circle cx="12" cy="12" r="3"></circle>',
    'id-card' => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="8" cy="10" r="2"></circle><path d="M5 16a3 3 0 0 1 6 0M14 9h4M14 13h4M14 17h3"></path>',
];
return static function (string $name) use ($iconPaths): string {
    $paths = $iconPaths[$name] ?? '<circle cx="12" cy="12" r="9"></circle><path d="M12 8v8M8 12h8"></path>';

    return '<svg class="nav-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
};
