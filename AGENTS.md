# AGENTS.md — AlquilaPro

Este archivo define cómo debe trabajar cualquier agente/IA en este repositorio.

## 1. Autoridad

Antes de actuar:

1. `README.md`;
2. último `docs/checkpoints/ALQUILAPRO_HANDOFF_FORENSIC_*`;
3. SPEC/PLAN maestro;
4. último checkpoint `*_MERGED_*`;
5. checkpoint/spec/plan de la fase activa;
6. GitHub real: branch, PR, diff, CI y código.

```text
GitHub/code/tests/CI = estado factual
checkpoint activo      = continuidad operativa
SPEC/PLAN              = intención/contrato
chat/memoria           = contexto auxiliar
```

Si existe contradicción, verificar y registrar ruling. No asumir que una frase histórica invalida evidencia posterior.

## 2. Ley 1 — KISS / arquitectura simple

La solución debe ser:

```text
simple
eficiente
optimizada
eficaz
auditable
mantenible
```

Usar el mínimo razonable de:

```text
tablas
clases
estados
rutas
capas
dependencias
fuentes de verdad
```

No arquitectura compleja ni piezas “por si acaso”.

## 3. Ley 2 — Cero Ruido / Convergencia

Antes de agregar:

```text
ELIMINAR
→ UNIR / CONVERGER
→ SIMPLIFICAR
→ MEJORAR / OPTIMIZAR
→ AGREGAR
```

Antes de eliminar:

```text
REFERENCIAS
→ AUTORIDAD
→ REEMPLAZO
→ TEST
→ ELIMINAR
→ REGRESIÓN / CI
```

No arrastrar código muerto, duplicados, fuentes paralelas, módulos futuros, branches/checkpoints/reportes redundantes o dependencias no usadas.

## 4. Ley 3 — Antiparches

Ante un defecto:

```text
REPRODUCE
→ ROOT CAUSE
→ OWNING AUTHORITY
→ SEARCH DUPLICATION
→ RED
→ MINIMAL GREEN
→ REMOVE SUPERSEDED LOGIC
→ REGRESSION
```

Pregunta obligatoria:

> ¿Dónde debería vivir esta regla si el sistema hubiese sido diseñado correctamente desde cero?

Prohibido salvo prueba de necesidad:

- compatibility fallback para conservar callers viejos;
- optional dependency injection solo para tests;
- segundo writer financiero/documental/notificaciones;
- special-case lateral cuando puede corregirse el owner;
- parche encima de parche;
- state duplicado para ocultar inconsistencia.

## 5. Proceso obligatorio

```text
SCOPE
→ PRE-KISS
→ SPEC / DESIGN
→ PLAN
→ AUDITAR 10 PASADAS
→ RED
→ VERIFY RED
→ MINIMAL GREEN
→ VERIFY GREEN
→ CONVERGE
→ FOCUSED QA
→ FRESH REVIEW
→ KISS / ZERO-NOISE / ANTIPATCH GATE
→ CHECKPOINT
→ PR CI
→ FORENSIC AUDIT
→ MERGE AUTORIZADO
```

Cada gate debe cubrir un riesgo diferente. No duplicar verificaciones por ritual.

## 6. Preferencias del usuario

- respuestas operativas directas;
- no pedir información ya resuelta;
- velocidad sin perder trazabilidad;
- evitar loops;
- no arquitectura grande;
- GitHub debe permitir recuperación entre chats;
- checkpoints deben permitir retomar sin repetir trabajo;
- antes de implementar: revisar, diseñar, planificar, auditar 10 pasadas y verificar las tres leyes.

### Codex

Cuando se use Codex:

- entregar al usuario **un Markdown descargable**;
- no pegar como salida principal el prompt grande en el chat;
- un contrato claro, específico, optimizado y con hard stops;
- suficiente autonomía local para no bloquearse;
- budget arquitectónico explícito;
- TDD focal;
- checkpoint recuperable después de Tasks/gates relevantes;
- informe final detallado en Markdown;
- no merge por Codex salvo autorización inequívoca;
- no releer todo el repo si SPEC/PLAN/checkpoint ya fijan la fase;
- no esperar CI cuando puede avanzar de forma segura y recertificar al final;
- no full suites locales repetitivas sin riesgo distinto.

### ChatGPT / Sol

Debe:

- contrastar GitHub real y documentación canónica;
- buscar huecos/faltantes/ruido;
- diseñar y planificar;
- auditar 10 pasadas;
- verificar KISS, Cero Ruido y Antiparches;
- implementar o preparar Codex solo después;
- auditar el resultado de Codex contra GitHub antes del merge.

