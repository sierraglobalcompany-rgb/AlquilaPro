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

    public function testOverviewContextualPanelRepeatsOnlyTheStatusLabelRequiredByG2(): void
    {
        $html = $this->renderOverview();

        self::assertSame(2, substr_count($html, 'Pendiente de estudio'));
        self::assertSame(1, substr_count($html, 'La solicitud está pendiente del estudio de aseguradora.'));
    }

    public function testOverviewKeepsUnitContextOnlyInItsGlobalShell(): void
    {
        $html = $this->renderOverview();

        self::assertGreaterThanOrEqual(2, substr_count($html, 'Edificio Demo'));
        self::assertGreaterThanOrEqual(2, substr_count($html, 'CED-101'));
    }

    public function testOverviewGroupsContactAndDatesWithoutRedundantCards(): void
    {
        $html = $this->renderOverview();
        preg_match_all('/<section class="content-card candidate-card[^\"]*"/', $html, $cards);

        self::assertCount(6, $cards[0]);
        foreach (['Información principal de la postulación', 'Fechas relevantes', '17/10/2026 · 00:00', '17/10/2026 · 00:10', '17/10/2026 · 00:20'] as $fact) {
            self::assertStringContainsString($fact, $html);
        }
    }

    public function testApprovedMockupHasThreeStructuralRowsAndSixRealCards(): void
    {
        $html = $this->renderOverview();
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        foreach (['summary', 'process', 'details'] as $row) {
            self::assertSame(1, $xpath->query('//div[@data-candidate-row="' . $row . '"]')->length, $row . ' row must exist.');
        }
        self::assertSame(1, $xpath->query('//div[@data-candidate-row="summary"]//section[contains(@class, "candidate-card")]')->length);
        self::assertSame(2, $xpath->query('//div[@data-candidate-row="process"]//section[contains(@class, "candidate-card")]')->length);
        self::assertSame(3, $xpath->query('//div[@data-candidate-row="details"]//section[contains(@class, "candidate-card")]')->length);

        foreach (['Progreso de la solicitud', 'Próximos pasos', 'Detalles del candidato', 'Información de contacto', 'Información del inmueble'] as $title) {
            self::assertStringContainsString($title, $html);
        }
        self::assertSame(3, $xpath->query('//section[@aria-labelledby="candidate-next-title"]//ol/li//a')->length, 'Steps must link to real application sections only.');
        self::assertStringNotContainsString('80%', $html);
        self::assertStringNotContainsString('82 / 100', $html);
    }

    public function testApprovedProgressMilestonesReflectRealEmploymentDataAndNeverInventContractVisibility(): void
    {
        $xpath = static function (string $html): \DOMXPath {
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            return new \DOMXPath($dom);
        };

        $overview = $xpath($this->renderOverview());
        $labels = [];
        foreach ($overview->query('//ol[contains(@class,"candidate-progress-list")]/li/strong') as $heading) {
            $labels[] = trim($heading->textContent);
        }
        self::assertSame(
            ['Solicitud', 'Información laboral', 'Referencias', 'Estudio de aseguradora', 'Contrato'],
            $labels,
            'The five approved visual stages must retain their business meaning.',
        );
        self::assertSame(
            'yes',
            $overview->evaluate('string(//li[strong="Información laboral"]/@data-milestone-recorded)'),
            'A declared employment status has a factual record, even without completion claims.',
        );

        $missingWork = $xpath($this->renderOverview(['employment_status' => null]));
        self::assertSame(
            'no',
            $missingWork->evaluate('string(//li[strong="Información laboral"]/@data-milestone-recorded)'),
            'A submission date must not falsely certify work information.',
        );
        self::assertSame(
            '2026-10-17 00:00:00',
            $overview->evaluate('string(//li[strong="Solicitud"]//time/@datetime)'),
            'Progress dates must use the correct factual source, never a label-dependent guess.',
        );
    }

    public function testRecordedMilestonesNeverLookLikeCompletedSteps(): void
    {
        $asXpath = static function (string $html): \DOMXPath {
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            return new \DOMXPath($dom);
        };
        $xpath = $asXpath($this->renderOverview());
        self::assertSame(
            'yes',
            $xpath->evaluate('string(//li[strong="Solicitud"]/@data-milestone-completed)'),
            'Submitted request may show a completed request stage.',
        );
        self::assertSame(
            1,
            $xpath->query('//li[@data-milestone-completed="yes"]//span[contains(@class,"candidate-milestone-marker")][contains(.,"✓")]')->length,
        );
        foreach (['Información laboral', 'Referencias', 'Estudio de aseguradora', 'Contrato'] as $stage) {
            self::assertSame(
                'no',
                $xpath->evaluate('string(//li[strong="' . $stage . '"]/@data-milestone-completed)'),
                $stage . ' cannot be marked completed based on record presence.',
            );
        }
        self::assertSame(
            0,
            $xpath->query('//li[@data-milestone-completed="no"]//span[contains(@class,"candidate-milestone-marker")][contains(.,"✓")]')->length,
        );
        $notSubmitted = $asXpath($this->renderOverview(['submitted_at' => null]));
        self::assertSame('no', $notSubmitted->evaluate('string(//li[strong="Solicitud"]/@data-milestone-completed)'));
    }

    public function testNextStepExplanationBelongsToTheSameAccessibleLink(): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $this->renderOverview());
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//section[@aria-labelledby="candidate-next-title"]//ol/li//a');

        self::assertCount(3, $links);
        foreach ($links as $link) {
            self::assertSame(1, $xpath->query('./strong', $link)->length);
            self::assertSame(1, $xpath->query('./span', $link)->length, 'The explanation must share the real link target instead of sitting outside its touch area.');
            self::assertStringContainsString('?section=', $link->getAttribute('href'));
        }
    }

    public function testStructuralCardsRemainPresentWhenOptionalFactsAreAbsent(): void
    {
        $html = $this->renderOverview([
            'birth_date' => null, 'current_address' => '',
            'created_at' => null, 'submitted_at' => null, 'updated_at' => null,
            'phone' => null, 'email' => '',
        ]);
        self::assertSame(6, substr_count($html, 'class="content-card candidate-card'));
        self::assertStringContainsString('No hay fechas registradas para esta solicitud.', $html);
        self::assertStringContainsString('Sin datos personales adicionales', $html);
        self::assertStringContainsString('Detalles limitados por permisos', $html);
    }

    public function testCandidateBodyLeavesTheMainLandmarkToTheAppLayout(): void
    {
        self::assertDoesNotMatchRegularExpression('/<main\b/', $this->renderOverview());
    }

    public function testOverviewHasOneNamedSummaryWithContactAndContextualState(): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $this->renderOverview());
        $xpath = new \DOMXPath($dom);
        $summary = '//section[@aria-labelledby="candidate-summary-title"]';
        self::assertSame(1, $xpath->query($summary)->length, 'The summary must be a single named semantic surface.');
        self::assertSame('Resumen de la solicitud', $xpath->evaluate('string(' . $summary . '//h2)'));
        self::assertSame(1, $xpath->query($summary . '//dl[dt="Correo"]/dd[contains(., "camilo@example.com")]')->length);
        self::assertSame(1, $xpath->query($summary . '//aside[@aria-labelledby="candidate-state-title"]')->length);
        self::assertStringContainsString('La solicitud está pendiente del estudio de aseguradora.', $xpath->evaluate('string(' . $summary . '//aside)'));
        self::assertSame(0, $xpath->query('//header[@data-candidate-shell]//p[contains(., "La solicitud está pendiente")]')->length);
    }

    public function testOverviewDatesAreSemanticAndMissingFactsAreExplicit(): void
    {
        $html = $this->renderOverview();
        self::assertStringContainsString('<time datetime="2026-10-17 00:10:00">17/10/2026 · 00:10</time>', $html);
        $empty = $this->renderOverview(['phone' => null, 'email' => '', 'document_number' => null, 'created_at' => null, 'submitted_at' => null, 'updated_at' => null]);
        self::assertStringContainsString('Sin teléfono registrado', $empty);
        self::assertStringContainsString('Sin correo registrado', $empty);
        self::assertStringContainsString('Sin documento registrado', $empty);
        self::assertStringContainsString('No hay fechas registradas para esta solicitud.', $empty);
        self::assertStringNotContainsString('<time', $empty);
    }

    public function testApprovedCompositionHasTonalStateIconsAndFactualSecondaryModules(): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $this->renderOverview());
        $xpath = new \DOMXPath($dom);
        self::assertSame(1, $xpath->query('//aside[@aria-labelledby="candidate-state-title"]//div[contains(@class,"candidate-state-panel")]/h4[text()="Pendiente de estudio"]')->length);
        self::assertSame(1, $xpath->query('//section[@aria-labelledby="candidate-summary-title"]//h2/*[local-name()="svg" and @aria-hidden="true"]')->length);
        self::assertSame(16, $xpath->query('//a[contains(@href,"?section=")]/*[local-name()="svg" and @aria-hidden="true"]')->length);
        self::assertSame(1, $xpath->query('//section[@aria-labelledby="candidate-personal-title"]//dt[text()="Fecha de nacimiento"]')->length);
        self::assertSame(1, $xpath->query('//section[@aria-labelledby="candidate-dates-title"]//time[@datetime="2026-10-17 00:00:00"]')->length);
        self::assertSame(2, substr_count($dom->textContent, '3000000000'));
        self::assertSame(2, substr_count($dom->textContent, 'camilo@example.com'));
    }

    public function testOptionalPersonalCardRequiresAtLeastOneFact(): void
    {
        $empty = $this->renderOverview(['birth_date' => null, 'current_address' => '']);
        self::assertStringContainsString('aria-labelledby="candidate-personal-title"', $empty);
        self::assertStringContainsString('Sin datos personales adicionales', $empty);
        self::assertStringContainsString('aria-labelledby="candidate-personal-title"', $this->renderOverview(['birth_date' => null, 'current_address' => 'Dirección ficticia']));
    }

    public function testOverviewEscapesLongFactsAndPreservesSubdirectoryLinks(): void
    {
        $name = '<script>alert("demo")</script>' . str_repeat('Nombre largo ', 12);
        $html = $this->renderOverview(['full_name' => $name, 'email' => 'correo+largo@example.invalid'], '/sub');
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('/sub/admin/candidates/1?section=overview', $html);
        self::assertStringContainsString('/sub/admin/candidates"', $html);
    }

    private function renderOverview(array $overrides = [], string $basePath = ''): string
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
        $candidate = array_replace($candidate, $overrides);
        $documents = [];
        $screening = null;
        $contractContext = ['contracts' => [], 'can_create' => false];
        $canManageCandidates = true;
        $csrfToken = 'csrf-demo';
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
