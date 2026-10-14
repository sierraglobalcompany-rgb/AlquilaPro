# ALQUILAPRO — HANDOFF FORENSE TOTAL DE RECUPERACIÓN
## Pasado · presente · futuro · leyes · metodología · GitHub · riesgos · huecos · continuidad

**Fecha de corte:** 2026-10-14  
**Proyecto:** AlquilaPro  
**Repositorio canónico:** `sierraglobalcompany-rgb/AlquilaPro`  
**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Estado de este handoff:** `RECOVERY AUTHORITY / FORENSIC SNAPSHOT / CODEX F15→F20 CURRENTLY WORKING LOCALLY`  
**Propósito:** permitir que un chat nuevo continúe exactamente donde quedó este hilo, sin reconstruir contexto, sin reabrir fases cerradas y sin arrastrar ruido.

---

# 0. CÓMO USAR ESTE ARCHIVO

Antes de hacer cualquier cambio:

```text
1. Leer este handoff completo.
2. Leer README.md.
3. Leer AGENTS.md.
4. Verificar GitHub real: main, ramas, PR, CI y checkpoint activo.
5. Si Codex ya terminó F15→F20: no volver a ejecutar el master; auditar su resultado.
6. Si Codex sigue trabajando: no crear implementación paralela sobre F15→F20.
```

Regla de autoridad:

```text
GitHub / código / tests / CI
> checkpoints actuales
> SPEC / PLAN canónicos
> este handoff
> memoria de conversación
```

---

# 1. RESUMEN EJECUTIVO

```text
F0–F14: CLOSED / VERIFIED
F1–F14: MERGED

F15–F20:
SPEC APPROVED
PLANS COMPLETE
CODEX MASTER V4 READY
CODEX TRABAJANDO LOCALMENTE SEGÚN EL USUARIO

GITHUB F15→F20 AL MOMENTO DE LA AUDITORÍA:
OPEN PRs: 0
REMOTE F15 BRANCH: no visible todavía
LIVE F15_F20_BATCH_CHECKPOINT: no visible todavía
```

Estado documental corregido durante este handoff:

```text
README obsoleto: CORREGIDO
commit: 75574860159d308d7e469971ecfa3a8e0ae3bff8

AGENTS.md inexistente: CORREGIDO
commit: cc4eabd5910d02dcf792209dcc15d749b581b72b

MAIN inmediatamente antes de crear este handoff:
cc4eabd5910d02dcf792209dcc15d749b581b72b
```

---

# 2. LEYES SUPREMAS

## KISS

```text
simple
eficiente
optimizado
eficaz
auditable
mantenible
```

Con el mínimo razonable de tablas, clases, estados, rutas, capas, dependencias y fuentes de verdad.

## Cero Ruido

```text
ELIMINAR
→ UNIR / CONVERGER
→ SIMPLIFICAR
→ MEJORAR / OPTIMIZAR
→ AGREGAR
```

Eliminar solo después de demostrar referencias, autoridad, reemplazo y regresión.

## Antiparches

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

> ¿Dónde debería vivir esta regla si AlquilaPro hubiese sido diseñado correctamente desde cero?

No compatibility fallbacks, optional dependencies para preservar callers viejos, segundos writers ni parche sobre parche.

---

# 3. PREFERENCIAS DE TRABAJO

## ChatGPT / Sol

```text
revisar
contrastar GitHub
entender pasado/presente/futuro
buscar huecos
diseñar
planificar
auditar 10 pasadas
comprobar leyes
optimizar
luego implementar/delegar
```

No repetir preguntas ya resueltas. Priorizar eficiencia sin perder trazabilidad.

## Codex

El usuario prefiere:

```text
UN MARKDOWN DESCARGABLE
```

No prompt gigante pegado en el chat como salida principal.

Debe ser autosuficiente, guiado, específico, eficiente, auditable, con límites, autonomía local, hard stops, checkpoint recuperable e informe final.

