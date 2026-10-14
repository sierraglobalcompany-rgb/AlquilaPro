# F21 — Bonus + Liquidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the good-conduct bonus and immutable lease liquidation without creating a second ledger, a second lease-ending workflow, or a second document/inventory authority.

**Architecture:** `LeaseService` remains the only lease lifecycle writer; `FinanceService`, `DocumentService`, `InsuranceService`, `CommunityService`, `ObligationService`, and the certified F20 `MembershipService` remain their domain authorities. F21 adds one composition service, `AlquilaPro\Leases\TerminationService`, plus two tables: one optional terminal bonus decision and one append-once liquidation snapshot per lease.

**Tech Stack:** PHP >=8.3 <8.6, Slim 4, PDO/MySQL, PHPUnit 11.5, existing SSR views and migrations.

**Spec:** `docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md` — sections F21 / 3–15.

## Global Constraints

- `NEW_TABLES <= 2`: `good_conduct_bonuses`, `lease_liquidations`.
- `NEW_DOMAIN_CLASSES = 1`: `TerminationService`.
- `NEW_RUNTIME_DEPENDENCIES = 0`.
- `NEW_PERMISSIONS = 0`; reuse `contracts.view`, `contracts.manage`, `finance.view`.
- `LeaseService::end()` is the only lease lifecycle writer.
- `FinanceService` is the only charge/payment authority; F21 writes no charges, payments, refunds or credits.
- `DocumentService` is the only document/inventory authority; F21 writes no document/inventory tables.
- Bonus feature is disabled by default via `ALQUILAPRO_GOOD_CONDUCT_BONUS_ENABLED=false`.
- No draft liquidation table, item table, status-history table, payout provider, accounting journal or second scheduler.
- Tenant ownership must be resolved with `LeaseService::findForUser()` before liquidation lookup.
- F21 must not add a second F20 read API: membership blockers are derived from the canonical `MembershipService::listForLease()` projection.
- No implementation starts until F15→F20 are converged, audited and integrated sequentially under explicit merge authorization.

## Pre-Execution Gate — certified F20 remote contract

- [x] Final F20 product contract re-read from the remote PR: Product Head `5f46c63f82672e6d34566284051a5f62e1db56db`; docs closure `5d2488286ce8370402544eaf4276a34a8d83a40d`; later test-only convergence does not change product behavior.
- [x] Final `app/Memberships/MembershipService.php`, migration 020, routes and concurrency/security tests reviewed.
- [x] Canonical read projection exists: `MembershipService::listForLease(int $leaseId): array`.
- [x] Pending paid charge decision is already exposed by each normalized membership as `charge_decision_required`; F21 derives the blocker by filtering `listForLease()` and **must not add** `hasPendingPaidChargeDecision()` or an equivalent duplicate predicate.
- [x] Acceptance and renewal lock an active tenant lease and reject an ended lease (`ended_at IS NULL`).
- [ ] Before Task 1, re-read the final integrated F20 branch after sequential F15→F20 merges only to confirm method names/fields are unchanged; if a material product contract changed during integration, stop and repair the owning phase rather than patching F21.

## Review Focus

1. Active lease passed to finalization must fail before any liquidation row is inserted — covered by Task 4.
2. Pending payment reconciliation must block finalization while an ordinary outstanding balance must not — covered by Task 3/4.
3. Paid F20 membership with unresolved CREATE/SKIP must block finalization — derive from `listForLease()` + `charge_decision_required`; covered by Task 3/4.
4. Initial inventory without final inventory must block finalization — covered by Task 3/4.
5. Tenant requesting another tenant's liquidation must receive 404 and no child lookup before ownership — covered by Task 5.

---

### Task 1: Schema and canonical lease read

**Files:**
- Create: `database/migrations/021_create_lease_termination.sql`
- Modify: `app/Leases/LeaseService.php`
- Create: `tests/Integration/Leases/TerminationSchemaTest.php`
- Modify: `tests/Integration/Leases/LeaseServiceTest.php`

**Interfaces:**
- Consumes: existing `leases`, `users`, migration runner.
- Produces: `LeaseService::find(int $leaseId): ?array`; tables `good_conduct_bonuses`, `lease_liquidations`.

