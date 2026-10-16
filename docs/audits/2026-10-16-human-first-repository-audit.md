# AlquilaPro — Auditoría Human-First UI/UX del repositorio

Fecha: 2026-10-16
Modo: `AUDIT ONLY — DO NOT FIX`
Estado: `DIAGNOSIS_COMPLETE / NOT_CERTIFIED`

## 1. Autoridad y alcance

Esta auditoría usa como ley el adjunto aprobado **ERP HUMAN-FIRST UI/UX MASTER SKILL — V2.1 FINAL UNIVERSAL**.

La copia canónica del Master todavía no está persistida en el repositorio porque el canal GitHub disponible bloqueó el payload exacto antes de crear blob/commit. La fuente exacta auditada localmente tiene:

- tamaño: `70,982 bytes`;
- SHA-256: `55265c92b7a49a4ac2ac976afb6789074b5f95ff5760f6d08b7b1d0e817f6940`.

Por tanto:

`HUMAN_FIRST_TASK1 = BLOCKED_TOOL_SAFETY`

La ausencia del Master persistido es un bloqueo de gobernanza, no una justificación para degradar la ley a un resumen o duplicarla.

Baseline técnico auditado:

- `main@b0403a5a1e544d95aa02917ef69687c560a94201`;
- delta funcional R3: `impl/r3-public-surfaces-20261016@845295c777151216655b7a7866bc0dc9d93f3e2e`;
- R3 está `13 commits ahead / 0 behind` frente a main y conserva CI exact-head GREEN PHP 8.3/8.4/8.5;
- R4 no iniciado;
- deployment no autorizado.

La auditoría no modifica PHP, CSS, JS, DB, rutas, servicios ni tests. No aplica remediaciones.

## 2. Método

Se aplicó el orden del Master:

`READ AUTHORITY → INVENTORY → 10 PASSES → FINDINGS → ROOT CAUSES → PRIORITIES → PLAN`

El paso de navegador real no pudo ejecutarse porque Browser requiere permiso del usuario. Por ley del Master, esa categoría queda `BLOCKED / NOT_CERTIFIED`, nunca GREEN por inferencia.

Escala usada:

- `GREEN`: contrato suficientemente demostrado por código + pruebas/evidencia disponible;
- `YELLOW`: base válida con deuda o cobertura/evidencia incompleta;
- `RED`: incumplimiento Human-First demostrado;
- `BLOCKED / NOT_CERTIFIED`: evidencia necesaria no disponible.

## 3. Resultado de las 10 pasadas

| # | Pasada | Estado | Evidencia resumida | Acción |
|---|---|---|---|---|
| 1 | Truth / permisos | GREEN técnico | `Permissions.php` es catálogo único; `View::navigationFor()` deriva una navegación única filtrada; rutas administrativas inspeccionadas vuelven a validar permisos; existen suites de seguridad/regresión por dominios. | KEEP |
| 2 | Comprensión humana | RED | Estados internos, roles, timestamps, IDs y términos técnicos aparecen directamente en múltiples vistas. Contratos expone JSON como entrada del operador. | SIMPLIFY / MERGE |
| 3 | Arquitectura de información | YELLOW | Shell y navegación por grupos son coherentes; varias páginas complejas mezclan demasiados trabajos en una sola superficie. | KEEP shell / SIMPLIFY páginas |
| 4 | Densidad / “Excel infinito” | RED | `candidate_detail`, `obligations` y `tenant_lease_detail` concentran muchos flujos y formularios lineales sin progressive disclosure suficiente. | SIMPLIFY |
| 5 | Sistema visual | YELLOW | Un solo `app.css`, tokens y primitives compartidos; no existe aún `design-system/alquilapro/MASTER.md` persistido. | KEEP + DOCUMENT/MERGE |
| 6 | Accesibilidad | YELLOW | Focus visible, 44/48 px, reduced motion, semántica/ARIA y matriz F24 real; no es auditoría WCAG completa, varias rutas se probaron vacías y R3 es posterior. | VERIFY |
| 7 | Interacción / forms / feedback | YELLOW | CSRF, labels y controles consistentes; hay formularios técnicos/densos y algunas rutas devuelven mensajes de excepción como `text/plain`. | OPTIMIZE |
| 8 | Responsive mobile/tablet | YELLOW | Mobile-first + breakpoints 768/1200 + matriz F24 real; falta revalidar R3 y estados complejos poblados en navegador. | VERIFY |
| 9 | Data-heavy / performance | YELLOW | Arquitectura SSR simple; `/admin/candidates` usa `fetchAll` sin paginación/filtro y renderiza todos los candidatos. | OPTIMIZE |
| 10 | Browser real / cross-page | BLOCKED / NOT_CERTIFIED | Browser control no autorizado en el entorno actual; R3 ya está bloqueado por el mismo gate. | UNBLOCK + VERIFY |

