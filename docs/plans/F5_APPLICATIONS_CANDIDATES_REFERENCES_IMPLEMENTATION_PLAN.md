# F5 Applications + Candidates + References Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement F5 as one candidate-centric application flow from public catalog to administrative review, with secure continuation tokens, references and private candidate documents, without introducing a parallel lead/application/auth system.

**Architecture:** Reuse F2 auth/CSRF, F3 SSR shell/branding and F4 units/publicability. Add exactly four F5 tables and two domain services: `CandidateService` owns candidate identity/process/token/references/status; `CandidateDocumentService` owns structured unit requirements and private upload lifecycle. Public candidate continuation is by a cryptographic raw token whose SHA-256 hash is persisted; staff access remains the existing authenticated permission model.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO, MariaDB/MySQL, server-rendered PHP views, existing CSS/JS, PHPUnit 11.5, `finfo`; no new runtime dependency and no npm.

**Spec:** `docs/specs/F5_APPLICATIONS_CANDIDATES_REFERENCES_SPEC.md`

**Refinement:** `docs/decisions/F5_DUPLICATE_IDENTITY_PRIVACY_AND_STATE_TRANSITIONS_2026-10-12.md`

## Global Constraints

- F5 adds exactly four tables: `candidates`, `candidate_references`, `unit_application_requirements`, `candidate_documents`.
- F5 adds exactly two permissions: `candidates.view`, `candidates.manage`.
- Maximum two F5 domain services: `CandidateService`, `CandidateDocumentService`.
- No `lead`, `application`, `rental_application`, candidate login, second session system, CRM, notification engine, workflow engine or second CSRF implementation.
- `candidates` has one active `unit_id`; no multi-application history table in F5.
- Candidate identity is unique by normalized `(document_type, document_number)`.
- Raw continuation token is `bin2hex(random_bytes(32))`; persist only `hash('sha256', $rawToken)`; TTL = 604800 seconds (7 days).
- F5 statuses are exactly `new`, `incomplete`, `verification`, `pending_screening`, `withdrawn`.
- Canonical transitions: `new -> incomplete|verification|withdrawn`; `incomplete -> verification|withdrawn`; `verification -> pending_screening|withdrawn`; `pending_screening -> withdrawn`; `withdrawn` terminal in F5.
- Only `submitByToken()` may perform public `new|incomplete -> verification` after completeness validation.
- A new public application requires a currently publicable F4 unit; an already-created candidate may continue/submit if that unit later stops being publicable.
- Public candidate URLs never contain internal candidate/document IDs.
- Duplicate identity privacy follows the refinement decision: no duplicate row, no token, no private data/candidate context returned; generic response + throttle. F5 does not claim strong existence-hiding without an out-of-band channel.
- Candidate documents accept only real MIME `application/pdf`, `image/jpeg`, `image/png`, maximum 10 MiB, stored under `storage/candidate-documents/` outside webroot.
- One current file per `(candidate_id, requirement_id)`.
- F5 does not change `units.status` automatically.
- Every POST reuses existing global CSRF middleware.
- All routes/redirects/forms preserve `basePath` / subfolder installation.
- Composer/runtime dependency delta = 0; npm delta = 0.
- No merge without explicit user approval.
- Work in microphases F5.1–F5.8.
- Maintain one rolling `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`; replace it with one canonical `F5_CLOSED_2026-10-12.md` at close rather than accumulating WIP checkpoints.

## Review Focus

1. **Duplicate identity probing:** duplicate start must not return token, candidate ID, unit, state or contact data and must not create a second row; response copy/status class remains generic and throttled.
2. **Unit becomes non-public mid-process:** new starts fail, but a candidate created earlier may still continue and submit.
3. **Document replacement failure windows:** invalid/persistence failures preserve the prior valid document; successful replacement must not delete the old file before the replacement is durable.
4. **Requirement/unit mismatch after unit change:** old/foreign requirement code is rejected and never attaches to the candidate.
5. **Expired/rotated token + subfolder:** expired/previous tokens fail closed; valid token routes/redirects work under base paths.

