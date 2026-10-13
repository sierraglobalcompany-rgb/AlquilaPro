# F10 Payment Methods Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the single AlquilaPro authority for configuring and showing payment methods—bank account, Bre-B key, Bre-B QR, wallet and manual instructions—without creating payments, reconciliation, bank integrations, extra permissions or parallel financial architecture.

**Architecture:** F10 adds exactly one table (`payment_methods`) and one domain class (`PaymentMethodService`). It reuses F9 `finance.view/manage`, the existing finance admin surface, F8/F9 tenant ownership and the current private-storage pattern. Scope is represented by nullable `property_id`: `NULL` means global; a concrete property means property-specific. F10 configures how to pay; F11 will own the fact that a payment occurred.

**Tech Stack:** PHP >=8.3 <8.6, Slim 4, MariaDB/MySQL, PDO, SSR HTML, existing CSS/JS, PHPUnit 11.x, PSR-7 `UploadedFileInterface`, existing private filesystem pattern, no new runtime dependency.

**Spec:** `docs/specs/F10_PAYMENT_METHODS_SPEC.md` — approved by the user in chat on 2026-10-13. Product implementation remains blocked until F9 is finally audited and merged into `main`.

## Global Constraints

- KISS: one `payment_methods` table, one `PaymentMethodService`, zero new permissions, zero public routes.
- Cero Ruido order: `ELIMINAR -> UNIR / CONVERGER -> SIMPLIFICAR -> MEJORAR -> AGREGAR`.
- Anti-patch: reproduce -> root cause -> owning authority -> converge/simplify -> minimal GREEN -> remove superseded logic -> regression.
- F10 must reuse `finance.view` and `finance.manage`; if F9 final does not expose those exact permissions, STOP and reopen design instead of inventing substitutes.
- No `payments`, `payment_allocations`, `payment_reports`, provider adapters, webhooks, PSE, card processing, bank APIs, bank credentials, cron or scheduler.
- F10 routes are private only. Tenant visibility always follows F8 ownership before payment-method scope resolution.
- No custom encryption/vault framework; never store username, password, PIN, OTP, API secret, webhook secret, private key or bank access token.
- No delete in normal operation. Retirement is `is_active = 0`.
- Bre-B QR is uploaded from a legitimate external financial channel; AlquilaPro never generates an interoperable banking QR.
- QR files: PNG/JPEG/WebP only, <=2 MiB, MIME by bytes, random name, outside webroot, restrictive permissions, SHA256/size/MIME verified on read.
- Current official Bre-B key categories are frozen for this phase as `identity_document|mobile|email|alphanumeric|merchant_code`; this is a validation/display descriptor, not a bank credential. If the official scheme changes before implementation, update SPEC/PLAN explicitly rather than silently accepting arbitrary types.
- No new CSS/JS unless a reproduced UI defect proves it necessary.
- Every product task uses RED -> verify RED -> minimal GREEN -> focused GREEN -> checkpoint.
- No F10 merge without explicit later authorization.

## Review Focus

1. **Type changes must remove stale data:** changing `bank_account -> manual`, `breb_qr -> wallet`, etc. must clear fields/files that no longer apply; stale hidden banking data is forbidden.
2. **QR lifecycle must never produce an invalid active `breb_qr`:** creation requires a QR; removal while remaining `breb_qr` is rejected unless a replacement QR is supplied in the same update; changing to a non-QR type removes the superseded QR.
3. **QR replacement must not leave DB/file disagreement:** failed DB update cleans the new file; successful replacement switches metadata first and then removes the superseded file without reverting to a broken DB reference.
4. **Tenant scope isolation:** global methods plus methods for the owned lease property only; property ID never comes from tenant input authority.
5. **Inactive/foreign/tampered QR and F11 boundary:** unauthorized/tampered QR fails closed, and no F10 surface may imply that money was paid, reported, reconciled or allocated.

---

## Planned File Map

### Create

- `database/migrations/010_create_payment_methods.sql` — the only F10 persistence delta.
- `app/Finance/PaymentMethodService.php` — the only F10 domain authority and QR storage owner.
- `resources/views/payment_methods.php` — one admin list/form surface.
- `tests/Integration/Finance/PaymentMethodSchemaTest.php` — schema/noise freeze.
- `tests/Integration/Finance/PaymentMethodServiceTest.php` — validation, scope, lifecycle, QR integrity.
- `tests/Integration/Finance/PaymentMethodRoutesTest.php` — admin auth/permissions/CSRF/PRG/basePath.
- `tests/Integration/Finance/PaymentMethodSecurityRegressionTest.php` — architecture/privacy/F11 boundary.

