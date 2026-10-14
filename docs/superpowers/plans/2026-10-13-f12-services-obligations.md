# F12 Services + Obligations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add one operational authority for utility, PH administration and PH fine obligations, with private evidence and explicit/atomic linkage to the existing financial engine without creating a second balance, payment flow or document system.

**Architecture:** F12 adds exactly one table (`lease_obligations`) and one domain class (`ObligationService`). `ObligationService` owns the external obligation lifecycle and private supports; `FinanceService` remains the only writer/authority for financial charges and F9/F11 remain the only balance/payment authority. PH administration links an existing monthly `administration` charge instead of creating a duplicate.

**Tech Stack:** PHP >=8.3 <8.6, Slim 4, MariaDB/MySQL, PDO, SSR HTML, existing CSS/JS, PHPUnit 11.x, existing private-file patterns, no new runtime dependency.

**Spec:** `docs/specs/F12_SERVICES_OBLIGATIONS_SPEC.md` — approved by the user on 2026-10-13. Product implementation remains blocked until F11 is finally audited and merged into `main`.

## Global Constraints

- KISS: exactly one F12 table and one F12 domain class; zero new permissions and zero public routes.
- Cero Ruido: `ELIMINAR -> UNIR/CONVERGER -> SIMPLIFICAR -> MEJORAR -> AGREGAR` before adding any piece.
- Anti-patch: reproduce -> root cause -> owning authority -> converge/simplify -> RED -> minimal GREEN -> remove superseded logic -> regression.
- Reuse F9/F11 `FinanceService`, `finance.view/manage`, F8 `LeaseService` ownership and existing finance/tenant surfaces.
- `lease_obligations` does not persist status, balance, paid amount, reminder state or tenant payment references.
- `settled_at` and `charge_id` are independent dimensions and may legitimately coexist.
- Utility and PH fine may explicitly generate one F9 manual charge; PH administration only links one existing monthly F9 `administration` charge.
- F12 never calculates tenant balance and never creates/reconciles payments.
- Source and settlement supports are single-file private evidence fields on the obligation row; no media/document table.
- Tenant F12 V1 is read-only; source support may be viewed for owned, non-voided obligations, settlement support remains staff-only.
- No cron, reminders, email, WhatsApp, OCR, provider APIs, F13/F14/F16/F18 behavior, interest, proration, accounting or e-invoicing.
- No F12 merge without a later explicit merge gate.

## Review Focus

1. **Atomic charge generation:** failure after `FinanceService::createManualCharge()` must roll back both the new charge and F12 link; no orphan charge may remain.
2. **Administration debt duplication:** `administration_ph` must never create a manual administration charge and must only link same-lease, same-period, non-voided F9 `administration` charge.
3. **Independent dimensions:** an external obligation can be settled while its linked tenant charge remains outstanding; neither dimension may overwrite the other.
4. **Concurrency:** generate-vs-generate, generate-vs-void, settle-vs-settle and concurrent administration links must serialize on the obligation row and never create duplicate links/terminal actors.
5. **Private-file lifecycle:** source-support replacement while mutable must clean failed new files and remove superseded files only after DB success; terminal obligations must not allow evidence rewriting.

---

## Planned File Map

### Create

- `database/migrations/012_create_lease_obligations.sql` — creates only `lease_obligations`.
- `app/Finance/ObligationService.php` — sole F12 domain authority.
- `tests/Integration/Finance/ObligationSchemaTest.php` — exact schema/noise freeze.
- `tests/Integration/Finance/ObligationServiceTest.php` — create/update/projections/lifecycle/supports.
- `tests/Integration/Finance/ObligationFinancialIntegrationTest.php` — charge generation/linking/atomicity/concurrency.
- `tests/Integration/Finance/ObligationRoutesTest.php` — staff + tenant routes, permissions, ownership, CSRF, supports.
- `tests/Integration/Finance/ObligationSecurityRegressionTest.php` — architecture/privacy/no-future guardrails.