Codex implementa/prueba/checkpoint/avanza. No rediseña, no mergea sin autorización y no adelanta fases futuras.

---

# 4. METODOLOGÍA

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

---

# 5. ROLES

```text
ChatGPT/Sol:
producto, SPEC, arquitectura, planes, auditoría forense, GitHub/CI, checkpoints, merge gates

Codex:
implementación, TDD focal, refactor controlado, Git local, branches, PR, evidencia

Antigravity:
QA/auditoría independiente cuando aporte valor
```

---

# 6. STACK

```text
PHP >=8.3 <8.6
CI PHP 8.3 / 8.4 / 8.5
Slim 4
MariaDB/MySQL
PDO
SSR HTML
CSS mobile-first
JS mínimo
Composer
PHPUnit
Dompdf desde F7
PHPMailer entra en F16
Hostinger
```

Sin SPA, Node obligatorio, Redis, workers daemon o microservicios salvo nueva SPEC aprobada.

---

# 7. PRESUPUESTO GLOBAL

```text
1 app
1 BD
1 repo
1 auth
1 catálogo derivado de units
1 candidato
1 motor financiero
1 motor recordatorios
1 sistema documental
1 flujo tickets/visitas
1 installer
1 cron
```

---

# 8. FASES CERRADAS

```text
F1 merge: a5225151358aaeca4698adf8cb85d815752a5ac4
F2 merge: 96a4fa59ca82604d821061c6aed7d9edb3849ae1
F3 merge: b35c32b9f8c99646879e22b1af92e6a54690ceca
F4 merge: 3226feeea0b1740659df46fae6499842dfbc894a
F5 merge: 8511f8f8eb0c086e84c1170940353ff5460da66b
F6 merge: 875c990a5a3c9fa7a2adba3edcad6a790703280e
F7 merge: b6ab43fefd7b4d2933e3e3adff0ac4c23efdf1b8
F8 merge: a1b50f0d1c6556531626fd35cba0668bd5c4029f
F9 merge: e11c053365f64cfab88e37254ea86466a31e24ce
F10 merge: e64cba16ee697f9db09232543bc550baa847f25b
F11 merge: 9112b19ab9585cb02e41960f17f78faeec64d06c
F12 merge: 23cf7576c95fca5e55825d8b5ffd5903bf3d3149
F13 merge: 57911302bd4e97e76b1e0fa6dc71c8ef24372453
F14 merge: c58ee5e660f51e873546f03a578fde6b5778ba19
```

F12 final CI:

```text
38519741952
406 tests / 4,193 assertions PHP 8.3/8.4/8.5
```

F13 final CI:

```text
38553176872
431 tests / 4,451 assertions PHP 8.3/8.4/8.5
security repair: b11705cb387129c51090bbef0c3658c4843f940d
```

F14 final CI:

```text
38553704399
443 tests / 4,604 assertions PHP 8.3/8.4/8.5
```

Autoridad detallada:

```text
docs/checkpoints/F12_F14_BATCH_CHECKPOINT_2026-10-14.md
docs/checkpoints/F14_MERGED_2026-10-14.md
```

---

# 9. BLOQUE ACTIVO F15→F20

Autoridades:

```text
docs/checkpoints/F15_F20_DESIGN_CHECKPOINT_2026-10-14.md
docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md
docs/specs/F20_PRODUCT_DECISION_2026-10-14.md
docs/specs/F20_MEMBERSHIPS_SERVICES_SPEC.md
docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
```

Master:

```text
V4 FINAL LEAN-FAST
commit 7348058fc5854c50537989dc04619af313d261fa
```

Budgets:

```text
F15: 1 maintenance_visits + 1 VisitService + 0 new permissions/deps
F16: 1 notification_jobs + 1 NotificationService + PHPMailer ^7.1 only
F17: 1 whatsapp_actions + 1 WhatsAppService + whatsapp.use
F18: 1 documents + 1 DocumentService + documents.view/manage
F19: 1 community_incidents + 1 CommunityService + community.view/manage + reuse F18
F20: 2 tables digital_services/service_memberships + 1 MembershipService + memberships.view/manage
```

