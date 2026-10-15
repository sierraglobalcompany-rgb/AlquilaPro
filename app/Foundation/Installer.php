<?php

declare(strict_types=1);

namespace AlquilaPro\Foundation;

use PDO;
use RuntimeException;
use Throwable;

final class Installer
{
    public function __construct(
        private readonly InstallationLock $lock,
        private readonly string $migrationDirectory,
        private readonly string $databaseConfigPath,
    ) {
    }

    public function testDatabase(array $database): void
    {
        DatabaseConnector::connect(DatabaseConfig::fromArray($database));
    }

    public function install(array $input): array
    {
        if ($this->lock->isInstalled()) {
            throw new RuntimeException('Alquila Pro ya está instalado.');
        }

        $appName = trim((string) ($input['app_name'] ?? 'Alquila Pro'));
        $baseUrl = rtrim(trim((string) ($input['base_url'] ?? '')), '/');
        $ownerName = trim((string) ($input['owner_name'] ?? ''));
        $ownerEmail = strtolower(trim((string) ($input['owner_email'] ?? '')));
        $ownerPassword = (string) ($input['owner_password'] ?? '');

        if ($ownerName === '' || filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) === false || strlen($ownerPassword) < 12) {
            throw new RuntimeException('Los datos del propietario no son válidos.');
        }

        if ($appName === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('La configuración inicial no es válida.');
        }

        $this->ensureConfigDirectory();
        $runtime = RuntimeRequirements::evaluate(PHP_VERSION, get_loaded_extensions(), is_writable(dirname($this->databaseConfigPath)));
        if (!$runtime['ok']) {
            throw new RuntimeException('El servidor no cumple los requisitos de Alquila Pro.');
        }

        $database = DatabaseConfig::fromArray((array) ($input['database'] ?? []));
        $pdo = DatabaseConnector::connect($database);
        MigrationRunner::migrate($pdo, $this->migrationDirectory);

        $committed = false;

        try {
            $pdo->beginTransaction();
            $this->saveOwner($pdo, $ownerName, $ownerEmail, $ownerPassword);
            $this->saveSetting($pdo, 'app.name', $appName);
            $this->saveSetting($pdo, 'app.base_url', $baseUrl);
            $this->writeDatabaseConfig($database);
            $pdo->commit();
            $committed = true;

            $this->lock->write([
                'schema_version' => '1',
                'installed_at' => date(DATE_ATOM),
            ]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (!$committed) {
                @unlink($this->databaseConfigPath);
                @unlink($this->databaseConfigPath . '.tmp');
            }

            throw $exception;
        }

        return ['app_name' => $appName, 'base_url' => $baseUrl];
    }

    private function saveOwner(PDO $pdo, string $name, string $email, string $password): void
    {
        $ownerId = $pdo->query("SELECT id FROM users WHERE role = 'owner' ORDER BY id LIMIT 1")->fetchColumn();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($ownerId !== false) {
            $statement = $pdo->prepare(
                'UPDATE users SET full_name = :full_name, email = :email, password_hash = :password_hash, '
                . 'is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            );
            $statement->execute([
                'full_name' => $name,
                'email' => $email,
                'password_hash' => $passwordHash,
                'id' => $ownerId,
            ]);
            return;
        }

        $statement = $pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (:role, :full_name, :email, :password_hash, 1)',
        );
        $statement->execute([
            'role' => 'owner',
            'full_name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);
    }

    private function saveSetting(PDO $pdo, string $key, string $value): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP',
        );
        $statement->execute(['setting_key' => $key, 'setting_value' => $value]);
    }

    private function ensureConfigDirectory(): void
    {
        $directory = dirname($this->databaseConfigPath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible preparar la configuración local.');
        }
    }

    private function writeDatabaseConfig(DatabaseConfig $database): void
    {
        $values = [
            'host' => $database->host,
            'port' => $database->port,
            'database' => $database->database,
            'username' => $database->username,
            'password' => $database->password,
        ];
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($values, true) . ";\n";
        $temporary = $this->databaseConfigPath . '.tmp';

        if (file_put_contents($temporary, $content, LOCK_EX) === false) {
            throw new RuntimeException('No fue posible guardar la configuración de base de datos.');
        }

        @chmod($temporary, 0600);
        if (!rename($temporary, $this->databaseConfigPath)) {
            @unlink($temporary);
            throw new RuntimeException('No fue posible activar la configuración de base de datos.');
        }
    }
}
