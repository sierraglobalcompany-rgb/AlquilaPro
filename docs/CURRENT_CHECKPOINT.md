# CURRENT CHECKPOINT — CAND-03 / G5 AUTHENTICATED BROWSER QA

**Corte:** 2026-10-26 America/Bogota. `G5=EN_EJECUCION/NO_APROBADO`. CI #1207 ejecutó el servidor y Browser QA real; los tres PHPUnit pasaron, pero el job PHP 8.5 terminó `FAIL` con Playwright 8/11. El artifact sintético es íntegro y conserva 48 capturas. No merge, deploy, Hostinger ni avance a CAND-04.

## Repo y autoridad viva
- Repo: sierraglobalcompany-rgb/AlquilaPro.
- Base inicial de esta unidad: `6140932cfddc1d08825480b6f033651c7005aea6`.
- PR #55 contrastado en GitHub 2026-10-26: OPEN / DRAFT / NOT MERGED; rama `impl/cand-03-household-human-first-20261023`. La corrección test-only parte del HEAD cubierto por CI #1207: `a1c07ca374bdcad09867174e1d145995cbb2949b`. El `main` remoto observado es `f682d09e140e0b86f5704630712df5116dad9d09`; los merges sintéticos de CI se comparan con esa base.
- CI #1204 / run `40346507909` certifica el HEAD anterior: 3/3 jobs success, PHP 8.3/8.4/8.5, 736 tests por job y 1 skip por job; checkout sintético `ec3e699a0d5dc3e0857cddf60081fc0719a8d057`.
- CI #1205 / run `40368250835`, HEAD `3a6023a766685a430a4191f203e767ce2465bd08`, checkout sintético `9d8137ebf0586b633bcb4dbb504883df9b0f67df`: PHPUnit PHP 8.3 y 8.4 PASS; PHP 8.5 PHPUnit PASS, fixture MariaDB sintética PASS, pero el chequeo HTTP de inicio de la app no obtuvo `/health` en 45 s. Playwright `NOT_RUN`; upload reportó que no había archivos y no creó artifact. El paso no imprimió el log PHP guardado en RUNNER_TEMP, así que la causa de inicio no quedó determinada.
- CI #1206 / run `40376198301`, HEAD `26d45a3123dc7164697a186bc4403704809543ed`, merge checkout `7305d9be58f089d0e26dc6311d4e7ed6e3e9ac17`: PHPUnit 736 tests, 1 skip por job, PASS en 8.3/8.4/8.5; fixture sintética PASS; instalación del harness Playwright y Chromium PASS. `/health` falló porque faltaban `ALQUILAPRO_SMTP_*` y `ALQUILAPRO_CRON_SECRET`; `bootstrap/app.php:417` rechazó la configuración. Ejecución Playwright `NOT_RUN`; artifact `MISSING` (ruta sin archivos).
- CI #1207 / run `40403271208`, PR HEAD `a1c07ca374bdcad09867174e1d145995cbb2949b`, merge checkout `49dfd6f7686d9d2f1dfd61debe913793c84f653b`: PHPUnit PASS en PHP 8.3/8.4/8.5 (736 tests, 8.499 assertions, 1 skip por versión); preparación de DB sintética, servidor PHP y `/health` PASS. PHP 8.5 falla en Playwright con 8/11 checks. Dos causas del harness están confirmadas: el viewer no tiene formularios de gestión/CSRF en household, y el selector global `.last()` llega a un formulario de mascota. El tercer evento solo tiene fingerprint de consola y ruta fallida `/admin/documents/2/file`; la causa no quedó demostrada. Artifact `12691570069`, SHA-256 `6ca67fe6932600d7557be3c984e3998f432580530863dfba311472f3a4c1bd34`, 48 capturas y 53/53 hashes internos verificados.
- Configuración SMTP sintética test-only publicada en `a1c07ca…`, solo en el paso `Start real AlquilaPro PHP application on loopback`, loopback `127.0.0.1:2525`; no se instaló sink ni se conectó a proveedor externo. La instrumentación focal de `tests/browser/run.mjs` para CSRF válido viewer, formularios por región y causa de eventos de navegador está preparada localmente y `PENDING_CI` hasta observar su ejecución. No se cambiarán permisos ni código de producto.

## H02 — corregido focalmente
Causa: la vista usaba pets_answered_at como respuesta, copy dirigido al candidato dentro de la revisión admin, no contaba fichas reales de mascotas y duplicaba observaciones; las fichas apilaban metadatos. En el owner existente se separaron respuesta/fecha, se mantuvo histórico false sin fecha como No confirmado, se corrigieron copy/conteos, se movieron notas una vez a Reglas y observaciones y se compactó el flujo de datos desktop. No se duplicó al solicitante ni se infirieron score/vacunación.

Pruebas locales en PHP 8.5.8: HumanFirstCandidateDetailViewTest, 30 tests / 261 assertions, PASS; php -l y git diff --check PASS. Los cambios funcionales son assets/app.css, resources/views/candidate_detail.php y tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php.

