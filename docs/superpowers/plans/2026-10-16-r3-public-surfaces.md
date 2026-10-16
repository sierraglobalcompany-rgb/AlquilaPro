# R3 Public Surfaces Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved AlquilaPro public landing, catalog, detail and integrated login experience using the existing PHP SSR, Properties/Auth owners and single CSS/JS assets.

**Architecture:** Keep `PropertyService` as the only public inventory owner, `UnitMediaService` as the media owner and the existing `/`, `/catalog`, `/catalog/{publicKey}` and `/login` routes. Render all inventory server-side, progressively enhance the landing carousel with the existing `assets/app.js`, share one small login-form PHP partial, and converge all public styling into `assets/app.css`.

**Tech Stack:** PHP 8.3–8.5, Slim 4, MariaDB/MySQL, server-rendered PHP, native HTML/CSS/JavaScript, PHPUnit 11.5.

**Spec:** `docs/superpowers/specs/2026-10-16-r3-public-surfaces-design.md`

## Global Constraints

- Base authority: `main@b0403a5a1e544d95aa02917ef69687c560a94201`.
- Mobile-first order: `375x812 -> 768x1024 -> 1440x900`.
- Existing CSS breakpoints remain authoritative: tablet `768px–1199px`; desktop `>=1200px`.
- Literal identifier copy: `Usuario, correo o cédula`.
- Landing login is fixed/integrated: no X, no close behavior, no modal and no Google login.
- `GET /catalog` remains the only complete public inventory list; no second catalog.
- `PropertyService`, `UnitMediaService`, `AuthService`, `SessionGuard`, `Csrf` and `View` keep ownership.
- `assets/app.css` remains the only principal stylesheet.
- `assets/app.js` remains the only application JavaScript file.
- `DB_NEW_TABLES = 0`; `MIGRATIONS = 0`; `NEW_SERVICES = 0`; `NEW_REPOSITORIES = 0`; `NEW_CONTROLLERS = 0`.
- `COMPOSER_NEW_DEPENDENCIES = 0`; `NODE_NPM = 0`; `SPA = NO`; `CSS_FRAMEWORK = NO`.
- Do not add persisted favorites, fake demo inventory or R4 coherent image packs.
- Preserve basePath/subfolder deployment and all existing public privacy/publicability rules.
- Current `identifier ?? email` POST fallback stays in R3 because current supported Auth route tests still submit `email`; record it as bounded compatibility debt rather than deleting it cosmetically.
- No merge before exact-head CI PHP 8.3/8.4/8.5 GREEN and visual gate evidence is complete.
- No deployment.

## Review Focus

1. **Private-data leakage:** landing/catalog/detail may only expose the existing public DTO plus numeric `linked_parking_count`; pin in Tasks 1–4.
2. **Empty/sparse inventory:** zero units, one unit and fewer than eight units must render truthfully without broken carousel controls; pin in Tasks 2–3.
3. **Subfolder URLs:** detail/photo/catalog/login actions must preserve `basePath`; pin in Tasks 2–4.
4. **JavaScript failure:** inventory links and login remain usable without JS; pin with SSR markup tests plus Task 3 structural checks.
5. **Auth drift:** integrated and standalone login must share `identifier`, CSRF and literal copy while preserving current POST/session behavior; pin in Task 2 and Auth regressions.

---

## File Structure Locked for R3

### Modify

- `app/Properties/PropertyService.php` — add only `linked_parking_count` to the existing public DTO/query path.
- `app/Properties/routes.php` — pass at most eight public units + media positions + CSRF into landing.
- `resources/views/public_landing.php` — approved public hierarchy and SSR carousel markup.
- `resources/views/public_catalog.php` — converge existing catalog presentation.
- `resources/views/public_catalog_detail.php` — converge detail/gallery presentation.
- `resources/views/login.php` — reuse shared form partial while keeping standalone error surface.
- `resources/views/layouts/public.php` — load existing `assets/app.js` with `defer`.
- `assets/app.css` — all R3 public styles, namespaced/converged.
- `assets/app.js` — native progressive carousel only.
- existing public/Auth/UI tests named below.

