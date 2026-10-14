# F24 — Release Readiness + Wizards + QA Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Converge AlquilaPro for release by turning the existing installer and candidate continuation flow into clear wizards and certifying cross-device, accessibility, migration, upload, email/cron, PDF and permission behavior without adding product architecture.

**Architecture:** Reuse the existing `Installer`, candidate row/token, Slim routes, PHP session, SSR views and domain services. Candidate progress is derived from current data + requested step; installer pre-install state is temporary server-side session state cleared on completion/cancel/error. No wizard tables, application session tables, new domain services or frontend framework.

**Tech Stack:** PHP >=8.3 <8.6, Slim 4, PDO/MySQL, SSR PHP, existing CSS/JS, PHPUnit 11.5.

**Spec:** `docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md` — sections 10–14, 17–19.

## Global Constraints

- `NEW_PRODUCT_TABLES = 0`; `NEW_DOMAIN_SERVICES = 0`; `NEW_RUNTIME_DEPS = 0`.
- Existing candidate row/token remain canonical; no `candidate_wizard_steps` or `application_sessions`.
- Existing Installer remains canonical; no second installer.
- Installer never creates sample tenant/candidate/lease/property data.
- No release packaging yet; F25 owns the exact ZIP.
- F24 may repair only reproduced defects; it is convergence/hardening, not feature expansion.

## Pre-Execution Gate

- [ ] F23 checkpoint is GREEN and adaptive shell is stable at 375/768/1024/1440.
- [ ] Fetch final candidate schema/service/routes and installer after F22/F23 so this plan uses actual contracts.
- [ ] Record base SHA and clean worktree.
- [ ] Confirm PHP 8.3/8.4/8.5 CI remains the supported matrix.

---

### Task 1: Candidate wizard navigation without new state

**Files:**
- Modify: `app/Candidates/routes.php`
- Modify only for read/completeness projection: `app/Candidates/CandidateService.php`
- Replace/converge: `resources/views/public_candidate_continue.php`
- Create: `resources/views/public_candidate_wizard.php`
- Modify: `resources/views/public_apply_start.php`
- Create: `tests/Integration/Candidates/CandidateWizardTest.php`

**Canonical flow:**
1. `/apply/{publicKey}` — unit/context/privacy + candidate start.
2. `/apply/continue/{token}/personal`
3. `/apply/continue/{token}/work`
4. `/apply/continue/{token}/occupants`
5. `/apply/continue/{token}/references`
6. `/apply/continue/{token}/documents`
7. `/apply/continue/{token}/review`

Existing mutation endpoints remain owners; step routes orchestrate them and redirect to the appropriate next step. `/apply/continue/{token}` becomes a compatibility redirect to the first incomplete allowed step.

- [ ] Write RED tests for the seven visible steps, same candidate id/token through the entire flow, back navigation, first-incomplete redirect, submitted candidate read-only review, invalid/expired token 404 and no persisted step/progress column/table.
- [ ] Add one pure/read-only completeness projection in `CandidateService` only if route code would otherwise duplicate field completeness logic; it must derive, never persist, step state.
- [ ] Render one wizard template with a route-local/derived step model; do not create seven domain forms/classes.
- [ ] Preserve existing throttling, CSRF, token rotation/expiry and upload authorities.
- [ ] Make errors field-local where current domain error information supports it; otherwise show one safe step-level error without inventing a validation framework.
- [ ] Run Candidate focal/integration suites and commit `feat(f24): converge candidate flow into seven step wizard`.

### Task 2: Installer wizard with explicit DB test

**Files:**
- Modify: `app/Foundation/Installer.php`
- Modify: `bootstrap/app.php`
- Modify: `resources/views/install.php`
- Create: `tests/Integration/Foundation/InstallerWizardTest.php`

**Canonical steps:**
0 server requirements → 1 DB credentials/test → 2 company/admin settings → 3 owner → 4 summary → 5 install/migrate → 6 success/login.

**Interfaces:**
- Add `Installer::testDatabase(array $database): void` using existing `DatabaseConfig` + `DatabaseConnector`.
- Temporary session key: `alquilapro_installer_wizard`; clear after successful install and when invalidated.
- Current DB credentials are considered tested only when a server-side fingerprint of the exact fields matches the last successful test.

