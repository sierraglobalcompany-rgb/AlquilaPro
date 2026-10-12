<?php

declare(strict_types=1);

namespace AlquilaPro\UI;

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

        ob_start();
        require $this->viewsPath . '/layouts/app.php';

        return (string) ob_get_clean();
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
