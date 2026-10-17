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

## 2. Main / Human-First

Último merge funcional cerrado:

- `main@b978219a6ec8d286164d2d849d68315ced894c66` — merge PR #37 / HF-P1C-A antes de este commit documental de checkpoint.
- PR #34 Human-First governance: **MERGED / CLOSED**.
- PR #35 HF-P1A human labels: **MERGED / CLOSED**.
- PR #36 HF-P1B dashboard cockpit: **MERGED / CLOSED**.
- PR #37 HF-P1C-A contract operator Human-First: **MERGED / CLOSED**.

### HF-P1A — human labels

- RED: `9ca67716e7bda4afbfa0d265d57d43793e1ffa5b`.
- RED CI #1063: 4 fallos causales nuevos.
- GREEN: `a7fbb0b1d87ed51a9ff6c553059e56e2c9b1dcd4`.
- GREEN CI #1068: **SUCCESS PHP 8.3 / 8.4 / 8.5**.
- Merge commit: `53523cd5773659cacb47d8ce100d66894e88f818`.
- Resultado: rol + estados de candidato/screening se muestran como labels humanos, conservando códigos internos y `value=` canónicos.

### HF-P1B — dashboard cockpit real

- RED funcional: `66a0c9e734e43630f6511bc43b8b7c3b08c01d58`.
- GREEN funcional: `b5727d504abcd7658a43ca3c7e67ecd1bc05a910`.
- RED eficiencia: `405625e73bdd241a9c1c4815df052ca0554d7259`.
- GREEN eficiencia compacto: `940773cdfec06339f7e9a3be2fad26cbba7e3103`.
- Warning cleanup test-only: `c8c9c1c80b7881c99fa772ff8e8b63f045f47498`.
- CI final #1080: **SUCCESS PHP 8.3 / 8.4 / 8.5**.
- PHP 8.3 final: `652 tests / 7724 assertions / 1 skipped / 0 warnings`.
- Merge commit: `08f4567190ac2caaeac384090f95f2870b7dff17`.
- Resultado: cockpit permission-scoped con pagos pendientes de conciliación y mantenimientos accionables; sin KPIs inventados; conteos escalares en services dueños.

### HF-P1C-A — operador contractual Human-First

- Rama cerrada: `impl/hf-p1c-contracts-human-form-20261016`.
- PR #37: **MERGED / CLOSED**.
- RED inicial: `53f6a0b84662db91c9adaf98c001d5a178a6ee08`.
- RED inicial PHP 8.3: `656 tests / 7740 assertions / 4 failures / 1 skipped`; los 4 fallos fueron exactamente los contratos Human-First nuevos.
- T3 RED checkpoint: `b6bf77ea09a71f4bb5039670df0d2ed4ad3cb866` — el nuevo GET contextual revelaba candidato sin `candidates.view`.
- GREEN final compacto: `c05842c8b17a0a597cf68cf180f290f5bc9fd839`.
- CI final #1091 / run `38985701980`: **SUCCESS PHP 8.3 / 8.4 / 8.5**.
- PHP 8.3 final: `657 tests / 7782 assertions / 1 skipped / 0 warnings`.
- Merge commit: `b978219a6ec8d286164d2d849d68315ced894c66`.
- Review threads al merge: `0`.
- Resultado: formulario SSR contextual; sin input visible de candidate ID; variables/tareas/anexos estructurados; compatibilidad JSON histórica; `ContractService` sigue como truth owner; el nuevo GET de contexto exige `candidates.view`; la autoridad POST histórica de `contracts.manage` se conserva; 0 DB/schema/migrations/CSS/JS/dependencias/servicios nuevos.

## 3. R3 — congelado

- Rama: `impl/r3-public-surfaces-20261016`.
- HEAD: `845295c777151216655b7a7866bc0dc9d93f3e2e`.
- PR #33: **OPEN / DRAFT / NOT MERGED**.
- R3.1–R3.5: funcionalmente implementados/certificados.
- R3.6 Browser QA: **BLOCKED / NOT_CERTIFIED**.
- R3 no se mergea hasta Browser QA real.
- R4: **NOT STARTED**.

## 4. Bloqueos externos vigentes

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

## 5. Auditoría Human-First aprobada

`AUDIT_ONLY_COMPLETE = YES`
`FINDINGS_P0_P4_APPROVED = YES`

Resultado original: `1 GREEN + 6 YELLOW + 2 RED + 1 BLOCKED`.

