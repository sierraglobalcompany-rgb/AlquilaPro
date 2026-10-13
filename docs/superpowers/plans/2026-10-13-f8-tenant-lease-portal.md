# F8 Tenant Lease Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert one finalized F7 contract into one operational lease, create the tenant identity inside the existing auth system, mark the canonical unit occupied, and expose a private tenant portal without parallel auth, tenant tables, duplicated economics, or future modules.

**Architecture:** F8 adds exactly one table (`leases`), one domain class (`LeaseService`), and one F8 route file. Tenant identity remains `users.role = tenant`; all lease context is derived through `leases.contract_id -> contracts -> candidate/unit/property`. Activation serializes on the canonical unit row and changes existing `units.status` to `occupied`; termination records actor/time but never auto-releases the unit.

**Tech Stack:** PHP 8.3–8.5, Slim 4, MariaDB/MySQL, PDO, SSR HTML, existing minimal CSS/JS, PHPUnit 11.5, existing Dompdf F7 path only.

**Spec:** `docs/specs/F8_TENANT_LEASE_PORTAL_SPEC.md` — user-approved in chat on 2026-10-13 before this plan was authored.

## Global Constraints

- KISS: one app, one DB, one auth, one unit source, one contract source, one lease table.
- Cero Ruido order: `ELIMINAR -> UNIR / CONVERGER -> SIMPLIFICAR -> MEJORAR -> AGREGAR`.
- PHP remains `>=8.3 <8.6`; no Node/npm, Redis, worker, queue, SPA, or new runtime dependency.
- Schema delta exactly `+1` table: `leases`.
- No `tenants`, `lease_units`, `tenant_users`, `tenant_sessions`, persisted lease status, copied `unit_id`, copied `candidate_id`, copied economics, or copied contract dates.
- `users.role = tenant` is created only by F8 lease activation; generic `/admin/users` stays `admin|gestor`.
- Tenant uses existing `/login`, `AuthService`, `SessionGuard`, CSRF, `/account/password`, `/reset-password`.
- Temporary password is cryptographically random, hash-only in persistence, visible only in the immediate successful activation response, never re-displayed.
- Admin authority remains `contracts.manage`; add zero F8 permissions.
- F8 adds zero public routes.
- `units.status` remains the sole operational availability/occupation authority.
- Active lease prevents unit leaving `occupied` through the existing editor.
- Ending lease never changes unit status and never deletes/deactivates tenant user.
- F7 contract/PDF snapshot is immutable and private PDF checks are reused.
- Every product task: RED -> verify RED -> minimal GREEN -> focused GREEN -> commit.
- No F8 merge without later explicit user merge authorization.

## Review Focus

1. **Email collision:** existing `users.email` fails closed; never relabel/auto-link staff.
2. **Concurrent activation:** one unit can produce only one successful ACTIVE lease and no orphan tenant user.
3. **Lateral unit mutation:** ACTIVE lease blocks `occupied -> available|in_process|inactive|maintenance`, while edits that keep `occupied` remain possible.
4. **Tenant IDOR/PDF:** foreign lease/detail/PDF returns 404 and never exposes physical PDF name.
5. **Credential lifecycle:** temporary password forces change; loss uses existing reset flow, never re-display or second recovery path.

---

## Planned File Map

**Create**
- `database/migrations/008_create_leases.sql`
- `app/Leases/LeaseService.php`
- `app/Leases/routes.php`
- `resources/views/lease_activation_success.php`
- `resources/views/tenant_home.php`
- `resources/views/tenant_lease_detail.php`
- `tests/Integration/Leases/LeaseSchemaTest.php`
- `tests/Integration/Leases/LeaseActivationTest.php`
- `tests/Integration/Leases/LeaseLifecycleTest.php`
- `tests/Integration/Leases/LeaseAdminRoutesTest.php`
- `tests/Integration/Leases/TenantPortalRoutesTest.php`
- `tests/Integration/Leases/LeaseSecurityRegressionTest.php`

