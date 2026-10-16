# AlquilaPro — CURRENT CHECKPOINT

Fecha de actualización: 2026-10-16
Propósito: **puntero operativo único para recuperar el proyecto si se pierde el chat/contexto**.

> Regla: este archivo resume estado vivo. GitHub + CI conservan la evidencia exacta; `docs/checkpoints/` conserva historia. No convertir históricos en autoridad activa.

## 1. Autoridad

Orden vigente:

1. GitHub del repositorio canónico.
2. CI del SHA exacto.
3. Este `docs/CURRENT_CHECKPOINT.md` + checkpoint ref del gate actual.
4. SPEC / PLAN aprobado aplicable.
5. `AGENTS.md` + README para leyes/guardrails durables.
6. Handoffs/chats sólo como recuperación subordinada.

Leyes obligatorias:

- **KISS**: A → B; simple, eficiente, optimizado, eficaz; no capas/servicios/tablas innecesarios.
- **Cero Ruido**: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`.
- **Anti-parche**: `REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → CAUSAL RED → MINIMAL GREEN → RETIRAR SUPERSEDED → REGRESSION`.
- TDD para cambio funcional; no producción antes del RED causal.
- Merge y deployment son gates separados.
- Deployment actualmente **NO AUTORIZADO**.

## 2. Main y roadmap

- `main@b0403a5a1e544d95aa02917ef69687c560a94201` al inicio de HF-G0.
- PRE_F26_OWNER_UAT: **REJECTED**.
- F26: **FROZEN**.
- R4: **NOT STARTED**.
- Deployment: **NO AUTHORIZED**.

Roadmap post-UAT:

`R0 → R1 → R2 → R3 → R4 → R5 → R6 → R7 → R8 → R9 → R10 → R11 → R12 → R13 → R14`

## 3. R3 — superficie pública

R3 sigue congelado mientras se resuelve el gate visual real:

- Rama: `impl/r3-public-surfaces-20261016`
- HEAD congelado: `845295c777151216655b7a7866bc0dc9d93f3e2e`
- PR #33: **OPEN / DRAFT / NOT MERGED**.
- Estado funcional: R3.1–R3.5 implementados/certificados según su ledger previo.
- CI exact-head de R3: GREEN PHP 8.3 / 8.4 / 8.5.
- R3.6 visual QA: **BLOCKED / NOT_CERTIFIED** porque Browser control requiere permiso.
- R3 merge: **NO** hasta cerrar QA visual real.

No iniciar R4.

## 4. Human-First — rama activa

- Rama: `chore/human-first-uiux-governance-20261016`
- PR #34: **DRAFT / OPEN / NOT MERGED**.
- Base: `main@b0403a5a1e544d95aa02917ef69687c560a94201`.
- Audit-ready commit: `7fee29fffb9500c64a208941550076720663c703`.
- README convergence commit: `02ef770220725525a5cd7b29f694cdb5b857ddab`.
- El HEAD exacto posterior a este archivo debe recuperarse desde PR #34 o el checkpoint ref vigente; no se autoescribe aquí para evitar un SHA autorreferencial.

Documentos vivos de esta rama:

- `docs/superpowers/specs/2026-10-16-human-first-uiux-governance-design.md`
- `docs/superpowers/plans/2026-10-16-human-first-uiux-governance.md`
- `docs/audits/2026-10-16-human-first-repository-audit.md`
- `docs/CURRENT_CHECKPOINT.md`

## 5. Human-First Master / Browser — bloqueos externos

### Master V2.1

`HUMAN_FIRST_TASK1 = BLOCKED_TOOL_SAFETY`

- Fuente exacta auditada localmente: `70,982 bytes`.
- SHA-256: `55265c92b7a49a4ac2ac976afb6789074b5f95ff5760f6d08b7b1d0e817f6940`.
- El conector GitHub bloqueó persistir el payload exacto.
- Prohibido resumir, truncar, fragmentar para bypass o crear una segunda autoridad.
- Adapter huérfano: **NO CREADO**.

### Browser

`PASS_10_BROWSER = BLOCKED / NOT_CERTIFIED`

El entorno responde que Browser control requiere permiso. No certificar visual/responsive/accessibility actual por inferencia.

## 6. Auditoría Human-First — cerrada en modo AUDIT ONLY

`AUDIT_ONLY_COMPLETE = YES`
`FIXES_APPLIED_AT_AUDIT_GATE = NO`

Resultado 10 pasadas:

1. Truth / permisos: **GREEN técnico**
2. Comprensión humana: **RED**
3. Arquitectura de información: **YELLOW**
4. Densidad / Excel infinito: **RED**
5. Sistema visual: **YELLOW**
6. Accesibilidad: **YELLOW**
7. Interacción / forms / feedback: **YELLOW**
8. Responsive mobile/tablet: **YELLOW**
9. Data-heavy / performance: **YELLOW**
10. Browser real / cross-page: **BLOCKED / NOT_CERTIFIED**

Global: `1 GREEN + 6 YELLOW + 2 RED + 1 BLOCKED`.

Hallazgos raíz aprobados:

- `GOV-001` P0 — Master Human-First no persistido.
- `DOC-001` P1 — README con snapshot vivo obsoleto. **HF-G0: CORRECCIÓN EN CURSO/HECHA EN RAMA**.
- `DOC-002` P2 — exceso de históricos sin puntero vivo. **HF-G0: ESTE ARCHIVO LO CORRIGE**.
- `UX-001` P1 — códigos/estados/timestamps/IDs técnicos llegan a UI.
- `UX-002` P1 — dashboard launcher, no cockpit operacional; **NO inventar KPIs**.
- `UX-003` P1 — Contratos expone ID + JSON al operador.
- `UX-004` P1 — candidate detail / obligations / tenant lease detail con sobrecarga vertical.
- `UX-005` P2 — formatos humanos inconsistentes.
- `UX-006` P2 — feedback 422 inconsistente.
- `PERF-001` P2 — candidatos usa `fetchAll()` sin paginación/filtros.
- `A11Y-001`, `RESP-001` P2 — buena evidencia F24, pero falta revalidación Browser actual/poblada.
- `DS-001` P3 — primitives reales existen; falta Design Master persistido.
- `BROWSER-001` — Pase 10 bloqueado.

Informe completo: `docs/audits/2026-10-16-human-first-repository-audit.md`.

## 7. Aprobación del owner

El owner respondió `continua` después del gate de auditoría.

Por tanto:

- `FINDINGS_P0_P4_APPROVED = YES`
- `CONTINUE_NEXT_PLANNED_GATE = AUTHORIZED`
- Se mantiene la autorización general previa: cuando el owner dice `continua`, ejecutar el siguiente gate planificado con preflight/leases.

Esto **NO** autoriza deployment.

## 8. Prioridades aprobadas

### P0

- Persistir Master V2.1 exacto cuando el canal lo permita.
- Crear adapters/Design Master sólo después del Master.
- Mantener checkpoint vivo.
- Habilitar Browser y cerrar R3.6/Pase 10.

### P1

- Convergencia de labels/estados/fechas/rol a lenguaje humano.
- Dashboard con pendientes/señales **reales**, sin KPIs inventados.
- Simplificar Contratos retirando JSON/IDs técnicos del flujo normal.
- Progressive disclosure para candidate detail, obligations y tenant lease detail.

### P2

- Feedback 422 consistente.
- Paginación/filtros server-side para candidatos.
- Formato canónico de dinero/fecha/hora/estado.
- Revalidación responsive/accessibility real.

### P3

- Design Master derivado de primitives reales.
- Consistencia visual después de P1/P2.
- Reducir drift documental, no borrar evidencia histórica ciegamente.

### P4

- Enhancements sólo después de P0–P3 + Browser QA.

## 9. KEEP — no reescribir

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

## 10. Ledger actual

- Human-First SPEC: **DONE**.
- Human-First PLAN: **DONE**.
- Auditoría 10 pasadas: **DONE / NOT_CERTIFIED por Browser**.
- Hallazgos P0–P4: **APPROVED BY OWNER**.
- HF-G0 / README convergence: **DONE en rama**.
- HF-G0 / `CURRENT_CHECKPOINT`: **DONE con este commit**.
- HF-G0 / Master exacto: **BLOCKED_TOOL_SAFETY**.
- HF-G0 / adapters + Design Master: **BLOCKED por Master**.
- HF-G1 / R3 Browser QA: **BLOCKED por Browser permission**.
- HF-P1A / human labels + formatting: **NOT STARTED**.
- HF-P1B / dashboard cockpit real: **NOT STARTED**.
- HF-P1C / contracts human form: **NOT STARTED**.
- HF-P1D / candidate detail disclosure: **NOT STARTED**.
- HF-P1E / obligations disclosure: **NOT STARTED**.
- HF-P1F / tenant portal IA: **NOT STARTED**.
- HF-P2A / candidates pagination/filter: **NOT STARTED**.
- HF-P2B / feedback consistency: **NOT STARTED**.
- R4: **NOT STARTED**.
- Deployment: **NOT AUTHORIZED**.

## 11. Próximo gate al recuperar

1. Verificar `main`, PR #34, rama Human-First y checkpoint ref exactos.
2. Reintentar Master/Browser **sólo si el canal ya está habilitado**; no repetir loops de bloqueo.
3. Si siguen bloqueados, no detener el proyecto: abrir **HF-P1A** como microbloque independiente con TDD.
4. Antes de escribir runtime en HF-P1A, auditar owner existente para labels/formato y escribir RED causal mínimo.
5. No tocar dashboard/contratos/detalles hasta cerrar HF-P1A GREEN + CI.
6. Actualizar este checkpoint y un checkpoint ref después de cada gate lógico.
7. No mergear R3 ni iniciar R4 sin cerrar Browser QA.
8. No deployment.

## 12. Regla de continuidad de chat

Si un chat termina:

1. abrir este archivo;
2. contrastar refs/PR/CI en GitHub;
3. continuar desde el primer ítem `NOT STARTED` cuyo predecesor esté cerrado o explícitamente bloqueado;
4. no repetir tareas cerradas;
5. actualizar este archivo al final de cada gate significativo.
