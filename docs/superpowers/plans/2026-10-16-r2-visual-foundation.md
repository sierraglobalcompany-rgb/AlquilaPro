# R2 Visual Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Separate the public visual surface from the authentication shell using only the existing layout/CSS owners, while preserving one shared visual foundation and all current runtime behavior.

**Architecture:** Keep the existing `AlquilaPro\UI\View`, `Branding`, three layouts and single `assets/app.css`. R2 changes only the public layout contract plus the CSS primitives/tests that prove it; it does not implement R3/R4 screens, routes, carousel behavior, fixtures or new frontend architecture.

**Tech Stack:** PHP 8.3–8.5, Slim 4, server-rendered PHP, native HTML/CSS/JavaScript, PHPUnit 11.5, MariaDB/MySQL test database.

**Spec:** `docs/superpowers/specs/2026-10-16-r2-visual-foundation-design.md`

## Global Constraints

- Base authority: `main@71445785f39caec7f4e7059a2a80ffbd75468b2d`.
- Preserve KISS: simple A→B ownership; no new architectural layer.
- Preserve Cero Ruido order: `ELIMINATE -> MERGE/CONVERGE -> SIMPLIFY -> IMPROVE/OPTIMIZE -> ADD`.
- Preserve Anti-parche order: `ROOT CAUSE -> OWNER -> DUPLICATION SEARCH -> RED -> VERIFY RED -> MINIMAL GREEN -> REMOVE SUPERSEDED -> REGRESSION`.
- `DB_NEW_TABLES: 0`.
- `MIGRATIONS: 0`.
- `NEW_SERVICES: 0`.
- `NEW_REPOSITORIES: 0`.
- `NEW_CONTROLLERS: 0`.
- `COMPOSER_NEW_DEPENDENCIES: 0`.
- `NODE_NPM: 0`.
- `SPA: NO`.
- `CSS_FRAMEWORK: NO`.
- `FRONTEND_BUILD_PIPELINE: NO`.
- `SECOND_STYLESHEET: NO`.
- `NEW_JS_RUNTIME: NO`.
- `NEW_ROUTE_DOMAIN: NO`.
- `NEW_BUSINESS_BEHAVIOR: NO`.
- Preserve PHP SSR and existing `View::renderPublic()` ownership.
- Keep exactly one principal stylesheet: `assets/app.css`.
- Do not implement carousel, final landing, final property cards, final public detail, embedded login, demo imagery or screenshot QA in R2.
- Do not resolve the R3 visual conflicts `X vs no-X` or `Usuario vs Usuario, correo o cédula` in R2.
- Do not remove the transitional `identifier ?? email` HTTP fallback in R2.
- No merge before exact-head CI is GREEN and the integration gate is reached.
- No deployment.

## Review Focus

1. **Public/auth ownership leak:** installed public `/` must render the public shell without `auth-shell` or `auth-card` as its primary structure; pinned in Task 1 `ShellRoutesTest`.
2. **Subfolder deployment:** public asset/home/catalog/login URLs must continue respecting `basePath`; pinned in Task 1 with `/sub` route assertions.
3. **Desktop width regression:** the public shell must not inherit the auth `520px` maximum; pinned in Task 1 `AssetContractTest`.
4. **Auth/private regression:** login and authenticated shell must keep their existing classes/contracts after public separation; pinned in Task 2 focal regression.
5. **R3 scope bleed:** R2 must not add final public carousel/login/marketing markup or a second stylesheet/runtime; pinned in Task 2 structural search assertions and final diff audit.

---

## File Structure Locked for R2

### Modify

- `tests/Integration/UI/ShellRoutesTest.php` — pin public ownership and subfolder URL behavior.
- `tests/Unit/UI/AssetContractTest.php` — pin public CSS foundation and single-authority constraints.
- `resources/views/layouts/public.php` — own the public structure explicitly.
- `assets/app.css` — add only the minimum namespaced public foundation rules.

### Read/verify but do not modify by default

