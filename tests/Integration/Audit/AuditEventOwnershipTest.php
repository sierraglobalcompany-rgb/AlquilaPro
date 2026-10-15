<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Audit;

use PHPUnit\Framework\TestCase;

final class AuditEventOwnershipTest extends TestCase
{
    /** @var array<string, list<string>> */
    private const EVENTS_BY_OWNER = [
        'app/Auth/routes.php' => [
            'user.created',
            'user.permissions_changed',
            'user.active_changed',
            'user.password_reset_issued',
        ],
        'app/UI/routes.php' => [
            'appearance.updated',
        ],
        'app/Contracts/routes.php' => [
            'contract.finalized',
        ],
        'app/Leases/routes.php' => [
            'lease.activated',
            'lease.ended',
            'bonus.decided',
            'liquidation.finalized',
        ],
        'app/Finance/routes.php' => [
            'charge.created',
            'charge.voided',
            'payment.reconciled',
            'payment.rejected',
        ],
        'app/Documents/routes.php' => [
            'document.version_created',
        ],
        'app/Memberships/routes.php' => [
            'membership.charge_created',
            'membership.charge_skipped',
        ],
    ];

    public function testFrozenAuditActionsLiveExactlyOnceInTheirApprovedRouteOwner(): void
    {
        $sources = [];
        foreach (array_keys(self::EVENTS_BY_OWNER) as $path) {
            $contents = file_get_contents(dirname(__DIR__, 3) . '/' . $path);
            self::assertIsString($contents, $path . ' must be readable.');
            $sources[$path] = $contents;
        }

        self::assertSame(17, array_sum(array_map('count', self::EVENTS_BY_OWNER)));

        foreach (self::EVENTS_BY_OWNER as $owner => $actions) {
            foreach ($actions as $action) {
                self::assertSame(
                    1,
                    substr_count($sources[$owner], "'" . $action . "'"),
                    $action . ' must appear exactly once in ' . $owner . '.',
                );

                foreach ($sources as $otherOwner => $source) {
                    if ($otherOwner === $owner) {
                        continue;
                    }
                    self::assertSame(
                        0,
                        substr_count($source, "'" . $action . "'"),
                        $action . ' must not be duplicated in ' . $otherOwner . '.',
                    );
                }
            }
        }
    }

    public function testEveryApprovedOwnerUsesCanonicalAuditServiceAndRemoteAddrOnly(): void
    {
        foreach (array_keys(self::EVENTS_BY_OWNER) as $path) {
            $source = file_get_contents(dirname(__DIR__, 3) . '/' . $path);
            self::assertIsString($source, $path . ' must be readable.');
            self::assertStringContainsString('AlquilaPro\\Audit\\AuditService', $source, $path);
            self::assertStringContainsString('$deps[\'audit\']', $source, $path);
            self::assertStringContainsString("['REMOTE_ADDR'] ?? null", $source, $path);
            self::assertStringNotContainsString('HTTP_X_FORWARDED_FOR', $source, $path);
            self::assertStringNotContainsString('HTTP_FORWARDED', $source, $path);
        }
    }

    public function testAuditDoesNotExpandIntoNotificationCronOrReadOnlyAuditViewer(): void
    {
        $notificationRoutes = file_get_contents(dirname(__DIR__, 3) . '/app/Notifications/routes.php');
        $auditRoutes = file_get_contents(dirname(__DIR__, 3) . '/app/Audit/routes.php');
        self::assertIsString($notificationRoutes);
        self::assertIsString($auditRoutes);

        self::assertStringNotContainsString('$deps[\'audit\']', $notificationRoutes);
        self::assertStringNotContainsString('->record(', $notificationRoutes);
        self::assertStringNotContainsString('->record(', $auditRoutes);
    }
}
