<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Leases;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Community\CommunityService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Finance\FinanceService;
use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Maintenance\MaintenanceService;
use AlquilaPro\Maintenance\VisitService;
use AlquilaPro\Memberships\MembershipService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class TenantPortalRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $contractDirectory;
    private string $qrDirectory;
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
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'payment_allocations',
            'payments',
            'payment_methods',
            'service_memberships',
            'charges',
            'digital_services',
            'community_incidents',
            'documents',
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
        $this->qrDirectory = $this->directory . '/payment-method-qrs';
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
        foreach ($this->temporaryUploads as $file) {
            @unlink($file);
        }
        foreach (glob($this->qrDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->directory . '/maintenance-photos/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->directory . '/documents/*') ?: [] as $file) {
            @unlink($file);
        }
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory . '/branding');
        @rmdir($this->directory . '/unit-media');
        @rmdir($this->directory . '/candidate-documents');
        @rmdir($this->contractDirectory);
        @rmdir($this->qrDirectory);
        @rmdir($this->directory . '/maintenance-photos');
        @rmdir($this->directory . '/documents');
        @rmdir($this->directory . '/maintenance-receipts');
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
            'start_date' => '2026-10-01',
            'end_date' => '2027-09-30',
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

        $tracedPdo = $this->tracedPdo();
        $monthlyCharges = (new FinanceService(
            $tracedPdo,
            new PaymentMethodService($tracedPdo, $this->qrDirectory),
            $this->directory . '/payment-receipts',
        ))->createMonthlyCharges(
            $apartment['lease_id'], '2026-10-01', '2026-10-12', $tenantId,
        );
        self::assertSame(['rent', 'administration'], array_column($monthlyCharges, 'type'));
        self::assertSame([null, null], array_column($monthlyCharges, 'description'));
        $this->insertCharge(
            $apartment['lease_id'], $tenantId, 'service', null, '2026-11-03', '75.25', '<script>tenant()</script>',
        );
        $this->insertCharge(
            $apartment['lease_id'], $tenantId, 'fine', null, '2026-09-01', '900.00', 'Anulado',
            '2026-09-02 12:00:00', 'MOTIVO PRIVADO TENANT',
        );
        $this->insertCharge(
            $parking['lease_id'], $tenantId, 'repair', null, '2026-10-12', '30.00', 'Reparación histórica',
        );

        $app = $this->app('/tenant-base', $tracedPdo, static fn (): string => '2026-10-14');
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
        self::assertStringContainsString('Cartera', $apartmentHtml);
        self::assertStringContainsString('Saldo pendiente: $1720075.25', $apartmentHtml);
        self::assertStringContainsString('Saldo vencido: $1720000.00', $apartmentHtml);
        self::assertStringContainsString('Vencimiento pendiente más antiguo: 2026-10-12', $apartmentHtml);
        self::assertStringContainsString('Canon mensual', $apartmentHtml);
        self::assertStringContainsString('Administración', $apartmentHtml);
        self::assertStringContainsString('Periodo: 2026-10', $apartmentHtml);
        self::assertStringContainsString('pending', $apartmentHtml);
        self::assertStringContainsString('overdue', $apartmentHtml);
        self::assertStringContainsString('voided', $apartmentHtml);
        self::assertStringContainsString('Días vencidos: 2', $apartmentHtml);
        self::assertStringContainsString('&lt;script&gt;tenant()&lt;/script&gt;', $apartmentHtml);
        self::assertStringNotContainsString('<script>tenant()</script>', $apartmentHtml);
        self::assertStringNotContainsString('MOTIVO PRIVADO TENANT', $apartmentHtml);
        self::assertStringNotContainsString('created_by_user_id', $apartmentHtml);
        self::assertStringNotContainsString('voided_by_user_id', $apartmentHtml);
        self::assertStringNotContainsString('void_reason', $apartmentHtml);
        self::assertStringContainsString('name="csrf_token"', $apartmentHtml);
        self::assertStringContainsString('/tenant-base/tenant/leases/' . $apartment['lease_id'] . '/maintenance', $apartmentHtml);
        self::assertStringNotContainsString('/admin/charges/', $apartmentHtml);
        self::assertStringNotContainsString('/tenant/payments', $apartmentHtml);
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
        self::assertStringContainsString('Reparación histórica', $parkingHtml);
        self::assertStringContainsString('Saldo pendiente: $30.00', $parkingHtml);

        $tracedPdo->preparedSql = [];
        self::assertSame(
            404,
            $this->request($app, 'GET', '/tenant-base/tenant/leases/' . $foreign['lease_id'])->getStatusCode(),
        );
        self::assertSame([], array_values(array_filter(
            $tracedPdo->preparedSql,
            static fn (string $sql): bool => str_contains(strtolower($sql), 'from charges'),
        )), 'Foreign lease must be rejected before FinanceService is queried.');

        $tracedPdo->preparedSql = [];
        self::assertSame(
            404,
            $this->request($app, 'GET', '/tenant-base/tenant/leases/999999')->getStatusCode(),
        );
        self::assertSame([], array_values(array_filter(
            $tracedPdo->preparedSql,
            static fn (string $sql): bool => str_contains(strtolower($sql), 'from charges'),
        )), 'Missing lease must be rejected before FinanceService is queried.');
    }

    public function testTenantDocumentsAreSafeCurrentAndPrivateDownloadsCheckLeaseOwnershipFirst(): void
    {
        $tenantId = $this->insertUser('documents-tenant@example.com', 'TenantPassword123', 'tenant');
        $foreignTenantId = $this->insertUser('documents-foreign@example.com', 'TenantPassword123', 'tenant');
        $ownedLease = $this->createLease($tenantId);
        $foreignLease = $this->createLease($foreignTenantId, ['property_name' => 'Foreign document property']);
        $documents = new DocumentService($this->pdo, $this->directory . '/documents');

        $oldDocument = $documents->createFileVersion(
            null,
            $ownedLease['lease_id'],
            'lease_document',
            'Versión anterior privada',
            $this->documentUpload(),
            null,
            $tenantId,
            1_800_000_000,
        );
        $currentDocument = $documents->createFileVersion(
            null,
            $ownedLease['lease_id'],
            'lease_document',
            'Documento de arrendamiento vigente',
            $this->documentUpload(),
            (int) $oldDocument['id'],
            $tenantId,
            1_800_000_001,
        );
        $documents->createContentVersion(
            null,
            $ownedLease['lease_id'],
            'inventory_initial',
            'Inventario inicial vigente',
            ['general_notes' => 'Entrega documentada', 'items' => [[
                'area' => 'Sala', 'item' => 'Ventana', 'condition' => 'Buen estado', 'quantity' => 1, 'notes' => null,
            ]]],
            null,
            $tenantId,
            1_800_000_002,
        );
        $manual = $documents->createContentVersion(
            $ownedLease['property_id'],
            null,
            'manual_convivencia',
            'Manual aplicable de convivencia',
            ['rules' => ['Mantener silencio después de las 10 p. m.']],
            null,
            $tenantId,
            1_800_000_003,
        );
        $documents->createFileVersion(
            $ownedLease['property_id'],
            null,
            'property_document',
            'Documento interno de la propiedad',
            $this->documentUpload(),
            null,
            $tenantId,
            1_800_000_004,
        );
        $foreignDocument = $documents->createFileVersion(
            null,
            $foreignLease['lease_id'],
            'lease_document',
            'Documento de otro inquilino',
            $this->documentUpload(),
            null,
            $foreignTenantId,
            1_800_000_005,
        );

        $app = $this->app('/sub');
        $this->login($app, 'documents-tenant@example.com', 'TenantPassword123', '/sub');
        $page = $this->request($app, 'GET', '/sub/tenant/leases/' . $ownedLease['lease_id']);
        self::assertSame(200, $page->getStatusCode());
        $html = (string) $page->getBody();
        self::assertStringContainsString('Documento de arrendamiento vigente', $html);
        self::assertStringContainsString('Inventario inicial vigente', $html);
        self::assertStringContainsString('Manual aplicable de convivencia', $html);
        self::assertStringNotContainsString('Versión anterior privada', $html);
        self::assertStringNotContainsString('Documento interno de la propiedad', $html);
        self::assertStringNotContainsString('Documento de otro inquilino', $html);
        self::assertStringNotContainsString((string) $currentDocument['stored_name'], $html);
        self::assertStringNotContainsString((string) $currentDocument['sha256'], $html);
        self::assertStringContainsString(
            '/sub/tenant/leases/' . $ownedLease['lease_id'] . '/documents/' . $currentDocument['id'] . '/file',
            $html,
        );
        self::assertSame((int) $manual['id'], (int) $this->pdo->query(
            "SELECT id FROM documents WHERE type = 'manual_convivencia' ORDER BY id DESC LIMIT 1",
        )->fetchColumn());

        $tracePdo = $this->tracedPdo();
        $_SESSION = [];
        $traceApp = $this->app('/sub', $tracePdo);
        $this->login($traceApp, 'documents-tenant@example.com', 'TenantPassword123', '/sub');
        $tracePdo->preparedSql = [];
        $foreignResponse = $this->request(
            $traceApp,
            'GET',
            '/sub/tenant/leases/' . $foreignLease['lease_id'] . '/documents/' . $foreignDocument['id'] . '/file',
        );
        self::assertSame(404, $foreignResponse->getStatusCode());
        self::assertNotEmpty($tracePdo->preparedSql);
        self::assertNotSame(false, array_search(true, array_map(
            static fn (string $sql): bool => str_contains(strtolower($sql), 'from leases l'),
            $tracePdo->preparedSql,
        ), true));
        self::assertSame([], array_values(array_filter(
            $tracePdo->preparedSql,
            static fn (string $sql): bool => str_contains(strtolower($sql), 'from documents'),
        )));

        $tracePdo->preparedSql = [];
        $download = $this->request(
            $traceApp,
            'GET',
            '/sub/tenant/leases/' . $ownedLease['lease_id'] . '/documents/' . $currentDocument['id'] . '/file',
        );
        self::assertSame(200, $download->getStatusCode());
        self::assertSame($this->pdfBytes(), (string) $download->getBody());
        $leaseLookup = array_search(true, array_map(
            static fn (string $sql): bool => str_contains(strtolower($sql), 'from leases l'),
            $tracePdo->preparedSql,
        ), true);
        $documentLookup = array_search(true, array_map(
            static fn (string $sql): bool => str_contains(strtolower($sql), 'from documents'),
            $tracePdo->preparedSql,
        ), true);
        self::assertIsInt($leaseLookup);
        self::assertIsInt($documentLookup);
        self::assertLessThan($documentLookup, $leaseLookup);
        self::assertSame('private, no-store', $download->getHeaderLine('Cache-Control'));
    }

    public function testTenantCommunityAndLegalProjectionIsSafeAndOwnershipPrecedesLookups(): void
    {
        $tenantId = $this->insertUser('community-projection@example.com', 'TenantPassword123', 'tenant');
        $foreignTenantId = $this->insertUser('community-foreign@example.com', 'TenantPassword123', 'tenant');
        $owned = $this->createLease($tenantId, ['property_name' => 'Comunidad propia']);
        $foreign = $this->createLease($foreignTenantId, ['property_name' => 'Comunidad ajena']);
        $community = new CommunityService($this->pdo);

        $open = $community->create(
            $owned['lease_id'], 'Ruido', '2026-10-13', 'Incidente abierto visible.', $tenantId, 1_800_000_000,
        );
        $reviewed = $community->create(
            $owned['lease_id'], 'Mascotas', '2026-10-12', 'Incidente revisado visible.', $tenantId, 1_800_000_001,
        );
        $community->markReviewed((int) $reviewed['id'], $tenantId, 1_800_000_002);
        $closed = $community->create(
            $owned['lease_id'], 'Acceso', '2026-10-11', 'Incidente cerrado visible.', $tenantId, 1_800_000_003,
        );
        $community->close((int) $closed['id'], 'Resolución visible tras cierre.', $tenantId, 1_800_000_004);
        $voided = $community->create(
            $owned['lease_id'], 'Registro', '2026-10-10', 'INCIDENTE_VOIDED_SECRET', $tenantId, 1_800_000_005,
        );
        $community->void((int) $voided['id'], 'MOTIVO_VOIDED_PRIVADO', $tenantId, 1_800_000_006);
        $community->create(
            $foreign['lease_id'], 'Privado', '2026-10-09', 'INCIDENTE_AJENO_SECRET', $foreignTenantId, 1_800_000_007,
        );

        $documents = new DocumentService($this->pdo, $this->directory . '/documents');
        $oldNotice = $documents->createContentVersion(
            $owned['property_id'], null, 'legal_notice', 'Aviso legal anterior',
            ['source_label' => 'Fuente anterior', 'notice_text' => 'AVISO_ANTERIOR_SECRET'],
            null, $tenantId, 1_800_000_008,
        );
        $documents->createContentVersion(
            $owned['property_id'], null, 'legal_notice', 'Aviso legal vigente',
            [
                'source_label' => 'Alcaldía local',
                'source_url' => 'https://example.test/current',
                'effective_on' => '2026-10-14',
                'notice_text' => 'Aviso legal aplicable vigente.',
            ],
            (int) $oldNotice['id'], $tenantId, 1_800_000_009,
        );

        $tracedPdo = $this->tracedPdo();
        $app = $this->app('/sub', $tracedPdo, static fn (): string => '2026-10-14');
        $this->login($app, 'community-projection@example.com', 'TenantPassword123', '/sub');
        $tracedPdo->preparedSql = [];
        $detail = $this->request($app, 'GET', '/sub/tenant/leases/' . $owned['lease_id']);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('Incidente abierto visible.', $html);
        self::assertStringContainsString('Incidente revisado visible.', $html);
        self::assertStringContainsString('Incidente cerrado visible.', $html);
        self::assertStringContainsString('Resolución visible tras cierre.', $html);
        self::assertStringNotContainsString('INCIDENTE_VOIDED_SECRET', $html);
        self::assertStringNotContainsString('MOTIVO_VOIDED_PRIVADO', $html);
        self::assertStringNotContainsString('INCIDENTE_AJENO_SECRET', $html);
        self::assertStringContainsString('Aviso legal vigente', $html);
        self::assertStringContainsString('Alcaldía local', $html);
        self::assertStringContainsString('https://example.test/current', $html);
        self::assertStringContainsString('Aviso legal aplicable vigente.', $html);
        self::assertStringNotContainsString('AVISO_ANTERIOR_SECRET', $html);
        foreach (['created_by_user_id', 'closed_by_user_id', 'voided_by_user_id', 'void_reason', 'stored_name', 'sha256'] as $privateField) {
            self::assertStringNotContainsString($privateField, $html);
        }

        $queries = array_map('strtolower', $tracedPdo->preparedSql);
        $leaseLookup = array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from leases l'),
            $queries,
        ), true);
        $incidentLookup = array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from community_incidents'),
            $queries,
        ), true);
        $documentLookup = array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from documents'),
            $queries,
        ), true);
        self::assertIsInt($leaseLookup);
        self::assertIsInt($incidentLookup);
        self::assertIsInt($documentLookup);
        self::assertLessThan($incidentLookup, $leaseLookup);
        self::assertLessThan($documentLookup, $leaseLookup);

        $tracedPdo->preparedSql = [];
        self::assertSame(404, $this->request(
            $app,
            'GET',
            '/sub/tenant/leases/' . $foreign['lease_id'],
        )->getStatusCode());
        $foreignQueries = array_map('strtolower', $tracedPdo->preparedSql);
        self::assertSame([], array_values(array_filter(
            $foreignQueries,
            static fn (string $sql): bool => str_contains($sql, 'from community_incidents')
                || str_contains($sql, 'from documents'),
        )));
    }

    public function testTenantMembershipRoutesEnforceLeaseOwnershipAndProjectOnlySafeOutcomes(): void
    {
        $tenantId = $this->insertUser('membership-portal@example.com', 'TenantPassword123', 'tenant');
        $foreignTenantId = $this->insertUser('membership-portal-foreign@example.com', 'TenantPassword123', 'tenant');
        $owned = $this->createLease($tenantId, ['unit_code' => 'APT-MEMBERSHIP-OWNED']);
        $foreign = $this->createLease($foreignTenantId, ['unit_code' => 'APT-MEMBERSHIP-FOREIGN']);
        $membershipService = new MembershipService(
            $this->pdo,
            new FinanceService($this->pdo, new PaymentMethodService($this->pdo, $this->qrDirectory), $this->directory . '/receipts'),
        );
        $catalog = $membershipService->createService(
            'Soporte digital', 'Ayuda adicional para residentes.', '28.50', 10, $tenantId, 1_800_000_000,
        );

        $tracedPdo = $this->tracedPdo();
        $app = $this->app('/sub', $tracedPdo, static fn (): string => '2026-10-14');
        $this->login($app, 'membership-portal@example.com', 'TenantPassword123', '/sub');

        $tracedPdo->preparedSql = [];
        $detail = $this->request($app, 'GET', '/sub/tenant/leases/' . $owned['lease_id']);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('Servicios digitales', $html);
        self::assertStringContainsString('Soporte digital', $html);
        self::assertStringContainsString('Aceptar servicio', $html);
        self::assertStringNotContainsString('accepted_by_user_id', $html);
        self::assertStringNotContainsString('charge_decided_by_user_id', $html);
        self::assertStringNotContainsString('charge_id', $html);
        $queries = array_map('strtolower', $tracedPdo->preparedSql);
        $leaseQuery = array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from leases l'),
            $queries,
        ), true);
        $membershipQuery = array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from service_memberships'),
            $queries,
        ), true);
        self::assertIsInt($leaseQuery);
        self::assertIsInt($membershipQuery);
        self::assertLessThan($membershipQuery, $leaseQuery);

        $tracedPdo->preparedSql = [];
        self::assertSame(404, $this->request(
            $app,
            'GET',
            '/sub/tenant/leases/' . $foreign['lease_id'],
        )->getStatusCode());
        $foreignQueries = array_map('strtolower', $tracedPdo->preparedSql);
        self::assertSame([], array_values(array_filter(
            $foreignQueries,
            static fn (string $sql): bool => str_contains($sql, 'digital_services')
                || str_contains($sql, 'service_memberships'),
        )));

        $tracedPdo->preparedSql = [];
        self::assertSame(404, $this->request(
            $app,
            'POST',
            '/sub/tenant/leases/' . $foreign['lease_id'] . '/memberships',
            ['service_id' => (string) $catalog['id']],
        )->getStatusCode());
        $foreignPostQueries = array_map('strtolower', $tracedPdo->preparedSql);
        self::assertSame([], array_values(array_filter(
            $foreignPostQueries,
            static fn (string $sql): bool => str_contains($sql, 'digital_services')
                || str_contains($sql, 'service_memberships'),
        )));

        $tracedPdo->preparedSql = [];
        self::assertSame(303, $this->request(
            $app,
            'POST',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/memberships',
            [
                'service_id' => (string) $catalog['id'],
                'expected_price' => '28.50',
                'expected_duration_days' => '10',
            ],
        )->getStatusCode());
        $membershipId = (int) $this->pdo->query('SELECT MAX(id) FROM service_memberships')->fetchColumn();
        self::assertGreaterThan(0, $membershipId);

        $tracedPdo->preparedSql = [];
        self::assertSame(404, $this->request(
            $app,
            'POST',
            '/sub/tenant/leases/' . $foreign['lease_id'] . '/memberships/' . $membershipId . '/renew',
        )->getStatusCode());
        $foreignRenewQueries = array_map('strtolower', $tracedPdo->preparedSql);
        self::assertSame([], array_values(array_filter(
            $foreignRenewQueries,
            static fn (string $sql): bool => str_contains($sql, 'digital_services')
                || str_contains($sql, 'service_memberships'),
        )));

        $queries = array_map('strtolower', $tracedPdo->preparedSql);
        $leaseQuery = array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from leases l'),
            $queries,
        ), true);
        self::assertIsInt($leaseQuery);

        $membershipService->decideCharge(
            $membershipId, 'create', '2026-10-20', 'Servicio digital', $tenantId, 1_800_000_030,
        );
        $tracedPdo->preparedSql = [];
        $renewResponse = $this->request(
            $app,
            'POST',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/memberships/' . $membershipId . '/renew',
            ['expected_price' => '28.50', 'expected_duration_days' => '10'],
        );
        self::assertSame(303, $renewResponse->getStatusCode());
        $renewQueries = array_map('strtolower', $tracedPdo->preparedSql);
        $leaseQuery = array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from leases l'),
            $renewQueries,
        ), true);
        $membershipQuery = array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from service_memberships'),
            $renewQueries,
        ), true);
        self::assertIsInt($leaseQuery);
        self::assertIsInt($membershipQuery);
        self::assertLessThan($membershipQuery, $leaseQuery);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM service_memberships')->fetchColumn());
        $renewed = $membershipService->listForLease($owned['lease_id'])[0];
        self::assertSame($membershipId, $renewed['renews_membership_id']);

        $history = $this->request($app, 'GET', '/sub/tenant/leases/' . $owned['lease_id']);
        self::assertSame(200, $history->getStatusCode());
        $historyHtml = (string) $history->getBody();
        self::assertStringContainsString('Cargo creado', $historyHtml);
        self::assertStringContainsString('Pendiente de decisión administrativa', $historyHtml);
        foreach (['accepted_by_user_id', 'charge_decided_by_user_id', 'charge_id'] as $privateField) {
            self::assertStringNotContainsString($privateField, $historyHtml);
        }
    }

    public function testTenantConfirmsDisplayedMembershipTermsAndRejectsStaleOffers(): void
    {
        $tenantId = $this->insertUser('membership-offer@example.com', 'TenantPassword123', 'tenant');
        $owned = $this->createLease($tenantId, ['unit_code' => 'APT-MEMBERSHIP-OFFER']);
        $membershipService = new MembershipService(
            $this->pdo,
            new FinanceService($this->pdo, new PaymentMethodService($this->pdo, $this->qrDirectory), $this->directory . '/receipts'),
        );
        $catalog = $membershipService->createService(
            'Conectividad', 'Acceso digital', '12.00', 3, $tenantId, 1_800_000_000,
        );
        $serviceId = (int) $catalog['id'];
        $app = $this->app('/sub', $this->pdo, static fn (): string => '2026-10-14');
        $this->login($app, 'membership-offer@example.com', 'TenantPassword123', '/sub');

        $initialOffer = $this->request($app, 'GET', '/sub/tenant/leases/' . $owned['lease_id']);
        self::assertSame(200, $initialOffer->getStatusCode());
        self::assertStringContainsString('12.00', (string) $initialOffer->getBody());
        self::assertStringContainsString('3 días', (string) $initialOffer->getBody());
        self::assertStringContainsString('2026-10-14', (string) $initialOffer->getBody());
        self::assertStringContainsString('2026-10-16', (string) $initialOffer->getBody());
        self::assertStringContainsString('name="expected_price" value="12.00"', (string) $initialOffer->getBody());

        $membershipService->updateService(
            $serviceId, 'Conectividad', 'Acceso digital', '18.00', 5, true, $tenantId, 1_800_000_010,
        );
        $staleAcceptance = $this->request(
            $app,
            'POST',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/memberships',
            ['service_id' => $serviceId, 'expected_price' => '12.00', 'expected_duration_days' => '3'],
        );
        self::assertSame(409, $staleAcceptance->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM service_memberships')->fetchColumn());

        $currentOffer = $this->request($app, 'GET', '/sub/tenant/leases/' . $owned['lease_id']);
        self::assertStringContainsString('18.00', (string) $currentOffer->getBody());
        self::assertStringContainsString('5 días', (string) $currentOffer->getBody());
        self::assertSame(303, $this->request(
            $app,
            'POST',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/memberships',
            ['service_id' => $serviceId, 'expected_price' => '18.00', 'expected_duration_days' => '5'],
        )->getStatusCode());
        $membershipId = (int) $this->pdo->query('SELECT MAX(id) FROM service_memberships')->fetchColumn();

        $membershipService->updateService(
            $serviceId, 'Conectividad', 'Acceso digital', '24.00', 7, true, $tenantId, 1_800_000_020,
        );
        $renewalOffer = $this->request($app, 'GET', '/sub/tenant/leases/' . $owned['lease_id']);
        $renewalHtml = (string) $renewalOffer->getBody();
        self::assertStringContainsString('24.00', $renewalHtml);
        self::assertStringContainsString('7 días', $renewalHtml);
        self::assertStringContainsString('Vigencia propuesta: 2026-10-19 a 2026-10-25', $renewalHtml);

        $staleRenewal = $this->request(
            $app,
            'POST',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/memberships/' . $membershipId . '/renew',
            ['expected_price' => '18.00', 'expected_duration_days' => '5'],
        );
        self::assertSame(409, $staleRenewal->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM service_memberships')->fetchColumn());

        self::assertSame(303, $this->request(
            $app,
            'POST',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/memberships/' . $membershipId . '/renew',
            ['expected_price' => '24.00', 'expected_duration_days' => '7'],
        )->getStatusCode());
        self::assertSame('24.00', $this->pdo->query(
            'SELECT price_snapshot FROM service_memberships WHERE renews_membership_id = ' . $membershipId,
        )->fetchColumn());
        self::assertSame(7, (int) $this->pdo->query(
            'SELECT duration_days_snapshot FROM service_memberships WHERE renews_membership_id = ' . $membershipId,
        )->fetchColumn());
    }

    public function testTenantLeaseDetailShowsOnlyActiveGlobalAndOwnedPropertyPaymentMethods(): void
    {
        $tenantId = $this->insertUser('payment-method-owner@example.com', 'TenantPassword123', 'tenant');
        $foreignId = $this->insertUser('payment-method-foreign@example.com', 'TenantPassword123', 'tenant');
        $owned = $this->createLease($tenantId, [
            'type' => 'apartment',
            'property_name' => 'Propiedad propia',
            'unit_code' => 'APT-PAYMENT-OWNER',
        ]);
        $ended = $this->createLease($tenantId, [
            'type' => 'parking',
            'property_name' => 'Propiedad histórica',
            'unit_code' => 'PARK-PAYMENT-ENDED',
            'ended_at' => '2026-10-12 12:00:00',
        ]);
        $foreign = $this->createLease($foreignId, [
            'type' => 'apartment',
            'property_name' => 'Propiedad ajena',
            'unit_code' => 'APT-PAYMENT-FOREIGN',
        ]);

        $globalManual = $this->createPaymentMethod('manual', [
            'label' => 'Global <strong>instrucciones</strong>',
            'instructions' => 'Referencia & cuenta general',
            'sort_order' => 2,
        ]);
        $propertyBank = $this->createPaymentMethod('bank_account', [
            'property_id' => $owned['property_id'],
            'label' => 'Cuenta de propiedad',
            'sort_order' => 1,
        ]);
        $propertyQr = $this->createPaymentMethod('breb_qr', [
            'property_id' => $owned['property_id'],
            'label' => 'QR de propiedad',
            'sort_order' => 3,
        ], $this->qrUpload());
        $globalQr = $this->createPaymentMethod('breb_qr', [
            'label' => 'QR global',
            'sort_order' => 4,
        ], $this->qrUpload());
        $foreignMethod = $this->createPaymentMethod('wallet', [
            'property_id' => $foreign['property_id'],
            'label' => 'Billetera de otra propiedad',
            'institution_name' => 'Billetera',
            'wallet_identifier' => '3001234567',
        ]);
        $inactiveQr = $this->createPaymentMethod('breb_qr', [
            'property_id' => $owned['property_id'],
            'label' => 'QR inactivo',
            'is_active' => 0,
        ], $this->qrUpload());

        $app = $this->app('/sub');
        $this->login($app, 'payment-method-owner@example.com', 'TenantPassword123', '/sub');
        $response = $this->request(
            $app,
            'GET',
            '/sub/tenant/leases/' . $owned['lease_id'] . '?property_id=' . $foreign['property_id'],
        );
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Cómo pagar', $html);
        self::assertStringContainsString('Global &lt;strong&gt;instrucciones&lt;/strong&gt;', $html);
        self::assertStringContainsString('Referencia &amp; cuenta general', $html);
        self::assertStringContainsString('Cuenta de propiedad', $html);
        self::assertStringContainsString('QR de propiedad', $html);
        self::assertStringContainsString('QR global', $html);
        self::assertStringNotContainsString('Billetera de otra propiedad', $html);
        self::assertStringNotContainsString('QR inactivo', $html);
        self::assertStringNotContainsString($propertyQr['qr_stored_name'], $html);
        self::assertStringNotContainsString($globalQr['qr_stored_name'], $html);
        self::assertStringNotContainsString($this->qrDirectory, $html);
        self::assertStringNotContainsString($propertyQr['qr_sha256'], $html);
        self::assertStringContainsString(
            '/sub/tenant/leases/' . $owned['lease_id'] . '/payment-methods/' . $propertyQr['id'] . '/qr',
            $html,
        );
        self::assertStringContainsString(
            '/sub/tenant/leases/' . $owned['lease_id'] . '/payment-methods/' . $globalQr['id'] . '/qr',
            $html,
        );
        $orderedLabels = ['Cuenta de propiedad', 'Global &lt;strong&gt;instrucciones', 'QR de propiedad', 'QR global'];
        $lastPosition = -1;
        foreach ($orderedLabels as $orderedLabel) {
            $position = strpos($html, $orderedLabel);
            self::assertNotFalse($position, $orderedLabel);
            self::assertGreaterThan($lastPosition, $position, 'Applicable methods must retain stable configured order.');
            $lastPosition = $position;
        }
        foreach (['Ya pagué', 'reconciliación', 'allocation', 'proof_received'] as $futureControl) {
            self::assertStringNotContainsString(strtolower($futureControl), strtolower($html));
        }

        $endedResponse = $this->request($app, 'GET', '/sub/tenant/leases/' . $ended['lease_id']);
        self::assertSame(200, $endedResponse->getStatusCode());
        self::assertStringContainsString('Global &lt;strong&gt;instrucciones&lt;/strong&gt;', (string) $endedResponse->getBody());
        self::assertSame(404, $this->request($app, 'GET', '/sub/tenant/leases/' . $foreign['lease_id'])->getStatusCode());
    }

    public function testTenantPrivateQrRequiresOwnedLeaseApplicableActiveMethodAndIntactFile(): void
    {
        $tenantId = $this->insertUser('private-qr-owner@example.com', 'TenantPassword123', 'tenant');
        $foreignId = $this->insertUser('private-qr-foreign@example.com', 'TenantPassword123', 'tenant');
        $owned = $this->createLease($tenantId, ['unit_code' => 'APT-QR-OWNER']);
        $foreign = $this->createLease($foreignId, ['unit_code' => 'APT-QR-FOREIGN']);
        $ownedQr = $this->createPaymentMethod('breb_qr', [
            'property_id' => $owned['property_id'],
            'label' => 'QR autorizado',
        ], $this->qrUpload());
        $globalQr = $this->createPaymentMethod('breb_qr', ['label' => 'QR global'], $this->qrUpload());
        $foreignQr = $this->createPaymentMethod('breb_qr', [
            'property_id' => $foreign['property_id'],
            'label' => 'QR extranjero',
        ], $this->qrUpload());
        $inactiveQr = $this->createPaymentMethod('breb_qr', [
            'property_id' => $owned['property_id'],
            'label' => 'QR apagado',
            'is_active' => 0,
        ], $this->qrUpload());
        $manual = $this->createPaymentMethod('manual', ['label' => 'No es QR']);
        $service = new PaymentMethodService($this->pdo, $this->qrDirectory);
        $tamperedQr = $this->createPaymentMethod('breb_qr', ['label' => 'QR alterado'], $this->qrUpload());
        $tamperedFile = $service->privateQr((int) $tamperedQr['id']);
        self::assertNotNull($tamperedFile);
        file_put_contents($tamperedFile['path'], 'not-an-image');
        $missingQr = $this->createPaymentMethod('breb_qr', ['label' => 'QR ausente'], $this->qrUpload());
        $missingFile = $service->privateQr((int) $missingQr['id']);
        self::assertNotNull($missingFile);
        @unlink($missingFile['path']);

        $tracedPdo = $this->tracedPdo();
        $app = $this->app('/sub', $tracedPdo);
        $this->login($app, 'private-qr-owner@example.com', 'TenantPassword123', '/sub');
        $path = '/sub/tenant/leases/' . $owned['lease_id'] . '/payment-methods/' . $ownedQr['id'] . '/qr';
        $response = $this->request($app, 'GET', $path);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertSame((string) strlen($this->pngBytes()), $response->getHeaderLine('Content-Length'));
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame($this->pngBytes(), (string) $response->getBody());
        self::assertStringNotContainsString($ownedQr['qr_stored_name'], (string) $response->getBody());
        self::assertStringNotContainsString($ownedQr['qr_stored_name'], $response->getHeaderLine('Content-Disposition'));
        self::assertSame(200, $this->request(
            $app,
            'GET',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/payment-methods/' . $globalQr['id'] . '/qr',
        )->getStatusCode());

        foreach ([
            '/sub/tenant/leases/' . $owned['lease_id'] . '/payment-methods/' . $foreignQr['id'] . '/qr',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/payment-methods/' . $inactiveQr['id'] . '/qr',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/payment-methods/' . $manual['id'] . '/qr',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/payment-methods/' . $tamperedQr['id'] . '/qr',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/payment-methods/' . $missingQr['id'] . '/qr',
            '/sub/tenant/leases/' . $foreign['lease_id'] . '/payment-methods/' . $globalQr['id'] . '/qr',
            '/sub/tenant/leases/999999/payment-methods/' . $globalQr['id'] . '/qr',
        ] as $deniedPath) {
            $tracedPdo->preparedSql = [];
            self::assertSame(404, $this->request($app, 'GET', $deniedPath)->getStatusCode(), $deniedPath);
            if (str_contains($deniedPath, '/leases/' . $foreign['lease_id'] . '/')) {
                self::assertSame([], array_values(array_filter(
                    $tracedPdo->preparedSql,
                    static fn (string $sql): bool => str_contains(strtolower($sql), 'from payment_methods'),
                )), 'Foreign lease must fail before payment-method lookup.');
            }
        }
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

    public function testTenantCanCreateMaintenanceOnlyForOwnedLeaseAndSeesSafeProjection(): void
    {
        $tenantId = $this->insertUser('maintenance-tenant@example.com', 'TenantPassword123', 'tenant');
        $foreignId = $this->insertUser('maintenance-foreign@example.com', 'TenantPassword123', 'tenant');
        $owned = $this->createLease($tenantId, ['unit_code' => 'APT-MAINT-OWNED']);
        $foreign = $this->createLease($foreignId, ['unit_code' => 'APT-MAINT-FOREIGN']);
        $app = $this->app('/sub');
        $this->login($app, 'maintenance-tenant@example.com', 'TenantPassword123', '/sub');

        $created = $this->request($app, 'POST', '/sub/tenant/leases/' . $owned['lease_id'] . '/maintenance', [
            'category' => '  Plomería  ',
            'priority' => 'high',
            'description' => '  Fuga bajo el lavaplatos  ',
        ]);
        self::assertSame(303, $created->getStatusCode());
        self::assertSame('/sub/tenant/leases/' . $owned['lease_id'], $created->getHeaderLine('Location'));

        $detail = $this->request($app, 'GET', '/sub/tenant/leases/' . $owned['lease_id']);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('Plomería', $html);
        self::assertStringContainsString('Fuga bajo el lavaplatos', $html);
        self::assertStringContainsString('Agregar foto', $html);
        self::assertStringNotContainsString('maintenance.manage', $html);
        self::assertStringNotContainsString('charge_id', $html);
        self::assertStringNotContainsString('stored_name', $html);

        $denied = $this->request($app, 'POST', '/sub/tenant/leases/' . $foreign['lease_id'] . '/maintenance', [
            'category' => 'Privada',
            'priority' => 'medium',
            'description' => 'No debe crearse',
        ]);
        self::assertSame(404, $denied->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM maintenance_requests')->fetchColumn());
    }

    public function testTenantSelectsOnlyOwnedProposedSlotAfterLeaseOwnershipCheck(): void
    {
        $tenantId = $this->insertUser('visit-tenant@example.com', 'TenantPassword123', 'tenant');
        $foreignId = $this->insertUser('visit-foreign@example.com', 'TenantPassword123', 'tenant');
        $owned = $this->createLease($tenantId, ['unit_code' => 'APT-VISIT-OWNED']);
        $foreign = $this->createLease($foreignId, ['unit_code' => 'APT-VISIT-FOREIGN']);
        $maintenance = $this->maintenanceService();
        $ownedRequest = $maintenance->create($owned['lease_id'], 'Plomería', 'medium', 'Fuga', $tenantId);
        $foreignRequest = $maintenance->create($foreign['lease_id'], 'Electricidad', 'medium', 'Cable', $foreignId);
        $visits = new VisitService($this->pdo);
        $now = time();
        $ownedStart = gmdate('Y-m-d H:i:s', $now + 86400);
        $foreignStart = gmdate('Y-m-d H:i:s', $now + 172800);
        $ownedVisit = $visits->propose($ownedRequest['id'], [[
            'start_at' => $ownedStart,
            'duration_minutes' => 60,
        ]], 'Técnico privado', '3009998877', $tenantId, $now);
        $foreignVisit = $visits->propose($foreignRequest['id'], [[
            'start_at' => $foreignStart,
            'duration_minutes' => 60,
        ]], 'Técnico extranjero', '3008887766', $foreignId, $now);

        $tracedPdo = $this->tracedPdo();
        $app = $this->app('/sub', $tracedPdo);
        $this->login($app, 'visit-tenant@example.com', 'TenantPassword123', '/sub');
        $detail = $this->request($app, 'GET', '/sub/tenant/leases/' . $owned['lease_id']);
        self::assertSame(200, $detail->getStatusCode());
        $html = (string) $detail->getBody();
        self::assertStringContainsString('Seleccionar horario', $html);
        self::assertStringContainsString($ownedStart, $html);
        self::assertStringNotContainsString('Técnico privado', $html);
        self::assertStringNotContainsString('3009998877', $html);
        self::assertStringNotContainsString('Completar visita', $html);
        self::assertStringNotContainsString('Cancelar visita', $html);
        self::assertStringNotContainsString($foreignStart, $html);

        $tracedPdo->preparedSql = [];
        $foreignSelection = $this->request(
            $app,
            'POST',
            '/sub/tenant/leases/' . $foreign['lease_id'] . '/maintenance/' . $foreignRequest['id']
                . '/visits/' . $foreignVisit['id'] . '/select',
            ['start_at' => $foreignStart],
        );
        self::assertSame(404, $foreignSelection->getStatusCode());
        $foreignQueries = array_map('strtolower', $tracedPdo->preparedSql);
        self::assertNotFalse(array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from leases'),
            $foreignQueries,
        ), true));
        self::assertSame([], array_values(array_filter(
            $foreignQueries,
            static fn (string $sql): bool => str_contains($sql, 'from maintenance_requests')
                || str_contains($sql, 'from maintenance_visits'),
        )));

        $tracedPdo->preparedSql = [];
        $selected = $this->request(
            $app,
            'POST',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/maintenance/' . $ownedRequest['id']
                . '/visits/' . $ownedVisit['id'] . '/select',
            ['start_at' => $ownedStart, 'technician_name' => 'Attacker'],
        );
        self::assertSame(303, $selected->getStatusCode());
        $orderedQueries = array_map('strtolower', $tracedPdo->preparedSql);
        $leaseIndex = array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from leases'),
            $orderedQueries,
        ), true);
        $requestIndex = array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from maintenance_requests'),
            $orderedQueries,
        ), true);
        $visitIndex = array_search(true, array_map(
            static fn (string $sql): bool => str_contains($sql, 'from maintenance_visits'),
            $orderedQueries,
        ), true);
        self::assertIsInt($leaseIndex);
        self::assertIsInt($requestIndex);
        self::assertIsInt($visitIndex);
        self::assertLessThan($requestIndex, $leaseIndex);
        self::assertLessThan($visitIndex, $requestIndex);
        self::assertSame('scheduled', $visits->find($ownedVisit['id'])['status']);
        self::assertSame('Técnico privado', $visits->find($ownedVisit['id'])['technician_name']);
        self::assertSame(404, $this->request(
            $app,
            'POST',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/maintenance/' . $ownedRequest['id']
                . '/visits/' . $ownedVisit['id'] . '/complete',
        )->getStatusCode());
    }

    public function testMaintenanceStaffRoutesSeparateViewAndManagePermissions(): void
    {
        $tenantId = $this->insertUser('maintenance-lease@example.com', 'TenantPassword123', 'tenant');
        $lease = $this->createLease($tenantId, ['unit_code' => 'APT-MAINT-STAFF']);
        $viewerEmail = 'maintenance-viewer@example.com';
        $managerEmail = 'maintenance-manager@example.com';
        $this->insertUser($viewerEmail, 'StaffPassword123', 'admin', ['maintenance.view']);
        $this->insertUser($managerEmail, 'StaffPassword123', 'admin', ['maintenance.view', 'maintenance.manage']);

        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, $viewerEmail, 'StaffPassword123', '/sub');
        self::assertSame(200, $this->request($viewerApp, 'GET', '/sub/admin/maintenance')->getStatusCode());
        self::assertSame(403, $this->request($viewerApp, 'POST', '/sub/admin/maintenance', [
            'lease_id' => (string) $lease['lease_id'],
            'category' => 'Eléctrica',
            'priority' => 'medium',
            'description' => 'Revisar interruptor',
        ])->getStatusCode());

        $managerApp = $this->app('/sub');
        $this->login($managerApp, $managerEmail, 'StaffPassword123', '/sub');
        $created = $this->request($managerApp, 'POST', '/sub/admin/maintenance', [
            'lease_id' => (string) $lease['lease_id'],
            'category' => 'Eléctrica',
            'priority' => 'medium',
            'description' => 'Revisar interruptor',
        ]);
        self::assertSame(303, $created->getStatusCode());
        self::assertMatchesRegularExpression('#\A/sub/admin/maintenance/[1-9][0-9]*\z#', $created->getHeaderLine('Location'));
        $requestId = (int) basename($created->getHeaderLine('Location'));
        self::assertSame(303, $this->request($managerApp, 'POST', '/sub/admin/maintenance/' . $requestId . '/classification', [
            'damage_classification' => 'tenant_damage',
        ])->getStatusCode());
        $detail = $this->request($managerApp, 'GET', $created->getHeaderLine('Location'));
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('Gestionar solicitud', (string) $detail->getBody());
        self::assertStringContainsString('Registrar cargo de reparación', (string) $detail->getBody());
    }

    public function testMaintenancePhotosRequireOwnedLeaseOrStaffViewAndFailClosed(): void
    {
        $tenantId = $this->insertUser('maintenance-photo-owner@example.com', 'TenantPassword123', 'tenant');
        $foreignId = $this->insertUser('maintenance-photo-foreign@example.com', 'TenantPassword123', 'tenant');
        $owned = $this->createLease($tenantId, ['unit_code' => 'APT-MAINT-PHOTO-OWNED']);
        $foreign = $this->createLease($foreignId, ['unit_code' => 'APT-MAINT-PHOTO-FOREIGN']);
        $service = $this->maintenanceService();
        $record = $service->create($owned['lease_id'], 'Ventana', 'medium', 'Vidrio rayado', $tenantId);
        $service->addPhoto($record['id'], $this->qrUpload(), $tenantId, 1791980000);
        $photo = $service->find($record['id'])['photos'][0];

        $app = $this->app('/sub');
        $this->login($app, 'maintenance-photo-owner@example.com', 'TenantPassword123', '/sub');
        $ownedPhoto = $this->request(
            $app,
            'GET',
            '/sub/tenant/leases/' . $owned['lease_id'] . '/maintenance/' . $record['id'] . '/photos/' . $photo['photo_id'],
        );
        self::assertSame(200, $ownedPhoto->getStatusCode());
        self::assertSame('image/png', $ownedPhoto->getHeaderLine('Content-Type'));
        self::assertSame('private, no-store', $ownedPhoto->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $ownedPhoto->getHeaderLine('X-Content-Type-Options'));
        self::assertSame($this->pngBytes(), (string) $ownedPhoto->getBody());
        self::assertSame(404, $this->request(
            $app,
            'GET',
            '/sub/tenant/leases/' . $foreign['lease_id'] . '/maintenance/' . $record['id'] . '/photos/' . $photo['photo_id'],
        )->getStatusCode());

        $_SESSION = [];
        $this->insertUser('maintenance-photo-viewer@example.com', 'StaffPassword123', 'admin', ['maintenance.view']);
        $staffApp = $this->app('/sub');
        $this->login($staffApp, 'maintenance-photo-viewer@example.com', 'StaffPassword123', '/sub');
        self::assertSame(200, $this->request(
            $staffApp,
            'GET',
            '/sub/admin/maintenance/' . $record['id'] . '/photos/' . $photo['photo_id'],
        )->getStatusCode());

        file_put_contents($this->directory . '/maintenance-photos/' . $photo['stored_name'], 'tampered');
        self::assertSame(404, $this->request(
            $staffApp,
            'GET',
            '/sub/admin/maintenance/' . $record['id'] . '/photos/' . $photo['photo_id'],
        )->getStatusCode());
    }

    private function maintenanceService(): MaintenanceService
    {
        $photoDirectory = $this->directory . '/maintenance-photos';
        $receipts = $this->directory . '/maintenance-receipts';
        $methods = new PaymentMethodService($this->pdo, $receipts);
        $finance = new FinanceService($this->pdo, $methods, $receipts);
        return new MaintenanceService($this->pdo, $finance, $photoDirectory);
    }

    private function app(string $basePath = '', ?PDO $pdo = null, ?\Closure $today = null): App
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
            'payment_method_qr_directory' => $this->qrDirectory,
            'maintenance_photo_directory' => $this->directory . '/maintenance-photos',
            'document_directory' => $this->directory . '/documents',
            'pdo' => $pdo ?? $this->pdo,
            'today' => $today,
        ]);
    }

    private function tracedPdo(): PDO
    {
        $host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
        $port = getenv('TEST_DB_PORT') ?: '3306';
        $database = getenv('TEST_DB_NAME') ?: 'alquila_test';
        $username = getenv('TEST_DB_USER') ?: 'root';
        $password = getenv('TEST_DB_PASSWORD') ?: 'root';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        return new class($dsn, $username, $password) extends PDO {
            public array $preparedSql = [];

            public function __construct(string $dsn, string $username, string $password)
            {
                parent::__construct($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                $this->preparedSql[] = $query;

                return parent::prepare($query, $options);
            }
        };
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

    private function insertCharge(
        int $leaseId,
        int $actorUserId,
        string $type,
        ?string $periodMonth,
        string $dueDate,
        string $amount,
        ?string $description,
        ?string $voidedAt = null,
        ?string $voidReason = null,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO charges '
            . '(lease_id, type, period_month, due_date, amount, description, created_by_user_id, '
            . 'voided_by_user_id, voided_at, void_reason) '
            . 'VALUES (:lease_id, :type, :period, :due_date, :amount, :description, :creator, :voider, :voided_at, :reason)',
        );
        $statement->execute([
            'lease_id' => $leaseId,
            'type' => $type,
            'period' => $periodMonth,
            'due_date' => $dueDate,
            'amount' => $amount,
            'description' => $description,
            'creator' => $actorUserId,
            'voider' => $voidedAt === null ? null : $actorUserId,
            'voided_at' => $voidedAt,
            'reason' => $voidReason,
        ]);
    }

    /** @return array{lease_id:int,contract_id:int,property_id:int,pdf_name:string,pdf_bytes:string} */
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
        $startDate = (string) ($options['start_date'] ?? '2026-11-01');
        $endDate = (string) ($options['end_date'] ?? '2027-10-31');

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
            'start_date' => $startDate,
            'end_date' => $endDate,
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
            'property_id' => $propertyId,
            'pdf_name' => $pdfName,
            'pdf_bytes' => $pdfBytes,
        ];
    }

    private function createPaymentMethod(string $type, array $overrides = [], ?UploadedFile $qr = null): array
    {
        $input = array_replace([
            'property_id' => null,
            'type' => $type,
            'label' => 'Medio de pago',
            'recipient_name' => 'Titular',
            'institution_name' => 'Banco',
            'account_type' => 'savings',
            'account_number' => '001234567890',
            'breb_key_type' => 'mobile',
            'breb_key_value' => '3001234567',
            'wallet_identifier' => '3001234567',
            'instructions' => 'Indique el número de contrato.',
            'sort_order' => 1,
            'is_active' => 1,
        ], $overrides);

        return (new PaymentMethodService($this->pdo, $this->qrDirectory))->create($input, $qr);
    }

    private function qrUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_tenant_qr_');
        if ($path === false || file_put_contents($path, $this->pngBytes()) === false) {
            self::fail('Unable to create tenant QR fixture.');
        }
        $this->temporaryUploads[] = $path;

        return new UploadedFile($path, 'bank-qr.png', 'image/png', strlen($this->pngBytes()), UPLOAD_ERR_OK, false);
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }

    private function documentUpload(): UploadedFile
    {
        $bytes = $this->pdfBytes();
        $path = tempnam(sys_get_temp_dir(), 'alquila_tenant_document_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create tenant document fixture.');
        }
        $this->temporaryUploads[] = $path;
        return new UploadedFile($path, 'tenant-document.pdf', 'application/pdf', strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function pdfBytes(): string
    {
        return \AlquilaPro\Tests\Support\PdfFixtures::validPdf();
    }
}
