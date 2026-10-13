<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var LeaseService $leases */
    $leases = $deps['leases'];
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

    $adminGuard = static function (ResponseInterface $response) use ($currentUser, $session, $path): array|ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            return $response->withHeader('Location', $path('/login'))->withStatus(302);
        }
        if ($session->mustChangePassword()) {
            return $response->withHeader('Location', $path('/account/password'))->withStatus(302);
        }
        if (!Permissions::allows($user, 'contracts.manage')) {
            return $response->withStatus(403);
        }

        return $user;
    };

    $app->post('/admin/contracts/{id:[0-9]+}/lease', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $leases, $view, $csrf): ResponseInterface {
        $user = $adminGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        try {
            $activated = $leases->activate((int) $args['id'], (int) $user['id'], time());
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        $lease = $activated['lease'];
        $response->getBody()->write($view->renderApp('lease_activation_credential', [
            'pageTitle' => 'Arrendamiento activado',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'lease' => $lease,
            'temporaryPassword' => $activated['temporary_password'],
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->post('/admin/leases/{id:[0-9]+}/end', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $leases, $path): ResponseInterface {
        $user = $adminGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        try {
            $lease = $leases->end((int) $args['id'], (int) $user['id'], time());
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/contracts/' . (int) $lease['contract_id']))
            ->withStatus(303);
    });
};