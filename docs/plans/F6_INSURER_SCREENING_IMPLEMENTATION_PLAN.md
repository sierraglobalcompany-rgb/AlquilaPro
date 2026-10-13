# F6 Insurer Screening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement F6 as one generic insurer-screening process attached to the existing F5 candidate, with SURA as the initial UI suggestion, safe candidate visibility, and no third-party automation or duplicated candidate state.

**Architecture:** Add exactly one table, `screening_processes`, and one domain service, `ScreeningService`. Reuse `CandidateService`, existing `candidates.view/manage`, the existing Candidates route file, the existing admin candidate detail, and the existing public continuation-token page. Screening state lives only in `screening_processes.status`; F6 never auto-transitions `candidates.status` or `units.status`.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO, MariaDB/MySQL, server-rendered PHP views, existing CSS/JS, PHPUnit 11.5; no new runtime dependency and no npm.

**Spec:** `docs/specs/F6_INSURER_SCREENING_SPEC.md`

**Approved design/spec context:** F6 design approved by user on 2026-10-12; specific SPEC approved by user through the project `continua` gate before this plan was written.

## Global Constraints

- F6 adds exactly one table: `screening_processes`.
- F6 adds at most one service: `ScreeningService`.
- F6 adds zero permissions, zero auth/session/CSRF systems, zero route files and zero runtime dependencies.
- Reuse exactly `candidates.view` for reading and `candidates.manage` for mutation.
- Reuse `app/Candidates/routes.php`, `resources/views/candidate_detail.php` and `resources/views/public_candidate_continue.php`.
- SURA is an initial UI suggestion only; no `sura_*` table, `SuraService`, adapter, API request, scraping, webhook, polling or iframe.
- One screening process per candidate: UNIQUE (`candidate_id`).
- Screening statuses are exactly `pending`, `in_progress`, `approved`, `rejected`.
- Creation always starts at `pending`.
- Allowed transitions: `pending -> in_progress|approved|rejected`; `in_progress -> approved|rejected`; `approved|rejected` terminal.
- Same-status metadata edits are allowed only while `pending|in_progress`; they are not state transitions.
- A screening may be created only while `candidate.status = pending_screening`.
- Any F6 mutation is rejected once the candidate no longer has `pending_screening` (including `withdrawn`).
- `approved|rejected` require a non-empty public-safe `result`.
- F6 never changes `candidates.status` or `units.status` automatically.
- Portal URL is optional; when present it must be an absolute HTTPS URL with a host and maximum 2048 characters.
- Candidate public projection never exposes screening ID, candidate ID, responsible user ID, private notes or support reference.
- External portal links must not receive the continuation token and must prevent Referer leakage with `rel="noopener noreferrer"` or an equivalent stronger response policy.
- All POST routes reuse existing global CSRF middleware and all paths preserve `basePath`.
- Composer/runtime dependency delta = 0; npm delta = 0.
- No merge without the project gate; `continua` means authorization to execute the next approved gate.
- Work in microphases F6.1–F6.5.
- Maintain at most one rolling `docs/checkpoints/F6_CURRENT_CHECKPOINT.md`; replace it at close with one canonical `F6_CLOSED_2026-10-12.md` and later `F6_MERGED_2026-10-12.md` after merge.

## Review Focus

1. **Candidate withdrawn between render and POST:** service must re-read candidate state and reject F6 mutation without modifying screening.
2. **Terminal tampering:** once `approved|rejected`, changing status back, editing metadata, URL, notes or result must fail and preserve the terminal row.
3. **URL parser edge cases:** protocol-relative, relative, `http:`, `javascript:`, missing-host and oversized URLs must fail; valid absolute HTTPS must persist unchanged after trim.
4. **Public projection privacy:** candidate page must never expose `notes`, `support_reference`, internal IDs or responsible user ID, including values crafted as XSS payloads.
5. **Duplicate/concurrent create:** database UNIQUE and service flow must leave one process only and must not silently create a second screening for the same candidate.

---

# Locked File Map

## New production files

- `database/migrations/006_create_screening_processes.sql`
- `app/Candidates/ScreeningService.php`

## Existing production files expected to change

- `bootstrap/app.php` — instantiate/inject `ScreeningService` only.
- `app/Candidates/routes.php` — reuse existing admin/detail and public continuation routes; add one POST screening route.
- `resources/views/candidate_detail.php` — render admin screening section/form.
- `resources/views/public_candidate_continue.php` — render candidate-safe screening projection and external CTA/fallback.
- `assets/app.css` — only if a reproduced F6 visual RED proves an existing primitive is insufficient.
- `README.md` — only at documented F6 gates/closure.

