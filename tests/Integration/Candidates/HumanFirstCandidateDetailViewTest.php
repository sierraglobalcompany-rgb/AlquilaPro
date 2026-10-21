<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use PHPUnit\Framework\TestCase;

final class HumanFirstCandidateDetailViewTest extends TestCase
{
    public function testCandidateDetailKeepsRequestSummaryVisibleAndCollapsesSecondaryWorkflows(): void
    {
        $candidate = [
            'id' => 1,
            'full_name' => 'Candidata Human First',
            'status' => 'pending_screening',
            'document_type' => 'cc',
            'document_number' => '123456789',
            'property_name' => 'Edificio Demo',
            'unit_code' => '101',
            'unit_id' => 1,
            'phone' => '3000000000',
            'email' => 'candidate@example.com',
            'birth_date' => '1990-01-01',
            'current_address' => 'Dirección demo',
            'consent_at' => '2026-10-17 00:00:00',
            'created_at' => '2026-10-17 00:00:00',
            'submitted_at' => '2026-10-17 00:10:00',
            'updated_at' => '2026-10-17 00:20:00',
            'employment_status' => 'employed',
            'employer_name' => 'Empresa Demo',
            'occupation' => 'Analista',
            'monthly_income' => '5000000',
            'employment_notes' => '',
            'occupants_count' => 2,
            'occupants_notes' => 'Dos adultos',
            'pets_declared' => 0,
            'pets_notes' => '',
            'references' => [],
        ];
        $documents = [];
        $screening = null;
        $contractContext = ['contracts' => [], 'can_create' => false];
        $canManageCandidates = true;
        $csrfToken = 'csrf-demo';
        $basePath = '';

        ob_start();
        require dirname(__DIR__, 3) . '/resources/views/candidate_detail.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('<h2>Solicitud</h2>', $html);
        self::assertStringNotContainsString('<summary>Solicitud</summary>', $html);

        preg_match_all(
            '/<details class="surface" data-candidate-section>\s*<summary>([^<]+)<\/summary>/',
            $html,
            $sections,
        );

        self::assertSame([
            'Información laboral',
            'Ocupantes y mascotas',
            'Referencias',
            'Documentos',
            'Estudio de aseguradora',
            'Contratos',
            'Administrar',
        ], $sections[1] ?? []);
        self::assertSame(7, substr_count($html, 'data-candidate-section'));
        self::assertStringNotContainsString('<details class="surface" data-candidate-section open>', $html);
        self::assertStringContainsString('Crear estudio', $html);
        self::assertStringContainsString('Regenerar enlace de continuación', $html);
    }
}
