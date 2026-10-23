# AlquilaPro — CURRENT CHECKPOINT

**Fecha:** 2026-10-23 (America/Bogota). **Alcance:** rama activa de CAND-03. Orden de autoridad: GitHub HEAD → CI del SHA exacto → este checkpoint → SPEC/PLAN → historial. Este archivo no sustituye las evidencias externas ni autoriza merges/deploys.

## Estado vivo y fuentes de verdad
- **Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`.
- **Main estable:** `414bcfeae0a3b4f8eea7262eefcb498e3f116307`; [PR #54](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/54) se fusionó por squash con autorización. **Sin deploy Hostinger**. PR #52 y PR #53 cerrados sin merge como `SUPERSEDED_BY_PR54`; sus evidencias permanecen en GitHub.
- **CAND-03 activo:** [PR #55](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/55), rama `impl/cand-03-household-human-first-20261023`, `Draft`, `mergeable=true`, no fusionado; base `main@414bcfeae0a3b4f8eea7262eefcb498e3f116307`.
- **Último HEAD de producto auditado:** `66a0d9a856366a315813ab6c84a2954f9b8f2263`, sin cambios al código tras QA. **Esta actualización documental crea un nuevo HEAD**: verificar nuevo commit y CI; no declarar que las capturas se tomaron sobre el SHA documental nuevo.
- **CI [#1179](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39911037860) del HEAD de producto:** `completed/success` en PHP 8.3, 8.4 y 8.5. Suite completa validada según GitHub Actions.
- **Browser QA V2 CAND-03:** `PASS_WITH_SCOPE` sobre HEAD de producto exacto `66a0d9a...`. ZIP `ALQUILAPRO_CAND03_PR55_QA_V2_20261023.zip` SHA256 `bbef35ad9c834a73bad6d80f5033aeea512fa178483a29c484e80ae9914c6c92`; validación independiente **9/9 SHA256, bytes, 7 PNG incluyendo mockup, seis capturas de navegador en dimensiones declaradas**. Referencia `03_OCUPANTES_MASCOTAS.png` SHA256 `be9b8848afed75e31be6df6fd3e1d341b2b960b0d0ca71de68ad33efd3866ae0`.
- **V1 Browser QA:** `BLOCKED_QA_TOOLING` exclusivamente por helpers `rect/round` fuera del contexto de `page.evaluate`; **SUPERSEDED_BY_V2**; no repetir.

## Contratos, implementación y cobertura
- **SPEC §9.3:** `docs/superpowers/specs/2026-10-17-candidates-human-first-redesign.md`. **Plan Task 3:** `docs/superpowers/plans/2026-10-17-candidates-human-first-redesign.md`. **Diseño:** `docs/design/CANDIDATES_VISUAL_BASELINE_2026-10-17.md` §03. No inventar detalles que solo figuran en el mockup.
- **Campos reales:** `occupants_count`, `occupants_notes`, `pets_declared`, `pets_notes`; separar `NULL` de cero legítimo. Como `pets_declared=0` es valor por defecto de DB/wizard, el cero de borrador no confirma ausencia. Si enviada, comunicar `Sin mascotas registradas en la solicitud` sin afirmar `No tiene mascotas`.
- **Archivos del PR:** `resources/views/candidate_detail.php` (solo sección household), `assets/app.css` (reglas scoped), `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php` (cuatro tests), este checkpoint. Sin nuevos servicios, tablas, migraciones, rutas, APIs, dependencias ni cambios de permisos.
- **Browser QA focal:** A enviado 3 ocupantes/mascota, B borrador vacío y C enviado cero, navegación GET Work↔Household, foco y overflow horizontal `PASS` en escenarios medidos; sin errores reportados de consola. A móvil final: último contenido a ~72,85 px antes de la barra fija. C móvil fue capturado inicialmente, **no** con scroll final; no atribuirle esa prueba. Matriz de roles, WCAG integral y dispositivos físicos `NOT_CERTIFIED`.
- **Fidelidad global:** `NOT_CERTIFIED`; mockup incluye perfiles/fotos/vacunas/acciones/scores sin persistencia ni contrato. CAND-03 cumple alcance factual/visual verificado, no paridad 1:1 ni aprobación humana integral.

## Leyes y gates
- **KISS:** PHP SSR/Slim/PDO y componentes existentes, sin arquitectura compleja.
- **Antirruido:** un PR activo para CAND-03 y un checkpoint vivo; eliminar estados V1/CI pendiente como autoridad actual sin perder historial.
- **Antiparches:** no tocar producto por defecto 0 de mascota sin reproducción causal nueva; el bloqueo corregido V2 fue de colector local, no de producto.
- **UX/UI Pro:** respetar jerarquía del PNG sin inventar individuos/acciones/estados; evidencias y reservas reales.

**NEXT ÚNICO:** validar CI del **nuevo commit solo Markdown** y que diff contra el HEAD de producto modifique únicamente este checkpoint. Luego presentar **al owner la aceptación de diseño factual con reservas y autorización explícita de merge exclusivo del PR #55 a main**. Sin esa autorización, mantener Draft/NO MERGE; Hostinger continúa NO DEPLOY. No iniciar CAND-04 hasta cerrar esta compuerta.
