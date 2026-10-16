# AlquilaPro Human-First UI/UX Governance Design

**Date:** 2026-10-16  
**Status:** REVIEW GATE  
**Base authority:** `main@b0403a5a1e544d95aa02917ef69687c560a94201`  
**Working branch:** `chore/human-first-uiux-governance-20261016`  
**Frozen product branch:** `impl/r3-public-surfaces-20261016@845295c777151216655b7a7866bc0dc9d93f3e2e`  
**Frozen product PR:** `#33` — OPEN / DRAFT / NOT MERGED  
**Deployment:** NOT AUTHORIZED

## 1. Goal

Install the attached **ERP HUMAN-FIRST UI/UX MASTER SKILL — V2.1 FINAL UNIVERSAL** as a durable repository law for AlquilaPro, adapt it to the project's existing owners without creating a parallel frontend architecture, and then use that law to audit the complete ERP before any broad UI/UX remediation.

This work is governance-first. It does **not** redesign AlquilaPro in one batch and it does **not** replace the current PHP SSR/CSS/JS architecture.

The target operating loop is the V2.1 canonical loop:

```text
LOAD LAW
→ LOAD DESIGN SYSTEM
→ VERIFY TRUTH
→ DEFINE ONE MICROBLOCK
→ RED
→ GREEN
→ AUTOMATED QA
→ REAL BROWSER
→ HUMAN-FIRST AUDIT
→ CHECKPOINT
```

The Human-First mantra is preserved exactly:

```text
VERDAD
→ COMPRENSIÓN
→ DECISIÓN
→ ACCIÓN
→ DETALLE
```

## 2. Source and authority

### 2.1 Installation source

The attached source file `Skill diseño ERP 2.1.md` is the requested basis for this installation.

Canonical source metadata:

```text
MASTER_VERSION=2.1
UPSTREAM=nextlevelbuilder/ui-ux-pro-max-skill
UPSTREAM_REVIEWED_SHA=56ea9cf174ed86786187daa9de0e6247cb0747e4
UPSTREAM_REVIEWED_DATE=2026-10-16
AUDIT_PASSES=10
```

The official upstream SHA was independently revalidated before this design was written and still matches the V2.1 declaration.

### 2.2 Authority after installation

Once merged, the canonical UI/UX governance authority becomes:

```text
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
        ↓
design-system/alquilapro/MASTER.md
        ↓
design-system/alquilapro/pages/<page>.md   # only when a real exception/override exists
        ↓
existing component/token/layout owner
        ↓
implementation
```

Project operational authority remains:

1. live GitHub repository state;
2. CI on the exact SHA when executable code changes;
3. `docs/CURRENT_CHECKPOINT.md` for the current operational pointer;
4. approved applicable spec/plan;
5. `README.md` and `AGENTS.md` for durable orientation/guardrails;
6. chats/handoffs only when they do not contradict higher authority.

Repository authority wins over stale chat context or a prompt with an obsolete SHA.

## 3. Existing AlquilaPro owners that MUST be preserved

The installation must converge onto the current architecture rather than inventing a second design system runtime.

Current owners:

- `app/UI/View.php` owns SSR view rendering and navigation model construction;
- `resources/views/layouts/auth.php` owns the auth shell;
- `resources/views/layouts/public.php` owns the public shell;
- `resources/views/layouts/app.php` owns the authenticated application shell;
- `assets/app.css` remains the single principal stylesheet and current token/layout authority;
- `assets/app.js` remains the single application JavaScript file;
- domain services remain truth owners for their own business data;
- permission checks remain in the existing authorization model and must not be duplicated in UI governance documents.

The governance layer describes how UI work is performed and audited. It must not become a runtime service, DI layer, theme engine, second navigation model, CSS framework, component framework, SPA or frontend build pipeline.

## 4. Approved installation strategy

Selected approach: **one canonical Master + thin adapters + project-specific design master**.

This is the KISS choice because it satisfies the V2.1 persistence contract without maintaining multiple independent copies of the full law.

### 4.1 Canonical Master

Create:

```text
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
```

Requirement:

- preserve the attached V2.1 law as the canonical repository copy;
- do not rewrite it into an AlquilaPro-specific fork;
- do not place branch/SHA/module-specific state inside the universal Master;
- future updates follow the V2.1 version-control procedure rather than silent edits.

