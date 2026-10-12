# F5 CHECKPOINT — PAUSED WITH F5.6 GREEN PENDING CI

**Fecha:** 2026-10-12 (America/Bogota)  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Branch:** `impl/f5-candidates-20261012`  
**Base:** `main` @ `648401a4927bfeb7525421a4edd2b5f2c60f06c4`  
**PR:** `#8` OPEN / DRAFT / NOT MERGED

> Este checkpoint ordena **PARAR**. No continuar implementación, no abrir F5.7/F5.8, no marcar Ready y no mergear hasta una instrucción posterior explícita.

## 1. Estado canónico al pausar

```text
F0-F4: CLOSED / VERIFIED / MERGED
F5_SPEC: APPROVED
F5_PLAN: APPROVED
F5.1_SCHEMA_PERMISSIONS: CLOSED / VERIFIED
F5.2_CANDIDATE_CORE_TOKEN: CLOSED / VERIFIED
F5.3_PUBLIC_START_CONTINUE: CLOSED / VERIFIED
F5.4_PROFILE_REFERENCES_UNIT_CHANGE: CLOSED / VERIFIED
F5.5_STRUCTURED_REQUIREMENTS_PRIVATE_DOCUMENTS: CLOSED / VERIFIED
F5.6_ADMIN_CANDIDATES: GREEN IMPLEMENTED / CI RE-RUN IN PROGRESS / NOT YET CLOSED
F5.7_CATALOG_CTA_LIFECYCLE: BLOCKED
F5.8_HARDENING_CLOSURE: BLOCKED
PR_READY: NO
MERGE_ALLOWED: NO
```

Producto F5.5 certificado: `4907bf6e16d3c51190fd582cd3c5d136461ff3bc`  
RED F5.6 válido: `e3126366eb15fe8c1b2f7d8d47f6f46afc9e394e`  
Checkpoint previo RED: `16f190bfe20483010a95282398f43dfe5e786cd5`  
HEAD de producto al pausar antes de este checkpoint: `b6dec56f74c2785206ccbfc4f9daa4bd25222be5`.

## 2. Leyes y método obligatorios

- **LEY 1 — KISS:** solución directa, mínima, simple, eficiente y sin arquitectura anticipatoria.
- **LEY 2 — CERO RUIDO / CONVERGENCIA:** eliminar ruido probado; luego unir/simplificar/mejorar antes de añadir.
- TDD obligatorio: RED → verificar RED → GREEN mínimo → verificar GREEN → full CI → Cero Ruido → checkpoint.
- No repositories/DTO/factories/interfaces ni tercer servicio F5 sin necesidad demostrada.
- No patch-over-patch.
- No merge sin aprobación humana explícita.

## 3. F5.5 permanece cerrado

F5.5 sigue **CLOSED / VERIFIED**.

CI final F5.5: `38287467062`:

```text
PHP 8.3: PASS — 192 tests / 1298 assertions
PHP 8.4: PASS
PHP 8.5: PASS
Composer validate --strict: PASS
SecurityBoundary: PASS
Failures: 0
Errors: 0
```

## 4. F5.6 RED válido preservado

Archivo RED:

```text
tests/Integration/Candidates/CandidatesAdminRoutesTest.php
```

Commit RED válido: `e3126366eb15fe8c1b2f7d8d47f6f46afc9e394e`  
CI RED: `38288342190`

PHP 8.3:

```text
Tests: 198
Assertions: 1309
Failures: 6
Errors: 0
```

Los seis fallos correspondían exactamente a los seis contratos F5.6 nuevos:
1. auth + `candidates.view` + forced-password + owner bypass;
2. launcher Apps autorizado con basePath;
3. listado/detalle SSR escapado;
4. transición administrativa con `candidates.manage` sin simular submit;
5. documento privado anti-IDOR;
6. regeneración segura de token raw one-shot.

## 5. GREEN F5.6 implementado

La implementación quedó limitada a cinco piezas de producción previstas, sin arquitectura nueva:

```text
app/Candidates/CandidateService.php
app/Candidates/routes.php
resources/views/candidates.php
resources/views/candidate_detail.php
resources/views/layouts/app.php
```

No se añadieron:
- tablas;
- servicios;
- route files;
- repositories;
- DTOs;
- factories;
- interfaces;
- dependencias Composer/npm;
- CSS/JS.

### 5.1 CandidateService

Se enriquecieron solo las lecturas administrativas existentes:
- `listCandidates()` ahora incluye contexto mínimo de unidad/propiedad mediante JOIN directo;
- `findCandidate()` incluye el mismo contexto y las referencias existentes;
- se reutilizan `transitionStatus()` y `regenerateToken()` sin crear otra capa.

### 5.2 Rutas F5.6 añadidas en el mismo `app/Candidates/routes.php`

```text
GET  /admin/candidates
GET  /admin/candidates/{id}
POST /admin/candidates/{id}/status
POST /admin/candidates/{id}/token/regenerate
GET  /admin/candidates/{id}/documents/{documentId}
```

Contratos aplicados:
- lectura requiere `candidates.view`;
- mutaciones requieren `candidates.manage`;
- owner bypass existente;
- forced-password gate reutilizado;
- CSRF global reutilizado;
- basePath preservado;
- transición usa `CandidateService::transitionStatus()`;
- descarga usa `CandidateDocumentService::privateDocument(candidateId, documentId)`;
- anti-IDOR queda por ownership candidato+documento;
- documento responde MIME real + `X-Content-Type-Options: nosniff` + `Cache-Control: private, no-store`;
- regeneración usa `CandidateService::regenerateToken()`;
- token raw solo se devuelve en la respuesta inmediata de regeneración;
- DB conserva solo hash;
- token anterior queda invalidado.