## New test files

- `tests/Integration/Candidates/ScreeningSchemaTest.php`
- `tests/Integration/Candidates/ScreeningServiceTest.php`
- `tests/Integration/Candidates/ScreeningAdminRoutesTest.php`
- `tests/Integration/Candidates/PublicCandidateScreeningTest.php`
- `tests/Integration/Candidates/ScreeningSecurityRegressionTest.php`

Existing F1–F5 tests remain regression authority.

---

### F6.1 — Schema Foundation

**Files:**
- Create: `database/migrations/006_create_screening_processes.sql`
- Create: `tests/Integration/Candidates/ScreeningSchemaTest.php`
- Modify: `tests/Integration/Foundation/MigrationRunnerTest.php` only if its exact migration-count/schema contract requires it
- Create/update: `docs/checkpoints/F6_CURRENT_CHECKPOINT.md`

**Interfaces:**
- Consumes: `candidates.id` (`BIGINT UNSIGNED`), `users.id` (`BIGINT UNSIGNED`), existing MigrationRunner.
- Produces: one `screening_processes` table with one process per candidate.

- [ ] **Step 1: Write schema RED**

Tests must pin:

```text
test_f6_migration_creates_only_screening_processes
test_screening_candidate_is_unique_and_cascades_with_candidate
test_screening_responsible_user_is_nullable_and_sets_null_on_user_delete
test_f6_does_not_create_sura_history_provider_or_permission_tables
```

Required columns/types:

```text
id BIGINT UNSIGNED PK AUTO_INCREMENT
candidate_id BIGINT UNSIGNED NOT NULL
provider VARCHAR(120) NOT NULL
product VARCHAR(190) NULL
status VARCHAR(32) NOT NULL
contact_email VARCHAR(190) NULL
portal_url VARCHAR(2048) NULL
result TEXT NULL
notes TEXT NULL
support_reference VARCHAR(255) NULL
responsible_user_id BIGINT UNSIGNED NULL
started_at DATETIME NULL
result_at DATETIME NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NULL
```

Required constraints:

```text
UNIQUE(candidate_id)
candidate_id -> candidates.id ON DELETE CASCADE
responsible_user_id -> users.id ON DELETE SET NULL
```

- [ ] **Step 2: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/ScreeningSchemaTest.php --testdox
```

Expected: FAIL because migration 006/table do not exist.

- [ ] **Step 3: Implement migration 006 only**

No status-history, support, provider or SURA-specific tables. Do not alter `candidates.status` schema.

- [ ] **Step 4: Verify focal GREEN**

```bash
vendor/bin/phpunit tests/Integration/Candidates/ScreeningSchemaTest.php tests/Integration/Foundation/MigrationRunnerTest.php --testdox
```

Expected: PASS.

- [ ] **Step 5: Full CI gate and Draft PR**

Run full suite locally where available, then open one Draft PR for F6 and require GitHub CI PHP 8.3/8.4/8.5 PASS before F6.2.

- [ ] **Step 6: Rolling checkpoint + commit**

Commit:

```text
feat(screening): add F6 screening schema
```

Checkpoint records exact HEAD, CI, schema delta `+1`, dependencies `+0`, next RED.

---

### F6.2 — ScreeningService + Lifecycle

**Files:**
- Create: `app/Candidates/ScreeningService.php`
- Create: `tests/Integration/Candidates/ScreeningServiceTest.php`
- Modify: `bootstrap/app.php`
- Update: `docs/checkpoints/F6_CURRENT_CHECKPOINT.md`

**Interfaces:**

```php
final class ScreeningService
{
    public function __construct(PDO $pdo, CandidateService $candidates);

    public function findForCandidate(int $candidateId): ?array;

    public function createForCandidate(
        int $candidateId,
        int $responsibleUserId,
        array $input,
        int $now,
    ): array;

    public function updateForCandidate(
        int $candidateId,
        array $input,
        int $now,
    ): array;

