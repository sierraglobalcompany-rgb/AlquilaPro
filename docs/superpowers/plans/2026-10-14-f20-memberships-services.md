# F20 Memberships + Digital Services Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let tenants accept lease-scoped add-on services with immutable price/duration history and an explicit admin `CREATE`/`SKIP` charge decision for paid memberships.

**Architecture:** Two tables (`digital_services`, `service_memberships`) and one `MembershipService`. Tenant acceptance never writes finance. Paid memberships surface a pending admin decision; `CREATE` calls the canonical `FinanceService::createManualCharge()`, while `SKIP` records a terminal no-charge decision.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO, PHPUnit, SSR.

**Spec:** `docs/specs/F20_MEMBERSHIPS_SERVICES_SPEC.md` plus `docs/specs/F20_PRODUCT_DECISION_2026-10-14.md`.

## Global Constraints

- Exactly 2 new tables and 1 new domain class.
- Exactly 2 permissions: `memberships.view`, `memberships.manage`.
- 0 payment/provider/history/renewal tables, public routes or runtime dependencies.
- `FinanceService` remains the only charge writer.
- Tenant acceptance and renewal never create charges automatically.
- `CREATE`/`SKIP` is one-shot for each paid membership.

## Review Focus

1. Concurrent tenant acceptance cannot create duplicate overlapping service memberships.
2. `CREATE` vs `SKIP` race produces one terminal financial decision.
3. Failed charge link transaction cannot leave orphan F9 charge or half-decided membership.
4. Service price/duration edits never rewrite historical membership snapshots.
5. Tenant ownership is proven before service acceptance or membership history lookup.

---

### Task 1: Schema + service catalog

**Files:**
- Create: `database/migrations/020_create_digital_services_memberships.sql`
- Create: `app/Memberships/MembershipService.php`
- Create: `tests/Integration/Memberships/MembershipSchemaTest.php`
- Create: `tests/Integration/Memberships/MembershipServiceTest.php`

**Constructor:**

```php
MembershipService::__construct(PDO $pdo, FinanceService $finance)
```

**Catalog interfaces:**

```php
MembershipService::createService(string $name, string $description, string $price, int $durationDays, int $actorUserId, int $now): array
MembershipService::updateService(int $serviceId, string $name, string $description, string $price, int $durationDays, bool $active, int $actorUserId, int $now): array
MembershipService::findService(int $serviceId): ?array
MembershipService::listServices(bool $activeOnly = false): array
```

- [x] RED exact two tables/FKs/indexes/unique nullable `charge_id`; no membership status/history/payment/provider tables.
- [x] GREEN migration.
- [x] RED service name/description/price/duration/active validation and no historical delete.
- [x] GREEN service catalog authority.
- [x] Run schema/catalog focused tests.

Task 1 result: RED found migration 020 missing (19 applied vs 20), no F20 tables, and no `MembershipService`. GREEN adds exactly `digital_services` and `service_memberships` with nullable unique `charge_id`, specified foreign keys and derived charge-decision constraints, plus a validated non-deleting catalog authority. Focused migration/schema/catalog set passed 5 tests / 75 assertions.

### Task 2: Tenant acceptance + renewal history

**Interfaces:**

```php
MembershipService::accept(int $serviceId, int $leaseId, int $tenantUserId, string $expectedPrice, int $expectedDurationDays, string $today, int $now): array
MembershipService::renew(int $membershipId, int $tenantUserId, string $expectedPrice, int $expectedDurationDays, string $today, int $now): array
MembershipService::end(int $membershipId, int $actorUserId, int $now): array
MembershipService::findMembership(int $membershipId): ?array
MembershipService::listForLease(int $leaseId): array
MembershipService::listPendingChargeDecisions(): array
```

Acceptance/renewal transaction:

```text
BEGIN
→ lock canonical lease row
→ validate tenant/lease ownership context supplied by route and service invariants
→ validate active service
→ compare current service price/duration with the terms shown and confirmed by the tenant
→ reject overlapping active same-service membership
→ snapshot current price/duration
→ insert membership
→ COMMIT
```

- [x] RED acceptance snapshot dates/price/duration and free-vs-paid pending decision projection.
- [x] RED accept/accept and renew/renew concurrency.
- [x] GREEN lease-row serialization and immutable historical rows.
- [x] RED renewal creates new row with `renews_membership_id`, never mutates prior snapshot.
- [x] Run lifecycle/concurrency focused tests.

Task 2 result: `MembershipService` now implements lease-locked acceptance, renewal, end, per-lease listing, and the paid pending-decision projection. Accept/accept and renew/renew process races each leave exactly one winner; ending preserves the snapshot and permits a new active period. Focused migration/schema/service/concurrency set passed 11 tests / 135 assertions.

Task 2: Ruling: renew an unended membership through the day after its `ends_on`; start a renewal today when the source has ended or expired — this keeps active terms contiguous and permits restart after early end; cost if wrong: renewal timing may differ from the business's intended immediate or scheduled-start policy.

### Task 3: Explicit admin charge decision

**Interface:**

```php
MembershipService::decideCharge(int $membershipId, string $decision, ?string $dueDate, ?string $description, int $actorUserId, int $now): array
```

Allowlist:

```text
create
skip
```

CREATE transaction:

