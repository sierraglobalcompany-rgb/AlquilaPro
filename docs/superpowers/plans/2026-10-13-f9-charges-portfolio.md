# F9 Charges + Portfolio Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the single AlquilaPro charges/portfolio authority for leases: monthly rent/administration charges, explicit manual charges, voiding, derived balance/overdue state, administrative portfolio visibility, and tenant visibility—without payments, reconciliation, cron, duplicated balances, or parallel financial architecture.

**Architecture:** F9 adds exactly one table (`charges`), one domain class (`FinanceService`), and at most one route file (`app/Finance/routes.php`). Charges belong to the canonical F8 `lease`; rent/administration amounts snapshot the finalized F7 contract at charge creation, while balance/status/days-overdue remain derived. Existing auth, shell, contract context and tenant portal are extended rather than duplicated.

**Tech Stack:** PHP 8.3–8.5, Slim 4, MariaDB/MySQL, PDO, SSR HTML, existing CSS/JS, PHPUnit 11.5, no new runtime dependency.

**Spec:** `docs/specs/F9_CHARGES_PORTFOLIO_SPEC.md` — approved by the user in chat on 2026-10-13. Product implementation remains blocked until F8 is finally audited and its exact base SHA is fixed.

## Global Constraints

- KISS: one financial authority, one `charges` table, one `FinanceService`, at most one F9 route file.
- Cero Ruido order: `ELIMINAR -> UNIR / CONVERGER -> SIMPLIFICAR -> MEJORAR -> AGREGAR`.
- Anti-patch: reproduce -> root cause -> owning authority -> converge/simplify -> minimal GREEN -> remove superseded logic -> regression.
- PHP remains `>=8.3 <8.6`; no Node/npm production runtime, Redis, queue, worker, SPA, microservice or new direct Composer dependency.
- F9 schema delta exactly `+1` table: `charges`.
- No `payments`, `payment_allocations`, `payment_methods`, `payment_reports`, `receivables`, `balances`, `late_fees`, `charge_periods`, `charge_status_history`, `ledger_entries` or future-module tables.
- No persisted `charges.status`, `balance`, `outstanding_amount`, `paid_amount`, `remaining_amount`, or `days_overdue`.
- F9 charge types exactly `rent|administration|service|fine|repair|other`.
- `membership` remains F20; utilities remain structurally F12; payment/report/reconciliation state remains F10/F11.
- No automatic interest, penalty, proration, cron generation, catch-up generation, scheduler or worker.
- Rent/administration use `contracts.monthly_rent` / `contracts.administration_fee` through `lease -> contract` and become immutable charge snapshots at creation.
- Financial staff permissions exactly `finance.view` and `finance.manage`; owner keeps existing bypass; tenant never receives staff finance permissions.
- Existing F8 tenant ownership remains `leases.user_id = current session user.id`; browser input never supplies ownership authority.
- No new tenant route is planned: F9 extends existing `GET /tenant/leases/{id}`.
- F9 adds zero public routes.
- No delete/edit-in-place for an existing charge; correction is `void + create correct charge`.
- Every product task follows RED -> verify RED -> minimal GREEN -> focused GREEN -> commit/checkpoint.
- No F9 merge without explicit later authorization.

## Review Focus

1. **Duplicate monthly charge under concurrency:** two attempts for the same lease/type/period must never create a second active rent or administration charge; the canonical lease lock is the serialization authority, not a speculative dedupe table/key.
2. **Money input boundary:** reject malformed, zero/negative, >2-decimal and out-of-range values; keep monetary authority as normalized decimal strings/DECIMAL rather than PHP float.
3. **Calendar boundary:** `period_month` must be strict `YYYY-MM-01`; rent/admin period must fall between the first day of the contract start month and first day of the contract end month inclusive; overdue calculations use an injected/testable `today`.
4. **Partial monthly correction:** if one expected monthly type remains active and the other was voided/missing, regeneration creates only the missing/voided type; if every expected type is already active, the operation fails as a duplicate/no-op. This avoids forcing an unrelated valid charge to be voided.
5. **Tenant isolation/XSS:** tenant detail may show only charges for the already-owned lease; manual `description` is escaped and staff-only void actor/reason metadata is not exposed.