---

# Locked File Map

## New production files

- `database/migrations/005_create_candidates_references_requirements_documents.sql`
- `app/Candidates/CandidateService.php`
- `app/Candidates/CandidateDocumentService.php`
- `app/Candidates/routes.php`
- `resources/views/public_apply_start.php`
- `resources/views/public_candidate_continue.php`
- `resources/views/candidates.php`
- `resources/views/candidate_detail.php`

## Existing production files expected to change

- `app/Auth/Permissions.php`
- `bootstrap/app.php`
- `resources/views/public_catalog_detail.php`
- `resources/views/unit_detail.php`
- existing Apps launcher rendering source only as needed to expose authorized `Candidatos`
- `assets/app.css` only after reproduced visual RED
- `README.md` at documented gates

## New test files

- `tests/Integration/Candidates/CandidatesSchemaTest.php`
- `tests/Integration/Candidates/CandidateServiceTest.php`
- `tests/Integration/Candidates/PublicCandidateApplyTest.php`
- `tests/Integration/Candidates/CandidateReferencesTest.php`
- `tests/Integration/Candidates/CandidateDocumentsTest.php`
- `tests/Integration/Candidates/CandidatesAdminRoutesTest.php`
- `tests/Integration/Candidates/CandidateCatalogCtaTest.php`
- `tests/Integration/Candidates/CandidatesSecurityRegressionTest.php`

Existing F1–F4 tests remain regression authority.

---

### F5.1 — Schema + Permissions Foundation

**Files:**
- Create: `database/migrations/005_create_candidates_references_requirements_documents.sql`
- Modify: `app/Auth/Permissions.php`
- Create: `tests/Integration/Candidates/CandidatesSchemaTest.php`
- Modify: `tests/Integration/Foundation/MigrationRunnerTest.php` only if its exact migration contract requires it
- Create/update: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Interfaces:**
- Consumes: existing migration runner, `units.id`, `Permissions::sanitize()` / `Permissions::allows()`.
- Produces: four F5 tables and two recognized permissions.

- [ ] **Step 1: Write schema RED**

Tests:

```text
test_f5_migration_creates_exactly_candidate_tables
test_candidate_identity_and_token_hash_are_unique
test_candidate_requirement_and_document_foreign_keys_are_correct
test_f5_does_not_create_leads_or_applications_tables
test_permissions_catalog_contains_candidate_permissions
```

Pin unique keys:

```text
(document_type, document_number)
continuation_token_hash
(unit_id, code)
(candidate_id, requirement_id)
```

- [ ] **Step 2: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidatesSchemaTest.php --testdox
```

Expected: FAIL because migration/permissions do not exist.

- [ ] **Step 3: Implement migration 005**

Create exactly the four SPEC tables. No audit/history/token/session/application tables. No candidate public key.

- [ ] **Step 4: Add permissions**

Append exactly:

```text
candidates.view
candidates.manage
```

to the existing permission catalog; preserve owner bypass.

- [ ] **Step 5: Verify focal GREEN**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidatesSchemaTest.php tests/Integration/Foundation/MigrationRunnerTest.php --testdox
```

- [ ] **Step 6: Full CI gate**

Require PHP 8.3/8.4/8.5 PASS. After this first product GREEN, open one Draft PR for F5.

- [ ] **Step 7: Rolling checkpoint + commit**

Checkpoint must record F5.1 GREEN, exact HEAD/CI and next RED.

Commit:

```text
feat(candidates): add F5 schema and permissions
```

---

### F5.2 — Candidate Core + Secure Continuation Token

**Files:**
- Create: `app/Candidates/CandidateService.php`
- Create: `tests/Integration/Candidates/CandidateServiceTest.php`
- Update: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Interfaces:**

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

- Consumes: PDO and F4 `PropertyService::findPublicUnit(string): ?array` for the publicability gate.
- Produces: all candidate/token/state primitives used later.

- [ ] **Step 1: Write candidate/token RED**

Cover:

```text
publicable unit -> candidate status new
raw token = 64 lowercase hex; DB stores only SHA-256 hash
valid until now+604800; expired/invalid fails closed
normalized duplicate document does not create second row
duplicate domain error carries no candidate-specific data/token
candidate created before unit unpublish still resolves by valid token
new candidate on non-publicable unit rejected
document_type/document_number immutable through public update
regenerateToken invalidates previous raw token
```

- [ ] **Step 2: Write state RED**

Pin exactly:

```text
new -> incomplete | verification | withdrawn
incomplete -> verification | withdrawn
verification -> pending_screening | withdrawn
pending_screening -> withdrawn
withdrawn -> terminal
```

`new|incomplete -> verification` via public flow occurs only through `submitByToken()`.

- [ ] **Step 3: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidateServiceTest.php --testdox
```

- [ ] **Step 4: Implement minimal CandidateService**

Use direct PDO. Reuse F4 publicability logic rather than duplicating its predicate. Normalize document type/number before duplicate query/insert. Persist only token hash. Public updates may modify profile/labor/home fields but not identity/unit/token/status directly.

- [ ] **Step 5: Define labor completeness deterministically**

For F5, labor data is considered “started” when any of `employment_status`, `employer_name`, `occupation`, `monthly_income`, `employment_notes` is non-empty. If labor data is started, `employment_status` must be non-empty at submit. Do not invent an enum of employment statuses in F5.

- [ ] **Step 6: Verify focal GREEN + full CI**

Run focal service test, then PHP 8.3/8.4/8.5 CI.

- [ ] **Step 7: Rolling checkpoint + commit**

```text
feat(candidates): add candidate core and secure tokens
```

Do not expose routes yet.

---

### F5.3 — Public Start + Continue Surface

**Files:**
- Create: `app/Candidates/routes.php`
- Create: `resources/views/public_apply_start.php`
- Create: `resources/views/public_candidate_continue.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Integration/Candidates/PublicCandidateApplyTest.php`
- Update: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Routes produced:**

```text
GET  /apply/{publicKey}
POST /apply/{publicKey}
GET  /apply/continue/{token}
POST /apply/continue/{token}
```

No catalog CTA yet; direct route contract is proven first.

- [ ] **Step 1: Write start/continue RED**

Cover:

```text
GET apply only for currently publicable unit
POST apply requires CSRF, document identity, full_name, phone, valid email, consent
new identity -> one continuation URL with raw token; never candidate ID
duplicate identity -> no token and no candidate-specific data; generic response per F5 decision
invalid/expired token -> safe 404/generic failure
valid token -> only that candidate's editable data
no token hash/storage path/internal candidate ID in public HTML
/sub basePath preserved in actions/links/redirects
```

- [ ] **Step 2: Write basic throttle RED**

Implement only a V1 session-local abuse brake:

```text
start bucket: max 5 POST attempts per 10-minute session window
continue/submit mutation bucket: max 10 POST attempts per 10-minute session window
over limit: HTTP 429, generic body, zero DB mutation
```

This is not strong identity-existence hiding and not F22 hardening.

- [ ] **Step 3: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/PublicCandidateApplyTest.php --testdox
```

- [ ] **Step 4: Register CandidateService + one F5 route file**

Modify `bootstrap/app.php` to instantiate `CandidateService($pdo)` and register only `app/Candidates/routes.php`. Do not instantiate `CandidateDocumentService` until F5.5.

- [ ] **Step 5: Implement SSR public routes/views + local throttle helper**

Use existing `View::renderPublic`, global CSRF and base path. No second public route file or middleware stack.

- [ ] **Step 6: Verify focal + F4 detail regression + full CI**

```bash
vendor/bin/phpunit tests/Integration/Candidates/PublicCandidateApplyTest.php tests/Integration/Properties/PublicCatalogDetailTest.php --testdox
```

Then PHP 8.3/8.4/8.5 CI.

- [ ] **Step 7: Rolling checkpoint + commit**

```text
feat(candidates): add public application continuation flow
```

---

### F5.4 — Profile Completion + References + Controlled Unit Change

