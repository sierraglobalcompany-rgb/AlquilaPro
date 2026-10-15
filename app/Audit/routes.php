<?php

declare(strict_types=1);

use AlquilaPro\Audit\AuditService;
use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var AuditService $audit */
    $audit = $deps['audit'];
    /** @var View $view */
    $view = $deps['view'];
    /** @var SessionGuard $session */
    $session = $deps['session'];
    /** @var Csrf $csrf */
    $csrf = $deps['csrf'];
    /** @var Closure(): ?array $currentUser */
    $currentUser = $deps['current_user'];
    $basePath = (string) ($deps['base_path'] ?? '');

    $path = static fn (string $value): string => $basePath . $value;

    $guard = static function (ResponseInterface $response) use ($currentUser, $session, $path): array|ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            return $response->withHeader('Location', $path('/login'))->withStatus(302);
        }
        if ($session->mustChangePassword()) {
            return $response->withHeader('Location', $path('/account/password'))->withStatus(302);
        }

        return $user;
    };

    $app->get('/admin/audit', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($guard, $view, $csrf, $audit): ResponseInterface {
        $user = $guard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        if (($user['role'] ?? null) !== 'owner') {
            return $response->withStatus(403);
        }

        $response->getBody()->write($view->renderApp('audit_log', [
            'pageTitle' => 'Auditoría',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'auditRows' => $audit->listRecent(100),
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });
};