---

## Planned File Map

### Create

- `database/migrations/009_create_charges.sql` — the only F9 persistence delta.
- `app/Finance/FinanceService.php` — the only F9 domain authority.
- `app/Finance/routes.php` — central portfolio read + three financial mutations.
- `resources/views/finance_index.php` — minimal staff portfolio view.
- `tests/Integration/Finance/ChargeSchemaTest.php` — schema/noise freeze.
- `tests/Integration/Finance/FinanceServiceTest.php` — manual/monthly/void/read domain behavior.
- `tests/Integration/Finance/FinanceRoutesTest.php` — auth/permissions/CSRF/basePath/PRG + portfolio.
- `tests/Integration/Finance/FinanceSecurityRegressionTest.php` — architectural/privacy/no-future guardrails.

### Modify, after execution preflight confirms F8 final paths

- `app/Auth/Permissions.php` — add exactly `finance.view`, `finance.manage`.
- `tests/Integration/Auth/UserServiceTest.php` — pin permission catalog/sanitization and tenant fail-closed behavior with finance permissions.
- `bootstrap/app.php` — instantiate one `FinanceService` and register one Finance route file; pass it to existing Contract/Lease route composition where needed.
- `app/Contracts/routes.php` — compose finance read data into the existing finalized-contract/lease detail only; F7 core service stays finance-free.
- `resources/views/contract_detail.php` — finance section/forms inside existing lease context.
- `app/Leases/routes.php` — after F8 ownership lookup, compose tenant finance summary/list into the existing detail route.
- `resources/views/tenant_lease_detail.php` — render owned balance/charges, no payment UI.
- `resources/views/layouts/app.php` — add one staff Finance launcher card if `finance.view`; tenant branch remains Apps-free.
- `tests/Integration/UI/ShellRoutesTest.php` — launcher authorization/basePath and tenant non-leakage.
- F7/F8 regression tests only if a legitimate new composition point makes a historical string guardrail too broad; never weaken core/domain isolation.
- closure-only: F9 SPEC status, README, `docs/checkpoints/F9_CLOSED_<date>.md`.

### Not planned

- No change to `composer.json` / lock file.
- No new CSS/JS unless a reproduced visual regression proves it necessary.
- No payment/reconciliation/cron/future-module code or placeholder routes.
- No new tenant layout/auth/session/controller stack.

## Execution Preflight — mandatory before Task 1 product work

Implementation MUST NOT start from this docs branch, which intentionally started from F7 `main` while F8 was in progress.

- [ ] Verify F8 is `CLOSED / VERIFIED` and ChatGPT/Sol has completed final F8 audit.
- [ ] Record the exact accepted F8 closure/product SHA and current `main` SHA.
- [ ] Re-read `docs/specs/F9_CHARGES_PORTFOLIO_SPEC.md`, this plan, F8 final checkpoint, actual `LeaseService`, F8 routes/views, Permissions, bootstrap, and relevant security tests.
- [ ] Confirm F8 final architecture still uses one `leases` table, one `LeaseService`, ownership via `leases.user_id`, and existing `contract_detail.php` / `tenant_lease_detail.php` integration points.
- [ ] Create the implementation branch from the exact accepted F8-integrated base (suggested name `impl/f9-charges-portfolio-20261013`). Never copy product commits from the F7-based docs branch.
- [ ] Bring only the approved F9 SPEC/PLAN documentation onto the implementation branch by normal Git integration if needed.
- [ ] Update F9 SPEC gate to `APPROVED / IMPLEMENTATION IN PROGRESS`, record the exact F8 base, keep merge unauthorized.
- [ ] Commit only that state transition before Task 1.

