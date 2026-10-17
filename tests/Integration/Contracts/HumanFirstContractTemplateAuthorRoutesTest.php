<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Contracts;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class HumanFirstContractTemplateAuthorRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]));
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'contracts',
            'contract_template_versions',
            'contract_templates',
            'settings',
            'users',
        ] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->directory = sys_get_temp_dir() . '/alquila_hf_template_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-16T19:30:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->removeDirectory($this->directory);
    }

    public function testTemplateAuthorUsesStructuredVariableFieldsInsteadOfJson(): void
    {
        $this->insertManager();
        $app = $this->app('/sub');
        $this->login($app, '/sub');

        $create = $this->request($app, 'POST', '/sub/admin/contract-templates', [
            'name' => 'Plantilla Human First',
            'document_type' => 'urban_housing',
        ]);
        self::assertSame(303, $create->getStatusCode());
        $templateId = (int) $this->pdo->query('SELECT id FROM contract_templates LIMIT 1')->fetchColumn();
        self::assertGreaterThan(0, $templateId);

        $detail = $this->request($app, 'GET', '/sub/admin/contract-templates/' . $templateId);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('name="variables[0][key]"', $html);
        self::assertStringContainsString('name="variables[0][label]"', $html);
        self::assertStringContainsString('name="variables[0][required]"', $html);
        self::assertStringContainsString('name="variables[49][key]"', $html);
        self::assertSame(50, substr_count($html, 'class="check-row"'));
        self::assertStringContainsString('Datos manuales del contrato', $html);
        self::assertStringContainsString('Más variables manuales', $html);
        self::assertStringNotContainsString('name="variables_json"', $html);
        self::assertStringNotContainsString('Variables manuales JSON', $html);
        self::assertStringNotContainsString('Texto UTF-8 plano con placeholders allowlisted', $html);

        $variables = array_fill(0, 50, ['key' => '', 'label' => '']);
        $variables[49] = [
            'key' => 'custom.note',
            'label' => 'Nota especial',
            'required' => '1',
        ];

        $version = $this->request($app, 'POST', '/sub/admin/contract-templates/' . $templateId, [
            'action' => 'new_version',
            'body_template' => 'Contrato {{candidate.full_name}} / {{custom.note}}',
            'variables' => $variables,
        ]);
        self::assertSame(303, $version->getStatusCode());

        $stored = $this->pdo->query(
            'SELECT variables_json FROM contract_template_versions WHERE template_id = ' . $templateId,
        )->fetchColumn();
        self::assertIsString($stored);
        self::assertSame(
            [['key' => 'custom.note', 'label' => 'Nota especial', 'required' => true]],
            json_decode($stored, true),
        );
    }

    private function app(string $basePath): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com' . $basePath,
            'base_path' => $basePath,
            'logo_directory' => $this->directory . '/branding',
            'unit_media_directory' => $this->directory . '/unit-media',
            'candidate_document_directory' => $this->directory . '/candidate-documents',
            'contract_directory' => $this->directory . '/contracts',
            'pdo' => $this->pdo,
        ]);
    }

    private function insertManager(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, 0)',
        );
        $statement->execute([
            'role' => 'admin',
            'full_name' => 'Template Manager',
            'email' => 'hf-template@example.com',
            'password_hash' => password_hash('ManagerPassword123', PASSWORD_DEFAULT),
            'permissions_json' => json_encode(['contracts.view', 'contracts.manage'], JSON_THROW_ON_ERROR),
        ]);
    }

    private function login(App $app, string $basePath): void
    {
        $response = $this->request($app, 'POST', $basePath . '/login', [
            'email' => 'hf-template@example.com',
            'password' => 'ManagerPassword123',
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame($basePath . '/', $response->getHeaderLine('Location'));
    }

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        bool $withCsrf = true,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );
        if (strtoupper($method) === 'POST' && $withCsrf) {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }

        return $app->handle($request);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $items = scandir($directory);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
