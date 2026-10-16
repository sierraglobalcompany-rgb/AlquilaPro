# AlquilaPro — CURRENT CHECKPOINT

Fecha de actualización: 2026-10-16
Propósito: **puntero operativo único para recuperar el proyecto si se pierde el chat/contexto**.

> Regla: este archivo resume estado vivo. GitHub + CI conservan la evidencia exacta; `docs/checkpoints/` conserva historia. No convertir históricos en autoridad activa.

## 1. Autoridad y leyes

Orden vigente:

1. GitHub del repositorio canónico.
2. CI del SHA exacto.
3. Este `docs/CURRENT_CHECKPOINT.md` + checkpoint ref del gate actual.
4. SPEC / PLAN aprobado aplicable.
5. `AGENTS.md` + README para leyes/guardrails durables.
6. Handoffs/chats sólo como recuperación subordinada.

Leyes obligatorias:

- **KISS**: A → B; simple, eficiente, optimizado y eficaz; no capas/servicios/tablas innecesarios.
- **Cero Ruido**: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`.
- **Anti-parche**: `REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → CAUSAL RED → MINIMAL GREEN → RETIRAR SUPERSEDED → REGRESSION`.
- TDD para cambios funcionales; no producción antes del RED causal.
- Merge y deployment son gates separados.
- Deployment actualmente **NO AUTORIZADO**.

## 2. Main / HF-G0 cerrado

- `main@634aa2c501acd48fd1752031adedc7f7ee3a6472`.
- PR #34 Human-First governance: **MERGED / CLOSED**.
- Merge commit: `634aa2c501acd48fd1752031adedc7f7ee3a6472`.
- CI certificador previo al merge: #1061 / run `38963591091` sobre `92bfcf90d87acc568a85b5598cf10df0d8cd6a56`.
  - PHP 8.3: SUCCESS — `646 tests / 7686 assertions / 1 skipped`.
  - PHP 8.4: SUCCESS.
  - PHP 8.5: SUCCESS.
  - Composer validate: SUCCESS.
- Checkpoint post-merge: `checkpoint/human-first-hfg0-docs-merged-20261016` → `634aa2c5…`.
- HF-G0 documental: **CLOSED GREEN**.

PRE_F26_OWNER_UAT: **REJECTED**.
F26: **FROZEN**.
R4: **NOT STARTED**.
Deployment: **NO AUTORIZADO**.

Roadmap post-UAT:

`R0 → R1 → R2 → R3 → R4 → R5 → R6 → R7 → R8 → R9 → R10 → R11 → R12 → R13 → R14`

## 3. R3 — superficie pública congelada

- Rama: `impl/r3-public-surfaces-20261016`.
- HEAD congelado: `845295c777151216655b7a7866bc0dc9d93f3e2e`.
- PR #33: **OPEN / DRAFT / NOT MERGED**.
- R3.1–R3.5: funcionalmente implementados/certificados según su ledger.
- CI exact-head: GREEN PHP 8.3 / 8.4 / 8.5.
- R3.6 visual QA: **BLOCKED / NOT_CERTIFIED** porque Browser control requiere permiso.
- R3 merge: **NO** hasta cerrar QA visual real.
- R4 no puede empezar.

## 4. Human-First Master / Browser — bloqueos externos

### Master V2.1

`HUMAN_FIRST_TASK1 = BLOCKED_TOOL_SAFETY`

- Fuente exacta auditada localmente: `70,982 bytes`.
- SHA-256: `55265c92b7a49a4ac2ac976afb6789074b5f95ff5760f6d08b7b1d0e817f6940`.
- El conector GitHub bloqueó persistir el payload exacto.
- Prohibido resumir, truncar, fragmentar para bypass o crear una segunda autoridad.
- Adapter huérfano: **NO CREADO**.

### Browser

`PASS_10_BROWSER = BLOCKED / NOT_CERTIFIED`

No certificar visual/responsive/accessibility actual por inferencia. Reintentar sólo si el canal ya está habilitado; no repetir loops de bloqueo.

## 5. Auditoría Human-First aprobada

`AUDIT_ONLY_COMPLETE = YES`
`FINDINGS_P0_P4_APPROVED = YES`

Resultado: `1 GREEN + 6 YELLOW + 2 RED + 1 BLOCKED`.

Hallazgos aprobados:

- `GOV-001` P0 — Master exacto no persistido.
- `DOC-001` — README obsoleto: **CLOSED en HF-G0**.
- `DOC-002` — faltaba puntero vivo: **CLOSED en HF-G0** con este archivo.
- `UX-001` P1 — códigos/estados/roles/timestamps/IDs técnicos llegan a UI.
- `UX-002` P1 — dashboard launcher, no cockpit operacional; **NO inventar KPIs**.
- `UX-003` P1 — Contratos expone ID + JSON al operador.
- `UX-004` P1 — candidate detail / obligations / tenant lease detail con sobrecarga vertical.
- `UX-005` P2 — formatos humanos inconsistentes.
- `UX-006` P2 — feedback 422 inconsistente.
- `PERF-001` P2 — candidatos usa `fetchAll()` sin paginación/filtros.
- `A11Y-001`, `RESP-001` P2 — evidencia F24 válida pero falta revalidación Browser actual/poblada.
- `DS-001` P3 — primitives reales existen; falta Design Master persistido.
- `BROWSER-001` — Pase 10 bloqueado.

Informe: `docs/audits/2026-10-16-human-first-repository-audit.md`.

## 6. Rama activa — HF-P1A

- Rama: `impl/hf-p1a-human-labels-20261016`.
- Base exacta: `main@634aa2c501acd48fd1752031adedc7f7ee3a6472`.
- PR: **pendiente de crear después de este primer commit de checkpoint**.
- Objetivo: resolver una sola causa raíz de `UX-001`: **códigos internos usados como texto visible**.

### Scope HF-P1A

Sólo convergencia de labels humanos para:

1. rol visible en `home.php`;
2. estados de candidato en `candidates.php` y `candidate_detail.php`;
3. estados de screening visibles en `candidate_detail.php`.

No incluye en este microbloque:

- fechas/timestamps;
- dinero;
- paginación/filtros;
- dashboard cockpit;
- contratos JSON;
- progressive disclosure;
- CSS/JS;
- DB/migraciones/rutas/servicios de dominio.

Esos temas quedan en sus bloques P1/P2 separados.

### Owner mínimo previsto

Un helper pequeño de presentación bajo `app/UI`, tentativamente `HumanLabels`, con mappings explícitos y fallback al valor original desconocido. No framework, no segundo modelo de estados, no persistencia.

Labels previstos:

- roles: `owner → Propietario`, `admin → Administrador`, `gestor → Gestor`, `tenant → Inquilino`;
- candidato: `new → Nueva`, `incomplete → Incompleta`, `verification → En verificación`, `pending_screening → Pendiente de estudio`, `withdrawn → Retirada`;
- screening: `pending → Pendiente`, `in_progress → En estudio`, `approved → Aprobado`, `rejected → Rechazado`.

**Contrato crítico:** los valores internos de formularios (`value="pending_screening"`, etc.) permanecen canónicos; sólo cambia el texto visible.

## 7. TDD HF-P1A

Próximo paso exacto: **RED only**.

RED focal previsto: `tests/Unit/UI/HumanLabelsContractTest.php`.

Debe demostrar en render de vistas:

- Home muestra `Propietario` y no `owner` como label visible.
- Candidatos muestra `Pendiente de estudio` en lugar del código raw.
- Candidate detail muestra labels humanos para estado actual y transiciones, conservando los `value=` internos.
- Screening muestra labels humanos, conservando sus `value=` canónicos.

Después:

1. commit RED;
2. CI exact-head debe fallar sólo por labels humanos ausentes;
3. verificar causalidad;
4. recién entonces implementar GREEN mínimo;
5. focal + full CI 8.3/8.4/8.5;
6. actualizar este checkpoint y checkpoint ref.

## 8. KEEP — no reescribir

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
- F24 como evidencia válida dentro de su alcance.

## 9. Ledger

- Human-First governance SPEC/PLAN/AUDIT: **MERGED / CLOSED**.
- HF-G0 README + current checkpoint: **MERGED / CLOSED GREEN**.
- HF-G0 Master exacto: **BLOCKED_TOOL_SAFETY**.
- HF-G0 adapters + Design Master: **BLOCKED por Master**.
- HF-G1 R3 Browser QA: **BLOCKED por Browser permission**.
- HF-P1A human labels: **ACTIVE / PRE-RED**.
- HF-P1B dashboard cockpit real: **NOT STARTED**.
- HF-P1C contracts human form: **NOT STARTED**.
- HF-P1D candidate detail disclosure: **NOT STARTED**.
- HF-P1E obligations disclosure: **NOT STARTED**.
- HF-P1F tenant portal IA: **NOT STARTED**.
- HF-P2A candidates pagination/filter: **NOT STARTED**.
- HF-P2B feedback consistency: **NOT STARTED**.
- R4: **NOT STARTED**.
- Deployment: **NOT AUTHORIZED**.

## 10. Regla de continuidad de chat

Si el chat termina:

1. leer este archivo;
2. contrastar `main`, rama activa, PR y CI en GitHub;
3. continuar desde el primer gate abierto sin repetir tareas cerradas;
4. si HF-P1A está en RED, no escribir producción hasta verificar el fallo causal;
5. actualizar este archivo y un checkpoint ref al cerrar cada gate lógico;
6. nunca inferir R3 visual GREEN sin Browser real;
7. no R4 y no deployment.
