# F23 — Adaptive UX + Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver one modern SSR application with a shared navigation authority, a touch shell for mobile/tablet, a distinct desktop shell, and a useful non-decorative dashboard.

**Architecture:** Keep the existing Slim/PHP SSR stack. `View` becomes the single presentation authority for navigation metadata; `resources/views/layouts/app.php` renders the same permission-filtered collection as bottom launcher on touch and persistent sidebar on desktop. No SPA, no frontend framework, no second route tree, no new DB state, and no new domain service unless concrete dashboard query duplication proves one is necessary.

**Tech Stack:** PHP >=8.3 <8.6, Slim 4, server-rendered PHP views, existing `assets/app.css` + `assets/app.js`, PHPUnit 11.5.

**Spec:** `docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md` — sections 0–10, 13, 17–19.

## Global Constraints

- `NEW_TABLES = 0`; `NEW_RUNTIME_DEPS = 0`; `NEW_AUTH = 0`; `NEW_ROUTE_TREES = 0`.
- Prefer `NEW_DOMAIN_SERVICES = 0`; only allow one `DashboardService` if repeated query logic is proven.
- One navigation source feeds touch launcher and desktop sidebar.
- Touch shell through 1199px; desktop shell at >=1200px.
- Bottom navigation <=5 destinations; default V1 is Inicio / Apps / Cuenta.
- No user-agent sniffing, frontend build chain, emoji navigation icons, or Google Font dependency.
- Accessibility targets: visible focus, >=48px touch hit area, no color-only selected state, reduced-motion support, no horizontal overflow.
- F23 changes presentation only; no domain semantics.

## Pre-Execution Gate

- [ ] F20, F21 and F22 are certified on the actual base branch and final module/permission names are known.
- [ ] Re-read final `app/Auth/Permissions.php`, F20 membership routes and F22 owner-only audit route before freezing navigation.
- [ ] Record base SHA and confirm a clean isolated worktree.
- [ ] Confirm no unresolved Critical/Important issue remains in F22.

## Review Focus

1. Same permission set must produce the same visible modules in touch Apps and desktop sidebar — Task 1/2.
2. Tenant must never receive staff launcher/sidebar modules — Task 1/2.
3. Dock/sidebar must not obscure content or focus at 375/768/1024/1440 — Task 2/4.
4. Desktop keyboard/focus must work without hover — Task 2/4.
5. Dashboard must show real actionable state only — Task 3.

---

### Task 1: Single navigation authority

**Files:**
- Modify: `app/UI/View.php`
- Modify: `resources/views/layouts/app.php`
- Create: `tests/Integration/UI/NavigationModelTest.php`

**Interfaces:**
- Add private `View::navigationFor(array $user): array`.
- Each item contains only `key`, `label`, `href`, `icon`, `group`, and resolved visibility.
- `renderApp()` passes one `$navigation` collection to the layout.

- [ ] Write RED tests for owner/gestor/tenant collections, permission filtering, unique href/key, no future placeholders, and F20/F22 final modules only when authorized.
- [ ] Run `vendor/bin/phpunit tests/Integration/UI/NavigationModelTest.php` and require RED against current duplicated layout logic.
- [ ] Move module metadata/visibility into `View::navigationFor()`; remove duplicated permission booleans/module cards from the layout.
- [ ] Keep decisions based on existing `Permissions::allows()` and roles; add no navigation DB/config.
- [ ] Run GREEN and commit `refactor(f23): centralize app navigation model`.

### Task 2: Touch and desktop shells from the same layout

**Files:**
- Modify: `resources/views/layouts/app.php`
- Modify: `assets/app.css`
- Modify: `assets/app.js`
- Create: `tests/Integration/UI/AdaptiveShellTest.php`

**Interfaces:**
- Touch: compact topbar + fixed bottom navigation + Apps dialog/sheet.
- Desktop >=1200: persistent 240–280px sidebar + desktop topbar + main canvas; bottom dock hidden.
- Breakpoints: `0–767`, `768–1199`, `>=1200`.