If F8 final paths/interfaces differ materially, adjust this plan's file references only enough to match the same approved F9 design. A design change requires a new SPEC review; a path/name convergence does not.

---

### Task 1: Freeze the single-table charge schema

**Files:**
- Create: `database/migrations/009_create_charges.sql`
- Create: `tests/Integration/Finance/ChargeSchemaTest.php`
- Modify only if a historical table-count guardrail legitimately needs phase-aware convergence: the owning schema regression test.

**Interfaces:**
- Consumes: `leases.id`, `users.id`, existing migration runner.
- Produces exact table `charges(id, lease_id, type, period_month, due_date, amount, description, created_by_user_id, created_at, voided_by_user_id, voided_at, void_reason)`.

- [ ] **Step 1: Write schema RED**

`ChargeSchemaTest` asserts exact columns/types/nullability, `lease_id -> leases.id ON DELETE RESTRICT`, creator/void actors `-> users.id ON DELETE SET NULL`, and exactly these indexes:

```text
charges_lease_due_index (lease_id, due_date)
charges_lease_period_type_index (lease_id, period_month, type)
charges_created_by_user_index (created_by_user_id)
charges_voided_by_user_index (voided_by_user_id)
```

Also assert F9 does not create `payments|payment_allocations|payment_methods|payment_reports|receivables|balances|late_fees|charge_periods|charge_status_history|ledger_entries` and `charges` has no `status|balance|outstanding_amount|paid_amount|remaining_amount|days_overdue|currency` columns.

- [ ] **Step 2: Verify RED**

Run:

`vendor/bin/phpunit --testdox tests/Integration/Finance/ChargeSchemaTest.php`

Expected: FAIL only because migration/table `charges` is absent; pre-F9 schema tests stay green.

- [ ] **Step 3: Implement migration 009 only**

Use `DECIMAL(12,2)` for `amount`, `DATE` for period/due dates, `DATETIME` actor/time fields, `VARCHAR(32)` type, `VARCHAR(500)` description/reason. No CHECK/enum/status/history table unless the existing migration style already has an approved equivalent and it does not create a second authority; service allowlists remain canonical validation.

- [ ] **Step 4: Verify GREEN**

Run Task 1 schema test plus existing F8 lease schema and F7 contract schema tests. Expected PASS.

- [ ] **Step 5: Commit + checkpoint**

Commit product unit:

`feat(f9): add minimal charge schema`

Checkpoint records exact migration delta and Two Laws PASS; stop/review boundary before Task 2.

---

### Task 2: Implement manual charges and canonical read projection

**Files:**
- Create: `app/Finance/FinanceService.php`
- Create/Modify: `tests/Integration/Finance/FinanceServiceTest.php`

**Interfaces:**
- Constructor: `FinanceService::__construct(PDO $pdo)`.
- Produces `createManualCharge(int $leaseId, string $type, string $amount, string $dueDate, string $description, ?string $periodMonth, int $actorUserId): array`.
- Produces `listForLease(int $leaseId, string $today): array`.
- Charge projection includes persisted charge fields plus derived `state`, `days_overdue`, and derived `contract_id`; no persisted balance/status.
- Manual type allowlist exactly `service|fine|repair|other`.

- [ ] **Step 1: Write manual-charge RED tests**

Assert valid manual charge persists against existing lease; `rent|administration` are rejected by the manual path; unsupported types fail; lease/actor IDs must be positive; amount rejects malformed, zero, negative, >2 decimals and value above `DECIMAL(12,2)`; `description` trims and is required for manual types; valid `period_month` is null or strict first-of-month; malformed dates fail; ended lease remains allowed; no contract/lease/unit/candidate/screening mutation occurs.

- [ ] **Step 2: Add Review Focus money/date tests**

Pin normalized decimal output (e.g. `1200000.00`), avoid float comparisons, and verify `listForLease(..., today)` derives `pending|overdue|voided` and exact `days_overdue` without writing derived columns.

