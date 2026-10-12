# F4 CHECKPOINT — TASK 1 SCHEMA WRITTEN / GREEN PENDING

**Fecha:** 2026-10-11  
**Fase:** F4 — Propiedades + Unidades + Landing + Catálogo  
**Rama:** `impl/f4-properties-catalog-20261011`  
**PR:** #7 — OPEN / DRAFT / NO MERGE  
**Base de implementación:** `940ebfae25a05d1589fe1ab5ceeaf667c2d08345`  
**HEAD de producto antes de este checkpoint:** `344aeaaae3d66b204179e736a2469758e8128a84`

---

## 1. ESTADO GENERAL

- SPEC F4: aprobada y merged en `main`.
- PLAN F4: aprobado y merged en `main`.
- Task 1 — Schema + Permissions: **EN PROGRESO / NO GREEN**.
- Task 2+: **NO INICIADAS**.
- PR #7 permanece Draft y no debe fusionarse.

## 2. TDD REAL EJECUTADO

Se escribieron primero los contratos RED:

- `tests/Integration/Properties/PropertiesSchemaTest.php`;
- actualización de `tests/Integration/Foundation/MigrationRunnerTest.php` para esperar migration 004;
- actualización de `tests/Integration/Auth/UserServiceTest.php` para exigir `properties.view` y `properties.manage`.

Primer RED tuvo un error de test en la consulta `GROUP_CONCAT ... SEPARATOR`; se corrigió antes de tocar producción.

RED limpio confirmado después:

- 97 tests;
- 454 assertions;
- 5 failures;
- 0 errors.

Los cinco fallos correspondían exactamente a:

1. migration 004 ausente;
2. tablas F4 ausentes;
3. índices F4 ausentes;
4. foreign keys F4 ausentes;
5. permisos `properties.view/manage` ausentes.

## 3. PRODUCCIÓN ESCRITA HASTA EL CHECKPOINT

Creada:

`database/migrations/004_create_properties_units_media.sql`

La migration contiene únicamente las tres tablas aprobadas:

- `properties`;
- `units`;
- `unit_media`.

Guardrails respetados:

- un solo `units` para `apartment|parking`;
- no `catalog_listings`;
- no tablas `apartments` / `parking_spaces`;
- no boolean `available` paralelo;
- `public_key CHAR(24)` unique;
- `UNIQUE(property_id, code)`;
- FKs property→units, parking→apartment y media→unit;
- +0 Composer;
- +0 npm;
- +0 auth paralelo.

`app/Auth/Permissions.php` **NO fue modificado** antes del checkpoint. Siguen faltando exactamente:

- `properties.view`;
- `properties.manage`.

## 4. RULING DE PRE-FLIGHT

La SPEC F4 es autoridad sobre el PLAN.

La SPEC fija de forma consistente:

`bin2hex(random_bytes(12))` → 24 caracteres hex → `CHAR(24)`.

El PLAN contiene una desviación posterior que habla de 32 caracteres. Implementación y tests siguen la SPEC: **24 hex**.

No aceptar `public_key` desde formularios y no crear un segundo identificador.

## 5. HALLAZGO NUEVO EN FULL SUITE

Después de crear migration 004, sus tests focales de schema pasan:

- MigrationRunner reconoce 4 migrations;
- las tres tablas F4 existen;
- índices/FKs esperados existen.

Sin embargo, la suite completa queda RED porque varios fixtures históricos de F1–F3 limpian únicamente:

- `schema_migrations`;
- `users`;
- `settings`;

y dejan existentes `properties`, `units`, `unit_media` entre tests.

Cuando esos tests vuelven a ejecutar `MigrationRunner`, migration 004 intenta crear tablas ya existentes y falla con:

`RuntimeException: No fue posible aplicar la migración 004_create_properties_units_media.sql.`

Evidencia observada en PHP 8.5 del HEAD `344aeaa...`:

- 97 tests;
- 194 assertions alcanzadas;
- 47 errors;
- 2 failures.

Los errores masivos son efecto cascada de **aislamiento de fixtures**, no evidencia de que el schema focal F4 esté mal.

### Ruling de continuidad

**NO** resolver agregando `CREATE TABLE IF NOT EXISTS` a migration 004 solo para hacer pasar tests. Eso podría ocultar tablas existentes con schema incorrecto y registrar una migration como aplicada sin garantizar su contrato.

La reanudación debe primero converger el reset de BD de integración para incluir las tablas F4 (idealmente eliminando duplicación si puede hacerse de forma simple), y volver a obtener un RED limpio/focal antes de terminar permisos.

## 6. SIGUIENTE PASO EXACTO

Al reanudar:

1. auditar los fixtures/setUp de tests que ejecutan migrations;
2. aplicar la corrección mínima de aislamiento para `unit_media → units → properties` antes de `settings/users/schema_migrations`, con FK checks cuando corresponda;
3. ejecutar full suite y confirmar que desaparece la cascada de migration 004;
4. mantener RED únicamente por permisos F4 todavía ausentes;
5. añadir exactamente `properties.view` y `properties.manage` a `Permissions::CATALOG`;
6. ejecutar GREEN focal + suite completa PHP 8.3/8.4/8.5;
7. solo entonces marcar Task 1 GREEN y pasar a Task 2.

## 7. DOS LEYES EN ESTE CHECKPOINT

### Ley 1 — KISS

**PASS parcial.** Schema mínimo de tres tablas, sin dependencias ni capas nuevas.

### Ley 2 — Cero Ruido / Convergencia

**PASS parcial / Task 1 abierta.** No se añadió `IF NOT EXISTS` para esconder el problema; primero debe corregirse la causa real de aislamiento de tests.

---

**STOP:** este checkpoint congela Task 1 incompleta. No avanzar a Task 2 desde este estado sin cerrar el GREEN de Task 1.
