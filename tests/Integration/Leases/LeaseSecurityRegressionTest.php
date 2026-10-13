<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Leases;

use PHPUnit\Framework\TestCase;

final class LeaseSecurityRegressionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testF8KeepsSingleLeaseArchitectureWithoutParallelTenantStacks(): void
    {
        $migration = $this->file('database/migrations/008_create_leases.sql');
        preg_match_all('/CREATE TABLE\s+([a-z_]+)/i', $migration, $tables);
        self::assertSame(['leases'], $tables[1] ?? []);
        foreach (['tenants', 'lease_units', 'tenant_users', 'tenant_sessions'] as $forbiddenTable) {
            self::assertStringNotContainsString($forbiddenTable, $migration);
        }

        $services = glob($this->root . '/app/Leases/*Service.php') ?: [];
        sort($services);
        self::assertSame([$this->root . '/app/Leases/LeaseService.php'], $services);

        $routeFiles = glob($this->root . '/app/Leases/routes.php') ?: [];
        self::assertCount(1, $routeFiles);

        $routes = $this->file('app/Leases/routes.php');
        preg_match_all('/\$app->(?:get|post)\(\'([^\']+)\'/', $routes, $routeMatches);
        self::assertSame([
            '/admin/contracts/{id:[0-9]+}/lease',
            '/admin/leases/{id:[0-9]+}/end',
            '/tenant',
            '/tenant/leases/{id:[0-9]+}',
            '/tenant/leases/{id:[0-9]+}/contract',
        ], $routeMatches[1] ?? []);

        $leaseCore = implode("\n", [
            $this->file('app/Leases/LeaseService.php'),
            $routes,
        ]);
        foreach (['session_start(', 'new SessionGuard(', 'new Csrf('] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $leaseCore,
                'F8 lease core must reuse shared auth/session/CSRF: ' . $forbidden,
            );
        }

        $f8Composition = $leaseCore . "\n" . $this->file('bootstrap/app.php');
        foreach ([
            '/tenant-login',
            'tenant_sessions',
            'tenant_users',
            'lease_units',
            'TenantAuth',
            'TenantSession',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $f8Composition,
                'Unexpected F8 parallel architecture: ' . $forbidden,
            );
        }

        $composer = json_decode($this->file('composer.json'), true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        $direct = array_keys($composer['require'] ?? []);
        sort($direct);
        self::assertSame(['dompdf/dompdf', 'php', 'slim/psr7', 'slim/slim'], $direct);
    }

    public function testActivationLocksCanonicalUnitBeforeTenantOrLeaseSideEffects(): void
    {
        $service = $this->file('app/Leases/LeaseService.php');
        $start = strpos($service, 'public function activate(');
        $end = strpos($service, 'public function end(', $start === false ? 0 : $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);

        $activate = substr($service, $start, $end - $start);
        self::assertIsString($activate);

        $unitLock = strpos($activate, 'SELECT id, property_id, status FROM units WHERE id = :id FOR UPDATE');
        $identity = strpos($activate, 'createTenantIdentity(');
        $leaseInsert = strpos($activate, 'INSERT INTO leases');
        $occupy = strpos($activate, "UPDATE units SET status = 'occupied'");

        self::assertNotFalse($unitLock);
        self::assertNotFalse($identity);
        self::assertNotFalse($leaseInsert);
        self::assertNotFalse($occupy);
        self::assertTrue($unitLock < $identity, 'Canonical unit lock must precede tenant identity creation.');
        self::assertTrue($unitLock < $leaseInsert, 'Canonical unit lock must precede lease persistence.');
        self::assertTrue($leaseInsert < $occupy, 'Lease insert must stay inside the serialized transaction before occupation commit.');
    }

    public function testTenantSurfaceHasNoFutureModulesAndCredentialNeverTravelsInUrlOrAction(): void
    {
        $routes = $this->file('app/Leases/routes.php');
        $home = $this->file('resources/views/tenant_home.php');
        $detail = $this->file('resources/views/tenant_lease_detail.php');
        $credential = $this->file('resources/views/lease_activation_credential.php');

        self::assertStringNotContainsString('Cartera', $home);
        self::assertStringContainsString('Cartera', $detail);
        foreach (['Pagos', 'Póliza', 'Mantenimiento', 'Solicitudes'] as $futureLabel) {
            self::assertStringNotContainsString($futureLabel, $home . "\n" . $detail);
        }

        foreach (['?password=', '?temporary_password=', '&password=', '&temporary_password='] as $forbiddenQuery) {
            self::assertStringNotContainsString($forbiddenQuery, $routes . "\n" . $credential);
        }
        self::assertDoesNotMatchRegularExpression(
            '/(?:href|action)\s*=\s*["\'][^"\']*(?:temporaryPassword|temporary_password|password)[^"\']*["\']/i',
            $credential,
        );
        self::assertStringNotContainsString("withHeader('Location'", substr(
            $routes,
            strpos($routes, "post('/admin/contracts/{id:[0-9]+}/lease") ?: 0,
            (strpos($routes, "post('/admin/leases/{id:[0-9]+}/end") ?: strlen($routes))
                - (strpos($routes, "post('/admin/contracts/{id:[0-9]+}/lease") ?: 0),
        ));
    }

    private function file(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing F8 closure source: ' . $path);

        return $contents;
    }
}
