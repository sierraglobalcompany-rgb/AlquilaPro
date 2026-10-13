# F7 Contract Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task. Apply `superpowers:test-driven-development` before each production change. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement F7 as the minimum administrative contract engine that turns an existing candidate with insurer screening approved into a versioned draft, safe preview, immutable generated snapshot/PDF with SHA-256, and administrative finalization, without creating tenant, lease, public contract routes or automatic unit/candidate transitions.

**Architecture:** Add exactly three tables (`contract_templates`, `contract_template_versions`, `contracts`), one domain service (`ContractService`) and one concrete PDF class (`ContractPdfRenderer`). Templates remain plain UTF-8 text with allowlisted placeholders. `ContractService` reuses `CandidateService`, `ScreeningService` and `PropertyService`; it owns validation, lifecycle, snapshots and private storage. `ContractPdfRenderer` is stateless and concrete; no repository, interface, DTO, adapter, template engine or document subsystem is added. F8 remains the owner of tenant/lease/occupancy.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO, MariaDB/MySQL, SSR PHP views, existing CSS/JS, PHPUnit 11.5, Dompdf `^3.1.6`; no Node, Chromium, wkhtmltopdf, queue or external PDF binary.

**Spec:** `docs/specs/F7_CONTRACT_ENGINE_SPEC.md`

**Approved design/spec context:** F7 scope/design and the PDF spike were approved by the user. The specific F7 SPEC was approved through the project `continua` gate on 2026-10-12 (America/Bogota) before this plan was written. SPEC-head CI run `38341212910` is GREEN on PHP 8.3/8.4/8.5.

## Global Constraints

- F7 adds exactly **3 tables**, **2 domain classes**, **2 permissions**, **1 route file**, **1 direct runtime dependency**, **0 public routes**, **0 auth systems** and **0 lease/tenant tables**.
- Contract types F7 are exactly `urban_housing` and `independent_parking`.
- Contract statuses are exactly `draft`, `generated`, `finalized`.
- Template bodies are plain UTF-8 text; no raw user HTML/CSS/JS, WYSIWYG, Twig, Mustache or arbitrary expression language.
- Automatic placeholders are an explicit allowlist. Manual placeholders use only `custom.*` and must be declared by the template version.
- Contract unit comes only from `candidate.unit_id`; request-supplied unit IDs are never authority.
- New draft requires candidate `pending_screening`, screening `approved`, active property, compatible unit, unit `available|in_process`, active template and matching version.
- Existing pinned draft remains usable if its template is later deactivated; deactivation blocks new drafts only.
- Candidate `withdrawn` blocks create/generate/finalize.
- Generated contract freezes content/economics/dates/variables/annexes/snapshot/PDF/hash. Only `tasks_json` may still change before finalization.
- Finalized is terminal. F7 never changes `candidates.status` or `units.status`, never creates tenant/lease and never claims electronic/digital signature.
- `start_date`, `end_date`, `duration_text` are explicit administrator-confirmed values; F7 never infers a legal end date from “N months”.
- Dompdf remote resources stay disabled. Contract PDFs live outside webroot and are served only through authenticated routes.
- `privatePdf()` must verify stored-name safety, `%PDF-`, actual byte size and SHA-256 against DB before delivery.
- Existing global CSRF, session, forced-password guard, owner bypass and `basePath` are reused.
- No merge without project gate. `continua` authorizes only the next planned gate.
- Work in microphases F7.1–F7.6. Do not start a later microphase while the previous one has a known RED/blocker.
- Maintain at most one rolling `docs/checkpoints/F7_CURRENT_CHECKPOINT.md`; replace it at close with `F7_CLOSED_2026-10-12.md`, and only after merge add `F7_MERGED_2026-10-12.md`.

## Review Focus