Estado de hallazgos principales:

- `GOV-001` P0 — Master exacto no persistido: **BLOCKED_TOOL_SAFETY**.
- `DOC-001` README drift: **CLOSED**.
- `DOC-002` puntero vivo: **CLOSED**.
- `UX-001` P1 — códigos/estados/roles técnicos: **PARTIAL CLOSED por HF-P1A**.
- `UX-002` P1 — dashboard sin cockpit: **CLOSED por HF-P1B**.
- `UX-003` P1 — contratos expone ID + JSON al operador: **operador contractual CLOSED por HF-P1C-A; autor de plantillas sigue pendiente en HF-P1C-B**.
- `UX-004` P1 — candidate detail / obligations / tenant lease detail con sobrecarga vertical: **PENDIENTE**.
- `UX-005` P2 — formatos humanos inconsistentes: **PENDIENTE**.
- `UX-006` P2 — feedback 422 inconsistente: **PENDIENTE**.
- `PERF-001` P2 — candidatos sin paginación/filtros: **PENDIENTE**.
- `A11Y-001`, `RESP-001` P2 — falta Browser actual/poblado: **BLOCKED / PENDIENTE**.
- `DS-001` P3 — Design Master: **bloqueado por Master**.
- `BROWSER-001` — Pase 10: **BLOCKED**.

Informe: `docs/audits/2026-10-16-human-first-repository-audit.md`.

## 6. Próximo gate — HF-P1C-B

HF-P1A, HF-P1B y HF-P1C-A están **CLOSED GREEN / MERGED**.

Siguiente microbloque aprobado: **HF-P1C-B — autor de plantillas contractual sin JSON crudo**.

Contrato bounded aprobado:

- superficie principal: `resources/views/contract_template_detail.php` + route existente de plantillas;
- eliminar de la tarea humana la edición de `variables_json` crudo;
- representar la misma estructura canónica `key + label + required` con campos SSR;
- mantener `ContractService::createTemplateVersion()` como único validador/truth owner;
- conservar body template, versionado inmutable y permisos actuales;
- no DB, no migración, no SPA/framework, no segundo servicio, no segunda autoridad;
- TDD: RED focal → RED causal → GREEN mínimo → full CI PHP 8.3/8.4/8.5 → auditoría → checkpoint → merge.

Fuera de HF-P1C-B: progressive disclosure candidatos/obligaciones/tenant; formatos fecha/dinero; paginación/filtros; Browser QA R3; R4; deployment.

## 7. KEEP — no reescribir

Conservar PHP SSR + Slim; un `View`; un catálogo `Permissions`; autorización revalidada en rutas; un `assets/app.css` y un `assets/app.js`; shell responsive; dashboard sin métricas inventadas; R3 progressive enhancement; `ContractService` como única autoridad contractual.

## 8. Ledger

- Human-First governance SPEC/PLAN/AUDIT: **MERGED / CLOSED**.
- HF-G0 README + checkpoint: **MERGED / CLOSED GREEN**.
- HF-G0 Master exacto: **BLOCKED_TOOL_SAFETY**.
- HF-G0 adapters + Design Master: **BLOCKED por Master**.
- HF-G1 R3 Browser QA: **BLOCKED por Browser permission**.
- HF-P1A human labels: **MERGED / CLOSED GREEN**.
- HF-P1B dashboard cockpit: **MERGED / CLOSED GREEN**.
- HF-P1C-A contract operator form: **MERGED / CLOSED GREEN**.
- HF-P1C-B template author form: **NEXT / NOT STARTED**.
- HF-P1D candidate detail disclosure: **NOT STARTED**.
- HF-P1E obligations disclosure: **NOT STARTED**.
- HF-P1F tenant portal IA: **NOT STARTED**.
- HF-P2A candidates pagination/filter: **NOT STARTED**.
- HF-P2B feedback consistency: **NOT STARTED**.
- R4: **NOT STARTED**.
- Deployment: **NOT AUTHORIZED**.

## 9. Regla de continuidad

Si el chat termina: leer este archivo; revalidar `main`, PRs y CI; no repetir HF-P1A/P1B/P1C-A; abrir HF-P1C-B sólo desde `main` vigente; aplicar TDD completo; actualizar este archivo y crear checkpoint ref al cerrar cada gate; no inferir R3 visual GREEN sin Browser; no R4 y no deployment.
