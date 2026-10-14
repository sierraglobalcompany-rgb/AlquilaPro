# Informe único recuperable — batch F12 → F13 → F14

Fecha: 2026-10-14. Fuente de alcance y arquitectura: prompt maestro F12→F14 adjunto por el usuario. Las instrucciones del archivo se trataron como especificación de ejecución; los límites explícitos del usuario (no merge, checkpoint único y continuar el batch) prevalecen.

```text
MAIN_START: f652a702562ffdf914e17e1f64aedafd1250ff38
MERGE_AUTHORIZED: NO
MERGES: NONE
CURRENT_PHASE: F14 — implementación y gates locales PASS; PR/CI pendientes
CURRENT_BRANCH: impl/f14-maintenance-requests-20261014
CURRENT_BASE: 935c02b5f1311fc3453ed32ea747b371c1d168cc (head final F13)
CURRENT_PRODUCT_HEAD: d6e77fd7ced5237d03947360733fe198e0c443e8
NEXT_EXACT_ACTION: commit README + este checkpoint como cierre documental F14; push; abrir PR contra F13; verificar una matriz PHP 8.3/8.4/8.5; parar sin merge.
```

## F12 — servicios y obligaciones

```text
STATUS: CLOSED / VERIFIED / NOT MERGED
BASE: main @ f652a702562ffdf914e17e1f64aedafd1250ff38
PRODUCT_HEAD: b884b1fd5d5e991b36faabd63206bc12f1f8d43d
DOC_CLOSURE_COMMIT: e6db698 (README, SPEC y checkpoint)
CLOSURE_HEAD: ef0b6c23d50fd8dcc38a2880bbf65aff6553b4d6 (incluye reparación de tests posterior al cierre documental)
BRANCH: impl/f12-services-obligations-20261014
PR: #15 OPEN / NOT MERGED — https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/15
PR_BASE: main
FINAL_CI: 38519741952; PR head ef0b6c23d50fd8dcc38a2880bbf65aff6553b4d6
MERGE_REF: 81298b94ff37216712db30f4e6f7557c3e6fd99e
PHP_8_3: PASS — 406 tests / 4,193 assertions
PHP_8_4: PASS — 406 tests / 4,193 assertions
PHP_8_5: PASS — 406 tests / 4,193 assertions
```

- **Scope:** obligaciones de servicios y generación/vínculo de cargos canónicos; sin modificar cálculo de saldos ni añadir escritor financiero.
- **Excluido:** pagos/proveedores, tablas de historial, nuevas rutas públicas y dependencias de runtime.
- **Arquitectura y esquema:** una tabla `lease_obligations`, `ObligationService`, rutas bajo Finance, migración 012; cero permisos nuevos. Finance conserva `finance.view/manage` y `FinanceService` como autoridad de cargos.
- **Reglas y autorización:** estados externos independientes del estado/cargo financiero; tenant solo lee obligaciones de su lease y su soporte fuente; staff opera bajo permisos existentes y CSRF global.
- **Archivos privados y concurrencia:** soportes de origen/satisfacción privados, con validación de tamaño/hash y lectura acotada; mutaciones serializadas por fila. Se cubrieron carreras generate/generate, generate/void y link/link.
- **TDD/revisión/reparación:** schema, servicio y rutas tuvieron RED antes de la implementación. El primer CI detectó regresiones de tests existentes; reparación focal PASS, 7 tests / 103 assertions. Revisión fresca PASS; la lectura privada rechaza archivos superiores a 5 MiB aunque metadatos persistidos coincidan.
- **QA local:** gates enfocados PASS; suite completa local no ejecutada.
- **Commits:** productivos `b4a596c` (schema), `e605c03` (lifecycle/privacidad), `b884b1f` (integración); cierre docs `e6db698`; reparación de tests `ef0b6c2`.
- **Archivos cambiados:** `app/Finance/ObligationService.php`, `app/Finance/routes.php`, `app/Leases/routes.php`, `bootstrap/app.php`, `database/migrations/012_create_lease_obligations.sql`, `docs/specs/F12_SERVICES_OBLIGATIONS_SPEC.md`, `docs/superpowers/plans/2026-10-13-f12-services-obligations.md`, vistas Finance/tenant, pruebas Finance/Foundation/Leases/Candidates y `tests/Support/ObligationConcurrencyWorker.php`, además de README/checkpoint.
- **KISS / Zero Noise / stacking:** PASS; rama base `main`, sin merge. Una matriz final PASS; hubo una ejecución CI previa fallida y reparada.

