# CURRENT CHECKPOINT — CAND-03 / Ley 5 cierre V3

**Corte:** 2026-10-23 (America/Bogota). Autoridad: GitHub → CI del SHA → este checkpoint → SPEC/PLAN → evidencia primaria. Estado local: `PARTIAL`; no autocertifica Ley 5. Merge, deploy y CAND-04 no autorizados.

## Proyecto, base y HEAD

- Repo: `sierraglobalcompany-rgb/AlquilaPro`.
- Base: `main@414bcfeae0a3b4f8eea7262eefcb498e3f116307`.
- PR: [#55](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/55), Draft / Open.
- Rama: `impl/cand-03-household-human-first-20261023`.
- HEAD de código probado: `0b0f30071a2ad8b05005416c08bb09bc62e8ce5c` (`fix: render document validation errors and household rows`).
- CI exacta de ese SHA: run `40044047474` / #1194, `completed/success`, PHP 8.3, 8.4 y 8.5. El refresh de este checkpoint es documental; volver a consultar GitHub y CI para el HEAD que resulte de su commit.
- QA server local aislado: `http://127.0.0.1:8181`, actualmente informa el SHA `0b0f300…` por `/__qa/build` y `X-QA-Build-SHA`. Datos y documentos de QA son sintéticos; sin producción.

## Contratos y cambios cerrados

- Causa del bug de hogar: al eliminar todas las filas opcionales, el wizard podía enviar arrays vacíos que no eran normalizados a `[]`; además la vista pública no cargaba el JS común usado por agregar/eliminar filas. Se corrigió en los owners existentes y se probó el flujo completo.
- Causa del 422 opaco: DocumentService mezclaba validaciones esperables con errores operativos y las rutas no re-renderizaban el formulario con errores por campo. Ahora se conserva el contexto seguro, se muestra mensaje accesible y no se simula el valor del input de archivo. Los errores operativos quedan separados y sanitizados.
- No se implementó un parser PDF. El pseudo-PDF de 43 bytes pasó por la ruta del navegador y se persistió en la DB/almacenamiento privado aislados. No se declara rechazo ni validación estructural. D-01 queda `C / DECISION_MATERIAL_PENDIENTE`, según el guardrail de no introducir dependencias o parsers improvisados.
- El envío inválido de JSON fue observado en browser con HTTP 422 en escritorio y móvil táctil; mensaje de validación asociado al textarea, entrada preservada, cero warnings de consola y una entrada de consola que corresponde al HTTP 422 esperado.
- El wizard público completó 1→7, envío final, persistencia y pantalla de solo lectura. Fixture candidato temporal, referencia y filas de ocupantes/mascotas se retiraron después; DB aislada verificada.
- Flujo adicional con fotos: se completó el wizard público 1→7 con un ocupante y una mascota sintéticos, ambas fotos PNG y sus consentimientos; la revisión admin confirmó la asociación a Edificio Demo CAND-03 / DEMO-301. DB mostró 1 ocupante, 1 mascota, 1 referencia, 2 fotos privadas de 90 bytes y envío en `verification`. Se retiraron las filas mediante `CandidateHouseholdService`, los archivos privados y el candidato con guardas por ID/documento/unidad; verificación posterior: candidato, filas, referencias y ambos archivos ausentes.
- Household presenta solicitante y las seis regiones con datos de QA; no crea indicadores de riesgo ni afirmaciones no sustentadas. Las seis regiones conservan su contenido en tablet/móvil sin overflow horizontal.

## QA, evidencia y límites

- Viewports documentados con Playwright real: escritorio 1448×1086 y 1100×850; tableta 768×1024; móvil emulado Pixel 10, táctil 375×812, DPR 3. Capturas full-page y de viewport, incluidos estados inferior móvil y 422 de escritorio/móvil.
- Household: seis regiones medidas en cada tamaño, dos imágenes con `complete=true` y `naturalWidth=240`; cero overflow horizontal. A 1448×1086, tercera fila inicia en y=1154 y la página mide 1490 px; a 1100×850 inicia en y=1179 y mide 1611 px. Ambas requieren scroll para mostrarla. En móvil táctil la página mide 2966 px; al final, la última región queda 51 px por encima de la barra fija.
- Comparación con PNG aprobado de 1448×1086, SHA-256 `be9b8848afed75e31be6df6fd3e1d341b2b960b0d0ca71de68ad33efd3866ae0`: se conservó la jerarquía de seis regiones y el contenido real. La tercera fila no cabe en el alto inicial. El PNG incluye afirmaciones de riesgo/mascotas que la SPEC prohíbe inferir; no se copiaron. `VISUAL=PARTIAL / NOT_PASS`; no es aceptación de Ley 5.
- Wizard: pasos editables 1→7, filas iniciales y eliminación de filas vacías, referencias, revisión y estado post-submit capturados. Un flujo adicional persistió un ocupante y una mascota con fotos/consentimientos y se verificó en administración; se retiró completo tras la evidencia. `readOnly=true`, cero alertas y cero imágenes rotas en revisión final.
- Histórico E-01: la evidencia `historical-photo-lifecycle.json` muestra foto sintética creada, reemplazada con bytes servidos distintos, quitada hasta placeholder y mascota temporal eliminada. La nota de alcance en el CSV histórico era contradictoria; ZIP histórico intacto, discrepancia reconciliada en el nuevo informe.
- PDF: evidencia del pseudo-PDF aceptado, fila/bytes privados verificados y limpieza posterior verificada. No convertir este hecho en PASS.
- Carpeta de entrega (fuera del árbol Git): `D:\Codex\_REPORTES\AlquilaPro\AP-CAND03-PR55-LEY5-CIERRE-V3\`. El informe final y el ZIP se entregan ahí; el ZIP no incluirá el informe ni el propio ZIP.

## Pruebas y CI

- `Documents` focal: 18 tests / 154 assertions / 1 skip; verde con DB aislada por puerto 3307.
- `CandidateWizard`: 14 / 191; verde.
- Regresión relacionada de Candidates: 58 / 548; verde.
- Total de esos grupos: 90 tests / 893 assertions / 1 skip.
- Suite local PHP 8.5.8: 722 tests / 8228 assertions, 3 errores, 7 fallos, 265 warnings, 2 skips. Los 3 errores y 7 fallos se concentran en Installer/Release por `gd` ausente en la configuración CLI. Al cargar GD explícitamente, los 16 tests implicados pasan (174 assertions). La suite local completa no se declara verde.
- CI GitHub del SHA de código `0b0f300…`: PHP 8.3/8.4/8.5 `success`, run #1194. Debe comprobarse otra vez el SHA que resulte del commit de checkpoint.
- `git diff --check` y `php -l` de PHP cambiado fueron limpios en el SHA de código.

## Dictamen por dimensión

`PDF=BLOCKED_C` · `DOCUMENTOS_422=PASS_FOCAL` · `VISUAL=PARTIAL/NOT_PASS` · `WIZARD_FINAL=PASS_FOCAL` · `SEGURIDAD=PASS_FOCAL_CON_LIMITE_PDF` · `REGRESION=FOCAL_PASS; SUITE_LOCAL_NOT_PASS_GD` · `CI=PASS_EN_0b0f300; VERIFICAR_HEAD_DOCUMENTAL` · `BROWSER=PASS_FOCAL_CON_LIMITES_VISUALES/PDF` · `GLOBAL=PARTIAL`.

## Bloqueos y siguiente acción

- A recuperable: suite local requiere GD; se habilitó para los 16 tests afectados y pasaron. No se modificó la configuración del producto.
- B: ninguno pendiente que impida documentar el lote.
- C: resolver estrategia soportable para validar estructura PDF sin parser/dependencia nueva, y revisar la diferencia material entre altura/composición del mockup y datos reales. No cambiar mockup/SPEC para silenciar discrepancias.
- **NEXT ÚNICO:** ChatGPT/Sol audita el informe, ZIP y código de este SHA bajo Ley 5; el owner decide la resolución material de PDF/visual y cualquier autorización futura. PR #55 sigue Draft; no merge, deploy ni CAND-04.
