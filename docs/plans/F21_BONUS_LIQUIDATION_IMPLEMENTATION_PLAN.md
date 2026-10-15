# F21 — Bonus + Liquidation Implementation Plan

> **Execution authority:** follow this plan task-by-task under TDD. `docs/checkpoints/F21_LIVE_HANDOFF_2026-10-14.md` is the live recovery snapshot; the frozen product contract remains `docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md`.

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
- F15→F20 are already converged, audited and integrated into canonical `main`; this prerequisite is CLOSED.

## Live Execution Status

- Canonical base: `main@748c9d3d70ed33bb216daab8322bf6a75573ff85`.
- Active branch: `impl/f21-bonus-liquidation-20261014`.
- PR: #26 — OPEN / NOT MERGED.
- Task 1: **GREEN / CLOSED** — CI #922 green on PHP 8.3/8.4/8.5.
- Task 2: **GREEN / CLOSED** — product commit `1dd9784bf03e2188ff003c07cf96656bcab20bdc`; closure test head `4e92974df2c590f5c48400f4dcf36ba88d1d4842`; CI #929 green on PHP 8.3/8.4/8.5; PHP 8.3 full suite `552 tests / 5806 assertions / 0 failures`.
- Task 3: **NOT STARTED**. PRE-KISS proved an Insurance read-authority gap; start with Task 3a owner RED before writing preview code.
- Tasks 4–6: **NOT STARTED**.
- F21 merge: **NOT READY** until Tasks 3–6 and final convergence are closed.

## Pre-Execution Gate — certified F20 remote contract

- [x] Final F20 product contract re-read from the remote PR: Product Head `5f46c63f82672e6d34566284051a5f62e1db56db`; docs closure `5d2488286ce8370402544eaf4276a34a8d83a40d`; later test-only convergence did not change product behavior.
- [x] Final `app/Memberships/MembershipService.php`, migration 020, routes and concurrency/security tests reviewed.
- [x] Canonical read projection exists: `MembershipService::listForLease(int $leaseId): array`.
- [x] Pending paid charge decision is exposed by each normalized membership as `charge_decision_required`; F21 derives the blocker by filtering `listForLease()` and **must not add** `hasPendingPaidChargeDecision()` or an equivalent duplicate predicate.
- [x] Acceptance and renewal lock an active tenant lease and reject an ended lease (`ended_at IS NULL`).
- [x] Integrated F20 contract re-read after sequential F15→F20 merges; method names/fields required by F21 remained valid.

## Review Focus

1. Active lease passed to finalization must fail before any liquidation row is inserted — Task 4.
2. Pending payment reconciliation must block finalization while an ordinary outstanding balance must not — Tasks 3/4.
3. Paid F20 membership with unresolved CREATE/SKIP must block finalization — derive from `listForLease()` + `charge_decision_required`; Tasks 3/4.
4. Initial inventory without final inventory must block finalization — Tasks 3/4.
5. Tenant requesting another tenant's liquidation must receive 404 and no child lookup before ownership — Task 5.

---

### Task 1: Schema and canonical lease read — CLOSED

**Files actually used:**
- Create: `database/migrations/021_create_lease_termination.sql`
- Modify: `app/Leases/LeaseService.php`
- Create: `tests/Integration/Leases/TerminationSchemaTest.php`
- Create: `tests/Integration/Leases/LeaseCanonicalReadTest.php`
- Modify: `tests/Integration/Foundation/MigrationRunnerTest.php`

**Interfaces:**
- Consumes: existing `leases`, `users`, migration runner.
- Produces: `LeaseService::find(int $leaseId): ?array`; tables `good_conduct_bonuses`, `lease_liquidations`.

