# F5 Applications + Candidates + References Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement F5 as one candidate-centric application flow from public catalog to administrative review, with secure continuation tokens, references and private candidate documents, without introducing a parallel lead/application/auth system.

**Architecture:** Reuse F2 auth/CSRF, F3 SSR shell/branding and F4 units/publicability. Add exactly four F5 tables and two domain services: `CandidateService` owns candidate identity/process/token/references/status; `CandidateDocumentService` owns structured unit requirements and private upload lifecycle. Public candidate access is by raw continuation token whose SHA-256 hash is stored; staff access remains the existing authenticated permission model.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO, MariaDB/MySQL, server-rendered PHP views, existing CSS/JS, PHPUnit 11.5, `finfo`; no new runtime dependency and no npm.

**Spec:** `docs/specs/F5_APPLICATIONS_CANDIDATES_REFERENCES_SPEC.md`

## Global Constraints

- F5 adds exactly four tables: `candidates`, `candidate_references`, `unit_application_requirements`, `candidate_documents`.
- F5 adds exactly two permissions: `candidates.view`, `candidates.manage`.
- Maximum two F5 domain services: `CandidateService`, `CandidateDocumentService`.
- No `lead`, `application`, `rental_application`, candidate login, second session system, CRM, notification engine, workflow engine or second CSRF implementation.
- `candidates` has one active `unit_id`; no multi-application history table in F5.
- Candidate identity is unique by normalized `(document_type, document_number)`.
- Continuation raw token is `bin2hex(random_bytes(32))`; persist only `hash('sha256', $rawToken)`; TTL = 7 days.
- F5 statuses are exactly `new`, `incomplete`, `verification`, `pending_screening`, `withdrawn`.
- A new public application requires a currently publicable F4 unit; an already-created candidate may continue/submit if that unit later stops being publicable.
- Public candidate URLs never contain internal candidate/document IDs.
- Candidate documents accept only real MIME `application/pdf`, `image/jpeg`, `image/png`, maximum 10 MiB, stored under `storage/candidate-documents/` outside webroot.
- One current file per `(candidate_id, requirement_id)`; replacement must not leave orphan files or delete the old file before the new record is durable.
- F5 does not change `units.status` automatically.
- Every POST reuses existing global CSRF middleware.
- All routes/redirects/forms preserve `basePath` / subfolder installation.
- Composer/runtime dependency delta = 0; npm delta = 0.
- No merge without explicit user approval.
- Work in microphases F5.1–F5.8. Maintain one rolling `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`; replace it with one canonical `F5_CLOSED_YYYY-MM-DD.md` at phase close instead of accumulating WIP checkpoint files.

## Review Focus

1. **Duplicate document probing:** repeated public start with an existing normalized document must not reveal candidate existence, unit, state, contact data or token and must not create a second row. Covered in F5.2/F5.3 tests.
2. **Unit becomes non-public mid-process:** new starts must fail, but a candidate created earlier must still continue and submit. Covered in F5.2/F5.7 tests.
3. **Document replacement failure windows:** invalid/new-file persistence failures must preserve the prior valid document and must not leave orphan files. Covered in F5.5 tests.
4. **Requirement/unit mismatch after unit change:** a requirement code from another/currently old unit must be rejected and never attach to the candidate. Covered in F5.5/F5.7 tests.
5. **Expired/rotated token + subfolder:** expired and prior rotated tokens must fail closed without identity leakage; valid token routes/redirects must still work under a subfolder. Covered in F5.2/F5.3/F5.6/F5.8 tests.

---

# File Structure Locked by This Plan

## New production files

- `database/migrations/005_create_candidates_references_requirements_documents.sql` — the four F5 tables only.
- `app/Candidates/CandidateService.php` — candidate process, token, profile, references, state and admin reads.
- `app/Candidates/CandidateDocumentService.php` — requirement configuration and candidate-document filesystem lifecycle.
- `app/Candidates/routes.php` — all F5 public/admin HTTP routes; one route file only.
- `resources/views/public_apply_start.php` — initial public application form.
- `resources/views/public_candidate_continue.php` — public continuation/profile/references/documents/review surface.
- `resources/views/candidates.php` — private candidate list.
- `resources/views/candidate_detail.php` — private candidate detail, state/token/document actions.

