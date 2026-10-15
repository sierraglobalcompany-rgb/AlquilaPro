<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CandidateWizardTest extends TestCase
{
    private const STEPS = ['personal', 'work', 'occupants', 'references', 'documents', 'review'];

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

        $this->directory = sys_get_temp_dir() . '/alquila_candidate_wizard_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        (new InstallationLock($this->lockPath))->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-15T00:00:00-05:00',
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testStartAndSixContinuationStepsExposeOneSevenStepFlow(): void
    {
        [$unit, $token, $candidateId] = $this->startThroughPublicRoute();
        $app = $this->app();
        $labels = ['Inicio', 'Datos personales', 'Trabajo', 'Ocupantes', 'Referencias', 'Documentos', 'Revisión'];

        $start = $this->request($app, 'GET', '/apply/' . $unit['public_key']);
        self::assertSame(200, $start->getStatusCode());
        foreach ($labels as $label) {
            self::assertStringContainsString($label, (string) $start->getBody());
        }

        foreach (self::STEPS as $step) {
            $response = $this->request($app, 'GET', '/apply/continue/' . $token . '/' . $step);
            self::assertSame(200, $response->getStatusCode(), 'Step ' . $step . ' must be reachable.');
            $html = (string) $response->getBody();
            self::assertStringContainsString('data-wizard-step="' . $step . '"', $html);
            self::assertStringContainsString('/apply/continue/' . $token . '/', $html);
            if (in_array($step, ['personal', 'review'], true)) {
                self::assertStringContainsString('Candidato Wizard', $html);
            }
            self::assertStringNotContainsString('candidate_id', $html);
            self::assertStringNotContainsString('continuation_token_hash', $html);
            foreach ($labels as $label) {
                self::assertStringContainsString($label, $html);
            }
        }

        $personal = $this->request($app, 'POST', '/apply/continue/' . $token, [
            'csrf_token' => $this->csrfToken(),
            'wizard_step' => 'personal',
            'full_name' => 'Candidato Wizard Actualizado',
        ]);
        self::assertSame(303, $personal->getStatusCode());
        self::assertSame('/apply/continue/' . $token . '/work', $personal->getHeaderLine('Location'));

        $updated = (new CandidateService($this->pdo))->findByToken($token, time());
        self::assertNotNull($updated);
        self::assertSame($candidateId, (int) $updated['id']);
        self::assertSame('Candidato Wizard Actualizado', $updated['full_name']);

        $work = $this->request($app, 'GET', '/apply/continue/' . $token . '/work');
        self::assertStringContainsString('href="/apply/continue/' . $token . '/personal"', (string) $work->getBody());

        $workSaved = $this->request($app, 'POST', '/apply/continue/' . $token, [
            'csrf_token' => $this->csrfToken(),
            'wizard_step' => 'work',
            'employment_status' => 'Empleado',
        ]);
        self::assertSame('/apply/continue/' . $token . '/occupants', $workSaved->getHeaderLine('Location'));

        $occupantsSaved = $this->request($app, 'POST', '/apply/continue/' . $token, [
            'csrf_token' => $this->csrfToken(),
            'wizard_step' => 'occupants',
            'occupants_count' => '2',
            'pets_declared' => '0',
        ]);
        self::assertSame('/apply/continue/' . $token . '/references', $occupantsSaved->getHeaderLine('Location'));

        $referencesSaved = $this->request($app, 'POST', '/apply/continue/' . $token . '/references', [
            'csrf_token' => $this->csrfToken(),
            'wizard_step' => 'references',
            'references' => [],
        ]);
        self::assertSame('/apply/continue/' . $token . '/documents', $referencesSaved->getHeaderLine('Location'));

        $documents = $this->request($app, 'GET', '/apply/continue/' . $token . '/documents');
        self::assertStringContainsString('href="/apply/continue/' . $token . '/review"', (string) $documents->getBody());
        $submitted = $this->request($app, 'POST', '/apply/continue/' . $token . '/submit', [
            'csrf_token' => $this->csrfToken(),
        ]);
        self::assertSame('/apply/continue/' . $token . '/review', $submitted->getHeaderLine('Location'));

        $final = (new CandidateService($this->pdo))->findByToken($token, time());
        self::assertSame($candidateId, (int) $final['id']);
        self::assertSame('Candidato Wizard Actualizado', $final['full_name']);
        self::assertSame('verification', $final['status']);
    }

    public function testCompatibilityRouteRedirectsToFirstIncompleteStepFromCurrentData(): void
    {
        [, $token] = $this->startThroughPublicRoute();
        $service = new CandidateService($this->pdo);
        $service->updateByToken($token, ['employer_name' => 'Empresa pendiente de estado laboral'], time());

        $response = $this->request($this->app(), 'GET', '/apply/continue/' . $token);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/apply/continue/' . $token . '/work', $response->getHeaderLine('Location'));

        $service->updateByToken($token, ['employment_status' => 'Independiente'], time());
        $response = $this->request($this->app(), 'GET', '/apply/continue/' . $token);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/apply/continue/' . $token . '/review', $response->getHeaderLine('Location'));
    }

    public function testSubmittedCandidateCanOnlyOpenReadOnlyReview(): void
    {
        $app = $this->app();
        [, $token] = $this->startThroughPublicRoute();
        $submission = $this->request($app, 'POST', '/apply/continue/' . $token . '/submit', [
            'csrf_token' => $this->csrfToken(),
        ]);
        self::assertSame('/apply/continue/' . $token . '/review', $submission->getHeaderLine('Location'));

        $otherStep = $this->request($app, 'GET', '/apply/continue/' . $token . '/personal');
        self::assertSame(303, $otherStep->getStatusCode());
        self::assertSame('/apply/continue/' . $token . '/review', $otherStep->getHeaderLine('Location'));

        $review = $this->request($app, 'GET', '/apply/continue/' . $token . '/review');
        self::assertSame(200, $review->getStatusCode());
        $html = (string) $review->getBody();
        self::assertStringContainsString('data-wizard-step="review"', $html);
        self::assertStringContainsString('Candidato Wizard', $html);
        self::assertStringNotContainsString('<form', $html);
        self::assertStringNotContainsString('type="submit"', $html);
    }

    public function testInvalidAndExpiredTokensReturnNotFoundForEveryStep(): void
    {
        $app = $this->app();
        $invalidToken = str_repeat('a', 64);
        foreach (self::STEPS as $step) {
            self::assertSame(404, $this->request($app, 'GET', '/apply/continue/' . $invalidToken . '/' . $step)->getStatusCode());
        }

        [, $token] = $this->startThroughPublicRoute();
        $this->pdo->prepare(
            'UPDATE candidates SET continuation_token_expires_at = :expires_at WHERE continuation_token_hash = :token_hash',
        )->execute([
            'expires_at' => '2000-01-01 00:00:00',
            'token_hash' => hash('sha256', $token),
        ]);
        foreach (self::STEPS as $step) {
            self::assertSame(404, $this->request($app, 'GET', '/apply/continue/' . $token . '/' . $step)->getStatusCode());
        }
    }

    public function testWizardProgressIsNotPersistedAsTablesOrCandidateColumns(): void
    {
        $tables = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables "
            . "WHERE table_schema = DATABASE() AND table_name LIKE '%wizard%'",
        )->fetchColumn();
        $columns = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns "
            . "WHERE table_schema = DATABASE() AND table_name = 'candidates' "
            . "AND (column_name LIKE '%wizard%' OR column_name LIKE '%step%' OR column_name LIKE '%progress%')",
        )->fetchColumn();

        self::assertSame(0, (int) $tables);
        self::assertSame(0, (int) $columns);
    }

    /** @return array{array{id:int,public_key:string},string,int} */
    private function startThroughPublicRoute(): array
    {
        $unit = $this->seedUnit();
        $response = $this->request($this->app(), 'POST', '/apply/' . $unit['public_key'], [
            'csrf_token' => $this->csrfToken(),
            'document_type' => 'cc',
            'document_number' => 'WIZ-' . bin2hex(random_bytes(5)),
            'full_name' => 'Candidato Wizard',
            'phone' => '3001234567',
            'email' => 'wizard-' . bin2hex(random_bytes(4)) . '@example.test',
            'consent' => '1',
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertMatchesRegularExpression('#^/apply/continue/[a-f0-9]{64}$#', $response->getHeaderLine('Location'));
        $token = basename($response->getHeaderLine('Location'));
        $statement = $this->pdo->prepare('SELECT id FROM candidates WHERE continuation_token_hash = :token_hash');
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $candidateId = $statement->fetchColumn();
        self::assertNotFalse($candidateId);

        return [$unit, $token, (int) $candidateId];
    }

    private function app(): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->directory . '/database.php',
            'migration_directory' => dirname(__DIR__, 3) . '/database/migrations',
            'base_url' => 'https://alquilapro.sierraglobalcompany.com',
            'pdo' => $this->pdo,
            'logo_directory' => $this->directory . '/branding',
            'unit_media_directory' => $this->directory . '/unit-media',
        ]);
    }

    private function request(App $app, string $method, string $path, array $body = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );
        if (strtoupper($method) === 'POST') {
            $request = $request->withParsedBody($body);
        }

        return $app->handle($request);
    }

    private function csrfToken(): string
    {
        return (new Csrf())->token();
    }

    /** @return array{id:int,public_key:string} */
    private function seedUnit(): array
    {
        $publicKey = bin2hex(random_bytes(12));
        $property = $this->pdo->prepare(
            "INSERT INTO properties (name, address, city, sector, is_active) "
            . "VALUES ('Wizard Property', 'Public address', 'Medellín', 'Calasanz', 1)",
        );
        $property->execute();
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements, private_notes) '
            . "VALUES (:property_id, :public_key, 'apartment', 'WIZ-01', 'Sector público', 'available', 1, 1800000, 'Wizard unit', 'Documentos básicos', 'private')",
        );
        $unit->execute(['property_id' => $propertyId, 'public_key' => $publicKey]);
        $unitId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', 'cover.jpg', 'image/jpeg', 0, 1)",
        )->execute(['unit_id' => $unitId]);

        return ['id' => $unitId, 'public_key' => $publicKey];
    }
}
