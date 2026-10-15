<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Leases;

use PHPUnit\Framework\TestCase;

final class TerminationSecurityRegressionTest extends TestCase
{
    public function testF21KeepsOneTerminationServiceAndExistingLeaseRouteTree(): void
    {
        $root = dirname(__DIR__, 3);
        $bootstrap = (string) file_get_contents($root . '/bootstrap/app.php');
        $leaseRoutes = (string) file_get_contents($root . '/app/Leases/routes.php');

        self::assertSame(1, substr_count($bootstrap, 'new TerminationService('));
        self::assertSame(1, substr_count($bootstrap, "'termination' => \$termination"));
        self::assertSame(1, substr_count($leaseRoutes, "'/admin/leases/{id:[0-9]+}/termination'"));
        self::assertSame(1, substr_count($leaseRoutes, "'/admin/leases/{id:[0-9]+}/liquidation'"));
        self::assertSame(1, substr_count($leaseRoutes, "'/tenant/leases/{id:[0-9]+}/liquidation'"));

        foreach (glob($root . '/app/*/routes.php') ?: [] as $routeFile) {
            if (str_replace('\\', '/', $routeFile) === str_replace('\\', '/', $root . '/app/Leases/routes.php')) {
                continue;
            }
            $source = (string) file_get_contents($routeFile);
            self::assertStringNotContainsString('/termination', $source);
            self::assertStringNotContainsString('/liquidation', $source);
        }
    }

    public function testExistingEndRouteRemainsTheOnlyLeaseLifecycleWriterSurface(): void
    {
        $root = dirname(__DIR__, 3);
        $leaseRoutes = (string) file_get_contents($root . '/app/Leases/routes.php');

        self::assertSame(1, substr_count($leaseRoutes, "'/admin/leases/{id:[0-9]+}/end'"));
        self::assertSame(1, substr_count($leaseRoutes, '$leases->end('));
        self::assertStringNotContainsString('UPDATE leases SET ended_at', $leaseRoutes);
        self::assertStringNotContainsString('UPDATE units SET status', $leaseRoutes);
    }

    public function testF21AddsNoPermissionNamespaceOrParallelSecurityLayer(): void
    {
        $root = dirname(__DIR__, 3);
        $permissions = (string) file_get_contents($root . '/app/Auth/Permissions.php');
        $leaseRoutes = (string) file_get_contents($root . '/app/Leases/routes.php');
        $bootstrap = (string) file_get_contents($root . '/bootstrap/app.php');

        foreach (['termination.view', 'termination.manage', 'liquidation.view', 'liquidation.manage', 'bonus.manage'] as $permission) {
            self::assertStringNotContainsString($permission, $permissions);
            self::assertStringNotContainsString($permission, $leaseRoutes);
        }
        self::assertStringNotContainsString('new Csrf(', $leaseRoutes);
        self::assertStringNotContainsString('new SessionGuard(', $leaseRoutes);
        self::assertStringNotContainsString('new AuthService(', $leaseRoutes);
        self::assertSame(1, substr_count($bootstrap, "'csrf' => \$csrf"));
        self::assertSame(1, substr_count($bootstrap, "'session' => \$session"));
    }

    public function testBonusFeatureFlagIsDefaultOffAndComesFromOneEnvironmentKey(): void
    {
        $root = dirname(__DIR__, 3);
        $index = (string) file_get_contents($root . '/index.php');
        $bootstrap = (string) file_get_contents($root . '/bootstrap/app.php');

        self::assertSame(1, substr_count($index, 'ALQUILAPRO_GOOD_CONDUCT_BONUS_ENABLED'));
        self::assertStringContainsString("'good_conduct_bonus_enabled'", $index);
        self::assertStringContainsString("\$settings['good_conduct_bonus_enabled'] ?? false", $bootstrap);
        self::assertStringNotContainsString('GOOD_CONDUCT_BONUS_RULES', $index);
        self::assertStringNotContainsString('bonus_rules', $bootstrap);
    }
}
