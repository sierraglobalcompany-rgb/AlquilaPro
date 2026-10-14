<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Insurance;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Insurance\InsuranceService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class InsuranceRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $documentDirectory;
    /** @var list<string> */
    private array $temporaryFiles = [];

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
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', str_replace('`', '``', (string) $table)));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquila_insurance_routes_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        $this->documentDirectory = $this->directory . '/insurance-documents';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-14T10:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach (glob($this->documentDirectory . '/*') ?: [] as $path) {
            @unlink($path);
        }
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
        @rmdir($this->documentDirectory);
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory . '/branding');
        @rmdir($this->directory . '/contracts');
        @rmdir($this->directory);
    }

    public function testStaffInsurancePageUsesDedicatedPermissionsAndManageCreatesPolicy(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput('SAFE-12345'), $lease['actor_id'], 1791980000);
        $viewer = $this->insertUser('insurance.view');
        $app = $this->app();

        self::assertSame(302, $this->request($app, 'GET', '/admin/insurance')->getStatusCode());
        $this->login($viewer);
        $viewResponse = $this->request($app, 'GET', '/admin/insurance');
        $body = (string) $viewResponse->getBody();
        self::assertSame(200, $viewResponse->getStatusCode());
        self::assertStringContainsString('Seguros', $body);
        self::assertStringContainsString('Aseguradora de prueba', $body);
        self::assertStringContainsString('SAFE-12345', $body);
        self::assertStringNotContainsString('stored_name', $body);
        self::assertSame(403, $this->request($app, 'POST', '/admin/insurance/policies', [])->getStatusCode());
        self::assertSame(403, $this->request($app, 'POST', '/admin/insurance/policies/' . $policy['id'] . '/notes', [
            'internal_notes' => 'No autorizado',
        ])->getStatusCode());
        self::assertSame(403, $this->request($app, 'POST', '/admin/leases/' . $lease['lease_id'] . '/insurance-policies', [
            'provider_name' => 'No autorizado',
        ])->getStatusCode());

        $manager = $this->insertUser('insurance.manage');
        $this->login($manager);
        $created = $this->request($app, 'POST', '/admin/leases/' . $lease['lease_id'] . '/insurance-policies', [
            'provider_name' => 'Aseguradora nueva',
            'product_name' => 'Protección hogar',
            'policy_number' => 'NEW-55',
            'coverage_start' => '2026-10-01',
            'coverage_end' => '2027-09-30',
            'coverage_json' => json_encode([['name' => 'Incendio', 'limit_amount' => null, 'deductible_amount' => null, 'notes' => null]], JSON_THROW_ON_ERROR),
            'internal_notes' => 'Nota staff',
        ]);
        self::assertSame(303, $created->getStatusCode());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM insurance_policies')->fetchColumn());
        $newPolicyId = (int) $this->pdo->query("SELECT id FROM insurance_policies WHERE policy_number = 'NEW-55'")->fetchColumn();
        $notesUpdated = $this->request($app, 'POST', '/admin/insurance/policies/' . $newPolicyId . '/notes', [
            'internal_notes' => 'Nota staff actualizada',
        ]);
        self::assertSame(303, $notesUpdated->getStatusCode());
        self::assertSame('Nota staff actualizada', $this->pdo->query(
            'SELECT internal_notes FROM insurance_policies WHERE id = ' . $newPolicyId,
        )->fetchColumn());
        self::assertSame(403, $this->request($app, 'GET', '/admin/insurance')->getStatusCode());
    }

    public function testPrivatePolicyDocumentDownloadRequiresViewPermissionAndHidesStorageDetails(): void
    {
        $lease = $this->createLease();
        $service = $this->service();
        $policy = $service->createPolicy($lease['lease_id'], $this->policyInput('DOC-12345'), $lease['actor_id'], 1791980000);
        $document = $service->addPolicyDocument((int) $policy['id'], 'Carátula', $this->upload("%PDF-1.4\ncontenido\n%%EOF\n"), 1791980001);
        $documentId = (string) $document['documents'][0]['document_id'];
        $viewer = $this->insertUser('insurance.view');
        $app = $this->app();

        self::assertSame(302, $this->request($app, 'GET', '/admin/insurance/policies/' . $policy['id'] . '/documents/' . $documentId)->getStatusCode());
        $this->login($viewer);
        $download = $this->request($app, 'GET', '/admin/insurance/policies/' . $policy['id'] . '/documents/' . $documentId);
        self::assertSame(200, $download->getStatusCode());
        self::assertSame('application/pdf', $download->getHeaderLine('Content-Type'));
        self::assertSame('attachment; filename="insurance-document"', $download->getHeaderLine('Content-Disposition'));
        self::assertSame('private, no-store', $download->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $download->getHeaderLine('X-Content-Type-Options'));
        self::assertStringStartsWith('%PDF-1.4', (string) $download->getBody());
        self::assertSame(404, $this->request($app, 'GET', '/admin/insurance/policies/' . $policy['id'] . '/documents/' . str_repeat('a', 32))->getStatusCode());

        $manager = $this->insertUser('insurance.manage');
        $this->login($manager);
        self::assertSame(403, $this->request($app, 'GET', '/admin/insurance/policies/' . $policy['id'] . '/documents/' . $documentId)->getStatusCode());
    }

    public function testTenantLeaseShowsOnlyOwnedMaskedSummaryWithoutClaimsOrPrivateFields(): void
    {
        $lease = $this->createLease();
        $otherLease = $this->createLease();
        $service = $this->service();
        $input = $this->policyInput('PRIVATE-12345');
        $input['internal_notes'] = 'INTERNAL-POLICY-NOTE';
        $input['coverage'][0]['notes'] = 'PRIVATE-COVERAGE-NOTE';
        $policy = $service->createPolicy($lease['lease_id'], $input, $lease['actor_id'], 1791980000);
        $claim = $service->createClaim($policy['id'], [
            'incident_on' => '2026-10-10',
            'description' => 'Reclamación privada',
            'claimed_amount' => null,
            'checklist' => [],
        ], null, $lease['actor_id'], '2026-10-14', 1791980001);
        $service->transitionClaim($claim['id'], 'filed', [
            'filed_on' => '2026-10-14',
            'radicado' => 'PRIVATE-CLAIM-RADICADO',
        ], $lease['actor_id'], '2026-10-14', 1791980002);
        $app = $this->app();
        $this->login($lease['tenant_id']);

        $response = $this->request($app, 'GET', '/tenant/leases/' . $lease['lease_id']);
        $body = (string) $response->getBody();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Aseguradora de prueba', $body);
        self::assertStringNotContainsString('PRIVATE-12345', $body);
        self::assertStringContainsString('••••2345', $body);
        self::assertStringContainsString('Responsabilidad civil', $body);
        self::assertStringNotContainsString('INTERNAL-POLICY-NOTE', $body);
        self::assertStringNotContainsString('PRIVATE-COVERAGE-NOTE', $body);
        self::assertStringNotContainsString('PRIVATE-CLAIM-RADICADO', $body);
        self::assertStringNotContainsString('Reclamación privada', $body);
        self::assertSame(404, $this->request($app, 'GET', '/tenant/leases/' . $otherLease['lease_id'])->getStatusCode());
        self::assertSame(403, $this->request($app, 'GET', '/admin/insurance')->getStatusCode());
        self::assertSame(404, $this->request($app, 'GET', '/tenant/insurance')->getStatusCode());
    }

    private function app(): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.example',
            'pdo' => $this->pdo,
            'logo_directory' => $this->directory . '/branding',
            'contract_directory' => $this->directory . '/contracts',
            'insurance_document_directory' => $this->documentDirectory,
            'today' => static fn (): string => '2026-10-14',
        ]);
    }

    private function request(App $app, string $method, string $path, array $body = [], array $files = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://alquilapro.example' . $path);
        if (strtoupper($method) === 'POST') {
            $body['csrf_token'] = (new Csrf())->token();
            $request = $request->withParsedBody($body);
        }
        if ($files !== []) {
            $request = $request->withUploadedFiles($files);
        }
        return $app->handle($request);
    }

    private function upload(string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_insurance_route_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create insurance upload fixture.');
        }
        $this->temporaryFiles[] = $path;
        return new UploadedFile($path, 'policy.pdf', 'application/pdf', strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function login(int $userId): void
    {
        (new SessionGuard())->login(['id' => $userId, 'must_change_password' => false], time());
    }

    private function insertUser(string $permission): int
    {
        $suffix = bin2hex(random_bytes(5));
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, permissions_json) VALUES (\'gestor\', :name, :email, :hash, :permissions)',
        );
        $statement->execute([
            'name' => 'Insurance ' . $suffix,
            'email' => $suffix . '@example.test',
            'hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
            'permissions' => json_encode([$permission], JSON_THROW_ON_ERROR),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function createLease(): array
    {
        $suffix = bin2hex(random_bytes(5));
        $actorId = $this->insertRoleUser('admin', 'actor-' . $suffix . '@example.test');
        $tenantId = $this->insertRoleUser('tenant', 'tenant-' . $suffix . '@example.test');
        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city) VALUES (:name, \'Calle 1\', \'Bogotá\')');
        $property->execute(['name' => 'Property ' . $suffix]);
        $unit = $this->pdo->prepare('INSERT INTO units (property_id, public_key, type, code, status) VALUES (:property, :key, \'apartment\', :code, \'occupied\')');
        $unit->execute(['property' => (int) $this->pdo->lastInsertId(), 'key' => bin2hex(random_bytes(12)), 'code' => 'APT-' . substr($suffix, 0, 5)]);
        $unitId = (int) $this->pdo->lastInsertId();
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit, \'cc\', :number, :name, \'3001234567\', :email, \'approved\', :token, \'2027-01-01 00:00:00\', \'2026-10-13 10:00:00\')',
        );
        $candidate->execute(['unit' => $unitId, 'number' => 'DOC-' . $suffix, 'name' => 'Candidate ' . $suffix, 'email' => 'candidate-' . $suffix . '@example.test', 'token' => hash('sha256', $suffix)]);
        $candidateId = (int) $this->pdo->lastInsertId();
        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type) VALUES (:name, \'urban_housing\')');
        $template->execute(['name' => 'Template ' . $suffix]);
        $version = $this->pdo->prepare('INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) VALUES (:template, 1, \'Contrato\', \'[]\')');
        $version->execute(['template' => (int) $this->pdo->lastInsertId()]);
        $versionId = (int) $this->pdo->lastInsertId();
        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, start_date, end_date, duration_text) '
            . 'VALUES (:candidate, :unit, :version, \'urban_housing\', \'finalized\', 1500000, 0, \'2026-10-01\', \'2027-09-30\', \'Doce meses\')',
        );
        $contract->execute(['candidate' => $candidateId, 'unit' => $unitId, 'version' => $versionId]);
        $lease = $this->pdo->prepare('INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) VALUES (:contract, :tenant, :actor, \'2026-10-01 12:00:00\')');
        $lease->execute(['contract' => (int) $this->pdo->lastInsertId(), 'tenant' => $tenantId, 'actor' => $actorId]);
        return ['lease_id' => (int) $this->pdo->lastInsertId(), 'actor_id' => $actorId, 'tenant_id' => $tenantId];
    }

    private function insertRoleUser(string $role, string $email): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash) VALUES (:role, :name, :email, :hash)',
        );
        $statement->execute([
            'role' => $role,
            'name' => ucfirst($role),
            'email' => $email,
            'hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function service(): InsuranceService
    {
        return new InsuranceService($this->pdo, $this->documentDirectory);
    }

    private function policyInput(string $number): array
    {
        return [
            'provider_name' => 'Aseguradora de prueba',
            'product_name' => 'Protección general',
            'policy_number' => $number,
            'coverage_start' => '2026-10-01',
            'coverage_end' => '2027-09-30',
            'coverage' => [['name' => 'Responsabilidad civil', 'limit_amount' => '1000000', 'deductible_amount' => null, 'notes' => null]],
        ];
    }
}
