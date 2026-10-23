# CURRENT CHECKPOINT — CAND-03 / Ley 4 V3

**Corte:** 2026-10-23 (America/Bogota). Autoridad: GitHub → CI del SHA → SPEC/PLAN → evidencia primaria. No merge, deploy ni CAND-04.

## Repositorio y fase

- Repo: `sierraglobalcompany-rgb/AlquilaPro`.
- Base: `main@414bcfeae0a3b4f8eea7262eefcb498e3f116307`.
- PR activo: [#55](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/55), Draft / Open, rama `impl/cand-03-household-human-first-20261023`.
- **HEAD de código validado por Browser QA:** `23a337b245056ec2db3b2438b19c02308d857a79` (`fix(candidates): include applicant in household view`). Cambios en `resources/views/candidate_detail.php` y `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`.
- CI run `40020885938` / #1191: `completed/success`, exact SHA `23a337b245056ec2db3b2438b19c02308d857a79`; PHP 8.3, 8.4 y 8.5 en verde.
- Este checkpoint se añadirá como commit solo documental sobre el SHA de código anterior. Comprobar el nuevo PR HEAD y su CI después del push antes de cualquier afirmación sobre el estado más reciente. El HEAD de código probado y las capturas no cambian por ese commit documental.

## Corrección y pruebas

- Causa raíz: la plantilla de ocupantes excluía al solicitante, bajo la premisa de que solo aparecía en el resumen. La región del hogar quedaba sin mostrar al solicitante como ocupante principal.
- RED/GREEN: se añadió el marcador de solicitante a los tests de legado y tercero real; se observó el marcador ausente antes del cambio; cambio mínimo en la vista, sin nueva fila duplicada, tabla, API o servicio.
- PHPUnit local PHP 8.5: `HumanFirstCandidateDetailViewTest.php` 29 tests / 245 assertions OK; tercero real 1/14 y legado 1/10 OK. `git diff --check` limpio antes del commit.
- PR #55 incluye la implementación anterior de CAND-03; el presente checkpoint cubre el ajuste de solicitante y el Browser QA focal, no afirma que todos los estados de producto estén cerrados.

## Browser QA — dictamen

- Resultado funcional: `PASS_WITH_SCOPE`; fidelidad visual Ley 5: `NOT_PASS`; estado global: `PARTIAL / NOT_CLOSED`.
- Build servido en `/__qa/build` y `X-QA-Build-SHA`: `23a337b245056ec2db3b2438b19c02308d857a79`.
- Browser QA se realizó contra servidor y DB aislados, con datos sintéticos. Cobertura: desktop 1448×1086 y 1100×850; tablet 768×1024; móvil táctil emulado 375×812, DPR3. Sin overflow horizontal medido. Navegación móvil, roles viewer/manager, estados de mascota con/sin foto, manual PDF vigente/ausente y denegaciones 404/403 focales.
- Las seis regiones están presentes. En 1448×1086 el documento mide 1493 px de alto y la tercera fila comienza en y=1156; no cabe en el viewport inicial. El contenido y la geometría difieren del mockup. El mockup contiene puntuaciones/afirmaciones de riesgo prohibidas por la SPEC vigente; la imagen original no se modificó. No declarar fidelidad hasta resolver la contradicción textual/visual y repetir la comparación.
- Quedan sin demostrar el wizard público vacío/completo/validaciones, los bordes de carga/reemplazo/eliminación de foto, validaciones de carga del manual y más casos CSRF/403/404. No se concede aceptación UX humana.
- Capturas largas de móvil/tableta esconden temporalmente la barra fija solo para evitar el artefacto de superposición de Playwright; capturas de viewport conservan la UI real. La prueba móvil no certifica hardware físico.

## Evidencia entregable

Carpeta: `C:\Users\felip\Downloads\AlquilaPro_CAND03_PR55_QA_23a337b_2026-10-23`.

- `FINAL_REPORT.md`, `index.html`, ZIP, `capture-manifest.csv`, `region-matrix.csv`, `audit-10-pasadas.csv`, `metrics.json` y SHA-256.
- 26 PNG con referencia intacta, capturas reales, comparativa, permisos y PDF; PDF privado ficticio validado y render inspeccionado.
- Capturas históricas anteriores conservadas en `C:\Users\felip\Downloads\AlquilaPro_CAND03_PR55_QA_a45befa_2026-10-23`; paquete histórico previo conservado en `C:\Users\felip\Downloads\AlquilaPro_CAND03_PR55_QA_2a09ebc_2026-10-23`.

## NEXT exacto

Resolver con una autoridad versionada la contradicción entre el mockup y la SPEC sobre contenido de riesgo. Luego hacer la corrección visual mínima aprobada, ejecutar los flujos restantes y repetir mediciones/capturas/CI en el nuevo HEAD. Mantener PR #55 Draft; merge y deploy no autorizados; CAND-04 congelado.
