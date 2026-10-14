# F11 Payment Report + Reconciliation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add tenant payment reporting, optional private receipts, human reconciliation, partial payments and relational allocations while keeping one financial authority and deriving all balances/statuses from canonical charges plus reconciled allocations.

**Architecture:** F11 adds exactly two tables (`payments`, `payment_allocations`) and extends the existing `FinanceService`; it does not create `PaymentService` or `payment_reports`. A `payments` row is the report and, after human review, the reconciled payment or rejected report. Allocations exist from report time as proposed distribution but affect balances only after reconciliation.

**Tech Stack:** PHP >=8.3 <8.6, Slim 4, MariaDB/MySQL, PDO, SSR HTML, existing CSS/JS, PHPUnit 11.x, existing private-filesystem pattern, no new runtime dependency.

**Spec:** `docs/specs/F11_PAYMENT_REPORT_RECONCILIATION_SPEC.md` — approved by the user on 2026-10-13. Product implementation remains blocked until F10 is finally audited and merged into `main`.

## Global Constraints

- KISS: exactly two F11 tables, zero new F11 domain classes expected, zero new permissions, zero public routes.
- Cero Ruido order: `ELIMINAR -> UNIR / CONVERGER -> SIMPLIFICAR -> MEJORAR -> AGREGAR`.
- Anti-patch: reproduce -> root cause -> owning authority -> converge/simplify -> minimal GREEN -> remove superseded logic -> regression.
- Reuse F9 `FinanceService` and `finance.view/manage`; reuse F10 `PaymentMethodService`; do not create parallel payment/report authorities.
- `payments` has no persisted `status`; state derives from reconciliation/rejection timestamps.
- `charges` keeps no persisted balance/status; F11 extends F9 projections using reconciled allocations only.
- Pending/rejected allocations never reduce outstanding.
- `SUM(payment_allocations.amount) = payments.amount` at report and reconciliation boundaries.
- No unapplied cash, credits, refunds, reversals, chargebacks, accounting ledger, bank API, Bre-B API, PSE, webhooks, OCR, cron or notifications.
- Receipt is optional, single-file, private, PNG/JPEG/WebP/PDF, <=5 MiB, MIME by bytes, random filename, SHA256/size verified on read.
- Duplicate detection is derived warning only; it never blocks a legitimate report and is not persisted.
- Tenant ownership is resolved before any lateral payment/receipt lookup.
- Existing F10 method must be active/applicable when reporting; later deactivation does not invalidate the historical payment report.
- `paid_on <= today` in `America/Bogota`; `paid_time` optional.
- No F11 merge without an explicit later merge gate.

## Review Focus

1. **Over-allocation under concurrency:** two reconciliations touching the same charge must serialize on charge rows in stable ID order and never allocate above current outstanding.
2. **Pending overlap:** a second legitimate pending report may target the same outstanding because pending allocations do not reduce balance; it should warn, not block.
3. **Method lifecycle:** payment method must be active/applicable at report time, but deactivation afterwards must not break reconciliation because the sanitized snapshot is historical evidence.
4. **Receipt failure cleanup:** if bytes are moved but payment/allocation DB work fails, the new receipt must be removed and no partial payment row remain.
5. **Terminal race:** reconcile vs reject and double reconcile/reject must leave exactly one immutable terminal result and preserve the first actor/timestamp/reason.

---

## Planned File Map

### Create

- `database/migrations/011_create_payments.sql` — creates only `payments` + `payment_allocations`.
- `tests/Integration/Finance/PaymentSchemaTest.php` — exact schema/noise freeze.
- `tests/Integration/Finance/PaymentReportTest.php` — report, allocations, snapshot, dates/money and receipt lifecycle.
- `tests/Integration/Finance/PaymentReconciliationTest.php` — reconcile/reject, partials, balances and concurrency.
- `tests/Integration/Finance/PaymentRoutesTest.php` — staff/tenant routes, permissions, ownership, CSRF, PRG and receipt serving.
- `tests/Integration/Finance/PaymentSecurityRegressionTest.php` — architecture/privacy/no-F12+ guardrails.

