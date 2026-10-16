# R3 Public Surfaces Design

**Date:** 2026-10-16  
**Status:** REVIEW GATE  
**Base authority:** `main@b0403a5a1e544d95aa02917ef69687c560a94201`  
**Branch:** `impl/r3-public-surfaces-20261016`

## 1. Goal

Implement the approved public experience for AlquilaPro across landing, catalog, public detail, and login while preserving the existing PHP SSR architecture and the current Properties/Auth owners.

R3 must make the public inventory the visual protagonist, work mobile-first, preserve privacy/publication rules, and avoid creating a second catalog, second auth flow, frontend framework, or parallel design system.

## 2. Source of truth

Authority order for R3:

1. live repository state at the R3 base SHA;
2. `docs/design/APPROVED_PUBLIC_UI_BASELINE_2026-10-15.md`;
3. approved PNGs in `/AlquilaPro/Approved UI 2026-10-15/`;
4. post-UAT implementation plan and canonical handoff;
5. this R3 spec, which resolves the two known baseline contradictions before implementation.

Canonical viewport order:

```text
375x812
→ 768x1024
→ 1440x900
```

Mobile defines hierarchy. Tablet and desktop adapt space without redefining the product flow.

## 3. Baseline contradiction rulings

### 3.1 Login close control

**Ruling: NO close/X control.**

The written approved baseline, implementation plan, and canonical handoff all define the login as fixed/integrated and non-dismissible. The mobile PNG contains one gray X, but desktop/responsive references and the written authorities do not.

R3 must therefore implement:

- no X;
- no close button;
- no dismiss behavior;
- no modal behavior for the landing login module.

The mobile PNG X is treated as a visual artifact superseded by the repeated written contract.

### 3.2 Login identifier copy

**Ruling: literal UI copy = `Usuario, correo o cédula`.**

Reasons:

- all three approved PNG references use that copy;
- the R1 login view already uses that exact label;
- R1 authentication accepts email, username, and document number through one `identifier` field.

Mentions of `Usuario` in older plan/handoff text are treated as semantic shorthand for the single identifier field, not as the required literal placeholder/label.

## 4. Non-negotiable architecture

Keep the current owners:

- `PropertyService` remains the public property/unit data owner;
- `UnitMediaService` remains the public media owner;
- `AuthService`, `SessionGuard`, and `Csrf` remain the auth/session owners;
- `View::renderPublic()` remains the public SSR rendering path;
- `View::renderAuth()` remains the standalone auth rendering path;
- `resources/views/layouts/public.php` remains the public shell;
- `assets/app.css` remains the only principal stylesheet;
- `assets/app.js` remains the only application JavaScript file.

R3 must not add:

- a `PublicLandingService`;
- a second catalog repository/service;
- a JSON/public API for the carousel;
- a second login endpoint or auth provider;
- a SPA/router/state library;
- a CSS framework or component framework;
- a second stylesheet;
- a second JS runtime/bundle;
- a new table or migration;
- persisted favorites merely because a heart appears in a mockup.

## 5. Landing data flow

The current root route already calls `PropertyService::listPublicUnits(null, null, null)` but reduces the result to a count. R3 will reuse the same result as the landing inventory and remove the obsolete count-only presentation.

Required flow:

```text
GET /
→ PropertyService::listPublicUnits(null, null, null)
→ preserve existing publicability/privacy filtering
→ first up to 8 units become featured carousel items
→ UnitMediaService supplies public photo positions for those featured units
→ renderPublic('public_landing', ...)
```

The maximum of 8 is a presentation bound, not a second catalog or ranking system. Ordering remains the existing canonical `listPublicUnits()` order. `Ver todas` always links to `/catalog` for the complete inventory.

For each featured unit:

- cover image uses the existing public photo route;
- up to the first 5 public photo positions may be shown as thumbnails when available;
- no direct filesystem path is exposed;
- no private property/unit field is added to the public DTO.

A bounded N+1 lookup through the existing `UnitMediaService` for at most 8 featured units is acceptable in R3 and preferred over introducing joins, caches, loaders, or a new abstraction without evidence of a performance problem.

