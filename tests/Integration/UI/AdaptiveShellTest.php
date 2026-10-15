<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\UI;

use PHPUnit\Framework\TestCase;

final class AdaptiveShellTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testLayoutRendersTouchAndDesktopNavigationFromSameCollection(): void
    {
        $layout = $this->file('resources/views/layouts/app.php');

        self::assertSame(
            2,
            substr_count($layout, 'foreach ($navigation as $item)'),
            'Touch Apps and desktop sidebar must consume the same navigation collection.',
        );
        self::assertStringContainsString('class="desktop-sidebar"', $layout);
        self::assertStringContainsString('aria-label="Navegación de escritorio"', $layout);
        self::assertStringContainsString('class="bottom-nav"', $layout);
        self::assertStringContainsString('aria-label="Navegación principal"', $layout);
    }

    public function testAdaptiveShellHasAccessibleSelectedStateAndLocalDecorativeIcons(): void
    {
        $layout = $this->file('resources/views/layouts/app.php');

        self::assertStringContainsString('aria-current="page"', $layout);
        self::assertStringContainsString('<svg', $layout);
        self::assertStringContainsString('aria-hidden="true"', $layout);
        self::assertStringContainsString('aria-label="Abrir aplicaciones"', $layout);
        self::assertStringNotContainsString('https://fonts.googleapis.com', $layout);
        self::assertStringNotContainsString('https://fonts.gstatic.com', $layout);
    }

    public function testStylesDefineTouchTabletAndDesktopShellContracts(): void
    {
        $css = $this->file('assets/app.css');

        self::assertStringContainsString('@media (min-width: 768px) and (max-width: 1199px)', $css);
        self::assertStringContainsString('@media (min-width: 1200px)', $css);
        self::assertStringContainsString('.desktop-sidebar', $css);
        self::assertMatchesRegularExpression('/@media \(min-width: 1200px\)[\s\S]*?\.bottom-nav\s*\{[^}]*display:\s*none;/m', $css);
        self::assertMatchesRegularExpression('/@media \(min-width: 1200px\)[\s\S]*?\.desktop-sidebar\s*\{[^}]*display:/m', $css);
        self::assertStringContainsString('env(safe-area-inset-bottom)', $css);
        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    public function testAppsDialogKeepsNativeProgressiveEnhancement(): void
    {
        $javascript = $this->file('assets/app.js');

        self::assertStringContainsString('HTMLDialogElement', $javascript);
        self::assertStringContainsString('showModal()', $javascript);
        self::assertStringContainsString("dialog.addEventListener('cancel'", $javascript);
        self::assertStringNotContainsString('React', $javascript);
        self::assertStringNotContainsString('Vue', $javascript);
    }

    private function file(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing F23 adaptive shell source: ' . $path);

        return $contents;
    }
}
