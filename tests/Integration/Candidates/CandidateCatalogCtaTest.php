<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Candidates;

use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CandidateCatalogCtaTest extends TestCase
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

        $this->directory = sys_get_temp_dir() . '/alquila_catalog_cta_' . bin2hex(random_bytes(6));
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

    public function testPublicableDetailShowsApplyCtaUsingPublicKeyAndBasePathOnly(): void
    {
        $unit = $this->seedUnit('CTA Pública', true);

        $response = $this->request($this->app(), '/catalog/' . $unit['public_key']);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('href="/apply/' . $unit['public_key'] . '"', $html);
        self::assertStringContainsString('Aplicar', $html);
        self::assertStringNotContainsString('href="/apply/' . $unit['id'] . '"', $html);
        self::assertStringNotContainsString('candidate_id', $html);
        self::assertStringNotContainsString('document_id', $html);
        self::assertStringNotContainsString('unit_id', $html);

        $sub = $this->request($this->app('/sub'), '/sub/catalog/' . $unit['public_key']);
        self::assertSame(200, $sub->getStatusCode());
        $subHtml = (string) $sub->getBody();
        self::assertStringContainsString('href="/sub/apply/' . $unit['public_key'] . '"', $subHtml);
        self::assertStringNotContainsString('/sub/sub/', $subHtml);
    }

    public function testNonPublicableDetailHasNoCtaAndCatalogListKeepsF4VisibilityRules(): void
    {
        $public = $this->seedUnit('CTA Visible', true);
        $hidden = $this->seedUnit('CTA Oculta', false);
        $app = $this->app();

        $hiddenDetail = $this->request($app, '/catalog/' . $hidden['public_key']);
        self::assertSame(404, $hiddenDetail->getStatusCode());
        self::assertStringNotContainsString('Aplicar', (string) $hiddenDetail->getBody());

        $catalog = $this->request($app, '/catalog');
        self::assertSame(200, $catalog->getStatusCode());
        $html = (string) $catalog->getBody();
        self::assertStringContainsString('CTA Visible', $html);
        self::assertStringNotContainsString('CTA Oculta', $html);
        self::assertStringContainsString('/catalog/' . $public['public_key'], $html);
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

    private function request(App $app, string $path): ResponseInterface
    {
        return $app->handle(
            (new ServerRequestFactory())->createServerRequest(
                'GET',
                'https://alquilapro.sierraglobalcompany.com' . $path,
            ),
        );
    }

    /** @return array{id:int,public_key:string} */
    private function seedUnit(string $propertyName, bool $publicable): array
    {
        $property = $this->pdo->prepare(
            "INSERT INTO properties (name, address, city, sector, is_active) VALUES (:name, 'Privada', 'Medellín', 'Calasanz', 1)",
        );
        $property->execute(['name' => $propertyName]);
        $propertyId = (int) $this->pdo->lastInsertId();
        $publicKey = bin2hex(random_bytes(12));

        $unit = $this->pdo->prepare(
            'INSERT INTO units '
            . '(property_id, public_key, type, code, location_label, status, is_public, monthly_price, description, requirements) '
            . "VALUES (:property_id, :public_key, 'apartment', :code, 'Ubicación pública', 'available', :is_public, 1800000, 'Descripción', 'Documentos')",
        );
        $unit->execute([
            'property_id' => $propertyId,
            'public_key' => $publicKey,
            'code' => 'CTA-' . substr($publicKey, 0, 8),
            'is_public' => $publicable ? 1 : 0,
        ]);
        $unitId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare(
            "INSERT INTO unit_media (unit_id, type, stored_name, mime_type, sort_order, is_cover) "
            . "VALUES (:unit_id, 'photo', :stored_name, 'image/jpeg', 1, 1)",
        )->execute([
            'unit_id' => $unitId,
            'stored_name' => 'cta-' . $unitId . '.jpg',
        ]);

        return ['id' => $unitId, 'public_key' => $publicKey];
    }
}
