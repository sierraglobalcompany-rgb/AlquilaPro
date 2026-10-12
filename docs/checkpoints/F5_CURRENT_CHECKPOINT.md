# F5 CURRENT CHECKPOINT — F5.6 CLOSED / VERIFIED

**Fecha:** 2026-10-12 (America/Bogota)  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Branch:** `impl/f5-candidates-20261012`  
**Base:** `main` @ `648401a4927bfeb7525421a4edd2b5f2c60f06c4`  
**PR:** `#8` OPEN / DRAFT / NOT MERGED

> Producto F5.6 certificado: `b6dec56f74c2785206ccbfc4f9daa4bd25222be5`. CI de certificación: `38289573230`.

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
F5.7_CATALOG_CTA_LIFECYCLE: NOT STARTED
F5.8_HARDENING_CLOSURE: NOT STARTED
PR_READY: NO
MERGE_ALLOWED: NO
```

Mantener PR Draft. **NO MERGEAR sin aprobación humana explícita.**

## 2. Leyes y método obligatorios

Autoridad: README → SPEC maestro → PLAN maestro → SPEC F5 → PLAN F5 → checkpoint → tests/CI → código real.

- **LEY 1 — KISS:** solución directa, mínima, simple y eficiente; no arquitectura anticipatoria.
- **LEY 2 — CERO RUIDO / CONVERGENCIA:** eliminar ruido probado; luego unir/simplificar/mejorar antes de añadir.
- TDD obligatorio: RED → verificar RED → GREEN mínimo → verificar GREEN completo → full CI → Cero Ruido → checkpoint.
- No repositories/DTO/factories/interfaces ni tercer servicio F5 sin necesidad demostrada.
- No patch-over-patch.
- No merge sin autorización humana explícita.

## 3. Arquitectura F5 preservada

F5 continúa con exactamente cuatro tablas:

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

No existen `leads`, entidad paralela `applications`, segundo auth/session, repositories, DTOs, factories ni interfaces F5.

## 4. F5.1–F5.4 — CLOSED / VERIFIED

Permanecen cerrados sin cambios de contrato:
- schema/permisos F5;
- candidato único + token de continuación hash-only;
- inicio/continuación pública;
- perfil, referencias y cambio controlado de unidad;
- solo submit público puede llevar a `verification`;
- transiciones administrativas respetan la máquina de estados canónica.

## 5. F5.5 — Structured Requirements + Private Candidate Documents — CLOSED / VERIFIED

Producto certificado F5.5: `4907bf6e16d3c51190fd582cd3c5d136461ff3bc`.  
CI: `38287467062` — PASS PHP 8.3/8.4/8.5. PHP 8.3: **192 tests / 1298 assertions**.

Contratos cerrados:
- requisitos documentales estructurados por unidad;
- code route-safe `[A-Za-z0-9_-]+`, máximo 80, inmutable, sin forzar lowercase;
- no delete físico de requisitos: se inactivan;
- PDF/JPEG/PNG <= 10 MiB, MIME real, nombre generado y storage privado;
- reemplazo seguro por candidate+requirement;
- `privateDocument()` valida ownership, existencia física, MIME y tamaño persistidos;
- submit exige requisitos actuales required+active con archivo físicamente válido;
- mismo `app/Candidates/routes.php`, sin route file paralelo;
- mismo `unit_detail.php` y `public_candidate_continue.php`;
- `candidates.manage` administra requisitos sin heredar datos/media privados F4;
- `properties.view` conserva visibilidad F4;
- CSRF, forced-password, basePath y throttle existentes reutilizados.

Rutas F5.5:

```text
POST /admin/units/{id}/application-requirements
POST /admin/units/{id}/application-requirements/{requirementId}
POST /admin/units/{id}/application-requirements/order
POST /apply/continue/{token}/documents/{requirementCode}
POST /apply/continue/{token}/submit
```

## 6. F5.6 RED — VALIDATED

Archivo de RED:

```text
tests/Integration/Candidates/CandidatesAdminRoutesTest.php
```

RED válido: `e3126366eb15fe8c1b2f7d8d47f6f46afc9e394e`.  
CI RED: `38288342190`.

PHP 8.3:

```text
198 tests
1309 assertions
6 failures
0 errors
```

Los seis fallos correspondían exactamente a los seis contratos F5.6 aún ausentes:
1. auth + `candidates.view` + forced-password + owner bypass;
2. launcher Apps autorizado + basePath;
3. listado/detalle SSR con escape de candidato, referencia y filename;
4. mutación de estado con `candidates.manage`, sin simular submit;
5. documento privado con `candidates.view` + anti-IDOR;
6. regeneración de token one-shot, hash-only e invalidación del token anterior.

Un RED inicial previo fue descartado porque contenía un error de fixture documental cross-unit; solo el RED corregido anterior es evidencia funcional.

## 7. F5.6 GREEN — implementación KISS

Producto certificado: `b6dec56f74c2785206ccbfc4f9daa4bd25222be5`.

Rutas añadidas al **mismo** `app/Candidates/routes.php`:

```text
GET  /admin/candidates
GET  /admin/candidates/{id}
POST /admin/candidates/{id}/status
POST /admin/candidates/{id}/token/regenerate
GET  /admin/candidates/{id}/documents/{documentId}
```

Implementación:
- `CandidateService` existente amplió solo la lectura administrativa necesaria;
- `CandidateDocumentService::privateDocument()` se reutiliza para descarga, sin SQL/filesystem paralelo;
- `candidates.view` controla list/detail/document read;
- `candidates.manage` controla status/token regenerate;
- owner bypass y forced-password gate existentes;
- POST sigue protegido por CSRF global;
- basePath/subfolder preservado;
- SSR escapado;
- documento privado exige candidate+document ownership y responde MIME real, `nosniff`, `private, no-store`;
- token raw aparece únicamente en la respuesta inmediata de regeneración;
- DB guarda solo hash; regenerar invalida el anterior;
- detalle posterior no vuelve a renderizar el token raw;
- transición administrativa no puede forzar `new -> verification`;
- launcher Apps muestra Candidatos solo a autorizado.

Vistas nuevas previstas por el PLAN, y solo esas:

```text
resources/views/candidates.php
resources/views/candidate_detail.php
```

## 8. Primer GREEN y corrección mínima

Primer GREEN: `3f8606bb2dc6f0b112641889b5232e1d69f63054`.  
CI `38289338221`:

```text
PHP 8.3: 198 tests / 1357 assertions / 1 failure
F5.6 focal: ALL GREEN
```

Único fallo: regresión histórica de `ShellRoutesTest` porque el texto del launcher contenía la etiqueta reservada `Solicitudes`.

Corrección mínima: `b6dec56f74c2785206ccbfc4f9daa4bd25222be5` cambió únicamente el copy del launcher a `Candidaturas y documentación`. No hubo cambio de arquitectura ni contrato.

## 9. Certificación final F5.6

CI: `38289573230` — **SUCCESS**.

```text
PHP 8.3: PASS — 198 tests / 1358 assertions
PHP 8.4: PASS
PHP 8.5: PASS
Composer validate --strict: PASS
SecurityBoundary: PASS
Failures: 0
Errors: 0
```

Los seis tests `CandidatesAdminRoutesTest` están GREEN y toda la regresión histórica permanece GREEN.

Warnings de MariaDB `io_uring` y deprecación Node 20 de `actions/checkout@v4` son ruido de runner, no fallos del producto.

## 10. Two Laws / Cero Ruido — F5.6

Delta de producto desde checkpoint previo `16f190bfe20483010a95282398f43dfe5e786cd5` hasta producto `b6dec56f74c2785206ccbfc4f9daa4bd25222be5`:

```text
FILES_CHANGED: 5
NEW_TABLES: 0
NEW_SERVICES: 0
TOTAL_F5_SERVICES: 2
NEW_ROUTE_FILES: 0
NEW_VIEWS: 2 previstas por PLAN
DEPENDENCIES_DELTA: 0
NPM_DELTA: 0
CSS_JS_DELTA: 0
SECOND_AUTH_OR_SESSION: NO
REPOSITORY_DTO_FACTORY_INTERFACE_DELTA: 0
```

Archivos de producto tocados:
- `app/Candidates/CandidateService.php`
- `app/Candidates/routes.php`
- `resources/views/candidates.php`
- `resources/views/candidate_detail.php`
- `resources/views/layouts/app.php`

Conclusión:

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
F5.6: CLOSED / VERIFIED
```

