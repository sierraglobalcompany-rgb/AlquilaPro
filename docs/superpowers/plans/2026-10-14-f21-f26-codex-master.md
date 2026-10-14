# ALQUILAPRO — MASTER DE EJECUCIÓN F21→F26 V1 LEAN
## Tasks internas · checkpoints · CI merge-ref · no merge sin autorización
### KISS · Cero Ruido · Antiparches · TDD focal · anti-bucles

**Repo:** `sierraglobalcompany-rgb/AlquilaPro`  
**START_RULE:** F21 solo puede iniciar cuando F15→F20 estén auditadas, corregidas, fusionadas secuencialmente en `main` con autorización explícita y exista un `F20_MERGED_BASE` verificable.  
**STOP:** F26 verificada o `DEPLOYMENT_HANDOFF_READY`. NO F27.  
**Estado de este master:** `PLAN ONLY / USER REVIEW REQUIRED / IMPLEMENTATION NOT AUTHORIZED`.

---

# 1. MISIÓN

Secuencia canónica:

```text
F21 Bono + Liquidación
→ F22 Seguridad + Privacidad + Audit Log
→ F23 Dashboard + UX Adaptativa
→ F24 Release Readiness + Wizards + QA
→ MERGE GATE F21→F24
→ F25 SERVER_READY + rehearsal exacto
→ F26 Hostinger manual + smoke
```

F25 no se construye desde una rama apilada no integrada: su fuente debe ser `main` post-F24 tras autorización y auditoría de merges.

---

# 2. AUTORIDAD

Leer una vez antes de ejecutar:

```text
docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md
docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md

docs/plans/F21_BONUS_LIQUIDATION_IMPLEMENTATION_PLAN.md
docs/plans/F22_SECURITY_PRIVACY_AUDIT_IMPLEMENTATION_PLAN.md
docs/plans/F23_ADAPTIVE_UX_DASHBOARD_IMPLEMENTATION_PLAN.md
docs/plans/F24_RELEASE_READINESS_WIZARDS_QA_IMPLEMENTATION_PLAN.md
docs/plans/F25_SERVER_READY_PACKAGE_REHEARSAL_IMPLEMENTATION_PLAN.md
docs/plans/F26_HOSTINGER_MANUAL_DEPLOY_SMOKE_IMPLEMENTATION_PLAN.md
```

Autoridad:

```text
Git/code/tests/CI real > checkpoint vivo > SPEC > plan de fase > este master
```

Si F20 certificado difiere de la SPEC previa, reconciliar solo el contrato F21 afectado antes de implementar.

---

# 3. LEYES

```text
KISS: mínimo correcto de tablas/clases/rutas/deps/estados/fuentes de verdad.
ZERO NOISE: ELIMINAR → CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR.
ANTIPATCH: REPRODUCE → ROOT CAUSE → OWNER → RED → MINIMAL GREEN
           → REMOVE SUPERSEDED LOGIC → REGRESSION.
```

Prohibido:

```text
segundo Finance/Lease/Documents/Auth/Notification owner
event bus/service locator/repository-DTO hierarchy
frontend SPA/framework/build chain para F23
wizard/progress/session tables para F24
release artifact con secretos/test data
hot patch canónico en producción
merge sin autorización
F27+
```

---

# 4. PRE-FLIGHT GLOBAL

Antes de F21:

```text
git fetch origin
git switch main
git pull --ff-only
git rev-parse HEAD      -> F20_MERGED_BASE
git status --short      -> CLEAN
php -v
php -m
composer validate --strict
```

Confirmar soporte PHP 8.3–8.5 y extensiones/runtime requeridos. No debilitar requisitos por limitaciones locales.

Hard stop si:
- F20 no está integrado/certificado;
- main no está limpio;
- resolver exige ampliar un budget congelado;
- aparece segundo owner/arquitectura paralela;
- hay Critical sin raíz/repair claro;
- acción destructiva o merge no autorizado.

---

# 5. BRANCH MODEL F21→F24

```text
main@F20_MERGED_BASE
→ impl/f21-bonus-liquidation-<date>
→ impl/f22-security-privacy-audit-<date>
→ impl/f23-adaptive-ux-dashboard-<date>
→ impl/f24-release-readiness-wizards-<date>
```

Cada branch nace del `CLOSURE_HEAD` anterior para permitir avance sin esperar merge, pero cada PR sigue marcado:

```text
STACKED F21-F24
MERGE NOT AUTHORIZED
```

No rebase destructivo. Repairs upstream se propagan limpiamente y solo se recertifican descendientes materialmente afectados.

---

# 6. PROTOCOLO POR TASK

```text
RED
→ VERIFY RED
→ MINIMAL GREEN
→ VERIFY GREEN
→ affected regression
→ checkpoint
```

No full suite por task. No rerun idéntico sin cambio/hipótesis. Máximo 3 hipótesis razonadas para un mismo fallo antes de reevaluar owner/diseño.

Después de cada task registrar:

```text
PHASE/TASK
BRANCH/BASE
CURRENT_HEAD + WORKTREE
RED command/signature
GREEN change/command/result
RULING/FINDING
NEXT_EXACT_ACTION
```

---

# 7. CIERRE F21 / F22 / F23 / F24

Al final de cada fase:

```text
fresh review
→ repair solo proven Critical/Important
→ affected focused rerun
→ full suite una vez donde el plan lo exige
→ composer validate --strict
→ lint/diff check
→ PRODUCT_HEAD
→ checkpoint/docs closure
→ CLOSURE_HEAD
→ verify Product→Closure product delta = 0
→ push una vez
→ PR
→ CI merge-ref PHP 8.3/8.4/8.5 cuando aplique
```

