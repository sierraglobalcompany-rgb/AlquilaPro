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
}
