# CURRENT CHECKPOINT — CAND-03 / Ley 4 V3

**Corte:** 2026-10-23 (America/Bogota). Autoridad: GitHub → CI del SHA → SPEC/PLAN → evidencia primaria. No merge, deploy ni CAND-04.

## Repositorio y fase

- Repo: `sierraglobalcompany-rgb/AlquilaPro`.
- Base: `main@414bcfeae0a3b4f8eea7262eefcb498e3f116307`.
- PR activo: [#55](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/55), Draft / Open, rama `impl/cand-03-household-human-first-20261023`.
- **SHA de código probado por Browser QA:** `23a337b245056ec2db3b2438b19c02308d857a79` (`fix(candidates): include applicant in household view`). Cambios de código en `resources/views/candidate_detail.php` y `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`.
- CI del SHA de código: run `40020885938` / #1191, `completed/success`, PHP 8.3 / 8.4 / 8.5.
- HEAD actual antes de este refresh documental: `e81dbfd4c1395bdeffe0940b957879697671e16e`; su CI run `40022890141` / #1192 también quedó `completed/success` en PHP 8.3 / 8.4 / 8.5.
- Este refresh modifica solo `docs/CURRENT_CHECKPOINT.md`. Después del push, consultar GitHub para el SHA y CI del nuevo HEAD; no tratarlo como cambio de runtime.

## Corrección y pruebas

- Causa raíz: la vista de hogar excluía al solicitante de la lista porque solo aparecía en el resumen. La región no mostraba el ocupante principal.
- RED/GREEN: tests requieren exactamente un marcador de solicitante en estado legado y con tercero real; marcador ausente antes del cambio, presente tras corrección.
- Corrección mínima: mostrar al candidato como solicitante principal desde su fila existente, sin una fila duplicada de ocupante, esquema, API ni servicio nuevo.
- PHPUnit local PHP 8.5: `HumanFirstCandidateDetailViewTest.php` 29 tests / 245 assertions OK; casos focales tercero 1/14 y legado 1/10 OK; `git diff --check` limpio antes del commit de código.

## Browser QA focal

- El build local `/__qa/build` y `X-QA-Build-SHA` devolvieron `23a337b245056ec2db3b2438b19c02308d857a79`. DB aislada; datos sintéticos.
- Viewports: 1448×1086, 1100×850, 768×1024 y móvil táctil emulado 375×812 DPR3. Sin overflow horizontal medido. La barra fija móvil conserva holgura 52–56 px en las capturas inferiores. No es certificación física.
- Seis regiones presentes; solicitante + dos ocupantes, una mascota; datos temporales restaurados al final.
- Roles/probes: viewer solo lectura (mutación 403 y PDF 403), manager administra; CSRF omitido en POST 403; token de continuación inválido 404; foto cruzada entre candidatos 404.
- Mascota: foto privada sintética añadida y reemplazada (respuestas 200, bytes distintos), retirada hasta placeholder; ficha temporal eliminada y fixture final verificado.
- Manual: PDF v3 privado descargado y validado (689 bytes, PDF 1.4, una página A4 y render legible); viewer directo 403. Carga sintética text/plain devolvió 422 y no persistió documento. Falta validar límite de tamaño y PDF corrupto.
- Wizard: paso real “Ocupantes”, 4 de 7, servido 200 con token de fixture no guardado en evidencia; el conteo vacío falla validez y radios de mascotas Sí/No aparecen desmarcados. Envío completo y errores de servidor del wizard pendientes.

## Dictamen visual y bloqueos

- QA funcional: `PASS_WITH_SCOPE`; fidelidad Ley 5: `NOT_PASS`; global: `PARTIAL / NOT_CLOSED`.
- En escritorio 1448×1086 la página real mide 1493 px; la tercera fila inicia en y=1156 y queda fuera del viewport. Geometría y contenido difieren del mockup. No hay paridad automática.
- Mockup SHA-256 `BE9B8848AFED75E31BE6DF6FD3E1D341B2B960B0D0CA71DE68AD33EFD3866AE0`, 1448×1086, se conserva intacto. Contiene indicadores/afirmaciones de riesgo que la SPEC vigente prohíbe. Resolver y congelar autoridad textual/visual antes de implementar esa superficie.
- No aceptar UX ni declarar cierre mientras falten la resolución visual, envío completo del wizard, límites de carga y aceptación humana independiente.

## Evidencia entregable

`C:\Users\felip\Downloads\AlquilaPro_CAND03_PR55_QA_23a337b_2026-10-23` con `FINAL_REPORT.md`, índice HTML verificado (28/28 imágenes), 28 PNG, manifiesto SHA, cobertura, matriz de seis regiones, mediciones, PDF, probes, checkpoint y ZIP con sidecar SHA-256. Capturas históricas conservadas en la carpeta anterior `...QA_a45befa_2026-10-23` y paquete previo `...QA_2a09ebc_2026-10-23`.

## NEXT exacto

Resolver con autoridad versionada la contradicción mockup/SPEC. Luego corregir la composición de escritorio en el mínimo alcance aprobado, completar envío válido del wizard y límites de archivos, repetir Browser QA y CI en el nuevo SHA, y solicitar aceptación UX humana. PR #55 sigue Draft; merge/deploy no autorizados; CAND-04 congelado.
