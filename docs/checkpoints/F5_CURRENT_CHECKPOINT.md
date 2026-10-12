# F5 CURRENT CHECKPOINT — F5.2 CLOSED / VERIFIED

**Fecha:** 2026-10-12 (America/Bogota)  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Branch:** `impl/f5-candidates-20261012`  
**Base:** `main` @ `648401a4927bfeb7525421a4edd2b5f2c60f06c4`  
**PR:** `#8` OPEN / DRAFT / NOT MERGED

> Regla documental: este checkpoint no auto-incrusta el SHA de su propio commit final, porque hacerlo crea un nuevo commit y vuelve obsoleto el SHA escrito. El HEAD exacto y su CI se consultan en GitHub/PR #8. El checkpoint sí conserva los SHAs y runs de producto/evidencia ya cerrados.

---

## 1. ESTADO

```text
F0-F4: CLOSED / VERIFIED / MERGED
F5_SPEC: APPROVED
F5_PLAN: APPROVED
F5.1_SCHEMA_PERMISSIONS: CLOSED / VERIFIED
F5.2_CANDIDATE_CORE_TOKEN: CLOSED / VERIFIED
F5.3_PUBLIC_START_CONTINUE: NOT STARTED
F5.4-F5.8: NOT STARTED
MERGE_ALLOWED: NO
```

No abrir F5.3 sin retomar desde este checkpoint y reconfirmar HEAD/CI/PR.

---

## 2. AUTORIDAD F5

1. `README.md`
2. `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`
3. `docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`
4. `docs/specs/F5_APPLICATIONS_CANDIDATES_REFERENCES_SPEC.md`
5. `docs/decisions/F5_DUPLICATE_IDENTITY_PRIVACY_AND_STATE_TRANSITIONS_2026-10-12.md`
6. `docs/plans/F5_APPLICATIONS_CANDIDATES_REFERENCES_IMPLEMENTATION_PLAN.md`
7. este checkpoint
8. tests / CI
9. código real

Leyes:

- LEY 1 KISS
- LEY 2 CERO RUIDO / CONVERGENCIA

---

## 3. F5.1 — FUNDACIÓN CERRADA

F5.1 quedó CLOSED / VERIFIED con:

- exactamente +4 tablas: `candidates`, `candidate_references`, `unit_application_requirements`, `candidate_documents`;
- exactamente +2 permisos: `candidates.view`, `candidates.manage`;
- +0 dependencias runtime;
- +0 npm;
- sin `lead`, `applications`, `rental_applications`, login candidato ni segunda sesión.

GREEN final F5.1:

- producto/test isolation: `a9449e8f87c345e6baafeda9bd26a5aa8f86b882`;
- CI: `38263081173`;
- PHP 8.3 / 8.4 / 8.5: PASS;
- PHPUnit PHP 8.3: **157 tests / 905 assertions / 0 failures / 0 errors**.

---

## 4. F5.2 — CANDIDATE CORE + SECURE CONTINUATION TOKEN

### Scope aplicado

F5.2 agregó únicamente:

- `app/Candidates/CandidateService.php`;
- `tests/Integration/Candidates/CandidateServiceTest.php`.

No agregó:

- rutas;
- vistas;
- bootstrap wiring público;
- nuevas tablas;
- nuevas dependencias;
- segundo auth/session;
- referencias runtime;
- documentos runtime;
- cambio de unidad runtime.

### Contratos cerrados

`CandidateService` implementa en F5.2:

- `startCandidate()`;
- `findByToken()`;
- `updateByToken()`;
- `submitByToken()`;
- `listCandidates()`;
- `findCandidate()`;
- `transitionStatus()`;
- `regenerateToken()`.

`changeUnitByToken()` y `replaceReferencesByToken()` **no se agregaron como stubs**. Su comportamiento pertenece a la microfase F5.4 — Profile Completion + References + Controlled Unit Change. Esta convergencia evita código muerto y no cambia el contrato funcional futuro del PLAN.

---

## 5. TOKEN / IDENTIDAD

Contrato comprobado:

- raw token = `bin2hex(random_bytes(32))`;
- exactamente 64 caracteres hex lowercase;
- DB persiste únicamente `hash('sha256', $rawToken)`;
- TTL por defecto = `604800` segundos / 7 días;
- expiración es fail-closed;
- token inválido no resuelve candidato;
- regeneración invalida inmediatamente el raw token anterior;
- raw token se devuelve únicamente al crear/regenerar, nunca se persiste.

Normalización mínima y no destructiva fijada para F5:

- `document_type`: `trim` + lowercase;
- `document_number`: `trim` + uppercase;
- no se eliminan puntos, guiones u otros caracteres internos porque SPEC/PLAN no definen esa transformación.

Identidad:

- `document_type` + `document_number` son inmutables mediante actualización pública;
- identidad duplicada normalizada no crea segunda fila;
- error de duplicado es genérico: no incluye candidate ID, token, nombre, email, unidad ni estado.

---

## 6. PUBLICABILIDAD Y CONTINUIDAD

`startCandidate()` reutiliza `PropertyService::findPublicUnit()` como gate de F4; no duplica el predicado de publicabilidad.

Reglas verificadas:

- unidad actualmente publicable -> puede iniciar candidato;
- unidad no publicable -> no acepta candidato nuevo;
- candidato creado válidamente antes de despublicar la unidad -> su token sigue resolviendo;
- F5 no altera `units.status`.

No se creó segunda fuente de disponibilidad/precio/publicación.