1. **State changed between render and mutation:** candidate withdrawal, screening change, unit status change or property deactivation between GET and create/generate must be re-read by the service and reject without partial mutation.
2. **Template deactivated after draft:** new drafts fail, but a previously pinned draft can still preview/generate using its immutable version.
3. **Concurrency:** concurrent create must leave at most one active draft per candidate; concurrent finalize must leave at most one finalized contract. Use one candidate-row lock, not a new lock table.
4. **Template/XSS/JSON abuse:** unknown placeholders, undeclared/unused custom variables, HTML/script payloads, malformed task/annex JSON and forged IDs fail closed or render escaped.
5. **PDF/storage tampering:** remote URLs/path input never reach Dompdf/filesystem; partial files are cleaned on failure; altered size/hash/PDF signature makes `privatePdf()` fail closed.

---

# Locked File Map

## New production files

- `database/migrations/007_create_contract_engine.sql`
- `app/Contracts/ContractService.php`
- `app/Contracts/ContractPdfRenderer.php`
- `app/Contracts/routes.php`
- `resources/views/contracts.php`
- `resources/views/contract_detail.php`
- `resources/views/contract_templates.php`
- `resources/views/contract_template_detail.php`

## Existing production files expected to change

- `composer.json` / `composer.lock` — add Dompdf only.
- `app/Foundation/RuntimeRequirements.php` — require `dom` and `gd`.
- `.github/workflows/ci.yml` — request `dom`, `gd`, `fileinfo` in the PHP matrix.
- `app/Auth/Permissions.php` — add `contracts.view`, `contracts.manage`; no DB permission migration.
- `bootstrap/app.php` — create contract storage path/service and load one Contracts route file.
- `resources/views/layouts/app.php` — expose one Contracts app card only when `contracts.view`/owner.
- `app/Candidates/routes.php` — pass contract context only; no contractual lifecycle logic.
- `resources/views/candidate_detail.php` — render contextual contract link/summary only.
- `assets/app.css` — only after a reproduced visual RED proves existing primitives insufficient.
- `README.md` — only at documented closure/merge gates.
- `app/Foundation/Installer.php` — **not expected to change**; touch only if a failing test proves `RuntimeRequirements` + installer diagnostic is insufficient.

## New test files

- `tests/Integration/Contracts/ContractSchemaTest.php`
- `tests/Integration/Contracts/ContractTemplatesTest.php`
- `tests/Integration/Contracts/ContractDraftTest.php`
- `tests/Integration/Contracts/ContractAdminRoutesTest.php`
- `tests/Integration/Contracts/ContractPdfTest.php`
- `tests/Integration/Contracts/ContractSecurityRegressionTest.php`
- `tests/Unit/Contracts/ContractPdfRendererTest.php`

## Existing tests expected to change

- `tests/Unit/Foundation/RuntimeRequirementsTest.php`
- `tests/Integration/Foundation/MigrationRunnerTest.php`
- `tests/Integration/Foundation/AppBootstrapTest.php` — only diagnostic/bootstrap assertions needed by F7.
- `tests/Integration/Auth/UserServiceTest.php` — permission catalog/sanitize expectation.
- `tests/Integration/UI/ShellRoutesTest.php` — authorized Contracts launcher.
- existing fixture cleanup lists only if a reproduced failure requires them; `DatabaseIsolationExtension` already drops all tables after every test.

Existing F1–F6 tests remain regression authority.

---

# Final Interfaces Frozen by the Plan

`ContractPdfRenderer` remains concrete and stateless:

```php
final class ContractPdfRenderer
{
    public function render(string $html): string;
}
```

`ContractService` final public surface:

```php
final class ContractService
{
    public function __construct(
        PDO $pdo,
        CandidateService $candidates,
        ScreeningService $screening,
        PropertyService $properties,
        string $contractDirectory,
    );

    public function listTemplates(): array;
    public function findTemplate(int $templateId): ?array;
    public function createTemplate(int $createdByUserId, array $input): array;
    public function setTemplateActive(int $templateId, bool $active): array;
    public function listTemplateVersions(int $templateId): array;
    public function findTemplateVersion(int $versionId): ?array;
    public function createTemplateVersion(int $templateId, int $createdByUserId, array $input): array;

    public function listContracts(): array;
    public function listForCandidate(int $candidateId): array;
    public function canCreateDraftForCandidate(int $candidateId): bool;
    public function findContract(int $contractId): ?array;

    public function createDraft(
        int $candidateId,
        int $templateVersionId,
        int $createdByUserId,
        array $input,
        int $now,
    ): array;

    public function updateDraft(int $contractId, array $input, int $now): array;
    public function previewHtml(int $contractId): string;
    public function generate(int $contractId, int $now): array;
    public function updateTasks(int $contractId, array $tasks, int $now): array;
    public function finalize(int $contractId, int $finalizedByUserId, int $now): array;
    public function privatePdf(int $contractId): ?array;
}
```