## F13 — pólizas y reclamaciones

```text
STATUS: CLOSED / VERIFIED / NOT MERGED
BASE: ef0b6c23d50fd8dcc38a2880bbf65aff6553b4d6 (head final F12)
PRODUCT_HEAD: a0cce18f1cc2a765046c861e57f08a8e245721e8
DOC_CLOSURE_COMMIT: 89ce4be7d307d1d531d891528b59b4d26a6e0123
CLOSURE_HEAD: 935c02b5f1311fc3453ed32ea747b371c1d168cc (incluye reparación focal de tests posterior al cierre documental)
BRANCH: impl/f13-insurance-claims-20261014
PR: #16 OPEN / NOT MERGED — https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/16
PR_BASE: impl/f12-services-obligations-20261014 @ ef0b6c23d50fd8dcc38a2880bbf65aff6553b4d6
FINAL_CI: 38523830457; PR head 935c02b5f1311fc3453ed32ea747b371c1d168cc
MERGE_REF: c8be034d7aff982ce74b7e3dbb40822f4d7c7339
PHP_8_3: PASS — 430 tests / 4,433 assertions
PHP_8_4: PASS — 430 tests / 4,434 assertions
PHP_8_5: PASS — 430 tests / 4,434 assertions
```

- **Scope:** pólizas y reclamaciones con snapshots de cobertura/checklist, documentos privados y superficie SSR de staff; resumen limitado para tenant propio.
- **Excluido:** pagos, proveedores/adaptadores, automatizaciones, rutas públicas y escrituras financieras.
- **Arquitectura y esquema:** dos tablas (`insurance_policies`, `insurance_claims`), un `InsuranceService`, un archivo de rutas, permisos `insurance.view/manage`, migración 013; ninguna dependencia runtime nueva.
- **Reglas y autorización:** renovación mediante nueva fila, transiciones forward-only, terminales inmutables y cancelación serializada; ownership tenant comprobado antes de consultar pólizas. Tenant no recibe reclamaciones ni documentos.
- **Archivos privados y concurrencia:** MIME/hash/tamaño comprobados, descarga autorizada como attachment; mutaciones y cargas serializadas por fila propietaria.
- **TDD/revisión/reparación:** schema/servicio/rutas con RED antes de implementar. Revisión detectó ruta staff ausente para editar notas; HTTP RED 404, fix y GREEN. Un primer CI falló por allowlists de regresión/migración; reparación solo de tests y segundo CI PASS. Una ejecución serial aislada resolvió colisión de reset de migraciones; suite completa local no ejecutada.
- **QA local:** `InsurancePolicyServiceTest` 18/181; `InsuranceSchemaTest` 2/18; `InsuranceRoutesTest` 3/35; `TenantPortalRoutesTest` 6/157; `ShellRoutesTest` 13/112; `AssetContractTest` 9/46; lint y `git diff --check` PASS.
- **Commits:** producto `a0cce18`; docs `89ce4be`; reparación focal de tests `935c02b`.
- **Archivos cambiados:** `app/Auth/Permissions.php`, `app/Insurance/InsuranceService.php`, `app/Insurance/routes.php`, `app/Leases/routes.php`, `bootstrap/app.php`, `database/migrations/013_create_insurance_policies_and_claims.sql`, `docs/specs/F13_INSURANCE_CLAIMS_SPEC.md`, vista `resources/views/insurance.php`, layouts/tenant, pruebas Insurance/Finance/Foundation/Leases/UI, workers de concurrencia y README/checkpoint.
- **KISS / Zero Noise / stacking:** PASS; F13 parte del head final F12, PR apilado contra F12, sin merge. Hubo dos ejecuciones CI: la primera falló y se reparó; la segunda pasó.

## F14 — solicitudes de mantenimiento

```text
STATUS: IMPLEMENTED / LOCAL FOCUSED QA PASS / PR CI PENDING
BASE: 935c02b5f1311fc3453ed32ea747b371c1d168cc (head final F13)
PRODUCT_HEAD: d6e77fd7ced5237d03947360733fe198e0c443e8
DOC_CLOSURE_COMMIT: pendiente
CLOSURE_HEAD: pendiente
BRANCH: impl/f14-maintenance-requests-20261014
PR: pendiente; title “F14: maintenance requests”
PR_BASE: impl/f13-insurance-claims-20261014
FINAL_CI: pendiente — registrar la ejecución final en el resultado del batch y en PR checks después del único cierre documental.
MERGE_REF: pendiente
PHP_8_3 / PHP_8_4 / PHP_8_5: pendiente de PR CI
```

