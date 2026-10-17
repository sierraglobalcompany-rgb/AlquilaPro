# Candidates Human-First Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convertir el módulo administrativo `Candidatos` en una experiencia moderna, humana y modular, sin inventar capacidades y sin alterar los truth owners existentes.

**Architecture:** Mantener PHP SSR + Slim y el endpoint existente `GET /admin/candidates/{id}`. La ruta resolverá una `section` permitida y la vista `candidate_detail.php` renderizará un shell único más exactamente un cuerpo activo; las secciones aún no rediseñadas conservarán su funcionalidad actual hasta que su microbloque las sustituya. No SPA, no nuevo service de presentación, no DB/migrations, no API paralela y no dependencia nueva.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PHP SSR, PHPUnit 11.5, HTML nativo, CSS existente en `assets/app.css`.

**Spec:** `docs/superpowers/specs/2026-10-17-candidates-human-first-redesign.md`

**Visual baseline:** `docs/design/CANDIDATES_VISUAL_BASELINE_2026-10-17.md` + pack persistente `/AlquilaPro/Design/Candidatos/2026-10-17/`.

**Owner approval:** la spec fue aprobada por el owner el 2026-10-17 mediante `continua`. Este documento es el gate posterior de planificación; escribir código de producto requiere aprobación de este plan.

## Global Constraints

- **KISS:** A → B; reutilizar antes de agregar; sin arquitectura paralela.
- **Cero Ruido:** `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`.
- **Anti-parche:** `REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → CAUSAL RED → MINIMAL GREEN → RETIRAR SUPERSEDED → REGRESSION`.
- **Human-First:** persona > modelo de datos; no Infinite Excel; no single-record-as-table dominante; no every-field-visible.
- Mockup = autoridad visual, **no autoridad funcional**.
- No implementar scores, ratings, riesgo calculado, historial de llamadas, historial laboral múltiple, firmas, mensajería, notas generales, editar candidato ni estados no soportados.
- Mantener `CandidateService`, `CandidateDocumentService`, `ScreeningService`, `ContractService`, `Permissions`, `HumanLabels` y `View` como owners existentes.
- No crear `CandidateDashboardService`, presenter service, frontend framework, SPA, API nueva, tabla nueva ni migration.
- Navegación de sección básica debe funcionar sin JavaScript.
- Roles/permisos deben seguir siendo autoridad del servidor; esconder UI nunca sustituye autorización.
- PHP soportado: `>=8.3 <8.6`; CI obligatorio en PHP 8.3 / 8.4 / 8.5.
- Viewports Browser QA: `1440×900`, `768×1024`, `375×812`.
- No merge de un microbloque sin focal GREEN + full CI GREEN + Browser QA real + Human-First GREEN + exact SHA.
- No abrir el microbloque siguiente hasta cerrar el anterior.
- PR #43 queda `DRAFT / DO NOT MERGE`; no cherry-pick de su solución `<details>`.
- R3 `FROZEN`; R4 `NOT_STARTED`; deployment `NOT_AUTHORIZED`.

## Review Focus

1. **Acceso directo a sección no autorizada:** `?section=manage` sin `candidates.manage` y `?section=contracts` sin `contracts.view` deben caer a `overview` sin filtrar datos ni controles.
2. **Section inválida o manipulada:** un valor desconocido, array o vacío debe resolver de forma determinista a `overview`, sin warning PHP ni 500.
3. **Datos opcionales ausentes o largos:** null/blank no se convierte en hechos falsos; textos largos y escapados deben envolver sin overflow ni XSS.
4. **Base path / URLs compartibles:** con `/sub`, todos los links de sección deben preservar `/sub/admin/candidates/{id}?section=...` y back/forward debe funcionar.
5. **Permisos + capacidades dinámicas:** `contracts` y `manage` sólo existen cuando corresponden; `Crear contrato` sólo aparece con `can_create=true`; formularios de screening sólo con reglas reales.

Cada punto anterior queda fijado por tests en el task que posee ese comportamiento.

---

## File Structure Locked by This Plan

### Owners que se modifican

