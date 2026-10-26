<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Support;

use RuntimeException;

final class PdfFixtures
{
    public static function validPdf(): string
    {
        $bytes = file_get_contents(dirname(__DIR__) . '/Fixtures/valid-conventional.pdf');
        if (!is_string($bytes)) {
            throw new RuntimeException('La fixture PDF estructural de pruebas no está disponible.');
        }
        return $bytes;
    }
}
