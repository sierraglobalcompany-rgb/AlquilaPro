# AlquilaPro Human-First UI/UX Governance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Install ERP HUMAN-FIRST UI/UX MASTER SKILL V2.1 as durable AlquilaPro repository governance, verify the installation without changing runtime behavior, then audit the existing ERP in AUDIT ONLY mode and produce a prioritized P0–P4 remediation report before any broad UI/UX fixes.

**Architecture:** Keep the current PHP SSR runtime untouched. Add one canonical universal Master, one AlquilaPro-specific design Master, thin activation/checklist adapters, one compact current checkpoint, and minimal anti-drift README convergence. The subsequent ERP audit reads those authorities, inventories the existing UI/truth owners, performs the ten Human-First passes, and records findings without fixing them.

**Tech Stack:** Markdown repository governance; existing PHP 8.3–8.5 / Slim SSR / Composer / PHPUnit project; GitHub PR/CI evidence; real browser required only for audit pass 10 and future user-facing GREEN certification.

**Spec:** `docs/superpowers/specs/2026-10-16-human-first-uiux-governance-design.md`

## Global Constraints

- Installation source is the attached `Skill diseño ERP 2.1.md` and must remain canonical/universal rather than becoming an AlquilaPro-specific fork.
- `MASTER_VERSION=2.1`.
- `UPSTREAM=nextlevelbuilder/ui-ux-pro-max-skill`.
- `UPSTREAM_REVIEWED_SHA=56ea9cf174ed86786187daa9de0e6247cb0747e4`.
- Preserve one runtime architecture: `app/UI/View.php` + existing SSR layouts + `assets/app.css` + `assets/app.js`.
- Do not create another CSS/JS runtime, SPA, frontend framework, component library, service/provider/route/controller, DB migration/table, Node/npm pipeline or governance platform.
- Preserve existing KISS, Cero Ruido, Anti-parche, TDD, security and roadmap laws in `AGENTS.md`.
- `TRUTH + SECURITY + ACCESSIBILITY > VISUAL PREFERENCE`.
- No invented routes, data, metrics, capabilities or permissions.
- Page overrides are created only for real exceptions; no placeholder files and no `.gitkeep` ceremony.
- Governance installation is documentation-only. If an executable/product file changes, STOP and reclassify under normal TDD/CI rules.
- R3 remains frozen at `impl/r3-public-surfaces-20261016@845295c777151216655b7a7866bc0dc9d93f3e2e` unless live authority proves drift before execution.
- R3 PR #33 remains OPEN/DRAFT/NOT MERGED during this interrupt.
- R4 remains NOT STARTED.
- Deployment remains NOT AUTHORIZED.
- After installation, the first ERP activity is `AUDIT ONLY — DO NOT FIX`.
- Audit pass 10 cannot be reported GREEN without real rendered browser evidence.

## Review Focus

1. **Canonical-law duplication:** adapters or `AGENTS.md` must reference the Master rather than copy its full text; verification compares size/content responsibility and rejects a second independent Master.
2. **Operational drift:** `docs/CURRENT_CHECKPOINT.md` and README must reflect live GitHub state at execution time, not hard-code a stale SHA from this plan; verification re-reads `main`, R3 branch and PR immediately before writing.
3. **Invented project truth:** `design-system/alquilapro/MASTER.md` may document only routes/roles/tokens/layouts/capabilities evidenced by the repository; every uncertain point is marked unknown/audit-needed rather than invented.
4. **Runtime contamination:** installation diff must contain only approved governance/docs paths; verification fails if `app/**`, `assets/**`, `resources/views/**`, `database/**` or product tests change.
5. **False audit certification:** passes 1–9 may use repository/static evidence, but pass 10 stays `BLOCKED_ENVIRONMENT` unless a real AlquilaPro browser session is inspected at target viewports with screenshot/console/overflow evidence.

---

### Task 1: Install the canonical V2.1 Master and thin Skill adapter

