# F5 CURRENT CHECKPOINT — F5.7 CLOSED / VERIFIED

**Fecha:** 2026-10-12 (America/Bogota)  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Branch:** `impl/f5-candidates-20261012`  
**Base:** `main` @ `648401a4927bfeb7525421a4edd2b5f2c60f06c4`  
**PR:** `#8` OPEN / DRAFT / NOT MERGED

> Producto F5.7 certificado: `9ecd5d5fe4817dd34cf0e81b84ea866457337d5f`. CI de certificación: `38290496383`.

## 1. Estado canónico

```text
F0-F4: CLOSED / VERIFIED / MERGED
F5_SPEC: APPROVED
F5_PLAN: APPROVED
F5.1_SCHEMA_PERMISSIONS: CLOSED / VERIFIED
F5.2_CANDIDATE_CORE_TOKEN: CLOSED / VERIFIED
F5.3_PUBLIC_START_CONTINUE: CLOSED / VERIFIED
F5.4_PROFILE_REFERENCES_UNIT_CHANGE: CLOSED / VERIFIED
F5.5_STRUCTURED_REQUIREMENTS_PRIVATE_DOCUMENTS: CLOSED / VERIFIED
F5.6_ADMIN_CANDIDATES: CLOSED / VERIFIED
F5.7_CATALOG_CTA_LIFECYCLE: CLOSED / VERIFIED
F5.8_HARDENING_CLOSURE: NOT STARTED / NEXT
PR_READY: NO
MERGE_ALLOWED: NO
```

Mantener PR Draft. **NO MERGEAR sin aprobación humana explícita.**

## 2. Leyes y método obligatorios

- **LEY 1 — KISS:** solución directa, mínima, simple y eficiente; no arquitectura anticipatoria.
- **LEY 2 — CERO RUIDO / CONVERGENCIA:** eliminar ruido probado; luego unir/simplificar/mejorar antes de añadir.
- TDD obligatorio: RED → verificar RED → GREEN mínimo → verificar GREEN completo → full CI → Cero Ruido → checkpoint.
- No repositories/DTO/factories/interfaces ni tercer servicio F5 sin necesidad demostrada.
- No patch-over-patch.
- No merge sin autorización humana explícita.

## 3. Arquitectura F5 preservada

Exactamente cuatro tablas F5:

```text
candidates
candidate_references
unit_application_requirements
candidate_documents
```

Exactamente dos permisos:

```text
candidates.view
candidates.manage
```

Exactamente dos servicios F5:

```text
CandidateService
CandidateDocumentService
```

No `leads`, no entidad paralela `applications`, no segundo auth/session, no repositories/DTO/factories/interfaces.

## 4. F5.1–F5.5 — CLOSED / VERIFIED

Permanecen cerrados. Producto F5.5: `4907bf6e16d3c51190fd582cd3c5d136461ff3bc`.  
CI F5.5 `38287467062`: PASS PHP 8.3/8.4/8.5; PHP 8.3 **192 tests / 1298 assertions**.

Contratos clave preservados:
- candidato único y token hash-only;
- continuación pública y perfil/referencias;
- cambio de unidad solo new|incomplete, target publicable y cero documentos;
- requisitos documentales estructurados;
- PDF/JPEG/PNG <=10 MiB con MIME real y storage privado;
- submit valida requisitos current required+active y archivo físico real;
- solo submit puede llegar a `verification`;
- ninguna operación F5 cambia `units.status`.

## 5. F5.6 — Administrative Candidates Module — CLOSED / VERIFIED

Producto: `b6dec56f74c2785206ccbfc4f9daa4bd25222be5`.  
CI: `38289573230` — PASS PHP 8.3/8.4/8.5; PHP 8.3 **198 tests / 1358 assertions**.

Rutas:

```text
GET  /admin/candidates
GET  /admin/candidates/{id}
POST /admin/candidates/{id}/status
POST /admin/candidates/{id}/token/regenerate
GET  /admin/candidates/{id}/documents/{documentId}
```

Contratos:
- `candidates.view`: list/detail/document read;
- `candidates.manage`: status/token regenerate;
- owner bypass, forced-password, CSRF y basePath existentes;
- SSR escapado;
- private document anti-IDOR + MIME real + `nosniff` + `private, no-store`;
- token raw solo en respuesta inmediata; DB hash-only; token anterior invalidado;
- admin no puede simular submit para forzar `verification`.