- `app/Candidates/routes.php`
  - única responsabilidad nueva: resolver `section` solicitada contra las secciones permitidas y pasar `candidateSections` + `activeSection` a la vista.
  - no mover aquí reglas de dominio.

- `resources/views/candidate_detail.php`
  - permanece como owner SSR del detalle administrativo.
  - renderiza shell, navegación contextual y exactamente una sección activa.
  - durante la transición conserva el markup funcional existente de secciones aún no rediseñadas, condicionado por `activeSection`.
  - no crear motor de componentes ni capa de presentación nueva.

- `app/UI/HumanLabels.php`
  - sólo etiquetas/descripciones humanas verificadas.
  - CAND-01 añade descripción humana del estado del candidato.
  - CAND-07 puede añadir label humano de estados contractuales reales `draft`, `generated`, `finalized`.

- `assets/app.css`
  - único stylesheet.
  - nuevas reglas prefijadas `.candidate-*`, usando tokens actuales.
  - no `!important`, no color literal duplicado si existe token apropiado.

### Tests

- Create: `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`
  - render directo para contrato de presentación, copy, ausencia de inventos y una sección activa.

- Modify: `tests/Integration/Candidates/CandidatesAdminRoutesTest.php`
  - permisos, basePath, query `section`, fallback y seguridad.

- Reusar sin reescribir:
  - `tests/Integration/Candidates/ScreeningAdminRoutesTest.php`
  - `tests/Integration/Candidates/CandidateDocumentsRoutesTest.php`
  - `tests/Integration/Candidates/CandidatesSecurityRegressionTest.php`
  - `tests/Integration/Contracts/ContractFinalizationRoutesTest.php`
  - `tests/Integration/Contracts/ContractAdminRoutesTest.php`
  - `tests/Integration/Candidates/CandidateCatalogCtaTest.php`

### No crear en esta fase

- `app/Candidates/*Presenter*`
- `app/Candidates/*Dashboard*`
- JS específico de candidato
- nuevas rutas por sección
- nuevas tablas/migrations
- ocho archivos de controller
- API JSON de candidato

---

# Execution Model

Cada microbloque se ejecuta en **rama y PR propios**, siempre desde `main` ya actualizado por el microbloque anterior.

Formato de ramas recomendado:

```text
impl/cand-01-shell-overview-20261017
impl/cand-02-work-20261017
impl/cand-03-household-20261017
impl/cand-04-references-20261017
impl/cand-05-documents-20261017
impl/cand-06-screening-20261017
impl/cand-07-contracts-20261017
impl/cand-08-manage-20261017
impl/cand-09-list-20261017
```

En cada bloque:

```text
PRECHECK → RED → verify RED → RED commit
→ minimal GREEN → focal GREEN → regression → GREEN commit
→ PR → CI 8.3/8.4/8.5
→ Browser QA → Human-First audit → checkpoint
→ owner gate → merge
```

No acumular varios microbloques sin merge intermedio.

---

### Task 1 — CAND-01: Shell + navegación + Solicitud

**Files:**
- Create: `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`
- Modify: `tests/Integration/Candidates/CandidatesAdminRoutesTest.php`
- Modify: `app/Candidates/routes.php`
- Modify: `resources/views/candidate_detail.php`
- Modify: `app/UI/HumanLabels.php`
- Modify: `assets/app.css`

**Interfaces:**
- Consumes: `CandidateService::findCandidate()`, `CandidateDocumentService::listDocumentsForCandidate()`, `ScreeningService::findForCandidate()`, `ContractService::listForCandidate()`, `ContractService::canCreateDraftForCandidate()`, `Permissions::allows()`.
- Produces: route variables `candidateSections: list<array{key:string,label:string}>` and `activeSection: string`; presentation method `HumanLabels::candidateStatusDescription(string $status): string`.
- Section keys fixed: `overview`, `work`, `household`, `references`, `documents`, `screening`, `contracts`, `manage`.

- [ ] **Step 1: Precheck exact base and superseded work**

Confirm `main` contains the approved design docs after PR #44 is merged, PR #43 remains unmerged, and no `<details data-candidate-section>` change from #43 entered `main`.