`ContractService` instantiates the single concrete `ContractPdfRenderer` only inside `generate()`. This matches existing project KISS style (direct concrete collaborators where no abstraction benefit exists), keeps the constructor stable before F7.4, and avoids a nullable renderer/interface solely for tests.

`privatePdf()` returns only verified server-side metadata, logically:

```text
path
stored_name
size_bytes
sha256
```

No public URL/path is persisted.

---

### F7.1 — Runtime + Schema + Permissions

**Files:**
- Modify: `composer.json`, `composer.lock`
- Modify: `app/Foundation/RuntimeRequirements.php`
- Modify: `.github/workflows/ci.yml`
- Modify: `app/Auth/Permissions.php`
- Create: `database/migrations/007_create_contract_engine.sql`
- Create: `tests/Integration/Contracts/ContractSchemaTest.php`
- Modify: `tests/Unit/Foundation/RuntimeRequirementsTest.php`
- Modify: `tests/Integration/Foundation/MigrationRunnerTest.php`
- Modify: `tests/Integration/Foundation/AppBootstrapTest.php` only for installer diagnostic assertions
- Modify: `tests/Integration/Auth/UserServiceTest.php`
- Create/update: `docs/checkpoints/F7_CURRENT_CHECKPOINT.md`

**Interfaces consumed:** existing MigrationRunner, users/candidates/units FKs, `Permissions::sanitize/allows`, `RuntimeRequirements::evaluate()`.

- [ ] **Step 1: Write runtime/permission/schema RED**

Pin at minimum:

```text
RuntimeRequirements rejects missing dom
RuntimeRequirements rejects missing gd
RuntimeRequirements accepts pdo,pdo_mysql,mbstring,fileinfo,dom,gd
installer diagnostic lists dom/gd and disables install when runtime fails
Permissions sanitize accepts contracts.view/contracts.manage and rejects contract.* noise
migration count becomes 7 and 007 is applied once
exactly contract_templates + contract_template_versions + contracts are added by F7
no clauses/tasks/annex/lease/tenant tables
```

`ContractSchemaTest` pins every column/FK/index from the SPEC, including no DELETE CASCADE on candidate/unit/template-version evidence relations.

- [ ] **Step 2: Run RED**

```bash
vendor/bin/phpunit \
  tests/Unit/Foundation/RuntimeRequirementsTest.php \
  tests/Integration/Contracts/ContractSchemaTest.php \
  tests/Integration/Foundation/MigrationRunnerTest.php \
  tests/Integration/Auth/UserServiceTest.php --testdox
```

Expected: FAIL because Dompdf/runtime extensions, permissions and migration 007 are absent.

- [ ] **Step 3: Minimal dependency/runtime GREEN**

Use Composer, do not hand-edit lock contents:

```bash
composer require dompdf/dompdf:^3.1.6 --no-interaction --prefer-dist
```

Then add `dom`/`gd` to RuntimeRequirements and CI extension list. Do not add imagick/gmagick/zip/Node/binaries.

Do **not** modify `Installer.php` unless the diagnostic RED still fails after RuntimeRequirements changes; the current installer already evaluates RuntimeRequirements and fails closed.

- [ ] **Step 4: Minimal permission/schema GREEN**

Add exactly:

```text
contracts.view
contracts.manage
```

to `Permissions::CATALOG`.

Create migration 007 with the exact 3-table SPEC. No seeds, clauses table, workflow table, permission table, history table or lease table.

Update MigrationRunner exact count/list. Rely on `DatabaseIsolationExtension`; update other legacy fixture cleanup only when a concrete test fails.

