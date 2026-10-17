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

## 2. Verdad viva de GitHub

### `main`

- Último `main` auditado: `3648ca8962980ed3a529d77bffacafc87a250b16`.
- PR #40 HF-P1C-B checkbox primitive: **MERGED / CLOSED**.
- Merge #40: `c15bb3b370970b686210e76c3f72fed5d968dce9`.
- HEAD funcional probado antes del merge #40: `670850668fc94f69f36bbbd8b784d1a22edbe524`.
- Historia preservada: `RED dd83b08a → GREEN 67085066 → merge c15bb3b3 → docs 3648ca89`.

### PR #41 — Browser QA / No Infinite Excel

- Estado: **OPEN / READY FOR REVIEW / NOT MERGED**.
- Rama: `codex/hf-p1c-b-infinite-excel-browser-fix-20261017`.
- HEAD certificado: `71b582c41d379968929c1703bba49dfb54cfdf90`.
- Base auditada: `main@3648ca8962980ed3a529d77bffacafc87a250b16`.
- Drift auditado: **ahead 1 / behind 0**.
- Alcance: sólo `resources/views/contract_template_detail.php` + `tests/Integration/Contracts/HumanFirstContractTemplateAuthorRoutesTest.php`.
- Merge gate: **REQUIERE AUTORIZACIÓN EXPLÍCITA DEL OWNER**.

### PR #42 — checkpoint documental

- Estado: **OPEN / DRAFT / NOT MERGED**.
- Rama: `docs/hf-p1c-b-browser-certification-20261017`.
- PR #42 depende de PR #41 y **NO debe fusionarse antes**.
- Después de fusionar #41, actualizar #42 una sola vez contra el `main` post-merge y sólo entonces marcar Ready.
- Merge gate: **REQUIERE AUTORIZACIÓN EXPLÍCITA DEL OWNER**.

## 3. HF-P1C-B — estado integrado en `main`

- UI de autor de plantillas sin JSON crudo como interfaz primaria.
- Copy técnico primario sustituido por lenguaje humano.
- Datos manuales ocultos por defecto tras disclosure Human-First.
- Variable 1 disponible en primer nivel del disclosure.
- Capacidad canónica de hasta 50 variables preservada.
- Los 50 checkboxes `Obligatoria` reutilizan `.check-row`; no se añadió CSS nuevo.
- `ContractService::validatedVariables()` sigue siendo el único owner profundo de reglas de dominio.
- `variables_json` queda sólo como fallback histórico de transporte no visible.
- Sin DB, migrations, JS, SPA, framework, nuevo servicio ni nueva route.

### Evidencia remediation / checkbox ya integrada

- Remediation RED: `d4d59fefdb6f785cecacc17576181b60faacfadf`.
- CI RED #1095 / run `38993550706`: fallo causal después de setup y Composer PASS.
- Remediation GREEN: `a276c136214ecb816366b29cd41a66cda27f1b01`.
- HEAD final PR #39: `7deb6d8f696a1ed410cc307cdd022996354a6a7b`.
- CI #1098: **SUCCESS PHP 8.3 / 8.4 / 8.5**.
- Checkbox RED: `dd83b08a4b71ea4f2681079cb2e9838ad7373133`.
- CI RED #1099: **658 tests / 7793 assertions / 1 failure / 1 skipped**; único fallo `0 !== 50`.
- Checkbox GREEN: `670850668fc94f69f36bbbd8b784d1a22edbe524`.
- CI #1100: **SUCCESS PHP 8.3 / 8.4 / 8.5**.
- PR #40: **MERGED / CLOSED** en `c15bb3b370970b686210e76c3f72fed5d968dce9`.

## 4. HF-P1C-B — Browser QA certificado en candidato PR #41

### Root cause

Al abrir `Más variables manuales`, las variables 2–50 aparecían juntas: 49 fieldsets y una página de aproximadamente **14.001 px** a 1440×900. Esto violaba Human-First / No Infinite Excel.

### Tratamiento mínimo

- Diez `<details>` HTML nativos cerrados inicialmente.
- Nueve grupos de cinco variables: 2–6, 7–11, 12–16, 17–21, 22–26, 27–31, 32–36, 37–41, 42–46.
- Último grupo: 47–50, cuatro variables.
- Sin JS nuevo.
- Sin CSS nuevo.
- Sin servicio, route, DB, migration, framework ni dependencia nueva.
- Se preservan las 50 variables y `.check-row`.

