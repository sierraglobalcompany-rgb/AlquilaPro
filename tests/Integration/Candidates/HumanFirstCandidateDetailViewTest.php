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

        foreach (['Progreso de la solicitud', 'Secciones para consultar', 'Detalles del candidato', 'Información de contacto', 'Información del inmueble'] as $title) {
            self::assertStringContainsString($title, $html);
        }
        $consultationList = '//section[@aria-labelledby="candidate-next-title"]//ul[contains(concat(" ", normalize-space(@class), " "), " candidate-next-list ")]';
        self::assertSame(1, $xpath->query($consultationList)->length, 'Consultation links must use a non-sequential list.');
        self::assertSame(0, $xpath->query('//section[@aria-labelledby="candidate-next-title"]//ol[contains(concat(" ", normalize-space(@class), " "), " candidate-next-list ")]')->length);
        self::assertSame(3, $xpath->query($consultationList . '/li')->length);
        $consultationLinks = $xpath->query($consultationList . '/li/a');
        self::assertSame(3, $consultationLinks->length, 'Consultation links must point to real application sections only.');
        $markers = $xpath->query($consultationList . '/li/span[contains(concat(" ", normalize-space(@class), " "), " candidate-next-marker ")]');
        self::assertSame(3, $markers->length);
        foreach ($markers as $marker) {
            self::assertSame('true', $marker->getAttribute('aria-hidden'));
            self::assertSame(1, $xpath->query('./svg', $marker)->length);
            self::assertSame('', trim($marker->textContent), 'Consultation markers must not imply sequential tasks.');
        }
        $expectedConsultations = [
            ['work', 'Información laboral', 'Consultar la información declarada'],
            ['references', 'Referencias', 'Consultar referencias registradas'],
            ['screening', 'Estudio de aseguradora', 'Consultar el estado del estudio'],
        ];
        foreach ($expectedConsultations as $index => [$section, $title, $description]) {
            $link = $consultationLinks->item($index);
            self::assertInstanceOf(\DOMElement::class, $link);
            parse_str((string) parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            self::assertSame($section, $query['section'] ?? null);
            self::assertSame($title, trim($xpath->evaluate('string(./strong)', $link)));
            self::assertSame($description, trim($xpath->evaluate('string(./span)', $link)));
        }
        self::assertStringNotContainsString('Próximos pasos', $html, 'Consultation links must not claim to be an automatic next-step workflow.');
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

    public function testContractWithoutPermissionIsUnknownRatherThanAnAbsentRecord(): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $this->renderOverview([], '', null));
        $xpath = new \DOMXPath($dom);
        $contract = '//li[strong="Contrato"]';

        self::assertSame('unknown', $xpath->evaluate('string(' . $contract . '/@data-milestone-recorded)'));
        self::assertSame('no', $xpath->evaluate('string(' . $contract . '/@data-milestone-completed)'));
        self::assertStringContainsString('Sin acceso', $xpath->evaluate('string(' . $contract . ')'));
        self::assertStringNotContainsString('Sin registro', $xpath->evaluate('string(' . $contract . ')'));
    }

    public function testNextStepExplanationBelongsToTheSameAccessibleLink(): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $this->renderOverview());
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//section[@aria-labelledby="candidate-next-title"]//ul/li//a');

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

    public function testWorkSectionShowsOnlyDeclaredFactsAndHumanMoney(): void
    {
        $html = $this->renderOverview([
            'employment_status' => 'Empleado',
            'employer_name' => 'Empresa Demo',
            'occupation' => 'Analista',
            'monthly_income' => '5200000',
            'employment_notes' => "Contrato vigente\nDeclaración del candidato",
        ], '', ['contracts' => [], 'can_create' => false], 'work');

        self::assertStringContainsString('data-candidate-section-body="work"', $html);
        self::assertSame(1, substr_count($html, 'data-candidate-section-body='));
        self::assertStringContainsString('aria-labelledby="candidate-work-title"', $html);
        self::assertStringContainsString('Empresa Demo', $html);
        self::assertStringContainsString('Analista', $html);
        self::assertStringContainsString('$ 5.200.000 COP', $html);
        self::assertStringNotContainsString('>5200000<', $html);
        self::assertStringContainsString('Observaciones laborales', $html);
        self::assertStringContainsString('Contrato vigente', $html);
        foreach (['Riesgo bajo', 'Perfil laboral sólido', 'Historial laboral', 'Otros ingresos', 'Verificación de ingresos'] as $invented) {
            self::assertStringNotContainsString($invented, $html);
        }
        self::assertStringNotContainsString('data-candidate-section-body="overview"', $html);
    }

    public function testWorkSectionKeepsIncomeAndNotesInsideOneSemanticPanel(): void
    {
        $html = $this->renderOverview([
            'employment_status' => 'Empleado',
            'employer_name' => 'Empresa Demo',
            'occupation' => 'Analista',
            'monthly_income' => '5200000',
            'employment_notes' => "Observación uno\nObservación dos",
        ], '', ['contracts' => [], 'can_create' => false], 'work');

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        $panel = '//section[@data-candidate-section-body="work"]/section[contains(concat(" ", normalize-space(@class), " "), " candidate-work-panel ")]';
        self::assertSame(1, $xpath->query($panel)->length);
        self::assertSame(1, $xpath->query($panel . '//h2[@id="candidate-work-title"][contains(., "Información laboral")]')->length);
        self::assertSame(1, $xpath->query($panel . '//div[contains(concat(" ", normalize-space(@class), " "), " candidate-work-income ")]')->length);
        self::assertSame(1, $xpath->query($panel . '//div[contains(concat(" ", normalize-space(@class), " "), " candidate-work-notes ")]')->length);
        self::assertSame(0, $xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " candidate-work-income ")]')->length);
        self::assertSame(1, substr_count($html, 'Estado laboral'));
        self::assertSame(1, substr_count($html, 'Empresa Demo'));
        self::assertSame(1, substr_count($html, 'Analista'));
        self::assertStringContainsString('candidate-work-layout--split', $html);
    }

    public function testWorkStatusOnlyUsesSinglePanelWithoutInventedDetailAndIncome(): void
    {
        $html = $this->renderOverview([
            'employment_status' => 'Independiente',
            'employer_name' => '',
            'occupation' => null,
            'monthly_income' => null,
            'employment_notes' => '',
        ], '', ['contracts' => [], 'can_create' => false], 'work');
        self::assertStringContainsString('candidate-work-panel', $html);
        self::assertStringContainsString('Independiente', $html);
        self::assertStringNotContainsString('candidate-work-income', $html);
        self::assertStringNotContainsString('candidate-work-facts', $html);
        self::assertStringNotContainsString('No se registró información laboral.', $html);
    }

    public function testWorkSectionShowsTrueEmptyStateWithoutFalseZeroOrUnknownIncome(): void
    {
        $html = $this->renderOverview([
            'employment_status' => null,
            'employer_name' => '',
            'occupation' => null,
            'monthly_income' => null,
            'employment_notes' => '',
        ], '', ['contracts' => [], 'can_create' => false], 'work');
        self::assertStringContainsString('No se registró información laboral.', $html);
        self::assertSame(0, substr_count($html, 'candidate-work-income"'));
        self::assertStringNotContainsString('Ingreso mensual', $html);
        self::assertStringNotContainsString('Observaciones laborales', $html);
        self::assertStringNotContainsString('$ 0 COP', $html);
    }

    public function testWorkSectionKeepsDeclaredZeroAndEscapesNotes(): void
    {
        $html = $this->renderOverview([
            'employment_status' => null,
            'employer_name' => null,
            'occupation' => '',
            'monthly_income' => '0',
            'employment_notes' => '<script>alert(1)</script>',
        ], '/sub', ['contracts' => [], 'can_create' => false], 'work');
        self::assertStringContainsString('$ 0 COP', $html);
        self::assertStringNotContainsString('candidate-work-summary', $html, 'Income-only submissions must not produce a blank summary card.');
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('No se registró información laboral.', $html);
        self::assertStringContainsString('/sub/admin/candidates/1?section=work', $html);
    }

    public function testWorkNotesOnlyDoesNotRenderBlankSummaryOrFakeIncome(): void
    {
        $html = $this->renderOverview([
            'employment_status' => null,
            'employer_name' => null,
            'occupation' => null,
            'monthly_income' => null,
            'employment_notes' => 'Trabajo por cuenta propia declarado.',
        ], '', ['contracts' => [], 'can_create' => false], 'work');
        self::assertStringContainsString('Trabajo por cuenta propia declarado.', $html);
        self::assertStringContainsString('Observaciones laborales', $html);
        self::assertStringNotContainsString('candidate-work-summary', $html);
        self::assertStringNotContainsString('candidate-work-income', $html);
        self::assertStringNotContainsString('No se registró información laboral.', $html);
    }

    public function testHouseholdRetainsSixRegionsForUnknownLegacyData(): void
    {
        $html = $this->renderOverview([
            'submitted_at' => null,
            'occupants_count' => null,
            'pets_declared' => 0,
            'pets_answered_at' => null,
        ], '', ['contracts' => [], 'can_create' => false], 'household');

        foreach (['summary', 'information', 'occupants', 'pets', 'rules', 'next-steps'] as $region) {
            self::assertSame(1, substr_count($html, 'data-household-region="' . $region . '"'), $region . ' region must render once.');
        }
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        self::assertSame(
            'Próximos pasos',
            trim($xpath->evaluate('string(//section[@data-household-region="next-steps"]/h3)')),
        );
        $nextStepLinks = $xpath->query('//section[@data-household-region="next-steps"]//ul/li/a');
        self::assertSame(3, $nextStepLinks->length, 'The household region must expose only its existing contextual links.');
        foreach (['work', 'references', 'documents'] as $index => $section) {
            $link = $nextStepLinks->item($index);
            self::assertInstanceOf(\DOMElement::class, $link);
            parse_str((string) parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            self::assertSame($section, $query['section'] ?? null);
        }
        self::assertSame(1, substr_count($html, 'data-household-applicant'));
        self::assertStringContainsString('Cantidad no declarada', $html);
        self::assertStringContainsString('No confirmado', $html);
        self::assertStringNotContainsString('No se declararon mascotas', $html);
        self::assertStringContainsString('0 fichas registradas', $html);
        self::assertStringNotContainsString('🐾', $html);
    }

    public function testHouseholdShowsOnlyRealDeclaredCountAndPetPresence(): void
    {
        $html = $this->renderOverview([
            'occupants_count' => 3,
            'occupants_notes' => 'Adultos y un menor declarados.',
            'pets_declared' => 1,
            'pets_answered_at' => '2026-10-20 10:00:00',
            'pets_notes' => 'Mascota declarada en la solicitud.',
        ], '', ['contracts' => [], 'can_create' => false], 'household');

        self::assertStringContainsString('data-candidate-section-body="household"', $html);
        self::assertSame(1, substr_count($html, 'data-candidate-section-body='));
        self::assertStringContainsString('class="candidate-household-grid"', $html);
        self::assertStringContainsString('Resumen del hogar', $html);
        self::assertStringContainsString('Ocupantes', $html);
        self::assertStringContainsString('Mascotas', $html);
        self::assertStringContainsString('3 ocupantes declarados', $html);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        $summaryText = $xpath->query('//section[@data-household-region="summary"]')->item(0)?->textContent ?? '';
        $informationText = $xpath->query('//section[@data-household-region="information"]')->item(0)?->textContent ?? '';
        self::assertStringContainsString('0 fichas registradas', $summaryText);
        self::assertStringNotContainsString('Mascotas declaradas', $summaryText);
        self::assertStringContainsString('Sí, se declararon mascotas', $informationText);
        self::assertStringContainsString('Adultos y un menor declarados.', $html);
        self::assertStringContainsString('Mascota declarada en la solicitud.', $html);
        self::assertSame(1, substr_count($html, 'Adultos y un menor declarados.'));
        self::assertSame(1, substr_count($html, 'Mascota declarada en la solicitud.'));
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        $rulesText = $xpath->query('//section[@data-household-region="rules"]')->item(0)?->textContent ?? '';
        $occupantsText = $xpath->query('//section[@data-household-region="occupants"]')->item(0)?->textContent ?? '';
        $petsText = $xpath->query('//section[@data-household-region="pets"]')->item(0)?->textContent ?? '';
        self::assertStringContainsString('Adultos y un menor declarados.', $rulesText);
        self::assertStringContainsString('Mascota declarada en la solicitud.', $rulesText);
        self::assertStringNotContainsString('Adultos y un menor declarados.', $occupantsText);
        self::assertStringNotContainsString('Mascota declarada en la solicitud.', $petsText);
        foreach (['Riesgo bajo', 'Perfil familiar estable', 'Vacunación', 'Política del inmueble'] as $invented) {
            self::assertStringNotContainsString($invented, $html);
        }
        self::assertStringContainsString('Agregar o editar ocupantes', $html);
        self::assertStringContainsString('Agregar o editar mascotas', $html);
        self::assertStringNotContainsString('Administrar fichas', $html);
        self::assertStringNotContainsString('data-candidate-section-body="work"', $html);
    }

    public function testHouseholdManagerActionsAreVisibleAndValidationTargetsOnlyTheMatchingForm(): void
    {
        $html = $this->renderOverview([
            'householdOccupants' => [
                ['id' => 7, 'full_name' => 'Ocupante Demo', 'relationship' => 'Hermana', 'has_photo' => false],
            ],
            'householdPets' => [
                ['id' => 9, 'name' => 'Luna Demo', 'species' => 'Gato', 'has_photo' => false],
            ],
            'householdFormError' => [
                'kind' => 'occupant',
                'record_id' => 7,
                'field' => 'full_name',
                'message' => 'El nombre del ocupante es obligatorio.',
                'old_input' => ['full_name' => '', 'relationship' => 'Relación conservada'],
            ],
        ], '', ['contracts' => [], 'can_create' => false], 'household');

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        $registeredOccupants = $xpath->query('//section[@data-household-region="summary"]//p[span[normalize-space(.)="Fichas adicionales"]]/strong');
        self::assertSame(1, $registeredOccupants->length);
        self::assertSame('1 ficha registrada', trim($registeredOccupants->item(0)?->textContent ?? ''));
        self::assertStringNotContainsString('1 registradas', trim($registeredOccupants->item(0)?->textContent ?? ''));

        foreach ([
            'occupants' => 'Agregar o editar ocupantes',
            'pets' => 'Agregar o editar mascotas',
        ] as $kind => $label) {
            $summary = $xpath->query('//section[@data-household-region="' . $kind . '"]/details/summary');
            self::assertSame(1, $summary->length);
            self::assertSame($label, trim($summary->item(0)?->textContent ?? ''));
        }

        $openManagers = $xpath->query('//section[@data-household-region="occupants" or @data-household-region="pets"]//details[@open]');
        self::assertSame(1, $openManagers->length, 'Only the manager containing the validation error should open.');
        $targetForm = $xpath->query('//form[@data-household-form-kind="occupant" and @data-household-form-id="7"]');
        self::assertSame(1, $targetForm->length);
        self::assertSame(1, $xpath->query('ancestor::details[@open]', $targetForm->item(0))->length);
        self::assertSame(0, $xpath->query('//form[@data-household-form-kind="pet" and @data-household-form-id="9"]//*[@autofocus]')->length);

        $alert = $xpath->query('.//*[@role="alert"]', $targetForm->item(0));
        self::assertSame(1, $alert->length);
        self::assertSame('El nombre del ocupante es obligatorio.', trim($alert->item(0)?->textContent ?? ''));
        $name = $xpath->query('.//*[@name="full_name"]', $targetForm->item(0))->item(0);
        self::assertInstanceOf(\DOMElement::class, $name);
        self::assertSame('true', $name->getAttribute('aria-invalid'));
        self::assertSame($alert->item(0)?->getAttribute('id'), $name->getAttribute('aria-describedby'));
        self::assertSame('', $name->getAttribute('value'));
        self::assertSame('Relación conservada', $xpath->query('.//*[@name="relationship"]/@value', $targetForm->item(0))->item(0)?->nodeValue);
    }

    public function testHouseholdSummaryCountsPetRowsIndependentOfExplicitAnswer(): void
    {
        $cases = [
            [
                'pets' => [],
                'declared' => 1,
                'answered_at' => '2026-10-20 10:00:00',
                'summary' => '0 fichas registradas',
                'response' => 'Sí, se declararon mascotas',
            ],
            [
                'pets' => [['id' => 1, 'name' => 'Nube Demo', 'species' => 'Gato']],
                'declared' => 1,
                'answered_at' => '2026-10-20 10:00:00',
                'summary' => '1 ficha registrada',
                'response' => 'Sí, se declararon mascotas',
            ],
            [
                'pets' => [
                    ['id' => 1, 'name' => 'Nube Demo', 'species' => 'Gato'],
                    ['id' => 2, 'name' => 'Sol Demo', 'species' => 'Perro'],
                    ['id' => 3, 'name' => 'Luna Demo', 'species' => 'Ave'],
                ],
                'declared' => 0,
                'answered_at' => null,
                'summary' => '3 fichas registradas',
                'response' => 'No confirmado',
            ],
            [
                'pets' => [],
                'declared' => 0,
                'answered_at' => '2026-10-20 10:00:00',
                'summary' => '0 fichas registradas',
                'response' => 'No se declararon mascotas',
            ],
        ];

        foreach ($cases as $case) {
            $html = $this->renderOverview([
                'pets_declared' => $case['declared'],
                'pets_answered_at' => $case['answered_at'],
                'householdPets' => $case['pets'],
            ], '', ['contracts' => [], 'can_create' => false], 'household');
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $summaryText = $xpath->query('//section[@data-household-region="summary"]')->item(0)?->textContent ?? '';
            $information = $xpath->query('//section[@data-household-region="information"]');
            $informationText = $information->item(0)?->textContent ?? '';

            self::assertStringContainsString($case['summary'], $summaryText);
            self::assertStringNotContainsString('No tiene mascotas', $summaryText);
            self::assertStringContainsString($case['response'], $informationText);
            $times = $xpath->query('//section[@data-household-region="information"]//time/@datetime');
            if ($case['answered_at'] === null) {
                self::assertSame(0, $times->length);
            } else {
                self::assertSame(str_replace(' ', 'T', $case['answered_at']), $times->item(0)?->nodeValue);
            }
        }
    }

    public function testPetSizeLabelsTranslateKnownValuesOnlyInDisplayAndEscapeCustomValues(): void
    {
        $cases = [
            ['id' => 1, 'name' => 'Nube Demo', 'raw' => 'small', 'label' => 'Pequeño'],
            ['id' => 2, 'name' => 'Sol Demo', 'raw' => 'medium', 'label' => 'Mediano'],
            ['id' => 3, 'name' => 'Luna Demo', 'raw' => 'large', 'label' => 'Grande'],
            ['id' => 4, 'name' => 'Mora Demo', 'raw' => 'Gigante familiar', 'label' => 'Gigante familiar'],
            ['id' => 5, 'name' => 'Brisa Demo', 'raw' => '', 'label' => null],
            ['id' => 6, 'name' => 'Pipa Demo', 'raw' => '<img src=x onerror=alert(1)>', 'label' => '<img src=x onerror=alert(1)>'],
        ];
        $pets = array_map(static fn (array $case): array => [
            'id' => $case['id'],
            'name' => $case['name'],
            'species' => 'Gato',
            'size_category' => $case['raw'],
            'has_photo' => false,
        ], $cases);
        $html = $this->renderOverview(['householdPets' => $pets], '', ['contracts' => [], 'can_create' => false], 'household');
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        $records = $xpath->query('//section[@data-household-region="pets"]/div[contains(@class,"candidate-household-records")]/article');

        self::assertSame(count($cases), $records->length);
        foreach ($cases as $index => $case) {
            $record = $records->item($index);
            $body = $xpath->query('.//div[contains(@class,"candidate-household-record__body")]', $record)->item(0);
            $bodyText = $body?->textContent ?? '';
            if ($case['label'] === null) {
                self::assertSame(1, $xpath->query('.//div[contains(@class,"candidate-household-record__body")]/p', $record)->length);
            } else {
                self::assertStringContainsString($case['label'], $bodyText);
            }
            $sizeValue = $xpath->query(
                '//section[@data-household-region="pets"]//details[contains(@class,"candidate-household-manager")]//form[contains(@class,"candidate-household-edit")][.//input[@name="record_id" and @value="' . $case['id'] . '"]]//input[@name="size_category"]/@value',
            )->item(0);
            self::assertSame($case['raw'], $sizeValue?->nodeValue, 'Editing must preserve the stored free-text value.');
        }
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }

    public function testHouseholdSeparatesPetAnswerFromTimestampAndCountsPetRecords(): void
    {
        $html = $this->renderOverview([
            'occupants_count' => 3,
            'pets_declared' => 1,
            'pets_answered_at' => '2026-10-20 10:00:00',
            'householdOccupants' => [
                ['id' => 7, 'full_name' => 'Alex Demo', 'relationship' => 'Pareja', 'age_years' => 32, 'has_photo' => false],
                ['id' => 8, 'full_name' => 'Sam Demo', 'relationship' => 'Hijo', 'age_years' => 8, 'has_photo' => false],
            ],
            'householdPets' => [
                ['id' => 9, 'name' => 'Nube Demo', 'species' => 'Perro', 'has_photo' => false],
            ],
        ], '', ['contracts' => [], 'can_create' => false], 'household');

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        $summaryText = $xpath->query('//section[@data-household-region="summary"]')->item(0)?->textContent ?? '';
        $informationText = $xpath->query('//section[@data-household-region="information"]')->item(0)?->textContent ?? '';
        $petsText = $xpath->query('//section[@data-household-region="pets"]')->item(0)?->textContent ?? '';

        self::assertStringContainsString('3, incluido el solicitante', $informationText);
        self::assertStringNotContainsString('incluyéndote', $html);
        self::assertStringContainsString('Sí, se declararon mascotas', $informationText);
        self::assertStringContainsString('2026-10-20 10:00:00', $informationText);
        self::assertStringContainsString('datetime="2026-10-20T10:00:00"', $html);
        self::assertStringContainsString('1 ficha registrada', $summaryText);
        self::assertStringContainsString('1 ficha registrada', $petsText);
    }

    public function testHouseholdUsesSingularForOneDeclaredOccupant(): void
    {
        $html = $this->renderOverview([
            'submitted_at' => '2026-10-20 10:00:00',
            'occupants_count' => 1,
            'pets_declared' => 0,
            'pets_answered_at' => '2026-10-20 10:00:00',
        ], '', ['contracts' => [], 'can_create' => false], 'household');

        self::assertStringContainsString('1 ocupante declarado', $html);
        self::assertStringNotContainsString('1 ocupantes declarados', $html);
    }

    public function testHouseholdUnknownValuesDoNotTurnIntoZeroOrNoPets(): void
    {
        $html = $this->renderOverview([
            'submitted_at' => null,
            'occupants_count' => null,
            'occupants_notes' => null,
            'pets_declared' => 0,
            'pets_notes' => '',
        ], '', ['contracts' => [], 'can_create' => false], 'household');

        self::assertSame(6, substr_count($html, 'data-household-region='));
        self::assertStringContainsString('Cantidad no declarada', $html);
        self::assertStringContainsString('No confirmado', $html);
        self::assertStringNotContainsString('0 ocupantes declarados', $html);
        self::assertStringNotContainsString('No tiene mascotas', $html);
        self::assertStringNotContainsString('Sin mascotas declaradas', $html);
        self::assertStringNotContainsString('Mascotas declaradas', $html);
        self::assertStringContainsString('candidate-household-grid', $html);
    }

    public function testHouseholdZeroOccupantsAndSubmittedPetDefaultRemainFactual(): void
    {
        $html = $this->renderOverview([
            'submitted_at' => '2026-10-20 10:00:00',
            'occupants_count' => 0,
            'occupants_notes' => '',
            'pets_declared' => 0,
            'pets_notes' => '',
        ], '', ['contracts' => [], 'can_create' => false], 'household');

        self::assertStringContainsString('0 ocupantes declarados', $html);
        self::assertStringContainsString('No confirmado', $html);
        self::assertStringNotContainsString('Cantidad no declarada', $html);
        self::assertStringNotContainsString('No tiene mascotas', $html);
        self::assertStringContainsString('No hay fichas de otras personas registradas.', $html);
        self::assertStringContainsString('No hay fichas de mascotas registradas.', $html);
    }

    public function testHouseholdEscapesUntrustedMultilineNotesAndDoesNotCreateIndividuals(): void
    {
        $html = $this->renderOverview([
            'submitted_at' => null,
            'occupants_count' => null,
            'occupants_notes' => "Persona de contacto\\n<script>alert(1)</script>",
            'pets_declared' => 0,
            'pets_notes' => '<img src=x onerror=alert(1)>',
        ], '/sub', ['contracts' => [], 'can_create' => false], 'household');

        self::assertStringContainsString('Persona de contacto', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        self::assertStringContainsString('Cantidad no declarada', $html);
        self::assertStringContainsString('No confirmado', $html);
        self::assertStringNotContainsString('Sin mascotas declaradas en la solicitud', $html);
        self::assertStringContainsString('/sub/admin/candidates/1?section=household', $html);
    }

    public function testHouseholdRendersRealThirdPartyRowsAndSourceBackedManual(): void
    {
        $html = $this->renderOverview([
            'pets_answered_at' => '2026-10-20 10:00:00',
            'pets_declared' => 1,
            'householdOccupants' => [[
                'id' => 7, 'full_name' => 'Acompañante Real', 'relationship' => 'Hija', 'age_years' => 8,
                'occupation_or_activity' => 'Estudiante', 'notes' => 'Dato real.', 'has_photo' => false,
            ]],
            'householdPets' => [[
                'id' => 9, 'name' => 'Luna', 'species' => 'Gato', 'breed' => 'Criolla', 'age_years' => 3,
                'vaccination_declaration' => 'declared_vaccinated', 'notes' => '', 'has_photo' => false,
            ]],
            'householdManual' => [
                'authorized' => true,
                'document' => ['id' => 12, 'title' => 'Manual vigente', 'created_at' => '2026-10-20 09:00:00', 'has_file' => true],
                'module_url' => '/sub/admin/documents?scope=property&id=4',
            ],
        ], '/sub', ['contracts' => [], 'can_create' => false], 'household');

        foreach (['Acompañante Real', 'Hija', 'Dato real.', 'Luna', 'Gato', 'Criolla', 'Manual vigente', '/sub/admin/documents?scope=property&amp;id=4'] as $fact) {
            self::assertStringContainsString($fact, $html);
        }
        self::assertSame(1, substr_count($html, 'data-household-region="occupants"'));
        self::assertSame(1, substr_count($html, 'data-household-region="pets"'));
        self::assertSame(1, substr_count($html, 'data-household-applicant'));
        self::assertStringContainsString('candidate-household-record--applicant', $html);
        self::assertStringContainsString('Camilo Demo UAT', $html);
        self::assertStringNotContainsString('No hay fichas de otras personas registradas.', $html);
    }

    private function renderOverview(array $overrides = [], string $basePath = '', ?array $contracts = ['contracts' => [], 'can_create' => false], string $section = 'overview'): string
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
        $contractContext = $contracts;
        $canManageCandidates = true;
        $householdCanManage = ($overrides['householdCanManage'] ?? true) === true;
        $householdFormError = is_array($overrides['householdFormError'] ?? null) ? $overrides['householdFormError'] : null;
        $householdOccupants = is_array($overrides['householdOccupants'] ?? null) ? $overrides['householdOccupants'] : [];
        $householdPets = is_array($overrides['householdPets'] ?? null) ? $overrides['householdPets'] : [];
        $householdManual = is_array($overrides['householdManual'] ?? null) ? $overrides['householdManual'] : ['authorized' => false];
        $householdPhotoPath = $basePath . '/admin/candidates/1/household';
        $csrfToken = 'csrf-demo';
        $activeSection = $section;
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
