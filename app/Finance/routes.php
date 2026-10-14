<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Properties\PropertyService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var FinanceService $finance */
    $finance = $deps['finance'];
    /** @var PaymentMethodService $paymentMethods */
    $paymentMethods = $deps['payment_methods'];
    /** @var PropertyService $properties */
    $properties = $deps['properties'];
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

    $allocationsFromForm = static function (mixed $input): array {
        if (!is_array($input)) {
            throw new RuntimeException('No fue posible completar la operación financiera.');
        }
        $allocations = [];
        foreach ($input as $chargeId => $amount) {
            if ((!is_int($chargeId) && (!is_string($chargeId) || preg_match('/\A[1-9]\d*\z/', $chargeId) !== 1))
                || !is_string($amount)
            ) {
                throw new RuntimeException('No fue posible completar la operación financiera.');
            }
            $allocations[] = ['charge_id' => (int) $chargeId, 'amount' => $amount];
        }

        return $allocations;
    };

    $app->get('/admin/finance/payment-methods', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $paymentMethods, $properties, $view, $csrf, $basePath): ResponseInterface {
        $user = $staffGuard($response, 'finance.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $allProperties = $properties->listProperties();
        $propertyNames = [];
        foreach ($allProperties as $property) {
            $propertyNames[(int) $property['id']] = (string) $property['name'];
        }

        $methods = array_map(static function (array $method) use ($propertyNames, $paymentMethods): array {
            $propertyId = $method['property_id'];
            $method['property_label'] = $propertyId === null
                ? 'Global'
                : ($propertyNames[$propertyId] ?? 'Propiedad');
            $method['has_qr'] = $method['type'] === 'breb_qr'
                && $paymentMethods->privateQr((int) $method['id']) !== null;
            unset(
                $method['qr_stored_name'],
                $method['qr_mime_type'],
                $method['qr_sha256'],
                $method['qr_size_bytes'],
            );

            return $method;
        }, $paymentMethods->listAll());

        $propertyOptions = array_map(
            static fn (array $property): array => [
                'id' => (int) $property['id'],
                'name' => (string) $property['name'],
            ],
            $allProperties,
        );

        $response->getBody()->write($view->renderApp('payment_methods', [
            'pageTitle' => 'Medios de pago',
            'user' => $user,
            'activeNav' => '',
            'csrfToken' => $csrf->token(),
            'methods' => $methods,
            'properties' => $propertyOptions,
            'canManage' => Permissions::allows($user, 'finance.manage'),
            'basePath' => $basePath,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->post('/admin/finance/payment-methods', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $paymentMethods, $path): ResponseInterface {
        $user = $staffGuard($response, 'finance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $form = (array) ($request->getParsedBody() ?? []);
        unset($form['csrf_token']);
        $uploads = $request->getUploadedFiles();
        $qr = $uploads['qr'] ?? null;
        if (!$qr instanceof UploadedFileInterface || $qr->getError() === UPLOAD_ERR_NO_FILE) {
            $qr = null;
        }

        try {
            $paymentMethods->create($form, $qr);
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible guardar el medio de pago.');

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/finance/payment-methods'))
            ->withStatus(303);
    });

    $app->post('/admin/finance/payment-methods/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $paymentMethods, $path): ResponseInterface {
        $user = $staffGuard($response, 'finance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $form = (array) ($request->getParsedBody() ?? []);
        unset($form['csrf_token']);
        $uploads = $request->getUploadedFiles();
        $qr = $uploads['qr'] ?? null;
        if (!$qr instanceof UploadedFileInterface || $qr->getError() === UPLOAD_ERR_NO_FILE) {
            $qr = null;
        }

        try {
            $paymentMethods->update(
                (int) $args['id'],
                $form,
                $qr,
                ($form['remove_qr'] ?? null) === '1',
            );
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible guardar el medio de pago.');

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/finance/payment-methods'))
            ->withStatus(303);
    });

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
            'pendingPayments' => $finance->listPendingPayments(),
            'basePath' => $basePath,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->get('/admin/finance/payments/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $finance, $view, $csrf, $today, $basePath): ResponseInterface {
        $user = $staffGuard($response, 'finance.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $payment = $finance->findPayment((int) $args['id']);
        if ($payment === null) {
            return $response->withStatus(404);
        }
        $response->getBody()->write($view->renderApp('payment_detail', [
            'pageTitle' => 'Revisión de pago #' . $payment['id'],
            'user' => $user,
            'activeNav' => '',
            'csrfToken' => $csrf->token(),
            'payment' => $payment,
            'charges' => $finance->listForLease((int) $payment['lease_id'], $today()),
            'canManage' => Permissions::allows($user, 'finance.manage'),
            'basePath' => $basePath,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->get('/admin/finance/payments/{id:[0-9]+}/receipt', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $finance): ResponseInterface {
        $user = $staffGuard($response, 'finance.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $payment = $finance->findPayment((int) $args['id']);
        if ($payment === null || !$payment['has_receipt']) {
            return $response->withStatus(404);
        }
        $receipt = $finance->privatePaymentReceipt((int) $payment['id']);
        $bytes = $receipt === null ? false : @file_get_contents($receipt['path']);
        if (!is_string($bytes)
            || strlen($bytes) !== $receipt['size_bytes']
            || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $receipt['mime']
            || !hash_equals($receipt['sha256'], hash('sha256', $bytes))) {
            return $response->withStatus(404);
        }
        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', $receipt['mime'])
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    });

    $app->post('/admin/finance/payments/{id:[0-9]+}/reconcile', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $finance, $path, $allocationsFromForm): ResponseInterface {
        $user = $staffGuard($response, 'finance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $finance->reconcilePayment(
                (int) $args['id'],
                $allocationsFromForm($form['allocations'] ?? null),
                (int) $user['id'],
                time(),
            );
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible completar la operación financiera.');

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/finance/payments/' . (int) $args['id']))
            ->withStatus(303);
    });

    $app->post('/admin/finance/payments/{id:[0-9]+}/reject', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $finance, $path): ResponseInterface {
        $user = $staffGuard($response, 'finance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $finance->rejectPayment(
                (int) $args['id'],
                (string) ($form['reason'] ?? ''),
                (int) $user['id'],
                time(),
            );
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible completar la operación financiera.');

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/finance/payments/' . (int) $args['id']))
            ->withStatus(303);
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
