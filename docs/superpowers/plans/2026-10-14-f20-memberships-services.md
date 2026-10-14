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

- [ ] RED exact two tables/FKs/indexes/unique nullable `charge_id`; no membership status/history/payment/provider tables.
- [ ] GREEN migration.
- [ ] RED service name/description/price/duration/active validation and no historical delete.
- [ ] GREEN service catalog authority.
- [ ] Run schema/catalog focused tests.

### Task 2: Tenant acceptance + renewal history

**Interfaces:**

```php
MembershipService::accept(int $serviceId, int $leaseId, int $tenantUserId, string $today, int $now): array
MembershipService::renew(int $membershipId, int $tenantUserId, string $today, int $now): array
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
→ reject overlapping active same-service membership
→ snapshot current price/duration
→ insert membership
→ COMMIT
```

- [ ] RED acceptance snapshot dates/price/duration and free-vs-paid pending decision projection.
- [ ] RED accept/accept and renew/renew concurrency.
- [ ] GREEN lease-row serialization and immutable historical rows.
- [ ] RED renewal creates new row with `renews_membership_id`, never mutates prior snapshot.
- [ ] Run lifecycle/concurrency focused tests.

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
→ membership FOR UPDATE
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

- [ ] RED free membership cannot enter decision flow.
- [ ] RED CREATE requires admin due date/nonempty bounded description; amount cannot be overridden from snapshot.
- [ ] RED CREATE/CREATE, CREATE/SKIP, SKIP/SKIP races.
- [ ] RED forced DB/link failure rolls back inserted charge and decision.
- [ ] GREEN minimal transaction using canonical `FinanceService`, no raw charges INSERT.
- [ ] Run F20 + affected F9 finance regression tests.

### Task 4: Permissions + staff/tenant surfaces

**Files:**
- Modify: `app/Auth/Permissions.php`
- Create: `app/Memberships/routes.php`
- Modify: `bootstrap/app.php`
- Create: `resources/views/memberships.php`
- Modify: `resources/views/tenant_lease_detail.php`
- Create: `tests/Integration/Memberships/MembershipRoutesTest.php`
- Modify: `tests/Integration/Leases/TenantPortalRoutesTest.php`

- [ ] RED `memberships.view/manage`, owner bypass, tenant hard deny for staff routes.
- [ ] Staff UI: service catalog/history + pending paid decisions with exactly `Crear cargo` / `No crear cargo`.
- [ ] Tenant UI: available active services, accept/renew action, own history and safe charge outcome.
- [ ] Tenant route calls `LeaseService::findForUser()` before service/membership lookup/mutation.
- [ ] Wire one `MembershipService($pdo,$finance)` in bootstrap.
- [ ] Keep CSRF/PRG/basePath/forced-password patterns.
- [ ] Run F20 routes + F8/F9/auth focused tests.

### Task 5: Architecture freeze + certification

**Files:**
- Create: `tests/Integration/Memberships/MembershipSecurityRegressionTest.php`

- [ ] Freeze 2 tables / 1 service / 2 permissions / 0 automatic finance side effect/provider/history table.
- [ ] Assert no F20 raw `INSERT INTO charges` and no acceptance path calls charge creation.
- [ ] Fresh review before remote CI; repair only Critical/Important.
- [ ] `composer validate --strict`, `git diff --check`, F20 + affected finance/lease/security focused suite.
- [ ] Product Head → docs-only Closure Head.
- [ ] One push; stacked PR against F19 closure branch; one PHP 8.3/8.4/8.5 PR CI.
- [ ] Stop without merge/F21.
