# AlquilaPro — CURRENT CHECKPOINT

**Corte:** 2026-10-22. **Fuente operativa única:** GitHub HEAD → CI del SHA exacto → este checkpoint → SPEC/AGENTS. No reemplaza historial de evidencias del PR #45.

## Estado y autoridad
| Campo | Estado |
| --- | --- |
| Repo | `sierraglobalcompany-rgb/AlquilaPro` |
| `main` base | `94ba5f486b158c2b1fed753f26c63f7b3570c97c` — merge de [PR #45](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/45) |
| Fase activa | CAND-02, Información laboral (`?section=work`) |
| Rama | `impl/cand-02-work-human-first-20261022` |
| Fuente visual | `02_INFORMACION_LABORAL.png` (Library original) + `docs/design/CANDIDATES_VISUAL_BASELINE_2026-10-17.md` |
| SPEC | `docs/superpowers/specs/2026-10-17-candidates-human-first-redesign.md`, §9.2, §§10–17 |
| CI de producto CAND-02 | [#1167 / run 39816216496](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39816216496): **SUCCESS PHP 8.3 / 8.4 / 8.5** para `00d86e91e59b98c1008c2f195a5fda1be145a539` |
| Browser QA V5 | **PASS_WITH_SCOPE**; ZIP `ALQUILAPRO_CAND02_QA_V5_20261022.zip` SHA-256 `c35454f34bbb954c61a9e5a6d2e0045703cf909e76588e4d5843e8bf4b3b283b`; sin aprobación visual global |
| Decisión visual del owner | **PENDING**; CAND-01 aprobada ≠ CAND-02 aprobada |
| CAND-02 merge / despliegue | NO AUTORIZADO / NO DESPLEGADO |
| CAND-03+ | NOT_STARTED |

## CAND-01 integrado
El PR #45 fue **fusionado a `main`** por aprobación expresa del owner. HEAD de producto auditado `aa9b2619d34451d406f05a019842da81f458d7a1`; commit documental posterior `e925b24f2c318eece7bdf9c9ad5092944068916c`. CI #1165 SUCCESS PHP 8.3/8.4/8.5. La dirección visual de Solicitud y su QA residual fueron aprobados; **`VISUAL_GLOBAL` y WCAG integral no fueron certificados**. No reabrir QA V6/V9/V12/G3 salvo defecto nuevo reproducido. Hostinger no desplegado.

## Contrato CAND-02
La ruta SSR y el shell compartidos NO se modifican. La vista `resources/views/candidate_detail.php` muestra la sección de trabajo con:
- Datos reales `employment_status`, `employer_name`, `occupation` agrupados, sin filas vacías ni valores inferidos.
- `monthly_income` solamente cuando es numérico, en formato COP mediante **el mismo `$humanMoney` de Solicitud**, movido al scope común del view (1 owner).
- Notas únicamente cuando son reales; escaping y saltos de línea seguros.
- Empty state `No se registró información laboral.` si todos los hechos faltan; cero **declarado** es válido, cero por ausencia NO.
- Sin sectores, antigüedad, fuentes extra, documentos, score/riesgo, verificación de ingresos, historial o CTAs ficticios del PNG.
- CSS delimitado a `.candidate-work-*` dentro de `assets/app.css`; dos columnas sólo a partir de 1024px con ingreso, una en móvil/tableta.
- Pruebas focales causales en `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`, manteniendo sus contratos de Solicitud.

## Auditoría independiente y QA V5 (22-10-2026)

**Dictamen:** `CAND02_BROWSER_QA=PASS_WITH_SCOPE`; **`VISUAL_GLOBAL=NOT_CERTIFIED`** y aprobación de la dirección visual por el owner **PENDING**. Revisión independiente de imágenes, manifiesto y ZIP: SHA256 ZIP `c35454f34bbb954c61a9e5a6d2e0045703cf909e76588e4d5843e8bf4b3b283b`; CRC OK, 8 entradas exactas (5 PNG, JSON, informe, manifiesto). Mockup ORIGINAL `02_INFORMACION_LABORAL.png`, 1448×1086 RGB, SHA256 `fc889a00c6ab29324b1efb71e48ae68f0708542b8c6c7f20b81d52cd1aa9e2c1`, no confundir con captura `A_REF` de igual tamaño.

| Evidencia | SHA-256 de captura |
| --- | --- |
| `A_REF_1448x1086.png` (APP real en medidas de mockup, **NO** mockup) | `c925d235fa660430711686a3dbba3ba2b6d9501caf2c324032f80e53f60caa89` |
| `A_DESKTOP_1440x900.png` | `607b3caa49aa97d2f35379d6e71cd50538643188f12c5b7b8ee9bdfdb796e533` |
| `A_TABLET_768x1024.png` (menú abierto; 6 enlaces disponibles para `candidates.view`) | `6015837f3edbffd8cb51d0f2d0416a7badd2f4486538581d7dabc1431f2424d1` |
| `A_MOBILE_375x812.png` (scroll al fondo, barra fija no tapa contenido final) | `0a462275ddfddf64d77d6387431b067e730eac5d038c5626639f411d1df69e9d` |
| `B_EMPTY_375x812.png` | `a5a50c3951b2285ecf2190a36de966a150d9abbf2ee2b72462b81267ec6ebf88` |

**Verificado dentro del alcance V5:** usuario gestor sintético con sólo `candidates.view`, autenticación y cambio forzado, A/B 200, una única sección work, ingreso declarado `$ 5.200.000 COP`, notas veraces, estado B vacío sin inventar cero, ningún control administrativo/contratos no autorizado; CSS/JS/favicon 200 y SHA CSS servido coincidente `48a31231b6d6c8f7151318e53a8a69396f236538545774228294b3db2cc2289c`; sin errores de consola, sin overflow observado en viewport real. Foco visible `Inicio`, 52px de separación al final de móvil.

**Comparación independiente con mockup:** la vista real usa los datos que existen: resumen laboral, ingreso y notas. El mockup enseña además *otros ingresos, antigüedad, contrato, historial, comprobaciones, documentos, análisis/score y recomendaciones* sin respaldo actual. **No inventarlos ni hacer paridad falsa.** La pantalla real es sustancialmente más simple que el PNG original; la dirección estética de esta versión requiere decisión expresa del owner, no `VISUAL_PASS` automático.

**Observación:** `Apps` aparece con `aria-current="page"` en barra inferior; se debe evaluar semánticamente sólo si se comprueba conflicto real de navegación, no corregir por parche. El enlace `Información laboral` tiene su propio `aria-current` correcto.

**Gates cerrados:** CI #1167 PHP 8.3/8.4/8.5; QA V5 funcional + responsive de la sección work. Los tests previos cubren escape, cero declarado y notas solas; **no se volvieron a ejecutar** en V5. Ningún merge / deploy / CAND-03. El checkout efímero de Codex no pudo borrarse por política Windows; mantenerlo aislado, nunca limpiar repositorios ajenos a la ejecución.

**Único NEXT:** presentar la comparación fiel **mockup original vs captura A_REF** al owner y solicitar aprobación de la *dirección visual real* con sus exclusiones. Si no aprueba, recopilar **una diferencia visual concreta y implementable con datos reales**, sin rehacer QA completo. Tras aprobación, converger documentación, confirmar CI del HEAD documental nuevo y solicitar **autorización explícita de merge**; Hostinger sigue bloqueado.

## Leyes durables
KISS → Cero Ruido (eliminar/unir sólo con evidencia) → Anti-parches (causa, RED/GREEN, regresiones) → Human-First/UX/UI Pro (mockup sin capacidades falsas) → Fidelidad visual verificable.

`NEXT=CAND02_OWNER_VISUAL_DIRECTION_DECISION_ON_V5_EVIDENCE`
