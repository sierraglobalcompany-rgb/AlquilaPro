<?php

declare(strict_types=1);

namespace AlquilaPro\UI;

use PDO;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

final class Branding
{
    public const DEFAULT_APP_NAME = 'Alquila Pro';
    public const DEFAULT_PRIMARY_COLOR = '#1E6A5A';
    private const MAX_LOGO_BYTES = 2_097_152;

    private const MIME_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $logoDirectory,
    ) {
    }

    public function current(): array
    {
        $statement = $this->pdo->query(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app.name', 'brand.primary_color', 'brand.logo_path')",
        );
        $settings = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        $appName = trim($settings['app.name'] ?? '');
        if ($appName === '' || mb_strlen($appName) > 80) {
            $appName = self::DEFAULT_APP_NAME;
        }

        $primaryColor = strtoupper(trim($settings['brand.primary_color'] ?? ''));
        if (preg_match('/^#[0-9A-F]{6}$/', $primaryColor) !== 1) {
            $primaryColor = self::DEFAULT_PRIMARY_COLOR;
        }

        $logoPath = $this->validLogoBasename($settings['brand.logo_path'] ?? null);

        return [
            'app_name' => $appName,
            'primary_color' => $primaryColor,
            'logo_path' => $logoPath,
        ];
    }

    public function update(string $appName, string $primaryColor, ?UploadedFileInterface $logo = null): array
    {
        $appName = trim($appName);
        if ($appName === '' || mb_strlen($appName) > 80) {
            throw new RuntimeException('El nombre de la aplicación no es válido.');
        }

        $primaryColor = strtoupper(trim($primaryColor));
        if (preg_match('/^#[0-9A-F]{6}$/', $primaryColor) !== 1) {
            throw new RuntimeException('El color primario no es válido.');
        }

        $previous = $this->current();
        $newLogoBasename = null;
        $newLogoPath = null;

        if ($logo !== null) {
            [$newLogoBasename, $newLogoPath] = $this->storeValidatedLogo($logo);
        }

        try {
            $this->pdo->beginTransaction();
            $this->upsert('app.name', $appName);
            $this->upsert('brand.primary_color', $primaryColor);
            if ($newLogoBasename !== null) {
                $this->upsert('brand.logo_path', $newLogoBasename);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($newLogoPath !== null) {
                @unlink($newLogoPath);
            }
            throw $exception;
        }

        if ($newLogoBasename !== null && $previous['logo_path'] !== null && $previous['logo_path'] !== $newLogoBasename) {
            $oldPath = $this->logoPath((string) $previous['logo_path']);
            if ($oldPath !== null) {
                @unlink($oldPath);
            }
        }

        return $this->current();
    }

    public function logoFile(): ?array
    {
        $basename = $this->current()['logo_path'];
        if (!is_string($basename)) {
            return null;
        }

        $path = $this->logoPath($basename);
        if ($path === null) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            return null;
        }

        $expectedExtension = self::MIME_EXTENSIONS[$mime];
        if (pathinfo($basename, PATHINFO_EXTENSION) !== $expectedExtension) {
            return null;
        }

        return ['path' => $path, 'mime' => $mime];
    }

    private function storeValidatedLogo(UploadedFileInterface $logo): array
    {
        if ($logo->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No fue posible recibir el logo.');
        }

        $stream = $logo->getStream();
        $size = $stream->getSize();
        if ($size === null || $size < 1 || $size > self::MAX_LOGO_BYTES) {
            throw new RuntimeException('El logo supera el tamaño permitido o está vacío.');
        }

        $stream->rewind();
        $bytes = $stream->getContents();
        if (strlen($bytes) !== $size) {
            throw new RuntimeException('No fue posible validar el logo completo.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('El formato del logo no es válido.');
        }

        if (!is_dir($this->logoDirectory) && !mkdir($this->logoDirectory, 0700, true) && !is_dir($this->logoDirectory)) {
            throw new RuntimeException('No fue posible preparar el almacenamiento del logo.');
        }

        $basename = 'logo-' . bin2hex(random_bytes(16)) . '.' . self::MIME_EXTENSIONS[$mime];
        $path = rtrim($this->logoDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $basename;
        $logo->moveTo($path);
        @chmod($path, 0600);

        return [$basename, $path];
    }

    private function upsert(string $key, string $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP',
        );
        $statement->execute(['setting_key' => $key, 'setting_value' => $value]);
    }

    private function validLogoBasename(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $basename = trim($value);
        if (preg_match('/^logo-[a-f0-9]{32}\.(?:png|jpg|webp)$/', $basename) !== 1) {
            return null;
        }

        return $this->logoPath($basename) !== null ? $basename : null;
    }

    private function logoPath(string $basename): ?string
    {
        if (preg_match('/^logo-[a-f0-9]{32}\.(?:png|jpg|webp)$/', $basename) !== 1) {
            return null;
        }

        $path = rtrim($this->logoDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $basename;

        return is_file($path) ? $path : null;
    }
}
