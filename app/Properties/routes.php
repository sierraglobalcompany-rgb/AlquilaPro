<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Properties\PropertyService;
use AlquilaPro\Properties\UnitMediaService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var PropertyService $properties */
    $properties = $deps['properties'];
    /** @var UnitMediaService $media */
    $media = $deps['unit_media'];
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

    $guard = static function (
        ResponseInterface $response,
        string $permission,
    ) use ($currentUser, $session, $path): array|ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            return $response->withHeader('Location', $path('/login'))->withStatus(302);
        }

        if ($session->mustChangePassword()) {
            return $response->withHeader('Location', $path('/account/password'))->withStatus(302);
        }

        if (!Permissions::allows($user, $permission)) {
            return $response->withStatus(403);
        }

        return $user;
    };

    $app->get('/admin/properties', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($guard, $view, $properties, $csrf): ResponseInterface {
        $user = $guard($response, 'properties.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $response->getBody()->write($view->renderApp('properties', [
            'pageTitle' => 'Propiedades',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'properties' => $properties->listProperties(),
            'canManage' => Permissions::allows($user, 'properties.manage'),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/admin/properties/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $view, $properties, $csrf): ResponseInterface {
        $user = $guard($response, 'properties.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $property = $properties->findProperty((int) $args['id']);
        if ($property === null) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($view->renderApp('property_detail', [
            'pageTitle' => $property['name'],
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'property' => $property,
            'units' => $properties->listUnitsForProperty($property['id']),
            'canManage' => Permissions::allows($user, 'properties.manage'),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/admin/units/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $view, $properties, $media, $csrf): ResponseInterface {
        $user = $guard($response, 'properties.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $unit = $properties->findUnit((int) $args['id']);
        if ($unit === null) {
            return $response->withStatus(404);
        }
        $property = $properties->findProperty($unit['property_id']);
        if ($property === null) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($view->renderApp('unit_detail', [
            'pageTitle' => $unit['code'],
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'property' => $property,
            'unit' => $unit,
            'media' => $media->listForUnit($unit['id']),
            'canManage' => Permissions::allows($user, 'properties.manage'),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/admin/units/{id:[0-9]+}/media/{mediaId:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $properties, $media): ResponseInterface {
        $user = $guard($response, 'properties.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $unitId = (int) $args['id'];
        if ($properties->findUnit($unitId) === null) {
            return $response->withStatus(404);
        }

        $photo = $media->privatePhoto($unitId, (int) $args['mediaId']);
        if ($photo === null) {
            return $response->withStatus(404);
        }

        $bytes = file_get_contents($photo['path']);
        if ($bytes === false) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($bytes);
        return $response
            ->withHeader('Content-Type', $photo['mime'])
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->post('/admin/properties', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($guard): ResponseInterface {
        $user = $guard($response, 'properties.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        return $response->withStatus(501);
    });
};
