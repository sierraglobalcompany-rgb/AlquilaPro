<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Foundation;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class SecurityBoundaryTest extends TestCase
{
    public function testUnhandledExceptionReturnsGeneric500WithoutSecret(): void
    {
        $app = $this->app();
        $app->get('/explode', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            throw new RuntimeException('db_password=supersecret-stack-marker');
        });

        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://alquilapro.sierraglobalcompany.com/explode'),
        );
        $body = (string) $response->getBody();

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('supersecret-stack-marker', $body);
        self::assertStringNotContainsString('RuntimeException', $body);
    }

    public function testApacheBoundaryBlocksInternalDirectories(): void
    {
        $rules = file_get_contents(dirname(__DIR__, 3) . '/.htaccess');
        self::assertIsString($rules);

        foreach (['app', 'bootstrap', 'config', 'database', 'docs', 'storage', 'tests', 'vendor'] as $directory) {
            self::assertMatchesRegularExpression('/\\b' . preg_quote($directory, '/') . '\\b/i', $rules, $directory . ' must be blocked from direct HTTP access.');
        }

        self::assertMatchesRegularExpression('/\\[F(?:,|\\])/', $rules, 'Sensitive path rule must fail the request instead of rewriting it.');
    }

    private function app(): App
    {
        $directory = sys_get_temp_dir() . '/alquila_security_' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);

        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $directory . '/installed.lock',
            'database_config_path' => $directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com',
            'base_path' => '',
        ]);
    }
}