### Modify after execution preflight confirms F9 final paths

- `bootstrap/app.php` — instantiate exactly one `PaymentMethodService` with a private QR directory and share it to finance/lease composition.
- `app/Finance/routes.php` — preferred home for the 3 admin F10 routes if F9 final still has a coherent finance route file.
- `app/Leases/routes.php` — tenant lease detail composition + private QR route after ownership.
- `resources/views/tenant_lease_detail.php` — `Cómo pagar` section.
- `resources/views/layouts/app.php` only if the existing F9 Cartera surface needs a minimal link to payment methods; do not add a second app card by default.
- relevant F8/F9 security tests only where legitimate new composition requires a phase-aware guardrail.
- closure-only: F10 SPEC status, README, `docs/checkpoints/F10_CLOSED_<date>.md`.

### Route-file decision

The approved default is **zero new route files in F10**: extend the existing F9 `app/Finance/routes.php` for admin routes and existing `app/Leases/routes.php` for the tenant QR route.

Only create `app/Finance/payment_methods_routes.php` if F9 final reality makes extending `app/Finance/routes.php` materially less clear. That deviation must be recorded in the execution preflight and still obey the SPEC budget of at most one F10 route file. Do not create a route file merely for symmetry.

### Not planned

- no `composer.json` or lock changes;
- no `payment_method_scopes` table;
- no QR/media table;
- no payment/report/allocation table;
- no bank/provider integration;
- no tenant auth/session/layout duplication.

---

## Execution Preflight — mandatory before Task 1 product work

Implementation MUST NOT start from this docs branch.

- [ ] Verify F9 is `CLOSED / VERIFIED`, ChatGPT/Sol final audit is PASS, and F9 is merged into `main`.
- [ ] Record exact F9 merge SHA and current `main` SHA; they must identify the accepted integrated base.
- [ ] Re-read F10 SPEC, this plan, F9 final checkpoint, actual `FinanceService`, finance routes/view, F8 lease ownership routes/views, `Permissions`, bootstrap, private-storage services and security tests.
- [ ] Confirm `finance.view` and `finance.manage` exist exactly and tenant still fails closed for staff permissions.
- [ ] Reconfirm the current official Bre-B key categories before implementation. If they differ from `identity_document|mobile|email|alphanumeric|merchant_code`, update the SPEC/PLAN documentation first; do not widen to arbitrary free text silently.
- [ ] Confirm F9 final admin finance surface and decide route-file placement using the rule above.
- [ ] Create `impl/f10-payment-methods-20261013` from the exact F9-integrated `main` SHA.
- [ ] Bring only approved F10 SPEC/PLAN docs onto the implementation branch if needed.
- [ ] Update F10 SPEC gate to `APPROVED / IMPLEMENTATION IN PROGRESS`, record the F9 base SHA, converge the explicit Bre-B key-type allowlist into the SPEC if not already present, and keep merge unauthorized.
- [ ] Commit only this state transition before Task 1.

If F9 final interfaces differ only in paths/names, converge references without changing design. If the difference changes the approved F10 authority or permission model, STOP and reopen SPEC.

---

### Task 1: Freeze the single payment-method table

**Files:**
- Create: `database/migrations/010_create_payment_methods.sql`
- Create: `tests/Integration/Finance/PaymentMethodSchemaTest.php`
- Modify only if an older table-count guardrail legitimately needs phase-aware convergence: the owning schema regression test.

**Interfaces:**
- Consumes: `properties.id`.
- Produces exact `payment_methods` schema from the SPEC.

- [ ] **Step 1: Write schema RED**

Assert exact columns/types/nullability/defaults, `property_id -> properties.id ON DELETE RESTRICT`, and exactly these indexes:

```text
payment_methods_property_active_order_index (property_id, is_active, sort_order)
payment_methods_active_order_index (is_active, sort_order)
```

Also assert F10 creates none of:

```text
payment_method_scopes
lease_payment_methods
property_payment_methods
bank_accounts
wallets
breb_keys
payment_providers
bank_tokens
payment_transactions
payment_intents
payments
payment_allocations
payment_reports
```

and contains no credential columns such as `password|pin|otp|api_key|secret|token|webhook_secret`.

- [ ] **Step 2: Verify RED**

Run `vendor/bin/phpunit --testdox tests/Integration/Finance/PaymentMethodSchemaTest.php`.

