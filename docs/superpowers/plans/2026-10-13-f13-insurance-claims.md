# F13 Insurance + Claims Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add one configurable insurance authority for lease policies and claims, with private evidence, provider-neutral claim workflow and read-only tenant summary, without creating insurer integrations, financial coupling, notification infrastructure or a parallel document system.

**Architecture:** F13 adds exactly two tables (`insurance_policies`, `insurance_claims`) and one domain class (`InsuranceService`). Coverage, checklist and document metadata are validated JSON snapshots stored in LONGTEXT; bytes remain private outside webroot. Policy lifecycle is mostly derived, claim status is persisted because it represents an external/human fact, and F9/F11 remain the only financial authority.

**Tech Stack:** PHP >=8.3 <8.6, Slim 4, MariaDB/MySQL, PDO, SSR HTML, existing CSS/JS, PHPUnit 11.x, existing auth/CSRF/private-file patterns, no new runtime dependency.

**Spec:** `docs/specs/F13_INSURANCE_CLAIMS_SPEC.md` — approved by the user on 2026-10-13. Product implementation remains blocked until F12 is finally audited and merged into `main`.

## Global Constraints

- KISS: exactly two F13 tables, one F13 domain class and maximum one F13 route file.
- Cero Ruido: `ELIMINAR -> UNIR/CONVERGER -> SIMPLIFICAR -> MEJORAR -> AGREGAR` before adding any piece.
- Anti-patch: reproduce -> root cause -> owning authority -> converge/simplify -> RED -> minimal GREEN -> remove superseded logic -> regression.
- Reuse `leases`, `LeaseService` tenant ownership, existing auth/session/CSRF/layout/private-storage patterns.
- Add only `insurance.view` and `insurance.manage`; owner bypass remains canonical.
- `insurance_policies` never persists derived status or reminder state.
- `insurance_claims.status` is the only persisted F13 lifecycle state because it is externally/humanly asserted.
- Provider/product/radicado semantics remain provider-neutral; no SURA branches or provider adapters.
- Coverage, checklist and document metadata remain validated JSON snapshots; no coverage/checklist/document/provider/history tables.
- F13 never writes charges/payments/allocations and never changes tenant outstanding.
- No claim↔charge bridge and no persisted `in_claim` in F13 V1.
- F13 produces derived expiry signals only; F16 owns reminders/cron/email/WhatsApp.
- F13 uses private document bytes only; F18 remains the future generic document authority.
- Tenant F13 V1 is read-only and does not receive insurance document/claim routes.
- No F13 merge without a later explicit merge gate.

## Review Focus

1. **Lost-update protection for JSON documents:** concurrent uploads to the same policy/claim must preserve both metadata entries by locking and re-reading the owner row.
2. **Cancellation versus historical claims:** a cancelled policy still accepts a claim only when `incident_on` was inside coverage and not after the local cancellation date; concurrent cancel/createClaim must revalidate under the policy lock.
3. **Terminal claim immutability:** terminal transition racing with checklist/reference/date edits must leave one coherent terminal record and must not allow post-terminal checklist mutation.
4. **Provider neutrality:** filing must not require radicado; all dates must obey `incident_on <= filed_on <= resolved_on <= today` where applicable without provider-specific exceptions.
5. **Private evidence lifecycle:** removable evidence becomes append-only at the exact policy/claim lifecycle boundary, with DB/file cleanup that never restores stale metadata after a physical-delete failure.

---

## Planned File Map

### Create

- `database/migrations/013_create_insurance.sql` — creates only `insurance_policies` and `insurance_claims`.
- `app/Insurance/InsuranceService.php` — sole F13 domain authority.
- `app/Insurance/routes.php` — one F13 staff route file maximum.
- `tests/Integration/Insurance/InsuranceSchemaTest.php` — schema/noise freeze.
- `tests/Integration/Insurance/InsurancePolicyServiceTest.php` — policy validation, lineage, cancellation, policy documents.
- `tests/Integration/Insurance/InsuranceClaimServiceTest.php` — claim draft/checklist/transitions/provider-neutral dates.
- `tests/Integration/Insurance/InsuranceConcurrencyTest.php` — policy/claim lifecycle and document races.
- `tests/Integration/Insurance/InsuranceRoutesTest.php` — staff auth/permissions/CSRF/private documents + tenant summary.
- `tests/Integration/Insurance/InsuranceSecurityRegressionTest.php` — architecture/privacy/no-future guardrails.

