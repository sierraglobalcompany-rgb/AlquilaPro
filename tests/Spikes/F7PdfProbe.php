<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->setPaper('letter', 'portrait');

$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZfGQAAAAASUVORK5CYII=';

$html = <<<'HTML'
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 72px 54px 60px; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; line-height: 1.45; color: #111; }
header { position: fixed; top: -48px; left: 0; right: 0; height: 24px; border-bottom: 1px solid #999; }
footer { position: fixed; bottom: -36px; left: 0; right: 0; height: 20px; border-top: 1px solid #999; font-size: 9px; }
h1 { font-size: 18px; margin: 0 0 12px; }
table { width: 100%; border-collapse: collapse; margin: 12px 0; }
th, td { border: 1px solid #777; padding: 5px; }
.page-break { page-break-before: always; }
.logo { width: 18px; height: 18px; vertical-align: middle; }
</style>
</head>
<body>
<header>Alquila Pro — contrato de prueba</header>
<footer>Documento generado por el spike F7</footer>
<h1>Contrato de arrendamiento — José Álvarez</h1>
<p>Objeto: validación UTF‑8 con caracteres españoles: áéíóú ñ Ñ ¿¡ — “comillas”.</p>
<p><img class="logo" src="__PNG__" alt="imagen"> Imagen embebida de prueba.</p>
<table>
<thead><tr><th>Concepto</th><th>Valor</th></tr></thead>
<tbody>
<tr><td>Canon mensual</td><td>$ 1.500.000</td></tr>
<tr><td>Administración</td><td>$ 250.000</td></tr>
</tbody>
</table>
<p>Cláusula de prueba con texto suficiente para validar maquetación HTML/CSS y tipografía Unicode.</p>
<div class="page-break"></div>
<h1>Anexo</h1>
<p>Segunda página forzada para validar saltos de página.</p>
</body>
</html>
HTML;

$html = str_replace('__PNG__', $png, $html);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
$canvas->page_text(500, 756, '{PAGE_NUM}/{PAGE_COUNT}', $font, 9);

$pdf = $dompdf->output();
$pageCount = $canvas->get_page_count();

$failures = [];
if (!str_starts_with($pdf, '%PDF-')) {
    $failures[] = 'output is not a PDF';
}
if (strlen($pdf) < 5000) {
    $failures[] = 'PDF output is unexpectedly small';
}
if ($pageCount < 2) {
    $failures[] = 'forced page break did not produce at least two pages';
}
if (!extension_loaded('dom')) {
    $failures[] = 'ext-dom is not loaded';
}
if (!extension_loaded('mbstring')) {
    $failures[] = 'ext-mbstring is not loaded';
}
if (!extension_loaded('gd')) {
    $failures[] = 'ext-gd is not loaded for image coverage';
}

if ($failures !== []) {
    fwrite(STDERR, 'F7_PDF_SPIKE_FAIL: ' . implode('; ', $failures) . PHP_EOL);
    exit(1);
}

$outputPath = sprintf('f7-pdf-spike-%d.%d.pdf', PHP_MAJOR_VERSION, PHP_MINOR_VERSION);
if (file_put_contents($outputPath, $pdf) === false) {
    fwrite(STDERR, 'F7_PDF_SPIKE_FAIL: unable to persist PDF artifact' . PHP_EOL);
    exit(1);
}

printf(
    "F7_PDF_SPIKE_PASS php=%s dompdf=%s pages=%d bytes=%d ext_dom=yes ext_mbstring=yes ext_gd=yes artifact=%s\n",
    PHP_VERSION,
    defined('Dompdf\\Dompdf::VERSION') ? Dompdf::VERSION : 'installed',
    $pageCount,
    strlen($pdf),
    $outputPath,
);
