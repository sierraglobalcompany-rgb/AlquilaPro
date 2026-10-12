<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Candidates\CandidateDocumentService;
use AlquilaPro\Properties\PropertyService;
use AlquilaPro\Properties\UnitMediaService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var PropertyService $properties */
    $properties = $deps['properties'];
    /** @var UnitMediaService $media */
    $media = $deps['unit_media'];
    /** @var CandidateDocumentService $candidateDocuments */
    $candidateDocuments = $deps['candidate_documents'];
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

    $app->get('/catalog/{publicKey}/photo/{position}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($media): ResponseInterface {
        $publicKey = (string) ($args['publicKey'] ?? '');
        $rawPosition = (string) ($args['position'] ?? '');
        if (!ctype_digit($rawPosition) || (int) $rawPosition < 1) {
            return $response->withStatus(404);
        }

        $photo = $media->publicPhoto($publicKey, (int) $rawPosition);
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
            ->withHeader('Cache-Control', 'no-store, max-age=0');
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
            'photoPositions' => $media->publicPhotoPositions($publicKey),
            'videoLinks' => $media->publicVideoLinks($publicKey),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

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

    $unitViewGuard = static function (ResponseInterface $response) use ($currentUser, $session, $path): array|ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            return $response->withHeader('Location', $path('/login'))->withStatus(302);
        }
        if ($session->mustChangePassword()) {
            return $response->withHeader('Location', $path('/account/password'))->withStatus(302);
        }
        if (!Permissions::allows($user, 'properties.view') && !Permissions::allows($user, 'candidates.manage')) {
            return $response->withStatus(403);
        }

        return $user;
    };

    $renderProperties = static function (
        ResponseInterface $response,
        array $user,
        ?string $error = null,
        int $status = 200,
    ) use ($view, $properties, $csrf): ResponseInterface {
        $response->getBody()->write($view->renderApp('properties', [
            'pageTitle' => 'Propiedades',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'properties' => $properties->listProperties(),
            'canManage' => Permissions::allows($user, 'properties.manage'),
            'error' => $error,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($status);
    };

    $renderProperty = static function (
        ResponseInterface $response,
        array $user,
        int $propertyId,
        ?string $error = null,
        int $status = 200,
    ) use ($view, $properties, $csrf): ResponseInterface {
        $property = $properties->findProperty($propertyId);
        if ($property === null) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($view->renderApp('property_detail', [
            'pageTitle' => $property['name'],
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'property' => $property,
            'units' => $properties->listUnitsForProperty($propertyId),
            'canManage' => Permissions::allows($user, 'properties.manage'),
            'error' => $error,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($status);
    };

    $renderUnit = static function (
        ResponseInterface $response,
        array $user,
        int $unitId,
        ?string $error = null,
        int $status = 200,
    ) use ($view, $properties, $media, $candidateDocuments, $csrf): ResponseInterface {
        $unit = $properties->findUnit($unitId);
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
            'propertyUnits' => $properties->listUnitsForProperty($unit['property_id']),
            'media' => $media->listForUnit($unitId),
            'applicationRequirements' => $candidateDocuments->listRequirementsForUnit($unitId),
            'canManage' => Permissions::allows($user, 'properties.manage'),
            'canViewPropertyDetails' => Permissions::allows($user, 'properties.view'),
            'canManageApplicationRequirements' => Permissions::allows($user, 'candidates.manage'),
            'error' => $error,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($status);
    };

    $app->get('/admin/properties', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($guard, $renderProperties): ResponseInterface {
        $user = $guard($response, 'properties.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        return $renderProperties($response, $user);
    });

    $app->post('/admin/properties', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($guard, $properties, $renderProperties, $path): ResponseInterface {
        $user = $guard($response, 'properties.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        try {
            $property = $properties->createProperty((array) ($request->getParsedBody() ?? []));
        } catch (RuntimeException $exception) {
            return $renderProperties($response, $user, $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/admin/properties/' . $property['id']))
            ->withStatus(303);
    });

    $app->get('/admin/properties/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $renderProperty): ResponseInterface {
        $user = $guard($response, 'properties.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        return $renderProperty($response, $user, (int) $args['id']);
    });

    $app->post('/admin/properties/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $properties, $renderProperty, $path): ResponseInterface {
        $user = $guard($response, 'properties.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $propertyId = (int) $args['id'];
        try {
            $properties->updateProperty($propertyId, (array) ($request->getParsedBody() ?? []));
        } catch (RuntimeException $exception) {
            return $renderProperty($response, $user, $propertyId, $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/admin/properties/' . $propertyId))
            ->withStatus(303);
    });

    $app->post('/admin/properties/{id:[0-9]+}/status', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $properties, $renderProperty, $path): ResponseInterface {
        $user = $guard($response, 'properties.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $propertyId = (int) $args['id'];
        $form = (array) ($request->getParsedBody() ?? []);
        $active = $form['active'] ?? null;
        if (!in_array($active, [0, 1, '0', '1', false, true], true)) {
            return $renderProperty($response, $user, $propertyId, 'El estado de la propiedad no es válido.', 422);
        }

        try {
            $properties->setPropertyActive($propertyId, in_array($active, [1, '1', true], true));
        } catch (RuntimeException $exception) {
            return $renderProperty($response, $user, $propertyId, $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/admin/properties/' . $propertyId))
            ->withStatus(303);
    });

    $app->post('/admin/properties/{id:[0-9]+}/units', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $properties, $renderProperty, $path): ResponseInterface {
        $user = $guard($response, 'properties.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $propertyId = (int) $args['id'];
        try {
            $unit = $properties->createUnit($propertyId, (array) ($request->getParsedBody() ?? []));
        } catch (RuntimeException $exception) {
            return $renderProperty($response, $user, $propertyId, $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/admin/units/' . $unit['id']))
            ->withStatus(303);
    });

    $app->get('/admin/units/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($unitViewGuard, $renderUnit): ResponseInterface {
        $user = $unitViewGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        return $renderUnit($response, $user, (int) $args['id']);
    });

    $app->post('/admin/units/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $properties, $renderUnit, $path): ResponseInterface {
        $user = $guard($response, 'properties.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $unitId = (int) $args['id'];
        try {
            $properties->updateUnit($unitId, (array) ($request->getParsedBody() ?? []));
        } catch (RuntimeException $exception) {
            return $renderUnit($response, $user, $unitId, $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/admin/units/' . $unitId))
            ->withStatus(303);
    });

    $app->post('/admin/units/{id:[0-9]+}/media/photo', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $media, $renderUnit, $path): ResponseInterface {
        $user = $guard($response, 'properties.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $unitId = (int) $args['id'];
        $upload = $request->getUploadedFiles()['photo'] ?? null;
        if (!$upload instanceof UploadedFileInterface || $upload->getError() === UPLOAD_ERR_NO_FILE) {
            return $renderUnit($response, $user, $unitId, 'Selecciona una foto.', 422);
        }

        try {
            $media->addPhoto($unitId, $upload);
        } catch (RuntimeException $exception) {
            return $renderUnit($response, $user, $unitId, $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/admin/units/' . $unitId))
            ->withStatus(303);
    });

    $app->post('/admin/units/{id:[0-9]+}/media/video', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $media, $renderUnit, $path): ResponseInterface {
        $user = $guard($response, 'properties.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $unitId = (int) $args['id'];
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $media->addVideoLink($unitId, (string) ($form['url'] ?? ''));
        } catch (RuntimeException $exception) {
            return $renderUnit($response, $user, $unitId, $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/admin/units/' . $unitId))
            ->withStatus(303);
    });

    $app->post('/admin/units/{id:[0-9]+}/media/{mediaId:[0-9]+}/delete', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $media, $renderUnit, $path): ResponseInterface {
        $user = $guard($response, 'properties.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $unitId = (int) $args['id'];
        try {
            $media->remove($unitId, (int) $args['mediaId']);
        } catch (RuntimeException $exception) {
            return $renderUnit($response, $user, $unitId, $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/admin/units/' . $unitId))
            ->withStatus(303);
    });

    $app->post('/admin/units/{id:[0-9]+}/media/{mediaId:[0-9]+}/cover', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $media, $renderUnit, $path): ResponseInterface {
        $user = $guard($response, 'properties.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $unitId = (int) $args['id'];
        try {
            $media->setCover($unitId, (int) $args['mediaId']);
        } catch (RuntimeException $exception) {
            return $renderUnit($response, $user, $unitId, $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/admin/units/' . $unitId))
            ->withStatus(303);
    });

    $app->post('/admin/units/{id:[0-9]+}/media/order', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($guard, $media, $renderUnit, $path): ResponseInterface {
        $user = $guard($response, 'properties.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $unitId = (int) $args['id'];
        $form = (array) ($request->getParsedBody() ?? []);
        $mediaIds = $form['media_ids'] ?? [];
        try {
            $media->reorder($unitId, is_array($mediaIds) ? $mediaIds : []);
        } catch (RuntimeException $exception) {
            return $renderUnit($response, $user, $unitId, $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/admin/units/' . $unitId))
            ->withStatus(303);
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
};