Resultado global:

`HUMAN_FIRST_AUDIT = 1 GREEN + 6 YELLOW + 2 RED + 1 BLOCKED`

No existe certificación Human-First final hasta cerrar el Pase 10 y persistir la gobernanza canónica.

## 4. Hallazgos

### GOV-001 — Master Human-First no persistido

- Severidad: P0 gobernanza.
- Estado: RED / BLOCKED_TOOL_SAFETY.
- Evidencia: falta `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md` en la rama Human-First.
- Esperado: una autoridad canónica exacta + adaptadores finos.
- Actual: SPEC/PLAN existen, pero la ley exacta sólo está disponible como adjunto local aprobado.
- Causa raíz: control de seguridad del conector bloquea el payload completo.
- Tratamiento: `DEFER/BLOCKED` hasta canal autorizado; luego `KEEP` una sola copia canónica.
- Prohibido: resumen, fragmentación para bypass, segunda autoridad o adapter huérfano.

### DOC-001 — README con estado vivo obsoleto

- Severidad: P1.
- Estado: RED drift.
- Evidencia: `README.md` aún declara `main@b45f625e…`, R1 y PR #31 como estado vigente.
- Autoridad real auditada: `main@b0403a5a…`, R3 PR #33 y Human-First PR #34.
- Causa raíz: README se usó como snapshot de fase y no convergió después de avances posteriores.
- Tratamiento: `SIMPLIFY / UPDATE` sólo estado vivo; conservar historia en Git.

### DOC-002 — Recuperación documental demasiado ruidosa

- Severidad: P2.
- Estado: YELLOW.
- Evidencia: `docs/checkpoints/` contiene numerosos preflight, RED, GREEN, CLOSED, MERGED y handoffs históricos.
- Valor: contienen evidencia única útil; no deben borrarse masivamente.
- Problema: descubrir la autoridad activa exige navegar demasiado historial.
- Causa raíz: falta un puntero pequeño y canónico de estado actual.
- Tratamiento: crear `docs/CURRENT_CHECKPOINT.md`; luego clasificar historia como evidencia, no como estado vivo. `MERGE/SIMPLIFY`, no borrado ciego.

### UX-001 — Códigos de dominio y datos técnicos filtrados a la presentación

- Severidad: P1.
- Estado: RED.
- Evidencia:
  - `home.php` imprime el rol interno;
  - `candidates.php` imprime estados y timestamps crudos;
  - `candidate_detail.php` muestra `new`, `incomplete`, `verification`, `pending_screening`, `in_progress`, etc.;
  - `audit_log.php` expone `action`, `entity_type`, IDs de actor y timestamps crudos;
  - otras vistas traducen algunos estados localmente, demostrando inconsistencia.
- Esperado: verdad técnica intacta, traducida a lenguaje humano consistente.
- Causa raíz: no existe un owner pequeño y compartido de labels/formatos de presentación; cada vista decide localmente o no decide.
- Tratamiento: `MERGE / SIMPLIFY` mediante helpers de presentación acotados, no un framework nuevo.