Expected: clean branch from current `main`; no product drift.

- [ ] **Step 2: Write RED presentation tests**

In `HumanFirstCandidateDetailViewTest.php`, create fixtures that render `candidate_detail.php` and assert:

```text
- shell contains candidate name, human candidate status, property + unit once
- shell does NOT contain phone/email/document as global-header facts
- overview renders `data-candidate-section-body="overview"`
- only one `data-candidate-section-body` exists per render
- nav has `aria-label="Secciones del candidato"`
- active section link has `aria-current="page"`
- overview includes document, phone, email and real dates when present
- strings `Editar candidato`, `Enviar mensaje`, `80%`, `82 / 100`, `Perfil laboral sólido` are absent
- unknown optional values produce human empty/omitted presentation, not fabricated values
```

- [ ] **Step 3: Write RED route/permission tests**

Extend `CandidatesAdminRoutesTest.php` with cases for:

```text
GET /admin/candidates/{id}                     -> overview
GET /admin/candidates/{id}?section=work        -> work only
GET /admin/candidates/{id}?section=unknown     -> overview
GET /sub/admin/candidates/{id}?section=work    -> links preserve /sub
viewer without contracts.view + ?section=contracts -> overview; Contracts nav absent
viewer without candidates.manage + ?section=manage -> overview; Administrar nav absent
manager with candidates.manage -> Administrar nav visible
user with contracts.view -> Contratos nav visible
```

Also test query tampering with array-style `section[]=work` resolves safely to overview.

- [ ] **Step 4: Run RED and prove causality**

Run:

```bash
vendor/bin/phpunit tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php
vendor/bin/phpunit tests/Integration/Candidates/CandidatesAdminRoutesTest.php
```

Expected: new Human-First assertions FAIL because section resolution/shell do not exist; unrelated security/auth assertions remain healthy.

- [ ] **Step 5: Commit RED only**

```bash
git add tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php tests/Integration/Candidates/CandidatesAdminRoutesTest.php
git commit -m "test(candidates): define Human-First detail shell"
```

- [ ] **Step 6: Add human candidate status description in the existing label owner**

Implement exactly:

```php
HumanLabels::candidateStatusDescription(string $status): string
```

Required copy:

```text
new               -> La solicitud fue iniciada y aún puede completarse.
incomplete        -> La solicitud tiene información pendiente por completar.
verification      -> La solicitud fue enviada y está en verificación.
pending_screening -> La solicitud está pendiente del estudio de aseguradora.
withdrawn         -> La solicitud fue retirada del proceso.
default           -> Consulta el estado actual de la solicitud.
```

Do not create another label class.

- [ ] **Step 7: Resolve allowed sections in the existing GET route**

In `app/Candidates/routes.php` after resolving permissions/data:

```text
base sections = overview, work, household, references, documents, screening
+ contracts only when contractContext !== null
+ manage only when canManageCandidates === true
```

Read `section` from query params. Only a scalar string matching an available key becomes active; everything else falls back to `overview`.

Pass `candidateSections` and `activeSection` to `renderApp('candidate_detail', ...)`.

Do not create routes like `/work`, `/references`, etc.

- [ ] **Step 8: Implement the shell and isolate every existing workflow behind one active section**

In `candidate_detail.php`:

```text
candidate header:
  back link
  initials derived from first two nonblank name parts
  full_name
  HumanLabels::candidateStatus()
  property_name + unit_code/unit_id

navigation desktop:
  <nav class="candidate-tabs" aria-label="Secciones del candidato">
  links to ?section=<key>
  active link aria-current="page"

navigation mobile:
  native <details class="candidate-section-menu">
  summary identifies active section
  same real links; no JS requirement

body:
  exactly one section with data-candidate-section-body=<active key>
```

Move **no domain behavior**. Existing work/household/references/documents/screening/contracts/manage markup remains functionally intact but renders only when its key is active. This is the compatibility bridge that allows incremental redesign without removing functions.

Overview is the only body visually redesigned in this task.

- [ ] **Step 9: Build Overview from real facts only**

