<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Memberships;

use AlquilaPro\Auth\Permissions;
use PHPUnit\Framework\TestCase;

final class MembershipSecurityRegressionTest extends TestCase
{
    public function testF20ArchitectureAndFinanceAuthorityRemainWithinApprovedBudget(): void
    {
        $root = dirname(__DIR__, 3);
        $migration = $this->read($root . '/database/migrations/020_create_digital_services_memberships.sql');
        preg_match_all('/CREATE\s+TABLE\s+([a-z_]+)/i', $migration, $tableMatches);
        self::assertSame(['digital_services', 'service_memberships'], $tableMatches[1] ?? []);

        $domainFiles = glob($root . '/app/Memberships/*.php') ?: [];
        $domainClasses = [];
        $moduleSource = '';
        foreach ($domainFiles as $file) {
            $source = $this->read($file);
            $moduleSource .= "\n" . $source;
            preg_match_all('/\b(?:final\s+)?(?:readonly\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/', $source, $matches);
            array_push($domainClasses, ...($matches[1] ?? []));
        }
        self::assertSame(['MembershipService'], $domainClasses);
        self::assertSame(
            ['memberships.view', 'memberships.manage'],
            Permissions::sanitize(['memberships.view', 'memberships.manage', 'memberships.delete']),
        );
        self::assertDoesNotMatchRegularExpression('/INSERT\s+INTO\s+`?charges`?/i', $moduleSource);
        self::assertSame(1, substr_count($moduleSource, 'createManualCharge('));

        $service = $this->read($root . '/app/Memberships/MembershipService.php');
        $acceptStart = strpos($service, 'public function accept(');
        $renewStart = strpos($service, 'public function renew(', $acceptStart === false ? 0 : $acceptStart);
        self::assertNotFalse($acceptStart);
        self::assertNotFalse($renewStart);
        self::assertStringNotContainsString('createManualCharge(', substr($service, $acceptStart, $renewStart - $acceptStart));
        $endStart = strpos($service, 'public function end(', $renewStart);
        self::assertNotFalse($endStart);
        self::assertStringNotContainsString('createManualCharge(', substr($service, $renewStart, $endStart - $renewStart));

        $routes = $this->read($root . '/app/Memberships/routes.php');
        preg_match_all('/\$app->(?:get|post)\(\'([^\']+)\'/i', $routes, $routeMatches);
        self::assertNotEmpty($routeMatches[1] ?? []);
        foreach ($routeMatches[1] as $route) {
            self::assertTrue(str_starts_with($route, '/admin/') || str_starts_with($route, '/tenant/'));
        }

        $acceptRouteStart = strpos($routes, "\$app->post('/tenant/leases/{leaseId:[0-9]+}/memberships'");
        $renewRouteStart = strpos($routes, "\$app->post('/tenant/leases/{leaseId:[0-9]+}/memberships/{membershipId:[0-9]+}/renew'");
        self::assertNotFalse($acceptRouteStart);
        self::assertNotFalse($renewRouteStart);
        $acceptRoute = substr($routes, $acceptRouteStart, $renewRouteStart - $acceptRouteStart);
        $renewRoute = substr($routes, $renewRouteStart);
        $acceptOwnership = strpos($acceptRoute, '$leases->findForUser(');
        $acceptMutation = strpos($acceptRoute, '$memberships->accept(');
        $renewOwnership = strpos($renewRoute, '$leases->findForUser(');
        $renewLookup = strpos($renewRoute, '$memberships->findMembership(');
        $renewMutation = strpos($renewRoute, '$memberships->renew(');
        self::assertIsInt($acceptOwnership);
        self::assertIsInt($acceptMutation);
        self::assertTrue($acceptOwnership < $acceptMutation);
        self::assertIsInt($renewOwnership);
        self::assertIsInt($renewLookup);
        self::assertIsInt($renewMutation);
        self::assertTrue($renewOwnership < $renewLookup && $renewLookup < $renewMutation);
        self::assertStringNotContainsString('createManualCharge(', $acceptRoute);
        self::assertStringNotContainsString('createManualCharge(', $renewRoute);

        $bootstrap = $this->read($root . '/bootstrap/app.php');
        self::assertSame(1, substr_count($bootstrap, 'new MembershipService($pdo, $finance)'));
    }

    private function read(string $path): string
    {
        $source = file_get_contents($path);
        self::assertIsString($source, 'Expected project source to be readable: ' . $path);
        return $source;
    }
}