### Modify after execution preflight confirms F10 final paths

- `app/Finance/FinanceService.php` — F11 financial authority: report/list/find/reconcile/reject/receipt + F9 balance/status extension.
- `app/Finance/routes.php` — preferred home for admin detail/receipt/reconcile/reject; existing `/admin/finance` receives pending queue.
- `app/Leases/routes.php` — tenant report and owned receipt routes after lease ownership.
- `resources/views/finance_index.php` — reconciliation queue.
- `resources/views/tenant_lease_detail.php` — report-payment form + own payment history.
- create one small admin detail view only if F10/F9 final `finance_index.php` cannot clearly host payment detail without mixing unrelated forms.
- `bootstrap/app.php` only if final F10/F9 service wiring requires a receipt directory or collaborator injection.
- existing F9/F10 tests where financial projections or constructor wiring legitimately evolve.
- closure-only: F11 SPEC status, README, `docs/checkpoints/F11_CLOSED_<date>.md`.

### Not planned

- no `payment_reports` table;
- no status/history/balance tables;
- no new payment domain service;
- no media table for receipts;
- no new permissions;
- no public routes;
- no Composer dependency change;
- no F12+ product surface.

## Execution Preflight — mandatory before Task 1 product work

Implementation MUST NOT start from this docs branch.

- [ ] Verify F10 is `CLOSED / VERIFIED`, ChatGPT/Sol final audit is PASS, and F10 is merged into `main`.
- [ ] Record exact F10 merge SHA and current `main` SHA; use that exact integrated SHA as the F11 implementation base.
- [ ] Re-read this SPEC/PLAN, F10 merged checkpoint, actual F9 `FinanceService`, F10 `PaymentMethodService`, finance/lease routes/views, Permissions, private-storage patterns and security tests.
- [ ] Confirm `finance.view/manage` exist exactly and tenant remains fail-closed for staff permissions.
- [ ] Confirm canonical F9/F10 method signatures. Preferred F11 wiring is to reuse `PaymentMethodService` for active/applicable method resolution and let `FinanceService` own payment persistence; do not duplicate F10 scope rules with lateral raw SQL if the final service already exposes the needed read.
- [ ] Confirm the private receipt directory can be injected/reused with minimal constructor/wiring change. If final `FinanceService` constructor would need to change, update all owning tests/wiring cleanly rather than adding compatibility overloads solely to preserve old test setup.
- [ ] Create `impl/f11-payment-report-reconciliation-20261013` from the exact F10-integrated `main` SHA.
- [ ] Bring only approved F11 SPEC/PLAN docs onto the implementation branch.
- [ ] Update F11 SPEC gate to `APPROVED / IMPLEMENTATION IN PROGRESS`, record `F10_INTEGRATED_BASE_SHA`, keep merge unauthorized, commit docs-only.

Path/name convergence to final F10 reality is allowed. A change to the two-table/one-financial-authority/permission model requires reopening SPEC.

---

### Task 1: Freeze the two-table payment schema

**Files:**
- Create: `database/migrations/011_create_payments.sql`
- Create: `tests/Integration/Finance/PaymentSchemaTest.php`
- Modify only if a historical schema-count guardrail legitimately needs phase-aware convergence: the owning regression test.

**Interfaces:**
- Consumes: `leases.id`, `payment_methods.id`, `charges.id`, `users.id`.
- Produces exact `payments` and `payment_allocations` persistence required by later tasks.

- [ ] **Step 1: Write schema RED**

Pin exact `payments` columns: `id, lease_id, payment_method_id, amount, paid_on, paid_time, payment_method_snapshot_json, reported_at, receipt_stored_name, receipt_mime_type, receipt_sha256, receipt_size_bytes, reconciled_by_user_id, reconciled_at, rejected_by_user_id, rejected_at, rejection_reason`.

Pin exact `payment_allocations` columns: `id, payment_id, charge_id, amount`.

Assert FK/delete rules from SPEC, `UNIQUE(payment_id, charge_id)`, minimal indexes from SPEC, and absence of `status|balance|is_duplicate|duplicate_of_id|reported_by_user_id` plus absence of `payment_reports|payment_status_history|ledger_entries|credits`.

