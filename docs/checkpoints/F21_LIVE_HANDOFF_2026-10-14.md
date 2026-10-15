# AlquilaPro — F21 Live Handoff / Recovery Authority

**Updated:** 2026-10-14  
**Repository:** `sierraglobalcompany-rgb/AlquilaPro`  
**Canonical main base for F21:** `748c9d3d70ed33bb216daab8322bf6a75573ff85`  
**Active branch:** `impl/f21-bonus-liquidation-20261014`  
**PR:** #26 — `F21: bonus and immutable lease liquidation`  
**Merge state:** OPEN / NOT MERGED

> This file is the first repository-local recovery authority for the active F21 execution. If a chat/session is lost, read this file, then PR #26, then the PLAN and frozen SPEC. Historical interruption checkpoints remain evidence but do not override this live state.

## 1. Non-negotiable laws

### KISS / simple architecture
Simple, efficient, optimized, effective, auditable, maintainable. Prefer A→B. No unnecessary tables, classes, layers, dependencies, sources of truth or parallel route trees.

### Zero Noise / convergence
Order: **ELIMINATE → MERGE/CONVERGE → SIMPLIFY → IMPROVE/OPTIMIZE → ADD**. Before deletion: refs → authority → replacement → test → delete → regression/CI.

### Anti-patch
**REPRODUCE → ROOT CAUSE → OWNING AUTHORITY → SEARCH DUPLICATION → RED → MINIMAL GREEN → REMOVE SUPERSEDED LOGIC → REGRESSION.** Ask: where should this rule live if designed correctly from scratch?

### Delivery / TDD
SPEC/DESIGN → PLAN → RED → verify RED → minimal GREEN → verify GREEN → convergence → focused QA → fresh review → checkpoint → PR CI. Do not accept a RED caused by a broken fixture. Do not call a task GREEN from a focal subset when the full integration gate is red.

## 2. Canonical project state before F21

F0→F20 are integrated in canonical `main`. F15→F20 were sequentially retargeted, recertified and merged. Documentation/planning convergence PRs #24 and #25 were also merged before F21 began.

F21 was branched from:

`main@748c9d3d70ed33bb216daab8322bf6a75573ff85`

Do not re-open or re-stack F15→F20. F21 consumes their canonical APIs.

## 3. F21 frozen product budget

From `docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md` and PRE-KISS rulings:

- max 2 new tables: `good_conduct_bonuses`, `lease_liquidations`;
- exactly 1 new domain class: `AlquilaPro\Leases\TerminationService`;
- 0 new permissions;
- 0 new runtime dependencies;
- 0 new public route tree;
- `LeaseService::end()` remains the only lease lifecycle writer;
- `FinanceService` remains sole charge/payment writer;
- `DocumentService` remains sole document/inventory authority;
- no payout/provider/wallet/deposit/escrow/accounting journal/credit-note architecture;
- no draft liquidation/item/history/status-history tables;
- membership blockers must use existing `MembershipService::listForLease()` + `charge_decision_required`; do not add a second F20 predicate/API;
- canonical feature flag runtime name is `ALQUILAPRO_GOOD_CONDUCT_BONUS_ENABLED`, default false.

## 4. Task 1 — CLOSED / GREEN

Purpose: schema 021 + canonical `LeaseService::find()`.

Actual files:
- `database/migrations/021_create_lease_termination.sql`
- `app/Leases/LeaseService.php`
- `tests/Integration/Leases/TerminationSchemaTest.php`
- `tests/Integration/Leases/LeaseCanonicalReadTest.php`
- `tests/Integration/Foundation/MigrationRunnerTest.php`

Important correction vs original plan: there is no canonical `LeaseServiceTest.php` owner for this work; `LeaseCanonicalReadTest.php` is the dedicated read test and `MigrationRunnerTest.php` owns migration enumeration/cleanup.

Task 1 product head before Task 2:  
`71a6a0e6e3baf9a6bfdfbddf1311de05bcf74158`

CI #922: **GREEN PHP 8.3 / 8.4 / 8.5**.  
PHP 8.3 full suite: **545 tests / 5753 assertions**.

