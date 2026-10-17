# AlquilaPro — CURRENT CHECKPOINT

Fecha de actualización: 2026-10-16
Propósito: **puntero operativo único para recuperar el proyecto si se pierde el chat/contexto**.

> Regla: GitHub + CI son autoridad técnica. Este archivo resume la verdad operativa viva; los checkpoints históricos no sustituyen el estado actual.

## 1. Leyes obligatorias

- **KISS**: A → B; simple, eficiente, optimizado y eficaz; no capas/servicios/tablas innecesarios.
- **Cero Ruido**: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`.
- **Anti-parche**: `REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → CAUSAL RED → MINIMAL GREEN → RETIRAR SUPERSEDED → REGRESSION`.
- **Human-First / Diseño**: persona > modelo de datos; lenguaje humano en primera capa; progressive disclosure; datos técnicos sólo donde sean necesarios; no certificar visual/responsive/accessibility sin Browser QA real.
- TDD para cambios funcionales; producción sólo después de RED causal.
- Merge y deployment son gates separados.
- Deployment: **NO AUTORIZADO**.

## 2. Main / merges cerrados

- `main@c23dd7fc1a1dfd2febfd8285c5e07d1033a6f06f`.
- PR #34 Human-First governance: **MERGED / CLOSED**.
- PR #35 HF-P1A human labels: **MERGED / CLOSED GREEN funcional**.
- PR #36 HF-P1B dashboard cockpit: **MERGED / CLOSED GREEN funcional**.
- PR #37 HF-P1C-A contract operator Human-First: **MERGED / CLOSED GREEN funcional**.
- PR #38 HF-P1C-B template author form: **MERGED / CLOSED**.
- HF-P1C-B merge commit: `c23dd7fc1a1dfd2febfd8285c5e07d1033a6f06f`.
- PR #38 preservó la historia `RED adf12146 → GREEN 87dc551f → cleanup 280a6a2e → merge c23dd7fc`.

## 3. ACTIVE — HF-P1C-B certification remediation

### Motivo

La re-auditoría posterior al merge confirmó:

- **KISS = PASS**.
- **Cero Ruido = PASS**.
- **Anti-parche = PASS**.
- **Human-First / Diseño = incompleto**, por:
  1. UI humana limitada a una sola variable pese a que `ContractService` soporta hasta 50;
  2. copy técnico primario (`Texto UTF-8 plano con placeholders allowlisted`);
  3. checkpoint canónico desactualizado;
  4. Browser QA real todavía bloqueado.

### Rama y PR

- Rama: `impl/hf-p1c-b-human-first-certification-20261016`.
- Base exacta: `main@c23dd7fc1a1dfd2febfd8285c5e07d1033a6f06f`.
- PR #39: **OPEN / DRAFT / NOT MERGED**.
- RED HEAD: `d4d59fefdb6f785cecacc17576181b60faacfadf`.
- GREEN de producción verificado: `a276c136214ecb816366b29cd41a66cda27f1b01`.
- Checkpoint intermedio: `checkpoint/hf-p1c-b-certification-green-ci-in-progress-20261016` → `a276c136214ecb816366b29cd41a66cda27f1b01`.

### RED causal

CI #1095 / run `38993550706` sobre `d4d59fefdb6f785cecacc17576181b60faacfadf`:

- setup: PASS;
- Composer: PASS;
- PHP 8.3: `Run tests = FAILURE` causal;
- fallo introducido deliberadamente por el test Human-First que exige:
  - mantener JSON crudo fuera de la UI primaria;
  - ofrecer transporte estructurado para múltiples variables;
  - lenguaje humano primero;
  - progressive disclosure SSR nativo.

### GREEN mínimo

Sólo se modificó producción en:

- `resources/views/contract_template_detail.php`.

El test causal está en:

- `tests/Integration/Contracts/HumanFirstContractTemplateAuthorRoutesTest.php`.

No se tocó route, servicio, DB, migration, CSS, JS, SPA, framework ni dependencias.

Resultado:

- primera variable visible de forma directa;
- variables 2–50 disponibles bajo `<details>` SSR nativo;
- copy primario Human-First;
- detalle técnico conservado sólo bajo disclosure;
- `ContractService::validatedVariables()` continúa como único owner profundo y conserva el límite canónico de 50;
- compatibilidad histórica `variables_json` permanece sólo como fallback de transporte no visible.

### CI GREEN exact-head

CI #1096 / run `38993819633` sobre `a276c136214ecb816366b29cd41a66cda27f1b01`:

- PHP 8.3: **SUCCESS**;
- PHP 8.4: **SUCCESS**;
- PHP 8.5: **SUCCESS**;
- Composer: **SUCCESS** en las tres matrices;
- `Run tests`: **SUCCESS** en las tres matrices.

### Estado de certificación

`HF_P1C_B_FUNCTIONAL_GREEN = YES`
`HF_P1C_B_KISS = PASS`
`HF_P1C_B_ZERO_NOISE = PASS`
`HF_P1C_B_ANTI_PATCH = PASS`
`HF_P1C_B_BROWSER_QA = BLOCKED / NOT_CERTIFIED`
`HF_P1C_B_HUMAN_FIRST_FINAL = NOT_CERTIFIED`
`HF_P1C_B_REMEDIATION_MERGED = NO`

No abrir HF-P1D mientras el gate Browser/Human-First siga sin certificar.

## 4. R3 — congelado

- Rama: `impl/r3-public-surfaces-20261016`.
- HEAD: `845295c777151216655b7a7866bc0dc9d93f3e2e`.
- PR #33: **OPEN / DRAFT / NOT MERGED**.
- R3.1–R3.5: funcionalmente implementados/certificados.
- R3.6 Browser QA: **BLOCKED / NOT_CERTIFIED**.
- R3 no se mergea hasta Browser QA real.
- R4: **NOT STARTED**.

## 5. Bloqueos externos vigentes

### Human-First Master V2.1

`HUMAN_FIRST_TASK1 = BLOCKED_TOOL_SAFETY`

- Fuente exacta conocida: `70,982 bytes`.
- SHA-256: `55265c92b7a49a4ac2ac976afb6789074b5f95ff5760f6d08b7b1d0e817f6940`.
- El payload exacto sigue sin persistirse en GitHub.
- Prohibido resumir/truncar/fragmentar para bypass.
- Adapter huérfano: **NO CREADO**.
- Design Master: **bloqueado por Master**.

### Browser

`PASS_10_BROWSER = BLOCKED / NOT_CERTIFIED`

- La conexión Browser requiere permiso del usuario.
- No certificar visual, responsive ni accessibility por inferencia.

## 6. Auditoría Human-First

`AUDIT_ONLY_COMPLETE = YES`
`FINDINGS_P0_P4_APPROVED = YES`

- `GOV-001` P0 — Master exacto no persistido: **BLOCKED_TOOL_SAFETY**.
- `DOC-001` README drift: **CLOSED**.
- `DOC-002` puntero vivo: **ACTUALIZADO por PR #39**.
- `UX-001` P1 — códigos/estados/roles técnicos: **PARTIAL CLOSED por HF-P1A**.
- `UX-002` P1 — dashboard sin cockpit: **CLOSED por HF-P1B**.
- `UX-003` P1 — operador contractual: **CLOSED funcional por HF-P1C-A**; autor de plantillas: **FUNCTIONAL GREEN / BROWSER NOT_CERTIFIED**.
- `UX-004` P1 — candidate detail / obligations / tenant detail: **PENDIENTE / NO ABRIR AÚN**.
- `UX-005` P2 — formatos humanos: **PENDIENTE**.
- `UX-006` P2 — feedback 422: **PENDIENTE**.
- `PERF-001` P2 — candidatos paginación/filtros: **PENDIENTE**.
- `A11Y-001`, `RESP-001`, `BROWSER-001`: **BLOCKED / PENDIENTE**.
- `DS-001` P3 — Design Master: **bloqueado por Master**.

Informe base: `docs/audits/2026-10-16-human-first-repository-audit.md`.

## 7. KEEP — no reescribir

Conservar PHP SSR + Slim; un `View`; un catálogo `Permissions`; autorización revalidada en rutas; un `assets/app.css` y un `assets/app.js`; `ContractService` como única autoridad contractual; compatibilidad histórica cuando no contradiga Human-First; sin arquitectura paralela.

## 8. Ledger

- Human-First governance SPEC/PLAN/AUDIT: **MERGED / CLOSED**.
- HF-G0 README + checkpoint: **MERGED / CLOSED GREEN funcional**.
- HF-G0 Master exacto: **BLOCKED_TOOL_SAFETY**.
- HF-G0 adapters + Design Master: **BLOCKED por Master**.
- HF-G1 R3 Browser QA: **BLOCKED por Browser permission**.
- HF-P1A human labels: **MERGED / CLOSED GREEN funcional**.
- HF-P1B dashboard cockpit: **MERGED / CLOSED GREEN funcional**.
- HF-P1C-A contract operator: **MERGED / CLOSED GREEN funcional**.
- HF-P1C-B template author original: **MERGED / CLOSED por PR #38**.
- HF-P1C-B certification remediation: **GREEN funcional / PR #39 OPEN / BROWSER BLOCKED / FINAL NOT_CERTIFIED**.
- HF-P1D candidate detail disclosure: **NOT STARTED / BLOCKED por cierre Human-First de P1C-B**.
- HF-P1E obligations disclosure: **NOT STARTED**.
- HF-P1F tenant portal IA: **NOT STARTED**.
- HF-P2A candidates pagination/filter: **NOT STARTED**.
- HF-P2B feedback consistency: **NOT STARTED**.
- R4: **NOT STARTED**.
- Deployment: **NOT AUTHORIZED**.

## 9. Regla de continuidad

Si el chat termina:

1. leer este archivo;
2. revalidar `main@c23dd7fc1a1dfd2febfd8285c5e07d1033a6f06f`, rama `impl/hf-p1c-b-human-first-certification-20261016` y PR #39;
3. confirmar el HEAD vivo de PR #39 y su CI exact-head;
4. no repetir el RED #1095 ni el GREEN funcional ya demostrado;
5. verificar Browser permission; si sigue bloqueado, mantener `NOT_CERTIFIED` y no abrir HF-P1D;
6. no descongelar R3 sin Browser QA real;
7. no R4 y no deployment.