## Existing production files modified

- `app/Auth/Permissions.php` — add only the two F5 permissions.
- `bootstrap/app.php` — instantiate/register the two F5 services, candidate storage path and one F5 route file.
- `resources/views/public_catalog_detail.php` — add the live `Aplicar` link only after F5 public route exists.
- `resources/views/unit_detail.php` — requirement configuration inside existing unit context for authorized managers.
- existing Apps launcher rendering source (`resources/views/layouts/app.php` and/or the current launcher data source discovered at implementation time) — expose `Candidatos` only when authorized; do not create a second launcher system.
- `assets/app.css` — only F5 layout rules actually required after visual RED; reuse F3/F4 primitives first.
- `README.md` — active/closed F5 status only at documented gates.

## New tests

- `tests/Integration/Candidates/CandidatesSchemaTest.php`
- `tests/Integration/Candidates/CandidateServiceTest.php`
- `tests/Integration/Candidates/PublicCandidateApplyTest.php`
- `tests/Integration/Candidates/CandidateReferencesTest.php`
- `tests/Integration/Candidates/CandidateDocumentsTest.php`
- `tests/Integration/Candidates/CandidatesAdminRoutesTest.php`
- `tests/Integration/Candidates/CandidateCatalogCtaTest.php`
- `tests/Integration/Candidates/CandidatesSecurityRegressionTest.php`

Existing F1–F4 tests are regression authority and must remain green.

---

### F5.1: Schema + Permissions Foundation

**Files:**
- Create: `database/migrations/005_create_candidates_references_requirements_documents.sql`
- Modify: `app/Auth/Permissions.php`
- Create: `tests/Integration/Candidates/CandidatesSchemaTest.php`
- Modify: `tests/Integration/Foundation/MigrationRunnerTest.php` only if its exact migration-count contract requires the new migration.
- Modify/Create rolling checkpoint: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Interfaces:**
- Consumes: F4 `units.id`; existing migration runner; `Permissions::sanitize(array): array` and `Permissions::allows(array,string): bool`.
- Produces: four F5 tables with spec-exact columns/FKs/unique keys; permissions `candidates.view` and `candidates.manage` available to existing auth/user permission flows.

- [ ] **Step 1: Write schema RED**

Add tests named at minimum:

```text
test_f5_migration_creates_exactly_candidate_tables
test_candidate_identity_and_token_hash_are_unique
test_candidate_requirement_and_document_foreign_keys_are_correct
test_f5_does_not_create_leads_or_applications_tables
test_permissions_catalog_contains_exactly_two_candidate_permissions
```

Assertions must pin:

```text
candidates
candidate_references
unit_application_requirements
candidate_documents
UNIQUE(document_type, document_number)
UNIQUE(continuation_token_hash)
UNIQUE(unit_id, code)
UNIQUE(candidate_id, requirement_id)
```

and prove no `leads`, `applications`, `rental_applications` table exists.

- [ ] **Step 2: Run RED**

Run:

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidatesSchemaTest.php --testdox
```

Expected: FAIL because migration 005/two candidate permissions do not exist.

- [ ] **Step 3: Implement migration 005 only**

Create the four tables exactly as the approved SPEC. Do not add audit/history/session/token tables. Use FKs to existing `units` and the F5 parent tables; do not add a candidate public key.

- [ ] **Step 4: Add permissions only**

Append exactly:

```text
candidates.view
candidates.manage
```

to `Permissions::CATALOG`; preserve owner bypass and existing sanitizer behavior.

- [ ] **Step 5: Verify focal GREEN + clean migration**

Run:

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidatesSchemaTest.php tests/Integration/Foundation/MigrationRunnerTest.php --testdox
```

Expected: PASS.

- [ ] **Step 6: Run full matrix on CI**

Push the microphase and require PHP 8.3/8.4/8.5 PASS before F5.2. Open PR as **Draft** after this first independently green product increment.

- [ ] **Step 7: Update rolling checkpoint and commit**

Checkpoint records F5.1 GREEN, migration/permission deltas, branch HEAD, CI run and next exact RED. Commit message:

```text
feat(candidates): add F5 schema and permissions
```

---

### F5.2: Candidate Core + Secure Continuation Token

