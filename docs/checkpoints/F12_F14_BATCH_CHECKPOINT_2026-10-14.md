# Checkpoint único — F12 → F13 → F14

```text
PHASE: F13
MACRO: A — PASS; B — PASS; CERTIFY — DOCS CLOSURE / PR CI PENDING
BASE: ef0b6c23d50fd8dcc38a2880bbf65aff6553b4d6 (F12 PR head; sin merge)
PRODUCT_HEAD: a0cce18f1cc2a765046c861e57f08a8e245721e8
DOC_CLOSURE_COMMIT: pendiente
CLOSURE_HEAD: pendiente — docs closure, push y PR CI
BRANCH: impl/f13-insurance-claims-20261014
PR: pendiente — base impl/f12-services-obligations-20261014
MERGE_AUTHORIZED: NO
```

## F12 — cerrado y validado

- F11 está integrado en `main`; merge commit `9112b19ab9585cb02e41960f17f78faeec64d06c`. La rama F12 parte de `f652a702562ffdf914e17e1f64aedafd1250ff38`.
- F12 añade una tabla (`lease_obligations`), una autoridad (`ObligationService`), cero permisos nuevos, cero rutas públicas y cero dependencias runtime nuevas.
- Macro A: esquema, validaciones, estados externos derivados, inmutabilidad terminal, soportes privados de origen/satisfacción, settle y void.
- Macro B: generación atómica de cargos `utility → service` y `fine_ph → fine`; vínculo de administración al cargo canónico F9; concurrencia generate/generate, generate/void y link/link.
- Staff usa la superficie Finance y `finance.view/manage`, con CSRF global y soportes privados. Tenant conserva lectura solamente, ownership por `LeaseService::findForUser` y acceso al soporte fuente del lease propio.
- Satisfacción externa y cargo financiero conservan dimensiones independientes. F12 no calcula saldos ni escribe cargos directamente.
- Revisión fresca: lectura privada también rechaza archivos >5 MiB aunque el tamaño/hash persistidos coincidan con un archivo sobredimensionado.
- SPEC F12 cerrada; README actualizado; PR #15 abierto contra `main`, con body `STACKED BATCH F12-F14\nMERGE NOT AUTHORIZED`. Sigue sin merge.
- CI final PR run `38519741952`, exact PR head `ef0b6c23d50fd8dcc38a2880bbf65aff6553b4d6`: PHP 8.3/8.4/8.5 PASS, cada matriz 406 tests / 4,193 assertions.

## F13 — Macro A y B

- Rama creada desde el head final validado de F12 `ef0b6c23d50fd8dcc38a2880bbf65aff6553b4d6`; F12 continúa abierto y sin merge.
- SPEC/PLAN de autoridad leídos desde `spec/f13-insurance-claims-20261013` en `8aa68b7f1b8d46722764aabea8f72a5e9521d9a5`.
- Presupuesto: dos tablas (`insurance_policies`, `insurance_claims`), una autoridad (`InsuranceService`), un archivo de rutas, dos permisos y cero rutas públicas, dependencias runtime, adaptadores o escrituras financieras.
- Macro A implementa snapshots de cobertura, estado de póliza derivado, renovación por nueva fila, notas internas, cancelación serializada y documentos privados con MIME/hash/tamaño verificados.
- Macro B implementa borradores, snapshots del checklist, transiciones forward-only, fechas/radicado, terminales inmutables, documentos y superficie SSR para staff; tenant recibe resumen propio con número enmascarado, sin claims ni documentos.
- Cargas y mutaciones serializan por fila propietaria. `InsurancePolicyServiceTest` cubre cancel/create-claim, retirada de documento/claim concurrente, cargas simultáneas, transición terminal/checklist y doble transición/cancelación.
- Revisión fresca: verificados rutas admin protegidas por permiso y CSRF global, ownership tenant antes de consultar pólizas, escape SSR, almacenamiento privado y presupuesto arquitectónico. Hallazgo: faltaba ruta staff para editar notas internas; RED HTTP observado (404), ruta/form añadidos con `insurance.manage`, y GREEN verificado. Lectura de documentos convergida en un helper; descarga responde como attachment.
- Un primer inicio concurrente de `ShellRoutesTest` chocó con el reset de migraciones compartido. La ejecución serial aislada pasó; no se repitió la suite completa.

## RED

```text
F12: schema/service y finance routes tuvieron RED antes de implementar; el primer PR CI encontró dos regresiones de tests. Corrección focal validada: 7 tests / 103 assertions.
F13: RED de schema/service y rutas antes de implementar. La revisión reveló la ausencia del endpoint de edición de notas internas; prueba HTTP RED 404, seguida de implementación y prueba GREEN.
No hubo bloqueo de arquitectura ni se modificó la autoridad financiera.
```

## GREEN — gates enfocados locales

```text
InsurancePolicyServiceTest: 18 tests / 181 assertions PASS (incluye las carreras descritas arriba).
InsuranceSchemaTest: 2 tests / 18 assertions PASS.
InsuranceRoutesTest: 3 tests / 35 assertions PASS.
TenantPortalRoutesTest: 6 tests / 157 assertions PASS.
ShellRoutesTest: 13 tests / 112 assertions PASS (repetición serial aislada).
AssetContractTest: 9 tests / 46 assertions PASS.
Lint PHP afectado: PASS. git diff --check: PASS.
Suite local completa: no ejecutada.
F13 PR CI PHP 8.3/8.4/8.5: pendiente.
```

## RULINGS

- Se mantienen nombres de ramas batch `20261014`; F13 no fusiona F12. F14 partirá del cierre exacto de F13 y F15 queda fuera del batch.
- Se mantiene únicamente este checkpoint; no se crearon informes o checkpoints por tarea.
- F13 agrega solo la migración 013 aprobada, las dos tablas exactas, `InsuranceService`, un archivo de rutas y los permisos `insurance.view/manage`; `composer.json` y `composer.lock` no cambian.
- `storage/` queda denegado por la regla existente en `.htaccess`; los documentos validan bytes y se sirven solo tras autorización staff, como attachment, sin nombre/ruta física.
- El `.phpunit.cache/` preexistente se conserva sin staging.

## FINDINGS

- Commits productivos F12: `b4a596c` (schema), `e605c03` (core privado), `b884b1f` (integración/superficies). Cierre documental `e6db698`; reparación de regresiones `ef0b6c2`.
- F12 PR #15 permanece abierto y sin merge; base `main` (`f652a702...`), head final `ef0b6c23...`.
- F13 Product Head: `a0cce18f1cc2a765046c861e57f08a8e245721e8`. Sigue un único commit documental de cierre antes del push/PR.
- Métricas batch conocidas: F12 tuvo dos ejecuciones de CI por reparación; F13 aún no tiene ejecución remota. No se ejecutó full suite local.

## NEXT_EXACT_ACTION

Completar el commit documental único de F13 (SPEC, README y este checkpoint), push de `impl/f13-insurance-claims-20261014`, abrir PR contra `impl/f12-services-obligations-20261014` y verificar una matriz PHP 8.3/8.4/8.5. Iniciar F14 solo desde el `CLOSURE_HEAD` exacto de F13 si toda la matriz pasa.