### Modify after execution preflight confirms F12-final paths

- `app/Auth/Permissions.php` — add exactly `insurance.view` and `insurance.manage` to staff catalog; tenant remains fail-closed.
- `bootstrap/app.php` — wire one `InsuranceService` and one private insurance storage directory if needed.
- shell/apps launcher file actually used after F12 — expose `Seguros` only when route/permission are real.
- `app/Leases/routes.php` — compose tenant-owned policy summary only.
- `resources/views/tenant_lease_detail.php` — read-only insurance summary.
- one or two focused F13 staff views if inspection proves clearer than overloading an existing view.
- closure-only: README, F13 SPEC status, `docs/checkpoints/F13_CLOSED_2026-10-13.md`.

### Not planned

- no `insurance_coverages`, `claim_checklist_items`, `insurance_documents`, `insurance_providers`, `policy_versions`, `claim_charge_links`, `claim_payments`, `claim_notifications` or `claim_status_history`;
- no provider SDK/adapters/webhooks/OCR;
- no charge/payment writer;
- no cron/reminder/email/WhatsApp;
- no public routes;
- no tenant claim workflow;
- no Composer dependency change.

---

## Execution Preflight — mandatory before Task 1 product work

Implementation MUST NOT start from this docs branch.

- [ ] Verify F12 is `CLOSED / VERIFIED`, ChatGPT/Sol final audit is PASS and F12 is merged into `main`.
- [ ] Record exact F12 merge SHA and current `main` SHA; use that exact integrated SHA as F13 implementation base.
- [ ] Re-read F13 SPEC/PLAN, F12 merged checkpoint, actual `LeaseService`, auth permissions, staff shell/routes/views, private-file helpers/patterns and test harness.
- [ ] Confirm tenant ownership remains `LeaseService::findForUser` and staff permission catalog/bypass semantics remain canonical.
- [ ] Confirm no integrated module already owns generic insurance/private-document behavior that would make a planned F13 piece redundant; converge rather than duplicate.
- [ ] Confirm migration number `013` is still free; if integrated F12 consumes another number, use the next real migration number without changing F13 semantics.
- [ ] Freeze exact F13 view paths and one-route-file placement from the integrated repo rather than creating files by symmetry.
- [ ] Create `impl/f13-insurance-claims-20261013` from exact F12-integrated `main`.
- [ ] Bring only approved F13 SPEC/PLAN docs onto the implementation branch.
- [ ] Converge stale SPEC banner to `APPROVED / IMPLEMENTATION IN PROGRESS`, record `F12_INTEGRATED_BASE_SHA`, keep `MERGE_AUTHORIZED: NO`, and commit docs-only.
- [ ] Create one rolling checkpoint `docs/checkpoints/F13_LUNA_EXECUTION_CHECKPOINT_2026-10-13.md`.

Path/name convergence to final F12 reality is allowed. A change to two-table/one-service/provider-neutral/no-financial-coupling model requires reopening SPEC.

---

### Task 1: Freeze the two-table insurance schema

**Files:**
- Create: `database/migrations/013_create_insurance.sql` (or next free number confirmed in preflight)
- Create: `tests/Integration/Insurance/InsuranceSchemaTest.php`
- Modify only if an existing schema-count guardrail legitimately needs phase-aware convergence: the owning regression test.

**Interfaces:**
- Consumes: `leases.id`, `users.id`.
- Produces exact `insurance_policies` and `insurance_claims` persistence for later Tasks.

- [ ] **Step 1: Write schema RED**

Assert exact policy columns/types/FKs/indexes from SPEC, including LONGTEXT snapshots, self-FK `supersedes_policy_id`, cancellation actor/time/reason and absence of policy `status`/reminder/provider-specific/financial fields.

