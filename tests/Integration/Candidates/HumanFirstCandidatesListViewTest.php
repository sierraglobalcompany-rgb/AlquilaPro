<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use PHPUnit\Framework\TestCase;

final class HumanFirstCandidatesListViewTest extends TestCase
{
    public function testCandidateCardsPrioritizeIdentityStatusAndLocationWithOnePrimaryAction(): void
    {
        $html = $this->renderList([
            [
                'id' => 11,
                'full_name' => 'Ana Demo',
                'status' => 'pending_screening',
                'document_type' => 'cc',
                'document_number' => 'DOC-DEMO-001',
                'property_name' => 'Torre Norte',
                'unit_code' => 'N-202',
                'unit_id' => 202,
                'phone' => '3000000011',
                'email' => 'ana@example.test',
                'created_at' => '2026-10-20 10:00:00',
                'submitted_at' => '2026-10-21 11:00:00',
            ],
            [
                'id' => 12,
                'full_name' => '<script>alert(1)</script>',
                'status' => '<img src=x onerror=alert(2)>',
                'document_type' => 'cc',
                'document_number' => 'DOC-DEMO-002',
                'property_name' => 'Residencial <img src=x onerror=alert(3)>',
                'unit_code' => 'B-303',
                'unit_id' => 303,
                'phone' => '',
                'email' => '',
                'created_at' => '2026-10-22 12:00:00',
                'submitted_at' => null,
            ],
        ], '/sub');
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        $cards = $xpath->query('//section[@aria-label="Candidatos registrados"]/article');

        self::assertSame(2, $cards->length);
        $first = $cards->item(0);
        self::assertSame('Ana Demo', trim($xpath->evaluate('string(.//div[contains(@class,"user-card__head")]/strong)', $first)));
        self::assertSame('Pendiente de estudio', trim($xpath->evaluate('string(.//span[contains(@class,"status-pill")])', $first)));
        self::assertStringContainsString('Torre Norte · N-202', $first->textContent);
        self::assertStringContainsString('3000000011', $first->textContent);
        self::assertStringContainsString('ana@example.test', $first->textContent);
        self::assertSame(1, $xpath->query('.//a', $first)->length);
        self::assertSame('Ver candidato', trim($xpath->evaluate('string(.//a)', $first)));
        self::assertSame('/sub/admin/candidates/11', $xpath->evaluate('string(.//a/@href)', $first));

        self::assertStringNotContainsString('DOC-DEMO-', $html);
        self::assertStringNotContainsString('Creado:', $html);
        self::assertStringNotContainsString('Enviado:', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('<img src=x onerror=alert(2)>', $html);
        self::assertStringNotContainsString('<img src=x onerror=alert(3)>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(2)&gt;', $html);
        self::assertSame(0, $xpath->query('//input | //select | //form | //nav')->length);
        foreach (['Buscar', 'Filtrar', 'Paginación', 'Resultados totales', 'Candidatos:'] as $unrequestedControl) {
            self::assertStringNotContainsString($unrequestedControl, $html);
        }
    }

    public function testEmptyListKeepsHumanEmptyStateWithoutInventedCounts(): void
    {
        $html = $this->renderList([]);

        self::assertStringContainsString('Aún no hay candidatos registrados.', $html);
        self::assertStringNotContainsString('0 candidatos', $html);
        self::assertStringNotContainsString('Buscar', $html);
        self::assertStringNotContainsString('Filtrar', $html);
    }

    private function renderList(array $candidates, string $basePath = ''): string
    {
        ob_start();
        require dirname(__DIR__, 3) . '/resources/views/candidates.php';

        return (string) ob_get_clean();
    }
}
