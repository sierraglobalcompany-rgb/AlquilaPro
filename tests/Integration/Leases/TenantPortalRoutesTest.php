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

final class TenantPortalRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $contractDirectory;

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

        $this->directory = sys_get_temp_dir() . '/alquila_tenant_portal_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->contractDirectory = $this->directory . '/contracts';
        mkdir($this->contractDirectory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-13T10:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach (glob($this->contractDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory . '/branding');
        @rmdir($this->directory . '/unit-media');
        @rmdir($this->directory . '/candidate-documents');
        @rmdir($this->contractDirectory);
        @rmdir($this->directory);
    }

    public function testTenantPortalRequiresSessionPasswordGateAndTenantRole(): void
    {
        $tenantPassword = 'TenantPassword123';
        $this->insertUser('tenant@example.com', $tenantPassword, 'tenant');
        $this->insertUser('forced@example.com', $tenantPassword, 'tenant', [], true);
        $this->insertUser('admin@example.com', 'AdminPassword123', 'admin', ['contracts.view']);

        $guest = $this->request($this->app('/sub'), 'GET', '/sub/tenant');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $_SESSION = [];
        $forcedApp = $this->app('/sub');
        $this->login($forcedApp, 'forced@example.com', $tenantPassword, '/sub', '/sub/account/password');
        $forced = $this->request($forcedApp, 'GET', '/sub/tenant');
        self::assertSame(302, $forced->getStatusCode());
        self::assertSame('/sub/account/password', $forced->getHeaderLine('Location'));

        $_SESSION = [];
        $adminApp = $this->app('/sub');
        $this->login($adminApp, 'admin@example.com', 'AdminPassword123', '/sub');
        self::assertSame(403, $this->request($adminApp, 'GET', '/sub/tenant')->getStatusCode());
    }

    public function testTenantHomeShowsOnlyOwnedLeasesActiveFirstWithSafeBasePath(): void
    {
        $tenantId = $this->insertUser('owner@example.com', 'TenantPassword123', 'tenant', [], false, 'Tenant Owner');
        $foreignId = $this->insertUser('foreign@example.com', 'TenantPassword123', 'tenant', [], false, 'Foreign Tenant');

        $ended = $this->createLease($tenantId, [
            'type' => 'parking',
            'property_name' => 'Parqueadero histórico',
            'unit_code' => 'PARK-HISTORY',
            'parking_level' => 'P2',
            'activated_at' => '2026-10-13 12:00:00',
            'ended_at' => '2026-10-13 13:00:00',
        ]);
        $active = $this->createLease($tenantId, [
            'type' => 'apartment',
            'property_name' => 'Casa <script>alert(1)</script>',
            'unit_code' => 'APT-ACTIVE',
            'unit_number' => '401',
            'tower' => 'Torre Norte',
            'floor' => '4',
            'activated_at' => '2026-10-13 10:00:00',
        ]);
        $foreign = $this->createLease($foreignId, [
            'type' => 'apartment',
            'property_name' => 'Propiedad ajena secreta',
            'unit_code' => 'APT-FOREIGN',
            'activated_at' => '2026-10-13 14:00:00',
        ]);

        $app = $this->app('/sub');
        $this->login($app, 'owner@example.com', 'TenantPassword123', '/sub');
        $response = $this->request($app, 'GET', '/sub/tenant');

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('data-f3-app-shell', $html);
        self::assertStringContainsString('APT-ACTIVE', $html);
        self::assertStringContainsString('PARK-HISTORY', $html);
        self::assertStringContainsString('/sub/tenant/leases/' . $active['lease_id'], $html);
        self::assertStringContainsString('/sub/tenant/leases/' . $ended['lease_id'], $html);
        self::assertStringNotContainsString('APT-FOREIGN', $html);
        self::assertStringNotContainsString('Propiedad ajena secreta', $html);
        self::assertStringNotContainsString('/sub/tenant/leases/' . $foreign['lease_id'], $html);
        self::assertStringContainsString('Casa &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);

        $activePosition = strpos($html, 'APT-ACTIVE');
        $endedPosition = strpos($html, 'PARK-HISTORY');
        self::assertNotFalse($activePosition);
        self::assertNotFalse($endedPosition);
        self::assertLessThan($endedPosition, $activePosition, 'ACTIVE leases must render before ended history.');
    }

    public function testTenantLeaseDetailUsesSessionOwnershipAndContextualUnitFields(): void
    {
        $tenantId = $this->insertUser('detail@example.com', 'TenantPassword123', 'tenant');
        $foreignId = $this->insertUser('other@example.com', 'TenantPassword123', 'tenant');

        $apartment = $this->createLease($tenantId, [
            'type' => 'apartment',
            'property_name' => 'Edificio Apartamentos',
            'unit_code' => 'APT-401',
            'location_label' => 'Costado oriental',
            'unit_number' => '401',
            'tower' => 'Torre Norte',
            'floor' => '4',
        ]);
        $parking = $this->createLease($tenantId, [
            'type' => 'parking',
            'property_name' => 'Edificio Parqueaderos',
            'unit_code' => 'PARK-22',
            'location_label' => 'Sótano',
            'parking_level' => 'P2',
            'ended_at' => '2026-10-13 15:00:00',
        ]);
        $foreign = $this->createLease($foreignId, [
            'type' => 'apartment',
            'property_name' => 'No debe filtrarse',
            'unit_code' => 'APT-SECRET',
        ]);

        $app = $this->app('/tenant-base');
        $this->login($app, 'detail@example.com', 'TenantPassword123', '/tenant-base');

        $apartmentResponse = $this->request(
            $app,
            'GET',
            '/tenant-base/tenant/leases/' . $apartment['lease_id'],
        );
        self::assertSame(200, $apartmentResponse->getStatusCode());
        $apartmentHtml = (string) $apartmentResponse->getBody();
        foreach (['Edificio Apartamentos', 'APT-401', '401', 'Torre Norte', '4', '1500000.00', '220000.00'] as $value) {
            self::assertStringContainsString($value, $apartmentHtml);
        }
        self::assertStringContainsString(
            '/tenant-base/tenant/leases/' . $apartment['lease_id'] . '/contract',
            $apartmentHtml,
        );

        $parkingResponse = $this->request(
            $app,
            'GET',
            '/tenant-base/tenant/leases/' . $parking['lease_id'],
        );
        self::assertSame(200, $parkingResponse->getStatusCode());
        $parkingHtml = (string) $parkingResponse->getBody();
        foreach (['Edificio Parqueaderos', 'PARK-22', 'Sótano', 'P2', '1500000.00'] as $value) {
            self::assertStringContainsString($value, $parkingHtml);
        }
        self::assertStringContainsString('Finalizado', $parkingHtml);

        self::assertSame(
            404,
            $this->request($app, 'GET', '/tenant-base/tenant/leases/' . $foreign['lease_id'])->getStatusCode(),
        );
        self::assertSame(
            404,
            $this->request($app, 'GET', '/tenant-base/tenant/leases/999999')->getStatusCode(),
        );
    }

    public function testOwnedContractPdfKeepsPrivateIntegrityAndForeignLeaseIs404(): void
    {
        $tenantId = $this->insertUser('pdf-owner@example.com', 'TenantPassword123', 'tenant');
        $foreignId = $this->insertUser('pdf-foreign@example.com', 'TenantPassword123', 'tenant');

        $owned = $this->createLease($tenantId, [
            'type' => 'apartment',
            'unit_code' => 'APT-PDF',
            'with_pdf' => true,
        ]);
        $tampered = $this->createLease($tenantId, [
            'type' => 'parking',
            'unit_code' => 'PARK-TAMPER',
            'with_pdf' => true,
        ]);
        $missing = $this->createLease($tenantId, [
            'type' => 'parking',
            'unit_code' => 'PARK-MISSING',
            'with_pdf' => true,
        ]);
        $foreign = $this->createLease($foreignId, [
            'type' => 'apartment',
            'unit_code' => 'APT-FOREIGN-PDF',
            'with_pdf' => true,
        ]);

        file_put_contents($this->contractDirectory . '/' . $tampered['pdf_name'], '%PDF-tampered');
        @unlink($this->contractDirectory . '/' . $missing['pdf_name']);

        $app = $this->app('/sub');
        $this->login($app, 'pdf-owner@example.com', 'TenantPassword123', '/sub');

        $pdf = $this->request($app, 'GET', '/sub/tenant/leases/' . $owned['lease_id'] . '/contract');
        self::assertSame(200, $pdf->getStatusCode());
        self::assertSame('application/pdf', $pdf->getHeaderLine('Content-Type'));
        self::assertSame('private, no-store', $pdf->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $pdf->getHeaderLine('X-Content-Type-Options'));
        self::assertStringContainsString('attachment;', $pdf->getHeaderLine('Content-Disposition'));
        self::assertStringNotContainsString($owned['pdf_name'], $pdf->getHeaderLine('Content-Disposition'));
        self::assertSame((string) strlen($owned['pdf_bytes']), $pdf->getHeaderLine('Content-Length'));
        self::assertSame($owned['pdf_bytes'], (string) $pdf->getBody());

        self::assertSame(
            404,
            $this->request($app, 'GET', '/sub/tenant/leases/' . $foreign['lease_id'] . '/contract')->getStatusCode(),
        );
        self::assertSame(
            404,
            $this->request($app, 'GET', '/sub/tenant/leases/' . $tampered['lease_id'] . '/contract')->getStatusCode(),
        );
        self::assertSame(
            404,
            $this->request($app, 'GET', '/sub/tenant/leases/' . $missing['lease_id'] . '/contract')->getStatusCode(),
        );
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
            'contract_directory' => $this->contractDirectory,
            'pdo' => $this->pdo,
        ]);
    }

    private function request(App $app, string $method, string $path, array $body = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );
        if (strtoupper($method) === 'POST') {
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
        string $name = 'Tenant Portal User',
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(role, full_name, email, password_hash, is_active, permissions_json, must_change_password) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1, :permissions_json, :must_change_password)',
        );
        $statement->execute([
            'role' => $role,
            'full_name' => $name,
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{lease_id:int,contract_id:int,pdf_name:string,pdf_bytes:string} */
    private function createLease(int $userId, array $options = []): array
    {
        $type = (string) ($options['type'] ?? 'apartment');
        $documentType = $type === 'parking' ? 'independent_parking' : 'urban_housing';
        $propertyName = (string) ($options['property_name'] ?? 'Tenant Property ' . bin2hex(random_bytes(2)));
        $unitCode = (string) ($options['unit_code'] ?? strtoupper($type) . '-' . bin2hex(random_bytes(2)));
        $locationLabel = (string) ($options['location_label'] ?? ($type === 'parking' ? 'Sótano' : 'Piso 4'));
        $unitNumber = $options['unit_number'] ?? ($type === 'apartment' ? '401' : null);
        $tower = $options['tower'] ?? ($type === 'apartment' ? 'Torre A' : null);
        $floor = $options['floor'] ?? ($type === 'apartment' ? '4' : null);
        $parkingLevel = $options['parking_level'] ?? ($type === 'parking' ? 'P1' : null);
        $activatedAt = (string) ($options['activated_at'] ?? '2026-10-13 10:00:00');
        $endedAt = $options['ended_at'] ?? null;
        $withPdf = (bool) ($options['with_pdf'] ?? false);

        $property = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, :address, :city, :sector, 1)',
        );
        $property->execute([
            'name' => $propertyName,
            'address' => 'Calle 10 # 20-30',
            'city' => 'Medellín',
            'sector' => 'La América',
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, monthly_price, administration_fee, '
            . 'unit_number, tower, floor, parking_level) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, \'occupied\', :monthly_price, '
            . ':administration_fee, :unit_number, :tower, :floor, :parking_level)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => bin2hex(random_bytes(12)),
            'type' => $type,
            'code' => $unitCode,
            'location_label' => $locationLabel,
            'monthly_price' => '1500000.00',
            'administration_fee' => $type === 'apartment' ? '220000.00' : null,
            'unit_number' => $unitNumber,
            'tower' => $tower,
            'floor' => $floor,
            'parking_level' => $parkingLevel,
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
            'full_name' => 'Tenant Candidate ' . bin2hex(random_bytes(2)),
            'phone' => '3001234567',
            'email' => 'candidate-' . bin2hex(random_bytes(4)) . '@example.com',
            'current_address' => 'Carrera 50 # 40-20',
            'token_hash' => bin2hex(random_bytes(32)),
            'expires_at' => '2027-01-01 00:00:00',
            'consent_at' => '2026-10-13 10:00:00',
        ]);
        $candidateId = (int) $this->pdo->lastInsertId();

        $template = $this->pdo->prepare(
            'INSERT INTO contract_templates (name, document_type, is_active) VALUES (:name, :document_type, 1)',
        );
        $template->execute([
            'name' => 'Tenant Template ' . bin2hex(random_bytes(4)),
            'document_type' => $documentType,
        ]);
        $templateId = (int) $this->pdo->lastInsertId();

        $version = $this->pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) '
            . 'VALUES (:template_id, 1, :body_template, :variables_json)',
        );
        $version->execute([
            'template_id' => $templateId,
            'body_template' => 'Contrato {{candidate.full_name}}',
            'variables_json' => '[]',
        ]);
        $versionId = (int) $this->pdo->lastInsertId();

        $pdfName = '';
        $pdfBytes = '';
        $pdfHash = null;
        $pdfSize = null;
        if ($withPdf) {
            $pdfName = bin2hex(random_bytes(16)) . '.pdf';
            $pdfBytes = "%PDF-1.4\nTenant portal evidence\n%%EOF\n";
            file_put_contents($this->contractDirectory . '/' . $pdfName, $pdfBytes);
            $pdfHash = hash('sha256', $pdfBytes);
            $pdfSize = strlen($pdfBytes);
        }

        $contract = $this->pdo->prepare(
            'INSERT INTO contracts '
            . '(candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, administration_fee, '
            . 'start_date, end_date, duration_text, pdf_stored_name, pdf_sha256, pdf_size_bytes, generated_at, finalized_at) '
            . 'VALUES (:candidate_id, :unit_id, :version_id, :document_type, \'finalized\', :monthly_rent, '
            . ':administration_fee, :start_date, :end_date, :duration_text, :pdf_stored_name, :pdf_sha256, '
            . ':pdf_size_bytes, :generated_at, :finalized_at)',
        );
        $contract->execute([
            'candidate_id' => $candidateId,
            'unit_id' => $unitId,
            'version_id' => $versionId,
            'document_type' => $documentType,
            'monthly_rent' => '1500000.00',
            'administration_fee' => $type === 'apartment' ? '220000.00' : null,
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'duration_text' => 'Doce meses',
            'pdf_stored_name' => $withPdf ? $pdfName : null,
            'pdf_sha256' => $pdfHash,
            'pdf_size_bytes' => $pdfSize,
            'generated_at' => $withPdf ? '2026-10-13 09:55:00' : null,
            'finalized_at' => '2026-10-13 10:00:00',
        ]);
        $contractId = (int) $this->pdo->lastInsertId();

        $lease = $this->pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_at, ended_at) '
            . 'VALUES (:contract_id, :user_id, :activated_at, :ended_at)',
        );
        $lease->execute([
            'contract_id' => $contractId,
            'user_id' => $userId,
            'activated_at' => $activatedAt,
            'ended_at' => $endedAt,
        ]);

        return [
            'lease_id' => (int) $this->pdo->lastInsertId(),
            'contract_id' => $contractId,
            'pdf_name' => $pdfName,
            'pdf_bytes' => $pdfBytes,
        ];
    }
}
