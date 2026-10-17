# AlquilaPro — CURRENT CHECKPOINT

Fecha: 2026-10-17
Propósito: **puntero operativo único de continuidad, sin ruido histórico**.

> GitHub + CI son autoridad técnica. Este archivo resume el estado vivo; PRs/commits y evidencia externa conservan el detalle forense.

## 1. Leyes y convención operativa

- **KISS**: A → B; simple, eficiente, optimizado y eficaz; sin arquitectura innecesaria.
- **Cero Ruido**: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`.
- **Anti-parche**: `REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → CAUSAL RED → MINIMAL GREEN → RETIRAR SUPERSEDED → REGRESSION`.
- **Human-First**: persona > modelo de datos; progressive disclosure; No Infinite Excel; accesibilidad/responsive/browser son gates reales.
- **No claim without evidence**: SHA exacto + CI para GREEN técnico; Browser real para certificación visual.
- Merge y deployment son gates separados.
- Deployment: **NOT AUTHORIZED**.
- Convención del owner: cuando Andrés diga **`continua`**, eso autoriza ejecutar el siguiente gate preparado —incluido merge— siempre que siga GREEN, sin drift y dentro del alcance auditado.

## 2. `main` integrado

- `main`: `f78d80f991334e2646aba1e6d5f8860b925ad88f`.
- HF-P1C-B: **MERGED / CLOSED / Browser GREEN / Human-First CERTIFIED**.
- PR #41 merge: `d0c7687ed9fa62554329215eff5f2324cff5e5d1`.
- PR #42 merge: `be3413296f1a8d8a980f07ef89307f8e6a99464b`.
- R3: **FROZEN**.
- R4: **NOT STARTED**.
- Deployment: **NOT AUTHORIZED**.

## 3. HF-P1D — FINAL candidato certificado, pendiente de merge

### GitHub

- PR #43: **OPEN / READY / NOT MERGED / mergeable=true**.
- Branch: `impl/hf-p1d-candidate-detail-disclosure-20261017`.
- Base: `f78d80f991334e2646aba1e6d5f8860b925ad88f`.
- HEAD técnico exacto: `e9594ef1e431eff2f3fc3193bb16a4533a1820af`.
- Changed files: **2**.

### RED causal

- Commit: `23e8af3f144fc3b96e9e3e95b18d19e12b9d6d26`.
- CI #1105 confirmó el defecto: el test Human-First esperaba 7 disclosures secundarios y encontró 0.
- Resultado registrado: **659 tests / 7808 assertions / 1 failure / 1 skipped**.

### Minimal GREEN

- Commit: `e9594ef1e431eff2f3fc3193bb16a4533a1820af`.
- Owner: `resources/views/candidate_detail.php`.
- `Solicitud` permanece visible.
- 7 bloques secundarios bajo `<details>/<summary>` nativos cerrados por defecto:
  1. Información laboral
  2. Ocupantes y mascotas
  3. Referencias
  4. Documentos
  5. Estudio de aseguradora
  6. Contratos
  7. Administrar
- `data-contract-context` preservado.
- Sin JS/CSS/rutas/servicios/tablas/migraciones/dependencias/autoridades nuevas.
- Test: `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`.

### CI

CI #1106 / run `39000889738` sobre `e9594ef1...`:
- PHP 8.3: **SUCCESS**
- PHP 8.4: **SUCCESS**
- PHP 8.5: **SUCCESS**

### Browser QA real — Antigravity / Playwright Chromium

Evidencia ejecutada sobre el mismo SHA `e9594ef1...`, working tree CLEAN, **NO CODE CHANGES REQUIRED**.

Navegador:
- Playwright Chromium `151.0.7922.34`.

Viewports:
- 1440×900
- 768×1024
- 375×812

Resultado:
- PASS-01 a PASS-10: **10/10 PASS**.
- 7 disclosures: open/close PASS.
- 0 overflow horizontal en los 3 viewports.
- Tab / Shift+Tab / Enter / Space: PASS.
- foco visible: PASS.
- `data-contract-context`: PASS.
- interacción real: abrió `/admin/contracts/1` y volvió al candidato.
- consola/red/aplicación: **0 errores relevantes**.
- 10 screenshots guardados fuera del repo.
- captura móvil específica demuestra foco visible.
- página cerrada reduce la carga inicial; No Infinite Excel: PASS.

Evidencia externa entregada al owner en `HF-P1D_BROWSER_QA_2026-10-17.zip`.

### Estado final HF-P1D

`HF_P1D_TECHNICAL_GREEN = YES`
`HF_P1D_KISS = PASS`
`HF_P1D_ZERO_NOISE = PASS`
`HF_P1D_ANTI_PATCH = PASS`
`HF_P1D_BROWSER_QA = GREEN`
`HF_P1D_HUMAN_FIRST_FINAL = CERTIFIED`
`HF_P1D_INTEGRATED_IN_MAIN = NO`

## 4. ENV-001 — cerrado

- Causa: contenedor QA omitía configuración SMTP demo requerida.
- Owner: entorno local, no `candidate_detail.php`.
- Tratamiento: reutilizar configuración demo existente.
- `/health`: HTTP 200 / `status=ok`.
- DB demo: `alquilapro_demo_uat`.
- Migración pendiente `023_extend_users_login_identifiers.sql` aplicada al entorno demo mediante `MigrationRunner::migrate()`.
- Datos/usuarios demo completados sólo para QA.
- Ningún commit de producto por ENV-001.

## 5. Próximo gate exacto

Precondiciones de merge #43 ya satisfechas:
- HEAD exacto sin drift: `e9594ef1...`.
- CI #1106 GREEN 8.3/8.4/8.5.
- Browser QA real GREEN.
- Human-First CERTIFIED.
- PR #43 READY / mergeable=true.

Siguiente `continua`:
1. revalidar inmediatamente PR #43 HEAD, `main` y CI #1106;
2. fusionar PR #43 con `expected_head_sha=e9594ef1e431eff2f3fc3193bb16a4533a1820af` si sigue sin drift;
3. verificar merge SHA real y nuevo `main`;
4. actualizar `docs/CURRENT_CHECKPOINT.md` post-merge en un único paso docs-only;
5. crear checkpoint durable post-HF-P1D;
6. **HF-P1E permanece NOT STARTED** hasta quedar HF-P1D integrado y documentado.

## 6. Ledger

- Human-First governance SPEC/PLAN/AUDIT: **MERGED / CLOSED**.
- HF-P1A: **MERGED / CLOSED GREEN**.
- HF-P1B: **MERGED / CLOSED GREEN**.
- HF-P1C-A: **MERGED / CLOSED GREEN**.
- HF-P1C-B: **MERGED / CERTIFIED / CLOSED**.
- HF-P1D: **GREEN / BROWSER GREEN / HUMAN-FIRST CERTIFIED / PR #43 READY / NOT MERGED**.
- HF-P1E: **NOT STARTED**.
- HF-P1F: **NOT STARTED**.
- HF-P2A: **NOT STARTED**.
- HF-P2B: **NOT STARTED**.
- R3: **FROZEN**.
- R4: **NOT STARTED**.
- Deployment: **NOT AUTHORIZED**.

## 7. Recuperación si el chat termina

1. Preferir esta rama durable: `checkpoint/hf-p1d-ci-green-browser-blocked-20261017` (nombre histórico; contenido actual ya certifica Browser GREEN).
2. SHA técnico que debe mergearse: `e9594ef1e431eff2f3fc3193bb16a4533a1820af`.
3. Confirmar PR #43 OPEN/READY, HEAD exacto y mergeable=true.
4. Confirmar CI #1106 SUCCESS.
5. No repetir Browser QA mientras el HEAD no cambie.
6. Siguiente `continua` autoriza merge #43 si no hay drift.
7. No abrir HF-P1E antes del merge + checkpoint post-merge de HF-P1D.
8. Mantener R3 FROZEN, R4 NOT STARTED y deployment NOT AUTHORIZED.