### Create

- `resources/views/partials/login_form.php` — markup-only shared login form; no auth logic.

### Do Not Create

- no service/repository/controller/table/migration;
- no second catalog or auth endpoint;
- no second CSS or JS file;
- no JSON carousel API;
- no frontend framework/build pipeline;
- no R4 property image pack.

---

### Task 1: Public DTO + Linked Parking Contract RED/GREEN

**Files:**
- Modify: `tests/Integration/Properties/PropertyServiceTest.php`
- Modify: `app/Properties/PropertyService.php`

**Interfaces:**
- Consumes: `PropertyService::listPublicUnits(?string $type, ?string $city, ?string $sector): array` and `findPublicUnit(string $publicKey): ?array`.
- Produces: every public unit DTO includes `linked_parking_count:int`; apartments count every existing parking row whose `linked_apartment_id` equals the apartment id, parking units return `0`.

- [ ] **Step 1: Write RED tests in `PropertyServiceTest`**

Extend the existing public-query test or add `testPublicApartmentDtoIncludesLinkedParkingCountWithoutParkingPrivateData()`.

Create one publicable apartment, two parking rows linked to it (their own public/status flags do not change the aggregate), and another unrelated parking row. Assert:

```php
self::assertSame(2, $publicApartment['linked_parking_count']);
self::assertArrayNotHasKey('linked_apartment_id', $publicApartment);
self::assertArrayNotHasKey('private_notes', $publicApartment);
self::assertSame(0, $publicParking['linked_parking_count']);
```

Also assert `findPublicUnit()` returns the same count as `listPublicUnits()`.

- [ ] **Step 2: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Properties/PropertyServiceTest.php --filter Public --testdox
```

Expected: FAIL only because `linked_parking_count` is absent/wrong.

- [ ] **Step 3: Implement minimal query/normalization change**

In both public SELECTs, derive one numeric alias with a correlated count against `units parking` where `parking.type = 'parking' AND parking.linked_apartment_id = u.id`. Add only:

```text
linked_parking_count => (int) $row['linked_parking_count']
```

to `normalizePublicUnit()`.

Do not expose parking IDs/details and do not add another query/service.

- [ ] **Step 4: Run GREEN + public service regression**

```bash
vendor/bin/phpunit tests/Integration/Properties/PropertyServiceTest.php --testdox
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Integration/Properties/PropertyServiceTest.php app/Properties/PropertyService.php
git commit -m "feat(r3): expose linked parking count in public inventory"
```

- [ ] **Step 6: Open the R3 draft PR as the live continuity surface**

Open a draft PR from `impl/r3-public-surfaces-20261016` to `main`. Record:

- audited base SHA;
- active R3 HEAD;
- approved spec + plan SHAs;
- Task 1 RED cause and GREEN result;
- `R4: NOT STARTED`;
- `DEPLOYMENT_AUTHORIZED: NO`.

From this point onward update the PR body after each task. Do not create active-branch checkpoint commits merely to restate task status.

---

### Task 2: Landing Inventory + Shared Login RED/GREEN

**Files:**
- Modify: `tests/Integration/Properties/PublicLandingTest.php`
- Modify: `tests/Integration/Auth/AuthRoutesTest.php` only for shared-form regression if needed.
- Modify: `app/Properties/routes.php`
- Modify: `resources/views/public_landing.php`
- Modify: `resources/views/login.php`
- Create: `resources/views/partials/login_form.php`

**Interfaces:**
- Consumes: public DTO from Task 1; `UnitMediaService::publicPhotoPositions(string): array`; existing `/login` POST and `Csrf::token()`.
- Produces: `public_landing` data keys `featuredUnits` (0–8 units, each optionally enriched with `photo_positions` max 5) and `csrfToken`; shared partial consumes `$action` and `$csrfToken`.

- [ ] **Step 1: Replace obsolete landing assertions with RED contract**

In `PublicLandingTest`, prove:

- heading `Propiedades disponibles` exists;
- `Ver todas` links to `/catalog`;
- a real publicable unit name/price/location/detail/photo URL appears;
- occupied/non-public/incomplete/private values do not appear;
- marketing copy `Encuentra tu próximo espacio` and old `unidad disponible ahora` summary are absent;
- no more than eight featured unit markers are rendered when nine publicable units exist;
- zero inventory shows a truthful empty state and login still exists;
- integrated form has `method="post"`, `/login`, `name="identifier"`, CSRF, `Usuario, correo o cédula`, `Contraseña`, `Iniciar sesión`;
- no Google, close/X control or `bottom-nav`;
- `/miapp` preserves catalog/detail/photo/login/form URLs.

- [ ] **Step 2: Add shared standalone-login regression**

In `AuthRoutesTest::testLoginUsesUnifiedIdentifierFieldAndAcceptsUsernameOrDocument()`, retain existing assertions and add the literal submit text `Iniciar sesión`. Do not remove compatibility `email` callers in this task.

- [ ] **Step 3: Run RED**

```bash
vendor/bin/phpunit \
  tests/Integration/Properties/PublicLandingTest.php \
  tests/Integration/Auth/AuthRoutesTest.php \
  --testdox
