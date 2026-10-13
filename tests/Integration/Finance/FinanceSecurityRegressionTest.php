<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use PHPUnit\Framework\TestCase;

final class FinanceSecurityRegressionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testTenantPortfolioReusesOwnedLeaseRouteAndResolvesOwnershipBeforeFinance(): void
    {
        $routes = $this->file('app/Leases/routes.php');
        preg_match_all('/\$app->(?:get|post)\(\'([^\']+)\'/i', $routes, $routeMatches);
        self::assertSame([
            '/admin/contracts/{id:[0-9]+}/lease',
            '/admin/leases/{id:[0-9]+}/end',
            '/tenant',
            '/tenant/leases/{id:[0-9]+}',
            '/tenant/leases/{id:[0-9]+}/contract',
        ], $routeMatches[1] ?? []);
        foreach (['/tenant/finance', '/tenant/charges', '/tenant/payments'] as $forbiddenRoute) {
            self::assertStringNotContainsString($forbiddenRoute, $routes);
        }

        $start = strpos($routes, "\$app->get('/tenant/leases/{id:[0-9]+}'");
        $end = strpos($routes, "\$app->get('/tenant/leases/{id:[0-9]+}/contract'", $start === false ? 0 : $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $detailRoute = substr($routes, $start, $end - $start);
        self::assertIsString($detailRoute);

        $ownership = strpos($detailRoute, '$leases->findForUser(');
        $notFound = strpos($detailRoute, '$lease === null');
        $summary = strpos($detailRoute, '$finance->summaryForLease(');
        $list = strpos($detailRoute, '$finance->listForLease(');
        $render = strpos($detailRoute, "\$view->renderApp('tenant_lease_detail'");
        self::assertNotFalse($ownership);
        self::assertNotFalse($notFound);
        self::assertNotFalse($summary);
        self::assertNotFalse($list);
        self::assertNotFalse($render);
        self::assertTrue($ownership < $notFound && $notFound < $summary && $summary < $list && $list < $render);
        self::assertStringContainsString('use ($tenantGuard, $leases, $finance, $view, $csrf)', $detailRoute);
    }

    public function testFinanceServiceIsSharedAndTenantViewExposesOnlySafeReadFields(): void
    {
        $bootstrap = $this->file('bootstrap/app.php');
        self::assertSame(1, substr_count($bootstrap, 'new FinanceService($pdo)'));
        self::assertStringContainsString("'finance' => \$finance", $bootstrap);
        self::assertStringContainsString('$leaseRoutes($app, $shared + [', $bootstrap);

        $view = $this->file('resources/views/tenant_lease_detail.php');
        foreach ([
            'created_by_user_id',
            'voided_by_user_id',
            'void_reason',
            'contract_id',
            'charge_id',
            '<form',
            'csrfToken',
        ] as $privateOrMutationField) {
            self::assertStringNotContainsString($privateOrMutationField, $view);
        }
        self::assertStringContainsString("\$escape(\$charge['description'] ?? 'Cargo')", $view);
        self::assertStringContainsString("\$escape(\$charge['state'])", $view);
        self::assertStringContainsString("\$escape(\$charge['due_date'])", $view);
        self::assertStringContainsString("\$escape(\$charge['amount'])", $view);
        self::assertStringContainsString('data-charge-state=', $view);
    }

    private function file(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing F9 security regression source: ' . $path);

        return $contents;
    }
}