### UX-002 — Dashboard no funciona todavía como cockpit operacional

- Severidad: P1.
- Estado: RED Human-First / contrato actual funcionalmente correcto.
- Evidencia: `home.php` ofrece saludo, rol y accesos rápidos; `DashboardTest` prohíbe métricas inventadas y valida permisos.
- Restricción clave: NO inventar KPIs ni estadísticas.
- Causa raíz: el contrato F23 priorizó launcher adaptativo y seguridad sobre señales operativas reales.
- Tratamiento: `OPTIMIZE` usando únicamente datos reales ya existentes: pendientes/alertas/acciones que requieran atención y estén demostradas por owners actuales.
- KEEP: quick actions permission-aware y prohibición de métricas inventadas.

### UX-003 — Contratos expone interfaces para desarrolladores al operador

- Severidad: P1.
- Estado: RED.
- Evidencia: `contracts.php` solicita `ID del candidato`, `Variables manuales JSON`, `Tareas JSON` y `Anexos JSON`.
- Esperado: seleccionar personas/contexto y editar tareas/anexos mediante controles humanos.
- Causa raíz: la primera UI refleja directamente el shape interno del motor de contratos.
- Tratamiento: `SIMPLIFY / REMOVE` JSON visible de la tarea normal; conservar ContractService como owner. No crear segundo motor.

### UX-004 — Sobrecarga de flujos en páginas complejas

- Severidad: P1.
- Estado: RED.
- Evidencia:
  - `candidate_detail.php`: solicitud + empleo + ocupantes + referencias + documentos + screening + contratos + administración;
  - `obligations.php`: alta + listado + edición + conciliación externa + cargo financiero + vínculo + anulación;
  - `tenant_lease_detail.php`: servicios + documentos + convivencia + legales + manuales + seguros + mantenimiento + demás contexto del arrendamiento.
- Causa raíz: crecimiento funcional acumulativo dentro de una sola vista lineal.
- Tratamiento: `SIMPLIFY` con progressive disclosure SSR: resumen primero, acciones contextuales, secciones colapsables o subrutas/hubs sólo cuando reduzcan carga cognitiva. No SPA.

### UX-005 — Formato humano inconsistente

- Severidad: P2.
- Estado: YELLOW.
- Evidencia: fechas ISO/timestamps, dinero sin formato consistente, IDs técnicos y estados traducidos de forma desigual.
- Causa raíz: formatos repetidos en views sin autoridad de presentación común.
- Tratamiento: `MERGE` en helpers pequeños/canónicos para estado, fecha/hora y dinero; evitar duplicar mapas por vista.

### UX-006 — Feedback de error inconsistente

- Severidad: P2.
- Estado: YELLOW.
- Evidencia: rutas de administración inspeccionadas capturan `RuntimeException` y en varios casos escriben el mensaje directamente como `text/plain` 422, mientras otras superficies renderizan feedback inline.
- Causa raíz: errores funcionales evolucionaron por módulo sin contrato visual común.
- Tratamiento: `OPTIMIZE` feedback humano en la vista conservando códigos/status HTTP y sanitización.

### IA-001 — Shell fuerte, IA de páginas complejas desigual

- Severidad: P1.
- Estado: YELLOW.
- Evidencia: navegación única agrupada en `Operación / Finanzas / Gestión / Administración`; desktop sidebar + Apps mobile. Las páginas complejas no siempre mantienen la misma jerarquía de tareas.
- Tratamiento: `KEEP` shell/navigation; `SIMPLIFY` sólo hubs/detalles con deuda demostrada.

### PERF-001 — Listado de candidatos no acotado

- Severidad: P2.
- Estado: YELLOW escalabilidad.
- Evidencia: `/admin/candidates` llama `CandidateService::listCandidates()`; éste hace `SELECT ... ORDER BY c.id ASC` + `fetchAll()` sin filtro, búsqueda ni límite; la vista renderiza todas las tarjetas.
- Causa raíz: implementación inicial asumió dataset pequeño.
- Tratamiento: `OPTIMIZE` con paginación server-side y filtros humanos. Sin grid framework ni API paralela.