    public function publicForCandidate(int $candidateId): ?array;
}
```

- Consumes: `CandidateService::findCandidate(int): ?array` as the single candidate-state source; PDO; `users.id` supplied by existing authenticated user context.
- Produces: normalized private screening row for admin and allowlisted public projection for candidate portal.

Public projection keys are limited to:

```text
provider
product
status
contact_email
portal_url
result
started_at
result_at
```

- [ ] **Step 1: Write eligibility/create RED**

Pin:

```text
candidate missing -> reject
candidate status new|incomplete|verification|withdrawn -> reject
pending_screening -> create exactly one status=pending
responsible_user_id = creator user id
started_at/result_at/updated_at NULL on create
second create for same candidate -> reject and row count remains one
provider trim + required + max 120
product max 190
contact email valid when present
support_reference max 255
portal_url absolute HTTPS + host + max 2048
```

- [ ] **Step 2: Write lifecycle/timestamp RED**

Pin:

```text
pending same-status metadata edit -> allowed
pending -> in_progress sets started_at once
pending -> approved|rejected sets started_at if NULL and result_at
in_progress same-status metadata edit -> allowed and preserves started_at
in_progress -> approved|rejected sets result_at and preserves started_at
approved|rejected require non-empty result
approved|rejected terminal: every later update fails and row is unchanged
invalid transition rejected
candidate becomes withdrawn before update -> reject and row unchanged
```

Use explicit `$now` timestamps; do not assert wall-clock `CURRENT_TIMESTAMP` for service-managed fields.

- [ ] **Step 3: Write no-side-effect RED**

Before and after terminal updates assert:

```text
candidates.status remains pending_screening
units.status remains unchanged
```

Also pin `publicForCandidate()` to omit all private/internal fields.

- [ ] **Step 4: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/ScreeningServiceTest.php --testdox
```

Expected: FAIL because `ScreeningService` does not exist.

- [ ] **Step 5: Implement minimal ScreeningService**

Use direct PDO, no repository/DTO/interface. Re-read candidate through `CandidateService::findCandidate()` on every create/update gate. `createForCandidate()` always persists `status='pending'`; `updateForCandidate()` accepts same-status metadata edits only for `pending|in_progress`, or one allowed forward transition. Preserve `responsible_user_id` after creation; no reassignment UI/domain operation in F6.

Validation rules are exactly those in the SPEC. `portal_url` validation must not perform network I/O.

- [ ] **Step 6: Wire service in bootstrap**

Instantiate only after `$candidates` exists:

```php
$screening = new ScreeningService($pdo, $candidates);
```

Inject into existing Candidate routes as dependency key:

```text
screening
```

No new route file.

- [ ] **Step 7: Verify focal GREEN + full CI**

```bash
vendor/bin/phpunit tests/Integration/Candidates/ScreeningServiceTest.php --testdox
```

Then full PHP 8.3/8.4/8.5 CI. Do not continue on any regression.

- [ ] **Step 8: Checkpoint + commit**

```text
feat(screening): add insurer screening lifecycle
```

---

### F6.3 — Administrative Candidate Screening

**Files:**
- Modify: `app/Candidates/routes.php`
- Modify: `resources/views/candidate_detail.php`
- Create: `tests/Integration/Candidates/ScreeningAdminRoutesTest.php`
- Update: `docs/checkpoints/F6_CURRENT_CHECKPOINT.md`

**Interfaces:**
- Consumes: `ScreeningService::findForCandidate()`, `createForCandidate()`, `updateForCandidate()`; existing `$adminGuard`, `current_user`, CSRF middleware and `basePath`.
- Produces: screening section on existing candidate detail plus one mutation route.

Route:

```text
POST /admin/candidates/{id}/screening
```

No new GET route: reading remains `GET /admin/candidates/{id}`.

- [ ] **Step 1: Write admin authorization RED**

Pin:

```text
anonymous -> existing login redirect
forced-password -> existing password-change redirect
candidates.view -> can read screening section
candidates.view without manage -> no mutation form / POST 403
candidates.manage -> may POST
owner bypass -> may POST
POST without valid CSRF -> existing global 403
missing candidate -> 404 or existing safe candidate behavior
```

- [ ] **Step 2: Write create/update route RED**

Pin:

```text
pending_screening + no process + valid body -> create pending, 303 to candidate detail
existing pending/in_progress + valid body -> update/transition, 303
service validation error -> 422 text, no partial mutation
terminal -> admin detail read-only and POST rejected
withdrawn candidate -> process remains visible but mutation rejected
/sub basePath -> form action and redirect include basePath
```

The POST route selects create vs update only by `ScreeningService::findForCandidate(candidateId)`; do not add hidden screening IDs.

