# F15 Agenda + Visits Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add visit scheduling to existing F14 maintenance requests without adding notification or provider systems.

**Architecture:** One `maintenance_visits` table and one `VisitService`. Staff routes converge into `app/Maintenance/routes.php`; tenant selection converges into `app/Leases/routes.php`. Existing `maintenance.view/manage`, auth, CSRF and tenant ownership remain authoritative.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO, MariaDB/MySQL, PHPUnit, SSR.

**Spec:** `docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md` — F15 section.

## Global Constraints

- Exactly 1 new table: `maintenance_visits`.
- Exactly 1 new domain class: `AlquilaPro\Maintenance\VisitService`.
- 0 new permissions; reuse `maintenance.view/manage`.
- 0 runtime dependencies, public routes, slot/provider/notification tables.
- No F16 reminder/email/WhatsApp behavior.
- No duplicated `lease_id`; visit derives lease from maintenance request.

## Review Focus

1. Tenant selects a slot only after lease ownership is proven.
2. Reprogram/select races cannot leave a stale selected time.
3. Complete/cancel races produce one terminal result.
4. Slots are unique, future, sorted and bounded to 1..10.
5. F14 request terminal/cancelled state cannot gain invalid future visits.

---

### Task 1: Schema + visit authority

**Files:**
- Create: `database/migrations/015_create_maintenance_visits.sql`
- Create: `app/Maintenance/VisitService.php`
- Create: `tests/Integration/Maintenance/VisitSchemaTest.php`
- Create: `tests/Integration/Maintenance/VisitServiceTest.php`

**Interfaces:**

```php
VisitService::__construct(PDO $pdo)
VisitService::propose(int $requestId, array $slots, ?string $technicianName, ?string $technicianContact, int $actorUserId, int $now): array
VisitService::scheduleDirect(int $requestId, string $startAt, int $durationMinutes, ?string $technicianName, ?string $technicianContact, int $actorUserId, int $now): array
VisitService::find(int $visitId): ?array
VisitService::listForRequest(int $requestId): array
VisitService::selectSlot(int $visitId, string $startAt, int $actorUserId, int $now): array
VisitService::reprogram(int $visitId, array $slots, int $actorUserId, int $now): array
VisitService::complete(int $visitId, ?string $resultNotes, int $actorUserId, int $now): array
VisitService::cancel(int $visitId, string $reason, int $actorUserId, int $now): array
```

- [ ] RED schema: exact single table, FK `maintenance_request_id`, minimal indexes, no lease/provider/notification fields.
- [ ] GREEN migration.
- [ ] RED service: proposal validation, direct scheduling, transitions, technician length/contact safety, terminal immutability.
- [ ] GREEN minimal `VisitService` with row locks for transitions.
- [ ] Run focused schema/service tests.

### Task 2: Concurrency + staff surface

**Files:**
- Modify: `app/Maintenance/routes.php`
- Modify: `resources/views/maintenance.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Integration/Maintenance/VisitConcurrencyTest.php`
- Create/modify: `tests/Support/VisitConcurrencyWorker.php`
- Modify: `tests/Integration/Maintenance/MaintenanceRoutesTest.php`

- [ ] RED races: select/select, select/reprogram, complete/cancel, reprogram/complete.
- [ ] GREEN lock order: visit row first; request only for invariant checks.
- [ ] Wire one `VisitService` instance in bootstrap.
- [ ] Add staff propose/direct schedule/reprogram/complete/cancel UI under existing Maintenance routes and permissions.
- [ ] Keep PRG, CSRF, forced-password gate and subfolder/basePath patterns.
- [ ] Run F14 + F15 focused staff/concurrency tests.

### Task 3: Tenant selection + architecture freeze

**Files:**
- Modify: `app/Leases/routes.php`
- Modify: `resources/views/tenant_lease_detail.php`
- Modify: `tests/Integration/Leases/TenantPortalRoutesTest.php`
- Create: `tests/Integration/Maintenance/VisitSecurityRegressionTest.php`

- [ ] RED ownership ordering: `LeaseService::findForUser()` before request/visit lookup.
- [ ] RED tenant can select only a currently proposed slot for own request; cannot set technician/complete/cancel.
- [ ] GREEN tenant visit projection and selection form.
- [ ] Freeze 1 table / 1 service / 0 permissions / 0 F16 surface.
- [ ] Run F15 + affected F14/F8/auth/CSRF focused suite.

### Task 4: Phase certification

- [ ] Environment smoke before remote certification.
- [ ] Fresh whole-phase review before final CI; repair only Critical/Important findings.
- [ ] `composer validate --strict` and `git diff --check`.
- [ ] Define `F15_PRODUCT_HEAD`.
- [ ] Update batch checkpoint/README/SPEC status only; closure must be docs-only.
- [ ] One push; PR `F15: agenda and visits` against current `main`.
- [ ] One PR CI matrix PHP 8.3/8.4/8.5; record merge-ref SHA and counts.
- [ ] Stop without merge and without F16 if gate fails.