```text
BEGIN
→ read membership lease id, then lock canonical lease row
→ membership FOR UPDATE scoped to that lease
→ require price_snapshot > 0
→ require charge_decision IS NULL
→ FinanceService::createManualCharge(lease_id,'service',price_snapshot,dueDate,description,null,actor)
→ set decision=create + charge_id + actor/time
→ COMMIT
```

SKIP:

```text
lock membership
→ require paid + undecided
→ set decision=skip + actor/time
→ charge_id stays NULL
```

- [x] RED free membership cannot enter decision flow.
- [x] RED CREATE requires admin due date/nonempty bounded description; amount cannot be overridden from snapshot.
- [x] RED CREATE/CREATE, CREATE/SKIP, SKIP/SKIP races.
- [x] RED forced DB/link failure rolls back inserted charge and decision.
- [x] GREEN minimal transaction using canonical `FinanceService`, no raw charges INSERT.
- [x] Run F20 + affected F9 finance regression tests.

Task 3 result: paid `CREATE` delegates to `FinanceService::createManualCharge()` on the membership transaction's PDO, uses the immutable snapshot, and stores the unique charge link and decision actor/time atomically. `SKIP` records only the terminal decision; free services cannot enter either decision path. All three concurrent decision races and forced membership-link rollback pass. F20 + F9 regression set passed 43 tests / 386 assertions.

### Task 4: Permissions + staff/tenant surfaces

**Files:**
- Modify: `app/Auth/Permissions.php`
- Create: `app/Memberships/routes.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Leases/routes.php`
- Modify: `resources/views/layouts/app.php`
- Create: `resources/views/memberships.php`
- Modify: `resources/views/tenant_lease_detail.php`
- Create: `tests/Integration/Memberships/MembershipRoutesTest.php`
- Modify: `tests/Integration/Leases/TenantPortalRoutesTest.php`
- Modify: `tests/Integration/UI/ShellRoutesTest.php`
- Modify: `tests/Integration/Finance/FinanceSecurityRegressionTest.php`
- Modify: `tests/Integration/Community/CommunityServiceTest.php`

- [x] RED `memberships.view/manage`, owner bypass, tenant hard deny for staff routes.
- [x] Staff UI: service catalog/history + pending paid decisions with exactly `Crear cargo` / `No crear cargo`.
- [x] Tenant UI: available active services, accept/renew action, own history and safe charge outcome.
- [x] Tenant route calls `LeaseService::findForUser()` before service/membership lookup/mutation.
- [x] Wire one `MembershipService($pdo,$finance)` in bootstrap.
- [x] Keep CSRF/PRG/basePath/forced-password patterns.
- [x] Run F20 routes + F8/F9/auth focused tests.

Task 4 result: staff and tenant routes/views are wired to one `MembershipService`; permission gates, explicit decision actions, lease-first ownership checks, safe tenant projections, CSRF/PRG, basePath and forced-password behavior pass. The F9 structural guard was updated to account for the additional shared dependency. Focused F20/F8/F9/auth/launcher set passed 117 tests / 1334 assertions.

### Task 5: Architecture freeze + certification

**Files:**
- Create: `tests/Integration/Memberships/MembershipSecurityRegressionTest.php`

- [x] Freeze 2 tables / 1 service / 2 permissions / 0 automatic finance side effect/provider/history table.
- [x] Assert no F20 raw `INSERT INTO charges` and no acceptance path calls charge creation.
- [x] Fresh whole-range review before remote CI; repair both Important findings.
- [x] `composer validate --strict`, `git diff --check`, PHP lint, and F20 + affected finance/lease/security focused suite.
- [x] Product Head → docs-only Closure Head.
- [ ] One push; stacked PR against F19 closure branch; verify the PHP 8.3/8.4/8.5 PR CI.
- [ ] Stop without merge/F21.

Task 5 local result: architecture regression freeze passes; fresh independent review found 0 Critical, 2 Important (both repaired), and 6 Minor (deferred below). The repair displays current price/duration/start/end terms before acceptance or renewal, rejects stale confirmations with 409, and serializes charge decisions lease-first to match membership creation and renewal. Final focused set passed 120 tests / 1419 assertions on PHP 8.5.8. `composer validate --strict`, `git diff --check`, and PHP lint for all 9 changed PHP files pass. Product Head: `5f46c638`. The docs-only Closure Head follows this local certification; remote push, stacked PR, and CI verification remain the next step.

Review Minor findings deferred: renewal history lookup is not explicitly scoped by the verified lease ID (foreign ownership still returns no route data); SQL CHECK constraints can evaluate UNKNOWN for nullable charge fields; staff history is capped at 200 without paging; a negative CSRF test uses a view-only account, so permission denial can mask CSRF behavior; the architecture test does not enumerate every permission in the memberships namespace; expected mutation failures return blank 422 responses. Reviewer declined to judge: tenant/staff early-termination UI policy; refunds/proration; pre-existing staff permission-editing controls; recovery from partial migration DDL failure. These are outside the Critical/Important repair gate or lack a defined product policy. No additional business ruling was introduced; the F20 Task 2 renewal-date ruling remains the only F20 business ruling.

Remote submission: one push of the Product Head and docs-only Closure Head, then one stacked PR targeting `impl/f19-community-legal-20261014`; do not merge and do not begin F21.
