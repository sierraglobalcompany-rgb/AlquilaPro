<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Auth\Permissions;
use PHPUnit\Framework\TestCase;

final class ScreeningSecurityRegressionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testF6KeepsSingleArchitectureWithoutParallelSecurityOrProviderStack(): void
    {
        self::assertFileExists($this->root . '/database/migrations/006_create_screening_processes.sql');
        self::assertFileExists($this->root . '/app/Candidates/ScreeningService.php');
        self::assertFileDoesNotExist($this->root . '/app/Candidates/SuraService.php');
        self::assertFileDoesNotExist($this->root . '/app/Screening/routes.php');
        self::assertSame(
            ['candidates.view', 'candidates.manage'],
            Permissions::sanitize(['screening.view', 'candidates.view', 'screening.manage', 'candidates.manage']),
        );

        $service = (string) file_get_contents($this->root . '/app/Candidates/ScreeningService.php');
        $routes = (string) file_get_contents($this->root . '/app/Candidates/routes.php');

        self::assertSame(1, substr_count($routes, "/admin/candidates/{id:[0-9]+}/screening"));
        self::assertStringNotContainsString('curl_', strtolower($service));
        self::assertStringNotContainsString('file_get_contents', strtolower($service));
        self::assertStringNotContainsString('guzzle', strtolower($service));
        self::assertStringNotContainsString('webhook', strtolower($service));
    }

    public function testCandidatePortalUsesSafeProjectionAndExternalReferrerProtection(): void
    {
        $routes = (string) file_get_contents($this->root . '/app/Candidates/routes.php');
        $view = (string) file_get_contents($this->root . '/resources/views/public_candidate_continue.php');

        self::assertStringContainsString(
            "'screening' => \$screening->publicForCandidate((int) \$candidate['id'])",
            $routes,
        );
        self::assertStringContainsString('rel="noopener noreferrer"', $view);
        self::assertStringContainsString("\$screening['portal_url']", $view);
        self::assertStringNotContainsString("\$screening['notes']", $view);
        self::assertStringNotContainsString("\$screening['support_reference']", $view);
        self::assertStringNotContainsString("\$screening['responsible_user_id']", $view);
        self::assertStringNotContainsString("\$screening['id']", $view);
        self::assertStringNotContainsString("\$screening['candidate_id']", $view);
    }

    public function testScreeningAdminReusesExistingGlobalAuthCsrfAndTokenRedactionBoundaries(): void
    {
        $routes = (string) file_get_contents($this->root . '/app/Candidates/routes.php');
        $bootstrap = (string) file_get_contents($this->root . '/bootstrap/app.php');

        self::assertStringContainsString("\$adminGuard(\$response, 'candidates.manage')", $routes);
        self::assertStringContainsString("strtoupper(\$request->getMethod()) === 'POST'", $bootstrap);
        self::assertStringContainsString('$csrf->validate($token)', $bootstrap);
        self::assertStringContainsString("'#(/apply/continue/)[a-f0-9]{64}(?=/|$)#i'", $bootstrap);
        self::assertStringContainsString("'$1[redacted]'", $bootstrap);
        self::assertStringNotContainsString('screening_token', $routes);
        self::assertStringNotContainsString('screening_session', $routes);
    }
}
