# AlquilaPro — CURRENT CHECKPOINT

**Corte:** 2026-10-22, después de CAND01-QA-CIERRE-02. **Rol:** checkpoint operativo canónico, no historial de cada commit. Orden de autoridad: HEAD GitHub → CI de SHA exacto → este checkpoint → SPEC/AGENTS → evidencias; confirmar HEAD antes de actuar.

## 1. Estado comprobado y autoridad

| Campo | Estado verificado (antes de este commit de documentación) |
| --- | --- |
| Repositorio / rama | `sierraglobalcompany-rgb/AlquilaPro` · `impl/cand-01-shell-overview-20261017` |
| PR #45 | [OPEN / DRAFT / NOT MERGED](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/45); GitHub informa mergeable/clean, **no es aprobación para fusionar** |
| Código exacto auditado | `aa9b2619d34451d406f05a019842da81f458d7a1` |
| `main` observado | `243dea9c821645dd554cc5fdd66cb6c9a0d4f2ce`; NO modificar |
| CI del código auditado | [#1164 / run 39723814465](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39723814465) SUCCESS PHP 8.3 / 8.4 / 8.5 |
| CAND-01 Solicitud | Dirección visual **APROBADA por el owner**, QA focal y residual **PASS**, fidelidad global **NO certificada** |
| Merge / Hostinger | NO autorizados / NO desplegado |
| Próximas fases | CAND-02 NOT_STARTED; R3/PR #33 FROZEN; PR #43 SUPERSEDED |

**Nota de temporalidad:** este documento se publica mediante un **nuevo commit exclusivamente documental**. Su nuevo SHA tendrá CI propio; las capturas y hashes evidencian el **código de producto sin cambios** de `aa9b2619d34451d406f05a019842da81f458d7a1`, no se deben atribuir falsamente a capturas de otro SHA.

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

Los paquetes históricos se conservan en Git/Library y la descripción del PR, no se duplican aquí. **Gates cerrados y evidencia única**:

| Gate | Prueba | Veredicto acotado |
| --- | --- | --- |
| Autenticación/permisos V6 | `candidates.view` candidato 200, medio privado 403; `+properties.view` medio 200; ZIP SHA-256 `0131d7c8ac73d6ad9f19d3561eed34907a24f5398dc2d93e07d4a83308fb234e` | PASS |
| Tipografía V9/V11 | Antes/después 5 viewports; fuente `system-ui` heredada del ERP; V9 ZIP SHA-256 `4a0f2039c6fc13018ed91909aa204b9d0b6a9f6abb915cb931e3ef36afc050a2` | PASS causal |
| Ocho secciones / responsive V12 | 8 rutas GET 200, desktop 1448×1086 y móvil 375×812; ZIP SHA-256 `f6e9d090bee5b7938866e56f7eb1d5e978ea81b810cdae82541ef480adf2b89b` | PASS funcional/estructural, no 17/17 visual |
| Hitos de progreso D1 | PNG SHA-256 `ad22f76c9cfde260d1cbccc726226df786528fe97565d7af92c00278bf0e6eb9`; sólo `completed` verde; CI #1163 | PASS focal |
| Fila C desktop | PNG SHA-256 `3a53702e043967008db88b3304688d209e0c23d586b9ba8d47eaace74facf916`; anchos 362.837/362.837/399.125 px, gaps 16/16 px, termina en y=1072.35; CI #1164 | PASS focal |
| QA residual final A/B/C | ZIP `CAND01_QA_RESIDUAL_20261022.zip` SHA-256 `ea135eac4b574055565e32f2764cf9c59ddee05a44ca89b730a21e8760220c8a`; CRC PASS; JSON y dos PNG íntegros | PASS A/B/C |
| CI producto actual | #1164 exacto para `aa9b2619d34451d406f05a019842da81f458d7a1`, tres versiones PHP SUCCESS | GREEN |

**QA residual A/B/C detallado:** A: menú `768×1024` abierto con 8 enlaces y estado activo correcto, sin overflow; B: en `375×812`, contenido final y=715.85 antes de barra y=740, botón Administrar alcanzable con 7 Tabs y foco visible, sin POST; C: foto sintética privada GET autenticado `200 image/jpeg`, 182879 bytes, SHA-256 `5e804b0dfcebbc26fdbbf612ba86d8bbf4633533909916b5c43a25ecfbbde74d`, naturalWidth 1280. La foto se restauró **únicamente en staging QA**, sin cambiar producto ni datos de negocio.

**La Dirección visual de Solicitud está aprobada por el owner.** Eso no aprueba las siete pantallas secundarias ni certifica todas las 17 regiones.

## 5. Bloqueos y decisiones pendientes

| Tema | Dictamen y siguiente acción |
| --- | --- |
| Producto, permisos, CI, ocho rutas y QA focal/residual | **GREEN dentro del alcance medido**; no repetir sin causa nueva |
| `VISUAL_GLOBAL` | **RED_ESENCIAL_NOT_CERTIFIED**; no declarar 17/17 PASS. El owner aprobó dirección de Solicitud, no certificación de fidelidad completa |
| Accesibilidad integral | Foco y semántica básica PASS en recorridos observados; **WCAG integral/lector de pantalla NO certificados**. La Definition of Done del **módulo Candidatos entero** (§21 SPEC) excede CAND-01 Solicitud |
| Barra fija móvil | Solapamiento temporal de contenido durante desplazamiento, sin último elemento o foco permanentemente cubiertos en gate B; registrar reserva, **sin parche CSS automático** |
| Foto QA histórica 404 | Falta de bytes en staging V12, corregida al reponer JPEG sintético aislado y obtener 200; no defecto demostrado del producto |
| CAND-02..CAND-08 | Mockups originales disponibles y backend existente; trabajo visual posterior, NO iniciado ni autorizado para este PR |

**Sin otros P0 de código demostrados.** No sumar capas, servicios, rutas, estilos ni tests por rutina.

## 6. NEXT exacto y gates

`NEXT=OWNER_FINAL_CAND01_ACCEPTANCE_DECISION`

1. Presentar al owner en un dictamen único: **QA residual PASS**, **dirección visual de Solicitud aprobada**, y **fidelidad/accessibilidad completas sin certificar**. La aceptación de CAND-01 con reservas debe ser **explícita**.
2. Si el owner exige `VISUAL_GLOBAL=PASS`, identificar únicamente las regiones de imagen que aún carecen de equivalencia o justificación; autorizar QA causal y acotado. No repetir V6/V9/V12/fila C/progreso/A-B-C.
3. Cualquier merge del PR #45 requiere autorización explícita y CI del nuevo HEAD documental exacto. Hostinger queda bloqueado y CAND-02 no empieza por inferencia.

**Leyes:** KISS (simple y eficaz), cero ruido (eliminar/unir/simplificar con evidencia), antiparches (RED→causa→GREEN + regresiones), Human-First/UX/UI Pro (sin capacidades falsas), fidelidad visual verificable (no autodeclarar PASS).

## 7. Semáforo de recuperación

```text
PR45 = OPEN_DRAFT_NOT_MERGED
PR45_BRANCH = impl/cand-01-shell-overview-20261017
AUDITED_PRODUCT_HEAD = aa9b2619d34451d406f05a019842da81f458d7a1
PRODUCT_CI = 1164_SUCCESS_PHP_8.3_8.4_8.5
CAND01_DIRECTION_VISUAL = OWNER_APPROVED
CAND01_QA_RESIDUAL = PASS
CAND01_TECHNICAL_GATES = PASS_WITH_SCOPE
VISUAL_GLOBAL = RED_ESENCIAL_NOT_CERTIFIED
ACCESSIBILITY_FULL = NOT_CERTIFIED
PR45_MERGE_AUTHORIZATION = NO
HOSTINGER_DEPLOY = NO
CAND02 = NOT_STARTED
NEXT = OWNER_FINAL_CAND01_ACCEPTANCE_DECISION
```

