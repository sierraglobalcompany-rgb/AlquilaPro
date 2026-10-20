# AlquilaPro — CURRENT CHECKPOINT

Fecha: 2026-10-17  
Propósito: **puntero operativo único de continuidad, sin ruido histórico**.

> GitHub + CI son autoridad técnica. Este archivo resume el estado vivo; PRs/commits conservan el detalle forense.

## 1. Leyes y convención operativa

- **KISS**: A → B; simple, eficiente, optimizado y eficaz; sin arquitectura innecesaria.
- **Cero Ruido**: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`.
- **Anti-parche**: `REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → CAUSAL RED → MINIMAL GREEN → RETIRAR SUPERSEDED → REGRESSION`.
- **Human-First / Diseño**: persona > modelo de datos; lenguaje humano; jerarquía clara; No Infinite Excel; accesibilidad, responsive y Browser QA real como gates.
- **Contrato visual aprobado (decisión explícita del owner 2026-10-19)**: el mockup `01_SOLICITUD.png` se **implementa**, no se usa como inspiración. No sustituir estética, composición, iconografía, jerarquía o densidad aprobadas por una versión "más simple". Sólo se omiten o adaptan **datos/acciones inexistentes**, justificando la excepción específica y conservando el resto del diseño.
- **No claim without evidence**: SHA exacto + CI para GREEN técnico; Browser real para certificación visual.
- Merge y deployment son gates separados.
- Deployment: **NOT AUTHORIZED**.
- **Hostinger sólo al final:** no preparar ni publicar previews, hPanel, DNS, bases o despliegues durante desarrollo. Instalar únicamente el sistema completo tras QA integral + UAT y autorización final separada del owner.
- Convención del owner: `continua` autoriza ejecutar el siguiente gate inequívocamente preparado —incluido merge— sólo si sigue GREEN, sin drift y dentro del alcance ya auditado.
- `checkpoint y para` = persistir/verificar checkpoint y detenerse.

## 2. Autoridad viva y base integrada

Orden operativo:

1. GitHub del repositorio canónico.
2. CI del SHA exacto.
3. este checkpoint.
4. SPEC / PLAN aprobado aplicable.
5. `AGENTS.md` + README.
6. handoffs/chats sólo como recuperación.

Repositorio canónico: `sierraglobalcompany-rgb/AlquilaPro`.

```text
CAND01_PRODUCT_BASE = 7caf82e910edec4153f5cc49dcfb404f8f372b37
MAIN_AT_PR45_OPEN = 7caf82e910edec4153f5cc49dcfb404f8f372b37
MAIN_CURRENT = REVALIDATE_LIVE_BEFORE_ACTION
PR_44 = MERGED / CLOSED
DEPLOYMENT = NOT_AUTHORIZED
```

PR #44 integró la baseline visual aprobada, la spec y el plan de implementación de Candidatos. `main` puede avanzar después por commits/merges exclusivamente documentales sin cambiar la base funcional de CAND-01; por eso este checkpoint **no fija como perpetuo** el HEAD vivo de `main`: debe revalidarse en GitHub antes de actuar.

## 3. Trabajo activo — CAND-01 / PR #45

```text
ACTIVE_BLOCK = CAND-01
PR_45 = OPEN / DRAFT / NOT_MERGED
PR_45_BRANCH = impl/cand-01-shell-overview-20261017
PR_45_BASE = 7caf82e910edec4153f5cc49dcfb404f8f372b37
PR_45_HEAD = a186b0ab765a9547def7501905486f18159c6982
PR_45_CHANGED_FILES = 11
PR_45_COMMITS = 19
```

Alcance implementado:

- shell único del candidato;
- navegación contextual por `?section=`;
- exactamente una sección activa;
- `overview` / Solicitud como default;
- permisos fail-closed para `contracts` y `manage`;
- navegación compacta móvil y tabs desde desktop amplio;
- estado humano + descripción humana;
- eliminación de redundancias de estado/unidad/cards;
- wrapping de textos largos;
- sin SPA, nuevo JS, migration, servicio, API o dependencia.

Owners conservados:

```text
CandidateService
CandidateDocumentService
ScreeningService
ContractService
Permissions
HumanLabels
View + candidate_detail.php
assets/app.css
```

No crear presenter/dashboard service ni arquitectura paralela.

## 4. Evidencia CAND-01

TDD / reparación:

```text
RED_ORIGINAL = ee79d6714ee0b77bf6ec7cd7c77184d673608780
GREEN_CURRENT = 95fcbf472bf3caedbfccc23867585ee0644122f0
```

Defectos corregidos por RED→GREEN durante CAND-01:

- navegación duplicada;
- estado repetido;
- unidad repetida;
- exceso de cards;
- targets pequeños;
- `<main>` anidado;
- wrapping de textos largos.

CI exact-head:

```text
CI_RUN_NUMBER = 1121
CI_RUN_ID = 39044229519
PHP_8_3 = SUCCESS
PHP_8_4 = SUCCESS
PHP_8_5 = SUCCESS
TESTS = 666
FAILURES = 0
ERRORS = 0
SKIPPED = 1
```

Checkpoint durable:

```text
checkpoint/cand-01-ci-green-browser-pending-20261017
→ 95fcbf472bf3caedbfccc23867585ee0644122f0
```

Estado del gate:

```text
CAND_01_AUTOMATED_QA = GREEN
CAND_01_BROWSER_QA = G1_GREEN_G2_PENDING
CAND_01_HUMAN_FIRST_FINAL = NOT_CERTIFIED
PR_45_MERGE = NO
CAND_02 = NOT_STARTED
```

**Un test verde o contratos CSS no certifican UX.** No mergear PR #45 sin Browser QA real de la **versión G2** y aprobación visual explícita del owner. G1 pasó QA técnico pero **fue rechazado visualmente** por su falta de fidelidad al mockup.

**Auditoría visual V2 — 2026-10-18/19 (evidencia nueva):** el paquete externo `AlquilaPro-Auditoria-Completa-2026-10-18.zip` contiene **889 PNG** (352 escritorio 1440×900 y 537 móvil emulado 390×844), con integridad de archivos/hashes verificada. Hay capturas de Candidatos y otras áreas, pero **no certificación integral**, ni prueba independiente de que el SHA servido fuese el PR45; faltan 375×812 y 768×1024, permisos/teclado/foco/acciones y aceptación visual final. Se confirmó **divergencia de composición en CAND-01/overview** contra el mockup `01_SOLICITUD.png`; otras siete secciones son microbloques CAND02–08 todavía planificados, no regresiones imputables a CAND-01. La portada pública también difiere del baseline, pero R3/PR33 continúa FROZEN. Los análisis externos `ALQUILAPRO_AUDITORIA_FORENSE_V2_2026-10-19.md`, `ALQUILAPRO_PLAN_MAESTRO_V2_DETALLADO_2026-10-19.md` y la matriz CSV documentan el detalle; **no** sustituir la spec aprobada ni integrar sus ejemplos sin verdad del backend.

Intentos documentados (2026-10-17): Codex/Antigravity y Work no consiguieron una instancia autorizada y observable; **cero capturas reales de producto**. El paquete SERVER_READY de PR #45 se construyó y auditó localmente, pero no hubo rehearsal, preview ni publicación. Los preflights de Hostinger quedan **SUSPENDIDOS por decisión del owner**, no son el siguiente gate. No repetir la misma ruta bloqueada ni sustituir Browser QA por inferencias estáticas.

## 5. Fidelidad al mockup y Browser QA pendiente — CAND-01

**G1 técnico cerrado / visual REJECTED por el owner (2026-10-19):** `PR45@a186b0ab765a9547def7501905486f18159c6982`, CI manual #1131 run `39204913575` SUCCESS en PHP 8.3/8.4/8.5; 670 tests, 7893 assertions, 0 failures, 1 skipped; 31 capturas reales del HEAD G1 y 2 capturas de baseline recreada; evidencia de SHA servido en ZIP. G1 resolvió fallos estructurales y foco móvil, pero **no reprodujo sidebar/topbar/hero/tabs/panel verde/card-grid/densidad del mockup aprobado**. `OWNER_VISUAL_APPROVAL=REJECTED_G1`, `HUMAN_FIRST_FINAL=NOT_CERTIFIED`; no merge. G2 es la corrección obligatoria de fidelidad: `ALQUILAPRO_CODEX_LUNA_G2_FIDELIDAD_MOCKUP_OBLIGATORIA_2026-10-19.md` (prompt externo), acompañado de `01_SOLICITUD.png` exacto. RED causal → GREEN con owners existentes → CI exact HEAD 8.3/8.4/8.5 → Browser QA de G2 a 375/390/768/1440 → comparación completa → **owner review**. Preservar 7 secciones legacy hasta CAND02–08, PR43 supersedido, R3 congelado y Hostinger al final.

Viewports obligatorios:

```text
1440×900
768×1024
375×812
```

Validar con instancia real renderizada:

- carga correcta;
- shell único;
- navegación real entre secciones;
- una sola sección activa;
- fallback seguro de sección inválida/no autorizada;
- permisos/capabilities;
- comparación contra baseline `01_SOLICITUD.png`;
- responsive y ausencia de overflow;
- teclado, foco visible y targets cómodos;
- consola/network sin errores relevantes;
- comprensión humana y jerarquía visual.

Si aparece defecto:

`REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → VERIFY RED → MINIMAL GREEN → REGRESSION → CI FRESCO → BROWSER QA OTRA VEZ`.

## 6. Diseño aprobado de Candidatos

Arquitectura:

```text
GET /admin/candidates/{id}?section=<key>
```

Keys:

```text
overview
work
household
references
documents
screening
contracts
manage
```

Regla de no redundancia:

```text
Nombre + estado + unidad -> shell
Identidad/contacto       -> Solicitud
Trabajo                  -> Información laboral
Hogar/mascotas           -> Ocupantes y mascotas
Referencias              -> Referencias
Archivos                 -> Documentos
Aseguradora              -> Estudio de aseguradora
Contratos                -> Contratos
Acciones sensibles       -> Administrar
```

Mockups mandan en arte/jerarquía/densidad; backend + permisos mandan en funciones. No inventar scores, ratings, mensajería, firmas, historial, pre-aprobación, datos estructurados de ocupantes/mascotas ni acciones inexistentes.

## 7. Spec / plan — estado documental

La spec `docs/superpowers/specs/2026-10-17-candidates-human-first-redesign.md` fue **aprobada por el owner el 2026-10-17** y su plan posterior ya registra esa aprobación.

El drift puramente documental del header/final de la spec fue cerrado por PR #47, sin modificar el contrato técnico/UX.

```text
SPEC_EFFECTIVE_STATUS = OWNER_APPROVED
SPEC_HEADER_DRIFT = CLOSED
SPEC_STATUS_CONVERGENCE_PR = 47_MERGED
SPEC_STATUS_CI = 1124_GREEN_8_3_8_4_8_5
TECHNICAL_CONTRACT_CHANGE = NO
```

No reabrir este drift salvo contradicción nueva y demostrable entre la spec, el plan y la verdad viva.

## 8. PR #43 — evidencia supersedida

```text
PR_43 = OPEN / DRAFT / DO_NOT_MERGE
PR_43_HEAD = e9594ef1e431eff2f3fc3193bb16a4533a1820af
HF_P1D_TECHNICAL_GREEN = YES
HF_P1D_PRODUCT_DESIGN_APPROVAL = REJECTED_FOR_DETAILS_STACK
```

Preservar su evidencia técnica/Browser histórica. **No reutilizar la solución de siete `<details>` como dirección visual.** Lección: colapsar un Excel no lo vuelve Human-First.

## 9. R3 / PR #33

```text
PR_33 = OPEN / DRAFT / FROZEN / NOT_MERGED
PR_33_HEAD = 845295c777151216655b7a7866bc0dc9d93f3e2e
R3_FUNCTIONAL = GREEN_HISTORICAL
R3_BROWSER_QA = BLOCKED / NOT_CERTIFIED
R4 = NOT_STARTED
```

No usar Browser QA de otra fase para certificar R3. No descongelar R3 por inferencia.

## 10. Gobernanza pendiente

Gaps conocidos en `main`:

```text
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
design-system/alquilapro/MASTER.md
.github/pull_request_template.md
.github/skills/erp-human-first-ui-ux/SKILL.md
```

Master Human-First V2.1 exacto respaldado externamente:

```text
SHA256 = 55265c92b7a49a4ac2ac976afb6789074b5f95ff5760f6d08b7b1d0e817f6940
```

No resumir, truncar ni fragmentar el Master para evadir bloqueos de seguridad.

`main` fue observado sin branch protection/required checks activos; no afirmar lo contrario sin nueva evidencia.

## 11. Próximos gates exactos

Orden obligatorio:

1. convergencia documental de checkpoint + estado formal de la spec: **CLOSED**;
2. **G2 FIDELIDAD MOCKUP APROBADO**: ejecutar reparación visual de CAND01 Solicitud en PR #45 contra `01_SOLICITUD.png` mediante RED causal, GREEN mínimo y, si es necesario, ajuste del layout compartido con regresión transversal. No inventar botones/funciones; la estética del shell existente no justifica diferencias visuales. Repetir CI del HEAD G2 y Browser QA real 375/390/768/1440, SHA servido, accesibilidad/roles/capturas. Presentar al owner para aprobación visual explícita;
3. sólo con Browser GREEN + Human-First CERTIFIED + HEAD exacto + CI requerido + reviews limpios + owner gate: merge PR #45;
4. verificar `main` post-merge y persistir checkpoint;
5. abrir CAND-02.

Microbloques posteriores:

```text
CAND-02 Información laboral
CAND-03 Ocupantes y mascotas
CAND-04 Referencias
CAND-05 Documentos
CAND-06 Estudio de aseguradora
CAND-07 Contratos
CAND-08 Administrar + convergencia de transiciones
CAND-09 Lista Candidatos — visual, sin HF-P2A
```

Cada bloque:

`TRUTH → UX CONTRACT → RED → VERIFY RED → MINIMAL GREEN → FOCAL → FULL SUITE → CI 8.3/8.4/8.5 → BROWSER QA → HUMAN-FIRST → CHECKPOINT → OWNER GATE → MERGE`.

No abrir el siguiente antes de cerrar el anterior.

Backlog posterior, no absorber ahora:

- HF-P1E obligations disclosure;
- HF-P1F tenant portal IA;
- HF-P2A candidates pagination/filter;
- HF-P2B feedback consistency.

## 12. Recuperación rápida

Al abrir un chat/sesión nueva:

1. leer este checkpoint;
2. revalidar `main` en GitHub;
3. confirmar PR #45 + HEAD + CI #1121;
4. confirmar PR #43 supersedido y PR #33 frozen;
5. cargar la spec/plan de Candidatos y el Master Human-First exacto;
6. aplicar KISS + Cero Ruido + Anti-parche + Human-First;
7. no mergear PR #45 mientras falten Browser QA de G2 y aprobación Human-First explícita del owner; el Browser QA técnico G1 fue GREEN pero el diseño G1 fue rechazado;
8. deployment sigue **NOT AUTHORIZED**; **Hostinger es la última fase**, tras completar producto, QA integral y UAT aprobado por el owner.

## 13. Estado resumido

```text
REPO = sierraglobalcompany-rgb/AlquilaPro
CAND01_PRODUCT_BASE = 7caf82e910edec4153f5cc49dcfb404f8f372b37
MAIN_CURRENT = REVALIDATE_LIVE_BEFORE_ACTION
ACTIVE_WORK = CAND-01
PR_45_HEAD = a186b0ab765a9547def7501905486f18159c6982
PR_45_CI = 1131_GREEN_8_3_8_4_8_5_G1
CAND_01_AUTOMATED_QA = GREEN
CAND_01_BROWSER_QA = G1_GREEN_G2_PENDING
CAND_01_HUMAN_FIRST_FINAL = NOT_CERTIFIED
PR_43 = DRAFT_DO_NOT_MERGE_VISUAL_SUPERSEDED
PR_33_R3 = FROZEN_BROWSER_BLOCKED
R4 = NOT_STARTED
DEPLOYMENT = NOT_AUTHORIZED
HOSTINGER_PHASE = FINAL_ONLY_AFTER_COMPLETE_SYSTEM_AND_OWNER_UAT
REMOTE_QA_PREVIEW = SUSPENDED_BY_OWNER
VISUAL_AUDIT_V2 = 889_PNG_INTEGRITY_VERIFIED_MISMATCH_CONFIRMED
G1_SERVED_SHA = EVIDENCE_REPORTED_VERIFIED_BY_CODEX
G2_SERVED_SHA = MUST_VERIFY_FRESH
G1_VIEWPORTS = 375x812_390x844_768x1024_1440x900
G2_VIEWPORTS = RECHECK_REQUIRED_AFTER_CHANGES
G1_VISUAL_GATE = REJECTED_BY_OWNER_MOCKUP_FIDELITY
G2_TARGET = IMPLEMENT_01_SOLICITUD_APPROVED_MOCKUP_WITH_REAL_BACKEND
CAND01_VISUAL_GATE = RED_G2_REQUIRED_OWNER_APPROVAL
NEXT_SAFE_ACTION = G2_FIDELITY_IMPLEMENTATION_RED_FIRST_PR45
```