- `app/UI/View.php` — existing render owner; no new method required.
- `resources/views/layouts/auth.php` — auth ownership must remain intact.
- `resources/views/layouts/app.php` — private shell must remain intact.
- `resources/views/public_landing.php` — R3 content; do not change unless a verified structural incompatibility makes it unavoidable.
- `tests/Integration/UI/AuthVisualTest.php` — focal regression.
- `tests/Integration/UI/ResponsiveMarkupRegressionTest.php` — focal responsive regression.

### Explicitly Do Not Create

- no new PHP class;
- no `DesignSystem` service;
- no component library/tree;
- no public-specific stylesheet;
- no JS module;
- no migration/table;
- no route/controller;
- no R3/R4 fixture or visual asset.

---

### Task 1: Public Foundation Contract RED

**Files:**
- Modify: `tests/Integration/UI/ShellRoutesTest.php`
- Modify: `tests/Unit/UI/AssetContractTest.php`

**Interfaces:**
- Consumes: existing installed public root rendered through `View::renderPublic(string $view, array $data = []): string`.
- Produces: executable structural contract for the public layout; no runtime interface change.

- [ ] **Step 1: Extend the installed public root integration test with ownership assertions**

In `ShellRoutesTest::testInstalledRootWithoutSessionRendersPublicLanding()`, keep the current assertions and add:

```php
self::assertStringContainsString('class="public-page"', $body);
self::assertStringContainsString('class="public-shell"', $body);
self::assertStringContainsString('class="public-main"', $body);
self::assertStringNotContainsString('class="auth-shell"', $body);
self::assertStringNotContainsString('class="auth-card"', $body);
```

The existing assertions for `data-public-shell`, `/catalog`, `/login` and absence of `data-f3-app-shell` remain.

- [ ] **Step 2: Add a subfolder regression test for the public shell**

Add `ShellRoutesTest::testPublicShellRespectsBasePathWithoutAuthContainer()`.

Use `$this->app('/sub')`, request `GET /sub/`, then assert:

```php
self::assertSame(200, $response->getStatusCode());
self::assertStringContainsString('href="/sub/assets/app.css"', $body);
self::assertStringContainsString('href="/sub/"', $body);
self::assertStringContainsString('href="/sub/catalog"', $body);
self::assertStringContainsString('href="/sub/login"', $body);
self::assertStringContainsString('class="public-shell"', $body);
self::assertStringNotContainsString('class="auth-shell"', $body);
```

- [ ] **Step 3: Add public CSS contract tests**

In `AssetContractTest.php`, add:

```php
public function testPublicFoundationHasOwnNamespacedLayoutContract(): void
{
    foreach (['.public-page', '.public-shell', '.public-main'] as $selector) {
        self::assertStringContainsString($selector, $this->css);
    }
}

public function testPublicFoundationIsNotLimitedToAuthCardWidth(): void
{
    self::assertDoesNotMatchRegularExpression(
        '/\.public-shell\s*\{[^}]*max-width\s*:\s*520px/s',
        $this->css,
    );
    self::assertDoesNotMatchRegularExpression(
        '/\.public-main\s*\{[^}]*max-width\s*:\s*520px/s',
        $this->css,
    );
}
```

Do not add a brittle full-CSS snapshot.

- [ ] **Step 4: Run the focal RED tests**

Run:

```bash
vendor/bin/phpunit tests/Integration/UI/ShellRoutesTest.php tests/Unit/UI/AssetContractTest.php --testdox
```

Expected: FAIL because `public.php` still uses `auth-page/auth-shell/auth-card` and `app.css` has no `public-page/public-shell/public-main` rules.

- [ ] **Step 5: Verify RED is causal, not collateral**

Confirm failures are limited to the new public foundation assertions. If unrelated pre-existing tests fail, stop and diagnose before implementation.

- [ ] **Step 6: Commit the verified RED only**

```bash
git add tests/Integration/UI/ShellRoutesTest.php tests/Unit/UI/AssetContractTest.php
git commit -m "test(r2): define public foundation ownership contract"
```