---

## 7. ESTADOS Y SUBMIT F5.2

Estados F5 siguen siendo exactamente:

- `new`;
- `incomplete`;
- `verification`;
- `pending_screening`;
- `withdrawn`.

Regla aplicada en F5.2:

- creación pública -> `new`;
- `submitByToken()` es la única operación F5.2 que puede producir `new|incomplete -> verification`;
- `transitionStatus()` no puede simular submit desde `new|incomplete`;
- admin/core permite `new -> incomplete|withdrawn`;
- `incomplete -> withdrawn` vía transición administrativa;
- `verification -> pending_screening|withdrawn`;
- `pending_screening -> withdrawn`;
- `withdrawn` terminal.

El salto `incomplete -> verification` sigue disponible por `submitByToken()`, no por transición administrativa.

Submit F5.2 valida:

- token vigente;
- estado editable `new|incomplete`;
- datos básicos persistidos;
- consentimiento;
- regla laboral determinística: si cualquiera de `employment_status`, `employer_name`, `occupation`, `monthly_income`, `employment_notes` fue iniciado, `employment_status` debe estar presente;
- al pasar, fija `status = verification` y `submitted_at`.

La completitud por requisitos/documentos se añadirá en F5.5, que es la microfase propietaria de `CandidateDocumentService` y requisitos estructurados.

F5.4 añadirá validaciones extendidas de perfil/referencias y promoción de guardado parcial `new -> incomplete`.

---

## 8. TDD / EVIDENCIA F5.2

### RED

Commit: `6d08aec9383de0e77ec3a6a0ffcc63969ee70bf6`  
CI: `38264567979`

PHP 8.3 observado:

- **164 tests / 912 assertions / 7 failures**;
- los 7 fallos fueron exactamente los tests nuevos F5.2 por ausencia de `CandidateService`;
- F1-F4 y F5.1 permanecieron verdes.

Casos RED:

1. candidato nuevo + token/hash/TTL;
2. token inválido/expirado + continuidad tras despublicación;
3. identidad duplicada normalizada sin segunda fila/fuga;
4. identidad inmutable + bloqueo de edición tras submit;
5. regeneración invalida token anterior;
6. regla laboral + submit como único camino público a verification;
7. matriz de transiciones y `withdrawn` terminal.

### GREEN producto

Commit: `511dc595b8265794f19dd3b5a04fef1ceaa62d36`  
CI push: `38264989604`

Resultado:

- PHP 8.3: PASS;
- PHP 8.4: PASS;
- PHP 8.5: PASS;
- Composer validate --strict: PASS;
- PHPUnit PHP 8.3: **164 tests / 968 assertions / 0 failures / 0 errors**;
- los 7 tests `CandidateService` pasan;
- regresión F1-F4 + F5.1 completa verde.

### Evidencia documental previa

- checkpoint `b4904bb0f3f5b4c52c8669850b178ee9f08dc86f` — CI `38265367655` — PHP 8.3/8.4/8.5 PASS;
- checkpoint `89901bc4253d391901dff621a2d74dc667638b86` — CI `38265599227` — PHP 8.3/8.4/8.5 PASS.

El HEAD exacto actual no se auto-registra en este archivo; debe tomarse del branch/PR y su workflow asociado para evitar el ciclo documental descrito al inicio.

---

## 9. F5.2 TWO LAWS GATE

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
ADDED_PRODUCT: +1 CandidateService
ADDED_TESTS: +1 CandidateServiceTest
ADDED_ROUTES: 0
ADDED_VIEWS: 0
ADDED_TABLES_F5.2: 0
DEPENDENCIES_DELTA: 0
NPM_DELTA: 0
SECOND_AUTH: NO
PARALLEL_APPLICATION_ENTITY: NO
PUBLICABILITY_DUPLICATION: NO
DEAD_STUBS_FOR_F5.4: NO
```

No se agregaron repository/DTO/interface/factory, workflow engine, token table, candidate session, CRM ni abstracciones futuras.

---

## 10. PR

PR #8 debe permanecer:

- state: OPEN;
- draft: YES;
- merged: NO;
- merge no autorizado.

No marcar Ready todavía: F5.3-F5.8 siguen abiertas.

---

## 11. SIGUIENTE MICROFASE — NO INICIADA

F5.3 — Public Start + Continue Surface.

Orden exacto al retomar:

1. reconfirmar branch HEAD / PR #8 / CI del checkpoint;
2. releer PLAN F5.3 y contratos de privacidad/throttle/subfolder;
3. crear RED `tests/Integration/Candidates/PublicCandidateApplyTest.php`;
4. verificar RED real antes de producción;
5. registrar `CandidateService` y exactamente un `app/Candidates/routes.php` en bootstrap;
6. crear `resources/views/public_apply_start.php` y `resources/views/public_candidate_continue.php`;
7. implementar solo start/continue + throttle local mínimo + CSRF/basePath;
8. verificar focal + regresión F4 public detail + full CI PHP 8.3/8.4/8.5;
9. actualizar este mismo checkpoint;
10. no abrir F5.4 antes de F5.3 GREEN.

F5.3 debe mantener:

- ninguna segunda auth/session;
- ningún candidate ID público;
- ningún token hash público;
- duplicate identity sin token/contexto privado;
- un solo route file F5;
- throttle V1 local/session, sin Redis/tabla/worker/dependencia;
- rutas/forms/redirects compatibles con `basePath`;
- PR #8 Draft;
- NO MERGE sin aprobación humana explícita.