- [x] **Step 1: Write RED schema test** for exactly the two approved F21 tables and no parallel status/item/history/payment/ledger persistence.
- [x] **Step 2: Verify RED** because migration 021 and canonical `LeaseService::find()` were absent.
- [x] **Step 3: Add migration 021** with the exact SPEC fields; no functional liquidation update/delete state.
- [x] **Step 4: Write RED for `LeaseService::find(int $leaseId): ?array`** using the canonical projection contract.
- [x] **Step 5: Implement minimal `find()`** by reusing `leaseSelect()` + `normalizeLease()`; no duplicate projection SQL.
- [x] **Step 6: Run GREEN**. CI #922 passed PHP 8.3/8.4/8.5; PHP 8.3: 545 tests / 5753 assertions.
- [x] **Step 7: Close Task 1** at product head `71a6a0e6e3baf9a6bfdfbddf1311de05bcf74158` before Task 2 RED.

### Task 2: Bonus terminal workflow — CLOSED

**Files actually used:**
- Create: `app/Leases/TerminationService.php`
- Create: `tests/Integration/Leases/TerminationBonusTest.php`
- Create: `tests/Support/TerminationBonusConcurrencyWorker.php`
- Modify test-only convergence: `tests/Integration/Leases/LeaseSecurityRegressionTest.php`

**Interfaces:**
- Constructor: `__construct(PDO $pdo, LeaseService $leases, FinanceService $finance, ObligationService $obligations, InsuranceService $insurance, DocumentService $documents, CommunityService $community, MembershipService $memberships, bool $bonusEnabled)`.
- Produces:
  - `bonusForLease(int $leaseId): ?array`
  - `configureBonus(int $leaseId, string $amount, ?string $note, int $actorUserId, int $now): array`
  - `decideBonus(int $leaseId, string $decision, int $actorUserId, int $now): array`

- [x] **Step 1: Write RED tests** for feature gate, amount `>0`, note `<=1000`, one row per lease, pending edit, terminal `approved|denied`, decision-vs-decision concurrency and zero `charges`/`payments` writes.
- [x] **Step 2: Verify valid RED**. CI #924 reached the intended failure because `TerminationService` did not exist. The earlier HY093 fixture defect was corrected test-only before accepting the RED.
- [x] **Step 3: Implement only bonus methods** in `TerminationService`; lock order is lease → bonus; stable terminal behavior; no Finance writer calls. Product commit: `1dd9784bf03e2188ff003c07cf96656bcab20bdc`.
- [x] **Step 4: Converge stale structural guard** without changing product. The F8 test now admits exactly the approved F21 `TerminationService` when canonical migration 021 exists and includes it in anti-parallel auth/session checks.
- [x] **Step 5: Cover mandatory update-vs-decision race** from the frozen SPEC. `configureBonus()` and `decideBonus()` serialize; terminal decision always persists; any later configure fails; amount reflects only an update that legitimately won before the decision; Finance remains untouched.
- [x] **Step 6: Run closure GREEN**. CI #929 passed PHP 8.3/8.4/8.5. PHP 8.3: **552 tests / 5806 assertions / 0 failures**.
- [x] **Step 7: Close Task 2**. No routes, permissions, runtime dependencies, provider integration, extra tables or additional domain classes were added.

### Task 3: Derived liquidation preview and blockers — NEXT

**Files:**
- Owner repair first, only if RED proves the already identified gap:
  - Modify: `app/Insurance/InsuranceService.php`
  - Modify: existing Insurance integration test owner (prefer `tests/Integration/Insurance/InsurancePolicyServiceTest.php`; do not create a parallel Insurance test architecture unless necessary).
- Then:
  - Modify: `app/Leases/TerminationService.php`
  - Create: `tests/Integration/Leases/TerminationPreviewTest.php`

**Interfaces:**
- Produces: `preview(int $leaseId, string $today, array $handover = []): array`.
- `handover` accepts only `property_returned` boolean, `keys_returned_count` integer `0..100`, and optional trimmed `notes <= 2000`.
- Preview returns canonical sections: `lease`, `contract`, `handover`, `finance`, `obligations`, `claims`, `inventories`, `current_documents`, `community`, `memberships`, `bonus`, `blockers`.
- F20 membership blocker source is fixed: `$memberships->listForLease($leaseId)` and any row with `charge_decision_required === true` blocks finalization.