Use at most four meaningful surfaces:

```text
Resumen de la solicitud
Estado actual
Unidad solicitada
Fechas de la solicitud
```

Rules:
- identity/contact grouped, not one KPI per field;
- state includes `candidateStatus()` + `candidateStatusDescription()`;
- unit only property + unit identifier available today;
- dates shown in `d/m/Y` or `d/m/Y H:i` consistently, not raw SQL timestamps;
- no progress percentage;
- no invented next step;
- no address of property unless route later provides it factually.

- [ ] **Step 10: Add minimal `.candidate-*` CSS**

Reuse existing tokens: `--color-background`, `--color-surface`, `--color-text`, `--color-muted`, `--color-border`, `--brand-primary`, `--radius-card`, `--radius-control`, `--shadow-soft`.

Required primitives:

```text
.candidate-shell
.candidate-header
.candidate-avatar
.candidate-header__identity
.candidate-status
.candidate-tabs
.candidate-section-menu
.candidate-section
.candidate-grid
.candidate-card
.candidate-facts
.candidate-fact
.candidate-dates
```

Behavior:
- mobile default: one column, `.candidate-tabs` hidden, mobile details visible;
- `>=768px`: 1–2 column content where useful;
- `>=1200px`: tabs visible, mobile section menu hidden;
- no page horizontal overflow;
- all interactive targets retain >=48px existing convention;
- no `!important`.

- [ ] **Step 11: Focal GREEN**

Run:

```bash
vendor/bin/phpunit tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php
vendor/bin/phpunit tests/Integration/Candidates/CandidatesAdminRoutesTest.php
vendor/bin/phpunit tests/Integration/Candidates/CandidatesSecurityRegressionTest.php
vendor/bin/phpunit tests/Integration/Contracts/ContractFinalizationRoutesTest.php
```

Expected: PASS.

- [ ] **Step 12: Full local regression**

Run:

```bash
composer test
```

Expected: no product failures. Environmental failures must be classified, not patched into product.

- [ ] **Step 13: Commit minimal GREEN**

```bash
git add app/Candidates/routes.php resources/views/candidate_detail.php app/UI/HumanLabels.php assets/app.css tests/Integration/Candidates
git commit -m "feat(candidates): add Human-First detail shell"
```

- [ ] **Step 14: Open PR and require exact-head CI**

CI must pass PHP 8.3 / 8.4 / 8.5 on the exact GREEN SHA.

- [ ] **Step 15: Browser QA CAND-01**

Use real browser at 1440×900, 768×1024, 375×812. Verify:

```text
- overview understandable in <10 seconds
- tabs/menu navigate using real URLs and browser back/forward
- each legacy section still opens and its existing actions remain usable
- viewer-only role cannot discover manage/contracts without permission
- focus visible; Tab/Shift+Tab/Enter/Space
- mobile menu usable without horizontal tab strip
- no console error; no horizontal overflow
- long name/property strings wrap
```

Capture overview closed state + one secondary section on each viewport. Evidence stays outside Git.

- [ ] **Step 16: Close CAND-01 gate**

Only after CI + Browser QA + Human-First PASS:
- checkpoint exact SHA;
- owner gate;
- merge;
- do not start CAND-02 before merge is verified in `main`.

---

### Task 2 — CAND-02: Información laboral

**Files:**
- Modify: `resources/views/candidate_detail.php`
- Modify: `assets/app.css` only if existing candidate primitives are insufficient
- Modify: `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`

**Interfaces:**
- Consumes: CAND-01 shell/navigation and candidate fields `employment_status`, `employer_name`, `occupation`, `monthly_income`, `employment_notes`.
- Produces: Human-First body for `section=work`; no backend interface change.

- [ ] **Step 1: RED for real-only work facts**

Add assertions for `section=work`:

```text
- heading `Información laboral`
- only real employment fields render
- monthly_income formatted as `$ 5.200.000 COP` for fixture 5000000
- null/blank work data -> `No se registró información laboral.`
- absent: Sector, Área, Antigüedad, Otros ingresos, Perfil laboral sólido, Riesgo bajo
- exactly one active body
```

