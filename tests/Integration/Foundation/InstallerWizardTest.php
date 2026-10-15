<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Foundation;

use AlquilaPro\Auth\Csrf;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\Installer;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\RuntimeRequirements;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class InstallerWizardTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $lockPath;
    private string $configPath;
    private string $migrationDirectory;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->pdo = DatabaseConnector::connect(DatabaseConfig::fromArray($this->databaseInput()));
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['unit_media', 'units', 'properties', 'settings', 'users', 'schema_migrations'] as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->directory = sys_get_temp_dir() . '/alquila_installer_wizard_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->lockPath = $this->directory . '/installed.lock';
        $this->configPath = $this->directory . '/database.php';
        $this->migrationDirectory = dirname(__DIR__, 3) . '/database/migrations';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        @unlink($this->lockPath);
        @unlink($this->lockPath . '.tmp');
        @unlink($this->configPath);
        @unlink($this->configPath . '.tmp');
        @rmdir($this->directory);
    }

    public function testRequirementsFailClosedWhenARequiredCheckIsMissing(): void
    {
        $runtime = RuntimeRequirements::evaluate('8.5.0', ['pdo', 'pdo_mysql', 'mbstring', 'dom', 'fileinfo'], true);

        self::assertFalse($runtime['ok']);
        self::assertFalse($runtime['checks']['gd']['ok']);
    }

    public function testExplicitDatabaseTestConnectsWithoutRunningMigrations(): void
    {
        $installer = $this->installer();

        $installer->testDatabase($this->databaseInput());

        self::assertFalse($this->tableExists('schema_migrations'));
        self::assertFalse((new InstallationLock($this->lockPath))->isInstalled());
        self::assertFileDoesNotExist($this->configPath);
    }

    public function testWizardRequiresOrderedStepsAndInstallsOnlyAfterFinalConfirmation(): void
    {
        $app = $this->app();
        $initial = $this->get($app, '/install');
        self::assertSame(200, $initial->getStatusCode());
        self::assertStringContainsString('Paso 0 de 7', (string) $initial->getBody());
        self::assertStringContainsString('Requisitos del servidor', (string) $initial->getBody());

        $database = $this->databaseInput();
        $outOfOrderProbe = $this->post($app, '/install/test-database', [
            'db_host' => $database['host'],
            'db_port' => (string) $database['port'],
            'db_name' => $database['database'],
            'db_user' => $database['username'],
            'db_password' => $database['password'],
        ]);
        self::assertSame(422, $outOfOrderProbe->getStatusCode());
        self::assertFalse($this->tableExists('schema_migrations'));

        $stepOne = $this->post($app, '/install/continue');
        self::assertSame(200, $stepOne->getStatusCode());
        self::assertStringContainsString('Paso 1 de 7', (string) $stepOne->getBody());
        self::assertFalse($this->tableExists('schema_migrations'));

        $database = $this->databaseInput();
        $dbForm = [
            'db_host' => $database['host'],
            'db_port' => (string) $database['port'],
            'db_name' => $database['database'],
            'db_user' => $database['username'],
            'db_password' => $database['password'],
        ];
        $tested = $this->post($app, '/install/test-database', $dbForm);
        $testedBody = (string) $tested->getBody();
        self::assertSame(200, $tested->getStatusCode());
        self::assertStringContainsString('Paso 2 de 7', $testedBody);
        self::assertDoesNotMatchRegularExpression('/<input[^>]+(?:name|id)="db_password"[^>]+value=/i', $testedBody);
        self::assertFalse($this->tableExists('schema_migrations'));

        $ownerStep = $this->post($app, '/install/continue', ['app_name' => 'Casa Central']);
        self::assertStringContainsString('Paso 3 de 7', (string) $ownerStep->getBody());
        $summary = $this->post($app, '/install/continue', [
            'owner_name' => 'Propietario Principal',
            'owner_email' => 'owner@example.com',
            'owner_password' => bin2hex(random_bytes(20)),
        ]);
        $summaryBody = (string) $summary->getBody();
        self::assertStringContainsString('Paso 4 de 7', $summaryBody);
        self::assertStringNotContainsString($this->ownerPasswordFromSession(), $summaryBody);
        self::assertDoesNotMatchRegularExpression('/<input[^>]+(?:name|id)="db_password"[^>]+value=/i', $summaryBody);
        self::assertFalse($this->tableExists('schema_migrations'));

        $finalStep = $this->post($app, '/install/continue');
        self::assertStringContainsString('Paso 5 de 7', (string) $finalStep->getBody());
        self::assertFalse($this->tableExists('schema_migrations'));

        $complete = $this->post($app, '/install');
        self::assertSame(200, $complete->getStatusCode());
        self::assertStringContainsString('Paso 6 de 7', (string) $complete->getBody());
        self::assertTrue((new InstallationLock($this->lockPath))->isInstalled());
        self::assertTrue($this->tableExists('schema_migrations'));
        self::assertArrayNotHasKey('alquilapro_installer_wizard', $_SESSION);
    }

    public function testChangedDatabaseCredentialsInvalidateEarlierConnectionTest(): void
    {
        $app = $this->app();
        $this->get($app, '/install');
        $this->post($app, '/install/continue');

        $valid = $this->databaseInput();
        $validForm = [
            'db_host' => $valid['host'],
            'db_port' => (string) $valid['port'],
            'db_name' => $valid['database'],
            'db_user' => $valid['username'],
            'db_password' => $valid['password'],
        ];
        self::assertSame(200, $this->post($app, '/install/test-database', $validForm)->getStatusCode());

        $editDatabase = $this->post($app, '/install/back-database');
        self::assertSame(200, $editDatabase->getStatusCode());
        self::assertSame(1, $_SESSION['alquilapro_installer_wizard']['step']);
        self::assertFalse($_SESSION['alquilapro_installer_wizard']['database_tested']);

        $changed = $validForm;
        $changedPassword = 'wrong-' . bin2hex(random_bytes(12));
        $changed['db_password'] = $changedPassword;
        $failed = $this->post($app, '/install/test-database', $changed);
        $body = (string) $failed->getBody();

        self::assertSame(422, $failed->getStatusCode());
        self::assertStringNotContainsString($changedPassword, $body);
        self::assertFalse($_SESSION['alquilapro_installer_wizard']['database_tested']);
        self::assertArrayNotHasKey('password', $_SESSION['alquilapro_installer_wizard']['database']);
        self::assertArrayNotHasKey('tested_fingerprint', $_SESSION['alquilapro_installer_wizard']);
        self::assertFalse($this->tableExists('schema_migrations'));

        $final = $this->post($app, '/install');
        self::assertSame(422, $final->getStatusCode());
        self::assertFalse((new InstallationLock($this->lockPath))->isInstalled());
    }

    public function testOwnerDataMustPassValidationBeforeSummary(): void
    {
        $app = $this->app();
        $this->get($app, '/install');
        $this->post($app, '/install/continue');
        $valid = $this->databaseInput();
        $this->post($app, '/install/test-database', [
            'db_host' => $valid['host'],
            'db_port' => (string) $valid['port'],
            'db_name' => $valid['database'],
            'db_user' => $valid['username'],
            'db_password' => $valid['password'],
        ]);
        $this->post($app, '/install/continue', ['app_name' => 'Casa Central']);

        $response = $this->post($app, '/install/continue', [
            'owner_name' => 'Propietario',
            'owner_email' => 'invalid-email',
            'owner_password' => 'short',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Paso 3 de 7', (string) $response->getBody());
        self::assertFalse($this->tableExists('schema_migrations'));
        self::assertFalse((new InstallationLock($this->lockPath))->isInstalled());
    }

    private function app(): App
    {
        $factory = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        return $factory([
            'lock_path' => $this->lockPath,
            'database_config_path' => $this->configPath,
            'migration_directory' => $this->migrationDirectory,
            'base_url' => 'https://alquilapro.sierraglobalcompany.com',
            'base_path' => '',
        ]);
    }

    private function get(App $app, string $path): \Psr\Http\Message\ResponseInterface
    {
        return $app->handle((new ServerRequestFactory())->createServerRequest('GET', 'https://alquilapro.sierraglobalcompany.com' . $path));
    }

    private function post(App $app, string $path, array $form = []): \Psr\Http\Message\ResponseInterface
    {
        $form['csrf_token'] = (new Csrf())->token();

        return $app->handle(
            (new ServerRequestFactory())
                ->createServerRequest('POST', 'https://alquilapro.sierraglobalcompany.com' . $path)
                ->withParsedBody($form),
        );
    }

    private function installer(): Installer
    {
        return new Installer(new InstallationLock($this->lockPath), $this->migrationDirectory, $this->configPath);
    }

    private function databaseInput(): array
    {
        return [
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ];
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        );
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function ownerPasswordFromSession(): string
    {
        return (string) ($_SESSION['alquilapro_installer_wizard']['owner_password'] ?? '');
    }
}