## 7. QA eficiente / anti-bucles

Preferir:

```text
focused RED/GREEN
→ focused integrated
→ fresh review
→ one repair pass
→ affected focused
→ one PR CI matrix
```

No repetir full suite o CI si el árbol/riesgo probado es demostrablemente equivalente.

Nunca:

```text
same test + same code + same environment = rerun
```

Máximo 3 hipótesis razonadas sobre el mismo fallo antes de reevaluar root cause/owner/arquitectura.

## 8. Riesgos críticos aprendidos

Tratar con especial cuidado:

- TOCTOU/concurrencia: lock sobre autoridad canónica;
- private storage: safe path + no symlink + size cap antes de read + bounded read + MIME/SHA/tamaño + fail-closed;
- tenant IDOR: ownership antes de lookup lateral;
- `FinanceService` como único charge writer;
- auth/session/CSRF únicos;
- CI de PR suele probar synthetic merge-ref, no PR HEAD;
- Product Head vs docs-only Closure Head;
- no optional/fallback constructor para evitar actualizar callers;
- no duplicar documents/notifications/media/status authority;
- F16 es el único motor automático de notificaciones;
- F17 no infiere sent/delivered/read de WhatsApp;
- F18 es la única autoridad documental genérica;
- F20 no crea cargo automáticamente al aceptar un servicio;
- installer wizard, candidate wizard y tenant lifecycle son tres responsabilidades distintas;
- F23 debe corregir el shell en su owner real, no parchear desktop con media queries aisladas.

## 9. Estado activo F15→F20

F0–F14 cerradas/verificadas; F1–F14 mergeadas.

Estado remoto conocido:

```text
F15 PR #18 OPEN — local certified; README conflict con main documental pendiente de reconciliación
F16 PR #19 OPEN — stacked on F15; product complete
F17 PR #20 OPEN — stacked on F16; product complete + closure
F18–F20 — Codex todavía trabajando/no visibles remotamente en último corte
MERGES — NONE
```

Autoridades:

```text
docs/checkpoints/F15_F20_DESIGN_CHECKPOINT_2026-10-14.md
docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md  # en rama activa
```

Mientras Codex trabaja F15→F20, no abrir implementación paralela sobre ese scope.

## 10. Futuro F21→F26

No existe F27 en el roadmap canónico.

```text
F21 Bono + liquidación
F22 Seguridad + privacidad + audit log
F23 Dashboard + Adaptive UX Final
F24 Release Readiness + installer/candidate wizard + QA
F25 SERVER_READY ZIP + local exact-artifact rehearsal
F26 Manual Hostinger Deploy + smoke
```

### F23 UX congelada

- mobile/tablet = touch shell + launcher inferior;
- PC = sidebar persistente + topbar;
- una sola definición PHP de navegación;
- no SPA/frontend framework por rediseño;
- UI-UX Pro Max es referencia, no dependencia runtime;
- targets mínimos 375/768/1024/1440;
- Modern SaaS / Corporate Minimal Fresh.

### F24 wizards

Installer mejora el existente:

```text
requirements
→ DB credentials + Test connection
→ company/settings
→ owner
→ summary
→ install/migrate
→ success/login
```

No `companies` table V1; no tenant/candidate/lease/sample property durante instalación.

Candidate wizard mejora el candidate/token existente:

```text
context/privacy
→ personal/contact
→ work/income
→ occupants/pets
→ references
→ documents
→ review/submit
```

No wizard/session/progress tables.

### F25/F26

F25 certifica el ZIP exacto en local limpio + BD vacía antes de etiquetarlo SERVER_READY.

F26 es subida manual del ZIP a Hostinger + mismo installer + smoke. No bot/SSH/API de despliegue obligatorio.

Autoridades:

```text
docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md
docs/checkpoints/F23_F26_UX_RELEASE_DECISION_2026-10-14.md
```

F21/F22 todavía necesitan SPEC/PLAN final antes de un master Codex F21→F26.

## 11. Recovery

```text
README
→ AGENTS
→ último handoff forense
→ F14_MERGED
→ F15_F20 design checkpoint
→ F15_F20 Codex master V4
→ live F15_F20 batch checkpoint en rama más avanzada
→ F23_F26 UX/release checkpoint
→ GitHub main/PR/CI real
→ continuar NEXT_EXACT_ACTION
```

No reimplementar trabajo certificado sin evidencia real de regresión o nueva SPEC aprobada.
