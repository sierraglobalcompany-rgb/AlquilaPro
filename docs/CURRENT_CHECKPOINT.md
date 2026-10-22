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
| Estado CI CAND-02 | **PENDING del nuevo SHA; no atribuir CI #1165 a CAND-02** |
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

## Gates
1. RED dirigido: pruebas deben fallar contra el view anterior por la causa correcta (COP, empty, notes) y pasar contra nuevo código; nunca debilitar aserciones previas.
2. Lint PHP, pruebas focales, suite y CI PHP 8.3/8.4/8.5 **del nuevo SHA**.
3. Browser QA real de la sección `work`: 1440×900, 768×1024, 375×812; candidato con datos declarados + vacío representativo, permisos, foco, overflow y comparación con PNG 02. **No afirmar `VISUAL_PASS` sin evidencia.**
4. Auditoría independiente ChatGPT y aprobación de dirección visual por owner; sólo entonces considerar merge de PR CAND-02. No Hostinger.

## Leyes durables
KISS → Cero Ruido (eliminar/unir sólo con evidencia) → Anti-parches (causa, RED/GREEN, regresiones) → Human-First/UX/UI Pro (mockup sin capacidades falsas) → Fidelidad visual verificable.

`NEXT=CAND02_RED_GREEN_CI_EXACT_SHA_THEN_BROWSER_3_VIEWPORTS_OWNER_VISUAL_DECISION`
