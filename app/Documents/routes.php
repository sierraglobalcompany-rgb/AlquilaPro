<?php

declare(strict_types=1);

use AlquilaPro\Audit\AuditService;
use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Documents\DocumentValidationException;
use AlquilaPro\Leases\LeaseService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var DocumentService $documents */
    $documents = $deps['documents'];
    /** @var LeaseService $leases */
    $leases = $deps['leases'];
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

    $safeProjection = static function (array $document, ?string $fileUrl = null): array {
        return [
            'id' => (int) $document['id'],
            'type' => (string) $document['type'],
            'title' => (string) $document['title'],
            'created_at' => (string) $document['created_at'],
            'content_json' => is_array($document['content_json'] ?? null) ? $document['content_json'] : null,
            'has_file' => is_string($document['stored_name'] ?? null),
            'file_url' => $fileUrl,
        ];
    };

    $renderDocuments = static function (
        ResponseInterface $response,
        array $user,
        string $scope,
        ?int $scopeId,
        array $formValues = [],
        array $formErrors = [],
        int $status = 200,
    ) use ($documents, $view, $csrf, $safeProjection, $path): ResponseInterface {
        $current = [];
        if ($scopeId !== null && Permissions::allows($user, 'documents.view')) {
            $current = $scope === 'lease'
                ? $documents->listCurrentForLease($scopeId)
                : $documents->listCurrentForProperty($scopeId);
        }
        $safeCurrent = array_map(static function (array $document) use ($safeProjection, $path): array {
            $fileUrl = is_string($document['stored_name'] ?? null)
                ? $path('/admin/documents/' . (int) $document['id'] . '/file')
                : null;
            return $safeProjection($document, $fileUrl);
        }, $current);

        $response->getBody()->write($view->renderApp('documents', [
            'pageTitle' => 'Documentos',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'scope' => $scope,
            'scopeId' => $scopeId,
            'documents' => $safeCurrent,
            'history' => [],
            'historyDocument' => null,
            'canManage' => Permissions::allows($user, 'documents.manage'),
            'formValues' => $formValues,
            'formErrors' => $formErrors,
        ]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withStatus($status);
    };

    $app->get('/admin/documents', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $renderDocuments): ResponseInterface {
        $user = $staffGuard($response, 'documents.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $query = $request->getQueryParams();
        $scope = ($query['scope'] ?? null) === 'lease' ? 'lease' : 'property';
        $scopeId = filter_var($query['id'] ?? null, FILTER_VALIDATE_INT);
        $scopeId = is_int($scopeId) && $scopeId > 0 ? $scopeId : null;
        return $renderDocuments($response, $user, $scope, $scopeId, [
            'scope' => $scope,
            'scope_id' => (string) ($scopeId ?? ''),
        ]);
    });

    $app->post('/admin/documents', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $documents, $audit, $path, $renderDocuments): ResponseInterface {
        $user = $staffGuard($response, 'documents.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $body = (array) ($request->getParsedBody() ?? []);
        $asString = static fn (mixed $value): string => is_scalar($value) ? (string) $value : '';
        $scopeInput = $asString($body['scope'] ?? '');
        $scope = $scopeInput === 'lease' ? 'lease' : 'property';
        $scopeIdValue = $body['scope_id'] ?? null;
        $scopeId = filter_var($scopeIdValue, FILTER_VALIDATE_INT);
        $scopeId = is_int($scopeId) && $scopeId > 0 ? $scopeId : null;
        $type = trim($asString($body['type'] ?? ''));
        $title = $asString($body['title'] ?? '');
        $supersedesInput = $asString($body['supersedes_document_id'] ?? '');
        $formValues = [
            'scope' => $scope,
            'scope_id' => $asString($scopeIdValue),
            'type' => $type,
            'title' => $title,
            'supersedes_document_id' => $supersedesInput,
            'content_json' => $asString($body['content_json'] ?? '{}'),
        ];
        $formErrors = [];
        if (!in_array($scopeInput, ['property', 'lease'], true)) {
            $formErrors['scope'] = 'Selecciona un ámbito válido.';
        }
        if ($scopeId === null) {
            $formErrors['scope_id'] = 'Indica un ID válido para consultar el ámbito.';
        }
        if (trim($title) === '') {
            $formErrors['title'] = 'Escribe un título para el documento.';
        } elseif (mb_strlen(trim($title), 'UTF-8') > 190) {
            $formErrors['title'] = 'El título no puede superar 190 caracteres.';
        }
        if ($formErrors !== []) {
            return $renderDocuments($response, $user, $scope, $scopeId, $formValues, $formErrors, 422);
        }

        $supersedes = null;
        if ($supersedesInput !== '') {
            $supersedesValue = filter_var($supersedesInput, FILTER_VALIDATE_INT);
            if (!is_int($supersedesValue) || $supersedesValue <= 0) {
                $formErrors['supersedes_document_id'] = 'Selecciona una versión válida para reemplazar.';
                return $renderDocuments($response, $user, $scope, $scopeId, $formValues, $formErrors, 422);
            }
            $supersedes = $supersedesValue;
        }
        $propertyId = $scope === 'property' ? $scopeId : null;
        $leaseId = $scope === 'lease' ? $scopeId : null;

        try {
            $uploads = $request->getUploadedFiles();
            $file = $uploads['document'] ?? null;
            if ($file instanceof UploadedFileInterface && $file->getError() === UPLOAD_ERR_OK) {
                $createdDocument = $documents->createFileVersion(
                    $propertyId,
                    $leaseId,
                    $type,
                    $title,
                    $file,
                    $supersedes,
                    (int) $user['id'],
                    time(),
                );
            } else {
                if ((array_key_exists('document', $uploads)
                        && (!($file instanceof UploadedFileInterface) || $file->getError() !== UPLOAD_ERR_NO_FILE))) {
                    throw new DocumentValidationException('No fue posible recibir el archivo. Revisa el archivo e inténtalo de nuevo.', 'document');
                }
                try {
                    $content = json_decode($formValues['content_json'], true, 64, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new DocumentValidationException('El contenido debe ser JSON válido.', 'content_json');
                }
                if (!is_array($content)) {
                    throw new DocumentValidationException('El contenido debe ser un objeto JSON válido.', 'content_json');
                }
                $createdDocument = $documents->createContentVersion(
                    $propertyId,
                    $leaseId,
                    $type,
                    $title,
                    $content,
                    $supersedes,
                    (int) $user['id'],
                    time(),
                );
            }
        } catch (DocumentValidationException $exception) {
            return $renderDocuments(
                $response,
                $user,
                $scope,
                $scopeId,
                $formValues,
                [$exception->field => $exception->getMessage()],
                422,
            );
        } catch (Throwable) {
            $response->getBody()->write('No fue posible guardar el documento. Inténtalo más tarde.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withHeader('Cache-Control', 'private, no-store')
                ->withStatus(500);
        }

        $audit->record(
            (int) $user['id'],
            'document.version_created',
            'document',
            (int) $createdDocument['id'],
            'Versión de documento creada.',
            $request->getServerParams()['REMOTE_ADDR'] ?? null,
            [
                'type' => $type,
                'scope_kind' => $scope,
                'scope_id' => $scopeId,
            ],
        );

        return $response->withHeader('Location', $path('/admin/documents?scope=' . $scope . '&id=' . $scopeId))
            ->withStatus(303);
    });

    $app->get('/admin/documents/{id:[0-9]+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $documents, $view, $csrf, $safeProjection, $path): ResponseInterface {
        $user = $staffGuard($response, 'documents.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $document = $documents->find((int) $args['id']);
        if ($document === null) {
            return $response->withStatus(404);
        }
        $history = $documents->history((int) $document['id']);
        if ($history === []) {
            return $response->withStatus(404);
        }
        $safeHistory = array_map(static function (array $row) use ($safeProjection, $path): array {
            $fileUrl = is_string($row['stored_name'] ?? null)
                ? $path('/admin/documents/' . (int) $row['id'] . '/file')
                : null;
            return $safeProjection($row, $fileUrl);
        }, $history);
        $scope = $document['lease_id'] === null ? 'property' : 'lease';
        $scopeId = (int) ($document['lease_id'] ?? $document['property_id']);
        $response->getBody()->write($view->renderApp('documents', [
            'pageTitle' => 'Historial de documento',
            'user' => $user,
            'activeNav' => 'apps',
            'csrfToken' => $csrf->token(),
            'scope' => $scope,
            'scopeId' => $scopeId,
            'documents' => [],
            'history' => $safeHistory,
            'historyDocument' => $safeProjection($document),
            'canManage' => false,
        ]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    });

    $app->get('/admin/documents/{id:[0-9]+}/file', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $documents): ResponseInterface {
        $user = $staffGuard($response, 'documents.view');
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $file = $documents->privateFile((int) $args['id']);
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
            ->withHeader('Content-Disposition', 'attachment; filename="document-' . (int) $args['id'] . '.' . $extension . '"')
            ->withHeader('Content-Length', (string) $file['size_bytes'])
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    });

    $app->get('/tenant/leases/{leaseId:[0-9]+}/documents/{documentId:[0-9]+}/file', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($tenantGuard, $leases, $documents): ResponseInterface {
        $user = $tenantGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $leaseId = (int) $args['leaseId'];
        $lease = $leases->findForUser($leaseId, (int) $user['id']);
        if ($lease === null) {
            return $response->withStatus(404);
        }
        $documentId = (int) $args['documentId'];
        $current = $documents->listCurrentForLease($leaseId);
        $allowed = false;
        foreach ($current as $document) {
            if ((int) $document['id'] === $documentId && $document['type'] === 'lease_document'
                && is_string($document['stored_name'] ?? null)
            ) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $file = $documents->privateFile($documentId);
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
            ->withHeader('Content-Disposition', 'attachment; filename="document-' . $documentId . '.' . $extension . '"')
            ->withHeader('Content-Length', (string) $file['size_bytes'])
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    });
};