- [ ] Write RED tests for step order, requirements fail-closed, explicit DB test, changed credentials invalidating prior test, no migration before final install, owner validation, summary redaction of DB password and successful final install.
- [ ] Add `POST /install/test-database` and minimal step handling within the existing installer route family; all POSTs use existing CSRF.
- [ ] Store only temporary pre-install form state in PHP session; never write DB credentials to HTML, logs, cookies or query strings.
- [ ] Final `Installer::install()` remains the only operation writing DB config, migrations, owner/settings and installation lock.
- [ ] Verify no tenant/candidate/lease/property/sample data is created.
- [ ] Run Foundation/Installer suites and commit `feat(f24): add tested installer wizard`.

### Task 3: Clean-install and upgrade migration certification

**Files:**
- Create: `tests/Integration/Foundation/ReleaseMigrationTest.php`
- Modify product migration only if a RED proves a defect.

- [ ] RED/GREEN test an empty database through all migrations in order and assert final expected schema/version.
- [ ] Test upgrade path from the latest pre-F15/F20 representative schema through final migration without destructive resets or duplicate data.
- [ ] Verify migration rerun/idempotency behavior matches existing `MigrationRunner` contract.
- [ ] Verify F16 nullable recipient migration, F20 final schema, F21 termination and F22 audit coexist.
- [ ] Repair only the owning migration if a reproducible defect exists; never rewrite historical migration semantics merely for style.
- [ ] Commit only if needed: `fix(f24): close release migration regressions`.

### Task 4: Release-critical functional QA matrix

**Files:**
- Create: `tests/Integration/Release/ReleaseCriticalFlowsTest.php`
- Reuse/extend existing focused tests only where needed.

- [ ] Certify installer → login → property/unit → public catalog → candidate wizard → admin candidate → contract/lease → tenant portal representative path.
- [ ] Certify PDF generation on supported environment, email with fake transport, protected cron, uploads/private downloads, payment method QR, permissions/owner bypass, UTF-8 and America/Bogota date behavior.
- [ ] Run before product changes; classify every failure `PRODUCT DEFECT`, `STALE TEST`, `ENVIRONMENT`, or `NO DEFECT`.
- [ ] Repair only proven product defects at their owner, one minimal repair pass.
- [ ] Do not weaken required GD/PDF/upload/runtime checks merely to make a local environment pass.
- [ ] Commit only if required: `fix(f24): close release critical flow defects`.

### Task 5: Cross-device + accessibility QA

**Files:**
- Create: `docs/qa/F24_RESPONSIVE_ACCESSIBILITY_MATRIX_2026-10-14.md`
- Modify presentation only if a reproduced visual/accessibility defect exists.

- [ ] Test `375x812`, `768x1024`, `1024x768`, `1440x900` for login, dashboard, Apps launcher, properties/unit, candidate admin, public candidate wizard, finance, maintenance, tenant lease portal and installer wizard.
- [ ] For each screen record: overflow, focus, keyboard desktop, dock/sidebar obstruction, safe area, labels, touch targets, modal/sheet escape, reduced motion and error association.
- [ ] Repair only reproduced defects in their presentation owner; no second CSS/component system.
- [ ] Re-run affected widths after each repair and keep one consolidated matrix.
- [ ] Commit matrix/repairs `test(f24): certify responsive accessibility matrix`.

### Task 6: PHP matrix, full convergence and checkpoint

**Files:**
- Create: `docs/checkpoints/F24_CHECKPOINT_2026-10-14.md`

- [ ] Run focal CandidateWizard, InstallerWizard, ReleaseMigration and ReleaseCriticalFlows tests.
- [ ] Run full suite locally once; `composer validate --strict`; PHP lint changed files.
- [ ] Require remote CI GREEN on PHP 8.3, 8.4 and 8.5 for the F24 PR merge-ref before closure.
- [ ] Fresh-review full F24 diff for zero new product tables/services, no sample data, no wizard state persistence and no weakened security/runtime gate.
- [ ] Write checkpoint with exact test counts, CI run/jobs, responsive matrix result, known environment-only skips and `MERGE_AUTHORIZED: NO`.
- [ ] Commit `docs(f24): close release readiness checkpoint`.

## Self-Review Result

- Spec coverage: PASS — candidate wizard, installer wizard, clean/upgrade migrations, release-critical functional QA, accessibility and PHP matrix are explicit.
- Step scan: PASS — existing authorities are reused; no hidden product expansion.
- Type consistency: PASS — no new service/table; optional candidate completeness logic remains in `CandidateService`.
- Review Focus: PASS.
- Proportion: PASS.

**Execution status:** `PLAN WRITTEN / USER REVIEW REQUIRED / F23 GREEN REQUIRED / IMPLEMENTATION NOT AUTHORIZED / MERGE NOT AUTHORIZED`.