Assert exact claim columns/types/FKs/indexes from SPEC, including persisted `status`, optional radicado/claimed amount, date fields, checklist/documents LONGTEXT and absence of charge/payment/provider/webhook/reminder/history fields.

Assert absence of all prohibited parallel tables: coverage/checklist/document/provider/version/claim-charge/payment/notification/history.

- [ ] **Step 2: Verify RED**

Run `vendor/bin/phpunit --testdox tests/Integration/Insurance/InsuranceSchemaTest.php`.

Expected: FAIL only because migration/tables are absent; existing F8–F12 schema suites remain green.

- [ ] **Step 3: Implement migration only**

Create exactly two tables. Use `ON DELETE RESTRICT` for lease/policy/self-history rows and `SET NULL` for actor FKs as frozen by SPEC. Add only frozen query indexes plus indexes required by the engine for FKs.

- [ ] **Step 4: Verify GREEN**

Run F13 schema plus integrated F8–F12 schema suites. Expected PASS.

- [ ] **Step 5: Commit + checkpoint**

Commit `feat(f13): add insurance policy and claim schema`.

Checkpoint `+2 tables`, zero subordinate insurance tables, KISS/Cero Ruido PASS.

---

### Task 2: Add policy authority, coverage snapshot and derived lifecycle

**Files:**
- Create: `app/Insurance/InsuranceService.php`
- Create/Modify: `tests/Integration/Insurance/InsurancePolicyServiceTest.php`

**Interfaces:**
- Constructor target: `InsuranceService(PDO $pdo, string $documentDirectory)` unless final wiring proves a simpler equivalent; no repository/DTO/provider layer.
- Produce `createPolicy(int $leaseId, array $input, array $documents, int $actorUserId, string $today): array`.
- Produce `findPolicy(int $policyId, string $today): ?array`.
- Produce `listPoliciesForLease(int $leaseId, string $today): array`.
- Produce `listPolicies(string $today): array` or equivalent admin projection name frozen in preflight.
- Produce `updatePolicyNotes(int $policyId, ?string $internalNotes, int $now): array`.

- [ ] **Step 1: Write policy-validation RED tests**

Pin provider/product/policy-number trim/non-empty/length, existing active-or-ended lease, `coverage_start <= coverage_end`, JSON snapshot shape, non-empty coverages, normalized duplicate coverage-name rejection, decimal-string limits/deductibles, notes bounds and provider-neutral fields.

- [ ] **Step 2: Write derived-lifecycle RED tests**

Pin `upcoming|active|expired|cancelled`, injected/testable `today`, start/expiry day boundaries, days-until signals and no persisted policy status.

- [ ] **Step 3: Write lineage/core-immutability RED tests**

`supersedes_policy_id` must reference an existing prior policy on the same lease; self/future/nonexistent/cross-lease references fail. New policy does not auto-cancel previous policy and overlapping coverage is allowed. Policy core fields never get a destructive update path; correction/renewal means another row. Internal notes alone remain mutable.

- [ ] **Step 4: Verify RED**

Run focused policy service tests; expected missing-class/method failures.

- [ ] **Step 5: Implement minimal policy core without document mutation helpers yet**

Keep snapshot validation/normalization and derived projection inside `InsuranceService`; encode canonical JSON deterministically enough for stable tests without creating JSON DTO classes.

- [ ] **Step 6: Verify GREEN**

Run `InsurancePolicyServiceTest` core cases + relevant lease lifecycle tests. Expected PASS.

- [ ] **Step 7: Commit + checkpoint**

Commit `feat(f13): add configurable insurance policy authority`.

---

### Task 3: Add private policy documents and cancellation lifecycle

**Files:**
- Modify: `app/Insurance/InsuranceService.php`
- Modify: `tests/Integration/Insurance/InsurancePolicyServiceTest.php`
- Modify: `bootstrap/app.php` only if private directory wiring is required.

**Interfaces:**
- Produce `addPolicyDocument(int $policyId, string $label, UploadedFileInterface $file): array`.
- Produce `removePolicyDocument(int $policyId, string $documentId): array`.
- Produce `privatePolicyDocument(int $policyId, string $documentId): ?array{path:string,mime:string,size_bytes:int}`.
- Produce `cancelPolicy(int $policyId, int $actorUserId, string $reason, int $now): array`.

