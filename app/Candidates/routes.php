<?php

declare(strict_types=1);

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Properties\PropertyService;
use AlquilaPro\UI\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var CandidateService $candidates */
    $candidates = $deps['candidates'];
    /** @var PropertyService $properties */
    $properties = $deps['properties'];
    /** @var View $view */
    $view = $deps['view'];
    /** @var Csrf $csrf */
    $csrf = $deps['csrf'];
    $basePath = (string) ($deps['base_path'] ?? '');

    $path = static fn (string $value): string => $basePath . $value;

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

    $renderStart = static function (
        ResponseInterface $response,
        array $unit,
        ?string $error = null,
        int $status = 200,
    ) use ($view, $csrf, $path): ResponseInterface {
        $response->getBody()->write($view->renderPublic('public_apply_start', [
            'pageTitle' => 'Aplicar',
            'unit' => $unit,
            'action' => $path('/apply/' . $unit['public_key']),
            'csrfToken' => $csrf->token(),
            'error' => $error,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($status);
    };

    $renderContinue = static function (
        ResponseInterface $response,
        array $candidate,
        string $token,
        ?string $error = null,
        int $status = 200,
    ) use ($view, $csrf, $path): ResponseInterface {
        $response->getBody()->write($view->renderPublic('public_candidate_continue', [
            'pageTitle' => 'Continuar solicitud',
            'candidate' => $candidate,
            'action' => $path('/apply/continue/' . $token),
            'csrfToken' => $csrf->token(),
            'error' => $error,
        ]));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($status);
    };

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
    ) use ($candidates, $renderContinue): ResponseInterface {
        $token = (string) ($args['token'] ?? '');
        $candidate = $candidates->findByToken($token, time());
        if ($candidate === null) {
            return $response->withStatus(404);
        }

        return $renderContinue($response, $candidate, $token);
    });

    $app->post('/apply/continue/{token}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ) use ($candidates, $allowAttempt, $tooMany, $renderContinue, $path): ResponseInterface {
        $token = (string) ($args['token'] ?? '');
        $candidate = $candidates->findByToken($token, time());
        if ($candidate === null) {
            return $response->withStatus(404);
        }

        if (!$allowAttempt('continue', 10, time())) {
            return $tooMany($response);
        }

        $input = (array) ($request->getParsedBody() ?? []);
        unset($input['csrf_token']);

        try {
            $candidates->updateByToken($token, $input, time());
        } catch (RuntimeException $exception) {
            return $renderContinue($response, $candidate, $token, $exception->getMessage(), 422);
        }

        return $response
            ->withHeader('Location', $path('/apply/continue/' . $token))
            ->withStatus(303);
    });
};
