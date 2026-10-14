<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var FinanceService $finance */
    $finance = $deps['finance'];
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
    $today = static fn (): string => (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))
        ->format('Y-m-d');

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

    $contractContext = static function (int $leaseId) use ($finance, $today): ?int {
        $charges = $finance->listForLease($leaseId, $today());

        return $charges === [] ? null : (int) $charges[0]['contract_id'];
    };

    $app->get('/admin/finance', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $finance, $view, $csrf, $today, $basePath): ResponseInterface {
        $user = $staffGuard($response, 'finance.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $response->getBody()->write($view->renderApp('finance_index', [
            'pageTitle' => 'Cartera',
            'user' => $user,
            'activeNav' => '',
            'csrfToken' => $csrf->token(),
            'portfolio' => $finance->portfolio($today()),
            'basePath' => $basePath,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->post('/admin/leases/{id:[0-9]+}/charges/monthly', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $finance, $contractContext, $path): ResponseInterface {
        $user = $staffGuard($response, 'finance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $leaseId = (int) $args['id'];
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $finance->createMonthlyCharges(
                $leaseId,
                (string) ($form['period_month'] ?? ''),
                (string) ($form['due_date'] ?? ''),
                (int) $user['id'],
            );
            $contractId = $contractContext($leaseId);
            if ($contractId === null) {
                throw new RuntimeException('No fue posible abrir el contrato del arrendamiento.');
            }
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible completar la operación financiera.');

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/contracts/' . $contractId))
            ->withStatus(303);
    });

    $app->post('/admin/leases/{id:[0-9]+}/charges', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $finance, $contractContext, $path): ResponseInterface {
        $user = $staffGuard($response, 'finance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $leaseId = (int) $args['id'];
        $form = (array) ($request->getParsedBody() ?? []);
        $periodMonth = trim((string) ($form['period_month'] ?? ''));
        try {
            $finance->createManualCharge(
                $leaseId,
                (string) ($form['type'] ?? ''),
                (string) ($form['amount'] ?? ''),
                (string) ($form['due_date'] ?? ''),
                (string) ($form['description'] ?? ''),
                $periodMonth === '' ? null : $periodMonth,
                (int) $user['id'],
            );
            $contractId = $contractContext($leaseId);
            if ($contractId === null) {
                throw new RuntimeException('No fue posible abrir el contrato del arrendamiento.');
            }
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible completar la operación financiera.');

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/contracts/' . $contractId))
            ->withStatus(303);
    });

    $app->post('/admin/charges/{id:[0-9]+}/void', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $finance, $contractContext, $path): ResponseInterface {
        $user = $staffGuard($response, 'finance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $charge = $finance->voidCharge(
                (int) $args['id'],
                (int) $user['id'],
                (string) ($form['reason'] ?? ''),
                time(),
            );
            $contractId = $contractContext((int) $charge['lease_id']);
            if ($contractId === null) {
                throw new RuntimeException('No fue posible abrir el contrato del arrendamiento.');
            }
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible completar la operación financiera.');

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/contracts/' . $contractId))
            ->withStatus(303);
    });
};
