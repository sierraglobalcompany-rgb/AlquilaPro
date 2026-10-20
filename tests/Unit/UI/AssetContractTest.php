<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\UI;

use PHPUnit\Framework\TestCase;

final class AssetContractTest extends TestCase
{
    private string $css;
    private string $js;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $css = file_get_contents($root . '/assets/app.css');
        $js = file_get_contents($root . '/assets/app.js');

        self::assertIsString($css);
        self::assertIsString($js);

        $this->css = $css;
        $this->js = $js;
    }

    public function testCssHasNoRemoteImportsOrFontUrls(): void
    {
        self::assertStringNotContainsString('@import', strtolower($this->css));
        self::assertStringNotContainsString('@font-face', strtolower($this->css));
        self::assertDoesNotMatchRegularExpression('/url\s*\(\s*["\']?https?:\/\//i', $this->css);
    }

    public function testCssIncludesSafeAreaAndReducedMotionContracts(): void
    {
        self::assertStringContainsString('env(safe-area-inset-bottom)', $this->css);
        self::assertStringContainsString('env(safe-area-inset-top)', $this->css);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $this->css);
    }

    public function testCssDefinesVisibleFocusAndMinimumTouchTarget(): void
    {
        self::assertStringContainsString(':focus-visible', $this->css);
        self::assertMatchesRegularExpression('/min-height\s*:\s*44px\s*;/', $this->css);
        self::assertMatchesRegularExpression('/min-height\s*:\s*48px\s*;/', $this->css);
    }

    public function testResponsiveImagesCannotOverflowPublicCards(): void
    {
        self::assertMatchesRegularExpression(
            '/img\s*\{[^}]*max-width\s*:\s*100%\s*;[^}]*height\s*:\s*auto\s*;/s',
            $this->css,
        );
    }

    public function testCandidateNavigationVariantsAreMutuallyExclusiveAtTheShellBreakpoint(): void
    {
        self::assertMatchesRegularExpression('/\.candidate-tabs\s*\{[^}]*display:\s*none\s*;/s', $this->css);
        self::assertMatchesRegularExpression('/\.candidate-section-menu\s*\{[^}]*display:\s*block\s*;/s', $this->css);
        self::assertMatchesRegularExpression('/@media \(min-width: 1200px\)\s*\{\s*\.candidate-tabs\s*\{[^}]*display:\s*block\s*;\s*\}\s*\.candidate-section-menu\s*\{[^}]*display:\s*none\s*;/s', $this->css);
    }

    public function testCandidateNavigationPreservesTheExistingTouchTargetMinimum(): void
    {
        foreach (['.candidate-tab', '.candidate-section-menu > summary', '.candidate-section-menu__link'] as $selector) {
            self::assertMatchesRegularExpression('/' . preg_quote($selector, '/') . '\s*\{[^}]*min-height:\s*48px\s*;/s', $this->css);
        }
    }

    public function testCandidateMenuFocusReservesSpaceForFixedMobileNavigation(): void
    {
        self::assertMatchesRegularExpression(
            '/\.candidate-section-menu__link\s*\{[^}]*scroll-margin-block:\s*100px\s*;/s',
            $this->css,
            'Native keyboard focus must scroll candidate links clear of the fixed mobile bars.',
        );
    }

    public function testCandidateDesktopDensityUsesCompactLayoutWithoutZoomOrHiddenCards(): void
    {
        // Guard the previously measured sources of 234px of extra document height.
        // This does NOT certify the rendered mockup: Browser QA at 1448x1086 is mandatory.
        self::assertMatchesRegularExpression('/\.candidate-page\s*\{[^}]*gap:\s*14px;/s', $this->css);
        self::assertMatchesRegularExpression('/\.candidate-back\s*\{[^}]*min-height:\s*44px;/s', $this->css);
        self::assertMatchesRegularExpression('/\.candidate-card\s*\{[^}]*padding:\s*18px;/s', $this->css);
        self::assertMatchesRegularExpression('/\.candidate-fact-grid\s*\{[^}]*gap:\s*14px 24px;[^}]*margin-top:\s*16px;/s', $this->css);
        self::assertMatchesRegularExpression('/\.candidate-card--progress\s*\{[^}]*gap:\s*9px;/s', $this->css);
        self::assertStringContainsString('.candidate-progress-footer', $this->css);
        self::assertStringNotContainsString('zoom:', $this->css);
    }

    public function testLongPublicLinksCanWrapWithinCards(): void
    {
        self::assertMatchesRegularExpression(
            '/a\s*\{[^}]*overflow-wrap\s*:\s*anywhere\s*;/s',
            $this->css,
        );
    }

    public function testCandidateDetailLongValuesCanWrapOnNarrowScreens(): void
    {
        self::assertMatchesRegularExpression(
            '/\.candidate-shell__copy\s*\{[^}]*overflow-wrap\s*:\s*anywhere\s*;/s',
            $this->css,
        );
        self::assertMatchesRegularExpression(
            '/dl\s+dd[^\{]*\{[^}]*overflow-wrap\s*:\s*anywhere\s*;/s',
            $this->css,
        );
        self::assertMatchesRegularExpression(
            '/\.user-card\s*>\s*div[^\{]*\{[^}]*overflow-wrap\s*:\s*anywhere\s*;/s',
            $this->css,
        );
    }

    public function testF4PrivateLayoutPrimitivesAreDefined(): void
    {
        foreach (['.content-card', '.page-stack', '.card-list', '.button-row', '.check-row'] as $selector) {
            self::assertStringContainsString($selector, $this->css, $selector . ' must have an explicit local style contract.');
        }
    }

    public function testPublicFoundationHasOwnNamespacedLayoutContract(): void
    {
        foreach (['.public-page', '.public-shell', '.public-main'] as $selector) {
            self::assertStringContainsString($selector, $this->css);
        }
    }

    public function testPublicFoundationIsNotLimitedToAuthCardWidth(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/\.public-shell\s*\{[^}]*max-width\s*:\s*520px/s',
            $this->css,
        );
        self::assertDoesNotMatchRegularExpression(
            '/\.public-main\s*\{[^}]*max-width\s*:\s*520px/s',
            $this->css,
        );
    }

    public function testAppsJavascriptUsesNativeDialogWithoutExternalDependency(): void
    {
        self::assertStringContainsString('HTMLDialogElement', $this->js);
        self::assertStringContainsString('showModal()', $this->js);
        self::assertStringContainsString('dialog.close()', $this->js);
        self::assertStringNotContainsString('import ', $this->js);
        self::assertStringNotContainsString('fetch(', $this->js);
    }

    public function testAssetsContainNoUnreleasedModuleLabels(): void
    {
        $assets = $this->css . "\n" . $this->js;

        foreach (['Cartera', 'Solicitudes', 'Propiedades', 'Contratos', 'Mantenimiento'] as $futureLabel) {
            self::assertStringNotContainsString($futureLabel, $assets);
        }
    }
}