- [ ] **Step 2: Verify RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Finance/PaymentSchemaTest.php`

Expected: FAIL only because migration/tables are absent; F9/F10 schema tests remain green.

- [ ] **Step 3: Implement migration 011 only**

One migration may create both tightly coupled F11 tables. Use `DECIMAL(12,2)`, `DATE`, nullable `TIME`, actor/timestamp pairs and TEXT snapshot exactly as SPEC; no enums/status/history tables.

- [ ] **Step 4: Verify GREEN**

Run F11 schema + F10 payment-method schema + F9 charge schema + F8 lease schema. Expected PASS.

- [ ] **Step 5: Commit + checkpoint**

Commit: `feat(f11): add payment and allocation schema`.

Checkpoint: exact +2-table delta, zero other schema authority, Two Laws PASS.

---

### Task 2: Add payment reporting, proposed allocations, snapshots and tenant read projections

**Files:**
- Modify: `app/Finance/FinanceService.php`
- Create/Modify: `tests/Integration/Finance/PaymentReportTest.php`
- Modify F9 finance tests where projected charge state/balance fixtures require F11-aware semantics later, but do not change balance semantics in this Task yet.

**Interfaces:**
- Preferred collaborator after F10 preflight: existing `PaymentMethodService` remains the authority for active/applicable method resolution; wire it into the report boundary without duplicating its scope algorithm.
- Produces conceptual API: `reportPayment(int $leaseId, int $paymentMethodId, string $amount, string $paidOn, ?string $paidTime, array $allocations, mixed $receipt, int $now): array`.
- Produces `listPaymentsForLease(int $leaseId): array` and `findPayment(int $paymentId): ?array`.
- `allocations` input is a list of `{charge_id:int, amount:string}`; duplicate charge IDs fail closed rather than silently sum.

- [ ] **Step 1: Write core report RED tests**

Assert valid owned/canonical lease payment persists pending with exact amount/date/time; ended lease with real outstanding remains allowed; amount uses decimal-string rules; future `paid_on` fails; valid time normalizes; malformed time/date fails; empty/zero/negative/out-of-range values fail.

- [ ] **Step 2: Add method/snapshot RED tests**

Assert method must currently be active and global or applicable to canonical lease property; another-property/inactive method fails. Snapshot includes only sanitized visible routing fields from SPEC and excludes QR filename/path/hash, secrets, actor/admin metadata. Later method changes do not mutate stored snapshot.

- [ ] **Step 3: Add allocation RED tests**

Assert every charge belongs to same lease, is non-voided, has outstanding >0, each allocation >0 and <= current outstanding computed from reconciled payments only, duplicate charge IDs fail, and exact decimal sum equals payment amount. Pending allocations from a different pending payment do not reduce outstanding and therefore do not block a second legitimate report.

- [ ] **Step 4: Verify RED**

Run focused `PaymentReportTest`; expected missing-method/behavior failures.

- [ ] **Step 5: Implement minimal no-receipt report transaction**

Validate canonical inputs first, then `BEGIN -> insert pending payment -> insert proposed allocations -> COMMIT`. Keep payment/charge/payment-method source records unchanged. No balance mutation/status write.

- [ ] **Step 6: Implement tenant/payment read projections**

`listPaymentsForLease()` must return only that lease, with derived payment state `pending_reconciliation|reconciled|rejected`, sanitized snapshot decode and proposed/final allocations. `findPayment()` is staff/internal read; tenant routes later must still filter by owned lease before lateral lookup.

- [ ] **Step 7: Verify GREEN**

Run PaymentReportTest plus F9 charge/manual/monthly/void suites and F10 payment-method service scope tests. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit: `feat(f11): report tenant payments with allocations`.

---

### Task 3: Add optional private receipt storage and integrity

**Files:**
- Modify: `app/Finance/FinanceService.php`
- Modify: `tests/Integration/Finance/PaymentReportTest.php`
- Modify: `bootstrap/app.php` only if receipt-directory wiring is required by final service construction.

**Interfaces:**
- Completes `reportPayment(..., receipt, ...)` for optional receipt.
- Produces `privatePaymentReceipt(int $paymentId): ?array{path:string,mime:string,size_bytes:int}` or an equivalent exact final name frozen in preflight; do not expose stored filename.

- [ ] **Step 1: Write receipt upload RED tests**

Accept real PNG/JPEG/WebP/PDF <=5 MiB by bytes; reject spoofed MIME/extension, unsupported formats, empty and oversize. Reporting without receipt remains valid.

- [ ] **Step 2: Write metadata/integrity RED tests**

Persist random stored name, real MIME, SHA256 and size. Private read verifies safe path, file existence, actual MIME, stored MIME, size and hash. Tampered/missing/mismatch returns null/404 boundary.

- [ ] **Step 3: Write failure-cleanup RED tests**

If receipt bytes are moved and payment/allocation DB transaction fails, remove the new file and leave no partial payment/allocation rows. Tenant/staff cannot replace/delete receipt in F11.

- [ ] **Step 4: Verify RED**

Run receipt-focused PaymentReportTest cases.

- [ ] **Step 5: Implement minimal receipt lifecycle inside existing FinanceService authority**

Reuse established private-file pattern and helpers where sensible; do not create media/receipt service or filesystem transaction framework.

- [ ] **Step 6: Verify GREEN**

Run full PaymentReportTest + existing F10 QR/private-file security regressions. Expected PASS.

- [ ] **Step 7: Commit + checkpoint**

Commit: `feat(f11): store private payment receipts safely`.

---

### Task 4: Add probable-duplicate warnings without persistence or blocking

**Files:**
- Modify: `app/Finance/FinanceService.php`
- Modify: `tests/Integration/Finance/PaymentReportTest.php`

**Interfaces:**
- `reportPayment()` return gains/retains derived `duplicate_warnings` list.
- Staff payment projection/detail exposes probable candidates without adding persisted flags/scores.

- [ ] **Step 1: Write receipt-hash duplicate RED tests**

Same lease + identical receipt SHA produces a high-signal warning against prior payment/report, including rejected historical reports; new report still persists.

- [ ] **Step 2: Write data-match duplicate RED tests**

Same lease + amount + payment method + paid_on yields warning; equal/near paid_time may strengthen the label when both times exist; missing time still permits weaker warning. Different lease must never leak as candidate.

- [ ] **Step 3: Write non-blocking RED test**

Probable duplicate warning never rejects a structurally valid report and never creates `is_duplicate|duplicate_of_id|duplicate_score` persistence.

- [ ] **Step 4: Verify RED**

Run duplicate-focused PaymentReportTest cases.

- [ ] **Step 5: Implement a private derived detector/query**

Keep rules in FinanceService as derived projection. Do not create a duplicate service/table/score engine.

- [ ] **Step 6: Verify GREEN**

Run full PaymentReportTest. Expected PASS.

- [ ] **Step 7: Commit + checkpoint**

Commit: `feat(f11): warn on probable duplicate payment reports`.

---

### Task 5: Add reconciliation, rejection, partial balances and concurrency authority

**Files:**
- Modify: `app/Finance/FinanceService.php`
- Create/Modify: `tests/Integration/Finance/PaymentReconciliationTest.php`
- Modify: existing F9 `FinanceServiceTest.php` or equivalent to freeze post-F11 charge/balance projections.

**Interfaces:**
- Produces `reconcilePayment(int $paymentId, array $finalAllocations, int $actorUserId, int $now): array`.
- Produces `rejectPayment(int $paymentId, string $reason, int $actorUserId, int $now): array`.
- Extends existing `listForLease()`, `summaryForLease()` and `portfolio()` semantics so only reconciled allocations reduce outstanding.
- Charge projection primary state priority: `voided -> paid -> partial -> proof_received -> reported -> overdue -> pending`, plus independent `is_overdue`, `outstanding`, `days_overdue`.

- [ ] **Step 1: Write reconcile/reject terminal RED tests**

First reconcile works; first reject works; second reconcile/reject or opposite terminal action fails closed and preserves original actor/timestamp/reason. Rejected payment retains allocations/receipt and never reduces balance.

- [ ] **Step 2: Write final-allocation RED tests**

Staff may replace proposed allocations only while pending; final set must use same lease, non-voided charges, positive values, each <= current outstanding and exact sum = payment amount. Payment amount/date/time/method snapshot/receipt are immutable during reconciliation.

- [ ] **Step 3: Write partial/balance/status RED tests**

Pending/rejected payment leaves F9 balance unchanged; reconciled payment reduces exactly once; partial allocation yields `partial` + remaining outstanding; full allocation yields `paid`; pending with receipt yields `proof_received`; pending without receipt yields `reported`; overdue remains independent when partial and due date passed.

- [ ] **Step 4: Write concurrency RED tests**

Pin transaction order: `payment FOR UPDATE` then affected charge rows locked in deterministic `charge_id` order before outstanding recomputation. Add strongest two-PDO integration test supported by existing MariaDB harness so simultaneous reconciliations cannot over-allocate the same charge and reconcile-vs-reject has one terminal winner.

- [ ] **Step 5: Verify RED**

Run PaymentReconciliationTest + affected F9 projection tests.

- [ ] **Step 6: Implement minimal reconciliation transaction**

Exact conceptual order from SPEC; replace pending allocation set atomically only after locks/current outstanding validation; set terminal actor/time last; rollback on any failure. Do not revalidate F10 method active state during reconciliation.

- [ ] **Step 7: Implement minimal rejection transaction**

`BEGIN -> payment FOR UPDATE -> require pending -> validate reason -> terminal rejection fields -> COMMIT`.

- [ ] **Step 8: Converge F9 balance/state queries**

One shared calculation semantics for tenant/admin/portfolio. Do not add cached balances or duplicate SQL formulas in views/routes.

- [ ] **Step 9: Verify GREEN**

Run reconciliation suite + full F9 FinanceService tests + F10 payment-method tests. Expected PASS.

- [ ] **Step 10: Commit + checkpoint**

Commit: `feat(f11): reconcile payments against charge balances`.

### Midpoint QA

Run `composer validate --strict` + full PHPUnit after Task 5 because the core financial semantics changed. Record exact counts; repair root cause before routes/UI.

---

### Task 6: Expose private staff reconciliation routes and queue

**Files:**
- Modify preferred: `app/Finance/routes.php`
- Modify: `resources/views/finance_index.php`
- Create one `resources/views/payment_detail.php` only if final finance view cannot clearly host one-payment review without becoming tangled.
- Create/Modify: `tests/Integration/Finance/PaymentRoutesTest.php`

**Interfaces:**
- Existing `GET /admin/finance` shows pending reconciliation queue.
- Routes exactly: `GET /admin/finance/payments/{id}`, `GET /admin/finance/payments/{id}/receipt`, `POST /admin/finance/payments/{id}/reconcile`, `POST /admin/finance/payments/{id}/reject`.
- `finance.view` reads; `finance.manage` mutates; owner bypass remains existing.

- [ ] **Step 1: Write admin auth/permission/CSRF RED tests**

Guest -> login; forced-password -> account password; viewer reads but cannot reconcile/reject; manager can; owner can; tenant gets 403 even with polluted permissions; POSTs require global CSRF; basePath/PRG 303 remains correct.

- [ ] **Step 2: Write queue/detail RED tests**

Queue exposes pending payment tenant/property/unit, amount, paid date/time, sanitized method label, receipt yes/no, reported_at and duplicate warning. Detail shows allocations/outstanding and reconcile/reject forms only when manageable. Escape all tenant/snapshot/reason text.

- [ ] **Step 3: Write staff receipt RED tests**

Authorized viewer can receive intact private receipt with actual MIME, Content-Length, `private, no-store`, `nosniff`; tampered/missing -> 404; no physical filename leak.

- [ ] **Step 4: Verify RED**

Run PaymentRoutesTest admin cases.

- [ ] **Step 5: Implement routes/view minimally in existing finance surface**

Prefer zero new route files. No separate Pagos app/dashboard. Reuse one FinanceService instance and existing auth/CSRF/basePath helpers.

- [ ] **Step 6: Verify GREEN**

Run PaymentRoutesTest admin cases + F9 FinanceRoutes + ShellRoutes. Expected PASS.

- [ ] **Step 7: Commit + checkpoint**

Commit: `feat(f11): expose private payment reconciliation workflow`.

---

### Task 7: Add tenant payment reporting, own history and receipt access

**Files:**
- Modify: `app/Leases/routes.php`
- Modify: `resources/views/tenant_lease_detail.php`
- Modify: `tests/Integration/Finance/PaymentRoutesTest.php`
- Modify F10 tenant payment-method/security tests only where legitimate F11 composition changes expected output.

**Interfaces:**
- Route `POST /tenant/leases/{id}/payments`.
- Route `GET /tenant/leases/{leaseId}/payments/{paymentId}/receipt`.
- Existing `GET /tenant/leases/{id}` composes F9 charges, F10 applicable methods and F11 own payment history.

- [ ] **Step 1: Write tenant ownership RED tests**

Owned active or ended lease with outstanding can report. Foreign/nonexistent lease returns 404 before payment/method/receipt lookup. Tenant cannot report against another lease's charge or another property's method. Tenant cannot reconcile/reject.

- [ ] **Step 2: Write report-form RED tests**

Form shows current outstanding charges + applicable active F10 methods, amount/date/time, allocation inputs and optional receipt. POST validates CSRF and returns PRG/detail without duplicate submission semantics. No property ID from browser is authority.

- [ ] **Step 3: Write tenant history/state RED tests**

Own payment history shows amount/date/method snapshot and derived `pending_reconciliation|reconciled|rejected`, receipt link only when present, operational rejection reason when present, no staff actor/internal metadata. Pending report does not falsely show balance reduced.

- [ ] **Step 4: Write owned receipt RED tests**

Owned payment receipt can be served privately; payment from another lease/user -> 404 before receipt lookup; rejected/reconciled/pending own receipt remains accessible; tampered -> 404.

- [ ] **Step 5: Verify RED**

Run tenant cases in PaymentRoutesTest + TenantPortalRoutes.

- [ ] **Step 6: Implement minimal lease-route composition**

Mandatory order: tenant guard -> `LeaseService::findForUser` -> 404 foreign -> F10/F11 lookups/actions -> render. Reuse existing tenant detail; no new tenant layout/app.

- [ ] **Step 7: Verify GREEN**

Run tenant payment routes + F8 tenant portal + F10 tenant payment-method routes/security. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit: `feat(f11): let tenants report and track payments`.

---

### Task 8: Freeze architecture/security, run full QA, review diff and close F11 without merging

**Files:**
- Create/Modify: `tests/Integration/Finance/PaymentSecurityRegressionTest.php`
- Modify: `README.md` only after product gate.
- Modify: `docs/specs/F11_PAYMENT_REPORT_RECONCILIATION_SPEC.md` only after product gate.
- Create closure: `docs/checkpoints/F11_CLOSED_2026-10-13.md`.
- Maintain one rolling execution checkpoint during implementation; converge/remove it at closure if final report absorbs all unique evidence.

**Interfaces:**
- No new product interface; this Task proves the frozen architecture and produces review-ready evidence.

- [ ] **Step 1: Write/finalize security architecture regression RED/GREEN as needed**

Freeze exactly two F11 tables, zero new domain classes, zero new permissions, zero public routes, no `payment_reports`, no persisted status/balance/duplicate flags, no bank/provider/webhook/cron code, receipt private/integrity guarded, ownership before tenant lateral lookup, F10 method deactivation after report does not invalidate reconciliation.

- [ ] **Step 2: Run focused final suite**

Run all F11 Finance tests plus F9 FinanceService/FinanceRoutes, F10 PaymentMethod service/routes/security, F8 tenant ownership, Auth/UserService, ShellRoutes and CSRF regressions. Expected 0 failures/0 errors; record exact counts.

- [ ] **Step 3: Run full local QA**

`composer validate --strict`

`vendor/bin/phpunit --testdox`

Record PHP version, tests, assertions, failures, errors, skipped.

- [ ] **Step 4: Run Two Laws + patch-smell audit against exact F10-integrated base**

Prove `+2 tables`, `+0 domain classes expected`, `+0 permissions`, `+0 public routes`, `+0 runtime deps`, `+0 media/status/balance/ledger/bank/webhook/cron tables/systems`. Search for duplicated outstanding formulas, raw payment writes outside FinanceService, stale compatibility helpers and F12+ placeholders. Classify findings `KEEP|REMOVE|CONVERGE|SIMPLIFY|IMPROVE|BLOCK` and repair only demonstrated issues.

- [ ] **Step 5: Define and verify PRODUCT_HEAD**

Review every product/test changed file. Push exact PRODUCT_HEAD only after focused/full QA + laws + patch-smell review PASS. Require CI PHP 8.3/8.4/8.5 PASS on that exact SHA and record run/counts.

- [ ] **Step 6: Closure documentation**

Set SPEC to `F11 CLOSED / VERIFIED / NOT MERGED`, update README, create `F11_CLOSED_2026-10-13.md` as the single detailed final report with Task evidence, schema/interfaces, concurrency repairs, receipt lifecycle, duplicate-warning semantics, KISS/Zero Noise/Patch audit, security, QA/CI and complete changed-file inventory.

- [ ] **Step 7: Converge rolling checkpoint**

If all unique evidence is in `F11_CLOSED`, remove rolling checkpoint; keep only if it still contains unique forensic evidence.

- [ ] **Step 8: Verify CLOSURE_HEAD**

Push exact closure HEAD and require fresh CI PHP 8.3/8.4/8.5 PASS with CI SHA exactly equal to closure HEAD.

- [ ] **Step 9: Open/update review-ready PR**

Head `impl/f11-payment-report-reconciliation-20261013` -> base `main`; title `F11: payment reporting and reconciliation`; include architecture budget, Product CI, Closure CI, security, KISS, Zero Noise, Patch Stacking and excluded scope. Body must say `MERGE NOT AUTHORIZED UNTIL NEXT EXPLICIT CONTINUA`.

- [ ] **Step 10: STOP**

Expected handoff: `F11 CLOSED / VERIFIED / REVIEW-READY / NOT MERGED`; next action `CHATGPT/SOL FINAL FORENSIC AUDIT`. Do not merge and do not start F12.

---

## Plan Self-Review — 10 passes

1. **Spec coverage:** report, allocations, snapshot, receipt, duplicates, reconciliation, rejection, balances, routes, privacy and concurrency each map to a Task — PASS.
2. **KISS:** two tables, zero new domain classes, existing FinanceService/routes/permissions reused — PASS.
3. **Cero Ruido:** no `payment_reports`, status/balance/history/media/credits/provider tables; no F12+ placeholders — PASS.
4. **Antipatches:** root-cause/owning-authority model retained; Task 5 converges F9 balance semantics instead of layering a second calculator — PASS.
5. **Money/date consistency:** decimal strings, exact allocation sum, reconciled-only outstanding and `paid_on <= today` are pinned before UI — PASS.
6. **Concurrency:** payment lock + deterministic charge locks, double terminal actions and cross-payment over-allocation are explicit RED cases — PASS.
7. **Security/privacy:** ownership-first, CSRF, staff permissions, escaped snapshot/reason, private receipt integrity and no filename leak are pinned — PASS.
8. **F10 lifecycle:** active/applicable at report time, historical snapshot, later method deactivation tolerated — PASS.
9. **Efficiency:** Tasks are reviewable units, midpoint full QA occurs only after core financial semantics change, CI is reserved for product/closure gates — PASS.
10. **Closure discipline:** exact Product HEAD + Closure HEAD CI, one final Markdown report, review-ready PR and hard stop before merge/F12 — PASS.

## Execution Gate

This PLAN is documentation only and remains blocked on F10 final audit + merge.

After user review/approval, the next artifact is the optimized Codex Luna master execution prompt for F11. Product implementation must still wait for F10 integrated `main`.