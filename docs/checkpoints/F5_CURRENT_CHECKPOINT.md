# F5 CURRENT CHECKPOINT — F5.1 CLOSED / VERIFIED

**Fecha:** 2026-10-12 (America/Bogota)  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Branch:** `impl/f5-candidates-20261012`  
**Base:** `main` @ `648401a4927bfeb7525421a4edd2b5f2c60f06c4`  
**PR:** `#8` OPEN / DRAFT / NOT MERGED

---

## 1. ESTADO

```text
F0-F4: CLOSED / VERIFIED / MERGED
F5_SPEC: APPROVED
F5_PLAN: APPROVED
F5.1_SCHEMA_PERMISSIONS: CLOSED / VERIFIED
F5.2_CANDIDATE_CORE_TOKEN: NOT STARTED
F5.3-F5.8: NOT STARTED
MERGE_ALLOWED: NO
```

No abrir F5.2 sin retomar desde este checkpoint y reconfirmar HEAD/CI/PR.

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

## 3. F5.1 — RESULTADO

F5.1 agrega únicamente la fundación de datos y permisos.

### Schema delta

Exactamente +4 tablas:

- `candidates`
- `candidate_references`
- `unit_application_requirements`
- `candidate_documents`

No existen:

- `leads`
- `applications`
- `rental_applications`
- tabla de auth/sesión candidato
- tabla pública paralela

Constraints fijados por tests:

- `UNIQUE(candidates.document_type, candidates.document_number)`
- `UNIQUE(candidates.continuation_token_hash)`
- `UNIQUE(unit_application_requirements.unit_id, code)`
- `UNIQUE(candidate_documents.candidate_id, requirement_id)`
- FKs candidaturas/referencias/requisitos/documentos según SPEC.

### Permissions delta

Exactamente +2:

- `candidates.view`
- `candidates.manage`

### Dependencies

- Composer runtime delta: 0
- npm delta: 0
- servicios F5: 0 todavía
- rutas F5: 0 todavía
- vistas F5: 0 todavía

---

## 4. TDD / EVIDENCIA

### RED 1

Commit: `e7e2613a789636cfebfb47cdc3f2a5b5b3b383e9`  
CI: `38261771347`

Resultado esperado:

- PHP 8.3 / 8.4 / 8.5: FAIL
- 156 tests / 880 assertions / 4 fallos en PHP 8.4 observado
- fallos únicamente por tablas/índices/FKs/permisos F5 ausentes.

### RED 2 — contrato de columnas exactas

Commit: `1baff8bbcc6c9be8762908bdaef285e55868ada6`  
CI: `38262110842`

Resultado observado:

- 157 tests / 881 assertions / 5 fallos
- se añadió RED de columnas exactas
- sin regresión funcional F1-F4.

### GREEN producto inicial

Commit: `bf81f7f4670e7407340ab179dc6fe390c0b19391`

Implementó:

- migration 005
- dos permisos F5
- contrato MigrationRunner a 5 migraciones

La suite focal F5 pasó, pero el full suite reveló contaminación histórica de BD de tests: nuevas tablas hijas permanecían entre métodos y setups antiguos intentaban borrar padres F1-F4.

No se relajaron FKs ni schema productivo.

### Corrección de causa raíz — test isolation

Commit: `a9449e8f87c345e6baafeda9bd26a5aa8f86b882`  
CI: `38263081173`

Se centralizó aislamiento de BD **solo en tests** mediante PHPUnit 11:

- `tests/Support/DatabaseIsolationExtension.php`
- `phpunit.xml`

La extensión vacía la BD de prueba después de cada test con FK checks desactivados durante cleanup. No modifica runtime/producción.

### GREEN final F5.1

CI `38263081173`:

- PHP 8.3: PASS
- PHP 8.4: PASS
- PHP 8.5: PASS
- Composer validate --strict: PASS
- PHPUnit PHP 8.3: **157 tests / 905 assertions / 0 failures / 0 errors**

---

## 5. F5.1 TWO LAWS GATE

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
ADDED_PRODUCT: migration 005 + 2 permissions
ADDED_TEST_INFRA: one database-isolation extension
REMOVED: 0
MERGED_OR_CONVERGED: DB test cleanup centralized instead of editing ~25 setup arrays
DEPENDENCIES_DELTA: 0
DB_SCHEMA_DELTA: +4 tables exactly
PUBLIC_ROUTES_DELTA: 0
```

No se añadió CandidateService, rutas, UI, CTA Aplicar ni documentos runtime fuera del schema.

---

## 6. PR

PR #8:

- title: `F5: Applications, candidates and references`
- state: OPEN
- draft: YES
- merged: NO
- merge no autorizado.

---

## 7. SIGUIENTE MICROFASE — NO INICIADA

F5.2 — Candidate Core + Secure Continuation Token.

Orden exacto al retomar:

1. reconfirmar branch HEAD / PR #8 / CI;
2. releer SPEC §4–§7, decisión de privacidad/transiciones y PLAN F5.2;
3. escribir `tests/Integration/Candidates/CandidateServiceTest.php` RED;
4. verificar RED exacto;
5. implementar únicamente `app/Candidates/CandidateService.php` GREEN mínimo;
6. verificar focal + full CI 8.3/8.4/8.5;
7. actualizar este mismo checkpoint, no crear WIP paralelo;
8. no abrir F5.3 hasta F5.2 GREEN.

Interfaces F5.2 previstas por PLAN:

- `startCandidate()`
- `findByToken()`
- `updateByToken()`
- `changeUnitByToken()`
- `replaceReferencesByToken()`
- `submitByToken()`
- `listCandidates()`
- `findCandidate()`
- `transitionStatus()`
- `regenerateToken()`

F5.2 debe seguir sin rutas/UI públicas.