### Modify after execution preflight confirms F11 final paths

- `app/Finance/routes.php` — preferred home for staff obligations actions/support reads.
- `app/Leases/routes.php` — tenant-owned source-support route and lease-detail composition.
- `resources/views/finance_index.php` or one focused `resources/views/obligations.php` if clearer after final F11 UI inspection.
- `resources/views/tenant_lease_detail.php` — owned obligation read-only section.
- `bootstrap/app.php` — wire one `ObligationService` and private support directory only if required.
- F9/F11 finance tests only where legitimate composition or transaction behavior evolves.
- closure-only: README, F12 SPEC status, `docs/checkpoints/F12_CLOSED_2026-10-13.md`.

### Not planned

- no `utility_periods`, `utility_bills`, `ph_fines`, `obligation_documents`, `obligation_payments` or status/history tables;
- no new financial writer;
- no new permissions;
- no public routes;
- no Composer dependency change;
- no F13/F14/F16/F18 product surface.

---

## Execution Preflight — mandatory before Task 1 product work

Implementation MUST NOT start from this docs branch.

- [ ] Verify F11 is `CLOSED / VERIFIED`, ChatGPT/Sol final audit is PASS and F11 is merged into `main`.
- [ ] Record exact F11 merge SHA and current `main` SHA; use that exact integrated SHA as F12 implementation base.
- [ ] Re-read F12 SPEC/PLAN, F11 merged checkpoint, actual `FinanceService`, finance/lease routes/views, `LeaseService`, permissions, private storage patterns and concurrency/security tests.
- [ ] Confirm `finance.view/manage` still exist exactly and tenant remains fail-closed for staff permissions.
- [ ] Confirm actual `FinanceService::createManualCharge(...)` remains the canonical writer for `service|fine` and can participate safely in an outer PDO transaction without committing/rolling back it internally. If final F11 changed that contract materially, STOP and reopen F12 integration design rather than duplicating a raw `charges` INSERT.
- [ ] Confirm final F11 charge projection exposes enough canonical information to determine whether a linked administration charge is voided without F12 inventing a second financial state.
- [ ] Confirm a private support directory can be injected/reused with minimal wiring.
- [ ] Create `impl/f12-services-obligations-20261013` from exact F11-integrated `main`.
- [ ] Bring only approved F12 SPEC/PLAN docs onto the implementation branch.
- [ ] Update F12 SPEC gate to `APPROVED / IMPLEMENTATION IN PROGRESS`, record `F11_INTEGRATED_BASE_SHA`, keep merge unauthorized and commit docs-only.
- [ ] Create one rolling checkpoint `docs/checkpoints/F12_LUNA_EXECUTION_CHECKPOINT_2026-10-13.md`.

Path/name convergence to final F11 reality is allowed. A change to one-table/one-service/finance-authority/permission model requires reopening SPEC.

---

### Task 1: Freeze the single-table obligation schema

**Files:**
- Create: `database/migrations/012_create_lease_obligations.sql`
- Create: `tests/Integration/Finance/ObligationSchemaTest.php`
- Modify only if a historical schema-count guardrail legitimately needs phase-aware convergence: the owning regression test.

**Interfaces:**
- Consumes: `leases.id`, `charges.id`, `users.id`.
- Produces exact `lease_obligations` persistence required by later Tasks.

- [ ] **Step 1: Write schema RED**

Pin exact columns from SPEC: identity/lease/type/service/description/period/dates/amount/external reference; four source-support metadata fields; four settlement-support metadata fields; nullable unique `charge_id`; creator/update fields; settled actor/time; void actor/time/reason.

Assert FK/delete rules, unique `charge_id`, only frozen query indexes `(lease_id,due_on)` and `(lease_id,period_month,type)` plus any FK indexes the engine truly requires, and absence of `status|balance|paid_amount|tenant_payment_id|provider_id|reminder_sent_at|notification_status`.

