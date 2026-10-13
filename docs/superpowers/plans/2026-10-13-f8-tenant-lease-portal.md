# F8 Tenant Lease Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert one finalized F7 contract into one operational lease, create the tenant identity inside the existing auth system, mark the canonical unit occupied, and expose a private tenant portal without introducing parallel auth, tenant tables, duplicated economics, or future modules.

**Architecture:** F8 adds exactly one table (`leases`) and one domain class (`LeaseService`). Tenant identity remains `users.role = tenant`; lease context is derived through `leases.contract_id -> contracts -> candidate/unit/property`, and the portal reuses F2/F3 auth/session/CSRF/layout plus F7 private PDF evidence. Activation serializes on the canonical unit row and changes the existing `units.status` to `occupied`; termination records actor/time but never auto-releases the unit.

**Tech Stack:** PHP 8.3–8.5, Slim 4, MariaDB/MySQL, PDO, SSR HTML, minimal existing CSS/JS, PHPUnit 11.5, Dompdf only through the existing F7 contract service.

**Spec:** `docs/specs/F8_TENANT_LEASE_PORTAL_SPEC.md` — user-approved in chat on 2026-10-13 before this plan was authored.

## Global Constraints

- KISS: one app, one DB, one auth, one unit source, one contract source, one lease table.
- Cero Ruido order: `ELIMINAR -> UNIR / CONVERGER -> SIMPLIFICAR -> MEJORAR -> AGREGAR`.
- Production remains PHP `>=8.3 <8.6`; no Node/npm, Redis, workers, queues, SPA, or new runtime dependency.
- F8 schema delta is exactly `+1` table: `leases`.
- No `tenants`, `lease_units`, `tenant_users`, `tenant_sessions`, `lease_status`, copied `unit_id`, copied `candidate_id`, copied contract economics, or copied contract dates.
- `users.role = tenant` is created only by the lease activation path; generic `/admin/users` continues to accept only `admin|gestor`.
- Tenant credentials use existing `/login`, `AuthService`, `SessionGuard`, `/account/password`, and `/reset-password`.
- Temporary password is cryptographically random, stored hash-only, shown only in the immediate successful activation response, and never re-displayed.
- Administrative authority remains `contracts.manage`; add zero F8 permissions.
- F8 adds zero public routes.
- `units.status` remains the only operational availability/occupation authority.
- While a lease is active, the canonical unit cannot leave `occupied` through the existing unit editor.
- Ending a lease never changes unit status automatically and never deletes/deactivates the tenant user.
- Contract/PDF snapshot stays immutable and is served through F7 integrity checks.
- Every product task is TDD: RED -> verify RED -> minimal GREEN -> focused verification -> commit.
- Do not merge F8 without the later explicit user merge gate.

## Review Focus

1. **Email collision at activation:** an existing `users.email` must fail closed; never relabel or auto-link a staff account.
2. **Concurrent activation of one unit:** unit locking plus active-lease check must permit only one successful active lease and no orphan tenant user.
3. **Lateral unit edit:** an active lease must prevent `occupied -> available|in_process|inactive|maintenance` while allowing non-status edits that keep `occupied`.
4. **Tenant IDOR / PDF access:** a tenant must receive 404 for another tenant's lease or contract PDF, with no physical filename leakage.
5. **Credential lifecycle:** temporary password forces change; if lost, recovery uses the existing reset flow, never password re-display or a second recovery mechanism.

---

## Planned File Map

