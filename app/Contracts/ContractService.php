<?php

declare(strict_types=1);

namespace AlquilaPro\Contracts;

use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Candidates\ScreeningService;
use AlquilaPro\Properties\PropertyService;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class ContractService
{
    private const DOCUMENT_TYPES = ['urban_housing', 'independent_parking'];
    private const ELIGIBLE_UNIT_STATUSES = ['available', 'in_process'];
    private const MAX_PDF_BYTES = 10 * 1024 * 1024;

    private const AUTOMATIC_PLACEHOLDERS = [
        'candidate.full_name',
        'candidate.document_type',
        'candidate.document_number',
        'candidate.phone',
        'candidate.email',
        'candidate.current_address',
        'property.name',
        'property.address',
        'property.city',
        'property.sector',
        'unit.code',
        'unit.type',
        'unit.location_label',
        'unit.unit_number',
        'unit.tower',
        'unit.floor',
        'contract.monthly_rent',
        'contract.administration_fee',
        'contract.start_date',
        'contract.end_date',
        'contract.duration',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly CandidateService $candidates,
        private readonly ScreeningService $screening,
        private readonly PropertyService $properties,
        private readonly string $contractDirectory,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function listTemplates(): array
    {
        $statement = $this->pdo->query('SELECT * FROM contract_templates ORDER BY name, id');
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->normalizeTemplate($row), $rows);
    }

    public function findTemplate(int $templateId): ?array
    {
        if ($templateId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT * FROM contract_templates WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $templateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeTemplate($row) : null;
    }

    public function createTemplate(int $createdByUserId, array $input): array
    {
        $this->assertUser($createdByUserId);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 190) {
            throw new RuntimeException('El nombre de la plantilla no es válido.');
        }

        $documentType = trim((string) ($input['document_type'] ?? ''));
        if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
            throw new RuntimeException('El tipo de contrato no es válido.');
        }

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO contract_templates (name, document_type, created_by_user_id) '
                . 'VALUES (:name, :document_type, :created_by_user_id)',
            );
            $statement->execute([
                'name' => $name,
                'document_type' => $documentType,
                'created_by_user_id' => $createdByUserId,
            ]);
        } catch (PDOException) {
            throw new RuntimeException('No fue posible crear la plantilla contractual.');
        }

        $created = $this->findTemplate((int) $this->pdo->lastInsertId());
        if ($created === null) {
            throw new RuntimeException('No fue posible crear la plantilla contractual.');
        }

        return $created;
    }

    public function setTemplateActive(int $templateId, bool $active): array
    {
        if ($this->findTemplate($templateId) === null) {
            throw new RuntimeException('La plantilla contractual no existe.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE contract_templates SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
        );
        $statement->execute([
            'is_active' => $active ? 1 : 0,
            'id' => $templateId,
        ]);

        $updated = $this->findTemplate($templateId);
        if ($updated === null) {
            throw new RuntimeException('No fue posible actualizar la plantilla contractual.');
        }

        return $updated;
    }

    /** @return list<array<string,mixed>> */
    public function listTemplateVersions(int $templateId): array
    {
        if ($templateId <= 0) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM contract_template_versions WHERE template_id = :template_id ORDER BY version_number, id',
        );
        $statement->execute(['template_id' => $templateId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->normalizeVersion($row), $rows);
    }

    public function findTemplateVersion(int $versionId): ?array
    {
        if ($versionId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT * FROM contract_template_versions WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $versionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeVersion($row) : null;
    }

    public function createTemplateVersion(int $templateId, int $createdByUserId, array $input): array
    {
        if ($templateId <= 0) {
            throw new RuntimeException('La plantilla contractual no existe.');
        }
        $this->assertUser($createdByUserId);

        $body = $this->validatedBody($input['body_template'] ?? null);
        $variables = $this->validatedVariables($input['variables'] ?? []);
        $this->assertPlaceholders($body, $variables);
        $variablesJson = json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        try {
            $this->pdo->beginTransaction();

            $template = $this->pdo->prepare('SELECT id FROM contract_templates WHERE id = :id FOR UPDATE');
            $template->execute(['id' => $templateId]);
            if ($template->fetchColumn() === false) {
                throw new RuntimeException('La plantilla contractual no existe.');
            }

            $number = $this->pdo->prepare(
                'SELECT COALESCE(MAX(version_number), 0) + 1 FROM contract_template_versions WHERE template_id = :template_id',
            );
            $number->execute(['template_id' => $templateId]);
            $versionNumber = (int) $number->fetchColumn();

            $insert = $this->pdo->prepare(
                'INSERT INTO contract_template_versions '
                . '(template_id, version_number, body_template, variables_json, created_by_user_id) '
                . 'VALUES (:template_id, :version_number, :body_template, :variables_json, :created_by_user_id)',
            );
            $insert->execute([
                'template_id' => $templateId,
                'version_number' => $versionNumber,
                'body_template' => $body,
                'variables_json' => $variablesJson,
                'created_by_user_id' => $createdByUserId,
            ]);
            $versionId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (RuntimeException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('No fue posible crear la versión contractual.');
        }

        $created = $this->findTemplateVersion($versionId);
        if ($created === null) {
            throw new RuntimeException('No fue posible crear la versión contractual.');
        }

        return $created;
    }

    /** @return list<array<string,mixed>> */
    public function listContracts(): array
    {
        $rows = $this->pdo->query($this->contractSelect() . ' ORDER BY c.id DESC')->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->normalizeContract($row), $rows);
    }

    /** @return list<array<string,mixed>> */
    public function listForCandidate(int $candidateId): array
    {
        if ($candidateId <= 0) {
            return [];
        }

        $statement = $this->pdo->prepare($this->contractSelect() . ' WHERE c.candidate_id = :candidate_id ORDER BY c.id DESC');
        $statement->execute(['candidate_id' => $candidateId]);

        return array_map(
            fn (array $row): array => $this->normalizeContract($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    public function canCreateDraftForCandidate(int $candidateId): bool
    {
        if ($candidateId <= 0) {
            return false;
        }

        $candidate = $this->candidates->findCandidate($candidateId);
        if ($candidate === null || $candidate['status'] !== 'pending_screening') {
            return false;
        }

        $screening = $this->screening->findForCandidate($candidateId);
        if ($screening === null || $screening['status'] !== 'approved') {
            return false;
        }

        $unit = $this->properties->findUnit((int) $candidate['unit_id']);
        if ($unit === null || !in_array($unit['status'], self::ELIGIBLE_UNIT_STATUSES, true)) {
            return false;
        }

        $property = $this->properties->findProperty((int) $unit['property_id']);
        if ($property === null || !$property['is_active']) {
            return false;
        }

        $statement = $this->pdo->prepare(
            "SELECT 1 FROM contracts WHERE candidate_id = :candidate_id AND status IN ('draft', 'finalized') LIMIT 1",
        );
        $statement->execute(['candidate_id' => $candidateId]);

        return $statement->fetchColumn() === false;
    }

    public function findContract(int $contractId): ?array
    {
        if ($contractId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare($this->contractSelect() . ' WHERE c.id = :id LIMIT 1');
        $statement->execute(['id' => $contractId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeContract($row) : null;
    }

    public function createDraft(
        int $candidateId,
        int $templateVersionId,
        int $createdByUserId,
        array $input,
        int $now,
    ): array {
        if ($candidateId <= 0 || $templateVersionId <= 0) {
            throw new RuntimeException('Los datos del borrador contractual no son válidos.');
        }
        $this->assertUser($createdByUserId);

        try {
            $this->pdo->beginTransaction();

            $lock = $this->pdo->prepare('SELECT id FROM candidates WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $candidateId]);
            if ($lock->fetchColumn() === false) {
                throw new RuntimeException('El candidato no existe.');
            }

            [$candidate, $unit, $property] = $this->eligibleCandidateContext($candidateId);
            [$version, $template] = $this->versionContext($templateVersionId);
            if (!$template['is_active']) {
                throw new RuntimeException('La plantilla contractual está inactiva.');
            }

            $documentType = $this->documentTypeForUnit((string) $unit['type']);
            if ($template['document_type'] !== $documentType) {
                throw new RuntimeException('La plantilla no corresponde al tipo de unidad.');
            }

            $existing = $this->pdo->prepare(
                "SELECT 1 FROM contracts WHERE candidate_id = :candidate_id AND status IN ('draft', 'finalized') LIMIT 1",
            );
            $existing->execute(['candidate_id' => $candidateId]);
            if ($existing->fetchColumn() !== false) {
                throw new RuntimeException('El candidato ya tiene un contrato inicial abierto o finalizado.');
            }

            $data = $this->validatedDraftInput($input, $version);
            $insert = $this->pdo->prepare(
                'INSERT INTO contracts ('
                . 'candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, '
                . 'start_date, end_date, duration_text, manual_variables_json, tasks_json, annexes_json, '
                . 'created_by_user_id, updated_at'
                . ') VALUES ('
                . ':candidate_id, :unit_id, :template_version_id, :document_type, :status, :monthly_rent, :administration_fee, '
                . ':start_date, :end_date, :duration_text, :manual_variables_json, :tasks_json, :annexes_json, '
                . ':created_by_user_id, :updated_at'
                . ')',
            );
            $insert->execute([
                'candidate_id' => (int) $candidate['id'],
                'unit_id' => (int) $unit['id'],
                'template_version_id' => (int) $version['id'],
                'document_type' => $documentType,
                'status' => 'draft',
                'monthly_rent' => $data['monthly_rent'],
                'administration_fee' => $data['administration_fee'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'duration_text' => $data['duration_text'],
                'manual_variables_json' => $this->encodeJson($data['manual_variables']),
                'tasks_json' => $this->encodeJson($data['tasks']),
                'annexes_json' => $this->encodeJson($data['annexes']),
                'created_by_user_id' => $createdByUserId,
                'updated_at' => $this->dateTime($now),
            ]);
            $contractId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (RuntimeException $exception) {
            $this->rollbackIfNeeded();
            throw $exception;
        } catch (Throwable) {
            $this->rollbackIfNeeded();
            throw new RuntimeException('No fue posible crear el borrador contractual.');
        }

        $created = $this->findContract($contractId);
        if ($created === null) {
            throw new RuntimeException('No fue posible crear el borrador contractual.');
        }

        return $created;
    }

    public function updateDraft(int $contractId, array $input, int $now): array
    {
        $contract = $this->findContract($contractId);
        if ($contract === null) {
            throw new RuntimeException('El contrato no existe.');
        }
        if ($contract['status'] !== 'draft') {
            throw new RuntimeException('El contrato ya no admite cambios de contenido.');
        }

        $templateVersionId = array_key_exists('template_version_id', $input)
            ? $this->positiveInteger($input['template_version_id'], 'La versión contractual no es válida.')
            : (int) $contract['template_version_id'];
        [$version, $template] = $this->versionContext($templateVersionId);
        if ($template['document_type'] !== $contract['document_type']) {
            throw new RuntimeException('La versión contractual no corresponde al tipo del contrato.');
        }

        $data = $this->validatedDraftInput($input, $version);
        $statement = $this->pdo->prepare(
            'UPDATE contracts SET '
            . 'template_version_id = :template_version_id, monthly_rent = :monthly_rent, '
            . 'administration_fee = :administration_fee, start_date = :start_date, end_date = :end_date, '
            . 'duration_text = :duration_text, manual_variables_json = :manual_variables_json, '
            . 'tasks_json = :tasks_json, annexes_json = :annexes_json, updated_at = :updated_at '
            . "WHERE id = :id AND status = 'draft'",
        );
        $statement->execute([
            'template_version_id' => (int) $version['id'],
            'monthly_rent' => $data['monthly_rent'],
            'administration_fee' => $data['administration_fee'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'duration_text' => $data['duration_text'],
            'manual_variables_json' => $this->encodeJson($data['manual_variables']),
            'tasks_json' => $this->encodeJson($data['tasks']),
            'annexes_json' => $this->encodeJson($data['annexes']),
            'updated_at' => $this->dateTime($now),
            'id' => $contractId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('El contrato ya no admite cambios de contenido.');
        }

        $updated = $this->findContract($contractId);
        if ($updated === null) {
            throw new RuntimeException('No fue posible actualizar el borrador contractual.');
        }

        return $updated;
    }

    public function previewHtml(int $contractId): string
    {
        $contract = $this->findContract($contractId);
        if ($contract === null) {
            throw new RuntimeException('El contrato no existe.');
        }
        if ($contract['status'] !== 'draft') {
            throw new RuntimeException('La vista previa solo está disponible para borradores.');
        }

        [$version] = $this->versionContext((int) $contract['template_version_id']);
        [$candidate, $unit, $property] = $this->contractSourceContext($contract, false);
        $document = $this->resolvedDocument($contract, $version, $candidate, $unit, $property, true);

        return $document['html'];
    }

    public function generate(int $contractId, int $now): array
    {
        $contract = $this->findContract($contractId);
        if ($contract === null) {
            throw new RuntimeException('El contrato no existe.');
        }
        if ($contract['status'] !== 'draft') {
            throw new RuntimeException('Solo un borrador puede generar evidencia contractual.');
        }

        [$candidate, $unit, $property] = $this->contractSourceContext($contract, true);
        [$version, $template] = $this->versionContext((int) $contract['template_version_id']);
        if ($template['document_type'] !== $contract['document_type']) {
            throw new RuntimeException('La versión contractual no corresponde al contrato.');
        }
        $document = $this->resolvedDocument($contract, $version, $candidate, $unit, $property, false);
        $snapshotHtml = $document['html'];
        $resolvedJson = $this->encodeJson($document['values']);

        $renderer = new ContractPdfRenderer();
        $bytes = $renderer->render($snapshotHtml);
        $size = strlen($bytes);
        if (!str_starts_with($bytes, '%PDF-') || $size <= 0 || $size > self::MAX_PDF_BYTES) {
            throw new RuntimeException('El PDF contractual generado no es válido.');
        }
        $hash = hash('sha256', $bytes);

        $tempPath = null;
        $finalPath = null;
        try {
            $this->ensureContractDirectory();
            $storedName = bin2hex(random_bytes(16)) . '.pdf';
            $tempPath = $this->contractDirectory . DIRECTORY_SEPARATOR . '.tmp-' . bin2hex(random_bytes(16));
            $finalPath = $this->contractDirectory . DIRECTORY_SEPARATOR . $storedName;
            if (file_exists($finalPath)) {
                throw new RuntimeException('No fue posible reservar el archivo contractual.');
            }

            $written = @file_put_contents($tempPath, $bytes, LOCK_EX);
            if ($written !== $size) {
                throw new RuntimeException('No fue posible guardar el PDF contractual.');
            }
            @chmod($tempPath, 0600);
            clearstatcache(true, $tempPath);
            if (@filesize($tempPath) !== $size || @hash_file('sha256', $tempPath) !== $hash) {
                throw new RuntimeException('No fue posible verificar el PDF contractual.');
            }
            if (!@rename($tempPath, $finalPath)) {
                throw new RuntimeException('No fue posible publicar el PDF contractual.');
            }
            $tempPath = null;
            @chmod($finalPath, 0600);

            $this->pdo->beginTransaction();
            $lock = $this->pdo->prepare('SELECT status FROM contracts WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $contractId]);
            if ($lock->fetchColumn() !== 'draft') {
                throw new RuntimeException('El contrato ya no admite generación.');
            }

            $fresh = $this->findContract($contractId);
            if ($fresh === null || $fresh['status'] !== 'draft') {
                throw new RuntimeException('El contrato ya no admite generación.');
            }
            $this->contractSourceContext($fresh, true);

            $update = $this->pdo->prepare(
                "UPDATE contracts SET status = 'generated', resolved_variables_json = :resolved_variables_json, "
                . 'snapshot_html = :snapshot_html, pdf_stored_name = :pdf_stored_name, pdf_sha256 = :pdf_sha256, '
                . 'pdf_size_bytes = :pdf_size_bytes, generated_at = :generated_at, updated_at = :updated_at '
                . "WHERE id = :id AND status = 'draft'",
            );
            $update->execute([
                'resolved_variables_json' => $resolvedJson,
                'snapshot_html' => $snapshotHtml,
                'pdf_stored_name' => $storedName,
                'pdf_sha256' => $hash,
                'pdf_size_bytes' => $size,
                'generated_at' => $this->dateTime($now),
                'updated_at' => $this->dateTime($now),
                'id' => $contractId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('El contrato ya no admite generación.');
            }
            $this->pdo->commit();
        } catch (RuntimeException $exception) {
            $this->rollbackIfNeeded();
            if ($tempPath !== null) {
                @unlink($tempPath);
            }
            if ($finalPath !== null) {
                @unlink($finalPath);
            }
            throw $exception;
        } catch (Throwable) {
            $this->rollbackIfNeeded();
            if ($tempPath !== null) {
                @unlink($tempPath);
            }
            if ($finalPath !== null) {
                @unlink($finalPath);
            }
            throw new RuntimeException('No fue posible generar el contrato.');
        }

        $generated = $this->findContract($contractId);
        if ($generated === null || $generated['status'] !== 'generated') {
            if ($finalPath !== null) {
                @unlink($finalPath);
            }
            throw new RuntimeException('No fue posible generar el contrato.');
        }

        return $generated;
    }

    /** @return array{path:string,stored_name:string,size_bytes:int,sha256:string}|null */
    public function privatePdf(int $contractId): ?array
    {
        $contract = $this->findContract($contractId);
        if ($contract === null || !in_array($contract['status'], ['generated', 'finalized'], true)) {
            return null;
        }

        $name = (string) ($contract['pdf_stored_name'] ?? '');
        $expectedHash = (string) ($contract['pdf_sha256'] ?? '');
        $expectedSize = (int) ($contract['pdf_size_bytes'] ?? 0);
        if (preg_match('/^[a-f0-9]{32}\.pdf$/D', $name) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1
            || $expectedSize <= 0
            || $expectedSize > self::MAX_PDF_BYTES) {
            return null;
        }

        $directory = realpath($this->contractDirectory);
        if ($directory === false || !is_dir($directory)) {
            return null;
        }
        $path = $this->contractDirectory . DIRECTORY_SEPARATOR . $name;
        $realPath = realpath($path);
        if ($realPath === false || dirname($realPath) !== $directory || !is_file($realPath) || !is_readable($realPath)) {
            return null;
        }

        clearstatcache(true, $realPath);
        $actualSize = @filesize($realPath);
        if (!is_int($actualSize) || $actualSize !== $expectedSize || $actualSize > self::MAX_PDF_BYTES) {
            return null;
        }
        if (@file_get_contents($realPath, false, null, 0, 5) !== '%PDF-') {
            return null;
        }
        $actualHash = @hash_file('sha256', $realPath);
        if (!is_string($actualHash) || !hash_equals($expectedHash, $actualHash)) {
            return null;
        }

        return [
            'path' => $realPath,
            'stored_name' => $name,
            'size_bytes' => $actualSize,
            'sha256' => $actualHash,
        ];
    }

    private function contractSourceContext(array $contract, bool $requireEligibility): array
    {
        if ($requireEligibility) {
            [$candidate, $unit, $property] = $this->eligibleCandidateContext((int) $contract['candidate_id']);
        } else {
            $candidate = $this->candidates->findCandidate((int) $contract['candidate_id']);
            $unit = $this->properties->findUnit((int) $contract['unit_id']);
            if ($candidate === null || $unit === null) {
                throw new RuntimeException('No fue posible resolver el borrador contractual.');
            }
            $property = $this->properties->findProperty((int) $unit['property_id']);
            if ($property === null) {
                throw new RuntimeException('No fue posible resolver el borrador contractual.');
            }
        }

        if ((int) $candidate['unit_id'] !== (int) $contract['unit_id']
            || (int) $unit['id'] !== (int) $contract['unit_id']
            || $this->documentTypeForUnit((string) $unit['type']) !== $contract['document_type']) {
            throw new RuntimeException('La unidad canónica del candidato ya no corresponde al contrato.');
        }

        return [$candidate, $unit, $property];
    }

    /** @return array{values:array<string,mixed>,html:string} */
    private function resolvedDocument(
        array $contract,
        array $version,
        array $candidate,
        array $unit,
        array $property,
        bool $draft,
    ): array {
        $manual = $this->decodeObject((string) ($contract['manual_variables_json'] ?? '[]'));
        $tasks = $draft ? $this->decodeList((string) ($contract['tasks_json'] ?? '[]')) : [];
        $annexes = $this->decodeList((string) ($contract['annexes_json'] ?? '[]'));

        $values = [
            'candidate.full_name' => $candidate['full_name'] ?? '',
            'candidate.document_type' => $candidate['document_type'] ?? '',
            'candidate.document_number' => $candidate['document_number'] ?? '',
            'candidate.phone' => $candidate['phone'] ?? '',
            'candidate.email' => $candidate['email'] ?? '',
            'candidate.current_address' => $candidate['current_address'] ?? '',
            'property.name' => $property['name'] ?? '',
            'property.address' => $property['address'] ?? '',
            'property.city' => $property['city'] ?? '',
            'property.sector' => $property['sector'] ?? '',
            'unit.code' => $unit['code'] ?? '',
            'unit.type' => $unit['type'] ?? '',
            'unit.location_label' => $unit['location_label'] ?? '',
            'unit.unit_number' => $unit['unit_number'] ?? '',
            'unit.tower' => $unit['tower'] ?? '',
            'unit.floor' => $unit['floor'] ?? '',
            'contract.monthly_rent' => $contract['monthly_rent'],
            'contract.administration_fee' => $contract['administration_fee'] ?? '',
            'contract.start_date' => $contract['start_date'],
            'contract.end_date' => $contract['end_date'],
            'contract.duration' => $contract['duration_text'],
            ...$manual,
        ];
        ksort($values);

        $safeBody = $this->escape((string) $version['body_template']);
        $replacements = [];
        foreach ($values as $key => $value) {
            $replacements['{{' . $key . '}}'] = $this->escape((string) $value);
        }
        $safeBody = strtr($safeBody, $replacements);

        $html = '<article class="contract-preview">';
        if ($draft) {
            $html .= '<p><strong>BORRADOR</strong></p>';
        }
        $html .= '<div class="contract-preview__body">' . nl2br($safeBody, false) . '</div>';

        if ($tasks !== []) {
            $html .= '<section><h2>Tareas</h2><ul>';
            foreach ($tasks as $task) {
                $html .= '<li>' . $this->escape((string) ($task['label'] ?? ''))
                    . (!empty($task['done']) ? ' — completada' : ' — pendiente') . '</li>';
            }
            $html .= '</ul></section>';
        }

        if ($annexes !== []) {
            $html .= '<section><h2>Anexos</h2><ul>';
            foreach ($annexes as $annex) {
                $html .= '<li><strong>' . $this->escape((string) ($annex['title'] ?? '')) . '</strong>';
                if (($annex['note'] ?? null) !== null && trim((string) $annex['note']) !== '') {
                    $html .= '<br>' . nl2br($this->escape((string) $annex['note']), false);
                }
                $html .= '</li>';
            }
            $html .= '</ul></section>';
        }

        return ['values' => $values, 'html' => $html . '</article>'];
    }

    private function ensureContractDirectory(): void
    {
        if (file_exists($this->contractDirectory) && !is_dir($this->contractDirectory)) {
            throw new RuntimeException('El almacenamiento contractual no está disponible.');
        }
        if (!is_dir($this->contractDirectory) && !@mkdir($this->contractDirectory, 0700, true) && !is_dir($this->contractDirectory)) {
            throw new RuntimeException('El almacenamiento contractual no está disponible.');
        }
        @chmod($this->contractDirectory, 0700);
        if (!is_writable($this->contractDirectory)) {
            throw new RuntimeException('El almacenamiento contractual no está disponible.');
        }
    }

    private function eligibleCandidateContext(int $candidateId): array
    {
        $candidate = $this->candidates->findCandidate($candidateId);
        if ($candidate === null || $candidate['status'] !== 'pending_screening') {
            throw new RuntimeException('El candidato no está habilitado para contrato.');
        }

        $screening = $this->screening->findForCandidate($candidateId);
        if ($screening === null || $screening['status'] !== 'approved') {
            throw new RuntimeException('El candidato no tiene estudio aprobado.');
        }

        $unit = $this->properties->findUnit((int) $candidate['unit_id']);
        if ($unit === null || !in_array($unit['status'], self::ELIGIBLE_UNIT_STATUSES, true)) {
            throw new RuntimeException('La unidad no está habilitada para contrato.');
        }

        $property = $this->properties->findProperty((int) $unit['property_id']);
        if ($property === null || !$property['is_active']) {
            throw new RuntimeException('La propiedad no está habilitada para contrato.');
        }

        return [$candidate, $unit, $property];
    }

    private function versionContext(int $versionId): array
    {
        $version = $this->findTemplateVersion($versionId);
        if ($version === null) {
            throw new RuntimeException('La versión contractual no existe.');
        }
        $template = $this->findTemplate((int) $version['template_id']);
        if ($template === null) {
            throw new RuntimeException('La plantilla contractual no existe.');
        }

        return [$version, $template];
    }

    private function documentTypeForUnit(string $unitType): string
    {
        return match ($unitType) {
            'apartment' => 'urban_housing',
            'parking' => 'independent_parking',
            default => throw new RuntimeException('El tipo de unidad no admite contrato F7.'),
        };
    }

    private function validatedDraftInput(array $input, array $version): array
    {
        $monthlyRent = $this->decimal($input['monthly_rent'] ?? null, false, true, 'El canon mensual no es válido.');
        $administrationFee = $this->decimal(
            $input['administration_fee'] ?? null,
            true,
            false,
            'La administración no es válida.',
        );
        $startDate = $this->date($input['start_date'] ?? null, 'La fecha de inicio no es válida.');
        $endDate = $this->date($input['end_date'] ?? null, 'La fecha final no es válida.');
        if ($endDate < $startDate) {
            throw new RuntimeException('La fecha final no puede ser anterior a la fecha de inicio.');
        }

        $duration = trim((string) ($input['duration_text'] ?? ''));
        if ($duration === '' || mb_strlen($duration) > 120 || !mb_check_encoding($duration, 'UTF-8')) {
            throw new RuntimeException('La duración contractual no es válida.');
        }

        $declarations = json_decode((string) $version['variables_json'], true);
        if (!is_array($declarations)) {
            throw new RuntimeException('La versión contractual tiene variables inválidas.');
        }

        return [
            'monthly_rent' => $monthlyRent,
            'administration_fee' => $administrationFee,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration_text' => $duration,
            'manual_variables' => $this->validatedManualValues($input['manual_variables'] ?? [], $declarations),
            'tasks' => $this->validatedTasks($input['tasks'] ?? []),
            'annexes' => $this->validatedAnnexes($input['annexes'] ?? []),
        ];
    }

    private function validatedManualValues(mixed $input, array $declarations): array
    {
        if (!is_array($input)) {
            throw new RuntimeException('Las variables manuales del contrato no son válidas.');
        }

        $declared = [];
        foreach ($declarations as $declaration) {
            if (!is_array($declaration) || !isset($declaration['key'])) {
                throw new RuntimeException('La versión contractual tiene variables inválidas.');
            }
            $declared[(string) $declaration['key']] = !empty($declaration['required']);
        }

        $result = [];
        foreach ($input as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, $declared) || !is_scalar($value) || is_bool($value)) {
                throw new RuntimeException('Las variables manuales del contrato no son válidas.');
            }
            $text = trim((string) $value);
            if (!mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > 4000) {
                throw new RuntimeException('Las variables manuales del contrato no son válidas.');
            }
            $result[$key] = $text;
        }

        foreach ($declared as $key => $required) {
            if ($required && (!array_key_exists($key, $result) || $result[$key] === '')) {
                throw new RuntimeException('Falta una variable manual obligatoria.');
            }
        }

        ksort($result);

        return $result;
    }

    private function validatedTasks(mixed $input): array
    {
        if (!is_array($input) || !array_is_list($input) || count($input) > 30) {
            throw new RuntimeException('Las tareas contractuales no son válidas.');
        }

        $result = [];
        foreach ($input as $task) {
            if (!is_array($task)
                || array_diff(array_keys($task), ['label', 'done']) !== []
                || !array_key_exists('done', $task)
                || !is_bool($task['done'])) {
                throw new RuntimeException('Las tareas contractuales no son válidas.');
            }
            $label = trim((string) ($task['label'] ?? ''));
            if ($label === '' || mb_strlen($label) > 190 || !mb_check_encoding($label, 'UTF-8')) {
                throw new RuntimeException('Las tareas contractuales no son válidas.');
            }
            $result[] = ['label' => $label, 'done' => $task['done']];
        }

        return $result;
    }

    private function validatedAnnexes(mixed $input): array
    {
        if (!is_array($input) || !array_is_list($input) || count($input) > 20) {
            throw new RuntimeException('Los anexos contractuales no son válidos.');
        }

        $result = [];
        foreach ($input as $annex) {
            if (!is_array($annex) || array_diff(array_keys($annex), ['title', 'note']) !== []) {
                throw new RuntimeException('Los anexos contractuales no son válidos.');
            }
            $title = trim((string) ($annex['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 190 || !mb_check_encoding($title, 'UTF-8')) {
                throw new RuntimeException('Los anexos contractuales no son válidos.');
            }
            $note = $annex['note'] ?? null;
            if ($note !== null && !is_scalar($note)) {
                throw new RuntimeException('Los anexos contractuales no son válidos.');
            }
            $note = $note === null ? null : trim((string) $note);
            if ($note === '') {
                $note = null;
            }
            if ($note !== null && !mb_check_encoding($note, 'UTF-8')) {
                throw new RuntimeException('Los anexos contractuales no son válidos.');
            }
            $result[] = ['title' => $title, 'note' => $note];
        }

        return $result;
    }

    private function decimal(mixed $value, bool $nullable, bool $positive, string $message): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            if ($nullable) {
                return null;
            }
            throw new RuntimeException($message);
        }

        $text = trim((string) $value);
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $text) !== 1) {
            throw new RuntimeException($message);
        }
        $number = (float) $text;
        if (!is_finite($number) || $number > 9999999999.99 || ($positive ? $number <= 0 : $number < 0)) {
            throw new RuntimeException($message);
        }

        return number_format($number, 2, '.', '');
    }

    private function date(mixed $value, string $message): string
    {
        $date = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $matches) !== 1
            || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw new RuntimeException($message);
        }

        return $date;
    }

    private function positiveInteger(mixed $value, string $message): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
            throw new RuntimeException($message);
        }

        return (int) $value;
    }

    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function decodeObject(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Los datos manuales almacenados no son válidos.');
        }
        if (!is_array($decoded) || array_is_list($decoded) && $decoded !== []) {
            throw new RuntimeException('Los datos manuales almacenados no son válidos.');
        }

        return $decoded;
    }

    private function decodeList(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Los datos contractuales almacenados no son válidos.');
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new RuntimeException('Los datos contractuales almacenados no son válidos.');
        }

        return $decoded;
    }

    private function assertUser(int $userId): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('El usuario responsable no es válido.');
        }

        $statement = $this->pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        if ($statement->fetchColumn() === false) {
            throw new RuntimeException('El usuario responsable no es válido.');
        }
    }

    private function validatedBody(mixed $value): string
    {
        $body = (string) $value;
        if (trim($body) === '' || !mb_check_encoding($body, 'UTF-8') || mb_strlen($body) > 250000) {
            throw new RuntimeException('El cuerpo de la plantilla no es válido.');
        }
        if (str_contains($body, '<?')
            || preg_match('/<\s*\/?\s*[a-z!][^>]*>/i', $body) === 1
            || preg_match('#https?://#i', $body) === 1
            || preg_match('/\bjavascript\s*:/i', $body) === 1) {
            throw new RuntimeException('El cuerpo de la plantilla debe ser texto plano seguro.');
        }

        return $body;
    }

    /** @return list<array{key:string,label:string,required:bool}> */
    private function validatedVariables(mixed $value): array
    {
        if (!is_array($value) || count($value) > 50) {
            throw new RuntimeException('Las variables manuales no son válidas.');
        }

        $result = [];
        $seen = [];
        foreach ($value as $declaration) {
            if (!is_array($declaration)) {
                throw new RuntimeException('Las variables manuales no son válidas.');
            }

            $key = trim((string) ($declaration['key'] ?? ''));
            $label = trim((string) ($declaration['label'] ?? ''));
            $required = $declaration['required'] ?? null;

            if (preg_match('/^custom\.[a-z0-9_]+(?:\.[a-z0-9_]+)*$/D', $key) !== 1
                || $label === ''
                || mb_strlen($label) > 190
                || !is_bool($required)
                || isset($seen[$key])) {
                throw new RuntimeException('Las variables manuales no son válidas.');
            }

            $seen[$key] = true;
            $result[] = ['key' => $key, 'label' => $label, 'required' => $required];
        }

        usort($result, static fn (array $left, array $right): int => $left['key'] <=> $right['key']);

        return $result;
    }

    /** @param list<array{key:string,label:string,required:bool}> $variables */
    private function assertPlaceholders(string $body, array $variables): void
    {
        preg_match_all('/{{([^{}]*)}}/u', $body, $matches);
        $expressions = $matches[1] ?? [];
        $stripped = preg_replace('/{{[^{}]*}}/u', '', $body);
        if (!is_string($stripped) || str_contains($stripped, '{{') || str_contains($stripped, '}}')) {
            throw new RuntimeException('La plantilla contiene placeholders no válidos.');
        }

        $declared = array_fill_keys(array_column($variables, 'key'), true);
        $used = [];
        foreach ($expressions as $expression) {
            if (!is_string($expression)
                || preg_match('/^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/D', $expression) !== 1) {
                throw new RuntimeException('La plantilla contiene placeholders no válidos.');
            }

            $used[$expression] = true;
            if (str_starts_with($expression, 'custom.')) {
                if (!isset($declared[$expression])) {
                    throw new RuntimeException('La plantilla usa una variable manual no declarada.');
                }
                continue;
            }

            if (!in_array($expression, self::AUTOMATIC_PLACEHOLDERS, true)) {
                throw new RuntimeException('La plantilla usa un placeholder automático no permitido.');
            }
        }

        foreach ($declared as $key => $_) {
            if (!isset($used[$key])) {
                throw new RuntimeException('La plantilla declara una variable manual que no usa.');
            }
        }
    }

    private function contractSelect(): string
    {
        return 'SELECT c.*, cand.full_name AS candidate_name, u.code AS unit_code, '
            . 't.name AS template_name, v.version_number AS template_version_number '
            . 'FROM contracts c '
            . 'INNER JOIN candidates cand ON cand.id = c.candidate_id '
            . 'INNER JOIN units u ON u.id = c.unit_id '
            . 'INNER JOIN contract_template_versions v ON v.id = c.template_version_id '
            . 'INNER JOIN contract_templates t ON t.id = v.template_id';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function dateTime(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** @return array<string,mixed> */
    private function normalizeTemplate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'document_type' => (string) $row['document_type'],
            'is_active' => (bool) $row['is_active'],
            'created_by_user_id' => $row['created_by_user_id'] === null ? null : (int) $row['created_by_user_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
        ];
    }

    /** @return array<string,mixed> */
    private function normalizeVersion(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'template_id' => (int) $row['template_id'],
            'version_number' => (int) $row['version_number'],
            'body_template' => (string) $row['body_template'],
            'variables_json' => (string) $row['variables_json'],
            'created_by_user_id' => $row['created_by_user_id'] === null ? null : (int) $row['created_by_user_id'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    /** @return array<string,mixed> */
    private function normalizeContract(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'candidate_id' => (int) $row['candidate_id'],
            'unit_id' => (int) $row['unit_id'],
            'template_version_id' => (int) $row['template_version_id'],
            'document_type' => (string) $row['document_type'],
            'status' => (string) $row['status'],
            'monthly_rent' => (string) $row['monthly_rent'],
            'administration_fee' => $row['administration_fee'] === null ? null : (string) $row['administration_fee'],
            'start_date' => (string) $row['start_date'],
            'end_date' => (string) $row['end_date'],
            'duration_text' => (string) $row['duration_text'],
            'manual_variables_json' => $row['manual_variables_json'] === null ? null : (string) $row['manual_variables_json'],
            'tasks_json' => $row['tasks_json'] === null ? null : (string) $row['tasks_json'],
            'annexes_json' => $row['annexes_json'] === null ? null : (string) $row['annexes_json'],
            'resolved_variables_json' => $row['resolved_variables_json'] === null ? null : (string) $row['resolved_variables_json'],
            'snapshot_html' => $row['snapshot_html'] === null ? null : (string) $row['snapshot_html'],
            'pdf_stored_name' => $row['pdf_stored_name'] === null ? null : (string) $row['pdf_stored_name'],
            'pdf_sha256' => $row['pdf_sha256'] === null ? null : (string) $row['pdf_sha256'],
            'pdf_size_bytes' => $row['pdf_size_bytes'] === null ? null : (int) $row['pdf_size_bytes'],
            'created_by_user_id' => $row['created_by_user_id'] === null ? null : (int) $row['created_by_user_id'],
            'finalized_by_user_id' => $row['finalized_by_user_id'] === null ? null : (int) $row['finalized_by_user_id'],
            'generated_at' => $row['generated_at'] === null ? null : (string) $row['generated_at'],
            'finalized_at' => $row['finalized_at'] === null ? null : (string) $row['finalized_at'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
            'candidate_name' => isset($row['candidate_name']) ? (string) $row['candidate_name'] : null,
            'unit_code' => isset($row['unit_code']) ? (string) $row['unit_code'] : null,
            'template_name' => isset($row['template_name']) ? (string) $row['template_name'] : null,
            'template_version_number' => isset($row['template_version_number']) ? (int) $row['template_version_number'] : null,
        ];
    }
}
