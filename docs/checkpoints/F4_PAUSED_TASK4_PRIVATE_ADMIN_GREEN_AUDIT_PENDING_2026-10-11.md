# Alquila Pro — F4 checkpoint: Task 4 private admin GREEN, audit pending

Date: 2026-10-11 / 2026-10-12 UTC
Branch: `impl/f4-properties-catalog-20261011`
PR: #7 `F4: Properties, units and public catalog`
Base: `main` @ `940ebfae25a05d1589fe1ab5ceeaf667c2d08345`
Product HEAD before this checkpoint: `3f8d4a12ee3f2196a83fbcf0d3d674604f7c0925`

## 1. STOP CONDITION

This checkpoint is intentionally created before any further product change. Resume from the state described here. Do not repeat Task 3 or the already-persisted part of Task 4.

PR #7 remains OPEN, DRAFT, MERGEABLE, NOT MERGED. No F4 merge is authorized yet.

## 2. VERIFIED CI AT PRODUCT HEAD

GitHub Actions run: `38200619984`
Head SHA: `3f8d4a12ee3f2196a83fbcf0d3d674604f7c0925`

- PHP 8.3: PASS
- PHP 8.4: PASS
- PHP 8.5: PASS
- Composer validation: PASS in all jobs
- PHPUnit step: PASS in all jobs

Do not replace this evidence with older RED runs.

## 3. F4 TASK STATUS

### Task 1 — Schema + permissions
GREEN.

Implemented and verified:
- migration `004_create_properties_units_media.sql`;
- exactly 3 F4 tables: `properties`, `units`, `unit_media`;
- exactly 2 F4 permissions: `properties.view`, `properties.manage`;
- `public_key` follows the authoritative F4 SPEC: `CHAR(24)` / `bin2hex(random_bytes(12))`;
- no extra catalog table, availability boolean, package or auth system.

### Task 2 — PropertyService / inventory domain
GREEN.

Implemented and verified:
- property CRUD/inactivation;
- one `units` model for `apartment|parking`;
- canonical unit states;
- parking -> apartment same-property validation;
- server-generated public key;
- public-query skeleton filtered by active property + available status + publication intent.

### Task 3 — secure media + publication invariants
GREEN.

Implemented and verified:
- secure photos outside webroot;
- actual MIME validation;
- 10 MiB photo limit;
- random stored filenames;
- rollback without orphan files;
- safe removal;
- HTTPS-only `video_link`;
- unique cover photo by service/transaction;
- exact owned-media reorder without duplicate/foreign IDs;
- publication completeness: price > 0, description, requirements and at least one photo before `is_public = 1`;
- public reads disappear immediately when unit/property state makes the unit unavailable.

Important historical note: an early GREEN used media type `video`; it was corrected to canonical `video_link`. Do not reintroduce `video`.

### Task 4 — private administration UI/routes
FUNCTIONALLY GREEN AT CURRENT HEAD, BUT AUDIT NOT CLOSED.

Already persisted and passing CI:
- `app/Properties/routes.php`;
- private property list/detail routes;
- create/update/activate-deactivate property;
- create/update unit;
- photo upload;
- video link creation;
- cover selection;
- media reorder;
- media deletion;
- authenticated private photo delivery;
- `resources/views/properties.php`;
- `resources/views/property_detail.php`;
- `resources/views/unit_detail.php`;
- permission guards `properties.view/manage`;
- must-change-password guard;
- 422 mutation errors;
- CSRF middleware still globally active.

`tests/Integration/Properties/PropertiesRoutesTest.php` exercises the main private administration routes and passes at the recorded product HEAD.

## 4. TASK 4 AUDIT FINDINGS — NEXT WORK

Two concrete gaps were found after the green CI. Close these before declaring Task 4 CLOSED:

### GAP A — Apps launcher does not expose the now-real Properties app

Current `resources/views/layouts/app.php` knows only:
- Users;
- Appearance.

It does NOT calculate `properties.view` or render `/admin/properties`.

Current F3 shell tests still explicitly assert that `Propiedades` is absent. That assertion is now obsolete because the route is real.

Required TDD continuation:
1. RED: update/add ShellRoutes tests so a user with `properties.view` (and owner bypass) sees `Propiedades` -> `/admin/properties`.
2. RED: user without `properties.view` does not see it.
3. Preserve zero dead/future links (`Cartera`, `Solicitudes`, etc. remain absent).
4. GREEN: minimal change in `resources/views/layouts/app.php`; no navigation service or new abstraction.
5. Verify full matrix PHP 8.3/8.4/8.5.

### GAP B — explicit F4 CSRF regression test is missing

Global CSRF middleware still protects POST, but F4 should have one focal regression proving a representative property mutation cannot bypass it.

Required TDD continuation:
1. Add a test in existing `tests/Integration/Auth/CsrfRoutesTest.php` (or the smallest existing appropriate suite) that POST `/admin/properties` without valid CSRF returns 403 and creates no property.
2. Do not create an F4-specific CSRF subsystem or middleware.
3. If the existing global middleware already satisfies the RED expectation immediately, treat it as characterization/regression coverage; do not manufacture product code.

After GAP A + GAP B and green matrix, Task 4 can be considered CLOSED.

## 5. TASK 5+ STATUS

Task 5 — public landing + public catalog: NOT STARTED.

No public catalog views/routes should be assumed implemented from Task 4. The F4 design requires:
- public landing at `/` for unauthenticated visitors with `Iniciar sesión` and `Catálogo`;
- authenticated `/` keeps the internal app-home behavior;
- public catalog only from real visible `units` inventory;
- filters/type handling per SPEC;
- no `Aplicar` button until F5;
- no `catalog_listings`, leads, applications or second auth.

Task 6+ (public media boundary/security, final QA, visual review, Cero Ruido closure): NOT STARTED / NOT CLOSED.

## 6. TWO LAWS STATUS AT THIS CHECKPOINT

### LAW 1 — KISS
PASS so far.

Current intended architecture remains:
- 1 app;
- 1 DB;
- 1 auth;
- 1 `units` inventory model;
- 1 `PropertyService`;
- 1 `UnitMediaService` because filesystem/DB rollback is a distinct responsibility;
- +3 F4 tables only;
- +2 permissions only;
- +0 Composer packages;
- +0 npm/build system.

### LAW 2 — ZERO NOISE / CONVERGENCE
PASS so far, with audit work pending.

Do not add:
- repositories/interfaces/DTO layers;
- second inventory or catalog persistence;
- second price source;
- second availability flag;
- second login;
- `catalog.manage`;
- fake Apps links;
- `Aplicar` before F5.

Temporary F4 checkpoints currently exist in the branch for recovery. They are historical aids during implementation and must be swept/converged at final F4 closure so only the canonical closed checkpoint remains live.

## 7. EXACT RESUME POINT

Resume at:

**F4 → Task 4 audit closure → GAP A launcher RED -> GREEN → GAP B focal CSRF regression → full PHP 8.3/8.4/8.5 CI.**

Only after Task 4 is closed, proceed to **Task 5 — public landing + catalog**, starting with RED tests.

Do not merge PR #7 without explicit human approval.