**Modify**
- `tests/Integration/Contracts/ContractSchemaTest.php`
- `app/Auth/UserService.php`
- `app/Auth/Permissions.php`
- `tests/Integration/Auth/UserServiceTest.php`
- `app/Properties/PropertyService.php`
- `tests/Integration/Properties/PropertyServiceTest.php`
- `bootstrap/app.php`
- `app/Contracts/routes.php`
- `resources/views/contract_detail.php`
- `app/Properties/routes.php`
- `resources/views/layouts/app.php`
- `tests/Integration/UI/ShellRoutesTest.php`
- closure-only: F8 SPEC gate, `README.md`, `docs/checkpoints/F8_CLOSED_2026-10-13.md`.

**Not planned**
- No package/Composer changes.
- No second auth/layout/session stack.
- No CSS delta unless a reproduced visual test proves it is necessary.

## Execution Preflight — only after user approves this plan

1. Use `superpowers:using-git-worktrees` if an isolated worktree is available; otherwise preserve the same branch isolation through native Git tooling.
2. Create `impl/f8-tenant-lease-portal-20261013` from the exact approved spec+plan commit.
3. On that implementation branch, converge the F8 SPEC banner/gate from `DRAFT / USER REVIEW REQUIRED` to `APPROVED BY USER / PLAN APPROVED / IMPLEMENTATION IN PROGRESS`; keep merge unauthorized.
4. Commit only that documentation state transition before Task 1 product work.
5. Verify implementation branch base still descends from `main @ f4a7e3f4d5ec7dc9cdc0f3a7e78777f98c85461d`; if main moved, inspect/rebase deliberately before product edits.

---

### Task 1: Freeze the single-table F8 schema

**Files:**
- Create: `database/migrations/008_create_leases.sql`
- Create: `tests/Integration/Leases/LeaseSchemaTest.php`
- Modify: `tests/Integration/Contracts/ContractSchemaTest.php`

**Interfaces:**
- Consumes: `contracts.id`, `users.id`, migration runner.
- Produces exact columns: `leases(id, contract_id, user_id, activated_by_user_id, activated_at, ended_by_user_id, ended_at)`.

- [ ] **Step 1: Write schema RED**

`LeaseSchemaTest` asserts exact columns, UNIQUE `contract_id`, indexes on `user_id` + both actor columns, FKs (`contract/users RESTRICT`, actors `SET NULL`), and absence of `tenants|lease_units|tenant_users|tenant_sessions` plus duplicated status/unit/candidate/economic/date columns.

- [ ] **Step 2: Converge F7's historical noise assertion**

In `ContractSchemaTest::testF7MigrationCreatesExactlyContractEngineTables()`, stop forbidding legitimate future `leases`/`tenants`; retain F7-specific forbidden tables such as `contract_clauses`, `contract_tasks`, `contract_annexes`, `contract_permissions`. F8's own test owns the new no-`tenants` rule.

- [ ] **Step 3: Verify RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases/LeaseSchemaTest.php tests/Integration/Contracts/ContractSchemaTest.php`

Expected: F8 schema test fails because migration 008/table `leases` is absent; unrelated F7 contracts remain green.

- [ ] **Step 4: Implement migration 008 only**

Create exactly the approved schema. No generic timestamps, no status, no copied contract/unit/candidate fields, no second table.

- [ ] **Step 5: Verify GREEN**

Run the same command; expected PASS.

- [ ] **Step 6: Commit**

`feat(f8): add minimal lease schema`

---

### Task 2: Add tenant identity inside existing auth

**Files:**
- Modify: `app/Auth/UserService.php`
- Modify: `app/Auth/Permissions.php`
- Modify: `tests/Integration/Auth/UserServiceTest.php`

**Interfaces:**
- Produces `UserService::createTenantIdentity(string $name, string $email): array{id:int,temporary_password:string}`.
- Preserves generic `UserService::create()` roles exactly `admin|gestor`.

- [ ] **Step 1: Write auth RED tests**

Assert tenant creation normalizes name/email; stores `role=tenant`, active=1, permissions NULL, must-change=1; generated password is >=12, distinct across creations, verifies against hash and is absent from DB/list projection; duplicate email fails with no mutation; generic create still rejects `tenant`; tenant rejects non-empty `setPermissions`; empty permissions remain idempotent; `Permissions::allows()` denies tenant even with tampered admin permissions.

- [ ] **Step 2: Verify RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Auth/UserServiceTest.php`

