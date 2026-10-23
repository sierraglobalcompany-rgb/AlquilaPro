<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Candidates\CandidateDocumentService;
use AlquilaPro\Candidates\CandidateHouseholdService;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Candidates\ScreeningService;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Properties\PropertyService;
use AlquilaPro\Properties\UnitMediaService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var CandidateService $candidates */
    $candidates = $deps['candidates'];
    /** @var CandidateDocumentService $candidateDocuments */
    $candidateDocuments = $deps['candidate_documents'];
    /** @var CandidateHouseholdService $household */
    $household = $deps['candidate_household'];
    /** @var DocumentService $documents */
    $documents = $deps['documents'];
    /** @var ScreeningService $screening */
    $screening = $deps['screening'];
    /** @var PropertyService $properties */
    $properties = $deps['properties'];
    /** @var UnitMediaService $media */
    $media = $deps['unit_media'];
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
    $baseUrl = rtrim((string) ($deps['base_url'] ?? ''), '/');

    $path = static fn (string $value): string => $basePath . $value;

    $wizardSteps = [
        ['key' => 'start', 'label' => 'Inicio'],
        ['key' => 'personal', 'label' => 'Datos personales'],
        ['key' => 'work', 'label' => 'Trabajo'],
        ['key' => 'occupants', 'label' => 'Ocupantes'],
        ['key' => 'references', 'label' => 'Referencias'],
        ['key' => 'documents', 'label' => 'Documentos'],
        ['key' => 'review', 'label' => 'Revisión'],
    ];
    $continuationStepKeys = array_column(array_slice($wizardSteps, 1), 'key');

    $allowAttempt = static function (string $bucket, int $limit, int $now): bool {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        $sessionKey = 'f5_public_throttle';
        $windowSeconds = 600;
        $buckets = is_array($_SESSION[$sessionKey] ?? null) ? $_SESSION[$sessionKey] : [];
        $entry = is_array($buckets[$bucket] ?? null) ? $buckets[$bucket] : [];
        $startedAt = (int) ($entry['started_at'] ?? 0);
        $count = (int) ($entry['count'] ?? 0);

        if ($startedAt <= 0 || $now < $startedAt || ($now - $startedAt) >= $windowSeconds) {
            $startedAt = $now;
            $count = 0;
        }

        if ($count >= $limit) {
            $_SESSION[$sessionKey][$bucket] = [
                'started_at' => $startedAt,
                'count' => $count,
            ];
            return false;
        }

        $_SESSION[$sessionKey][$bucket] = [
            'started_at' => $startedAt,
            'count' => $count + 1,
        ];

        return true;
    };

    $tooMany = static function (ResponseInterface $response): ResponseInterface {
        $response->getBody()->write('Demasiados intentos. Intenta de nuevo más tarde.');

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withStatus(429);
    };

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

    $renderStart = static function (
        ResponseInterface $response,
        array $unit,
        ?string $error = null,
        int $status = 200,
    ) use ($view, $csrf, $path, $wizardSteps): ResponseInterface {
        $response->getBody()->write($view->renderPublic('public_apply_start', [
            'pageTitle' => 'Aplicar',
            'unit' => $unit,
            'action' => $path('/apply/' . $unit['public_key']),
            'wizardSteps' => $wizardSteps,
            'csrfToken' => $csrf->token(),
            'error' => $error,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($status);
    };

    $renderWizard = static function (
        ResponseInterface $response,
        array $candidate,
        string $token,
        string $step,
        ?string $error = null,
        int $status = 200,
        array $householdForm = [],
    ) use ($view, $csrf, $path, $properties, $candidateDocuments, $household, $screening, $wizardSteps): ResponseInterface {
        $continuePath = '/apply/continue/' . $token;
        $documentsByRequirement = [];
        foreach ($candidateDocuments->listDocumentsForCandidate((int) $candidate['id']) as $document) {
            if (is_string($document['requirement_code'] ?? null)) {
                $documentsByRequirement[$document['requirement_code']] = true;
            }
        }

        $stepKeys = array_column($wizardSteps, 'key');
        $stepIndex = array_search($step, $stepKeys, true);
        $stepIndex = is_int($stepIndex) ? $stepIndex : 1;
        $editable = in_array((string) ($candidate['status'] ?? ''), ['new', 'incomplete'], true)
            && ($candidate['submitted_at'] ?? null) === null;
        $previousStep = $stepIndex > 1 ? $stepKeys[$stepIndex - 1] : null;
        $nextStep = $stepKeys[$stepIndex + 1] ?? null;
        $stepLabel = $wizardSteps[$stepIndex]['label'] ?? 'Datos personales';

        $response->getBody()->write($view->renderPublic('public_candidate_wizard', [
            'pageTitle' => $stepLabel,
            'candidate' => $candidate,
            'action' => $path($continuePath),
            'referencesAction' => $path($continuePath . '/references'),
            'unitAction' => $path($continuePath . '/unit'),
            'submitAction' => $path($continuePath . '/submit'),
            'documentActionBase' => $path($continuePath . '/documents'),
            'token' => $token,
            'step' => $step,
            'wizardSteps' => $wizardSteps,
            'stepIndex' => $stepIndex,
            'previousStep' => $previousStep,
            'nextStep' => $nextStep,
            'editable' => $editable,
            'applicationRequirements' => $candidateDocuments->listRequirementsForUnit((int) $candidate['unit_id'], true),
            'documentsByRequirement' => $documentsByRequirement,
            'householdOccupants' => $household->listOccupants((int) $candidate['id']),
            'householdPets' => $household->listPets((int) $candidate['id']),
            'householdForm' => $householdForm,
            'availableUnits' => $step === 'personal' && $editable
                ? $properties->listPublicUnits(null, null, null)
                : [],
            'screening' => $screening->publicForCandidate((int) $candidate['id']),
            'csrfToken' => $csrf->token(),
            'error' => $error,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($status);
    };

    $nextWizardStep = static function (mixed $step) use ($continuationStepKeys): ?string {
        if (!is_string($step)) {
            return null;
        }

        $index = array_search($step, $continuationStepKeys, true);

        return is_int($index) ? ($continuationStepKeys[$index + 1] ?? null) : null;
    };

    $app->post('/admin/units/{id:[0-9]+}/application-requirements', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $candidateDocuments, $path): ResponseInterface {
        $user = $adminGuard($response, 'candidates.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $unitId = (int) $args['id'];
        try {
            $candidateDocuments->createRequirement($unitId, (array) ($request->getParsedBody() ?? []));
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response->withHeader('Location', $path('/admin/units/' . $unitId))->withStatus(303);
    });

    $app->post('/admin/units/{id:[0-9]+}/application-requirements/{requirementId:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $candidateDocuments, $path): ResponseInterface {
        $user = $adminGuard($response, 'candidates.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $unitId = (int) $args['id'];
        try {
            $candidateDocuments->updateRequirement(
                $unitId,
                (int) $args['requirementId'],
                (array) ($request->getParsedBody() ?? []),
            );
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response->withHeader('Location', $path('/admin/units/' . $unitId))->withStatus(303);
    });

    $app->post('/admin/units/{id:[0-9]+}/application-requirements/order', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $candidateDocuments, $path): ResponseInterface {
        $user = $adminGuard($response, 'candidates.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $unitId = (int) $args['id'];
        $body = (array) ($request->getParsedBody() ?? []);
        $requirementIds = $body['requirement_ids'] ?? [];
        try {
            $candidateDocuments->reorderRequirements($unitId, is_array($requirementIds) ? $requirementIds : []);
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response->withHeader('Location', $path('/admin/units/' . $unitId))->withStatus(303);
    });

    $app->get('/admin/candidates', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($adminGuard, $candidates, $view, $csrf): ResponseInterface {
        $user = $adminGuard($response, 'candidates.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $response->getBody()->write($view->renderApp('candidates', [
            'pageTitle' => 'Candidatos',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'candidates' => $candidates->listCandidates(),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/admin/candidates/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $candidates, $candidateDocuments, $household, $documents, $screening, $contracts, $properties, $media, $view, $csrf, $path): ResponseInterface {
        $user = $adminGuard($response, 'candidates.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $candidate = $candidates->findCandidate((int) $args['id']);
        if ($candidate === null) {
            return $response->withStatus(404);
        }

        $candidateId = (int) $candidate['id'];
        $propertyDetails = null;
        $coverPhotoId = null;
        $householdPropertyId = null;
        // Candidate access does not imply access to private property details.
        if (Permissions::allows($user, 'properties.view')) {
            $unit = $properties->findUnit((int) $candidate['unit_id']);
            $property = $unit !== null ? $properties->findProperty((int) $unit['property_id']) : null;
            if ($unit !== null && $property !== null) {
                $householdPropertyId = (int) $property['id'];
                $propertyDetails = [
                    'unit_id' => (int) $unit['id'],
                    'unit_code' => $unit['code'],
                    'type' => $unit['type'],
                    'address' => $property['address'],
                    'city' => $property['city'],
                    'sector' => $property['sector'],
                    'monthly_price' => $unit['monthly_price'],
                    'administration_fee' => $unit['administration_fee'],
                    'area_m2' => $unit['area_m2'],
                    'bedrooms' => $unit['bedrooms'],
                    'bathrooms' => $unit['bathrooms'],
                ];
                foreach ($media->listForUnit((int) $unit['id']) as $item) {
                    if ($item['type'] !== 'photo') {
                        continue;
                    }
                    if ($coverPhotoId === null || $item['is_cover']) {
                        $coverPhotoId = (int) $item['id'];
                    }
                    if ($item['is_cover']) {
                        break;
                    }
                }
            }
        }
        $householdManualAccess = Permissions::allows($user, 'properties.view')
            && Permissions::allows($user, 'documents.view');
        $householdManual = ['authorized' => false, 'document' => null, 'module_url' => null];
        if ($householdManualAccess && $householdPropertyId !== null) {
            $manual = null;
            foreach ($documents->listCurrentForProperty($householdPropertyId) as $document) {
                if (($document['type'] ?? null) === 'manual_convivencia') {
                    $manual = [
                        'id' => (int) $document['id'],
                        'title' => (string) $document['title'],
                        'created_at' => (string) $document['created_at'],
                        'has_file' => is_string($document['stored_name'] ?? null),
                    ];
                    break;
                }
            }
            $householdManual = [
                'authorized' => true,
                'document' => $manual,
                'module_url' => $path('/admin/documents?scope=property&id=' . $householdPropertyId),
            ];
        }
        $contractContext = null;
        if (Permissions::allows($user, 'contracts.view')) {
            $contractContext = [
                'contracts' => $contracts->listForCandidate($candidateId),
                'can_create' => $contracts->canCreateDraftForCandidate($candidateId),
            ];
        }
        $canManageCandidates = Permissions::allows($user, 'candidates.manage');

        $candidateSections = [
            ['key' => 'overview', 'label' => 'Solicitud'],
            ['key' => 'work', 'label' => 'Información laboral'],
            ['key' => 'household', 'label' => 'Ocupantes y mascotas'],
            ['key' => 'references', 'label' => 'Referencias'],
            ['key' => 'documents', 'label' => 'Documentos'],
            ['key' => 'screening', 'label' => 'Estudio de aseguradora'],
        ];
        if ($contractContext !== null) {
            $candidateSections[] = ['key' => 'contracts', 'label' => 'Contratos'];
        }
        if ($canManageCandidates) {
            $candidateSections[] = ['key' => 'manage', 'label' => 'Administrar'];
        }

        $query = $request->getQueryParams();
        $requestedSection = $query['section'] ?? null;
        $availableSectionKeys = array_column($candidateSections, 'key');
        $activeSection = is_string($requestedSection) && in_array($requestedSection, $availableSectionKeys, true)
            ? $requestedSection
            : 'overview';

        $response->getBody()->write($view->renderApp('candidate_detail', [
            'pageTitle' => 'Candidato',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'candidate' => $candidate,
            'householdOccupants' => $household->listOccupants($candidateId),
            'householdPets' => $household->listPets($candidateId),
            'householdCanManage' => Permissions::allows($user, 'candidates.manage'),
            'householdManual' => $householdManual,
            'householdPhotoPath' => $path('/admin/candidates/' . $candidateId . '/household'),
            'propertyDetails' => $propertyDetails,
            'coverPhotoId' => $coverPhotoId,
            'documents' => $candidateDocuments->listDocumentsForCandidate($candidateId),
            'documentRequirements' => $activeSection === 'documents'
                ? $candidateDocuments->listRequirementsForUnit((int) $candidate['unit_id'], true)
                : [],
            'screening' => $screening->findForCandidate($candidateId),
            'contractContext' => $contractContext,
            'canManageCandidates' => $canManageCandidates,
            'candidateSections' => $candidateSections,
            'activeSection' => $activeSection,
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/admin/candidates/{id:[0-9]+}/household/{kind}/{recordId:[0-9]+}/photo', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $candidates, $household): ResponseInterface {
        $user = $adminGuard($response, 'candidates.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $candidateId = (int) $args['id'];
        if ($candidates->findCandidate($candidateId) === null) {
            return $response->withStatus(404);
        }
        $photo = $household->privatePhoto($candidateId, (string) $args['kind'], (int) $args['recordId']);
        if ($photo === null) {
            return $response->withStatus(404);
        }
        $bytes = file_get_contents($photo['path']);
        if ($bytes === false) {
            return $response->withStatus(404);
        }
        $response->getBody()->write($bytes);
        return $response
            ->withHeader('Content-Type', $photo['mime'])
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->post('/admin/candidates/{id:[0-9]+}/household/{kind}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $candidates, $household, $path): ResponseInterface {
        $user = $adminGuard($response, 'candidates.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $candidateId = (int) $args['id'];
        if ($candidates->findCandidate($candidateId) === null) {
            return $response->withStatus(404);
        }
        $kind = (string) $args['kind'];
        $body = (array) ($request->getParsedBody() ?? []);
        $action = (string) ($body['action'] ?? 'save');
        try {
            if ($action === 'delete') {
                $recordId = filter_var($body['record_id'] ?? null, FILTER_VALIDATE_INT);
                if (!is_int($recordId) || $recordId <= 0) {
                    return $response->withStatus(422);
                }
                $household->deleteRecord($kind, $candidateId, $recordId);
            } elseif ($action === 'save') {
                $recordId = trim((string) ($body['record_id'] ?? '')) === ''
                    ? null
                    : filter_var($body['record_id'], FILTER_VALIDATE_INT);
                if ($recordId !== null && (!is_int($recordId) || $recordId <= 0)) {
                    return $response->withStatus(422);
                }
                $uploads = $request->getUploadedFiles();
                $photo = $uploads['photo'] ?? null;
                if ($photo !== null && !$photo instanceof UploadedFileInterface) {
                    return $response->withStatus(422);
                }
                unset($body['csrf_token'], $body['action'], $body['record_id']);
                $household->saveRecord($kind, $candidateId, $recordId, $body, $photo, time());
            } else {
                return $response->withStatus(422);
            }
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }
        return $response
            ->withHeader('Location', $path('/admin/candidates/' . $candidateId . '?section=household'))
            ->withStatus(303);
    });

    $app->post('/admin/candidates/{id:[0-9]+}/screening', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $candidates, $screening, $path): ResponseInterface {
        $user = $adminGuard($response, 'candidates.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $candidateId = (int) $args['id'];
        if ($candidates->findCandidate($candidateId) === null) {
            return $response->withStatus(404);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        unset($body['csrf_token']);

        try {
            if ($screening->findForCandidate($candidateId) === null) {
                $screening->createForCandidate($candidateId, (int) $user['id'], $body, time());
            } else {
                $screening->updateForCandidate($candidateId, $body, time());
            }
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response->withHeader('Location', $path('/admin/candidates/' . $candidateId))->withStatus(303);
    });

    $app->post('/admin/candidates/{id:[0-9]+}/status', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $candidates, $path): ResponseInterface {
        $user = $adminGuard($response, 'candidates.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $candidateId = (int) $args['id'];
        $body = (array) ($request->getParsedBody() ?? []);
        try {
            $candidates->transitionStatus($candidateId, (string) ($body['status'] ?? ''));
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        return $response->withHeader('Location', $path('/admin/candidates/' . $candidateId))->withStatus(303);
    });

    $app->post('/admin/candidates/{id:[0-9]+}/token/regenerate', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $candidates, $baseUrl, $path): ResponseInterface {
        $user = $adminGuard($response, 'candidates.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        try {
            $token = $candidates->regenerateToken((int) $args['id'], time());
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withStatus(422);
        }

        $continuePath = '/apply/continue/' . $token;
        $link = $baseUrl !== '' ? $baseUrl . $continuePath : $path($continuePath);
        $response->getBody()->write($link);

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->get('/admin/candidates/{id:[0-9]+}/documents/{documentId:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($adminGuard, $candidateDocuments): ResponseInterface {
        $user = $adminGuard($response, 'candidates.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $document = $candidateDocuments->privateDocument((int) $args['id'], (int) $args['documentId']);
        if ($document === null) {
            return $response->withStatus(404);
        }

        $bytes = file_get_contents((string) $document['path']);
        if ($bytes === false) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', (string) $document['mime'])
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->get('/apply/{publicKey}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($properties, $renderStart): ResponseInterface {
        $unit = $properties->findPublicUnit((string) ($args['publicKey'] ?? ''));
        if ($unit === null) {
            return $response->withStatus(404);
        }

        return $renderStart($response, $unit);
    });

    $app->post('/apply/{publicKey}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($properties, $candidates, $allowAttempt, $tooMany, $renderStart, $path): ResponseInterface {
        $publicKey = (string) ($args['publicKey'] ?? '');
        $unit = $properties->findPublicUnit($publicKey);
        if ($unit === null) {
            return $response->withStatus(404);
        }

        if (!$allowAttempt('start', 5, time())) {
            return $tooMany($response);
        }

        try {
            $started = $candidates->startCandidate(
                $publicKey,
                (array) ($request->getParsedBody() ?? []),
                time(),
            );
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
            if ($error === 'No fue posible iniciar la solicitud.') {
                $error = 'No fue posible iniciar la solicitud. Si ya tienes un proceso, usa tu enlace de continuación o contacta a administración.';
            }

            return $renderStart($response, $unit, $error, 422);
        }

        return $response
            ->withHeader('Location', $path('/apply/continue/' . $started['continuation_token']))
            ->withStatus(303);
    });

    $app->get('/apply/continue/{token}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($candidates, $path): ResponseInterface {
        $token = (string) ($args['token'] ?? '');
        $step = $candidates->firstIncompleteWizardStep($token, time());
        if ($step === null) {
            return $response->withStatus(404);
        }

        return $response
            ->withHeader('Location', $path('/apply/continue/' . $token . '/' . $step))
            ->withStatus(303);
    });

    $app->get('/apply/continue/{token}/household/{kind}/{recordId:[0-9]+}/photo', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($candidates, $household): ResponseInterface {
        $token = (string) ($args['token'] ?? '');
        $candidate = $candidates->findByToken($token, time());
        if ($candidate === null) {
            return $response->withStatus(404);
        }
        $photo = $household->privatePhoto((int) $candidate['id'], (string) $args['kind'], (int) $args['recordId']);
        if ($photo === null) {
            return $response->withStatus(404);
        }
        $bytes = file_get_contents($photo['path']);
        if ($bytes === false) {
            return $response->withStatus(404);
        }
        $response->getBody()->write($bytes);
        return $response
            ->withHeader('Content-Type', $photo['mime'])
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->get('/apply/continue/{token}/{step}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($candidates, $renderWizard, $path, $continuationStepKeys): ResponseInterface {
        $token = (string) ($args['token'] ?? '');
        $step = (string) ($args['step'] ?? '');
        if (!in_array($step, $continuationStepKeys, true)) {
            return $response->withStatus(404);
        }

        $candidate = $candidates->findByToken($token, time());
        if ($candidate === null) {
            return $response->withStatus(404);
        }

        if ((!in_array((string) ($candidate['status'] ?? ''), ['new', 'incomplete'], true)
                || ($candidate['submitted_at'] ?? null) !== null)
            && $step !== 'review') {
            return $response
                ->withHeader('Location', $path('/apply/continue/' . $token . '/review'))
                ->withStatus(303);
        }

        return $renderWizard($response, $candidate, $token, $step);
    });

    $app->post('/apply/continue/{token}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($candidates, $household, $allowAttempt, $tooMany, $renderWizard, $path, $nextWizardStep, $continuationStepKeys): ResponseInterface {
        $token = (string) ($args['token'] ?? '');
        $candidate = $candidates->findByToken($token, time());
        if ($candidate === null) {
            return $response->withStatus(404);
        }

        if (!$allowAttempt('continue', 10, time())) {
            return $tooMany($response);
        }

        $input = (array) ($request->getParsedBody() ?? []);
        $step = $input['wizard_step'] ?? null;
        unset($input['csrf_token']);
        unset($input['wizard_step']);

        if ($step === 'occupants') {
            $occupants = $input['occupants'] ?? null;
            $pets = $input['pets'] ?? null;
            if (($input['occupants_present'] ?? null) !== '1'
                || ($input['pets_present'] ?? null) !== '1'
                || !is_array($occupants)
                || !is_array($pets)
            ) {
                return $renderWizard($response, $candidate, $token, 'occupants', 'Las fichas del hogar no son válidas.', 422);
            }
            $householdForm = $input;
            unset($input['occupants'], $input['pets'], $input['occupants_present'], $input['pets_present']);
            try {
                $household->saveWizard(
                    (int) $candidate['id'],
                    $occupants,
                    $pets,
                    $request->getUploadedFiles(),
                    static fn (): array => $candidates->updateByToken($token, $input, time()),
                    time(),
                );
            } catch (RuntimeException $exception) {
                return $renderWizard($response, $candidate, $token, 'occupants', $exception->getMessage(), 422, $householdForm);
            }
            if ($candidates->firstIncompleteWizardStep($token, time()) === 'occupants') {
                return $response
                    ->withHeader('Location', $path('/apply/continue/' . $token . '/occupants'))
                    ->withStatus(303);
            }
            return $response
                ->withHeader('Location', $path('/apply/continue/' . $token . '/references'))
                ->withStatus(303);
        }

        try {
            $candidates->updateByToken($token, $input, time());
        } catch (RuntimeException $exception) {
            $errorStep = is_string($step) && in_array($step, $continuationStepKeys, true) ? $step : 'personal';
            return $renderWizard($response, $candidate, $token, $errorStep, $exception->getMessage(), 422);
        }

        $nextStep = $nextWizardStep($step);
        if ($step === 'work' && $candidates->firstIncompleteWizardStep($token, time()) === 'work') {
            $nextStep = 'work';
        }
        if ($nextStep === null) {
            return $response
                ->withHeader('Location', $path('/apply/continue/' . $token))
                ->withStatus(303);
        }

        return $response
            ->withHeader('Location', $path('/apply/continue/' . $token . '/' . $nextStep))
            ->withStatus(303);
    });

    $app->post('/apply/continue/{token}/references', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($candidates, $allowAttempt, $tooMany, $renderWizard, $path, $nextWizardStep): ResponseInterface {
        $token = (string) ($args['token'] ?? '');
        $candidate = $candidates->findByToken($token, time());
        if ($candidate === null) {
            return $response->withStatus(404);
        }

        if (!$allowAttempt('continue', 10, time())) {
            return $tooMany($response);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $references = $body['references'] ?? [];
        if (!is_array($references)) {
            return $renderWizard($response, $candidate, $token, 'references', 'Las referencias no son válidas.', 422);
        }

        try {
            $candidates->replaceReferencesByToken($token, $references, time());
        } catch (RuntimeException $exception) {
            return $renderWizard($response, $candidate, $token, 'references', $exception->getMessage(), 422);
        }

        $nextStep = $nextWizardStep($body['wizard_step'] ?? null);
        if ($nextStep === null) {
            return $response
                ->withHeader('Location', $path('/apply/continue/' . $token))
                ->withStatus(303);
        }

        return $response
            ->withHeader('Location', $path('/apply/continue/' . $token . '/' . $nextStep))
            ->withStatus(303);
    });

    $app->post('/apply/continue/{token}/unit', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($candidates, $allowAttempt, $tooMany, $renderWizard, $path): ResponseInterface {
        $token = (string) ($args['token'] ?? '');
        $candidate = $candidates->findByToken($token, time());
        if ($candidate === null) {
            return $response->withStatus(404);
        }

        if (!$allowAttempt('continue', 10, time())) {
            return $tooMany($response);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        try {
            $candidates->changeUnitByToken($token, (string) ($body['public_key'] ?? ''), time());
        } catch (RuntimeException $exception) {
            return $renderWizard($response, $candidate, $token, 'personal', $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/apply/continue/' . $token . '/personal'))
            ->withStatus(303);
    });

    $app->post('/apply/continue/{token}/documents/{requirementCode}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($candidates, $candidateDocuments, $allowAttempt, $tooMany, $renderWizard, $path): ResponseInterface {
        $token = (string) ($args['token'] ?? '');
        $candidate = $candidates->findByToken($token, time());
        if ($candidate === null) {
            return $response->withStatus(404);
        }

        if (!$allowAttempt('continue', 10, time())) {
            return $tooMany($response);
        }

        $upload = $request->getUploadedFiles()['document'] ?? null;
        if (!$upload instanceof UploadedFileInterface || $upload->getError() === UPLOAD_ERR_NO_FILE) {
            return $renderWizard($response, $candidate, $token, 'documents', 'Selecciona un documento.', 422);
        }

        try {
            $candidateDocuments->addOrReplaceDocument(
                (int) $candidate['id'],
                (string) ($args['requirementCode'] ?? ''),
                $upload,
            );
        } catch (RuntimeException $exception) {
            return $renderWizard($response, $candidate, $token, 'documents', $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/apply/continue/' . $token . '/documents'))
            ->withStatus(303);
    });

    $app->post('/apply/continue/{token}/submit', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($candidates, $allowAttempt, $tooMany, $renderWizard, $path): ResponseInterface {
        $token = (string) ($args['token'] ?? '');
        $candidate = $candidates->findByToken($token, time());
        if ($candidate === null) {
            return $response->withStatus(404);
        }

        if (!$allowAttempt('continue', 10, time())) {
            return $tooMany($response);
        }

        try {
            $candidates->submitByToken($token, time());
        } catch (RuntimeException $exception) {
            return $renderWizard($response, $candidate, $token, 'review', $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/apply/continue/' . $token . '/review'))
            ->withStatus(303);
    });
};