**Files:**
- Create: `app/Candidates/CandidateService.php`
- Create: `tests/Integration/Candidates/CandidateServiceTest.php`
- Modify: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Interfaces:**
- Consumes: PDO; F4 `PropertyService::findPublicUnit(string $publicKey): ?array`; four F5 tables; current Unix time passed explicitly by tests/routes.
- Produces:

```php
final class CandidateService
{
    public function __construct(PDO $pdo, int $tokenTtlSeconds = 604800);

    /** @return array{candidate_id:int, continuation_token:string} */
    public function startCandidate(string $publicKey, array $input, int $now): array;

    public function findByToken(string $rawToken, int $now): ?array;
    public function updateByToken(string $rawToken, array $input, int $now): array;
    public function changeUnitByToken(string $rawToken, string $publicKey, int $now): array;
    public function replaceReferencesByToken(string $rawToken, array $references, int $now): array;
    public function submitByToken(string $rawToken, int $now): array;

    public function listCandidates(): array;
    public function findCandidate(int $candidateId): ?array;
    public function transitionStatus(int $candidateId, string $status): array;
    public function regenerateToken(int $candidateId, int $now): string;
}
```

`startCandidate()` may return the internal ID to server-side callers, but no public route/view may emit it.

- [ ] **Step 1: Write core RED**

Tests must cover:

```text
start candidate on publicable unit -> status new
raw continuation token is 64 hex chars but DB stores only 64-char SHA-256 hash
token resolves only before now + 604800
token invalid/expired -> null without mutation
document type/number normalization prevents duplicate person
duplicate start throws one generic domain error and does not create row
candidate existing on unit that later becomes non-public still resolves by valid token
new candidate on non-publicable unit is rejected
public identity document_type/document_number cannot be changed by updateByToken
allowed states exactly new/incomplete/verification/pending_screening/withdrawn
invalid transition rejected
regenerateToken rotates hash and old token no longer resolves
```

Review Focus #1, #2 and #5 must be explicit tests here.

- [ ] **Step 2: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidateServiceTest.php --testdox
```

Expected: FAIL because `CandidateService` does not exist.

- [ ] **Step 3: Implement minimal `CandidateService`**

Use direct PDO, no repository/DTO/interface. Instantiate/reuse F4 publicability logic rather than duplicating the unit publication predicate. Normalize candidate identity deterministically before querying/inserting. Use DB transactions only where a multi-write invariant exists.

`updateByToken()` updates profile/labor fields but never public identity fields, `unit_id`, token hash or status arbitrarily. A successful partial save promotes `new` to `incomplete` but never moves a later state backward.

- [ ] **Step 4: Implement state rules**

F5 transitions:

```text
new -> incomplete | withdrawn
incomplete -> verification | withdrawn
verification -> pending_screening | withdrawn
pending_screening -> withdrawn
withdrawn -> no transition in F5
```

`submitByToken()` is the only public path that sets `verification`; it must later receive document-completeness coverage in F5.5 before public submit is wired.

- [ ] **Step 5: Verify focal GREEN**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidateServiceTest.php --testdox
```

Expected: PASS.

- [ ] **Step 6: Full CI + rolling checkpoint + commit**

Require PHP 8.3/8.4/8.5 PASS. Update one rolling checkpoint. Commit:

```text
feat(candidates): add candidate core and secure tokens
```

Do not open public routes yet.

---

### F5.3: Public Start + Continue Surface

**Files:**
- Create: `app/Candidates/routes.php`
- Create: `resources/views/public_apply_start.php`
- Create: `resources/views/public_candidate_continue.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Integration/Candidates/PublicCandidateApplyTest.php`
- Modify: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Interfaces:**
- Consumes: `CandidateService` F5.2, `PropertyService`, existing `View::renderPublic`, global `Csrf`, `base_path`, F4 publicability.
- Produces public routes:

```text
GET  /apply/{publicKey}
POST /apply/{publicKey}
GET  /apply/continue/{token}
POST /apply/continue/{token}
```

No CTA is added to the catalog yet; direct routes are proven first.

- [ ] **Step 1: Write public-route RED**

Cover:

```text
GET apply renders only for currently publicable unit
POST apply validates CSRF/basic fields/consent
POST apply creates candidate and returns one continuation URL containing raw token, never candidate ID
existing normalized document returns same generic non-enumerating response whether account exists or not
GET continue invalid/expired token fails closed
GET/POST continue valid token exposes only that candidate's editable data
public responses never include continuation_token_hash/storage/internal candidate ID
all links/actions/redirects preserve /sub basePath
```

- [ ] **Step 2: Add throttle RED**

Pin the basic F5 throttle to session-local windows with no new infrastructure:

```text
start: maximum 5 POST attempts per rolling 10-minute session window
continue/submit mutation bucket: maximum 10 POST attempts per rolling 10-minute session window
```

Expected behavior over limit: HTTP 429 generic response, no DB mutation. This is a V1 abuse brake, not F22 hardening.

- [ ] **Step 3: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/PublicCandidateApplyTest.php --testdox
```

Expected: FAIL because routes/views/bootstrap registration are absent.

- [ ] **Step 4: Register F5 services/routes in bootstrap**

Add:

```text
candidate_document_directory = storage/candidate-documents
CandidateService($pdo)
CandidateDocumentService($pdo, $candidateDocumentDirectory)  // class lands in F5.5; until then do not instantiate it early
```

For F5.3, register only `CandidateService` and `app/Candidates/routes.php`. When F5.5 lands, extend the same registration; never create `public_routes.php` + `routes.php` duplication.

- [ ] **Step 5: Implement public start/continue routes and views**

Use one public layout. POST start catches duplicate identity with the same generic user-facing result used for non-sensitive start failures. Never regenerate/return an existing candidate token from a duplicate public start.

Implement throttle as a small closure/local helper in `app/Candidates/routes.php` backed by session timestamps; no new service/table/dependency.

- [ ] **Step 6: Verify focal + regression GREEN**

```bash
vendor/bin/phpunit tests/Integration/Candidates/PublicCandidateApplyTest.php tests/Integration/Properties/PublicCatalogDetailTest.php --testdox
```

Then full CI PHP 8.3/8.4/8.5.

- [ ] **Step 7: Rolling checkpoint + commit**

```text
feat(candidates): add public application continuation flow
```

---

### F5.4: Profile Completion + References

**Files:**
- Modify: `app/Candidates/CandidateService.php`
- Modify: `app/Candidates/routes.php`
- Modify: `resources/views/public_candidate_continue.php`
- Create: `tests/Integration/Candidates/CandidateReferencesTest.php`
- Extend: `tests/Integration/Candidates/CandidateServiceTest.php`
- Modify: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Interfaces:**
- Consumes: `CandidateService::updateByToken`, `replaceReferencesByToken`, `changeUnitByToken`.
- Produces:

```text
POST /apply/continue/{token}/references
POST /apply/continue/{token}/unit
```

and a complete SSR continuation form for profile/labor/occupants/pets/references.

- [ ] **Step 1: Write profile/reference RED**

Pin validation:

```text
monthly_income >= 0 when present
occupants_count >= 0 when present
pets_declared strict boolean
references may be zero or more; no invented minimum
reference requires name + phone; optional type/relationship/email
replaceReferencesByToken replaces the entire owned set in one transaction
foreign candidate reference IDs are never accepted because public form sends no reference IDs
partial save new -> incomplete
submitted/withdrawn candidate cannot mutate publicly
```

- [ ] **Step 2: Write unit-change RED**

Cover exact gate:

```text
allowed only new|incomplete
submitted_at must be null
candidate_documents count must be zero
new target publicKey must still be publicable
same candidate row is updated; no second candidate row
```

- [ ] **Step 3: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidateReferencesTest.php tests/Integration/Candidates/CandidateServiceTest.php --testdox
```

Expected: relevant new tests FAIL.

- [ ] **Step 4: Implement minimal service/view/routes**

References are set replacement, not public CRUD-by-ID. Keep candidate document identity immutable publicly. Escape all candidate/reference values in SSR.

- [ ] **Step 5: Verify focal + full CI GREEN**

Run focal suite, then PHP 8.3/8.4/8.5 CI.

- [ ] **Step 6: Rolling checkpoint + commit**

```text
feat(candidates): add profile references and unit change rules
```

---

### F5.5: Structured Requirements + Private Candidate Documents