**Create**
- `database/migrations/008_create_leases.sql` — the only F8 table.
- `app/Leases/LeaseService.php` — activation, termination, and safe lease projections.
- `app/Leases/routes.php` — admin activation/termination plus private tenant routes; one F8 route file total.
- `resources/views/lease_activation_success.php` — one-time credential response.
- `resources/views/tenant_home.php` — tenant lease list/history.
- `resources/views/tenant_lease_detail.php` — contextual apartment/parking detail.
- `tests/Integration/Leases/LeaseSchemaTest.php`.
- `tests/Integration/Leases/LeaseActivationTest.php`.
- `tests/Integration/Leases/LeaseLifecycleTest.php`.
- `tests/Integration/Leases/LeaseAdminRoutesTest.php`.
- `tests/Integration/Leases/TenantPortalRoutesTest.php`.
- `tests/Integration/Leases/LeaseSecurityRegressionTest.php`.

**Modify**
- `tests/Integration/Contracts/ContractSchemaTest.php` — stop treating future legitimate `leases` as F7 noise; keep F7-specific noise assertions.
- `app/Auth/UserService.php` — tenant-only identity creation and tenant permission guard.
- `app/Auth/Permissions.php` — tenant fails closed even if `permissions_json` is tampered.
- `tests/Integration/Auth/UserServiceTest.php` — tenant identity, collision, permissions.
- `app/Properties/PropertyService.php` — active lease status guard using the canonical status field.
- `tests/Integration/Properties/PropertyServiceTest.php` — active lease status guard.
- `bootstrap/app.php` — instantiate one shared `UserService`, one `LeaseService`, wire one lease route file.
- `app/Contracts/routes.php` — provide existing contract detail with lease context only; no lease mutations here.
- `resources/views/contract_detail.php` — activate/end lease UI inside existing contract context.
- `app/Properties/routes.php` — tenant root dispatch to `/tenant`.
- `resources/views/layouts/app.php` — tenant navigation reuses shell but omits Apps launcher.
- `tests/Integration/UI/ShellRoutesTest.php` — tenant root/shell regression.
- `README.md`, F8 SPEC status, and `docs/checkpoints/F8_CLOSED_2026-10-13.md` only at verified closure.

**Not planned**
- No Composer/package changes.
- No second auth/layout/session stack.
- No CSS change unless a reproducible visual regression proves one is required; reuse existing responsive primitives first.

---

### Task 1: Freeze the single-table F8 schema

**Files:**
- Create: `database/migrations/008_create_leases.sql`
- Create: `tests/Integration/Leases/LeaseSchemaTest.php`
- Modify: `tests/Integration/Contracts/ContractSchemaTest.php`

**Interfaces:**
- Consumes: existing `contracts.id`, `users.id`, migration runner.
- Produces: table `leases(id, contract_id, user_id, activated_by_user_id, activated_at, ended_by_user_id, ended_at)`.

- [ ] **Step 1: Write `LeaseSchemaTest` RED**

Tests must assert:
- `leases` exists after all migrations;
- exact column list is the seven columns above, with no `status`, `unit_id`, `candidate_id`, rent, administration, start/end contract dates, or duplicated timestamps;
- `contract_id` is UNIQUE;
- indexes exist on `user_id`, `activated_by_user_id`, `ended_by_user_id`;
- FKs are `contract_id -> contracts.id RESTRICT`, `user_id -> users.id RESTRICT`, both actor FKs `SET NULL`;
- `tenants`, `lease_units`, `tenant_users`, `tenant_sessions` do not exist.

- [ ] **Step 2: Converge the F7 schema regression before running RED**

In `ContractSchemaTest::testF7MigrationCreatesExactlyContractEngineTables()`, remove only `leases`/`tenants` from the list of forbidden *future* tables; keep F7-specific forbidden tables such as `contract_clauses`, `contract_tasks`, `contract_annexes`, `contract_permissions`.