- [ ] **Step 3: Verify RED**

Run `FinanceServiceTest` only. Expected: class/method missing failures.

- [ ] **Step 4: Implement the minimal FinanceService boundary**

Use prepared PDO only. Normalize money/date/period inside this one authority; manual creation validates the canonical lease exists and performs one INSERT. Do **not** lock the lease or open an otherwise-unnecessary transaction for a single manual INSERT: FK integrity plus the existing no-delete operational model is sufficient. Keep normalization helpers private—do not create Money/Date/Repository classes.

- [ ] **Step 5: Implement canonical lease read projection**

`listForLease()` queries only the supplied lease, orders history deterministically (recommended `due_date ASC, id ASC`), derives state and days-overdue from the passed `today`, and returns no staff-only void actor/reason fields to tenant composition. Include `id` because staff void forms need the charge identity; tenant views need not render it. Do not duplicate admin and tenant balance algorithms.

- [ ] **Step 6: Verify GREEN**

Run `FinanceServiceTest` plus F8 LeaseLifecycle/LeaseActivation tests. Expected PASS and zero F8 side-effect regression.

- [ ] **Step 7: Commit + checkpoint**

`feat(f9): add manual charge authority`

Checkpoint records interface signatures and decimal/date boundary.

---

### Task 3: Add atomic monthly rent/administration generation

**Files:**
- Modify: `app/Finance/FinanceService.php`
- Modify: `tests/Integration/Finance/FinanceServiceTest.php`

**Interfaces:**
- Produces `createMonthlyCharges(int $leaseId, string $periodMonth, string $dueDate, int $actorUserId): array`.
- Consumes canonical F8 `leases.contract_id` and F7 `contracts(monthly_rent, administration_fee, start_date, end_date)`.

- [ ] **Step 1: Write monthly RED tests**

Assert `rent` amount comes exactly from contract `monthly_rent`; administration is expected only when non-null and >0; both use one explicit period/due date; persisted amounts remain unchanged if source data is altered in a test fixture after creation; period must be `YYYY-MM-01`; the accepted monthly range is first day of `start_date` month through first day of `end_date` month inclusive; out-of-range period fails without rows.

- [ ] **Step 2: Write duplicate/correction RED tests**

Pin per-type behavior under the same lease lock:

- when all expected monthly types for the period are already active, the operation fails as duplicate/no-op and creates nothing;
- when rent is active but administration is missing/voided, preserve rent and create only administration if the contract expects it;
- when administration is active but rent is missing/voided, preserve administration and create only rent;
- when both are missing/voided, create both expected types;
- a voided prior row never counts as an active duplicate;
- if any new INSERT in the operation fails, roll back every new row created by that invocation while preserving pre-existing active rows.

This is the minimal correction model: never duplicate a valid active charge and never force an unrelated valid counterpart to be voided.

- [ ] **Step 3: Pin lock ordering structurally**

Regression assertion verifies monthly generation begins a transaction and locks the canonical `lease FOR UPDATE` before active-duplicate queries/INSERTs. Do not add a `dedupe_key`, unique-period table, or second lock authority.

- [ ] **Step 4: Verify RED**

Run focused FinanceService monthly tests. Expected missing method/behavior failures only.

- [ ] **Step 5: Implement minimal monthly transaction**

Exact order: `BEGIN -> lease FOR UPDATE -> read contract -> normalize/range-check period and due date -> read active rent/admin rows for this period -> determine only missing expected types -> if none missing then fail duplicate/no-op -> INSERT missing rent/admin rows -> COMMIT`. Any throwable rolls back only this invocation's new F9 writes.

- [ ] **Step 6: Verify concurrency contract**

Add the strongest deterministic test supported by the existing MariaDB integration harness. At minimum, structural lock-order regression plus sequential duplicate proof must be present; if the harness cleanly supports two PDO connections, add a real serialization test without adding production coordination primitives.