**Files:**
- Create: `app/Candidates/CandidateDocumentService.php`
- Modify: `app/Candidates/CandidateService.php`
- Modify: `app/Candidates/routes.php`
- Modify: `bootstrap/app.php`
- Modify: `resources/views/unit_detail.php`
- Modify: `resources/views/public_candidate_continue.php`
- Create: `tests/Integration/Candidates/CandidateDocumentsTest.php`
- Extend: `tests/Integration/Candidates/CandidateServiceTest.php`
- Modify: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Interfaces:**
- Consumes: F5 schema, candidate token resolution, existing `UploadedFileInterface`, `finfo`, existing unit/admin context.
- Produces:

```php
final class CandidateDocumentService
{
    public function __construct(PDO $pdo, string $documentDirectory);

    public function listRequirementsForUnit(int $unitId, bool $activeOnly = false): array;
    public function createRequirement(int $unitId, array $input): array;
    public function updateRequirement(int $unitId, int $requirementId, array $input): array;
    public function reorderRequirements(int $unitId, array $orderedRequirementIds): void;

    public function listDocumentsForCandidate(int $candidateId): array;
    public function addOrReplaceDocument(int $candidateId, string $requirementCode, UploadedFileInterface $upload): array;
    public function privateDocument(int $candidateId, int $documentId): ?array;
}
```

New routes:

```text
POST /admin/units/{id}/application-requirements
POST /admin/units/{id}/application-requirements/{requirementId}
POST /admin/units/{id}/application-requirements/order
POST /apply/continue/{token}/documents/{requirementCode}
POST /apply/continue/{token}/submit
```

- [ ] **Step 1: Write requirement RED**

Cover:

```text
requirement code unique within unit
code immutable after create
manager may edit label/description/is_required/is_active/sort_order
reorder requires exact owned set, no duplicate/foreign IDs
requirement with candidate documents is inactivated, not physically deleted
activeOnly returns only active unit requirements in configured order
```

- [ ] **Step 2: Write upload/replacement RED**

Cover:

```text
real PDF/JPEG/PNG <= 10 MiB accepted
SVG/PHP/fake MIME/empty/oversize rejected
stored filename random and constrained; original name never used as path
requirementCode must belong to candidate's current unit and be active
UNIQUE candidate+requirement produces replacement, not duplicate row
failed new upload/persistence preserves prior row/file
successful replacement removes prior file after durable replacement
missing/foreign candidate rejected
storage directory created restrictively outside webroot
```

Explicitly cover Review Focus #3/#4.

- [ ] **Step 3: Write submit-completeness RED**

`CandidateService::submitByToken()` must fail until all current unit requirements where `is_required=1 AND is_active=1` have a valid candidate document. Optional/inactive requirements do not block. Unit no longer being publicable does not block an already-created candidate's submit.

- [ ] **Step 4: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidateDocumentsTest.php tests/Integration/Candidates/CandidateServiceTest.php --testdox
```

- [ ] **Step 5: Implement `CandidateDocumentService` + bootstrap registration**

No reuse of `UnitMediaService`. Files live in `storage/candidate-documents`; validate content MIME with `finfo`; constrain generated stored names; filesystem/DB replacement must be rollback-safe.

- [ ] **Step 6: Wire unit requirement UI + candidate uploads + final submit**

Use `candidates.manage` for requirement mutations even though UI sits in unit context. Public upload resolves candidate from token server-side, then calls document service with internal candidate ID without emitting that ID.

- [ ] **Step 7: Verify focal + storage security + full CI**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidateDocumentsTest.php tests/Integration/Foundation/SecurityBoundaryTest.php --testdox
```

Then full PHP 8.3/8.4/8.5 CI.

- [ ] **Step 8: Rolling checkpoint + commit**

```text
feat(candidates): add application requirements and private documents
```

---

### F5.6: Administrative Candidates Module

**Files:**
- Modify: `app/Candidates/routes.php`
- Create: `resources/views/candidates.php`
- Create: `resources/views/candidate_detail.php`
- Modify: existing Apps launcher source only as necessary
- Create: `tests/Integration/Candidates/CandidatesAdminRoutesTest.php`
- Modify: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Interfaces:**
- Consumes: `CandidateService::listCandidates`, `findCandidate`, `transitionStatus`, `regenerateToken`; `CandidateDocumentService::listDocumentsForCandidate`, `privateDocument`; existing auth/session/permission guard patterns.
- Produces routes:

