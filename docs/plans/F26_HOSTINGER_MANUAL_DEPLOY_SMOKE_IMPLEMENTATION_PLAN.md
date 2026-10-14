# F26 — Manual Hostinger Deploy + Smoke Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to execute this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deploy the exact F25 `ALQUILA_PRO_V1_SERVER_READY.zip` manually to Hostinger and certify production smoke honestly, without introducing an automated deployment platform.

**Architecture:** F26 is an operator runbook + smoke gate. The user uploads/extracts the exact F25 ZIP through Hostinger, prepares the target database and environment values, runs the same installer wizard, then executes a bounded production smoke with non-sensitive data. No SSH/API/FTP/CI deployment is required for V1.

**Target domain:** `https://alquilapro.sierraglobalcompany.com`

**Spec:** `docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md` — sections 16–19.

## Global Constraints

- Deploy only the exact F25 artifact whose SHA-256 is recorded `SERVER_READY: YES`.
- Never upload repository worktrees, tests, caches or secrets outside the packaged artifact/configuration workflow.
- No production database reset unless this is explicitly confirmed as a fresh empty target.
- No sample/real sensitive tenant data for smoke.
- No claim of successful deployment without direct production evidence.
- If the executing agent cannot access Hostinger, stop at `DEPLOYMENT_HANDOFF_READY`; the user performs upload/extract/install and returns observed results.
- Any source-code defect discovered in production smoke returns to its owning source phase; do not hot-patch files directly in Hostinger as the canonical fix.

## Pre-Deployment Gate

- [ ] F25 checkpoint says `SERVER_READY: YES` and supplies artifact SHA-256.
- [ ] Recalculate local SHA-256 immediately before upload and match F25 exactly.
- [ ] Confirm target domain/subdomain and document root intentionally point to the AlquilaPro deployment directory.
- [ ] Confirm PHP version is supported (8.3–8.5) and required extensions/runtime checks pass.
- [ ] Prepare a fresh/approved MySQL database and credentials without recording password in repo/chat/checkpoint.
- [ ] Prepare SMTP and cron secret values separately from source control.
- [ ] If an older deployment exists, take a Hostinger/file + DB backup before replacement; record backup existence, not credentials/data.

---

### Task 1: Upload and extract the exact artifact

**Operator steps:**
- [ ] Upload `ALQUILA_PRO_V1_SERVER_READY.zip` to the intended deployment directory with Hostinger File Manager or another user-approved manual transfer.
- [ ] Verify uploaded file size matches the local artifact; if Hostinger provides checksum capability, compare SHA-256; otherwise preserve local checksum and verify extraction manifest afterward.
- [ ] Extract into a clean target directory; do not merge blindly over unknown historical files.
- [ ] Verify required files/directories from F25 manifest exist and forbidden development files are absent.
- [ ] Verify writable directories required by installer/runtime and private document directory topology can be created outside direct web exposure.
- [ ] Remove the uploaded ZIP from public webroot after successful extraction when operationally safe.

### Task 2: Run the canonical installer wizard

**Operator steps:**
- [ ] Open the target domain over HTTPS and reach installer Step 0.
- [ ] Verify server requirements all PASS; do not bypass missing GD/PDO/MySQL/mbstring/dom/fileinfo/storage requirements.
- [ ] Enter target DB credentials and use explicit **Test connection**; do not proceed on failure.
- [ ] Enter company/admin settings and owner account.
- [ ] Review redacted summary; ensure DB password is not displayed.
- [ ] Run install/migrations once and reach success/login.
- [ ] Verify installer is locked/404/inaccessible for re-install after success.
- [ ] Verify generated DB config and installation lock are not publicly downloadable.

### Task 3: Production environment configuration