- [ ] **Step 1: Write file-contract RED tests**

Accept real PNG/JPEG/WebP/PDF <=5 MiB by bytes; reject spoof/unsupported/empty/oversize. Persist `document_id` 32hex distinct from random stored filename, label 1..190, actual MIME, SHA256 and exact size. Read validates safe path/MIME/size/hash; tamper/missing/unknown document -> 404-equivalent domain absence.

- [ ] **Step 2: Write policy-document lifecycle RED tests**

Before cancellation and before first claim, staff may add/remove an erroneous document. Once policy has any claim or is cancelled, existing documents become append-only but new documents can still be appended. Removing a non-existent ID fails closed without touching files.

- [ ] **Step 3: Write JSON/file atomicity RED tests**

Concurrent/serialized add path locks policy, re-reads JSON and appends without lost update. DB failure after storing a new file removes only the new file. Remove path changes DB first and deletes physical file only after DB success; delete failure may leave an orphan file but must not restore stale DB metadata.

- [ ] **Step 4: Write cancel RED tests**

First cancellation stores actor/time/reason once, leaves docs/claims untouched and derives cancelled state. Second cancel fails closed preserving original values. No automatic claim rejection/cancellation.

- [ ] **Step 5: Verify RED**

Run document/cancel-focused policy tests.

- [ ] **Step 6: Implement minimal private-document and cancellation helpers inside InsuranceService**

Reuse project file patterns; no generic DocumentService/table/provider abstraction.

- [ ] **Step 7: Verify GREEN**

Run full policy service tests + existing private-file/security regressions. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit `feat(f13): secure policy evidence and cancellation`.

---

### Task 4: Add claim draft, checklist and provider-neutral transitions

**Files:**
- Modify: `app/Insurance/InsuranceService.php`
- Create: `tests/Integration/Insurance/InsuranceClaimServiceTest.php`

**Interfaces:**
- Produce `createClaim(int $policyId, array $input, array $documents, int $actorUserId, string $today): array`.
- Produce `updateDraftClaim(int $claimId, array $input, string $today): array`.
- Produce `updateClaimChecklist(int $claimId, array $checklist): array`.
- Produce `transitionClaim(int $claimId, string $targetStatus, ?string $filedOn, ?string $radicado, ?string $resolvedOn, ?string $resolutionNotes, int $actorUserId, string $today): array`.
- Produce `findClaim(int $claimId): ?array` and `listClaimsForPolicy(int $policyId): array`.

- [ ] **Step 1: Write create/draft RED tests**

Require policy row lock; accept policy active/expired/upcoming only when incident date itself lies inside coverage. For cancelled policy, accept historical claim only when incident date is inside coverage and <= local cancellation date. Reject future incident. Validate description, optional decimal claimed amount and exact checklist shape/key allowlist/uniqueness/booleans. Create only `draft`.

- [ ] **Step 2: Write draft-edit RED tests**

While draft, incident/description/amount/checklist can be corrected and must revalidate full policy coverage/cancellation rules. After leaving draft, incident/description/amount are immutable. Checklist remains updateable only until terminal.

- [ ] **Step 3: Write transition RED tests**

Pin exact allowlist/transitions: `draft→filed`, `filed→in_review|terminal`, `in_review→terminal`; no backwards/terminal-to-terminal/reopen. For filed+ require `filed_on` with `incident_on <= filed_on <= today`; radicado remains optional in every state and bounded when present. Terminal requires `resolved_on` with `filed_on <= resolved_on <= today`; terminal freezes status/radicado/dates/checklist.

- [ ] **Step 4: Write provider-neutral RED tests**

Filing without radicado succeeds. Adding/correcting radicado while non-terminal under lock succeeds. No provider/product name changes the transition rules. Incomplete required checklist does not automatically block filing/transition.

- [ ] **Step 5: Verify RED**

Run claim service tests; expected missing behavior only.

- [ ] **Step 6: Implement minimal claim/checklist/transition authority**