F20:

```text
tenant accepts service
→ membership persists
→ NEVER automatic F9 charge

free:
→ no financial decision

paid:
→ admin CREATE / Crear cargo
→ admin SKIP / No crear cargo

CREATE → FinanceService::createManualCharge(type='service')
SKIP   → terminal no-charge decision
```

---

# 10. MECÁNICA CODEX

```text
main
→ F15
→ F16
→ F17
→ F18
→ F19
→ F20
```

Live checkpoint esperado:

```text
docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md
```

Después de cada Task:

```text
PHASE/TASK
BRANCH/BASE
CURRENT_HEAD
WORKTREE
DONE
RED
GREEN
RULING
FINDING
NEXT_EXACT_ACTION
```

No commit solo por checkpoint.

Cierre:

```text
focused GREEN
→ fresh review
→ repair Critical/Important only
→ affected focal
→ composer strict
→ diff-check
→ PRODUCT_HEAD
→ docs-only CLOSURE_HEAD
→ push
→ PR CI
→ continuar siguiente fase sin esperar CI
```

Codex no mergea y no inicia F21.

---

# 11. ERRORES IMPORTANTES A EVITAR

## F8 TOCTOU

Lock sobre autoridad canónica cuando dos flujos compiten.

## F11 fallback constructor

No hacer dependency opcional para preservar callers/tests viejos. Actualizar callers.

## F11 merge-ref

No confundir PR HEAD con synthetic merge-ref probado por GitHub Actions.

## F11 blank allocations

Vacío/no seleccionado no es dato inválido.

## QA/GD

Environment smoke antes de suites caras.

## F13 bounded private read

```text
physical size cap
→ fstat
→ bounded read
→ MIME
→ SHA
```

## Retarget stacked PR

No pagar CI redundante si se demuestra tree equivalence exacta.

---

# 12. RIESGOS DELICADOS

```text
TOCTOU/concurrency
private storage
IDOR/tenant ownership
FinanceService único writer
one auth/session/CSRF
PR HEAD vs merge-ref
Product vs Closure
no compatibility fallback
no duplicate Documents/Notifications
no fake WhatsApp delivery
F20 no auto-charge
```

---

# 13. HUECOS ENCONTRADOS

## README stale

```text
CLOSED
commit 75574860159d308d7e469971ecfa3a8e0ae3bff8
```

## AGENTS missing

```text
CLOSED
commit cc4eabd5910d02dcf792209dcc15d749b581b72b
```

## F15 live checkpoint no visible

```text
EXPECTED / NON-BLOCKING WHILE CODEX LOCAL
GitHub 404 at audit time
```

## F15 remote branch / open PR absent

```text
EXPECTED / NON-BLOCKING WHILE CODEX LOCAL
open PRs = 0 at audit time
```

## Branch noise candidates

```text
noop-do-not-use
noop-do-not-use-2
impl/f9-charges-portfolio-20261013-docs-tmp
```

Comparisons show main ahead and these branches behind_by 0: no known unmerged commits. Reconfirm before deleting.

## .phpunit.cache/

`.gitignore` ignores `/.phpunit.result.cache` but not `/.phpunit.cache/`.

Codex has repeatedly mentioned a preexisting `.phpunit.cache/`. Verify locally after current batch; if transient, add it to `.gitignore`.

## Main branch protection

GitHub reported `main protected: false`.

Future governance improvement: consider require PR + CI + no force-push, without overengineering.

---

# 14. FUTURO F21→F26

```text
F21 Bono + liquidación
F22 Seguridad + privacidad
F23 Dashboard final con datos reales
F24 QA PHP 8.3/8.4/8.5
F25 ALQUILA_PRO_V1_SERVER_READY.zip
F26 Hostinger + smoke test
```

