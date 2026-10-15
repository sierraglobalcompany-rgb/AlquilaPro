<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\Permissions;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Notifications\NotificationService;
use AlquilaPro\Notifications\WhatsAppService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    $notifications = $deps['notifications'] ?? null;
    /** @var WhatsAppService $whatsApp */
    $whatsApp = $deps['whatsapp'];
    /** @var PDO $pdo */
    $pdo = $deps['pdo'];
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

    if ($notifications instanceof NotificationService) {
        $cronSecret = (string) ($deps['cron_secret'] ?? '');
        $app->post('/cron/notifications', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
        ) use ($notifications, $cronSecret): ResponseInterface {
            $providedSecret = $request->getHeaderLine('X-AlquilaPro-Cron-Secret');
            if (strlen($cronSecret) < 32 || !hash_equals($cronSecret, $providedSecret)) {
                return $response->withStatus(403);
            }

            $now = gmdate('Y-m-d H:i:s');
            $today = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
            $redacted = $notifications->redactTerminalRecipients($now);
            $generated = $notifications->generateReminders($today, $now);
            $processed = $notifications->processDue(50, $now);
            $response->getBody()->write(json_encode([
                'generated' => $generated,
                'sent' => $processed['sent'],
                'failed' => $processed['failed'],
                'redacted' => $redacted,
            ], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        });
    }

    $staffGuard = static function (ResponseInterface $response) use ($currentUser, $session, $path): array|ResponseInterface {
        $user = $currentUser();
        if ($user === null) {
            return $response->withHeader('Location', $path('/login'))->withStatus(302);
        }
        if ($session->mustChangePassword()) {
            return $response->withHeader('Location', $path('/account/password'))->withStatus(302);
        }
        if (!Permissions::allows($user, 'whatsapp.use')) {
            return $response->withStatus(403);
        }
        return $user;
    };

    $renderPage = static function (
        ResponseInterface $response,
        array $user,
        ?array $createdAction = null,
        ?string $error = null,
        int $status = 200,
    ) use ($view, $whatsApp, $csrf): ResponseInterface {
        $response->getBody()->write($view->renderApp('whatsapp', [
            'user' => $user,
            'actions' => $whatsApp->listRecent(),
            'createdAction' => $createdAction,
            'error' => $error,
            'csrfToken' => $csrf->token(),
            'pageTitle' => 'WhatsApp',
            'activeNav' => 'apps',
        ]));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus($status);
    };

    $resolveContext = static function (string $contextType, int $contextId) use ($pdo): ?array {
        $queries = [
            'candidate' => 'SELECT full_name, phone, NULL AS category FROM candidates WHERE id = :id LIMIT 1',
            'lease' => 'SELECT candidates.full_name, candidates.phone, NULL AS category '
                . 'FROM leases JOIN contracts ON contracts.id = leases.contract_id '
                . 'JOIN candidates ON candidates.id = contracts.candidate_id WHERE leases.id = :id LIMIT 1',
            'maintenance_request' => 'SELECT candidates.full_name, candidates.phone, maintenance_requests.category '
                . 'FROM maintenance_requests JOIN leases ON leases.id = maintenance_requests.lease_id '
                . 'JOIN contracts ON contracts.id = leases.contract_id '
                . 'JOIN candidates ON candidates.id = contracts.candidate_id '
                . 'WHERE maintenance_requests.id = :id LIMIT 1',
        ];
        $query = $queries[$contextType] ?? null;
        if (!is_string($query)) {
            return null;
        }
        $statement = $pdo->prepare($query);
        $statement->execute(['id' => $contextId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    };

    $app->get('/admin/whatsapp', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $renderPage): ResponseInterface {
        $user = $staffGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        return $renderPage($response, $user);
    });

    $app->post('/admin/whatsapp', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) use ($staffGuard, $renderPage, $resolveContext, $whatsApp): ResponseInterface {
        $user = $staffGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $form = (array) ($request->getParsedBody() ?? []);
        $contextType = is_string($form['context_type'] ?? null) ? $form['context_type'] : '';
        $rawContextId = is_string($form['context_id'] ?? null) ? $form['context_id'] : '';
        $templateKey = is_string($form['template_key'] ?? null) ? $form['template_key'] : '';
        $contextId = preg_match('/\A[1-9][0-9]{0,17}\z/D', $rawContextId) === 1
            ? filter_var($rawContextId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $context = $contextId === false ? null : $resolveContext($contextType, (int) $contextId);

        if (!is_array($context)) {
            return $renderPage($response, $user, error: 'Selecciona un registro válido para esta acción.', status: 422);
        }

        $variables = ['name' => (string) $context['full_name']];
        if ($contextType === 'maintenance_request') {
            $variables['category'] = (string) $context['category'];
        }

        try {
            $action = $whatsApp->prepare(
                $contextType,
                (int) $contextId,
                (string) $context['phone'],
                $templateKey,
                $variables,
                (int) $user['id'],
                time(),
            );
        } catch (RuntimeException) {
            return $renderPage($response, $user, error: 'No fue posible preparar la acción. Revisa el tipo de plantilla.', status: 422);
        }

        return $renderPage($response, $user, createdAction: $action);
    });

    $app->post('/admin/whatsapp/{id}/open', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($staffGuard, $whatsApp): ResponseInterface {
        $user = $staffGuard($response);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $rawId = (string) ($args['id'] ?? '');
        $actionId = preg_match('/\A[1-9][0-9]{0,17}\z/D', $rawId) === 1
            ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $action = $actionId === false ? null : $whatsApp->find((int) $actionId);
        if (!is_array($action)) {
            return $response->withStatus(404);
        }

        $expectedUrl = 'https://wa.me/' . (string) $action['recipient_phone_snapshot']
            . '?text=' . rawurlencode((string) $action['message_snapshot']);
        $storedUrl = (string) $action['url_snapshot'];
        if (preg_match('/\A[1-9][0-9]{7,14}\z/D', (string) $action['recipient_phone_snapshot']) !== 1
            || !hash_equals($expectedUrl, $storedUrl)
        ) {
            return $response->withStatus(422);
        }

        try {
            $opened = $whatsApp->markOpened((int) $actionId, (int) $user['id'], time());
        } catch (RuntimeException) {
            return $response->withStatus(404);
        }
        return $response->withHeader('Location', (string) $opened['url_snapshot'])->withStatus(303);
    });
};