Persist only the external/human status. Do not create status-history rows or provider branches.

- [ ] **Step 7: Verify GREEN**

Run claim + policy service tests. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit `feat(f13): manage provider neutral insurance claims`.

---

### Task 5: Add claim documents and prove lifecycle concurrency

**Files:**
- Modify: `app/Insurance/InsuranceService.php`
- Modify: `tests/Integration/Insurance/InsuranceClaimServiceTest.php`
- Create: `tests/Integration/Insurance/InsuranceConcurrencyTest.php`

**Interfaces:**
- Produce `addClaimDocument(int $claimId, string $label, UploadedFileInterface $file): array`.
- Produce `removeClaimDocument(int $claimId, string $documentId): array`.
- Produce `privateClaimDocument(int $claimId, string $documentId): ?array{path:string,mime:string,size_bytes:int}`.

- [ ] **Step 1: Write claim-document RED tests**

Reuse exact policy MIME/hash/size/document-id contract. While draft, add/remove allowed; after leaving draft existing evidence is append-only but new documents remain appendable through terminal. DB/file failure behavior mirrors policy documents.

- [ ] **Step 2: Write two-upload RED tests**

Using the strongest two-PDO MariaDB harness available, concurrent uploads to one policy preserve both metadata entries and concurrent uploads to one claim preserve both entries; no last-write-wins JSON loss.

- [ ] **Step 3: Write policy claim/freezing race RED tests**

Concurrent `createClaim` versus policy document removal must serialize on policy row so that once claim creation wins, prior policy evidence cannot be removed. Concurrent `cancelPolicy` versus `createClaim` must revalidate incident date against final policy cancellation state.

- [ ] **Step 4: Write claim terminal race RED tests**

Two incompatible transitions allow only one legal outcome. Terminal transition racing with checklist update must not permit checklist mutation after terminal state. Record exact expected loser behavior without retry loops.

- [ ] **Step 5: Verify RED**

Run `InsuranceConcurrencyTest` + document-focused claim tests.

- [ ] **Step 6: Implement owner-row locking/re-read semantics**

Policy row is lock authority for policy docs/cancel/createClaim; claim row is lock authority for claim docs/checklist/transitions. No service-level/distributed locks and no retry patch hiding a broken transaction boundary.

- [ ] **Step 7: Verify GREEN**

Run full policy + claim + concurrency suites. Expected PASS and orphan-file count zero in normal success/failure-path tests.

- [ ] **Step 8: Commit + checkpoint**

Commit `feat(f13): preserve insurance evidence under concurrency`.

### Midpoint QA

After Task 5 run `composer validate --strict` and full PHPUnit because schema, files, claim terminality and concurrency semantics are complete. Record exact counts; repair root cause before routes/UI.

---

### Task 6: Expose staff insurance workflow with exact permissions

**Files:**
- Create: `app/Insurance/routes.php`
- Create/Modify: one or two focused F13 staff views frozen during preflight.
- Modify: `app/Auth/Permissions.php`
- Modify: shell/apps launcher actual file.
- Create: `tests/Integration/Insurance/InsuranceRoutesTest.php`

**Interfaces:**
- Staff routes conceptually: `GET /admin/insurance`; policy create/cancel/document add/remove/read; claim create/draft update/transition/document add/remove/read.
- `insurance.view` reads; `insurance.manage` mutates; owner bypass remains canonical.

- [ ] **Step 1: Write permission/auth/CSRF RED tests**

Guest login redirect, forced-password gate, viewer read-only, manager permission split, owner bypass, tenant denial even if staff permission data is polluted, global CSRF on every POST, correct PRG/basePath behavior.

- [ ] **Step 2: Write admin policy UI RED tests**

Show lease/property/unit context, provider/product/full policy number, vigencia-derived state/signals, coverages including notes, lineage, docs and allowed lifecycle actions. Escape all configured strings and never render stored filenames/paths.

- [ ] **Step 3: Write admin claim UI RED tests**

Show incident/amount/status, optional radicado, filed/resolved dates, checklist, documents and valid next actions only; incomplete checklist may be visible but not hard-blocked. No insurer decision/coverage interpretation text generated by system.