Mientras Codex trabaja F15→F20 se puede adelantar diseño/auditoría F21+, pero no implementación paralela de F15→F20.

---

# 15. CUANDO CODEX TERMINE

Auditar:

```text
GitHub branch/PR/base/head
changed files
migrations
services
permissions
routes
tests
live checkpoint
Product→Closure docs-only
merge-ref CI
review findings
repair propagation
KISS
Zero Noise
Antipatch
```

Orden:

```text
F15 → F16 → F17 → F18 → F19 → F20
```

No mergear por confianza en el reporte.

---

# 16. MERGE ESPERADO F15→F20

```text
merge F15 → main
retarget F16 → main
verify tree equivalence / recertify if needed
merge F16
...
merge F20
```

No forzar CI equivalente si se demuestra igualdad exacta del árbol probado.

---

# 17. FORMATO FUTURO DE PROMPTS CODEX

Siempre un `.md` descargable con:

```text
MISSION
EXACT BASE
AUTHORITIES
SCOPE
ARCHITECTURE BUDGET
KISS
ZERO NOISE
ANTIPATCH
AUTONOMY
HARD STOPS
TASK CARDS
TDD
CHECKPOINT
ANTI-LOOP
QA
CI
FINAL REPORT FORMAT
STOP CONDITION
```

El checkpoint vivo debe convertirse en informe final; no crear múltiples documentos equivalentes.

---

# 18. AUDITORÍA 10 PASADAS

```text
1 GitHub factual state       PASS
2 KISS                       PASS
3 Zero Noise                 PASS WITH NON-BLOCKING HYGIENE
4 Antipatch                  PASS
5 Past F0-F14 recovery       PASS
6 Current F15-F20 recovery   PASS
7 Future F21-F26 recovery    PASS
8 User/Codex preferences     PASS
9 Critical risk lessons      PASS
10 New-chat recoverability   PASS
```

```text
HANDOFF_FORENSIC_AUDIT_10_PASSES: PASS
```

---

# 19. START SCRIPT PARA CHAT NUEVO

```text
PROJECT: AlquilaPro
LAST FULLY MERGED PRODUCT PHASE: F14
ACTIVE EXECUTION BATCH: F15→F20
CODEX STATUS: working locally per user at handoff time
REMOTE STATUS AT AUDIT: no F15 branch, no open PR, no live batch checkpoint visible
CANONICAL MASTER: docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
```

Primeras acciones:

```text
1 fetch current main
2 inspect this handoff
3 inspect live F15_F20 checkpoint if now present
4 inspect branches/PR/CI if Codex pushed
5 if Codex still working, only future design/audit
6 when Codex responds, forensic-audit F15→F20
```

---

# 20. ORDEN DE LECTURA

```text
README.md
AGENTS.md
this handoff
F14_MERGED checkpoint
F12_F14 batch checkpoint
F15_F20 design checkpoint
master SPEC V1.2
master roadmap V1.2
F15_F20 batch SPEC
F20 product/full SPEC
Codex master V4
live checkpoint if present
GitHub real
```

---

# 21. ESTADO FINAL

```text
README: CURRENT
AGENTS: CURRENT
RECOVERY HANDOFF: CREATED

F0-F14: CLOSED / VERIFIED
F1-F14: MERGED

F15-F20:
CODEX ACTIVE LOCALLY
REMOTE NOT YET VISIBLE AT AUDIT CUT

F21-F26:
ROADMAP DEFINED / NOT IMPLEMENTED

KISS: PASS
ZERO_NOISE: PASS
ANTIPATCH: PASS

KNOWN PRODUCT BLOCKERS BEFORE CODEX OUTPUT:
NONE

NON-BLOCKING HYGIENE:
noop branches
F9 docs-tmp branch
possible .phpunit.cache ignore
main branch protection disabled

NEXT MAJOR GATE:
CODEX F15→F20 RESULT
→ SOL FORENSIC AUDIT
→ SEQUENTIAL MERGE GATES
```
