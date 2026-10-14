<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use PHPUnit\Framework\TestCase;

final class PaymentMethodSecurityRegressionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testF10UsesOneAuthorityFourPrivateRoutesAndNoNewRuntimeArchitecture(): void
    {
        $migration = $this->file('database/migrations/010_create_payment_methods.sql');
        preg_match_all('/CREATE TABLE\s+([a-z_]+)/i', $migration, $tables);
        self::assertSame(['payment_methods'], $tables[1] ?? []);
        self::assertDoesNotMatchRegularExpression(
            '/^\s*[a-z_]*(?:password|pin|otp|api_key|secret|token|webhook)[a-z_]*\s+/mi',
            $migration,
        );

        $migrations = implode("\n", array_map(
            static fn (string $path): string => (string) file_get_contents($path),
            glob($this->root . '/database/migrations/*.sql') ?: [],
        ));
        preg_match_all('/CREATE TABLE\s+([a-z_]+)/i', $migrations, $allTables);
        foreach ([
            'payment_method_scopes',
            'lease_payment_methods',
            'property_payment_methods',
            'payment_providers',
            'bank_tokens',
            'payment_transactions',
            'payment_intents',
            'payment_reports',
        ] as $forbiddenTable) {
            self::assertNotContains($forbiddenTable, $allTables[1] ?? []);
        }

        $financeServices = glob($this->root . '/app/Finance/*Service.php') ?: [];
        $financeServices = array_map(static fn (string $path): string => str_replace('\\', '/', $path), $financeServices);
        sort($financeServices);
        self::assertSame([
            str_replace('\\', '/', $this->root) . '/app/Finance/FinanceService.php',
            str_replace('\\', '/', $this->root) . '/app/Finance/PaymentMethodService.php',
        ], $financeServices);
        $paymentMethodClassFiles = [];
        $appFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root . '/app', \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($appFiles as $appFile) {
            if (!$appFile->isFile() || $appFile->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($appFile->getPathname());
            if (preg_match('/\bclass\s+PaymentMethodService\b/', $source) === 1) {
                $paymentMethodClassFiles[] = $appFile->getPathname();
            }
        }
        self::assertCount(1, $paymentMethodClassFiles);

        $financeRouteFiles = glob($this->root . '/app/Finance/*routes*.php') ?: [];
        $leaseRouteFiles = glob($this->root . '/app/Leases/*routes*.php') ?: [];
        self::assertCount(1, $financeRouteFiles);
        self::assertCount(1, $leaseRouteFiles);
        $financeRoutes = $this->file('app/Finance/routes.php');
        $leaseRoutes = $this->file('app/Leases/routes.php');
        $routeFilesWithF10 = [];
        foreach (glob($this->root . '/app/*/routes.php') ?: [] as $routeFile) {
            if (str_contains((string) file_get_contents($routeFile), 'payment-methods')) {
                $routeFilesWithF10[] = str_replace('\\', '/', substr($routeFile, strlen($this->root) + 1));
            }
        }
        sort($routeFilesWithF10);
        self::assertSame(['app/Finance/routes.php', 'app/Leases/routes.php'], $routeFilesWithF10);
        preg_match_all("~->(?:get|post)\\('([^']*payment-methods[^']*)'~", $financeRoutes, $adminF10Routes);
        preg_match_all("~->(?:get|post)\\('([^']*payment-methods[^']*)'~", $leaseRoutes, $tenantF10Routes);
        self::assertSame([
            '/admin/finance/payment-methods',
            '/admin/finance/payment-methods/{id:[0-9]+}',
        ], array_values(array_unique($adminF10Routes[1] ?? [])));
        self::assertSame([
            '/tenant/leases/{leaseId:[0-9]+}/payment-methods/{methodId:[0-9]+}/qr',
        ], $tenantF10Routes[1] ?? []);
        self::assertCount(3, $adminF10Routes[1] ?? []);
        self::assertCount(1, $tenantF10Routes[1] ?? []);
        self::assertStringContainsString("\$app->get('/admin/finance/payment-methods'", $financeRoutes);
        self::assertStringContainsString("\$app->post('/admin/finance/payment-methods'", $financeRoutes);
        self::assertStringContainsString("\$app->post('/admin/finance/payment-methods/{id:[0-9]+}'", $financeRoutes);
        self::assertStringContainsString("\$app->get('/tenant/leases/{leaseId:[0-9]+}/payment-methods/", $leaseRoutes);
        self::assertStringContainsString("\$staffGuard(\$response, 'finance.view')", $financeRoutes);
        self::assertStringContainsString("\$staffGuard(\$response, 'finance.manage')", $financeRoutes);
        self::assertStringContainsString('$tenantGuard($response)', $leaseRoutes);

        $permissions = $this->file('app/Auth/Permissions.php');
        preg_match_all('/^\s*\'(finance\.[a-z]+)\',?\s*$/m', $permissions, $financePermissions);
        self::assertSame(['finance.view', 'finance.manage'], $financePermissions[1] ?? []);

        $composer = json_decode($this->file('composer.json'), true, 32, JSON_THROW_ON_ERROR);
        $runtimeRequirements = array_keys($composer['require'] ?? []);
        sort($runtimeRequirements);
        self::assertSame(['dompdf/dompdf', 'php', 'slim/psr7', 'slim/slim'], $runtimeRequirements);

        $bootstrap = $this->file('bootstrap/app.php');
        self::assertSame(1, substr_count($bootstrap, 'new PaymentMethodService($pdo, $paymentMethodQrDirectory)'));
        self::assertStringContainsString("'payment_methods' => \$paymentMethods", $bootstrap);
        self::assertStringContainsString('$financeRoutes($app, $shared)', $bootstrap);
        self::assertStringContainsString('$leaseRoutes($app, $shared + [', $bootstrap);
        foreach (['new SessionGuard(', 'new Csrf(', 'session_start('] as $parallelAuth) {
            self::assertStringNotContainsString($parallelAuth, $financeRoutes . $leaseRoutes);
        }
        foreach (['PaymentProvider', 'PaymentGateway', 'BankApi', 'webhook', 'curl_init('] as $integrationLayer) {
            self::assertStringNotContainsString($integrationLayer, $this->file('app/Finance/PaymentMethodService.php'));
        }
    }

    public function testCanonicalTypesScopeOrderingAndQrOwnershipAreFrozen(): void
    {
        $service = $this->file('app/Finance/PaymentMethodService.php');
        self::assertStringContainsString(
            "private const TYPES = ['bank_account', 'breb_key', 'breb_qr', 'wallet', 'manual'];",
            $service,
        );
        self::assertStringContainsString(
            "private const BREB_KEY_TYPES = ['identity_document', 'mobile', 'email', 'alphanumeric', 'merchant_code'];",
            $service,
        );
        self::assertStringContainsString(
            'WHERE is_active = 1 AND (property_id IS NULL OR property_id = :property_id)',
            $service,
        );
        self::assertStringContainsString('ORDER BY sort_order ASC, id ASC', $service);
        self::assertDoesNotMatchRegularExpression('/function\s+delete\s*\(/i', $service);

        $leaseRoutes = $this->file('app/Leases/routes.php');
        $detailStart = strpos($leaseRoutes, "\$app->get('/tenant/leases/{id:[0-9]+}'");
        $qrStart = strpos($leaseRoutes, "\$app->get('/tenant/leases/{leaseId:[0-9]+}/payment-methods/");
        $contractStart = strpos($leaseRoutes, "\$app->get('/tenant/leases/{id:[0-9]+}/contract'");
        self::assertNotFalse($detailStart);
        self::assertNotFalse($qrStart);
        self::assertNotFalse($contractStart);
        $detailRoute = substr($leaseRoutes, $detailStart, $qrStart - $detailStart);
        $qrRoute = substr($leaseRoutes, $qrStart, $contractStart - $qrStart);
        $detailOwnership = strpos($detailRoute, '$leases->findForUser(');
        $detailScope = strpos($detailRoute, '$paymentMethods->listForProperty(');
        self::assertNotFalse($detailOwnership);
        self::assertNotFalse($detailScope);
        self::assertLessThan(
            $detailScope,
            $detailOwnership,
            'Lease ownership must be resolved before payment-method scope.',
        );
        $qrOwnership = strpos($qrRoute, '$leases->findForUser(');
        $qrScope = strpos($qrRoute, '$paymentMethods->listForProperty(');
        $qrDelivery = strpos($qrRoute, '$paymentMethods->privateQr(');
        self::assertNotFalse($qrOwnership);
        self::assertNotFalse($qrScope);
        self::assertNotFalse($qrDelivery);
        self::assertLessThan(
            $qrScope,
            $qrOwnership,
            'QR delivery must resolve lease ownership before method scope.',
        );
        self::assertLessThan(
            $qrDelivery,
            $qrScope,
            'QR integrity lookup must follow active scope authorization.',
        );

        $adminRoute = $this->file('app/Finance/routes.php');
        self::assertStringContainsString("unset(\n                \$method['qr_stored_name']", $adminRoute);
        foreach (['qr_stored_name', 'qr_mime_type', 'qr_sha256', 'qr_size_bytes'] as $internalQrField) {
            self::assertStringNotContainsString($internalQrField, $this->file('resources/views/payment_methods.php'));
            self::assertStringNotContainsString($internalQrField, $this->file('resources/views/tenant_lease_detail.php'));
        }
    }

    public function testF10ProductSurfacesContainNoCredentialFieldsOrF11Workflow(): void
    {
        $service = $this->file('app/Finance/PaymentMethodService.php');
        $adminView = $this->file('resources/views/payment_methods.php');
        $tenantView = $this->file('resources/views/tenant_lease_detail.php');
        $financeRoutes = $this->file('app/Finance/routes.php');
        $leaseRoutes = $this->file('app/Leases/routes.php');
        $detailStart = strpos($leaseRoutes, "\$app->get('/tenant/leases/{id:[0-9]+}'");
        $contractStart = strpos($leaseRoutes, "\$app->get('/tenant/leases/{id:[0-9]+}/contract'");
        self::assertNotFalse($detailStart);
        self::assertNotFalse($contractStart);
        $tenantF10Routes = substr($leaseRoutes, $detailStart, $contractStart - $detailStart);
        $f10SourceAndSurfaces = $service . $financeRoutes . $tenantF10Routes . $adminView . $tenantView;
        foreach (['bank_username', 'bank_password', 'bank_pin', 'otp', 'api_key', 'webhook_secret', 'access_token'] as $credentialField) {
            self::assertStringNotContainsString($credentialField, $f10SourceAndSurfaces);
        }
        preg_match_all('/(?:error_log|logger->\w+)\s*\(([^;]*?)\);/i', $f10SourceAndSurfaces, $logExpressions);
        foreach ($logExpressions[1] ?? [] as $logExpression) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:password|pin|otp|api[_-]?key|secret|token|webhook|qr_stored_name|qr_sha256|qr_size_bytes)/i',
                $logExpression,
            );
        }

        // F11 now composes reporting in the existing tenant lease view; keep this F10-only guard scoped to its own screen.
        $productSurfaces = strtolower($adminView);
        foreach ([
            'ya pagué',
            'reportar pago',
            'reconciliación',
            'payment_transactions',
            'payment_reports',
            'payment_allocations',
            'payments',
            'proof_received',
            'reconciled',
            'reported',
            'partial',
            'paid',
        ] as $futureWorkflow) {
            self::assertStringNotContainsString(strtolower($futureWorkflow), $productSurfaces);
        }
    }

    private function file(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing F10 security regression source: ' . $path);

        return $contents;
    }
}
