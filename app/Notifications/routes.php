<?php

declare(strict_types=1);

use AlquilaPro\Notifications\NotificationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, array $deps): void {
    /** @var NotificationService $notifications */
    $notifications = $deps['notifications'];
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
        $generated = $notifications->generateReminders($today, $now);
        $processed = $notifications->processDue(50, $now);
        $response->getBody()->write(json_encode([
            'generated' => $generated,
            'sent' => $processed['sent'],
            'failed' => $processed['failed'],
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    });
};