Expected: FAIL only because migration/table is absent; F9 schema tests remain green.

- [ ] **Step 3: Implement migration 010 only**

Use the SPEC columns exactly. `is_active` is TINYINT(1), `sort_order` unsigned integer >=1 at domain boundary, QR metadata lives on the same row. No enum/status/history/provider table.

- [ ] **Step 4: Verify GREEN**

Run F10 schema test + F9 charge schema + F8 lease schema + F7 contract schema. Expected PASS.

- [ ] **Step 5: Commit + checkpoint**

Commit: `feat(f10): add minimal payment method schema`.

Checkpoint records exact one-table delta and Two Laws PASS.

---

### Task 2: Implement canonical validation, CRUD-without-delete, and scope resolution

**Files:**
- Create: `app/Finance/PaymentMethodService.php`
- Create/Modify: `tests/Integration/Finance/PaymentMethodServiceTest.php`

**Interfaces:**
- Constructor: `PaymentMethodService::__construct(PDO $pdo, string $qrDirectory)`.
- Produces `create(array $input, ?UploadedFileInterface $qr): array`.
- Produces `update(int $id, array $input, ?UploadedFileInterface $qr, bool $removeQr): array`.
- Produces `listAll(): array`.
- Produces `listForProperty(int $propertyId): array`.
- Produces `find(int $id): ?array`.
- QR upload behavior remains RED until Task 3; non-QR types must already work.

- [ ] **Step 1: Write non-QR validation RED tests**

Pin exact payment-method type allowlist `bank_account|breb_key|breb_qr|wallet|manual`; required fields per non-QR type; trimmed non-empty label; property NULL/global or existing positive property; nonexistent property fails; `sort_order >= 1`; `is_active` normalized to 0/1; length limits enforced.

For `breb_key`, pin key-type allowlist `identity_document|mobile|email|alphanumeric|merchant_code`. Apply only minimal stable shape checks supported by the official Bre-B contract: mobile is national 10 digits beginning with `3`; email is a valid email; alphanumeric starts with `@` and contains the permitted alphanumeric body; merchant code follows the current official commerce-code shape; identity document rejects separators/whitespace inconsistent with the official key format. These are routing-data validations, not bank authentication.

- [ ] **Step 2: Write stale-field convergence RED tests**

Changing between non-QR types must NULL all irrelevant fields. Examples: `bank_account -> manual` clears institution/account data; `breb_key -> wallet` clears Bre-B fields. No hidden prior-type values survive.

- [ ] **Step 3: Write scope/list RED tests**

`listForProperty(propertyId)` returns only active global + matching property-specific rows, ordered `sort_order ASC, id ASC`; inactive rows absent; property-specific rows for another property absent. `listAll()` retains active and inactive for staff administration.

- [ ] **Step 4: Verify RED**

Run PaymentMethodServiceTest. Expected class/method missing failures.

- [ ] **Step 5: Implement minimal service core**

Centralize type/key normalization and validation in this service. Use prepared PDO only. Do not create per-type service/helper classes. For create/update without QR file changes, use the minimum transactionality the operation requires; do not add locks simply for caution.

- [ ] **Step 6: Enforce no operational delete**

No service DELETE method. Retirement is update with `is_active=0`.

- [ ] **Step 7: Verify GREEN**

Run PaymentMethodServiceTest non-QR/scope cases + relevant PropertyService tests. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit: `feat(f10): add payment method configuration authority`.

---

### Task 3: Add private Bre-B QR storage, replacement, cleanup, and integrity

**Files:**
- Modify: `app/Finance/PaymentMethodService.php`
- Modify: `tests/Integration/Finance/PaymentMethodServiceTest.php`

**Interfaces:**
- Completes `create()` / `update()` for `breb_qr`.
- Produces `privateQr(int $id): ?array{path:string,mime:string,size_bytes:int}` without exposing stored filename.

- [ ] **Step 1: Write upload RED tests**

Accept only valid PNG/JPEG/WebP <=2 MiB by MIME bytes; reject SVG, spoofed extensions/MIME, empty and oversize uploads. Generated name must match `qr-[a-f0-9]{32}.(png|jpg|webp)` and live under the configured private QR directory. Creating `type=breb_qr` without a valid QR must fail closed.

- [ ] **Step 2: Write metadata/integrity RED tests**

Persist `qr_stored_name`, real MIME, SHA256 and exact size. `privateQr()` verifies safe path, existence, actual MIME, stored MIME, size and hash. Missing/tampered/mismatch returns null.

