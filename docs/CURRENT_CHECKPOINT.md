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

- `main@08f4567190ac2caaeac384090f95f2870b7dff17` — merge PR #36 / HF-P1B.
- PR #34 Human-First governance: **MERGED / CLOSED**.
- PR #35 HF-P1A human labels: **MERGED / CLOSED**.
- PR #36 HF-P1B dashboard cockpit: **MERGED / CLOSED**.

### HF-P1A — human labels

- RED: `9ca67716e7bda4afbfa0d265d57d43793e1ffa5b`.
- RED CI #1063 / run `38964810347`.
- PHP 8.3 RED: `650 tests / 7692 assertions / 4 failures / 1 skipped`; los 4 fallos fueron exactamente los contratos nuevos.
- GREEN: `a7fbb0b1d87ed51a9ff6c553059e56e2c9b1dcd4`.
- GREEN CI #1068 / run `38965541653`: **SUCCESS PHP 8.3 / 8.4 / 8.5**.
- Merge commit: `53523cd5773659cacb47d8ce100d66894e88f818`.
- Resultado: rol + estados de candidato/screening se muestran como labels humanos, conservando códigos internos y `value=` canónicos.

### HF-P1B — dashboard cockpit real

- RED funcional: `66a0c9e734e43630f6511bc43b8b7c3b08c01d58`.
- RED CI #1069 / run `38967404393`: PHP 8.3 `651 tests / 7705 assertions / 1 failure / 1 skipped`.
- GREEN funcional: `b5727d504abcd7658a43ca3c7e67ecd1bc05a910`.
- GREEN funcional CI #1072 / run `38968791865`: **SUCCESS PHP 8.3 / 8.4 / 8.5**.
- RED eficiencia: `405625e73bdd241a9c1c4815df052ca0554d7259`.
- RED eficiencia CI #1073 / run `38977591761`: PHP 8.3 `652 tests / 7718 assertions / 1 failure / 1 skipped`.
- GREEN eficiencia compacto: `940773cdfec06339f7e9a3be2fad26cbba7e3103`.
- Warning cleanup test-only: `c8c9c1c80b7881c99fa772ff8e8b63f045f47498`.
- CI final #1080 / run `38980480019`: **SUCCESS PHP 8.3 / 8.4 / 8.5**.
- PHP 8.3 final: `652 tests / 7724 assertions / 1 skipped / 0 warnings`.
- Merge commit: `08f4567190ac2caaeac384090f95f2870b7dff17`.
- Resultado: cockpit permission-scoped con pagos pendientes de conciliación y mantenimientos accionables; sin KPIs inventados; conteos escalares `COUNT(*)` dentro de los services dueños.

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

Hallazgos principales:

- `GOV-001` P0 — Master exacto no persistido.
- `DOC-001` README drift — **CLOSED**.
- `DOC-002` puntero vivo — **CLOSED**.
- `UX-001` P1 — códigos/estados/roles/timestamps/IDs técnicos en UI; **labels internos cerrados parcialmente por HF-P1A**.
- `UX-002` P1 — dashboard launcher, no cockpit operacional; **CLOSED por HF-P1B**.
- `UX-003` P1 — Contratos expone ID + JSON al operador.
- `UX-004` P1 — candidate detail / obligations / tenant lease detail con sobrecarga vertical.
- `UX-005` P2 — formatos humanos inconsistentes.
- `UX-006` P2 — feedback 422 inconsistente.
- `PERF-001` P2 — candidatos sin paginación/filtros.
- `A11Y-001`, `RESP-001` P2 — falta revalidación Browser actual/poblada.
- `DS-001` P3 — falta Design Master persistido.
- `BROWSER-001` — Pase 10 bloqueado.

Informe: `docs/audits/2026-10-16-human-first-repository-audit.md`.

## 6. Próximo gate — HF-P1C

HF-P1A y HF-P1B están **CLOSED GREEN / MERGED**.

Siguiente microbloque previsto: **HF-P1C — contracts human form**.

Contrato de alcance inicial:

- eliminar de la tarea humana la necesidad de escribir IDs internos o JSON crudo cuando el backend ya conoce/puede ofrecer opciones canónicas;
- conservar exactamente los contratos, validaciones, snapshots, permisos y valores internos del dominio;
- no crear un segundo motor de contratos, tablas, migraciones, SPA, dependencia frontend ni proveedor paralelo;
- reutilizar `ContractService`, rutas y vistas existentes;
- antes de producción: inventario exacto de campos/rutas/DTO → UX contract → RED focal → RED causal → GREEN mínimo → full CI 8.3/8.4/8.5.

Fuera de HF-P1C:

- progressive disclosure de candidatos/obligaciones/tenant;
- formatos fecha/dinero;
- paginación/filtros;
- Browser QA R3;
- R4;
- deployment.

## 7. KEEP — no reescribir

Conservar:

- PHP SSR + Slim; no SPA.
- un `View`.
- un catálogo `Permissions` y navegación derivada.
- autorización revalidada en rutas.
- un `assets/app.css` y un `assets/app.js`.
- shell responsive actual.
- 44/48 px, `:focus-visible`, reduced-motion, safe areas.
- dashboard quick actions filtradas por permisos.
- prohibición de métricas inventadas.
- R3 progressive enhancement nativo.

## 8. Ledger

- Human-First governance SPEC/PLAN/AUDIT: **MERGED / CLOSED**.
- HF-G0 README + checkpoint: **MERGED / CLOSED GREEN**.
- HF-G0 Master exacto: **BLOCKED_TOOL_SAFETY**.
- HF-G0 adapters + Design Master: **BLOCKED por Master**.
- HF-G1 R3 Browser QA: **BLOCKED por Browser permission**.
- HF-P1A human labels: **MERGED / CLOSED GREEN**.
- HF-P1B dashboard cockpit real: **MERGED / CLOSED GREEN**.
- HF-P1C contracts human form: **NEXT / NOT STARTED**.
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
2. revalidar `main`, PRs y CI en GitHub;
3. no repetir HF-P1A ni HF-P1B;
4. abrir HF-P1C sólo desde el `main` vigente;
5. aplicar TDD completo antes de tocar producción;
6. actualizar este archivo y crear checkpoint ref al cerrar cada gate lógico;
7. no inferir R3 visual GREEN sin Browser real;
8. no R4 y no deployment.
