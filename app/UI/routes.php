<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
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

    $app->get('/', static function (ServerRequestInterface $request, ResponseInterface $response) use ($guard, $view, $csrf): ResponseInterface {
        $user = $guard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $response->getBody()->write($view->renderApp('home', [
            'pageTitle' => 'Inicio',
            'user' => $user,
            'activeNav' => 'home',
            'csrfToken' => $csrf->token(),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/account', static function (ServerRequestInterface $request, ResponseInterface $response) use ($guard, $view, $csrf): ResponseInterface {
        $user = $guard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $response->getBody()->write($view->renderApp('account', [
            'pageTitle' => 'Cuenta',
            'user' => $user,
            'activeNav' => 'account',
            'csrfToken' => $csrf->token(),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
};
