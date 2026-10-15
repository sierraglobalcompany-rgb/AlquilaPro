# F22 Task 3A GREEN checkpoint — 2026-10-15

## Canonical state

- Repository: `sierraglobalcompany-rgb/AlquilaPro`
- Base main: `7c0f0c75c665b9888d26bfdbfd5be9bec4df3b82`
- Active implementation branch: `impl/f22-security-privacy-audit-20261014`
- PR: #27 — OPEN / NOT MERGED
- Product head certified by this checkpoint: `d2bcd74183973c9708037539c15fe74f52043db5`
- Checkpoint branch: `checkpoint/f22-task3a-green-20261015`

## F22 progress

### Task 1 — migration 022 + canonical AuditService
CLOSED / GREEN.

### Task 2 — owner-only audit viewer + centralized security headers
CLOSED / GREEN.

### Task 3 — explicit high-value audit events
PRODUCT GREEN.

Original product head: `b8506b31597c6428f1da28caeb91a79845f8553a`.
CI #977 / run `38676786710`: PASS PHP 8.3 / 8.4 / 8.5.

### Task 3A — behavioral certification + anti-patch repair
CLOSED / GREEN.

#### Proven RED

- RED commit: `1a5a743f72f18332a38dc3950c647b3cfe71dcd8`
- CI #978 / run `38681519741`
- PHP 8.3: 588 tests / 6284 assertions / exactly 1 expected failure.
- Exact defect: when `audit_log` persistence failed during `POST /admin/users`, HTTP returned 500 but the newly created administrative user remained committed.
- Control cases passed: successful user creation wrote exactly one safe audit row; rejected business validation wrote neither user nor audit row.

#### Root cause

`UserService::create()` and route-level `AuditService::record()` were separate commits. A required audit write failure could therefore leave high-value business state committed without its corresponding evidence.

#### Minimal GREEN

- `ed5b8a552154709c55087721f05ec491403af316` — expose the canonical PDO only to the Auth route tree.
- `8aa23b37e8afbfcd3d8d1180e008ee355f640986` — wrap `user.create + user.created audit` in one local transaction using that same PDO.
- `f1c9f61e67fdb9d8f960861d375e551573ab974e` — remove an unnecessary global namespace `use PDO` warning/noise.
- No new class, table, dependency, event bus, auth stack, scheduler or constructor fanout.

#### Behavioral certification matrix

Commit `d2bcd74183973c9708037539c15fe74f52043db5` adds route-level behavioral certification using real route closures, real `AuditService`, real DB/migrations and minimal business-boundary fakes while existing module integration suites continue certifying the real domain services.

Covered route owners:

1. Auth — successful mutation, rejected mutation, audit failure rollback, canonical id, `REMOTE_ADDR`, no temporary-password leak.
2. UI — `appearance.updated` after successful appearance mutation.
3. Contracts — `contract.finalized` after successful finalization.
4. Leases — `lease.ended` using returned canonical lease identity.
5. Finance — `payment.rejected` without raw rejection-reason leakage.
6. Documents — `document.version_created` with only safe scope/type metadata and no document-body leakage.
7. Memberships — terminal membership charge decision event without private description leakage.

Existing `AuditEventOwnershipTest` still certifies the frozen allowlist placement, one owner per event, canonical `AuditService` dependency and `REMOTE_ADDR` only.

#### GREEN certification

CI #982 / run `38682661915`: PASS PHP 8.3 / 8.4 / 8.5.

PHP 8.3 exact result:

```text
594 tests
6342 assertions
0 failures
0 errors
```

`composer validate --strict`: PASS.
No PHPUnit warning remains from the Task 3A repair. Runner/MariaDB environment notices are not product warnings.

## KISS / Zero Noise / Anti-patch gate

PASS.

Architecture budget remains:

```text
F22_NEW_TABLES = 1          # audit_log
F22_NEW_DOMAIN_CLASSES = 1 # AuditService
NEW_PERMISSIONS = 0
NEW_RUNTIME_DEPS = 0
NEW_EVENT_BUS = 0
NEW_SECURITY_FRAMEWORK = 0
NEW_AUTH_STACK = 0
NEW_SCHEDULER = 0
```

Ruling:

- Do not inject `AuditService` into all business-service constructors.
- Do not retrofit transactions speculatively across every audit event without a reproducing RED.
- Preserve route-level explicit audit integration and canonical business owners.
- Future defects follow REPRODUCE → ROOT CAUSE → OWNER → RED → MINIMAL GREEN.

## Remaining F22 work

### Task 4 — F16 terminal notification-recipient retention
NOT STARTED in product at this checkpoint.

Frozen next contract:

```text
NotificationService::redactTerminalRecipients(string $now): int
retention = 90 days
old terminal sent/failed rows -> recipient_name/email NULL only
exact cutoff + newer + non-terminal rows -> unchanged
reuse existing POST /cron/notifications
no new route / cron / scheduler / table
```

### Tasks 5–6
NOT STARTED.

- Task 5: evidence-first security/privacy matrix; repair only proven REDs.
- Task 6: convergence, full affected/full suite once, fresh 10-pass audit, governance truth, canonical docs, final fresh CI and merge gate.

## Merge state

`MERGE_AUTHORIZED: NO`

PR #27 must remain open until Tasks 4–6 close and the final exact PR CI matrix is green.

## Next exact action

Task 4 RED only:

1. add focused privacy-retention service tests;
2. add existing-cron integration expectation;
3. verify RED is caused only by missing `redactTerminalRecipients()` / missing cron retention output;
4. checkpoint the RED;
5. implement minimal GREEN in `NotificationService` + existing notification cron only.

STOP_POINT: F22 Task 3A GREEN / Task 4 RED next.