- [ ] **Step 3: Run the schema RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases/LeaseSchemaTest.php tests/Integration/Contracts/ContractSchemaTest.php`

Expected: FAIL because migration 008/table `leases` does not exist; no unrelated F1–F7 failure.

- [ ] **Step 4: Add migration 008 only**

Create exactly the schema from the approved SPEC. Do not add generic `created_at`, `updated_at`, persisted status, copied unit/candidate/economics/date columns, or a second table.

- [ ] **Step 5: Verify GREEN**

Run the same focused command. Expected: PASS.

- [ ] **Step 6: Commit**

Commit message: `feat(f8): add minimal lease schema`.

---

### Task 2: Add tenant identity inside existing auth

**Files:**
- Modify: `app/Auth/UserService.php`
- Modify: `app/Auth/Permissions.php`
- Modify: `tests/Integration/Auth/UserServiceTest.php`

**Interfaces:**
- Produces: `UserService::createTenantIdentity(string $name, string $email): array{id:int,temporary_password:string}`.
- Preserves: `UserService::create(...)` allowed roles remain exactly `admin|gestor`.
- Preserves: existing `AuthService`, reset, change-password, sessions, CSRF.

- [ ] **Step 1: Add auth RED tests**

Add tests named around these contracts:
- tenant identity normalizes name/email, stores `role=tenant`, active=1, `permissions_json=NULL`, `must_change_password=1`;
- returned temporary password length is >=12, differs across two creations, verifies against stored hash, and plaintext is absent from DB/list projection;
- duplicate email fails with `RuntimeException` and does not mutate the existing account;
- generic `create(..., 'tenant', ...)` still rejects the role;
- `setPermissions(tenant, non-empty)` fails and leaves permissions empty;
- `setPermissions(tenant, [])` is allowed/idempotent;
- `Permissions::allows()` returns false for a tenant even if an in-memory/tampered permissions array contains `contracts.manage`.

- [ ] **Step 2: Run RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Auth/UserServiceTest.php`

Expected: FAIL on missing tenant identity API / tenant permission hardening.

- [ ] **Step 3: Implement minimal tenant identity API**

Use a server-generated cryptographic password (32 hex chars from `random_bytes(16)` is sufficient), `PASSWORD_DEFAULT`, existing email normalization/validation semantics, and one INSERT into `users`. Return plaintext only from this method invocation.

- [ ] **Step 4: Harden tenant permissions without changing the catalog**

`Permissions::allows()` must return false immediately for `role=tenant` (owner bypass remains first). `UserService::setPermissions()` must load role and reject non-empty permissions for tenant; an empty permission request clears/remains NULL.

- [ ] **Step 5: Verify GREEN**

Run: `vendor/bin/phpunit --testdox tests/Integration/Auth/UserServiceTest.php tests/Integration/Auth/AuthServiceTest.php tests/Integration/Auth/AuthRoutesTest.php`

Expected: PASS; existing email login/password/reset behavior intact.

- [ ] **Step 6: Commit**

Commit message: `feat(f8): create tenant identity in existing auth`.

---

### Task 3: Implement atomic lease activation

**Files:**
- Create: `app/Leases/LeaseService.php`
- Create: `tests/Integration/Leases/LeaseActivationTest.php`

**Interfaces:**
- Constructor: `LeaseService::__construct(PDO $pdo, UserService $users)`.
- Produces: `activate(int $contractId, int $activatedByUserId, int $now): array{lease:array,temporary_password:string}`.
- Produces: `findByContract(int $contractId): ?array`.
- `lease` normalized fields must include at least `id`, `contract_id`, `user_id`, actor/timestamps, derived `is_active`, tenant email/name, unit/property context, and frozen contract economics/dates needed by later views.

- [ ] **Step 1: Write activation RED tests**

Cover:
- only `finalized` contract activates; `draft|generated` reject;
- unit `available|in_process` activates and ends `occupied`;
- unit `occupied|inactive|maintenance` rejects;
- same contract cannot activate twice;
- second finalized contract targeting the same unit cannot create a second ACTIVE lease;
- email collision rolls back with no lease, no new user, no unit mutation;
- forced simulated failure after user creation proves transaction rollback leaves no tenant user/lease/status change;
- activation does not mutate candidate status, screening row, contract status, snapshot, hash, or PDF metadata.