### 4.2 AGENTS activation contract

Update the existing `AGENTS.md` instead of replacing it.

Preserve all current AlquilaPro laws:

- KISS;
- Cero Ruido;
- Anti-parche;
- TDD;
- CI exact-head evidence;
- checkpoint discipline;
- security and product guardrails;
- current recovery roadmap.

Add one durable section equivalent to the V2.1 mandatory contract:

```text
For any user-facing UI/UX task:
1. read docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md;
2. read design-system/alquilapro/MASTER.md;
3. read page override if one exists;
4. read docs/CURRENT_CHECKPOINT.md;
5. verify backend truth, permissions, routes and capabilities;
6. declare the Human-First context loaded;
7. work one isolated microblock;
8. run automated QA;
9. run real-browser QA for user-facing changes;
10. persist checkpoint before opening another UI block.
```

Conflict rule:

```text
TRUTH + SECURITY + ACCESSIBILITY > VISUAL PREFERENCE
```

No route, data, capability, metric or permission may be invented to satisfy a design.

### 4.3 Project design master

Create:

```text
design-system/alquilapro/MASTER.md
```

This file is AlquilaPro-specific and must describe the existing system rather than inventing a new one.

Required sections:

- Product;
- Users / roles;
- Human-First laws;
- Truth owners;
- Visual direction;
- current color tokens;
- typography;
- spacing;
- radius/shadow;
- current components/primitives;
- navigation;
- responsive contract;
- accessibility target;
- data-density policy;
- tables/lists/cards;
- forms;
- status language;
- empty/unknown/conflict/error/loading states;
- motion;
- anti-patterns;
- browser QA matrix;
- localization rules supported by current product truth.

It must explicitly reference the current one-stylesheet/one-JS architecture and existing view/layout owners.

It must not embed transient branch/SHA state. That belongs in `docs/CURRENT_CHECKPOINT.md`.

### 4.4 Page overrides

Do **not** create placeholder override files for every page.

Create:

```text
design-system/alquilapro/pages/<page>.md
```

only when a page needs:

- a real exception to the project master;
- a page-specific job/action contract that cannot be expressed by the master;
- a documented temporary deviation that has owner, risk, evidence and review date.

An override must contain:

```text
EXCEPTION_ID=
LAW_OVERRIDDEN=
REASON=
USER_BENEFIT=
RISK=
EVIDENCE=
OWNER=
DATE=
REVIEW_DATE=
```

No silent exception. No permanent exception because a mockup happens to show something different.

Git does not preserve empty directories, so the installation will not add `.gitkeep` merely to force an empty `pages/` folder. That would be noise.

### 4.5 GitHub skill adapter

Create the intentionally thin adapter:

```text
.github/skills/erp-human-first-ui-ux/SKILL.md
```

Its responsibility is activation only:

- load Master;
- load project design master;
- load page override if present;
- load current checkpoint;
- verify truth/permissions/routes;
- apply Human-First/no-infinite-Excel/progressive disclosure/accessibility;
- require UX contract before production UI changes;
- require browser evidence before final GREEN;
- persist checkpoint;
- never silently override the canonical Master.

It must **not** duplicate the full Master text.

### 4.6 Pull-request gate

Create:

```text
.github/pull_request_template.md
```

Add the V2.1 Human-First gate for UI-changing PRs, including:

- Master read;
- project master read;
- page override read or absent;
- truth owner verified;
- permissions verified;
- primary job/action defined;
- raw machine language not dominant;
- no unnecessary table/empty table chrome;
- UNKNOWN != 0;
- mobile/tablet/desktop defined;
- keyboard/focus checked;
- contrast/accessibility reviewed;
- no primary-page horizontal scroll;
- automated QA GREEN;
- real-browser QA GREEN;
- screenshots/evidence referenced;
- checkpoint persisted.

For a UI PR, an incomplete Human-First gate means:

```text
NOT READY FOR MERGE
```

Backend-only PRs are not falsely blocked by UI/browser items that do not apply; they may mark them N/A with a reason.

### 4.7 Current checkpoint

Create:

```text
docs/CURRENT_CHECKPOINT.md
```

