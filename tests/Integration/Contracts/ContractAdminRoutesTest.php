<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Contracts;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Candidates\ScreeningService;
use AlquilaPro\Contracts\ContractService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Properties\PropertyService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ContractAdminRoutesTest extends TestCase
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
        foreach (['contracts', 'contract_template_versions', 'contract_templates', 'settings', 'users'] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->directory = sys_get_temp_dir() . '/alquila_contract_admin_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-12T23:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory . '/branding');
        @rmdir($this->directory . '/unit-media');
        @rmdir($this->directory . '/candidate-documents');
        @rmdir($this->directory . '/contracts');
        @rmdir($this->directory);
    }

    public function testTemplateReadsReuseAuthViewForcedPasswordOwnerAndLauncherWithBasePath(): void
    {
        $guestApp = $this->app('/sub');
        $guest = $this->request($guestApp, 'GET', '/sub/admin/contract-templates');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $this->insertUser('blocked@example.com', 'BlockedPassword123', 'admin', []);
        $blockedApp = $this->app('/sub');
        $this->login($blockedApp, 'blocked@example.com', 'BlockedPassword123', '/sub');
        self::assertSame(403, $this->request($blockedApp, 'GET', '/sub/admin/contract-templates')->getStatusCode());

        $_SESSION = [];
        $this->insertUser('forced@example.com', 'ForcedPassword123', 'admin', ['contracts.view'], true);
        $forcedApp = $this->app('/sub');
        $this->login($forcedApp, 'forced@example.com', 'ForcedPassword123', '/sub', '/sub/account/password');
        $forced = $this->request($forcedApp, 'GET', '/sub/admin/contract-templates');
        self::assertSame(302, $forced->getStatusCode());
        self::assertSame('/sub/account/password', $forced->getHeaderLine('Location'));

        $_SESSION = [];
        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['contracts.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'viewer@example.com', 'ViewerPassword123', '/sub');
        $viewer = $this->request($viewerApp, 'GET', '/sub/admin/contract-templates');
        self::assertSame(200, $viewer->getStatusCode());
        self::assertStringContainsString('Plantillas de contrato', (string) $viewer->getBody());
        self::assertStringNotContainsString('data-contract-template-create', (string) $viewer->getBody());

        $launcher = (string) $this->request($viewerApp, 'GET', '/sub/')->getBody();
        self::assertStringContainsString('href="/sub/admin/contract-templates"', $launcher);
        self::assertStringContainsString('Contratos', $launcher);

        $_SESSION = [];
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $ownerApp = $this->app('/sub');
        $this->login($ownerApp, 'owner@example.com', 'OwnerPassword123', '/sub');
        self::assertSame(200, $this->request($ownerApp, 'GET', '/sub/admin/contract-templates')->getStatusCode());
    }

    public function testManagerCanCreateToggleAndVersionWhileViewerCannotMutate(): void
    {
        $managerId = $this->insertUser(
            'manager@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $app = $this->app('/sub');
        $this->login($app, 'manager@example.com', 'ManagerPassword123', '/sub');

        $create = $this->request($app, 'POST', '/sub/admin/contract-templates', [
            'name' => '<script>alert(1)</script>',
            'document_type' => 'urban_housing',
        ]);
        self::assertSame(303, $create->getStatusCode());
        $templateId = (int) $this->pdo->query('SELECT id FROM contract_templates LIMIT 1')->fetchColumn();
        self::assertGreaterThan(0, $templateId);
        self::assertSame('/sub/admin/contract-templates/' . $templateId, $create->getHeaderLine('Location'));

        $detail = $this->request($app, 'GET', '/sub/admin/contract-templates/' . $templateId);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('action="/sub/admin/contract-templates/' . $templateId . '"', $html);
        self::assertStringContainsString('name="body_template"', $html);

        $version = $this->request($app, 'POST', '/sub/admin/contract-templates/' . $templateId, [
            'action' => 'new_version',
            'body_template' => 'Contrato {{candidate.full_name}} / {{custom.note}}',
            'variables_json' => '[{"key":"custom.note","label":"<b>Nota</b>","required":true}]',
        ]);
        self::assertSame(303, $version->getStatusCode());
        self::assertSame(1, $this->versionCount($templateId));
        self::assertSame($managerId, (int) $this->pdo->query(
            'SELECT created_by_user_id FROM contract_template_versions WHERE template_id = ' . $templateId,
        )->fetchColumn());

        $versionHtml = (string) $this->request($app, 'GET', '/sub/admin/contract-templates/' . $templateId)->getBody();
        self::assertStringContainsString('&lt;b&gt;Nota&lt;/b&gt;', $versionHtml);
        self::assertStringNotContainsString('<b>Nota</b>', $versionHtml);

        $toggle = $this->request($app, 'POST', '/sub/admin/contract-templates/' . $templateId, [
            'action' => 'toggle',
            'active' => '0',
        ]);
        self::assertSame(303, $toggle->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query(
            'SELECT is_active FROM contract_templates WHERE id = ' . $templateId,
        )->fetchColumn());

        self::assertSame(422, $this->request($app, 'POST', '/sub/admin/contract-templates/' . $templateId, [
            'action' => 'delete',
        ])->getStatusCode());
        self::assertSame(1, $this->versionCount($templateId));

        $_SESSION = [];
        $this->insertUser('readonly@example.com', 'ReadonlyPassword123', 'admin', ['contracts.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'readonly@example.com', 'ReadonlyPassword123', '/sub');
        self::assertSame(403, $this->request($viewerApp, 'POST', '/sub/admin/contract-templates/' . $templateId, [
            'action' => 'toggle',
            'active' => '1',
        ])->getStatusCode());
    }

    public function testTemplateMutationsRequireGlobalCsrfAndMalformedVariablesFailClosed(): void
    {
        $managerId = $this->insertUser(
            'csrf@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $service = $this->service();
        $template = $service->createTemplate($managerId, [
            'name' => 'CSRF base',
            'document_type' => 'urban_housing',
        ]);
        $templateId = (int) $template['id'];

        $app = $this->app();
        $this->login($app, 'csrf@example.com', 'ManagerPassword123');
        $withoutCsrf = $this->request($app, 'POST', '/admin/contract-templates/' . $templateId, [
            'action' => 'new_version',
            'body_template' => 'Contrato {{candidate.full_name}}',
            'variables_json' => '[]',
        ], false);
        self::assertSame(403, $withoutCsrf->getStatusCode());
        self::assertSame(0, $this->versionCount($templateId));

        $malformed = $this->request($app, 'POST', '/admin/contract-templates/' . $templateId, [
            'action' => 'new_version',
            'body_template' => 'Contrato {{candidate.full_name}}',
            'variables_json' => '{not-json}',
        ]);
        self::assertSame(422, $malformed->getStatusCode());
        self::assertSame(0, $this->versionCount($templateId));
    }

    private function app(string $basePath = ''): App
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

    private function service(): ContractService
    {
        $candidates = new CandidateService($this->pdo);

        return new ContractService(
            $this->pdo,
            $candidates,
            new ScreeningService($this->pdo, $candidates),
            new PropertyService($this->pdo),
            $this->directory . '/contracts',
        );
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

    private function login(
        App $app,
        string $email,
        string $password,
        string $basePath = '',
        string $expectedLocation = '/',
    ): void {
        $response = $this->request($app, 'POST', $basePath . '/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame($expectedLocation === '/' ? $basePath . '/' : $expectedLocation, $response->getHeaderLine('Location'));
    }

    private function insertUser(
        string $email,
        string $password,
        string $role,
        array $permissions = [],
        bool $mustChangePassword = false,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, :must_change_password)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => 'Contract Route User',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function versionCount(int $templateId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM contract_template_versions WHERE template_id = :template_id',
        );
        $statement->execute(['template_id' => $templateId]);

        return (int) $statement->fetchColumn();
    }
}
