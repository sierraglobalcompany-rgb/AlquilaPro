<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Community\CommunityService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var CommunityService $community */
    $community = $deps['community'];
    /** @var DocumentService $documents */
    $documents = $deps['documents'];
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

    $redirect = static fn (ResponseInterface $response, string $location): ResponseInterface =>
        $response->withHeader('Location', $path($location))->withStatus(303);

    $app->get('/admin/community', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $community, $documents, $view, $csrf, $path): ResponseInterface {
        $user = $staffGuard($response, 'community.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $query = $request->getQueryParams();
        $status = isset($query['status']) && is_string($query['status']) && $query['status'] !== ''
            ? $query['status']
            : null;
        $propertyId = filter_var($query['property_id'] ?? null, FILTER_VALIDATE_INT);
        $propertyId = is_int($propertyId) && $propertyId > 0 ? $propertyId : null;

        $incidentRows = [];
        foreach ($community->listForStaff($status) as $incident) {
            $evidenceRows = [];
            foreach ($documents->listCurrentForIncident((int) $incident['id']) as $document) {
                $versions = [];
                foreach ($documents->history((int) $document['id']) as $version) {
                    $versions[] = [
                        'id' => (int) $version['id'],
                        'title' => (string) $version['title'],
                        'created_at' => (string) $version['created_at'],
                        'file_url' => is_string($version['stored_name'] ?? null)
                            ? $path('/admin/community/incidents/' . (int) $incident['id']
                                . '/documents/' . (int) $version['id'] . '/file')
                            : null,
                    ];
                }
                $evidenceRows[] = [
                    'id' => (int) $document['id'],
                    'title' => (string) $document['title'],
                    'created_at' => (string) $document['created_at'],
                    'versions' => $versions,
                ];
            }
            $incidentRows[] = [
                'id' => (int) $incident['id'],
                'lease_id' => (int) $incident['lease_id'],
                'category' => (string) $incident['category'],
                'occurred_on' => (string) $incident['occurred_on'],
                'description' => (string) $incident['description'],
                'status' => (string) $incident['status'],
                'resolution_notes' => $incident['resolution_notes'],
                'void_reason' => $incident['void_reason'],
                'evidence' => $evidenceRows,
            ];
        }

        $legalNotices = [];
        if ($propertyId !== null) {
            foreach ($documents->listCurrentForProperty($propertyId) as $document) {
                if ($document['type'] !== 'legal_notice' || !is_array($document['content_json'] ?? null)) {
                    continue;
                }
                $legalNotices[] = [
                    'id' => (int) $document['id'],
                    'title' => (string) $document['title'],
                    'created_at' => (string) $document['created_at'],
                    'content_json' => $document['content_json'],
                ];
            }
        }

        $response->getBody()->write($view->renderApp('community', [
            'pageTitle' => 'Convivencia y avisos legales',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'incidents' => $incidentRows,
            'statusFilter' => $status,
            'propertyId' => $propertyId,
            'legalNotices' => $legalNotices,
            'canManage' => Permissions::allows($user, 'community.manage'),
        ]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->post('/admin/community/incidents', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $community, $redirect): ResponseInterface {
        $user = $staffGuard($response, 'community.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $body = (array) ($request->getParsedBody() ?? []);
        $leaseId = filter_var($body['lease_id'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($leaseId) || $leaseId <= 0) {
            return $response->withStatus(422);
        }
        try {
            $community->create(
                $leaseId,
                (string) ($body['category'] ?? ''),
                (string) ($body['occurred_on'] ?? ''),
                (string) ($body['description'] ?? ''),
                (int) $user['id'],
                time(),
            );
        } catch (Throwable) {
            return $response->withStatus(422);
        }
        return $redirect($response, '/admin/community');
    });

    $app->post('/admin/community/incidents/{id:[0-9]+}/review', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $community, $redirect): ResponseInterface {
        $user = $staffGuard($response, 'community.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        try {
            $community->markReviewed((int) $args['id'], (int) $user['id'], time());
        } catch (Throwable) {
            return $response->withStatus(422);
        }
        return $redirect($response, '/admin/community');
    });

    $app->post('/admin/community/incidents/{id:[0-9]+}/close', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $community, $redirect): ResponseInterface {
        $user = $staffGuard($response, 'community.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $body = (array) ($request->getParsedBody() ?? []);
        try {
            $community->close((int) $args['id'], (string) ($body['resolution_notes'] ?? ''), (int) $user['id'], time());
        } catch (Throwable) {
            return $response->withStatus(422);
        }
        return $redirect($response, '/admin/community');
    });

    $app->post('/admin/community/incidents/{id:[0-9]+}/void', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $community, $redirect): ResponseInterface {
        $user = $staffGuard($response, 'community.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $body = (array) ($request->getParsedBody() ?? []);
        try {
            $community->void((int) $args['id'], (string) ($body['void_reason'] ?? ''), (int) $user['id'], time());
        } catch (Throwable) {
            return $response->withStatus(422);
        }
        return $redirect($response, '/admin/community');
    });

    $app->post('/admin/community/incidents/{id:[0-9]+}/evidence', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $documents, $redirect): ResponseInterface {
        $user = $staffGuard($response, 'community.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $body = (array) ($request->getParsedBody() ?? []);
        $upload = $request->getUploadedFiles()['evidence'] ?? null;
        if (!$upload instanceof UploadedFileInterface || $upload->getError() !== UPLOAD_ERR_OK) {
            return $response->withStatus(422);
        }
        $supersedes = null;
        if (isset($body['supersedes_document_id']) && $body['supersedes_document_id'] !== '') {
            $value = filter_var($body['supersedes_document_id'], FILTER_VALIDATE_INT);
            if (!is_int($value) || $value <= 0) {
                return $response->withStatus(422);
            }
            $supersedes = $value;
        }
        try {
            $documents->createIncidentFileVersion(
                (int) $args['id'],
                (string) ($body['title'] ?? ''),
                $upload,
                $supersedes,
                (int) $user['id'],
                time(),
            );
        } catch (Throwable) {
            return $response->withStatus(422);
        }
        return $redirect($response, '/admin/community');
    });

    $app->post('/admin/community/legal-notices', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $documents, $redirect): ResponseInterface {
        $user = $staffGuard($response, 'community.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $body = (array) ($request->getParsedBody() ?? []);
        $propertyId = filter_var($body['property_id'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($propertyId) || $propertyId <= 0) {
            return $response->withStatus(422);
        }
        $supersedes = null;
        if (isset($body['supersedes_document_id']) && $body['supersedes_document_id'] !== '') {
            $value = filter_var($body['supersedes_document_id'], FILTER_VALIDATE_INT);
            if (!is_int($value) || $value <= 0) {
                return $response->withStatus(422);
            }
            $supersedes = $value;
        }
        $sourceUrl = trim((string) ($body['source_url'] ?? ''));
        $effectiveOn = trim((string) ($body['effective_on'] ?? ''));
        try {
            $documents->createContentVersion(
                $propertyId,
                null,
                'legal_notice',
                (string) ($body['title'] ?? ''),
                [
                    'source_label' => (string) ($body['source_label'] ?? ''),
                    'source_url' => $sourceUrl === '' ? null : $sourceUrl,
                    'effective_on' => $effectiveOn === '' ? null : $effectiveOn,
                    'notice_text' => (string) ($body['notice_text'] ?? ''),
                ],
                $supersedes,
                (int) $user['id'],
                time(),
            );
        } catch (Throwable) {
            return $response->withStatus(422);
        }
        return $redirect($response, '/admin/community?property_id=' . $propertyId);
    });

    $app->get('/admin/community/incidents/{incidentId:[0-9]+}/documents/{documentId:[0-9]+}/file', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $documents): ResponseInterface {
        $user = $staffGuard($response, 'community.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $document = $documents->find((int) $args['documentId']);
        if ($document === null || $document['type'] !== 'incident_evidence'
            || (int) $document['community_incident_id'] !== (int) $args['incidentId']
        ) {
            return $response->withStatus(404);
        }
        $file = $documents->privateFile((int) $document['id']);
        if ($file === null) {
            return $response->withStatus(404);
        }
        $response->getBody()->write($file['contents']);
        $extension = match ($file['mime']) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
        return $response
            ->withHeader('Content-Type', $file['mime'])
            ->withHeader('Content-Disposition', 'attachment; filename="community-evidence-' . (int) $document['id'] . '.' . $extension . '"')
            ->withHeader('Content-Length', (string) $file['size_bytes'])
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    });
};
