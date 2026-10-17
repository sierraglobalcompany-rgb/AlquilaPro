# AlquilaPro — CURRENT CHECKPOINT

Fecha: 2026-10-17
Propósito: **puntero operativo único de continuidad, sin ruido histórico**.

> GitHub + CI son autoridad técnica. Este archivo resume el estado vivo; PRs/commits conservan el detalle forense.

## 1. Leyes y convención operativa

- **KISS**: A → B; simple, eficiente, optimizado y eficaz; sin arquitectura innecesaria.
- **Cero Ruido**: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`.
- **Anti-parche**: `REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → CAUSAL RED → MINIMAL GREEN → RETIRAR SUPERSEDED → REGRESSION`.
- **Human-First / Diseño**: persona > modelo de datos; lenguaje humano; progressive disclosure; No Infinite Excel; accesibilidad/responsive/browser como gates reales.
- **No claim without evidence**: SHA exacto + CI para GREEN técnico; Browser real para certificación visual.
- Merge y deployment son gates separados.
- Deployment: **NOT AUTHORIZED**.
- Convención del owner: cuando Andrés diga **`continua`**, eso autoriza ejecutar el siguiente gate preparado —incluido merge— siempre que siga GREEN, sin drift y dentro del alcance previamente auditado.

## 2. `main` integrado

- `main`: `f78d80f991334e2646aba1e6d5f8860b925ad88f`.
- HF-P1C-B: **MERGED / CLOSED / Browser GREEN / Human-First CERTIFIED**.
- PR #41 merge: `d0c7687ed9fa62554329215eff5f2324cff5e5d1`.
- PR #42 checkpoint documental merge: `be3413296f1a8d8a980f07ef89307f8e6a99464b`.
- R3: **FROZEN**.
- R4: **NOT STARTED**.
- Deployment: **NOT AUTHORIZED**.

## 3. HF-P1D — candidate detail progressive disclosure

### Estado técnico

- PR #43 branch: `impl/hf-p1d-candidate-detail-disclosure-20261017`.
- HEAD técnico exacto: `e9594ef1e431eff2f3fc3193bb16a4533a1820af`.
- RED causal: `23e8af3f144fc3b96e9e3e95b18d19e12b9d6d26`.
- CI #1106 / run `39000889738`: SUCCESS en PHP 8.3 / 8.4 / 8.5.
- Browser QA Antigravity/Playwright: 10/10 PASS en 1440×900, 768×1024 y 375×812.
- Working tree de QA: CLEAN.

### Revisión humana posterior — CAMBIO DE DIRECCIÓN VISUAL

Aunque #43 es técnicamente GREEN y el Browser QA pasó, el owner rechazó explícitamente la experiencia visual resultante por seguir pareciendo un "Excel" y no cumplir el objetivo Human-First del producto.

Nueva verdad de diseño:
- el patrón de siete `<details>` apilados **NO es baseline visual aprobada**;
- el Browser QA previo demuestra estabilidad técnica, no aprobación de producto;
- PR #43 **NO debe fusionarse** en su forma actual;
- se abre una nueva fase de diseño formal para `Candidatos` basada en mockups modernos aprobados por el owner;
- la nueva línea visual debe reducir redundancia, mejorar copy, jerarquía, respiración, navegación y comprensión humana;
- cada opción/submódulo debe tener un propósito único y no repetir información global innecesariamente.

## 4. Nueva baseline visual — Candidatos

El owner aprobó el nuevo lenguaje visual generado para:
- Solicitud;
- Información laboral;
- Ocupantes y mascotas;
- Referencias;
- Documentos;
- Estudio de aseguradora;
- Contratos;
- Administrar.

Estado:
- **VISUAL DIRECTION APPROVED**;
- **STRUCTURAL/COPY OPTIMIZATION REQUIRED BEFORE CODE**;
- todavía NO es implementación autorizada píxel-por-píxel;
- los mockups deben tratarse como referencia de composición, no como autoridad para inventar datos, rutas, scores, métricas o acciones inexistentes.

## 5. Gate actual

Antes de tocar producción:
1. cargar Human-First Master Skill V2.1;
2. verificar truth/permissions/routes/capabilities reales del módulo Candidatos;
3. auditar redundancias y copy;
4. definir contrato UX por opción;
5. consolidar baseline visual y page override;
6. obtener aprobación del owner sobre la especificación escrita;
7. crear plan de implementación;
8. sólo después abrir RED/GREEN por microbloques.

`HF_P1D_TECHNICAL_GREEN = YES`
`HF_P1D_PREVIOUS_BROWSER_QA = GREEN`
`HF_P1D_PRODUCT_DESIGN_APPROVAL = REJECTED_FOR_CURRENT_DETAILS_STACK`
`CANDIDATES_NEW_VISUAL_DIRECTION = APPROVED`
`CANDIDATES_NEW_UX_SPEC = IN_PROGRESS`
`PR_43 = DO_NOT_MERGE_CURRENT_FORM`
`HF_P1E = PAUSED_PENDING_CANDIDATES_REDESIGN`
`R3 = FROZEN`
`R4 = NOT_STARTED`
`DEPLOYMENT = NOT_AUTHORIZED`

## 6. Recuperación

1. `main` sigue en `f78d80f991334e2646aba1e6d5f8860b925ad88f` mientras #43 no se fusione.
2. Conservar #43 como evidencia técnica de la aproximación supersedida, no como solución final.
3. No usar el PASS de Antigravity como sustituto de aprobación humana de producto.
4. Cargar `ERP HUMAN-FIRST UI/UX MASTER SKILL — V2.1 FINAL UNIVERSAL` antes de cualquier cambio UI.
5. Aplicar: copy breve/específica/accionable, decisión-first, no single-record-as-table, no 10 cards iguales, no every-field-visible, no Infinite Excel.
6. Diseñar Candidatos primero; sólo después extender el lenguaje visual a Contratos/Propiedades/etc.
7. `continua` = autorización para ejecutar el siguiente gate ya aprobado; no permite saltarse la aprobación de una especificación de diseño nueva.
