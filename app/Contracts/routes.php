<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var ContractService $contracts */
    $contracts = $deps['contracts'];
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

    $adminGuard = static function (
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

    $app->get('/admin/contract-templates', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($adminGuard, $contracts, $view, $csrf): ResponseInterface {
        $user = $adminGuard($response, 'contracts.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $response->getBody()->write($view->renderApp('contract_templates', [
            'pageTitle' => 'Plantillas de contrato',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'templates' => $contracts->listTemplates(),
            'canManage' => Permissions::allows($user, 'contracts.manage'),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/admin/contract-templates', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($adminGuard, $contracts, $path): ResponseInterface {
        $user = $adminGuard($response, 'contracts.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        try {
            $created = $contracts->createTemplate((int) $user['id'], (array) ($request->getParsedBody() ?? []));
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/contract-templates/' . (int) $created['id']))
            ->withStatus(303);
    });

    $app->get('/admin/contract-templates/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $contracts, $view, $csrf): ResponseInterface {
        $user = $adminGuard($response, 'contracts.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $template = $contracts->findTemplate((int) $args['id']);
        if ($template === null) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($view->renderApp('contract_template_detail', [
            'pageTitle' => 'Plantilla de contrato',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'template' => $template,
            'versions' => $contracts->listTemplateVersions((int) $template['id']),
            'canManage' => Permissions::allows($user, 'contracts.manage'),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/admin/contract-templates/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $contracts, $path): ResponseInterface {
        $user = $adminGuard($response, 'contracts.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $templateId = (int) $args['id'];
        $body = (array) ($request->getParsedBody() ?? []);

        try {
            $action = trim((string) ($body['action'] ?? ''));
            if ($action === 'toggle') {
                $active = (string) ($body['active'] ?? '');
                if (!in_array($active, ['0', '1'], true)) {
                    throw new RuntimeException('La acción contractual no es válida.');
                }
                $contracts->setTemplateActive($templateId, $active === '1');
            } elseif ($action === 'new_version') {
                $rawVariables = (string) ($body['variables_json'] ?? '[]');
                try {
                    $variables = json_decode($rawVariables, true, 64, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new RuntimeException('Las variables manuales no son válidas.');
                }
                if (!is_array($variables)) {
                    throw new RuntimeException('Las variables manuales no son válidas.');
                }

                $contracts->createTemplateVersion($templateId, (int) $user['id'], [
                    'body_template' => $body['body_template'] ?? '',
                    'variables' => $variables,
                ]);
            } else {
                throw new RuntimeException('La acción contractual no es válida.');
            }
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/contract-templates/' . $templateId))
            ->withStatus(303);
    });
};