- [ ] **Step 2: Run RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases/LeaseActivationTest.php`

Expected: FAIL because `LeaseService` does not exist.

- [ ] **Step 3: Implement transaction/lock order exactly**

Inside `activate()`:
1. begin transaction;
2. `SELECT candidate_id, unit_id, status FROM contracts ... FOR UPDATE`;
3. require `finalized`;
4. `SELECT status FROM units ... FOR UPDATE`;
5. require `available|in_process`;
6. reject existing lease for contract;
7. reject ACTIVE lease for that unit via `leases JOIN contracts`;
8. read canonical candidate `full_name,email`;
9. call `UserService::createTenantIdentity()` on the same PDO transaction;
10. insert lease with `activated_at` and actor;
11. update canonical unit to `occupied` with guarded WHERE;
12. commit;
13. only then return the held temporary password.

On any throwable: rollback and return no credential.

- [ ] **Step 4: Add structural concurrency regression inside the activation test**

Assert the product source contains the unit `FOR UPDATE` lock before tenant identity creation/lease INSERT, so future refactors cannot move side effects ahead of serialization.

- [ ] **Step 5: Verify GREEN**

Run the activation test plus `tests/Integration/Contracts/ContractFinalizationTest.php`.

Expected: PASS.

- [ ] **Step 6: Commit**

Commit message: `feat(f8): activate lease atomically`.

---

### Task 4: Terminate leases and protect `units.status`

**Files:**
- Modify: `app/Leases/LeaseService.php`
- Modify: `app/Properties/PropertyService.php`
- Create: `tests/Integration/Leases/LeaseLifecycleTest.php`
- Modify: `tests/Integration/Properties/PropertyServiceTest.php`

**Interfaces:**
- Produces: `LeaseService::end(int $leaseId, int $endedByUserId, int $now): array`.
- Property contract: an ACTIVE lease allows unit updates only when requested status remains `occupied`.

- [ ] **Step 1: Write lifecycle RED tests**

Assert:
- ACTIVE lease becomes ended with exactly `ended_at` + `ended_by_user_id`;
- second termination fails closed and does not rewrite actor/time;
- termination leaves unit status unchanged (`occupied`), user active, contract unchanged;
- while lease ACTIVE, `PropertyService::updateUnit()` rejects requested status `available|in_process|inactive|maintenance`;
- while ACTIVE, non-status field changes are allowed when submitted status remains `occupied`;
- after end, existing unit editor can manually choose a valid next status.

- [ ] **Step 2: Run RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases/LeaseLifecycleTest.php tests/Integration/Properties/PropertyServiceTest.php`

Expected: FAIL on missing end method / active-lease guard.

- [ ] **Step 3: Implement `end()` minimally**

Begin transaction, lock lease `FOR UPDATE`, require `ended_at IS NULL`, set actor/time once, commit. Do not touch user, contract, candidate, screening, or unit.

- [ ] **Step 4: Add canonical status guard to `PropertyService::updateUnit()`**

If an ACTIVE lease exists for the unit and validated requested status is not `occupied`, throw `RuntimeException`. Do not introduce a boolean/cache column or LeaseService dependency into PropertyService; one direct existence query against canonical tables is enough.

- [ ] **Step 5: Verify GREEN**

Run focused lifecycle/property suites. Expected: PASS.

- [ ] **Step 6: Commit**

Commit message: `feat(f8): protect active lease lifecycle`.

---

### Task 5: Expose activation/termination in the existing contract context

**Files:**
- Create: `app/Leases/routes.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Contracts/routes.php`
- Modify: `resources/views/contract_detail.php`
- Create: `resources/views/lease_activation_success.php`
- Create: `tests/Integration/Leases/LeaseAdminRoutesTest.php`

**Interfaces:**
- Admin mutation routes in the single F8 route file:
  - `POST /admin/contracts/{id}/lease` — activate;
  - `POST /admin/leases/{id}/end` — end.
