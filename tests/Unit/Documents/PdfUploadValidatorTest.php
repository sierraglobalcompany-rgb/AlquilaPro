<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\Documents;

use AlquilaPro\Documents\PdfUploadValidator;
use AlquilaPro\Tests\Support\PdfFixtures;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\PDFObject;
use Smalot\PdfParser\Parser;

final class PdfUploadValidatorTest extends TestCase
{
    public function testAcceptsConventionalScannedAndSignatureShapedPdfsWithoutChangingBytes(): void
    {
        $conventional = PdfFixtures::validPdf();
        self::assertTrue(PdfUploadValidator::isValid($conventional));

        $scanned = $this->baseObjects();
        $image = str_repeat("\x91", 4 * 1024 * 1024);
        $compressedImage = gzcompress($image);
        $scanned[4] = '<< /Length ' . strlen($compressedImage)
            . " /Filter /FlateDecode /Type /XObject /Subtype /Image /Width 2048 /Height 2048"
            . " /ColorSpace /DeviceGray /BitsPerComponent 8 >>\nstream\n"
            . $compressedImage . "\nendstream";
        self::assertTrue(PdfUploadValidator::isValid($this->buildPdf($scanned)));

        $signed = $this->baseObjects();
        $signed[5] = '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached '
            . '/ByteRange [0 100 200 50] /Contents <01020304> >>';
        $signedBytes = $this->buildPdf($signed);
        $before = hash('sha256', $signedBytes);
        self::assertTrue(PdfUploadValidator::isValid($signedBytes));
        self::assertSame($before, hash('sha256', $signedBytes));
    }

    public function testAcceptsPdfAtTheTenMebibyteBoundaryAndRejectsOneByteOver(): void
    {
        $pdf = PdfFixtures::validPdf();
        $atLimit = $pdf . str_repeat(' ', 10 * 1024 * 1024 - strlen($pdf));
        self::assertTrue(PdfUploadValidator::isValid($atLimit));
        self::assertFalse(PdfUploadValidator::isValid($atLimit . ' '));
    }

    public function testRejectsEmptyFakeTruncatedAndZipPolyglotFiles(): void
    {
        $validPdf = PdfFixtures::validPdf();
        self::assertFalse(PdfUploadValidator::isValid(''));
        self::assertFalse(PdfUploadValidator::isValid('%PDF-1.7\nThis is not a PDF.\n%%EOF'));
        self::assertFalse(PdfUploadValidator::isValid(substr($validPdf, 0, -8)));
        self::assertFalse(PdfUploadValidator::isValid(str_replace(
            '%%EOF',
            "PK\x03\x04payloadPK\x01\x02indexPK\x05\x06end%%EOF",
            $validPdf,
        )));
    }

    public function testRejectsActiveActionsAttachmentsAndEncryptedPdfs(): void
    {
        $active = $this->baseObjects();
        $active[1] = '<< /Type /Catalog /Pages 2 0 R /OpenAction 5 0 R >>';
        $active[5] = '<< /S /Java#53cript /J#53 (app.alert\\(1\\)) >>';
        self::assertFalse(PdfUploadValidator::isValid($this->buildPdf($active)));

        $attachment = $this->baseObjects();
        $attachment[1] = '<< /Type /Catalog /Pages 2 0 R /AF [6 0 R] '
            . '/Names << /EmbeddedFiles << /Names [(payload.bin) 6 0 R] >> >> >>';
        $attachment[6] = '<< /Type /Filespec /F (payload.bin) /EF << /F 7 0 R >> >>';
        $attachment[7] = "<< /Type /EmbeddedFile /Length 4 >>\nstream\nDATA\nendstream";
        self::assertFalse(PdfUploadValidator::isValid($this->buildPdf($attachment)));

        $encrypted = $this->baseObjects();
        $encrypted[5] = '<< /Filter /Standard /V 1 /R 2 /O (x) /U (y) /P -4 >>';
        self::assertFalse(PdfUploadValidator::isValid($this->buildPdf($encrypted, '/Encrypt 5 0 R')));

        $commentRecursion = $this->baseObjects();
        $commentRecursion[3] = str_repeat("% comment\n", 150)
            . '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] >>';
        self::assertFalse(PdfUploadValidator::isValid($this->buildPdf($commentRecursion)));
    }

    public function testRejectsHexEscapedOpenActionKeyReadByParser(): void
    {
        $control = $this->baseObjects();
        $control[1] = '<< /Type /Catalog /Pages 2 0 R /Metadata << /S /Named /N /Print >> >>';
        $controlBytes = $this->buildPdf($control);
        self::assertTrue(PdfUploadValidator::isValid($controlBytes));

        $active = $this->baseObjects();
        $active[1] = '<< /Type /Catalog /Pages 2 0 R /Open#41ction << /S /Named /N /Print >> >>';
        $bytes = $this->buildPdf($active);

        $document = (new Parser())->parseContent($bytes);
        $catalog = $document->getTrailer()->get('Root');
        self::assertInstanceOf(PDFObject::class, $catalog);
        $catalogElements = $catalog->getHeader()->getElements();
        self::assertArrayHasKey('Open#41ction', $catalogElements);
        self::assertFalse(PdfUploadValidator::isValid($bytes));
    }