- [ ] **Step 0 / Task 3a: Repair the Insurance read authority first under TDD.** RED must prove that F21 needs a lease-scoped policy projection with policy IDs sufficient to call existing `listClaims(policyId)`. Current `listPolicies()` is global and `tenantPolicySummary()` intentionally removes IDs/claims. Add the smallest read-only lease-scoped method in `InsuranceService`; no SQL duplication in `TerminationService`, no new table/class/permission/route.
- [ ] **Step 1: Write RED preview tests** proving preview persists nothing and derives data from existing authorities.
- [ ] **Step 2: Add RED blockers** for pending payment report, pending paid membership charge decision, pending bonus, and initial inventory without final inventory.
- [ ] **Step 3: Add RED non-blockers** proving outstanding balance, open community incident and open insurance claim are included but do not alone block finalization.
- [ ] **Step 4: Run RED** with `vendor/bin/phpunit tests/Integration/Leases/TerminationPreviewTest.php` after the Insurance owner API is GREEN.
- [ ] **Step 5: Implement minimal preview** using existing public service APIs only. Do not modify F20 or add convenience predicates.
- [ ] **Step 6: Ensure deterministic ordering** by stable id/date ordering before snapshot assembly; do not add a canonicalization framework.
- [ ] **Step 7: Run GREEN** for preview plus focal Finance/Documents/Insurance/Community/Memberships tests.
- [ ] **Step 8: Commit** the minimal preview product delta.

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
- [ ] **Step 7: Commit** minimal immutable finalization product delta.

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
- [ ] **Step 4: Add the single SSR view** and minimal links from contract/tenant lease detail; tenant route creates a safe projection with no actor ids, audit metadata or cross-lease documents.
- [ ] **Step 5: Run GREEN** for route/security tests and affected Lease/Finance/Documents tests.
- [ ] **Step 6: Commit** minimal controlled termination surfaces.

### Task 6: F21 convergence, regression and checkpoint

**Files:**
- Create final closure checkpoint: `docs/checkpoints/F21_CHECKPOINT_2026-10-14.md`
- Modify only if factual commands/results require it: `README.md`

- [ ] **Step 1: Run focal F21 suite**: schema, bonus, preview, finalization, concurrency, routes and security regression.
- [ ] **Step 2: Run affected integrated suites**: Leases, Finance, Documents, Insurance, Community, Memberships/F20.
- [ ] **Step 3: Run full suite once** with `composer test`.
- [ ] **Step 4: Run syntax/Composer gates**: `composer validate --strict` and PHP lint over changed PHP files.
- [ ] **Step 5: Fresh-review complete F21 diff** for KISS, zero-noise, antipatch, tenant IDOR and duplicate authority; repair only proven issues, max one repair pass before re-review.
- [ ] **Step 6: Write final checkpoint** with base SHA, product SHA, closure SHA, exact test counts, known minors and merge state.
- [ ] **Step 7: Commit** final F21 documentation closure.

## Self-Review Result

- Spec coverage: PASS — schema, bonus, preview, blockers, immutable finalization, routes/security, concurrency and gates all map to tasks.
- Type consistency: PASS — one `TerminationService`; `LeaseService::find()` is the only new lease read interface; F20 is consumed through existing `listForLease()` with no duplicate predicate.
- Authority convergence: PASS through Task 2 — no second Finance, Lease, Document or Membership authority introduced.
- Task 3 PRE-KISS: PASS with one explicit owner repair required in `InsuranceService` before preview implementation.

**Execution status:** `TASK 1 CLOSED / TASK 2 CLOSED / TASK 3 NEXT AT INSURANCE OWNER RED / TASKS 4–6 NOT STARTED / PR #26 OPEN / MERGE NOT READY`.