Result:
- migration 021 creates only the two approved F21 tables;
- `LeaseService::find(int $leaseId): ?array` reuses the canonical `leaseSelect()` / normalized projection;
- no duplicate lease SQL projection architecture.

## 5. Task 2 — CLOSED / GREEN

Purpose: optional good-conduct bonus with a single pending row and terminal `approved|denied` decision, feature-gated and financially non-writing.

### TDD history

1. Initial RED commit `0ef9cb14...` was **invalid** because the test fixture reused a native PDO placeholder and produced `HY093`. It was not accepted as product evidence.
2. Test-only fixture repair:  
   `3108316726691ff23177e2356112ff521a1becbb`
3. CI #924 produced the **valid RED**: `TerminationService` did not exist.
4. Minimal product GREEN commit:  
   `1dd9784bf03e2188ff003c07cf96656bcab20bdc`
5. CI #925 proved all original bonus behavior green but exposed a stale F8 structural test that froze `app/Leases/*Service.php` to only `LeaseService.php`.
6. Test-only structural convergence: `f3f2704e...`.
7. CI #927 showed that convergence referenced a non-canonical migration filename, so F21 detection remained false. Root cause was test-only naming, not product.
8. Canonical migration-name repair:  
   `c61c190be0861a760088c9d514a32fd077902212`
9. Frozen SPEC re-audit found a missing mandatory race: **bonus update vs decision**.
10. Test-only closure coverage:  
    `4e92974df2c590f5c48400f4dcf36ba88d1d4842`
    - adds update-vs-decision test;
    - generalizes only the existing concurrency worker;
    - product code unchanged.
11. CI #929: **GREEN PHP 8.3 / 8.4 / 8.5**.
    - PHP 8.3 full suite: **552 tests / 5806 assertions / 0 failures**.

### Verified Task 2 behavior

- disabled feature fails closed;
- amount is validated/normalized and must be positive;
- note bounded to 1000;
- exactly one bonus row per lease;
- pending row can be edited;
- only `approved|denied` terminal decisions;
- terminal state immutable;
- no writes to `charges` or `payments`;
- decision vs decision has one terminal winner;
- update vs decision serializes correctly;
- if update legitimately wins first, its amount may persist before terminal decision;
- if decision wins first, update fails;
- after terminal decision, no later bonus mutation succeeds.

### Task 2 product architecture

`TerminationService` is the only new F21 domain class. Bonus mutations lock in the order **lease → bonus**. No routes, permissions, runtime dependencies, providers, financial writers or extra persistence were introduced for Task 2.

## 6. Current branch / PR state

At the Task 2 closure point, the last test head is:

`4e92974df2c590f5c48400f4dcf36ba88d1d4842`

PR #26 is OPEN, NOT MERGED and targets `main@748c9d3d...`.

The next commit after that head is expected to be documentation-only: this live handoff + PLAN convergence. If recovering after that commit, use the branch head from PR #26 as the current SHA and verify its CI status rather than assuming the SHA above is still HEAD.

## 7. Task 3 — NEXT, PRODUCT NOT STARTED

Task 3 is liquidation preview + blockers. **Do not begin by adding SQL to `TerminationService`.** PRE-KISS already proved one real owning-authority gap in Insurance.

### Task 3a — required Insurance owner RED first

Current Insurance APIs:
- `findPolicy(id, today)` — individual policy;
- `listPolicies(today)` — global list, too broad for F21 composition;
- `tenantPolicySummary(leaseId, today)` — lease scoped but intentionally masks/omits policy IDs and claims;
- `listClaims(policyId)` — claim list by policy.

F21 preview needs lease-scoped policies with IDs so it can compose existing claims without global filtering or duplicate Insurance SQL.

Correct next action:
1. add RED in the existing Insurance test owner proving a minimal lease-scoped read projection is needed;
2. implement the smallest **read-only lease-scoped method in `InsuranceService`**;
3. no new table/class/permission/route/dependency;
4. GREEN the affected Insurance suite;
5. only then write `TerminationPreviewTest` RED and `TerminationService::preview()`.

Do **not** expose tenant-safe masked data for this internal composition need; this is a staff/domain composition read, not a tenant route projection.

