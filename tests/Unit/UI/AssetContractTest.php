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

    public function testAppsJavascriptUsesNativeDialogWithoutExternalDependency(): void
    {
        self::assertStringContainsString('HTMLDialogElement', $this->js);
        self::assertStringContainsString('showModal()', $this->js);
        self::assertStringContainsString('dialog.close()', $this->js);
        self::assertStringNotContainsString('import ', $this->js);
        self::assertStringNotContainsString('fetch(', $this->js);
    }

    public function testAssetsContainNoFutureModuleLabels(): void
    {
        $assets = $this->css . "\n" . $this->js;

        foreach (['Cartera', 'Solicitudes', 'Propiedades', 'Contratos', 'Pólizas', 'Mantenimiento'] as $futureLabel) {
            self::assertStringNotContainsString($futureLabel, $assets);
        }
    }
}