Expected: missing F8 identity/hardening failures.

- [ ] **Step 3: Implement tenant identity**

Generate a cryptographic temporary password (32 hex chars from `random_bytes(16)`), hash via `PASSWORD_DEFAULT`, use current email validation/normalization, INSERT only into existing `users`, return plaintext only from this invocation.

- [ ] **Step 4: Harden permission authority**

`Permissions::allows()` returns false for `role=tenant` after owner bypass. `UserService::setPermissions()` loads role; tenant + non-empty permissions throws; empty clears/remains NULL.

- [ ] **Step 5: Verify GREEN**

Run: `vendor/bin/phpunit --testdox tests/Integration/Auth/UserServiceTest.php tests/Integration/Auth/AuthServiceTest.php tests/Integration/Auth/AuthRoutesTest.php`

Expected: PASS; existing login/reset/change-password remains intact.

- [ ] **Step 6: Commit**

`feat(f8): create tenant identity in existing auth`

---

### Task 3: Implement atomic lease activation

**Files:**
- Create: `app/Leases/LeaseService.php`
- Create: `tests/Integration/Leases/LeaseActivationTest.php`

**Interfaces:**
- Constructor: `LeaseService::__construct(PDO $pdo, UserService $users)`.
- Produces `activate(int $contractId, int $activatedByUserId, int $now): array{lease:array,temporary_password:string}`.
- Produces `findByContract(int $contractId): ?array`.
- Lease projection includes IDs/actors/timestamps, derived `is_active`, tenant name/email, unit/property context, and frozen contract fields needed by views.

- [ ] **Step 1: Write activation RED tests**

Cover finalized-only; draft/generated rejected; unit `available|in_process` -> `occupied`; `occupied|inactive|maintenance` rejected; same contract twice rejected; different finalized contracts targeting same unit yield only one ACTIVE lease; email collision rolls back; simulated failure after user creation rolls back tenant/lease/unit; activation leaves candidate/screening/contract snapshot/hash/PDF untouched.

- [ ] **Step 2: Verify RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases/LeaseActivationTest.php`

Expected: missing `LeaseService` failure.

- [ ] **Step 3: Implement exact transaction order**

`BEGIN -> contract FOR UPDATE -> require finalized -> unit FOR UPDATE -> require available|in_process -> reject lease for contract -> reject active lease for unit via leases JOIN contracts -> read candidate name/email -> createTenantIdentity() on same PDO transaction -> INSERT lease -> guarded UPDATE units.status='occupied' -> COMMIT -> return credential`.

Any throwable rolls back and exposes no credential.

- [ ] **Step 4: Pin serialization structurally**

Regression assertion ensures unit `FOR UPDATE` appears before tenant identity/lease side effects so future refactors cannot move writes before serialization.

- [ ] **Step 5: Verify GREEN**

Run activation test + `tests/Integration/Contracts/ContractFinalizationTest.php`.

- [ ] **Step 6: Commit**

`feat(f8): activate lease atomically`

---

### Task 4: Terminate leases and protect canonical unit status

**Files:**
- Modify: `app/Leases/LeaseService.php`
- Modify: `app/Properties/PropertyService.php`
- Create: `tests/Integration/Leases/LeaseLifecycleTest.php`
- Modify: `tests/Integration/Properties/PropertyServiceTest.php`

**Interfaces:**
- Produces `LeaseService::end(int $leaseId, int $endedByUserId, int $now): array`.
- Property rule: ACTIVE lease permits unit update only if requested status is `occupied`.

- [ ] **Step 1: Write lifecycle RED**

Assert first end stores actor/time; second end fails and preserves originals; ending leaves unit occupied, user active, contract unchanged; ACTIVE lease rejects requested `available|in_process|inactive|maintenance`; non-status edits with `occupied` remain valid; after end, existing editor can select a valid next status.

- [ ] **Step 2: Verify RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases/LeaseLifecycleTest.php tests/Integration/Properties/PropertyServiceTest.php`

- [ ] **Step 3: Implement `end()` minimally**

Transaction + lease `FOR UPDATE` + require `ended_at IS NULL` + write actor/time once + commit. Touch nothing else.

