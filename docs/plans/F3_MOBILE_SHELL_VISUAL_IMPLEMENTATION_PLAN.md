# F3 Mobile Shell + Visual System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the existing F1/F2 server-rendered application into one coherent, mobile-first administrative shell with real navigation, minimal configurable branding, accessible layouts, and no future-module placeholders.

**Architecture:** Keep Slim 4 + server-rendered PHP. Add one focused `Branding` service backed by the existing `settings` table, one small `View` renderer for the two real layouts, and one `UI/routes.php` file for home/account/appearance. CSS and JavaScript remain native and static; the Apps launcher uses `<dialog>`. Logo files stay under blocked `storage/branding` and are exposed only through one safe read route.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO, MariaDB/MySQL, PSR-7, native HTML/CSS/JavaScript, PHPUnit 11.5.

**Spec:** `docs/specs/F3_MOBILE_SHELL_VISUAL_DESIGN_SPEC.md`

## Global Constraints

- Preserve **LEY 1 — KISS / arquitectura simple** and **LEY 2 — Cero Ruido / Convergencia**.
- PHP support remains `>=8.3 <8.6`.
- `DB_NEW_TABLES: 0`.
- `COMPOSER_NEW_DEPENDENCIES: 0`.
- `NODE_NPM: 0`.
- `SPA: NO`.
- `CSS_FRAMEWORK: NO`.
- `FRONTEND_BUILD_PIPELINE: NO`.
- `GENERIC_DESIGN_SYSTEM: NO`.
- `FAKE_FUTURE_ROUTES: 0`.
- `FAKE_NOTIFICATIONS: 0`.
- `MOBILE_SIDEBAR: NO`.
- Do not add Cartera, Solicitudes, Propiedades, Candidatos, Contratos, Pólizas or any other future module to the navigation before its phase exists.
- Admin bottom navigation in F3 contains only **Inicio · Apps · Cuenta**.
- Appearance is owner-only in F3; do not add a new permission solely for appearance.
- Use the existing `settings` table; do not create `branding` or media tables.
- Configurable branding is limited to `app.name`, one `brand.primary_color`, and one optional logo.
- User-uploaded SVG is forbidden in F3.
- Logo validation must inspect actual file bytes, not trust client filename or reported MIME.
- Logo technical limit for F3: **2 MiB**; accepted actual MIME types: `image/png`, `image/jpeg`, `image/webp`.
- `fileinfo` becomes a runtime requirement because F3 performs real MIME validation.
- User-controlled filenames are never persisted.
- Password fields are never repopulated after validation errors.
- F2 security must remain intact: session timeout, session-ID regeneration, global POST CSRF, CSRF rotation, permissions, no permission escalation, sanitized errors.
- Direct HTTP access to `resources/` must be blocked without blocking `/assets/*`.
- System font stack only; no Google Fonts, remote icons, CDN runtime assets or `@import`.
- Touch actions must be at least 44 px; primary navigation/actions should target 48 px.
- Bottom navigation must account for `env(safe-area-inset-bottom)`.
- Custom motion must respect `prefers-reduced-motion`.
- No merge to `main` until F3 is CLOSED/VERIFIED and the user explicitly approves the merge.

## Review Focus

1. **Subfolder deployment:** `/miapp` must prefix CSS/JS links, nav links, redirects and form actions without duplicate slashes. Task 3 pins this with a shell route test using `base_path=/miapp`.
2. **Malformed stored branding:** hostile/invalid DB values must fall back to safe app name/color/logo behavior instead of reaching HTML/CSS unsanitized. Task 1 tests malformed settings on read.
3. **Logo attack surface:** oversized files, SVG/polyglot-like content and misleading client MIME/filename must be rejected while a valid PNG/JPEG/WebP is accepted. Tasks 1 and 5 test byte-level MIME validation and the HTTP upload flow.
4. **Restricted users:** a gestor/admin without `users.view` must not see the Users app, and a non-owner must not see Appearance. Task 3 tests launcher filtering and the zero-app empty state.
5. **Forced password change:** a session with `must_change_password=1` must never reach `/`, `/account`, `/admin/users` or `/admin/appearance` shell pages before changing the password. Tasks 3–5 pin this behavior.

---

# File Structure Locked for F3

## Create

