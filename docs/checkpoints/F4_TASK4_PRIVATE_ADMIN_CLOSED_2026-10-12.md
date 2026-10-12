# ALQUILA PRO — F4 TASK 4 PRIVATE ADMIN — CLOSED

Fecha: 2026-10-12
Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
Branch: `impl/f4-properties-catalog-20261011`
PR: `#7 — F4: Properties, units and public catalog`
Base `main`: `940ebfae25a05d1589fe1ab5ceeaf667c2d08345`
Product HEAD verificado: `a398b16ff418db7b55e935ddb29182e296ccfb91`

## Estado

**TASK 4 — PRIVATE ADMIN UI: CLOSED / VERIFIED**

PR #7 permanece `OPEN / DRAFT / NOT MERGED`. Merge no autorizado.

## Evidencia final

CI product HEAD `a398b16ff418db7b55e935ddb29182e296ccfb91`:

- GitHub Actions run `38207117777`
- PHP 8.3: PASS
- PHP 8.4: PASS
- PHP 8.5: PASS
- Composer validate --strict: PASS
- PHPUnit PHP 8.3: `134 tests / 758 assertions / 0 failures`

## Auditoría cerrada

- AUD-01 — publicabilidad viva: GREEN.
- AUD-02 — test público skeleton obsoleto: CONVERGIDO / GREEN.
- AUD-03 — salida pública por allowlist: GREEN.
- AUD-04 — no crear unidad en propiedad inactiva: GREEN.
- AUD-05 — tipo de unidad inmutable: GREEN.
- AUD-06 — parking muestra apartamentos de la misma propiedad: GREEN.
- AUD-07 — launcher Propiedades autorizado: GREEN.
- AUD-08 — CSRF focal F4: GREEN usando middleware global existente, sin código paralelo.
- AUD-09 — formularios contextuales por tipo: GREEN.
- AUD-10 — conteos simples por estado en cards de propiedad: GREEN.

## P2 revisados, no bloqueantes

### AUD-11 — delete media DB/filesystem

El flujo actual elimina fila y luego archivo. Existe una ventana teórica si `unlink()` falla después de eliminar la fila. No hay fallo reproducido en el flujo soportado; el test normal de eliminación y frontera de unidad permanece GREEN. No se introduce `FilesystemManager`, compensador ni nueva arquitectura por un edge artificial no reproducido.

Ruling: **riesgo P2 aceptado en Task 4; reabrir solo ante reproducción real**.

### AUD-12 — post-insert `findMedia()`

Después de un INSERT exitoso, `addPhoto()` confirma la fila. La rama teórica donde esa lectura inmediata falla podría dejar fila sin archivo porque el cleanup borra el archivo; no existe caso natural reproducido y forzarlo requeriría fault injection/arquitectura no justificada.

Ruling: **riesgo P2 aceptado en Task 4; reabrir solo ante reproducción real**.

## Gate Ley 1 — KISS

**PASS**.

Task 4 termina manteniendo:

- una aplicación;
- una BD;
- una tabla `units` para apartment|parking;
- `PropertyService` + `UnitMediaService` ya existentes;
- auth/CSRF/View/shell existentes reutilizados;
- cero dependencia Composer nueva;
- cero dependencia npm nueva;
- cero tabla nueva durante la auditoría;
- cero permiso nuevo fuera de `properties.view|properties.manage`;
- cero ORM/repository/DTO/factory/DI adicional;
- cero SPA/framework frontend;
- cero servicio de estadísticas.

AUD-10 se resolvió con una sola consulta agregada en `listProperties()` y la vista consume los conteos; no hay N+1 ni persistencia/caché paralela.

## Gate Ley 2 — Cero Ruido / Convergencia

**PASS para Task 4**.

Convergencias realizadas:

- regla pública única compartida por propiedades y media;
- test skeleton público histórico actualizado al contrato real;
- launcher F3 histórico convergido al módulo F4 real;
- `type` inmutable en backend y UI;
- salida pública cambió blacklist → allowlist;
- selector parking reutiliza `listUnitsForProperty()`;
- CSRF reutiliza middleware global;
- un solo formulario de creación contextual con JS nativo mínimo;
- conteos se calculan desde estados canónicos, sin segunda fuente de verdad.

No se eliminó código/documentación a ciegas. La convergencia documental global de F4/README/PLAN/checkpoints/PR body queda para el cierre final Task 8, cuando exista reemplazo canónico F4 CLOSED.

## Estado de tareas F4 después de este cierre

- Task 1 — schema + permissions: CLOSED / GREEN.
- Task 2 — properties + units domain: CLOSED / GREEN.
- Task 3 — media + publication foundation: CLOSED / GREEN.
- Task 4 — private admin UI: **CLOSED / VERIFIED**.
- Task 5 — landing + public catalog: **NEXT / NOT STARTED al momento de este checkpoint**.
- Task 6 — public media route: NOT STARTED.
- Task 7 — security/regression close: NOT CLOSED.
- Task 8 — visual QA + zero-noise/docs final: NOT STARTED.

## Siguiente paso exacto

Abrir **Task 5 de F4** con RED, en este orden:

1. root instalado + visitante => landing pública 200;
2. root autenticado conserva shell privado;
3. must-change-password conserva redirect `/account/password`;
4. `/catalog` público con solo `PUBLIC_VISIBLE`;
5. filtros `type|city|sector`, tipo inválido no vacío => 422;
6. `/catalog/{public_key}` solo unidad publicable; 404 para inexistente, propiedad inactiva, status `occupied|maintenance|in_process|inactive`, no publicada o incompleta;
7. no renderizar `Aplicar` ni rutas F5;
8. mantener privacidad/XSS/base-path.

Task 5 de F4 **no es fase F5**. La fase F5 sigue bloqueada hasta que F4 esté CLOSED / VERIFIED / MERGED.

**No merge.**
