<?php

declare(strict_types=1);

use AlquilaPro\Audit\AuditService;
use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\Properties\PropertyService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var ContractService $contracts */
    $contracts = $deps['contracts'];
    /** @var CandidateService $candidates */
    $candidates = $deps['candidates'];
    /** @var PropertyService $properties */
    $properties = $deps['properties'];
    /** @var LeaseService $leases */
    $leases = $deps['leases'];
    /** @var FinanceService $finance */
    $finance = $deps['finance'];
    /** @var AuditService $audit */
    $audit = $deps['audit'];
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
        string|array $permission,
    ) use ($currentUser, $session, $path): array|ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            return $response->withHeader('Location', $path('/login'))->withStatus(302);
        }
        if ($session->mustChangePassword()) {
            return $response->withHeader('Location', $path('/account/password'))->withStatus(302);
        }
        $hasPermission = false;
        foreach (is_array($permission) ? $permission : [$permission] as $requiredPermission) {
            if (Permissions::allows($user, $requiredPermission)) {
                $hasPermission = true;
                break;
            }
        }
        if (!$hasPermission) {
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

    $manualValues = static function (array $body) use ($jsonField): array {
        if (!array_key_exists('manual_variables', $body)) {
            return $jsonField($body, 'manual_variables_json', '{}', false);
        }
        if (!is_array($body['manual_variables'])) {
            throw new RuntimeException('Las variables manuales del contrato no son válidas.');
        }

        return $body['manual_variables'];
    };

    $taskValues = static function (array $body) use ($jsonField): array {
        if (!array_key_exists('tasks', $body)) {
            return $jsonField($body, 'tasks_json', '[]', true);
        }
        if (!is_array($body['tasks'])) {
            throw new RuntimeException('Las tareas contractuales no son válidas.');
        }

        $result = [];
        foreach ($body['tasks'] as $task) {
            if (!is_array($task)) {
                $result[] = $task;
                continue;
            }
            $label = trim((string) ($task['label'] ?? ''));
            $unknown = array_diff(array_keys($task), ['label', 'done']);
            if ($label === '' && $unknown === []) {
                continue;
            }
            if (array_key_exists('done', $task)) {
                $done = $task['done'];
                if ($done === '1' || $done === 1 || $done === true) {
                    $task['done'] = true;
                } elseif ($done === '0' || $done === 0 || $done === false || $done === '') {
                    $task['done'] = false;
                }
            }
            $result[] = $task;
        }

        return array_values($result);
    };

    $annexValues = static function (array $body) use ($jsonField): array {
        if (!array_key_exists('annexes', $body)) {
            return $jsonField($body, 'annexes_json', '[]', true);
        }
        if (!is_array($body['annexes'])) {
            throw new RuntimeException('Los anexos contractuales no son válidos.');
        }

        $result = [];
        foreach ($body['annexes'] as $annex) {
            if (!is_array($annex)) {
                $result[] = $annex;
                continue;
            }
            $title = trim((string) ($annex['title'] ?? ''));
            $note = trim((string) ($annex['note'] ?? ''));
            $unknown = array_diff(array_keys($annex), ['title', 'note']);
            if ($title === '' && $note === '' && $unknown === []) {
                continue;
            }
            $result[] = $annex;
        }

        return array_values($result);
    };

    $templateVariables = static function (array $body): array {
        if (!array_key_exists('variables', $body)) {
            $rawVariables = (string) ($body['variables_json'] ?? '[]');
            try {
                $variables = json_decode($rawVariables, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('Las variables manuales no son válidas.');
            }
            if (!is_array($variables)) {
                throw new RuntimeException('Las variables manuales no son válidas.');
            }

            return $variables;
        }

        if (!is_array($body['variables'])) {
            throw new RuntimeException('Las variables manuales no son válidas.');
        }

        $result = [];
        foreach ($body['variables'] as $variable) {
            if (!is_array($variable)) {
                $result[] = $variable;
                continue;
            }

            $key = trim((string) ($variable['key'] ?? ''));
            $label = trim((string) ($variable['label'] ?? ''));
            $requiredPresent = array_key_exists('required', $variable);
            $unknown = array_diff(array_keys($variable), ['key', 'label', 'required']);
            if ($key === '' && $label === '' && !$requiredPresent && $unknown === []) {
                continue;
            }

            $required = $variable['required'] ?? false;
            if ($required === '1' || $required === 1 || $required === true || $required === 'on') {
                $variable['required'] = true;
            } elseif ($required === '0' || $required === 0 || $required === false || $required === '') {
                $variable['required'] = false;
            }
            $result[] = $variable;
        }

        return array_values($result);
    };

    $versionDeclarations = static function (int $versionId) use ($contracts): array {
        $version = $contracts->findTemplateVersion($versionId);
        if ($version === null) {
            throw new RuntimeException('La versión contractual no es válida.');
        }
        try {
            $declarations = json_decode((string) $version['variables_json'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('La versión contractual no es válida.');
        }
        if (!is_array($declarations) || !array_is_list($declarations)) {
            throw new RuntimeException('La versión contractual no es válida.');
        }

        return $declarations;
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

    $draftContextForCandidate = static function (int $candidateId) use ($contracts, $candidates, $properties): ?array {
        if ($candidateId <= 0 || !$contracts->canCreateDraftForCandidate($candidateId)) {
            return null;
        }
        $candidate = $candidates->findCandidate($candidateId);
        if ($candidate === null) {
            return null;
        }
        $unit = $properties->findUnit((int) $candidate['unit_id']);
        if ($unit === null) {
            return null;
        }
        $documentType = match ((string) $unit['type']) {
            'apartment' => 'urban_housing',
            'parking' => 'independent_parking',
            default => null,
        };
        if ($documentType === null) {
            return null;
        }

        return [
            'candidate_id' => (int) $candidate['id'],
            'candidate_name' => (string) $candidate['full_name'],
            'candidate_document_type' => (string) $candidate['document_type'],
            'candidate_document_number' => (string) $candidate['document_number'],
            'property_name' => (string) ($candidate['property_name'] ?? ''),
            'unit_code' => (string) ($candidate['unit_code'] ?? $unit['code'] ?? ''),
            'document_type' => $documentType,
            'monthly_rent' => (string) ($unit['monthly_price'] ?? ''),
            'administration_fee' => $unit['administration_fee'] ?? null,
        ];
    };

    $app->get('/admin/contracts', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use (
        $adminGuard,
        $contracts,
        $view,
        $csrf,
        $versionOptions,
        $versionDeclarations,
        $draftContextForCandidate,
    ): ResponseInterface {
        $user = $adminGuard($response, 'contracts.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $canManage = Permissions::allows($user, 'contracts.manage');
        $query = $request->getQueryParams();
        $candidateId = (int) ($query['candidate_id'] ?? 0);
        $candidateRequested = $candidateId > 0;
        if ($canManage && $candidateRequested && !Permissions::allows($user, 'candidates.view')) {
            return $response->withStatus(403);
        }
        $draftContext = null;
        $availableVersions = [];
        $selectedVersionId = null;
        $manualVariableDeclarations = [];
        if ($canManage && $candidateRequested) {
            $draftContext = $draftContextForCandidate($candidateId);
            if ($draftContext !== null) {
                $availableVersions = $versionOptions((string) $draftContext['document_type'], true);
                $requestedVersionId = (int) ($query['template_version_id'] ?? 0);
                foreach ($availableVersions as $option) {
                    if ((int) $option['id'] === $requestedVersionId) {
                        $selectedVersionId = $requestedVersionId;
                        $manualVariableDeclarations = $versionDeclarations($requestedVersionId);
                        break;
                    }
                }
            }
        }

        $response->getBody()->write($view->renderApp('contracts', [
            'pageTitle' => 'Contratos',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'contracts' => $contracts->listContracts(),
            'versionOptions' => $availableVersions,
            'selectedVersionId' => $selectedVersionId,
            'manualVariableDeclarations' => $manualVariableDeclarations,
            'draftContext' => $draftContext,
            'candidateRequested' => $candidateRequested,
            'canManage' => $canManage,
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/admin/contracts', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($adminGuard, $contracts, $manualValues, $taskValues, $annexValues, $path): ResponseInterface {
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
                    'manual_variables' => $manualValues($body),
                    'tasks' => $taskValues($body),
                    'annexes' => $annexValues($body),
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
    ) use (
        $adminGuard,
        $contracts,
        $leases,
        $finance,
        $view,
        $csrf,
        $versionOptions,
        $versionDeclarations,
        $path,
    ): ResponseInterface {
        $user = $adminGuard($response, ['contracts.view', 'finance.view', 'finance.manage']);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $contract = $contracts->findContract((int) $args['id']);
        if ($contract === null) {
            return $response->withStatus(404);
        }

        $canViewContract = Permissions::allows($user, 'contracts.view');
        $lease = $leases->findByContract((int) $contract['id']);
        if (!$canViewContract && $lease === null) {
            return $response->withStatus(404);
        }

        $previewHtml = null;
        if ($canViewContract && $contract['status'] === 'draft') {
            $previewHtml = $contracts->previewHtml((int) $contract['id']);
        }

        $canViewFinance = $lease !== null && (
            Permissions::allows($user, 'finance.view') || Permissions::allows($user, 'finance.manage')
        );
        $canManageFinance = $lease !== null && Permissions::allows($user, 'finance.manage');
        $financeSummary = null;
        $charges = [];
        $today = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
        if ($canViewFinance) {
            $financeSummary = $finance->summaryForLease((int) $lease['id'], $today);
            $charges = $finance->listForLease((int) $lease['id'], $today);
        }

        $response->getBody()->write($view->renderApp('contract_detail', [
            'pageTitle' => $canViewContract
                ? 'Contrato #' . (int) $contract['id']
                : 'Cartera del arrendamiento #' . (int) $lease['id'],
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'contract' => $contract,
            'lease' => $lease,
            'previewHtml' => $previewHtml,
            'versionOptions' => $canViewContract ? $versionOptions((string) $contract['document_type']) : [],
            'manualVariableDeclarations' => $canViewContract
                ? $versionDeclarations((int) $contract['template_version_id'])
                : [],
            'canManage' => Permissions::allows($user, 'contracts.manage'),
            'canViewContract' => $canViewContract,
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
    ) use (
        $adminGuard,
        $contracts,
        $audit,
        $manualValues,
        $taskValues,
        $annexValues,
        $path,
    ): ResponseInterface {
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
                    'manual_variables' => $manualValues($body),
                    'tasks' => $taskValues($body),
                    'annexes' => $annexValues($body),
                ];
                if (array_key_exists('template_version_id', $body)) {
                    $input['template_version_id'] = $body['template_version_id'];
                }
                $contracts->updateDraft($contractId, $input, time());
            } elseif ($action === 'generate') {
                $contracts->generate($contractId, time());
            } elseif ($action === 'update_tasks') {
                $contracts->updateTasks($contractId, $taskValues($body), time());
            } elseif ($action === 'finalize') {
                $contracts->finalize($contractId, (int) $user['id'], time());
            } else {
                throw new RuntimeException('La acción contractual no es válida.');
            }
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        if ($action === 'finalize') {
            $audit->record(
                (int) $user['id'],
                'contract.finalized',
                'contract',
                $contractId,
                'Contrato finalizado.',
                $request->getServerParams()['REMOTE_ADDR'] ?? null,
            );
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
    ) use ($adminGuard, $contracts, $templateVariables, $path): ResponseInterface {
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
                $contracts->createTemplateVersion($templateId, (int) $user['id'], [
                    'body_template' => $body['body_template'] ?? '',
                    'variables' => $templateVariables($body),
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