- [ ] **Step 3: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/ScreeningAdminRoutesTest.php --testdox
```

- [ ] **Step 4: Extend existing routes.php only**

Add dependency extraction for `ScreeningService`. Extend candidate-detail GET data with:

```text
screening
canManageCandidates (existing)
```

Add one POST route. Use authenticated `$user['id']` only for creation responsibility. Preserve existing permissions/CSRF/basePath helpers.

- [ ] **Step 5: Extend candidate_detail.php**

Render one SSR section:

```text
Estudio de aseguradora
```

Creation UI:
- provider prefilled `SURA` in the view only;
- product/contact_email/portal_url/notes/support_reference;
- no editable status on create because creation is always `pending`.

Existing non-terminal UI:
- current fields;
- status select limited to current status + valid next statuses;
- result field available and required by service only for terminal target;
- no responsible-user selector.

Terminal UI:
- read-only data; no mutation form.

Escape every screening value with the existing SSR pattern.

- [ ] **Step 6: Verify focal GREEN + full CI**

```bash
vendor/bin/phpunit tests/Integration/Candidates/ScreeningAdminRoutesTest.php tests/Integration/Candidates/CandidatesAdminRoutesTest.php --testdox
```

Then PHP 8.3/8.4/8.5 CI.

- [ ] **Step 7: Checkpoint + commit**

```text
feat(screening): add candidate admin screening workflow
```

---

### F6.4 — Candidate Portal Screening Projection

**Files:**
- Modify: `app/Candidates/routes.php`
- Modify: `resources/views/public_candidate_continue.php`
- Create: `tests/Integration/Candidates/PublicCandidateScreeningTest.php`
- Update: `docs/checkpoints/F6_CURRENT_CHECKPOINT.md`

**Interfaces:**
- Consumes: token-resolved candidate from existing `CandidateService::findByToken()`, `ScreeningService::publicForCandidate()`.
- Produces: candidate-safe screening section inside the existing continuation page; no new public route.

- [ ] **Step 1: Write public privacy RED**

Pin public HTML contains only allowlisted screening fields and does not contain values from:

```text
screening_processes.id
candidate_id
responsible_user_id
notes
support_reference
```

Use unique sentinel values in each private field and assert none appear in body. Include XSS payloads in provider/product/result/contact URL-adjacent data and assert escaped output.

- [ ] **Step 2: Write CTA/fallback RED**

Pin:

```text
no screening -> no F6 section
screening pending/in_progress + candidate pending_screening + HTTPS portal_url -> external link visible
external href equals stored absolute URL, never basePath-prefixed, never contains continuation token
external link contains rel="noopener noreferrer" or stronger tested no-referrer policy
pending/in_progress without URL -> neutral fallback visible
approved/rejected -> result visible, external CTA hidden
candidate withdrawn -> process may be shown but external CTA hidden
```

Status labels must be human-readable; raw DB status may remain available only as escaped text if needed for deterministic label mapping, but do not invent new state.

- [ ] **Step 3: Write token/basePath regression RED**

Pin:

```text
invalid/expired token -> existing fail-closed behavior
valid /sub/apply/continue/{token} renders F6 section
external HTTPS URL is not rewritten with /sub
raw token is not present in screening data or external href
```

- [ ] **Step 4: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/PublicCandidateScreeningTest.php --testdox
```

- [ ] **Step 5: Extend renderContinue data only**

Resolve public screening by candidate ID and pass a single safe projection to the view:

```text
screening
```

Do not pass private screening row to the public view.

- [ ] **Step 6: Extend public_candidate_continue.php**

Render F6 section even though F5 editing is locked at `pending_screening`; the screening section is informational and independent of the F5 editable form. External CTA rules depend on both candidate status and screening status. Escape every value.

Do not create a new candidate portal page, login or route.

- [ ] **Step 7: Verify focal GREEN + full CI**

```bash
vendor/bin/phpunit tests/Integration/Candidates/PublicCandidateScreeningTest.php tests/Integration/Candidates/PublicCandidateApplyTest.php --testdox
```

Then PHP 8.3/8.4/8.5 CI.

- [ ] **Step 8: Checkpoint + commit**

```text
feat(screening): expose safe screening status to candidates
```

---

### F6.5 — Security, Visual QA, Cero Ruido and Closure

**Files:**
- Create: `tests/Integration/Candidates/ScreeningSecurityRegressionTest.php`
- Modify: `assets/app.css` only after a failing visual/contract test demonstrates need
- Modify: `README.md`
- Replace/delete: `docs/checkpoints/F6_CURRENT_CHECKPOINT.md`
- Create: `docs/checkpoints/F6_CLOSED_2026-10-12.md`

**Interfaces:**
- Consumes: complete F6 implementation.
- Produces: security/visual closure evidence and one canonical F6 pre-merge checkpoint.

