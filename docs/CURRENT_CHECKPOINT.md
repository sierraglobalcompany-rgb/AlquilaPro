# AlquilaPro — CURRENT CHECKPOINT

Fecha: 2026-10-17  
Propósito: **puntero operativo único de continuidad, sin ruido histórico**.

> GitHub + CI son autoridad técnica. Este archivo resume el estado vivo; PRs/commits conservan el detalle forense.

## 1. Leyes y convención operativa

- **KISS**: A → B; simple, eficiente, optimizado y eficaz; sin arquitectura innecesaria.
- **Cero Ruido**: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`.
- **Anti-parche**: `REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → CAUSAL RED → MINIMAL GREEN → RETIRAR SUPERSEDED → REGRESSION`.
- **Human-First / Diseño**: persona > modelo de datos; lenguaje humano; jerarquía clara; No Infinite Excel; accesibilidad, responsive y Browser QA real como gates.
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
PR_45_HEAD = 95fcbf472bf3caedbfccc23867585ee0644122f0
PR_45_CHANGED_FILES = 11
PR_45_COMMITS = 15
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
CAND_01_BROWSER_QA = BLOCKED
CAND_01_HUMAN_FIRST_FINAL = NOT_CERTIFIED
PR_45_MERGE = NO
CAND_02 = NOT_STARTED
```

**Un test verde o contratos CSS no certifican UX.** No mergear PR #45 sin Browser QA real de CAND-01.

**Auditoría visual V2 — 2026-10-18/19 (evidencia nueva):** el paquete externo `AlquilaPro-Auditoria-Completa-2026-10-18.zip` contiene **889 PNG** (352 escritorio 1440×900 y 537 móvil emulado 390×844), con integridad de archivos/hashes verificada. Hay capturas de Candidatos y otras áreas, pero **no certificación integral**, ni prueba independiente de que el SHA servido fuese el PR45; faltan 375×812 y 768×1024, permisos/teclado/foco/acciones y aceptación visual final. Se confirmó **divergencia de composición en CAND-01/overview** contra el mockup `01_SOLICITUD.png`; otras siete secciones son microbloques CAND02–08 todavía planificados, no regresiones imputables a CAND-01. La portada pública también difiere del baseline, pero R3/PR33 continúa FROZEN. Los análisis externos `ALQUILAPRO_AUDITORIA_FORENSE_V2_2026-10-19.md`, `ALQUILAPRO_PLAN_MAESTRO_V2_DETALLADO_2026-10-19.md` y la matriz CSV documentan el detalle; **no** sustituir la spec aprobada ni integrar sus ejemplos sin verdad del backend.

Intentos documentados (2026-10-17): Codex/Antigravity y Work no consiguieron una instancia autorizada y observable; **cero capturas reales de producto**. El paquete SERVER_READY de PR #45 se construyó y auditó localmente, pero no hubo rehearsal, preview ni publicación. Los preflights de Hostinger quedan **SUSPENDIDOS por decisión del owner**, no son el siguiente gate. No repetir la misma ruta bloqueada ni sustituir Browser QA por inferencias estáticas.

## 5. Browser QA pendiente — CAND-01

**Siguiente trabajo de producto (tras convergencia documental): `CAND01-VIS-REPAIR` dentro de PR #45**: reproducir diferencia contra el mockup 01 con captura real, escribir RED focal causal, reorganizar únicamente overview + CSS en owners existentes, retirar estilos/markup supersedidos, pasar regresión y CI nuevo del SHA modificado, y capturar 375×812 / 390×844 / 768×1024 / 1440×900 con SHA servido comprobado. No abrir CAND02 ni deshacer el freeze R3. No confundir las 889 capturas actuales con el Browser QA GREEN de la versión reparada.

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
2. **CAND01-VIS-REPAIR**: reparar sólo la divergencia demostrada de overview en el PR #45 mediante RED causal y GREEN mínimo; luego **Browser QA real** de la versión reparada, local autorizado y sin Hostinger; aportar SHA servido, capturas, navegación, permisos, consola, foco y viewports 375/390/768/1440. Si no hay acceso o evidencia, permanecer BLOCKED;
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
7. no mergear PR #45 mientras Browser QA y Human-First sigan sin certificar; las 889 capturas son evidencia de diagnóstico, no aceptación de la versión reparada;
8. deployment sigue **NOT AUTHORIZED**; **Hostinger es la última fase**, tras completar producto, QA integral y UAT aprobado por el owner.

## 13. Estado resumido

```text
REPO = sierraglobalcompany-rgb/AlquilaPro
CAND01_PRODUCT_BASE = 7caf82e910edec4153f5cc49dcfb404f8f372b37
MAIN_CURRENT = REVALIDATE_LIVE_BEFORE_ACTION
ACTIVE_WORK = CAND-01
PR_45_HEAD = 95fcbf472bf3caedbfccc23867585ee0644122f0
PR_45_CI = 1121_GREEN_8_3_8_4_8_5
CAND_01_AUTOMATED_QA = GREEN
CAND_01_BROWSER_QA = BLOCKED
CAND_01_HUMAN_FIRST_FINAL = NOT_CERTIFIED
PR_43 = DRAFT_DO_NOT_MERGE_VISUAL_SUPERSEDED
PR_33_R3 = FROZEN_BROWSER_BLOCKED
R4 = NOT_STARTED
DEPLOYMENT = NOT_AUTHORIZED
HOSTINGER_PHASE = FINAL_ONLY_AFTER_COMPLETE_SYSTEM_AND_OWNER_UAT
REMOTE_QA_PREVIEW = SUSPENDED_BY_OWNER
VISUAL_AUDIT_V2 = 889_PNG_INTEGRITY_VERIFIED_MISMATCH_CONFIRMED
SERVED_SHA = NOT_INDEPENDENTLY_VERIFIED
VISUAL_QA_VIEWPORT_GAPS = 375x812_768x1024
CAND01_VISUAL_GATE = REPAIR_REQUIRED_NOT_CERTIFIED
NEXT_SAFE_ACTION = CAND01_VIS_REPAIR_RED_FIRST_PR45
```