```text
GET  /admin/candidates
GET  /admin/candidates/{id}
POST /admin/candidates/{id}/status
POST /admin/candidates/{id}/token/regenerate
GET  /admin/candidates/{id}/documents/{documentId}
```

- [ ] **Step 1: Write admin RED**

Cover:

```text
unauthenticated -> login
must_change_password -> account/password
candidates.view -> list/detail/private document read
candidates.manage -> status/token regenerate
view-only cannot mutate
no candidate permission -> 403
owner bypass works
launcher visible only to authorized user
list/detail escape candidate/reference/filename input
```

- [ ] **Step 2: Write private-document IDOR RED**

Requesting document B under candidate A returns 404 even with valid `candidates.view`. Valid document returns persisted MIME plus `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-store`.

- [ ] **Step 3: Write token-regeneration RED**

Admin regenerate returns/shows the new raw continuation URL only in the immediate response; DB contains hash only; old raw token fails; response respects `base_url` + `basePath` and does not persist/log raw token.

- [ ] **Step 4: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidatesAdminRoutesTest.php --testdox
```

- [ ] **Step 5: Implement admin routes/views using existing guard pattern**

Keep one candidate module and one auth. Do not add dashboard analytics or filters until tests prove a real need; initial list may be simple chronological/actionable list.

- [ ] **Step 6: Verify focal + auth regressions + full CI**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidatesAdminRoutesTest.php tests/Integration/Auth/AuthRoutesTest.php tests/Integration/UI/ShellRoutesTest.php --testdox
```

Then full PHP 8.3/8.4/8.5 CI.

- [ ] **Step 7: Rolling checkpoint + commit**

```text
feat(candidates): add private candidate administration
```

---

### F5.7: Catalog CTA + End-to-End Unit/Candidate Rules

**Files:**
- Modify: `resources/views/public_catalog_detail.php`
- Modify: `app/Properties/routes.php` only if view data requires a small existing-contract adjustment; prefer no service change.
- Extend: `app/Candidates/routes.php` / views only where an end-to-end rule is missing.
- Create: `tests/Integration/Candidates/CandidateCatalogCtaTest.php`
- Extend: `tests/Integration/Candidates/PublicCandidateApplyTest.php`
- Modify: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Interfaces:**
- Consumes: F4 detail's already-live publicability gate and `unit.public_key`; F5 public `/apply/{publicKey}` route.
- Produces: real **Aplicar** CTA from current public detail to F5; no dead button and no internal ID.

- [ ] **Step 1: Write CTA RED**

Cover:

```text
publicable detail contains Aplicar -> /apply/{publicKey}
subfolder produces /sub/apply/{publicKey}
private/internal IDs absent from CTA
non-publicable detail remains 404, therefore no CTA
F4 list behavior remains unchanged
```

- [ ] **Step 2: Write end-to-end lifecycle RED**

Cover Review Focus #2/#4:

```text
candidate starts while unit publicable
unit later becomes occupied/inactive/unpublished
new start now rejected / CTA disappears
existing candidate valid token still loads, uploads against original unit requirements and submits
candidate with zero documents may change to another publicable unit while new/incomplete
candidate with any document may not change unit
old-unit requirementCode after successful unit change is rejected
no unit.status is mutated by application start/submit
```

- [ ] **Step 3: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidateCatalogCtaTest.php tests/Integration/Candidates/PublicCandidateApplyTest.php --testdox
```

- [ ] **Step 4: Implement minimum CTA/integration changes**

Do not add `application_count`, reservation flags, `in_process` automation or catalog state cache.

- [ ] **Step 5: Verify all F4 + F5 integration tests and full CI**

```bash
vendor/bin/phpunit tests/Integration/Properties tests/Integration/Candidates --testdox
```

Then PHP 8.3/8.4/8.5 CI.

- [ ] **Step 6: Rolling checkpoint + commit**

```text
feat(candidates): connect catalog to candidate application
```

---

### F5.8: Security/Visual Regression + Zero-Noise Closure

**Files:**
- Create: `tests/Integration/Candidates/CandidatesSecurityRegressionTest.php`
- Modify: `tests/Unit/UI/AssetContractTest.php` only if a reproduced F5 visual contract requires CSS changes.
- Modify: `assets/app.css` only after visual/test RED.
- Modify: `README.md`
- Update SPEC/PLAN to as-built status only where implementation refined a preliminary contract.
- Replace: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md` with canonical `docs/checkpoints/F5_CLOSED_2026-10-12.md` after all gates pass.