## 6. F5.7 RED — VALIDATED

RED final test-only: `7a57ff0366c59235c1cfae65561efbc3aa85ae10`.  
CI RED: `38290353921`.

PHP 8.3:

```text
Tests: 201
Assertions: 1376
Failures: 1
Errors: 0
```

Único fallo funcional:

```text
CandidateCatalogCtaTest::testPublicableDetailShowsApplyCtaUsingPublicKeyAndBasePathOnly
```

Causa exacta: `public_catalog_detail.php` aún no renderizaba `Aplicar -> /apply/{publicKey}`.

Todo lo demás quedó GREEN en el RED, incluido:
- non-publicable detail 404;
- F4 catalog list intacto;
- existing candidate survives unit unpublish/occupied;
- new start rechazado después de dejar de ser publicable;
- existing continuation sigue cargando y puede submit;
- submit no modifica `units.status`;
- cambio de unidad/doc guards históricos permanecen GREEN.

El antiguo test F4 que prohibía la palabra `Aplicar` en detalle fue actualizado porque F5.7 cambia explícitamente ese contrato; no se debilitó ninguna regla de privacidad.

## 7. F5.7 GREEN — KISS

Producto: `9ecd5d5fe4817dd34cf0e81b84ea866457337d5f`.

Delta de producción desde RED:

```text
FILES_CHANGED: 1
resources/views/public_catalog_detail.php: +1 / -0
```

Implementación:
- un único enlace `Aplicar`;
- URL construida con `basePath` + `/apply/` + `unit.public_key`;
- public key escapado;
- ningún ID interno en CTA;
- no cambio en `app/Properties/routes.php`;
- no cambio en CandidateService/PropertyService;
- no reservation flag;
- no contador de aplicaciones;
- no auto-`in_process`;
- no cache;
- no entidad nueva.

## 8. Certificación final F5.7

CI `38290496383`: **SUCCESS**.

```text
PHP 8.3: PASS — 201 tests / 1384 assertions
PHP 8.4: PASS
PHP 8.5: PASS
Composer validate --strict: PASS
SecurityBoundary: PASS
Failures: 0
Errors: 0
```

## 9. Two Laws / Cero Ruido — F5.7

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
PRODUCTION_FILES_CHANGED_FROM_RED: 1
PRODUCTION_LINES_ADDED: 1
NEW_TABLES: 0
NEW_SERVICES: 0
NEW_ROUTE_FILES: 0
ROUTES_CHANGED: 0
DEPENDENCIES_DELTA: 0
NPM_DELTA: 0
CSS_JS_DELTA: 0
AUTH_DELTA: 0
```

F5.7 = **CLOSED / VERIFIED**.

## 10. Próximo y último gate — F5.8

F5.8 — **Security + Visual QA + Zero-Noise Closure** queda habilitado ahora.

Plan obligatorio:
1. crear `CandidatesSecurityRegressionTest.php` y observar RED real;
2. incluir la deuda conocida: el error handler global no debe registrar raw continuation tokens en paths;
3. corregir solo defectos reproducidos;
4. visual QA en superficies F5 y CTA; CSS solo con reproducción real;
5. arquitectura/Two Laws final;
6. full CI PHP 8.3/8.4/8.5 sobre HEAD exacto;
7. converger README/SPEC/PLAN/checkpoint final;
8. revisar PR completo y marcar Ready solo si no queda blocker;
9. **PARAR antes de merge**.

No introducir framework de seguridad, Redis, nueva tabla o worker.

---

# RECOVERY POINT

```text
F5.5 = CLOSED / VERIFIED
F5.6 = CLOSED / VERIFIED
F5.7 = CLOSED / VERIFIED
F5.7_PRODUCT = 9ecd5d5fe4817dd34cf0e81b84ea866457337d5f
F5.7_CI = 38290496383
PHP_8_3 = 201 tests / 1384 assertions / 0 failures / 0 errors
F5.8 = NOT STARTED / NEXT
PR_8 = OPEN / DRAFT / NOT MERGED
MERGE_ALLOWED = NO
```