Checkpoint rule: after this commit, update the live PR body/state; do not add a separate checkpoint commit merely to restate RED.

---

### Task 2: Minimal Public Foundation GREEN

**Files:**
- Modify: `resources/views/layouts/public.php`
- Modify: `assets/app.css`
- Test: `tests/Integration/UI/ShellRoutesTest.php`
- Test: `tests/Unit/UI/AssetContractTest.php`

**Interfaces:**
- Consumes: unchanged `View::renderPublic()` variables `$content`, `$branding`, `$basePath`, `$pageTitle`.
- Produces: public root markup with `public-page`, `public-shell`, `public-main`; no new PHP/public API.

- [ ] **Step 1: Replace auth structural wrappers in `public.php`**

Keep the current document head, title calculation, stylesheet URL, brand color inline variable, logo/fallback logic and `$content` rendering.

Set the body and main structure to the public owner contract:

```html
<body class="public-page" ...>
<div class="public-shell" data-public-shell>
    ...existing brand link...
    <main class="public-main">
        ...$content...
    </main>
</div>
</body>
```

The brand link remains `href="<?= $escape($basePath) ?>/"` and the logo route remains `<?= $escape($basePath) ?>/brand/logo`.

Do not add nav, carousel, hero, login card, property markup or JavaScript.

- [ ] **Step 2: Add the minimum namespaced CSS foundation**

In `assets/app.css`, add only rules required to make the public shell independent from auth while using existing tokens.

Required contract:

```css
.public-page {
    min-height: 100dvh;
}
.public-shell {
    width: 100%;
    min-height: 100dvh;
}
.public-main {
    width: min(100%, 1440px);
    margin: 0 auto;
    padding: 24px 16px 40px;
}
```

The public brand may reuse `.auth-brand` only if that reuse does not reintroduce an auth container dependency; prefer an existing neutral brand rule only if already available. If a public-specific brand wrapper is required, add one namespaced rule rather than mutating auth behavior.

For tablet/desktop, use the existing breakpoint structure and only widen gutters, e.g. `28px` at tablet and `clamp(24px, 4vw, 56px)` at desktop. Do not add viewport-specific width hacks.

- [ ] **Step 3: Run Task 1 tests and verify GREEN**

Run:

```bash
vendor/bin/phpunit tests/Integration/UI/ShellRoutesTest.php tests/Unit/UI/AssetContractTest.php --testdox
```

Expected: PASS.

- [ ] **Step 4: Run auth/private/responsive focal regression**

Run:

```bash
vendor/bin/phpunit \
  tests/Integration/UI/AuthVisualTest.php \
  tests/Integration/UI/ResponsiveMarkupRegressionTest.php \
  tests/Integration/UI/ShellRoutesTest.php \
  tests/Unit/UI/AssetContractTest.php \
  --testdox
```

Expected: PASS with no change to auth/private shell behavior.

- [ ] **Step 5: Search the R2 diff for prohibited architecture/scope bleed**

Verify the branch diff introduces none of:

```text
DesignSystem
new stylesheet link
@import
npm/package.json
carousel
Propiedades disponibles
Google login
```

`Propiedades disponibles` is intentionally absent in R2 because it belongs to R3.

- [ ] **Step 6: Commit minimal GREEN**

```bash
git add resources/views/layouts/public.php assets/app.css
git commit -m "feat(r2): separate public visual foundation"
```

Checkpoint rule: after the GREEN commit and focal verification, update live PR state. Do not create a redundant runtime-neutral checkpoint commit.

---

### Task 3: Full Regression + Exact-Head CI

**Files:**
- No planned runtime modifications.
- Modify only if a real regression is reproduced and root-caused through the Anti-parche workflow.

**Interfaces:**
- Consumes: Task 2 GREEN HEAD.
- Produces: certified R2 product SHA eligible for closure audit.

- [ ] **Step 1: Run the complete local test suite**

Run the repository's canonical full PHPUnit command used by CI.

Expected: `0 failures`, `0 errors`; existing intentional skips remain documented rather than patched away.

