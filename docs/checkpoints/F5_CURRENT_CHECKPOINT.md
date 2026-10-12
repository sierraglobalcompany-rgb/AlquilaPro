# F5 CURRENT CHECKPOINT — F5.4 CLOSED / VERIFIED

**Fecha:** 2026-10-12 (America/Bogota)  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Branch:** `impl/f5-candidates-20261012`  
**Base:** `main` @ `648401a4927bfeb7525421a4edd2b5f2c60f06c4`  
**PR:** `#8` OPEN / DRAFT / NOT MERGED

> El SHA del commit que contiene este archivo no se auto-incrusta para evitar ciclos documentales. Consultar el HEAD del branch/PR y su CI exacto.

## 1. Estado

```text
F0-F4: CLOSED / VERIFIED / MERGED
F5_SPEC: APPROVED
F5_PLAN: APPROVED
F5.1_SCHEMA_PERMISSIONS: CLOSED / VERIFIED
F5.2_CANDIDATE_CORE_TOKEN: CLOSED / VERIFIED
F5.3_PUBLIC_START_CONTINUE: CLOSED / VERIFIED
F5.4_PROFILE_REFERENCES_UNIT_CHANGE: CLOSED / VERIFIED
F5.5_STRUCTURED_REQUIREMENTS_PRIVATE_DOCUMENTS: NOT STARTED
F5.6-F5.8: NOT STARTED
PR_READY: NO
MERGE_ALLOWED: NO
```

No abrir F5.5 sin reconfirmar este checkpoint, HEAD, PR #8 y CI.

## 2. Autoridad y leyes

Autoridad: README → SPEC maestro V1.2 → PLAN maestro V1.2 → SPEC F5 → decisión F5 de identidad/privacidad/estados → PLAN F5 → checkpoint → tests/CI → código real.

Leyes:
- LEY 1 KISS.
- LEY 2 CERO RUIDO / CONVERGENCIA.
- RED antes de GREEN.
- No merge sin aprobación humana explícita.

## 3. F5.1–F5.3 ya cerradas

### F5.1
- exactamente +4 tablas: `candidates`, `candidate_references`, `unit_application_requirements`, `candidate_documents`;
- exactamente +2 permisos: `candidates.view`, `candidates.manage`;
- sin lead/application paralelo, segundo auth/session, npm o dependencias nuevas;
- GREEN `a9449e8f87c345e6baafeda9bd26a5aa8f86b882`;
- CI `38263081173` PASS PHP 8.3/8.4/8.5;
- PHP 8.3: 157 tests / 905 assertions.

### F5.2
- un solo `CandidateService`;
- token raw 64 hex, solo SHA-256 persistido, TTL 7 días, expiración fail-closed;
- identidad normalizada/inmutable, duplicado genérico;
- candidato nuevo solo sobre unidad publicable; candidato existente puede continuar tras despublicación;
- estados exactos `new|incomplete|verification|pending_screening|withdrawn`;
- submit público es el camino a `verification`; `withdrawn` terminal;
- GREEN `511dc595b8265794f19dd3b5a04fef1ceaa62d36`;
- CI `38264989604` PASS PHP 8.3/8.4/8.5;
- PHP 8.3: 164 tests / 968 assertions.

### F5.3
Rutas públicas:
```text
GET  /apply/{publicKey}
POST /apply/{publicKey}
GET  /apply/continue/{token}
POST /apply/continue/{token}
```
Contratos: publicabilidad F4 reutilizada, CSRF global, basePath, token raw sin candidate ID/hash, duplicado genérico, aislamiento por token y throttle V1 por sesión (start 5/10min; continue 10/10min; 429 sin mutación).

RED `c1fab238ecc4cc5c172ab9f36f1941ecd3758734` / CI `38270416673`: 172 tests / 980 assertions / 8 failures esperados.  
GREEN producto `544d6c6f15a926a4b28d004bd32b5075aa9f2fcd` / CI `38271564490`: PASS 8.3/8.4/8.5, 172 tests / 1050 assertions en PHP 8.3.  
Checkpoint F5.3 `4b29a9a639b1ab3af9ecfc977abaa948b0270afb` / CI `38271890830`: PASS 8.3/8.4/8.5.

## 4. F5.4 — alcance cerrado

Solo cambiaron los archivos previstos por el PLAN:
- `app/Candidates/CandidateService.php`;
- `app/Candidates/routes.php`;
- `resources/views/public_candidate_continue.php`;
- `tests/Integration/Candidates/CandidateReferencesTest.php`;
- `tests/Integration/Candidates/CandidateServiceTest.php`.

No cambió schema, bootstrap, CSS/JS, Composer/npm, permisos, F4 ni almacenamiento documental. No se creó `CandidateReferenceService`, segunda entidad de aplicación, CRUD público por reference ID ni historial de cambios de unidad.

## 5. Perfil ampliado

`updateByToken()` ahora valida/persiste:
- `monthly_income`: opcional, numérico, >= 0 y dentro de `DECIMAL(12,2)`;
- `occupants_count`: opcional, entero >= 0 y compatible con `SMALLINT UNSIGNED`;
- `occupants_notes`;
- `pets_declared`: boolean estricto;
- `pets_notes`;
- campos base/laborales previos.

Guardado público válido: `new -> incomplete`; `incomplete` permanece `incomplete`. Estados no editables continúan bloqueados. La regla laboral F5.2 sigue activa en submit.

## 6. Referencias 0..N

Método añadido en el mismo servicio:
```php
replaceReferencesByToken(string $rawToken, array $references, int $now)
```

