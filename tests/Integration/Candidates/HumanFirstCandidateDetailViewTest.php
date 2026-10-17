<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use PHPUnit\Framework\TestCase;

final class HumanFirstCandidateDetailViewTest extends TestCase
{
    public function testOverviewUsesHumanFirstShellAndRendersOnlyTheActiveSection(): void
    {
        $html = $this->renderOverview();

        self::assertStringContainsString('aria-label="Secciones del candidato"', $html);
        self::assertStringContainsString('class="content-card candidate-section-menu"', $html);
        self::assertStringContainsString('<summary>Sección: Solicitud</summary>', $html);
        self::assertStringContainsString('data-candidate-shell', $html);
        self::assertStringContainsString('data-candidate-section-body="overview"', $html);
        self::assertSame(1, substr_count($html, 'data-candidate-section-body='));
        self::assertMatchesRegularExpression('/href="[^\"]*\?section=overview"[^>]*aria-current="page"/', $html);

        foreach (['Camilo Demo UAT', 'Pendiente de estudio', 'Edificio Demo', '101', '123456789', '3000000000', 'camilo@example.com'] as $fact) {
            self::assertStringContainsString($fact, $html);
        }

        foreach (['Información laboral', 'Ocupantes y mascotas', 'Referencias', 'Documentos', 'Estudio de aseguradora', 'Contratos', 'Administrar'] as $section) {
            self::assertStringNotContainsString('<h2>' . $section . '</h2>', $html);
        }
        foreach (['Editar candidato', 'Enviar mensaje', '80%', '82 / 100', 'Perfil laboral sólido'] as $inventedCopy) {
            self::assertStringNotContainsString($inventedCopy, $html);
        }
    }

    public function testOverviewKeepsStatusOnlyInItsGlobalShell(): void
    {
        $html = $this->renderOverview();

        self::assertSame(1, substr_count($html, 'Pendiente de estudio'));
        self::assertSame(1, substr_count($html, 'La solicitud está pendiente del estudio de aseguradora.'));
    }

    public function testOverviewKeepsUnitContextOnlyInItsGlobalShell(): void
    {
        $html = $this->renderOverview();

        self::assertSame(1, substr_count($html, 'Edificio Demo'));
        self::assertSame(1, substr_count($html, 'CED-101'));
    }

    public function testOverviewGroupsContactAndDatesWithoutRedundantCards(): void
    {
        $html = $this->renderOverview();
        preg_match_all('/<section class="content-card candidate-card[^\"]*"/', $html, $cards);

        self::assertCount(2, $cards[0]);
        foreach (['Identidad y contacto', 'Fechas relevantes', '17/10/2026 · 00:00', '17/10/2026 · 00:10', '17/10/2026 · 00:20'] as $fact) {
            self::assertStringContainsString($fact, $html);
        }
    }

    public function testCandidateBodyLeavesTheMainLandmarkToTheAppLayout(): void
    {
        self::assertDoesNotMatchRegularExpression('/<main\b/', $this->renderOverview());
    }

    private function renderOverview(): string
    {
        $candidate = [
            'id' => 1,
            'full_name' => 'Camilo Demo UAT',
            'status' => 'pending_screening',
            'document_type' => 'cc',
            'document_number' => '123456789',
            'property_name' => 'Edificio Demo',
            'unit_code' => 'CED-101',
            'unit_id' => 1,
            'phone' => '3000000000',
            'email' => 'camilo@example.com',
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
        $activeSection = 'overview';
        $candidateSections = [
            ['key' => 'overview', 'label' => 'Solicitud'],
            ['key' => 'work', 'label' => 'Información laboral'],
            ['key' => 'household', 'label' => 'Ocupantes y mascotas'],
            ['key' => 'references', 'label' => 'Referencias'],
            ['key' => 'documents', 'label' => 'Documentos'],
            ['key' => 'screening', 'label' => 'Estudio de aseguradora'],
            ['key' => 'contracts', 'label' => 'Contratos'],
            ['key' => 'manage', 'label' => 'Administrar'],
        ];

        ob_start();
        require dirname(__DIR__, 3) . '/resources/views/candidate_detail.php';
        return (string) ob_get_clean();
    }
}