- [ ] **Step 5: Verify focal GREEN**

Run the focal set above plus:

```bash
composer validate --strict
composer check-platform-reqs
```

- [ ] **Step 6: Full CI gate + Draft PR**

Open one Draft PR after first product GREEN. Require GitHub CI PHP 8.3/8.4/8.5 PASS before F7.2. Record exact run/job evidence; do not predeclare counts.

- [ ] **Step 7: Checkpoint + commit**

```text
feat(contracts): add F7 runtime and contract schema
```

Checkpoint records dependency delta `+1`, schema delta `+3`, permissions `+2`, route files/classes still `0` at this microphase, exact HEAD/CI and next RED.

---

### F7.2 — Templates + Immutable Versions

**Files:**
- Create: `app/Contracts/ContractService.php`
- Create: `app/Contracts/routes.php`
- Create: `resources/views/contract_templates.php`
- Create: `resources/views/contract_template_detail.php`
- Create: `tests/Integration/Contracts/ContractTemplatesTest.php`
- Create: `tests/Integration/Contracts/ContractAdminRoutesTest.php` (template route portion first)
- Modify: `bootstrap/app.php`
- Modify: `resources/views/layouts/app.php`
- Modify: `tests/Integration/UI/ShellRoutesTest.php`
- Update: rolling checkpoint

**Interfaces used:** `ContractService` template methods from frozen surface; auth/session/CSRF/basePath and owner bypass from existing app.

- [ ] **Step 1: Write template-domain RED**

Pin:

```text
create template trims name, requires unique 1..190 and exact document type allowlist
created_by_user_id must be valid positive user
set active/inactive preserves history
create version v1, then v2 server-side
concurrent/sequential version numbering cannot accept caller version number
body required UTF-8 <=250000 chars
unknown/invalid placeholder rejected
automatic placeholder outside allowlist rejected
custom placeholder must be declared
custom declaration unused in body rejected
custom keys unique, custom.* only, max 50, label/required validated
old version row stays byte-for-byte immutable after later versions
```

Version creation locks the parent template row inside one transaction before computing `MAX(version_number)+1`; no numbering table.

- [ ] **Step 2: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Contracts/ContractTemplatesTest.php --testdox
```

Expected: FAIL because `ContractService` does not exist.

- [ ] **Step 3: Implement minimal ContractService template slice**

Use direct PDO and private validation/helpers only. No repository, TemplateService, ClauseService, VariableResolver or template engine.

Placeholder parser is one simple regex extraction + allowlist check. `variables_json` is normalized/canonicalized by PHP before persistence.

- [ ] **Step 4: Write/admin route RED**

Pin:

```text
anonymous -> login redirect
forced-password -> password-change redirect
contracts.view -> can list/view templates, cannot mutate
contracts.manage -> create/toggle/version
owner -> bypass
invalid CSRF -> existing 403
/sub basePath actions and redirects correct
no raw template values unescaped in SSR
Contracts app card visible only to contracts.view/owner
```

- [ ] **Step 5: Implement one Contracts route file + two template views**

Routes in this slice:

```text
GET  /admin/contract-templates
POST /admin/contract-templates
GET  /admin/contract-templates/{id}
POST /admin/contract-templates/{id}
```

Detail POST uses allowlisted actions only (`toggle`, `new_version`). No route per micro-action.

Wire service in bootstrap with:

```php
$contracts = new ContractService($pdo, $candidates, $screening, $properties, $contractDirectory);
```

Create one contract directory setting/default only; no storage service class.

Add Contracts app card in existing launcher. No second navigation system.

- [ ] **Step 6: Verify GREEN + full CI**

```bash
vendor/bin/phpunit \
  tests/Integration/Contracts/ContractTemplatesTest.php \
  tests/Integration/Contracts/ContractAdminRoutesTest.php \
  tests/Integration/UI/ShellRoutesTest.php --testdox