- [ ] **Step 3: Write replacement/removal/type-change RED tests**

Replacement must store new bytes/metadata, then remove the superseded file after successful DB update. `removeQr=true` while the destination type remains `breb_qr` is invalid unless a valid replacement QR is supplied in the same update. Changing away from `breb_qr` clears QR metadata/file automatically. No stale QR remains on another type and no active `breb_qr` row may end the operation without intact QR metadata.

- [ ] **Step 4: Write failure-cleanup RED test**

When a new QR file is moved but DB update fails, the newly created file is removed and the previous DB/file authority remains valid. Do not invent filesystem transactions.

- [ ] **Step 5: Verify RED**

Run focused QR tests only.

- [ ] **Step 6: Implement QR storage inside PaymentMethodService**

Reuse the established `finfo`/random-name/private-directory pattern; do not call or inject `UnitMediaService` because payment-method QR is a separate domain authority even though it follows the same storage pattern.

- [ ] **Step 7: Verify GREEN**

Run full PaymentMethodServiceTest and relevant existing media/security regression tests. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit: `feat(f10): store private Bre-B payment QR safely`.

---

### Task 4: Expose admin payment-method management inside the existing finance app

**Files:**
- Create: `resources/views/payment_methods.php`
- Create/Modify: `tests/Integration/Finance/PaymentMethodRoutesTest.php`
- Modify: `bootstrap/app.php`
- Modify preferred: `app/Finance/routes.php`
- Optional only if preflight proved necessary: create `app/Finance/payment_methods_routes.php`
- Modify only if needed: existing finance admin view/navigation.

**Interfaces:**
- Routes exactly:
  - `GET /admin/finance/payment-methods`
  - `POST /admin/finance/payment-methods`
  - `POST /admin/finance/payment-methods/{id}`
- Read authority: `finance.view`.
- Mutation authority: `finance.manage`.
- `PaymentMethodService` instantiated once in bootstrap and shared.

- [ ] **Step 1: Write auth/permission RED tests**

Guest -> login; forced-password -> `/account/password`; finance viewer can GET but cannot POST; finance manager can create/update; owner bypasses; tenant gets 403 even with polluted permissions. Global CSRF applies to both POSTs; basePath redirects/actions remain correct.

- [ ] **Step 2: Write admin-view RED tests**

List renders escaped label/type/scope/receptor/institution/order/active/has-QR status. It must not render stored filenames, filesystem paths or secret-like fields. No payments/report/reconciliation controls.

- [ ] **Step 3: Write mutation RED tests**

Create/update successes use PRG 303 back to `/admin/finance/payment-methods`; invalid domain input returns 422 without partial mutation. Multipart QR upload flows only through `breb_qr` validation. One update route handles fields, active flag, sort order, optional replacement and `remove_qr=1`; the route cannot bypass the service rule that a retained `breb_qr` requires an intact/replacement QR.

- [ ] **Step 4: Wire one shared PaymentMethodService**

Bootstrap gets one private QR directory setting (default under storage, outside webroot), instantiates one service and passes it to finance + leases composition. No second instance per route file.

- [ ] **Step 5: Implement admin routes using existing finance guard style**

Do not create new auth/session/CSRF stack. Prefer extending F9 `app/Finance/routes.php`; if the preflight-approved second route file exists, it must reuse the same deps/guards rather than duplicate architecture.

- [ ] **Step 6: Implement one SSR list/form view**

Keep create/edit simple; type-specific fields may be server-rendered together rather than requiring client-side dynamic forms. Bre-B key-type options use the canonical allowlist, not arbitrary text. No wizard, SPA or mandatory JS.

- [ ] **Step 7: Verify GREEN**

Run PaymentMethodRoutesTest + F9 FinanceRoutesTest + UserService/Permissions tests + global CSRF + basePath tests. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit: `feat(f10): manage payment methods in finance`.

---

### Task 5: Integrate applicable methods into the existing tenant lease detail and serve private QR

**Files:**
- Modify: `app/Leases/routes.php`
- Modify: `resources/views/tenant_lease_detail.php`
- Modify/Create focused tenant payment-method integration tests under `tests/Integration/Finance/` or existing F8 tenant test file.
- Modify: `tests/Integration/Finance/PaymentMethodSecurityRegressionTest.php`

**Interfaces:**
- Reuses existing `GET /tenant/leases/{id}`.
- Adds exactly one F10 tenant route: `GET /tenant/leases/{leaseId}/payment-methods/{methodId}/qr`.
- Ownership is always resolved first via existing `LeaseService::findForUser()`.

