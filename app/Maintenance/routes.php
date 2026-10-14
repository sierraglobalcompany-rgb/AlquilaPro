<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\Maintenance\MaintenanceService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var MaintenanceService $maintenance */
    $maintenance = $deps['maintenance'];
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

    $staffGuard = static function (
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
    $tenantGuard = static function (
        ResponseInterface $response,
    ) use ($currentUser, $session, $path): array|ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            return $response->withHeader('Location', $path('/login'))->withStatus(302);
        }
        if ($session->mustChangePassword()) {
            return $response->withHeader('Location', $path('/account/password'))->withStatus(302);
        }
        if (($user['role'] ?? null) !== 'tenant') {
            return $response->withStatus(403);
        }
        return $user;
    };
    $uploadFromRequest = static function (ServerRequestInterface $request): UploadedFileInterface {
        $upload = $request->getUploadedFiles()['photo'] ?? null;
        if (!$upload instanceof UploadedFileInterface || $upload->getError() === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Selecciona una foto PNG, JPEG o WebP de máximo 5 MiB.');
        }
        return $upload;
    };
    $photoResponse = static function (ResponseInterface $response, ?array $photo): ResponseInterface {
        if ($photo === null || !is_string($photo['bytes'] ?? null) || !is_string($photo['mime'] ?? null)) {
            return $response->withStatus(404);
        }
        $bytes = $photo['bytes'];
        $response->getBody()->write($bytes);
        return $response
            ->withHeader('Content-Type', $photo['mime'])
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Content-Disposition', 'inline; filename="maintenance-photo"')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    };
    $app->get('/admin/maintenance', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $maintenance, $view, $csrf, $basePath): ResponseInterface {
        $user = $staffGuard($response, 'maintenance.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $status = $request->getQueryParams()['status'] ?? null;
        $status = is_string($status) && $status !== '' ? $status : null;
        $response->getBody()->write($view->renderApp('maintenance', [
            'pageTitle' => 'Mantenimiento',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'requests' => $maintenance->listForStaff($status),
            'staff' => Permissions::allows($user, 'maintenance.manage') ? $maintenance->listActiveStaff() : [],
            'canManage' => Permissions::allows($user, 'maintenance.manage'),
            'basePath' => $basePath,
        ]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->post('/admin/maintenance', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $maintenance, $path): ResponseInterface {
        $user = $staffGuard($response, 'maintenance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        $leaseId = $form['lease_id'] ?? null;
        if ((!is_int($leaseId) && (!is_string($leaseId) || preg_match('/\A[1-9]\d*\z/', $leaseId) !== 1))) {
            return $response->withStatus(422);
        }
        try {
            $created = $maintenance->create(
                (int) $leaseId,
                (string) ($form['category'] ?? ''),
                (string) ($form['priority'] ?? 'medium'),
                (string) ($form['description'] ?? ''),
                (int) $user['id'],
            );
        } catch (RuntimeException) {
            return $response->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/maintenance/' . $created['id']))->withStatus(303);
    });

    $app->get('/admin/maintenance/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $maintenance, $view, $csrf, $basePath): ResponseInterface {
        $user = $staffGuard($response, 'maintenance.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $record = $maintenance->find((int) $args['id']);
        if ($record === null) {
            return $response->withStatus(404);
        }
        $response->getBody()->write($view->renderApp('maintenance', [
            'pageTitle' => 'Solicitud #' . $record['id'],
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'requests' => [$record],
            'selectedRequest' => $record,
            'staff' => Permissions::allows($user, 'maintenance.manage') ? $maintenance->listActiveStaff() : [],
            'canManage' => Permissions::allows($user, 'maintenance.manage'),
            'basePath' => $basePath,
        ]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->post('/admin/maintenance/{id:[0-9]+}/assignment', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $maintenance, $path): ResponseInterface {
        $user = $staffGuard($response, 'maintenance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        $assignee = $form['assigned_user_id'] ?? '';
        if ($assignee !== '' && (!is_int($assignee) && (!is_string($assignee) || preg_match('/\A[1-9]\d*\z/', $assignee) !== 1))) {
            return $response->withStatus(422);
        }
        try {
            $maintenance->assign((int) $args['id'], $assignee === '' ? null : (int) $assignee, (int) $user['id'], time());
        } catch (RuntimeException) {
            return $response->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/maintenance/' . (int) $args['id']))->withStatus(303);
    });

    $app->post('/admin/maintenance/{id:[0-9]+}/status', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $maintenance, $path): ResponseInterface {
        $user = $staffGuard($response, 'maintenance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $maintenance->transitionStatus(
                (int) $args['id'],
                (string) ($form['status'] ?? ''),
                (int) $user['id'],
                time(),
                isset($form['resolution_notes']) ? (string) $form['resolution_notes'] : null,
                isset($form['cancel_reason']) ? (string) $form['cancel_reason'] : null,
            );
        } catch (RuntimeException) {
            return $response->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/maintenance/' . (int) $args['id']))->withStatus(303);
    });

    $app->post('/admin/maintenance/{id:[0-9]+}/classification', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $maintenance, $path): ResponseInterface {
        $user = $staffGuard($response, 'maintenance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $maintenance->classifyDamage((int) $args['id'], (string) ($form['damage_classification'] ?? ''), time());
        } catch (RuntimeException) {
            return $response->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/maintenance/' . (int) $args['id']))->withStatus(303);
    });

    $app->post('/admin/maintenance/{id:[0-9]+}/photos', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $maintenance, $uploadFromRequest, $path): ResponseInterface {
        $user = $staffGuard($response, 'maintenance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        try {
            $maintenance->addPhoto((int) $args['id'], $uploadFromRequest($request), (int) $user['id'], time());
        } catch (RuntimeException) {
            return $response->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/maintenance/' . (int) $args['id']))->withStatus(303);
    });

    $app->post('/admin/maintenance/{id:[0-9]+}/repair-charge', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $maintenance, $path): ResponseInterface {
        $user = $staffGuard($response, 'maintenance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $maintenance->createRepairCharge(
                (int) $args['id'],
                (string) ($form['amount'] ?? ''),
                (string) ($form['due_date'] ?? ''),
                (string) ($form['description'] ?? ''),
                (int) $user['id'],
            );
        } catch (RuntimeException) {
            return $response->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/maintenance/' . (int) $args['id']))->withStatus(303);
    });

    $app->get('/admin/maintenance/{id:[0-9]+}/photos/{photoId:[a-f0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $maintenance, $photoResponse): ResponseInterface {
        $user = $staffGuard($response, 'maintenance.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        return $photoResponse($response, $maintenance->privatePhoto((int) $args['id'], (string) $args['photoId']));
    });

    $app->post('/tenant/leases/{leaseId:[0-9]+}/maintenance', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $maintenance, $path): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $leaseId = (int) $args['leaseId'];
        if ($leases->findForUser($leaseId, (int) $user['id']) === null) {
            return $response->withStatus(404);
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $maintenance->create(
                $leaseId,
                (string) ($form['category'] ?? ''),
                (string) ($form['priority'] ?? 'medium'),
                (string) ($form['description'] ?? ''),
                (int) $user['id'],
            );
        } catch (RuntimeException) {
            return $response->withStatus(422);
        }
        return $response->withHeader('Location', $path('/tenant/leases/' . $leaseId))->withStatus(303);
    });

    $app->post('/tenant/leases/{leaseId:[0-9]+}/maintenance/{id:[0-9]+}/photos', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $maintenance, $uploadFromRequest, $path): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $leaseId = (int) $args['leaseId'];
        if ($leases->findForUser($leaseId, (int) $user['id']) === null) {
            return $response->withStatus(404);
        }
        $record = $maintenance->find((int) $args['id']);
        if ($record === null || $record['lease_id'] !== $leaseId) {
            return $response->withStatus(404);
        }
        try {
            $maintenance->addPhoto((int) $args['id'], $uploadFromRequest($request), (int) $user['id'], time());
        } catch (RuntimeException) {
            return $response->withStatus(422);
        }
        return $response->withHeader('Location', $path('/tenant/leases/' . $leaseId))->withStatus(303);
    });

    $app->get('/tenant/leases/{leaseId:[0-9]+}/maintenance/{id:[0-9]+}/photos/{photoId:[a-f0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $maintenance, $photoResponse): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $leaseId = (int) $args['leaseId'];
        if ($leases->findForUser($leaseId, (int) $user['id']) === null) {
            return $response->withStatus(404);
        }
        $record = $maintenance->find((int) $args['id']);
        if ($record === null || $record['lease_id'] !== $leaseId) {
            return $response->withStatus(404);
        }
        return $photoResponse(
            $response,
            $maintenance->privatePhoto((int) $args['id'], (string) $args['photoId']),
        );
    });
};