- [ ] **Step 1: Write security closure RED**

Cover at minimum:

```text
HTTP/protocol-relative/javascript/missing-host portal URLs rejected with no mutation
candidate withdrawn between GET and POST rejected
terminal row tampering rejected and byte-for-byte relevant values preserved
public page leaks no private screening sentinel/internal IDs
external CTA leaks no continuation token and has noreferrer protection
F6 errors expose no SQL/internal IDs/private notes
no new auth/session/permissions/routes file/dependencies
```

- [ ] **Step 2: Run security RED and apply only reproduced fixes**

```bash
vendor/bin/phpunit tests/Integration/Candidates/ScreeningSecurityRegressionTest.php --testdox
```

No speculative hardening unrelated to a failing F6 contract.

- [ ] **Step 3: Visual QA existing surfaces**

Review only changed surfaces:

```text
admin candidate detail with no screening
admin candidate detail with long provider/product/email/URL/result/notes/support
public continuation pending/in_progress with CTA
public continuation no-URL fallback
public continuation terminal result
```

Widths/reflow authority follows existing F3–F5 primitives. If long URL/value causes overflow, first add a failing UI contract test, then make the smallest CSS change; otherwise CSS delta = 0.

- [ ] **Step 4: Cero Ruido audit**

Prove:

```text
1 new table only
1 new service only
0 new permissions
0 new route files
0 SURA-specific classes/tables
0 candidate-status additions
0 runtime dependencies
0 npm
0 notification engine
0 external HTTP calls
```

Search for dead WIP helpers/files and remove only after confirming no references and green regression tests.

- [ ] **Step 5: Full final verification**

Run all PHPUnit locally, Composer validate strict and the project security boundary tests. Then require GitHub CI on exact final product HEAD:

```text
PHP 8.3 PASS
PHP 8.4 PASS
PHP 8.5 PASS
0 failures
0 errors
```

Record exact test/assertion counts from CI; do not predeclare counts in this plan.

- [ ] **Step 6: Close documentation**

Create `docs/checkpoints/F6_CLOSED_2026-10-12.md` containing:

```text
final product HEAD
exact CI run
schema/service/routes/dependency deltas
F6.1-F6.5 status
security + visual QA
LEY 1 KISS gate
LEY 2 CERO RUIDO gate
PR status
MERGE_ALLOWED state
next F7 gate
```

Delete `F6_CURRENT_CHECKPOINT.md` only after its unique evidence is folded into the canonical close checkpoint. Update README from F6 active to F6 CLOSED / VERIFIED / NOT MERGED.

- [ ] **Step 7: Final review gate**

Review full PR diff, comments, threads and current base/head. Mark Ready for Review only if clean. Do not merge until the project continuation gate authorizes it.

- [ ] **Step 8: Closure commit**

```text
docs(f6): record verified screening closure
```

---

# Execution Order / Blocking

```text
F6.1 GREEN + CI
  -> F6.2 RED/GREEN + CI
  -> F6.3 RED/GREEN + CI
  -> F6.4 RED/GREEN + CI
  -> F6.5 security/visual/zero-noise + exact final CI
  -> F6 CLOSED / VERIFIED / NOT MERGED
  -> final PR review
  -> merge only on project continuation authorization
  -> F7 only after F6 merged
```

No task may skip its RED verification. No later task may be implemented while the previous task has a known failure or unresolved blocker.

---

# Plan Self-Review Result

- **Spec coverage:** all SPEC sections map to F6.1–F6.5; no API/email/upload/contract scope introduced.
- **Step scan:** each task has explicit RED, command, minimal GREEN target, verification and commit/checkpoint gate.
- **Type consistency:** all later tasks consume the exact `ScreeningService` methods defined in F6.2; no second screening identifier is exposed to routes/views.
- **Review Focus coverage:** withdrawal race, terminal tampering, URL edge cases, public privacy/XSS and duplicate create each have an owning test task.
- **Proportion/KISS:** implementation remains one migration + one service + changes to the existing route/views/bootstrap; no repositories/DTOs/interfaces/factories.

---

# Approval Gate

```text
F6_SPEC_APPROVED_BY_USER: YES
F6_PLAN_WRITTEN: YES
F6_PLAN_APPROVED_BY_USER: NO
F6_IMPLEMENTATION_ALLOWED: NO
NEXT: USER PLAN APPROVAL -> F6.1 RED
```

Execution method already established by project methodology: **Native/ChatGPT-first**. Codex is optional only when a concrete implementation/review need demonstrates value; it is not a default dependency.