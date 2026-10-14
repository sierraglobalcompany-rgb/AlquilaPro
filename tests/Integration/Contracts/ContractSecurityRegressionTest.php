<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Contracts;

use PHPUnit\Framework\TestCase;

final class ContractSecurityRegressionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testF7KeepsSinglePrivateContractArchitectureWithoutParallelStacks(): void
    {
        $routes = $this->file('app/Contracts/routes.php');
        preg_match_all('/\$app->(?:get|post)\(\'([^\']+)\'/', $routes, $matches);
        $paths = $matches[1] ?? [];
        self::assertNotSame([], $paths);
        foreach ($paths as $path) {
            self::assertStringStartsWith('/admin/', $path, 'F7 contract routes must remain private admin routes.');
        }

        $migration = $this->file('database/migrations/007_create_contract_engine.sql');
        preg_match_all('/CREATE TABLE\s+([a-z_]+)/i', $migration, $tableMatches);
        self::assertSame(
            ['contract_templates', 'contract_template_versions', 'contracts'],
            $tableMatches[1] ?? [],
            'F7 must keep exactly the three approved contract tables.',
        );

        $composer = json_decode($this->file('composer.json'), true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        $direct = array_keys($composer['require'] ?? []);
        sort($direct);
        self::assertSame(
            ['dompdf/dompdf', 'php', 'slim/psr7', 'slim/slim'],
            $direct,
            'F7 must not grow a second runtime stack beyond Dompdf.',
        );

        $contractService = $this->file('app/Contracts/ContractService.php');
        $contractRenderer = $this->file('app/Contracts/ContractPdfRenderer.php');
        $contractCore = implode("\n", [$contractService, $contractRenderer]);
        self::assertStringNotContainsString(
            'LeaseService',
            $contractCore,
            'F7 core must not absorb F8 lease domain logic.',
        );
        self::assertStringNotContainsString(
            'FinanceService',
            $contractCore,
            'Finance composition belongs in route/view boundaries, not the F7 contract or PDF core.',
        );

        $contractPhp = implode("\n", [$contractCore, $routes]);
        foreach ([
            'ContractRepository',
            'TemplateService',
            'VariableResolver',
            'StorageService',
            'TenantService',
            'Twig',
            'Mustache',
            'Guzzle',
            'session_start(',
            'new SessionGuard(',
            'new Csrf(',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $contractPhp, 'Unexpected F7 parallel architecture: ' . $forbidden);
        }
    }

    public function testPdfBoundaryKeepsRemoteResourcesBinariesAndPhysicalNamesPrivate(): void
    {
        $renderer = $this->file('app/Contracts/ContractPdfRenderer.php');
        self::assertStringContainsString('setIsRemoteEnabled(false)', $renderer);
        self::assertStringNotContainsString('setIsRemoteEnabled(true)', $renderer);
        foreach (['curl_', 'Guzzle', 'shell_exec(', 'proc_open(', 'wkhtmltopdf', 'chromium', 'chrome --'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $renderer);
        }

        $service = $this->file('app/Contracts/ContractService.php');
        foreach (['MAX_PDF_BYTES', "'%PDF-'", 'realpath(', 'hash_equals(', "bin2hex(random_bytes(16)) . '.pdf'"] as $required) {
            self::assertStringContainsString($required, $service);
        }
        self::assertStringContainsString('/^[a-f0-9]{32}\\.pdf$/D', $service);

        $routes = $this->file('app/Contracts/routes.php');
        self::assertStringNotContainsString('pdf_stored_name', $routes);
        self::assertStringContainsString("'Cache-Control', 'private, no-store'", $routes);
        self::assertStringContainsString("'X-Content-Type-Options', 'nosniff'", $routes);

        $detail = $this->file('resources/views/contract_detail.php');
        self::assertStringNotContainsString('pdf_stored_name', $detail);

        $htaccess = $this->file('.htaccess');
        self::assertMatchesRegularExpression('/\\bstorage\\b/i', $htaccess);
        self::assertMatchesRegularExpression('/\\[F(?:,|\\])/', $htaccess);
    }

    public function testTemplateAndJsonBoundariesRemainExplicitlyFailClosed(): void
    {
        $service = $this->file('app/Contracts/ContractService.php');
        self::assertStringContainsString("preg_match('/<\\s*\\/?\\s*[a-z!][^>]*>/i'", $service);
        self::assertStringContainsString("preg_match('#https?://#i'", $service);
        self::assertStringContainsString("str_contains(\$body, '<?')", $service);
        self::assertStringContainsString('htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE', $service);
        self::assertStringContainsString('count($input) > 30', $service);
        self::assertStringContainsString('count($input) > 20', $service);
        self::assertStringContainsString('mb_strlen($text) > 4000', $service);
        self::assertStringContainsString("array_diff(array_keys(\$task), ['label', 'done'])", $service);
        self::assertStringContainsString("array_diff(array_keys(\$annex), ['title', 'note'])", $service);
    }

    public function testGenerateLocksDraftBeforeResolvingImmutableEvidence(): void
    {
        $service = $this->file('app/Contracts/ContractService.php');
        $start = strpos($service, 'public function generate(');
        $end = strpos($service, 'public function privatePdf(', $start === false ? 0 : $start);
        self::assertNotFalse($start, 'ContractService::generate must exist.');
        self::assertNotFalse($end, 'ContractService::privatePdf must delimit generate().');

        $generate = substr($service, $start, $end - $start);
        self::assertIsString($generate);

        $begin = strpos($generate, '$this->pdo->beginTransaction();');
        $lock = strpos($generate, 'SELECT status FROM contracts WHERE id = :id FOR UPDATE');
        $resolve = strpos($generate, '$this->resolvedDocument(');
        $render = strpos($generate, '$renderer->render(');

        self::assertNotFalse($begin, 'generate() must start a transaction.');
        self::assertNotFalse($lock, 'generate() must lock the draft row.');
        self::assertNotFalse($resolve, 'generate() must resolve immutable evidence.');
        self::assertNotFalse($render, 'generate() must render the PDF.');
        self::assertTrue($begin < $lock, 'generate() must begin its transaction before locking the draft.');
        self::assertTrue($lock < $resolve, 'generate() must lock the draft before resolving snapshot values.');
        self::assertTrue($lock < $render, 'generate() must lock the draft before rendering PDF bytes.');
    }

    public function testLongContractTextSurfacesHaveResponsiveWrappingContract(): void
    {
        $css = $this->file('assets/app.css');

        self::assertMatchesRegularExpression(
            '/pre\\s*\\{[^}]*white-space:\\s*pre-wrap;[^}]*overflow-wrap:\\s*anywhere;/s',
            $css,
            'Long plain-text template versions must wrap instead of overflowing on mobile.',
        );
        self::assertMatchesRegularExpression(
            '/\\.contract-preview__body\\s*\\{[^}]*overflow-wrap:\\s*anywhere;/s',
            $css,
            'Resolved candidate/custom values must wrap inside the contract preview.',
        );
    }

    private function file(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing F7 closure source: ' . $path);

        return $contents;
    }
}
