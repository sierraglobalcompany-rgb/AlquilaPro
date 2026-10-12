<?php

declare(strict_types=1);

use AlquilaPro\Auth\AuthService;
use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Auth\UserService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var AuthService $auth */
    $auth = $deps['auth'];
    /** @var UserService $users */
    $users = $deps['users'];
    /** @var SessionGuard $session */
    $session = $deps['session'];
    /** @var Csrf $csrf */
    $csrf = $deps['csrf'];
    /** @var View $view */
    $view = $deps['view'];
    /** @var Closure(): ?array $currentUser */
    $currentUser = $deps['current_user'];
    $basePath = (string) ($deps['base_path'] ?? '');
    $baseUrl = rtrim((string) ($deps['base_url'] ?? ''), '/');

    $path = static fn (string $value): string => $basePath . $value;

    $guard = static function (ResponseInterface $response, ?string $permission = null) use ($currentUser, $session, $path): array|ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            return $response->withHeader('Location', $path('/login'))->withStatus(302);
        }

        if ($session->mustChangePassword()) {
            return $response->withHeader('Location', $path('/account/password'))->withStatus(302);
        }

        if ($permission !== null && !Permissions::allows($user, $permission)) {
            return $response->withStatus(403);
        }

        return $user;
    };

    $app->get('/login', static function (ServerRequestInterface $request, ResponseInterface $response) use ($view, $csrf, $path): ResponseInterface {
        $response->getBody()->write($view->renderAuth('login', [
            'pageTitle' => 'Iniciar sesión',
            'action' => $path('/login'),
            'csrfToken' => $csrf->token(),
            'error' => null,
        ]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/login', static function (ServerRequestInterface $request, ResponseInterface $response) use ($auth, $session, $view, $csrf, $path): ResponseInterface {
        $form = (array) ($request->getParsedBody() ?? []);
        $user = $auth->authenticate(
            (string) ($form['email'] ?? ''),
            (string) ($form['password'] ?? ''),
            new DateTimeImmutable(),
        );

        if ($user === null) {
            $response->getBody()->write($view->renderAuth('login', [
                'pageTitle' => 'Iniciar sesión',
                'action' => $path('/login'),
                'csrfToken' => $csrf->token(),
                'error' => 'No fue posible iniciar sesión con esas credenciales.',
            ]));
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
        }

        $session->login($user, time());
        $csrf->rotate();
        $destination = $user['must_change_password'] ? $path('/account/password') : $path('/');
        return $response->withHeader('Location', $destination)->withStatus(303);
    });

    $app->post('/logout', static function (ServerRequestInterface $request, ResponseInterface $response) use ($session, $csrf, $path): ResponseInterface {
        $session->logout();
        $csrf->rotate();
        return $response->withHeader('Location', $path('/login'))->withStatus(303);
    });

    $app->get('/account/password', static function (ServerRequestInterface $request, ResponseInterface $response) use ($currentUser, $view, $csrf, $path): ResponseInterface {
        if ($currentUser() === null) {
            return $response->withHeader('Location', $path('/login'))->withStatus(302);
        }

        $response->getBody()->write($view->renderAuth('change_password', [
            'pageTitle' => 'Cambiar contraseña',
            'action' => $path('/account/password'),
            'csrfToken' => $csrf->token(),
            'error' => null,
        ]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/account/password', static function (ServerRequestInterface $request, ResponseInterface $response) use ($currentUser, $auth, $session, $view, $csrf, $path): ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            return $response->withHeader('Location', $path('/login'))->withStatus(302);
        }

        $form = (array) ($request->getParsedBody() ?? []);
        $changed = $auth->changePassword(
            (int) $user['id'],
            (string) ($form['current_password'] ?? ''),
            (string) ($form['new_password'] ?? ''),
        );

        if (!$changed) {
            $response->getBody()->write($view->renderAuth('change_password', [
                'pageTitle' => 'Cambiar contraseña',
                'action' => $path('/account/password'),
                'csrfToken' => $csrf->token(),
                'error' => 'No fue posible cambiar la contraseña.',
            ]));
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
        }

        $updated = $auth->findUser((int) $user['id']);
        if ($updated !== null) {
            $session->login($updated, time());
        }

        $csrf->rotate();
        return $response->withHeader('Location', $path('/'))->withStatus(303);
    });

    $app->get('/reset-password', static function (ServerRequestInterface $request, ResponseInterface $response) use ($view, $csrf, $path): ResponseInterface {
        $token = (string) ($request->getQueryParams()['token'] ?? '');
        $response->getBody()->write($view->renderAuth('reset_password', [
            'pageTitle' => 'Restablecer contraseña',
            'action' => $path('/reset-password'),
            'csrfToken' => $csrf->token(),
            'token' => $token,
            'error' => null,
        ]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/reset-password', static function (ServerRequestInterface $request, ResponseInterface $response) use ($auth, $view, $csrf, $path): ResponseInterface {
        $form = (array) ($request->getParsedBody() ?? []);
        $token = (string) ($form['token'] ?? '');
        $changed = $auth->resetPassword(
            $token,
            (string) ($form['new_password'] ?? ''),
            new DateTimeImmutable(),
        );

        if (!$changed) {
            $response->getBody()->write($view->renderAuth('reset_password', [
                'pageTitle' => 'Restablecer contraseña',
                'action' => $path('/reset-password'),
                'csrfToken' => $csrf->token(),
                'token' => $token,
                'error' => 'El enlace no es válido o ya venció.',
            ]));
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
        }

        $csrf->rotate();
        return $response->withHeader('Location', $path('/login'))->withStatus(303);
    });

    $app->get('/admin/users', static function (ServerRequestInterface $request, ResponseInterface $response) use ($guard, $users, $view, $csrf): ResponseInterface {
        $authorized = $guard($response, 'users.view');
        if ($authorized instanceof ResponseInterface) {
            return $authorized;
        }

        $response->getBody()->write($view->renderApp('admin_users', [
            'pageTitle' => 'Usuarios',
            'user' => $authorized,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'users' => $users->listUsers(),
            'canManageUsers' => Permissions::allows($authorized, 'users.manage'),
        ]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/admin/users', static function (ServerRequestInterface $request, ResponseInterface $response) use ($guard, $users, $path): ResponseInterface {
        $authorized = $guard($response, 'users.manage');
        if ($authorized instanceof ResponseInterface) {
            return $authorized;
        }

        $form = (array) ($request->getParsedBody() ?? []);
        $requestedPermissions = is_array($form['permissions'] ?? null) ? $form['permissions'] : [];
        if ($requestedPermissions !== [] && !Permissions::allows($authorized, 'users.permissions')) {
            return $response->withStatus(403);
        }

        try {
            $users->create(
                (string) ($form['full_name'] ?? ''),
                (string) ($form['email'] ?? ''),
                (string) ($form['role'] ?? ''),
                (string) ($form['temporary_password'] ?? ''),
                $requestedPermissions,
            );
        } catch (Throwable) {
            return $response->withStatus(422);
        }

        return $response->withHeader('Location', $path('/admin/users'))->withStatus(303);
    });

    $app->post('/admin/users/{id}/status', static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($guard, $users, $path): ResponseInterface {
        $authorized = $guard($response, 'users.manage');
        if ($authorized instanceof ResponseInterface) {
            return $authorized;
        }

        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $users->setActive((int) ($args['id'] ?? 0), (string) ($form['active'] ?? '0') === '1');
        } catch (Throwable) {
            return $response->withStatus(422);
        }

        return $response->withHeader('Location', $path('/admin/users'))->withStatus(303);
    });

    $app->post('/admin/users/{id}/permissions', static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($guard, $users, $path): ResponseInterface {
        $authorized = $guard($response, 'users.permissions');
        if ($authorized instanceof ResponseInterface) {
            return $authorized;
        }

        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $users->setPermissions(
                (int) ($args['id'] ?? 0),
                is_array($form['permissions'] ?? null) ? $form['permissions'] : [],
            );
        } catch (Throwable) {
            return $response->withStatus(422);
        }

        return $response->withHeader('Location', $path('/admin/users'))->withStatus(303);
    });

    $app->post('/admin/users/{id}/reset-link', static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($guard, $auth, $baseUrl, $path): ResponseInterface {
        $authorized = $guard($response, 'users.manage');
        if ($authorized instanceof ResponseInterface) {
            return $authorized;
        }

        try {
            $token = $auth->issueResetToken((int) ($args['id'] ?? 0), new DateTimeImmutable());
        } catch (Throwable) {
            return $response->withStatus(422);
        }

        $link = ($baseUrl !== '' ? $baseUrl : '') . $path('/reset-password') . '?token=' . $token;
        $response->getBody()->write($link);
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    });
};
