# AlquilaPro — Checkpoint de pausa F5.5 / B01 GREEN escrito, CI pendiente

Fecha: 2026-10-12 (America/Bogota)  
Proyecto: AlquilaPro  
Repositorio: `sierraglobalcompany-rgb/AlquilaPro`  
Branch: `impl/f5-candidates-20261012`  
Base: `main` @ `648401a4927bfeb7525421a4edd2b5f2c60f06c4`  
PR: `#8` — OPEN / DRAFT / NOT MERGED  
HEAD de producto al crear este checkpoint: `d920798fe193fc68d40454d3132335e32a2dacbf`  
CI de ese HEAD al pausar: `38283495211` — `in_progress`

> PAUSA POR INSTRUCCIÓN HUMANA. No continuar implementación, no abrir UI, no abrir F5.6 y no mergear hasta nueva instrucción.

---

## 1. Estado ejecutivo

```text
F0-F4: CLOSED / VERIFIED / MERGED
F5_SPEC: APPROVED
F5_PLAN: APPROVED
F5.1: CLOSED / VERIFIED
F5.2: CLOSED / VERIFIED
F5.3: CLOSED / VERIFIED
F5.4: CLOSED / VERIFIED
F5.5_DOMAIN_REQUIREMENTS_DOCUMENTS: GREEN PREVIO / VERIFIED HASTA 23ef4428...
F5.5_AUDIT_B01_PHYSICAL_FILE_COMPLETENESS: GREEN ESCRITO / CI PENDIENTE
F5.5_B02_REQUIREMENT_CODE_POLICY: OPEN
F5.5_B03_UNIT_DETAIL_WIRING: OPEN
F5.5_BOOTSTRAP_ROUTES_UI: OPEN
F5.5_OVERALL: OPEN
F5.6-F5.8: BLOCKED
PR_READY: NO
MERGE_ALLOWED: NO
```

F5.5 NO está cerrada.

---

## 2. Leyes vinculantes

### LEY 1 — KISS

Solución simple, eficiente, optimizada y efectiva. Arquitectura mínima, de A a B. No crear capas, servicios, tablas, DTO, repositories, factories, interfaces, colas o abstracciones sin necesidad demostrada.

### LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

```text
ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR
```

No parche sobre parche. No duplicar fuentes de verdad.

### TDD

```text
RED → verificar RED → GREEN mínimo → verificar GREEN → full CI → converger → checkpoint
```

No declarar GREEN verificado mientras CI no lo certifique.

---

## 3. Auditoría de continuidad realizada antes de B01

Se contrastaron los dos handoffs con GitHub real y se reconfirmó:

- `main` sigue en `648401a4927bfeb7525421a4edd2b5f2c60f06c4`;
- PR #8 sigue OPEN / DRAFT / NOT MERGED;
- checkpoint previo real: `23ef4428eabac458297445c21efd7116f06b08c2`;
- CI previo `38277493358`: PASS PHP 8.3/8.4/8.5;
- PHP 8.3 previo: 183 tests / 1191 assertions;
- `CandidateDocumentService` existe y contiene validación física/MIME/tamaño mediante `privateDocument()`;
- `CandidateService::assertDocumentCompleteness()` en el estado previo solo comprobaba existencia de fila DB;
- B01 quedó confirmado como hueco real;
- B02 quedó confirmado: `createRequirement()` acepta cualquier código no vacío <=80 y no tiene policy sintáctica estricta;
- B03 quedó confirmado: `unit_detail.php` es renderizado por `app/Properties/routes.php`, que todavía no entrega requisitos F5 ni capability `candidates.manage`;
- `unit.requirements` F4 y `unit_application_requirements` F5 son conceptos distintos, no duplicación accidental;
- `main` no tiene protección de rama activa/ruleset visible, por lo que se mantiene guardrail humano NO MERGEAR;
- PR #8 conserva cuerpo documental viejo de F5.4 y está desactualizado respecto al estado F5.5;
- hallazgo adicional de auditoría para F5.8: el error handler global registra URI completa y las rutas de continuación llevan token raw; revisar sanitización de logs en hardening F5.8. NO mezclarlo en F5.5 sin RED específico.

---

## 4. B01 — RED demostrado

Objetivo:

> `submit` no puede tratar como satisfecho un requisito obligatorio solo porque exista la fila `candidate_documents`; el archivo físico también debe existir y seguir siendo válido.

Se añadieron dos pruebas a:

```text
tests/Integration/Candidates/CandidateDocumentsTest.php
```

Casos:

1. documento obligatorio con fila DB pero archivo físico borrado;
2. documento obligatorio con fila DB pero bytes físicos manipulados.

Commits test-only efectivos desde `23ef4428...`:

```text
6dd1e4738aece04ac695483a0e7cd2e09746f3b3
7da55e343eaae8c5ac004507815175b49943f454
```

Nota de Cero Ruido: el primer commit alteró accidentalmente el fixture JPEG existente al reemplazar el archivo completo. El segundo commit restauró exactamente ese fixture. Comparado `23ef4428...` → `7da55e34...`, el estado final efectivo fue:

```text
1 archivo modificado
77 líneas añadidas
0 líneas eliminadas
solo los dos tests B01
```

### CI RED

Run:

```text
38283199451
```

PHP 8.3:

```text
185 tests
1206 assertions
2 failures
0 errors
```

Fallos exactos:

```text
Submit rejects required document whose physical file was deleted
expected status: new
actual status: verification

Submit rejects required document whose physical file was tampered
expected status: new
actual status: verification
```

La regresión restante pasó. RED válido y causa raíz demostrada.

---

## 5. B01 — GREEN mínimo escrito, NO certificado aún

Commit de producción:

```text
d920798fe193fc68d40454d3132335e32a2dacbf
```

Archivo modificado:

```text
app/Candidates/CandidateService.php
```

Cambio aplicado:

- constructor de `CandidateService` admite opcionalmente `CandidateDocumentService`;
- `assertDocumentCompleteness()` dejó de limitarse a contar filas DB;
- ahora obtiene cada documento requerido activo y obligatorio;
- si falta fila → falla;
- si no existe servicio documental para validar físicamente → falla cerrado;
- si `CandidateDocumentService::privateDocument(candidateId, documentId)` devuelve `null` → falla;
- si todas las validaciones físicas pasan → permite continuar.

Intención KISS:

- reutilizar validación física ya existente en `CandidateDocumentService::privateDocument()`;
- no duplicar MIME/tamaño/path checks en `CandidateService`;
- no crear tercer servicio, repository, helper de filesystem, tabla ni compensador.

### ADVERTENCIA DE CONTINUIDAD

El GREEN está **escrito pero todavía no certificado**. El CI del HEAD `d920798f...` era `in_progress` al recibir la orden de pausar:

```text
CI: 38283495211
status al checkpoint: in_progress
```

Además, antes de cerrar B01 hay que verificar cuidadosamente el wiring de la nueva dependencia:

```text
CandidateService actualmente se instancia en muchos tests/rutas con new CandidateService($pdo)
```

El constructor mantiene compatibilidad sintáctica mediante dependencia opcional, pero para casos con requisitos documentales físicos la ausencia del servicio hace fail-closed. Si CI revela regresiones en escenarios válidos con documentos, NO relajar el fail-closed: resolver el wiring mínimo según TDD/KISS, probablemente reutilizando la misma instancia de `CandidateDocumentService` que F5.5 debe registrar en bootstrap.

No asumir que `d920798f...` es GREEN hasta inspeccionar CI.

---

## 6. Estado de los tres bloqueos de auditoría

### B01 — integridad física en submit

```text
RED: DEMOSTRADO
GREEN: ESCRITO
CI GREEN: PENDIENTE AL PAUSAR
ESTADO: OPEN HASTA CERTIFICACIÓN
```

### B02 — policy de requirement code

Confirmado por código actual:

```text
trim
no vacío
<= 80
unique por unidad
```

Falta RED para policy canónica estricta. Recomendación del handoff auditado:

```regex
^[a-z][a-z0-9_]{0,79}$
```

Pero NO implementar hasta fijar RED y reconfirmar que esa policy sigue siendo la decisión canónica.

### B03 — wiring de `unit_detail.php`

Confirmado:

- `resources/views/unit_detail.php` es renderizado por `app/Properties/routes.php`;
- hoy recibe propiedad/unidad/media/permisos F4;
- no recibe `applicationRequirements`;
- no recibe `canManageCandidateRequirements`;
- no meter SQL ni service construction dentro de la vista;
- no crear pantalla paralela para evitar tocar `Properties/routes.php`.

Solución esperada: wiring mínimo justificado en la ruta existente cuando llegue el RED de UI.

---

## 7. Orden exacto al reanudar

```text
1. Leer este checkpoint completo.
2. Reconfirmar HEAD de branch y PR #8.
3. Inspeccionar resultado final del CI 38283495211.
4. Si CI falla, clasificar primero la causa; no parchear a ciegas.
5. Cerrar B01 solo cuando los dos tests nuevos y la suite completa estén GREEN en PHP 8.3/8.4/8.5.
6. Mantener fail-closed físico; resolver wiring mínimo si es la causa.
7. Después abrir B02 con RED propio.
8. Después abrir el segundo RED de bootstrap/rutas/UI F5.5.
9. Resolver B03 solo dentro de ese RED/UI.
10. Full CI + Cero Ruido + checkpoint.
11. Solo entonces declarar F5.5 CLOSED / VERIFIED.
12. F5.6 permanece bloqueada hasta cierre real de F5.5.
```

---

## 8. Prohibiciones al reanudar

- NO mergear PR #8.
- NO marcar PR Ready todavía.
- NO abrir F5.6.
- NO crear tablas nuevas.
- NO crear más servicios F5.
- NO repositories/DTO/factories/interfaces.
- NO duplicar validación de filesystem/MIME si puede converger en `CandidateDocumentService`.
- NO debilitar fail-closed para hacer pasar tests.
- NO SQL en vistas.
- NO nueva pantalla paralela para requisitos.
- NO nuevo auth/CSRF/throttle.
- NO CSS/JS salvo defecto reproducido.
- NO mezclar el hardening de logs/tokens F5.8 dentro de B01/B02/B03 sin RED.
- NO reescribir historia/force-push salvo instrucción humana explícita.

---

## 9. Punto corto de recuperación

```text
RESUME_FROM:
F5.5 / B01 physical required-document completeness
RED verified by CI 38283199451
GREEN written at d920798fe193fc68d40454d3132335e32a2dacbf
CI 38283495211 was still in_progress when paused
first action: inspect that CI, then fix only proven wiring/regression if needed
B02 and B03 remain unopened
PR #8 must remain Draft / NOT MERGED
```