- [ ] Write RED render assertions that both shells consume `$navigation`, selected state includes `aria-current`, decorative SVGs are `aria-hidden`, and icon-only controls have labels.
- [ ] Implement one local SVG icon map/helper inside presentation code; no JS icon package.
- [ ] Implement responsive shell CSS using semantic tokens and `env(safe-area-inset-bottom)`.
- [ ] Keep Apps launcher 2 columns mobile, 3–4 tablet; desktop uses grouped sidebar.
- [ ] Keep JS limited to dialog/sheet interaction, Escape/focus behavior and progressive enhancement.
- [ ] Add reduced-motion and `:focus-visible` rules.
- [ ] Run GREEN and commit `feat(f23): add adaptive touch and desktop shells`.

### Task 3: Useful staff dashboard without decorative analytics

**Files:**
- Modify: `app/Properties/routes.php`
- Modify: `resources/views/home.php`
- Modify only if proven necessary: existing domain services used by dashboard reads.
- Create: `tests/Integration/UI/DashboardTest.php`

**Interfaces:** Existing authenticated `/` route remains staff home; public `/` and tenant redirect behavior remain unchanged.

- [ ] Write RED tests for staff home, tenant redirect preservation, permission-aware quick actions and absence of fake/sample metrics.
- [ ] Reuse existing service reads for actionable summaries; if no meaningful metric is cheaply available, prefer navigation/actions instead of invented analytics.
- [ ] Do not add `DashboardService` unless at least two repeated multi-service queries are proven and one class reduces duplication.
- [ ] Run GREEN and commit `feat(f23): finalize actionable dashboard`.

### Task 4: Component/token convergence and page adaptation

**Files:**
- Modify: `assets/app.css`
- Modify only presentation markup where needed: properties/unit, candidate admin, finance, maintenance, tenant lease portal views.
- Create: `tests/Integration/UI/ResponsiveMarkupRegressionTest.php`

- [ ] Write RED/inspection tests for semantic buttons/inputs/cards/tables/badges/empty states, labels tied to fields, tokenized branding and no mobile interaction that requires horizontal-scroll-only tables on critical pages.
- [ ] Converge styles into semantic tokens for primary/secondary/surface/canvas/text/muted/status states.
- [ ] Preserve desktop tables where useful; adapt touch markup only where needed.
- [ ] Keep system-font scale and mobile inputs >=16px.
- [ ] Run affected UI/module suites and commit `refactor(f23): converge responsive component styling`.

### Task 5: F23 responsive/accessibility acceptance and checkpoint

**Files:**
- Create: `docs/checkpoints/F23_CHECKPOINT_2026-10-14.md`

- [ ] Run focal UI tests: NavigationModel, AdaptiveShell, Dashboard, ResponsiveMarkupRegression.
- [ ] Run full PHP suite once with `composer test`; run `composer validate --strict` and PHP lint.
- [ ] Verify critical private screens at `375x812`, `768x1024`, `1024x768`, `1440x900`: no overflow, focus visible, keyboard usable desktop, dock does not obscure controls, safe area respected, labels unclipped, dialog escapable, reduced motion respected.
- [ ] Verify public/auth pages remain related visually but do not receive private sidebar/dock.
- [ ] Fresh-review F23 for one navigation authority, zero domain-semantic change, zero runtime dependency and zero second component system.
- [ ] Write checkpoint with base/product/closure SHAs, target-width matrix, known minors and `MERGE_AUTHORIZED: NO`.
- [ ] Commit `docs(f23): close adaptive ux checkpoint`.

## Self-Review Result

- Spec coverage: PASS.
- Step scan: PASS.
- Type consistency: PASS — navigation authority stays in `View`.
- Review Focus: PASS.
- Proportion: PASS.

**Execution status:** `PLAN WRITTEN / USER REVIEW REQUIRED / F22 GREEN REQUIRED / IMPLEMENTATION NOT AUTHORIZED / MERGE NOT AUTHORIZED`.
