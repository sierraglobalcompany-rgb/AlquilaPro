# CURRENT CHECKPOINT — CAND-03 / G5

**Corte:** 2026-10-27 America/Bogota. `G5=EN_EJECUCION / PENDING_CI`; `LEY5_UX=PENDING`; `OWNER_UAT=PENDING`. No declarar PASS global ni paridad visual antes de revisar evidencia del SHA publicado.

## Estado GitHub y referencia

- Repo `sierraglobalcompany-rgb/AlquilaPro`; PR #55 OPEN/DRAFT, no merged. Rama `impl/cand-03-household-human-first-20261023`.
- HEAD local detached autorizado y HEAD remoto antes del microbloque: `f2011f50d6b84a2eb2969f5fe92e82b42e6143f5`; padre `cd3b1d7aa72bd6a80a8ae044dcbe45e56a086e9a`.
- CI #1209 (`40500170485`) fue SUCCESS solo para `cd3b1d7…`; no certifica el HEAD posterior.
- CI #1210 (`40525573980`) terminó FAILURE para `f2011f50…`: PHPUnit PASS en PHP 8.3/8.4/8.5; Browser QA 11/13, 60 PNG. La falla primaria del escenario de mascota es `invalidPetForm.evaluate is not a function`: faltó esperar la Promise de `formByRecord()` en `tests/browser/run.mjs`. `browser-harness-fatal` es secundario al rechazo legítimo del 422 del escenario fallido.
- Evidencia del CI #1210: manifest SHA-256 `fe15e68bba450f24ee7ccd9bf5fa5b2602bc259fb17f5624996447a3e7c31551`; 65/65 hashes internos. Artifact oficial GitHub ID `12744733454`, digest `sha256:ab50d0d9125b22c5548f162abb93c17fa5d2276895590ab3fbc64b89393dbd1f` (distinto del ZIP de entrega).

## Microbloque focal G5

- Harness: el cuarto llamado a `formByRecord()` ahora usa `await`; los otros tres consumidores ya lo hacían. Sin cambios a clasificador, negativos esperados, fases ni capturas.
- Vista: el resumen de fichas adicionales muestra `0 fichas registradas`, `1 ficha registrada` o `N fichas registradas`, sin alterar conteos ni permisos.
- Regresión integrada: el test de gestión existente usa un XPath limitado a `strong` de “Fichas adicionales” dentro de `summary`, valida `1 ficha registrada` y rechaza `1 registradas`.
- RED en base `f2011f50…`: test focal falló con esperado `1 ficha registrada`, obtenido `1 registradas`. GREEN local, PHP 8.5.8: test puntual 1/18 y clase focal completa 33/321. `php -l` de ambos PHP, `node --check tests/browser/run.mjs` y `git diff --check` pasan. No se ejecutó el Browser QA local ni se simularon variables de CI.
- Próximo CI del nuevo SHA: `PENDING_CI`; no heredar PASS de #1209 ni considerar CI #1210 como validación del fix. No repetir H01 ni partes aceptadas del CI anterior.

## WIP y límites

- Se verificaron 9 worktrees antes del cambio; el checkout de trabajo permanece detached por autorización complementaria del owner. Los otros 8 worktrees no se modificaron. `tests/browser/node_modules/` conserva 182 archivos locales sin stage y queda excluido.
- Una publicación normal autorizada para las cuatro rutas de este microbloque. Antes del push, confirmar otra vez que la rama remota siga en `f2011f50…`; padre del commit requerido: ese SHA.
- No merge, deploy, Hostinger ni CAND-04. Ley 5 visual y UAT siguen pendientes.