This is a compact operational pointer, not another historical handoff archive.

Required fields:

```text
MAIN_SHA=
ACTIVE_PHASE=
ACTIVE_BRANCH=
ACTIVE_PR=
ACTIVE_HEAD=
CI_STATE=
HUMAN_FIRST_MASTER_VERSION=
PROJECT_DESIGN_MASTER_VERSION=
BROWSER_QA_STATE=
BLOCKERS=
NEXT_GATE=
MERGE_AUTHORIZED=
DEPLOYMENT_AUTHORIZED=
```

At installation time it must truthfully state:

- `main=b0403a5a...` unless repository drift occurs before write;
- R3 remains the active product phase;
- R3 branch/head/PR remain frozen and recoverable;
- R3 visual browser QA remains blocked until real browser evidence exists;
- this governance interrupt is not R4 and does not authorize R3 merge/deploy.

### 4.8 README anti-drift convergence

The current README still describes an older R1 state and an obsolete `main` SHA.

The installation may update the **current-state/orientation block only** so README no longer contradicts live repository authority.

Do not rewrite historical phase documents. Do not delete unique evidence merely to make documentation look clean.

## 5. Human-First activation handshake

Before any future UI implementation, the agent/work log must establish:

```text
HUMAN_FIRST_LAW_LOADED=YES
PROJECT_DESIGN_MASTER_LOADED=YES
PAGE_OVERRIDE_LOADED=YES|NO|NOT_PRESENT
CURRENT_CHECKPOINT_LOADED=YES
TRUTH_OWNER_VERIFIED=YES
AUTHORIZATION_MODEL_VERIFIED=YES
TARGET_VIEWPORTS_DEFINED=YES
UI_TASK_SCOPE=<one microblock>
```

If a required item is unknown:

```text
DO NOT START UI IMPLEMENTATION
```

Resolve authority first.

## 6. Definition of Ready for UI work

A UI task is not ready until the following are known:

```text
USER=
JOB_TO_BE_DONE=
TRUTH_OWNER=
AUTHORIZED_ROLES=
PRIMARY_ACTION=
PRIMARY_INFORMATION=
SECONDARY_INFORMATION=
TECHNICAL_INFORMATION=
EMPTY_STATE=
UNKNOWN_STATE=
CONFLICT_STATE=
ERROR_STATE=
LOADING_STATE=
TARGET_VIEWPORTS=
ACCESSIBILITY_TARGET=
RISK_TIER=
```

Do not design pixels first and infer product truth later.

## 7. Risk tiers

Preserve V2.1 risk classification:

### T0 — Cosmetic

Examples: spacing, typography, token, icon alignment.

Requires at minimum visual QA + accessibility regression check.

### T1 — Interaction

Examples: modal, launcher, form, focus behavior, navigation.

Requires lower-tier checks plus interaction tests, keyboard QA and browser QA.

### T2 — Truth semantics

Examples: status interpretation, money meaning, UNKNOWN/CONFLICT/0, summary derived from domain truth.

Requires lower-tier checks plus backend truth verification, explicit UNKNOWN/CONFLICT/0 coverage and factual browser fixtures.

### T3 — Security/authorization

Examples: role visibility, destructive action, secrets, company switching, admin controls.

Requires all lower-tier checks plus authorization test, negative-role test, security review and browser role QA.

Higher tiers include lower-tier requirements.

## 8. Audit mode after installation

After governance is installed, the next product activity is **AUDIT ONLY — DO NOT FIX**.

The audit covers the current ERP surfaces and must use the V2.1 finding contract.

Each finding must include:

```text
ID
Severity
Law
Evidence
Expected
Actual
Root
Action
Disposition = KEEP|MERGE|OPTIMIZE|SIMPLIFY|REMOVE|DEFER
```

No code remediation starts while the inventory/root-cause report is still incomplete.

## 9. Mandatory 10-pass ERP audit

The post-installation audit is split into ten independent passes so one broad impression cannot substitute for evidence:

