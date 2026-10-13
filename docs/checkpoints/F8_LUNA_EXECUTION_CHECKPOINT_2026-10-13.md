# F8 Luna Execution Checkpoint — 2026-10-13

## Recovery and Task 1–7 Audit

PHASE: 1 — TASKS 1–7 AUDIT / REPAIR LOOP
PHASE_STATUS: REPAIR_REQUIRED — no hard blocker
BRANCH: impl/f8-tenant-lease-portal-20261013
RECOVERY_BASE: f4a7e3f4d5ec7dc9cdc0f3a7e78777f98c85461d (origin/main)
START_HEAD: 416218fc43ae363902d99eb82cf793a932d9f74b
CURRENT_HEAD: bb4528b2bb6a2f5b1de4a59f6d4ab2538e7772ff (before this checkpoint update)
PRODUCT_HEAD: 827daabded3539b3488ade77e5a99b9b50105cea
LAST_GOOD_LOCAL_SUITE: Focused F8/Auth/Properties/Shell — 56 tests, 579 assertions, 0 failures, 0 errors (PHP 8.5.8).
LAST_GOOD_CI: 38431546341 at PRODUCT_HEAD; PHP 8.3/8.4/8.5 completed successfully.
TEST_COUNTS: Product CI PHP 8.3 — 309 tests, 2599 assertions, 0 failures, 0 errors. Focused local — 56 tests, 579 assertions, 0 failures, 0 errors.
ASSERTION_COUNTS: Product CI PHP 8.3 — 2599; focused local — 579.
FILES_CHANGED_THIS_PHASE: docs/checkpoints/F8_LUNA_EXECUTION_CHECKPOINT_2026-10-13.md
ROOT_CAUSE_FINDINGS: PropertyService::updateUnit() reads the unit and active-lease relation, then updates without a transaction or row lock. Concurrent activation locks the unit, so an editor can check before activation commits, wait on its UPDATE, then overwrite occupied after activation commits. Existing tests cover sequential behavior only. RED regression and repair are pending. Stale plan approval footer is contradicted by SPEC commit 044cb9b and the current explicit user request; update it during closure. Ruling: proceed under current user authorization; cost if wrong is carrying out a plan whose old footer was not updated.
NOISE_FOUND: Stale plan approval footer. Potential unit-status time-of-check/time-of-use race; validate with regression before repair.
NOISE_REMOVED_OR_CONVERGED: None yet.
KISS_STATUS: Pending Two Laws gate.
ZERO_NOISE_STATUS: Pending Two Laws gate.
BLOCKERS: NONE — identified issue is repairable within the existing PropertyService authority.
NEXT_EXACT_ACTION: Finish the baseline full-suite run; add and observe a RED guard proving updateUnit serializes on the canonical unit row before the active-lease check; repair that owning authority, then rerun focused and full suites.
MERGE_STATUS: NOT AUTHORIZED / NOT ATTEMPTED.

## Phase 0 Evidence

- Recovery checkout began at Task 7 checkpoint HEAD `416218fc43ae363902d99eb82cf793a932d9f74b`; branch was clean and matches the expected implementation branch.
- `origin/main` is `f4a7e3f4d5ec7dc9cdc0f3a7e78777f98c85461d`; PRODUCT HEAD is `827daabded3539b3488ade77e5a99b9b50105cea`.
- Tasks 1–7 implementation and RED/GREEN checkpoints are present. Task 8 was pending.
- Product CI `38431546341` is tied to PRODUCT HEAD exactly; PHP 8.3/8.4/8.5 jobs succeeded. PHP 8.3 log confirms 309 tests and 2599 assertions.
- `composer validate --strict` passes. The initial local test attempt failed only because no MariaDB listener was running; an isolated MariaDB 11.4 container now supplies the expected test database.
- The plan's final approval footer is stale, but its historical execution preflight was authorized by commit `044cb9b`; the active user request also explicitly says to execute.

CHECKPOINT_RESULT: PASS

PHASE: 1 — TASKS 1–7 AUDIT / REPAIR LOOP
CURRENT_HEAD: bb4528b2bb6a2f5b1de4a59f6d4ab2538e7772ff
PRODUCT_HEAD: 827daabded3539b3488ade77e5a99b9b50105cea
CI: 38431546341 — exact PRODUCT HEAD; PHP 8.3/8.4/8.5 PASS
TESTS: focused 56 PASS; full suite in progress (interim output: 126/309, one failure observed; pending diagnosis)
ASSERTIONS: focused 579
KISS: PENDING
ZERO_NOISE: PENDING
PATCH_STACKING: no stacked repair identified; unit status race requires a root-level serialization fix
CHANGES_THIS_PHASE: Rolling audit checkpoint updated.
REMOVED_OR_CONVERGED_NOISE: None yet.
BLOCKERS: NONE
NEXT_EXACT_ACTION: Complete baseline full suite and repair the unit status serialization gap using RED → GREEN.
MERGE: NOT AUTHORIZED