- [ ] **Step 7: Verify GREEN**

Run full FinanceService tests plus F7 ContractFinalization/ContractDraft tests and F8 lease lifecycle tests. Expected PASS; F9 never mutates contract/lease/unit/candidate/screening state.

- [ ] **Step 8: Commit + checkpoint**

`feat(f9): generate monthly lease charges atomically`

---

### Task 4: Add voiding, balance/overdue summaries, and portfolio projection

**Files:**
- Modify: `app/Finance/FinanceService.php`
- Modify: `tests/Integration/Finance/FinanceServiceTest.php`

**Interfaces:**
- Produces `voidCharge(int $chargeId, int $actorUserId, string $reason, int $now): array`.
- Produces `summaryForLease(int $leaseId, string $today): array{outstanding:string,overdue:string,oldest_due_date:?string}`.
- Produces `portfolio(string $today): array` — one row per lease that has charge history, with tenant/property/unit context plus derived totals and oldest current due date.

- [ ] **Step 1: Write void RED tests**

First void stores actor/time/reason once; second void fails closed and preserves originals; row remains; no replacement is auto-created; void does not change contract/lease/unit/user; a voided charge projects state `voided`, days-overdue 0, and is excluded from current balances.

- [ ] **Step 2: Write summary RED tests**

For controlled `today`, assert outstanding sums only non-voided charges; overdue sums only non-voided charges with `due_date < today`; due today is not overdue; oldest pending due date ignores voided charges; zero/no-charge lease returns normalized zero decimals and null oldest due date; no summary column is persisted.

- [ ] **Step 3: Write portfolio RED tests**

Assert portfolio uses canonical joins `charges -> leases -> contracts -> users/units/properties`, aggregates one row per lease with history, excludes voided amounts from current totals but keeps the lease visible if it has historical charge rows, and never associates a tenant/user outside the canonical lease relation.

- [ ] **Step 4: Verify RED**

Run focused FinanceService void/summary/portfolio tests.

- [ ] **Step 5: Implement void transaction**

`BEGIN -> charge FOR UPDATE -> require not voided -> validate non-empty reason -> update actor/time/reason once -> COMMIT`. Keep void reason staff-only in route/view composition.

- [ ] **Step 6: Implement shared derived calculation helpers inside FinanceService**

Avoid separate admin vs tenant balance algorithms. Both `summaryForLease` and `portfolio` must use the same canonical state/amount semantics; if SQL aggregation is simplest, keep it in FinanceService and test exact decimals.

- [ ] **Step 7: Verify GREEN**

Run all FinanceService tests. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

`feat(f9): derive charge portfolio and void history`

---

### Task 5: Expose private staff finance routes, permissions, portfolio, and launcher

**Files:**
- Create: `app/Finance/routes.php`
- Create: `resources/views/finance_index.php`
- Create/Modify: `tests/Integration/Finance/FinanceRoutesTest.php`
- Modify: `app/Auth/Permissions.php`
- Modify: `tests/Integration/Auth/UserServiceTest.php`
- Modify: `bootstrap/app.php`
- Modify: `resources/views/layouts/app.php`
- Modify: `tests/Integration/UI/ShellRoutesTest.php`

**Interfaces:**
- Routes exactly:
  - `GET /admin/finance`
  - `POST /admin/leases/{id}/charges/monthly`
  - `POST /admin/leases/{id}/charges`
  - `POST /admin/charges/{id}/void`
- Read authority: `finance.view`; mutation authority: `finance.manage`.
- `FinanceService` is instantiated once in bootstrap and shared; no container/repository layer.

- [ ] **Step 1: Write permission/route RED tests**

Assert unauthenticated -> login; forced-password -> `/account/password`; viewer can GET portfolio but cannot POST; manager can mutate; owner bypasses; tenant receives 403 on staff finance surface even with polluted permissions; all POSTs use global CSRF; subfolder/basePath redirects work.

