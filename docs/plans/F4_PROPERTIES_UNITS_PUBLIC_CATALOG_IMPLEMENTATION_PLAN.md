# F4 Properties, Units and Public Catalog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the single-source property/unit inventory, safe media, public landing/catalog, and private F4 administration without duplicating auth, prices, availability, catalog records, or candidate/application flows.

**Architecture:** Add one focused domain under `app/Properties`: `PropertyService` owns property/unit persistence, validation, publication rules and catalog queries; `UnitMediaService` owns photo/video-link lifecycle and filesystem rollback. HTTP is one `app/Properties/routes.php` file using the existing Slim app, `View`, CSRF, session/current-user resolver and Permissions. Public pages reuse the existing visual tokens through one new lightweight public layout; private pages reuse the F3 app shell. No repository layer, DTO layer, DI container, ORM, frontend framework, media library, or second auth system.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO MySQL/MariaDB, SSR PHP views, native CSS, PHPUnit 11.5, `fileinfo`, native filesystem.

**Spec:** `docs/specs/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_SPEC.md`

## Global Constraints

- Mandatory order: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`.
- One `units` model for `apartment|parking`; no `apartments`, `parking_spaces`, `catalog_listings`, `leads`, or `applications` tables.
- Maximum F4 schema delta: exactly the needed subset of `properties`, `units`, `unit_media`; never more than +3 tables.
- Exactly two new permissions: `properties.view`, `properties.manage`; owner bypass remains unchanged.
- Publicable unit: active property + `status=available` + `is_public=1` + `monthly_price` + nonblank description + nonblank requirements + at least one photo.
- Unit statuses: `available|in_process|occupied|inactive|maintenance`.
- `public_key`: server-generated `bin2hex(random_bytes(16))`, 32 hex chars, unique, never accepted from forms.
- One commercial price source: `units.monthly_price`.
- Photo MIME: JPEG/PNG/WebP by bytes with `fileinfo`; maximum 10 MiB; random stored name; outside webroot.
- Video V1: validated HTTPS link only; no upload, transcoding or arbitrary iframe.
- Public catalog never exposes exact property address, administration contact, property notes, unit private notes, registry number, DB IDs, tenant/candidate data.
- Visitor `GET /` becomes the public landing after installation; authenticated `GET /` keeps the private F3 shell; pre-install `GET /` still redirects to installer.
- F4 does not render an `Aplicar` CTA. F5 owns candidate/application continuation.
- Composer dependency delta: +0. Node/npm delta: +0. Auth-system delta: +0.
- Every product change is RED → verify expected failure → minimal GREEN → full relevant tests → commit.
- Final gate: PHP 8.3/8.4/8.5 CI, visual QA, KISS PASS, Cero Ruido PASS.

## Review Focus

1. **Publication race/incomplete content:** setting `is_public=1` on an available unit without price, description, requirements or photo must be rejected without persisting the public flag and must not leak into `/catalog`.
2. **Cross-property parking link:** a parking unit must never link to an apartment from another property or to a non-apartment unit.
3. **Media authorization drift:** a previously public photo must become 404 immediately when property/unit publication conditions cease to hold; no stale direct media access.
4. **Multipart edge cases:** `UPLOAD_ERR_NO_FILE`, misleading filename/MIME, SVG/PHP bytes, oversized file and DB failure after moving a file must not leave orphan files/rows.
5. **Public privacy boundary:** long/hostile text and private fields must never escape HTML or appear in landing/catalog/detail, including when base path is a subfolder.

---

## File Structure

### Create

- `database/migrations/004_create_properties_units_media.sql` — only F4 schema.
- `app/Properties/PropertyService.php` — property/unit validation, persistence, publication predicate, private/public reads.
- `app/Properties/UnitMediaService.php` — photo/video-link lifecycle and filesystem safety.
- `app/Properties/routes.php` — all F4 public/private routes and the sole installed `GET /` route.
- `resources/views/layouts/public.php` — lightweight public shell reusing F3 assets/tokens.
- `resources/views/public_home.php` — public landing.
- `resources/views/catalog.php` — public browse/filter cards.
- `resources/views/catalog_detail.php` — public unit detail.
- `resources/views/properties.php` — private property cards/create form.
- `resources/views/property_detail.php` — private property edit + units.
- `resources/views/unit_detail.php` — private unit edit + publication/media.
- `tests/Integration/Properties/PropertiesSchemaTest.php`
- `tests/Integration/Properties/PropertyServiceTest.php`
- `tests/Integration/Properties/UnitMediaServiceTest.php`
- `tests/Integration/Properties/PropertiesRoutesTest.php`
- `tests/Integration/Properties/PublicCatalogRoutesTest.php`

### Modify

- `app/Auth/Permissions.php` — add only `properties.view`, `properties.manage`.
- `app/UI/View.php` — add `renderPublic(string $view, array $data = []): string` using existing branding/base path.
- `resources/views/layouts/app.php` — add Propiedades to Apps only when current user is authorized and real route exists.
- `bootstrap/app.php` — instantiate F4 services, media directory setting and register F4 routes; keep existing single auth/current-user resolver.
- `app/UI/routes.php` — delete its installed `GET /` registration. `app/Properties/routes.php` becomes the only installed root owner; its authenticated branch renders the same `home` view through existing `View::renderApp` with `pageTitle=Inicio`, `activeNav=home` and existing CSRF token.
- `assets/app.css` — extend existing stylesheet only; no second design system.
- `tests/Integration/UI/ShellRoutesTest.php` — update the intentionally changed visitor-root contract and assert authenticated root still renders F3 shell.
- `tests/Integration/Auth/UserServiceTest.php` — only if sanitizer expectations enumerate the complete permission catalog.
- `README.md` and one final F4 checkpoint after implementation closes.

---

### Task 1: F4 Schema and Permission Catalog

**Files:**
- Create: `database/migrations/004_create_properties_units_media.sql`
- Create: `tests/Integration/Properties/PropertiesSchemaTest.php`
- Modify: `app/Auth/Permissions.php`
- Modify: `tests/Integration/Auth/UserServiceTest.php` only if its existing assertions require the full catalog

**Interfaces:**
- Consumes: existing `MigrationRunner`; `Permissions::allows(array $user, string $permission): bool`; `Permissions::sanitize(array $permissions): array`.
- Produces: tables `properties`, `units`, `unit_media`; accepted permissions `properties.view`, `properties.manage`.

- [ ] **Step 1: Write schema and permission RED tests.**
  - `test_f4_migration_creates_only_properties_units_and_unit_media()` asserts the three F4 tables and required FK/unique indexes exist.
  - `test_f4_schema_has_no_parallel_catalog_or_type_tables()` asserts no `catalog_listings`, `apartments`, `parking_spaces`.
  - `test_f4_public_key_and_property_code_constraints_are_unique()` asserts `units.public_key` unique and `(property_id, code)` unique.
  - extend permission sanitizer test to keep exactly the five supported permissions total after F4.
- [ ] **Step 2: Run RED.** Run `vendor/bin/phpunit tests/Integration/Properties/PropertiesSchemaTest.php tests/Integration/Auth/UserServiceTest.php --testdox`; expected failure: migration/tables and F4 permissions absent, prior F1–F3 assertions otherwise green.
- [ ] **Step 3: Implement minimal schema.** Create `004_create_properties_units_media.sql` with the exact SPEC fields, FK indexes and nullable self-FK `linked_apartment_id`; add the two strings to `Permissions::CATALOG` and nothing else.
- [ ] **Step 4: Run GREEN.** Same command; expected PASS.
- [ ] **Step 5: Run foundation migration regression.** `vendor/bin/phpunit tests/Integration/Foundation/MigrationRunnerTest.php tests/Integration/Properties/PropertiesSchemaTest.php --testdox`; expected PASS including idempotent second run.
- [ ] **Step 6: Commit.** `git commit -m "feat(f4): add property inventory schema and permissions"`.

---

### Task 2: Property and Unit Domain Service

**Files:**
- Create: `app/Properties/PropertyService.php`
- Create: `tests/Integration/Properties/PropertyServiceTest.php`

**Interfaces:**
- Consumes: `PDO` with Task 1 schema.
- Produces:
  - `__construct(PDO $pdo)`
  - `createProperty(array $input): array`
  - `updateProperty(int $propertyId, array $input): array`
  - `setPropertyActive(int $propertyId, bool $active): void`
  - `listProperties(): array`
  - `findProperty(int $propertyId): ?array`
  - `createUnit(int $propertyId, array $input): array`
  - `updateUnit(int $unitId, array $input): array`
  - `findUnit(int $unitId): ?array`
  - `listUnitsForProperty(int $propertyId): array`
  - `listPublicUnits(?string $type, ?string $city, ?string $sector): array`
  - `findPublicUnit(string $publicKey): ?array`
  - `isUnitPublicable(int $unitId): bool`
  - Invalid domain input throws `RuntimeException` with user-safe Spanish message; missing IDs return `null` on reads and throw on mutating a nonexistent record.

- [ ] **Step 1: Write RED for property CRUD/inactivation.** Cover required name/address/city, optional sector/admin fields, list/read/update, and deactivation preserving units.
- [ ] **Step 2: Run RED.** `vendor/bin/phpunit tests/Integration/Properties/PropertyServiceTest.php --filter Property --testdox`; expected fail because service does not exist.
- [ ] **Step 3: Implement property methods minimally with direct PDO.** No repository/interface/DTO.
- [ ] **Step 4: Run property GREEN.** Same command; expected PASS.
- [ ] **Step 5: Write unit RED.** Cover apartment/parking creation, exact statuses, nonnegative numeric values, server-generated 32-hex `public_key`, unique code per property, explicit rejection when input contains a nonblank `public_key`, and type-specific field normalization.
- [ ] **Step 6: Add Review Focus parking tests.** Assert parking→apartment same property succeeds; cross-property/non-apartment/self link rejects; multiple parking units may reference one apartment.
- [ ] **Step 7: Run RED.** Expected failures only for missing unit methods/rules.
- [ ] **Step 8: Implement unit methods and public query skeleton.** `listPublicUnits`/`findPublicUnit` must already enforce active property + available + `is_public`; Task 3 adds the completeness predicate including photo.
- [ ] **Step 9: Run GREEN.** Entire `PropertyServiceTest.php` PASS.
- [ ] **Step 10: Commit.** `git commit -m "feat(f4): add property and unit domain service"`.

---

### Task 3: Safe Unit Media Lifecycle and Publication Completeness

**Files:**
- Create: `app/Properties/UnitMediaService.php`
- Create: `tests/Integration/Properties/UnitMediaServiceTest.php`
- Modify: `app/Properties/PropertyService.php`
- Modify: `tests/Integration/Properties/PropertyServiceTest.php`

**Interfaces:**
- Consumes: `PDO`, absolute media directory outside webroot, PSR-7 `UploadedFileInterface`, existing unit rows.
- Produces:
  - `UnitMediaService::__construct(PDO $pdo, string $mediaDirectory)`
  - `addPhoto(int $unitId, UploadedFileInterface $upload): array`
  - `addVideoLink(int $unitId, string $url): array`
  - `remove(int $unitId, int $mediaId): void`
  - `setCover(int $unitId, int $mediaId): void`
  - `reorder(int $unitId, array $orderedMediaIds): void`
  - `listForUnit(int $unitId): array`
  - `publicPhoto(int $mediaId): ?array` shape `['path'=>string,'mime'=>string,'stored_name'=>string]`
  - `privatePhoto(int $unitId, int $mediaId): ?array` same shape, without publicability requirement.
- `PropertyService::isUnitPublicable()` includes price + description + requirements + at least one photo in addition to active-property/status/is_public.

- [ ] **Step 1: Write photo RED.** Valid JPEG/PNG/WebP, real bytes MIME, random stored name, outside-webroot path, 10 MiB max, SVG/fake/PHP bytes rejection, `UPLOAD_ERR_NO_FILE` rejection at service level.
- [ ] **Step 2: Add rollback/orphan RED.** Force persistence failure after move and assert moved file is removed; removing media deletes DB row + file; missing file is handled without path escape.
- [ ] **Step 3: Run RED.** Expected fail because media service absent.
- [ ] **Step 4: Implement photo lifecycle minimally.** Use `finfo(FILEINFO_MIME_TYPE)`, `random_bytes`, directory containment checks, and transaction/cleanup only where required.
- [ ] **Step 5: Run photo GREEN.** Photo subset PASS.
- [ ] **Step 6: Write video/cover/order RED.** HTTPS only, no iframe/storage, one cover per unit, reorder accepts exactly media owned by that unit and rejects foreign/duplicate IDs.
- [ ] **Step 7: Implement video/cover/order and run GREEN.** Full `UnitMediaServiceTest.php` PASS.
- [ ] **Step 8: Add publication completeness RED.** An available unit with requested `is_public=1` but missing price, description, requirements or photo makes `updateUnit()` throw and leaves `is_public=0`; after all requirements + photo exist, update to `is_public=1` succeeds; deactivating property or changing status makes public reads return no unit immediately.
- [ ] **Step 9: Update `PropertyService` publication predicate/query and run GREEN.** Both service test files PASS.
- [ ] **Step 10: Commit.** `git commit -m "feat(f4): add safe unit media and publication rules"`.

---

### Task 4: Private Property Administration

**Files:**
- Create: `app/Properties/routes.php`
- Create: `resources/views/properties.php`
- Create: `resources/views/property_detail.php`
- Create: `resources/views/unit_detail.php`
- Create: `tests/Integration/Properties/PropertiesRoutesTest.php`
- Modify: `bootstrap/app.php`
- Modify: `resources/views/layouts/app.php`
- Modify: `assets/app.css`

**Interfaces:**
- Consumes: `PropertyService`, `UnitMediaService`, existing `View::renderApp`, `current_user`, `SessionGuard`, `Csrf`, `Permissions::allows`, `base_path`.
- Produces exact private routes:
  - `GET /admin/properties`
  - `POST /admin/properties`
  - `GET /admin/properties/{id}`
  - `POST /admin/properties/{id}`
  - `POST /admin/properties/{id}/status`
  - `POST /admin/properties/{id}/units`
  - `GET /admin/units/{id}`
  - `POST /admin/units/{id}`
  - `POST /admin/units/{id}/media/photo`
  - `POST /admin/units/{id}/media/video`
  - `POST /admin/units/{id}/media/{mediaId}/delete`
  - `POST /admin/units/{id}/media/{mediaId}/cover`
  - `POST /admin/units/{id}/media/order`
  - `GET /admin/units/{id}/media/{mediaId}` private preview.

- [ ] **Step 1: Write access RED.** Unauthenticated redirects login; must-change-password redirects password page; `properties.view` can GET only; `properties.manage` required for every POST; owner bypass; unauthorized gets 403.
- [ ] **Step 2: Run RED.** Expected 404/missing routes only; existing auth behavior green.
- [ ] **Step 3: Wire F4 services in bootstrap.** Add setting `unit_media_directory` default `storage/unit-media`; instantiate exactly one `PropertyService` and one `UnitMediaService`; pass them to `app/Properties/routes.php`.
- [ ] **Step 4: Implement GET routes/views and run GREEN for read tests.** Cards instead of tables where practical; no future-module links.
- [ ] **Step 5: Write mutation RED.** Property create/edit/active, unit create/edit/state/publication, photo/video/delete/cover/order; assert POST without CSRF remains globally 403.
- [ ] **Step 6: Implement POST routes minimally.** Catch domain `RuntimeException` as 422 with same form context; successful mutations use 303 redirects; normalize `UPLOAD_ERR_NO_FILE` to no selected upload at route boundary.
- [ ] **Step 7: Add launcher RED then GREEN.** `resources/views/layouts/app.php` shows **Propiedades** only when `Permissions::allows($user,'properties.view')` and `/admin/properties` is live; no Candidatos/Contratos/Cartera/Solicitudes labels.
- [ ] **Step 8: Run full Task 4 GREEN.** `PropertiesRoutesTest.php` + `ShellRoutesTest.php` + CSRF tests PASS.
- [ ] **Step 9: Commit.** `git commit -m "feat(f4): add private property administration"`.

---

### Task 5: Public Rendering Primitive, Landing and Catalog

**Files:**
- Modify: `app/UI/View.php`
- Create: `resources/views/layouts/public.php`
- Create: `resources/views/public_home.php`
- Create: `resources/views/catalog.php`
- Create: `resources/views/catalog_detail.php`
- Create: `tests/Integration/Properties/PublicCatalogRoutesTest.php`
- Modify: `app/Properties/routes.php`
- Modify: `app/UI/routes.php`
- Modify: `tests/Integration/UI/ShellRoutesTest.php`
- Modify: `assets/app.css`

**Interfaces:**
- Produces `View::renderPublic(string $view, array $data = []): string`.
- `app/Properties/routes.php` becomes the sole installed owner of `GET /`.
- Exact public routes:
  - `GET /` — if `current_user()` returns a user and `must_change_password` is false, render existing `home` via `View::renderApp('home', ['pageTitle'=>'Inicio','user'=>$user,'activeNav'=>'home','csrfToken'=>$csrf->token()])`; if no current user, render `public_home`; forced-password users redirect `/account/password`; pre-install root remains handled by bootstrap before F4 routes are registered.
  - `GET /catalog`
  - `GET /catalog/{publicKey}`
- Catalog query params: `type` accepts `''|apartment|parking`; any other nonblank type returns HTTP 422; `city` and `sector` are trimmed strings.

- [ ] **Step 1: Write `renderPublic` and landing RED.** Visitor installed `/` must return 200 with real `Ver catálogo` + `Iniciar sesión`; no private nav/user data; authenticated `/` remains F3 private shell; forced-password user still redirects; pre-install root remains installer redirect.
- [ ] **Step 2: Run RED.** Existing `ShellRoutesTest` should fail only on intentionally replaced unauthenticated-root expectation plus missing public renderer/routes.
- [ ] **Step 3: Implement root ownership convergence exactly.** Delete `GET /` from `app/UI/routes.php`; register it only in `app/Properties/routes.php`; authenticated branch performs the existing F3 `renderApp('home', ...)` call, visitor branch uses new `renderPublic('public_home', ...)`. No helper/class is added solely for root dispatch.
- [ ] **Step 4: Run landing GREEN.** New + updated shell tests PASS.
- [ ] **Step 5: Write catalog browse/detail RED.** Only publicable units list; filters type/city/sector; invalid type 422; nonpublic/missing detail 404; stable public key used; no `Aplicar` text/link.
- [ ] **Step 6: Add privacy/XSS Review Focus assertions.** HTML escapes hostile title/description/requirements/video URL text; exact address, admin contact, notes, private notes, registry number and DB IDs absent; subfolder links/assets correct.
- [ ] **Step 7: Implement browse/detail views/routes minimally.** Cards, no search engine/map/favorites/pagination framework; external video links validated HTTPS with `rel="noopener noreferrer"`.
- [ ] **Step 8: Run full Task 5 GREEN.** PublicCatalogRoutes + ShellRoutes PASS.
- [ ] **Step 9: Commit.** `git commit -m "feat(f4): add public landing and catalog"`.

---

### Task 6: Public Media Boundary and Immediate Unpublish

**Files:**
- Modify: `app/Properties/routes.php`
- Modify: `app/Properties/UnitMediaService.php`
- Modify: `tests/Integration/Properties/PublicCatalogRoutesTest.php`
- Modify: `tests/Integration/Properties/UnitMediaServiceTest.php`

**Interfaces:**
- Produces `GET /catalog/media/{id}`.
- Public photo response: stored validated MIME, `X-Content-Type-Options: nosniff`, public cache header only while publicable; all missing/nonpublic/foreign/unsafe cases return 404.

- [ ] **Step 1: Write public-media RED.** Publicable photo serves; unpublished/inactive/maintenance/property-disabled photo returns 404; video-link rows cannot be served as bytes; arbitrary filesystem name/path is never accepted.
- [ ] **Step 2: Add immediate-unpublish regression.** Fetch photo 200, change unit/property state in DB/service, next request is 404 without copying/deleting original media.
- [ ] **Step 3: Run RED.** Expected missing public media route/authorization.
- [ ] **Step 4: Implement route using only `UnitMediaService::publicPhoto(int $mediaId)`.** Route never builds path from request input.
- [ ] **Step 5: Run GREEN.** Both test files PASS.
- [ ] **Step 6: Commit.** `git commit -m "feat(f4): secure public catalog media"`.

---

### Task 7: F4 Security, Base-Path and Regression Gate

**Files:**
- Modify: `tests/Integration/Auth/CsrfRoutesTest.php` only for representative F4 POST coverage if global test needs explicit proof.
- Modify: `tests/Integration/Foundation/SecurityBoundaryTest.php` only if F4 storage boundary needs an explicit source contract.
- Modify: F4 integration tests from Tasks 4–6.

**Interfaces:** no new runtime API.

- [ ] **Step 1: Add cross-cutting RED assertions.** Representative F4 POST without CSRF = 403/no mutation; all private routes honor base path; public links/media honor base path; forced password change blocks private F4; unauthorized media preview cannot IDOR another unit.
- [ ] **Step 2: Run focused suite.** `vendor/bin/phpunit tests/Integration/Properties tests/Integration/Auth/CsrfRoutesTest.php tests/Integration/Foundation/SecurityBoundaryTest.php --testdox`; expected only uncovered boundary failures.
- [ ] **Step 3: Apply minimal fixes in existing F4 route/service code.** No middleware framework or abstraction unless a duplicated rule is proven in at least two live call sites and simpler extraction reduces code.
- [ ] **Step 4: Run focused GREEN.** Expected PASS.
- [ ] **Step 5: Run complete local suite.** `vendor/bin/phpunit --testdox`; expected 0 failures/errors/warnings.
- [ ] **Step 6: Commit.** `git commit -m "test(f4): close security and regression boundaries"`.

---

### Task 8: Visual QA, Cero Ruido Sweep and F4 Closure

**Files:**
- Modify: `assets/app.css` only for defects demonstrated by QA.
- Modify: `README.md`
- Create: `docs/checkpoints/F4_CLOSED_2026-10-11.md` (use actual local project date if execution occurs later)
- Delete: any F4 WIP/pause checkpoints created during implementation once canonical close exists.

**Interfaces:** no new runtime API.

- [ ] **Step 1: Run real-browser visual QA.** Landing, catalog, catalog detail, property list/detail, unit detail at 360×800, 390×844, 412×915, 768×1024, desktop ≥1280; equivalent 200% zoom; keyboard/focus; long content; missing images; Apps/dialog; no horizontal overflow or fixed-nav overlap.
- [ ] **Step 2: Record defects before fixes.** Any actual visual/interaction defect becomes a reproducible RED check or documented browser repro; do not alter CSS for hypothetical issues.
- [ ] **Step 3: Apply minimal visual GREEN fixes and rerun affected viewports.** One existing CSS file only; no component library/carousel package.
- [ ] **Step 4: Perform Cero Ruido diff sweep against F4 base.** Confirm: +3 tables max; +2 permissions exact; +0 Composer/+0 npm; no dead views/routes/classes; no `catalog_listings`, second auth, application/lead table, future CTA, duplicated price/availability; no orphan temp checkpoint.
- [ ] **Step 5: Run final CI on exact product HEAD in PHP 8.3/8.4/8.5.** Require all jobs PASS; capture test/assertion counts and 0 PHPUnit warnings.
- [ ] **Step 6: Create canonical F4 checkpoint and update README.** Include base/head SHA, PR, schema/dependency deltas, test counts, visual QA, KISS PASS, Cero Ruido PASS, and next allowed gate F5 design only.
- [ ] **Step 7: Run CI again on exact final documentation HEAD.** Require PHP 8.3/8.4/8.5 PASS before marking implementation PR Ready for review.
- [ ] **Step 8: Commit docs.** `git commit -m "docs(f4): close properties and public catalog phase"`.

---

## Execution Order / Gates

1. Integrate approved SPEC + PLAN documentation into `main`.
2. Create a clean implementation branch from that exact `main`.
3. Open implementation PR as Draft.
4. Execute Tasks 1→8 sequentially. Do not start the next task until current task GREEN is verified.
5. At each task boundary, inspect actual diff for KISS/Cero Ruido before continuing.
6. Do not merge implementation PR without explicit human approval.
7. F5 starts only after F4 is CLOSED/VERIFIED/MERGED.

## Expected F4 Final Architectural Delta

```text
TABLES: +3 max
- properties
- units
- unit_media

PERMISSIONS: +2 exact
- properties.view
- properties.manage

RUNTIME DOMAIN CLASSES: +2 expected
- PropertyService
- UnitMediaService

ROUTE FILES: +1
PUBLIC LAYOUTS: +1
AUTH SYSTEMS: +0
COMPOSER: +0
NODE/NPM: +0
CATALOG PARALLEL TABLES: +0
APPLICATION/LEAD TABLES: +0
```

## Final Two-Laws Gate

**LEY 1 — KISS requires:** one inventory, one catalog source, one price, one status, two focused domain services, direct PDO, SSR/native CSS, no infrastructure expansion.

**LEY 2 — Cero Ruido requires:** no duplicate auth/catalog/application models, no future buttons/routes, no obsolete renderer/storage copy, no WIP checkpoints after closure, no unused schema/fields/classes introduced “for later”.