**Files:**
- Modify: `app/Candidates/CandidateService.php`
- Modify: `app/Candidates/routes.php`
- Modify: `resources/views/public_candidate_continue.php`
- Create: `tests/Integration/Candidates/CandidateReferencesTest.php`
- Extend: `tests/Integration/Candidates/CandidateServiceTest.php`
- Update: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Routes produced:**

```text
POST /apply/continue/{token}/references
POST /apply/continue/{token}/unit
```

- [ ] **Step 1: Write profile/reference RED**

Pin:

```text
monthly_income >= 0 when present
occupants_count >= 0 when present
pets_declared strict boolean
labor-started rule from F5.2
zero-or-more references; no invented minimum
reference requires name + phone; optional type/relationship/email
reference set is replaced transactionally
public form uses no reference IDs
successful partial save promotes new -> incomplete only
verification/pending_screening/withdrawn cannot edit publicly
```

- [ ] **Step 2: Write unit-change RED**

Allowed only when all are true:

```text
status new|incomplete
submitted_at IS NULL
candidate has zero candidate_documents
new target publicKey is currently publicable
```

The same candidate row changes `unit_id`; no second candidate row/history table.

- [ ] **Step 3: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidateReferencesTest.php tests/Integration/Candidates/CandidateServiceTest.php --testdox
```

- [ ] **Step 4: Implement minimum service/routes/view changes**

Escape all candidate/reference values. Do not create public CRUD-by-reference-ID.

- [ ] **Step 5: Focal GREEN + full CI + checkpoint + commit**

Commit:

```text
feat(candidates): add profile references and unit change rules
```

---

### F5.5 — Structured Requirements + Private Candidate Documents

**Files:**
- Create: `app/Candidates/CandidateDocumentService.php`
- Modify: `app/Candidates/CandidateService.php`
- Modify: `app/Candidates/routes.php`
- Modify: `bootstrap/app.php`
- Modify: `resources/views/unit_detail.php`
- Modify: `resources/views/public_candidate_continue.php`
- Create: `tests/Integration/Candidates/CandidateDocumentsTest.php`
- Extend: `tests/Integration/Candidates/CandidateServiceTest.php`
- Update: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Interfaces:**

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

**Routes added:**

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
code unique within unit and immutable after create
manager edits label/description/is_required/is_active/sort_order
reorder requires exact owned set; duplicate/foreign IDs rejected
requirement with candidate documents is never physically deleted in F5; inactivate instead
activeOnly filters and preserves configured order
```

- [ ] **Step 2: Write upload/replacement RED**

Cover:

```text
real PDF/JPEG/PNG <=10 MiB accepted
SVG/PHP/fake MIME/empty/oversize rejected
stored filename generated; original name never becomes path
requirementCode belongs to candidate current unit and is active
one candidate+requirement row; upload replaces
failed validation/persistence preserves prior valid row/file
successful replacement confirms new row/file before deleting old file
foreign/missing candidate rejected
storage outside webroot with restrictive directory/file permissions
```

If deletion of an obsolete old file fails after a durable replacement, do not roll back to a broken DB state or introduce a compensator architecture; surface the filesystem failure for audit and keep the new valid document authoritative. This is P2 cleanup debt, not a reason for a new queue/table.

- [ ] **Step 3: Write submit-completeness RED**

`submitByToken()` requires all current `is_required=1 AND is_active=1` requirements for the candidate's current unit to have valid candidate documents. Optional/inactive requirements do not block. Labor-started rule must pass. Unit no longer publicable does not block an already-created candidate.

- [ ] **Step 4: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidateDocumentsTest.php tests/Integration/Candidates/CandidateServiceTest.php --testdox
```

- [ ] **Step 5: Implement CandidateDocumentService + bootstrap registration**

Add `candidate_document_directory` defaulting to `storage/candidate-documents`. Validate MIME by bytes with `finfo`. No `UnitMediaService` reuse.

- [ ] **Step 6: Wire requirement UI, upload and final submit**

Requirement management remains in unit context but mutations require `candidates.manage`. Public upload resolves candidate by token server-side and never emits internal IDs.

- [ ] **Step 7: Verify focal + SecurityBoundary + full CI**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidateDocumentsTest.php tests/Integration/Foundation/SecurityBoundaryTest.php --testdox
```