- [ ] **Step 2: Write portfolio-view RED test**

`GET /admin/finance` renders escaped tenant/property/unit context, outstanding/overdue/oldest due from `FinanceService::portfolio(today)`, no charts/KPIs/payment controls/future modules, and no internal void reason or actor IDs.

- [ ] **Step 3: Write mutation PRG RED tests**

Monthly/manual/void successful POSTs return 303 to the owning existing contract context using derived `contract_id`; invalid domain input returns 422 without mutation; no secret/credential is involved. Manual form cannot submit `rent|administration` through the generic path.

- [ ] **Step 4: Add exactly two permissions**

Extend catalog with `finance.view`, `finance.manage`; preserve tenant fail-closed behavior and owner bypass. Update `UserServiceTest`/permission expectations accordingly—do not create per-charge permissions or implicit role-specific permission stacks.

- [ ] **Step 5: Wire one shared FinanceService and one route file**

`bootstrap/app.php`: instantiate `FinanceService($pdo)` once; register `app/Finance/routes.php`; pass FinanceService to existing Contract/Lease route composition for later tasks without instantiating it again.

- [ ] **Step 6: Implement staff route guard and routes**

Follow existing local route style: current user -> forced-password -> permission. Boundary supplies current date in `America/Bogota` to derived read methods; do not hide a global clock in FinanceService. Use existing global CSRF middleware.

- [ ] **Step 7: Add one staff Finance launcher card**

Existing shared layout: `finance.view` shows a `Cartera` card linking `/admin/finance`; tenant branch remains Apps-free even with polluted permissions; empty-state condition includes finance permission. No new nav system or CSS.

- [ ] **Step 8: Verify GREEN**

Run FinanceRoutes, UserService and ShellRoutes tests plus global CSRF tests. Expected PASS.

- [ ] **Step 9: Commit + checkpoint**

`feat(f9): expose private finance portfolio routes`

---

### Task 6: Integrate charges into existing admin contract/lease context

**Files:**
- Modify: `app/Contracts/routes.php`
- Modify: `resources/views/contract_detail.php`
- Modify: `tests/Integration/Finance/FinanceRoutesTest.php`
- Modify historical F7 guardrail only if it incorrectly forbids legitimate route-composition dependency while the F7 core remains isolated.

**Interfaces:**
- Consumes existing `LeaseService::findByContract(int): ?array` and `FinanceService::listForLease()/summaryForLease()`.
- No new GET detail route and no finance logic added to `ContractService`.

- [ ] **Step 1: Write admin-context RED test**

For a finalized contract with lease: user with `contracts.view + finance.view` sees charge history/summary in existing contract detail; `finance.manage` adds monthly/manual/void forms; finance viewer sees data but no mutation controls; contract viewer without finance permission sees no financial section. Existing contract/lease actions preserve their own permissions.

- [ ] **Step 2: Pin composition boundary**

Add/adjust regression assertion: `ContractService` + `ContractPdfRenderer` must not contain `FinanceService`; route composition may receive it. Never hide the type to trick a string guardrail—narrow only an obsolete guardrail while preserving F7 core isolation.

- [ ] **Step 3: Implement minimal route composition**

Existing contract GET obtains the existing lease projection first; if lease exists and current staff user can `finance.view`, pass `financeSummary` + `charges`; pass `canManageFinance` independently from contract-management permission. No finance mutation logic belongs in Contract routes.

- [ ] **Step 4: Extend existing `contract_detail.php` only**

Inside Arrendamiento context, render escaped summary/history and SSR forms posting to the Task 5 Finance routes. Use existing components; no charge CRUD page/modal/JS.

- [ ] **Step 5: Verify GREEN**

Run FinanceRoutes plus ContractAdminRoutes/ContractFinalizationRoutes/ContractSecurityRegression tests. Expected PASS.

- [ ] **Step 6: Commit + checkpoint**

