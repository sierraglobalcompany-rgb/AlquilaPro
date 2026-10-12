# F5 CURRENT CHECKPOINT — F5.3 CLOSED / VERIFIED

**Fecha:** 2026-10-12 (America/Bogota)  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Branch:** `impl/f5-candidates-20261012`  
**Base:** `main` @ `648401a4927bfeb7525421a4edd2b5f2c60f06c4`  
**PR:** `#8` OPEN / DRAFT / NOT MERGED

> Regla documental: este checkpoint no auto-incrusta el SHA de su propio commit final. El HEAD exacto se consulta en GitHub/PR #8. Sí conserva SHAs de producto y runs de CI ya cerrados.

---

## 1. ESTADO

```text
F0-F4: CLOSED / VERIFIED / MERGED
F5_SPEC: APPROVED
F5_PLAN: APPROVED
F5.1_SCHEMA_PERMISSIONS: CLOSED / VERIFIED
F5.2_CANDIDATE_CORE_TOKEN: CLOSED / VERIFIED
F5.3_PUBLIC_START_CONTINUE: CLOSED / VERIFIED
F5.4_PROFILE_REFERENCES_UNIT_CHANGE: NOT STARTED
F5.5-F5.8: NOT STARTED
PR_READY: NO
MERGE_ALLOWED: NO
```

No abrir F5.4 sin retomar desde este checkpoint y reconfirmar HEAD / PR #8 / CI.

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

Leyes obligatorias:

- LEY 1 KISS;
- LEY 2 CERO RUIDO / CONVERGENCIA;
- RED antes de GREEN;
- no merge sin aprobación humana explícita.

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
- CI `38263081173`;
- PHP 8.3 / 8.4 / 8.5 PASS;
- PHP 8.3: **157 tests / 905 assertions / 0 failures / 0 errors**.

---

## 4. F5.2 — CANDIDATE CORE + SECURE CONTINUATION TOKEN

Producto principal:

- `app/Candidates/CandidateService.php`;
- `tests/Integration/Candidates/CandidateServiceTest.php`.

Contrato cerrado:

- token raw = 32 bytes aleatorios / 64 hex lowercase;
- solo SHA-256 del token persiste;
- TTL 7 días;
- expiración fail-closed;
- regeneración invalida token anterior;
- identidad `document_type + document_number` normalizada e inmutable;
- duplicado no crea segunda fila ni revela contexto privado;
- candidato nuevo solo sobre unidad publicable F4;
- candidato ya creado puede continuar aunque la unidad luego deje de publicarse;
- estados exactos: `new`, `incomplete`, `verification`, `pending_screening`, `withdrawn`;
- `submitByToken()` es el camino público F5.2 a `verification`;
- `withdrawn` es terminal.

`CandidateService` implementa:

- `startCandidate()`;
- `findByToken()`;
- `updateByToken()`;
- `submitByToken()`;
- `listCandidates()`;
- `findCandidate()`;
- `transitionStatus()`;
- `regenerateToken()`.

`changeUnitByToken()` y `replaceReferencesByToken()` siguen deliberadamente ausentes hasta F5.4; no hay stubs muertos.

GREEN F5.2:

- commit `511dc595b8265794f19dd3b5a04fef1ceaa62d36`;
- CI `38264989604`;
- PHP 8.3 / 8.4 / 8.5 PASS;
- PHP 8.3: **164 tests / 968 assertions / 0 failures / 0 errors**.

---

## 5. F5.3 — PUBLIC START + CONTINUE SURFACE

### Scope implementado

F5.3 agregó exactamente:

- `app/Candidates/routes.php` — único route file F5;
- `resources/views/public_apply_start.php`;
- `resources/views/public_candidate_continue.php`;
- wiring mínimo en `bootstrap/app.php`;
- `tests/Integration/Candidates/PublicCandidateApplyTest.php`.

No modificó:

- `CandidateService`;
- schema / migraciones;
- CSS;
- catálogo F4 ni CTA `Aplicar`;
- Composer / npm;
- autenticación/sesión existente;
- tablas o workers;
- requisitos/documentos/referencias/cambio de unidad.

### Rutas públicas cerradas

Exactamente:

- `GET /apply/{publicKey}`;
- `POST /apply/{publicKey}`;
- `GET /apply/continue/{token}`;
- `POST /apply/continue/{token}`.

Todas respetan `basePath` en forms y redirects.

### Start

- GET exige unidad actualmente publicable reutilizando `PropertyService::findPublicUnit()`;
- unidad no publicable o key inválida -> 404;
- formulario contiene únicamente identidad + nombre + teléfono + email + consentimiento;
- POST vuelve a validar publicabilidad;
- global CSRF existente protege el POST;
- identidad nueva -> crea exactamente un candidato y redirige 303 a `/apply/continue/{rawToken}`;
- URL pública usa solo token raw de 64 hex, nunca candidate ID ni hash;
- identidad duplicada -> 422 genérico sin token, nombre/email previo, unidad, estado ni contexto privado.

### Continue

- token inválido o expirado -> 404;
- token válido resuelve únicamente su candidato;
- HTML no expone `continuation_token_hash`, candidate ID, storage paths ni datos de otro candidato;
- POST usa CSRF global;
- guarda únicamente campos permitidos por `CandidateService::updateByToken()`;
- éxito -> 303 a la misma URL de continuación;
- F5.3 no implementa todavía referencias, cambio de unidad, documentos ni submit final.

### Throttle V1 KISS

Throttle local por sesión, sin Redis / tabla / worker / dependencia:

- bucket start: máximo 5 POST por ventana de 10 minutos;
- bucket continue: máximo 10 POST por ventana de 10 minutos;
- excedido -> 429 con mensaje genérico;
- petición bloqueada no muta BD.