```

Then full PHP 8.3/8.4/8.5 CI.

- [ ] **Step 7: Checkpoint + commit**

```text
feat(contracts): add versioned contract templates
```

---

### F7.3 — Draft Contract + Safe Preview

**Files:**
- Modify: `app/Contracts/ContractService.php`
- Modify: `app/Contracts/routes.php`
- Create: `resources/views/contracts.php`
- Create: `resources/views/contract_detail.php`
- Create: `tests/Integration/Contracts/ContractDraftTest.php`
- Extend: `tests/Integration/Contracts/ContractAdminRoutesTest.php`
- Update: rolling checkpoint

- [ ] **Step 1: Write eligibility/concurrency RED**

Pin:

```text
missing candidate rejects
candidate new|incomplete|verification|withdrawn rejects
pending_screening with screening missing/pending/in_progress/rejected rejects
screening approved allows only compatible unit/template type
unit occupied|inactive|maintenance rejects
inactive property rejects
forged/request unit id is ignored/rejected and candidate.unit_id wins
inactive template blocks new draft
version must belong to selected active template
max one draft per candidate even under serialized concurrent create path
existing finalized blocks new initial F7 contract
```

`createDraft()` must begin a transaction, lock the candidate row (`FOR UPDATE`), re-read all eligibility sources and check existing `draft|finalized` before insert. No partial-unique index/generation column/lock table.

- [ ] **Step 2: Write draft validation RED**

Pin:

```text
monthly_rent required >0 and DECIMAL(12,2)-safe
administration_fee null or >=0
start_date/end_date valid and end>=start
duration_text 1..120
manual required variables present, values <=4000
unknown manual keys rejected
tasks max30, label 1..190, strict bool
annexes max20, title 1..190, note plain text
unit price/admin fee may be UI defaults but contract values can differ
update draft never mutates unit.monthly_price or unit.administration_fee
only draft accepts content update
```

- [ ] **Step 3: Write preview/XSS RED**

Pin:

```text
preview draft does not change DB status or create PDF
preview contains BORRADOR
same stored draft/template values resolve placeholders
all candidate/property/unit/custom/task/annex payloads render escaped
no raw script/html from template or values
start/end/duration use explicit saved values, no inferred date
```

Resolution implementation remains private inside `ContractService`:

1. validate/load exact immutable version;
2. build automatic value map from existing services/DB state;
3. validate manual values;
4. escape template and replacement values;
5. replace only known tokens;
6. convert line breaks safely;
7. append annex text safely.

No `eval`, reflection or dynamic SQL.

- [ ] **Step 4: Run RED**

```bash
vendor/bin/phpunit tests/Integration/Contracts/ContractDraftTest.php --testdox
```

- [ ] **Step 5: Implement draft/list/detail/preview GREEN**

Add service methods:

```text
listContracts
listForCandidate
canCreateDraftForCandidate
findContract
createDraft
updateDraft
previewHtml
```

Routes enabled in this slice:

```text
GET  /admin/contracts
POST /admin/contracts
GET  /admin/contracts/{id}
POST /admin/contracts/{id} action=save
```

POST create accepts candidate/template version + contract fields, but never unit ID as authority.

Views use SSR existing primitives. No JS required.

- [ ] **Step 6: Verify GREEN + full CI**

```bash
vendor/bin/phpunit \
  tests/Integration/Contracts/ContractDraftTest.php \
  tests/Integration/Contracts/ContractAdminRoutesTest.php --testdox
