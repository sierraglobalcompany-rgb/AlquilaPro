<?php

declare(strict_types=1);

namespace AlquilaPro\UI;

use AlquilaPro\Auth\Permissions;

final class View
{
    public function __construct(
        private readonly string $viewsPath,
        private readonly Branding $branding,
        private readonly string $basePath,
    ) {
    }

    public function renderAuth(string $view, array $data = []): string
    {
        $content = $this->renderPartial($view, $data);
        $branding = $this->branding->current();
        $basePath = $this->basePath;
        $pageTitle = trim((string) ($data['pageTitle'] ?? ''));

        ob_start();
        require $this->viewsPath . '/layouts/auth.php';

        return (string) ob_get_clean();
    }

    public function renderPublic(string $view, array $data = []): string
    {
        $content = $this->renderPartial($view, $data);
        $branding = $this->branding->current();
        $basePath = $this->basePath;
        $pageTitle = trim((string) ($data['pageTitle'] ?? ''));

        ob_start();
        require $this->viewsPath . '/layouts/public.php';

        return (string) ob_get_clean();
    }

    public function renderApp(string $view, array $data = []): string
    {
        $content = $this->renderPartial($view, $data);
        $branding = $this->branding->current();
        $basePath = $this->basePath;
        $pageTitle = trim((string) ($data['pageTitle'] ?? ''));
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];
        $activeNav = (string) ($data['activeNav'] ?? '');
        $csrfToken = (string) ($data['csrfToken'] ?? '');
        $navigation = $this->navigationFor($user);

        ob_start();
        require $this->viewsPath . '/layouts/app.php';

        return (string) ob_get_clean();
    }

    private function navigationFor(array $user): array
    {
        if (($user['role'] ?? null) === 'tenant') {
            return [];
        }

        $isOwner = ($user['role'] ?? null) === 'owner';
        $definitions = [
            ['key' => 'users', 'label' => 'Usuarios', 'href' => '/admin/users', 'icon' => 'users', 'group' => 'Administración', 'permission' => 'users.view'],
            ['key' => 'properties', 'label' => 'Propiedades', 'href' => '/admin/properties', 'icon' => 'building', 'group' => 'Operación', 'permission' => 'properties.view'],
            ['key' => 'candidates', 'label' => 'Candidatos', 'href' => '/admin/candidates', 'icon' => 'user-search', 'group' => 'Operación', 'permission' => 'candidates.view'],
            ['key' => 'contracts', 'label' => 'Contratos', 'href' => '/admin/contract-templates', 'icon' => 'file-signature', 'group' => 'Operación', 'permission' => 'contracts.view'],
            ['key' => 'finance', 'label' => 'Cartera', 'href' => '/admin/finance', 'icon' => 'wallet', 'group' => 'Finanzas', 'permission' => 'finance.view'],
            ['key' => 'insurance', 'label' => 'Seguros', 'href' => '/admin/insurance', 'icon' => 'shield', 'group' => 'Gestión', 'permission' => 'insurance.view'],
            ['key' => 'maintenance', 'label' => 'Mantenimiento', 'href' => '/admin/maintenance', 'icon' => 'wrench', 'group' => 'Gestión', 'permission' => 'maintenance.view'],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'href' => '/admin/whatsapp', 'icon' => 'message-circle', 'group' => 'Gestión', 'permission' => 'whatsapp.use'],
            ['key' => 'documents', 'label' => 'Documentos', 'href' => '/admin/documents', 'icon' => 'files', 'group' => 'Gestión', 'permission' => 'documents.view'],
            ['key' => 'community', 'label' => 'Convivencia y avisos legales', 'href' => '/admin/community', 'icon' => 'home-alert', 'group' => 'Gestión', 'permission' => 'community.view'],
            ['key' => 'memberships', 'label' => 'Servicios digitales', 'href' => '/admin/memberships', 'icon' => 'badge-check', 'group' => 'Gestión', 'permission' => 'memberships.view'],
            ['key' => 'audit', 'label' => 'Auditoría', 'href' => '/admin/audit', 'icon' => 'history', 'group' => 'Administración', 'owner_only' => true],
            ['key' => 'appearance', 'label' => 'Apariencia', 'href' => '/admin/appearance', 'icon' => 'palette', 'group' => 'Administración', 'owner_only' => true],
        ];

        $navigation = [];
        foreach ($definitions as $item) {
            $ownerOnly = ($item['owner_only'] ?? false) === true;
            if ($ownerOnly) {
                if (!$isOwner) {
                    continue;
                }
            } elseif (!Permissions::allows($user, (string) $item['permission'])) {
                continue;
            }

            $navigation[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'href' => $item['href'],
                'icon' => $item['icon'],
                'group' => $item['group'],
            ];
        }

        return $navigation;
    }

    private function renderPartial(string $view, array $data): string
    {
        if (preg_match('/^[a-z0-9_]+$/', $view) !== 1) {
            throw new \RuntimeException('Vista no válida.');
        }

        extract($data, EXTR_SKIP);
        $basePath = $this->basePath;
        ob_start();
        require $this->viewsPath . '/' . $view . '.php';

        return (string) ob_get_clean();
    }
}
