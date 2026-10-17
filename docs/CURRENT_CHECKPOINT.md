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

### Estado GitHub

- PR #43: **OPEN / DRAFT / NOT MERGED / mergeable=true**.
- Branch: `impl/hf-p1d-candidate-detail-disclosure-20261017`.
- Base verificada: `f78d80f991334e2646aba1e6d5f8860b925ad88f`.
- HEAD técnico exacto: `e9594ef1e431eff2f3fc3193bb16a4533a1820af`.
- Changed files: **2**.
- No abrir HF-P1E mientras HF-P1D no esté certificado/cerrado.

### Root cause / RED

Problema Human-First: `candidate_detail.php` acumulaba solicitud + empleo + ocupantes + referencias + documentos + aseguradora + contratos + administración en una única columna extensa.

RED causal:
- commit `23e8af3f144fc3b96e9e3e95b18d19e12b9d6d26`;
- CI #1105;
- test Human-First esperaba 7 disclosures secundarios y encontró 0;
- resultado registrado: **659 tests / 7808 assertions / 1 failure / 1 skipped**.

### Minimal GREEN

Commit exacto:
`e9594ef1e431eff2f3fc3193bb16a4533a1820af`

Owner permanece en la misma vista SSR:
`resources/views/candidate_detail.php`.

Tratamiento mínimo:
- `Solicitud` permanece visible;
- 7 bloques secundarios pasan a `<details>/<summary>` nativos cerrados por defecto:
  1. Información laboral
  2. Ocupantes y mascotas
  3. Referencias
  4. Documentos
  5. Estudio de aseguradora
  6. Contratos
  7. Administrar
- se preserva `data-contract-context` cuando corresponde;
- sin JS nuevo;
- sin CSS nuevo;
- sin rutas/servicios/tablas/migraciones/dependencias/autoridades nuevas.

Test añadido:
`tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`.

### CI

CI #1106 / run `39000889738` sobre el HEAD exacto `e9594ef1...`:
- PHP 8.3: **SUCCESS**
- PHP 8.4: **SUCCESS**
- PHP 8.5: **SUCCESS**

`HF_P1D_TECHNICAL_GREEN = YES`
`HF_P1D_KISS = PASS`
`HF_P1D_ZERO_NOISE = PASS`
`HF_P1D_ANTI_PATCH = PASS`
`HF_P1D_HUMAN_FIRST_AUTOMATED = PASS`

## 4. ENV-001 — entorno local QA

El primer intento de Browser QA quedó bloqueado por arranque local: faltaba configuración SMTP de la demo.

Causa raíz:
- contenedor QA con DB de demo;
- variables `ALQUILAPRO_*` requeridas para notificaciones omitidas;
- no era defecto demostrado en `candidate_detail.php`.

Tratamiento mínimo:
- recrear sólo el contenedor QA;
- reutilizar configuración existente del contenedor web de demo;
- conservar SHA y montajes de código de sólo lectura.

Verificación posterior:
- entrypoint `/health`: `status=ok`;
- `/health` por HTTP: `status=ok`;
- PDO a DB correcta;
- DNS de Mailpit correcto;
- working tree limpio;
- **NO CODE CHANGES REQUIRED**;
- ningún commit nuevo por ENV-001.

## 5. Browser QA — bloqueo actual real

Browser previsto: navegador interno de Codex.

Estado:
- el navegador rechazó adquirir la pestaña por política URL;
- la instrucción del navegador prohibió reintentar mediante Playwright u otros mecanismos indirectos;
- no se hizo bypass;
- health sólo certifica infraestructura, **no** certifica UI/UX.

Pendiente en 1440×900, 768×1024 y 375×812:
- identidad/carga visual;
- consola;
- jerarquía Human-First;
- disclosures;
- teclado/foco;
- responsive;
- No Infinite Excel;
- comprensión humana;
- interacción real;
- 5 screenshots fuera del repo.

`HF_P1D_BROWSER_QA = BLOCKED`
`HF_P1D_HUMAN_FIRST_FINAL = NOT_CERTIFIED`

Por tanto:
- PR #43 permanece **DRAFT**;
- **NO MERGE** hasta Browser QA real GREEN;
- HF-P1E permanece **NOT STARTED**.

## 6. Próximo gate exacto

Cuando exista acceso permitido a Browser:

1. probar exactamente `e9594ef1e431eff2f3fc3193bb16a4533a1820af`;
2. viewports 1440×900, 768×1024 y 375×812;
3. comprobar teclado/foco, disclosures, responsive, consola, No Infinite Excel y comprensión humana;
4. capturar 5 PNG fuera del repo;
5. si todo pasa, registrar:
   - `HF_P1D_BROWSER_QA = GREEN`
   - `HF_P1D_HUMAN_FIRST_FINAL = CERTIFIED`
6. sólo entonces revalidar drift + CI exacto y fusionar PR #43 bajo la convención `continua` del owner.

Si aparece defecto: `REPRODUCE → ROOT CAUSE → OWNER → RED → CAUSAL RED → MINIMAL GREEN → REGRESSION → BROWSER QA`.

## 7. Ledger

- Human-First governance SPEC/PLAN/AUDIT: **MERGED / CLOSED**.
- HF-P1A: **MERGED / CLOSED GREEN**.
- HF-P1B: **MERGED / CLOSED GREEN**.
- HF-P1C-A: **MERGED / CLOSED GREEN**.
- HF-P1C-B: **MERGED / CERTIFIED / CLOSED**.
- HF-P1D: **TECHNICAL GREEN / BROWSER BLOCKED / NOT CERTIFIED / PR #43 DRAFT**.
- HF-P1E: **NOT STARTED**.
- HF-P1F: **NOT STARTED**.
- HF-P2A: **NOT STARTED**.
- HF-P2B: **NOT STARTED**.
- R3: **FROZEN**.
- R4: **NOT STARTED**.
- Deployment: **NOT AUTHORIZED**.

## 8. Recuperación si el chat termina

1. Preferir esta rama durable:
   `checkpoint/hf-p1d-ci-green-browser-blocked-20261017`.
2. Separar **checkpoint branch HEAD docs-only** del **SHA técnico certificado por CI**: `e9594ef1e431eff2f3fc3193bb16a4533a1820af`.
3. Confirmar PR #43 sigue OPEN/DRAFT y su HEAD técnico no cambió.
4. Confirmar CI #1106 SUCCESS en PHP 8.3/8.4/8.5.
5. No repetir ENV-001: `/health` ya quedó verificado y el entorno fue reparado sin cambios de código.
6. No inferir Human-First desde health o CI.
7. No mergear #43 hasta Browser QA real GREEN.
8. No abrir HF-P1E mientras HF-P1D permanezca NOT_CERTIFIED.
9. Mantener R3 FROZEN, R4 NOT STARTED y deployment NOT AUTHORIZED.
10. `continua` = autorización del owner para ejecutar el siguiente gate preparado cuando sus precondiciones estén satisfechas.