- [ ] Configure production SMTP values outside Git/source and send only a controlled test message.
- [ ] Configure a strong notification cron secret outside Git/source.
- [ ] Configure Hostinger cron for the existing protected notifications endpoint at the approved cadence; do not create a second scheduler.
- [ ] Verify HTTPS cookie/security header behavior from F22 and HSTS only under HTTPS.
- [ ] Verify private document/storage paths are outside direct web access and writable by the application.
- [ ] Confirm application timezone/date behavior remains America/Bogota where designed.

### Task 4: Bounded production smoke

**Smoke matrix:**
- [ ] HTTPS landing loads and certificate is valid.
- [ ] Public catalog loads and unpublished/private fields are not exposed.
- [ ] Owner login/logout/password controls work.
- [ ] Create one non-sensitive test property/unit and verify adaptive staff UI.
- [ ] Public candidate wizard completes using clearly fake/non-sensitive test identity/data.
- [ ] Admin candidate/contract/lease representative path works.
- [ ] Tenant portal representative path works and cross-tenant data cannot be accessed.
- [ ] Upload/private download succeeds for one harmless test file and direct storage URL fails.
- [ ] PDF generation succeeds.
- [ ] SMTP test succeeds without secrets leaking to UI/logs.
- [ ] Protected cron succeeds with correct secret and rejects missing/wrong secret.
- [ ] Payment method/Bre-B QR representative view works.
- [ ] Permissions and owner bypass behave as certified.
- [ ] F20 membership representative flow works without automatic charge.
- [ ] F21/F22 representative liquidation/audit read path works if those phases are in the deployed source.
- [ ] Touch shell checked on phone-size viewport/device; desktop shell checked at >=1440px; no blocking overflow.
- [ ] Review application/server logs for fatal errors, stack traces, secrets or raw tokens after smoke.

### Task 5: Clean smoke data and freeze deployment checkpoint

**Files:**
- Create: `docs/checkpoints/F26_DEPLOYMENT_CHECKPOINT_2026-10-14.md`
- Create/update: `docs/deployment/HOSTINGER_DEPLOYMENT_RUNBOOK.md`

- [ ] Remove/void only the explicitly created smoke records where product rules safely allow it; do not directly SQL-delete evidence/history to make production look clean.
- [ ] Record deployed source SHA, F25 artifact SHA-256, deployment date/time, PHP version, domain, installer result and each smoke PASS/FAIL without credentials or PII.
- [ ] Record cron/SMTP as configured/not-configured, never their secret values.
- [ ] If every mandatory smoke passes, mark `PRODUCTION_SMOKE: PASS` and `DEPLOYMENT_STATUS: DEPLOYED_VERIFIED`.
- [ ] If Hostinger access was unavailable to the executor, mark only `DEPLOYMENT_STATUS: DEPLOYMENT_HANDOFF_READY` and list the exact remaining user-run steps; never mark deployed.
- [ ] If any mandatory smoke fails, mark `DEPLOYMENT_STATUS: HOLD`, preserve evidence, identify owning phase/root cause and do not hot-patch around it.
- [ ] Commit documentation/checkpoint only after factual evidence exists.

## Rollback Guardrail

If deployment blocks the site or a mandatory smoke reveals a release-critical defect:

1. stop further mutations;
2. preserve relevant safe logs/error evidence;
3. restore the pre-deploy file/DB backup if one existed and rollback is required;
4. repair source at the owning phase;
5. rerun its tests/CI;
6. rebuild a new F25 artifact with a new SHA-256;
7. repeat F25 rehearsal before redeploying.

No production-only source patch becomes canonical.

## Self-Review Result

- Spec coverage: PASS — manual upload/extract, same installer, Hostinger configuration, full smoke and honest no-access outcome are explicit.
- Step scan: PASS — no hidden deployment platform or automation architecture.
- Type consistency: PASS — F26 adds no product service/table/dependency.
- Review Focus: PASS — exact F25 artifact remains deployment authority.
- Proportion: PASS.

**Execution status:** `PLAN WRITTEN / USER REVIEW REQUIRED / F25 SERVER_READY REQUIRED / DEPLOYMENT NOT AUTHORIZED / MERGE NOT AUTHORIZED`.
