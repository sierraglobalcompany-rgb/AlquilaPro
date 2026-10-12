<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
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

    $app->get('/', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($properties, $view, $currentUser, $session, $csrf, $path): ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            $response->getBody()->write($view->renderPublic('public_landing', [
                'pageTitle' => 'Inicio',
                'availabilityCount' => count($properties->listPublicUnits(null, null, null)),
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

    $app->get('/catalog', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($properties, $view): ResponseInterface {
        $query = $request->getQueryParams();
        $type = trim((string) ($query['type'] ?? ''));
        $city = trim((string) ($query['city'] ?? ''));
        $sector = trim((string) ($query['sector'] ?? ''));
        $filters = [
            'type' => $type,
            'city' => $city,
            'sector' => $sector,
        ];

        try {
            $units = $properties->listPublicUnits(
                $type === '' ? null : $type,
                $city === '' ? null : $city,
                $sector === '' ? null : $sector,
            );
            $error = null;
            $status = 200;
        } catch (RuntimeException $exception) {
            $units = [];
            $error = $exception->getMessage();
            $status = 422;
        }

        $response->getBody()->write($view->renderPublic('public_catalog', [
            'pageTitle' => 'Catálogo',
            'units' => $units,
            'filters' => $filters,
            'error' => $error,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($status);
    });

    $app->get('/catalog/{publicKey}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($properties, $media, $view): ResponseInterface {
        $publicKey = (string) ($args['publicKey'] ?? '');
        $unit = $properties->findPublicUnit($publicKey);
        if ($unit === null) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($view->renderPublic('public_catalog_detail', [
            'pageTitle' => $unit['property_name'],
            'unit' => $unit,
            'videoLinks' => $media->publicVideoLinks($publicKey),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
};