- [ ] **Step 8: Rolling checkpoint + commit**

```text
feat(candidates): add application requirements and private documents
```

---

### F5.6 — Administrative Candidates Module

**Files:**
- Modify: `app/Candidates/routes.php`
- Create: `resources/views/candidates.php`
- Create: `resources/views/candidate_detail.php`
- Modify: existing Apps launcher source only where needed
- Create: `tests/Integration/Candidates/CandidatesAdminRoutesTest.php`
- Update: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

**Routes produced:**

```text
GET  /admin/candidates
GET  /admin/candidates/{id}
POST /admin/candidates/{id}/status
POST /admin/candidates/{id}/token/regenerate
GET  /admin/candidates/{id}/documents/{documentId}
```

- [ ] **Step 1: Write admin authorization RED**

Cover:

```text
unauthenticated -> login
must_change_password -> account/password
candidates.view -> list/detail/private document read
candidates.manage -> status/token regenerate
view-only mutation -> 403
no candidate permission -> 403
owner bypass
launcher visible only to authorized user
SSR escapes candidate/reference/original filename
```

- [ ] **Step 2: Write private-document IDOR RED**

Document B requested under candidate A -> 404. Valid owned document -> persisted MIME, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`.

- [ ] **Step 3: Write token-regeneration RED**

New raw token/continuation URL appears only in immediate authorized response. DB contains hash only. Previous raw token fails. URL honors `base_url` and `basePath`.

- [ ] **Step 4: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidatesAdminRoutesTest.php --testdox
```

- [ ] **Step 5: Implement admin routes/views using existing guard pattern**

Admin status route may perform only F5 transitions. It must not simulate public completeness validation to force `new|incomplete -> verification`; that transition belongs to submit. Admin may move `verification -> pending_screening` or any non-withdrawn F5 state to `withdrawn` according to the canonical transition table.

- [ ] **Step 6: Focal auth/shell regression + full CI**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidatesAdminRoutesTest.php tests/Integration/Auth/AuthRoutesTest.php tests/Integration/UI/ShellRoutesTest.php --testdox
```

- [ ] **Step 7: Rolling checkpoint + commit**

```text
feat(candidates): add private candidate administration
```

---

### F5.7 — Catalog CTA + End-to-End Unit/Candidate Rules

**Files:**
- Modify: `resources/views/public_catalog_detail.php`
- Modify: `app/Properties/routes.php` only if view data requires a minimal contract change; prefer no service change
- Extend: F5 routes/views only for reproduced integration gaps
- Create: `tests/Integration/Candidates/CandidateCatalogCtaTest.php`
- Extend: `tests/Integration/Candidates/PublicCandidateApplyTest.php`
- Update: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md`

- [ ] **Step 1: Write CTA RED**

Cover:

```text
publicable detail contains Aplicar -> /apply/{publicKey}
/sub detail uses /sub/apply/{publicKey}
CTA contains no unit/candidate internal ID
non-publicable detail is 404 and has no CTA
F4 catalog list contract remains intact
```

- [ ] **Step 2: Write lifecycle integration RED**

Cover:

```text
candidate starts while unit publicable
unit later occupied/inactive/unpublished
new start rejected and CTA gone
existing candidate token still loads/uploads/submits
candidate with zero documents may change to another publicable unit while new|incomplete
candidate with any document cannot change unit
old-unit requirementCode after unit change rejected
application start/submit never changes units.status
```

