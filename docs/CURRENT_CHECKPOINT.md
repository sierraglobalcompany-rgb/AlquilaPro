# CURRENT CHECKPOINT — CAND-03 / CIERRE H01-H02 V4

**Corte:** 2026-10-23 America/Bogota. Estado: PARTIAL; Codex no autocertifica Ley 5. No merge, deploy ni CAND-04.

## Repo y autoridad viva
- Repo: sierraglobalcompany-rgb/AlquilaPro.
- Base: main@414bcfeae0a3b4f8eea7262eefcb498e3f116307.
- PR #55: OPEN / DRAFT / NOT MERGED; rama impl/cand-03-household-human-first-20261023.
- Código H01/H02: 3e830163a411806ad093a35c3f6bfae30aad1538.
- CI del SHA de código: run #1196 / 40051385506, completed/success; PHP 8.3, 8.4 y 8.5.
- Este refresh cambia solo el checkpoint. Verificar el CI del HEAD documental resultante antes de reportar cierre de CI final.

## H02 — corregido focalmente
Causa: la vista usaba pets_answered_at como respuesta, copy dirigido al candidato dentro de la revisión admin, no contaba fichas reales de mascotas y duplicaba observaciones; las fichas apilaban metadatos. En el owner existente se separaron respuesta/fecha, se mantuvo histórico false sin fecha como No confirmado, se corrigieron copy/conteos, se movieron notas una vez a Reglas y observaciones y se compactó el flujo de datos desktop. No se duplicó al solicitante ni se infirieron score/vacunación.

Pruebas locales en PHP 8.5.8: HumanFirstCandidateDetailViewTest, 30 tests / 261 assertions, PASS; php -l y git diff --check PASS. Los cambios funcionales son assets/app.css, resources/views/candidate_detail.php y tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php.

Preview de componente con Playwright y datos ficticios, no ruta autenticada:
- 1448×1086: ocupantes 360 px; fila 3 y=933; documento 1413 px.
- Mejora en el mismo harness contra la medición pre-refinamiento: 424→360 px y y=997→933. La referencia histórica de aplicación real 592 px/y=1154 pertenece a otra corrida y no es comparación controlada.
- 1100×850, 768×1024 y móvil táctil 375×812: seis regiones, sin overflow horizontal. Fixture largo: altura de ocupantes 589 px con crecimiento natural. Foco de teclado visible. Render solo lectura: cero controles de gestión.
- No se incluye foto en el fixture. Las capturas no validan ACL/servicio de foto ni estado de sesión. A 1448×1086 la fila 3 empieza dentro del viewport, pero su contenido continúa bajo el viewport.
- Evidencia V4: D:\Codex\_REPORTES\AlquilaPro\AP-CAND03-PR55-CIERRE-H01-H02-V4\.

Browser QA de aplicación y rutas: BLOCKED_B. La app y DB de AlquilaPro no escuchaban. El único DB container activo era erp_v14_qa de otro entorno y se dejó intacto. CandidatesAdminRoutesTest::testHouseholdAdminCrudAndPrivatePhotoRouteAreCandidateScoped falló antes de assertions por conexión DB en DatabaseConnector.php:27.

## H01 — PDF
BLOCKED_C. DocumentService sigue verificando tamaño/MIME sin validar estructura. El fixture sintético corrupto de 43 bytes fue rechazado por pdfinfo por trailer/xref faltantes (exit 1) en la evidencia previa; esta tarea no repitió una carga en la app. No se añadió parser casero, Composer dependency ni binario externo. El owner debe elegir una estrategia desplegable y compatible con su plan Hostinger entre parser PHP con extensiones verificadas o proceso/binario externo con soporte demostrado.

## CI y límites
- CI #1196 del SHA de código 3e83016: PHP 8.3/8.4/8.5 success.
- El CI del commit de este checkpoint debe verificarse sobre su SHA exacto.
- PR sigue Draft; H01 bloqueado; Browser QA real pendiente; aceptación independiente de Ley 5 pendiente. CAND-03 no está cerrado globalmente.
- Evidencia histórica V3 conservada: D:\Codex\_REPORTES\AlquilaPro\AP-CAND03-PR55-LEY5-CIERRE-V3\FINAL_REPORT.md y su ZIP; no se regeneró ni duplicó.
- Informe V4: AP-CAND03-PR55-CIERRE-H01-H02-V4_FINAL_REPORT.md y paquete nuevo en la carpeta externa V4 indicada arriba.

## NEXT ÚNICO
Owner: proveer/restaurar una app+DB aislada de AlquilaPro para Browser QA autenticada y decidir la estrategia de validación PDF. Después, Codex repite rutas, ACL/fotos y pruebas faltantes sobre el HEAD vigente; ChatGPT/owner audita Ley 5. No avanzar a CAND-04.
