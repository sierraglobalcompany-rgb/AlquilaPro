<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Leases;

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

final class LeaseAdminRoutesTest extends TestCase
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
            'leases',
            'contracts',
            'contract_template_versions',
            'contract_templates',
            'screening_processes',
            'candidate_documents',
            'unit_application_requirements',
            'candidate_references',
            'candidates',
            'unit_media',
            'units',
            'properties',
            'settings',
            'users',
        ] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->directory = sys_get_temp_dir() . '/alquila_lease_admin_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-13T10:00:00-05:00',
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

    public function testLeaseMutationsReuseLoginManagePasswordGateAndGlobalCsrf(): void
    {
        $managerId = $this->insertUser(
            'manager@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $contractId = $this->finalizedContract($managerId, 'tenant-auth@example.com');

        $guest = $this->request($this->app('/sub'), 'POST', '/sub/admin/contracts/' . $contractId . '/lease');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $_SESSION = [];
        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['contracts.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'viewer@example.com', 'ViewerPassword123', '/sub');
        self::assertSame(
            403,
            $this->request($viewerApp, 'POST', '/sub/admin/contracts/' . $contractId . '/lease')->getStatusCode(),
        );
        self::assertSame(0, $this->leaseCount());

        $_SESSION = [];
        $this->insertUser(
            'forced@example.com',
            'ForcedPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
            true,
        );
        $forcedApp = $this->app('/sub');
        $this->login($forcedApp, 'forced@example.com', 'ForcedPassword123', '/sub', '/sub/account/password');
        $forced = $this->request($forcedApp, 'POST', '/sub/admin/contracts/' . $contractId . '/lease');
        self::assertSame(302, $forced->getStatusCode());
        self::assertSame('/sub/account/password', $forced->getHeaderLine('Location'));
        self::assertSame(0, $this->leaseCount());

        $_SESSION = [];
        $managerApp = $this->app('/sub');
        $this->login($managerApp, 'manager@example.com', 'ManagerPassword123', '/sub');
        self::assertSame(
            403,
            $this->request(
                $managerApp,
                'POST',
                '/sub/admin/contracts/' . $contractId . '/lease',
                [],
                false,
            )->getStatusCode(),
        );
        self::assertSame(0, $this->leaseCount());
    }

    public function testFinalizedContractActivationShowsCredentialOnlyInImmediatePrivateResponse(): void
    {
        $managerId = $this->insertUser(
            'activate@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $tenantEmail = 'tenant-once@example.com';
        $contractId = $this->finalizedContract($managerId, $tenantEmail);
        $app = $this->app('/sub');
        $this->login($app, 'activate@example.com', 'ManagerPassword123', '/sub');

        $before = $this->request($app, 'GET', '/sub/admin/contracts/' . $contractId);
        self::assertSame(200, $before->getStatusCode());
        $beforeHtml = (string) $before->getBody();
        self::assertStringContainsString('action="/sub/admin/contracts/' . $contractId . '/lease"', $beforeHtml);
        self::assertStringContainsString('Activar arrendamiento', $beforeHtml);

        $activated = $this->request($app, 'POST', '/sub/admin/contracts/' . $contractId . '/lease');
        self::assertSame(200, $activated->getStatusCode());
        self::assertSame('', $activated->getHeaderLine('Location'));
        self::assertSame('private, no-store', $activated->getHeaderLine('Cache-Control'));
        $html = (string) $activated->getBody();
        self::assertStringContainsString('data-f3-app-shell', $html);
        self::assertStringContainsString($tenantEmail, $html);
        self::assertMatchesRegularExpression(
            '/<code[^>]*data-temporary-password[^>]*>([^<]{12,})<\/code>/',
            $html,
        );
        preg_match('/<code[^>]*data-temporary-password[^>]*>([^<]{12,})<\/code>/', $html, $matches);
        $temporaryPassword = html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES, 'UTF-8');
        self::assertGreaterThanOrEqual(12, strlen($temporaryPassword));

        $tenant = $this->pdo->prepare('SELECT password_hash FROM users WHERE email = :email AND role = \'tenant\' LIMIT 1');
        $tenant->execute(['email' => $tenantEmail]);
        $hash = $tenant->fetchColumn();
        self::assertIsString($hash);
        self::assertTrue(password_verify($temporaryPassword, $hash));
        self::assertSame(1, $this->leaseCount());

        $revisit = $this->request($app, 'GET', '/sub/admin/contracts/' . $contractId);
        self::assertSame(200, $revisit->getStatusCode());
        $revisitHtml = (string) $revisit->getBody();
        self::assertStringContainsString($tenantEmail, $revisitHtml);
        self::assertStringNotContainsString($temporaryPassword, $revisitHtml);
        self::assertStringNotContainsString('data-temporary-password', $revisitHtml);
        self::assertStringNotContainsString('Activar arrendamiento', $revisitHtml);
        self::assertStringContainsString('Finalizar arrendamiento', $revisitHtml);
    }

    public function testEndingLeaseRedirectsToOwningContractAndKeepsBasePath(): void
    {
        $managerId = $this->insertUser(
            'end@example.com',
            'ManagerPassword123',
            'admin',
            ['contracts.view', 'contracts.manage'],
        );
        $contractId = $this->finalizedContract($managerId, 'tenant-end@example.com');
        $app = $this->app('/miapp');
        $this->login($app, 'end@example.com', 'ManagerPassword123', '/miapp');

        self::assertSame(
            200,
            $this->request($app, 'POST', '/miapp/admin/contracts/' . $contractId . '/lease')->getStatusCode(),
        );
        $leaseId = (int) $this->pdo->query('SELECT id FROM leases WHERE contract_id = ' . $contractId)->fetchColumn();
        self::assertGreaterThan(0, $leaseId);

        $ended = $this->request($app, 'POST', '/miapp/admin/leases/' . $leaseId . '/end');
        self::assertSame(303, $ended->getStatusCode());
        self::assertSame('/miapp/admin/contracts/' . $contractId, $ended->getHeaderLine('Location'));
        self::assertNotFalse($this->pdo->query('SELECT ended_at FROM leases WHERE id = ' . $leaseId)->fetchColumn());

        $detail = (string) $this->request($app, 'GET', '/miapp/admin/contracts/' . $contractId)->getBody();
        self::assertStringContainsString('Arrendamiento finalizado', $detail);
        self::assertStringNotContainsString('Finalizar arrendamiento', $detail);
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
        self::assertSame(
            $expectedLocation === '/' ? $basePath . '/' : $expectedLocation,
            $response->getHeaderLine('Location'),
        );
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
            'full_name' => 'Lease Admin User',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function finalizedContract(int $managerId, string $tenantEmail): int
    {
        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => 'Edificio Lease',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Medellín',
            'sector' => 'La América',
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, monthly_price, administration_fee, unit_number) '
            . 'VALUES (:property_id, :public_key, \'apartment\', :code, :location_label, \'available\', :monthly_price, :administration_fee, :unit_number)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'code' => 'APT-' . bin2hex(random_bytes(3)),
            'location_label' => 'Piso 4',
            'monthly_price' => '1500000.00',
            'administration_fee' => '220000.00',
            'unit_number' => '401',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates '
            . '(unit_id, document_type, document_number, full_name, phone, email, current_address, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, \'cc\', :document_number, :full_name, :phone, :email, :current_address, \'pending_screening\', '
            . ':token_hash, :expires_at, :consent_at)',
        );
        $candidate->execute([
            'unit_id' => $unitId,
            'document_number' => strtoupper(bin2hex(random_bytes(5))),
            'full_name' => 'Tenant Route Example',
            'phone' => '3001234567',
            'email' => strtolower($tenantEmail),
            'current_address' => 'Carrera 50 # 40-20',
            'token_hash' => bin2hex(random_bytes(32)),
            'expires_at' => '2027-01-01 00:00:00',
            'consent_at' => '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type, is_active, created_by_user_id) '
            . 'VALUES (:name, \'urban_housing\', 1, :user_id)',
        );
        $template->execute([
            'name' => 'Lease Admin Template ' . bin2hex(random_bytes(3)),
            'user_id' => $managerId,
        ]);
        $templateId = (int) $this->pdo->lastInsertId();

        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions '
            . '(template_id, version_number, body_template, variables_json, created_by_user_id) '
            . 'VALUES (:template_id, 1, :body_template, :variables_json, :user_id)',
        );
        $version->execute([
            'template_id' => $templateId,
            'body_template' => 'Contrato {{candidate.full_name}}',
            'variables_json' => '[]',
            'user_id' => $managerId,
        ]);
        $versionId = (int) $this->pdo->lastInsertId();

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts '
            . '(candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, '
            . 'start_date, end_date, duration_text, finalized_by_user_id, finalized_at) '
            . 'VALUES (:candidate_id, :unit_id, :version_id, \'urban_housing\', \'finalized\', :monthly_rent, '
            . ':administration_fee, :start_date, :end_date, :duration_text, :user_id, :finalized_at)',
        );
        $contract->execute([
            'candidate_id' => $candidateId,
            'unit_id' => $unitId,
            'version_id' => $versionId,
            'monthly_rent' => '1500000.00',
            'administration_fee' => '220000.00',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'duration_text' => 'Doce meses',
            'user_id' => $managerId,
            'finalized_at' => '2026-10-13 10:05:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function leaseCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM leases')->fetchColumn();
    }
}