- [ ] **Step 1: Write tenant list RED tests**

Owned lease shows only active global + matching-property methods, stable order, escaped visible fields and a QR image/link only for applicable `breb_qr`. Inactive and another-property methods are absent. Ended/historical owned lease may still see current active applicable methods.

- [ ] **Step 2: Write ownership/scope RED tests**

Foreign/nonexistent lease returns 404 before payment-method resolution. Tenant cannot supply/override property ID. The lease's canonical `property_id` is the only property-scope input.

- [ ] **Step 3: Write private QR RED tests**

Correct owned lease + applicable active breb_qr + intact file => 200 with exact detected MIME, Content-Length, `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`. Wrong lease, foreign property scope, inactive method, non-QR type, missing/tampered file => 404. Response never includes physical stored filename.

- [ ] **Step 4: Write F11-boundary RED test**

Tenant UI has no `Ya pagué`, payment-report form, reconciliation state, paid/partial status or allocation UI.

- [ ] **Step 5: Implement ownership-first composition**

Existing tenant guard and `findForUser()` remain the authority. Only after ownership succeeds call `listForProperty((int)$lease['property_id'])` or `privateQr(methodId)` plus applicable-scope verification.

- [ ] **Step 6: Extend existing tenant view only**

Add one `Cómo pagar` section using current surface/card primitives. Do not add a second portal/page/layout.

- [ ] **Step 7: Verify GREEN**

Run tenant payment-method tests + F8 TenantPortalRoutes + F9 tenant finance tests + ShellRoutes. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit: `feat(f10): show applicable payment methods to tenants`.

---

### Task 6: Freeze F10 architecture, privacy, anti-noise and F11 boundary

**Files:**
- Create/complete: `tests/Integration/Finance/PaymentMethodSecurityRegressionTest.php`
- Modify historical F8/F9 guardrails only if they incorrectly forbid legitimate composition while the older core authorities remain isolated.

**Interfaces:**
- Produces architectural/security regression proof before closure QA.

- [ ] **Step 1: Freeze architecture budget**

Assert:

```text
1 payment_methods table
1 PaymentMethodService
0 new permissions
4 private F10 routes total
0 public F10 routes
0 payment/report/allocation/transaction tables
0 provider adapters
0 bank APIs/webhooks
0 new runtime dependencies
0 parallel auth/session
```

If F10 needed one additional route file per approved preflight, freeze `<=1`; otherwise freeze `0 F10-specific route files` and require reuse of F9/F8 route files.

- [ ] **Step 2: Freeze forbidden credentials and secret leakage**

Schema/source/views/logging surfaces must not introduce bank username/password/PIN/OTP/API tokens/secrets. HTML and QR responses must not expose stored name/path/hash/internal metadata.

- [ ] **Step 3: Freeze type/scope/data-cleanliness contracts**

Exact payment-method and Bre-B key-type allowlists, irrelevant fields NULL, type change removes stale fields/QR, retained `breb_qr` cannot exist without QR metadata, tenant ownership precedes scope, global + matching property only, inactive excluded.

- [ ] **Step 4: Freeze F11 boundary**

No `payments`, `reported`, `reconciled`, `paid`, `partial`, `allocation`, `proof_received`, bank-provider workflow or `Ya pagué` UI appears in F10 product surfaces except explicit negative assertions/documentation.

- [ ] **Step 5: Verify GREEN**

Run PaymentMethodSecurityRegressionTest + F8/F9 security regression tests. Expected PASS.

- [ ] **Step 6: Commit + checkpoint**

Commit: `test(f10): freeze payment method security architecture`.

---

### Task 7: Full QA, Two Laws audit, closure docs and review-ready PR

**Files:**
- Modify after product GREEN: `docs/specs/F10_PAYMENT_METHODS_SPEC.md`
- Modify after product GREEN: `README.md`
- Create after product GREEN: `docs/checkpoints/F10_CLOSED_<date>.md`

**Interfaces:**
- Produces review-ready F10; does not merge and does not start F11.

- [ ] **Step 1: Run focused F10/F9/F8 integration suite**

Run all PaymentMethod tests plus relevant Finance, TenantPortal, Lease lifecycle, Permissions/UserService, ShellRoutes, CSRF and private-storage/security tests. Expected 0 failures / 0 errors. Record actual counts.

- [ ] **Step 2: Run Composer + full local suite**