    public function testAcceptsValidNestedPageTree(): void
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Pages /Parent 2 0 R /Kids [4 0 R] /Count 1 >>',
            4 => '<< /Type /Page /Parent 3 0 R /MediaBox [0 0 100 100] >>',
            5 => "<< /Length 0 >>\nstream\n\nendstream",
        ];

        self::assertTrue(PdfUploadValidator::isValid($this->buildPdf($objects)));
    }

    public function testRejectsPdfWithoutTrailerRootEvenWhenObjectsParse(): void
    {
        $bytes = $this->buildPdf($this->baseObjects(), '', false);

        $document = (new Parser())->parseContent($bytes);
        self::assertCount(4, $document->getObjects());
        self::assertFalse(PdfUploadValidator::isValid($bytes));
    }

    public function testRejectsMissingInvalidUnreachableAndCyclicPageTrees(): void
    {
        $withoutCatalog = $this->baseObjects();
        $withoutCatalog[1] = '<< /Type /Pages /Pages 2 0 R >>';

        $withoutPages = $this->baseObjects();
        $withoutPages[1] = '<< /Type /Catalog >>';

        $wrongPagesType = $this->baseObjects();
        $wrongPagesType[2] = '<< /Type /Catalog /Pages 3 0 R >>';

        $withoutReachablePage = $this->baseObjects();
        $withoutReachablePage[2] = '<< /Type /Pages /Kids [] /Count 0 >>';

        $pageWithoutParent = $this->baseObjects();
        $pageWithoutParent[3] = '<< /Type /Page /MediaBox [0 0 100 100] >>';

        $cyclic = $this->baseObjects();
        $cyclic[2] = '<< /Type /Pages /Kids [2 0 R] /Count 1 >>';

        foreach ([$withoutCatalog, $withoutPages, $wrongPagesType, $withoutReachablePage, $pageWithoutParent, $cyclic] as $objects) {
            self::assertFalse(PdfUploadValidator::isValid($this->buildPdf($objects)));
        }
    }

    public function testRejectsPageTreeBeyondTraversalDepthLimit(): void
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        for ($id = 2; $id <= 131; ++$id) {
            $childId = $id + 1;
            $objects[$id] = '<< /Type /Pages /Kids [' . $childId . ' 0 R] /Count 1 >>';
        }
        $objects[132] = '<< /Type /Page /Parent 131 0 R /MediaBox [0 0 100 100] >>';

        self::assertFalse(PdfUploadValidator::isValid($this->buildPdf($objects)));
    }

    public function testRejectsDecompressionAndAggregateLimitsAndDecodedZipPayload(): void
    {
        $bomb = $this->baseObjects();
        $expanded = gzcompress(str_repeat('A', 40 * 1024 * 1024));
        $bomb[4] = '<< /Length ' . strlen($expanded) . " /Filter /FlateDecode >>\nstream\n"
            . $expanded . "\nendstream";
        self::assertFalse(PdfUploadValidator::isValid($this->buildPdf($bomb)));

        $aggregate = $this->baseObjects();
        for ($id = 4; $id <= 7; $id++) {
            $stream = gzcompress(str_repeat(chr(0x90 + $id), 18 * 1024 * 1024));
            $aggregate[$id] = '<< /Length ' . strlen($stream) . " /Filter /FlateDecode >>\nstream\n"
                . $stream . "\nendstream";
        }
        self::assertFalse(PdfUploadValidator::isValid($this->buildPdf($aggregate)));

        $zip = $this->baseObjects();
        $payload = gzcompress("prefix PK\x03\x04 body PK\x01\x02 footer PK\x05\x06");
        $zip[4] = '<< /Length ' . strlen($payload) . " /Filter /FlateDecode >>\nstream\n"
            . $payload . "\nendstream";
        self::assertFalse(PdfUploadValidator::isValid($this->buildPdf($zip)));
    }

    private function baseObjects(): array
    {
        return [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] >>',
            4 => "<< /Length 0 >>\nstream\n\nendstream",
        ];
    }

    private function buildPdf(array $objects, string $trailerExtra = '', bool $includeRoot = true): string
    {
        ksort($objects);
        $pdf = "%PDF-1.7\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 %s \n", $offsets[$id] ?? 0, isset($offsets[$id]) ? 'n' : 'f');
        }
        $root = $includeRoot ? '/Root 1 0 R ' : '';
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " " . $root . $trailerExtra . " >>\n"
            . "startxref\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf;
    }
}