**Files:**
- Create: `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md`
- Create: `.github/skills/erp-human-first-ui-ux/SKILL.md`
- Source: attached `Skill diseño ERP 2.1.md`

**Interfaces:**
- Consumes: the approved V2.1 attachment and metadata in the spec.
- Produces: one canonical repository law and one thin activation adapter that all later UI/UX work can load.

- [ ] **Step 1: Re-read the source metadata before writing**

Confirm the attachment declares exactly:

```text
MASTER_VERSION=2.1
UPSTREAM=nextlevelbuilder/ui-ux-pro-max-skill
UPSTREAM_REVIEWED_SHA=56ea9cf174ed86786187daa9de0e6247cb0747e4
AUDIT_PASSES=10
```

Expected: all four values match the approved spec. If they do not, STOP and return to authority review.

- [ ] **Step 2: Create the canonical Master as an exact repository copy of the approved source**

Create `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md` from the approved attachment without inserting AlquilaPro branch/SHA/module-specific state.

Expected: the repository copy retains the V2.1 terminology, installation contract, Human-First laws, risk tiers, ten-pass audit, templates and version-control metadata.

- [ ] **Step 3: Create the thin repository Skill adapter**

Create `.github/skills/erp-human-first-ui-ux/SKILL.md` with frontmatter:

```yaml
---
name: erp-human-first-ui-ux
description: Mandatory Human-First UI/UX law for ERP design, review, implementation, accessibility, responsive behavior, information hierarchy and browser QA.
---
```

Its body must only activate the workflow:

```text
read canonical Master
read design-system/alquilapro/MASTER.md
read page override if present
read docs/CURRENT_CHECKPOINT.md
verify truth / permissions / routes
apply Human-First + no-infinite-Excel + progressive disclosure + accessibility
require UX contract before production UI changes
require real browser evidence before final user-facing GREEN
persist checkpoint
never silently override Master
```

Do not reproduce the complete Master.

- [ ] **Step 4: Verify single-authority behavior**

Run/review equivalent checks:

```bash
grep -F "MASTER_VERSION=2.1" docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
grep -F "docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md" .github/skills/erp-human-first-ui-ux/SKILL.md
```

Expected: canonical version marker exists; adapter references Master; adapter remains intentionally small and contains no second copy of the full law.

- [ ] **Step 5: Commit Task 1**

```bash
git add docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md .github/skills/erp-human-first-ui-ux/SKILL.md
git commit -m "docs: install Human-First UI UX master skill"
```

---

### Task 2: Activate Human-First governance in AGENTS and PR review

**Files:**
- Modify: `AGENTS.md`
- Create: `.github/pull_request_template.md`

**Interfaces:**
- Consumes: canonical Master from Task 1.
- Produces: durable agent activation and merge-readiness checklist without duplicating product logic.

- [ ] **Step 1: Preserve all existing AGENTS laws before editing**

Confirm the current file still contains:

```text
Ley KISS
Ley Cero Ruido
Ley Anti-parche
TDD y QA
Git / PR / merge
Checkpoints
Roadmap post-UAT
Seguridad y producto
Diseño público aprobado
Delegación a Codex
```

Expected: all existing sections are retained after Task 2.

- [ ] **Step 2: Add one mandatory Human-First section to `AGENTS.md`**

Add a concise activation contract equivalent to:

```text
For any user-facing UI/UX task:
1. read docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
2. read design-system/alquilapro/MASTER.md
3. read page override if present
4. read docs/CURRENT_CHECKPOINT.md
5. verify backend truth, permissions, routes and capabilities
6. declare Human-First context loaded
7. work one isolated microblock
8. run automated QA
9. run real-browser QA for user-facing changes
10. persist checkpoint before the next UI block
```

Also pin:

```text
TRUTH + SECURITY + ACCESSIBILITY > VISUAL PREFERENCE
```

Do not paste the complete Master into `AGENTS.md`.

- [ ] **Step 3: Create the PR Human-First gate**

Create `.github/pull_request_template.md` with the V2.1 UI checklist:

```text
Master read
project Master read
page override read/absent
truth owner verified
permissions verified
primary job/action defined
raw machine language not dominant
no unnecessary table / empty table chrome
UNKNOWN != 0
mobile/tablet/desktop defined
keyboard/focus checked
contrast/accessibility reviewed
no primary-page horizontal scroll
automated QA GREEN
real-browser QA GREEN
screenshots/evidence referenced
checkpoint persisted
```

State that UI PRs with an incomplete applicable gate are `NOT READY FOR MERGE`; backend-only PRs may mark UI-only items N/A with reason.

- [ ] **Step 4: Verify no authority duplication or law regression**

Review the diff and confirm:

```text
existing AGENTS sections retained = YES
canonical Master path referenced = YES
full Master copied into AGENTS = NO
full Master copied into PR template = NO
```

- [ ] **Step 5: Commit Task 2**

```bash
git add AGENTS.md .github/pull_request_template.md
git commit -m "docs: enforce Human-First UI UX review gate"
```

---

### Task 3: Define the AlquilaPro project design Master

**Files:**
- Create: `design-system/alquilapro/MASTER.md`
- Read for evidence: `app/UI/View.php`
- Read for evidence: `resources/views/layouts/auth.php`
- Read for evidence: `resources/views/layouts/public.php`
- Read for evidence: `resources/views/layouts/app.php`
- Read for evidence: `assets/app.css`
- Read for evidence: `assets/app.js`
- Read for evidence: current routes/services/tests needed to verify roles/capabilities

**Interfaces:**
- Consumes: canonical Human-First Master plus live repository evidence.
- Produces: AlquilaPro-specific design authority that describes existing truth and constrains future UI work.

- [ ] **Step 1: Inventory existing UI/runtime owners in read-only mode**

Record evidence for:

```text
SSR rendering owner
public/auth/private layouts
navigation owner
single CSS owner
single JS owner
role/permission owners
domain truth owners
current responsive breakpoints
current focus/touch/safe-area/reduced-motion contracts
current form/table/card/list primitives
```

Expected: every claim intended for the design Master has a repository owner; unknowns are not inferred.

- [ ] **Step 2: Create `design-system/alquilapro/MASTER.md`**

Required sections:

```text
Product
Users / roles
Human-First laws
Truth owners
Visual direction
Color tokens
Typography
Spacing
Radius / shadow
Components / primitives
Navigation
Responsive
Accessibility
Data density
Tables / lists / cards
Forms
Status language
Empty / unknown / conflict / error / loading states
Motion
Localization
Anti-patterns
Browser QA matrix
```

Pin the existing architecture:

```text
PHP SSR
View.php owner
three existing layouts
assets/app.css = principal stylesheet
aassets/app.js = application JavaScript owner
no SPA / no second design runtime
```

Correct the typo to `assets/app.js` in the actual file; the design Master must contain valid paths.

- [ ] **Step 3: Keep uncertain product truth explicit**

For any value that cannot be proven from current repository authority, write one of:

```text
UNKNOWN — audit required
NOT PRESENT
PAGE OVERRIDE REQUIRED BEFORE EXCEPTION
```

Never convert unknown to `0`, empty, healthy or supported.

- [ ] **Step 4: Do not create page overrides during installation**

Expected repository state after Task 3:

```text
design-system/alquilapro/MASTER.md exists
design-system/alquilapro/pages/*.md does not exist unless a real exception was discovered and separately approved
.gitkeep not added
```

- [ ] **Step 5: Commit Task 3**

```bash
git add design-system/alquilapro/MASTER.md
git commit -m "docs: define AlquilaPro design system master"
```

---

### Task 4: Create the live checkpoint and converge stale README state

**Files:**
- Create: `docs/CURRENT_CHECKPOINT.md`
- Modify: `README.md`

**Interfaces:**
- Consumes: live GitHub state at the moment Task 4 executes, not the stale values embedded in old docs.
- Produces: one compact operational pointer plus a README orientation block that no longer contradicts repository authority.

- [ ] **Step 1: Re-read live authority immediately before writing**

