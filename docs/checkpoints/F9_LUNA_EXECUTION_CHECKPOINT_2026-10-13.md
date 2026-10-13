# F9 Luna Execution Checkpoint — 2026-10-13

```text
TASK: 2 — MANUAL CHARGES + CANONICAL READ PROJECTION
TASK_STATUS: COMPLETE
BRANCH: impl/f9-charges-portfolio-20261013
F8_INTEGRATED_BASE_SHA: 89811cbba5ae922bab00b50dd02af08dbaa1cb8b
START_HEAD: 96e2f34d61aea41e518c5e3abdfdb606dd106dbd
CURRENT_HEAD: bcadd77230d30ba12bb1bd27f4b8ee200b3d7ff1 (Task 2 product head; this checkpoint metadata commit follows)
LAST_PRODUCT_COMMIT: bcadd77230d30ba12bb1bd27f4b8ee200b3d7ff1 — feat(f9): add manual charge authority
LAST_GOOD_FOCUSED: FinanceServiceTest — 8 tests / 62 assertions
LAST_GOOD_FULL: F8 baseline — 310 tests / 2611 assertions / 0 failures / 0 errors (PHP 8.3)
LAST_GOOD_CI: 38443826390 — F8 final audit CI, PHP 8.3/8.4/8.5 PASS
RED_EVIDENCE: Task 1 — focused schema test failed only because `charges` was absent. Task 2 — FinanceServiceTest produced 8 expected errors, all `Class "AlquilaPro\Finance\FinanceService" not found`.
GREEN_EVIDENCE: `vendor/bin/phpunit --testdox tests/Integration/Finance/FinanceServiceTest.php tests/Integration/Leases/LeaseLifecycleTest.php tests/Integration/Leases/LeaseActivationTest.php` — PASS, 20 tests / 199 assertions.
TESTS: Task 1 schema + F7/F8 schema regression PASS (9/87); Task 2 manual/read projection + F8 lifecycle/activation PASS (20/199).
ASSERTIONS: Last focused FinanceServiceTest: 62; last related regression run: 199.
FILES_CHANGED: docs/specs/F9_CHARGES_PORTFOLIO_SPEC.md; database/migrations/009_create_charges.sql; app/Finance/FinanceService.php; tests/Integration/Finance/ChargeSchemaTest.php; tests/Integration/Finance/FinanceServiceTest.php; docs/checkpoints/F9_LUNA_EXECUTION_CHECKPOINT_2026-10-13.md
ROOT_CAUSE_FINDINGS: Task 1 RED confirmed the expected missing `charges` table. Task 2 RED confirmed the expected missing `FinanceService`; existing F7/F8 authorities and lifecycle regressions remained green.
NOISE_FOUND: No extra schema, route, permission, persistence, or service authority introduced in Tasks 1–2.
NOISE_CONVERGED: Task 0 updated only the SPEC state; Task 1 added exactly one table; Task 2 added exactly one `FinanceService` with manual writes and one canonical lease projection.
PATCHES_AVOIDED: No float-based money, no manual-write transaction/lease lock, no second balance/status authority, no additional class/table/dependency, and no F7/F8 authority mutations.
KISS_STATUS: PASS — exactly one F9 table and one F9 domain class.
ZERO_NOISE_STATUS: PASS
PATCH_STACKING: NONE
BLOCKERS: NONE
NEXT_EXACT_ACTION: Task 3 RED — cover monthly rent/administration snapshots, contract-period bounds, duplicates, partial correction, and concurrent lease serialization before extending FinanceService.
MERGE_STATUS: NOT MERGED; MERGE_AUTHORIZED=NO
```

## Task 0 evidence

- Verified the current branch is `impl/f9-charges-portfolio-20261013` at the expected start HEAD `96e2f34d61aea41e518c5e3abdfdb606dd106dbd`.
- Verified `origin/main` is the integrated F8 base `89811cbba5ae922bab00b50dd02af08dbaa1cb8b`.
- Verified F8 PR #11 merged at `a1b50f0d1c6556531626fd35cba0668bd5c4029f`; F8 final audit CI 38443826390 passed PHP 8.3/8.4/8.5.
- The SPEC gate now reads `F9_SPEC: APPROVED`, `F9_PLAN: APPROVED`, `F9_IMPLEMENTATION: IN PROGRESS`, records the F8 base, and retains `MERGE_AUTHORIZED: NO`.

## Task 1 evidence

- RED: before migration 009, the focused schema test failed only because `charges` was absent (0 rows in information_schema; expected 1).
- GREEN: the focused schema test and the existing F8 lease / F7 contract schema tests passed: 9 tests, 87 assertions, 0 failures, 0 errors.
- Product commit: `8fa177399bac3db066cc6ae0dcf5bd3f85f44871` (`feat(f9): add minimal charge schema`).
- Exact schema delta: one migration and one table, `charges`; amount is `DECIMAL(12,2)`, both dates are `DATE`, and only the four approved secondary indexes exist.

## Task 2 evidence

- RED: before `FinanceService` existed, `FinanceServiceTest` reported 8 expected class-not-found errors.
- GREEN: FinanceServiceTest and the existing F8 lease lifecycle/activation regressions passed: 20 tests, 199 assertions, 0 failures, 0 errors.
- Product commit: `bcadd77230d30ba12bb1bd27f4b8ee200b3d7ff1` (`feat(f9): add manual charge authority`).
- Manual types are exactly `service|fine|repair|other`; amounts remain normalized decimal strings; dates and first-of-month periods are strict; ended leases accept historical manual charges.
- `listForLease()` scopes by the given lease, derives status/days overdue from its `today` argument, and omits actor IDs and void reason from its output.
