# F25 — SERVER_READY Package + Local Rehearsal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce one exact `ALQUILA_PRO_V1_SERVER_READY.zip`, prove that exact artifact works from a clean extraction and empty database, and freeze its version, manifest and SHA-256 without shipping secrets or development noise.

**Architecture:** Packaging is a release concern, not runtime architecture. Build from a clean release commit into a temporary staging directory, install production Composer dependencies there, copy only allowlisted runtime/release files, generate manifest/version metadata, then create the ZIP. The application runtime receives no new dependency solely for packaging.

**Tech Stack:** Existing PHP/Composer application; local packaging may use OS ZIP tooling or `ZipArchive` if available, but this is not a production runtime requirement.

**Spec:** `docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md` — sections 15, 17–19.

## Global Constraints

- Exact output filename: `ALQUILA_PRO_V1_SERVER_READY.zip`.
- Build only from a clean, committed F24 closure SHA.
- Include production `vendor`; exclude dev dependencies.
- Never include `.git`, worktrees, tests, CI artifacts, caches, local DB/config secrets, `.env`, logs, uploaded/private user data, test credentials or temporary files.
- The ZIP itself is not a source-of-truth replacement for Git; record source SHA inside release metadata.
- Only the exact SHA-256-certified ZIP that passes rehearsal may be labeled `SERVER_READY`.
- Do not change product behavior in F25; any product defect discovered returns to its owning phase for repair and a new artifact build.

## Pre-Execution Gate

- [ ] F24 checkpoint and PHP 8.3/8.4/8.5 CI are GREEN.
- [ ] Working tree is clean at the approved F24 closure SHA.
- [ ] Full suite and release-critical QA already pass.
- [ ] Local build host has enough disk space for staging + vendor + ZIP + extracted rehearsal copy.

---

### Task 1: Freeze release contents and exclusions

**Files:**
- Create: `docs/release/SERVER_READY_FILE_POLICY.md`
- Create: `docs/release/RELEASE_NOTES_V1.md`
- Create: `docs/release/VERSION`
- Modify if required: `.gitignore`
- Create: `tests/Release/ServerReadyFilePolicyTest.php`

**Required runtime/release content categories:** application PHP, `assets`, `resources`, migrations, Composer production metadata/vendor, web config, installer, empty required storage directories/markers, safe config example/guidance, release notes and version/source metadata.

- [ ] Write RED file-policy test with explicit forbidden names/patterns and required runtime paths.
- [ ] Freeze V1 version string and source-SHA placeholder format; no marketing version guessing after artifact creation.
- [ ] Define private writable directory guidance without shipping user data.
- [ ] Define secrets exclusions explicitly, including generated `config/database.php`, SMTP/cron values and any local `.env`.
- [ ] Run GREEN and commit `docs(f25): freeze server ready file policy`.

### Task 2: Minimal deterministic release builder

**Files:**
- Create: `bin/build-server-ready.php`
- Create: `tests/Release/ServerReadyBuilderTest.php`

**Interfaces:**
- Usage: `php bin/build-server-ready.php --source=<repo> --output=<dir> --version=<version>`.
- Output directory contains:
  - `ALQUILA_PRO_V1_SERVER_READY.zip`
  - `ALQUILA_PRO_V1_SERVER_READY.sha256`
  - `ALQUILA_PRO_V1_SERVER_READY.manifest.json`

- [ ] Write RED builder tests proving build refuses dirty/invalid source input when invoked against the real release workflow, forbidden files never enter staging, required files exist, and manifest records source SHA/version/file hashes.
- [ ] Implement one small build script: create fresh temp staging, copy allowlist, run/verify `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` in staging, remove build-only noise, generate internal release metadata, zip staging, compute outer SHA-256 and manifest.
- [ ] If local ZIP capability is missing, fail with one explicit packaging prerequisite; do **not** add a Composer/runtime ZIP dependency to AlquilaPro.
- [ ] Ensure timestamps/ordering are stable where practical, but release identity is the computed SHA-256, not a promise of cross-OS bit-for-bit reproducibility.
- [ ] Run GREEN and commit `build(f25): add server ready package builder`.

### Task 3: Artifact static audit

**Files:**
- Create: `tests/Release/ServerReadyArtifactAuditTest.php`

- [ ] Build a candidate ZIP from the exact clean F24 closure SHA.
- [ ] Audit ZIP entry list for required/forbidden policy, path traversal entries, duplicate/case-conflicting entries, development dependencies, secrets and non-empty user storage.
- [ ] Extract to a fresh directory and verify Composer production autoload works without dev packages.
- [ ] Verify release metadata source SHA/version matches the build commit and outer `.sha256` matches the exact ZIP bytes.
- [ ] Any failure invalidates the candidate; repair builder/policy, rebuild from scratch and recalculate SHA.

### Task 4: Mandatory clean local rehearsal of the exact ZIP

**Files:**
- Create: `docs/qa/F25_SERVER_READY_REHEARSAL_2026-10-14.md`

**Exact rehearsal sequence:** clean folder → extract exact ZIP → empty DB → installer requirements → Test connection → company/settings → owner → install/migrate → login → property/unit → public catalog → candidate wizard from public link → representative tenant/portal flow → private files/uploads → PDF → fake/local email transport where applicable → cron → payment methods/QR.

- [ ] Record the ZIP SHA-256 **before** extraction and never rebuild during the rehearsal.
- [ ] Use a genuinely empty test database and no repository working-tree files outside the extracted package.
- [ ] Exercise the installer only through the packaged application.
- [ ] Verify no test/sample business data appears unless entered manually during rehearsal.
- [ ] Verify writable/private directories and web denial boundaries in the extracted topology.
- [ ] Record each smoke step PASS/FAIL with no real secrets in the report.
- [ ] If a product defect is discovered, mark artifact `REJECTED`, repair in owning source phase, rerun affected QA/full gate, build a new ZIP with a new checksum, and restart rehearsal from zero.

### Task 5: Freeze SERVER_READY checkpoint

**Files:**
- Create: `docs/checkpoints/F25_CHECKPOINT_2026-10-14.md`

- [ ] Confirm one exact ZIP, one matching `.sha256`, one manifest and one PASS rehearsal report.
- [ ] Re-run static artifact audit against the final bytes.
- [ ] Record source closure SHA, artifact byte size, SHA-256, version, build command/environment and rehearsal database/environment class without credentials.
- [ ] Mark `SERVER_READY: YES` only after all gates pass; otherwise `SERVER_READY: NO`.
- [ ] Record `MERGE_AUTHORIZED: NO` unless user explicitly authorizes source merges separately.
- [ ] Commit source-controlled build policy/script/tests/checkpoint; do not commit large release ZIP to Git unless explicitly decided later.

## Self-Review Result

- Spec coverage: PASS — exact ZIP, production vendor, release metadata, secrets exclusion and exact-artifact rehearsal are explicit.
- Step scan: PASS — packaging does not mutate runtime architecture.
- Type consistency: PASS — no new product service/table/dependency.
- Review Focus: PASS — exact bytes/checksum and clean extraction are authoritative.
- Proportion: PASS.

**Execution status:** `PLAN WRITTEN / USER REVIEW REQUIRED / F24 GREEN REQUIRED / IMPLEMENTATION NOT AUTHORIZED / DEPLOYMENT NOT AUTHORIZED`.