Verify:

```text
main SHA
R3 branch HEAD
R3 PR #33 state
R3 CI/browser blocker
Human-First governance branch HEAD / PR #34
R4 state
merge authorization
deployment authorization
```

Expected: values come from GitHub at execution time. If drift exists, use the new truth and document it rather than forcing this plan's historical SHA.

- [ ] **Step 2: Create compact `docs/CURRENT_CHECKPOINT.md`**

Include exactly the operational fields:

```text
MAIN_SHA=
ACTIVE_PHASE=
ACTIVE_BRANCH=
ACTIVE_PR=
ACTIVE_HEAD=
CI_STATE=
HUMAN_FIRST_MASTER_VERSION=2.1
PROJECT_DESIGN_MASTER_VERSION=
BROWSER_QA_STATE=
BLOCKERS=
NEXT_GATE=
MERGE_AUTHORIZED=
DEPLOYMENT_AUTHORIZED=
```

Also state the temporary governance interrupt separately so `ACTIVE_PHASE` continues to mean product roadmap state rather than pretending governance is R4.

Keep this file compact. Do not reproduce the R3 timeline or historical CI ledger already preserved in PR #33/checkpoints/Git history.

- [ ] **Step 3: Update only the stale live-state/orientation block in README**

Replace obsolete R1-era values such as old `main`, branch and PR with current authority and point readers to `docs/CURRENT_CHECKPOINT.md` for moving state.

Preserve:

```text
product description
roadmap
KISS / Cero Ruido / Anti-parche
stack
source-of-truth ordering
historical documents outside the stale live-state block
```

Prefer fewer volatile SHAs in README; moving SHAs belong in CURRENT_CHECKPOINT.

- [ ] **Step 4: Verify anti-drift convergence**

Expected:

```text
README no longer claims R1 is active
README does not contradict live main
CURRENT_CHECKPOINT contains live R3 blocker
CURRENT_CHECKPOINT says R4 NOT STARTED
CURRENT_CHECKPOINT says deployment NO
```

- [ ] **Step 5: Commit Task 4**

```bash
git add docs/CURRENT_CHECKPOINT.md README.md
git commit -m "docs: add current checkpoint and converge repository state"
```

---

### Task 5: Verify the governance installation as a documentation-only change

**Files:**
- Verify all files changed since `main`
- No new product/runtime files

**Interfaces:**
- Consumes: Tasks 1–4.
- Produces: objective persistence/anti-drift evidence and a clean installation gate before the ERP audit begins.

- [ ] **Step 1: Compare branch against live `main`**

Run/review equivalent:

```bash
git diff --name-only main...HEAD
```

Expected changed installation paths are limited to:

```text
docs/superpowers/specs/2026-10-16-human-first-uiux-governance-design.md
docs/superpowers/plans/2026-10-16-human-first-uiux-governance.md
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
docs/CURRENT_CHECKPOINT.md
design-system/alquilapro/MASTER.md
.github/skills/erp-human-first-ui-ux/SKILL.md
.github/pull_request_template.md
AGENTS.md
README.md
```

No `app/**`, `assets/**`, `resources/views/**`, `database/**` or product test path may appear.

- [ ] **Step 2: Verify the V2.1 persistence contract**

Record:

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

Any `NO` blocks the installation gate.

- [ ] **Step 3: Revalidate R3 isolation**

Re-read `impl/r3-public-surfaces-20261016` and PR #33.

Expected: product HEAD remains the certified R3 head unless an external actor changed it; this governance branch has not written to R3.

- [ ] **Step 4: Decide whether CI evidence is required**

If the diff remains documentation/governance only, do not invent runtime RED/GREEN and do not claim a PHP CI run as necessary proof of Markdown content.

If any executable file changed unexpectedly, STOP and reclassify before continuing.

- [ ] **Step 5: Update PR #34 to `INSTALLATION_READY_FOR_AUDIT`**

PR body must include the persistence matrix above and exact current head. Keep PR draft; installation is not yet authorized to merge merely because the files exist.

---