Contrato:
- cero, una o múltiples referencias;
- fila completamente vacía se ignora;
- referencia real exige `name + phone`;
- `reference_type`, `relationship`, `email` opcionales;
- email opcional validado/normalizado;
- conjunto completo validado antes de mutar;
- reemplazo transaccional;
- error conserva el conjunto previo;
- superficie pública sin reference IDs;
- `findByToken()` devuelve referencias solo con `reference_type`, `name`, `relationship`, `phone`, `email`.

Ruta añadida:
```text
POST /apply/continue/{token}/references
```
Reutiliza CSRF global, basePath y bucket de throttle `continue`.

## 7. Cambio controlado de unidad

Método añadido:
```php
changeUnitByToken(string $rawToken, string $publicKey, int $now)
```

Permitido solo si simultáneamente:
```text
status = new|incomplete
submitted_at IS NULL
candidate_documents = 0
target publicKey actualmente publicable
```

La publicabilidad reutiliza `PropertyService::findPublicUnit()`. El servidor resuelve el ID interno; se actualiza únicamente la misma fila `candidate.unit_id`; no se crea segundo candidato ni historial paralelo.

Ruta añadida:
```text
POST /apply/continue/{token}/unit
```
La vista usa `public_key`, nunca unit ID interno.

## 8. SSR / privacidad

La misma `public_candidate_continue.php` ahora contiene perfil ampliado, referencias y selector de unidad. Todos los valores dinámicos se escapan. No expone candidate ID, reference ID, continuation hash ni storage path. No se agregó CSS ni JS.

## 9. TDD F5.4

### RED
Commit: `01159acb731705ab18b850be3253da23bf05f635`  
CI: `38273121352`

PHP 8.3:
- 176 tests / 1051 assertions;
- 1 failure + 3 errors;
- exactamente por F5.4 ausente: promoción `new -> incomplete`, referencias, cambio de unidad y superficie SSR/rutas dependiente de referencias;
- F1-F5.3 permanecieron verdes.

### GREEN inicial
HEAD `a368460feb628207e8d186019221d9337a51ba3f`  
CI `38273634863`
- PHP 8.3/8.4/8.5 PASS;
- Composer validate --strict PASS;
- PHP 8.3: 176 tests / 1114 assertions / 0 failures / 0 errors.

### GREEN final de conformidad PLAN
El PLAN exige también extender `CandidateServiceTest.php`. Se añadió una caracterización mínima que comprueba que `findByToken()` entrega referencias sin `id` ni `candidate_id`.

Producto/test final F5.4: `471c92d47a568a720dd3606e9455a39ec51a75ed`  
CI: `38273953538`
- PHP 8.3 PASS;
- PHP 8.4 PASS;
- PHP 8.5 PASS;
- Composer validate --strict PASS;
- PHP 8.3: **177 tests / 1121 assertions / 0 failures / 0 errors**.

## 10. Two Laws / Cero Ruido

Compare `4b29a9a639b1ab3af9ecfc977abaa948b0270afb...471c92d47a568a720dd3606e9455a39ec51a75ed` confirmó exactamente 5 archivos efectivos, todos previstos por F5.4.

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
NEW_TABLES_F5.4: 0
NEW_SERVICES_F5.4: 0
NEW_ROUTE_FILES_F5.4: 0
NEW_VIEWS_F5.4: 0
BOOTSTRAP_DELTA_F5.4: 0
CSS_JS_DELTA_F5.4: 0
DEPENDENCIES_DELTA: 0
NPM_DELTA: 0
SECOND_AUTH_OR_SESSION: NO
REFERENCE_PUBLIC_IDS: NO
PARALLEL_APPLICATION_ENTITY: NO
UNIT_CHANGE_HISTORY_TABLE: NO
DOCUMENT_RUNTIME_EARLY: NO
PUBLICABILITY_DUPLICATION: NO
```

Hasta F5.4 solo existe `CandidateService`. El único segundo servicio permitido, `CandidateDocumentService`, sigue reservado para F5.5.

## 11. PR / gobernanza

PR #8 debe permanecer OPEN / DRAFT / NOT MERGED. No marcar Ready: F5.5-F5.8 siguen abiertas. Merge no autorizado.

## 12. Próximo gate — F5.5 NOT STARTED

**F5.5 — Structured Requirements + Private Candidate Documents**.

Orden al retomar:
1. reconfirmar HEAD / PR #8 / CI del checkpoint;
2. releer PLAN/SPEC F5.5;
3. RED en `CandidateDocumentsTest.php` + extensión focal de `CandidateServiceTest.php`;
4. verificar RED antes de producción;
5. crear el único segundo servicio permitido: `CandidateDocumentService`;
6. reutilizar tablas F5 existentes; no crear tablas nuevas;
7. requisitos estructurados por unidad;
8. documentos privados ligados a candidate + requirement;
9. validar MIME real, tamaño y nombre almacenado generado por servidor;
10. no servir `storage/` directamente;
11. descarga privada debe validar par candidate/document;
12. ampliar el mismo `app/Candidates/routes.php` y wiring mínimo;
13. ampliar `unit_detail.php` y `public_candidate_continue.php` solo lo necesario;
14. CI PHP 8.3/8.4/8.5 + Cero Ruido + checkpoint;
15. no abrir F5.6 hasta F5.5 CLOSED / VERIFIED.

Guardrails F5.5:
- máximo dos servicios F5 totales;
- no repositories/DTO/factories/interfaces;
- no almacenamiento público directo;
- no IDs internos en URLs públicas de candidato;
- no tercera tabla documental;
- no queue/cloud-storage abstraction sin necesidad probada;
- PR #8 sigue Draft;
- **NO MERGEAR sin aprobación humana explícita**.
