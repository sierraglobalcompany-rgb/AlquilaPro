<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Insurance\InsuranceService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var InsuranceService $insurance */
    $insurance = $deps['insurance'];
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
    $todayProvider = $deps['today'] ?? static fn (): string =>
        (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');

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

    $jsonList = static function (mixed $value, string $message): array {
        if (!is_string($value)) {
            throw new RuntimeException($message);
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException($message);
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new RuntimeException($message);
        }
        return $decoded;
    };

    $optionalUpload = static function (ServerRequestInterface $request, string $field): ?UploadedFileInterface {
        $upload = $request->getUploadedFiles()[$field] ?? null;
        if ($upload === null || ($upload instanceof UploadedFileInterface && $upload->getError() === UPLOAD_ERR_NO_FILE)) {
            return null;
        }
        if (!$upload instanceof UploadedFileInterface) {
            throw new RuntimeException('No fue posible recibir el documento.');
        }
        return $upload;
    };

    $documentResponse = static function (ResponseInterface $response, ?array $document): ResponseInterface {
        if ($document === null || !is_string($document['bytes'] ?? null) || !is_string($document['mime'] ?? null)) {
            return $response->withStatus(404);
        }
        $bytes = $document['bytes'];
        $response->getBody()->write($bytes);
        return $response
            ->withHeader('Content-Type', $document['mime'])
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Content-Disposition', 'attachment; filename="insurance-document"')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    };

    $app->get('/admin/insurance', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $insurance, $view, $csrf, $todayProvider, $basePath): ResponseInterface {
        $user = $staffGuard($response, 'insurance.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $policies = array_map(
            static function (array $policy) use ($insurance): array {
                $policy['claims'] = $insurance->listClaims((int) $policy['id']);
                return $policy;
            },
            $insurance->listPolicies($todayProvider()),
        );
        $response->getBody()->write($view->renderApp('insurance', [
            'pageTitle' => 'Seguros',
            'user' => $user,
            'activeNav' => '',
            'csrfToken' => $csrf->token(),
            'policies' => $policies,
            'canManage' => Permissions::allows($user, 'insurance.manage'),
            'basePath' => $basePath,
        ]));
        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $createPolicy = static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $leaseId,
        array $user,
    ) use ($insurance, $path, $jsonList): ResponseInterface {
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $coverage = $jsonList($form['coverage_json'] ?? null, 'El snapshot de coberturas no es válido.');
            $supersedes = trim((string) ($form['supersedes_policy_id'] ?? ''));
            if ($supersedes !== '' && preg_match('/\A[1-9]\d*\z/', $supersedes) !== 1) {
                throw new RuntimeException('La póliza anterior no es válida.');
            }
            $insurance->createPolicy($leaseId, [
                'provider_name' => $form['provider_name'] ?? null,
                'product_name' => $form['product_name'] ?? null,
                'policy_number' => $form['policy_number'] ?? null,
                'coverage_start' => $form['coverage_start'] ?? null,
                'coverage_end' => $form['coverage_end'] ?? null,
                'coverage' => $coverage,
                'supersedes_policy_id' => $supersedes === '' ? null : (int) $supersedes,
                'internal_notes' => $form['internal_notes'] ?? null,
            ], (int) $user['id'], time());
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible guardar la póliza.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/insurance'))->withStatus(303);
    };

    $app->post('/admin/insurance/policies', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $createPolicy): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        $leaseId = $form['lease_id'] ?? null;
        if ((!is_int($leaseId) && (!is_string($leaseId) || preg_match('/\A[1-9]\d*\z/', $leaseId) !== 1))) {
            return $response->withStatus(422);
        }
        return $createPolicy($request, $response, (int) $leaseId, $user);
    });

    $app->post('/admin/leases/{leaseId:[0-9]+}/insurance-policies', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $createPolicy): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        return $createPolicy($request, $response, (int) $args['leaseId'], $user);
    });

    $app->post('/admin/insurance/policies/{id:[0-9]+}/notes', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $path): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        $notes = $form['internal_notes'] ?? null;
        if ($notes !== null && !is_string($notes)) {
            $response->getBody()->write('No fue posible actualizar las notas internas.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        try {
            $insurance->updatePolicyNotes((int) $args['id'], $notes, time());
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible actualizar las notas internas.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/insurance'))->withStatus(303);
    });

    $app->post('/admin/insurance/policies/{id:[0-9]+}/cancel', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $path): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $insurance->cancelPolicy((int) $args['id'], (int) $user['id'], (string) ($form['reason'] ?? ''), time());
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible cancelar la póliza.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/insurance'))->withStatus(303);
    });

    $app->post('/admin/insurance/policies/{id:[0-9]+}/documents', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $optionalUpload, $path): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $upload = $optionalUpload($request, 'document');
            if ($upload === null) {
                throw new RuntimeException('Selecciona un documento.');
            }
            $insurance->addPolicyDocument((int) $args['id'], (string) ($form['label'] ?? ''), $upload, time());
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible guardar el documento de póliza.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/insurance'))->withStatus(303);
    });

    $app->post('/admin/insurance/policies/{id:[0-9]+}/documents/{documentId:[a-f0-9]{32}}/remove', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $path): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        try {
            $insurance->removePolicyDocument((int) $args['id'], (string) $args['documentId'], time());
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible retirar el documento de póliza.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/insurance'))->withStatus(303);
    });

    $app->get('/admin/insurance/policies/{id:[0-9]+}/documents/{documentId:[a-f0-9]{32}}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $documentResponse): ResponseInterface {
        $user = $staffGuard($response, 'insurance.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        return $documentResponse($response, $insurance->readPolicyDocument((int) $args['id'], (string) $args['documentId']));
    });

    $app->post('/admin/insurance/policies/{id:[0-9]+}/claims', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $jsonList, $optionalUpload, $path, $todayProvider): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $claimedAmount = trim((string) ($form['claimed_amount'] ?? ''));
            $insurance->createClaim((int) $args['id'], [
                'incident_on' => $form['incident_on'] ?? null,
                'description' => $form['description'] ?? null,
                'claimed_amount' => $claimedAmount === '' ? null : $claimedAmount,
                'checklist' => $jsonList($form['checklist_json'] ?? '[]', 'El checklist no es válido.'),
                'document_label' => $form['document_label'] ?? null,
            ], $optionalUpload($request, 'document'), (int) $user['id'], $todayProvider(), time());
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible registrar la reclamación.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/insurance'))->withStatus(303);
    });

    $app->post('/admin/insurance/claims/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $path, $todayProvider): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        $amount = trim((string) ($form['claimed_amount'] ?? ''));
        try {
            $insurance->updateDraftClaim((int) $args['id'], [
                'incident_on' => $form['incident_on'] ?? null,
                'description' => $form['description'] ?? null,
                'claimed_amount' => $amount === '' ? null : $amount,
            ], $todayProvider(), time());
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible actualizar el borrador.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/insurance'))->withStatus(303);
    });

    $app->post('/admin/insurance/claims/{id:[0-9]+}/checklist', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $jsonList, $path): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $insurance->updateClaimChecklist((int) $args['id'], $jsonList($form['checklist_json'] ?? null, 'El checklist no es válido.'), time());
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible actualizar el checklist.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/insurance'))->withStatus(303);
    });

    $app->post('/admin/insurance/claims/{id:[0-9]+}/transition', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $path, $todayProvider): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        $details = [];
        foreach (['filed_on', 'radicado', 'resolved_on', 'resolution_notes'] as $field) {
            if (array_key_exists($field, $form)) {
                $value = is_string($form[$field]) ? trim($form[$field]) : '';
                $details[$field] = $value === '' ? null : $value;
            }
        }
        try {
            $insurance->transitionClaim(
                (int) $args['id'],
                (string) ($form['target_status'] ?? ''),
                $details,
                (int) $user['id'],
                $todayProvider(),
                time(),
            );
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible cambiar el estado de la reclamación.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/insurance'))->withStatus(303);
    });

    $app->post('/admin/insurance/claims/{id:[0-9]+}/documents', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $optionalUpload, $path): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $form = (array) ($request->getParsedBody() ?? []);
        try {
            $upload = $optionalUpload($request, 'document');
            if ($upload === null) {
                throw new RuntimeException('Selecciona un documento.');
            }
            $insurance->addClaimDocument((int) $args['id'], (string) ($form['label'] ?? ''), $upload, time());
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible guardar el documento de reclamación.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/insurance'))->withStatus(303);
    });

    $app->post('/admin/insurance/claims/{id:[0-9]+}/documents/{documentId:[a-f0-9]{32}}/remove', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $path): ResponseInterface {
        $user = $staffGuard($response, 'insurance.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        try {
            $insurance->removeClaimDocument((int) $args['id'], (string) $args['documentId'], time());
        } catch (RuntimeException) {
            $response->getBody()->write('No fue posible retirar el documento de reclamación.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response->withHeader('Location', $path('/admin/insurance'))->withStatus(303);
    });

    $app->get('/admin/insurance/claims/{id:[0-9]+}/documents/{documentId:[a-f0-9]{32}}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $insurance, $documentResponse): ResponseInterface {
        $user = $staffGuard($response, 'insurance.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        return $documentResponse($response, $insurance->readClaimDocument((int) $args['id'], (string) $args['documentId']));
    });
};
