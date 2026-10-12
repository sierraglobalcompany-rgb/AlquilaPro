<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\UI\Branding;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var View $view */
    $view = $deps['view'];
    /** @var Branding $branding */
    $branding = $deps['branding'];
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

    $app->get('/', static function (ServerRequestInterface $request, ResponseInterface $response) use ($currentUser, $session, $path, $view, $csrf): ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            $response->getBody()->write($view->renderPublic('public_landing', [
                'pageTitle' => 'Inicio',
            ]));

            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        }

        if ($session->mustChangePassword()) {
            return $response->withHeader('Location', $path('/account/password'))->withStatus(302);
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

    $app->get('/brand/logo', static function (ServerRequestInterface $request, ResponseInterface $response) use ($branding): ResponseInterface {
        $logo = $branding->logoFile();
        if ($logo === null) {
            return $response->withStatus(404);
        }

        $bytes = file_get_contents($logo['path']);
        if ($bytes === false) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($bytes);
        return $response
            ->withHeader('Content-Type', $logo['mime'])
            ->withHeader('Cache-Control', 'private, max-age=300');
    });

    $app->get('/admin/appearance', static function (ServerRequestInterface $request, ResponseInterface $response) use ($guard, $view, $branding, $csrf): ResponseInterface {
        $user = $guard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        if (($user['role'] ?? null) !== 'owner') {
            return $response->withStatus(403);
        }

        $response->getBody()->write($view->renderApp('appearance', [
            'pageTitle' => 'Apariencia',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'currentBranding' => $branding->current(),
            'saved' => (string) ($request->getQueryParams()['saved'] ?? '') === '1',
            'error' => null,
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/admin/appearance', static function (ServerRequestInterface $request, ResponseInterface $response) use ($guard, $view, $branding, $csrf, $path): ResponseInterface {
        $user = $guard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        if (($user['role'] ?? null) !== 'owner') {
            return $response->withStatus(403);
        }

        $form = (array) ($request->getParsedBody() ?? []);
        $uploads = $request->getUploadedFiles();
        $logo = $uploads['logo'] ?? null;
        if ($logo instanceof UploadedFileInterface && $logo->getError() === UPLOAD_ERR_NO_FILE) {
            $logo = null;
        }

        try {
            $branding->update(
                (string) ($form['app_name'] ?? ''),
                (string) ($form['primary_color'] ?? ''),
                $logo instanceof UploadedFileInterface ? $logo : null,
            );
        } catch (RuntimeException $exception) {
            $response->getBody()->write($view->renderApp('appearance', [
                'pageTitle' => 'Apariencia',
                'user' => $user,
                'activeNav' => 'apps',
                'csrfToken' => $csrf->token(),
                'currentBranding' => $branding->current(),
                'saved' => false,
                'error' => $exception->getMessage(),
            ]));

            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/appearance?saved=1'))
            ->withStatus(303);
    });
};