- [ ] **Step 2: Verify RED, commit RED**

Run focal test; expected FAIL against compatibility markup. Commit `test(candidates): define Human-First work section`.

- [ ] **Step 3: Minimal GREEN**

Replace only work body with:
- one main card for employment declaration;
- income emphasis inside that card, not a KPI dashboard;
- notes secondary block only when nonblank;
- exact empty state above.

No inference of stability/solvency.

- [ ] **Step 4: Focal + full regression**

Run Human-First view test + `CandidateServiceTest.php` + `composer test`.

- [ ] **Step 5: GREEN commit / PR / CI / Browser QA**

Commit `feat(candidates): humanize work section`; require CI 8.3/8.4/8.5 and Browser QA all three viewports before merge.

---

### Task 3 — CAND-03: Ocupantes y mascotas

**Files:**
- Modify: `resources/views/candidate_detail.php`
- Modify: `assets/app.css` only if needed
- Modify: `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`

**Interfaces:**
- Consumes: `occupants_count`, `occupants_notes`, `pets_declared`, `pets_notes`.
- Produces: Human-First body for `section=household`.

- [ ] **Step 1: RED factual boundary**

Assert:

```text
- count/notes are shown only when available
- pet declaration and pet notes are shown without inventing animal records
- null occupants_count does not become 0
- no names, ages, parentescos, breed, weight, vaccination, household-risk score
- exact empty state when no useful household details exist
```

- [ ] **Step 2: Verify RED and commit RED**

Commit `test(candidates): define Human-First household section`.

- [ ] **Step 3: Minimal GREEN**

Use no more than:
- `Resumen del hogar`;
- `Ocupantes`;
- `Mascotas`.

Use copy `No hay información adicional sobre ocupantes.` / `No hay información adicional sobre mascotas.` when appropriate; never fabricate individual people or animal profiles.

- [ ] **Step 4: Regression / PR / CI / Browser QA**

Run Human-First view test + candidate public wizard tests + full suite. Browser three viewports. Merge only after GREEN.

---

### Task 4 — CAND-04: Referencias

**Files:**
- Modify: `resources/views/candidate_detail.php`
- Modify: `assets/app.css` only if needed
- Modify: `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`

**Interfaces:**
- Consumes list `candidate['references']` with `reference_type`, `name`, `relationship`, `phone`, `email`.
- Produces Human-First body for `section=references`.

- [ ] **Step 1: RED real reference cards**

Assert:

```text
- one semantic card per real reference
- name is primary; type/relation/contact secondary
- empty list -> `No hay referencias registradas.`
- XSS fixture stays escaped
- absent: score, rating, stars, Recomendado, Contactada, historial de intentos, notas del evaluador
```

- [ ] **Step 2: Verify RED and commit RED**

Commit `test(candidates): define Human-First references section`.

- [ ] **Step 3: Minimal GREEN**

Use the existing reference truth only. Phone/email may be human-readable links (`tel:`/`mailto:`) but no tracking or new persistence.

- [ ] **Step 4: Regression / PR / CI / Browser QA**

Run Human-First test + `CandidateReferencesTest.php` + `CandidatesAdminRoutesTest.php` + full suite. Browser all viewports. Merge only after GREEN.

---

### Task 5 — CAND-05: Documentos

**Files:**
- Modify: `resources/views/candidate_detail.php`
- Modify: `assets/app.css` only if needed
- Modify: `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`

**Interfaces:**
- Consumes `documents` from `CandidateDocumentService::listDocumentsForCandidate()`.
- Produces Human-First body for `section=documents`.
- Existing private open route remains `/admin/candidates/{candidateId}/documents/{documentId}`.

- [ ] **Step 1: RED document truth**

Assert:

```text
- real requirement label + original filename + `Abrir documento`
- no stored filename/path leaks
- empty -> `No hay documentos adjuntos.`
- absent: En revisión, aprobado, checklist del revisor, Solicitar documentos, Subir documento admin, progress percent, faltantes guessed
```

- [ ] **Step 2: Verify RED and commit RED**

Commit `test(candidates): define Human-First documents section`.

