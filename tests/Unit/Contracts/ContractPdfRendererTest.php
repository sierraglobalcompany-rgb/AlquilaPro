<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\Contracts;

use AlquilaPro\Contracts\ContractPdfRenderer;
use PHPUnit\Framework\TestCase;

final class ContractPdfRendererTest extends TestCase
{
    public function testRendererProducesLockedDownUtf8MultipagePdfWithoutExternalBinary(): void
    {
        self::assertTrue(
            class_exists(ContractPdfRenderer::class),
            'F7.4 missing concrete ContractPdfRenderer.',
        );

        $renderer = new ContractPdfRenderer();
        $html = '<h1>Contrato de arrendamiento — Medellín</h1>'
            . '<p>Señor Pérez, canon $1.750.000, año 2026.</p>'
            . '<div style="page-break-before: always"></div>'
            . '<p>Segunda página: información contractual.</p>';

        $pdf = $renderer->render($html);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(1000, strlen($pdf));
        self::assertMatchesRegularExpression('/\/Count\s+[2-9][0-9]*/', $pdf);

        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Contracts/ContractPdfRenderer.php');
        self::assertIsString($source);
        self::assertStringContainsString('setIsRemoteEnabled(false)', $source);
        self::assertStringContainsString('DejaVu Sans', $source);
        self::assertStringContainsString("setPaper('letter', 'portrait')", $source);
        self::assertStringContainsString('page_text', $source);
        self::assertStringNotContainsString('shell_exec(', $source);
        self::assertStringNotContainsString('proc_open(', $source);
        self::assertStringNotContainsString('exec(', $source);
    }
}
