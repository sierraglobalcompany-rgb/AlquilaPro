# AlquilaPro — CURRENT CHECKPOINT

Fecha de actualización: 2026-10-16
Propósito: **puntero operativo único para recuperar el proyecto si se pierde el chat/contexto**.

> Regla: GitHub + CI son autoridad técnica. Este archivo sólo resume el estado vivo. Los checkpoints históricos no sustituyen el estado actual.

## 1. Leyes obligatorias

- **KISS**: A → B; simple, eficiente, optimizado y eficaz; no capas/servicios/tablas innecesarios.
- **Cero Ruido**: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`.
- **Anti-parche**: `REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → CAUSAL RED → MINIMAL GREEN → RETIRAR SUPERSEDED → REGRESSION`.
- TDD para cambios funcionales; producción sólo después de RED causal.
- Merge y deployment son gates separados.
- Deployment: **NO AUTORIZADO**.

## 2. Main / merges cerrados

- `main@ce3f98deb04263bc668376a3b14031a3888f3413` antes de este commit documental.
- PR #34 Human-First governance: **MERGED / CLOSED**.
- PR #35 HF-P1A human labels: **MERGED / CLOSED GREEN**.
- PR #36 HF-P1B dashboard cockpit: **MERGED / CLOSED GREEN**.
- PR #37 HF-P1C-A contract operator Human-First: **MERGED / CLOSED GREEN**.
- HF-P1C-A merge commit: `b978219a6ec8d286164d2d849d68315ced894c66`.
- HF-P1C-A CI final #1091: **GREEN PHP 8.3 / 8.4 / 8.5**; PHP 8.3 `657 tests / 7782 assertions / 1 skipped / 0 warnings`.

## 3. ACTIVE — HF-P1C-B causal RED / STOP antes de GREEN

### Estado exacto

- Microbloque: **HF-P1C-B — autor de plantillas contractual sin JSON crudo**.
- Rama: `impl/hf-p1c-b-template-human-form-20261016`.
- HEAD: `adf1214627490231d9848ec8b0612c8ac2e8c461`.
- PR #38: **OPEN / DRAFT / NOT MERGED / mergeable**.
- Checkpoint ref: `checkpoint/hf-p1c-b-red-stop-20261016` → `adf1214627490231d9848ec8b0612c8ac2e8c461`.
- Cambios en rama: **1 archivo de test nuevo, 0 producción**.
- Test: `tests/Integration/Contracts/HumanFirstContractTemplateAuthorRoutesTest.php`.

### RED causal certificado

CI #1092 / run `38986855330` sobre PR #38:

- PHP 8.3: **FAILURE causal** — `658 tests / 7790 assertions / 1 failure / 1 skipped`.
- PHP 8.4: **FAILURE causal**.
- PHP 8.5: **FAILURE causal**.
- Composer validate strict: PASS.
- Único fallo verificado en PHP 8.3: `HumanFirstContractTemplateAuthorRoutesTest::testTemplateAuthorUsesStructuredVariableFieldsInsteadOfJson`.
- Causa exacta: la vista todavía expone `variables_json` y aún no renderiza `variables[0][key]`, `variables[0][label]`, `variables[0][required]`.
- No hay fallo de setup, DB, Composer ni regresión ajena.

### Contrato GREEN ya diseñado, NO escrito

Al reanudar:

1. revalidar `main`, rama, PR #38 y checkpoint;
2. **no repetir RED**;
3. implementar GREEN mínimo sólo en:
   - `resources/views/contract_template_detail.php`;
   - `app/Contracts/routes.php`;
4. la vista debe sustituir el textarea humano `variables_json` por campos SSR `variables[n][key|label|required]`;
5. la route debe aceptar `variables[...]` como transporte HTML y normalizar `required` a booleano;
6. conservar `variables_json` únicamente como fallback histórico de compatibilidad;
7. `ContractService::createTemplateVersion()` permanece como único validador/truth owner profundo;
8. no duplicar reglas de dominio en route;
9. no DB, no migration, no JS, no CSS, no SPA/framework, no segundo servicio;
10. correr full CI PHP 8.3/8.4/8.5; sólo después auditar/checkpoint/merge.

`HF_P1C_B_RED_CAUSAL = YES`
`HF_P1C_B_GREEN = NOT_WRITTEN`
`HF_P1C_B_MERGED = NO`
`RESUME_EXACTLY_FROM = HF-P1C-B minimal GREEN after causal RED adf12146`

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

- Fuente exacta: `70,982 bytes`.
- SHA-256: `55265c92b7a49a4ac2ac976afb6789074b5f95ff5760f6d08b7b1d0e817f6940`.
- El conector GitHub bloqueó persistir el payload exacto.
- Prohibido resumir/truncar/fragmentar para bypass.
- Adapter huérfano: **NO CREADO**.

### Browser

`PASS_10_BROWSER = BLOCKED / NOT_CERTIFIED`

No certificar visual/responsive/accessibility actual por inferencia.

## 6. Auditoría Human-First

`AUDIT_ONLY_COMPLETE = YES`
`FINDINGS_P0_P4_APPROVED = YES`

- `GOV-001` P0 — Master exacto no persistido: **BLOCKED_TOOL_SAFETY**.
- `DOC-001` README drift: **CLOSED**.
- `DOC-002` puntero vivo: **CLOSED**.
- `UX-001` P1 — códigos/estados/roles técnicos: **PARTIAL CLOSED por HF-P1A**.
- `UX-002` P1 — dashboard sin cockpit: **CLOSED por HF-P1B**.
- `UX-003` P1 — operador contractual: **CLOSED por HF-P1C-A**; autor de plantillas: **ACTIVE RED HF-P1C-B**.
- `UX-004` P1 — candidate detail / obligations / tenant detail: **PENDIENTE**.
- `UX-005` P2 — formatos humanos: **PENDIENTE**.
- `UX-006` P2 — feedback 422: **PENDIENTE**.
- `PERF-001` P2 — candidatos paginación/filtros: **PENDIENTE**.
- `A11Y-001`, `RESP-001`, `BROWSER-001`: **BLOCKED / PENDIENTE**.
- `DS-001` P3 — Design Master: **bloqueado por Master**.

Informe: `docs/audits/2026-10-16-human-first-repository-audit.md`.

## 7. KEEP — no reescribir

Conservar PHP SSR + Slim; un `View`; un catálogo `Permissions`; autorización revalidada en rutas; un `assets/app.css` y un `assets/app.js`; `ContractService` como única autoridad contractual; compatibilidad histórica cuando no contradiga Human-First; sin arquitectura paralela.

## 8. Ledger

- Human-First governance SPEC/PLAN/AUDIT: **MERGED / CLOSED**.
- HF-G0 README + checkpoint: **MERGED / CLOSED GREEN**.
- HF-G0 Master exacto: **BLOCKED_TOOL_SAFETY**.
- HF-G0 adapters + Design Master: **BLOCKED por Master**.
- HF-G1 R3 Browser QA: **BLOCKED por Browser permission**.
- HF-P1A human labels: **MERGED / CLOSED GREEN**.
- HF-P1B dashboard cockpit: **MERGED / CLOSED GREEN**.
- HF-P1C-A contract operator: **MERGED / CLOSED GREEN**.
- HF-P1C-B template author: **ACTIVE / CAUSAL RED / GREEN NOT WRITTEN / STOP**.
- HF-P1D candidate detail disclosure: **NOT STARTED**.
- HF-P1E obligations disclosure: **NOT STARTED**.
- HF-P1F tenant portal IA: **NOT STARTED**.
- HF-P2A candidates pagination/filter: **NOT STARTED**.
- HF-P2B feedback consistency: **NOT STARTED**.
- R4: **NOT STARTED**.
- Deployment: **NOT AUTHORIZED**.

## 9. Regla de continuidad

Si el chat termina:

1. leer este archivo;
2. revalidar `main`, rama `impl/hf-p1c-b-template-human-form-20261016`, PR #38 y CI #1092;
3. confirmar checkpoint `checkpoint/hf-p1c-b-red-stop-20261016@adf1214627490231d9848ec8b0612c8ac2e8c461`;
4. no repetir HF-P1A/P1B/P1C-A ni el RED de P1C-B;
5. reanudar exactamente en el GREEN mínimo de HF-P1C-B;
6. no R3 visual GREEN sin Browser real;
7. no R4 y no deployment.