- `app/UI/Branding.php` — one source of truth for branding settings and safe logo storage.
- `app/UI/View.php` — render partial views through the real auth/app layouts.
- `app/UI/routes.php` — home, account, appearance and public current-logo route.
- `resources/views/layouts/auth.php` — unauthenticated/authentication layout.
- `resources/views/layouts/app.php` — authenticated shell, topbar, bottom nav and Apps dialog.
- `resources/views/home.php` — real administrative welcome screen with no invented metrics.
- `resources/views/account.php` — account summary, password link and logout.
- `resources/views/appearance.php` — owner-only app name/color/logo form.
- `assets/app.css` — the only F3 stylesheet.
- `assets/app.js` — minimal Apps `<dialog>` behavior only.
- `tests/Integration/UI/BrandingTest.php`.
- `tests/Integration/UI/ShellRoutesTest.php`.
- `tests/Integration/UI/AppearanceRoutesTest.php`.
- `tests/Unit/UI/AssetContractTest.php`.
- `docs/checkpoints/F3_CLOSED_2026-10-11.md` — created only after all QA is green.

## Modify

- `app/Foundation/RuntimeRequirements.php` — require `fileinfo` because branding upload uses it.
- `bootstrap/app.php` — instantiate UI dependencies, converge current-user lookup and delegate installed `/` to UI routes.
- `app/Auth/routes.php` — consume shared `View` and shared current-user resolver; preserve F2 authorization.
- `resources/views/login.php` — become auth-layout content partial.
- `resources/views/reset_password.php` — become auth-layout content partial.
- `resources/views/change_password.php` — become auth-layout content partial.
- `resources/views/admin_users.php` — become app-layout content partial and hide create UI when actor lacks `users.manage`.
- `resources/views/install.php` — use the same CSS language while remaining independent of installed DB branding.
- `.htaccess` — block `resources/` direct access; keep `/assets/` public.
- `tests/Unit/Foundation/RuntimeRequirementsTest.php` — pin `fileinfo` requirement.
- `tests/Integration/Auth/AuthRoutesTest.php` — pin shared layout and user-page integration without weakening F2 behavior.
- `tests/Integration/Auth/CsrfRoutesTest.php` — pin appearance POST under the existing global CSRF policy.
- `tests/Integration/Foundation/AppBootstrapTest.php` — preserve pre-install root behavior after installed `/` moves to UI routes.
- `tests/Integration/Foundation/SecurityBoundaryTest.php` — require `resources` in the Apache forbidden set.
- `README.md` — update only after F3 closes.

## Explicitly Do Not Create

- no `components/` tree;
- no `Theme` subsystem;
- no `NavigationService`;
- no `MediaLibrary`;
- no `BrandingRepository`;
- no `Controller` classes;
- no new migration;
- no JS package manifest;
- no CSS preprocessor/build config.

---

### Task 1: Branding Source of Truth + Safe Logo Storage

**Files:**
- Create: `app/UI/Branding.php`
- Create: `tests/Integration/UI/BrandingTest.php`
- Modify: `app/Foundation/RuntimeRequirements.php`
- Modify: `tests/Unit/Foundation/RuntimeRequirementsTest.php`

**Interfaces:**
- Consumes: existing `settings(setting_key, setting_value)` table and PDO.
- Produces: `AlquilaPro\UI\Branding::__construct(PDO $pdo, string $logoDirectory)`.
- Produces: `Branding::current(): array` returning exactly `app_name`, `primary_color`, `logo_path`.
- Produces: `Branding::update(string $appName, string $primaryColor, ?Psr\Http\Message\UploadedFileInterface $logo = null): array`.
- Produces: `Branding::logoFile(): ?array` returning exactly `path`, `mime` for the currently configured valid logo.
- Default app name: `Alquila Pro`.
- Default primary color: `#1E6A5A`.

- [ ] **Step 1: Write RED tests for runtime + branding fallbacks**

Add tests named:

```php
RuntimeRequirementsTest::testRejectsMissingFileinfo()
BrandingTest::testCurrentUsesSafeDefaultsWhenSettingsAreMissingOrMalformed()
```

