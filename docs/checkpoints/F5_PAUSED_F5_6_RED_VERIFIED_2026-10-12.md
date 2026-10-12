# F5 CHECKPOINT — PAUSED AFTER F5.6 VERIFIED RED

**Fecha:** 2026-10-12 (America/Bogota)  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Branch:** `impl/f5-candidates-20261012`  
**Base:** `main` @ `648401a4927bfeb7525421a4edd2b5f2c60f06c4`  
**PR:** `#8` OPEN / DRAFT / NOT MERGED

> Este checkpoint ordena **PARAR**. No continuar GREEN, no abrir F5.7, no marcar Ready y no mergear hasta una instrucción posterior explícita.

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
F5.6_ADMIN_CANDIDATES: RED VERIFIED / GREEN NOT STARTED
F5.7_CATALOG_CTA_LIFECYCLE: BLOCKED
F5.8_HARDENING_CLOSURE: BLOCKED
PR_READY: NO
MERGE_ALLOWED: NO
```

Producto certificado más reciente: `4907bf6e16d3c51190fd582cd3c5d136461ff3bc`  
Checkpoint F5.5: `12a31f1665de62fc335954a8312819f596af5f3c`  
HEAD antes de este checkpoint documental: `e3126366eb15fe8c1b2f7d8d47f6f46afc9e394e`.

## 2. Leyes y método obligatorios

- **LEY 1 — KISS:** solución directa, mínima, simple, eficiente y sin arquitectura anticipatoria.
- **LEY 2 — CERO RUIDO / CONVERGENCIA:** eliminar ruido probado; luego unir/simplificar/mejorar antes de añadir.
- TDD obligatorio: RED → verificar RED → GREEN mínimo → verificar GREEN → full CI → Cero Ruido → checkpoint.
- No repositories/DTO/factories/interfaces ni tercer servicio F5 sin necesidad demostrada.
- No patch-over-patch.
- No merge sin aprobación humana explícita.

## 3. F5.5 permanece cerrado

F5.5 sigue **CLOSED / VERIFIED**. No hubo cambios de producción después de su producto certificado durante la apertura de F5.6.

CI F5.5 final: `38287467062`:

```text
PHP 8.3: PASS — 192 tests / 1298 assertions
PHP 8.4: PASS
PHP 8.5: PASS
Composer validate --strict: PASS
SecurityBoundary: PASS
Failures: 0
Errors: 0
```

## 4. F5.6 — objetivo canónico

F5.6 es el módulo administrativo mínimo de candidatos. El PLAN autoriza estas rutas:

```text
GET  /admin/candidates
GET  /admin/candidates/{id}
POST /admin/candidates/{id}/status
POST /admin/candidates/{id}/token/regenerate
GET  /admin/candidates/{id}/documents/{documentId}
```

Contratos principales:
- `candidates.view`: listado, detalle y lectura privada de documentos;
- `candidates.manage`: cambios de estado y regeneración de token;
- owner bypass existente;
- forced-password gate existente;
- CSRF en mutaciones;
- basePath/subfolder preservado;
- SSR escapado;
- descarga documental anti-IDOR con ownership candidato+documento;
- `Content-Type` real, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`;
- token raw solo en la respuesta inmediata de regeneración;
- DB conserva solo hash;
- regenerar invalida el token anterior;
- administración NO puede simular submit/completitud para forzar `verification`;
- sin filtros/workflow extra hasta demostrar necesidad.

## 5. RED F5.6

Archivo único de RED:

```text
tests/Integration/Candidates/CandidatesAdminRoutesTest.php
```

Cobertura RED:
1. auth + `candidates.view` + forced-password + owner bypass;
2. launcher Apps visible solo a autorizado y con basePath;
3. listado/detalle SSR escapan candidato, referencia y nombre original de archivo;
4. status requiere `candidates.manage` y no permite `new -> verification`;
5. documento privado requiere view y bloquea cross-candidate IDOR;
6. regeneración muestra URL raw una vez, persiste solo hash, invalida token anterior y no vuelve a mostrar el nuevo token en detalle.

### RED inicial inválido — NO usar como evidencia funcional

Commit: `83ae035ab5acaff3a301daa086fb0c566435f5db`

El test anti-IDOR creó candidato B en otra unidad pero intentó adjuntarle un documento usando el requisito de la unidad A. Eso produjo un **error de fixture**, no una señal válida de producto.

Se corrigió únicamente el test. Producción permaneció intacta.

### RED corregido y válido

Commit: `e3126366eb15fe8c1b2f7d8d47f6f46afc9e394e`

Delta desde checkpoint F5.5 `12a31f16...`:

```text
FILES_CHANGED: 1
PRODUCTION_FILES_CHANGED: 0
TEST_FILES_ADDED: 1
ADDITIONS: 420
DELETIONS: 0
```

CI RED válido: `38288342190`

PHP 8.3:

```text
Tests: 198
Assertions: 1309
Failures: 6
Errors: 0
```

Los **6 fallos corresponden exactamente a los 6 contratos F5.6 nuevos**. Todo el comportamiento previo permaneció GREEN.

Fallos observados:
- `/admin/candidates` aún no existe → 500 global por `HttpNotFoundException` en vez del guard esperado;
- launcher no contiene Candidatos;
- listado/detalle admin aún no existen;
- status admin aún no existe;
- documento privado admin aún no existe;
- regeneración admin aún no existe.

PHP 8.4 y PHP 8.5 también concluyeron FAILURE en la misma ejecución RED, como corresponde a una superficie todavía no implementada.

## 6. Estado exacto del GREEN al pausar

**GREEN F5.6 NO FUE INICIADO EN GITHUB.**

Se alcanzó a preparar mentalmente la solución mínima, pero una llamada de edición de `CandidateService.php` fue interrumpida antes de ejecutarse. La verificación posterior del PR confirmó:

```text
HEAD = e3126366eb15fe8c1b2f7d8d47f6f46afc9e394e
```

Por tanto:
- `CandidateService.php` sigue en su estado anterior;
- `app/Candidates/routes.php` sigue sin rutas F5.6;
- no existen aún vistas admin F5.6;
- layout Apps aún no tiene tarjeta Candidatos;
- no existe commit GREEN parcial que recuperar.

## 7. Implementación KISS prevista al reanudar

No crear arquitectura nueva. Reutilizar:

```text
CandidateService
CandidateDocumentService
app/Candidates/routes.php
resources/views/layouts/app.php
```

Añadir únicamente las dos vistas SSR previstas por el PLAN:

```text
resources/views/candidates.php
resources/views/candidate_detail.php
```

CandidateService ya contiene:
- `listCandidates()`;
- `findCandidate()`;
- `transitionStatus()`;
- `regenerateToken()`.

La necesidad mínima adicional esperada es exponer referencias para el detalle administrativo sin crear otro servicio/repository. Antes de codificar, verificar si puede hacerse con una extensión pequeña del servicio existente.

`CandidateDocumentService` ya contiene:
- `listDocumentsForCandidate()`;
- `privateDocument(candidateId, documentId)` con ownership + validación física/MIME/tamaño.

No duplicar esa lógica en routes.

## 8. Orden exacto para reanudar

1. Confirmar PR #8 sigue OPEN / DRAFT / NOT MERGED.
2. Confirmar HEAD parte de este checkpoint y revisar que ningún cambio externo haya alterado el branch.
3. Releer este checkpoint y el PLAN F5.6.
4. Implementar GREEN mínimo sobre servicios existentes + mismo `app/Candidates/routes.php` + dos vistas SSR + launcher.
5. No permitir transición administrativa a `verification` desde `new`; usar contrato canónico existente.
6. Token raw solo en respuesta inmediata; no persistir ni re-renderizar después.
7. Documento privado: usar `CandidateDocumentService::privateDocument()`; no SQL/filesystem paralelo.
8. Ejecutar focal F5.6.
9. Ejecutar full CI PHP 8.3/8.4/8.5 + Composer + SecurityBoundary.
10. Corregir solo con evidencia de fallo; no parches preventivos.
11. Gate LEY 1 KISS + LEY 2 CERO RUIDO.
12. Actualizar checkpoint/PR solo cuando F5.6 quede CLOSED / VERIFIED.
13. F5.7 permanece bloqueada hasta entonces.

## 9. Deuda F5.8 preservada

No mezclar todavía sin RED:

`bootstrap/app.php` registra la ruta completa en errores no manejados. Una excepción sobre `/apply/continue/{token}` podría incluir el token raw de capacidad en logs. Debe tratarse en F5.8 como hardening explícito.

## 10. Estado del PR al pausar

Verificado antes de escribir este checkpoint:

```text
PR #8: OPEN
DRAFT: YES
MERGED: NO
MERGEABLE: YES
HEAD antes del checkpoint: e3126366eb15fe8c1b2f7d8d47f6f46afc9e394e
```

El body del PR todavía describe F5.6 como NOT STARTED; está documentalmente desactualizado respecto a este RED, pero **no se actualiza ahora** para respetar la orden de pausar. Corregirlo únicamente cuando se retome el trabajo o al cerrar F5.6.

---

# RECOVERY POINT

```text
F5.5 = CLOSED / VERIFIED
F5.6 = RED VALIDATED
RED_COMMIT = e3126366eb15fe8c1b2f7d8d47f6f46afc9e394e
RED_CI = 38288342190
PHP_8_3_RED = 198 tests / 1309 assertions / 6 failures / 0 errors
GREEN_F5.6 = NOT STARTED
PRODUCTION_DELTA_AFTER_F5.5 = 0
F5.7 = BLOCKED
F5.8 = BLOCKED
PR_8 = OPEN / DRAFT / NOT MERGED
ACTION = PAUSE
```