No merge automático.

Presupuestos:

```text
F21 <=2 tables + 1 TerminationService + 0 deps/perms
F22 1 audit_log + 1 AuditService + 0 deps/perms/auth stack/event bus
F23 0 tables/deps/auth/route trees; one nav authority
F24 0 product tables/services/deps; convergence only
```

---

# 8. F21 — EXECUTION GATE

Antes de T21.1:
- revalidar F20 remoto/merged real;
- confirmar APIs de membership list/pending charge decision;
- ended lease no acepta/renueva membership.

Ejecutar `docs/plans/F21_BONUS_LIQUIDATION_IMPLEMENTATION_PLAN.md` exactamente por tasks.

Hard invariants:

```text
LeaseService = lifecycle owner
FinanceService = financial writer
DocumentService = documents/inventory owner
TerminationService = composition + bonus + final snapshot only
```

Cierre exige `F21_CHECKPOINT...`, PR CI y `MERGE NOT AUTHORIZED`.

---

# 9. F22 — EXECUTION GATE

Solo desde F21 `CLOSURE_HEAD` local/stacked GREEN.

Ejecutar `docs/plans/F22_SECURITY_PRIVACY_AUDIT_IMPLEMENTATION_PLAN.md`.

Hard invariants:

```text
one audit_log
one AuditService
owner-only viewer
no event bus/new auth/new scheduler
security repair only after RED
notification recipient retention uses existing F16 cron
```

Un test estructural obsoleto no justifica parche de producto: preferir comportamiento/orden semántico, especialmente en IDOR.

---

# 10. F23 — EXECUTION GATE

Solo desde F22 GREEN sin Critical/Important.

Ejecutar `docs/plans/F23_ADAPTIVE_UX_DASHBOARD_IMPLEMENTATION_PLAN.md`.

Hard invariants:

```text
same SSR/routes/auth/data
one navigation model
touch shell <=1199
desktop shell >=1200
0 SPA/framework/build chain
0 domain semantic change
```

---

# 11. F24 — EXECUTION GATE

Solo desde F23 adaptive shell GREEN.

Ejecutar `docs/plans/F24_RELEASE_READINESS_WIZARDS_QA_IMPLEMENTATION_PLAN.md`.

Hard invariants:

```text
candidate wizard = same candidate row/token
installer wizard = same Installer + temporary PHP session
0 wizard tables
0 sample business data
PHP 8.3/8.4/8.5 CI GREEN
```

---

# 12. MERGE GATE F21→F24

F25 is blocked until canonical `main` contains F21–F24.

Required sequence with explicit user authorization:

```text
F21 -> main
retarget/rebase-safe reconcile F22 -> main -> recertify merge-ref
F22 -> main
retarget F23 -> main -> recertify
F23 -> main
retarget F24 -> main -> recertify
F24 -> main
```

Before each merge:
- inspect actual diff/merge-ref;
- require CI green;
- confirm no unapproved product delta;
- user explicitly authorizes that merge.

After F24 merge:

```text
git switch main
git pull --ff-only
git status --short = CLEAN
git rev-parse HEAD -> RELEASE_SOURCE_SHA
```

Only then unlock F25.

---

# 13. F25 — SERVER_READY

Execute `docs/plans/F25_SERVER_READY_PACKAGE_REHEARSAL_IMPLEMENTATION_PLAN.md` from `main@RELEASE_SOURCE_SHA`.

Hard invariants:

```text
exact filename ALQUILA_PRO_V1_SERVER_READY.zip
production vendor included
no secrets/test data/dev noise
manifest + VERSION + SHA-256
exact ZIP rehearsal from clean extract + empty DB
```

If product defect appears: reject artifact, repair owner in source, recertify, merge authorized repair, create NEW artifact/checksum, rehearse from zero.

No deployment before `SERVER_READY: YES`.

---

# 14. F26 — HOSTINGER MANUAL DEPLOY

Execute `docs/plans/F26_HOSTINGER_MANUAL_DEPLOY_SMOKE_IMPLEMENTATION_PLAN.md` using the exact F25 SHA-256 artifact.

Hard invariants:

```text
manual upload/extract acceptable
same installer wizard
no mandatory SSH/API/FTP/CI architecture
no sensitive smoke data
no production hot patch as canonical fix
```

Final truthful states:

```text
DEPLOYED_VERIFIED
HOLD
DEPLOYMENT_HANDOFF_READY
```

Never infer deployment from documentation alone.

---

# 15. FINAL STOP

Successful project close requires:

```text
F21 CLOSED
F22 CLOSED
F23 CLOSED
F24 CLOSED
main canonical post-F24
F25 SERVER_READY: YES
F26 DEPLOYED_VERIFIED
```

or, if execution cannot access Hostinger:

```text
F25 SERVER_READY: YES
F26 DEPLOYMENT_HANDOFF_READY
```

Then STOP. **NO F27.**

## Self-Review

```text
SPEC coverage: PASS
phase order: PASS
F25 canonical-main gate: PASS
merge authorization preserved: PASS
KISS: PASS
ZERO NOISE: PASS
ANTIPATCH: PASS
NO F27: PASS
```

**MASTER_STATUS:** `WRITTEN / USER REVIEW REQUIRED / F20 COMPLETION + MERGE GATE REQUIRED / EXECUTION NOT AUTHORIZED`.
