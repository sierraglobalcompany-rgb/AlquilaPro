# F9 Luna Execution Checkpoint — 2026-10-13

```text
TASK: 7 — OWNED CHARGE PORTFOLIO IN EXISTING TENANT DETAIL
TASK_STATUS: COMPLETE
BRANCH: impl/f9-charges-portfolio-20261013
F8_INTEGRATED_BASE_SHA: 89811cbba5ae922bab00b50dd02af08dbaa1cb8b
START_HEAD: 96e2f34d61aea41e518c5e3abdfdb606dd106dbd
CURRENT_HEAD: 35287335be0b2d8bc918157730862365869b713e (Task 7 product head; this checkpoint metadata commit follows)
LAST_PRODUCT_COMMIT: 35287335be0b2d8bc918157730862365869b713e — feat(f9): show owned charges in tenant portal
LAST_GOOD_FOCUSED: TenantPortalRoutes + FinanceSecurityRegression + ShellRoutes + LeaseSecurityRegression — 21 tests / 267 assertions
LAST_GOOD_FULL: F8 baseline — 310 tests / 2611 assertions / 0 failures / 0 errors (PHP 8.3)
LAST_GOOD_CI: 38443826390 — F8 final audit CI, PHP 8.3/8.4/8.5 PASS
RED_EVIDENCE: Task 1 — focused schema test failed only because `charges` was absent. Task 2 — FinanceServiceTest produced 8 expected errors, all `Class "AlquilaPro\Finance\FinanceService" not found`. Task 3 — monthly FinanceService tests produced 6 expected missing-method errors before implementation. Task 4 — void, summary, and portfolio focused tests produced only expected missing-method errors before implementation. Task 5 — finance route tests reached NotFound before route registration; permission sanitizer rejected new permissions; authorized shell viewer had no finance launcher. Task 6 — existing contract detail lacked the finance summary/history and permission-gated controls. Task 7 — existing tenant detail rendered without the required `Cartera` section.
GREEN_EVIDENCE: Task 3 monthly-focused run — PASS, 6 tests / 26 assertions; Task 3 regression command — PASS, 35 tests / 382 assertions. Task 4 FinanceService suite — PASS, 18 tests / 121 assertions. Task 5 private route/permissions/shell/CSRF — PASS, 31 tests / 216 assertions. Task 6 FinanceRoutes + ContractAdminRoutes + ContractFinalizationRoutes + ContractSecurityRegression — PASS, 18 tests / 261 assertions. Task 7 TenantPortalRoutes + FinanceSecurityRegression + ShellRoutes + LeaseSecurityRegression — PASS, 21 tests / 267 assertions, 0 warnings.
TESTS: Task 1 schema + F7/F8 schema regression PASS (9/87); Task 2 manual/read projection + F8 lifecycle/activation PASS (20/199); Task 3 monthly generation + contract/lease regression PASS (35/382); Task 4 FinanceService PASS (18/121); Task 5 private route/permissions/shell/CSRF PASS (31/216); Task 6 existing admin contract context and F7 guardrail PASS (18/261); Task 7 tenant-owned portfolio, IDOR order, XSS/privacy, shell and F8 lease architecture PASS (21/267).
ASSERTIONS: Last Task 7 focused regression set: 267.
FILES_CHANGED: docs/specs/F9_CHARGES_PORTFOLIO_SPEC.md; database/migrations/009_create_charges.sql; app/Finance/FinanceService.php; tests/Integration/Finance/ChargeSchemaTest.php; tests/Integration/Finance/FinanceServiceTest.php; docs/checkpoints/F9_LUNA_EXECUTION_CHECKPOINT_2026-10-13.md
ROOT_CAUSE_FINDINGS: Task 1 RED confirmed the expected missing `charges` table. Task 2 RED confirmed the expected missing `FinanceService`. Task 3 RED confirmed the monthly method was absent; monthly generation now locks the lease before contract and active-charge reads and rolls back the full insertion unit on failure. Task 4 RED confirmed void and balance/portfolio projections were absent; those remain derived from canonical charge rows and voiding locks the charge before its one-time update. Task 5 RED confirmed the private route file, permissions and authorized launcher were absent. Task 6 RED confirmed finance had not been composed into the existing contract detail. Task 7 RED confirmed the existing tenant lease detail lacked its charge portfolio; the route now proves F8 ownership before FinanceService reads.
NOISE_FOUND: No extra schema, permission, persistence or service authority introduced in Tasks 1–7; the one finance route file registers only the four approved private routes, and tenant portfolio composes in the existing detail GET.
NOISE_CONVERGED: Task 0 updated only the SPEC state; Task 1 added exactly one table; Task 2 added exactly one `FinanceService`; Tasks 3–4 extended that same service; Task 5 creates it once in bootstrap, shares it with route composition, adds exactly two finance permissions and one private portfolio surface; Tasks 6–7 compose reads at existing contract and tenant route/view boundaries.
PATCHES_AVOIDED: No float-based money, no second balance/status authority, no additional class/table/dependency, no F7 core mutation, and no public finance route. Monthly generation and voiding are atomic; partial correction preserves active counterparts; summaries use one canonical aggregation expression and do not persist derived values. ContractService and ContractPdfRenderer remain finance-agnostic. Tenant view escapes descriptions and omits actor/reason data and mutation forms.
KISS_STATUS: PASS — exactly one F9 table and one F9 domain class.
ZERO_NOISE_STATUS: PASS
PATCH_STACKING: NONE
BLOCKERS: NONE
NEXT_EXACT_ACTION: Task 8 — freeze security/architecture guardrails; run focused F9/F8 QA, composer validation and full PHPUnit; perform Two Laws, patch-smell and product diff reviews; collect exact PRODUCT_HEAD CI; write closure report; collect fresh exact CLOSURE_HEAD CI; open review-ready PR; stop before merge.
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

## Task 3 evidence

- RED: the monthly-focused FinanceService tests failed with 6 expected missing-method errors before `createMonthlyCharges()` was implemented.
- GREEN: monthly-focused run passed 6 tests / 26 assertions; the Task 3 required regression command passed 35 tests / 382 assertions, 0 failures, 0 errors.
- Product commit: `138efcf66f2279063534f4c9d23ead3aa7b32a26` (`feat(f9): generate monthly lease charges atomically`).
- Generation locks the canonical lease before loading contract values and active charges, snapshots rent and positive administration fees, accepts contract boundary months, ignores voided rows for duplicate detection, and rolls back newly inserted rows together.
- The integration test observes PDO statement order and a MariaDB trigger forces the second insert to fail while confirming the pre-existing active rent row remains intact.

## Task 4 evidence

- RED: focused void tests failed because `voidCharge()` was absent; summary tests failed because `summaryForLease()` was absent; portfolio tests failed because `portfolio()` was absent.
- GREEN: `vendor/bin/phpunit --testdox tests/Integration/Finance/FinanceServiceTest.php` — PASS, 18 tests / 121 assertions, 0 failures, 0 errors.
- Product commit: `198b682aaf7f1a1e2ad648d6bc3ec2953f8ea8ea` (`feat(f9): derive charge portfolio and void history`).
- Voiding locks one charge in a transaction, records actor/time/reason once, preserves historical rows and fails closed on repeat or invalid requests.
- `summaryForLease()` and `portfolio()` reuse one exact DECIMAL aggregation expression; overdue is strictly `due_date < today`, all-voided history remains visible in the portfolio with zero current totals, and leases without history are excluded.
- Tests cover near-maximum DECIMAL sums, due-today boundary, no-charge zero output, one portfolio row per lease and canonical tenant/unit/property relationships. No derived balance/status columns were added.

## Task 5 evidence

- RED: FinanceRoutes tests reached the expected missing-route NotFound; the permission catalog rejected `finance.view`/`finance.manage`; the shell omitted the authorized finance launcher.
- GREEN: `vendor/bin/phpunit --testdox tests/Integration/Finance/FinanceRoutesTest.php tests/Integration/Auth/UserServiceTest.php tests/Integration/UI/ShellRoutesTest.php tests/Integration/Auth/CsrfRoutesTest.php` — PASS, 31 tests / 216 assertions, 0 failures, 0 errors.
- Product commit: `8f25f16100b4d956b7be3dcd964377e36d0bfb7d` (`feat(f9): expose private finance portfolio routes`).
- Exactly four private routes are registered in `app/Finance/routes.php`; route guards apply login, forced-password, and their independent finance permission. The global CSRF middleware covers each POST.
- Bootstrap constructs one FinanceService and shares it with Finance, Contract and Lease route composition. The portfolio view escapes canonical context, shows only derived totals/history context, and contains no payment controls or void metadata.
- The existing Apps launcher shows one `Cartera` card to `finance.view` staff; tenant users remain fail-closed and Apps-free even with polluted finance permissions. No public routes were added.

## Task 6 evidence

- RED: the integration test for the existing `/admin/contracts/{id}` detail failed because finance history/summary and forms were absent.
- GREEN: FinanceRoutes, ContractAdminRoutes, ContractFinalizationRoutes and ContractSecurityRegression passed 18 tests / 261 assertions, 0 failures, 0 errors.
- Product commit: `55fa6e47e467c3c70205a098bb4780dbf927818b` (`feat(f9): integrate charges in lease administration context`).
- The existing contract GET finds its lease first, then calls `summaryForLease()` and `listForLease()` only when `finance.view` is allowed. It passes `canManageFinance` separately from `contracts.manage`.
- `contract_detail.php` now renders escaped charge history/totals and CSRF-protected monthly, manual and void forms using only the existing finance POST routes. Manual options exclude rent/administration; voided charges cannot be voided again.
- The F7 regression test confirms `ContractService` and `ContractPdfRenderer` contain no FinanceService dependency. Views use existing user-card primitives; no new CSS or detail route was added.

## Task 7 evidence

- RED: focused tenant detail integration failed because the existing owned lease view had no `Cartera` section.
- GREEN: `vendor/bin/phpunit --display-warnings --testdox tests/Integration/Leases/TenantPortalRoutesTest.php tests/Integration/Finance/FinanceSecurityRegressionTest.php tests/Integration/UI/ShellRoutesTest.php tests/Integration/Leases/LeaseSecurityRegressionTest.php` — PASS, 21 tests / 267 assertions, 0 failures/errors/warnings.
- Product commit: `35287335be0b2d8bc918157730862365869b713e` (`feat(f9): show owned charges in tenant portal`).
- Existing tenant detail resolves `LeaseService::findForUser()` and returns 404 before FinanceService; traced-PDO integration assertions prove foreign and missing leases make no `charges` query. Ended owned leases retain readable history.
- The view displays canonical summary/list projections, localized pending/overdue/voided states, period, due date, amount, description and overdue days. Description is escaped; void reason and actor metadata are absent; no staff forms, payment UI, new route or CSS was added.
- Added `FinanceSecurityRegressionTest` for exact route inventory, ownership-before-finance order, shared FinanceService composition, and tenant-safe read fields. Updated the F8 future-module guardrail so `Cartera` is permitted only in tenant lease detail.