Assertions:
- missing `fileinfo` makes runtime requirements fail;
- missing `app.name` returns `Alquila Pro`;
- malformed/empty `app.name` falls back to `Alquila Pro`;
- invalid stored `brand.primary_color` falls back to `#1E6A5A`;
- unsafe stored logo path such as `../../config/database.php` produces `logo_path === null`.

- [ ] **Step 2: Run RED**

Run:

```bash
vendor/bin/phpunit tests/Unit/Foundation/RuntimeRequirementsTest.php tests/Integration/UI/BrandingTest.php --testdox
```

Expected: FAIL because `fileinfo` is not required and `Branding` does not exist.

- [ ] **Step 3: Implement `Branding::current()` and the runtime requirement**

Rules:
- read only the three real keys from `settings`;
- trim and validate on read as well as write;
- color accepts exactly `#[0-9A-Fa-f]{6}` and is normalized uppercase;
- logo setting accepts only generated basenames matching the F3 logo naming scheme and only when the resolved file exists inside `$logoDirectory`;
- no generic settings repository.

- [ ] **Step 4: Write RED tests for updates and logo validation**

Add tests named:

```php
BrandingTest::testUpdatePersistsNameAndNormalizedPrimaryColor()
BrandingTest::testUpdateRejectsInvalidNameOrColorWithoutMutatingSettings()
BrandingTest::testValidPngLogoUsesGeneratedNameAndCanBeResolved()
BrandingTest::testSvgMisleadingMimeAndOversizedLogoAreRejected()
BrandingTest::testReplacingLogoRemovesPreviousGeneratedFile()
```

Pin:
- app name non-empty, maximum 80 characters;
- logo size maximum `2_097_152` bytes;
- actual MIME allowlist PNG/JPEG/WebP via `fileinfo` inspection of bytes;
- user filename never appears in stored setting;
- generated logo filename is random and restricted to the branding directory;
- replacement removes the prior generated logo after successful persistence;
- invalid input leaves previous settings/logo usable.

- [ ] **Step 5: Run RED for upload/update behavior**

Run:

```bash
vendor/bin/phpunit tests/Integration/UI/BrandingTest.php --testdox
```

Expected: FAIL only for unimplemented update/logo behavior.

- [ ] **Step 6: Implement minimal update/logo behavior**

Use direct PDO upserts to `settings`; use `finfo_buffer()` against actual uploaded bytes; write the accepted file under `storage/branding` with a generated name; no public upload directory and no image-processing library.

- [ ] **Step 7: Run GREEN**

Run:

```bash
vendor/bin/phpunit tests/Unit/Foundation/RuntimeRequirementsTest.php tests/Integration/UI/BrandingTest.php --testdox
```

Expected: PASS.

- [ ] **Step 8: KISS / Cero Ruido check**

Verify:
- +1 focused class;
- +0 tables;
- +0 packages;
- no repository/DTO/media subsystem;
- old logo is removed on replacement.

- [ ] **Step 9: Commit**

```bash
git add app/UI/Branding.php app/Foundation/RuntimeRequirements.php tests/Integration/UI/BrandingTest.php tests/Unit/Foundation/RuntimeRequirementsTest.php
git commit -m "feat(f3): add minimal branding source of truth"
```

---

### Task 2: Shared Auth Layout + Native Visual Foundation

**Files:**
- Create: `app/UI/View.php`
- Create: `resources/views/layouts/auth.php`
- Create: `assets/app.css`
- Modify: `bootstrap/app.php`
- Modify: `app/Auth/routes.php`
- Modify: `resources/views/login.php`
- Modify: `resources/views/reset_password.php`
- Modify: `resources/views/change_password.php`
- Modify: `resources/views/install.php`
- Modify: `tests/Integration/Auth/AuthRoutesTest.php`
- Modify: `tests/Integration/Foundation/AppBootstrapTest.php`

**Interfaces:**
- Consumes: Task 1 `Branding::current()`.
- Produces: `AlquilaPro\UI\View::__construct(string $viewsPath, Branding $branding, string $basePath)`.
- Produces: `View::renderAuth(string $view, array $data = []): string`.
- Auth layout receives the current branding once per render and links `${basePath}/assets/app.css`.

- [ ] **Step 1: Write RED auth-layout tests**