```

Then full matrix.

- [ ] **Step 7: Checkpoint + commit**

```text
feat(contracts): add contract drafts and safe preview
```

---

### F7.4 — Immutable Snapshot + PDF + SHA-256

**Files:**
- Create: `app/Contracts/ContractPdfRenderer.php`
- Modify: `app/Contracts/ContractService.php`
- Modify: `app/Contracts/routes.php`
- Create: `tests/Unit/Contracts/ContractPdfRendererTest.php`
- Create: `tests/Integration/Contracts/ContractPdfTest.php`
- Extend: `tests/Integration/Contracts/ContractAdminRoutesTest.php`
- Update: rolling checkpoint

- [ ] **Step 1: Write renderer RED**

Pin real Dompdf behavior:

```text
render returns bytes beginning %PDF-
UTF-8 Spanish survives render contract
multi-page content works
fixed header/footer/page numbering configured
remote fetching disabled
no request-configurable Dompdf options
no external binary invocation
```

The unit test may inspect configuration/behavior but must not introduce a renderer interface/mock solely for testing.

- [ ] **Step 2: Implement minimal ContractPdfRenderer**

Concrete class only. Fixed paper/font/CSS/header/footer/page numbering. `isRemoteEnabled=false`. It receives trusted internally-built HTML and returns bytes; it never writes storage or touches DB.

- [ ] **Step 3: Write generate/storage RED**

Pin:

```text
generate requires draft
generate re-reads candidate/screening/unit/property at action time
withdrawn/screening no longer approved/unit no longer eligible/property inactive -> reject and remain draft
pinned draft still generates when template later inactive
generated stores resolved_variables_json + snapshot_html + random pdf_stored_name + exact sha256 + exact size + generated_at
snapshot has no BORRADOR watermark
generated row immutable except tasks
second generate same row rejected
PDF max 10 MiB
render/write/DB failure leaves draft and cleans temp/final partial file
```

- [ ] **Step 4: Implement `generate()` atomically**

Algorithm:

1. load draft and revalidate mutable external sources (not template active flag for an already pinned draft);
2. resolve variables + build immutable internal HTML;
3. render PDF and validate `%PDF-`/size;
4. hash exact bytes;
5. ensure private directory, create server-side random temp/final names;
6. write temp, chmod 0600 where supported, verify bytes, rename;
7. within transaction recheck row still draft, update snapshot/hash/size/generated_at/status;
8. commit; on any exception rollback and unlink temp/final file.

No request controls filename/path.

- [ ] **Step 5: Write private download/tamper RED**

`privatePdf()` and `GET /admin/contracts/{id}/pdf` pin:

```text
only generated/finalized
contracts.view or owner required
stored_name must match server-generated filename pattern
file exists outside webroot
actual file begins %PDF-
actual size equals DB
actual sha256 equals DB
altered bytes/size/hash -> 404/fail closed
Content-Type application/pdf
Content-Disposition attachment safe server-side name
Cache-Control private,no-store
X-Content-Type-Options nosniff
basePath works
no filesystem path in response/error
```

- [ ] **Step 6: Verify GREEN + full CI**

```bash
vendor/bin/phpunit \
  tests/Unit/Contracts/ContractPdfRendererTest.php \
  tests/Integration/Contracts/ContractPdfTest.php \
  tests/Integration/Contracts/ContractAdminRoutesTest.php --testdox
```

Then full PHP matrix and Composer strict/platform checks.

- [ ] **Step 7: Checkpoint + commit**

```text
feat(contracts): generate immutable private contract PDFs
```

---

### F7.5 — Finalization + Candidate Context

**Files:**
- Modify: `app/Contracts/ContractService.php`
- Modify: `app/Contracts/routes.php`
- Modify: `app/Candidates/routes.php`
- Modify: `resources/views/candidate_detail.php`
- Extend: `resources/views/contract_detail.php`
- Extend: `tests/Integration/Contracts/ContractDraftTest.php`
- Extend: `tests/Integration/Contracts/ContractAdminRoutesTest.php`
- Modify: candidate route test only as needed for new context
- Update: rolling checkpoint

- [ ] **Step 1: Write checklist/finalization RED**

Pin:

```text
update_tasks accepted for draft/generated only
update_tasks cannot alter snapshot/pdf/hash/economics/annexes
finalize requires generated
finalize sets status, finalized_by_user_id, finalized_at
finalized terminal including tasks/content
double finalized for same candidate blocked under candidate-row transaction
candidate withdrawn immediately before finalize rejects
finalize does not create tenant/lease/user
does not mutate candidate.status or unit.status
```

`finalize()` locks candidate row, reloads contract and checks no other finalized contract exists before mutation.

- [ ] **Step 2: Write candidate-context RED**

Pin:

```text
candidate viewer without contracts.view sees no contract private section/links
contracts.view or owner sees existing contracts
approved eligible candidate with no finalized + usable compatible template exposes a real create link
existing draft/generated exposes real contract detail link
finalized shows link/status but no create-new initial contract CTA
candidate route delegates to ContractService; no direct contract SQL/status logic
/sub basePath links correct
```

Use `ContractService::listForCandidate()` and `canCreateDraftForCandidate()` only. Candidate routes must not reproduce insurer/unit/template eligibility rules.

- [ ] **Step 3: Implement GREEN**

Enable POST detail actions:

```text
update_tasks
finalize
```

Pass only contract context and permission booleans into candidate detail. No mutation POST is added to Candidate routes.

- [ ] **Step 4: Verify focal GREEN + full CI**

```bash
vendor/bin/phpunit \
  tests/Integration/Contracts/ContractDraftTest.php \
  tests/Integration/Contracts/ContractAdminRoutesTest.php \
  tests/Integration/Candidates/CandidatesAdminRoutesTest.php --testdox