1. **Truth / permissions** — correct owners, role visibility, UNKNOWN/CONFLICT/0 semantics, no fake capabilities;
2. **Human comprehension** — 5–10 second comprehension, primary job/action, status language, raw machine language secondary;
3. **Information architecture** — hierarchy, navigation, progressive disclosure, drill-down;
4. **Density / Infinite Excel** — table necessity, column budget, one-record tables, empty table chrome, repeated row actions, exports/filters/pagination;
5. **Visual system** — tokens, typography, spacing, cards, forms, tables, statuses, iconography, cross-page consistency;
6. **Accessibility** — contrast, focus, keyboard, semantics, target sizes, reduced motion, zoom, screen-reader affordances;
7. **Interaction / forms / feedback** — validation, destructive confirmation, success/error/loading/empty/conflict behavior;
8. **Responsive** — mobile-first, tablet, desktop, safe areas, sticky/fixed clearance, primary-page horizontal scroll;
9. **Data-heavy operations / performance UX** — filters, search, pagination, bulk actions, saved views when justified, freshness/staleness, audit visibility, keyboard-heavy desktop flows;
10. **Real browser / cross-page consistency** — rendered application at target viewports, console, 5xx, overflow, factual fixtures, screenshot/evidence.

Stop and report on BLOCKER/CRITICAL findings before broad remediation.

## 10. Human comprehension test

For each audited page, without prior explanation, a reviewer should be able to answer:

1. What page is this?
2. What is happening?
3. What requires attention?
4. What would I do now?
5. Where would I see detail?

If two or more fail:

```text
NOT HUMAN-FIRST
```

## 11. Infinite Excel test

Flag a page when multiple symptoms appear:

- more than 10 primary columns;
- more than one large table without hierarchy;
- no summary;
- no clear filters;
- no drill-down;
- raw IDs dominate;
- actions repeated row-by-row;
- mandatory mobile horizontal scrolling;
- a single record displayed as a table;
- empty tables rendered as chrome.

Preferred remediation order:

```text
summary
→ filter
→ column reduction
→ group
→ card
→ detail page
→ disclosure
→ pagination
```

Do not convert every table into cards. Preserve tables where comparison genuinely is the human job.

## 12. Audit output and remediation roadmap

Produce one canonical audit report after the ten passes.

Recommended path:

```text
docs/qa/HUMAN_FIRST_UI_UX_AUDIT_2026-10-16.md
```

Report sections:

- authority;
- screens reviewed;
- overall score;
- blockers;
- findings;
- cross-page consistency;
- responsive;
- accessibility;
- human language;
- data density;
- truth semantics;
- remediation plan;
- next checkpoint.

Convert findings into ordered remediation:

```text
P0 = truth / security / accessibility blockers
P1 = comprehension / primary workflows / navigation
P2 = responsive / forms / data presentation
P3 = visual consistency / polish
P4 = optional enhancements only after evidence
```

Every fix later returns to the existing AlquilaPro Anti-parche loop:

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

## 13. Browser QA constraint

The attached law requires real-browser evidence before final GREEN for user-facing changes.

At the time of this design, R3 already proves the current environment blocker:

```text
R3_VISUAL_QA=BLOCKED_ENVIRONMENT
R3_MERGE_GATE=BLOCKED_VISUAL_QA
```

Therefore:

- repository/static audit passes may proceed without pretending browser evidence exists;
- pass 10 must remain `BLOCKED_ENVIRONMENT` until a real renderable AlquilaPro instance is available to the connected browser;
- no screenshot substitute, CI text assertion or imagined visual inspection may be reported as real-browser QA;
- the global audit cannot be called fully certified while pass 10 is blocked.

## 14. Branch/isolation strategy

This governance installation is an explicit interrupt from `main`, not a continuation commit on the R3 branch.

Rules:

- branch from exact `main@b0403a5a...`;
- keep `impl/r3-public-surfaces-20261016@845295c7...` unchanged;
- do not cherry-pick partial governance commits into R3 while the governance branch is under review;
- after governance is approved and integrated, rebase/merge strategy for R3 is determined from live drift, not assumed in advance;
- R4 remains NOT STARTED;
- deployment remains NO.

## 15. Files planned for the installation gate

### Create

```text
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
docs/CURRENT_CHECKPOINT.md
design-system/alquilapro/MASTER.md
.github/skills/erp-human-first-ui-ux/SKILL.md
.github/pull_request_template.md
```

