<?php

declare(strict_types=1);

namespace AlquilaPro\Documents;

use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Element\ElementArray;
use Smalot\PdfParser\Element\ElementName;
use Smalot\PdfParser\Font;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\PDFObject;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\RawData\FilterHelper;
use Smalot\PdfParser\RawData\RawDataParser;
use Throwable;

final class PdfUploadValidator
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;
    private const MAX_DECODED_STREAM_BYTES = 32 * 1024 * 1024;
    private const MAX_TOTAL_DECODED_BYTES = 64 * 1024 * 1024;
    private const MAX_NESTING_DEPTH = 128;
    private const MAX_OBJECTS = 5000;

    public static function isValid(string $bytes): bool
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_FILE_BYTES
            || !str_starts_with($bytes, '%PDF-')
            || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'application/pdf'
            || !preg_match('/%%EOF[\x00\x09\x0A\x0C\x0D\x20]*\z/', $bytes)
            || self::containsZipPayload($bytes)
        ) {
            return false;
        }

        try {
            $config = new Config();
            $config->setDecodeMemoryLimit(self::MAX_DECODED_STREAM_BYTES);
            $config->setMaxNestingDepth(self::MAX_NESTING_DEPTH);
            $document = (new BoundedPdfParser($config))->parseContent($bytes);
            return self::hasValidPageTree($document) && !self::findUnsafeContent($document);
        } catch (Throwable) {
            return false;
        }
    }

    private static function hasValidPageTree(Document $document): bool
    {
        $catalog = $document->getTrailer()->get('Root');
        if (!$catalog instanceof PDFObject || !self::hasName($catalog, 'Type', 'Catalog')) {
            return false;
        }

        $rootPages = $catalog->get('Pages');
        if (!$rootPages instanceof PDFObject || !self::hasName($rootPages, 'Type', 'Pages')
            || $rootPages->has('Parent')
        ) {
            return false;
        }

        $pending = [[$rootPages, null, 0]];
        $seen = new \SplObjectStorage();
        $pageCount = 0;
        $visitedCount = 0;

        while ($pending !== []) {
            [$node, $parent, $depth] = array_pop($pending);
            if ($depth > self::MAX_NESTING_DEPTH || $seen->offsetExists($node)
                || ++$visitedCount > self::MAX_OBJECTS
            ) {
                return false;
            }
            $seen->offsetSet($node, true);

            if ($parent instanceof PDFObject && $node->get('Parent') !== $parent) {
                return false;
            }

            $type = $node->get('Type');
            if (!$type instanceof ElementName) {
                return false;
            }

            if ($type->getContent() === 'Page') {
                if ($node->has('Kids')) {
                    return false;
                }
                ++$pageCount;
                continue;
            }

            if ($type->getContent() !== 'Pages') {
                return false;
            }

            $kids = $node->get('Kids');
            if (!$kids instanceof ElementArray) {
                return false;
            }
            $rawChildren = $kids->getRawContent();
            $availableNodes = self::MAX_OBJECTS - $visitedCount - count($pending);
            if ($rawChildren === [] || count($rawChildren) > $availableNodes) {
                return false;
            }
            $children = $kids->getContent();

            foreach ($children as $child) {
                if (!$child instanceof PDFObject) {
                    return false;
                }
                $pending[] = [$child, $node, $depth + 1];
            }
        }

        return $pageCount > 0;
    }

    private static function hasName(PDFObject $object, string $key, string $expected): bool
    {
        $value = $object->get($key);

        return $value instanceof ElementName && $value->getContent() === $expected;
    }

    private static function findUnsafeContent(Document $document): bool
    {
        $seen = new \SplObjectStorage();
        foreach ($document->getObjects() as $object) {
            if (!$object instanceof PDFObject) {
                continue;
            }
            $header = $object->getHeader();
            if ($header instanceof Header && self::hasUnsafeHeader($header, $seen, 0)) {
                return true;
            }
            $content = $object->getContent();
            if (is_string($content) && self::containsZipPayload($content)) {
                return true;
            }
        }

        return false;
    }

    private static function hasUnsafeHeader(Header $header, \SplObjectStorage $seen, int $depth): bool
    {
        if ($depth > self::MAX_NESTING_DEPTH) {
            throw new RuntimeException('PDF object walk limit exceeded.');
        }
        foreach ($header->getElements() as $key => $value) {
            $key = Font::decodeEntities(ltrim((string) $key, '/'));
            if (in_array($key, [
                'A', 'AA', 'OpenAction', 'JavaScript', 'JS', 'Launch', 'EF', 'AF', 'FS',
                'EmbeddedFiles', 'EmbeddedFile', 'Filespec', 'XFA', 'RichMedia', 'Rendition', 'GoToR', 'GoToE',
            ], true)) {
                return true;
            }
            if ($key === 'Type' && $value instanceof ElementName
                && in_array(trim((string) $value->getContent(), '/'), ['EmbeddedFile', 'Filespec'], true)
            ) {
                return true;
            }
            if ($key === 'S' && $value instanceof ElementName
                && in_array(trim((string) $value->getContent(), '/'), [
                    'JavaScript', 'Launch', 'GoTo', 'GoToR', 'GoToE', 'URI', 'SubmitForm',
                    'ImportData', 'ResetForm', 'Hide', 'SetOCGState', 'Rendition', 'Movie', 'Sound', 'RichMediaExecute',
                ], true)
            ) {
                return true;
            }
            if (self::hasUnsafeValue($value, $seen, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    private static function hasUnsafeValue(mixed $value, \SplObjectStorage $seen, int $depth): bool
    {
        if (!is_object($value) || $seen->offsetExists($value)) {
            return false;
        }
        if ($depth > self::MAX_NESTING_DEPTH) {
            throw new RuntimeException('PDF object walk limit exceeded.');
        }
        $seen->offsetSet($value, true);

        if ($value instanceof Header) {
            return self::hasUnsafeHeader($value, $seen, $depth + 1);
        }
        if ($value instanceof PDFObject) {
            $header = $value->getHeader();
            if ($header instanceof Header && self::hasUnsafeHeader($header, $seen, $depth + 1)) {
                return true;
            }
            $content = $value->getContent();
            return is_string($content) && self::containsZipPayload($content);
        }
        if ($value instanceof ElementArray) {
            foreach ($value->getRawContent() as $child) {
                if (self::hasUnsafeValue($child, $seen, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function containsZipPayload(string $bytes): bool
    {
        foreach (["PK\x03\x04", "PK\x01\x02", "PK\x05\x06"] as $signature) {
            if (!str_contains($bytes, $signature)) {
                return false;
            }
        }

        return true;
    }
}

final class BoundedPdfFilterHelper extends FilterHelper
{
    public function decodeFilter(string $filter, string $data, int $decodeMemoryLimit = 0): string
    {
        $decoded = parent::decodeFilter($filter, $data, $decodeMemoryLimit);
        if ($decodeMemoryLimit > 0 && strlen($decoded) >= $decodeMemoryLimit) {
            throw new RuntimeException('PDF stream reached its configured decode limit.');
        }

        return $decoded;
    }
}

final class BoundedPdfRawDataParser extends RawDataParser
{
    private int $rawCallDepth = 0;

    public function __construct(Config $config)
    {
        parent::__construct([
            'ignore_filter_decoding_errors' => false,
            'ignore_missing_filter_decoders' => false,
        ], $config);
        $this->filterHelper = new BoundedPdfFilterHelper();
    }

    protected function getRawObject(string $pdfData, int $offset = 0, ?array $headerDic = null, int $depth = 0): array
    {
        if ($depth >= 128) {
            throw new RuntimeException('PDF parser recursion limit exceeded.');
        }
        if (++$this->rawCallDepth > 128) {
            --$this->rawCallDepth;
            throw new RuntimeException('PDF parser recursion limit exceeded.');
        }

        try {
            return parent::getRawObject($pdfData, $offset, $headerDic, $depth);
        } finally {
            --$this->rawCallDepth;
        }
    }
}

final class BoundedPdfParser extends Parser
{
    private int $totalDecodedBytes = 0;

    public function __construct(Config $config)
    {
        parent::__construct([
            'ignore_filter_decoding_errors' => false,
            'ignore_missing_filter_decoders' => false,
        ], $config);
        $this->rawDataParser = new BoundedPdfRawDataParser($config);
    }

    public function parseContent(string $content): Document
    {
        $this->totalDecodedBytes = 0;

        return parent::parseContent($content);
    }

    protected function parseObject(string $id, array $structure, ?Document $document)
    {
        foreach ($structure as $part) {
            if (($part[0] ?? null) !== 'stream') {
                continue;
            }
            $stream = $part[3][0] ?? $part[1] ?? '';
            $length = is_string($stream) ? strlen($stream) : 0;
            if ($length >= 32 * 1024 * 1024) {
                throw new RuntimeException('PDF stream reached its configured decode limit.');
            }
            $this->totalDecodedBytes += $length;
            if ($this->totalDecodedBytes > 64 * 1024 * 1024) {
                throw new RuntimeException('PDF total decoded data limit exceeded.');
            }
        }

        parent::parseObject($id, $structure, $document);
        if (count($this->objects) > 5000) {
            throw new RuntimeException('PDF object count limit exceeded.');
        }
    }
}