Extend route tests with assertions that:
- login, reset and change-password responses contain one shared F3 auth-shell marker;
- configured `app.name` appears on login/reset/change-password;
- login/reset pages do **not** contain authenticated bottom navigation;
- asset href respects a non-empty base path;
- failed password/login forms still do not echo password values.

Also update bootstrap test to assert the pre-install page links the same local stylesheet without requiring DB branding.

- [ ] **Step 2: Run RED**

Run:

```bash
vendor/bin/phpunit tests/Integration/Auth/AuthRoutesTest.php tests/Integration/Foundation/AppBootstrapTest.php --testdox
```

Expected: FAIL because the shared renderer/layout/assets do not exist.

- [ ] **Step 3: Implement `View::renderAuth()` and auth layout**

Implementation constraints:
- views become content partials rather than complete duplicated HTML documents;
- `View` performs output buffering only; it does not own auth, routing or business rules;
- HTML document/title/branding/CSS link live in `resources/views/layouts/auth.php`;
- no generic component abstraction.

- [ ] **Step 4: Create the initial single stylesheet**

`assets/app.css` must establish only currently used tokens/patterns:
- exact default colors from the F3 spec;
- system font stack;
- body/surface/card/form/button/error/focus/auth-shell styles;
- minimum 44 px interactive height;
- no remote imports.

- [ ] **Step 5: Converge F2 auth routes onto `View`**

Replace the local `$render` closure in `app/Auth/routes.php` with injected `View` calls for login/reset/change-password. Do not change AuthService, session, CSRF or permission semantics.

- [ ] **Step 6: Style installer without coupling it to installed branding**

Keep installer pre-DB and standalone, but make its existing document link `${basePath}/assets/app.css` and use the same form/card class vocabulary. Do not instantiate `Branding` before installation.

- [ ] **Step 7: Run GREEN**

Run:

```bash
vendor/bin/phpunit tests/Integration/Auth/AuthRoutesTest.php tests/Integration/Foundation/AppBootstrapTest.php --testdox
```

Expected: PASS.

- [ ] **Step 8: KISS / Cero Ruido check**

Verify:
- one stylesheet;
- one auth layout;
- duplicated full-document markup removed from the three F2 auth views;
- no JS added yet because Task 2 has no interactive need.

- [ ] **Step 9: Commit**

```bash
git add app/UI/View.php bootstrap/app.php app/Auth/routes.php resources/views/layouts/auth.php resources/views/login.php resources/views/reset_password.php resources/views/change_password.php resources/views/install.php assets/app.css tests/Integration/Auth/AuthRoutesTest.php tests/Integration/Foundation/AppBootstrapTest.php
git commit -m "feat(f3): converge authentication visual shell"
```

---

### Task 3: Authenticated Shell + Home + Account + Apps Dialog

**Files:**
- Create: `app/UI/routes.php`
- Create: `resources/views/layouts/app.php`
- Create: `resources/views/home.php`
- Create: `resources/views/account.php`
- Create: `assets/app.js`
- Create: `tests/Integration/UI/ShellRoutesTest.php`
- Modify: `app/UI/View.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Auth/routes.php`
- Modify: `assets/app.css`

**Interfaces:**
- Consumes: `View`, `Branding`, `AuthService`, `SessionGuard`, `Csrf`, existing `Permissions::allows(array $user, string $permission): bool`.
- Produces: `View::renderApp(string $view, array $data = []): string`.
- Produces: UI route registrar `return static function (Slim\App $app, array $deps): void` in `app/UI/routes.php`.
- `bootstrap/app.php` produces one shared `Closure $currentUser` for both Auth and UI route files; it returns a normalized active user or `null` and logs out stale/inactive sessions.

- [ ] **Step 1: Write RED shell route tests**

Create `ShellRoutesTest` covering:

```text
testInstalledRootWithoutSessionRedirectsToLogin
testAuthenticatedRootRendersSemanticMobileShell
testAccountRequiresLoginAndRendersCurrentUser
testMustChangePasswordCannotReachShell
testShellUsesConfiguredBrandingAndSafeFallbacks
testShellRespectsSubfolderBasePath
testAppsLauncherShowsOnlyRealAuthorizedApps
testRestrictedUserGetsEmptyAppsStateWithoutFutureModules
```

