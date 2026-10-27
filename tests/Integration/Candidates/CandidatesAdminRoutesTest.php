<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Candidates\CandidateDocumentService;
use AlquilaPro\Candidates\CandidateHouseholdService;
use AlquilaPro\Documents\DocumentService;
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

final class CandidatesAdminRoutesTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $documentDirectory;
    private string $propertyDocumentDirectory;
    private string $householdDirectory;
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
        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['candidate_household_occupants', 'candidate_household_pets', 'candidate_documents', 'candidate_references', 'unit_application_requirements', 'documents', 'candidates', 'unit_media', 'units', 'properties', 'users'] as $table) {
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->directory = sys_get_temp_dir() . '/alquila_candidates_admin_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->documentDirectory = $this->directory . '/candidate-documents';
        $this->propertyDocumentDirectory = $this->directory . '/property-documents';
        $this->householdDirectory = $this->directory . '/candidate-household';
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-12T00:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        foreach (glob($this->documentDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach ([$this->propertyDocumentDirectory, $this->householdDirectory] as $directory) {
            foreach (glob($directory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
        @rmdir($this->documentDirectory);
        foreach (glob($this->directory . '/unit-media/*') ?: [] as $file) {
            @unlink($file);
        }
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory . '/branding');
        @rmdir($this->directory . '/unit-media');
        @rmdir($this->directory);
    }

    public function testAdminCandidatesRequiresAuthViewPermissionPasswordGateAndOwnerBypass(): void
    {
        $candidateId = $this->insertCandidate('VIEW-1', 'Candidato Visible');
        $app = $this->app('/sub');

        $guest = $this->request($app, 'GET', '/sub/admin/candidates');
        self::assertSame(302, $guest->getStatusCode());
        self::assertSame('/sub/login', $guest->getHeaderLine('Location'));

        $this->insertUser('restricted@example.com', 'RestrictedPassword123', 'admin', ['properties.view']);
        $this->login($app, 'restricted@example.com', 'RestrictedPassword123', '/sub');
        self::assertSame(403, $this->request($app, 'GET', '/sub/admin/candidates')->getStatusCode());

        $_SESSION = [];
        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'viewer@example.com', 'ViewerPassword123', '/sub');
        self::assertSame(200, $this->request($viewerApp, 'GET', '/sub/admin/candidates')->getStatusCode());
        self::assertSame(200, $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId)->getStatusCode());

        $_SESSION = [];
        $this->insertUser('forced@example.com', 'ForcedPassword123', 'admin', ['candidates.view'], true);
        $forcedApp = $this->app('/sub');
        $login = $this->request($forcedApp, 'POST', '/sub/login', [
            'email' => 'forced@example.com',
            'password' => 'ForcedPassword123',
        ]);
        self::assertSame('/sub/account/password', $login->getHeaderLine('Location'));
        $blocked = $this->request($forcedApp, 'GET', '/sub/admin/candidates');
        self::assertSame(302, $blocked->getStatusCode());
        self::assertSame('/sub/account/password', $blocked->getHeaderLine('Location'));

        $_SESSION = [];
        $this->insertUser('owner@example.com', 'OwnerPassword123', 'owner');
        $ownerApp = $this->app('/sub');
        $this->login($ownerApp, 'owner@example.com', 'OwnerPassword123', '/sub');
        self::assertSame(200, $this->request($ownerApp, 'GET', '/sub/admin/candidates')->getStatusCode());
        self::assertSame(200, $this->request($ownerApp, 'GET', '/sub/admin/candidates/' . $candidateId)->getStatusCode());
    }

    public function testCandidatesLauncherIsVisibleOnlyToCandidateViewerAndUsesBasePath(): void
    {
        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'viewer@example.com', 'ViewerPassword123', '/sub');
        $viewerHtml = (string) $this->request($viewerApp, 'GET', '/sub/')->getBody();
        self::assertStringContainsString('href="/sub/admin/candidates"', $viewerHtml);
        self::assertStringContainsString('Candidatos', $viewerHtml);

        $_SESSION = [];
        $this->insertUser('restricted@example.com', 'RestrictedPassword123', 'admin', ['properties.view']);
        $restrictedApp = $this->app('/sub');
        $this->login($restrictedApp, 'restricted@example.com', 'RestrictedPassword123', '/sub');
        $restrictedHtml = (string) $this->request($restrictedApp, 'GET', '/sub/')->getBody();
        self::assertStringNotContainsString('/sub/admin/candidates', $restrictedHtml);
        self::assertStringNotContainsString('Candidatos', $restrictedHtml);
    }

    public function testCandidateDetailSectionsAreFailClosedPermissionAwareAndPreserveBasePath(): void
    {
        $candidateId = $this->insertCandidate('SECTIONS-1', 'Candidato Secciones');

        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $viewerApp = $this->app('/sub');
        $this->login($viewerApp, 'viewer@example.com', 'ViewerPassword123', '/sub');

        $overview = (string) $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId)->getBody();
        self::assertStringContainsString('data-candidate-section-body="overview"', $overview);
        self::assertSame(1, substr_count($overview, 'data-candidate-section-body='));
        self::assertSame(1, substr_count($overview, '<main '), 'The app layout owns the only main landmark.');
        self::assertStringContainsString('/sub/admin/candidates/' . $candidateId . '?section=work', $overview);
        self::assertStringNotContainsString('?section=contracts', $overview);
        self::assertStringNotContainsString('?section=manage', $overview);

        $work = (string) $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=work')->getBody();
        self::assertStringContainsString('data-candidate-section-body="work"', $work);
        self::assertSame(1, substr_count($work, 'data-candidate-section-body='));

        $unknown = (string) $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=unknown')->getBody();
        self::assertStringContainsString('data-candidate-section-body="overview"', $unknown);
        $arrayQuery = (string) $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId . '?section[]=work')->getBody();
        self::assertStringContainsString('data-candidate-section-body="overview"', $arrayQuery);
        $forbiddenContracts = (string) $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=contracts')->getBody();
        self::assertStringContainsString('data-candidate-section-body="overview"', $forbiddenContracts);
        $forbiddenManage = (string) $this->request($viewerApp, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=manage')->getBody();
        self::assertStringContainsString('data-candidate-section-body="overview"', $forbiddenManage);

        $_SESSION = [];
        $this->insertUser('manager@example.com', 'ManagerPassword123', 'admin', ['candidates.view', 'candidates.manage']);
        $managerApp = $this->app('/sub');
        $this->login($managerApp, 'manager@example.com', 'ManagerPassword123', '/sub');
        $manage = (string) $this->request($managerApp, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=manage')->getBody();
        self::assertStringContainsString('data-candidate-section-body="manage"', $manage);
        self::assertStringContainsString('?section=manage', $manage);

        $_SESSION = [];
        $this->insertUser('contracts@example.com', 'ContractsPassword123', 'admin', ['candidates.view', 'contracts.view']);
        $contractsApp = $this->app('/sub');
        $this->login($contractsApp, 'contracts@example.com', 'ContractsPassword123', '/sub');
        $contracts = (string) $this->request($contractsApp, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=contracts')->getBody();
        self::assertStringContainsString('data-candidate-section-body="contracts"', $contracts);
        self::assertStringContainsString('?section=contracts', $contracts);
    }

    public function testCandidateOverviewKeepsPrivatePropertyFactsBehindIndependentPropertyPermission(): void
    {
        $candidateId = $this->insertCandidate('PROPERTY-PRIVATE-G3', 'Persona Demo G3');
        $unitId = $this->candidateUnitId($candidateId);
        $update = $this->pdo->prepare('UPDATE units SET monthly_price = :price, area_m2 = :area, bedrooms = :bedrooms WHERE id = :id');
        $update->execute(['price' => '2500000', 'area' => '85', 'bedrooms' => 3, 'id' => $unitId]);

        $photoDir = $this->directory . '/unit-media';
        mkdir($photoDir, 0700, true);
        $filename = 'photo-' . bin2hex(random_bytes(16)) . '.png';
        file_put_contents(
            $photoDir . '/' . $filename,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0ZkAAAAASUVORK5CYII=', true),
        );
        $photoStatement = $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, external_url, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', :stored_name, 'image/png', NULL, 1, 1)",
        );
        $photoStatement->execute(['unit_id' => $unitId, 'stored_name' => $filename]);
        $photoId = (int) $this->pdo->lastInsertId();

        $this->insertUser('g3-viewer@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $viewer = $this->app('/sub');
        $this->login($viewer, 'g3-viewer@example.com', 'ViewerPassword123', '/sub');
        $restricted = $this->request($viewer, 'GET', '/sub/admin/candidates/' . $candidateId);
        self::assertSame(200, $restricted->getStatusCode());
        $viewerHtml = (string) $restricted->getBody();
        self::assertStringContainsString('data-candidate-row="details"', $viewerHtml);
        self::assertStringContainsString('Detalles limitados por permisos', $viewerHtml);
        self::assertStringNotContainsString('Calle 1', $viewerHtml);
        self::assertStringNotContainsString('2.500.000', $viewerHtml);
        self::assertStringNotContainsString('/admin/units/' . $unitId . '/media/', $viewerHtml);
        self::assertSame(403, $this->request($viewer, 'GET', '/sub/admin/units/' . $unitId . '/media/' . $photoId)->getStatusCode());

        $_SESSION = [];
        $this->insertUser('g3-property@example.com', 'PropertyPassword123', 'admin', ['candidates.view', 'properties.view']);
        $allowed = $this->app('/sub');
        $this->login($allowed, 'g3-property@example.com', 'PropertyPassword123', '/sub');
        $full = $this->request($allowed, 'GET', '/sub/admin/candidates/' . $candidateId);
        self::assertSame(200, $full->getStatusCode());
        $propertyHtml = (string) $full->getBody();
        self::assertStringContainsString('Dirección del inmueble', $propertyHtml);
        self::assertStringContainsString('Calle 1', $propertyHtml);
        self::assertMatchesRegularExpression('/<p class="muted candidate-shell__property-address">[^<]*<svg[^>]*>.*?Calle 1, Medellín<\/p>/s', $propertyHtml);
        self::assertMatchesRegularExpression('/<div class="candidate-fact candidate-fact--property-address">.*?Calle 1, Medellín<\/dd><\/dl><\/div>/s', $propertyHtml);
        self::assertStringContainsString('2.500.000', $propertyHtml);
        self::assertStringContainsString('/sub/admin/units/' . $unitId . '/media/' . $photoId, $propertyHtml);
        self::assertSame(200, $this->request($allowed, 'GET', '/sub/admin/units/' . $unitId . '/media/' . $photoId)->getStatusCode());
    }

    public function testHouseholdManualMetadataRequiresCandidatePropertyAndDocumentViewPermissionsTogether(): void
    {
        $candidateId = $this->insertCandidate('HOUSEHOLD-MANUAL-1', 'Candidato Manual');
        $unitId = $this->candidateUnitId($candidateId);
        $propertyId = (int) $this->pdo->query('SELECT property_id FROM units WHERE id = ' . $unitId)->fetchColumn();
        $actorId = $this->insertUser('manual-writer@example.com', 'WriterPassword123', 'owner');
        $documents = new DocumentService($this->pdo, $this->propertyDocumentDirectory);
        $json = $documents->createContentVersion(
            $propertyId,
            null,
            'manual_convivencia',
            'Manual JSON superseded',
            ['sections' => [['title' => 'Ruido', 'body' => 'Respetar los horarios de descanso.']]],
            null,
            $actorId,
            time(),
        );
        $pdf = $documents->createFileVersion(
            $propertyId,
            null,
            'manual_convivencia',
            'Manual PDF actual',
            $this->upload($this->pdfBytes(), 'manual.pdf', 'application/pdf'),
            (int) $json['id'],
            $actorId,
            time() + 1,
        );
        self::assertSame([$pdf['id']], array_column(
            array_values(array_filter($documents->listCurrentForProperty($propertyId), static fn (array $document): bool => $document['type'] === 'manual_convivencia')),
            'id',
        ));

        $restrictedProfiles = [
            ['manual-candidate-only@example.com', ['candidates.view']],
            ['manual-no-property@example.com', ['candidates.view', 'documents.view']],
            ['manual-no-documents@example.com', ['candidates.view', 'properties.view']],
        ];
        foreach ($restrictedProfiles as [$email, $permissions]) {
            $_SESSION = [];
            $this->insertUser($email, 'ViewerPassword123', 'admin', $permissions);
            $app = $this->app('/sub');
            $this->login($app, $email, 'ViewerPassword123', '/sub');
            $html = (string) $this->request($app, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=household')->getBody();
            self::assertStringContainsString('La información del manual no está disponible para este perfil.', $html);
            self::assertStringNotContainsString('Manual PDF actual', $html);
            self::assertStringNotContainsString('Manual JSON superseded', $html);
            self::assertStringNotContainsString('/sub/admin/documents?scope=property', $html);
        }

        $_SESSION = [];
        $this->insertUser('manual-complete@example.com', 'ViewerPassword123', 'admin', [
            'candidates.view', 'properties.view', 'documents.view',
        ]);
        $authorized = $this->app('/sub');
        $this->login($authorized, 'manual-complete@example.com', 'ViewerPassword123', '/sub');
        $html = (string) $this->request($authorized, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=household')->getBody();
        self::assertStringContainsString('Manual PDF actual', $html);
        self::assertStringNotContainsString('Manual JSON superseded', $html);
        self::assertStringContainsString('/sub/admin/documents?scope=property&amp;id=' . $propertyId, $html);
        self::assertStringContainsString('Versión #' . $pdf['id'], $html);
    }

    public function testHouseholdAdminCrudAndPrivatePhotoRouteAreCandidateScoped(): void
    {
        $candidateId = $this->insertCandidate('HOUSEHOLD-CRUD-1', 'Candidato Hogar');
        $otherCandidateId = $this->insertCandidate('HOUSEHOLD-CRUD-2', 'Otro Candidato');
        $household = new CandidateHouseholdService($this->pdo, $this->householdDirectory);
        $seedRecords = [
            [
                'kind' => 'occupant',
                'name' => 'Acompañante Con Foto',
                'table' => 'candidate_household_occupants',
                'data' => ['full_name' => 'Acompañante Con Foto', 'relationship' => 'Hermano'],
                'routeData' => ['full_name' => 'Nueva Ficha Ocupante', 'relationship' => 'Hermana'],
                'photoName' => 'occupant.png',
            ],
            [
                'kind' => 'pet',
                'name' => 'Luna',
                'table' => 'candidate_household_pets',
                'data' => ['name' => 'Luna', 'species' => 'Gato'],
                'routeData' => ['name' => 'Sol', 'species' => 'Perro'],
                'photoName' => 'pet.png',
            ],
        ];
        $seedRecords = array_map(function (array $record) use ($household, $candidateId): array {
            $record['seed'] = $household->saveRecord(
                $record['kind'],
                $candidateId,
                null,
                [...$record['data'], 'photo_consent' => '1'],
                $this->upload($this->pngBytes(), $record['photoName'], 'image/png'),
                time(),
            );
            return $record;
        }, $seedRecords);

        $this->insertUser('household-viewer@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $viewer = $this->app('/sub');
        $this->login($viewer, 'household-viewer@example.com', 'ViewerPassword123', '/sub');
        $page = (string) $this->request($viewer, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=household')->getBody();
        self::assertStringContainsString('Acompañante Con Foto', $page);
        self::assertStringNotContainsString('Agregar o editar ocupantes', $page);
        foreach ($seedRecords as $record) {
            $photoPath = '/sub/admin/candidates/' . $candidateId . '/household/' . $record['kind'] . '/' . $record['seed']['id'] . '/photo';
            $photo = $this->request($viewer, 'GET', $photoPath);
            self::assertSame(200, $photo->getStatusCode(), $record['kind']);
            self::assertSame('image/png', $photo->getHeaderLine('Content-Type'), $record['kind']);
            self::assertSame('nosniff', $photo->getHeaderLine('X-Content-Type-Options'), $record['kind']);
            self::assertSame('private, no-store', $photo->getHeaderLine('Cache-Control'), $record['kind']);
            self::assertGreaterThan(0, strlen((string) $photo->getBody()), $record['kind']);
            self::assertSame(404, $this->request(
                $viewer,
                'GET',
                '/sub/admin/candidates/' . $otherCandidateId . '/household/' . $record['kind'] . '/' . $record['seed']['id'] . '/photo',
            )->getStatusCode(), $record['kind'] . ' photos must remain candidate-scoped.');
            self::assertSame(403, $this->request($viewer, 'POST', '/sub/admin/candidates/' . $candidateId . '/household/' . $record['kind'], [
                ...$record['routeData'],
            ], true, ['photo' => $this->upload($this->pngBytes(), 'forbidden.png', 'image/png')])->getStatusCode(), $record['kind']);
        }

        $_SESSION = [];
        $this->insertUser('household-no-view@example.com', 'NoViewPassword123', 'admin', ['properties.view']);
        $noView = $this->app('/sub');
        $this->login($noView, 'household-no-view@example.com', 'NoViewPassword123', '/sub');
        foreach ($seedRecords as $record) {
            self::assertSame(403, $this->request(
                $noView,
                'GET',
                '/sub/admin/candidates/' . $candidateId . '/household/' . $record['kind'] . '/' . $record['seed']['id'] . '/photo',
            )->getStatusCode(), $record['kind'] . ' photos require candidates.view.');
        }

        $_SESSION = [];
        $this->insertUser('household-manager@example.com', 'ManagerPassword123', 'admin', ['candidates.view', 'candidates.manage']);
        $manager = $this->app('/sub');
        $this->login($manager, 'household-manager@example.com', 'ManagerPassword123', '/sub');
        $managerPage = (string) $this->request($manager, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=household')->getBody();
        self::assertStringContainsString('Agregar o editar ocupantes', $managerPage);
        self::assertStringNotContainsString('Manual PDF actual', $managerPage);

        foreach ($seedRecords as $record) {
            $collectionPath = '/sub/admin/candidates/' . $candidateId . '/household/' . $record['kind'];
            $created = $this->request($manager, 'POST', $collectionPath, $record['routeData']);
            self::assertSame(303, $created->getStatusCode(), $record['kind']);
            $statement = $this->pdo->prepare('SELECT id FROM ' . $record['table'] . ' WHERE candidate_id = :candidate_id ORDER BY id DESC LIMIT 1');
            $statement->execute(['candidate_id' => $candidateId]);
            $createdId = (int) $statement->fetchColumn();
            $editedField = $record['kind'] === 'occupant' ? 'full_name' : 'name';
            $editedValue = $record['kind'] === 'occupant' ? 'Ficha Ocupante Editada' : 'Ficha Mascota Editada';
            $editBody = [...$record['routeData'], 'record_id' => (string) $createdId, $editedField => $editedValue];
            $edited = $this->request($manager, 'POST', $collectionPath, $editBody);
            self::assertSame(303, $edited->getStatusCode(), $record['kind'] . ' editing must redirect.');
            $persisted = $this->pdo->prepare(
                'SELECT ' . $editedField . ' FROM ' . $record['table'] . ' WHERE id = :id AND candidate_id = :candidate_id',
            );
            $persisted->execute(['id' => $createdId, 'candidate_id' => $candidateId]);
            self::assertSame($editedValue, $persisted->fetchColumn(), $record['kind'] . ' edited field must persist.');
            $record['routeData'][$editedField] = $editedValue;
            $photoPath = $collectionPath . '/' . $createdId . '/photo';
            self::assertSame(404, $this->request($manager, 'GET', $photoPath)->getStatusCode(), $record['kind'] . ' without a photo must return 404.');

            $uploadBody = [...$record['routeData'], 'record_id' => (string) $createdId, 'photo_consent' => '1'];
            $uploaded = $this->request($manager, 'POST', $collectionPath, $uploadBody, true, [
                'photo' => $this->upload($this->pngBytes(), $record['kind'] . '.png', 'image/png'),
            ]);
            self::assertSame(303, $uploaded->getStatusCode(), $record['kind'] . ' upload');
            $stored = $this->pdo->prepare('SELECT photo_stored_name FROM ' . $record['table'] . ' WHERE id = :id AND candidate_id = :candidate_id');
            $stored->execute(['id' => $createdId, 'candidate_id' => $candidateId]);
            $firstStoredPath = $this->householdDirectory . '/' . $stored->fetchColumn();
            self::assertFileExists($firstStoredPath, $record['kind'] . ' upload must create private storage.');

            $replacement = $this->request($manager, 'POST', $collectionPath, $uploadBody, true, [
                'photo' => $this->upload($this->jpegBytes(), $record['kind'] . '-replacement.jpg', 'image/jpeg'),
            ]);
            self::assertSame(303, $replacement->getStatusCode(), $record['kind'] . ' replacement');
            $stored->execute(['id' => $createdId, 'candidate_id' => $candidateId]);
            $replacementName = (string) $stored->fetchColumn();
            $replacementPath = $this->householdDirectory . '/' . $replacementName;
            self::assertNotSame(basename($firstStoredPath), $replacementName, $record['kind'] . ' replacement must rotate the stored file.');
            self::assertFileDoesNotExist($firstStoredPath, $record['kind'] . ' replacement must retire the previous file.');
            self::assertFileExists($replacementPath, $record['kind'] . ' replacement must keep the new private file.');
            $replacementPhoto = $this->request($manager, 'GET', $photoPath);
            self::assertSame(200, $replacementPhoto->getStatusCode(), $record['kind']);
            self::assertSame('image/jpeg', $replacementPhoto->getHeaderLine('Content-Type'), $record['kind']);
            self::assertSame('nosniff', $replacementPhoto->getHeaderLine('X-Content-Type-Options'), $record['kind']);
            self::assertSame('private, no-store', $replacementPhoto->getHeaderLine('Cache-Control'), $record['kind']);

            $removed = $this->request($manager, 'POST', $collectionPath, [
                ...$record['routeData'], 'record_id' => (string) $createdId, 'remove_photo' => '1',
            ]);
            self::assertSame(303, $removed->getStatusCode(), $record['kind'] . ' photo removal');
            self::assertFileDoesNotExist($replacementPath, $record['kind'] . ' removal must retire the private file.');
            self::assertSame(404, $this->request($manager, 'GET', $photoPath)->getStatusCode(), $record['kind'] . ' removed photo');
        }

        $occupantId = (int) $this->pdo->query(
            'SELECT id FROM candidate_household_occupants WHERE candidate_id = ' . $candidateId . ' ORDER BY id DESC LIMIT 1',
        )->fetchColumn();
        $deleted = $this->request($manager, 'POST', '/sub/admin/candidates/' . $candidateId . '/household/occupant', [
            'action' => 'delete', 'record_id' => (string) $occupantId,
        ]);
        self::assertSame(303, $deleted->getStatusCode());
        self::assertCount(1, $household->listOccupants($candidateId));
    }

    public function testHouseholdSaveValidationRendersFullDetailWithScopedTransientInput(): void
    {
        $candidateId = $this->insertCandidate('HOUSEHOLD-VALIDATION-1', 'Candidato de validación');
        $household = new CandidateHouseholdService($this->pdo, $this->householdDirectory);
        $occupant = $household->saveRecord('occupant', $candidateId, null, [
            'full_name' => 'Ocupante Original',
            'relationship' => 'Hermana',
        ], null, time());
        $pet = $household->saveRecord('pet', $candidateId, null, [
            'name' => 'Mascota Original',
            'species' => 'Gato',
        ], null, time());

        $this->insertUser('household-validation@example.com', 'ManagerPassword123', 'admin', ['candidates.view', 'candidates.manage']);
        $app = $this->app('/sub');
        $this->login($app, 'household-validation@example.com', 'ManagerPassword123', '/sub');

        $cases = [
            [
                'kind' => 'occupant',
                'record_id' => (string) $occupant['id'],
                'field' => 'full_name',
                'body' => [
                    'action' => 'save',
                    'record_id' => (string) $occupant['id'],
                    'full_name' => '',
                    'relationship' => 'Relación editada conservada',
                    'notes' => 'Nota editada conservada',
                    'request_secret' => 'NO_RENDER_SECRET_71',
                ],
                'preserved_field' => 'relationship',
                'preserved_value' => 'Relación editada conservada',
                'message' => 'El nombre del ocupante es obligatorio.',
            ],
            [
                'kind' => 'occupant',
                'record_id' => 'new',
                'field' => 'full_name',
                'body' => [
                    'action' => 'save',
                    'full_name' => '',
                    'relationship' => 'Relación nueva conservada',
                    'notes' => 'Nota nueva conservada',
                ],
                'preserved_field' => 'relationship',
                'preserved_value' => 'Relación nueva conservada',
                'message' => 'El nombre del ocupante es obligatorio.',
            ],
            [
                'kind' => 'pet',
                'record_id' => (string) $pet['id'],
                'field' => 'name',
                'body' => [
                    'action' => 'save',
                    'record_id' => (string) $pet['id'],
                    'name' => '',
                    'species' => 'Especie editada conservada',
                    'notes' => 'Nota de mascota conservada',
                ],
                'preserved_field' => 'species',
                'preserved_value' => 'Especie editada conservada',
                'message' => 'El nombre y la especie de la mascota son obligatorios.',
            ],
            [
                'kind' => 'pet',
                'record_id' => 'new',
                'field' => 'name',
                'body' => [
                    'action' => 'save',
                    'name' => '',
                    'species' => 'Especie nueva conservada',
                    'notes' => 'Nota nueva de mascota conservada',
                ],
                'preserved_field' => 'species',
                'preserved_value' => 'Especie nueva conservada',
                'message' => 'El nombre y la especie de la mascota son obligatorios.',
            ],
        ];

        foreach ($cases as $case) {
            $response = $this->request(
                $app,
                'POST',
                '/sub/admin/candidates/' . $candidateId . '/household/' . $case['kind'],
                $case['body'],
            );

            self::assertSame(422, $response->getStatusCode(), $case['kind'] . ' validation status');
            self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'), $case['kind'] . ' validation response type');
            $html = (string) $response->getBody();
            self::assertStringContainsString('<main ', $html, $case['kind'] . ' must retain the application shell');
            self::assertStringContainsString('Candidato de validación', $html);
            self::assertStringContainsString('data-candidate-section-body="household"', $html);
            self::assertStringContainsString('name="csrf_token" value="', $html);
            self::assertStringNotContainsString('NO_RENDER_SECRET_71', $html);
            self::assertSame(1, substr_count($html, $case['message']), $case['kind'] . ' error message should be rendered once');

            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $region = $case['kind'] === 'occupant' ? 'occupants' : 'pets';
            $form = $xpath->query(
                '//section[@data-household-region="' . $region . '"]//form[@data-household-form-kind="'
                . $case['kind'] . '" and @data-household-form-id="' . $case['record_id'] . '"]',
            )->item(0);
            self::assertInstanceOf(\DOMElement::class, $form, $case['kind'] . ' validation must target the correct create/edit form');
            self::assertSame(1, $xpath->query('ancestor::details[@open]', $form)->length, $case['kind'] . ' manager must be open');
            self::assertSame(1, $xpath->query('.//*[@role="alert"]', $form)->length);
            self::assertSame($case['preserved_value'], $xpath->query('.//*[@name="' . $case['preserved_field'] . '"]/@value', $form)->item(0)?->nodeValue);
            $invalidField = $xpath->query('.//*[@name="' . $case['field'] . '"]', $form)->item(0);
            self::assertInstanceOf(\DOMElement::class, $invalidField);
            self::assertSame('true', $invalidField->getAttribute('aria-invalid'));
            self::assertNotSame('', $invalidField->getAttribute('aria-describedby'));
            self::assertTrue($invalidField->hasAttribute('autofocus'));
        }

        $detail = (string) $this->request($app, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=household')->getBody();
        self::assertStringNotContainsString('Relación editada conservada', $detail);
        self::assertStringNotContainsString('Relación nueva conservada', $detail);
        self::assertStringNotContainsString('Especie editada conservada', $detail);
        self::assertStringNotContainsString('Especie nueva conservada', $detail);
        self::assertStringNotContainsString('data-household-validation-error', $detail);
        self::assertSame(1, count($household->listOccupants($candidateId)), 'Invalid create must not persist an occupant.');
        self::assertSame(1, count($household->listPets($candidateId)), 'Invalid create must not persist a pet.');
        self::assertSame('Ocupante Original', $household->listOccupants($candidateId)[0]['full_name']);
        self::assertSame('Mascota Original', $household->listPets($candidateId)[0]['name']);
    }

    public function testHouseholdPhotoValidationTargetsFileAndConsentWithoutRetainingUpload(): void
    {
        $candidateId = $this->insertCandidate('HOUSEHOLD-PHOTO-VALIDATION-1', 'Candidato foto');
        $household = new CandidateHouseholdService($this->pdo, $this->householdDirectory);
        $pet = $household->saveRecord('pet', $candidateId, null, [
            'name' => 'Mascota Original',
            'species' => 'Gato',
        ], null, time());

        $this->insertUser('household-photo-validation@example.com', 'ManagerPassword123', 'admin', ['candidates.view', 'candidates.manage']);
        $app = $this->app('/sub');
        $this->login($app, 'household-photo-validation@example.com', 'ManagerPassword123', '/sub');

        $cases = [
            [
                'kind' => 'occupant',
                'record_id' => 'new',
                'field' => 'photo_consent',
                'message' => 'Confirma que cuentas con autorización para guardar esta fotografía.',
                'body' => ['full_name' => 'Ocupante Foto', 'relationship' => 'Amiga'],
                'photo' => $this->upload($this->pngBytes(), 'DO_NOT_RENDER_CONSENT.png', 'image/png'),
                'consent_checked' => false,
            ],
            [
                'kind' => 'pet',
                'record_id' => (string) $pet['id'],
                'field' => 'photo',
                'message' => 'La fotografía debe ser una imagen PNG o JPEG válida.',
                'body' => [
                    'record_id' => (string) $pet['id'],
                    'name' => 'Mascota Editada',
                    'species' => 'Gato',
                    'photo_consent' => '1',
                    'unlisted_secret' => 'NO_RENDER_PHOTO_SECRET',
                ],
                'photo' => $this->upload('not an image', 'DO_NOT_RENDER_INVALID.png', 'image/png'),
                'consent_checked' => true,
            ],
        ];

        foreach ($cases as $case) {
            $response = $this->request(
                $app,
                'POST',
                '/sub/admin/candidates/' . $candidateId . '/household/' . $case['kind'],
                ['action' => 'save', ...$case['body']],
                true,
                ['photo' => $case['photo']],
            );

            self::assertSame(422, $response->getStatusCode());
            $html = (string) $response->getBody();
            self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
            self::assertSame(1, substr_count($html, $case['message']));
            self::assertStringNotContainsString('DO_NOT_RENDER_', $html);
            self::assertStringNotContainsString('NO_RENDER_PHOTO_SECRET', $html);

            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $region = $case['kind'] === 'occupant' ? 'occupants' : 'pets';
            $form = $xpath->query(
                '//section[@data-household-region="' . $region . '"]//form[@data-household-form-kind="'
                . $case['kind'] . '" and @data-household-form-id="' . $case['record_id'] . '"]',
            )->item(0);
            self::assertInstanceOf(\DOMElement::class, $form);
            self::assertSame(1, $xpath->query('ancestor::details[@open]', $form)->length);
            self::assertSame(1, $xpath->query('.//*[@role="alert"]', $form)->length);
            $invalidControl = $xpath->query('.//*[@name="' . $case['field'] . '"]', $form)->item(0);
            self::assertInstanceOf(\DOMElement::class, $invalidControl);
            self::assertSame('true', $invalidControl->getAttribute('aria-invalid'));
            self::assertNotSame('', $invalidControl->getAttribute('aria-describedby'));
            self::assertTrue($invalidControl->hasAttribute('autofocus'));
            if ($case['field'] === 'photo_consent') {
                self::assertSame('', $invalidControl->getAttribute('checked'));
            }
        }

        self::assertSame([], $household->listOccupants($candidateId));
        self::assertSame('Mascota Original', $household->listPets($candidateId)[0]['name']);
        self::assertNull($household->privatePhoto($candidateId, 'pet', (int) $pet['id']));
    }

    public function testWorkAndDocumentsFromBothBranchesRemainAccessible(): void
    {
        $candidateId = $this->insertCandidate('INTEGRATION-WORK-DOC', 'Candidato Integración');
        $this->pdo->prepare(
            'UPDATE candidates SET employment_status = :status, employer_name = :employer, '
            . 'occupation = :occupation, monthly_income = :income WHERE id = :id',
        )->execute([
            'status' => 'Empleado',
            'employer' => 'Empresa de Prueba',
            'occupation' => 'Contador',
            'income' => '5200000',
            'id' => $candidateId,
        ]);
        $documents = new CandidateDocumentService($this->pdo, $this->documentDirectory);
        $documents->createRequirement($this->candidateUnitId($candidateId), [
            'code' => 'income_certificate',
            'label' => 'Certificación de ingresos',
            'is_required' => 1,
            'is_active' => 1,
        ]);

        $this->insertUser('integration-viewer@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $app = $this->app('/sub');
        $this->login($app, 'integration-viewer@example.com', 'ViewerPassword123', '/sub');
        $work = (string) $this->request($app, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=work')->getBody();
        self::assertStringContainsString('data-candidate-section-body="work"', $work);
        self::assertSame(1, substr_count($work, 'data-candidate-section-body='));
        self::assertStringContainsString('Empresa de Prueba', $work);
        self::assertStringContainsString('Ingresos declarados', $work);
        self::assertStringNotContainsString('candidate-documents-layout', $work);

        $documentPage = (string) $this->request($app, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=documents')->getBody();
        self::assertStringContainsString('data-candidate-section-body="documents"', $documentPage);
        self::assertSame(1, substr_count($documentPage, 'data-candidate-section-body='));
        self::assertStringContainsString('Certificación de ingresos', $documentPage);
        self::assertStringContainsString('Pendiente de recibir', $documentPage);
        self::assertStringContainsString('candidate-documents-layout', $documentPage);
        self::assertStringNotContainsString('candidate-work-panel', $documentPage);
    }

    public function testDocumentSectionUsesActiveUnitRequirementsWithoutFakeApproval(): void
    {
        $candidateId = $this->insertCandidate('DOC-TRUTH-01', 'Candidato Documentos');
        $unitId = $this->candidateUnitId($candidateId);
        $documents = new CandidateDocumentService($this->pdo, $this->documentDirectory);
        $received = $this->addDocument($candidateId, 'documento-real.pdf');
        $documents->createRequirement($unitId, [
            'code' => 'income',
            'label' => '<script>Ingresos</script>',
            'description' => 'Comprobante de ingresos',
            'is_required' => 1,
            'is_active' => 1,
        ]);
        $documents->createRequirement($unitId, [
            'code' => 'pets',
            'label' => 'Soporte mascota',
            'is_required' => 0,
            'is_active' => 1,
        ]);
        $documents->createRequirement($unitId, [
            'code' => 'obsolete',
            'label' => 'REQUISITO_INACTIVO_NO_MOSTRAR',
            'is_required' => 1,
            'is_active' => 0,
        ]);

        $this->insertUser('doc-viewer@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $app = $this->app('/sub');
        $this->login($app, 'doc-viewer@example.com', 'ViewerPassword123', '/sub');
        $response = $this->request($app, 'GET', '/sub/admin/candidates/' . $candidateId . '?section=documents');
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('class="candidate-documents-layout"', $html);
        self::assertStringContainsString('class="candidate-document-list"', $html);
        self::assertSame(3, substr_count($html, 'class="candidate-document-row" data-document-status='));
        self::assertSame(1, substr_count($html, 'data-document-status="pending"'));
        self::assertSame(1, substr_count($html, 'data-document-status="optional"'));
        self::assertStringContainsString('aria-label="Resumen documental"', $html);
        self::assertStringContainsString('<progress value="1" max="2"', $html);
        self::assertStringContainsString('Requisitos pendientes', $html);
        self::assertStringNotContainsString('class="users-grid" aria-label="Requisitos documentales activos"', $html);
        self::assertStringContainsString('data-document-required-count="2"', $html);
        self::assertStringContainsString('data-document-received-count="1"', $html);
        self::assertStringContainsString('1 de 2 requisitos obligatorios recibidos', $html);
        self::assertStringContainsString('Identidad', $html);
        self::assertStringContainsString('documento-real.pdf', $html);
        self::assertStringContainsString('/sub/admin/candidates/' . $candidateId . '/documents/' . $received['id'], $html);
        self::assertStringContainsString('&lt;script&gt;Ingresos&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>Ingresos</script>', $html);
        self::assertStringContainsString('Pendiente de recibir', $html);
        self::assertStringContainsString('Opcional, no aportado', $html);
        self::assertStringNotContainsString('REQUISITO_INACTIVO_NO_MOSTRAR', $html);
        self::assertStringNotContainsString('Aprobado', $html);
        self::assertStringNotContainsString('En revisión', $html);
        self::assertStringNotContainsString('Subir documento', $html);
        self::assertStringNotContainsString($this->documentDirectory, $html);
    }

    public function testDocumentSectionWithoutRequirementsRetainsHonestEmptyState(): void
    {
        $candidateId = $this->insertCandidate('DOC-TRUTH-EMPTY', 'Candidato sin documentos');
        $this->insertUser('doc-empty@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $app = $this->app();
        $this->login($app, 'doc-empty@example.com', 'ViewerPassword123');

        $html = (string) $this->request($app, 'GET', '/admin/candidates/' . $candidateId . '?section=documents')->getBody();
        self::assertStringContainsString('No hay requisitos documentales activos configurados', $html);
        self::assertStringContainsString('No hay documentos adjuntos.', $html);
        self::assertStringNotContainsString('de 0 requisitos obligatorios', $html);
    }

    public function testListAndDetailEscapeCandidateReferenceAndOriginalFilename(): void
    {
        $candidateId = $this->insertCandidate('ESC-1', '<script>alert(1)</script>');
        $this->pdo->prepare(
            'INSERT INTO candidate_references (candidate_id, reference_type, name, relationship, phone, email) '
            . 'VALUES (:candidate_id, :type, :name, :relationship, :phone, :email)',
        )->execute([
            'candidate_id' => $candidateId,
            'type' => 'personal',
            'name' => '<b>Referencia</b>',
            'relationship' => 'Amigo',
            'phone' => '3001112233',
            'email' => 'ref@example.com',
        ]);
        $document = $this->addDocument($candidateId, '<img src=x onerror=alert(1)>.pdf');

        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $app = $this->app();
        $this->login($app, 'viewer@example.com', 'ViewerPassword123');

        $list = $this->request($app, 'GET', '/admin/candidates');
        self::assertSame(200, $list->getStatusCode());
        $listHtml = (string) $list->getBody();
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $listHtml);
        self::assertStringNotContainsString('<script>alert(1)</script>', $listHtml);

        $references = $this->request($app, 'GET', '/admin/candidates/' . $candidateId . '?section=references');
        self::assertSame(200, $references->getStatusCode());
        $referencesHtml = (string) $references->getBody();
        self::assertStringContainsString('&lt;b&gt;Referencia&lt;/b&gt;', $referencesHtml);
        self::assertStringNotContainsString('<b>Referencia</b>', $referencesHtml);

        $documents = $this->request($app, 'GET', '/admin/candidates/' . $candidateId . '?section=documents');
        self::assertSame(200, $documents->getStatusCode());
        $documentsHtml = (string) $documents->getBody();
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;.pdf', $documentsHtml);
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $documentsHtml);
        self::assertStringContainsString('/admin/candidates/' . $candidateId . '/documents/' . $document['id'], $documentsHtml);
        self::assertStringNotContainsString('stored_name', $documentsHtml);
        self::assertStringNotContainsString($this->documentDirectory, $documentsHtml);
    }

    public function testStatusMutationRequiresManageAndCannotSimulatePublicSubmit(): void
    {
        $candidateId = $this->insertCandidate('STATUS-1', 'Estado Candidato');
        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $viewerApp = $this->app();
        $this->login($viewerApp, 'viewer@example.com', 'ViewerPassword123');
        self::assertSame(403, $this->request($viewerApp, 'POST', '/admin/candidates/' . $candidateId . '/status', [
            'status' => 'withdrawn',
        ])->getStatusCode());
        self::assertSame('new', $this->candidateStatus($candidateId));

        $_SESSION = [];
        $this->insertUser('manager@example.com', 'ManagerPassword123', 'admin', ['candidates.view', 'candidates.manage']);
        $managerApp = $this->app();
        $this->login($managerApp, 'manager@example.com', 'ManagerPassword123');
        $invalid = $this->request($managerApp, 'POST', '/admin/candidates/' . $candidateId . '/status', [
            'status' => 'verification',
        ]);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame('new', $this->candidateStatus($candidateId));

        $withdraw = $this->request($managerApp, 'POST', '/admin/candidates/' . $candidateId . '/status', [
            'status' => 'withdrawn',
        ]);
        self::assertSame(303, $withdraw->getStatusCode());
        self::assertSame('/admin/candidates/' . $candidateId, $withdraw->getHeaderLine('Location'));
        self::assertSame('withdrawn', $this->candidateStatus($candidateId));
    }

    public function testPrivateDocumentReadRequiresViewAndRejectsCrossCandidateIdor(): void
    {
        $candidateA = $this->insertCandidate('DOC-A', 'Candidato A');
        $unitId = $this->candidateUnitId($candidateA);
        $candidateB = $this->insertCandidate('DOC-B', 'Candidato B', 'new', null, $unitId);
        $documents = new CandidateDocumentService($this->pdo, $this->documentDirectory);
        $documents->createRequirement($unitId, [
            'code' => 'identity',
            'label' => 'Identidad',
            'is_required' => 0,
            'is_active' => 1,
        ]);
        $documentA = $documents->addOrReplaceDocument(
            $candidateA,
            'identity',
            $this->upload($this->pdfBytes(), 'a.pdf', 'application/pdf'),
        );
        $documentB = $documents->addOrReplaceDocument(
            $candidateB,
            'identity',
            $this->upload($this->pdfBytes(), 'b.pdf', 'application/pdf'),
        );

        $this->insertUser('viewer@example.com', 'ViewerPassword123', 'admin', ['candidates.view']);
        $app = $this->app();
        $this->login($app, 'viewer@example.com', 'ViewerPassword123');

        $owned = $this->request($app, 'GET', '/admin/candidates/' . $candidateA . '/documents/' . $documentA['id']);
        self::assertSame(200, $owned->getStatusCode());
        self::assertSame('application/pdf', $owned->getHeaderLine('Content-Type'));
        self::assertSame('nosniff', $owned->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('private, no-store', $owned->getHeaderLine('Cache-Control'));
        self::assertSame($this->pdfBytes(), (string) $owned->getBody());
        self::assertSame(404, $this->request(
            $app,
            'GET',
            '/admin/candidates/' . $candidateA . '/documents/' . $documentB['id'],
        )->getStatusCode());

        $_SESSION = [];
        $this->insertUser('restricted@example.com', 'RestrictedPassword123', 'admin', ['properties.view']);
        $restrictedApp = $this->app();
        $this->login($restrictedApp, 'restricted@example.com', 'RestrictedPassword123');
        self::assertSame(403, $this->request(
            $restrictedApp,
            'GET',
            '/admin/candidates/' . $candidateA . '/documents/' . $documentA['id'],
        )->getStatusCode());
    }

    public function testTokenRegenerationShowsNewRawUrlOnceStoresOnlyHashAndInvalidatesOldToken(): void
    {
        $oldToken = str_repeat('a', 64);
        $candidateId = $this->insertCandidate('TOKEN-1', 'Token Candidato', 'new', $oldToken);
        $this->insertUser('manager@example.com', 'ManagerPassword123', 'admin', ['candidates.view', 'candidates.manage']);
        $app = $this->app('/sub', 'https://tenant.example.test/sub');
        $this->login($app, 'manager@example.com', 'ManagerPassword123', '/sub');

        $response = $this->request($app, 'POST', '/sub/admin/candidates/' . $candidateId . '/token/regenerate');
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertMatchesRegularExpression('#https://tenant\.example\.test/sub/apply/continue/([a-f0-9]{64})#', $html);
        preg_match('#/apply/continue/([a-f0-9]{64})#', $html, $matches);
        $newToken = (string) ($matches[1] ?? '');
        self::assertNotSame('', $newToken);
        self::assertNotSame($oldToken, $newToken);
        self::assertStringNotContainsString($oldToken, $html);

        $hash = (string) $this->pdo->query(
            'SELECT continuation_token_hash FROM candidates WHERE id = ' . $candidateId,
        )->fetchColumn();
        self::assertSame(hash('sha256', $newToken), $hash);
        self::assertNotSame($newToken, $hash);
        self::assertSame(404, $this->request($app, 'GET', '/sub/apply/continue/' . $oldToken)->getStatusCode());
        self::assertSame(200, $this->request($app, 'GET', '/sub/apply/continue/' . $newToken . '/personal')->getStatusCode());

        $detailHtml = (string) $this->request($app, 'GET', '/sub/admin/candidates/' . $candidateId)->getBody();
        self::assertStringNotContainsString($newToken, $detailHtml);
    }

    private function app(string $basePath = '', ?string $baseUrl = null): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';
        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => $baseUrl ?? ('https://alquilapro.sierraglobalcompany.com' . $basePath),
            'base_path' => $basePath,
            'logo_directory' => $this->directory . '/branding',
            'unit_media_directory' => $this->directory . '/unit-media',
            'candidate_document_directory' => $this->documentDirectory,
            'document_directory' => $this->propertyDocumentDirectory,
            'candidate_household_media_directory' => $this->householdDirectory,
            'pdo' => $this->pdo,
        ]);
    }

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        bool $withCsrf = true,
        array $uploads = [],
    ): ResponseInterface
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
        if ($uploads !== []) {
            $request = $request->withUploadedFiles($uploads);
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
            'full_name' => ucfirst($role) . ' Test',
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'permissions_json' => $permissions === [] ? null : json_encode($permissions, JSON_THROW_ON_ERROR),
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function insertCandidate(
        string $documentNumber,
        string $fullName,
        string $status = 'new',
        ?string $rawToken = null,
        ?int $unitId = null,
    ): int {
        if ($unitId === null) {
            $property = $this->pdo->prepare(
                "INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, 'Calle 1', 'Medellín', 'Calasanz', 1)",
            );
            $property->execute(['name' => 'Propiedad ' . $documentNumber]);
            $propertyId = (int) $this->pdo->lastInsertId();
            $unit = $this->pdo->prepare(
                "INSERT INTO units (property_id, public_key, type, code, status, is_public) "
                . "VALUES (:property_id, :public_key, 'apartment', :code, 'available', 0)",
            );
            $unit->execute([
                'property_id' => $propertyId,
                'public_key' => bin2hex(random_bytes(12)),
                'code' => 'A-' . substr(hash('sha256', $documentNumber), 0, 8),
            ]);
            $unitId = (int) $this->pdo->lastInsertId();
        }

        $token = $rawToken ?? bin2hex(random_bytes(32));
        $candidate = $this->pdo->prepare(
            'INSERT INTO candidates '
            . '(unit_id, document_type, document_number, full_name, phone, email, status, continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :status, :token_hash, :expires_at, :consent_at)',
        );
        $candidate->execute([
            'unit_id' => $unitId,
            'document_type' => 'cc',
            'document_number' => $documentNumber,
            'full_name' => $fullName,
            'phone' => '3001234567',
            'email' => strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $documentNumber) ?? 'candidate') . '@example.com',
            'status' => $status,
            'token_hash' => hash('sha256', $token),
            'expires_at' => '2099-01-01 00:00:00',
            'consent_at' => '2026-10-12 12:00:00',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function addDocument(int $candidateId, string $name): array
    {
        $documents = new CandidateDocumentService($this->pdo, $this->documentDirectory);
        $documents->createRequirement($this->candidateUnitId($candidateId), [
            'code' => 'identity',
            'label' => 'Identidad',
            'is_required' => 1,
            'is_active' => 1,
        ]);
        return $documents->addOrReplaceDocument(
            $candidateId,
            'identity',
            $this->upload($this->pdfBytes(), $name, 'application/pdf'),
        );
    }

    private function upload(string $bytes, string $clientName, string $clientType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'alquila_candidate_admin_upload_');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            self::fail('Unable to create candidate admin upload fixture.');
        }
        $this->temporaryFiles[] = $path;
        return new UploadedFile($path, $clientName, $clientType, strlen($bytes), UPLOAD_ERR_OK, false);
    }

    private function pdfBytes(): string
    {
        return \AlquilaPro\Tests\Support\PdfFixtures::validPdf();
    }

    private function pngBytes(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    }

    private function jpegBytes(): string
    {
        $image = imagecreatetruecolor(2, 2);
        if ($image === false) {
            throw new \RuntimeException('No fue posible generar el JPEG de prueba.');
        }
        ob_start();
        $encoded = imagejpeg($image, null, 85);
        $bytes = ob_get_clean();
        unset($image);
        if (!$encoded || !is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('No fue posible generar el JPEG de prueba.');
        }

        return $bytes;
    }

    private function candidateUnitId(int $candidateId): int
    {
        return (int) $this->pdo->query('SELECT unit_id FROM candidates WHERE id = ' . $candidateId)->fetchColumn();
    }

    private function candidateStatus(int $candidateId): string
    {
        return (string) $this->pdo->query('SELECT status FROM candidates WHERE id = ' . $candidateId)->fetchColumn();
    }
}
