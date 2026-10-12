<?php

declare(strict_types=1);

namespace AlquilaPro\UI;

use PDO;

final class Branding
{
    public const DEFAULT_APP_NAME = 'Alquila Pro';
    public const DEFAULT_PRIMARY_COLOR = '#1E6A5A';

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

    private function validLogoBasename(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $basename = trim($value);
        if (preg_match('/^logo-[a-f0-9]{32}\.(?:png|jpg|webp)$/', $basename) !== 1) {
            return null;
        }

        $path = rtrim($this->logoDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $basename;

        return is_file($path) ? $basename : null;
    }
}