Pin HTML contracts:
- semantic `header`, `main`, `nav`;
- visible Inicio, Apps, Cuenta;
- `<dialog>` Apps launcher;
- owner sees Users + Appearance;
- user with `users.view` sees Users but not Appearance;
- user with no real app permission sees a useful empty state;
- strings `Cartera`, `Solicitudes`, `Propiedades` do not appear in F3 shell;
- `aria-current` marks active nav;
- subfolder paths use `/miapp/...` exactly once.

- [ ] **Step 2: Run RED**

Run:

```bash
vendor/bin/phpunit tests/Integration/UI/ShellRoutesTest.php --testdox
```

Expected: FAIL because installed `/`, `/account`, app layout and UI routes do not exist.

- [ ] **Step 3: Converge current-user lookup in bootstrap**

Move the existing current-user lookup out of the Auth route file into one closure created after PDO/AuthService/SessionGuard exist; inject that same closure into Auth and UI route registrars. Do not create `AccessService` or another auth layer.

- [ ] **Step 4: Make installed `/` belong to UI routes**

Keep the existing pre-install `/ -> /install` behavior in bootstrap. When installed, register `/` only through `app/UI/routes.php`; remove the installed plain-text `Alquila Pro` response.

- [ ] **Step 5: Implement `View::renderApp()` + authenticated layout**

`resources/views/layouts/app.php` owns exactly:
- topbar with logo-or-text branding and account initials/link;
- content slot;
- bottom nav Inicio / Apps / Cuenta;
- permission-aware Apps dialog;
- local CSS/JS references.

Use `Permissions::allows()` directly in the layout for the two real app entries; do not create a navigation service for two entries.

- [ ] **Step 6: Implement real home and account routes/views**

`GET /`:
- require active authenticated admin user;
- enforce `must_change_password` redirect;
- show greeting, role and real available actions only;
- no invented metrics.

`GET /account`:
- show name/email/role;
- link to `/account/password`;
- include POST logout form with existing CSRF token.

- [ ] **Step 7: Implement minimal Apps dialog JavaScript**

`assets/app.js` only wires open/close for the native `<dialog>` launcher and optional backdrop close. No framework and no generalized modal API.

- [ ] **Step 8: Extend CSS for the real app shell**

Add only used shell patterns: topbar, main content spacing, bottom dock, elevated central Apps action, dialog sheet, safe-area, desktop max-width and focus states. Include `prefers-reduced-motion` handling.

- [ ] **Step 9: Run GREEN + F1/F2 route regression**

Run:

```bash
vendor/bin/phpunit tests/Integration/UI/ShellRoutesTest.php tests/Integration/Auth/AuthRoutesTest.php tests/Integration/Foundation/AppBootstrapTest.php --testdox
```

Expected: PASS.

- [ ] **Step 10: KISS / Cero Ruido check**

Verify:
- one shared current-user resolver, not duplicated Auth/UI copies;
- no sidebar;
- no future nav items;
- one dialog and one tiny JS file;
- no new DB/Composer pieces.

- [ ] **Step 11: Commit**

```bash
git add app/UI/routes.php app/UI/View.php bootstrap/app.php app/Auth/routes.php resources/views/layouts/app.php resources/views/home.php resources/views/account.php assets/app.css assets/app.js tests/Integration/UI/ShellRoutesTest.php tests/Integration/Auth/AuthRoutesTest.php tests/Integration/Foundation/AppBootstrapTest.php
git commit -m "feat(f3): add mobile administrative shell"
```

---

### Task 4: Integrate User Administration Into the Shell

**Files:**
- Modify: `app/Auth/routes.php`
- Modify: `resources/views/admin_users.php`
- Modify: `tests/Integration/Auth/AuthRoutesTest.php`
- Modify: `assets/app.css`

**Interfaces:**
- Consumes: Task 3 `View::renderApp()` and shared `current_user` resolver.
- Produces: no new service or route; preserves existing `/admin/users*` endpoints.

- [ ] **Step 1: Write RED integration assertions**

Add tests asserting:
- `/admin/users` contains the authenticated app shell and marks Apps active;
- owner/authorized manager still sees create-user form;
- a `users.view`-only actor can view users but does not see the create-user form;
- user cards fit the responsive pattern and no `<table>` is introduced;
- all existing create/status/permissions/reset-link backend tests stay unchanged and green.

