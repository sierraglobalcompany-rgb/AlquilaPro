# AlquilaPro — CURRENT CHECKPOINT

Fecha de actualización: 2026-10-17
Propósito: **puntero operativo único para recuperar el proyecto sin arrastrar ruido histórico**.

> GitHub + CI son autoridad técnica. Este archivo resume el estado vivo; PRs y commits conservan el detalle forense histórico.

## 1. Leyes obligatorias

- **KISS**: A → B; simple, eficiente, optimizado y eficaz; sin capas, servicios, tablas o abstracciones innecesarias.
- **Cero Ruido**: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`; preservar evidencia única antes de eliminar.
- **Anti-parche**: `REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → CAUSAL RED → MINIMAL GREEN → RETIRAR SUPERSEDED → REGRESSION`.
- **Human-First / Diseño**: persona > modelo de datos; lenguaje humano primero; progressive disclosure; detalle técnico sólo donde sea necesario.
- **No claim without evidence**: SHA exacto + CI para afirmar GREEN técnico.
- **Browser QA real** requerido para certificar visual, responsive y accessibility; nunca inferirlo.
- Merge y deployment son gates separados.
- Deployment: **NO AUTORIZADO**.

## 2. Main / último merge funcional

- HF-P1C-B checkbox primitive PR #40: **MERGED / CLOSED**.
- Merge commit: `c15bb3b370970b686210e76c3f72fed5d968dce9`.
- HEAD funcional probado antes del merge: `670850668fc94f69f36bbbd8b784d1a22edbe524`.
- Historia preservada: `RED dd83b08a → GREEN 67085066 → merge c15bb3b3`.
- HF-P1C-B remediation PR #39: **MERGED / CLOSED** en `7f56ad39ad230a460259d115e35cb897d68f5496`.
- PR #38 HF-P1C-B original: **MERGED / CLOSED** en `c23dd7fc1a1dfd2febfd8285c5e07d1033a6f06f`.
- PR #37 HF-P1C-A: **MERGED / CLOSED GREEN funcional**.
- PR #36 HF-P1B: **MERGED / CLOSED GREEN funcional**.
- PR #35 HF-P1A: **MERGED / CLOSED GREEN funcional**.
- PR #34 Human-First governance: **MERGED / CLOSED**.

## 3. HF-P1C-B — estado actual

### Correcciones integradas

- UI de autor de plantillas sin JSON crudo como interfaz primaria.
- Copy técnico primario retirado y reemplazado por lenguaje humano.
- Datos manuales ocultos por defecto tras disclosure Human-First.
- Variable 1 disponible en primer nivel del disclosure.
- Variables 2–50 disponibles bajo segundo `<details>` SSR nativo.
- Se preservó la capacidad canónica de hasta 50 variables.
- Los 50 checkboxes `Obligatoria` reutilizan la primitive existente `.check-row`; no se añadió CSS nuevo.
- `ContractService::validatedVariables()` sigue siendo el único owner profundo de reglas de dominio.
- `variables_json` permanece únicamente como fallback histórico de transporte no visible.
- Sin DB, migrations, JS, CSS nuevo, SPA, framework, nuevo servicio ni nueva route.

### Evidencia TDD / CI principal

- RED causal remediation: `d4d59fefdb6f785cecacc17576181b60faacfadf`.
- CI RED #1095 / run `38993550706`: fallo causal después de setup y Composer PASS.
- GREEN remediation: `a276c136214ecb816366b29cd41a66cda27f1b01`.
- HEAD final PR #39: `7deb6d8f696a1ed410cc307cdd022996354a6a7b`.
- CI final #1098 / run `38994399978`: **SUCCESS** PHP 8.3 / 8.4 / 8.5.

### Evidencia TDD / CI microfix checkbox

- Root cause: la vista no reutilizaba `.check-row` y el CSS general de `input` podía estirar el checkbox.
- RED causal: `dd83b08a4b71ea4f2681079cb2e9838ad7373133`.
- CI RED #1099 / run `38995420951`: PHP 8.3 terminó con **658 tests / 7793 assertions / 1 failure / 1 skipped**; único fallo `0 !== 50` en `HumanFirstContractTemplateAuthorRoutesTest`.
- GREEN mínimo: `670850668fc94f69f36bbbd8b784d1a22edbe524`.
- Cambio de producción: sólo `class="check-row"` en el `<label>` existente; test protege 50 usos.
- CI GREEN #1100 / run `38995707021`: **SUCCESS** PHP 8.3 / 8.4 / 8.5; Composer y `Run tests` SUCCESS en las tres matrices.
- PR #40: **MERGED / CLOSED**.
- Merge: `c15bb3b370970b686210e76c3f72fed5d968dce9`.
- El merge commit conserva el mismo árbol del GREEN probado (`ac32ae50aed326c863e4a37b6dacad3f39c25d09`).

### Auditoría

`HF_P1C_B_FUNCTIONAL_GREEN = YES`
`HF_P1C_B_KISS = PASS`
`HF_P1C_B_ZERO_NOISE = PASS`
`HF_P1C_B_ANTI_PATCH = PASS`
`HF_P1C_B_REMEDIATION_MERGED = YES`
`HF_P1C_B_CHECKBOX_MICROFIX_MERGED = YES`
`HF_P1C_B_BROWSER_QA = BLOCKED / NOT_CERTIFIED`
`HF_P1C_B_HUMAN_FIRST_FINAL = NOT_CERTIFIED`

HF-P1C-B está funcionalmente integrado y el preflight estático no deja abierto el defecto del checkbox, pero **no se declara Human-First final** hasta Browser QA real.

## 4. Gate activo — Browser / Human-First final

`PASS_10_BROWSER = BLOCKED / NOT_CERTIFIED`

- Browser control sigue requiriendo permiso del usuario.
- No certificar visual, responsive ni accessibility por inferencia.
- **HF-P1D NO se abre mientras este gate siga bloqueado**, según el plan vigente.

## 5. R3 — congelado

- Rama: `impl/r3-public-surfaces-20261016`.
- HEAD: `845295c777151216655b7a7866bc0dc9d93f3e2e`.
- PR #33: **OPEN / DRAFT / NOT MERGED**.
- R3.1–R3.5: funcionalmente implementados/certificados.
- R3.6 Browser QA: **BLOCKED / NOT_CERTIFIED**.
- R3 no se mergea sin Browser QA real.
- R4: **NOT STARTED**.

## 6. Bloqueos externos de gobernanza

### Human-First Master V2.1

`HUMAN_FIRST_TASK1 = BLOCKED_TOOL_SAFETY`

- Fuente exacta conocida: `70,982 bytes`.
- SHA-256: `55265c92b7a49a4ac2ac976afb6789074b5f95ff5760f6d08b7b1d0e817f6940`.
- Payload exacto aún no persistido en GitHub.
- Prohibido resumir, truncar o fragmentar como bypass.
- Adapter huérfano: **NO CREADO**.
- Design Master: **bloqueado por Master**.

## 7. Auditoría Human-First pendiente

- `GOV-001` P0 — Master exacto no persistido: **BLOCKED_TOOL_SAFETY**.
- `DOC-001` README drift: **CLOSED**.
- `DOC-002` puntero vivo: **CLOSED / ACTUALIZADO**.
- `UX-001` P1 — códigos/estados/roles técnicos: **PARTIAL CLOSED por HF-P1A**.
- `UX-002` P1 — dashboard sin cockpit: **CLOSED por HF-P1B**.
- `UX-003` P1 — operador contractual: **CLOSED funcional por HF-P1C-A**; autor de plantillas: **FUNCTIONAL GREEN MERGED / STATIC PREFLIGHT GREEN / BROWSER NOT_CERTIFIED**.
- `UX-004` P1 — candidate detail / obligations / tenant detail: **PENDIENTE / NO ABRIR AÚN**.
- `UX-005` P2 — formatos humanos: **PENDIENTE**.
- `UX-006` P2 — feedback 422: **PENDIENTE**.
- `PERF-001` P2 — candidatos paginación/filtros: **PENDIENTE**.
- `A11Y-001`, `RESP-001`, `BROWSER-001`: **BLOCKED / PENDIENTE**.
- `DS-001` P3 — Design Master: **bloqueado por Master**.

Informe base: `docs/audits/2026-10-16-human-first-repository-audit.md`.

## 8. KEEP — no reescribir

Conservar PHP SSR + Slim; un `View`; un catálogo `Permissions`; autorización revalidada en rutas; un `assets/app.css` y un `assets/app.js`; `ContractService` como única autoridad contractual; compatibilidad histórica cuando no contradiga Human-First; sin arquitectura paralela.

## 9. Ledger

- Human-First governance SPEC/PLAN/AUDIT: **MERGED / CLOSED**.
- HF-G0 Master exacto: **BLOCKED_TOOL_SAFETY**.
- HF-G0 adapters + Design Master: **BLOCKED por Master**.
- HF-G1 R3 Browser QA: **BLOCKED por Browser permission**.
- HF-P1A: **MERGED / CLOSED GREEN funcional**.
- HF-P1B: **MERGED / CLOSED GREEN funcional**.
- HF-P1C-A: **MERGED / CLOSED GREEN funcional**.
- HF-P1C-B original: **MERGED / CLOSED**.
- HF-P1C-B remediation: **MERGED / GREEN funcional / checkbox microfix MERGED / Browser BLOCKED / FINAL NOT_CERTIFIED**.
- HF-P1D: **NOT STARTED / BLOCKED por Browser/Human-First final**.
- HF-P1E: **NOT STARTED**.
- HF-P1F: **NOT STARTED**.
- HF-P2A: **NOT STARTED**.
- HF-P2B: **NOT STARTED**.
- R4: **NOT STARTED**.
- Deployment: **NOT AUTHORIZED**.

## 10. Regla de continuidad

Si el chat termina:

1. leer este archivo;
2. revalidar `main` y confirmar que incluye merge `c15bb3b370970b686210e76c3f72fed5d968dce9`;
3. no repetir RED/GREEN de HF-P1C-B, no reabrir PR #39 ni PR #40;
4. comprobar Browser permission;
5. si Browser sigue bloqueado, mantener `HF_P1C_B_HUMAN_FIRST_FINAL = NOT_CERTIFIED` y **no abrir HF-P1D**;
6. no descongelar R3 sin Browser QA real;
7. no R4 y no deployment.
