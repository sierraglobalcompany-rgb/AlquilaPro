<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\UI;

use PHPUnit\Framework\TestCase;

final class ResponsiveMarkupRegressionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testSharedFormsUseMobileFirstFieldsAndTouchSizedControls(): void
    {
        $css = $this->file('assets/app.css');

        self::assertStringContainsString('font-size: 16px;', $css);
        self::assertStringContainsString('min-height: 48px;', $css);
        self::assertMatchesRegularExpression(
            '/\.field-grid\s*\{[^}]*display:\s*grid;[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\);/s',
            $css,
        );
        self::assertMatchesRegularExpression(
            '/@media \(min-width: 768px\)[\s\S]*?\.field-grid\s*\{[^}]*grid-template-columns:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\);/s',
            $css,
        );
    }

    public function testCriticalPropertyCandidateFinanceMaintenanceAndTenantViewsStayCardBased(): void
    {
        foreach ([
            'resources/views/properties.php',
            'resources/views/property_detail.php',
            'resources/views/unit_detail.php',
            'resources/views/candidates.php',
            'resources/views/candidate_detail.php',
            'resources/views/contracts.php',
            'resources/views/finance_index.php',
            'resources/views/obligations.php',
            'resources/views/maintenance.php',
            'resources/views/tenant_home.php',
            'resources/views/tenant_lease_detail.php',
        ] as $path) {
            self::assertStringNotContainsString('<table', $this->file($path), $path . ' must not rely on a wide table for its critical flow.');
        }

        self::assertStringContainsString('<div class="table-wrap">', $this->file('resources/views/whatsapp.php'));
    }

    public function testSharedTableStatusAndEmptyStateStylesUseResponsiveTokens(): void
    {
        $css = $this->file('assets/app.css');

        self::assertMatchesRegularExpression('/\.table-wrap\s*\{[^}]*max-width:\s*100%;[^}]*overflow-x:\s*auto;/s', $css);
        self::assertStringContainsString('.status-pill {', $css);
        self::assertStringNotContainsString('#f3f3f1', $css);
        self::assertStringContainsString('.empty-state {', $css);
        self::assertStringContainsString('--brand-primary: var(--color-primary);', $css);
    }

    public function testCandidateDesktopGridStartsOnlyAfterFixedBottomNavigationEnds(): void
    {
        $css = $this->file('assets/app.css');
        $tabletIndex = strpos($css, '@media (min-width: 1024px) {');
        self::assertNotFalse($tabletIndex);
        $desktopIndex = strpos($css, '@media (min-width: 1200px) {', $tabletIndex);
        self::assertNotFalse($desktopIndex);

        $tabletRules = substr($css, $tabletIndex, $desktopIndex - $tabletIndex);
        self::assertStringNotContainsString(
            '.candidate-surface .app-shell',
            $tabletRules,
            'Keep base app-shell bottom spacing while fixed bottom-nav is still visible at 1024-1199px.',
        );
        self::assertStringContainsString(
            '.candidate-surface .app-shell { grid-template-columns: 212px minmax(0, 1fr); }',
            substr($css, $desktopIndex),
        );
    }

    private function file(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing responsive UI source: ' . $path);

        return $contents;
    }
}