- [ ] **Step 2: Run RED**

Run:

```bash
vendor/bin/phpunit tests/Integration/Auth/AuthRoutesTest.php --testdox
```

Expected: FAIL only for the new shell/visibility assertions.

- [ ] **Step 3: Render users via `View::renderApp()`**

Pass the authenticated actor and `activeNav=apps` to the shared app layout. Keep permission enforcement in backend routes exactly as F2; UI visibility is convenience, never authorization.

- [ ] **Step 4: Convert user list to mobile cards and conditionally show create form**

Use existing user fields and permission checks only. Do not add search/filter/pagination until data volume proves it is required.

- [ ] **Step 5: Run GREEN**

Run:

```bash
vendor/bin/phpunit tests/Integration/Auth/AuthRoutesTest.php --testdox
```

Expected: PASS with all prior F2 security tests intact.

- [ ] **Step 6: Commit**

```bash
git add app/Auth/routes.php resources/views/admin_users.php assets/app.css tests/Integration/Auth/AuthRoutesTest.php
git commit -m "feat(f3): integrate user management into mobile shell"
```

---

### Task 5: Owner Appearance Screen + Safe Logo Route

**Files:**
- Create: `resources/views/appearance.php`
- Create: `tests/Integration/UI/AppearanceRoutesTest.php`
- Modify: `app/UI/routes.php`
- Modify: `tests/Integration/Auth/CsrfRoutesTest.php`
- Modify: `assets/app.css`

**Interfaces:**
- Consumes: Task 1 `Branding::update()` / `Branding::logoFile()` and Task 3 app layout/current-user resolver.
- Produces routes:
  - `GET /admin/appearance` — owner-only.
  - `POST /admin/appearance` — owner-only; existing global CSRF applies.
  - `GET /brand/logo` — public current-logo response after installation; 404 when no valid logo exists.

- [ ] **Step 1: Write RED route tests**

Create tests:

```text
testAppearanceRequiresAuthentication
testAppearanceIsOwnerOnly
testOwnerCanUpdateAppNameAndPrimaryColor
testInvalidColorReturns422WithoutMutation
testValidPngUploadPersistsAndLogoRouteServesActualMime
testSvgAndOversizedLogoAreRejected
testAppearanceRespectsForcedPasswordChange
testAppearanceRespectsSubfolderPaths
```

Extend `CsrfRoutesTest` with:

```text
testAppearanceMutationWithoutCsrfIsForbidden
```

- [ ] **Step 2: Run RED**

Run:

```bash
vendor/bin/phpunit tests/Integration/UI/AppearanceRoutesTest.php tests/Integration/Auth/CsrfRoutesTest.php --testdox
```

Expected: FAIL because appearance/logo routes and view do not exist.

- [ ] **Step 3: Implement owner-only appearance GET/POST**

Rules:
- use actor role `owner`; no new permission constant;
- GET reads `Branding::current()` once;
- POST passes name/color/upload to `Branding::update()`;
- validation failure returns 422 with safe error inside the same app layout;
- success redirects `303` to `/admin/appearance?saved=1`;
- no flash-session subsystem.

- [ ] **Step 4: Implement current-logo route**

Use `Branding::logoFile()` only; return 404 if absent/invalid; set actual allowlisted `Content-Type`; stream bytes from the internal generated path. Do not accept any user-provided filename/path parameter.

- [ ] **Step 5: Build the appearance view**

Fields only:
- app name;
- primary color (`type=color` plus server validation);
- optional logo upload with PNG/JPEG/WebP guidance;
- current logo/fallback;
- success/error state.

No theme editor, palette builder, dark mode or logo cropper.

- [ ] **Step 6: Run GREEN**

Run:

```bash
vendor/bin/phpunit tests/Integration/UI/BrandingTest.php tests/Integration/UI/AppearanceRoutesTest.php tests/Integration/Auth/CsrfRoutesTest.php --testdox
```

Expected: PASS.

- [ ] **Step 7: KISS / Cero Ruido check**

Verify:
- no upload table;
- no public writable upload directory;
- no user-controlled logo retrieval parameter;
- no new permission;
- one brand source of truth.

- [ ] **Step 8: Commit**