- [ ] **Step 4: Guard `PropertyService::updateUnit()` directly**

One existence query against `leases JOIN contracts` is sufficient. If ACTIVE and validated requested status != `occupied`, throw. Do not inject LeaseService or add a boolean/cache field.

- [ ] **Step 5: Verify GREEN**

Run focused lifecycle/property suites; expected PASS.

- [ ] **Step 6: Commit**

`feat(f8): protect active lease lifecycle`

---

### Task 5: Expose activation/termination in existing contract context

**Files:**
- Create: `app/Leases/routes.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Contracts/routes.php`
- Modify: `resources/views/contract_detail.php`
- Create: `resources/views/lease_activation_success.php`
- Create: `tests/Integration/Leases/LeaseAdminRoutesTest.php`

**Interfaces:**
- One F8 route file owns:
  - `POST /admin/contracts/{id}/lease`
  - `POST /admin/leases/{id}/end`
- Both reuse `contracts.manage`, current auth, forced-password gate, global CSRF, basePath.
- Contract detail consumes `LeaseService::findByContract()`.

- [ ] **Step 1: Write admin route RED**

Assert login required; `contracts.view` without manage cannot mutate; CSRF enforced; activate CTA only finalized/no-lease/manager; successful activation returns app-shell HTML with tenant email + temp password exactly in immediate response, `Cache-Control: private, no-store`, no credential URL/redirect; revisiting contract never shows password; active lease exposes end action; ending redirects to contract; basePath works.

- [ ] **Step 2: Verify RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases/LeaseAdminRoutesTest.php`

- [ ] **Step 3: Wire shared services without a container layer**

`bootstrap/app.php`: instantiate `UserService` once, share with auth routes and `LeaseService`; pass LeaseService to contract routes + one new F8 route file.

- [ ] **Step 4: Add admin lease mutations**

Use a local guard matching contract routes. Activation renders `lease_activation_success` directly after commit; do not persist password in session/query/DB. End route 303s to owning contract detail.

- [ ] **Step 5: Converge contract detail**

Contract GET adds only lease view data. Existing `contract_detail.php` gets activate/state/end UI. No admin lease list/CRUD.

- [ ] **Step 6: Verify GREEN**

Run F8 admin route test + existing contract admin/finalization route tests.

- [ ] **Step 7: Commit**

`feat(f8): activate leases from contract detail`

---

### Task 6: Build private tenant portal and owned PDF route

**Files:**
- Modify: `app/Leases/LeaseService.php`
- Modify: `app/Leases/routes.php`
- Create: `resources/views/tenant_home.php`
- Create: `resources/views/tenant_lease_detail.php`
- Create: `tests/Integration/Leases/TenantPortalRoutesTest.php`

**Interfaces:**
- `listForUser(int $userId): array` — ACTIVE first, then newest activation.
- `findForUser(int $leaseId, int $userId): ?array` — ownership enforced in SQL.
- Routes: `GET /tenant`, `GET /tenant/leases/{id}`, `GET /tenant/leases/{id}/contract`.
- Owned PDF route obtains lease first, then calls existing `ContractService::privatePdf(int $contractId): ?array`.

- [ ] **Step 1: Write portal RED**

Assert no-session login redirect; forced-password redirect; authenticated non-tenant 403; tenant only sees own leases; foreign detail/PDF 404; ACTIVE first and ended history visible; apartment/parking contextual fields; PDF attachment/private no-store/nosniff/hash+size integrity/no physical filename; tampered/missing PDF 404; basePath and XSS escaping safe.

- [ ] **Step 2: Verify RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases/TenantPortalRoutesTest.php`

- [ ] **Step 3: Add safe joined projections only**

Join existing canonical tables; derive `is_active = ended_at === null`; persist nothing new.

- [ ] **Step 4: Add ownership-first routes**

Browser `{id}` identifies only lease. Session `user.id` is ownership authority. Never accept user/candidate/contract ownership fields from request.

- [ ] **Step 5: Add two minimal views**

Reuse existing responsive `surface`, `users-grid`, `user-card`, `status-pill`, `button` primitives. No F9–F21 tabs/cards.

- [ ] **Step 6: Verify GREEN**

Run tenant portal + existing contract PDF suites.