- [ ] **Step 2: Run project static/package checks used by CI**

Run the same Composer validation/static checks present in `.github/workflows` for the active CI workflow.

Expected: PASS.

- [ ] **Step 3: Open/update the R2 draft PR against `main`**

The PR body must record:

- base SHA;
- current R2 HEAD;
- Task 1 RED commit and causal failure;
- Task 2 GREEN commit;
- focal/full regression results;
- KISS/Cero Ruido/Anti-parche budget;
- `R3: NOT STARTED`;
- `DEPLOYMENT_AUTHORIZED: NO`.

Use the PR body as the live continuity surface.

- [ ] **Step 4: Obtain exact-head CI PHP 8.3 / 8.4 / 8.5**

Expected: all three jobs `SUCCESS` on the same final product HEAD.

Do not certify from an ancestor SHA.

- [ ] **Step 5: If CI fails, stop and root-cause**

Required order:

```text
REPRODUCE -> ROOT CAUSE -> OWNER -> DUPLICATION SEARCH -> RED -> VERIFY RED -> MINIMAL GREEN -> REGRESSION
```

No blind retry/patch and no weakening tests to make CI green.

---

### Task 4: Closure Audit + Canonical Checkpoint + Merge Gate

**Files:**
- Create only at closure: `docs/checkpoints/R2_VISUAL_FOUNDATION_GREEN_CHECKPOINT_2026-10-16.md`
- Update PR body/state.

**Interfaces:**
- Consumes: exact-head CI GREEN from Task 3.
- Produces: one durable R2 checkpoint and a merge-gate-ready PR.

- [ ] **Step 1: Run the 10-pass closure audit**

Verify:

1. Base/drift: branch is based on the intended post-R1 `main` or any later audited fast-forward base.
2. Ownership: public uses explicit public wrappers; auth/app owners remain separate.
3. KISS: no new class/service/table/package/route/domain.
4. Cero Ruido: one CSS authority; no duplicate public stylesheet/layout.
5. Anti-parche: auth wrappers removed from public owner rather than overridden.
6. TDD: RED commit exists before GREEN.
7. Responsive: foundation supports 375/768/1440 without auth 520px constraint.
8. Security/function: auth, CSRF, permission, tenant and business tests remain GREEN.
9. Scope: no R3/R4 screen content or imagery implemented.
10. Governance: exact-head CI 8.3/8.4/8.5 GREEN; deployment remains NO.

- [ ] **Step 2: Create one canonical R2 checkpoint**

The checkpoint must record:

- base and final HEAD;
- RED/GREEN commits;
- focal/full test evidence;
- exact CI run/jobs;
- final changed-file list;
- KISS/Cero Ruido/Anti-parche results;
- remaining R3 blockers (`X/no-X`, login copy, fallback removal gate);
- `R3: NOT STARTED`;
- `DEPLOYMENT_AUTHORIZED: NO`;
- exact next action = merge gate.

Do not create additional transient checkpoint files on the active branch.

- [ ] **Step 3: Commit closure documentation**

```bash
git add docs/checkpoints/R2_VISUAL_FOUNDATION_GREEN_CHECKPOINT_2026-10-16.md
git commit -m "docs(r2): close visual foundation checkpoint"
```

Because this changes HEAD, obtain exact-head CI for the final closure SHA before merge, or keep closure evidence outside the active branch until product CI is certified and use the PR body as live state. Prefer the latter when it avoids ritual CI loops.

- [ ] **Step 4: Final merge-gate verification**

Refresh immediately before merge:

- PR head/base;
- `main` SHA;
- mergeability;
- exact-head CI;
- review threads;
- drift compared with base.

- [ ] **Step 5: Stop at merge gate unless the owner says `continua`**

Project convention: the owner has explicitly established that `continua` authorizes the next planned gate, including merge when merge is the current gate.

After authorized merge:

- verify the new `main` SHA;
- create a post-merge recovery checkpoint on a separate `checkpoint/...` branch if useful;
- do not deploy;
- only then open R3.
