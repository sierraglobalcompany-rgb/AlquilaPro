# AGENTS.md — AlquilaPro

Este archivo define cómo debe trabajar cualquier agente/IA en este repositorio.

## 1. Autoridad

Antes de actuar:

1. `README.md`;
2. último `docs/checkpoints/ALQUILAPRO_HANDOFF_FORENSIC_*`;
3. `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`;
4. `docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`;
5. último checkpoint `*_MERGED_*`;
6. checkpoint/spec/plan de la fase activa;
7. GitHub real: branch, PR, diff, CI, código.

Regla:

```text
GitHub/code/tests/CI = estado factual
SPEC/PLAN/checkpoints = intención y contrato
chat/memoria = contexto auxiliar
```

Si contradicen el estado real, no inventar: verificar y registrar el ruling.

## 2. Ley 1 — KISS

La solución correcta debe ser:

```text
simple
eficiente
optimizada
eficaz
auditable
mantenible
```

Con el mínimo razonable de:

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

Antes de eliminar demostrar:

```text
REFERENCIAS
→ AUTORIDAD
→ REEMPLAZO
→ TEST
→ ELIMINAR
→ REGRESIÓN/CI
```

No arrastrar:
- código muerto;
- duplicados;
- fuentes paralelas;
- branches/checkpoints/reportes redundantes sin utilidad;
- dependencias no usadas;
- módulos futuros.

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

Prohibido:
- compatibility fallback para conservar callers viejos;
- optional dependency injection solo para tests;
- segundo writer financiero/documental/notificaciones;
- special-case lateral si el owner real puede corregirse;
- parche sobre parche.

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

No merge sin autorización del usuario.

## 6. Preferencias de trabajo del usuario

- respuestas operativas resumidas y directas;
- no repetir preguntas ya resueltas;
- priorizar velocidad sin perder trazabilidad;
- evitar loops y verificaciones equivalentes;
- no crear arquitectura grande;
- GitHub debe quedar como fuente de continuidad entre chats;
- checkpoints deben permitir retomar exactamente sin repetir trabajo.

### Codex

Cuando se necesite Codex:

- entregar al usuario **un archivo Markdown descargable**;
- no pegar en el chat un prompt Codex enorme como salida principal;
- prompt específico, guiado, autónomo y con hard stops;
- permitir decisiones locales seguras para evitar bloqueos;
- presupuesto arquitectónico explícito;
- TDD focal;
- checkpoint recuperable;
- informe final detallado en Markdown;
- no merge por Codex salvo autorización inequívoca;
- no pedirle que relea todo el repositorio si la fase ya tiene SPEC/PLAN/checkpoint.

### ChatGPT/Sol

Antes de implementar/diseñar:
- revisar GitHub y documentación canónica;
- diseñar/planificar;
- auditar diez pasadas;
- comprobar KISS, Cero Ruido y Antiparches;
- buscar huecos, duplicaciones y trabajo incompleto;
- después ejecutar o preparar Codex.

## 7. QA eficiente

Cada verificación debe cubrir un riesgo distinto.

Preferir:

```text
focused RED/GREEN
→ focused integrated
→ fresh review
→ one repair pass
→ affected focused
→ one PR CI matrix
```

No repetir full suites o CI si el árbol y riesgo probado son equivalentes.

Máximo 3 hipótesis razonadas ante el mismo fallo antes de reevaluar owner/arquitectura.

Nunca:

```text
same test + same code + same environment = rerun
```

## 8. Riesgos críticos ya aprendidos

Tratar con especial cuidado:

- TOCTOU/concurrencia: lock sobre autoridad canónica;
- private storage: tamaño físico antes de lectura, bounded read, MIME/SHA/tamaño, symlink/tamper fail-closed;
- tenant IDOR: ownership antes de lookup lateral;
- FinanceService como único charge writer;
- auth/session/CSRF únicos;
- PR CI usa synthetic merge-ref: no confundir con PR HEAD;
- Product Head vs docs-only Closure Head;
- no optional/fallback constructor para evitar actualizar callers;
- no duplicar documents/notifications/media/status authority;
- no inferir delivery/read de WhatsApp;
- no crear cargo F20 automáticamente al aceptar servicio.

## 9. Estado actual

F0–F14 están cerradas/verificadas; F1–F14 mergeadas.

Bloque siguiente: F15→F20.

Autoridades:

```text
docs/checkpoints/F15_F20_DESIGN_CHECKPOINT_2026-10-14.md
docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
```

Codex puede estar ejecutándolo localmente. Verificar GitHub antes de asumir progreso.

## 10. Recovery

Si el contexto se pierde:

```text
README
→ AGENTS
→ último handoff forense
→ último merged checkpoint
→ checkpoint de fase activa
→ GitHub real
→ continuar NEXT_EXACT_ACTION
```

No reimplementar trabajo certificado sin evidencia real de regresión.