```bash
git add app/UI/routes.php resources/views/appearance.php assets/app.css tests/Integration/UI/AppearanceRoutesTest.php tests/Integration/Auth/CsrfRoutesTest.php
git commit -m "feat(f3): add minimal owner branding settings"
```

---

### Task 6: Web Boundary + Asset/Accessibility Contract

**Files:**
- Modify: `.htaccess`
- Create: `tests/Unit/UI/AssetContractTest.php`
- Modify: `tests/Integration/Foundation/SecurityBoundaryTest.php`
- Modify: `assets/app.css` only if RED exposes a missing contract.
- Modify: `assets/app.js` only if RED exposes a missing accessibility behavior.

**Interfaces:**
- Consumes: static F3 assets/layout contracts from Tasks 2–5.
- Produces: no new application interface.

- [ ] **Step 1: Write RED web-boundary test**

Add `resources` to the required forbidden-directory list in `SecurityBoundaryTest::testApacheBoundaryBlocksInternalDirectories()`.

Also assert `.htaccess` does not forbid the real `assets` directory.

- [ ] **Step 2: Write RED asset contract tests**

Create tests named:

```text
testCssHasNoRemoteImportsOrFontUrls
testCssIncludesSafeAreaAndReducedMotionContracts
testCssDefinesVisibleFocusAndMinimumTouchTarget
testAppsJavascriptUsesNativeDialogWithoutExternalDependency
testAssetsContainNoFutureModuleLabels
```

Avoid pixel-perfect snapshot tests. Pin architecture/accessibility contracts only.

- [ ] **Step 3: Run RED**

Run:

```bash
vendor/bin/phpunit tests/Integration/Foundation/SecurityBoundaryTest.php tests/Unit/UI/AssetContractTest.php --testdox
```

Expected: resource-boundary test fails until `.htaccess` is fixed; any genuine asset-contract gap is visible.

- [ ] **Step 4: Close the Apache boundary**

Add `resources` to the existing single forbidden internal-path rule. Do not add separate per-directory `.htaccess` files.

- [ ] **Step 5: Make only minimal asset corrections required by RED**

No cosmetic expansion outside the approved spec.

- [ ] **Step 6: Run GREEN**

Run:

```bash
vendor/bin/phpunit tests/Integration/Foundation/SecurityBoundaryTest.php tests/Unit/UI/AssetContractTest.php --testdox
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add .htaccess assets/app.css assets/app.js tests/Integration/Foundation/SecurityBoundaryTest.php tests/Unit/UI/AssetContractTest.php
git commit -m "fix(f3): close UI web boundary and accessibility contracts"
```

---

### Task 7: F3 Full QA + Visual Review + Two Laws Closure

**Files:**
- Create after QA: `docs/checkpoints/F3_CLOSED_2026-10-11.md`
- Modify after QA: `README.md`
- No product-code changes unless a failing test/review requires a new RED→GREEN cycle.

**Interfaces:**
- Consumes: complete F3 branch.
- Produces: canonical F3 checkpoint and updated repository continuity hub.

- [ ] **Step 1: Run Composer validation**

```bash
composer validate --strict
```

Expected: PASS.

- [ ] **Step 2: Run complete PHPUnit suite**

```bash
vendor/bin/phpunit --testdox
```

Expected: 0 failures, 0 errors, 0 warnings attributable to project code.

- [ ] **Step 3: Verify PHP matrix in CI**

Require the exact implementation HEAD to pass:
- PHP 8.3;
- PHP 8.4;
- PHP 8.5;
- MariaDB 11.4 service.

Do not infer PHP matrix success from an older SHA.

- [ ] **Step 4: Perform viewport/accessibility review**

Review real rendered screens at least at:
- 360×800;
- 390×844;
- 412×915;
- tablet;
- wide desktop.

Check:
- no horizontal overflow;
- bottom nav does not cover content;
- Apps sheet opens/closes and remains usable with keyboard;
- visible focus;
- safe-area padding;
- zoom 200% keeps functionality;
- no sidebar appears;
- no future-module navigation appears.

If the current harness cannot render browser viewports, do **not** mark this complete from source inspection alone; use an available browser/Codex/Antigravity review before F3 closure.

- [ ] **Step 5: Audit deltas against `main`**

Record exact:

```text
ADDED
REMOVED
MERGED_OR_CONVERGED
DEPENDENCIES_DELTA
DB_SCHEMA_DELTA
PUBLIC_ROUTES_DELTA
```

Expected architectural deltas:
- Composer dependencies: `+0`;
- DB migrations/tables: `+0`;
- Node/npm/build files: `+0`;
- new real routes: `/account`, `/admin/appearance`, `/brand/logo`; installed `/` changes from plain text to authenticated UI ownership;
- future-module routes: `+0`.

- [ ] **Step 6: Run Cero Ruido sweep**

Explicitly search for:
- unused views/assets;
- duplicate render helpers;
- duplicate branding values;
- dead CSS selectors introduced during iteration;
- stale F3 pause checkpoints;
- placeholder navigation;
- unused JS functions;
- redundant settings keys.

Apply `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR` before adding anything.

- [ ] **Step 7: Create canonical checkpoint only after evidence is complete**

`docs/checkpoints/F3_CLOSED_2026-10-11.md` must contain:

```text
F3: CLOSED / VERIFIED / PENDING MERGE
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
implementation SHA
CI run IDs/results
PHPUnit totals
visual review evidence
added/removed/merged
schema/dependency/route deltas
open PR
next permitted phase: F4 only after merge approval
```

Do not retain competing temporary F3 checkpoints once canonical closure exists.

- [ ] **Step 8: Update README continuity hub**

README must point to the canonical F3 checkpoint and say F3 CLOSED/VERIFIED/PENDING MERGE. Do not claim F3 merged before it is actually merged.

- [ ] **Step 9: Run final CI on documentation HEAD**

Require PHP 8.3/8.4/8.5 GREEN on the exact final documentation SHA.

- [ ] **Step 10: Move PR to Ready for review, but do not merge**

PR body must summarize:
- F3 scope;
- QA totals;
- Two Laws PASS;
- no new DB tables/dependencies;
- visual review evidence;
- canonical checkpoint.

Wait for explicit user approval before merge.

---

# Plan Self-Review

## 1. Spec Coverage

Mapped:
- shared auth shell → Task 2;
- authenticated shell/topbar/bottom nav/Apps → Task 3;
- real home/account → Task 3;
- user administration convergence → Task 4;
- owner branding/name/color/logo → Tasks 1 + 5;
- safe logo fallback/storage → Tasks 1 + 5;
- no dead destinations → Task 3 + Task 6;
- responsive/touch/safe area/reduced motion → Tasks 3 + 6 + 7;
- direct view protection → Task 6;
- security regression → all targeted tasks + Task 7;
- PHP 8.3–8.5 → Task 7;
- Two Laws/checkpoint/README → Task 7.

No F4+ business module is implemented by this plan.

## 2. Step Scan

Each task owns one reviewable capability and its RED→GREEN cycle. No task asks an implementer to invent an unspecified class/interface. Product-code bodies are deliberately not prewritten in this plan.

## 3. Type / Interface Consistency

- `Branding` is the only branding source used by `View` and UI routes.
- `View` owns only rendering/layout composition.
- `currentUser` remains one injected closure rather than a new access-control service.
- F2 `Permissions`, `AuthService`, `SessionGuard` and global CSRF remain authoritative.
- `UI/routes.php` owns only real F3 routes.

## 4. Review Focus Coverage

All five Review Focus risks have explicit tests in Tasks 1, 3 and 5. The Apache boundary has its own Task 6 proof.

## 5. Proportion / KISS

The plan adds only the pieces required by the approved F3 spec. It explicitly rejects a component library, navigation service, media library, theme subsystem, CSS framework, build system and new data model.

---

# Execution Gate

This plan must be reviewed and approved before product-code implementation begins.

The established project execution method is **Native**: ChatGPT executes the plan task-by-task in the current harness, uses GitHub CI for PHP 8.3/8.4/8.5 evidence, and calls Codex/Antigravity only where an independent browser/QA review materially adds confidence.

After approval:

1. merge/promote the approved F3 spec + plan documentation into the implementation base without product code;
2. create a clean implementation branch from that documented base;
3. use `superpowers:using-git-worktrees` when a local workspace is available;
4. use `superpowers:executing-plans`;
5. execute Task 1 → Task 7 in order;
6. never merge F3 without explicit user approval.