- Both use existing `contracts.manage`, auth guard, forced-password gate, global CSRF, and basePath.
- Contract detail consumes `LeaseService::findByContract()`.

- [ ] **Step 1: Write route/UI RED tests**

Assert:
- unauthenticated redirects to `/login`;
- viewer with `contracts.view` but not manage cannot activate/end;
- CSRF remains globally enforced;
- activation CTA exists only for finalized contract with no lease and manager permission;
- activation success response shows normalized tenant email + temporary password once, uses app shell, `Cache-Control: private, no-store`, and does not place password in URL/redirect;
- revisiting contract after activation shows lease state but never the temporary password;
- ending active lease redirects back to contract detail and removes end action after success;
- basePath/subfolder works.

- [ ] **Step 2: Run RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases/LeaseAdminRoutesTest.php`

Expected: FAIL because routes/views/wiring do not exist.

- [ ] **Step 3: Wire one shared `UserService` and one `LeaseService`**

In `bootstrap/app.php`, instantiate `UserService` once, pass it both to auth routes and `LeaseService`; pass `LeaseService` to contract routes and the one new lease route file. Add no container/factory layer.

- [ ] **Step 4: Add admin lease routes**

Use a local guard mirroring the existing contract guard. Activation directly renders `lease_activation_success` after successful commit so the password is not persisted in session/query/DB. End route can 303 back to `/admin/contracts/{contract_id}`.

- [ ] **Step 5: Converge existing contract detail UI**

`app/Contracts/routes.php` adds only `lease` view data from `findByContract()`. `contract_detail.php` owns the CTA/status/end forms; do not add a new admin lease CRUD/list.

- [ ] **Step 6: Verify GREEN**

Run lease admin routes plus existing contract admin/finalization route suites. Expected: PASS.

- [ ] **Step 7: Commit**

Commit message: `feat(f8): activate leases from contract detail`.

---

### Task 6: Build the private tenant portal and owned PDF path

**Files:**
- Modify: `app/Leases/LeaseService.php`
- Modify: `app/Leases/routes.php`
- Create: `resources/views/tenant_home.php`
- Create: `resources/views/tenant_lease_detail.php`
- Create: `tests/Integration/Leases/TenantPortalRoutesTest.php`

**Interfaces:**
- Produces: `listForUser(int $userId): array` ordered ACTIVE first then newest activation.
- Produces: `findForUser(int $leaseId, int $userId): ?array` using ownership in SQL.
- Private routes:
  - `GET /tenant`;
  - `GET /tenant/leases/{id}`;
  - `GET /tenant/leases/{id}/contract`.
- PDF route obtains owned lease first, then delegates contract evidence to existing `ContractService::privatePdf(int $contractId): ?array`.

- [ ] **Step 1: Write portal RED tests**

Assert:
- no session -> login redirect;
- must-change-password tenant -> `/account/password`;
- non-tenant authenticated user -> 403 on tenant routes;
- tenant sees only own lease(s), ACTIVE first;
- another tenant's lease detail and PDF both return 404;
- apartment view shows apartment fields and no parking-only noise;
- parking view shows code/location/level and no bedroom/bathroom module;
- ended historical lease remains visible to its owner;
- PDF response is attachment, `private, no-store`, `nosniff`, validates bytes/hash/size, exposes no `pdf_stored_name`;
- tampered/missing PDF returns 404;
- basePath and escaped stored values remain safe.

- [ ] **Step 2: Run RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases/TenantPortalRoutesTest.php`

Expected: FAIL because portal methods/routes/views do not exist.

- [ ] **Step 3: Add safe joined projections only**

Queries may join `leases -> contracts -> candidates -> units -> properties -> users`, but must not add duplicate persistence. Normalize derived `is_active = ended_at === null`; expose only fields required by the approved portal.

