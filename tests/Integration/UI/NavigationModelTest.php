<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\UI;

use AlquilaPro\UI\View;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class NavigationModelTest extends TestCase
{
    public function testViewOwnsOnePermissionFilteredNavigationModel(): void
    {
        $method = new ReflectionMethod(View::class, 'navigationFor');
        self::assertTrue($method->isPrivate());

        $view = (new ReflectionClass(View::class))->newInstanceWithoutConstructor();

        $owner = $method->invoke($view, [
            'role' => 'owner',
            'permissions' => [],
        ]);
        self::assertSame([
            'users',
            'properties',
            'candidates',
            'contracts',
            'finance',
            'insurance',
            'maintenance',
            'whatsapp',
            'documents',
            'community',
            'memberships',
            'audit',
            'appearance',
        ], array_column($owner, 'key'));

        $gestor = $method->invoke($view, [
            'role' => 'gestor',
            'permissions' => ['properties.view', 'finance.view', 'memberships.view'],
        ]);
        self::assertSame(['properties', 'finance', 'memberships'], array_column($gestor, 'key'));

        $tenant = $method->invoke($view, [
            'role' => 'tenant',
            'permissions' => ['users.view', 'properties.view', 'finance.view'],
        ]);
        self::assertSame([], $tenant);

        foreach ($owner as $item) {
            self::assertSame(['key', 'label', 'href', 'icon', 'group'], array_keys($item));
            self::assertNotSame('', trim((string) $item['key']));
            self::assertNotSame('', trim((string) $item['label']));
            self::assertStringStartsWith('/admin/', (string) $item['href']);
            self::assertNotSame('', trim((string) $item['icon']));
            self::assertContains($item['group'], ['Operación', 'Finanzas', 'Gestión', 'Administración']);
        }

        self::assertSame(count($owner), count(array_unique(array_column($owner, 'key'))));
        self::assertSame(count($owner), count(array_unique(array_column($owner, 'href'))));
    }

    public function testLayoutConsumesNavigationInsteadOfOwningPermissionDecisions(): void
    {
        $root = dirname(__DIR__, 3);
        $layout = file_get_contents($root . '/resources/views/layouts/app.php');
        $view = file_get_contents($root . '/app/UI/View.php');

        self::assertIsString($layout);
        self::assertIsString($view);
        self::assertStringContainsString('navigationFor', $view);
        self::assertStringContainsString('$navigation', $layout);
        self::assertStringNotContainsString('Permissions::allows', $layout);
        self::assertStringContainsString('/admin/audit', $view);
    }
}
