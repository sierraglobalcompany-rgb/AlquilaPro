<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var ContractService $contracts */
    $contracts = $deps['contracts'];
    /** @var LeaseService $leases */
    $leases = $deps['leases'];
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

    $jsonField = static function (
        array $body,
        string $field,
        string $default,
        bool $list,
    ): array {
        $raw = trim((string) ($body[$field] ?? $default));
        if ($raw === '') {
            $raw = $default;
        }

        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Los datos contractuales en JSON no son válidos.');
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Los datos contractuales en JSON no son válidos.');
        }
        if ($list && !array_is_list($decoded)) {
            throw new RuntimeException('Los datos contractuales en JSON no son válidos.');
        }
        if (!$list && $decoded !== [] && array_is_list($decoded)) {
            throw new RuntimeException('Los datos contractuales en JSON no son válidos.');
        }

        return $decoded;
    };

    $versionOptions = static function (?string $documentType = null, bool $activeOnly = false) use ($contracts): array {
        $options = [];
        foreach ($contracts->listTemplates() as $template) {
            if ($activeOnly && !$template['is_active']) {
                continue;
            }
            if ($documentType !== null && $template['document_type'] !== $documentType) {
                continue;
            }

            foreach ($contracts->listTemplateVersions((int) $template['id']) as $version) {
                $options[] = [
                    'id' => (int) $version['id'],
                    'template_name' => (string) $template['name'],
                    'document_type' => (string) $template['document_type'],
                    'version_number' => (int) $version['version_number'],
                ];
            }
        }

        return $options;
    };

    $app->get('/admin/contracts', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($adminGuard, $contracts, $view, $csrf, $versionOptions): ResponseInterface {
        $user = $adminGuard($response, 'contracts.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $response->getBody()->write($view->renderApp('contracts', [
            'pageTitle' => 'Contratos',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'contracts' => $contracts->listContracts(),
            'versionOptions' => $versionOptions(null, true),
            'canManage' => Permissions::allows($user, 'contracts.manage'),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/admin/contracts', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($adminGuard, $contracts, $jsonField, $path): ResponseInterface {
        $user = $adminGuard($response, 'contracts.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $body = (array) ($request->getParsedBody() ?? []);
        try {
            $created = $contracts->createDraft(
                (int) ($body['candidate_id'] ?? 0),
                (int) ($body['template_version_id'] ?? 0),
                (int) $user['id'],
                [
                    'monthly_rent' => $body['monthly_rent'] ?? null,
                    'administration_fee' => $body['administration_fee'] ?? null,
                    'start_date' => $body['start_date'] ?? null,
                    'end_date' => $body['end_date'] ?? null,
                    'duration_text' => $body['duration_text'] ?? null,
                    'manual_variables' => $jsonField($body, 'manual_variables_json', '{}', false),
                    'tasks' => $jsonField($body, 'tasks_json', '[]', true),
                    'annexes' => $jsonField($body, 'annexes_json', '[]', true),
                ],
                time(),
            );
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/contracts/' . (int) $created['id']))
            ->withStatus(303);
    });

    $app->get('/admin/contracts/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $contracts, $leases, $finance, $view, $csrf, $versionOptions): ResponseInterface {
        $user = $adminGuard($response, 'contracts.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $contract = $contracts->findContract((int) $args['id']);
        if ($contract === null) {
            return $response->withStatus(404);
        }

        $previewHtml = null;
        if ($contract['status'] === 'draft') {
            $previewHtml = $contracts->previewHtml((int) $contract['id']);
        }

        $lease = $leases->findByContract((int) $contract['id']);
        $canViewFinance = $lease !== null && Permissions::allows($user, 'finance.view');
        $canManageFinance = $lease !== null && Permissions::allows($user, 'finance.manage');
        $financeSummary = null;
        $charges = [];
        $today = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
        if ($canViewFinance) {
            $financeSummary = $finance->summaryForLease((int) $lease['id'], $today);
            $charges = $finance->listForLease((int) $lease['id'], $today);
        }

        $response->getBody()->write($view->renderApp('contract_detail', [
            'pageTitle' => 'Contrato #' . (int) $contract['id'],
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'contract' => $contract,
            'lease' => $lease,
            'previewHtml' => $previewHtml,
            'versionOptions' => $versionOptions((string) $contract['document_type']),
            'canManage' => Permissions::allows($user, 'contracts.manage'),
            'canViewFinance' => $canViewFinance,
            'canManageFinance' => $canManageFinance,
            'financeSummary' => $financeSummary,
            'charges' => $charges,
            'today' => $today,
            'currentPeriodMonth' => substr($today, 0, 7) . '-01',
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/admin/contracts/{id:[0-9]+}/pdf', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $contracts): ResponseInterface {
        $user = $adminGuard($response, 'contracts.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $contractId = (int) $args['id'];
        $pdf = $contracts->privatePdf($contractId);
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
            ->withHeader('Content-Disposition', 'attachment; filename="contrato-' . $contractId . '.pdf"')
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    });

    $app->post('/admin/contracts/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $contracts, $jsonField, $path): ResponseInterface {
        $user = $adminGuard($response, 'contracts.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $contractId = (int) $args['id'];
        $body = (array) ($request->getParsedBody() ?? []);

        try {
            $action = trim((string) ($body['action'] ?? ''));
            if ($action === 'save') {
                $input = [
                    'monthly_rent' => $body['monthly_rent'] ?? null,
                    'administration_fee' => $body['administration_fee'] ?? null,
                    'start_date' => $body['start_date'] ?? null,
                    'end_date' => $body['end_date'] ?? null,
                    'duration_text' => $body['duration_text'] ?? null,
                    'manual_variables' => $jsonField($body, 'manual_variables_json', '{}', false),
                    'tasks' => $jsonField($body, 'tasks_json', '[]', true),
                    'annexes' => $jsonField($body, 'annexes_json', '[]', true),
                ];
                if (array_key_exists('template_version_id', $body)) {
                    $input['template_version_id'] = $body['template_version_id'];
                }
                $contracts->updateDraft($contractId, $input, time());
            } elseif ($action === 'generate') {
                $contracts->generate($contractId, time());
            } elseif ($action === 'update_tasks') {
                $contracts->updateTasks(
                    $contractId,
                    $jsonField($body, 'tasks_json', '[]', true),
                    time(),
                );
            } elseif ($action === 'finalize') {
                $contracts->finalize($contractId, (int) $user['id'], time());
            } else {
                throw new RuntimeException('La acción contractual no es válida.');
            }
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response
            ->withHeader('Location', $path('/admin/contracts/' . $contractId))
            ->withStatus(303);
    });

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