- [ ] **Step 7: Commit**

`feat(f8): add private tenant portal`

---

### Task 7: Converge root navigation and security architecture

**Files:**
- Modify: `app/Properties/routes.php`
- Modify: `resources/views/layouts/app.php`
- Modify: `tests/Integration/UI/ShellRoutesTest.php`
- Create: `tests/Integration/Leases/LeaseSecurityRegressionTest.php`

**Interfaces:**
- Authenticated tenant after forced-password gate: `/` -> `/tenant`.
- Staff `/` behavior unchanged.
- Same F3 app layout; tenant gets Inicio/Cuenta but no Apps launcher/dialog/admin cards.

- [ ] **Step 1: Write navigation/security RED**

Assert must-change tenant still goes password screen; changed tenant root redirects `/tenant`; staff root unchanged; tenant shell lacks Apps/admin links; tenant denied admin even with polluted permission data; architecture has exactly one F8 table, one production LeaseService, one F8 route file, zero tenant auth/table/join/public route/new dependency/future modules; activation source locks unit before side effects; credential success view has no password in URL/action.

- [ ] **Step 2: Verify RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/UI/ShellRoutesTest.php tests/Integration/Leases/LeaseSecurityRegressionTest.php`

- [ ] **Step 3: Modify current root route only**

After current auth/forced-password checks, tenant redirects to `$path('/tenant')`; public/staff branches remain exact.

- [ ] **Step 4: Make existing app layout tenant-aware**

For tenant, Inicio points `/tenant` and Apps button/dialog is omitted. No second layout/JS.

- [ ] **Step 5: Verify GREEN + visual contract**

Run the two suites plus `tests/Unit/UI/AssetContractTest.php`. If existing primitives pass, CSS delta is exactly 0. A CSS change requires its own reproduced RED and minimal GREEN.

- [ ] **Step 6: Commit**

`feat(f8): converge tenant navigation and security`

---

### Task 8: Full QA, Two Laws gate, closure docs, review-ready branch

**Files:**
- Modify after technical GREEN: `docs/specs/F8_TENANT_LEASE_PORTAL_SPEC.md`
- Modify after technical GREEN: `README.md`
- Create after technical GREEN: `docs/checkpoints/F8_CLOSED_2026-10-13.md`
- Modify this plan only if execution evidence forces a documented plan correction.

**Interfaces:**
- Produces one review-ready F8 implementation branch and one canonical CLOSED checkpoint.
- Does not merge.

- [ ] **Step 1: Run focused F8 suite**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases tests/Integration/Auth/UserServiceTest.php tests/Integration/Properties/PropertyServiceTest.php tests/Integration/UI/ShellRoutesTest.php`

Expected: 0 failures / 0 errors.

- [ ] **Step 2: Run Composer + full local suite**

Run `composer validate --strict` and `vendor/bin/phpunit --testdox`. Record actual counts; never predict them.

- [ ] **Step 3: Audit Two Laws against product diff**

Prove:
```text
NEW_TABLES: 1 (leases)
NEW_DOMAIN_CLASSES: 1 (LeaseService)
NEW_ROUTE_FILES: 1 (app/Leases/routes.php)
NEW_PERMISSIONS: 0
NEW_PUBLIC_ROUTES: 0
NEW_DIRECT_RUNTIME_DEPENDENCIES: 0
NEW_AUTH_SYSTEMS: 0
TENANTS_TABLE: 0
LEASE_UNITS_TABLE: 0
```

Inspect for dead code, duplicate statuses/economics/dates, future-module labels, second auth/session/recovery, PDF filename leak, credential persistence.

- [ ] **Step 4: Push product HEAD and verify PHP matrix**

On `impl/f8-tenant-lease-portal-20261013`, push current product HEAD and require CI PASS PHP 8.3/8.4/8.5. Record run ID and actual PHP 8.3 test/assertion counts.

- [ ] **Step 5: Review product HEAD before closure docs**

Compare against F7-integrated base; inspect every changed file and critical patch. Any blocker returns to the owning task with a new RED before docs closure.

- [ ] **Step 6: Converge closure documentation**