### Modify

```text
AGENTS.md
README.md   # only stale live-state/orientation convergence
```

### Do not create by default

```text
design-system/alquilapro/pages/*.md  # only for real overrides
new CSS file
new JS file
new PHP class/service/provider
new route/controller
new database table/migration
Node/npm package
frontend framework
SPA
component library
parallel design-token runtime
new CI workflow solely for governance
```

### Do not modify during installation

```text
assets/app.css
assets/app.js
app/**/*.php
resources/views/**/*.php
database/**
tests for product behavior
R3 product branch files
```

## 16. Verification for the installation gate

Because the installation is documentation/governance only, runtime RED/GREEN is not fabricated.

Verification must instead prove:

```text
MASTER_SKILL_IN_REPO=YES
AGENTS_REFERENCES_MASTER=YES
PROJECT_DESIGN_MASTER=YES
PAGE_OVERRIDES_AS_NEEDED=YES
PR_GATE_PRESENT=YES
CHECKPOINT_CURRENT=YES
SKILL_ADAPTER_THIN=YES
MASTER_DUPLICATED=NO
README_LIVE_STATE_NOT_STALE=YES
R3_BRANCH_UNCHANGED=YES
R4_NOT_STARTED=YES
DEPLOYMENT_AUTHORIZED=NO
```

If executable files unexpectedly change, stop and reclassify the task; executable changes require the normal TDD/CI contract.

## 17. KISS / Cero Ruido / Anti-parche budget

### KISS

- one canonical universal law;
- one project design master;
- thin adapters;
- existing runtime owners;
- no new frontend architecture.

### Cero Ruido

Required order:

```text
ELIMINAR
→ UNIR/CONVERGER
→ SIMPLIFICAR
→ MEJORAR/OPTIMIZAR
→ AGREGAR
```

Practical application:

- converge stale README live-state text instead of adding another status document;
- `CURRENT_CHECKPOINT.md` is a compact pointer, not a duplicate history archive;
- no placeholder page overrides;
- no duplicate Master in adapter/AGENTS;
- no `.gitkeep` for empty directory ceremony;
- historical evidence remains in Git history/docs unless a later dedicated noise-cleanup gate proves safe removal.

### Anti-parche

The Skill is not permission to cosmetically patch every page.

The ERP audit must identify cause/root owner first. Remediation is microblock-based and follows TDD where behavior changes.

## 18. Ten-pass review of this design

This design itself is reviewed against the same project laws before implementation:

1. **Authority:** attached V2.1 is preserved as installation source; repo/CI remain operational authority — PASS.
2. **Portability:** universal Master remains universal; AlquilaPro details stay in project master/checkpoint — PASS.
3. **Persistence:** all required repository persistence points are represented — PASS.
4. **Activation:** AGENTS + thin GitHub skill adapter define automatic loading — PASS.
5. **Human-First:** decision-first, progressive disclosure, no fake data and anti-infinite-Excel are explicit — PASS.
6. **Architecture:** no second runtime/design framework is introduced — PASS.
7. **Accessibility/responsive:** target governance and browser requirement are explicit — PASS.
8. **Data-heavy ERP:** truth semantics, density, tables, filters and roles are included — PASS.
9. **Audit/remediation:** ten passes, finding contract and P0–P4 ordering are explicit — PASS.
10. **Anti-drift:** current main/R3 exact SHAs, isolated branch, compact checkpoint and README convergence are explicit — PASS.

Result:

```text
SPEC_10_PASS_AUDIT=PASS
RUNTIME_CHANGES=0
R3_PRODUCT_BRANCH_CHANGED=NO
READY_FOR_OWNER_SPEC_REVIEW=YES
```

## 19. Next gate after owner approval

After this spec is approved:

1. write the implementation plan using the repository planning workflow;
2. audit that plan for KISS / Cero Ruido / Anti-parche;
3. install the governance files in one isolated batch or a few logically atomic tasks;
4. verify persistence and anti-drift contracts;
5. create/update a draft PR and checkpoint;
6. only then run the full ERP Human-First 10-pass audit in **AUDIT ONLY** mode;
7. present findings/P0–P4 plan before any broad remediation.

No merge or deployment is authorized by approval of this design alone.
