# AlquilaPro — CURRENT CHECKPOINT

**Fecha de corte:** 2026-10-21 (actualización G3 posterior a V12 y MEDIA-PROBE-01). **Rol:** única referencia operativa del estado actual. El historial completo de G1/G2/G3 queda en Git y evidencias del PR; no interpretar commits históricos como HEAD.

## 1. Estado comprobado y autoridad

| Campo | Estado |
| --- | --- |
| Repositorio | `sierraglobalcompany-rgb/AlquilaPro` |
| Rama activa | `impl/cand-01-shell-overview-20261017` |
| PR | [#45](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/45), OPEN / DRAFT / NOT_MERGED |
| `main` observado | `243dea9c821645dd554cc5fdd66cb6c9a0d4f2ce`; volver a comprobar antes de escribir |
| Último HEAD remoto acreditado (base de este microbloque) | `24a6918ee61d69b1cf8e53156abcb30acf833c27` |
| CI exacto de la base | [#1162 / run 39703346438](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39703346438): SUCCESS PHP 8.3/8.4/8.5; no atribuirlo al nuevo cambio de hitos |
| Fase | CAND-01 / G3 — Solicitud, seis tarjetas en tres filas |
| Browser QA | V9 cinco viewports tipografía; V12 escritorio/móvil ocho secciones y matriz 17, con foto ausente (HTTP 404 en staging). Fidelidad **RED_ESENCIAL_NOT_CERTIFIED** |
| Tipografía V11 | Publicada en `24a6918e`; CI #1162 GREEN; no repetir V9. |
| Microbloque visual actual | Eliminar estilo verde redundante de hitos `recorded` no completados; preservar marcador verde solo `completed` y leyenda de registro. Nuevo SHA/CI pendiente. |
| Suite V9 | PHP 8.5.8: 682 tests, 8.017 aserciones, 0 fallos, 0 errores, 266 warnings, 2 skipped |
| Focales V11 | PHP 8.5.8: 33/33 tests, 225 aserciones; lint y `composer validate --strict` válidos |
| Fidelidad visual de CAND-01 | **RED_ESENCIAL_NOT_CERTIFIED** |
| Owner UAT / merge / despliegue | NO autorizado |
| Otros frentes | CAND-02 NOT_STARTED; R3/PR #33 FROZEN; PR #43 SUPERSEDED, DRAFT |

**Importante:** CI #1162/run 39703346438 acredita exclusivamente `24a6918e` (fuente `system-ui`). La mejora visual de hitos de esta revisión requiere CI exacto del nuevo SHA; la evidencia V12 no acredita ese cambio futuro ni certifica paridad visual.

**Orden de autoridad:** GitHub (HEAD real) → CI del SHA exacto → este checkpoint → SPEC/PLAN aprobados → AGENTS/README → handoffs y chats. Si hay contradicción, conciliar; nunca inventar un PASS.

## 2. Leyes y forma de trabajo

1. **KISS:** simple, eficiente, optimizado y eficaz. Reutilizar PHP SSR/Slim4/PDO/MySQL-MariaDB; no SPA, servicios paralelos, tablas o abstracciones sin evidencia de necesidad.
2. **Cero ruido:** `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`. Antes de eliminar, demostrar que el comportamiento queda cubierto y que no se pierden contratos ni pruebas.
3. **Anti-parche:** `REPRODUCIR → CAUSA RAÍZ → OWNER → DUPLICADOS → RED CAUSAL → GREEN MÍNIMO → RETIRAR SOBRANTES → REGRESIONES`. No acumular reglas CSS laterales ni debilitar tests.
4. **Diseño/Human-First:** UI moderna, móvil y entendible; no Infinite Excel ni estados, acciones o datos inventados. El sistema de diseño debe respetar la SPEC y la referencia visual aprobada. La instalación/versionado exacto de UI UX Pro Max no está demostrado en el árbol de esta rama; no presumirlo ni instalar a ciegas.
5. **Ley complementaria de fidelidad verificable:** el PNG aprobado es contrato visual, no inspiración. Comparar contra captura REAL del SHA servido, geometría y 17 regiones, evitando autoaprobar por DOM, HTTP 200, CI o encaje básico. Excepciones funcionales requieren soporte en SPEC/rutas y no justifican desfigurar el resto del diseño.

**Interacción:** ChatGPT resuelve análisis, contrato, código remoto y verificación de GitHub directamente siempre que sea seguro. Usar Codex **al final y sólo** cuando el entorno local, base de datos o Browser QA real lo requieran. Si es imprescindible, un único lote autocontenido y auditable; evitar ping-pong. `continua` ejecuta el siguiente gate preparado, pero jamás implica ignorar un RED ni autorizar despliegue.

## 3. Contrato actual de CAND-01

- **SPEC aprobada:** `docs/superpowers/specs/2026-10-17-candidates-human-first-redesign.md`.
- **Baseline:** `docs/design/CANDIDATES_VISUAL_BASELINE_2026-10-17.md`; imagen `01_SOLICITUD.png`, SHA256 `8f7b16a46cb9decf905c218a35e5535aa3acad29041f02840a93c5228c103d15`.
- **Arquitectura:** GET `/admin/candidates/{id}?section=overview|work|household|references|documents|screening|contracts|manage`; una sola vista SSR activa.
- **Owners reales:** `app/Candidates/routes.php`, `resources/views/candidate_detail.php`, `app/UI/HumanLabels.php`, `resources/views/layouts/app.php`, `assets/app.css`; services ya existentes para propiedades, medios, candidatos, seguros y contratos. No crear una segunda autoridad.
- **Permisos:** `candidates.view` no implica `properties.view`; foto/canon/dirección privados fail-closed. Secciones `contracts`/`manage` sólo con sus permisos; nunca presentar `Sin registro` cuando el resultado es `Sin acceso`.
- **No inventar:** editar candidato, enviar mensaje, búsqueda global, campana, menú con acciones ficticias, `Continuar evaluación` o porcentaje de progreso sin endpoint/contrato. Las exclusiones se derivan de SPEC; cada excepción visual conserva el resto de composición y jerarquía.
- **Layout esencial:** fila A (1 resumen/estado), fila B (2 progreso + consulta), fila C (3 datos personales/contacto/inmueble). No ocultar ni suprimir tarjetas para simular fit.

## 4. Evidencia G3 heredada y cambios nuevos

- **Paquete G3 anterior:** HEAD `17e7bce398f38f670914e237fa26415582722679`, [CI #1158](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39506039397) SUCCESS PHP 8.3/8.4/8.5, 680 tests cada uno, 1 skipped. Browser anterior con 65 capturas actuales (58 hashes únicos), referencia, históricos y comparativas; auditoría independiente de 17 regiones y comentario [Ley 5 en PR](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/45#issuecomment-6304009090). Los ZIP originales y matriz se conservan en Library `/AlquilaPro/`.
- **Hecho del paquete anterior:** geometría 1+2+3 y seis tarjetas PASS en 1448×1086; diferencias visuales esenciales en topbar/hero, proporciones/íconos/tipo y densidad de tarjetas. Sin autorización para inventar botones o datos; visual global RED. No convertir 65 capturas en certificación del nuevo SHA.
- **Cambio directo nuevo:** commit [`fbf2bf48`](https://github.com/sierraglobalcompany-rgb/AlquilaPro/commit/fbf2bf48aa18fb988c3c9ff96a003e7d80528a56). Sólo tres archivos existentes: vista reemplaza `Próximos pasos` por `Secciones para consultar` (enlaces reales, no workflow); CSS `.candidate-card` radio 12px y titulares 700; test de semántica actualizado. CI #1159 GREEN; no nueva captura del commit.
- **QA del HEAD base `c501f589`:** `CAND01_G3_C501_QA_20261021.zip` (SHA-256 `64c229f7bb919c4e9b58d8973ffcb5a7280d7140df54aa6416e12c9c80352369`), 14 PNG auténticos y cinco viewports; geometría 1+2+3 y controles de seguridad verificados. La auditoría visual independiente conserva `VISUAL_FIDELITY=RED_ESENCIAL`; la evidencia no certifica el microbloque local.
- **Microbloque de navegación ya publicado en 710b9671:** lista semántica no secuencial, enlaces reales y `.bottom-nav` opaca. No afirmar que siga pendiente.
- **V6 — permisos:** `candidates.view` permite candidato HTTP 200, oculta datos privados y niega imagen privada con 403; control `candidates.view + properties.view` permite la misma imagen con 200. ZIP `CAND01_G3_710B9671_ROLE_QA_V6_DELTA.zip`, SHA-256 `0131d7c8ac73d6ad9f19d3561eed34907a24f5398dc2d93e07d4a83308fb234e`; no repetir aquí.
- **V9 — fuente y QA causal:** elimina solo `font-family: Arial, Helvetica, sans-serif;` de `.candidate-surface` y hereda `system-ui` de `:root`; conserva `testCandidateSurfaceInheritsCanonicalSystemFont()`. ZIP `ALQUILAPRO-CAND01-G3-V9_FONT_QA_DELTA.zip`, SHA-256 `4a0f2039c6fc13018ed91909aa204b9d0b6a9f6abb915cb931e3ef36afc050a2`; manifiesto 24 payloads verificados y CRC PASS. Incluye 10 capturas reales BEFORE/AFTER en cinco viewports: 1448×1086, 1440×900, 768×1024, 390×844 y 375×812. Mismo rol/fixture y CSS servido; H1 −3 px de ancho, misma altura, 0 overflow horizontal, composición 1+2+3 intacta. Esto demuestra ausencia de regresión tipográfica relevante, no `PASS_VISUAL`.
- **V11 publicado:** HEAD `24a6918e61d69b1cf8e53156abcb30acf833c27`, CI #1162 SUCCESS PHP 8.3/8.4/8.5; retira override Arial, mantiene test causal y hereda fuente ERP.
- **V12 ocho secciones:** ZIP SHA `f6e9d090bee5b7938866e56f7eb1d5e978ea81b810cdae82541ef480adf2b89b`; D1 1448×1086 y M2 375×812; 8 secciones HTTP 200, geometría 1+2+3 dentro del viewport desktop; 404 solo de foto privada no restaurada en staging. Foto sintética original de V6 localizada en MEDIA-PROBE-01, SHA-256 `5e804b0dfcebbc26fdbbf612ba86d8bbf4633533909916b5c43a25ecfbbde74d` (182879 bytes); no fue copiada ni recapturada. Evitar más ciclos solo por esa foto.
- **Hitos del progreso:** el backend distingue `recorded` vs `completed` (`data-milestone-*` y texto). El aro verde en un hito solamente registrado transmite aprobación falsa; se retira el owner CSS redundante y el estado neutral recupera el estilo base. Solo `completed` continúa verde; se conserva el texto explícito. Comparativa visual del nuevo SHA pendiente.
- **Suite V9:** 682 tests, 8.017 aserciones, 0 fallos, 0 errores, 266 warnings, 2 skipped; no ocultar warnings.
- **No demostrado todavía:** fidelidad visual global, auditoría de accesibilidad integral o comportamiento en dispositivos físicos. No marcar visual GREEN.

## 5. Bloqueos y decisiones pendientes

| Prioridad | Asunto | Acción correcta |
| --- | --- | --- |
| P0 | Microcorrección de semántica visual de hitos (`recorded` ≠ `completed`) | Quitar regla CSS redundante y añadir prueba causal al owner existente. CI triple y una comparación de progreso real en siguiente QA justificado, sin reabrir permisos/8 secciones. |
| P0 | Diferencias visuales esenciales frente al mockup | Mantener `RED_ESENCIAL_NOT_CERTIFIED`; decisión independiente del owner antes de cualquier remediación |
| P1 | Móvil/tableta: barra inferior fija cruza texto en scroll | V9 midió solapamiento transitorio de 72 px en 375×812, recuperable al final; no aplicar parche global sin causa/RED nuevo |
| P1 | CSS de foco global `:focus-visible{scroll-margin-block:100px}` | Smoke de Propiedades, Contratos, login, catálogo, candidatos y diálogo Apps; no duplicar CSS sin reproducir defecto |
| P1 | Repetición teléfono/correo en resumen y tarjeta contacto | Revisar con SPEC y utilidad contextual; evitar borrar campos o degradar seis tarjetas por una abstracción |
| P2 | UI UX Pro Max no está versionada en el árbol GitHub comprobado | Sólo registrar versión/commit si se comprueba su uso; no instalar a ciegas |

## 6. NEXT exacto y gates

`NEXT=CI_NEW_SHA_THEN_TARGETED_PROGRESS_BROWSER_EVIDENCE_THEN_INDEPENDENT_VISUAL_REVIEW`

El único cambio de producto de este microbloque es neutralizar el marcador de hitos registrados pero NO completados; conservar seis tarjetas, responsive, permisos y hitos verdaderamente completados. Esperar CI exacto y pedir Browser QA focal únicamente si es imprescindible para certificar estética. Mantener PR #45 Draft; no merge/deploy/CAND-02.

## 7. Semáforo de recuperación

```text
PR45 = OPEN_DRAFT_NOT_MERGED
PR45_BRANCH = impl/cand-01-shell-overview-20261017
REMOTE_BASE_HEAD = 24a6918ee61d69b1cf8e53156abcb30acf833c27
BASE_CI = CI_1162_GREEN_PHP_8.3_8.4_8.5
V11_TYPOGRAPHY = PUBLISHED_VERIFIED
G3_VISUAL_PROGRESS_FIX = REMOVE_RECORDED_GREEN_ONLY_KEEP_COMPLETED_GREEN
G3_PROGRESS_NEW_CI = PENDING_NEW_SHA
BROWSER_QA_V9 = 10_SCREENSHOTS_REUSABLE_FOR_TYPOGRAPHY_ONLY
BROWSER_QA_V12 = 2_REAL_SCREENSHOTS_8_TABS_MISSING_MEDIA_404
PERMISSIONS_V6 = PASS_NOT_RERUN
VISUAL_GLOBAL = RED_ESENCIAL_NOT_CERTIFIED
STAGING_V6_V9 = RETAINED_POLICY_RESTRICTION
CAND02 = NOT_STARTED
MAIN = DO_NOT_TOUCH
HOSTINGER = DO_NOT_DEPLOY
NEXT = CI_NEW_SHA_THEN_TARGETED_PROGRESS_BROWSER_EVIDENCE_THEN_INDEPENDENT_VISUAL_REVIEW
```
