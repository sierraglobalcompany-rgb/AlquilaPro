# AlquilaPro — CURRENT CHECKPOINT

**Fecha de corte:** 2026-10-21. **Rol:** única referencia operativa del estado actual. El historial completo de G1/G2/G3 queda en Git y evidencias del PR; no interpretar commits históricos como HEAD.

## 1. Estado comprobado y autoridad

| Campo | Estado |
| --- | --- |
| Repositorio | `sierraglobalcompany-rgb/AlquilaPro` |
| Rama activa | `impl/cand-01-shell-overview-20261017` |
| PR | [#45](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/45), OPEN / DRAFT / NOT_MERGED |
| `main` observado | `243dea9c821645dd554cc5fdd66cb6c9a0d4f2ce`; volver a comprobar antes de escribir |
| Último HEAD de producto acreditado (base del microbloque) | `c501f589c2133d8723615e6d671f31b96130a38b` |
| CI exacto de la base | [#1160](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39538013276): SUCCESS PHP 8.3/8.4/8.5; no atribuirlo al código local aún no publicado |
| Fase | CAND-01 / G3 — Solicitud, seis tarjetas en tres filas |
| Browser QA de `c501f589` | 14 PNG auténticos en cinco viewports; geometría 1+2+3 y seguridad verificadas; fidelidad global **RED_ESENCIAL** |
| Microbloque local | Lista de consulta semántica y nav opaca implementadas; XPath alineado; dos clases focales locales GREEN; nuevo SHA/CI/Browser QA pendientes |
| Fidelidad visual de CAND-01 | **RED_ESENCIAL / no certificada** |
| Owner UAT / merge / despliegue | NO autorizado |
| Otros frentes | CAND-02 NOT_STARTED; R3/PR #33 FROZEN; PR #43 SUPERSEDED, DRAFT |

**Importante:** CI #1160 y el Browser QA de 14 capturas acreditan exclusivamente la base `c501f589`. No certifican el microbloque local ni ningún SHA futuro. Verificar CI y Browser QA del HEAD nuevo antes de afirmar un resultado técnico para ese código.

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
- **Microbloque local pendiente de publicar:** lista `ul` con SVG no secuenciales y fondo opaco `.bottom-nav`; clases de integración y assets GREEN localmente. El SHA nuevo aún no existe; CI exacto y Browser QA del nuevo HEAD están pendientes.
- **No demostrado todavía:** fidelidad visual global, auditoría de accesibilidad integral o comportamiento en dispositivos físicos. No marcar visual GREEN.

## 5. Bloqueos y decisiones pendientes

| Prioridad | Asunto | Acción correcta |
| --- | --- |
| P0 | Verificar geometría y fidelidad visual del HEAD actual | Capturar SHA servido real a 1448×1086, 1440×900, 768×1024, 390×844 y 375×812; comparar 17 regiones y registrar RED concretos |
| P0 | Diferencias de topbar/hero/panel estado | Resolver equivalencia estética sin acciones ficticias; no crear búsqueda, campana, mensajería ni botones mudos |
| P1 | Móvil/tableta: barra inferior fija cruza texto en scroll | La captura anterior demuestra intersección temporal, pero el texto es recuperable al final. Medir top/middle/bottom y foco antes de cambiar CSS global |
| P1 | CSS de foco global `:focus-visible{scroll-margin-block:100px}` | Smoke de Propiedades, Contratos, login, catálogo, candidatos y diálogo Apps; no duplicar CSS sin reproducir defecto |
| P1 | Repetición teléfono/correo en resumen y tarjeta contacto | Revisar con SPEC y utilidad contextual; evitar borrar campos o degradar seis tarjetas por una abstracción |
| P2 | UI UX Pro Max no está versionada en el árbol GitHub comprobado | Sólo registrar versión/commit si se comprueba su uso; no instalar a ciegas |

## 6. NEXT exacto y gates

1. `NEXT=EXACT_SHA_CI_THEN_BROWSER_QA_THEN_INDEPENDENT_VISUAL_REVIEW`: verificar el nuevo SHA publicado y CI exacto PHP 8.3/8.4/8.5.
2. Con CI GREEN, ejecutar Browser QA real del SHA servido con fixture sintético, cinco viewports, comprobación de SHA/CSS, seguridad, responsive y regresión transversal; registrar 17 regiones sin dictamen estético automático.
3. ChatGPT/owner dictamina fidelidad visual contra la referencia. Mantener PR #45 Draft mientras exista RED esencial; sin merge ni despliegue.

## 7. Semáforo de recuperación

```text
BASELINE_HEAD = c501f589c2133d8723615e6d671f31b96130a38b
BASELINE_CI = CI_1160_GREEN@c501f589c2133d8723615e6d671f31b96130a38b
G3_VISUAL_BASELINE_C501 = 14_REAL_SCREENSHOTS; FIVE_VIEWPORTS; GEOMETRY_AND_SECURITY_VERIFIED
G3_VISUAL_CURRENT_CODE = VERIFY_PR45_HEAD_AND_LATEST_QA_EVIDENCE
VISUAL_FIDELITY = RED_ESENCIAL_NOT_CERTIFIED
PR45 = OPEN_DRAFT_NOT_MERGED
OWNER_APPROVAL = NOT_REQUESTED_WHILE_RED
CAND02 = NOT_STARTED
R3_PR33 = FROZEN
PR43 = SUPERSEDED
MAIN = DO_NOT_MODIFY
HOSTINGER = FINAL_ONLY_NO_DEPLOY
NEXT = EXACT_SHA_CI_THEN_BROWSER_QA_THEN_INDEPENDENT_VISUAL_REVIEW
```
