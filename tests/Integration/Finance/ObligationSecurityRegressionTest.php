<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use PHPUnit\Framework\TestCase;

final class ObligationSecurityRegressionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testF12UsesOnePrivateAuthorityAndNoParallelFinancialOrPublicArchitecture(): void
    {
        $migration = $this->file('database/migrations/012_create_lease_obligations.sql');
        preg_match_all('/CREATE TABLE\s+([a-z_]+)/i', $migration, $tables);
        self::assertSame(['lease_obligations'], $tables[1] ?? []);
        foreach ([
            'utility_periods', 'utility_bills', 'ph_fines', 'obligation_documents',
            'obligation_payments', 'obligation_status_history',
        ] as $parallelTable) {
            self::assertStringNotContainsString('CREATE TABLE ' . $parallelTable, $migration);
        }

        $obligationServices = glob($this->root . '/app/Finance/*Obligation*Service.php') ?: [];
        self::assertSame([$this->root . '/app/Finance/ObligationService.php'], $obligationServices);
        $service = $this->file('app/Finance/ObligationService.php');
        self::assertStringNotContainsString('INSERT INTO charges', $service);
        self::assertStringNotContainsString('INSERT INTO payments', $service);
        self::assertStringNotContainsString('balance', strtolower($service));
        self::assertStringNotContainsString('paid_amount', $service);

        $permissions = $this->file('app/Auth/Permissions.php');
        preg_match_all('/^\s*\'(finance\.[a-z]+)\',?\s*$/m', $permissions, $financePermissions);
        self::assertSame(['finance.view', 'finance.manage'], $financePermissions[1] ?? []);
        self::assertStringNotContainsString('obligations.view', $permissions);
        self::assertStringNotContainsString('obligations.manage', $permissions);

        $composer = json_decode($this->file('composer.json'), true, 32, JSON_THROW_ON_ERROR);
        $runtimeRequirements = array_keys($composer['require'] ?? []);
        sort($runtimeRequirements);
        self::assertSame(['dompdf/dompdf', 'php', 'phpmailer/phpmailer', 'slim/psr7', 'slim/slim', 'smalot/pdfparser'], $runtimeRequirements);

        $financeRoutes = $this->file('app/Finance/routes.php');
        $leaseRoutes = $this->file('app/Leases/routes.php');
        self::assertStringContainsString("\$app->get('/admin/finance/obligations'", $financeRoutes);
        self::assertStringContainsString("\$app->post('/admin/finance/obligations'", $financeRoutes);
        self::assertStringContainsString("\$app->get('/tenant/leases/{leaseId:[0-9]+}/obligations/{obligationId:[0-9]+}/source-support'", $leaseRoutes);
        self::assertDoesNotMatchRegularExpression("~\$app->post\('/tenant/[^']*obligation~", $leaseRoutes);
        self::assertDoesNotMatchRegularExpression("~\$app->get\('/tenant/[^']*settlement-support~", $leaseRoutes);
        self::assertStringNotContainsString('external_reference', $this->tenantObligationProjection($leaseRoutes));
        self::assertStringNotContainsString('settlement_support', $this->tenantObligationProjection($leaseRoutes));
        self::assertStringNotContainsString('source_support_sha256', $this->tenantObligationProjection($leaseRoutes));

        $ownershipStart = strpos($leaseRoutes, "\$app->get('/tenant/leases/{leaseId:[0-9]+}/obligations/");
        self::assertNotFalse($ownershipStart);
        $tenantSupportRoute = substr($leaseRoutes, $ownershipStart);
        $ownershipLookup = strpos($tenantSupportRoute, '$leases->findForUser(');
        $obligationLookup = strpos($tenantSupportRoute, '$obligations->find(');
        $supportLookup = strpos($tenantSupportRoute, '$obligations->privateSourceSupport(');
        self::assertNotFalse($ownershipLookup);
        self::assertNotFalse($obligationLookup);
        self::assertNotFalse($supportLookup);
        self::assertLessThan($obligationLookup, $ownershipLookup);
        self::assertLessThan($supportLookup, $obligationLookup);

        self::assertStringNotContainsString('/public/', $this->file('bootstrap/app.php'));
        self::assertStringContainsString("'/storage/obligation-supports'", $this->file('bootstrap/app.php'));
    }

    private function tenantObligationProjection(string $leaseRoutes): string
    {
        $start = strpos($leaseRoutes, '$tenantObligations = [];');
        $end = strpos($leaseRoutes, '$paymentHistory =', $start === false ? 0 : $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);

        return substr($leaseRoutes, $start, $end - $start);
    }

    private function file(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
