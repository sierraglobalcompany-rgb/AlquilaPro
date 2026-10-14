# Checkpoint único — F12 → F13 → F14

```text
PHASE: F12
MACRO: A — PASS; B — PASS; CERTIFY — cierre local listo, PR CI pendiente
BASE: f652a702562ffdf914e17e1f64aedafd1250ff38 (main con F11 integrado)
PRODUCT_HEAD: b884b1fd5d5e991b36faabd63206bc12f1f8d43d
CURRENT_HEAD: b884b1fd5d5e991b36faabd63206bc12f1f8d43d (antes del commit documental de cierre)
BRANCH: impl/f12-services-obligations-20261014
MERGE_AUTHORIZED: NO
```

## DONE

- F11 está integrado en `main`; merge commit `9112b19ab9585cb02e41960f17f78faeec64d06c`. La rama F12 parte de `f652a702562ffdf914e17e1f64aedafd1250ff38`.
- F12 añade una tabla (`lease_obligations`), una autoridad (`ObligationService`), cero permisos nuevos, cero rutas públicas y cero dependencias runtime nuevas.
- Macro A: esquema, validaciones, estados externos derivados, inmutabilidad terminal, soportes privados de origen/satisfacción, settle y void.
- Macro B: generación atómica de cargos `utility → service` y `fine_ph → fine`; vínculo de administración al cargo canónico F9; controles de concurrencia generate/generate, generate/void y link/link.
- Superficie staff dentro de Finance con `finance.view/manage`, CSRF global y soportes privados; superficie tenant de solo lectura con ownership por `LeaseService::findForUser` y acceso únicamente al soporte fuente de lease propio.
- La cancelación externa y el cargo financiero conservan dimensiones independientes. F12 no calcula saldos ni escribe cargos directamente.
- Revisión fresca: se añadió un límite de lectura de 5 MiB también para soportes ya almacenados, incluso si los metadatos coinciden con un archivo sobredimensionado.

## RED

```text
Schema/service: RED observado antes de implementar F12.
Finance integration/routes: RED observado antes de añadir las superficies F12.
Macro B regression: 3 fallas por allowlists históricas F8/F9 que no contemplaban la composición F12; se corrigieron condicionalmente a la presencia del esquema F12.
Private support review: nuevo caso de lectura >5 MiB con hash/tamaño coincidentes; corregido en el servicio y cubierto por regresión.
```

## GREEN

```text
Macro A schema/service focused: 14 tests / 259 assertions PASS.
Macro B integrated focused gate: 88 tests / 1,415 assertions PASS.
Post-review affected gate: 14 tests / 231 assertions PASS.
PHP lint de archivos PHP modificados: PASS.
git diff --check: PASS.
No full local PHPUnit suite was run.
PR CI PHP 8.3/8.4/8.5: pendiente de crear PR; F13 no inicia hasta PASS en las tres matrices.
```

## RULINGS

- Se mantienen los nombres de ramas F12–F14 del prompt batch, con sufijo `20261014`.
- Se conserva este único checkpoint. No se crea `F12_CLOSED` separado ni reporte por tarea.
- Las regresiones F8/F9 aceptan las rutas/servicio F12 solo cuando existe la migración `012_create_lease_obligations.sql`; el presupuesto F12 queda además fijado por `ObligationSecurityRegressionTest`.
- El directorio preexistente `.phpunit.cache/` se conserva sin cambios.

## FINDINGS

- Los commits productivos F12 son `b4a596c` (schema), `e605c03` (autoridad/core privado) y `b884b1f` (integración/superficies). El commit documental de cierre añade README, SPEC y este checkpoint.
- SPEC/PLAN F12 se importaron de `spec/f12-services-obligations-20261013` (`db91fba2244b4d7824986c7567eb3620a41d8092`); SPEC se cierra en el commit documental. PLAN conservado como autoridad TDD.
- F13 SPEC/PLAN se leerán una sola vez al iniciar F13. La base será el `HEAD` exacto de la rama F12 después del commit documental de cierre y tras confirmar CI verde.
- No hay merge autorizado. No empezar F13 con CI de F12 pendiente o fallida.

## NEXT_EXACT_ACTION

Crear el commit documental F12, hacer un solo push de `impl/f12-services-obligations-20261014`, abrir PR a `main` con título `F12: services and obligations` y body `STACKED BATCH F12-F14\nMERGE NOT AUTHORIZED`. Esperar y verificar CI PHP 8.3/8.4/8.5; solo con las tres matrices PASS, crear `impl/f13-insurance-claims-20261014` desde el closure HEAD exacto F12 y leer una vez su SPEC/PLAN.