- [ ] **Step 4: Add tenant routes with ownership-first checks**

Never accept browser-supplied user/candidate/contract ownership. Route `{id}` identifies only the lease; current session user is the ownership key. Use 404 for nonexistent/foreign lease.

- [ ] **Step 5: Add two minimal views**

Reuse existing `surface`, `users-grid`, `user-card`, `status-pill`, `button` primitives. Do not add future tabs/cards for F9–F21.

- [ ] **Step 6: Verify GREEN**

Run tenant portal suite plus existing contract PDF tests. Expected: PASS.

- [ ] **Step 7: Commit**

Commit message: `feat(f8): add private tenant portal`.

---

### Task 7: Converge root navigation and close security architecture gaps

**Files:**
- Modify: `app/Properties/routes.php`
- Modify: `resources/views/layouts/app.php`
- Modify: `tests/Integration/UI/ShellRoutesTest.php`
- Create: `tests/Integration/Leases/LeaseSecurityRegressionTest.php`

**Interfaces:**
- Root dispatch: authenticated `role=tenant` after forced-password gate -> `/tenant`; staff behavior unchanged.
- Tenant app shell: same layout/branding/account/logout system, but no Apps launcher/dialog/admin cards.

- [ ] **Step 1: Write navigation/security RED tests**

Assert:
- tenant login with `must_change_password=1` still goes to password change before portal;
- after password change, tenant root redirects `/tenant`;
- owner/admin/gestor root remains existing home;
- tenant shell contains Inicio/Cuenta and no Apps launcher or `/admin/` links;
- tenant remains denied from admin routes even if DB/in-memory permission data is polluted;
- source architecture has exactly one F8 table and one production class under `app/Leases`, no `TenantAuthService`, `tenant-login`, `tenants`, `lease_units`, F8 public route, financial/future modules, or new runtime dependency;
- lease activation source locks canonical unit before user/lease side effects;
- temporary-password success view never embeds credential in a link/form action/loggable URL.

- [ ] **Step 2: Run RED**

Run: `vendor/bin/phpunit --testdox tests/Integration/UI/ShellRoutesTest.php tests/Integration/Leases/LeaseSecurityRegressionTest.php`

Expected: navigation assertions fail before root/layout convergence.

- [ ] **Step 3: Modify existing root route only**

After authentication and forced-password guard, redirect tenant to `$path('/tenant')`; leave public visitor and staff branches unchanged.

- [ ] **Step 4: Make the existing app layout tenant-aware**

Use `role=tenant` condition to point Inicio at `/tenant` and omit Apps button/dialog. Do not create a second layout or JS path.

- [ ] **Step 5: Verify GREEN and visual contract**

Run the two suites above plus `tests/Unit/UI/AssetContractTest.php` if present under the repo naming convention. If existing primitives pass, make CSS delta `0`; only a reproduced failure may justify a minimal CSS change and its own RED/GREEN evidence.

- [ ] **Step 6: Commit**

Commit message: `feat(f8): converge tenant navigation and security`.

---

### Task 8: Full QA, Two Laws gate, checkpoint, and review-ready branch

**Files:**
- Modify: `docs/specs/F8_TENANT_LEASE_PORTAL_SPEC.md` — converge status from user-approved draft to implemented/verified only after evidence exists.
- Modify: `README.md` — F8 CLOSED / VERIFIED / NOT MERGED state only after all gates pass.
- Create: `docs/checkpoints/F8_CLOSED_2026-10-13.md`.
- Modify: this plan only if execution evidence reveals a plan correction; never rewrite history silently.

**Interfaces:**
- Produces: review-ready F8 branch and one canonical CLOSED checkpoint.
- Does not merge.

- [ ] **Step 1: Run focused F8 suite locally**

Run: `vendor/bin/phpunit --testdox tests/Integration/Leases tests/Integration/Auth/UserServiceTest.php tests/Integration/Properties/PropertyServiceTest.php tests/Integration/UI/ShellRoutesTest.php`