### Evidencia TDD / CI

- RED causal: test focal exigió los grupos 2–6, 7–11, …, 47–50; antes del cambio: **1 test / 13 assertions / FAIL causal**.
- GREEN focal en `71b582c41d379968929c1703bba49dfb54cfdf90`: **1 test / 19 assertions / PASS**.
- CI #1101 / run `38997826887`: **SUCCESS** con suite completa en PHP 8.3 / 8.4 / 8.5.
- Suite local del contenedor temporal: **658 tests / 7.585 assertions / 4 failures / 1 skipped**; los cuatro fallos fueron ambientales: `InstallerTest` recibió `host.docker.internal` en vez de `127.0.0.1`, y tres `ServerReadyBuilder` no encontraron `composer`. La misma revisión completa pasó en CI oficial.

### Browser QA real — navegador interno de Codex

- URL local: `http://127.0.0.1:8180/admin/contract-templates/1`.
- Desktop: **1440×900 PASS**.
- Tablet: **768×1024 PASS**.
- Mobile: **375×812 PASS**.
- Sin overflow horizontal ni clipping.
- Consola: sin errores ni warnings relevantes.
- Interacción: `Enter`, `Tab`, `Space`, foco visible, checkbox operable, summaries operables.
- Se guardó una versión demo con una variable adicional.
- Capturas reales: cinco vistas solicitadas; no versionadas en el repositorio.

### Auditoría del candidato

`HF_P1C_B_KISS = PASS`
`HF_P1C_B_ZERO_NOISE = PASS`
`HF_P1C_B_ANTI_PATCH = PASS`
`HF_P1C_B_BROWSER_QA = GREEN @ 71b582c41d379968929c1703bba49dfb54cfdf90`
`HF_P1C_B_HUMAN_FIRST_FINAL = CERTIFIED @ PR #41 HEAD`
`HF_P1C_B_INTEGRATED_IN_MAIN = NO`

La certificación aplica al candidato PR #41. **No afirmar que el fix Browser/No Infinite Excel está integrado hasta que #41 sea fusionado.**

## 5. Siguiente gate exacto

1. Revalidar PR #41, HEAD `71b582c41d379968929c1703bba49dfb54cfdf90`, `main` y CI #1101.
2. Esperar autorización explícita del owner para **merge PR #41**.
3. Tras merge #41, verificar merge SHA y nuevo `main`.
4. Actualizar PR #42 **una sola vez** contra el `main` post-merge:
   - eliminar textos `OPEN / NOT MERGED` de #41;
   - registrar merge SHA real;
   - mantener `Browser = navegador interno de Codex`;
   - mantener agrupación exacta `9×5 + 1×4`;
   - mantener HF-P1D `READY_FOR_OWNER_AUTHORIZATION / NOT STARTED`.
5. Reauditar PR #42, marcar Ready sólo si no hay drift ni contradicciones.
6. Esperar autorización explícita del owner para **merge PR #42**.
7. Sólo después decidir si se abre HF-P1D.

## 6. R3 — congelado

- Rama: `impl/r3-public-surfaces-20261016`.
- HEAD: `845295c777151216655b7a7866bc0dc9d93f3e2e`.
- PR #33: **OPEN / DRAFT / NOT MERGED**.
- R3.1–R3.5: funcionalmente implementados/certificados.
- R3.6 Browser QA: **BLOCKED / NOT_CERTIFIED**.
- El Browser QA de HF-P1C-B realizado por Codex **NO certifica R3**.
- R3 no se mergea sin su Browser QA real específico.
- R4: **NOT STARTED**.

## 7. Bloqueos de gobernanza Human-First

### Master V2.1

`HUMAN_FIRST_TASK1 = BLOCKED_TOOL_SAFETY`

- Fuente exacta conocida: `70,982 bytes`.
- SHA-256: `55265c92b7a49a4ac2ac976afb6789074b5f95ff5760f6d08b7b1d0e817f6940`.
- Payload exacto aún no persistido en GitHub.
- Prohibido resumir, truncar o fragmentar como bypass.
- `.github/skills/erp-human-first-ui-ux/SKILL.md`: pendiente/bloqueado por Master si no existe en la verdad viva.
- `design-system/alquilapro/MASTER.md`: bloqueado por Master.
- `.github/pull_request_template.md`: pendiente si sigue ausente.