### A11Y-001 — Evidencia accesible buena pero incompleta

- Severidad: P2.
- Estado: YELLOW.
- Evidencia positiva F24: Chromium real en 375×812, 768×1024, 1024×768 y 1440×900; foco visible, reduced motion, controles nombrados, tamaños táctiles y contraste base > 4.5:1.
- Límites de esa evidencia: no es WCAG completa, varias rutas complejas se probaron en estado vacío, branding configurable no cubierto totalmente y R3 público es posterior.
- Tratamiento: `VERIFY`; no rediseñar lo que ya pasó.

### RESP-001 — Responsive actual requiere nueva evidencia real

- Severidad: P2.
- Estado: YELLOW / NOT fully certified.
- Evidencia técnica: mobile-first, breakpoint tablet `768–1199`, desktop `>=1200`, safe areas, sidebar desktop y dock mobile/tablet.
- Gap: R3 y estados poblados complejos no tienen una matriz visual actualizada.
- Tratamiento: `VERIFY` en Browser real antes de cerrar fases UI.

### DS-001 — Sistema visual existe en código, falta su autoridad de proyecto

- Severidad: P3.
- Estado: YELLOW gobernanza.
- Evidencia: `assets/app.css` posee tokens y primitives compartidos; sigue siendo una sola hoja y `app.js` un único runtime nativo.
- Gap: falta `design-system/alquilapro/MASTER.md` por instalación Human-First pendiente.
- Tratamiento: `KEEP` código; `MERGE/DOCUMENT` la verdad actual, sin segundo CSS ni component framework.

### BROWSER-001 — Pase 10 bloqueado

- Severidad: Gate.
- Estado: `BLOCKED / NOT_CERTIFIED`.
- Evidencia: entorno actual responde `Browser control requires your permission.`
- Tratamiento: habilitar navegador real y ejecutar cross-page QA; no sustituirlo por inferencia de CSS/tests.

## 5. Decisiones KEEP

Se conservan explícitamente:

1. PHP SSR + Slim; no SPA.
2. Un `View` y tres layouts (`auth`, `public`, `app`).
3. Un `Permissions` catalog y navegación derivada del mismo modelo.
4. Autorización revalidada en rutas; la navegación no es control de seguridad.
5. Un `assets/app.css` y un `assets/app.js`.
6. Tokens/primitives actuales como base del Design Master.
7. Touch targets 44/48 px, focus-visible, reduced motion y safe-area handling.
8. Shell responsive: dock mobile/tablet + sidebar desktop.
9. Dashboard quick actions filtradas por permisos.
10. Prohibición de métricas inventadas.
11. `finance_index.php` como buen patrón de separar conciliación y cartera y enlazar configuración secundaria.
12. R3 progressive enhancement sin framework ni autoplay/fetch.
13. F24 como evidencia histórica válida dentro de su alcance declarado.

## 6. Causas raíz agrupadas

No hay 13 problemas independientes. Las causas raíz principales son cinco:

### RC-1 — Presentación demasiado cercana al modelo interno

Consecuencias: códigos raw, JSON, IDs, timestamps y labels inconsistentes.

### RC-2 — Crecimiento por acumulación vertical

Consecuencias: páginas detalle convertidas en contenedores de múltiples workflows.

### RC-3 — Contratos Human-First aún no persistidos

Consecuencias: la base visual existe, pero no hay una ley/design master/checkpoint activo versionados como autoridad de UI.

### RC-4 — Listados diseñados para volumen inicial

Consecuencias: al menos candidatos tiene lectura/render no acotada.

### RC-5 — Evidencia browser no acompaña todavía el estado efectivo R3

Consecuencias: accesibilidad/responsive actuales no pueden certificarse por completo.

