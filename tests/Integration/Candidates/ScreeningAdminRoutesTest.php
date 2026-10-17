<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Candidates\ScreeningService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ScreeningAdminRoutesTest extends TestCase
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
        foreach (['screening_processes', 'candidate_documents', 'candidate_references', 'unit_application_requirements', 'candidates', 'unit_media', 'units', 'properties', 'users'] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->directory = sys_get_temp_dir() . '/alquila_screening_admin_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-12T00:00:00-05:00',
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
        @rmdir($this->directory);
    }

    public function testViewerReadsScreeningButOnlyManagerGetsMutationFormWithBasePath(): void
    {
        $candidateId = $this->insertCandidate('ADMIN-1', 'pending_screening');
        $this->insertUser('viewer-screening@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'viewer-screening@example.com', 'ViewerPassword123', '/sub');

        $viewer = $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=screening');
        self::assertSame(200, $viewer->getStatusCode());
        $viewerHtml = (string) $viewer->getBody();
        self::assertStringContainsString('Estudio de aseguradora', $viewerHtml);
        self::assertStringNotContainsString('/sub/admin/candidates/' . $candidateId . '/screening', $viewerHtml);

        $_SESSION = [];
        $this->insertUser('manager-screening@example.com', 'ManagerPassword123', 'admin', ['candidates.view', 'candidates.manage']);
        $managerApp = $this->app('/sub');
        $this->login($managerApp, 'manager-screening@example.com', 'ManagerPassword123', '/sub');

        $manager = $this->request($managerApp, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=screening');
        self::assertSame(200, $manager->getStatusCode());
        $managerHtml = (string) $manager->getBody();
        self::assertStringContainsString('action="/sub/admin/candidates/' . $candidateId . '/screening"', $managerHtml);
        self::assertStringContainsString('name="provider"', $managerHtml);
        self::assertStringContainsString('value="SURA"', $managerHtml);
        self::assertStringNotContainsString('name="screening_id"', $managerHtml);
    }

    public function testScreeningPostReusesAuthManageForcedPasswordOwnerAndGlobalCsrf(): void
    {
        $candidateId = $this->insertCandidate('ADMIN-2', 'pending_screening');
        $guestApp = $this->app('/sub');
        $guest = $this->request($guestApp, 'POST', '/sub/admin/candidates/' . $candidateId . '/screening', [
            'provider' => 'SURA',
        ]);
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $this->insertUser('viewer-post@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'viewer-post@example.com', 'ViewerPassword123', '/sub');
        self::assertSame(403, $this->request($viewerApp, 'POST', '/sub/admin/candidates/' . $candidateId . '/screening', [
            'provider' => 'SURA',
        ])->getStatusCode());
        self::assertSame(0, $this->screeningCount($candidateId));

        $_SESSION = [];
        $this->insertUser('forced-screening@example.com', 'ForcedPassword123', 'admin', ['candidates.manage'], true);
        $forcedApp = $this->app('/sub');
        $login = $this->request($forcedApp, 'POST', '/sub/login', [
            'email' => 'forced-screening@example.com',
            'password' => 'ForcedPassword123',
        ]);
        self::assertSame('/sub/account/password', $login->getHeaderLine('Location'));
        $forced = $this->request($forcedApp, 'POST', '/sub/admin/candidates/' . $candidateId . '/screening', [
            'provider' => 'SURA',
        ]);
        self::assertSame(302, $forced->getStatusCode());
        self::assertSame('/sub/account/password', $forced->getHeaderLine('Location'));

        $_SESSION = [];
        $this->insertUser('manager-csrf@example.com', 'ManagerPassword123', 'admin', ['candidates.manage']);
        $managerApp = $this->app('/sub');
        $this->login($managerApp, 'manager-csrf@example.com', 'ManagerPassword123', '/sub');
        self::assertSame(403, $this->request(
            $managerApp,
            'POST',
            '/sub/admin/candidates/' . $candidateId . '/screening',
            ['provider' => 'SURA'],
            false,
        )->getStatusCode());
        self::assertSame(0, $this->screeningCount($candidateId));

        $_SESSION = [];
        $this->insertUser('owner-screening@example.com', 'OwnerPassword123', 'owner');
        $ownerApp = $this->app('/sub');
        $this->login($ownerApp, 'owner-screening@example.com', 'OwnerPassword123', '/sub');
        $owner = $this->request($ownerApp, 'POST', '/sub/admin/candidates/' . $candidateId . '/screening', [
            'provider' => 'SURA',
        ]);
        self::assertSame(303, $owner->getStatusCode());
        self::assertSame('/sub/admin/candidates/' . $candidateId, $owner->getHeaderLine('Location'));
        self::assertSame(1, $this->screeningCount($candidateId));
    }

    public function testManagerCreatesAndUpdatesSameScreeningWithoutInternalId(): void
    {
        $candidateId = $this->insertCandidate('ADMIN-3', 'pending_screening');
        $managerId = $this->insertUser(
            'manager-flow@example.com',
            'ManagerPassword123',
            'admin',
            ['candidates.view', 'candidates.manage'],
        );
        $app = $this->app('/sub');
        $this->login($app, 'manager-flow@example.com', 'ManagerPassword123', '/sub');

        $create = $this->request($app, 'POST', '/sub/admin/candidates/' . $candidateId . '/screening', [
            'provider' => '  SURA  ',
            'product' => 'Arrendamiento',
            'contact_email' => 'sura@example.com',
            'portal_url' => 'https://example.com/study/admin-3',
            'notes' => 'Privado',
            'support_reference' => 'RAD-3',
        ]);
        self::assertSame(303, $create->getStatusCode());
        self::assertSame('/sub/admin/candidates/' . $candidateId, $create->getHeaderLine('Location'));
        $created = $this->screeningRow($candidateId);
        self::assertSame('SURA', $created['provider']);
        self::assertSame('pending', $created['status']);
        self::assertSame($managerId, (int) $created['responsible_user_id']);

        $update = $this->request($app, 'POST', '/sub/admin/candidates/' . $candidateId . '/screening', [
            'provider' => 'SURA',
            'product' => 'Arrendamiento actualizado',
            'status' => 'in_progress',
            'portal_url' => 'https://example.com/study/admin-3-v2',
        ]);
        self::assertSame(303, $update->getStatusCode());
        self::assertSame(1, $this->screeningCount($candidateId));
        $updated = $this->screeningRow($candidateId);
        self::assertSame('in_progress', $updated['status']);
        self::assertSame('Arrendamiento actualizado', $updated['product']);
        self::assertSame($managerId, (int) $updated['responsible_user_id']);
    }

    public function testValidationTerminalAndWithdrawnAreFailClosedAndReadOnly(): void
    {
        $candidateId = $this->insertCandidate('ADMIN-4', 'pending_screening');
        $managerId = $this->insertUser(
            'manager-terminal@example.com',
            'ManagerPassword123',
            'admin',
            ['candidates.view', 'candidates.manage'],
        );
        $service = new ScreeningService($this->pdo, new CandidateService($this->pdo));
        $service->createForCandidate($candidateId, $managerId, ['provider' => 'SURA'], 1791813600);

        $app = $this->app();
        $this->login($app, 'manager-terminal@example.com', 'ManagerPassword123');
        $invalid = $this->request($app, 'POST', '/admin/candidates/' . $candidateId . '/screening', [
            'provider' => 'SURA',
            'status' => 'approved',
            'result' => '   ',
        ]);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame('pending', $this->screeningRow($candidateId)['status']);

        $closed = $this->request($app, 'POST', '/admin/candidates/' . $candidateId . '/screening', [
            'provider' => 'SURA',
            'status' => 'approved',
            'result' => 'Aprobado',
        ]);
        self::assertSame(303, $closed->getStatusCode());
        $terminalHtml = (string) $this->request($app, 'GET', '/admin/candidates/' . $candidateId . '?section=screening')->getBody();
        self::assertStringContainsString('Aprobado', $terminalHtml);
        self::assertStringNotContainsString('/admin/candidates/' . $candidateId . '/screening', $terminalHtml);
        self::assertSame(422, $this->request($app, 'POST', '/admin/candidates/' . $candidateId . '/screening', [
            'provider' => 'Otro',
            'status' => 'approved',
            'result' => 'Alterado',
        ])->getStatusCode());
        self::assertSame('SURA', $this->screeningRow($candidateId)['provider']);

        $withdrawnId = $this->insertCandidate('ADMIN-5', 'pending_screening');
        $service->createForCandidate($withdrawnId, $managerId, ['provider' => 'SURA'], 1791813601);
        $this->pdo->exec("UPDATE candidates SET status = 'withdrawn' WHERE id = " . $withdrawnId);
        $withdrawnHtml = (string) $this->request($app, 'GET', '/admin/candidates/' . $withdrawnId . '?section=screening')->getBody();
        self::assertStringContainsString('Estudio de aseguradora', $withdrawnHtml);
        self::assertStringContainsString('SURA', $withdrawnHtml);
        self::assertStringNotContainsString('/admin/candidates/' . $withdrawnId . '/screening', $withdrawnHtml);
        self::assertSame(422, $this->request($app, 'POST', '/admin/candidates/' . $withdrawnId . '/screening', [
            'provider' => 'SURA',
            'status' => 'in_progress',
        ])->getStatusCode());
    }

    public function testAdminScreeningEscapesStoredValues(): void
    {
        $candidateId = $this->insertCandidate('ADMIN-XSS', 'pending_screening');
        $managerId = $this->insertUser(
            'manager-xss@example.com',
            'ManagerPassword123',
            'admin',
            ['candidates.view', 'candidates.manage'],
        );
        (new ScreeningService($this->pdo, new CandidateService($this->pdo)))->createForCandidate($candidateId, $managerId, [
            'provider' => '<script>alert(1)</script>',
            'product' => '<b>Producto</b>',
            'notes' => '<img src=x onerror=alert(2)>',
            'support_reference' => '<svg onload=alert(3)>',
        ], 1791813600);

        $app = $this->app();
        $this->login($app, 'manager-xss@example.com', 'ManagerPassword123');
        $html = (string) $this->request($app, 'GET', '/admin/candidates/' . $candidateId . '?section=screening')->getBody();

        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;b&gt;Producto&lt;/b&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(2)&gt;', $html);
        self::assertStringContainsString('&lt;svg onload=alert(3)&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('<img src=x onerror=alert(2)>', $html);
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
            'pdo' => $this->pdo,
        ]);
    }

    private function request(App $app, string $method, string $path, array $body = [], bool $withCsrf = true): ResponseInterface
    {
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

    private function login(App $app, string $email, string $password, string $basePath = ''): void
    {
        self::assertSame(303, $this->request($app, 'POST', $basePath . '/login', [
            'email' => $email,
            'password' => $password,
        ])->getStatusCode());
    }

    private function insertUser(string $email, string $password, string $role, array $permissions = [], bool $mustChangePassword = false): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, :must_change_password)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => ucfirst($role) . ' Screening',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertCandidate(string $documentNumber, string $status): int
    {
        $property = $this->pdo->prepare(
            "INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, 'Calle 1', 'Medellín', 'Calasanz', 1)",
        );
        $property->execute(['name' => 'Propiedad ' . $documentNumber]);
        $propertyId = (int) $this->pdo->lastInsertId();
        $publicKey = substr(hash('sha256', $documentNumber), 0, 24);
        $unit = $this->pdo->prepare(
            "INSERT INTO units (property_id, public_key, type, code, status, is_public) "
            . "VALUES (:property_id, :public_key, 'apartment', :code, 'available', 0)",
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'code' => 'A-' . substr($publicKey, 0, 8),
        ]);
        $unitId = (int) $this->pdo->lastInsertId();
        $token = bin2hex(random_bytes(32));
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates '
            . '(unit_id, document_type, document_number, full_name, phone, email, status, continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :status, :token_hash, :expires_at, :consent_at)',
        );
        $candidate->execute([
            'unit_id' => $unitId,
            'document_type' => 'cc',
            'document_number' => $documentNumber,
            'full_name' => 'Candidato ' . $documentNumber,
            'phone' => '3001234567',
            'email' => strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $documentNumber) ?? 'candidate') . '@example.com',
            'status' => $status,
            'token_hash' => hash('sha256', $token),
            'expires_at' => '2099-01-01 00:00:00',
            'consent_at' => '2026-10-12 12:00:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function screeningCount(int $candidateId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM screening_processes WHERE candidate_id = :candidate_id');
        $statement->execute(['candidate_id' => $candidateId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string,mixed> */
    private function screeningRow(int $candidateId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM screening_processes WHERE candidate_id = :candidate_id');
        $statement->execute(['candidate_id' => $candidateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }
}