### Task 6: Run the full ERP Human-First audit in AUDIT ONLY mode

**Files:**
- Create: `docs/qa/HUMAN_FIRST_UI_UX_AUDIT_2026-10-16.md`
- Read: all current UI views/layouts/routes/services/tests/assets required to establish truth
- Do not modify: product/runtime files

**Interfaces:**
- Consumes: installed Master, AlquilaPro design Master, CURRENT_CHECKPOINT and live repository truth.
- Produces: one canonical evidence-based ERP audit and prioritized remediation backlog; no product fixes.

- [ ] **Step 1: Declare the activation handshake before auditing**

Record in the audit work log/report:

```text
HUMAN_FIRST_LAW_LOADED=YES
PROJECT_DESIGN_MASTER_LOADED=YES
PAGE_OVERRIDE_LOADED=NOT_PRESENT or exact override
CURRENT_CHECKPOINT_LOADED=YES
TRUTH_OWNER_VERIFIED=YES
AUTHORIZATION_MODEL_VERIFIED=YES
TARGET_VIEWPORTS_DEFINED=YES
UI_TASK_SCOPE=AUDIT_ONLY
```

If any required value is unknown, resolve authority before scoring that surface.

- [ ] **Step 2: Build the complete current UI surface inventory**

Inventory real routes/views/modules and associate each with:

```text
user / role
job to be done
truth owner
authorized roles
primary action
primary information
secondary/technical information
risk tier
relevant tests
browser route if renderable
```

Do not invent a surface that is only mentioned historically but absent from current code.

- [ ] **Step 3: Execute passes 1–3**

Passes:

```text
1 Truth / permissions
2 Human comprehension
3 Information architecture
```

For each finding use:

```text
ID
Severity
Law
Evidence
Expected
Actual
Root
Action
Disposition=KEEP|MERGE|OPTIMIZE|SIMPLIFY|REMOVE|DEFER
```

Stop and surface BLOCKER/CRITICAL immediately, but continue collecting non-mutating evidence where safe.

- [ ] **Step 4: Execute passes 4–6**

Passes:

```text
4 Density / Infinite Excel
5 Visual system consistency
6 Accessibility
```

Explicitly inspect:

```text
>10 primary columns
multiple large tables without hierarchy
single-record tables
empty table chrome
raw IDs/statuses as primary UX
repeated row actions
focus visibility
target size
keyboard semantics
contrast evidence available from tokens/CSS
reduced motion
safe area
zoom/overflow risks
```

Do not prescribe “cards everywhere”; preserve tables when comparison is the real job.

- [ ] **Step 5: Execute passes 7–9**

Passes:

```text
7 Interaction / forms / feedback
8 Responsive static contract
9 Data-heavy operations / performance UX
```

Inspect validation, destructive actions, empty/error/loading/conflict states, mobile/tablet/desktop behavior, filters/search/pagination/bulk actions/freshness/audit visibility and keyboard-heavy workflows.

- [ ] **Step 6: Execute pass 10 only with real browser evidence**

Target viewports:

```text
375x812
768x1024
1440x900
```

For each renderable representative surface inspect:

```text
5–10 second comprehension
primary job/action
overflow
sticky/fixed clearance
keyboard/focus
console errors
5xx
truthful factual fixture
cross-page consistency
```

If browser control or a real app instance is unavailable, write exactly:

```text
PASS_10=BLOCKED_ENVIRONMENT
GLOBAL_AUDIT=NOT_FULLY_CERTIFIED
```

Do not substitute source inspection, CI assertions or imagined screenshots for browser QA.

- [ ] **Step 7: Write the canonical audit report**

Create `docs/qa/HUMAN_FIRST_UI_UX_AUDIT_2026-10-16.md` with:

```text
Authority
Screens reviewed
Overall score
Blockers
Findings
Cross-page consistency
Responsive
Accessibility
Human language
Data density
Truth semantics
P0–P4 remediation plan
Next checkpoint
```

P0–P4 ordering:

```text
P0 truth / security / accessibility blockers
P1 comprehension / primary workflows / navigation
P2 responsive / forms / data presentation
P3 visual consistency / polish
P4 optional enhancements after evidence
```

No remediation commit is part of Task 6.

- [ ] **Step 8: Commit the audit report**

```bash
git add docs/qa/HUMAN_FIRST_UI_UX_AUDIT_2026-10-16.md
git commit -m "docs: audit AlquilaPro with Human-First UI UX law"
```

---

### Task 7: Final 10-pass governance audit, checkpoint and next merge/remediation gate

**Files:**
- Modify: `docs/CURRENT_CHECKPOINT.md`
- Update: PR #34 body
- Optional recovery ref: checkpoint branch pointing at exact final audit head

**Interfaces:**
- Consumes: complete installation + ERP audit report.
- Produces: durable handoff state and an explicit decision gate; no silent merge and no broad remediation.

- [ ] **Step 1: Self-audit installation and report against the approved spec**

Check ten areas:

```text
1 authority
2 portability
3 persistence
4 activation
5 Human-First / comprehension
6 architecture / KISS
7 accessibility / responsive
8 data-heavy ERP coverage
9 audit/remediation contract
10 anti-drift / browser evidence honesty
```

Expected: installation can be PASS even if audit pass 10 is truthfully BLOCKED; global UX certification cannot be PASS while browser evidence is absent.

- [ ] **Step 2: Re-run KISS / Cero Ruido / Anti-parche review**

Reject:

```text
second Master
placeholder page overrides
history duplicated into CURRENT_CHECKPOINT
new governance framework/CI platform
runtime/UI fixes mixed into audit branch
findings without root/evidence
patch recommendations that bypass owners/tests
```

- [ ] **Step 3: Refresh `docs/CURRENT_CHECKPOINT.md`**

Set exact final branch/PR/head and:

```text
HUMAN_FIRST_INSTALLATION_STATE=
ERP_AUDIT_STATE=
BROWSER_QA_STATE=
BLOCKERS=
NEXT_GATE=
MERGE_AUTHORIZED=NO unless owner authorization has been given for that gate
DEPLOYMENT_AUTHORIZED=NO
```

The next gate after owner review is either:

```text
A) fix governance/audit gaps, or
B) merge governance with lease, then realign R3 and start P0 remediation only after separate plan/approval
```

- [ ] **Step 4: Create a recovery checkpoint ref**

Create a checkpoint branch/ref pointing at the exact final audited HEAD without adding a ceremonial extra commit.

Suggested name:

```text
checkpoint/human-first-governance-audit-ready-20261016
```

- [ ] **Step 5: Update PR #34 with exact evidence**

Include:

```text
installation persistence matrix
audit report path
finding counts by severity
P0–P4 summary
browser pass state
exact HEAD
main drift state
R3 isolation state
merge/deploy authorization
```

Keep draft until the approved merge gate is actually satisfied.

- [ ] **Step 6: Stop for owner review**

Do not begin broad remediation, R4 or deployment in this plan.

---

## Execution Order

```text
Task 1 canonical Master + thin adapter
→ Task 2 AGENTS + PR gate
→ Task 3 AlquilaPro project Master
→ Task 4 CURRENT_CHECKPOINT + README convergence
→ Task 5 persistence/isolation verification
→ Task 6 complete ERP AUDIT ONLY
→ Task 7 final audit + checkpoint + owner gate
```

## Definition of Done for this plan

This plan is complete only when:

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
RUNTIME_CHANGES=0
R3_ISOLATED=YES
R4_NOT_STARTED=YES
ERP_10_PASS_AUDIT_REPORT_EXISTS=YES
PASS_10=GREEN or BLOCKED_ENVIRONMENT with no false certification
P0_P4_PLAN_EXISTS=YES
BROAD_REMEDIATION_STARTED=NO
DEPLOYMENT_AUTHORIZED=NO
```

The next plan after owner review will implement only the approved highest-priority remediation microblocks using the existing AlquilaPro Anti-parche/TDD workflow.