```

Expected: FAIL only on the newly introduced landing/shared-form assertions (including the current standalone submit text `Entrar`); existing R1 auth behavior must otherwise remain green.

- [ ] **Step 4: Pass real inventory/media into landing**

In the unauthenticated `/` branch:

1. call `listPublicUnits(null, null, null)` once;
2. `array_slice(..., 0, 8)`;
3. enrich each featured unit with at most first five `publicPhotoPositions(public_key)`;
4. pass `featuredUnits` and `$csrf->token()` to `renderPublic()`;
5. remove `availabilityCount`.

Reuse existing `$media`; add it to the root closure capture. No new service/helper class.

- [ ] **Step 5: Create and reuse `partials/login_form.php`**

The partial owns only the `<form>` and fields. Exact field contract:

```text
POST action = existing /login action
csrf_token hidden input
identifier text input, autocomplete=username
password password input, autocomplete=current-password
submit = Iniciar sesión
```

The partial must HTML-escape `$action` and `$csrfToken` exactly as the current standalone form does. `login.php` keeps its heading/hint/error and requires `__DIR__ . '/partials/login_form.php'`. `public_landing.php` sets `$action = $basePath . '/login'` and requires the same partial inside the integrated login panel.

- [ ] **Step 6: Implement landing SSR structure**

Use namespaced markup: `public-section-heading`, `public-carousel`, `public-carousel__track`, `public-property-card`, `public-login-panel`. Each unit has a direct `/catalog/{publicKey}` link and cover `/photo/1`; optional thumbnails use the supplied positions. Render apartment/parking facts only when present. No fake favorite behavior.

- [ ] **Step 7: Run GREEN**

```bash
vendor/bin/phpunit \
  tests/Integration/Properties/PublicLandingTest.php \
  tests/Integration/Auth/AuthRoutesTest.php \
  --testdox
