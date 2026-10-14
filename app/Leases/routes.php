<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var LeaseService $leases */
    $leases = $deps['leases'];
    /** @var ContractService $contracts */
    $contracts = $deps['contracts'];
    /** @var FinanceService $finance */
    $finance = $deps['finance'];
    /** @var PaymentMethodService $paymentMethods */
    $paymentMethods = $deps['payment_methods'];
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
    $allocationsFromForm = static function (mixed $input): array {
        if (!is_array($input)) {
            throw new RuntimeException('No fue posible reportar el pago.');
        }
        $allocations = [];
        foreach ($input as $chargeId => $amount) {
            if ((!is_int($chargeId) && (!is_string($chargeId) || preg_match('/\A[1-9]\d*\z/', $chargeId) !== 1))
                || !is_string($amount)
            ) {
                throw new RuntimeException('No fue posible reportar el pago.');
            }
            $allocations[] = ['charge_id' => (int) $chargeId, 'amount' => $amount];
        }

        return $allocations;
    };

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

    $tenantGuard = static function (ResponseInterface $response) use ($currentUser, $session, $path): array|ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            return $response->withHeader('Location', $path('/login'))->withStatus(302);
        }
        if ($session->mustChangePassword()) {
            return $response->withHeader('Location', $path('/account/password'))->withStatus(302);
        }
        if ((string) ($user['role'] ?? '') !== 'tenant') {
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

    $app->get('/tenant', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($tenantGuard, $leases, $view, $csrf): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $response->getBody()->write($view->renderApp('tenant_home', [
            'pageTitle' => 'Mis arrendamientos',
            'user' => $user,
            'activeNav' => 'home',
            'csrfToken' => $csrf->token(),
            'leases' => $leases->listForUser((int) $user['id']),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/tenant/leases/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $finance, $paymentMethods, $view, $csrf, $todayProvider, $path): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $lease = $leases->findForUser((int) $args['id'], (int) $user['id']);
        if ($lease === null) {
            return $response->withStatus(404);
        }

        $today = $todayProvider();
        $financeSummary = $finance->summaryForLease((int) $lease['id'], $today);
        $charges = $finance->listForLease((int) $lease['id'], $today);
        $paymentHistory = $finance->listPaymentsForLease((int) $lease['id']);
        $tenantPaymentMethods = array_map(static function (array $method) use ($paymentMethods, $lease, $path): array {
            $qrUrl = null;
            if ($method['type'] === 'breb_qr' && $paymentMethods->privateQr((int) $method['id']) !== null) {
                $qrUrl = $path(
                    '/tenant/leases/' . (int) $lease['id'] . '/payment-methods/' . (int) $method['id'] . '/qr',
                );
            }

            return [
                'id' => (int) $method['id'],
                'type' => (string) $method['type'],
                'label' => (string) $method['label'],
                'recipient_name' => $method['recipient_name'],
                'institution_name' => $method['institution_name'],
                'account_type' => $method['account_type'],
                'account_number' => $method['account_number'],
                'breb_key_type' => $method['breb_key_type'],
                'breb_key_value' => $method['breb_key_value'],
                'wallet_identifier' => $method['wallet_identifier'],
                'instructions' => $method['instructions'],
                'qr_url' => $qrUrl,
            ];
        }, $paymentMethods->listForProperty((int) $lease['property_id']));

        $response->getBody()->write($view->renderApp('tenant_lease_detail', [
            'pageTitle' => 'Arrendamiento #' . (int) $lease['id'],
            'user' => $user,
            'activeNav' => 'home',
            'csrfToken' => $csrf->token(),
            'lease' => $lease,
            'financeSummary' => $financeSummary,
            'charges' => $charges,
            'paymentMethods' => $tenantPaymentMethods,
            'paymentHistory' => $paymentHistory,
            'today' => $today,
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/tenant/leases/{id:[0-9]+}/payments', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $finance, $path, $allocationsFromForm): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $lease = $leases->findForUser((int) $args['id'], (int) $user['id']);
        if ($lease === null) {
            return $response->withStatus(404);
        }

        $form = (array) ($request->getParsedBody() ?? []);
        $uploads = $request->getUploadedFiles();
        $receipt = $uploads['receipt'] ?? null;
        if (!$receipt instanceof \Psr\Http\Message\UploadedFileInterface || $receipt->getError() === UPLOAD_ERR_NO_FILE) {
            $receipt = null;
        }
        try {
            $finance->reportPayment(
                (int) $lease['id'],
                (int) ($form['payment_method_id'] ?? 0),
                (string) ($form['amount'] ?? ''),
                (string) ($form['paid_on'] ?? ''),
                isset($form['paid_time']) && (string) $form['paid_time'] !== '' ? (string) $form['paid_time'] : null,
                $allocationsFromForm($form['allocations'] ?? null),
                $receipt,
                time(),
            );
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible reportar el pago.');

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/tenant/leases/' . (int) $lease['id']))
            ->withStatus(303);
    });

    $app->get('/tenant/leases/{leaseId:[0-9]+}/payments/{paymentId:[0-9]+}/receipt', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $finance): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $lease = $leases->findForUser((int) $args['leaseId'], (int) $user['id']);
        if ($lease === null) {
            return $response->withStatus(404);
        }

        $payment = $finance->findPayment((int) $args['paymentId']);
        if ($payment === null || (int) $payment['lease_id'] !== (int) $lease['id'] || !$payment['has_receipt']) {
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

    $app->get('/tenant/leases/{leaseId:[0-9]+}/payment-methods/{methodId:[0-9]+}/qr', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $paymentMethods): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $lease = $leases->findForUser((int) $args['leaseId'], (int) $user['id']);
        if ($lease === null) {
            return $response->withStatus(404);
        }

        $methodId = (int) $args['methodId'];
        $applicableMethod = null;
        foreach ($paymentMethods->listForProperty((int) $lease['property_id']) as $method) {
            if ((int) $method['id'] === $methodId && $method['type'] === 'breb_qr') {
                $applicableMethod = $method;
                break;
            }
        }
        if ($applicableMethod === null) {
            return $response->withStatus(404);
        }

        $privateQr = $paymentMethods->privateQr($methodId);
        $bytes = $privateQr === null ? false : @file_get_contents($privateQr['path']);
        $actualMime = is_string($bytes) ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) : false;
        if (!is_string($bytes)
            || strlen($bytes) !== $privateQr['size_bytes']
            || $actualMime !== $privateQr['mime']
            || !is_string($applicableMethod['qr_sha256'])
            || !hash_equals($applicableMethod['qr_sha256'], hash('sha256', $bytes))
        ) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', $privateQr['mime'])
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    });

    $app->get('/tenant/leases/{id:[0-9]+}/contract', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $contracts): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $lease = $leases->findForUser((int) $args['id'], (int) $user['id']);
        if ($lease === null) {
            return $response->withStatus(404);
        }

        $pdf = $contracts->privatePdf((int) $lease['contract_id']);
        if ($pdf === null) {
            return $response->withStatus(404);
        }

        $bytes = @file_get_contents($pdf['path']);
        if (!is_string($bytes)
            || !str_starts_with($bytes, '%PDF-')
            || strlen($bytes) !== $pdf['size_bytes']
            || !hash_equals($pdf['sha256'], hash('sha256', $bytes))) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="contrato-arrendamiento-' . (int) $lease['id'] . '.pdf"')
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    });
};