- [ ] **Step 3: Minimal GREEN**

Design document rows/cards around opening existing private files. Do **not** add missing-requirements truth in this block.

- [ ] **Step 4: Security regression**

Run:

```bash
vendor/bin/phpunit tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php
vendor/bin/phpunit tests/Integration/Candidates/CandidateDocumentsRoutesTest.php
vendor/bin/phpunit tests/Integration/Candidates/CandidatesAdminRoutesTest.php
vendor/bin/phpunit tests/Integration/Candidates/CandidatesSecurityRegressionTest.php
composer test
```

- [ ] **Step 5: PR / CI / Browser QA**

Open a real PDF/image from the UI during Browser QA. Require CI 8.3/8.4/8.5 and all viewports.

---

### Task 6 — CAND-06: Estudio de aseguradora

**Files:**
- Modify: `resources/views/candidate_detail.php`
- Modify: `assets/app.css` only if needed
- Modify: `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`
- Existing route/service code should remain unchanged unless a regression proves otherwise.

**Interfaces:**
- Consumes `screening`, `canManageCandidates`, existing POST `/admin/candidates/{id}/screening`, `HumanLabels::screeningStatus()`.
- Produces Human-First body for `section=screening` preserving current form contract.

- [ ] **Step 1: RED screening states**

Cover at least:

```text
screening null + viewer -> empty state only
screening null + eligible manager -> Crear estudio form
pending/in_progress + eligible manager -> Actualizar estudio form
approved/rejected -> read-only terminal presentation
viewer -> read-only even when process exists
```

Assert absence of `82`, `/100`, `Riesgo bajo`, `Capacidad de pago`, `Historial crediticio`, invented ETA.

- [ ] **Step 2: Verify RED and commit RED**

Commit `test(candidates): define Human-First screening section`.

- [ ] **Step 3: Minimal GREEN**

Group factual status/provider/product/contact/radicado/result/notes/dates. Preserve exact existing POST action, CSRF and field names. One primary action max (`Crear estudio` or `Actualizar estudio`).

- [ ] **Step 4: Regression**

Run Human-First view test + `ScreeningAdminRoutesTest.php` + `PublicCandidateScreeningTest.php` + full suite.

- [ ] **Step 5: PR / CI / Browser QA**

QA viewer and manager, plus terminal screening. No merge without exact-head CI and real interaction.

---

### Task 7 — CAND-07: Contratos

**Files:**
- Modify: `resources/views/candidate_detail.php`
- Modify: `app/UI/HumanLabels.php`
- Modify: `assets/app.css` only if needed
- Modify: `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`

**Interfaces:**
- Consumes `contractContext['contracts']`, `contractContext['can_create']`, existing contract routes.
- Produces `HumanLabels::contractStatus(string $status): string` for verified contract statuses.

- [ ] **Step 1: RED permission and factual contract presentation**

Assert:

```text
- contracts body is impossible when contractContext === null
- each real contract shows id/context, human status, template name/unit when present, Abrir contrato
- Crear contrato only when can_create=true
- retain `data-contract-context` compatibility marker
- absent: signatures, signature progress, reminders, approvals, invented history
```

Contract label mapping fixed to real states currently owned by `ContractService`:

```text
draft     -> Borrador
generated -> Generado
finalized -> Finalizado
default   -> escaped fallback value
```

- [ ] **Step 2: Verify RED and commit RED**

Commit `test(candidates): define Human-First contracts section`.

- [ ] **Step 3: Minimal GREEN**

Add `HumanLabels::contractStatus()` and redesign only the candidate contracts body. Do not duplicate contract editing/finalization UI here.

- [ ] **Step 4: Contract regression**

Run:

```bash
vendor/bin/phpunit tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php
vendor/bin/phpunit tests/Integration/Contracts/ContractAdminRoutesTest.php
vendor/bin/phpunit tests/Integration/Contracts/ContractFinalizationRoutesTest.php
composer test
```

- [ ] **Step 5: PR / CI / Browser QA**

Test user with/without `contracts.view`, no contracts, existing draft/generated/finalized, and can_create true/false.