### Task 3 preview contract

`preview(int $leaseId, string $today, array $handover = []): array`

Handover allowlist:
- `property_returned`: boolean;
- `keys_returned_count`: integer 0..100;
- optional `notes`: trimmed <=2000.

Canonical preview sections:
- lease
- contract
- handover
- finance
- obligations
- claims
- inventories
- current_documents
- community
- memberships
- bonus
- blockers

Mandatory blockers:
- active lease at finalization stage;
- pending payment reconciliation/rejection state as defined by canonical payment authority;
- paid F20 membership with `charge_decision_required === true`;
- pending good-conduct bonus;
- initial inventory exists but final inventory missing;
- already finalized liquidation.

Not blockers by themselves:
- ordinary outstanding balance;
- open community incident;
- open insurance claim;
- maintenance history.

Preview persists nothing and must use existing domain authorities.

## 8. Tasks 4–6 — not started

### Task 4
Immutable final liquidation. Must lock canonical lease, require ended lease, recompute blockers in same connection, insert exactly one deterministic snapshot, and have finalize-vs-finalize concurrency test. No update/delete liquidation API.

### Task 5
Staff/tenant routes and one SSR view. Reuse existing auth/CSRF/password gate/permissions. Tenant ownership must be resolved with `LeaseService::findForUser()` **before** liquidation/child lookups. Existing lease-ending route remains the only lease lifecycle writer.

### Task 6
F21 convergence: focal suite, affected domain suites, one full suite, Composer/lint gates, fresh KISS/Zero-Noise/Anti-patch review, final checkpoint, PR CI. Only then is merge eligibility evaluated.

## 9. Branch noise / what NOT to use

The following branches were accidental/auxiliary and are **not authoritative bases**:
- `scratch/f21-task2-red-build`
- `scratch/f21-task2-red-build2`
- `noop`
- `noop-do-not-use`
- `noop-do-not-use-2`
- `checkpoint/f21-task1-green-20261014-doc`

Historical but valid checkpoint branch:
- `checkpoint/f21-task1-green-20261014`

The active execution authority is:
- `impl/f21-bonus-liquidation-20261014`
- PR #26

Current connector did not expose safe branch-ref deletion, so cleanup debt is documented rather than faked. Do not merge or branch from the noise refs.

## 10. Historical documents and precedence

Recovery order:
1. **this live handoff**;
2. PR #26 current body/head/CI;
3. `docs/checkpoints/F21_PRE_KISS_EXECUTION_GATE_2026-10-14.md`;
4. `docs/plans/F21_BONUS_LIQUIDATION_IMPLEMENTATION_PLAN.md`;
5. frozen `docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md`;
6. older interruption checkpoints only as historical evidence.

`docs/checkpoints/F21_TASK2_BONUS_INTERRUPT_CHECKPOINT_2026-10-14.md` is **superseded** for current status. It remains useful evidence of the exact stop at CI #925 but must not be treated as the current gate.

The frozen F21/F22 SPEC contains historical drafting language about implementation authorization/integration. Its product rules remain authoritative; its old execution-status prose is superseded by this live handoff, current PLAN and PR #26.

## 11. Future roadmap after F21

F22 is already specified/planned but must not start until F21 is closed/integrated according to the project sequence. F23–F26 are also planned/frozen. No F27 is planned. F25 packaging waits for the appropriate canonical post-feature main source; F26 deploys the exact F25 artifact.

## 12. Exact resume instruction for a new chat

If this chat/session ends, the next assistant should:

1. open PR #26 and verify current head/base/merge state;
2. read this file;
3. verify the latest CI for the current PR head rather than trusting historical CI numbers;
4. confirm Task 1 and Task 2 remain unchanged and green;
5. start **Task 3a Insurance owner RED**, not `TerminationService::preview()` directly;
6. preserve KISS / Zero Noise / Anti-patch / TDD;
7. do not merge PR #26 until Tasks 3–6 and final certification are complete.

**Current execution marker:** `F21 TASK 1 CLOSED / TASK 2 CLOSED / TASK 3 NEXT AT INSURANCE OWNER RED / PR #26 OPEN / NOT MERGED`.