- [ ] **Step 3: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Candidates/CandidateCatalogCtaTest.php tests/Integration/Candidates/PublicCandidateApplyTest.php --testdox
```

- [ ] **Step 4: Implement minimum integration**

No reservation flag, application counter, auto-`in_process`, catalog cache or extra application entity.

- [ ] **Step 5: Run complete F4+F5 integration suite + full CI**

```bash
vendor/bin/phpunit tests/Integration/Properties tests/Integration/Candidates --testdox
```

- [ ] **Step 6: Rolling checkpoint + commit**

```text
feat(candidates): connect catalog to candidate application
```

---

### F5.8 — Security + Visual QA + Zero-Noise Closure

**Files:**
- Create: `tests/Integration/Candidates/CandidatesSecurityRegressionTest.php`
- Modify: `tests/Unit/UI/AssetContractTest.php` only for reproduced visual contract
- Modify: `assets/app.css` only after RED/reproduction
- Modify: `README.md`
- Update SPEC/PLAN as-built only where a preliminary contract was refined
- Replace: `docs/checkpoints/F5_CURRENT_CHECKPOINT.md` with `docs/checkpoints/F5_CLOSED_2026-10-12.md` after final gate

- [ ] **Step 1: Complete security regression RED**

Pin:

```text
malformed token lengths fail closed
expired/rotated token cannot read/mutate
duplicate identity returns no candidate-specific data/token and is throttled
all F5 POST routes require existing CSRF
all F5 routes/redirects honor basePath
candidate-documents storage remains blocked from direct web access
cross-candidate document IDOR -> 404
fake MIME/path traversal stored_name rejected
public HTML emits no token hash/storage path/internal candidate/document IDs
throttle 429 causes no mutation
```

Do not test or claim strong hiding of duplicate-document existence without OOB verification; the decision document explicitly defines the F5 guarantee.

- [ ] **Step 2: Security GREEN cycle**

Fix only reproduced defects in existing F5 services/routes/views. No security framework, Redis, new table or worker.

- [ ] **Step 3: Real visual QA before CSS changes**

Viewports:

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
public candidate continue with long name/email/reference text
candidate documents/review
admin candidate list
admin candidate detail
catalog detail with Aplicar
```

Check horizontal overflow, focus visibility, touch targets, long filename/email wrapping, upload controls and bottom-nav overlap. Add CSS only for reproduced failures; reuse F3/F4 primitives first.

- [ ] **Step 4: Two Laws / architecture diff gate**

Prove exactly:

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
DB_SCHEMA_DELTA: +4 tables
PERMISSIONS_DELTA: +2
DOMAIN_SERVICES_DELTA: +2 max
ROUTE_FILES_F5: 1
COMPOSER_DELTA: 0
NPM_DELTA: 0
AUTH_DELTA: 0
NO lead/application/CRM parallel entity
NO notification engine
NO redundant F5 WIP checkpoints
```

- [ ] **Step 5: Final full CI on exact closing HEAD**

Require:

```text
composer validate --strict PASS
PHP 8.3 PASS
PHP 8.4 PASS
PHP 8.5 PASS
full PHPUnit 0 failures / 0 errors
```

Record exact CI run and test/assertion totals.

- [ ] **Step 6: Documentation convergence**

Update README to `F5 CLOSED / VERIFIED / NOT MERGED`. Fold the duplicate-identity/state refinement into F5 SPEC/PLAN as-built so the standalone decision no longer creates ambiguity; keep the decision file only if it remains useful historical rationale. Delete rolling checkpoint only after canonical `F5_CLOSED_2026-10-12.md` demonstrably subsumes it.

- [ ] **Step 7: Final PR review**

Review changed-file list, high-risk patches, comments/review threads, CI and mergeability. Set Draft PR Ready for review only when no blocker remains.

- [ ] **Step 8: Stop before merge**

Report F5 final HEAD, CI, tests, visual QA, Two Laws and PR state. Merge only after a new explicit user authorization.

---

# Execution Discipline

Every microphase follows:

```text
SCOPE
PRE-KISS
RED
VERIFY RED
MINIMAL GREEN
VERIFY GREEN
FULL CI PHP 8.3/8.4/8.5
CONVERGE
UPDATE ONE ROLLING CHECKPOINT
NEXT MICROPHASE
```

Do not open the next microphase on focal/local green alone. If CI exposes an infrastructure issue, diagnose it separately instead of patching product blindly.

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

F6 must extend the same candidate process from `pending_screening`; it must not copy a candidate into a second application identity.