- [ ] **Step 4: Write private document route RED tests**

Authorized `insurance.view` can read intact policy/claim docs with actual Content-Type/Length, private no-store, nosniff. Tamper/missing/wrong owner entity -> 404. No raw JSON/stored path leak.

- [ ] **Step 5: Verify RED**

Run staff cases in `InsuranceRoutesTest`.

- [ ] **Step 6: Implement minimal route/view surface**

One F13 route file maximum, existing shell/auth/CSRF helpers, no second dashboard or JSON API. Add exactly two permissions and launcher visibility only when route is real.

- [ ] **Step 7: Verify GREEN**

Run F13 routes + Auth/UserService + ShellRoutes + CSRF/security regressions. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit `feat(f13): expose private insurance workflow`.

---

### Task 7: Add tenant read-only policy summary without insurance document routes

**Files:**
- Modify: `app/Leases/routes.php`
- Modify: `resources/views/tenant_lease_detail.php`
- Modify: `tests/Integration/Insurance/InsuranceRoutesTest.php`
- Modify tenant security tests only for legitimate F13 composition.

**Interfaces:**
- Existing `GET /tenant/leases/{id}` composes read-only F13 policy summary.
- No tenant F13 POST routes and no tenant insurance document/claim routes.

- [ ] **Step 1: Write tenant ownership RED tests**

Owned active or ended lease displays its policies; foreign/nonexistent lease returns 404 before insurance lookup. No policy from another lease leaks.

- [ ] **Step 2: Write tenant projection RED tests**

Show provider, product, masked policy number, coverage dates, derived policy state and each coverage `name|limit_amount|deductible_amount`. Do not expose coverage notes, internal notes, documents, claims/radicados/checklist/resolution notes or actors.

- [ ] **Step 3: Write masking RED tests**

Length <=4 -> exactly `••••`; length >4 -> exactly `••••` + last four characters. Never persist or reuse masked value for admin.

- [ ] **Step 4: Write no-surface RED tests**

Assert tenant has no F13 create/cancel/claim/upload/transition/document routes/actions in V1.

- [ ] **Step 5: Verify RED**

Run tenant F13 cases + existing tenant ownership/security suites.

- [ ] **Step 6: Implement minimal ownership-first projection**

Mandatory order: tenant guard -> `LeaseService::findForUser` -> 404 foreign -> policy projection -> render. Reuse existing tenant detail; no new tenant app/layout.

- [ ] **Step 7: Verify GREEN**

Run F13 tenant + full tenant portal/security suites. Expected PASS.

- [ ] **Step 8: Commit + checkpoint**

Commit `feat(f13): show tenant insurance summary`.

---

### Task 8: Freeze architecture/security, run full QA and close F13 without merging

**Files:**
- Create/Modify: `tests/Integration/Insurance/InsuranceSecurityRegressionTest.php`
- Modify closure-only: `README.md`, F13 SPEC status.
- Create closure: `docs/checkpoints/F13_CLOSED_2026-10-13.md`.
- Maintain one rolling checkpoint during implementation; converge/remove it at closure if final report absorbs all unique evidence.

**Interfaces:**
- No new product API; this Task proves architecture/security and produces review-ready evidence.

- [ ] **Step 1: Freeze architecture/security invariants**

Assert exactly 2 F13 tables, 1 `InsuranceService`, <=1 route file, exactly two F13 permissions, zero public routes/runtime deps/provider/coverage/checklist/document/notification/payment/claim-charge/history tables, no provider APIs/OCR/cron, no financial writes, tenant read-only/no insurance docs/claims, policy status derived and claim status exact allowlist.

- [ ] **Step 2: Run focused final suite**

Run all F13 tests plus lease ownership, auth/permissions, shell/basePath, CSRF/private-file suites and relevant F9/F11 financial regressions proving F13 did not alter their authority. Expected 0 failures/0 errors; record exact counts.

- [ ] **Step 3: Run full local QA**

Run `composer validate --strict` and `vendor/bin/phpunit --testdox`. Record PHP version, tests, assertions, failures, errors and skipped.