`feat(f9): integrate charges in lease administration context`

---

### Task 7: Extend the existing tenant lease detail with owned portfolio

**Files:**
- Modify: `app/Leases/routes.php`
- Modify: `resources/views/tenant_lease_detail.php`
- Modify: `tests/Integration/Leases/TenantPortalRoutesTest.php` or create a focused Finance tenant integration test only if that keeps ownership coverage clearer.
- Modify: `tests/Integration/Finance/FinanceSecurityRegressionTest.php`

**Interfaces:**
- Reuses existing F8 `GET /tenant/leases/{id}`; adds zero routes.
- Ownership is resolved first via `LeaseService::findForUser(leaseId, currentUserId)`; only then call `FinanceService` with the owned lease ID.

- [ ] **Step 1: Write tenant RED tests**

Owned lease detail displays outstanding, overdue, period, due date, amount, tenant-safe description, derived pending/overdue/voided state and exact days-overdue. Foreign/nonexistent lease remains 404 before finance lookup; ended/historical owned lease remains readable; voided history may remain visible but does not sum; no payment/report UI exists.

- [ ] **Step 2: Write privacy/XSS RED tests**

Manual description containing HTML is escaped; tenant output omits `created_by_user_id`, `voided_by_user_id`, `void_reason`, other lease data, SQL/storage metadata and all staff mutation forms.

- [ ] **Step 3: Implement ownership-first composition**

Keep F8 tenant guard unchanged. After `findForUser()` succeeds, pass FinanceService summary/list using the canonical owned lease ID. Do not move tenant ownership into FinanceService or accept user ID from request parameters.

- [ ] **Step 4: Extend the existing tenant detail view**

Add one `Cartera` section using existing surface/card primitives. No second portal/layout, no Apps, no new CSS unless a reproduced overflow/visual defect demands the smallest fix.

- [ ] **Step 5: Verify GREEN**

Run TenantPortalRoutes + finance tenant/security tests + ShellRoutes. Expected PASS and zero F8 ownership/navigation regression.

- [ ] **Step 6: Commit + checkpoint**

`feat(f9): show owned charges in tenant portal`

---

### Task 8: Freeze F9 security/architecture, full QA, Two Laws gate, and closure docs

**Files:**
- Create/complete: `tests/Integration/Finance/FinanceSecurityRegressionTest.php`
- Modify after product GREEN: `docs/specs/F9_CHARGES_PORTFOLIO_SPEC.md`
- Modify after product GREEN: `README.md`
- Create after product GREEN: `docs/checkpoints/F9_CLOSED_<date>.md`

**Interfaces:**
- Produces one review-ready F9 branch and one canonical CLOSED checkpoint.
- Does not merge.

- [ ] **Step 1: Complete architecture/security regression test**

Freeze at minimum:

```text
1 charges table
1 FinanceService
1 Finance route file maximum
2 finance permissions
4 private F9 routes maximum
0 F9 public routes
0 payment/report/allocation tables
0 persisted charge status/balance/days-overdue
0 direct runtime dependencies
0 cron/scheduler/worker
0 tenant/lease duplicate tables
0 future-module UI
```

Also pin: Finance core has no second auth/session/CSRF stack; tenant route performs F8 ownership before finance read; manual path does not allow rent/admin; staff void reason is not tenant output; launcher does not leak to tenant.

- [ ] **Step 2: Run focused F9/F8 integration suite**

Run all `tests/Integration/Finance`, relevant `Auth/UserServiceTest`, `Leases/TenantPortalRoutesTest`, lease lifecycle/activation tests, Contract security/admin tests, ShellRoutes and CSRF tests. Expected 0 failures / 0 errors. Record actual counts, never predict them.

- [ ] **Step 3: Run Composer + full local suite**

Run `composer validate --strict` and `vendor/bin/phpunit --testdox`. Expected PASS; record PHP version, tests, assertions, failures/errors/skips.

