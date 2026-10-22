# AlquilaPro — CURRENT CHECKPOINT

**Fecha de corte:** 2026-10-21. **Rol:** única referencia operativa del estado actual. El historial completo de G1/G2/G3 queda en Git y evidencias del PR; no interpretar commits históricos como HEAD.

## 1. Estado comprobado y autoridad

| Campo | Estado |
| --- | --- |
| Repositorio | `sierraglobalcompany-rgb/AlquilaPro` |
| Rama activa | `impl/cand-01-shell-overview-20261017` |
| PR | [#45](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/45), OPEN / DRAFT / NOT_MERGED |
| `main` observado | `243dea9c821645dd554cc5fdd66cb6c9a0d4f2ce`; volver a comprobar antes de escribir |
| Último HEAD de producto acreditado / base del microbloque tipográfico | `710b96710ee1d8839d462dd01d3de81806378891` |
| CI exacto de la base | [#1161 / run 39623120946](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39623120946): SUCCESS PHP 8.3/8.4/8.5; no atribuirlo al commit local futuro |
| Fase | CAND-01 / G3 — Solicitud, seis tarjetas en tres filas |
| Browser QA de `710b9671` | 13 PNG y matriz de 17 regiones en cinco viewports; permisos complementados por V6; fidelidad global **RED_ESENCIAL_NOT_CERTIFIED** |
| Microbloque tipográfico local | V7/V9 quita solo el override Arial de `.candidate-surface`; GREEN causal observado; commit local preparado, CI remoto nuevo pendiente |
| Suite V9 | PHP 8.5.8: 682 tests, 8.017 aserciones, 0 fallos, 0 errores, 266 warnings, 2 skipped |
| Focales V11 | PHP 8.5.8: 33/33 tests, 225 aserciones; lint y `composer validate --strict` válidos |
| Fidelidad visual de CAND-01 | **RED_ESENCIAL_NOT_CERTIFIED** |
| Owner UAT / merge / despliegue | NO autorizado |
| Otros frentes | CAND-02 NOT_STARTED; R3/PR #33 FROZEN; PR #43 SUPERSEDED, DRAFT |

**Importante:** CI #1161/run 39623120946 acredita exclusivamente la base remota `710b9671`. V9 acredita la regresión tipográfica causal local, no paridad visual global. El nuevo commit local requiere CI propio tras la publicación propietaria; no reabrir ni repetir el Browser QA V9.

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
- **V11 focales:** 33/33 tests, 225 aserciones con aislamiento PHPUnit dirigido al puerto local 1 sin servicio; sin acceso a bases V6/V9. `php -l` y `composer validate --strict` válidos.
- **Suite V9:** 682 tests, 8.017 aserciones, 0 fallos, 0 errores, 266 warnings, 2 skipped; no ocultar warnings.
- **No demostrado todavía:** fidelidad visual global, auditoría de accesibilidad integral o comportamiento en dispositivos físicos. No marcar visual GREEN.

## 5. Bloqueos y decisiones pendientes

| Prioridad | Asunto | Acción correcta |
| --- | --- | --- |
| P0 | Publicar el commit local tipográfico y obtener CI del nuevo SHA | ChatGPT verifica los tres blobs y publica por fast-forward con la conexión GitHub propietaria; esperar CI PHP 8.3/8.4/8.5 |
| P0 | Diferencias visuales esenciales frente al mockup | Mantener `RED_ESENCIAL_NOT_CERTIFIED`; decisión independiente del owner antes de cualquier remediación |
| P1 | Móvil/tableta: barra inferior fija cruza texto en scroll | V9 midió solapamiento transitorio de 72 px en 375×812, recuperable al final; no aplicar parche global sin causa/RED nuevo |
| P1 | CSS de foco global `:focus-visible{scroll-margin-block:100px}` | Smoke de Propiedades, Contratos, login, catálogo, candidatos y diálogo Apps; no duplicar CSS sin reproducir defecto |
| P1 | Repetición teléfono/correo en resumen y tarjeta contacto | Revisar con SPEC y utilidad contextual; evitar borrar campos o degradar seis tarjetas por una abstracción |
| P2 | UI UX Pro Max no está versionada en el árbol GitHub comprobado | Sólo registrar versión/commit si se comprueba su uso; no instalar a ciegas |

## 6. NEXT exacto y gates

`NEXT=OWNER_GITHUB_PUBLISH_EXACT_3_FILE_COMMIT -> CI_NEW_SHA_8.3_8.4_8.5 -> INDEPENDENT_DESIGN_DECISION`

ChatGPT revalida HEAD remoto y base, publica por fast-forward los tres blobs preparados, verifica el SHA y espera el CI exacto. Mantener PR #45 Draft; no hacer merge, deploy ni iniciar CAND-02.

## 7. Semáforo de recuperación

```text
PR45 = OPEN_DRAFT_NOT_MERGED
PR45_BRANCH = impl/cand-01-shell-overview-20261017
REMOTE_BASE_HEAD = 710b96710ee1d8839d462dd01d3de81806378891
BASE_CI = CI_1161_GREEN_PHP_8.3_8.4_8.5
FONT_LOCAL_CHANGE = GREEN_CAUSAL_V7_V9
V11_CHANGES = ONLY_CSS_MINUS_1_TEST_PLUS_15_CANONICAL_CHECKPOINT
V11_LOCAL_COMMIT = PREPARED_FOR_OWNER_GITHUB_PUBLICATION
V11_REMOTE_PUBLISH = NOT_ATTEMPTED_BY_CODEX
V11_CI = PENDING_OWNER_REMOTE_PUBLICATION
BROWSER_QA_V9 = 10_SCREENSHOTS_REUSABLE_FOR_CAUSAL_ONLY
PERMISSIONS_V6 = PASS_NOT_RERUN
VISUAL_GLOBAL = RED_ESENCIAL_NOT_CERTIFIED
STAGING_V6_V9 = RETAINED_POLICY_RESTRICTION
CAND02 = NOT_STARTED
MAIN = DO_NOT_TOUCH
HOSTINGER = DO_NOT_DEPLOY
NEXT = CHATGPT_RECHECK_REMOTE_HEAD_PUBLISH_IDENTICAL_3_BLOBS_CI_8.3_8.4_8.5
```