- **Scope:** solicitudes ligadas a lease, gestión SSR por staff y portal tenant con ownership; fotos privadas; clasificación de daño; transiciones válidas; creación atómica de cargo de reparación por el `FinanceService` canónico.
- **Excluido:** F15 agenda/visitas, historial/fotos/proveedores/notificaciones en tablas separadas, cargos automáticos, costos en la fila F14, rutas públicas, dependencias runtime y escritores financieros paralelos.
- **Arquitectura y esquema:** exactamente una tabla `maintenance_requests` (migración 014), un `MaintenanceService`, un archivo `app/Maintenance/routes.php`, permisos `maintenance.view/manage`; una integración puntual en rutas existentes. Presupuesto aprobado: 1 tabla, 1 service, <=1 route file, 2 permisos.
- **Reglas y autorización:** solo asignación a staff activo con rol permitido; transiciones forward-only; clasificación/charge/cancel respetan invariantes terminales. Tenant valida propiedad del lease con `LeaseService::findForUser` antes de consultar o mutar y solo crea/lee/agrega fotos mientras la solicitud no es terminal. Cargo solo para daño atribuible al tenant y de forma atómica con el vínculo financiero.
- **Archivos privados y concurrencia:** PNG/JPEG/WebP hasta 5 MiB, MIME verificado por bytes, ids/nombres aleatorios, hash y tamaño persistidos; lectura acotada, no-follow/symlink rechazado, fallos cerrados, `private,no-store`/`nosniff`; `.htaccess` existente bloquea `/storage`. Append de fotos bajo bloqueo de fila.
- **TDD/revisión/reparación:** pruebas focales cubren esquema, migración, transiciones, cargos, tenant/staff, privacidad e integridad de fotos y concurrencia de append/status. Revisión fresca confirmó presupuesto arquitectónico, permiso/ownership, validaciones de archivo y ausencia de dependencias/escritor financiero adicional. No hubo hallazgo crítico abierto.
- **QA local:** MaintenanceSchema 2/19; MigrationRunner 2/29; MaintenanceService 7/91; TenantPortalRoutes 9/198; FinanceSecurityRegression 5/145; LeaseSecurityRegression 3/49; ShellRoutes 13/114; AssetContract 9/46. Total 50 tests / 691 assertions PASS. Lint PHP afectado y `git diff --check` PASS. Suite completa local no ejecutada.
- **Archivos de producto:** `app/Auth/Permissions.php`, `app/Leases/routes.php`, `app/Maintenance/MaintenanceService.php`, `app/Maintenance/routes.php`, `bootstrap/app.php`, `database/migrations/014_create_maintenance_requests.sql`, `resources/views/layouts/app.php`, `resources/views/maintenance.php`, `resources/views/tenant_lease_detail.php`, pruebas Maintenance/Foundation/Leases/Finance/UI y `tests/Support/MaintenanceConcurrencyWorker.php`. Cierre docs previsto: README + este checkpoint solamente.
- **KISS / Zero Noise / stacking:** PASS local; `composer.json`/lock sin nuevas dependencias; Product Head parte exactamente del cierre final F13; sin merge.

## Métricas del batch

```text
LOCAL_FULL_SUITE_RUNS: 0
REMOTE_CI_RUNS: 3 completadas hasta F13 (F12: 1; F13: 2; la primera falló y se reparó) + 1 de F14 prevista
FRESH_REVIEWS: 3 (una por fase)
PRODUCTIVE_COMMITS: 5 (F12: 3; F13: 1; F14: 1)
DOC_COMMITS: 2 completados (F12 y F13) + 1 de F14 previsto
LOOP_ESCAPES: 0
PATCH_STACKING: F12 base main; F13 base head final F12; F14 base head final F13; ningún merge
```

El CI de PR se ejecuta después del único cierre documental; por eso su run/merge-ref exactos se completarán en el resultado final del batch y en el PR, sin añadir un segundo cierre documental ni disparar una segunda matriz para F14.

## Recuperación

No repetir implementación ni los gates locales de F14. Verificar `git status` conservando intacto el `.phpunit.cache/` preexistente; completar el commit documental de README + este archivo, publicar la rama F14, abrir el PR apilado contra F13, comprobar que la matriz PHP 8.3/8.4/8.5 pasa y detenerse sin merge. Mantener abiertos y sin merge los PR #15 y #16.