- [ ] **Step 4: Audit Two Laws against the product diff**

Prove architecture budget and inspect for dead code, duplicated balance/status algorithms, duplicate finance SQL authorities, future payment tables, F12/F14/F20 integration placeholders, cron, new dependencies, unnecessary CSS/JS, staff/tenant privacy leaks and patch-on-patch conditionals. Classify suspicious code `REMOVE|CONVERGE|SIMPLIFY|IMPROVE|KEEP`; any product correction returns to the owning task with RED before closure.

- [ ] **Step 5: Push exact PRODUCT_HEAD and require CI matrix**

Require exact SHA CI PASS on PHP 8.3/8.4/8.5. Record run ID and actual PHP 8.3 test/assertion counts. No partial-matrix closure.

- [ ] **Step 6: Review every changed product file before docs closure**

Compare against accepted F8 base. CI green is not a substitute for diff review. Any blocker returns to owning task and repeats focused/full/CI verification.

- [ ] **Step 7: Converge closure documentation**

Only after clean product review: mark SPEC implemented/verified; README `F9 CLOSED / VERIFIED / NOT MERGED`; create canonical F9 CLOSED checkpoint with F8 base SHA, product/closure SHAs, exact schema/routes/interfaces, RED/GREEN summary, CI evidence, Two Laws/antipatch verdict and explicit non-features.

- [ ] **Step 8: Commit closure docs and require fresh exact-HEAD CI**

Commit:

`docs(f9): close verified charges portfolio`

Push exact closure HEAD and require a fresh PHP 8.3/8.4/8.5 PASS. If closure CI fails, F9 is not CLOSED.

- [ ] **Step 9: Open/update F9 PR and stop at merge gate**

PR base must be current accepted `main`; body includes architecture budget, F8 base, product + closure CI, changed-file audit, security/privacy, Two Laws PASS and `MERGE NOT AUTHORIZED`. Do not auto-merge, merge, start F10, or add optional improvements.

---

## Self-Review Result

1. **Spec coverage:** every F9 SPEC requirement maps to Tasks 1–8; F10/F11/F12/F14/F16/F20 surfaces remain explicitly excluded.
2. **Step granularity:** each implementation step has one checkable output; no task requires an executor to invent a table/class/route/state outside the plan.
3. **Type consistency:** FinanceService signatures are stable across tasks; `periodMonth` is nullable only for manual charges and strict first-of-month for recurrent charges; all monetary values are decimal strings.
4. **Review Focus coverage:** concurrency, money/date boundaries, partial correction and tenant isolation each have owning RED tests.
5. **Proportion/KISS:** plan defines decisions/interfaces/tests but does not transcribe method bodies; no second financial authority or future payment scaffold was introduced.
6. **Anti-patch audit:** manual charge path no longer takes an unnecessary lease lock/transaction; monthly correction handles only missing expected types instead of forcing an unrelated valid counterpart to be voided.

## Final Execution Contract

A task is not closed because code exists; it is closed only after its focused verification and checkpoint. A bug found in a later task returns to its owning authority with a new RED—never a lateral patch. If completing F9 requires a second table/class/financial authority outside this plan, stop and reopen SPEC rather than expanding architecture silently.

Expected final handoff:

```text
F9_STATUS: CLOSED / VERIFIED / NOT MERGED
LAW_1_KISS: PASS
LAW_2_CERO_RUIDO: PASS
PATCH_STACKING: NONE
NEW_TABLES: 1
NEW_DOMAIN_CLASSES: 1
NEW_ROUTE_FILES: 1 max
NEW_PERMISSIONS: 2
NEW_PUBLIC_ROUTES: 0
NEW_PAYMENT_TABLES: 0
NEW_DIRECT_RUNTIME_DEPENDENCIES: 0
NEW_CRON_JOBS: 0
NEXT_ACTION: CHATGPT/SOL FINAL FORENSIC AUDIT
```
