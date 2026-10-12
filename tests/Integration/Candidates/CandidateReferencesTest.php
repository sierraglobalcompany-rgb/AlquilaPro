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
use RuntimeException;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CandidateReferencesTest extends TestCase
{
    private const NOW = 1791817200;

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

        $this->directory = sys_get_temp_dir() . '/alquila_candidate_refs_' . bin2hex(random_bytes(6));
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

    public function testProfileCompletionValidatesExtendedFieldsAndPromotesNewToIncomplete(): void
    {
        $unit = $this->seedUnit(str_repeat('4', 24), true, 'Unidad Perfil');
        $service = new CandidateService($this->pdo);
        $started = $service->startCandidate($unit['public_key'], $this->candidateInput('7001'), self::NOW);

        $updated = $service->updateByToken($started['continuation_token'], [
            'employment_status' => 'Empleado',
            'employer_name' => 'Empresa Uno',
            'occupation' => 'Analista',
            'monthly_income' => '2500000.50',
            'employment_notes' => 'Contrato indefinido',
            'occupants_count' => '3',
            'occupants_notes' => 'Dos adultos y un menor',
            'pets_declared' => '1',
            'pets_notes' => 'Un perro pequeño',
        ], self::NOW + 1);

        self::assertSame('incomplete', $updated['status']);
        self::assertSame('2500000.50', $updated['monthly_income']);
        self::assertSame(3, $updated['occupants_count']);
        self::assertTrue($updated['pets_declared']);
        self::assertSame('Dos adultos y un menor', $updated['occupants_notes']);
        self::assertSame('Un perro pequeño', $updated['pets_notes']);

        $before = $this->candidateRow((int) $started['candidate_id']);
        foreach ([
            ['monthly_income' => '-1'],
            ['occupants_count' => '-1'],
            ['occupants_count' => '1.5'],
            ['pets_declared' => 'yes'],
        ] as $invalid) {
            try {
                $service->updateByToken($started['continuation_token'], $invalid, self::NOW + 2);
                self::fail('Invalid F5.4 profile value must be rejected.');
            } catch (RuntimeException) {
                self::assertSame($before, $this->candidateRow((int) $started['candidate_id']));
            }
        }

        $cleared = $service->updateByToken($started['continuation_token'], [
            'monthly_income' => '',
            'occupants_count' => '',
            'pets_declared' => '0',
            'pets_notes' => '',
        ], self::NOW + 3);
        self::assertNull($cleared['monthly_income']);
        self::assertNull($cleared['occupants_count']);
        self::assertFalse($cleared['pets_declared']);
        self::assertNull($cleared['pets_notes']);
        self::assertSame('incomplete', $cleared['status']);
    }

    public function testReferencesAreZeroOrMoreValidatedAndReplacedTransactionally(): void
    {
        $unit = $this->seedUnit(str_repeat('5', 24), true, 'Unidad Referencias');
        $service = new CandidateService($this->pdo);
        $started = $service->startCandidate($unit['public_key'], $this->candidateInput('7002'), self::NOW);

        $candidate = $service->replaceReferencesByToken($started['continuation_token'], [
            [
                'reference_type' => 'personal',
                'name' => 'Ana Referencia',
                'relationship' => 'Amiga',
                'phone' => '3001112233',
                'email' => 'ANA@EXAMPLE.COM',
            ],
            [
                'reference_type' => '',
                'name' => 'Carlos Referencia',
                'relationship' => '',
                'phone' => '3004445566',
                'email' => '',
            ],
        ], self::NOW + 1);

        self::assertSame('incomplete', $candidate['status']);
        $rows = $this->referenceRows((int) $started['candidate_id']);
        self::assertCount(2, $rows);
        self::assertSame('ana@example.com', $rows[0]['email']);
        self::assertNull($rows[1]['reference_type']);
        self::assertNull($rows[1]['relationship']);
        self::assertNull($rows[1]['email']);

        $service->replaceReferencesByToken($started['continuation_token'], [[
            'reference_type' => 'familiar',
            'name' => 'Referencia Vigente',
            'relationship' => 'Hermana',
            'phone' => '3010000000',
            'email' => 'vigente@example.com',
        ]], self::NOW + 2);
        self::assertCount(1, $this->referenceRows((int) $started['candidate_id']));

        try {
            $service->replaceReferencesByToken($started['continuation_token'], [
                [
                    'name' => 'Valida pero no debe persistir',
                    'phone' => '3020000000',
                ],
                [
                    'name' => 'Sin teléfono',
                    'phone' => '',
                ],
            ], self::NOW + 3);
            self::fail('Invalid reference set must fail before replacement.');
        } catch (RuntimeException) {
            $preserved = $this->referenceRows((int) $started['candidate_id']);
            self::assertCount(1, $preserved);
            self::assertSame('Referencia Vigente', $preserved[0]['name']);
        }

        $service->replaceReferencesByToken($started['continuation_token'], [], self::NOW + 4);
        self::assertSame([], $this->referenceRows((int) $started['candidate_id']));
    }

    public function testUnitChangeKeepsSingleCandidateAndEnforcesAllGuards(): void
    {
        $current = $this->seedUnit(str_repeat('6', 24), true, 'Unidad Actual');
        $target = $this->seedUnit(str_repeat('7', 24), true, 'Unidad Destino');
        $hidden = $this->seedUnit(str_repeat('8', 24), false, 'Unidad Oculta');
        $service = new CandidateService($this->pdo);

        $started = $service->startCandidate($current['public_key'], $this->candidateInput('7003'), self::NOW);
        $changed = $service->changeUnitByToken($started['continuation_token'], $target['public_key'], self::NOW + 1);
        self::assertSame((int) $started['candidate_id'], $changed['id']);
        self::assertSame($target['id'], $changed['unit_id']);
        self::assertSame('incomplete', $changed['status']);
        self::assertSame(1, $this->candidateCount());

        try {
            $service->changeUnitByToken($started['continuation_token'], $hidden['public_key'], self::NOW + 2);
            self::fail('Non-publicable target must be rejected.');
        } catch (RuntimeException) {
            self::assertSame($target['id'], $this->candidateRow((int) $started['candidate_id'])['unit_id']);
        }

        $submittedFlag = $service->startCandidate($current['public_key'], $this->candidateInput('7004'), self::NOW + 10);
        $this->pdo->prepare('UPDATE candidates SET submitted_at = :submitted_at WHERE id = :id')->execute([
            'submitted_at' => '2026-10-12 15:00:00',
            'id' => $submittedFlag['candidate_id'],
        ]);
        $this->assertUnitChangeRejected($service, $submittedFlag['continuation_token'], $target['public_key']);

        $withDocument = $service->startCandidate($current['public_key'], $this->candidateInput('7005'), self::NOW + 20);
        $requirement = $this->pdo->prepare(
            "INSERT INTO unit_application_requirements (unit_id, code, label) VALUES (:unit_id, 'identity', 'Documento')",
        );
        $requirement->execute(['unit_id' => $current['id']]);
        $requirementId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO candidate_documents (candidate_id, requirement_id, stored_name, original_name, mime_type, size_bytes) "
            . "VALUES (:candidate_id, :requirement_id, 'stored.pdf', 'documento.pdf', 'application/pdf', 100)",
        )->execute([
            'candidate_id' => $withDocument['candidate_id'],
            'requirement_id' => $requirementId,
        ]);
        $this->assertUnitChangeRejected($service, $withDocument['continuation_token'], $target['public_key']);

        $submitted = $service->startCandidate($current['public_key'], $this->candidateInput('7006'), self::NOW + 30);
        $service->submitByToken($submitted['continuation_token'], self::NOW + 31);
        $this->assertUnitChangeRejected($service, $submitted['continuation_token'], $target['public_key']);
    }

    public function testPublicContinuationRendersEscapedF54FormsAndRoutesUseCsrfAndBasePath(): void
    {
        $current = $this->seedUnit(str_repeat('9', 24), true, 'Unidad Uno');
        $target = $this->seedUnit(str_repeat('a', 24), true, 'Unidad Dos');
        $service = new CandidateService($this->pdo);
        $started = $service->startCandidate($current['public_key'], $this->candidateInput('7007'), time());
        $service->updateByToken($started['continuation_token'], [
            'monthly_income' => '3200000',
            'occupants_count' => '2',
            'occupants_notes' => 'Pareja',
            'pets_declared' => '1',
            'pets_notes' => 'Gato',
        ], time());
        $service->replaceReferencesByToken($started['continuation_token'], [[
            'reference_type' => 'personal',
            'name' => '<script>alert(1)</script>',
            'relationship' => 'Amigo',
            'phone' => '3009990000',
            'email' => 'ref@example.com',
        ]], time());

        $app = $this->app('/sub');
        $continuePath = '/sub/apply/continue/' . $started['continuation_token'];
        $response = $this->request($app, 'GET', $continuePath);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        foreach (['monthly_income', 'occupants_count', 'occupants_notes', 'pets_declared', 'pets_notes'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $html);
        }
        self::assertStringContainsString('action="' . $continuePath . '/references"', $html);
        self::assertStringContainsString('action="' . $continuePath . '/unit"', $html);
        self::assertStringContainsString('name="references[0][name]"', $html);
        self::assertStringContainsString('value="' . $target['public_key'] . '"', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('reference_id', $html);
        self::assertStringNotContainsString('candidate_id', $html);
        self::assertStringNotContainsString('/sub/sub/', $html);

        $beforeReferences = $this->referenceRows((int) $started['candidate_id']);
        $withoutCsrf = $this->request($app, 'POST', $continuePath . '/references', [
            'references' => [[
                'name' => 'No debe cambiar',
                'phone' => '3111111111',
            ]],
        ]);
        self::assertSame(403, $withoutCsrf->getStatusCode());
        self::assertSame($beforeReferences, $this->referenceRows((int) $started['candidate_id']));

        $saved = $this->request($app, 'POST', $continuePath . '/references', [
            'csrf_token' => $this->csrfToken(),
            'references' => [[
                'reference_type' => 'familiar',
                'name' => 'Nueva Referencia',
                'relationship' => 'Hermano',
                'phone' => '3122222222',
                'email' => 'nueva@example.com',
            ]],
        ]);
        self::assertSame(303, $saved->getStatusCode());
        self::assertSame($continuePath, $saved->getHeaderLine('Location'));
        self::assertSame('Nueva Referencia', $this->referenceRows((int) $started['candidate_id'])[0]['name']);

        $unitChanged = $this->request($app, 'POST', $continuePath . '/unit', [
            'csrf_token' => $this->csrfToken(),
            'public_key' => $target['public_key'],
        ]);
        self::assertSame(303, $unitChanged->getStatusCode());
        self::assertSame($continuePath, $unitChanged->getHeaderLine('Location'));
        self::assertSame($target['id'], $this->candidateRow((int) $started['candidate_id'])['unit_id']);
        self::assertSame(1, $this->candidateCount());
    }

    private function assertUnitChangeRejected(CandidateService $service, string $token, string $publicKey): void
    {
        try {
            $service->changeUnitByToken($token, $publicKey, self::NOW + 100);
            self::fail('Unit change must be rejected by the F5.4 guard.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
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
            'pdo' => $this->pdo,
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
    private function seedUnit(string $publicKey, bool $publicable, string $name): array
    {
        $property = $this->pdo->prepare(
            "INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, 'Dirección privada', 'Medellín', 'Calasanz', 1)",
        );
        $property->execute(['name' => $name]);
        $propertyId = (int) $this->pdo->lastInsertId();

        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements) '
            . 'VALUES (:property_id, :public_key, :type, :code, :location_label, :status, :is_public, :monthly_price, :description, :requirements)',
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'type' => 'apartment',
            'code' => 'APT-' . substr($publicKey, 0, 6),
            'location_label' => $name,
            'status' => 'available',
            'is_public' => $publicable ? 1 : 0,
            'monthly_price' => '1800000.00',
            'description' => 'Apartamento disponible',
            'requirements' => 'Documentos básicos',
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', 'cover.jpg', 'image/jpeg', 0, 1)",
        )->execute(['unit_id' => $unitId]);

        return ['id' => $unitId, 'public_key' => $publicKey];
    }

    /** @return array<string,mixed> */
    private function candidateInput(string $documentNumber): array
    {
        return [
            'document_type' => 'cc',
            'document_number' => $documentNumber,
            'full_name' => 'Candidato F5.4',
            'phone' => '3001234567',
            'email' => 'candidate' . $documentNumber . '@example.com',
            'consent' => true,
        ];
    }

    /** @return array<string,mixed> */
    private function candidateRow(int $candidateId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM candidates WHERE id = :id');
        $statement->execute(['id' => $candidateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        $row['id'] = (int) $row['id'];
        $row['unit_id'] = (int) $row['unit_id'];

        return $row;
    }

    /** @return list<array<string,mixed>> */
    private function referenceRows(int $candidateId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT reference_type, name, relationship, phone, email FROM candidate_references WHERE candidate_id = :candidate_id ORDER BY id ASC',
        );
        $statement->execute(['candidate_id' => $candidateId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function candidateCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM candidates')->fetchColumn();
    }
}
