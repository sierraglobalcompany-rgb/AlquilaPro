<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\UI;

use PHPUnit\Framework\TestCase;

final class HumanLabelsContractTest extends TestCase
{
    public function testHomeShowsHumanRoleLabelInsteadOfInternalCode(): void
    {
        $body = $this->renderView('home', [
            'user' => [
                'full_name' => 'Ana Operadora',
                'role' => 'owner',
            ],
            'quickActions' => [],
        ]);

        self::assertStringContainsString('<p class="role-pill">Propietario</p>', $body);
        self::assertStringNotContainsString('<p class="role-pill">owner</p>', $body);
    }

    public function testCandidateListShowsHumanStatusLabelInsteadOfInternalCode(): void
    {
        $body = $this->renderView('candidates', [
            'basePath' => '',
            'candidates' => [[
                'id' => 7,
                'full_name' => 'Laura Candidata',
                'status' => 'pending_screening',
                'document_type' => 'cc',
                'document_number' => '123456789',
                'property_name' => 'Edificio Centro',
                'unit_code' => 'A-301',
                'unit_id' => 9,
                'phone' => '3001234567',
                'created_at' => '2026-10-16 10:00:00',
                'submitted_at' => '2026-10-16 11:00:00',
            ]],
        ]);

        self::assertStringContainsString('<span class="status-pill">Pendiente de estudio</span>', $body);
        self::assertStringNotContainsString('<span class="status-pill">pending_screening</span>', $body);
    }

    public function testCandidateDetailUsesHumanLabelsWhileKeepingCanonicalTransitionValues(): void
    {
        $body = $this->renderView('candidate_detail', $this->candidateDetailVariables('new'));

        self::assertStringContainsString('<dt>Estado</dt><dd>Nueva</dd>', $body);
        self::assertStringContainsString('<option value="incomplete">Incompleta</option>', $body);
        self::assertStringContainsString('<option value="withdrawn">Retirada</option>', $body);
        self::assertStringNotContainsString('<option value="incomplete">incomplete</option>', $body);
        self::assertStringNotContainsString('<option value="withdrawn">withdrawn</option>', $body);
    }

    public function testScreeningUsesHumanLabelsWhileKeepingCanonicalTransitionValues(): void
    {
        $variables = $this->candidateDetailVariables('pending_screening');
        $variables['screening'] = [
            'status' => 'in_progress',
            'provider' => 'SURA',
            'product' => null,
            'contact_email' => null,
            'portal_url' => null,
            'result' => null,
            'notes' => null,
            'support_reference' => null,
            'started_at' => '2026-10-16 12:00:00',
            'result_at' => null,
            'created_at' => '2026-10-16 12:00:00',
            'updated_at' => '2026-10-16 12:00:00',
        ];

        $body = $this->renderView('candidate_detail', $variables);

        self::assertStringContainsString('<dt>Estado</dt><dd>Pendiente de estudio</dd>', $body);
        self::assertStringContainsString('<option value="in_progress" selected>En estudio</option>', $body);
        self::assertStringContainsString('<option value="approved">Aprobado</option>', $body);
        self::assertStringContainsString('<option value="rejected">Rechazado</option>', $body);
        self::assertStringNotContainsString('<option value="in_progress" selected>in_progress</option>', $body);
    }

    /** @return array<string, mixed> */
    private function candidateDetailVariables(string $status): array
    {
        return [
            'basePath' => '',
            'csrfToken' => 'csrf-token',
            'candidate' => [
                'id' => 7,
                'status' => $status,
                'full_name' => 'Laura Candidata',
                'document_type' => 'cc',
                'document_number' => '123456789',
                'unit_id' => 9,
                'property_name' => 'Edificio Centro',
                'unit_code' => 'A-301',
                'phone' => '3001234567',
                'email' => 'laura@example.com',
                'birth_date' => null,
                'current_address' => null,
                'consent_at' => '2026-10-16 09:00:00',
                'created_at' => '2026-10-16 10:00:00',
                'submitted_at' => null,
                'updated_at' => '2026-10-16 10:00:00',
                'employment_status' => null,
                'employer_name' => null,
                'occupation' => null,
                'monthly_income' => null,
                'employment_notes' => null,
                'occupants_count' => null,
                'occupants_notes' => null,
                'pets_declared' => false,
                'pets_notes' => null,
                'references' => [],
            ],
            'documents' => [],
            'screening' => null,
            'contractContext' => null,
            'canManageCandidates' => true,
        ];
    }

    /** @param array<string, mixed> $variables */
    private function renderView(string $view, array $variables): string
    {
        extract($variables, EXTR_SKIP);
        ob_start();

        try {
            require dirname(__DIR__, 3) . '/resources/views/' . $view . '.php';

            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