- [ ] **Step 1: Write the RED schema test** asserting migration 021 creates only the two approved F21 tables, one unique `lease_id` in each, terminal bonus decision constraint, and no `status`, item, history, payment or ledger table.
- [ ] **Step 2: Run RED** with `vendor/bin/phpunit tests/Integration/Leases/TerminationSchemaTest.php` and require failure because migration 021 does not exist.
- [ ] **Step 3: Add migration 021** with the exact SPEC fields. `lease_liquidations` must have no functional update/delete state; `good_conduct_bonuses.decision` is `NULL|approved|denied`.
- [ ] **Step 4: Write RED for `LeaseService::find(int $leaseId): ?array`** proving it returns the same normalized projection as existing lease reads and returns `null` for invalid/missing ids.
- [ ] **Step 5: Implement the minimal `find()`** by reusing `leaseSelect()` + `normalizeLease()`; do not duplicate the projection SQL.
- [ ] **Step 6: Run GREEN** for `TerminationSchemaTest` and `LeaseServiceTest`.
- [ ] **Step 7: Commit** `feat(f21): add termination schema and canonical lease lookup`.

### Task 2: Bonus terminal workflow

**Files:**
- Create: `app/Leases/TerminationService.php`
- Create: `tests/Integration/Leases/TerminationBonusTest.php`

**Interfaces:**
- Constructor: `__construct(PDO $pdo, LeaseService $leases, FinanceService $finance, ObligationService $obligations, InsuranceService $insurance, DocumentService $documents, CommunityService $community, MembershipService $memberships, bool $bonusEnabled)`.
- Produces:
  - `bonusForLease(int $leaseId): ?array`
  - `configureBonus(int $leaseId, string $amount, ?string $note, int $actorUserId, int $now): array`
  - `decideBonus(int $leaseId, string $decision, int $actorUserId, int $now): array`

- [ ] **Step 1: Write RED tests** for feature disabled by default, amount `> 0`, note `<=1000`, one row per lease, pending edit allowed, `approved|denied` terminal, concurrent decision one winner, and no writes to `charges`/`payments`.
- [ ] **Step 2: Run RED** with `vendor/bin/phpunit tests/Integration/Leases/TerminationBonusTest.php`.
- [ ] **Step 3: Implement only the bonus methods** in `TerminationService`; use `SELECT ... FOR UPDATE` on the bonus row for decision/update races and stable generic domain errors.
- [ ] **Step 4: Run GREEN** for the bonus test plus `tests/Integration/Finance`.
- [ ] **Step 5: Commit** `feat(f21): add terminal good conduct bonus workflow`.

### Task 3: Derived liquidation preview and blockers

**Files:**
- Modify: `app/Leases/TerminationService.php`
- Create: `tests/Integration/Leases/TerminationPreviewTest.php`

**Interfaces:**
- Produces: `preview(int $leaseId, string $today, array $handover = []): array`.
- `handover` accepts only `property_returned` boolean, `keys_returned_count` integer `0..100`, and optional trimmed `notes <= 2000`.
- Preview returns canonical sections: `lease`, `contract`, `handover`, `finance`, `obligations`, `claims`, `inventories`, `current_documents`, `community`, `memberships`, `bonus`, `blockers`.
- F20 membership blocker source is fixed: `$memberships->listForLease($leaseId)` and any row with `charge_decision_required === true` blocks finalization.

- [ ] **Step 1: Write RED tests** proving preview persists nothing and derives data from existing authorities.
- [ ] **Step 2: Add RED blockers** for pending payment report, pending paid membership charge decision, pending bonus, and initial inventory without final inventory.
- [ ] **Step 3: Add RED non-blockers** proving outstanding balance, open community incident and open insurance claim are included but do not alone block finalization.
- [ ] **Step 4: Run RED** with `vendor/bin/phpunit tests/Integration/Leases/TerminationPreviewTest.php`.
- [ ] **Step 5: Implement minimal preview** using existing public service APIs. Do not modify F20 or add a convenience predicate for membership decisions. If a real authority gap is proven, stop and repair the owning phase separately.
- [ ] **Step 6: Ensure deterministic ordering** by stable id/date ordering before snapshot assembly; do not add a canonicalization framework.
- [ ] **Step 7: Run GREEN** for preview plus focal Finance/Documents/Insurance/Community/Memberships tests.
- [ ] **Step 8: Commit** `feat(f21): derive liquidation preview from canonical authorities`.

### Task 4: Immutable final liquidation

**Files:**
- Modify: `app/Leases/TerminationService.php`
- Create: `tests/Integration/Leases/TerminationFinalizationTest.php`
- Create: `tests/Integration/Leases/TerminationConcurrencyTest.php`

**Interfaces:**
- Produces:
  - `liquidationForLease(int $leaseId): ?array`
  - `finalizeLiquidation(int $leaseId, array $handover, ?string $summaryNote, int $actorUserId, string $today, int $now): array`