Preview de componente con Playwright y datos ficticios, no ruta autenticada:
- 1448×1086: ocupantes 360 px; fila 3 y=933; documento 1413 px.
- Mejora en el mismo harness contra la medición pre-refinamiento: 424→360 px y y=997→933. La referencia histórica de aplicación real 592 px/y=1154 pertenece a otra corrida y no es comparación controlada.
- 1100×850, 768×1024 y móvil táctil 375×812: seis regiones, sin overflow horizontal. Fixture largo: altura de ocupantes 589 px con crecimiento natural. Foco de teclado visible. Render solo lectura: cero controles de gestión.
- No se incluye foto en el fixture. Las capturas no validan ACL/servicio de foto ni estado de sesión. A 1448×1086 la fila 3 empieza dentro del viewport, pero su contenido continúa bajo el viewport.
- Evidencia V4: D:\Codex\_REPORTES\AlquilaPro\AP-CAND03-PR55-CIERRE-H01-H02-V4\.

Browser preview de componente con fixture ficticio, sin login, sesión ni DB de aplicación: 1440×900 escritorio y 390×844 móvil emulado/táctil; seis regiones; sin overflow horizontal; consola sin mensajes/errores/warnings observados. Evidencia histórica en el paquete V8. Browser QA de aplicación autenticada corrió en CI #1207; consulta arriba para resultado y artifact. Las rutas de foto/ACL pasan con DB MariaDB 11.4 aislada en la suite; una captura por sí sola no certifica ACL.

## H01 — PDF
- Causa: `DocumentService` y `CandidateDocumentService` aceptaban archivos por tamaño/MIME sin validar estructura; un fixture del portal tenant también era un pseudo-PDF sin xref/trailer.
- Ambos owners llaman a `PdfUploadValidator`, que integra `smalot/pdfparser` v2.13.1 con límites de recursos. Máximo de archivo: 10 MiB; límites internos de streams/objetos/recursión; rechaza truncados, MIME falso, ZIP/polyglot, cifrado, acciones activas y adjuntos incrustados. No modifica bytes aceptados ni afirma detectar malware.
- Dependencia directa `smalot/pdfparser` (`^2.13.1`, lock `v2.13.1`, LGPL-3.0), necesaria para parseo estructural; requiere revisión de obligaciones de distribución. No hay dictamen legal.
- Corpus H01: SHA-256 `f37a204b56149d2c3a331ff7242bfe305de96a747640e68084ea795ce5618f5c`; manifiesto 13/13 archivos/hashes coinciden; validador 13/13 decisiones esperadas (6 ACCEPT, 7 REJECT). La matriz está en el paquete V8.
- RED observado antes de implementación: cinco tests nuevos fallaban porque faltaba `PdfUploadValidator`. RED de regresión completa identificó siete allowlists de arquitectura y el fixture tenant obsoleto; corregidos en los owners de tests/fixture. Focal final afectada: 8 tests / 261 assertions, PASS.

## Verificación local y límites
- PHP 8.5.8, PHPUnit 11.5.57, MariaDB 11.4 efímera y schema/credenciales aleatorias exclusivos; el contenedor temporal fue eliminado.
- Suite completa final: 731 tests, 8.479 assertions, exit 0; 2 skips y 267 warnings. Los warnings vienen de `ServerReadyBuilderTest` al borrar objetos Git temporales (`unlink: Permission denied`, y directorios no vacíos); no se modificó esa suite fuera del alcance.
- `composer validate --no-check-publish`: válido. PHP lint: sin errores. `git diff --check`: sin errores; Git avisó normalización LF→CRLF en algunos archivos.
- La primera suite completa registró 7 fallos y 1 error; tras alinear las allowlists/owners y reemplazar el fixture tenant, la focal afectada pasó y la suite completa terminó exit 0.
- Los resultados locales de 2026-10-25 son históricos. CI #1207 cubre el HEAD `a1c07ca…`: matriz PHPUnit verde, Browser QA incompleto y fallido; los arreglos focales del harness están pendientes de CI sobre un nuevo HEAD. Hostinger y deploy no se ejecutaron.
- Aceptación independiente Ley 5 pendiente; Codex no autocertifica PASS global. `TEMP_SENSITIVE=BLOCKED`: no se leyó ni limpió la ruta temporal sensible.
- Evidencia histórica V3/V4 conservada en sus carpetas previas; este cierre añade el paquete V8 sin sobrescribirlos.

## NEXT ÚNICO
Esperar y revisar el CI asociado al único commit test-only de `tests/browser/run.mjs` y este checkpoint (`PENDING_CI` al publicar). Si el CI o una causa real de producto falla, documentar y detenerse sin otra publicación. Ley 5 independiente y UAT siguen pendientes; PR #55 permanece Draft. No merge, deploy ni avance a CAND-04.