## 7. Prioridades P0–P4

### P0 — Gobernanza / gates

1. Desbloquear la carga exacta del Master V2.1.
2. Crear adapter fino después de que exista el Master.
3. Instalar `AGENTS` Human-First, PR gate, Design Master y Current Checkpoint.
4. Habilitar Browser para cerrar el Pase 10 y R3.6.

No se propone cambiar runtime en P0 salvo un defecto real reproducido por Browser/seguridad.

### P1 — Comprensión y arquitectura de información

1. Convergencia de labels/estados/rol/fechas visibles.
2. Dashboard con pendientes reales, sin KPIs inventados.
3. Simplificar creación de contratos: retirar JSON/IDs técnicos de la tarea humana normal.
4. Progressive disclosure en candidate detail, obligations y tenant lease detail.
5. Mantener shell/nav actuales y mejorar páginas, no reescribir arquitectura.

### P2 — Forms, datos, escalabilidad, evidencia

1. Feedback 422 consistente dentro de UI.
2. Paginación/filtros server-side para candidatos; auditar otros listados antes de generalizar.
3. Formateo canónico de dinero/fecha/hora/estado.
4. Reejecutar accesibilidad/responsive con estados poblados y R3 actual.

### P3 — Consistencia visual / documentación

1. Persistir Design Master desde los primitives reales.
2. Normalizar detalles visuales sólo después de P1/P2.
3. Corregir drift README y convertir `CURRENT_CHECKPOINT` en puntero vivo.

### P4 — Enhancements

Sólo después de cerrar P0–P3 y Browser QA. No hay mejoras P4 autorizadas en esta auditoría.

## 8. Secuencia de remediación propuesta

Cada bloque funcional futuro debe seguir:

`REPRODUCE/CONTRACT → ROOT CAUSE → OWNER → RED → CAUSAL RED → MINIMAL GREEN → REGRESSION → BROWSER QA → CHECKPOINT`

Orden propuesto:

1. **HF-G0** — persistencia de gobernanza, cero runtime.
2. **HF-G1** — R3 Browser QA y merge gate separado.
3. **HF-P1A** — human labels/formatting owner mínimo + convergencia focal.
4. **HF-P1B** — dashboard operativo con datos reales.
5. **HF-P1C** — contracts human form.
6. **HF-P1D** — candidate detail progressive disclosure.
7. **HF-P1E** — obligations progressive disclosure.
8. **HF-P1F** — tenant portal IA.
9. **HF-P2A** — candidates pagination/filter.
10. **HF-P2B** — feedback/forms consistency.
11. **HF-QA** — matrices real-browser pobladas + WCAG focal + 10-pass re-audit.

Cada bloque debe ser independiente y pequeño. Si un bloque exige crear un segundo owner o framework, detener y rediseñar.

## 9. Límites / no-hallazgos

Esta auditoría NO afirma que:

- todas las listas sean no paginadas; sólo candidatos quedó demostrado;
- exista un fallo de seguridad runtime; el Pase 1 auditado es GREEN técnico;
- la matriz F24 sea inválida; es evidencia útil pero de alcance limitado;
- un archivo grande sea por sí mismo un defecto;
- deban borrarse checkpoints históricos en masa;
- deban crearse tabs/SPA/component frameworks para resolver densidad.

## 10. Gate final de esta auditoría

`AUDIT_ONLY_COMPLETE = YES`

`FIXES_APPLIED = NO`

`HUMAN_FIRST_MASTER_PERSISTED = NO / BLOCKED_TOOL_SAFETY`

`PASS_10_BROWSER = BLOCKED / NOT_CERTIFIED`

`R3_MERGED = NO`

`R4_STARTED = NO`

`DEPLOYMENT_AUTHORIZED = NO`

Próximo gate: owner revisa/aprueba findings y P0–P4. Después se ejecutan microbloques; no se permite una “reescritura general” del ERP.
