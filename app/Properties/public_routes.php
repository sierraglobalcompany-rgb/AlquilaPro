<?php

declare(strict_types=1);

use AlquilaPro\Properties\PropertyService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var PropertyService $properties */
    $properties = $deps['properties'];
    /** @var View $view */
    $view = $deps['view'];

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
};
