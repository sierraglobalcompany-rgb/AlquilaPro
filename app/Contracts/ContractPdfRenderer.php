<?php

declare(strict_types=1);

namespace AlquilaPro\Contracts;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class ContractPdfRenderer
{
    public function render(string $html): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->loadHtml($this->document($html), 'UTF-8');
        $dompdf->render();

        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        if ($font !== null) {
            $dompdf->getCanvas()->page_text(
                430,
                756,
                'Página {PAGE_NUM} de {PAGE_COUNT}',
                $font,
                8,
                [0.25, 0.25, 0.25],
            );
        }

        $bytes = $dompdf->output();
        if (!is_string($bytes) || !str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('No fue posible generar el PDF contractual.');
        }

        return $bytes;
    }

    private function document(string $html): string
    {
        return '<!doctype html><html lang="es"><head><meta charset="UTF-8">'
            . '<style>'
            . '@page{margin:54pt 54pt 58pt 54pt}'
            . 'body{font-family:"DejaVu Sans",sans-serif;font-size:10.5pt;line-height:1.45;color:#111}'
            . 'header{position:fixed;top:-35pt;left:0;right:0;height:20pt;font-size:8pt;color:#555}'
            . 'main{width:100%}'
            . 'h1,h2,h3{page-break-after:avoid}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'tr{page-break-inside:avoid}'
            . '</style></head><body>'
            . '<header>AlquilaPro — Documento contractual</header>'
            . '<main>' . $html . '</main>'
            . '</body></html>';
    }
}
