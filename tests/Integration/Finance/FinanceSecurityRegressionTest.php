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

    public function testF9ArchitectureRemainsWithinTheApprovedBudget(): void
    {
        $migration = $this->file('database/migrations/009_create_charges.sql');
        preg_match_all('/CREATE TABLE\s+([a-z_]+)/i', $migration, $tables);
        self::assertSame(['charges'], $tables[1] ?? []);
        foreach ([
            'payments',
            'payment_allocations',
            'payment_methods',
            'payment_reports',
            'balances',
            'charge_status',
            'charge_status_history',
            'ledger_entries',
            'tenant_users',
            'tenant_sessions',
            'lease_units',
        ] as $forbiddenSchemaName) {
            self::assertStringNotContainsString($forbiddenSchemaName, $migration);
        }
        foreach (['status', 'balance', 'days_overdue', 'outstanding_amount', 'paid_amount'] as $derivedColumn) {
            self::assertDoesNotMatchRegularExpression('/^\s*' . preg_quote($derivedColumn, '/') . '\s+/mi', $migration);
        }

        $serviceFiles = glob($this->root . '/app/Finance/*Service.php') ?: [];
        $serviceFiles = array_map(static fn (string $path): string => str_replace('\\', '/', $path), $serviceFiles);
        sort($serviceFiles);
        self::assertSame([str_replace('\\', '/', $this->root) . '/app/Finance/FinanceService.php'], $serviceFiles);

        $financePhpFiles = glob($this->root . '/app/Finance/*.php') ?: [];
        $financePhpFiles = array_map(static fn (string $path): string => str_replace('\\', '/', $path), $financePhpFiles);
        sort($financePhpFiles);
        self::assertSame([
            str_replace('\\', '/', $this->root) . '/app/Finance/FinanceService.php',
            str_replace('\\', '/', $this->root) . '/app/Finance/routes.php',
        ], $financePhpFiles);

        $routeFiles = glob($this->root . '/app/Finance/routes.php') ?: [];
        self::assertCount(1, $routeFiles);
        $financeRoutes = $this->file('app/Finance/routes.php');
        preg_match_all('/\$app->(?:get|post)\(\'([^\']+)\'/i', $financeRoutes, $routeMatches);
        self::assertSame([
            '/admin/finance',
            '/admin/leases/{id:[0-9]+}/charges/monthly',
            '/admin/leases/{id:[0-9]+}/charges',
            '/admin/charges/{id:[0-9]+}/void',
        ], $routeMatches[1] ?? []);
        foreach ($routeMatches[1] ?? [] as $route) {
            self::assertStringStartsWith('/admin/', $route, 'F9 routes must remain private staff routes.');
        }

        $permissionSource = $this->file('app/Auth/Permissions.php');
        preg_match_all('/^\s*\'(finance\.[a-z]+)\',?\s*$/m', $permissionSource, $permissionMatches);
        self::assertSame(['finance.view', 'finance.manage'], $permissionMatches[1] ?? []);

        $composer = json_decode($this->file('composer.json'), true, 32, JSON_THROW_ON_ERROR);
        $runtimeRequirements = array_keys($composer['require'] ?? []);
        sort($runtimeRequirements);
        self::assertSame(['dompdf/dompdf', 'php', 'slim/psr7', 'slim/slim'], $runtimeRequirements);
        self::assertSame(['phpunit/phpunit'], array_keys($composer['require-dev'] ?? []));

        $financeSource = $this->file('app/Finance/FinanceService.php');
        foreach (['session_start(', 'new SessionGuard(', 'new Csrf(', 'cron', 'scheduler', 'worker'] as $forbiddenCore) {
            self::assertStringNotContainsString($forbiddenCore, $financeSource);
        }
        self::assertSame(1, substr_count($financeSource, 'private function chargeSummaryExpressions('));
        self::assertSame(2, substr_count($financeSource, '$this->chargeSummaryExpressions('));

        $migrations = implode("\n", array_map(
            static fn (string $path): string => (string) file_get_contents($path),
            glob($this->root . '/database/migrations/*.sql') ?: [],
        ));
        foreach (['tenant_users', 'tenant_sessions', 'lease_units'] as $duplicateTenantSchema) {
            self::assertDoesNotMatchRegularExpression('/CREATE TABLE\s+' . preg_quote($duplicateTenantSchema, '/') . '\b/i', $migrations);
        }
    }

    public function testManualAndTenantSurfacesKeepFinancialAndPrivacyBoundaries(): void
    {
        $financeService = $this->file('app/Finance/FinanceService.php');
        self::assertStringContainsString("private const MANUAL_TYPES = ['service', 'fine', 'repair', 'other'];", $financeService);

        $home = $this->file('resources/views/tenant_home.php');
        $tenantDetail = $this->file('resources/views/tenant_lease_detail.php');
        foreach (['Pagos', 'Reportes', 'Póliza', 'Mantenimiento', 'Solicitudes'] as $futureUi) {
            self::assertStringNotContainsString($futureUi, $home . "\n" . $tenantDetail);
        }
        self::assertStringNotContainsString('Cartera', $home);
        self::assertStringContainsString('Cartera', $tenantDetail);

        $layout = $this->file('resources/views/layouts/app.php');
        $financeRoutes = $this->file('app/Finance/routes.php');
        self::assertStringContainsString(
            '\\AlquilaPro\\Auth\\Permissions::allows($user, \'finance.view\')',
            $layout,
        );
        self::assertStringContainsString("\$isTenant = (\$user['role'] ?? null) === 'tenant';", $layout);
        self::assertStringNotContainsString("new SessionGuard(", $financeRoutes);
        self::assertStringNotContainsString("new Csrf(", $financeRoutes);
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
