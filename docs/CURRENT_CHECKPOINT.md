# CURRENT CHECKPOINT — CAND-03 / G5

**Corte:** 2026-10-27 America/Bogota. `G5=EN_EJECUCION / PENDING_CI`; `LEY5_UX=PENDING`; `OWNER_UAT=PENDING`. No declarar PASS global ni paridad visual antes de revisar evidencia del SHA publicado.

## Estado GitHub y referencia

- Repo `sierraglobalcompany-rgb/AlquilaPro`; PR #55 OPEN/DRAFT, no merged. Rama `impl/cand-03-household-human-first-20261023`.
- HEAD base de este lote, local detached y remoto antes del cambio: `5e7a54c6addfc005374409d264dfbffbf1808b81`; padre `f2011f50d6b84a2eb2969f5fe92e82b42e6143f5`.
- CI #1209 (`40500170485`) fue SUCCESS solo para `cd3b1d7…`; no certifica el HEAD posterior.
- CI #1210 (`40525573980`) terminó FAILURE para `f2011f50…`: PHPUnit PASS en PHP 8.3/8.4/8.5; Browser QA 11/13, 60 PNG. La falla primaria del escenario de mascota es `invalidPetForm.evaluate is not a function`: faltó esperar la Promise de `formByRecord()` en `tests/browser/run.mjs`. `browser-harness-fatal` es secundario al rechazo legítimo del 422 del escenario fallido.
- Evidencia del CI #1210: manifest SHA-256 `fe15e68bba450f24ee7ccd9bf5fa5b2602bc259fb17f5624996447a3e7c31551`; 65/65 hashes internos. Artifact oficial GitHub ID `12744733454`, digest `sha256:ab50d0d9125b22c5548f162abb93c17fa5d2276895590ab3fbc64b89393dbd1f` (distinto del ZIP de entrega).
- CI #1211 (`40587784551`) terminó FAILURE para `5e7a54c6addfc005374409d264dfbffbf1808b81`: PHPUnit PASS en PHP 8.3/8.4/8.5; Browser QA 12/13, 62 capturas. Check `real-household-create-edit-delete-and-csrf` PASS incluye `invalid_occupant=422` e `invalid_pet=422`; `browser-harness-fatal` falla porque el clasificador omitía el evento HTTP 422 de mascota en esa fase.
- Evidencia CI #1211: artifact oficial GitHub ID `12771059755`, ZIP digest `sha256:87e7ad86e7abfe79aca720825324d6afdfa4b1be96cab97dd67a3a9b1e62bdab`; manifest SHA-256 `525f15e990e20b94b46e6f0ce5d802c3217572e1af1423267b732a258c789d3b`; 67/67 hashes internos.

## Microbloque focal G5

- Harness: el commit base `5e7a54c…` corrigió el `await` de `formByRecord()`, manteniendo los cuatro consumidores esperados. Este lote añade únicamente la regla específica del HTTP 422 de mascota en la fase CRUD; conserva el `candidatePath` dinámico, la correlación única con respuesta, el check PASS y el rechazo de duplicados y errores inesperados.
- Vista: el resumen de fichas adicionales muestra `0 fichas registradas`, `1 ficha registrada` o `N fichas registradas`, sin alterar conteos ni permisos.
- Regresión integrada: el test de gestión existente usa un XPath limitado a `strong` de “Fichas adicionales” dentro de `summary`, valida `1 ficha registrada` y rechaza `1 registradas`.
- RED en base `f2011f50…`: test focal falló con esperado `1 ficha registrada`, obtenido `1 registradas`. GREEN local, PHP 8.5.8: test puntual 1/18 y clase focal completa 33/321. `php -l` de ambos PHP, `node --check tests/browser/run.mjs` y `git diff --check` pasan. No se ejecutó el Browser QA local ni se simularon variables de CI.
- Próximo CI del nuevo SHA: `PENDING_CI`; no heredar PASS de #1209 ni #1210/#1211. Exigir PHP 8.3/8.4/8.5 y Browser QA 13/13 con barrera final PASS. No repetir H01 ni partes aceptadas del CI anterior.

## WIP y límites

- Se verificaron snapshots de 9 worktrees antes del cambio; el checkout de trabajo permanece detached. El worktree que ocupa la rama autorizada conserva sus 4 entradas WIP. `tests/browser/node_modules/` conserva 182 archivos locales sin stage y queda excluido.
- Publicación normal autorizada solo para `tests/browser/run.mjs` y `docs/CURRENT_CHECKPOINT.md`. Antes del push, confirmar otra vez que la rama remota siga en `5e7a54c…`; padre del commit requerido: ese SHA. El nuevo resultado CI se registrará en el informe final; sin segundo push.
- No merge, deploy, Hostinger ni CAND-04. Ley 5 visual y UAT siguen pendientes.
