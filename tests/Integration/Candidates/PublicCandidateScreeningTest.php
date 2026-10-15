<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

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

final class PublicCandidateScreeningTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_screening_public_' . bin2hex(random_bytes(6));
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

    public function testPendingScreeningShowsSafeExternalPortalWithoutTokenOrBasePath(): void
    {
        [$candidateId, $rawToken] = $this->insertCandidate('PUBLIC-1', 'pending_screening');
        $responsibleUserId = $this->insertUser('responsible-public-1@example.com');
        $service = $this->screening();
        $service->createForCandidate($candidateId, $responsibleUserId, [
            'provider' => 'SURA',
            'product' => 'Arrendamiento',
            'contact_email' => 'contacto@example.com',
            'portal_url' => 'https://aseguradora.example/study/abc123',
        ], 1791813600);
        $service->updateForCandidate($candidateId, ['status' => 'in_progress'], 1791813610);

        $response = $this->get($this->app('/sub'), '/sub/apply/continue/' . $rawToken . '/review');
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('Estudio de aseguradora', $html);
        self::assertStringContainsString('SURA', $html);
        self::assertStringContainsString('Arrendamiento', $html);
        self::assertStringContainsString('En estudio', $html);
        self::assertStringContainsString('contacto@example.com', $html);
        self::assertStringContainsString(
            'href="https://aseguradora.example/study/abc123" target="_blank" rel="noopener noreferrer"',
            $html,
        );
        self::assertStringNotContainsString('/subhttps://aseguradora.example', $html);
        self::assertStringNotContainsString('https://aseguradora.example/study/abc123' . $rawToken, $html);
    }

    public function testPublicScreeningEscapesAllowlistedValuesAndNeverLeaksPrivateFields(): void
    {
        [$candidateId, $rawToken] = $this->insertCandidate('PUBLIC-XSS', 'pending_screening');
        $responsibleUserId = $this->insertUser('responsible-public-xss@example.com');
        $service = $this->screening();
        $service->createForCandidate($candidateId, $responsibleUserId, [
            'provider' => '<script>alert(1)</script>',
            'product' => '<b>Producto visible</b>',
            'contact_email' => 'visible@example.com',
            'portal_url' => 'https://aseguradora.example/safe',
            'notes' => 'PRIVATE-NOTES-SENTINEL',
            'support_reference' => 'PRIVATE-SUPPORT-SENTINEL',
        ], 1791813600);

        $html = (string) $this->get($this->app(), '/apply/continue/' . $rawToken . '/review')->getBody();

        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;b&gt;Producto visible&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('PRIVATE-NOTES-SENTINEL', $html);
        self::assertStringNotContainsString('PRIVATE-SUPPORT-SENTINEL', $html);
        self::assertStringNotContainsString('responsible_user_id', $html);
        self::assertStringNotContainsString('candidate_id', $html);
        self::assertStringNotContainsString('screening_processes', $html);
    }

    public function testMissingPortalUrlShowsNeutralFallback(): void
    {
        [$candidateId, $rawToken] = $this->insertCandidate('PUBLIC-2', 'pending_screening');
        $responsibleUserId = $this->insertUser('responsible-public-2@example.com');
        $this->screening()->createForCandidate($candidateId, $responsibleUserId, [
            'provider' => 'SURA',
        ], 1791813600);

        $html = (string) $this->get($this->app(), '/apply/continue/' . $rawToken . '/review')->getBody();
        self::assertStringContainsString('Estudio de aseguradora', $html);
        self::assertStringContainsString('Pendiente', $html);
        self::assertStringContainsString('No hay enlace externo disponible para este estudio.', $html);
    }

    public function testTerminalScreeningShowsResultAndHidesExternalPortal(): void
    {
        [$candidateId, $rawToken] = $this->insertCandidate('PUBLIC-3', 'pending_screening');
        $responsibleUserId = $this->insertUser('responsible-public-3@example.com');
        $service = $this->screening();
        $service->createForCandidate($candidateId, $responsibleUserId, [
            'provider' => 'SURA',
            'portal_url' => 'https://aseguradora.example/study/terminal',
        ], 1791813600);
        $service->updateForCandidate($candidateId, [
            'status' => 'approved',
            'result' => '<strong>Aprobado final</strong>',
        ], 1791813620);

        $html = (string) $this->get($this->app(), '/apply/continue/' . $rawToken . '/review')->getBody();
        self::assertStringContainsString('Aprobado', $html);
        self::assertStringContainsString('&lt;strong&gt;Aprobado final&lt;/strong&gt;', $html);
        self::assertStringNotContainsString('href="https://aseguradora.example/study/terminal"', $html);
    }

    public function testWithdrawnCandidateKeepsScreeningVisibleButHidesExternalPortal(): void
    {
        [$candidateId, $rawToken] = $this->insertCandidate('PUBLIC-4', 'pending_screening');
        $responsibleUserId = $this->insertUser('responsible-public-4@example.com');
        $this->screening()->createForCandidate($candidateId, $responsibleUserId, [
            'provider' => 'SURA',
            'product' => 'Visible tras retiro',
            'portal_url' => 'https://aseguradora.example/study/withdrawn',
        ], 1791813600);
        $this->pdo->exec("UPDATE candidates SET status = 'withdrawn' WHERE id = " . $candidateId);

        $html = (string) $this->get($this->app(), '/apply/continue/' . $rawToken . '/review')->getBody();
        self::assertStringContainsString('Estudio de aseguradora', $html);
        self::assertStringContainsString('Visible tras retiro', $html);
        self::assertStringNotContainsString('href="https://aseguradora.example/study/withdrawn"', $html);
    }

    public function testInvalidContinuationTokenRemainsFailClosedAndSubfolderCatalogLinkSurvives(): void
    {
        $app = $this->app('/sub');
        self::assertSame(404, $this->get($app, '/sub/apply/continue/' . str_repeat('a', 64))->getStatusCode());

        [$candidateId, $rawToken] = $this->insertCandidate('PUBLIC-5', 'pending_screening');
        $responsibleUserId = $this->insertUser('responsible-public-5@example.com');
        $this->screening()->createForCandidate($candidateId, $responsibleUserId, ['provider' => 'SURA'], 1791813600);

        $html = (string) $this->get($app, '/sub/apply/continue/' . $rawToken . '/review')->getBody();
        self::assertStringContainsString('href="/sub/catalog"', $html);
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

    private function get(App $app, string $path): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );

        return $app->handle($request);
    }

    private function screening(): ScreeningService
    {
        return new ScreeningService($this->pdo, new CandidateService($this->pdo));
    }

    private function insertUser(string $email): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1)',
        );
        $statement->execute([
            'role' => 'admin',
            'full_name' => 'Responsable Público',
            'email' => $email,
            'password_hash' => password_hash('TemporaryPassword123', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{0:int,1:string} */
    private function insertCandidate(string $documentNumber, string $status): array
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

        $rawToken = bin2hex(random_bytes(32));
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
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => '2099-01-01 00:00:00',
            'consent_at' => '2026-10-12 12:00:00',
        ]);

        return [(int) $this->pdo->lastInsertId(), $rawToken];
    }
}
