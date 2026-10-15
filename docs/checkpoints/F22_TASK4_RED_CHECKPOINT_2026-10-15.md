# F22 Task 4 RED checkpoint — 2026-10-15

## Canonical state

- Repository: `sierraglobalcompany-rgb/AlquilaPro`
- Base main: `7c0f0c75c665b9888d26bfdbfd5be9bec4df3b82`
- Active implementation branch: `impl/f22-security-privacy-audit-20261014`
- PR: #27 — OPEN / NOT MERGED
- Task 3A GREEN product head: `d2bcd74183973c9708037539c15fe74f52043db5`
- Task 3A checkpoint: `ad411b09bcbc7152b60871f1d17fb1132f1215f4`
- Task 4 RED product head: `3058e2132c24b80f880a2eb44de5be12f0ea3e33`
- Checkpoint branch: `checkpoint/f22-task4-red-20261015`

## Task 4 contract

F16 terminal notification-recipient privacy retention must remain owned by the existing `NotificationService` and existing protected notification cron.

Frozen interface:

```text
NotificationService::redactTerminalRecipients(string $now): int
```

Frozen behavior:

```text
retention = 90 days
terminal sent/failed row older than cutoff -> recipient_email + recipient_name = NULL
row exactly at cutoff -> unchanged
newer terminal row -> unchanged
non-terminal row -> unchanged
all operational evidence -> unchanged
second execution -> 0 affected rows
existing POST /cron/notifications -> invokes retention and returns safe numeric redacted count
```

No new route, cron, scheduler, table, worker, privacy table, hash backup or recipient copy.

## RED tests

New focused test:

```text
tests/Integration/Notifications/NotificationPrivacyRetentionTest.php
```

RED commits:

- `9c26fcd103b8be9d113df30a42eff361d51fdccd` — define terminal recipient retention RED.
- `3058e2132c24b80f880a2eb44de5be12f0ea3e33` — tighten RED assertions to avoid test-warning noise and assert exact safe cron keys.

Coverage:

1. Old sent terminal recipient is redacted.
2. Old failed terminal recipient is redacted.
3. Exact 90-day cutoff is preserved.
4. Newer terminal recipient is preserved.
5. Pending/non-terminal recipient is preserved.
6. Only recipient name/email may change; type/template/payload/dedupe/schedule/attempt/terminal timestamps/error/created evidence stays identical.
7. Redaction is idempotent.
8. Existing protected cron executes retention.
9. Cron response exposes only safe counts and never recipient data or cron secret.

## Verified RED

Canonical RED CI:

```text
CI #984
run 38683381860
PHP 8.3
596 tests
6359 assertions
1 error
1 failure
```

Exact expected causes:

```text
ERROR:
Call to undefined method
AlquilaPro\Notifications\NotificationService::redactTerminalRecipients()

FAILURE:
existing protected cron response does not contain key `redacted`
```

All pre-existing NotificationService and NotificationCronRoutes tests passed before the two new RED expectations.
`composer validate --strict`: PASS.
No fixture/syntax/schema failure caused the RED.

## KISS / Zero Noise / Anti-patch ruling

RED is valid and narrow.

Minimal GREEN is limited to:

```text
app/Notifications/NotificationService.php
app/Notifications/routes.php
existing cron tests only if exact safe response shape requires `redacted => 0`
```

Expected implementation:

- one internal fixed 90-day constant;
- validate/normalize `$now` through existing timestamp authority;
- compute UTC cutoff;
- one bounded SQL UPDATE;
- null only the two already-nullable recipient snapshot columns;
- return affected row count;
- call retention from the existing protected cron before reminder processing/sending;
- add only numeric `redacted` to existing safe JSON response.

Architecture budget remains unchanged:

```text
F22_NEW_TABLES = 1          # audit_log only
F22_NEW_DOMAIN_CLASSES = 1 # AuditService only
NEW_PERMISSIONS = 0
NEW_RUNTIME_DEPS = 0
NEW_EVENT_BUS = 0
NEW_SECURITY_FRAMEWORK = 0
NEW_AUTH_STACK = 0
NEW_SCHEDULER = 0
```

## Merge state

`MERGE_AUTHORIZED: NO`

## Next exact action

Implement Task 4 minimal GREEN only, certify PHP 8.3 / 8.4 / 8.5, then create Task 4 GREEN checkpoint before Task 5.

STOP_POINT: F22 Task 4 RED VERIFIED / GREEN next.