Assert absence of `utility_periods|utility_bills|ph_fines|obligation_documents|obligation_payments|obligation_status_history`.

- [ ] **Step 2: Verify RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Finance/ObligationSchemaTest.php`

Expected: FAIL only because migration/table is absent; F9/F10/F11 schema suites remain green.

- [ ] **Step 3: Implement migration 012 only**

Create exactly `lease_obligations`; use `DECIMAL(12,2)`, nullable dates/metadata and actor/time pairs exactly as SPEC. Do not add decorative indexes or enums.

- [ ] **Step 4: Verify GREEN**

Run F12 schema plus F11 payment, F10 payment-method, F9 charge and F8 lease schema suites. Expected PASS.

- [ ] **Step 5: Commit + checkpoint**

Commit: `feat(f12): add lease obligations schema`.

Checkpoint exact +1 table, zero parallel authorities, Two Laws PASS.

---

### Task 2: Add core obligation authority, validation, mutability and projections

**Files:**
- Create: `app/Finance/ObligationService.php`
- Create/Modify: `tests/Integration/Finance/ObligationServiceTest.php`

**Interfaces:**
- Constructor target: `ObligationService(PDO $pdo, FinanceService $financeService, string $supportDirectory)` unless final F11 wiring proves a simpler equivalent; no repository/DTO layer.
- Produce `create(int $leaseId, array $input, ?UploadedFileInterface $sourceSupport, int $actorUserId, int $now): array`.
- Produce `update(int $obligationId, array $input, ?UploadedFileInterface $newSourceSupport, bool $removeSourceSupport, int $now): array`.
- Produce `find(int $obligationId, string $today): ?array`.
- Produce `listForLease(int $leaseId, string $today): array`.
- Produce `listAll(string $today): array` or an equivalent final admin projection name frozen in preflight.

- [ ] **Step 1: Write create-validation RED tests**

Pin exact types `utility|administration_ph|fine_ph`, required/null fields by type, decimal-string amount >0/max 2 decimals/range, `period_month` shape, real dates and `issued_on <= due_on`, active or ended lease accepted, unsupported type/service-specific type rejected.

- [ ] **Step 2: Write projection RED tests**

Pin derived external state `voided|settled_external|overdue_external|open`, independent `is_charged` and `external_overdue`, deterministic list ordering, normalized money and no financial balance calculation in F12.

- [ ] **Step 3: Write mutability RED tests**

Update is allowed only while `voided_at NULL AND charge_id NULL AND settled_at NULL`; after charge/settle/void, core fields fail closed. No DELETE operational path.

- [ ] **Step 4: Verify RED**

Run focused `ObligationServiceTest`; expected missing-class/method failures.

- [ ] **Step 5: Implement minimal core authority without file persistence yet**

Keep validations/projections in `ObligationService`; use one INSERT/UPDATE authority and no financial writes. Do not create status/balance helpers outside this class.

- [ ] **Step 6: Verify GREEN**

Run `ObligationServiceTest` core cases + relevant lease lifecycle tests. Expected PASS.

- [ ] **Step 7: Commit + checkpoint**

Commit: `feat(f12): add lease obligation authority`.

---

### Task 3: Add private source and settlement support lifecycle

**Files:**
- Modify: `app/Finance/ObligationService.php`
- Modify: `tests/Integration/Finance/ObligationServiceTest.php`
- Modify: `bootstrap/app.php` only if directory wiring is needed.

**Interfaces:**
- Complete `create/update` source-support handling.
- Produce `settleExternal(int $obligationId, int $actorUserId, ?UploadedFileInterface $settlementSupport, int $now): array`.
- Produce `privateSourceSupport(int $obligationId): ?array{path:string,mime:string,size_bytes:int}`.
- Produce `privateSettlementSupport(int $obligationId): ?array{path:string,mime:string,size_bytes:int}`.

- [ ] **Step 1: Write source-support RED tests**

Accept real PNG/JPEG/WebP/PDF <=5 MiB by bytes; reject spoof/unsupported/empty/oversize. Persist random stored name, actual MIME, SHA256 and exact size; read validates safe path/MIME/size/hash and fails closed on tamper/missing.

- [ ] **Step 2: Write mutable replacement/removal RED tests**

While fully mutable, replacement order must preserve old evidence if DB update fails and delete old file only after successful DB metadata update. `removeSourceSupport=true` is allowed only while mutable. After charge/settle/void, source support cannot be replaced/removed.

- [ ] **Step 3: Write settlement-support + settle RED tests**

First `settleExternal` works with or without support, locks obligation, requires non-voided/unsettled, writes actor/time once and permits existing `charge_id`. Second settle fails closed preserving actor/time/evidence. Settlement support cannot later be replaced/deleted.

- [ ] **Step 4: Write failure-cleanup RED tests**

If a new file is stored but DB operation fails, remove only the new file and preserve prior DB/file authority. No filesystem transaction framework.

- [ ] **Step 5: Verify RED**

Run support/settlement-focused `ObligationServiceTest` cases.

- [ ] **Step 6: Implement minimal private-file helpers inside ObligationService**

Reuse existing project patterns, not a new document/media service.

- [ ] **Step 7: Verify GREEN**

Run full `ObligationServiceTest` plus F10/F11 private-file regressions. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit: `feat(f12): store private obligation supports safely`.

---

### Task 4: Integrate obligations with the canonical financial engine atomically

**Files:**
- Modify: `app/Finance/ObligationService.php`
- Create: `tests/Integration/Finance/ObligationFinancialIntegrationTest.php`
- Modify existing `FinanceService` tests only if outer-transaction participation requires a legitimate regression test; do not change its financial contract gratuitously.

**Interfaces:**
- Produce `generateCharge(int $obligationId, string $chargeDueDate, int $actorUserId): array` for `utility|fine_ph` only.
- Produce `linkAdministrationCharge(int $obligationId, int $chargeId): array` for `administration_ph` only.
- Mapping: `utility -> service`; `fine_ph -> fine`; `administration_ph -> existing administration charge only`.

- [ ] **Step 1: Write generate-charge RED tests**

Pin utility/fine mappings, exact obligation amount, sanitized visible description, optional `period_month`, mandatory explicit financial `chargeDueDate`, no automatic copy of external `due_on`, one charge maximum, no generation for `administration_ph`, and no generation from voided obligation.

- [ ] **Step 2: Write atomicity RED test**

Force failure after `FinanceService::createManualCharge()` but before/while linking `charge_id`; assert outer transaction rolls back both the charge and link. Prove `ObligationService` never performs a raw `INSERT INTO charges` as a second financial writer.

- [ ] **Step 3: Write administration-link RED tests**

Require obligation `administration_ph`, not voided/unlinked; charge exists, same lease, type `administration`, non-voided and same `period_month`; amount mismatch is allowed/informational; unique charge cannot link to another F12 obligation.

- [ ] **Step 4: Write concurrency RED tests**

Use strongest two-PDO MariaDB harness available: concurrent `generateCharge` on same obligation yields one charge/link; `generateCharge` vs `void` cannot leave a voided obligation with newly active charge; concurrent administration links cannot double-link the same charge. Primary serialization starts with obligation `FOR UPDATE`; link path locks/reads canonical charge coherently.

- [ ] **Step 5: Verify RED**

Run `ObligationFinancialIntegrationTest`; expected missing behavior/concurrency failures only.

- [ ] **Step 6: Implement generation using one outer PDO transaction**

Exact ownership order: `BEGIN -> obligation FOR UPDATE -> revalidate -> FinanceService::createManualCharge(...) on same PDO -> set charge_id -> COMMIT`. Do not duplicate F9 money/type validation or charge INSERT. Roll back all on any failure.

- [ ] **Step 7: Implement administration linking**

Lock obligation first, validate canonical charge from F9/F11 authority, then persist only `charge_id`. Rely on `UNIQUE(charge_id)` as relational final barrier; do not copy charge amount/status into F12.

- [ ] **Step 8: Verify GREEN**

Run financial integration + F9/F11 FinanceService suites. Expected PASS with zero orphan/double charges.

- [ ] **Step 9: Commit + checkpoint**

Commit: `feat(f12): link obligations to canonical charges atomically`.

---

### Task 5: Freeze void lifecycle and cross-authority consistency

**Files:**
- Modify: `app/Finance/ObligationService.php`
- Modify: `tests/Integration/Finance/ObligationServiceTest.php`
- Modify: `tests/Integration/Finance/ObligationFinancialIntegrationTest.php`

**Interfaces:**
- Produce `void(int $obligationId, int $actorUserId, string $reason, int $now): array`.
- Reads linked charge state from canonical F9/F11 financial authority; does not write/void charges automatically.

- [ ] **Step 1: Write void RED tests**

First void stores actor/time/reason once and retains supports. Second void fails closed. If linked charge remains active/non-voided, obligation void must fail. If linked charge was already voided canonically, obligation may be voided. No cascade or automatic replacement charge.

- [ ] **Step 2: Write independent-dimension RED tests**

`settled_at` and active `charge_id` may coexist; settling never mutates/concilies charge; charge voiding does not automatically settle/void obligation; obligation projections show external state independently from linked charge financial projection.

- [ ] **Step 3: Verify RED**

Run focused lifecycle/financial tests.

- [ ] **Step 4: Implement minimal void transaction**

`BEGIN -> obligation FOR UPDATE -> require nonvoided -> inspect linked charge if any -> require charge already voided or absent -> reason -> actor/time -> COMMIT`.

- [ ] **Step 5: Verify GREEN**

Run full service + financial integration suites. Expected PASS.

- [ ] **Step 6: Commit + checkpoint**

Commit: `feat(f12): preserve obligation lifecycle integrity`.

### Midpoint QA

After Task 5 run `composer validate --strict` and full PHPUnit because filesystem + financial transaction + lifecycle semantics are now complete. Record exact counts; repair root cause before routes/UI.

---

### Task 6: Expose staff obligations workflow inside existing finance surface

**Files:**
- Modify preferred: `app/Finance/routes.php`
- Modify: `resources/views/finance_index.php` OR create one focused `resources/views/obligations.php` if clearer after F11 final UI inspection.
- Create: `tests/Integration/Finance/ObligationRoutesTest.php`

**Interfaces:**
- Staff routes conceptually: `GET /admin/finance/obligations`; create/update/settle/generate-charge/link-administration/void POSTs; private source/settlement support GETs.
- `finance.view` reads; `finance.manage` mutates; owner bypass remains existing.

- [ ] **Step 1: Write admin auth/permission/CSRF RED tests**

Guest redirects to login; forced password gate applies; viewer reads but cannot mutate; manager/owner mutate; tenant denied even with polluted staff permissions; all POSTs use global CSRF; basePath and PRG 303 remain correct.

- [ ] **Step 2: Write admin list/form RED tests**

List/detail shows lease/tenant/property/unit, type/service, external amount/dates, derived external state, `is_charged`, linked canonical charge state, source/settlement support presence and staff-only `external_reference`. Forms/actions appear only when lifecycle permits. Escape all content.

- [ ] **Step 3: Write private support route RED tests**

Authorized staff viewer can read intact source and settlement supports with actual MIME, Content-Length, `private, no-store`, `nosniff`; tamper/missing -> 404; no stored filename/path leak.

- [ ] **Step 4: Verify RED**

Run staff cases in `ObligationRoutesTest`.

- [ ] **Step 5: Implement minimal routes/view in existing finance surface**

Prefer zero new route files; no second “Servicios” app/dashboard. Reuse one ObligationService instance and existing auth/CSRF/basePath helpers.

- [ ] **Step 6: Verify GREEN**

Run F12 admin routes + F9/F11 FinanceRoutes + ShellRoutes. Expected PASS.

- [ ] **Step 7: Commit + checkpoint**

Commit: `feat(f12): expose private obligations workflow`.

---

### Task 7: Add tenant read-only obligation context and owned source-support access

**Files:**
- Modify: `app/Leases/routes.php`
- Modify: `resources/views/tenant_lease_detail.php`
- Modify: `tests/Integration/Finance/ObligationRoutesTest.php`
- Modify existing tenant security tests only for legitimate F12 composition.

**Interfaces:**
- Existing `GET /tenant/leases/{id}` composes F12 obligations read-only.
- Route: `GET /tenant/leases/{leaseId}/obligations/{obligationId}/source-support`.
- No tenant F12 POST routes in V1.

- [ ] **Step 1: Write tenant ownership RED tests**

Owned active or ended lease shows its obligations; foreign/nonexistent lease returns 404 before obligation/support lookup. Tenant never accesses another lease obligation or support.

- [ ] **Step 2: Write tenant projection RED tests**

Show type/service, visible description, external amount/period/dates, derived external state, and linked canonical financial charge state when any. Do not expose `external_reference`, settlement support, actor IDs or internal metadata. F12 does not calculate/display a second financial balance.

- [ ] **Step 3: Write source-support RED tests**

Owned, non-voided obligation source support is private-viewable; voided obligation, foreign lease, tampered/missing support -> 404. Settlement support has no tenant route/access.

- [ ] **Step 4: Write no-mutation RED test**

Assert tenant has no create/edit/settle/generate/link/void/upload F12 POST surface.

- [ ] **Step 5: Verify RED**

Run tenant F12 cases + F8/F11 tenant portal ownership regressions.

- [ ] **Step 6: Implement minimal ownership-first composition**

Mandatory order: tenant guard -> `LeaseService::findForUser` -> 404 foreign -> ObligationService lookup/read -> render/serve. Reuse existing tenant detail; no new tenant app/layout.

- [ ] **Step 7: Verify GREEN**

Run F12 tenant + full tenant portal/security suites. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit: `feat(f12): show owned obligations in tenant portal`.

---

### Task 8: Freeze architecture/security, run full QA and close F12 without merging

**Files:**
- Create/Modify: `tests/Integration/Finance/ObligationSecurityRegressionTest.php`
- Modify closure-only: `README.md`, F12 SPEC status.
- Create closure: `docs/checkpoints/F12_CLOSED_2026-10-13.md`.
- Maintain one rolling execution checkpoint during implementation; converge/remove it at closure if the final report absorbs all unique evidence.

**Interfaces:**
- No new product API; this Task proves architecture/security and produces review-ready evidence.

- [ ] **Step 1: Freeze security/architecture invariants**

Assert exactly one F12 table, one `ObligationService`, zero new permissions/public routes/runtime deps/document/payment/balance/status-history tables, no provider/OCR/cron/notification code, source/settlement support private/integrity guarded, tenant ownership first, tenant read-only, and no manual administration-charge generation from F12.

- [ ] **Step 2: Run focused final suite**

Run all F12 tests plus F9/F11 FinanceService/routes, F10 PaymentMethod, F8 tenant ownership, Auth/UserService, ShellRoutes and CSRF/private-file regressions. Expected 0 failures/0 errors; record exact counts.

- [ ] **Step 3: Run full local QA**

`composer validate --strict`

`vendor/bin/phpunit --testdox`

Record PHP version, tests, assertions, failures, errors, skipped.

- [ ] **Step 4: Run Two Laws + patch-smell audit against exact F11-integrated base**

Prove `+1 table`, `+1 domain class`, `+0 permissions`, `+0 public routes`, `+0 runtime deps`, `+0 document/payment/balance/status-history/provider/cron systems`. Search for duplicated financial calculations, raw `charges` INSERTs outside FinanceService, repeated support validation, F13/F14/F16/F18 placeholders and compatibility shims. Classify `KEEP|REMOVE|CONVERGE|SIMPLIFY|IMPROVE|BLOCK` and repair only demonstrated issues.

- [ ] **Step 5: Define and verify PRODUCT_HEAD**

Review every product/test changed file. Push exact PRODUCT_HEAD only after focused/full QA + laws + patch-smell review PASS. Require CI PHP 8.3/8.4/8.5 PASS on exact SHA and record run/counts.

- [ ] **Step 6: Closure documentation**

Set SPEC to `F12 CLOSED / VERIFIED / NOT MERGED`, update README and create `F12_CLOSED_2026-10-13.md` as the single detailed final report containing Task evidence, schema/interfaces, obligation/charge dimensional model, financial atomicity/concurrency repairs, support lifecycle, tenant visibility, KISS/Zero Noise/Patch audit, security, QA/CI and complete changed-file inventory.

- [ ] **Step 7: Converge rolling checkpoint**

Remove it if closure report contains all unique evidence; otherwise keep only genuinely unique forensic data.

- [ ] **Step 8: Verify CLOSURE_HEAD**

Push exact closure HEAD and require fresh CI PHP 8.3/8.4/8.5 PASS with CI SHA equal to closure HEAD.

- [ ] **Step 9: Open/update review-ready PR**

Head `impl/f12-services-obligations-20261013` -> base `main`; title `F12: services and obligations`; body includes architecture budget, Product CI, Closure CI, atomic charge linkage, support security, tenant read-only model, KISS, Zero Noise, Patch Stacking and exclusions. Body must state `MERGE NOT AUTHORIZED UNTIL NEXT EXPLICIT CONTINUA`.

- [ ] **Step 10: STOP**

Expected handoff: `F12 CLOSED / VERIFIED / REVIEW-READY / NOT MERGED`; next action `CHATGPT/SOL FINAL FORENSIC AUDIT`. Do not merge and do not start F13.

---

## Plan Self-Review — 10 passes

1. **Spec coverage:** utility/admin/fine types, dates/money, supports, settle, generate/link, void, staff/tenant UI, privacy and concurrency each map to an owning Task — PASS.
2. **KISS:** one table, one service, zero new permissions, existing finance/tenant surfaces reused — PASS.
3. **Cero Ruido:** no utility-period parallel authority, document table, second balance/payment process, provider/OCR/cron or future modules — PASS.
4. **Financial convergence:** utility/fine call canonical FinanceService; administration links existing monthly charge and never duplicates it — PASS.
5. **Atomicity/concurrency:** one outer PDO transaction for generated charge+link; obligation row is first lock; duplicate generation/link and void race are explicit tests — PASS.
6. **Independent dimensions:** external settlement and tenant financial charge may coexist without a fake single status or cross-mutation — PASS.
7. **Support lifecycle/security:** source/settlement evidence uses private MIME/hash/size storage, safe replacement/cleanup and frozen terminal evidence — PASS.
8. **Tenant isolation:** ownership-first, tenant read-only, source support only, settlement support/external reference staff-only — PASS.
9. **Efficiency:** reviewable Tasks, one midpoint full QA after core semantics, CI reserved for product/closure heads, no repeated full-repo scans mandated — PASS.
10. **Closure discipline:** exact Product/Closure CI, one final Markdown report, review-ready PR and hard stop before merge/F13 — PASS.

## Execution Gate

This PLAN is documentation only and remains blocked on F11 final audit + merge.

After user review/approval, the next artifact is the optimized Codex Luna master execution prompt for F12. Product implementation must still wait for F11-integrated `main`.