```

Then full matrix.

- [ ] **Step 5: Checkpoint + commit**

```text
feat(contracts): finalize contracts and link candidate context
```

---

### F7.6 — Security + Visual QA + Cero Ruido + Closure

**Files:**
- Create: `tests/Integration/Contracts/ContractSecurityRegressionTest.php`
- Modify: `assets/app.css` only after reproduced visual failure
- Modify: `README.md`
- Replace/delete: `docs/checkpoints/F7_CURRENT_CHECKPOINT.md`
- Create: `docs/checkpoints/F7_CLOSED_2026-10-12.md`

- [ ] **Step 1: Write security/architecture closure RED**

Cover at minimum:

```text
no public contract routes
no second auth/session/CSRF
no lease/tenant tables/classes
no clauses/tasks/annex tables
no template engine/WYSIWYG/HTML input
no remote Dompdf resources or curl/Guzzle PDF fetching
forged candidate/unit/template relations fail closed
XSS payloads never become executable HTML
JSON limits/types fail closed
path traversal and arbitrary stored filename fail closed
PDF tamper detected by signature/size/hash
partial file cleanup on generation error
finalized immutability
candidate/screening/unit/property race revalidation
```

Also assert architecture budget:

```text
3 tables
2 domain classes
2 permissions
1 Contracts route file
1 direct runtime dependency
0 public routes
0 lease/tenant
```

- [ ] **Step 2: Run security RED and apply only reproduced fixes**

```bash
vendor/bin/phpunit tests/Integration/Contracts/ContractSecurityRegressionTest.php --testdox
```

No speculative hardening unrelated to a proven F7 contract.

- [ ] **Step 3: Visual QA**

Review only changed surfaces:

```text
contracts list empty/populated
contract template list/detail with long plain text
contract draft/detail preview with long candidate/custom text
contract generated/finalized metadata + PDF action
candidate detail contract section
Apps launcher contract card
mobile and desktop widths already covered by F3 primitives
```

If overflow/readability fails, add a reproducible UI contract first and apply the smallest CSS delta. Otherwise CSS delta = 0.

- [ ] **Step 4: Cero Ruido audit**

Prove there is no:

```text
ContractRepository
TemplateService
VariableResolver
StorageService
PDF interface/adapter/factory
clauses/tasks/annex tables
lease/tenant/user creation
public contract route
parallel auth
extra dependency beyond Dompdf/transitives
Node/npm/external binary
raw user HTML/CSS/JS
```

Remove dead WIP only after reference search + tests.

- [ ] **Step 5: Full final verification**

Run:

```bash
composer validate --strict
composer check-platform-reqs
vendor/bin/phpunit --testdox
```

Require GitHub CI on exact final product HEAD:

```text
PHP 8.3 PASS
PHP 8.4 PASS
PHP 8.5 PASS
0 failures
0 errors
```

Record exact test/assertion counts from CI; never infer them.

- [ ] **Step 6: Two Laws Gate**

Record:

```text
LEY_1_KISS: PASS|FAIL
LEY_2_CERO_RUIDO: PASS|FAIL
ADDED:
REMOVED:
MERGED_OR_CONVERGED:
DEPENDENCIES_DELTA:
DB_SCHEMA_DELTA:
PUBLIC_ROUTES_DELTA:
PROOF:
```

Any FAIL blocks closure/merge.

- [ ] **Step 7: Canonical closure checkpoint**

Create `docs/checkpoints/F7_CLOSED_2026-10-12.md` with exact:

```text
base SHA
final product HEAD
CI run/jobs/test counts
F7.1-F7.6 status
Dompdf/runtime evidence
schema/classes/permissions/routes/dependencies deltas
PDF hash/tamper/security evidence
visual QA
Two Laws gate
PR status
MERGE_ALLOWED state
next F8 gate
```

Delete rolling checkpoint only after unique evidence is folded into closure. Update README to `F7 CLOSED / VERIFIED / NOT MERGED`.

- [ ] **Step 8: Final PR review**

Review full diff, base/head, CI, comments/threads and Cero Ruido. Mark Ready for Review only when clean. Do not merge until the next explicit continuation gate.

- [ ] **Step 9: Closure commit**

```text
docs(f7): record verified contract engine closure
```

---

# Execution Order / Blocking

```text
F7.1 GREEN + CI
  -> F7.2 RED/GREEN + CI
  -> F7.3 RED/GREEN + CI
  -> F7.4 RED/GREEN + CI
  -> F7.5 RED/GREEN + CI
  -> F7.6 security/visual/zero-noise + exact final CI
  -> F7 CLOSED / VERIFIED / NOT MERGED
  -> final PR review
  -> merge only on project continuation authorization
  -> F8 scope only after F7 merge is verified