Run `composer validate --strict` and `vendor/bin/phpunit --testdox`. Record PHP version, tests, assertions, failures/errors/skips. No predicted counts.

- [ ] **Step 3: Audit Two Laws and patch smells against F9-integrated base**

Prove one table/service, no new permissions, no F11 tables, no provider integration, no public routes, no secret credential fields, no duplicate storage service, no stale-type data, no duplicate tenant scope algorithm, no unnecessary CSS/JS. Classify suspicious code `REMOVE|CONVERGE|SIMPLIFY|IMPROVE|KEEP`; any correction returns to the owning Task with a RED.

- [ ] **Step 4: Define PRODUCT_HEAD and require exact GitHub CI**

Push exact product SHA and require PHP 8.3/8.4/8.5 PASS. Record run ID and actual PHP 8.3 tests/assertions/failures/errors.

- [ ] **Step 5: Review every changed product file before closure docs**

CI green is not a substitute for diff review. Inspect migration, service, bootstrap, admin/tenant routes, views and tests. Any blocker returns to owning Task and repeats focused/full/CI verification.

- [ ] **Step 6: Converge closure documentation**

Set SPEC to implemented/verified, README to `F10 CLOSED / VERIFIED / NOT MERGED`, create canonical F10 CLOSED checkpoint with F9 base SHA, schema, routes, scope rules, Bre-B key-type allowlist/source date, QR lifecycle/integrity, privacy boundary, RED/GREEN history, CI and Two Laws/antipatch verdict. Merge remains unauthorized.

- [ ] **Step 7: Commit closure docs and require fresh exact-HEAD CI**

Commit: `docs(f10): close verified payment methods`.

Push closure HEAD and require fresh PHP 8.3/8.4/8.5 PASS for that SHA.

- [ ] **Step 8: Open/update F10 PR and stop at merge gate**

PR head `impl/f10-payment-methods-20261013`, base `main`, title `F10: payment methods`. Body includes architecture budget, F9 base, product + closure CI, private QR security, tenant scope, Two Laws PASS and `MERGE NOT AUTHORIZED`. Do not merge, auto-merge, start F11 or add optional improvements.

---

## Self-Review Result — 10 passes

1. **Spec coverage:** schema, five payment-method types, global/property scope, lifecycle, QR integrity/storage, finance permissions, admin UI, tenant visibility, private QR, F11 exclusion and closure all map to Tasks 1–7 — PASS.
2. **KISS:** one table + one service; route reuse is default and no new F10 route file is expected when F9 matches design — PASS.
3. **Cero Ruido:** no scope bridge/media/provider/payment tables; stale type data is explicitly cleared — PASS.
4. **Type/interface consistency:** one `PaymentMethodService` API across all tasks; QR responsibility never leaves it — PASS.
5. **Bre-B consistency:** key type is no longer arbitrary free text; current official categories are frozen and preflight revalidates them before implementation — PASS.
6. **QR lifecycle:** create requires QR, retained QR type cannot be emptied, replacement/removal/type change have explicit tests — PASS.
7. **Tenant isolation:** ownership precedes property scope and QR delivery — PASS.
8. **Security:** no banking authentication credentials, no public QR route, integrity checks and no physical filename leakage — PASS.
9. **F11 boundary:** no paid/reported/reconciled/allocation behavior or UI is allowed — PASS.
10. **Antipatches:** no per-type service/route, no custom vault, no provider abstraction, no future placeholders; defects return to owning Task with RED — PASS.

## Final Execution Contract

A Task closes only after its focused GREEN and checkpoint. A defect found later returns to the Task/authority that owns it with a new RED; never add a lateral exception. If the final F9 reality makes this design require extra permissions, a second scope table, a provider abstraction or a second payment authority, stop and reopen SPEC rather than expanding architecture silently.

Expected final handoff:

```text
F10_STATUS: CLOSED / VERIFIED / NOT MERGED
LAW_1_KISS: PASS
LAW_2_CERO_RUIDO: PASS
PATCH_STACKING: NONE
NEW_TABLES: 1
NEW_DOMAIN_CLASSES: 1
NEW_PERMISSIONS: 0
NEW_PUBLIC_ROUTES: 0
NEW_PAYMENT_TRANSACTION_TABLES: 0
NEW_BANK_INTEGRATIONS: 0
NEW_DIRECT_RUNTIME_DEPENDENCIES: 0
NEXT_ACTION: CHATGPT/SOL FINAL FORENSIC AUDIT
```