## 8. Auditoría Human-First pendiente

- `GOV-001` P0 — Master exacto no persistido: **BLOCKED_TOOL_SAFETY**.
- `DOC-001` README drift: **CLOSED**.
- `DOC-002` puntero vivo: **CLOSED / ACTUALIZADO EN PR #42 DRAFT**.
- `UX-001` P1 — códigos/estados/roles técnicos: **PARTIAL CLOSED por HF-P1A**.
- `UX-002` P1 — dashboard sin cockpit: **CLOSED por HF-P1B**.
- `UX-003` P1 — operador contractual: **CLOSED funcional por HF-P1C-A**; autor de plantillas: **BROWSER/HUMAN-FIRST CERTIFIED EN CANDIDATO PR #41; merge pendiente**.
- `UX-004` P1 — candidate detail / obligations / tenant detail: **READY_FOR_OWNER_AUTHORIZATION / NOT STARTED**.
- `UX-005` P2 — formatos humanos: **PENDIENTE**.
- `UX-006` P2 — feedback 422: **PENDIENTE**.
- `PERF-001` P2 — candidatos paginación/filtros: **PENDIENTE**.
- `A11Y-001`, `RESP-001`, `BROWSER-001`: HF-P1C-B certificado en candidato #41; otros scopes siguen pendientes según su propio gate.
- `DS-001` P3 — Design Master: **bloqueado por Master**.

Informe base: `docs/audits/2026-10-16-human-first-repository-audit.md`.

## 9. KEEP — no reescribir

Conservar PHP SSR + Slim; un `View`; un catálogo `Permissions`; autorización revalidada en rutas; un `assets/app.css` y un `assets/app.js`; `ContractService` como única autoridad contractual; compatibilidad histórica cuando no contradiga Human-First; sin arquitectura paralela.

## 10. Ledger operativo

- Human-First governance SPEC/PLAN/AUDIT: **MERGED / CLOSED**.
- HF-G0 Master exacto: **BLOCKED_TOOL_SAFETY**.
- HF-G0 adapters + Design Master: **BLOCKED por Master**.
- HF-G1 R3 Browser QA: **BLOCKED / NOT_CERTIFIED**.
- HF-P1A: **MERGED / CLOSED GREEN funcional**.
- HF-P1B: **MERGED / CLOSED GREEN funcional**.
- HF-P1C-A: **MERGED / CLOSED GREEN funcional**.
- HF-P1C-B original: **MERGED / CLOSED**.
- HF-P1C-B remediation: **MERGED / GREEN funcional**.
- HF-P1C-B checkbox microfix: **MERGED / GREEN**.
- HF-P1C-B No Infinite Excel / Browser fix: **PR #41 GREEN + CERTIFIED / NOT MERGED**.
- PR #42 checkpoint documental: **DRAFT / DEPENDE DE #41 / NOT MERGED**.
- HF-P1D: **READY_FOR_OWNER_AUTHORIZATION / NOT STARTED**.
- HF-P1E: **NOT STARTED**.
- HF-P1F: **NOT STARTED**.
- HF-P2A: **NOT STARTED**.
- HF-P2B: **NOT STARTED**.
- R3: **FROZEN**.
- R4: **NOT STARTED**.
- Deployment: **NOT AUTHORIZED**.

## 11. Regla de recuperación si el chat termina

1. Leer este archivo desde la rama documental/checkpoint más reciente.
2. Revalidar `main`; último SHA auditado: `3648ca8962980ed3a529d77bffacafc87a250b16`.
3. Revalidar PR #41; HEAD esperado: `71b582c41d379968929c1703bba49dfb54cfdf90`.
4. Revalidar CI #1101 / run `38997826887` = SUCCESS 8.3/8.4/8.5.
5. No repetir Browser QA de HF-P1C-B si #41 no cambió.
6. No mergear #41 sin autorización explícita del owner.
7. Mantener #42 DRAFT hasta que #41 esté fusionado y luego actualizarlo una sola vez.
8. No abrir HF-P1D sin autorización del owner.
9. Mantener R3 FROZEN, R4 NOT STARTED y deployment NOT AUTHORIZED.
10. No confundir los dos archivos locales ignorados por Codex (`config/database.php`, `storage/app/installed.lock`) con cambios versionados; fueron residuos de entorno local y no forman parte del repo.
