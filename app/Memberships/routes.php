<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\Memberships\MembershipService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var MembershipService $memberships */
    $memberships = $deps['memberships'];
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
    $todayProvider = $deps['today'] ?? static fn (): string =>
        (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
    $path = static fn (string $value): string => $basePath . $value;

    $staffGuard = static function (ResponseInterface $response, string $permission) use (
        $currentUser,
        $session,
        $path,
    ): array|ResponseInterface {
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

    $tenantGuard = static function (ResponseInterface $response) use ($currentUser, $session, $path): array|ResponseInterface {
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

    $redirect = static fn (ResponseInterface $response, string $location): ResponseInterface =>
        $response->withHeader('Location', $path($location))->withStatus(303);

    $app->get('/admin/memberships', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $memberships, $view, $csrf, $todayProvider): ResponseInterface {
        $user = $staffGuard($response, 'memberships.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $response->getBody()->write($view->renderApp('memberships', [
            'pageTitle' => 'Servicios digitales',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'services' => $memberships->listServices(),
            'pendingMemberships' => $memberships->listPendingChargeDecisions(),
            'history' => $memberships->listRecentMemberships(),
            'today' => $todayProvider(),
            'canManage' => Permissions::allows($user, 'memberships.manage'),
        ]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->post('/admin/memberships/services', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $memberships, $redirect): ResponseInterface {
        $user = $staffGuard($response, 'memberships.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $body = (array) ($request->getParsedBody() ?? []);
        $duration = filter_var($body['duration_days'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($duration)) {
            return $response->withStatus(422);
        }
        try {
            $memberships->createService(
                (string) ($body['name'] ?? ''),
                (string) ($body['description'] ?? ''),
                (string) ($body['current_price'] ?? ''),
                $duration,
                (int) $user['id'],
                time(),
            );
        } catch (Throwable) {
            return $response->withStatus(422);
        }
        return $redirect($response, '/admin/memberships');
    });

    $app->post('/admin/memberships/services/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $memberships, $redirect): ResponseInterface {
        $user = $staffGuard($response, 'memberships.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $body = (array) ($request->getParsedBody() ?? []);
        $duration = filter_var($body['duration_days'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($duration)) {
            return $response->withStatus(422);
        }
        try {
            $memberships->updateService(
                (int) $args['id'],
                (string) ($body['name'] ?? ''),
                (string) ($body['description'] ?? ''),
                (string) ($body['current_price'] ?? ''),
                $duration,
                ($body['active'] ?? '') === '1',
                (int) $user['id'],
                time(),
            );
        } catch (Throwable) {
            return $response->withStatus(422);
        }
        return $redirect($response, '/admin/memberships');
    });

    $app->post('/admin/memberships/{id:[0-9]+}/decision', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $memberships, $redirect): ResponseInterface {
        $user = $staffGuard($response, 'memberships.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $body = (array) ($request->getParsedBody() ?? []);
        $decision = is_string($body['decision'] ?? null) ? $body['decision'] : '';
        $dueDate = is_string($body['due_date'] ?? null) && $body['due_date'] !== '' ? $body['due_date'] : null;
        $description = is_string($body['description'] ?? null) && $body['description'] !== '' ? $body['description'] : null;
        try {
            $memberships->decideCharge((int) $args['id'], $decision, $dueDate, $description, (int) $user['id'], time());
        } catch (Throwable) {
            return $response->withStatus(422);
        }
        return $redirect($response, '/admin/memberships');
    });

    $app->post('/tenant/leases/{leaseId:[0-9]+}/memberships', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $memberships, $todayProvider, $redirect): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $leaseId = (int) $args['leaseId'];
        $lease = $leases->findForUser($leaseId, (int) $user['id']);
        if ($lease === null) {
            return $response->withStatus(404);
        }
        if (!$lease['is_active']) {
            return $response->withStatus(422);
        }
        $body = (array) ($request->getParsedBody() ?? []);
        $serviceId = filter_var($body['service_id'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($serviceId) || $serviceId <= 0) {
            return $response->withStatus(422);
        }
        $expectedPrice = is_string($body['expected_price'] ?? null) ? $body['expected_price'] : '';
        $expectedDurationDays = filter_var($body['expected_duration_days'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($expectedDurationDays)) {
            return $response->withStatus(422);
        }
        try {
            $memberships->accept(
                $serviceId,
                $leaseId,
                (int) $user['id'],
                $expectedPrice,
                $expectedDurationDays,
                $todayProvider(),
                time(),
            );
        } catch (\DomainException $error) {
            $response->getBody()->write($error->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(409);
        } catch (Throwable) {
            return $response->withStatus(422);
        }
        return $redirect($response, '/tenant/leases/' . $leaseId . '#digital-services');
    });

    $app->post('/tenant/leases/{leaseId:[0-9]+}/memberships/{membershipId:[0-9]+}/renew', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $memberships, $todayProvider, $redirect): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $leaseId = (int) $args['leaseId'];
        $lease = $leases->findForUser($leaseId, (int) $user['id']);
        if ($lease === null) {
            return $response->withStatus(404);
        }
        if (!$lease['is_active']) {
            return $response->withStatus(422);
        }
        $membershipId = (int) $args['membershipId'];
        $membership = $memberships->findMembership($membershipId);
        if ($membership === null || $membership['lease_id'] !== $leaseId) {
            return $response->withStatus(404);
        }
        $body = (array) ($request->getParsedBody() ?? []);
        $expectedPrice = is_string($body['expected_price'] ?? null) ? $body['expected_price'] : '';
        $expectedDurationDays = filter_var($body['expected_duration_days'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($expectedDurationDays)) {
            return $response->withStatus(422);
        }
        try {
            $memberships->renew(
                $membershipId,
                (int) $user['id'],
                $expectedPrice,
                $expectedDurationDays,
                $todayProvider(),
                time(),
            );
        } catch (\DomainException $error) {
            $response->getBody()->write($error->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(409);
        } catch (Throwable) {
            return $response->withStatus(422);
        }
        return $redirect($response, '/tenant/leases/' . $leaseId . '#digital-services');
    });
};
