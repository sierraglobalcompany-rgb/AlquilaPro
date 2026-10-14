<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Candidates\CandidateService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Notifications\NotificationService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class PublicCandidateApplyTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_public_apply_' . bin2hex(random_bytes(6));
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
        @rmdir($this->directory);
    }

    public function testGetApplyRequiresPublicableUnitAndRendersSafeStartForm(): void
    {
        $public = $this->seedUnit(str_repeat('a', 24), true, 'Calle privada 123');
        $hidden = $this->seedUnit(str_repeat('b', 24), false, 'Otra privada 456');
        $app = $this->app();

        $response = $this->request($app, 'GET', '/apply/' . $public['public_key']);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('action="/apply/' . $public['public_key'] . '"', $html);
        self::assertStringContainsString('name="csrf_token"', $html);
        foreach (['document_type', 'document_number', 'full_name', 'phone', 'email', 'consent'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $html);
        }
        self::assertStringNotContainsString('Calle privada 123', $html);
        self::assertStringNotContainsString('candidate_id', $html);
        self::assertStringNotContainsString('continuation_token_hash', $html);
        self::assertStringNotContainsString('storage/', $html);

        self::assertSame(404, $this->request($app, 'GET', '/apply/' . $hidden['public_key'])->getStatusCode());
        self::assertSame(404, $this->request($app, 'GET', '/apply/not-a-public-key')->getStatusCode());
    }

    public function testPostApplyRequiresCsrfAndRedirectsOnceWithRawTokenButNeverCandidateId(): void
    {
        $unit = $this->seedUnit(str_repeat('c', 24), true);
        $app = $this->app();

        $withoutCsrf = $this->request($app, 'POST', '/apply/' . $unit['public_key'], $this->candidateInput('cc', '1001'));
        self::assertSame(403, $withoutCsrf->getStatusCode());
        self::assertSame(0, $this->candidateCount());

        $response = $this->request($app, 'POST', '/apply/' . $unit['public_key'], $this->candidateInput('cc', '1001') + [
            'csrf_token' => $this->csrfToken(),
        ]);
        self::assertSame(303, $response->getStatusCode());

        $location = $response->getHeaderLine('Location');
        self::assertMatchesRegularExpression('#^/apply/continue/[a-f0-9]{64}$#', $location);
        preg_match('#/([a-f0-9]{64})$#', $location, $matches);
        $token = (string) ($matches[1] ?? '');
        self::assertNotSame('', $token);

        $row = $this->candidateRowByDocument('1001');
        self::assertSame(hash('sha256', $token), $row['continuation_token_hash']);
        self::assertStringNotContainsString($row['continuation_token_hash'], $location);
        self::assertSame(1, $this->candidateCount());
    }

    public function testDuplicateIdentityReturnsGenericResponseWithoutTokenOrCandidateContext(): void
    {
        $unit = $this->seedUnit(str_repeat('d', 24), true);
        $app = $this->app();

        $first = $this->request($app, 'POST', '/apply/' . $unit['public_key'], $this->candidateInput(' cc ', ' abc-200 ', 'Persona Original', 'original@example.com') + [
            'csrf_token' => $this->csrfToken(),
        ]);
        self::assertSame(303, $first->getStatusCode());
        $token = basename($first->getHeaderLine('Location'));

        $duplicate = $this->request($app, 'POST', '/apply/' . $unit['public_key'], $this->candidateInput('CC', 'ABC-200', 'Persona Intrusa', 'intrusa@example.com') + [
            'csrf_token' => $this->csrfToken(),
        ]);
        self::assertSame(422, $duplicate->getStatusCode());
        $html = (string) $duplicate->getBody();

        self::assertStringContainsString('No fue posible iniciar la solicitud.', $html);
        self::assertStringNotContainsString($token, $html);
        self::assertStringNotContainsString('Persona Original', $html);
        self::assertStringNotContainsString('original@example.com', $html);
        self::assertStringNotContainsString('duplicad', mb_strtolower($html));
        self::assertSame(1, $this->candidateCount());
    }

    public function testContinueRejectsInvalidOrExpiredTokenAndOnlyShowsResolvedCandidateData(): void
    {
        $unit = $this->seedUnit(str_repeat('e', 24), true);
        $service = new CandidateService($this->pdo);
        $now = time();
        $first = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '3001', 'Candidato Uno', 'uno@example.com'), $now);
        $second = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '3002', 'Candidato Dos', 'dos@example.com'), $now);
        $service->updateByToken($first['continuation_token'], ['current_address' => 'Dirección propia visible'], $now + 1);

        $app = $this->app();
        self::assertSame(404, $this->request($app, 'GET', '/apply/continue/not-a-token')->getStatusCode());

        $this->pdo->prepare('UPDATE candidates SET continuation_token_expires_at = :expired WHERE id = :id')->execute([
            'expired' => '2000-01-01 00:00:00',
            'id' => $second['candidate_id'],
        ]);
        self::assertSame(404, $this->request($app, 'GET', '/apply/continue/' . $second['continuation_token'])->getStatusCode());

        $response = $this->request($app, 'GET', '/apply/continue/' . $first['continuation_token']);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Candidato Uno', $html);
        self::assertStringContainsString('uno@example.com', $html);
        self::assertStringContainsString('Dirección propia visible', $html);
        self::assertStringNotContainsString('Candidato Dos', $html);
        self::assertStringNotContainsString('dos@example.com', $html);
        self::assertStringNotContainsString('continuation_token_hash', $html);
        self::assertStringNotContainsString('candidate_id', $html);
        self::assertStringNotContainsString('storage/', $html);
    }

    public function testValidContinuePostUpdatesOnlyTokenCandidateAndUsesCsrf(): void
    {
        $unit = $this->seedUnit(str_repeat('f', 24), true);
        $service = new CandidateService($this->pdo);
        $now = time();
        $first = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '4001', 'Primero', 'primero@example.com'), $now);
        $second = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '4002', 'Segundo', 'segundo@example.com'), $now);
        $app = $this->app();

        $withoutCsrf = $this->request($app, 'POST', '/apply/continue/' . $first['continuation_token'], [
            'full_name' => 'No debe cambiar',
        ]);
        self::assertSame(403, $withoutCsrf->getStatusCode());
        self::assertSame('Primero', $service->findByToken($first['continuation_token'], time())['full_name']);

        $response = $this->request($app, 'POST', '/apply/continue/' . $first['continuation_token'], [
            'csrf_token' => $this->csrfToken(),
            'full_name' => 'Primero Actualizado',
            'phone' => '3009998877',
            'email' => 'actualizado@example.com',
            'current_address' => 'Carrera 10 # 20-30',
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/apply/continue/' . $first['continuation_token'], $response->getHeaderLine('Location'));

        $updated = $service->findByToken($first['continuation_token'], time());
        self::assertSame('Primero Actualizado', $updated['full_name']);
        self::assertSame('actualizado@example.com', $updated['email']);
        self::assertSame('Carrera 10 # 20-30', $updated['current_address']);
        self::assertSame('Segundo', $service->findByToken($second['continuation_token'], time())['full_name']);
    }

    public function testStartThrottleAllowsFivePostsThenReturns429WithoutSixthMutation(): void
    {
        $unit = $this->seedUnit(str_repeat('1', 24), true);
        $app = $this->app();
        $csrf = $this->csrfToken();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->request($app, 'POST', '/apply/' . $unit['public_key'], $this->candidateInput('cc', 'S-' . $attempt, 'Persona ' . $attempt, 'p' . $attempt . '@example.com') + [
                'csrf_token' => $csrf,
            ]);
            self::assertSame(303, $response->getStatusCode(), 'Attempt ' . $attempt . ' should be allowed.');
        }

        self::assertSame(5, $this->candidateCount());
        $blocked = $this->request($app, 'POST', '/apply/' . $unit['public_key'], $this->candidateInput('cc', 'S-6', 'Persona 6', 'p6@example.com') + [
            'csrf_token' => $csrf,
        ]);

        self::assertSame(429, $blocked->getStatusCode());
        self::assertStringContainsString('Demasiados intentos', (string) $blocked->getBody());
        self::assertStringNotContainsString('S-6', (string) $blocked->getBody());
        self::assertSame(5, $this->candidateCount());
    }

    public function testContinueThrottleAllowsTenPostsThenReturns429WithoutEleventhMutation(): void
    {
        $unit = $this->seedUnit(str_repeat('2', 24), true);
        $service = new CandidateService($this->pdo);
        $started = $service->startCandidate($unit['public_key'], $this->candidateInput('cc', '5001'), time());
        $app = $this->app();
        $csrf = $this->csrfToken();

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->request($app, 'POST', '/apply/continue/' . $started['continuation_token'], [
                'csrf_token' => $csrf,
                'phone' => '30000000' . str_pad((string) $attempt, 2, '0', STR_PAD_LEFT),
            ]);
            self::assertSame(303, $response->getStatusCode(), 'Attempt ' . $attempt . ' should be allowed.');
        }

        $before = $service->findByToken($started['continuation_token'], time());
        $blocked = $this->request($app, 'POST', '/apply/continue/' . $started['continuation_token'], [
            'csrf_token' => $csrf,
            'phone' => '3999999999',
        ]);

        self::assertSame(429, $blocked->getStatusCode());
        self::assertStringContainsString('Demasiados intentos', (string) $blocked->getBody());
        self::assertSame($before['phone'], $service->findByToken($started['continuation_token'], time())['phone']);
    }

    public function testExistingCandidateSurvivesUnitUnpublishAndSubmitNeverChangesUnitStatus(): void
    {
        $unit = $this->seedUnit(bin2hex(random_bytes(12)), true);
        $sent = 0;
        $notifications = new NotificationService($this->pdo, static function () use (&$sent): void {
            $sent++;
        });
        $app = $this->app(notifications: $notifications);

        $created = $this->request($app, 'POST', '/apply/' . $unit['public_key'], $this->candidateInput('cc', 'F57-LIFECYCLE-1') + [
            'csrf_token' => $this->csrfToken(),
        ]);
        self::assertSame(303, $created->getStatusCode());
        $continuePath = $created->getHeaderLine('Location');
        self::assertMatchesRegularExpression('#^/apply/continue/[a-f0-9]{64}$#', $continuePath);
        self::assertSame('available', $this->unitStatus($unit['id']));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM notification_jobs')->fetchColumn());
        self::assertSame(0, $sent, 'Candidate creation queues an email without sending inline.');

        $this->pdo->prepare('UPDATE units SET status = :status, is_public = 0 WHERE id = :id')->execute([
            'status' => 'occupied',
            'id' => $unit['id'],
        ]);

        self::assertSame(404, $this->request($app, 'GET', '/apply/' . $unit['public_key'])->getStatusCode());
        self::assertSame(404, $this->request($app, 'POST', '/apply/' . $unit['public_key'], $this->candidateInput('cc', 'F57-LIFECYCLE-2') + [
            'csrf_token' => $this->csrfToken(),
        ])->getStatusCode());
        self::assertSame(200, $this->request($app, 'GET', $continuePath)->getStatusCode());

        $submitted = $this->request($app, 'POST', $continuePath . '/submit', [
            'csrf_token' => $this->csrfToken(),
        ]);
        self::assertSame(303, $submitted->getStatusCode());
        self::assertSame($continuePath, $submitted->getHeaderLine('Location'));
        self::assertSame('verification', $this->candidateRowByDocument('F57-LIFECYCLE-1')['status']);
        self::assertSame('occupied', $this->unitStatus($unit['id']));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM notification_jobs')->fetchColumn());
        self::assertSame(0, $sent, 'Candidate submission queues an email without sending inline.');
        self::assertSame(
            ['candidate_created', 'candidate_submitted'],
            $this->pdo->query('SELECT template_key FROM notification_jobs ORDER BY id')->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    public function testCandidateCreationRollsBackIfItsNotificationCannotBeQueued(): void
    {
        $unit = $this->seedUnit(bin2hex(random_bytes(12)), true);
        $notifications = new NotificationService($this->pdo, static function (): void {
        });
        $app = $this->app(notifications: $notifications);
        $this->pdo->exec('DROP TABLE notification_jobs');

        $response = $this->request(
            $app,
            'POST',
            '/apply/' . $unit['public_key'],
            $this->candidateInput('cc', 'QUEUE-ROLLBACK-1') + ['csrf_token' => $this->csrfToken()],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->candidateCount(), 'A committed candidate must never lose its creation email job.');
    }

    public function testCandidateSubmissionRollsBackIfItsNotificationCannotBeQueued(): void
    {
        $unit = $this->seedUnit(bin2hex(random_bytes(12)), true);
        $started = (new CandidateService($this->pdo))->startCandidate(
            $unit['public_key'],
            $this->candidateInput('cc', 'QUEUE-ROLLBACK-2'),
            time(),
        );
        $notifications = new NotificationService($this->pdo, static function (): void {
        });
        $app = $this->app(notifications: $notifications);
        $this->pdo->exec('DROP TABLE notification_jobs');

        $response = $this->request(
            $app,
            'POST',
            '/apply/continue/' . $started['continuation_token'] . '/submit',
            ['csrf_token' => $this->csrfToken()],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('new', $this->candidateRowByDocument('QUEUE-ROLLBACK-2')['status']);
        self::assertNull($this->candidateRowByDocument('QUEUE-ROLLBACK-2')['submitted_at']);
    }

    public function testApplyAndContinuePreserveSubfolderInFormsAndRedirects(): void
    {
        $unit = $this->seedUnit(str_repeat('3', 24), true);
        $app = $this->app('/sub');

        $start = $this->request($app, 'GET', '/sub/apply/' . $unit['public_key']);
        self::assertSame(200, $start->getStatusCode());
        self::assertStringContainsString('action="/sub/apply/' . $unit['public_key'] . '"', (string) $start->getBody());

        $created = $this->request($app, 'POST', '/sub/apply/' . $unit['public_key'], $this->candidateInput('cc', '6001') + [
            'csrf_token' => $this->csrfToken(),
        ]);
        self::assertSame(303, $created->getStatusCode());
        $location = $created->getHeaderLine('Location');
        self::assertMatchesRegularExpression('#^/sub/apply/continue/[a-f0-9]{64}$#', $location);
        self::assertStringNotContainsString('/sub/sub/', $location);

        $continue = $this->request($app, 'GET', $location);
        self::assertSame(200, $continue->getStatusCode());
        self::assertStringContainsString('action="' . $location . '"', (string) $continue->getBody());
        self::assertStringNotContainsString('/sub/sub/', (string) $continue->getBody());
    }

    private function app(string $basePath = '', ?NotificationService $notifications = null): App
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
            'pdo' => $this->pdo,
            'notifications' => $notifications,
        ]);
    }

    private function request(App $app, string $method, string $path, array $body = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://alquilapro.sierraglobalcompany.com' . $path,
        );
        if ($method === 'POST') {
            $request = $request->withParsedBody($body);
        }

        return $app->handle($request);
    }

    private function csrfToken(): string
    {
        return (new Csrf())->token();
    }

    /** @return array{id:int,public_key:string} */
    private function seedUnit(string $publicKey, bool $publicable, string $privateAddress = 'Dirección privada'): array
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO properties (name, address, city, sector, is_active) VALUES "
            . "(:name, :address, 'Medellín', 'Calasanz', 1)",
        );
        $statement->execute([
            'name' => 'Propiedad Pública',
            'address' => $privateAddress,
        ]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $statement = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements, private_notes) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, :status, :is_public, :monthly_price, :description, :requirements, :private_notes)',
        );
        $statement->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'type' => 'apartment',
            'code' => 'APT-' . substr($publicKey, 0, 6),
            'location_label' => 'Sector público',
            'status' => 'available',
            'is_public' => $publicable ? 1 : 0,
            'monthly_price' => '1800000.00',
            'description' => 'Apartamento disponible',
            'requirements' => 'Documentos básicos',
            'private_notes' => 'NO PUBLICAR ESTA NOTA',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', 'cover.jpg', 'image/jpeg', 0, 1)",
        )->execute(['unit_id' => $unitId]);

        return ['id' => $unitId, 'public_key' => $publicKey];
    }

    /** @return array<string,mixed> */
    private function candidateInput(
        string $documentType,
        string $documentNumber,
        string $fullName = 'Candidato Público',
        string $email = 'candidate@example.com',
    ): array {
        return [
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'full_name' => $fullName,
            'phone' => '3001234567',
            'email' => $email,
            'consent' => '1',
        ];
    }

    private function candidateCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM candidates')->fetchColumn();
    }

    private function unitStatus(int $unitId): string
    {
        return (string) $this->pdo->query('SELECT status FROM units WHERE id = ' . $unitId)->fetchColumn();
    }

    /** @return array<string,mixed> */
    private function candidateRowByDocument(string $documentNumber): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM candidates WHERE document_number = :document_number LIMIT 1');
        $statement->execute(['document_number' => $documentNumber]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }
}