## 6. Landing visual structure

The landing must replace the current marketing hero with this hierarchy:

1. existing AlquilaPro/tenant branding at top-left;
2. section header `Propiedades disponibles`;
3. `Ver todas` linking to `/catalog`;
4. protagonist public inventory carousel;
5. carousel pagination/controls;
6. fixed/integrated login module directly below.

Explicitly absent:

- `Encuentra tu próximo espacio` or equivalent marketing hero;
- old availability-count marketing copy;
- public bottom navigation;
- Google login;
- second `Más propiedades` grid;
- extra public navigation;
- dismissible login;
- fake favorites.

If no publicable inventory exists, show a compact truthful empty state and keep the login module available. Do not create demo inventory to hide the empty state.

## 7. Carousel behavior

Use SSR markup plus progressive enhancement in the existing `assets/app.js`.

### 7.1 Markup

- all featured units are rendered server-side;
- each slide is a real linkable public unit, not a client-fetched payload;
- the carousel uses namespaced `public-carousel*` classes/data attributes;
- previous/next controls are real `<button type="button">` elements with accessible labels;
- pagination buttons expose an accessible label such as `Ir a propiedad 2`;
- without JavaScript, the content remains readable/navigable using horizontal overflow/scroll-snap and direct links.

### 7.2 JavaScript

Extend `assets/app.js`; do not create another JS file.

Responsibilities are limited to:

- previous/next navigation;
- pagination navigation;
- syncing the active pagination state with the visible slide;
- preserving focus/accessibility behavior.

No autoplay is required. No remote fetch, timer, animation library, touch library, or state framework is allowed.

### 7.3 Responsive behavior

**375x812:** one protagonist card dominates; neighboring card edges may peek without making the screen feel like a three-column grid. Information stacks below/with the image as required by available height.

**768x1024:** same hierarchy, larger media/data spacing, no new navigation level.

**1440x900:** protagonist card becomes horizontal with media/gallery on the left and safe public information on the right; previous/next units remain partially visible to communicate carousel continuity.

## 8. Public property information

Render only values authorized by the public domain contract.

For apartments, when available:

- property name;
- safe location label/city/sector;
- monthly price;
- bedrooms;
- bathrooms;
- area;
- floor;
- linked parking count.

The linked parking count is defined exactly as the number of existing `units` rows where `type = 'parking'` and `linked_apartment_id` equals the apartment unit id. Only the numeric count is exposed. Public/private flag and parking detail fields are not exposed through this aggregate. The count must be derived in the existing `PropertyService` public query/normalization path; no schema, table, or new service is permitted.

For parking units, when available:

- property name;
- safe location label/city/sector;
- monthly price;
- parking level;
- vehicle type.

Do not expose address, registry number, private notes, administration private contact, internal code, or other fields currently protected by public tests.

## 9. Login integration

The landing login is a real POST form, not a visual mock.

Required fields/behavior:

- one identifier field labeled/placeholdered `Usuario, correo o cédula`;
- one password field `Contraseña`;
- submit label `Iniciar sesión`;
- existing CSRF token;
- action points to the existing `/login` POST;
- successful login preserves current role/session redirect behavior;
- no Google login;
- no X/close.

To prevent markup drift, create one small reusable PHP partial for the login form and render it from:

- the landing integrated module;
- the existing standalone `login.php` screen.

The partial owns only form markup. Authentication logic remains in `app/Auth/routes.php` and existing auth services.

A failed POST may continue rendering the standalone `/login` auth surface with the existing error semantics. R3 does not couple Auth routes back to PropertyService merely to recreate the landing around an authentication failure.

## 10. Transitional login fallback

Current POST logic accepts:

```php
$form['identifier'] ?? $form['email'] ?? ''
```

R3 will audit remaining callers/tests that still submit `email`.

Removal is allowed only if:

1. all real UI/forms use `identifier`;
2. search proves no supported caller depends on `email`;
3. a RED regression test establishes the canonical `identifier` contract;
4. the minimal removal passes full regression.

If any supported dependency remains, keep the bounded fallback and record the debt. Do not remove it cosmetically.

