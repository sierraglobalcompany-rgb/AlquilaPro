<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Auth\SessionGuard;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\ObligationService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class ObligationRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $supportDirectory;
    private string $lockPath;
    private array $temporaryUploads = [];

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
            $safeTable = str_replace('`', '``', (string) $table);
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safeTable));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->directory = sys_get_temp_dir() . '/alquilapro-obligation-routes-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $this->supportDirectory = $this->directory . '/obligation-supports';
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-14T10:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach ($this->temporaryUploads as $path) {
            @unlink($path);
        }
        $this->removeDirectory($this->directory);
    }

    public function testStaffGuardsPermissionsCsrfCreateUpdateAndPrivateSupportDelivery(): void
    {
        $tenantId = $this->insertUser('tenant', 'tenant-routes@example.com', ['finance.view', 'finance.manage']);
        $viewerId = $this->insertUser('gestor', 'viewer-routes@example.com', ['finance.view']);
        $managerId = $this->insertUser('gestor', 'manager-routes@example.com', ['finance.view', 'finance.manage']);
        $forcedId = $this->insertUser('gestor', 'forced-routes@example.com', ['finance.view'], true);
        $lease = $this->createLease($tenantId, 'STAFF-UNIT');
        $app = $this->app('/sub');

        $_SESSION = [];
        $guest = $this->request($app, 'GET', '/sub/admin/finance/obligations');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $this->login($forcedId, true);
        $forced = $this->request($app, 'GET', '/sub/admin/finance/obligations');
        self::assertSame(302, $forced->getStatusCode());
        self::assertSame('/sub/account/password', $forced->getHeaderLine('Location'));

        $this->login($tenantId);
        self::assertSame(403, $this->request($app, 'GET', '/sub/admin/finance/obligations')->getStatusCode());

        $sourceBytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        $obligation = $this->obligations()->create($lease['lease_id'], [
            'type' => 'utility', 'service_name' => 'Acueducto', 'description' => 'Cuenta <script>bad()</script>',
            'amount' => '50.00', 'due_on' => '2026-10-20', 'external_reference' => 'STAFF-ONLY-REF',
        ], $this->upload($sourceBytes, 'application/pdf'), $managerId, 1792000000);

        $this->login($viewerId);
        $viewerPage = $this->request($app, 'GET', '/sub/admin/finance/obligations');
        $viewerHtml = (string) $viewerPage->getBody();
        self::assertSame(200, $viewerPage->getStatusCode());
        self::assertSame('private, no-store', $viewerPage->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('STAFF-ONLY-REF', $viewerHtml);
        self::assertStringContainsString('Cuenta &lt;script&gt;bad()&lt;/script&gt;', $viewerHtml);
        self::assertStringNotContainsString('<script>bad()</script>', $viewerHtml);
        self::assertStringNotContainsString('method="post"', strtolower($viewerHtml));
        self::assertSame(403, $this->request($app, 'POST', '/sub/admin/finance/obligations/' . $obligation['id'], [
            'description' => 'No permitido',
        ])->getStatusCode());
        $staffSupport = $this->request(
            $app, 'GET', '/sub/admin/finance/obligations/' . $obligation['id'] . '/source-support',
        );
        self::assertSame(200, $staffSupport->getStatusCode());
        self::assertSame('application/pdf', $staffSupport->getHeaderLine('Content-Type'));
        self::assertSame((string) strlen($sourceBytes), $staffSupport->getHeaderLine('Content-Length'));
        self::assertSame('private, no-store', $staffSupport->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $staffSupport->getHeaderLine('X-Content-Type-Options'));
        self::assertSame($sourceBytes, (string) $staffSupport->getBody());
        self::assertStringNotContainsString((string) $this->obligationRow($obligation['id'])['source_support_stored_name'], $viewerHtml);
        self::assertStringNotContainsString($this->supportDirectory, $viewerHtml);
        $sourceName = (string) $this->obligationRow($obligation['id'])['source_support_stored_name'];
        file_put_contents($this->supportDirectory . DIRECTORY_SEPARATOR . $sourceName, 'tampered');
        self::assertSame(404, $this->request(
            $app, 'GET', '/sub/admin/finance/obligations/' . $obligation['id'] . '/source-support',
        )->getStatusCode());

        $this->login($managerId);
        $managerPage = $this->request($app, 'GET', '/sub/admin/finance/obligations');
        self::assertSame(200, $managerPage->getStatusCode());
        self::assertStringContainsString('action="/sub/admin/finance/obligations"', (string) $managerPage->getBody());
        self::assertStringContainsString('name="csrf_token"', (string) $managerPage->getBody());

        $createPath = '/sub/admin/finance/obligations';
        $missingCsrf = $this->request($app, 'POST', $createPath, [
            'lease_id' => (string) $lease['lease_id'],
            'type' => 'fine_ph', 'description' => 'Sin token', 'amount' => '1',
        ], [], false);
        self::assertSame(403, $missingCsrf->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM lease_obligations')->fetchColumn());

        $created = $this->request($app, 'POST', $createPath, [
            'lease_id' => (string) $lease['lease_id'],
            'type' => 'fine_ph', 'description' => 'Multa válida', 'amount' => '10.50', 'period_month' => '',
            'issued_on' => '', 'due_on' => '2026-10-22', 'external_reference' => 'REF-STAFF',
        ], ['source_support' => $this->upload($sourceBytes, 'application/pdf')]);
        self::assertSame(303, $created->getStatusCode());
        self::assertSame('/sub/admin/finance/obligations', $created->getHeaderLine('Location'));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM lease_obligations')->fetchColumn());

        $newId = (int) $this->pdo->query('SELECT MAX(id) FROM lease_obligations')->fetchColumn();
        $updated = $this->request($app, 'POST', '/sub/admin/finance/obligations/' . $newId, [
            'type' => 'fine_ph', 'description' => 'Multa corregida', 'amount' => '11', 'period_month' => '',
            'issued_on' => '', 'due_on' => '2026-10-23', 'external_reference' => 'REF-CORREGIDA',
        ]);
        self::assertSame(303, $updated->getStatusCode());
        self::assertSame('Multa corregida', $this->obligationRow($newId)['description']);

        $settled = $this->request($app, 'POST', '/sub/admin/finance/obligations/' . $newId . '/settle', [], [
            'settlement_support' => $this->upload($sourceBytes, 'application/pdf'),
        ]);
        self::assertSame(303, $settled->getStatusCode());
        self::assertNotNull($this->obligationRow($newId)['settled_at']);
        $settlementRoute = $this->request(
            $app, 'GET', '/sub/admin/finance/obligations/' . $newId . '/settlement-support',
        );
        self::assertSame(200, $settlementRoute->getStatusCode());

        $charge = $this->request($app, 'POST', '/sub/admin/finance/obligations/' . $newId . '/generate-charge', [
            'charge_due_date' => '2026-10-30',
        ]);
        self::assertSame(303, $charge->getStatusCode());
        $linkedChargeId = (int) $this->obligationRow($newId)['charge_id'];
        $chargeVoided = $this->request($app, 'POST', '/sub/admin/charges/' . $linkedChargeId . '/void', [
            'reason' => 'Corrección del cargo',
        ]);
        self::assertSame(303, $chargeVoided->getStatusCode());
        $voided = $this->request($app, 'POST', '/sub/admin/finance/obligations/' . $newId . '/void', [
            'reason' => 'Registro duplicado',
        ]);
        self::assertSame(303, $voided->getStatusCode());
        self::assertNotNull($this->obligationRow($newId)['voided_at']);

        $monthly = $this->finance()->createMonthlyCharges($lease['lease_id'], '2026-11-01', '2026-11-15', $managerId);
        $adminCharge = null;
        foreach ($monthly as $candidateCharge) {
            if ($candidateCharge['type'] === 'administration') {
                $adminCharge = $candidateCharge;
                break;
            }
        }
        self::assertIsArray($adminCharge);
        $adminObligation = $this->request($app, 'POST', $createPath, [
            'lease_id' => (string) $lease['lease_id'],
            'type' => 'administration_ph', 'description' => 'Administración de noviembre',
            'amount' => '190000', 'period_month' => '2026-11-01', 'issued_on' => '', 'due_on' => '',
            'external_reference' => 'PH-NOV',
        ]);
        self::assertSame(303, $adminObligation->getStatusCode());
        $adminObligationId = (int) $this->pdo->query('SELECT MAX(id) FROM lease_obligations')->fetchColumn();
        $adminPage = $this->request($app, 'GET', '/sub/admin/finance/obligations');
        self::assertStringContainsString('/admin/finance/obligations/' . $adminObligationId . '/link-administration-charge', (string) $adminPage->getBody());
        $linked = $this->request(
            $app,
            'POST',
            '/sub/admin/finance/obligations/' . $adminObligationId . '/link-administration-charge',
            ['charge_id' => (string) $adminCharge['id']],
        );
        self::assertSame(303, $linked->getStatusCode());
        self::assertSame((string) $adminCharge['id'], (string) $this->obligationRow($adminObligationId)['charge_id']);
    }

    public function testTenantSeesOnlyOwnedReadOnlyObligationsAndSourceSupport(): void
    {
        $tenantId = $this->insertUser('tenant', 'tenant-privacy@example.com', ['finance.view', 'finance.manage']);
        $foreignTenantId = $this->insertUser('tenant', 'foreign-privacy@example.com', []);
        $staffId = $this->insertUser('owner', 'owner-privacy@example.com', []);
        $ownedLease = $this->createLease($tenantId, 'OWNED-UNIT');
        $foreignLease = $this->createLease($foreignTenantId, 'FOREIGN-UNIT');
        $this->pdo->prepare('UPDATE leases SET ended_by_user_id = :actor_id, ended_at = :ended_at WHERE id = :id')
            ->execute(['actor_id' => $staffId, 'ended_at' => '2026-10-13 15:00:00', 'id' => $ownedLease['lease_id']]);
        $bytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        $service = $this->obligations();
        $owned = $service->create($ownedLease['lease_id'], [
            'type' => 'utility', 'service_name' => 'Gas', 'description' => 'Factura privada', 'amount' => '45.00',
            'due_on' => '2026-10-12', 'external_reference' => 'BANK-REFERENCE-SECRET',
        ], $this->upload($bytes, 'application/pdf'), $staffId, 1792000000);
        $service->settleExternal($owned['id'], $staffId, $this->upload($bytes, 'application/pdf'), 1792000100);
        $service->generateCharge($owned['id'], '2026-10-25', $staffId);

        $foreign = $service->create($foreignLease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'No debe verse', 'amount' => '90',
        ], $this->upload($bytes, 'application/pdf'), $staffId, 1792000200);
        $voided = $service->create($ownedLease['lease_id'], [
            'type' => 'fine_ph', 'description' => 'Soporte anulado', 'amount' => '12',
        ], $this->upload($bytes, 'application/pdf'), $staffId, 1792000300);
        $service->void($voided['id'], $staffId, 'Duplicada', 1792000400);

        $app = $this->app('/sub', static fn (): string => '2026-10-14');
        $this->login($tenantId);
        $detail = $this->request($app, 'GET', '/sub/tenant/leases/' . $ownedLease['lease_id']);
        $html = (string) $detail->getBody();
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('Mis obligaciones externas', $html);
        self::assertStringContainsString('Factura privada', $html);
        self::assertStringContainsString('45.00', $html);
        self::assertStringContainsString('Atendida externamente', $html);
        self::assertStringContainsString('Cargo financiero vinculado', $html);
        self::assertStringNotContainsString('BANK-REFERENCE-SECRET', $html);
        self::assertStringNotContainsString('settlement_support_stored_name', $html);
        self::assertStringNotContainsString((string) $this->obligationRow($owned['id'])['settlement_support_stored_name'], $html);
        self::assertStringNotContainsString((string) $this->obligationRow($owned['id'])['source_support_sha256'], $html);
        self::assertStringNotContainsString('settled_by_user_id', $html);
        self::assertStringNotContainsString('created_by_user_id', $html);
        self::assertStringContainsString('Soporte anulado', $html, 'Owned voided obligations remain visible with their derived state.');
        self::assertStringNotContainsString('/obligations/' . $voided['id'] . '/source-support', $html);
        self::assertStringNotContainsString('No debe verse', $html);
        self::assertStringNotContainsString('BANK-REFERENCE-SECRET', $html);

        $sourcePath = '/sub/tenant/leases/' . $ownedLease['lease_id'] . '/obligations/' . $owned['id'] . '/source-support';
        $support = $this->request($app, 'GET', $sourcePath);
        self::assertSame(200, $support->getStatusCode());
        self::assertSame('application/pdf', $support->getHeaderLine('Content-Type'));
        self::assertSame((string) strlen($bytes), $support->getHeaderLine('Content-Length'));
        self::assertSame('private, no-store', $support->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $support->getHeaderLine('X-Content-Type-Options'));
        self::assertSame($bytes, (string) $support->getBody());
        self::assertSame(404, $this->request(
            $app, 'GET', '/sub/tenant/leases/' . $ownedLease['lease_id'] . '/obligations/' . $voided['id'] . '/source-support',
        )->getStatusCode());
        self::assertSame(404, $this->request(
            $app, 'GET', '/sub/tenant/leases/' . $foreignLease['lease_id'] . '/obligations/' . $foreign['id'] . '/source-support',
        )->getStatusCode());
        self::assertSame(404, $this->request(
            $app, 'GET', '/sub/tenant/leases/999999/obligations/' . $owned['id'] . '/source-support',
        )->getStatusCode());
        self::assertSame(404, $this->request(
            $app, 'GET', '/sub/tenant/leases/' . $ownedLease['lease_id'] . '/obligations/' . $owned['id'] . '/settlement-support',
        )->getStatusCode());
        self::assertSame(404, $this->request($app, 'POST', '/sub/tenant/leases/' . $ownedLease['lease_id'] . '/obligations')->getStatusCode());

        $this->login($staffId);
        self::assertSame(403, $this->request($app, 'GET', '/sub/tenant/leases/' . $ownedLease['lease_id'])->getStatusCode());
    }

    private function app(string $basePath = '', ?\Closure $today = null): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.example' . $basePath,
            'base_path' => $basePath,
            'logo_directory' => $this->directory . '/branding',
            'unit_media_directory' => $this->directory . '/unit-media',
            'candidate_document_directory' => $this->directory . '/candidate-documents',
            'contract_directory' => $this->directory . '/contracts',
            'payment_method_qr_directory' => $this->directory . '/payment-method-qrs',
            'payment_receipt_directory' => $this->directory . '/payment-receipts',
            'obligation_support_directory' => $this->supportDirectory,
            'pdo' => $this->pdo,
            'today' => $today,
        ]);
    }

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        array $files = [],
        bool $withCsrf = true,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://alquilapro.example' . $path);
        if (strtoupper($method) === 'POST' && $withCsrf) {
            $body['csrf_token'] = (new Csrf())->token();
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }
        if ($files !== []) {
            $request = $request->withUploadedFiles($files);
        }

        return $app->handle($request);
    }

    private function login(int $userId, bool $mustChangePassword = false): void
    {
        (new SessionGuard())->login(['id' => $userId, 'must_change_password' => $mustChangePassword], time());
    }

    private function insertUser(string $role, string $email, array $permissions, bool $mustChangePassword = false): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :name, :email, :password_hash, 1, :permissions, :must_change)',
        );
        $statement->execute([
            'role' => $role,
            'name' => $email,
            'email' => $email,
            'password_hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
            'permissions' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createLease(int $tenantId, string $code): array
    {
        $suffix = bin2hex(random_bytes(5));
        $actorId = (int) $this->pdo->query("SELECT id FROM users WHERE role = 'owner' ORDER BY id LIMIT 1")->fetchColumn();
        if ($actorId === 0) {
            $actorId = $this->insertUser('owner', 'owner-' . $suffix . '@example.com', []);
        }
        $property = $this->pdo->prepare('INSERT INTO properties (name, address, city) VALUES (:name, :address, :city)');
        $property->execute(['name' => 'Property ' . $code, 'address' => 'Calle 1', 'city' => 'Bogotá']);
        $propertyId = (int) $this->pdo->lastInsertId();
        $unit = $this->pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) VALUES (:property_id, :public_key, :type, :code, :status)',
        );
        $unit->execute([
            'property_id' => $propertyId, 'public_key' => bin2hex(random_bytes(12)),
            'type' => 'apartment', 'code' => $code, 'status' => 'occupied',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :status, :token_hash, :expires_at, :consent_at)',
        );
        $candidate->execute([
            'unit_id' => $unitId, 'document_type' => 'cc', 'document_number' => 'DOC-' . $suffix,
            'full_name' => 'Candidate ' . $suffix, 'phone' => '3001234567', 'email' => 'candidate-' . $suffix . '@example.com',
            'status' => 'approved', 'token_hash' => hash('sha256', 'token-' . $suffix),
            'expires_at' => '2027-01-01 00:00:00', 'consent_at' => '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();
        $template = $this->pdo->prepare('INSERT INTO contract_templates (name, document_type) VALUES (:name, :document_type)');
        $template->execute(['name' => 'Template ' . $suffix, 'document_type' => 'urban_housing']);
        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) VALUES (:template_id, 1, :body, :variables)',
        );
        $version->execute(['template_id' => (int) $this->pdo->lastInsertId(), 'body' => 'Contrato', 'variables' => '[]']);
        $versionId = (int) $this->pdo->lastInsertId();
        $contract = $this->pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, start_date, end_date, duration_text) '
            . 'VALUES (:candidate_id, :unit_id, :version_id, :document_type, :status, :rent, :fee, :start_date, :end_date, :duration)',
        );
        $contract->execute([
            'candidate_id' => $candidateId,
            'unit_id' => $unitId, 'version_id' => $versionId, 'document_type' => 'urban_housing',
            'status' => 'finalized', 'rent' => '1500000.00', 'fee' => '200000.00',
            'start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'duration' => 'Doce meses',
        ]);
        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) VALUES (:contract_id, :user_id, :actor_id, :activated_at)',
        );
        $lease->execute([
            'contract_id' => (int) $this->pdo->lastInsertId(), 'user_id' => $tenantId,
            'actor_id' => $actorId, 'activated_at' => '2026-10-01 12:00:00',
        ]);

        return ['lease_id' => (int) $this->pdo->lastInsertId(), 'actor_id' => $actorId];
    }

    private function obligations(): ObligationService
    {
        return new ObligationService($this->pdo, $this->finance(), $this->supportDirectory);
    }

    private function finance(): FinanceService
    {
        $paymentMethods = new PaymentMethodService($this->pdo, $this->directory . '/payment-method-qrs');

        return new FinanceService($this->pdo, $paymentMethods, $this->directory . '/payment-receipts');
    }

    private function obligationRow(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM lease_obligations WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    private function upload(string $bytes, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'obligation-route-upload-');
        if ($path === false) {
            self::fail('Unable to create upload fixture.');
        }
        file_put_contents($path, $bytes);
        $this->temporaryUploads[] = $path;

        return new UploadedFile($path, 'support.pdf', $mime, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }
}