**Interfaces:**
- Consumes: all F5.1–F5.7 behavior.
- Produces: one final recoverable F5 state ready for PR review; no merge.

- [ ] **Step 1: Write/complete security regression tests**

Pin:

```text
candidate token not enumerable and malformed lengths fail closed
expired/rotated token cannot read or mutate
public duplicate document response is non-enumerating
all F5 POST routes reject missing CSRF
all F5 public/admin routes honor subfolder/base path
candidate document direct storage path remains blocked by existing Apache/security boundary
private document cross-candidate IDOR 404
fake MIME/path traversal stored_name rejected
public pages never emit token hash, storage path, internal candidate/document IDs
basic throttle returns 429 with no mutation
```

- [ ] **Step 2: Run security RED/GREEN cycle**

Run focal security suite. If a test exposes a defect, fix only the reproduced cause in existing F5 service/route/view; do not add a security framework.

- [ ] **Step 3: Run real visual QA before CSS changes**

Render at minimum:

```text
360x800
390x844
412x915
768x1024
1280x900
200% reflow equivalent
```

Surfaces:

```text
public apply start
public candidate continue with long names/email/reference text
candidate document/review sections
admin candidate list
admin candidate detail
catalog detail with Aplicar
```

Check horizontal overflow, visible focus, touch targets, bottom-nav overlap, long filename/email wrapping and upload controls. Add CSS only for reproduced defects; reuse F3/F4 primitives first.

- [ ] **Step 4: Full architecture diff / Two Laws gate**

Prove:

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
DB_SCHEMA_DELTA: exactly +4 tables
PERMISSIONS_DELTA: exactly +2
DOMAIN_SERVICES_DELTA: exactly +2
ROUTE_FILES_F5: exactly 1
COMPOSER_DELTA: 0
NPM_DELTA: 0
AUTH_DELTA: 0
NO lead/application/CRM parallel entity
NO notification engine
NO extra WIP checkpoint files
```

- [ ] **Step 5: Run final full CI on exact closing HEAD**

Require:

```text
composer validate --strict PASS
PHP 8.3 PASS
PHP 8.4 PASS
PHP 8.5 PASS
full PHPUnit 0 failures / 0 errors
```

Record exact run ID/test/assertion totals.

- [ ] **Step 6: Converge documentation**

Update README to `F5 CLOSED / VERIFIED / NOT MERGED`. Convert SPEC/PLAN to as-built only where necessary; preserve future F6+ roadmap. Delete the rolling checkpoint only after `F5_CLOSED_2026-10-12.md` demonstrably subsumes it.

- [ ] **Step 7: Final PR review**

Review changed-file list, focal high-risk patches, comments/review threads, mergeability and CI. Set Draft PR to Ready for review only when no P0/P1/blocker remains.

- [ ] **Step 8: Stop before merge**

Report final F5 HEAD, CI, tests, QA, Two Laws and PR state. Merge requires a new explicit user authorization.

---

# Execution Discipline

For every F5 microphase:

```text
SCOPE
PRE-KISS
RED
VERIFY RED
MINIMAL GREEN
VERIFY GREEN
FULL CI 8.3/8.4/8.5
CONVERGE
UPDATE ONE ROLLING CHECKPOINT
NEXT MICROPHASE
```

Never open the next microphase merely because local/focal tests pass; require the agreed CI gate. If CI reveals an unrelated infrastructure failure, diagnose and record it separately rather than patching product blindly.

# Final Expected F5 Delta

```text
+4 DB tables
+2 permissions
+2 domain services max
+1 F5 route file
+4 primary F5 views
+0 runtime dependencies
+0 npm
+0 auth systems
+0 lead/application parallel tables
+0 notification engines
```

F6 must consume the existing `candidates` state/process and extend from `pending_screening`; it must not copy candidates into a second application/screening identity.
