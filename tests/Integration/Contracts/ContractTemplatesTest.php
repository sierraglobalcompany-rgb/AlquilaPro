<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Contracts;

use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Candidates\ScreeningService;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Properties\PropertyService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContractTemplatesTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]));
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['contracts', 'contract_template_versions', 'contract_templates', 'users'] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testTemplateCreationNormalizesAndRejectsInvalidIdentityTypeUserAndDuplicate(): void
    {
        $service = $this->service();
        $userId = $this->insertUser();

        $template = $service->createTemplate($userId, [
            'name' => '  Vivienda urbana base  ',
            'document_type' => 'urban_housing',
        ]);

        self::assertSame('Vivienda urbana base', $template['name']);
        self::assertSame('urban_housing', $template['document_type']);
        self::assertTrue($template['is_active']);
        self::assertSame($userId, $template['created_by_user_id']);
        self::assertCount(1, $service->listTemplates());
        self::assertSame($template, $service->findTemplate((int) $template['id']));

        $this->assertRuntimeFailure(fn () => $service->createTemplate($userId, [
            'name' => '   ',
            'document_type' => 'urban_housing',
        ]));
        $this->assertRuntimeFailure(fn () => $service->createTemplate($userId, [
            'name' => str_repeat('a', 191),
            'document_type' => 'urban_housing',
        ]));
        $this->assertRuntimeFailure(fn () => $service->createTemplate($userId, [
            'name' => 'Tipo inválido',
            'document_type' => 'renewal',
        ]));
        $this->assertRuntimeFailure(fn () => $service->createTemplate(0, [
            'name' => 'Usuario cero',
            'document_type' => 'urban_housing',
        ]));
        $this->assertRuntimeFailure(fn () => $service->createTemplate(999999, [
            'name' => 'Usuario inexistente',
            'document_type' => 'urban_housing',
        ]));
        $this->assertRuntimeFailure(fn () => $service->createTemplate($userId, [
            'name' => 'Vivienda urbana base',
            'document_type' => 'urban_housing',
        ]));
    }

    public function testVersionNumbersAreServerSideCanonicalAndOlderRowsStayImmutable(): void
    {
        $service = $this->service();
        $userId = $this->insertUser();
        $template = $service->createTemplate($userId, [
            'name' => 'Base versiones',
            'document_type' => 'urban_housing',
        ]);

        $v1 = $service->createTemplateVersion((int) $template['id'], $userId, [
            'version_number' => 99,
            'body_template' => "Contrato de {{candidate.full_name}}\nNota: {{custom.note}}",
            'variables' => [
                ['key' => 'custom.note', 'label' => 'Nota especial', 'required' => true],
            ],
        ]);
        self::assertSame(1, $v1['version_number']);
        self::assertSame(
            '[{"key":"custom.note","label":"Nota especial","required":true}]',
            $v1['variables_json'],
        );
        $frozenV1 = $service->findTemplateVersion((int) $v1['id']);

        $v2 = $service->createTemplateVersion((int) $template['id'], $userId, [
            'version_number' => 1,
            'body_template' => 'Contrato actualizado de {{candidate.full_name}}',
            'variables' => [],
        ]);

        self::assertSame(2, $v2['version_number']);
        self::assertSame($frozenV1, $service->findTemplateVersion((int) $v1['id']));
        self::assertSame([1, 2], array_column($service->listTemplateVersions((int) $template['id']), 'version_number'));
    }

    public function testVersionValidationRejectsUnsafeUnknownAndNoisyVariables(): void
    {
        $service = $this->service();
        $userId = $this->insertUser();
        $template = $service->createTemplate($userId, [
            'name' => 'Validación variables',
            'document_type' => 'urban_housing',
        ]);

        foreach ([
            ['body_template' => '   ', 'variables' => []],
            ['body_template' => str_repeat('x', 250001), 'variables' => []],
            ['body_template' => "Texto \xB1", 'variables' => []],
            ['body_template' => '{{candidate.unknown}}', 'variables' => []],
            ['body_template' => '{{Candidate.full_name}}', 'variables' => []],
            ['body_template' => '{{custom.missing}}', 'variables' => []],
            [
                'body_template' => '{{candidate.full_name}}',
                'variables' => [['key' => 'custom.unused', 'label' => 'No usada', 'required' => false]],
            ],
            [
                'body_template' => '{{custom.dupe}}',
                'variables' => [
                    ['key' => 'custom.dupe', 'label' => 'Uno', 'required' => true],
                    ['key' => 'custom.dupe', 'label' => 'Dos', 'required' => false],
                ],
            ],
            [
                'body_template' => '{{custom.bad}}',
                'variables' => [['key' => 'candidate.bad', 'label' => 'Mala', 'required' => true]],
            ],
            [
                'body_template' => '{{custom.bad}}',
                'variables' => [['key' => 'custom.bad', 'label' => 'Mala', 'required' => 'true']],
            ],
            ['body_template' => '<script>alert(1)</script>', 'variables' => []],
            ['body_template' => 'Consulta https://evil.example', 'variables' => []],
        ] as $input) {
            $this->assertRuntimeFailure(
                fn () => $service->createTemplateVersion((int) $template['id'], $userId, $input),
            );
        }

        $declarations = [];
        $placeholders = [];
        for ($index = 1; $index <= 51; $index++) {
            $key = 'custom.v' . $index;
            $declarations[] = ['key' => $key, 'label' => 'Variable ' . $index, 'required' => false];
            $placeholders[] = '{{' . $key . '}}';
        }
        $this->assertRuntimeFailure(fn () => $service->createTemplateVersion((int) $template['id'], $userId, [
            'body_template' => implode(' ', $placeholders),
            'variables' => $declarations,
        ]));
    }

    public function testTemplateActivationChangesOnlyTemplateAndPreservesVersionHistory(): void
    {
        $service = $this->service();
        $userId = $this->insertUser();
        $template = $service->createTemplate($userId, [
            'name' => 'Activación',
            'document_type' => 'independent_parking',
        ]);
        $version = $service->createTemplateVersion((int) $template['id'], $userId, [
            'body_template' => 'Parqueadero {{unit.code}}',
            'variables' => [],
        ]);

        $disabled = $service->setTemplateActive((int) $template['id'], false);
        self::assertFalse($disabled['is_active']);
        self::assertSame($version, $service->findTemplateVersion((int) $version['id']));

        $enabled = $service->setTemplateActive((int) $template['id'], true);
        self::assertTrue($enabled['is_active']);
        self::assertCount(1, $service->listTemplateVersions((int) $template['id']));
        $this->assertRuntimeFailure(fn () => $service->setTemplateActive(999999, false));
    }

    private function service(): ContractService
    {
        self::assertTrue(class_exists(ContractService::class), 'ContractService must exist for F7.2 GREEN.');
        $candidates = new CandidateService($this->pdo);

        return new ContractService(
            $this->pdo,
            $candidates,
            new ScreeningService($this->pdo, $candidates),
            new PropertyService($this->pdo),
            sys_get_temp_dir() . '/alquila-contracts-test',
        );
    }

    private function insertUser(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (\'owner\', \'Contract Owner\', :email, :password_hash, 1, NULL, 0)',
        );
        $statement->execute([
            'email' => 'contracts-' . bin2hex(random_bytes(5)) . '@example.com',
            'password_hash' => password_hash('OwnerPassword123', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function assertRuntimeFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected RuntimeException.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }
}
