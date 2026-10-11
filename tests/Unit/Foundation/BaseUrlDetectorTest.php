<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\Foundation;

use AlquilaPro\Foundation\BaseUrlDetector;
use PHPUnit\Framework\TestCase;

final class BaseUrlDetectorTest extends TestCase
{
    public function testDetectsHttpsSubdomainBaseUrl(): void
    {
        if (!class_exists(BaseUrlDetector::class)) {
            self::fail('BaseUrlDetector must exist before this behavior can pass.');
        }

        $url = BaseUrlDetector::fromServer([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'alquilapro.sierraglobalcompany.com',
            'SCRIPT_NAME' => '/index.php',
        ]);

        self::assertSame('https://alquilapro.sierraglobalcompany.com', $url);
    }

    public function testDetectsSubfolderBaseUrlWithoutDuplicatingPath(): void
    {
        if (!class_exists(BaseUrlDetector::class)) {
            self::fail('BaseUrlDetector must exist before this behavior can pass.');
        }

        $url = BaseUrlDetector::fromServer([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'example.com',
            'SCRIPT_NAME' => '/apps/alquila/index.php',
        ]);

        self::assertSame('https://example.com/apps/alquila', $url);
    }

    public function testKeepsHttpForLocalDevelopmentWhenHttpsIsNotIndicated(): void
    {
        if (!class_exists(BaseUrlDetector::class)) {
            self::fail('BaseUrlDetector must exist before this behavior can pass.');
        }

        $url = BaseUrlDetector::fromServer([
            'HTTP_HOST' => 'localhost:8080',
            'SCRIPT_NAME' => '/index.php',
        ]);

        self::assertSame('http://localhost:8080', $url);
    }
}
