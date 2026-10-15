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

        foreach (['app', 'bootstrap', 'config', 'database', 'docs', 'resources', 'storage', 'tests', 'vendor'] as $directory) {
            self::assertMatchesRegularExpression('/\\b' . preg_quote($directory, '/') . '\\b/i', $rules, $directory . ' must be blocked from direct HTTP access.');
        }

        self::assertDoesNotMatchRegularExpression('/\\bassets\\b/i', $rules, 'The real public assets directory must remain web-accessible.');
        self::assertMatchesRegularExpression('/\\[F(?:,|\\])/', $rules, 'Sensitive path rule must fail the request instead of rewriting it.');
    }

    public function testRuntimeStorageIsIgnoredByGit(): void
    {
        $ignore = file_get_contents(dirname(__DIR__, 3) . '/.gitignore');
        self::assertIsString($ignore);
        self::assertMatchesRegularExpression(
            '/^\/storage\/\s*$/m',
            $ignore,
            'All runtime storage must stay outside version control, including private PDFs and uploaded documents.',
        );
    }

    public function testProductionSessionCookiePolicyIsStrictAndHttpsAware(): void
    {
        $index = file_get_contents(dirname(__DIR__, 3) . '/index.php');
        self::assertIsString($index);

        self::assertStringContainsString("ini_set('session.use_strict_mode', '1');", $index);
        self::assertStringContainsString("ini_set('session.use_only_cookies', '1');", $index);
        self::assertStringContainsString("session_name('alquilapro_session');", $index);
        self::assertStringContainsString("'lifetime' => 0", $index);
        self::assertStringContainsString("'path' => \$basePath === '' ? '/' : rtrim(\$basePath, '/') . '/'", $index);
        self::assertStringContainsString("'secure' => str_starts_with(\$baseUrl, 'https://')", $index);
        self::assertStringContainsString("'httponly' => true", $index);
        self::assertStringContainsString("'samesite' => 'Lax'", $index);
        self::assertStringContainsString('$requestPath !== $healthPath', $index);
        self::assertStringContainsString('$requestPath !== $notificationCronPath', $index);
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