## 11. Deuda explícita reservada para F5.8

No mezclar antes de su RED:

`bootstrap/app.php` registra la ruta completa en errores no manejados. Las rutas `/apply/continue/{token}` contienen el token raw de capacidad; una excepción podría incluirlo en logs. Debe cerrarse como hardening F5.8.

## 12. Próximo gate — F5.7

**F5.7 — Catalog CTA + Lifecycle** queda habilitado únicamente porque F5.6 ya está certificado.

Antes de implementar:
1. releer el bloque F5.7 del PLAN/SPEC;
2. congelar alcance exacto;
3. crear RED mínimo;
4. verificar RED;
5. GREEN mínimo reutilizando F4/F5 existentes;
6. full CI PHP 8.3/8.4/8.5;
7. Cero Ruido + checkpoint.

No abrir F5.8 hasta cerrar F5.7.

---

# RECOVERY POINT

```text
F5.5 = CLOSED / VERIFIED
F5.6 = CLOSED / VERIFIED
F5.6_PRODUCT = b6dec56f74c2785206ccbfc4f9daa4bd25222be5
F5.6_CI = 38289573230
PHP_8_3 = 198 tests / 1358 assertions / 0 failures / 0 errors
F5.7 = NOT STARTED / NEXT
F5.8 = BLOCKED
PR_8 = OPEN / DRAFT / NOT MERGED
MERGE_ALLOWED = NO
```