Expected: 0 failures / 0 errors.

- [ ] **Step 2: Run Composer and full local suite**

Run:
- `composer validate --strict`
- `vendor/bin/phpunit --testdox`

Expected: PASS with all F1–F8 tests; record exact test/assertion counts rather than predicting them.

- [ ] **Step 3: Run explicit Two Laws audit against the diff**

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

Also inspect for dead code, duplicate status/economics/dates, future module labels, second auth/session/recovery paths, physical PDF filename exposure, or credential persistence.

- [ ] **Step 4: Push implementation branch and verify CI matrix**

Execution branch must be `impl/f8-tenant-lease-portal-20261013` (created from the approved spec+plan commit). GitHub CI must pass PHP 8.3 / 8.4 / 8.5. Record run ID and exact PHP 8.3 test/assertion counts.

- [ ] **Step 5: Perform full branch review before docs closure**

Compare implementation branch against its F7-integrated base. Review every changed filename and critical patch; confirm no accidental schema/dependency/public-route expansion and no unresolved PR comments/reviews.

- [ ] **Step 6: Converge documentation only after technical GREEN**

Update F8 SPEC gate to approved/implemented/verified, write `F8_CLOSED_2026-10-13.md` with RED/GREEN commit and CI evidence, and update README to `F8 CLOSED / VERIFIED / NOT MERGED` with next action `FINAL PR REVIEW`.

- [ ] **Step 7: Re-run CI on exact closure HEAD**

Because docs commits change HEAD, require a fresh exact-head PHP 8.3/8.4/8.5 CI run before calling F8 CLOSED/VERIFIED.

- [ ] **Step 8: Open/update PR and stop at merge gate**

PR should summarize architecture budget, test evidence, Two Laws PASS, and explicitly state `MERGE NOT AUTHORIZED UNTIL NEXT EXPLICIT CONTINUA`.

- [ ] **Step 9: Commit closure docs**

Commit message: `docs(f8): close verified tenant lease portal`.

---

## Execution Order / Gate Discipline

```text
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
TASK 8 FULL QA + TWO LAWS + CLOSED CHECKPOINT
  CI 8.3/8.4/8.5 -> REVIEW -> STOP BEFORE MERGE
```

Do not combine multiple REDs into one broad patch. If a task discovers a contract/spec contradiction, stop that task, document the evidence, and re-open the smallest design decision rather than layering a workaround.

## Self-Review Result

- **Spec coverage:** schema, auth convergence, activation atomicity, termination, unit authority, admin entry point, credential handling, private portal, ownership, PDF, shell, subfolder, security, Two Laws, QA, closure all map to tasks above.
- **Type/interface consistency:** later tasks consume only `LeaseService` and `UserService` interfaces defined earlier; no repository/DTO/factory layer is assumed.
- **Review Focus coverage:** all five risk classes have explicit tests in Tasks 2–7.
- **Proportion:** plan decides signatures/tests/files/order but does not transcribe implementation bodies.
- **KISS check:** implementation remains one table + one service + one route file + existing authorities; no future-module scaffolding.

## Approval Gate

```text
F7: CLOSED / VERIFIED / MERGED
F8_SCOPE_PRE_KISS: APPROVED
F8_SPEC: APPROVED BY USER 2026-10-13
F8_PLAN: USER REVIEW REQUIRED
F8_IMPLEMENTATION: NOT AUTHORIZED
NEXT: USER APPROVAL OF THIS PLAN + EXECUTION METHOD
```

Recommended execution method: **Native** — the eight tasks are sequential and share the same PDO/auth/lease interfaces, and the user has explicitly preferred ChatGPT to implement most work directly. Use an independent whole-branch review at the end; do not invoke Codex/subagents unless a demonstrated blocker makes that additional context materially useful.