- [ ] **Step 4: Run Two Laws + patch-smell audit against exact F12-integrated base**

Prove `+2 tables`, `+1 domain class`, `+2 permissions`, `+0 public routes`, `+0 runtime deps`, and zero subordinate/future systems. Search for provider-specific branches, duplicate file helpers, persisted policy status, claim aliases, claim-charge wiring, reminder/email placeholders, F18 abstractions and raw financial writes. Classify `KEEP|REMOVE|CONVERGE|SIMPLIFY|IMPROVE|BLOCK` and repair only demonstrated issues.

- [ ] **Step 5: Define and verify PRODUCT_HEAD**

Review every changed product/test file. Push exact PRODUCT_HEAD only after focused/full QA + laws + patch-smell review PASS. Require CI PHP 8.3/8.4/8.5 PASS on exact SHA and record run/counts.

- [ ] **Step 6: Closure documentation**

Set SPEC to `F13 CLOSED / VERIFIED / NOT MERGED`, update README and create `F13_CLOSED_2026-10-13.md` as the single detailed final report containing Task evidence, schema/interfaces, policy/claim lifecycle, provider-neutral decisions, JSON/file lifecycle, concurrency repairs, tenant visibility, KISS/Zero Noise/Patch audit, security, QA/CI and complete changed-file inventory.

- [ ] **Step 7: Converge rolling checkpoint**

Remove it if closure report contains all unique evidence; otherwise keep only genuinely unique forensic data.

- [ ] **Step 8: Verify CLOSURE_HEAD**

Push exact closure HEAD and require fresh CI PHP 8.3/8.4/8.5 PASS with CI SHA equal to closure HEAD.

- [ ] **Step 9: Open/update review-ready PR**

Head `impl/f13-insurance-claims-20261013` -> base `main`; title `F13: insurance policies and claims`; body includes architecture budget, provider neutrality, private evidence, concurrency, tenant read-only model, Product CI, Closure CI, KISS, Zero Noise, Patch Stacking and exclusions. State `MERGE NOT AUTHORIZED UNTIL NEXT EXPLICIT CONTINUA`.

- [ ] **Step 10: STOP**

Expected handoff: `F13 CLOSED / VERIFIED / REVIEW-READY / NOT MERGED`; next action `CHATGPT/SOL FINAL FORENSIC AUDIT`. Do not merge and do not start F14.

---

## Plan Self-Review — 10 passes

1. **Spec coverage:** policies, coverages, lineage, cancellation, documents, claims, checklist, provider-neutral transitions, derived alerts, staff/tenant surfaces and privacy each map to an owning Task — PASS.
2. **KISS:** exactly 2 tables, 1 service, <=1 route file, 2 permissions and no subordinate data tables — PASS.
3. **Cero Ruido:** no provider/coverage/checklist/document/history/claim-charge/payment/notification systems or F16/F18 placeholders — PASS.
4. **Provider neutrality:** provider/product are data, radicado optional, checklist nonblocking, no SURA branches — PASS.
5. **Policy lifecycle:** status derived, lineage immutable, overlapping policies permitted, cancellation fail-closed, historical claims after cancellation tested — PASS.
6. **Claim lifecycle:** exact status transitions, nonfuture dates, terminal immutability and draft-only core edits are explicit REDs — PASS.
7. **Document lifecycle/concurrency:** private MIME/hash/size storage, owner-row locks, re-read JSON, append-only boundaries and cleanup/orphan behavior are explicit — PASS.
8. **Tenant/security:** ownership-first, masking exact, read-only summary, no docs/claims/coverage notes, staff permission split — PASS.
9. **Financial/future isolation:** no claims-to-charges, no financial writes, no notifications/cron/provider integrations/generic document authority — PASS.
10. **Closure discipline:** Product/CLOSURE exact CI, single final Markdown report, review-ready PR and hard stop before merge/F14 — PASS.

## Execution Gate

This PLAN is documentation only and remains blocked on F12 final audit + merge.

After user review/approval, the next artifact is the optimized Codex Luna master execution prompt for F13. Product implementation must still wait for F12-integrated `main`.