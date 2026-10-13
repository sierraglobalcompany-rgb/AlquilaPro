# F7 REVIEW FIXES — CHECKPOINT

**Fecha:** 2026-10-13 (America/Bogota)  
**Estado:** PAUSED / REVIEW FIXES IN PROGRESS / NOT MERGED  
**Branch:** `impl/f7-contracts-20261012`  
**PR:** `#10` — `F7: Contract engine`  
**Base main:** `1b9ee85ebb224b310d4ab4f0c84a28628b86b747`

## 1. Punto exacto de reanudación

F7 estaba técnicamente cerrado, pero la revisión posterior abrió correcciones acotadas antes de permitir merge.

No abrir F8. No mergear F7 todavía.

Orden acordado:

1. `F7-CONC-01` — carrera de `generate()` al congelar evidencia contractual.
2. P2 capacidad JSON — alinear tipos SQL con los límites de entrada ya aprobados.
3. P2 storage/runtime — cerrar la comprobación pendiente sin crear arquitectura paralela.
4. Reauditar F7 completo, actualizar cierre y solo entonces evaluar merge.

Leyes obligatorias:

- **KISS:** simple, eficiente, optimizado, eficaz, arquitectura directa, sin capas innecesarias.
- **Cero Ruido:** no conservar piezas sin función demostrable; antes de eliminar, comprobar si se elimina, mejora, une o optimiza.

## 2. F7-CONC-01 — CERRADO EN GREEN

### RED reproducible

Commit RED:

`bdbe38012d42206fef50157e930d91348b55d5be`

Test añadido en:

`tests/Integration/Contracts/ContractSecurityRegressionTest.php`

Contrato del RED:

- `generate()` debe iniciar transacción;
- bloquear la fila `contracts` con `FOR UPDATE`;
- solo después resolver snapshot/variables y renderizar PDF.

El código previo resolvía/renderizaba evidencia antes de adquirir el lock, dejando una ventana real para dos generaciones concurrentes sobre el mismo draft.

CI RED confirmó exactamente un fallo esperado:

- PHP 8.3: `275 tests / 2224 assertions / 1 failure`;
- fallo: `generate() must lock the draft before resolving snapshot values`.

### GREEN mínimo

Commit GREEN:

`6fec34e675456b8c0ca9a12b2922a8111526f541`

Cambio productivo:

- se movió `beginTransaction()` + `SELECT status FROM contracts ... FOR UPDATE` antes de releer, resolver y renderizar evidencia;
- tras adquirir el lock se relee el contrato dentro de la misma transacción;
- se mantuvo el mismo `ContractService`;
- no se añadió estado `generating`;
- no se añadió tabla, servicio, lock externo, worker ni arquitectura paralela;
- se conserva cleanup fail-closed de temporales/PDF parcial.

CI GREEN:

- run `38359695161`;
- PHP 8.3: PASS;
- PHP 8.4: PASS;
- PHP 8.5: PASS;
- run completo: SUCCESS;
- suite: `275 tests / 2225 assertions` en la referencia verificada.

**Resultado:** `F7-CONC-01 = CLOSED / VERIFIED`.

## 3. P2 CAPACIDAD JSON — ABIERTO EN RED

Commit RED actual antes de este checkpoint:

`8d497228d696b44f267178b2d39ae35901b393f4`

Test añadido a:

`tests/Integration/Contracts/ContractSchemaTest.php`

Nuevo contrato de capacidad:

- `contracts.manual_variables_json` debe ser `LONGTEXT`;
- `contracts.tasks_json` puede seguir siendo `TEXT`;
- `contracts.annexes_json` debe ser `LONGTEXT`.

Razón:

- variables manuales admiten hasta 50 declaraciones y cada valor puede llegar a 4.000 caracteres, por lo que el agregado puede superar `TEXT`;
- tareas tienen 30 × 190 caracteres y caben holgadamente en `TEXT`;
- anexos admiten hasta 20 entradas y `note` no tiene un tope funcional aprobado que garantice caber en `TEXT`.

El objetivo es corregir solo las dos columnas cuyo presupuesto funcional excede el tipo actual, sin convertir columnas innecesarias ni inventar límites nuevos.

### RED confirmado

CI run:

`38359931108`

PHP 8.3 confirmó:

- `276 tests / 2226 assertions / 1 failure`;
- fallo exacto: `Contract json capacity matches approved input bounds`;
- esperado `longtext`, actual `text`.

No se ha aplicado todavía ningún cambio productivo a la migración/esquema para este P2.

**Resultado actual:** `P2 JSON CAPACITY = RED / REPRODUCIBLE / FIX PENDING`.

## 4. Estado de arquitectura al pausar

No se ha introducido arquitectura nueva durante estas correcciones.

Se mantienen los guardrails F7:

```text
NEW_TABLES: 3
NEW_DOMAIN_CLASSES: 2
NEW_PERMISSIONS: 2
NEW_ROUTE_FILES: 1
NEW_DIRECT_RUNTIME_DEPENDENCIES: 1
NEW_PUBLIC_ROUTES: 0
NEW_AUTH_SYSTEMS: 0
NEW_LEASE/TENANT_TABLES: 0
```

Estados contractuales siguen siendo exactamente:

`draft → generated → finalized`

No crear:

- `generating`;
- locks distribuidos;
- colas/workers;
- tabla de evidencias paralela;
- repositorios/DTO/interfaces contractuales adicionales;
- segundo almacenamiento;
- segundo renderer PDF;
- tenant/lease F8 antes de cerrar F7.

## 5. Próximo paso exacto al reanudar

Reanudar desde el RED de capacidad en `ContractSchemaTest`.

GREEN esperado más pequeño:

1. cambiar únicamente `manual_variables_json` y `annexes_json` de `TEXT` a `LONGTEXT` en la migración F7 aplicable al esquema limpio;
2. conservar `tasks_json` como `TEXT`;
3. ejecutar CI completo PHP 8.3/8.4/8.5;
4. si GREEN, abrir por separado el P2 de storage/runtime con RED propio;
5. después actualizar `F7_CLOSED_2026-10-12.md` con las nuevas evidencias;
6. no mergear hasta una nueva auditoría final explícita.

## 6. Gate al detenerse

```text
F7-CONC-01: GREEN / VERIFIED / CLOSED
P2-JSON-CAPACITY: RED / REPRODUCIBLE / OPEN
P2-STORAGE-RUNTIME: NOT STARTED IN THIS CYCLE
F8: NOT STARTED
PR #10: NOT MERGED
MERGE_ALLOWED: NO
PAUSE_POINT: after RED capacity proof, before production schema fix
```

Este checkpoint es el punto canónico de continuidad para retomar la revisión correctiva de F7 sin perder el RED actual ni mezclarlo con el siguiente P2.