- [ ] **Step 1: Write RED finalization tests** for active lease rejection, ended lease success, blocker rejection, bounded summary note `<=2000`, immutable `schema_version=1` snapshot, and no financial writer side effects.
- [ ] **Step 2: Write RED concurrency test** `finalize vs finalize` proving exactly one liquidation row and one stable winner.
- [ ] **Step 3: Run RED** for both finalization test files.
- [ ] **Step 4: Implement transaction**: lock canonical lease row; require `ended_at`; require no existing liquidation; recompute preview/blockers under the same connection; insert one deterministic JSON snapshot; commit.
- [ ] **Step 5: Do not add update/delete methods** for liquidation.
- [ ] **Step 6: Run GREEN** and verify the second concurrent/follow-up finalization fails without mutation.
- [ ] **Step 7: Commit** `feat(f21): finalize immutable lease liquidation`.

### Task 5: Staff and tenant routes/UI with ownership and permission boundaries

**Files:**
- Modify: `index.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Leases/routes.php`
- Create: `resources/views/lease_termination.php`
- Modify: `resources/views/contract_detail.php`
- Modify: `resources/views/tenant_lease_detail.php`
- Create: `tests/Integration/Leases/TerminationRoutesTest.php`
- Create: `tests/Integration/Leases/TerminationSecurityRegressionTest.php`

**Interfaces / Routes:**
- `GET /admin/leases/{id}/termination` — `contracts.view`; financial detail only with `finance.view`.
- `POST /admin/leases/{id}/bonus` — `contracts.manage`; feature flag required.
- `POST /admin/leases/{id}/bonus/decision` — `contracts.manage`.
- Existing `POST /admin/leases/{id}/end` remains the only lease-ending route.
- `POST /admin/leases/{id}/liquidation` — non-owner requires `contracts.manage AND finance.view`.
- `GET /tenant/leases/{id}/liquidation` — own lease only, final liquidation only, safe projection.

- [ ] **Step 1: Write RED route/security tests** for auth, password-change gate, permissions, CSRF, financial detail redaction and tenant 404-before-child-lookup.
- [ ] **Step 2: Add RED feature-flag test** proving `ALQUILAPRO_GOOD_CONDUCT_BONUS_ENABLED` defaults to false and only explicit truthy configuration enables mutations.
- [ ] **Step 3: Wire `TerminationService` once in `bootstrap/app.php`** and pass it into existing Leases routes; do not add a second route tree.
- [ ] **Step 4: Add the single SSR view** and minimal links from contract/tenant lease detail; the tenant route must create a safe projection with no actor ids, audit metadata or cross-lease documents.
- [ ] **Step 5: Run GREEN** for route/security tests and affected Lease/Finance/Documents tests.
- [ ] **Step 6: Commit** `feat(f21): expose controlled lease termination surfaces`.

### Task 6: F21 convergence, regression and checkpoint

**Files:**
- Create: `docs/checkpoints/F21_CHECKPOINT_2026-10-14.md`
- Modify only if factual commands/results require it: `README.md`

- [ ] **Step 1: Run focal F21 suite**: `vendor/bin/phpunit tests/Integration/Leases/TerminationSchemaTest.php tests/Integration/Leases/TerminationBonusTest.php tests/Integration/Leases/TerminationPreviewTest.php tests/Integration/Leases/TerminationFinalizationTest.php tests/Integration/Leases/TerminationConcurrencyTest.php tests/Integration/Leases/TerminationRoutesTest.php tests/Integration/Leases/TerminationSecurityRegressionTest.php`.
- [ ] **Step 2: Run affected integrated suites**: Leases, Finance, Documents, Insurance, Community, Memberships/F20.
- [ ] **Step 3: Run full suite once** with `composer test`.
- [ ] **Step 4: Run syntax/Composer gates**: `composer validate --strict` and PHP lint over changed PHP files.
- [ ] **Step 5: Fresh-review the complete F21 diff** for KISS, zero-noise, antipatch, tenant IDOR and duplicate authority; repair only proven issues, max one repair pass before re-review.
- [ ] **Step 6: Write checkpoint** with base SHA, product SHA, closure SHA, exact test counts, known minors and `MERGE_AUTHORIZED: NO`.
- [ ] **Step 7: Commit** `docs(f21): close implementation checkpoint`.

## Self-Review Result

- Spec coverage: PASS — schema, bonus, preview, blockers, immutable finalization, routes/security, concurrency and gates all map to tasks.
- Step scan: PASS — no implementation body transcript; each step has one checkable outcome.
- Type consistency: PASS — one `TerminationService`; `LeaseService::find()` is the only new lease read interface; F20 is consumed through existing `listForLease()` with no new predicate.
- Review Focus: PASS — all five high-risk conditions have owning tests.
- Proportion: PASS — plan stays implementation-oriented and smaller than a code transcript.

**Execution status:** `PLAN UPDATED / F20 PRODUCT CONTRACT REVALIDATED / F15→F20 INTEGRATION STILL REQUIRED / IMPLEMENTATION NOT AUTHORIZED / MERGE NOT AUTHORIZED`.