```

Expected: PASS.

- [ ] **Step 8: Commit and update PR state**

```bash
git add app/Properties/routes.php resources/views/public_landing.php resources/views/login.php resources/views/partials/login_form.php tests/Integration/Properties/PublicLandingTest.php tests/Integration/Auth/AuthRoutesTest.php
git commit -m "feat(r3): render public inventory and integrated login"
```

Update the draft PR body with Task 2 RED/GREEN evidence and exact HEAD.

---

### Task 3: Native Carousel + Responsive Public Foundation RED/GREEN

**Files:**
- Modify: `tests/Unit/UI/AssetContractTest.php`
- Modify: `tests/Unit/UI/PublicFoundationContractTest.php`
- Modify: `tests/Integration/UI/ResponsiveMarkupRegressionTest.php`
- Modify: `resources/views/layouts/public.php`
- Modify: `assets/app.js`
- Modify: `assets/app.css`

**Interfaces:**
- Consumes: Task 2 SSR data attributes/markup.
- Produces: progressive carousel controls on `[data-public-carousel]`; public layout loads the existing `/assets/app.js` with `defer`.

- [ ] **Step 1: Write structural RED tests**

Pin:

- public layout includes exactly existing `assets/app.css` and existing `assets/app.js` with `defer`;
- `app.js` contains public carousel selectors but still no `import ` and no `fetch(`;
- no autoplay/timer (`setInterval(` absent);
- CSS contains namespaced public carousel/property/login selectors, `scroll-snap-type`, mobile default, tablet `@media (min-width: 768px) and (max-width: 1199px)` and desktop `@media (min-width: 1200px)`;
- reduced-motion contract remains present;
- no additional CSS/JS asset file is referenced.

Avoid asserting full CSS/JS bodies.

- [ ] **Step 2: Run RED**

```bash
vendor/bin/phpunit tests/Unit/UI tests/Integration/UI/ResponsiveMarkupRegressionTest.php --testdox
```

Expected: new carousel/public-responsive assertions FAIL only for missing R3 behavior.

- [ ] **Step 3: Add existing JS asset to public layout**

Add one deferred script tag using `$basePath . '/assets/app.js'`; no new file/CDN.

- [ ] **Step 4: Add progressive enhancement in `app.js`**

For each `[data-public-carousel]`:

- collect track/slides/prev/next/dots;
- previous/next calculates a bounded target index and calls the target slide's native `scrollIntoView()`/equivalent horizontal scroll;
- dot click targets its matching slide;
- one `scroll` listener schedules at most one `requestAnimationFrame`; in that frame choose the slide whose horizontal center is nearest the track viewport center, then synchronize active dot and previous/next disabled state;
- initialize index/state from the first slide;
- disable/hide controls when fewer than two slides;
- no autoplay, `setInterval`, fetch, dependency or global application state.

Keep existing private dialog/unit-type code intact.

- [ ] **Step 5: Converge CSS mobile-first**

Use existing tokens. Mobile must show one protagonist with optional neighboring edge; tablet uses the existing `768–1199px` range; desktop uses the existing `>=1200px` breakpoint with a horizontal media-left/info-right card and neighboring cards partially visible. Add shared public facts/price/status/login/card primitives once, not per page.

- [ ] **Step 6: Run GREEN + UI regressions**

```bash
vendor/bin/phpunit \
  tests/Unit/UI \
  tests/Integration/UI/AuthVisualTest.php \
  tests/Integration/UI/ResponsiveMarkupRegressionTest.php \
  tests/Integration/UI/ShellRoutesTest.php \
  --testdox
```

Expected: PASS.

- [ ] **Step 7: Commit and update PR state**

```bash
git add tests/Unit/UI tests/Integration/UI/ResponsiveMarkupRegressionTest.php resources/views/layouts/public.php assets/app.js assets/app.css
git commit -m "feat(r3): add native responsive public carousel"
```

Update the draft PR body with Task 3 RED/GREEN evidence and exact HEAD.

---

### Task 4: Catalog + Detail Visual Convergence RED/GREEN

**Files:**
- Modify: `tests/Integration/Properties/PublicCatalogListTest.php`
- Modify: `tests/Integration/Properties/PublicCatalogDetailTest.php`
- Modify: `resources/views/public_catalog.php`
- Modify: `resources/views/public_catalog_detail.php`
- Modify: `assets/app.css` only when a shared public primitive from Task 3 needs completion.

**Interfaces:**
- Consumes: unchanged `listPublicUnits()`, `findPublicUnit()`, media routes and shared R3 public CSS primitives.
- Produces: visual catalog cards and public detail gallery without changing route/domain ownership.

- [ ] **Step 1: Add catalog RED assertions**

Keep all existing filter/privacy/422/basePath assertions. Add contract assertions for namespaced catalog grid/cards, cover photo, safe facts, price and detail link. Assert no private address/internal code/apply action on list page.

- [ ] **Step 2: Add detail RED assertions**

Keep escaping/private/HTTPS-video/404/basePath assertions. Add protagonist gallery, thumbnail navigation for multiple positions, status/price/facts, linked parking count for apartment, CTA `/apply/{publicKey}`, and return `/catalog`.

- [ ] **Step 3: Run RED**

```bash
vendor/bin/phpunit \
  tests/Integration/Properties/PublicCatalogListTest.php \
  tests/Integration/Properties/PublicCatalogDetailTest.php \
  --testdox
```

Expected: FAIL only for new markup/presentation contracts.

- [ ] **Step 4: Converge catalog view**

Retain GET filters and source data. Replace generic `.stack/.surface` presentation with shared public card/grid primitives and existing photo/detail URLs. Do not duplicate landing data logic.

- [ ] **Step 5: Converge detail view**

Use supplied `photoPositions`/`videoLinks`, shared media/gallery/facts/status/price primitives and existing `/apply/{publicKey}` flow. Render the gallery as SSR horizontal `scroll-snap` media with stable photo fragment IDs; thumbnail anchors point to those IDs, so gallery navigation remains usable without JavaScript and no second gallery runtime is introduced. Do not add client API, candidate behavior or private fields.

- [ ] **Step 6: Run GREEN + full public focal matrix**

```bash
vendor/bin/phpunit tests/Integration/Properties/PublicLandingTest.php tests/Integration/Properties/PublicCatalogListTest.php tests/Integration/Properties/PublicCatalogDetailTest.php --testdox
```

Expected: PASS.

- [ ] **Step 7: Commit and update PR state**

```bash
git add tests/Integration/Properties/PublicCatalogListTest.php tests/Integration/Properties/PublicCatalogDetailTest.php resources/views/public_catalog.php resources/views/public_catalog_detail.php assets/app.css
git commit -m "feat(r3): converge public catalog and detail"
```

Update the draft PR body with Task 4 RED/GREEN evidence and exact HEAD.

---

### Task 5: Compatibility Audit + Full Regression + Exact-Head CI

**Files:**
- No planned runtime change.
- `app/Auth/routes.php` remains unchanged unless a new causal RED proves removal safe; current repository evidence says keep fallback.

**Interfaces:**
- Consumes: Tasks 1–4 GREEN.
- Produces: certified R3 functional product SHA.

- [ ] **Step 1: Re-audit login fallback callers**

Search UI/tests for POST `email` callers. Current `AuthRoutesTest` intentionally contains several. Therefore record:

```text
R3_LOGIN_EMAIL_FALLBACK = RETAINED_BOUNDED_COMPATIBILITY
```

Do not rewrite historical/support callers merely to justify removing two fallback tokens.

- [ ] **Step 2: Run focal matrix**

```bash
vendor/bin/phpunit \
  tests/Integration/Properties/PropertyServiceTest.php \
  tests/Integration/Properties/PublicLandingTest.php \
  tests/Integration/Properties/PublicCatalogListTest.php \
  tests/Integration/Properties/PublicCatalogDetailTest.php \
  tests/Integration/Auth/AuthRoutesTest.php \
  tests/Integration/Auth/CsrfRoutesTest.php \
  tests/Integration/UI/AuthVisualTest.php \
  tests/Integration/UI/ShellRoutesTest.php \
  tests/Integration/UI/ResponsiveMarkupRegressionTest.php \
  tests/Unit/UI \
  --testdox
```

Expected: PASS.

- [ ] **Step 3: Run full suite and Composer checks**

```bash
composer validate --strict
composer test
```

Expected: zero failures/errors; only already-documented intentional skips.

- [ ] **Step 4: Update the draft PR**

Record base SHA, current exact head, spec/plan commits, every RED/GREEN task commit, focal/full test evidence, fallback ruling, KISS/Cero Ruido/Anti-parche budget, `R4: NOT STARTED`, `DEPLOYMENT_AUTHORIZED: NO`.

- [ ] **Step 5: Obtain exact-head CI**

Require PHP 8.3, 8.4 and 8.5 SUCCESS on the same final product HEAD. If any job fails, use root-cause workflow; no blind patch/retry.

---

### Task 6: Real Visual QA + 10-Pass Closure + Merge Gate

**Files:**
- No runtime changes unless visual QA reproduces a real R3 defect.
- Continuity lives in PR body/checkpoint ref; avoid a documentation commit that creates a ritual CI loop.

**Interfaces:**
- Consumes: exact-head functional/CI GREEN.
- Produces: R3 closure evidence eligible for merge.

- [ ] **Step 1: Capture real rendered screenshots**

Capture landing and representative catalog/detail at:

```text
375x812
768x1024
1440x900
```

Use the real app with publicable inventory/media, not a competing mockup. If the current environment cannot render/capture the app, mark `R3_VISUAL_QA = BLOCKED_ENVIRONMENT` and do **not** claim R3 closed or merge until a capable browser environment is available.

- [ ] **Step 2: Compare against approved baseline**

Check protagonist hierarchy, neighboring-card visibility, desktop media-left/info-right composition, login placement, exact copy, no X, no Google, no marketing hero, no public bottom nav, no second property grid, touch/overflow behavior.

R4 image coherence is explicitly excluded from this pass.

- [ ] **Step 3: Run 10-pass closure audit**

1. Base/head drift.
2. Public data/privacy contract.
3. One Properties owner / one catalog.
4. One Auth owner; fallback bounded and documented.
5. One CSS + one JS asset.
6. No patch-stack/`!important` workaround introduced for R3.
7. TDD RED before each functional GREEN.
8. Mobile/tablet/desktop visual evidence.
9. Full suite + exact-head CI 8.3/8.4/8.5.
10. Governance: no R4 scope, no deploy, merge only after gate verification.

- [ ] **Step 4: Create recovery checkpoint ref**

Create `checkpoint/r3-green-<ci>-20261016` pointing to the exact certified HEAD, not a new active-branch commit. Update the PR body with final evidence.

- [ ] **Step 5: Refresh merge gate**

Verify immediately before merge:

- `main` expected SHA/no unexpected drift;
- PR head is exact certified SHA;
- CI exact-head SUCCESS all PHP versions;
- no pending review threads;
- PR mergeable;
- visual QA PASS.

Only then execute merge when owner continuation/approval is present. After merge verify `main`, PR merged state and create a separate post-merge checkpoint before R4.

---

## Execution Order / Stop Rules

```text
R3.1 PUBLIC DTO RED/GREEN
→ draft PR/live continuity surface
R3.2 LANDING + LOGIN RED/GREEN
→ update PR/checkpoint ref as needed
R3.3 CAROUSEL + RESPONSIVE RED/GREEN
→ update PR/checkpoint ref as needed
R3.4 CATALOG + DETAIL RED/GREEN
→ update PR/checkpoint ref as needed
R3.5 FULL REGRESSION + EXACT-HEAD CI
→ R3.6 VISUAL QA + 10-PASS AUDIT
→ MERGE GATE
→ POST-MERGE CHECKPOINT
→ R4 only after R3 is actually merged/verified
```

Hard stop on any non-causal RED, privacy regression, base drift, CI mismatch, visual blocker or architecture expansion. Never solve those by weakening tests or adding a parallel owner.