## 11. Catalog

`GET /catalog` remains the only complete public inventory list.

R3 changes presentation, not ownership:

- retain current type/city/sector filters;
- retain current invalid-filter 422 behavior;
- retain `listPublicUnits()` as source;
- retain subfolder/basePath correctness;
- present cards using the shared public visual foundation;
- use existing public photo endpoint;
- link each card to `/catalog/{publicKey}`.

The landing carousel and catalog must never become separate data authorities.

## 12. Public detail

`GET /catalog/{publicKey}` remains the public detail route.

R3 presentation must provide:

- protagonist gallery/media area;
- thumbnails when multiple photos exist;
- safe location/status/price/attributes;
- description and requirements;
- HTTPS video links using existing safe behavior;
- CTA to the existing `/apply/{publicKey}` flow;
- return path to `/catalog`.

Existing 404/publicability/private-data protections remain mandatory.

No new candidate/application workflow is introduced in R3.

## 13. CSS ownership

All R3 styling goes into `assets/app.css` using public namespaced selectors and existing tokens.

Prefer convergence over overrides:

```text
ELIMINATE
→ MERGE/CONVERGE
→ SIMPLIFY
→ IMPROVE/OPTIMIZE
→ ADD
```

Do not stack a new override on a broken old public rule when the owner rule can be replaced/converged.

Shared public primitives may cover:

- public section header;
- carousel track/slide;
- property media/gallery;
- status pill;
- property facts;
- price block;
- login panel;
- catalog cards;
- detail gallery.

Do not restyle unrelated private/admin modules in R3.

## 14. Public layout and assets

`resources/views/layouts/public.php` may load the existing `assets/app.js` with `defer` so the public carousel can progressively enhance.

No remote CDN or third-party frontend asset is permitted.

The approved mockups contain scenic/property imagery. R3 must not invent or commit the coherent demo image packs reserved for R4. R3 visual QA evaluates structure, hierarchy, sizing, responsiveness, gallery behavior, and real media wiring using available public/test media. R4 owns the final coherent property asset packs.

If no canonical decorative background asset exists, use the existing visual foundation/background treatment rather than introducing an unapproved external image.

## 15. Error and empty states

Preserve current HTTP/domain behavior:

- invalid catalog type remains 422;
- malformed/missing/non-publicable public key remains 404;
- missing media remains 404;
- failed login remains 422 with current credential error semantics;
- no public unit state is truthful and non-blocking;
- private/internal fields never appear in public HTML.

JavaScript failure must not prevent:

- reading the first/featured inventory;
- opening a detail link;
- opening `/catalog`;
- submitting login.

## 16. Accessibility

Minimum R3 contract:

- semantic headings preserve hierarchy;
- carousel controls are buttons with text alternatives;
- property images have meaningful alt text based on safe public names/positions;
- keyboard users can activate carousel controls and all CTAs;
- focus indicators remain visible;
- no autoplay that moves content unexpectedly;
- form labels remain associated with inputs;
- errors keep `role="alert"` where applicable;
- reduced-motion preferences are respected for any smooth movement/transition.

## 17. Testing strategy

Implementation follows Anti-parche/TDD:

```text
REPRODUCE
→ ROOT CAUSE
→ OWNER
→ SEARCH DUPLICATION
→ RED
→ VERIFY RED
→ MINIMAL GREEN
→ REMOVE SUPERSEDED
→ REGRESSION
```

### 17.1 Landing integration tests

Extend `PublicLandingTest` to prove at minimum:

- real publicable unit data appears on `/`;
- non-public/occupied/incomplete/private data does not appear;
- `Propiedades disponibles` and `Ver todas` exist;
- hero copy `Encuentra tu próximo espacio` and obsolete availability summary are gone;
- detail/photo links respect `basePath`;
- integrated login posts `identifier` to `/login` with CSRF;
- literal login copy is `Usuario, correo o cédula`;
- no Google login;
- no X/dismiss control;
- no public bottom nav;
- empty inventory remains truthful and still exposes login;
- linked parking count is derived without exposing parking private fields.

### 17.2 Catalog/detail regression

