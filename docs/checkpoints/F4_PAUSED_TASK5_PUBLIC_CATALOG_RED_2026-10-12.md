# ALQUILA PRO — F4 CHECKPOINT — TASK 5 PUBLIC CATALOG RED

Fecha: 2026-10-12
Estado: **PAUSADO POR SOLICITUD DEL USUARIO**
Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
Branch: `impl/f4-properties-catalog-20261011`
PR: `#7 — F4: Properties, units and public catalog`
PR estado: `OPEN / DRAFT / MERGEABLE / NOT MERGED`
Base: `main`
Base SHA: `940ebfae25a05d1589fe1ab5ceeaf667c2d08345`

## Regla de parada

Este checkpoint se crea porque el usuario indicó explícitamente: **“crea un checkpoint y parar”**.

Después de este archivo no se debe continuar implementación, abrir nuevas tareas, modificar producto ni hacer merge hasta una nueva orden explícita del usuario.

## HEAD y separación producto/documentación

Product HEAD al momento de pausar:

`e9edfb2c0a371b85df3e46d48bca59033d6fb59b`

Ese commit contiene únicamente el RED abierto de catálogo público:

`tests/Integration/Properties/PublicCatalogListTest.php`

El commit de este archivo será **docs-only** y pasará a ser el branch HEAD de checkpoint. Para reanudar producto, considerar `e9edfb2c...` como el último Product HEAD previo al checkpoint.

## Estado F4

- Task 1 — schema + permissions: **CLOSED / GREEN**.
- Task 2 — properties + units domain: **CLOSED / GREEN**.
- Task 3 — media + publication foundation: **CLOSED / GREEN**.
- Task 4 — private admin UI: **CLOSED / VERIFIED**.
- Task 5 — landing + public catalog: **IN PROGRESS**.
- Task 6 — public media route: **NOT STARTED**.
- Task 7 — security/regression close: **NOT CLOSED**.
- Task 8 — visual QA + zero-noise/docs final: **NOT STARTED**.

La fase F5 sigue bloqueada. **Task 5 de F4 no es fase F5.**

## Task 4 — cierre confirmado

Checkpoint de cierre Task 4:

`docs/checkpoints/F4_TASK4_PRIVATE_ADMIN_CLOSED_2026-10-12.md`

Cierre relevante:

- AUD-01..AUD-10 cerrados;
- KISS PASS;
- Cero Ruido / Convergencia PASS para Task 4;
- AUD-11/AUD-12 quedan como P2 no reproducidos, sin sobrearquitectura;
- no se agregó tabla, dependencia, permiso, framework ni servicio nuevo para cerrar Task 4.

Product HEAD de AUD-10:

`a398b16ff418db7b55e935ddb29182e296ccfb91`

CI:

- run `38207117777`
- PHP 8.3 PASS
- PHP 8.4 PASS
- PHP 8.5 PASS
- `134 tests / 758 assertions / 0 failures`

## Task 5 — landing pública

### RED

Commit:

`cc292a4b23cc3f390a152debcfa5653d6d2f7a65`

RED esperado: instalado + visitante en `/` seguía recibiendo `302 /login`.

### GREEN

Commit:

`3a0a6a8fa7dab2a5c13b57109fb9f6023a655e89`

Cambios mínimos:

- `View::renderPublic()`;
- `resources/views/layouts/public.php`;
- `resources/views/public_landing.php`;
- `/` decide visitante público vs shell autenticado;
- Branding y CSS existentes reutilizados;
- sin segundo sistema visual;
- sin segundo auth.

### Convergencia contrato F3

Commit:

`83adb9eb4c3cf8ae3bcb3859d5741601f02dabcb`

Se actualizó el único test histórico contradictorio de F3 para que instalado + visitante en `/` espere landing pública 200, mientras:

- autenticado conserva shell privado;
- `/account` sigue protegido;
- `must_change_password` sigue forzando `/account/password`.

Último GREEN completo antes de abrir catálogo:

- Product HEAD: `83adb9eb4c3cf8ae3bcb3859d5741601f02dabcb`
- CI run: `38207573402`
- PHP 8.3 PASS
- PHP 8.4 PASS
- PHP 8.5 PASS
- Composer validate --strict PASS
- PHPUnit PHP 8.3: `136 tests / 775 assertions / 0 failures`

## Task 5 — catálogo público RED abierto

Commit RED actual:

`e9edfb2c0a371b85df3e46d48bca59033d6fb59b`

Archivo:

`tests/Integration/Properties/PublicCatalogListTest.php`

El RED exige:

1. `GET /catalog` público => 200;
2. solo unidades realmente `PUBLIC_VISIBLE`;
3. ocultar propiedad inactiva;
4. ocultar unidad no disponible;
5. ocultar unidad incompleta aunque `is_public=1`;
6. links de detalle por `public_key` de 24 hex;
7. filtros `type`, `city`, `sector`;
8. `type` inválido no vacío => 422;
9. subfolder/basePath correcto;
10. no exponer dirección exacta;
11. no exponer código operativo interno;
12. no renderizar `Aplicar` ni flujo F5.

### RED verificado

CI run:

`38207686855`

Resultado:

- PHP 8.3 FAIL esperado;
- PHP 8.4 FAIL esperado;
- PHP 8.5 FAIL esperado;
- Composer validate --strict PASS;
- PHP 8.3: `139 tests / 778 assertions / 3 failures`.

Los tres fallos son exactamente los tres tests nuevos del catálogo y la causa es la misma:

`GET /catalog` todavía no existe y Slim produce `HttpNotFoundException`, convertido por la frontera global en 500 sanitizado.

No hay indicio de fallo de datos de prueba ni regresión de landing privada/pública.

## GREEN NO IMPLEMENTADO

**Importante:** el GREEN de `/catalog` NO se implementó antes de pausar.

La solución mínima ya decidida, pero no ejecutada, es:

- reutilizar `PropertyService::listPublicUnits()`;
- reutilizar `View::renderPublic()`;
- agregar una sola ruta pública `GET /catalog`;
- agregar una sola vista pública de listado;
- capturar `RuntimeException` de tipo inválido y responder 422;
- no crear `CatalogService`;
- no crear tabla de catálogo;
- no crear permiso de catálogo;
- no crear DTO/repository/ORM;
- no tocar Task 6 todavía.

## Archivos que NO deben tocarse al reanudar salvo necesidad demostrada

Para cerrar este RED no se necesita nueva arquitectura ni migración.

No tocar por defecto:

- `database/migrations/*`;
- permisos;
- auth;
- CSRF;
- schema F4;
- `UnitMediaService` para servir media pública;
- fase F5;
- candidatos/leads/solicitudes/contratos/cartera.

## Siguiente paso exacto al recibir “continua”

Retomar desde:

**Task 5 / `/catalog` RED abierto en `e9edfb2c...`**.

Secuencia exacta:

1. releer este checkpoint y verificar PR/branch/HEAD/base;
2. confirmar que el branch HEAD docs-only desciende de Product HEAD `e9edfb2c...`;
3. implementar GREEN mínimo de `/catalog` con `listPublicUnits()` + `renderPublic()` + una vista;
4. correr/verificar CI PHP 8.3/8.4/8.5;
5. si GREEN, abrir después el RED de `/catalog/{public_key}` incluyendo 404 para `occupied|maintenance|in_process|inactive`, propiedad inactiva, no publicada e incompleta;
6. después cerrar resumen de disponibilidad de landing;
7. no abrir Task 6 hasta cerrar Task 5.

## Reglas de oro vigentes

- KISS.
- Cero Ruido / Convergencia.
- `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`.
- RED → verificar RED → GREEN mínimo → verificar GREEN → converger → QA.
- una fuente de verdad por concepto.
- no arquitectura futura.
- no patch sobre patch.
- no merge sin autorización humana explícita.
- no declarar GREEN/CLOSED sin evidencia.

## Merge

**NO AUTORIZADO.**

PR #7 debe permanecer Draft y sin merge.

## STOP

Checkpoint creado. **Parar aquí.**