Only after product review is clean: update SPEC gate to implemented/verified, create `F8_CLOSED_2026-10-13.md` with RED/GREEN + CI evidence, update README to `F8 CLOSED / VERIFIED / NOT MERGED` and next action `FINAL PR REVIEW`.

- [ ] **Step 7: Commit closure documentation**

Commit: `docs(f8): close verified tenant lease portal`.

- [ ] **Step 8: Push exact closure HEAD and verify fresh CI**

Because docs changed HEAD, require a new CI run on that exact commit with PHP 8.3/8.4/8.5 PASS. Record the closure run ID. If it fails, F8 is not CLOSED.

- [ ] **Step 9: Open/update PR and perform final PR audit**

PR summary includes architecture budget, product + closure CI, Two Laws PASS, changed-file audit, and pending comments/reviews. Re-check PR head SHA matches the exact closure CI SHA.

- [ ] **Step 10: Stop at merge gate**

PR must state `MERGE NOT AUTHORIZED UNTIL NEXT EXPLICIT CONTINUA`. Do not merge in this task.

---

## Execution Order / Gate Discipline

```text
PREFLIGHT
  APPROVED PLAN -> IMPL BRANCH -> SPEC STATUS CONVERGENCE
TASK 1 SCHEMA
  RED -> GREEN -> COMMIT
TASK 2 TENANT IDENTITY
  RED -> GREEN -> COMMIT
TASK 3 ACTIVATION
  RED -> GREEN -> COMMIT
TASK 4 END + UNIT AUTHORITY
  RED -> GREEN -> COMMIT
TASK 5 ADMIN CONTEXT
  RED -> GREEN -> COMMIT
TASK 6 TENANT PORTAL
  RED -> GREEN -> COMMIT
TASK 7 NAV + SECURITY/VISUAL
  RED -> GREEN -> COMMIT
TASK 8 FULL QA + TWO LAWS
  PRODUCT CI -> REVIEW -> CLOSURE DOC COMMIT -> EXACT-HEAD CI -> PR AUDIT -> STOP BEFORE MERGE
```

Never combine multiple REDs into one broad patch. If implementation discovers a SPEC contradiction, stop the owning task, document evidence, and reopen the smallest design decision instead of layering a workaround.

## Self-Review Result

- **Spec coverage:** schema, auth, activation, termination, unit authority, admin entry, credential lifecycle, portal, ownership, PDF, shell, subfolder, security, Two Laws, QA, closure all map to tasks.
- **Step scan:** each product task has a checkable RED, minimal GREEN, verification, and commit; closure CI now runs only after its exact documentation commit exists.
- **Type consistency:** later tasks consume only interfaces defined earlier; no repository/DTO/factory is assumed.
- **Review Focus:** all five failure classes map to explicit tests.
- **Test isolation:** existing `DatabaseIsolationExtension` drops every DB table after each test, so migration 008 does not require copy-pasting `leases` into legacy cleanup lists.
- **Proportion:** the plan fixes decisions/signatures/tests/order without transcribing implementation bodies.
- **KISS:** one table + one service + one route file + existing authorities; no future scaffolding.

## Approval Gate

```text
F7: CLOSED / VERIFIED / MERGED
F8_SCOPE_PRE_KISS: APPROVED
F8_SPEC: APPROVED BY USER 2026-10-13
F8_PLAN: APPROVED BY USER 2026-10-13
F8_IMPLEMENTATION: COMPLETE / VERIFIED
F8_QA: VERIFIED
F8_TWO_LAWS: PASS
F8_STATUS: CLOSED / VERIFIED / NOT MERGED
MERGE_AUTHORIZED: NO
NEXT: FINAL F8 FORENSIC REVIEW / MERGE GATE
```

The former `USER REVIEW REQUIRED` / `NOT AUTHORIZED` footer was stale: the SPEC approval gate was committed as `044cb9b`, and the user subsequently directed execution with “ejecuta el prompt.” This closure records that authorization and the completed gates; it does not change the approved design.

**Recommended execution method: Native.** The eight tasks are sequential and share the same PDO/auth/lease interfaces, and the user has explicitly preferred ChatGPT to implement most work directly. Use an independent whole-branch review at the end; do not invoke Codex/subagents unless a demonstrated blocker makes that extra context materially useful.