Preserve and extend existing `PublicCatalogListTest` and `PublicCatalogDetailTest` only where R3 markup/data contracts require it. Their existing privacy, filtering, publicability, escaping, HTTPS video, 404, and basePath assertions remain authoritative.

### 17.3 Auth regression

Preserve R1 behavior:

- one identifier field;
- email/username/document authentication;
- password policy;
- CSRF/session handling;
- standalone `/login` error behavior.

If the transitional `email` POST fallback is removed, that change gets its own causal RED and regression proof.

### 17.4 Asset/structure contracts

Extend existing UI/asset structural tests to prove:

- one principal stylesheet;
- one application JS asset;
- public shell remains separate from auth shell;
- public layout can load existing `app.js` without adding another runtime;
- no prohibited public bottom-nav/Google/second marketing hero.

Avoid brittle full-HTML or full-CSS snapshots.

### 17.5 Visual QA

Before R3 closes, capture the real rendered public flow at:

```text
375x812
768x1024
1440x900
```

Compare against the approved baseline for:

- hierarchy;
- protagonist carousel sizing;
- desktop horizontal card composition;
- neighboring-card visibility;
- login placement;
- absence of X/Google/marketing hero/public bottom nav;
- responsive overflow and touch targets.

Asset-photo coherence itself is completed in R4, not hidden inside R3.

## 18. Scope budget

Expected R3 owners, subject to RED-driven necessity:

- `app/Properties/routes.php`;
- `app/Properties/PropertyService.php` for the linked-parking aggregate only;
- `resources/views/public_landing.php`;
- `resources/views/public_catalog.php`;
- `resources/views/public_catalog_detail.php`;
- `resources/views/login.php`;
- one small login-form partial;
- `resources/views/layouts/public.php`;
- `assets/app.css`;
- `assets/app.js`;
- existing tests for public/Auth/UI contracts.

Budget:

```text
DB_NEW_TABLES = 0
MIGRATIONS = 0
NEW_SERVICES = 0
NEW_REPOSITORIES = 0
NEW_CONTROLLERS = 0
COMPOSER_NEW_DEPENDENCIES = 0
NODE_NPM = 0
SPA = NO
CSS_FRAMEWORK = NO
SECOND_STYLESHEET = NO
SECOND_JS_FILE = NO
SECOND_CATALOG = NO
SECOND_AUTH_STACK = NO
DEPLOYMENT = NO
```

## 19. R3 implementation sequence

The implementation plan must decompose R3 into short checkpointable tasks, at minimum:

1. landing/public data contract RED;
2. minimal landing + integrated login GREEN;
3. carousel progressive-enhancement RED/GREEN;
4. catalog visual convergence RED/GREEN;
5. public detail/gallery visual convergence RED/GREEN;
6. login fallback audit/removal only if safely proven;
7. focal + full regression;
8. browser visual QA 375/768/1440;
9. exact-head CI PHP 8.3/8.4/8.5;
10. 10-pass final audit;
11. merge gate + post-merge checkpoint.

Do not combine the whole phase into one large runtime commit.

## 20. Closure criteria

R3 can close only when all are true:

```text
LANDING_REAL_PUBLIC_INVENTORY = PASS
CAROUSEL_PROGRESSIVE_ENHANCEMENT = PASS
CATALOG_SINGLE_AUTHORITY = PASS
PUBLIC_DETAIL_SAFE_GALLERY = PASS
LOGIN_INTEGRATED_NO_X_NO_GOOGLE = PASS
LOGIN_IDENTIFIER_COPY = Usuario, correo o cédula
LINKED_PARKING_COUNT = PASS
BASE_PATH = PASS
PRIVACY = PASS
MOBILE_375 = PASS
TABLET_768 = PASS
DESKTOP_1440 = PASS
FULL_REGRESSION = PASS
EXACT_HEAD_CI_8_3_8_4_8_5 = PASS
KISS = PASS
CERO_RUIDO = PASS
ANTI_PARCHE = PASS
R4 = NOT STARTED
DEPLOYMENT_AUTHORIZED = NO
```

R3 completion does not authorize deployment and does not reopen F26.