```

No task may skip RED verification. A later microphase may not be implemented while the previous microphase has a known failure or unresolved blocker.

---

# Plan Self-Review Result

- **Spec coverage:** every SPEC area is owned by F7.1–F7.6: runtime/schema/permissions, templates/versioning, eligibility/draft/preview, snapshot/PDF/hash/private storage, finalization/candidate context, security/visual/Two Laws closure.
- **Step scan:** each microphase has explicit files, RED behavior, command, minimal GREEN, verification and commit/checkpoint gate.
- **Type/interface consistency:** routes and later tasks consume only the frozen `ContractService` public methods above. Candidate routes receive contract context through the service; no second contract SQL path is introduced.
- **Review Focus coverage:** mutable-state races (F7.3/F7.4/F7.5), template deactivation (F7.3/F7.4), concurrency (F7.3/F7.5), placeholder/XSS/JSON abuse (F7.2/F7.3/F7.6), PDF tamper/storage failure (F7.4/F7.6) each has an owning test gate.
- **Runtime consistency:** Dompdf is the only new direct package. `RuntimeRequirements` owns dom/gd checks; `Installer.php` remains unchanged unless an actual RED proves it necessary.
- **Fixture consistency:** project `DatabaseIsolationExtension` already drops every DB table after each test; no broad cleanup-list edits are planned. MigrationRunner exact migration contract is updated, and other cleanup code changes require a reproduced failure.
- **Proportion/KISS:** no repository, service split, template engine, storage layer, task/annex tables, history table, partial-index workaround, public portal or F8 entity is introduced.
- **Legal-date ambiguity:** plan preserves explicit `start_date`, `end_date`, `duration_text` and contains no automatic legal-duration inference.

---

# Approval Gate

```text
F6: CLOSED / VERIFIED / MERGED
F7_SCOPE: APPROVED
F7_PRE_KISS: APPROVED
F7_DESIGN_IN_CHAT: APPROVED
F7_PDF_SPIKE: PASS
F7_SPEC_APPROVED_BY_USER: YES
F7_PLAN_WRITTEN: YES
F7_PLAN_APPROVED_BY_USER: NO
F7_IMPLEMENTATION_ALLOWED: NO
NEXT: USER PLAN APPROVAL -> F7.1 RED
```

Execution method: **Native/ChatGPT-first**. Codex is optional only if a concrete implementation or review need demonstrates value; it is not a default dependency.