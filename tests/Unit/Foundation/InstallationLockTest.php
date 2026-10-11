<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Unit\Foundation;

use AlquilaPro\Foundation\InstallationLock;
use PHPUnit\Framework\TestCase;

final class InstallationLockTest extends TestCase
{
    private string $directory;
    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/alquila_lock_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->path = $this->directory . '/installed.lock';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->path . '.tmp');
        @rmdir($this->directory);
    }

    public function testAbsentLockMeansApplicationIsNotInstalled(): void
    {
        if (!class_exists(InstallationLock::class)) {
            self::fail('InstallationLock must exist before this behavior can pass.');
        }

        self::assertFalse((new InstallationLock($this->path))->isInstalled());
    }

    public function testValidLockMeansApplicationIsInstalled(): void
    {
        if (!class_exists(InstallationLock::class)) {
            self::fail('InstallationLock must exist before this behavior can pass.');
        }

        file_put_contents($this->path, json_encode([
            'schema_version' => '1',
            'installed_at' => '2026-10-11T18:30:00-05:00',
        ], JSON_THROW_ON_ERROR));

        self::assertTrue((new InstallationLock($this->path))->isInstalled());
    }

    public function testMalformedExistingLockFailsClosedAsInstalled(): void
    {
        if (!class_exists(InstallationLock::class)) {
            self::fail('InstallationLock must exist before this behavior can pass.');
        }

        file_put_contents($this->path, '{broken-json');

        self::assertTrue((new InstallationLock($this->path))->isInstalled());
    }

    public function testWritePersistsOnlySafeInstallationMetadata(): void
    {
        if (!class_exists(InstallationLock::class)) {
            self::fail('InstallationLock must exist before this behavior can pass.');
        }

        $lock = new InstallationLock($this->path);
        $lock->write([
            'schema_version' => '1',
            'installed_at' => '2026-10-11T18:30:00-05:00',
            'password' => 'must-not-be-written',
            'database_password' => 'also-secret',
        ]);

        $stored = json_decode((string) file_get_contents($this->path), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame([
            'schema_version' => '1',
            'installed_at' => '2026-10-11T18:30:00-05:00',
        ], $stored);
        self::assertTrue($lock->isInstalled());
    }
}