### 5.3 SSR

Se añadieron exactamente dos vistas:

```text
resources/views/candidates.php
resources/views/candidate_detail.php
```

Incluyen escape HTML de candidato, referencias y nombre original de documento. No exponen `stored_name` ni storage path.

### 5.4 Launcher

`resources/views/layouts/app.php` añade tarjeta **Candidatos** solo cuando `candidates.view` está permitido.

## 6. Primer GREEN CI

HEAD evaluado: `3f8606bb2dc6f0b112641889b5232e1d69f63054`  
CI: `38289338221`

Resultado PHP 8.3:

```text
Tests: 198
Assertions: 1357
Failures: 1
```

Dato importante: **los seis tests F5.6 quedaron GREEN**.

El único fallo restante fue una regresión histórica de copy en:

```text
tests/Integration/UI/ShellRoutesTest.php::testAuthenticatedRootRendersSemanticMobileShell
```

Causa exacta:
- launcher Candidatos usó el texto `Solicitudes y documentación`;
- el contrato histórico del shell prohíbe la palabra `Solicitudes` como etiqueta futura/reservada.

No fue un fallo de arquitectura, seguridad ni dominio F5.6.

Composer validate --strict pasó antes de tests. SecurityBoundary también apareció GREEN dentro de la suite.

PHP 8.4 y 8.5 fallaron en la misma ejecución por el mismo run de suite.

## 7. Corrección mínima después del primer GREEN

Commit: `b6dec56f74c2785206ccbfc4f9daa4bd25222be5`

Único cambio:

```text
Solicitudes y documentación
→
Candidaturas y documentación
```

Archivo:

```text
resources/views/layouts/app.php
```

No hubo ningún otro cambio de producción.

## 8. Segundo CI al momento de pausar

CI asociado a `b6dec56f74c2785206ccbfc4f9daa4bd25222be5`:

```text
RUN_ID: 38289573230
STATUS: IN_PROGRESS
CONCLUSION: pending
```

Por instrucción humana se pausa **sin esperar el resultado**.

Por tanto F5.6 todavía NO puede marcarse CLOSED / VERIFIED.

## 9. Diff KISS observado antes del segundo CI

Desde checkpoint previo `16f190bf...` hasta primer GREEN `3f8606bb...`:

```text
FILES_CHANGED: 5
app/Candidates/CandidateService.php: +21 / -3
app/Candidates/routes.php: +125 / -0
resources/views/candidate_detail.php: +112 / -0
resources/views/candidates.php: +36 / -0
resources/views/layouts/app.php: +8 / -1
TOTAL: +302 / -4
```

Luego `b6dec56f...` cambió solo una línea de copy en el layout.

No se detectó ruido arquitectónico.

## 10. Orden exacto para reanudar

1. Leer este checkpoint.
2. Confirmar PR #8 sigue OPEN / DRAFT / NOT MERGED.
3. Confirmar HEAD parte de este checkpoint y que no hubo cambios externos.
4. Consultar resultado de CI `38289573230`.
5. Si CI está GREEN en PHP 8.3/8.4/8.5, extraer conteos finales de PHP 8.3 y verificar Composer/SecurityBoundary.
6. Si falla, leer logs y corregir únicamente la causa demostrada.
7. Ejecutar gate LEY 1 KISS + LEY 2 CERO RUIDO sobre el diff final.
8. Solo entonces marcar F5.6 **CLOSED / VERIFIED** y actualizar checkpoint/PR.
9. F5.7 permanece bloqueada hasta cierre formal de F5.6.
10. F5.8 permanece bloqueada.
11. Mantener PR Draft.
12. **NO MERGEAR sin aprobación humana explícita.**

## 11. Deuda F5.8 preservada

No mezclar sin RED:

`bootstrap/app.php` registra la ruta completa en errores no manejados. Una excepción sobre `/apply/continue/{token}` podría incluir el token raw de capacidad en logs. Debe tratarse en F5.8 como hardening explícito.

## 12. Estado del PR al pausar

Verificado inmediatamente antes de escribir este checkpoint:

```text
PR #8: OPEN
DRAFT: YES
MERGED: NO
MERGEABLE: YES
HEAD antes del checkpoint: b6dec56f74c2785206ccbfc4f9daa4bd25222be5
```

El body del PR sigue documentalmente desactualizado y aún marca F5.6 como NOT STARTED. No se actualiza durante esta pausa. Debe sincronizarse solo al reanudar/cerrar F5.6.

---

# RECOVERY POINT

```text
F5.5 = CLOSED / VERIFIED
F5.6_RED = VERIFIED
F5.6_GREEN = IMPLEMENTED
FIRST_GREEN_CI = 38289338221
FIRST_GREEN_RESULT = 198 tests / 1357 assertions / 1 failure in PHP 8.3
F5.6_NEW_TESTS = ALL GREEN in first GREEN CI
ONLY_FIRST_GREEN_FAILURE = historical shell copy contract
COPY_FIX_COMMIT = b6dec56f74c2785206ccbfc4f9daa4bd25222be5
SECOND_CI = 38289573230 IN_PROGRESS
F5.6 = NOT YET CLOSED / VERIFIED
F5.7 = BLOCKED
F5.8 = BLOCKED
PR_8 = OPEN / DRAFT / NOT MERGED
ACTION = PAUSE
```