---

### Task 8 — CAND-08: Administrar + eliminar duplicación de transiciones

**Files:**
- Modify: `app/Candidates/CandidateService.php`
- Modify: `app/Candidates/routes.php`
- Modify: `resources/views/candidate_detail.php`
- Modify: `assets/app.css` only if needed
- Modify: `tests/Integration/Candidates/CandidateServiceTest.php`
- Modify: `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`
- Modify: `tests/Integration/Candidates/CandidatesAdminRoutesTest.php`

**Interfaces:**
- Produces: `CandidateService::allowedTransitionsForStatus(string $status): array`.
- `CandidateService::transitionStatus()` must consume that same method; the view no longer owns a duplicate `match`.
- Route passes `allowedCandidateTransitions` only for authorized management context.

- [ ] **Step 1: RED single-owner transition matrix**

In `CandidateServiceTest.php`, pin exactly:

```text
new               -> incomplete, withdrawn
incomplete        -> withdrawn
verification      -> pending_screening, withdrawn
pending_screening -> withdrawn
withdrawn         -> []
unknown           -> []
```

Also test `transitionStatus()` behavior still matches this matrix.

- [ ] **Step 2: RED Human-First manage surface**

Assert:

```text
- absent entirely without candidates.manage
- current status visible
- only real target states offered
- regenerate link form remains
- no pre-aprobar/aprobar/rechazar shortcut
- no generic notes/history/archive controls
```

- [ ] **Step 3: Verify RED and commit RED**

Commit `test(candidates): centralize candidate management contract`.

- [ ] **Step 4: Minimal domain de-dup GREEN**

Implement `allowedTransitionsForStatus()` in `CandidateService`; replace the private duplicated matrix inside `transitionStatus()` with this method. Route obtains the list from the service. Remove the status transition `match` from `candidate_detail.php`.

- [ ] **Step 5: Humanize manage body**

Use one high-risk action area with explicit current status and target select only when targets exist; preserve CSRF/action. Keep token regeneration as separate secondary action with warning copy that a new continuation link invalidates the previous one.

- [ ] **Step 6: Regression / PR / CI / Browser QA**

Run CandidateServiceTest, HumanFirst view test, CandidatesAdminRoutesTest, security tests and full suite. Browser with viewer + manager and at least two candidate statuses. No merge without GREEN.

---

### Task 9 — CAND-09: Listado Candidatos — alineación visual sin HF-P2A

**Files:**
- Modify: `resources/views/candidates.php`
- Modify: `assets/app.css` only through reusable candidate primitives
- Modify: `tests/Integration/Candidates/CandidateCatalogCtaTest.php`
- Create only if needed for presentation isolation: `tests/Integration/Candidates/HumanFirstCandidatesListViewTest.php`

**Interfaces:**
- Consumes existing `CandidateService::listCandidates()` exactly as today.
- Produces a more scannable list; no query/filter/pagination interface.

- [ ] **Step 1: RED list hierarchy**

Assert each item prioritizes:

```text
full_name
human candidate status
property + unit
phone/email as secondary if useful
one primary action: Ver candidato
```

Assert absence of KPIs, search/filter/pagination controls and fake counts.

- [ ] **Step 2: Verify RED and commit RED**

Commit `test(candidates): define Human-First candidate catalog`.

- [ ] **Step 3: Minimal GREEN**

Align list art direction with detail shell using existing tokens; preserve empty state and escaping. Do not open HF-P2A.

- [ ] **Step 4: Regression / PR / CI / Browser QA**

Run catalog CTA test + admin routes + full suite. Browser with 0, 1 and several candidates; all target viewports.

---

### Task 10 — Final Candidates Human-First Closure

**Files:**
- Modify: `docs/CURRENT_CHECKPOINT.md` only after all nine microblocks are integrated and verified.
- No product code in this task unless a new defect follows Anti-parche from RED.

**Interfaces:**
- Consumes merged SHAs and Browser evidence from CAND-01…CAND-09.
- Produces durable module-level certification/checkpoint.

- [ ] **Step 1: Whole-module automated regression**