---

## 6. TDD / EVIDENCIA F5.3

### RED

Commit: `c1fab238ecc4cc5c172ab9f36f1941ecd3758734`  
CI: `38270416673`

PHP 8.3 observado:

- **172 tests / 980 assertions / 8 failures**;
- los 8 fallos correspondieron exactamente a los 8 contratos nuevos F5.3 por ausencia de rutas/vistas;
- F1-F4 + F5.1/F5.2 permanecieron verdes.

Casos RED:

1. GET apply exige unidad publicable + formulario seguro;
2. POST apply CSRF + redirect raw token sin candidate ID;
3. duplicado genérico sin fuga;
4. token inválido/expirado + aislamiento entre candidatos;
5. continue POST actualiza solo candidato del token + CSRF;
6. throttle start 5 -> 429 en sexto sin mutación;
7. throttle continue 10 -> 429 en onceavo sin mutación;
8. compatibilidad subfolder/basePath.

### GREEN inicial y diagnóstico

HEAD de implementación inicial: `7e97c8bf65a6fe7464133fefe66d48eb6d70aaa3`  
CI: `38271152808`

Resultado PHP 8.3:

- **172 tests / 1049 assertions / 1 failure**;
- 7/8 pruebas F5.3 pasaron;
- toda la regresión restante pasó;
- el único fallo era una aserción defectuosa del test que exigía que la URL no contuviera el texto del ID `1`; un token hexadecimal aleatorio puede contener legítimamente el dígito `1`.

No se cambió producción para satisfacer ese falso fallo.

### Corrección mínima del test

Commit: `544d6c6f15a926a4b28d004bd32b5075aa9f2fcd`

Cambio único:

- eliminar la aserción aleatoria/imposible sobre el carácter textual del candidate ID;
- conservar la prueba fuerte del contrato mediante regex exacta `/apply/continue/[a-f0-9]{64}` + correspondencia SHA-256 del token + ausencia del hash público.

CI final producto F5.3: `38271564490`

- PHP 8.3: PASS;
- PHP 8.4: PASS;
- PHP 8.5: PASS;
- Composer validate --strict: PASS;
- PHP 8.3: **172 tests / 1050 assertions / 0 failures / 0 errors**;
- los 8 tests `PublicCandidateApply` pasan;
- regresión F1-F4 + F5.1/F5.2 completa verde.

---

## 7. F5.3 TWO LAWS / CERO RUIDO GATE

Diff funcional entre checkpoint F5.2 y GREEN F5.3:

```text
+ app/Candidates/routes.php                              196 líneas
+ resources/views/public_apply_start.php                 63 líneas
+ resources/views/public_candidate_continue.php          69 líneas
~ bootstrap/app.php                                       +8 líneas
+ tests/Integration/Candidates/PublicCandidateApplyTest.php
```

GitHub compare confirmó exactamente 5 archivos cambiados en el delta funcional.

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
F5_ROUTE_FILES_TOTAL: 1
F5_NEW_VIEWS: 2
CANDIDATE_SERVICE_CHANGED_IN_F5.3: NO
NEW_TABLES_F5.3: 0
DEPENDENCIES_DELTA: 0
NPM_DELTA: 0
SECOND_AUTH_OR_SESSION: NO
REDIS: NO
WORKER: NO
CATALOG_CTA_EARLY: NO
REFERENCES_EARLY: NO
DOCUMENTS_EARLY: NO
UNIT_CHANGE_EARLY: NO
```

Durante el wiring el conector GitHub generó commits intermedios/no-op por operaciones de Contents API. No añadieron contenido funcional adicional. La auditoría se hizo sobre el diff efectivo F5.2 -> F5.3, que queda limitado a los 5 archivos anteriores.

---

## 8. PR / GOBERNANZA

PR #8 debe permanecer:

- state: OPEN;
- draft: YES;
- merged: NO;
- merge no autorizado.

No marcar Ready: F5.4-F5.8 continúan abiertas.

---

## 9. SIGUIENTE MICROFASE — NO INICIADA

F5.4 — Profile Completion + References + Controlled Unit Change.

Orden exacto al retomar:

1. reconfirmar branch HEAD / PR #8 / CI de este checkpoint;
2. releer PLAN F5.4 y SPEC de perfil, referencias y cambio de unidad;
3. escribir RED de `CandidateServiceTest` + `CandidateReferencesTest` antes de producción;
4. verificar RED exacto;
5. ampliar el mismo `CandidateService`, sin segundo servicio para referencias;
6. ampliar el mismo `app/Candidates/routes.php` y la misma vista de continuación;
7. implementar guardado parcial `new -> incomplete`;
8. validar `monthly_income >= 0` si existe, `occupants_count >= 0` y boolean estricto de mascotas;
9. implementar referencias 0..N con reemplazo transaccional y sin IDs públicos;
10. implementar cambio controlado de unidad solo si `new|incomplete`, `submitted_at IS NULL`, cero `candidate_documents` y destino actualmente publicable;
11. cambio de unidad modifica el mismo `candidate.unit_id`; no crea segundo candidato ni historial paralelo;
12. ejecutar focal + full CI PHP 8.3/8.4/8.5;
13. aplicar Cero Ruido + actualizar este mismo checkpoint;
14. no abrir F5.5 hasta F5.4 CLOSED / VERIFIED.

Guardrails F5.4:

- no `CandidateReferenceService`;
- no segunda tabla de aplicaciones;
- no IDs de referencia públicos;
- no historial de cambios de unidad salvo evidencia funcional futura;
- no documentos runtime todavía;
- PR #8 sigue Draft;
- **NO MERGEAR sin aprobación humana explícita**.
