<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\Community\CommunityService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\ObligationService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Insurance\InsuranceService;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\Maintenance\MaintenanceService;
use AlquilaPro\Maintenance\VisitService;
use AlquilaPro\Memberships\MembershipService;
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
    /** @var ObligationService $obligations */
    $obligations = $deps['obligations'];
    /** @var PaymentMethodService $paymentMethods */
    $paymentMethods = $deps['payment_methods'];
    /** @var InsuranceService $insurance */
    $insurance = $deps['insurance'];
    /** @var MaintenanceService $maintenance */
    $maintenance = $deps['maintenance'];
    /** @var VisitService $visits */
    $visits = $deps['visits'];
    /** @var DocumentService $documents */
    $documents = $deps['documents'];
    /** @var CommunityService $community */
    $community = $deps['community'];
    /** @var MembershipService $memberships */
    $memberships = $deps['memberships'];
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
            if ($amount === '') {
                continue;
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

    $supportResponse = static function (
        ResponseInterface $response,
        ?array $support,
        ?string $expectedHash,
    ): ResponseInterface {
        if ($support === null || !is_string($expectedHash)) {
            return $response->withStatus(404);
        }
        $bytes = @file_get_contents($support['path']);
        if (!is_string($bytes)
            || strlen($bytes) !== $support['size_bytes']
            || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $support['mime']
            || !hash_equals($expectedHash, hash('sha256', $bytes))
        ) {
            return $response->withStatus(404);
        }
        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', $support['mime'])
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
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

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->get('/tenant/leases/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $documents, $community, $memberships, $finance, $obligations, $paymentMethods, $insurance, $maintenance, $visits, $view, $csrf, $todayProvider, $path): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $lease = $leases->findForUser((int) $args['id'], (int) $user['id']);
        if ($lease === null) {
            return $response->withStatus(404);
        }

        $today = $todayProvider();
        $membershipEndDate = static function (string $startsOn, int $durationDays): ?string {
            try {
                $endDate = (new DateTimeImmutable($startsOn))->modify('+' . ($durationDays - 1) . ' days');
            } catch (Throwable) {
                return null;
            }
            if ($endDate === false || $endDate->format('Y-m-d') < $startsOn) {
                return null;
            }
            return $endDate->format('Y-m-d');
        };
        $membershipHistory = $memberships->listForLease((int) $lease['id']);
        $availableServices = $lease['is_active'] ? $memberships->listServices(true) : [];
        $serviceById = [];
        foreach ($memberships->listServices() as $service) {
            $serviceById[(int) $service['id']] = $service;
        }
        $renewedMembershipIds = [];
        $occupiedServiceIds = [];
        foreach ($membershipHistory as $membership) {
            if ($membership['renews_membership_id'] !== null) {
                $renewedMembershipIds[(int) $membership['renews_membership_id']] = true;
            }
            if ($membership['ended_at'] === null && $membership['ends_on'] >= $today) {
                $occupiedServiceIds[(int) $membership['service_id']] = true;
            }
        }
        $availableServices = array_values(array_filter(
            $availableServices,
            static fn (array $service): bool => !isset($occupiedServiceIds[(int) $service['id']]),
        ));
        $availableServices = array_map(static function (array $service) use ($today, $membershipEndDate): array {
            $service['offer_starts_on'] = $today;
            $service['offer_ends_on'] = $membershipEndDate($today, (int) $service['duration_days']);
            return $service;
        }, $availableServices);
        $tenantMemberships = array_map(static function (array $membership) use (
            $today,
            $serviceById,
            $renewedMembershipIds,
            $membershipEndDate,
        ): array {
            $service = $serviceById[(int) $membership['service_id']] ?? null;
            $outcomeLabel = match ($membership['charge_outcome']) {
                'created' => 'Cargo creado',
                'skipped' => 'No se creará cargo',
                'pending' => 'Pendiente de decisión administrativa',
                default => 'Sin cargo',
            };
            $status = $membership['ended_at'] !== null
                ? 'Finalizado'
                : (($membership['ends_on'] < $today)
                    ? 'Vencido'
                    : (($membership['starts_on'] > $today) ? 'Programado' : 'Activo'));
            $renewalStartsOn = $membership['ended_at'] === null && $membership['ends_on'] >= $today
                ? $membershipEndDate((string) $membership['ends_on'], 2)
                : $today;
            $renewalEndsOn = is_array($service) && $service['active'] && $renewalStartsOn !== null
                ? $membershipEndDate($renewalStartsOn, (int) $service['duration_days'])
                : null;
            $renewalOffer = is_array($service) && $service['active']
                && $renewalStartsOn !== null && $renewalEndsOn !== null
                ? [
                    'current_price' => (string) $service['current_price'],
                    'duration_days' => (int) $service['duration_days'],
                    'starts_on' => $renewalStartsOn,
                    'ends_on' => $renewalEndsOn,
                ]
                : null;
            return [
                'id' => (int) $membership['id'],
                'service_name' => (string) ($membership['service_name'] ?? $service['name'] ?? 'Servicio'),
                'price_snapshot' => (string) $membership['price_snapshot'],
                'starts_on' => (string) $membership['starts_on'],
                'ends_on' => (string) $membership['ends_on'],
                'status' => $status,
                'charge_outcome_label' => $outcomeLabel,
                'can_renew' => $renewalOffer !== null
                    && !isset($renewedMembershipIds[(int) $membership['id']]),
                'renewal_offer' => $renewalOffer,
            ];
        }, $membershipHistory);

        $communityIncidents = [];
        foreach ($community->listForLease((int) $lease['id']) as $incident) {
            if ($incident['status'] === 'voided') {
                continue;
            }
            $communityIncidents[] = [
                'category' => (string) $incident['category'],
                'occurred_on' => (string) $incident['occurred_on'],
                'description' => (string) $incident['description'],
                'status' => (string) $incident['status'],
                'resolution_notes' => $incident['status'] === 'closed'
                    ? (string) $incident['resolution_notes']
                    : null,
            ];
        }

        $leaseDocuments = [];
        foreach ($documents->listCurrentForLease((int) $lease['id']) as $document) {
            if (!in_array($document['type'], ['lease_document', 'inventory_initial', 'inventory_final'], true)) {
                continue;
            }
            $leaseDocuments[] = [
                'id' => (int) $document['id'],
                'type' => (string) $document['type'],
                'title' => (string) $document['title'],
                'created_at' => (string) $document['created_at'],
                'content_json' => is_array($document['content_json'] ?? null) ? $document['content_json'] : null,
                'file_url' => $document['type'] === 'lease_document' && is_string($document['stored_name'] ?? null)
                    ? $path('/tenant/leases/' . (int) $lease['id'] . '/documents/' . (int) $document['id'] . '/file')
                    : null,
            ];
        }
        $propertyManuals = [];
        $propertyLegalNotices = [];
        foreach ($documents->listCurrentForProperty((int) $lease['property_id']) as $document) {
            if (!is_array($document['content_json'] ?? null)) {
                continue;
            }
            if ($document['type'] === 'manual_convivencia') {
                $propertyManuals[] = [
                    'id' => (int) $document['id'],
                    'title' => (string) $document['title'],
                    'created_at' => (string) $document['created_at'],
                    'content_json' => $document['content_json'],
                ];
            } elseif ($document['type'] === 'legal_notice') {
                $propertyLegalNotices[] = [
                    'title' => (string) $document['title'],
                    'created_at' => (string) $document['created_at'],
                    'source_label' => (string) $document['content_json']['source_label'],
                    'source_url' => $document['content_json']['source_url'],
                    'effective_on' => $document['content_json']['effective_on'],
                    'notice_text' => (string) $document['content_json']['notice_text'],
                ];
            }
        }

        $insurancePolicies = $insurance->tenantPolicySummary((int) $lease['id'], $today);
        $maintenanceRequests = array_map(static function (array $record) use ($path, $lease, $visits): array {
            $canSelect = !in_array($record['status'], ['resolved', 'cancelled'], true);
            return [
                'id' => (int) $record['id'],
                'category' => (string) $record['category'],
                'priority' => (string) $record['priority'],
                'description' => (string) $record['description'],
                'photos' => array_map(static fn (array $photo): array => [
                    'photo_id' => (string) ($photo['photo_id'] ?? ''),
                    'created_at' => (string) ($photo['created_at'] ?? ''),
                ], array_values(array_filter($record['photos'], 'is_array'))),
                'status' => (string) $record['status'],
                'resolution_notes' => $record['resolution_notes'] === null ? null : (string) $record['resolution_notes'],
                'visits' => array_map(static function (array $visit) use ($canSelect): array {
                    $status = (string) $visit['status'];
                    return [
                        'id' => (int) $visit['id'],
                        'status' => $status,
                        'proposed_slots' => $canSelect && $status === 'proposed'
                            ? array_map(static fn (array $slot): array => [
                                'start_at' => (string) $slot['start_at'],
                                'duration_minutes' => (int) $slot['duration_minutes'],
                            ], $visit['proposed_slots'])
                            : [],
                        'selected_start_at' => $visit['selected_start_at'] === null
                            ? null : (string) $visit['selected_start_at'],
                        'duration_minutes' => $visit['duration_minutes'] === null
                            ? null : (int) $visit['duration_minutes'],
                    ];
                }, $visits->listForRequest((int) $record['id'])),
                'photo_base_url' => $path('/tenant/leases/' . (int) $lease['id'] . '/maintenance/' . (int) $record['id'] . '/photos/'),
            ];
        }, $maintenance->listForLease((int) $lease['id']));
        $financeSummary = $finance->summaryForLease((int) $lease['id'], $today);
        $charges = $finance->listForLease((int) $lease['id'], $today);
        $chargesById = [];
        foreach ($charges as $charge) {
            $chargesById[(int) $charge['id']] = $charge;
        }
        $tenantObligations = [];
        foreach ($obligations->listForLease((int) $lease['id'], $today) as $obligation) {
            $linkedCharge = $obligation['charge_id'] === null
                ? null
                : ($chargesById[(int) $obligation['charge_id']] ?? null);
            $tenantObligations[] = [
                'id' => (int) $obligation['id'],
                'type' => (string) $obligation['type'],
                'service_name' => $obligation['service_name'],
                'description' => (string) $obligation['description'],
                'period_month' => $obligation['period_month'],
                'issued_on' => $obligation['issued_on'],
                'due_on' => $obligation['due_on'],
                'amount' => (string) $obligation['amount'],
                'external_state' => (string) $obligation['external_state'],
                'external_overdue' => (bool) $obligation['external_overdue'],
                'is_charged' => (bool) $obligation['is_charged'],
                'linked_charge_state' => is_array($linkedCharge) ? (string) $linkedCharge['state'] : null,
                'linked_charge_type' => is_array($linkedCharge) ? (string) $linkedCharge['type'] : null,
                'linked_charge_due_date' => is_array($linkedCharge) ? (string) $linkedCharge['due_date'] : null,
                'source_support_url' => $obligation['has_source_support'] && $obligation['voided_at'] === null
                    ? $path('/tenant/leases/' . (int) $lease['id'] . '/obligations/' . (int) $obligation['id'] . '/source-support')
                    : null,
            ];
        }
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
            'obligations' => $tenantObligations,
            'paymentMethods' => $tenantPaymentMethods,
            'paymentHistory' => $paymentHistory,
            'insurancePolicies' => $insurancePolicies,
            'maintenanceRequests' => $maintenanceRequests,
            'leaseDocuments' => $leaseDocuments,
            'propertyManuals' => $propertyManuals,
            'communityIncidents' => $communityIncidents,
            'propertyLegalNotices' => $propertyLegalNotices,
            'digitalServices' => $availableServices,
            'digitalServiceMemberships' => $tenantMemberships,
            'today' => $today,
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->post('/tenant/leases/{leaseId:[0-9]+}/maintenance/{id:[0-9]+}/visits/{visitId:[0-9]+}/select', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $maintenance, $visits, $path): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $leaseId = (int) $args['leaseId'];
        $lease = $leases->findForUser($leaseId, (int) $user['id']);
        if ($lease === null) {
            return $response->withStatus(404);
        }
        $requestId = (int) $args['id'];
        $maintenanceRequest = $maintenance->find($requestId);
        if ($maintenanceRequest === null || $maintenanceRequest['lease_id'] !== $leaseId
            || in_array($maintenanceRequest['status'], ['resolved', 'cancelled'], true)
        ) {
            return $response->withStatus(404);
        }
        $visitId = (int) $args['visitId'];
        $visit = $visits->find($visitId);
        if ($visit === null || $visit['maintenance_request_id'] !== $requestId || $visit['status'] !== 'proposed') {
            return $response->withStatus(404);
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $visits->selectSlot(
                $visitId,
                (string) ($form['start_at'] ?? ''),
                (int) $user['id'],
                time(),
            );
        } catch (RuntimeException) {
            return $response->withStatus(422);
        }
        return $response->withHeader('Location', $path('/tenant/leases/' . $leaseId))->withStatus(303);
    });

    $app->get('/tenant/leases/{leaseId:[0-9]+}/obligations/{obligationId:[0-9]+}/source-support', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $obligations, $todayProvider, $supportResponse): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $leaseId = (int) $args['leaseId'];
        $lease = $leases->findForUser($leaseId, (int) $user['id']);
        if ($lease === null) {
            return $response->withStatus(404);
        }

        $obligation = $obligations->find((int) $args['obligationId'], $todayProvider());
        if ($obligation === null
            || (int) $obligation['lease_id'] !== $leaseId
            || $obligation['voided_at'] !== null
            || !$obligation['has_source_support']
        ) {
            return $response->withStatus(404);
        }

        return $supportResponse(
            $response,
            $obligations->privateSourceSupport((int) $obligation['id']),
            $obligation['source_support_sha256'],
        );
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