Run full suite on current `main` and confirm exact-head CI PHP 8.3/8.4/8.5.

- [ ] **Step 2: Whole-module Browser QA**

At 1440×900, 768×1024, 375×812:

```text
Candidatos list
Overview
Información laboral
Ocupantes y mascotas
Referencias
Documentos
Estudio de aseguradora
Contratos when authorized
Administrar when authorized
```

Check keyboard/focus, back/forward, direct section URLs, viewer/manager permission differences, empty states, long content, console, overflow and human comprehension.

- [ ] **Step 3: No Infinite Excel audit**

Fail final certification if:
- multiple sections render simultaneously;
- the candidate becomes a wall of label/value rows again;
- more than one dominant CTA appears without necessity;
- facts are redundantly repeated across sections;
- raw codes appear as primary UI;
- visually identical cards replace the old table without improving hierarchy.

- [ ] **Step 4: Laws audit**

Record:

```text
KISS=PASS/FAIL
ZERO_NOISE=PASS/FAIL
ANTI_PATCH=PASS/FAIL
HUMAN_FIRST=PASS/FAIL
NO_FAKE_CAPABILITIES=PASS/FAIL
NO_REDUNDANT_PRIMARY_FACTS=PASS/FAIL
NO_INFINITE_EXCEL=PASS/FAIL
ACCESSIBILITY=PASS/FAIL
RESPONSIVE=PASS/FAIL
```

- [ ] **Step 5: Persist checkpoint**

Update `docs/CURRENT_CHECKPOINT.md` once, not as a patch chain. Include exact integrated SHA, CI run, Browser QA evidence location, and next permitted project gate.

- [ ] **Step 6: Final checkpoint branch**

Create one durable branch/reference pointing to the final integrated checkpoint.

---

# Browser Evidence Contract

For every microblock, evidence must be kept outside Git and include:

```text
exact SHA
URL tested
browser
role/permissions
viewport
PASS/FAIL matrix
console result
keyboard/focus result
overflow result
screenshots
code changes made after Browser QA, if any
```

If Browser reveals a defect, the current candidate loses visual certification. Apply Anti-parche: reproduce → root cause → RED → minimal GREEN → fresh CI → fresh Browser QA on the new exact SHA.

---

# Plan Self-Review

## Spec coverage

- Shell + navigation: CAND-01.
- Solicitud: CAND-01.
- Información laboral: CAND-02.
- Ocupantes/mascotas: CAND-03.
- Referencias: CAND-04.
- Documentos: CAND-05.
- Screening: CAND-06.
- Contratos: CAND-07.
- Administrar: CAND-08.
- Listado: CAND-09.
- Responsive/accessibility/Browser: each task + final closure.
- No redundancia / No Infinite Excel / final DoD: Task 10.
- HF-P2A remains excluded.

No uncovered spec requirement found.

## Step scan

Each task owns a testable behavior, has RED proof, minimal implementation boundary, regression command, CI and Browser gate. No task requires speculative backend work.

## Type/interface consistency

- `candidateSections` + `activeSection` are introduced once in CAND-01 and reused.
- `HumanLabels::candidateStatusDescription()` is introduced once in CAND-01.
- `HumanLabels::contractStatus()` is introduced once in CAND-07.
- `CandidateService::allowedTransitionsForStatus()` is introduced once in CAND-08 and becomes the single transition-list owner.

## Proportion / KISS

The plan intentionally keeps one route, one main candidate view, one stylesheet and the existing services. It does not create eight controllers, eight endpoints, a SPA, a design framework or a presentation service.

---

# Gate Status

```text
SPEC_OWNER_APPROVED=YES
PLAN_STATUS=READY_FOR_OWNER_REVIEW
IMPLEMENTATION_STARTED=NO
PR_43=DRAFT_DO_NOT_MERGE
PR_44=DESIGN_DOCS_ONLY
NEXT_IMPLEMENTATION_GATE=CAND-01_AFTER_PLAN_APPROVAL
R3=FROZEN
R4=NOT_STARTED
DEPLOYMENT=NOT_AUTHORIZED
```
