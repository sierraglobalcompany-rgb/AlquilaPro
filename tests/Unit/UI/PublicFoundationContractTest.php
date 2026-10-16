<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\UI;

use PHPUnit\Framework\TestCase;

final class PublicFoundationContractTest extends TestCase
{
    private string $layout;
    private string $landing;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $layout = file_get_contents($root . '/resources/views/layouts/public.php');
        $landing = file_get_contents($root . '/resources/views/public_landing.php');

        self::assertIsString($layout);
        self::assertIsString($landing);

        $this->layout = $layout;
        $this->landing = $landing;
    }

    public function testPublicLayoutOwnsItsStructureInsteadOfAuthContainers(): void
    {
        self::assertStringContainsString('class="public-page"', $this->layout);
        self::assertStringContainsString('class="public-shell"', $this->layout);
        self::assertStringContainsString('class="public-main"', $this->layout);
        self::assertStringContainsString('data-public-shell', $this->layout);
        self::assertStringNotContainsString('class="auth-shell"', $this->layout);
        self::assertStringNotContainsString('class="auth-card"', $this->layout);
    }

    public function testPublicMarkupKeepsBasePathOwnedUrls(): void
    {
        self::assertStringContainsString('$basePath', $this->layout);
        self::assertStringContainsString('/assets/app.css', $this->layout);
        self::assertStringContainsString('/brand/logo', $this->layout);
        self::assertStringContainsString('$basePath', $this->landing);
        self::assertStringContainsString('/catalog', $this->landing);
        self::assertStringContainsString('/login', $this->landing);
    }

    public function testPublicLayoutLoadsExistingApplicationJavascriptDeferredOnce(): void
    {
        self::assertSame(1, substr_count($this->layout, '/assets/app.js'));
        self::assertStringContainsString('/assets/app.js', $this->layout);
        self::assertStringContainsString('defer', $this->layout);
    }

    public function testLandingKeepsServerRenderedCarouselProgressiveEnhancementMarkers(): void
    {
        self::assertStringContainsString('data-public-carousel', $this->landing);
        self::assertStringContainsString('public-carousel__track', $this->landing);
        self::assertStringContainsString('data-public-featured-unit', $this->landing);
        self::assertStringContainsString('public-login-panel', $this->landing);
    }
}
