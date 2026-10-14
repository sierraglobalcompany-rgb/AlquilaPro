# ALQUILAPRO — CODEX MASTER ÚNICO F15→F20 V4 FINAL LEAN-FAST
## Tasks internas · checkpoint vivo · CI en paralelo · no merge
### KISS · Cero Ruido · Antiparches · TDD focal · anti-bucles

**Repo:** `sierraglobalcompany-rgb/AlquilaPro`  
**PREPACKAGE_BASE:** `37350105ac8c1806ae69194082098dbe230e5838`  
**START_RULE:** antes de crear F15, ejecutar `git fetch origin && git switch main && git pull --ff-only && git rev-parse HEAD`; ese SHA exacto será `MAIN_START`. Debe ser `PREPACKAGE_BASE` o un descendiente exclusivamente documental que contenga esta V4 y el checkpoint actualizado.  
**Modo:** `superpowers:executing-plans` inline.  
**STOP:** F20 verificada. NO merge. NO F21.

---

# 1. MISIÓN

Implementa secuencialmente:

```text
F15 Agenda/Visitas
→ F16 Recordatorios/Email
→ F17 WhatsApp Asistido
→ F18 Documentos/Inventarios
→ F19 Convivencia/Legal
→ F20 Membresías/Servicios
```

Ramas apiladas; cada fase auditable; avanzar sin esperar CI remota.

---

# 2. AUTORIDAD

Leer una vez:

```text
docs/checkpoints/F14_MERGED_2026-10-14.md
docs/checkpoints/F15_F20_DESIGN_CHECKPOINT_2026-10-14.md
docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md
docs/specs/F20_PRODUCT_DECISION_2026-10-14.md
docs/specs/F20_MEMBERSHIPS_SERVICES_SPEC.md
```

Planes de fase = fallback solo para firma/test exacto:

```text
F15 docs/superpowers/plans/2026-10-14-f15-agenda-visits.md
F16 docs/superpowers/plans/2026-10-14-f16-reminders-email.md
F17 docs/superpowers/plans/2026-10-14-f17-whatsapp-assisted.md
F18 docs/superpowers/plans/2026-10-14-f18-documents-inventories.md
F19 docs/superpowers/plans/2026-10-14-f19-community-legal.md
F20 docs/superpowers/plans/2026-10-14-f20-memberships-services.md
```

```text
Git = hechos
SPEC = comportamiento
Plan = firma/test
V4 = ejecución/checkpoint/CI/anti-loop
```

No reauditar F1–F14.

---

# 3. LEYES + LÍMITES

```text
KISS:
mínimas tablas/clases/estados/rutas/capas/deps.

ZERO NOISE:
ELIMINAR → CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR.

ANTIPATCH:
REPRODUCE → ROOT CAUSE → OWNER → RED → MINIMAL GREEN
→ REMOVE SUPERSEDED LOGIC → REGRESSION.
```

Prohibido introducir:

```text
repository/DTO/event-bus/service-locator/factory hierarchy
compatibility fallback
optional dependency para conservar tests viejos
segundo writer de Finance/Documents/Notifications
status-history especulativo
provider abstraction futura
no-op commit/push
F21+
```

Codex puede decidir tests, fixtures, SQL preparado, helpers privados, copy SSR,
mensajes seguros, pequeños refactors locales y rulings menores.

HARD STOP solo si la solución correcta exige ampliar budget, cambiar auth/ownership,
duplicar autoridad, añadir provider/API/ruta pública/dep no aprobada, acción destructiva,
CI imposible o Critical no resuelto tras un repair.

---

# 4. RAMAS

```text
main
→ impl/f15-agenda-visits-20261014
→ impl/f16-reminders-email-20261014
→ impl/f17-whatsapp-assisted-20261014
→ impl/f18-documents-inventories-20261014
→ impl/f19-community-legal-20261014
→ impl/f20-memberships-services-20261014
```

Cada rama nace del `CLOSURE_HEAD` anterior.

No merge/squash/rebase destructivo.

---

# 5. CHECKPOINT ÚNICO — DESPUÉS DE CADA TASK

Crear:

```text
docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md
```

Después de **cada Task**, antes de continuar, actualizar localmente:

```text
PHASE/TASK
BRANCH/BASE
CURRENT_HEAD + WORKTREE
DONE
RED command + signature
GREEN change + command + result
RULING/FINDING
NEXT_EXACT_ACTION
```

Reglas:
- ningún otro checkpoint;
- no commit solo por checkpoint;
- arrastrarlo con el siguiente commit natural;
- closure commit de fase lo consolida.

Recuperación:

```text
git branch --show-current
git rev-parse HEAD
git status --short
leer checkpoint
continuar NEXT_EXACT_ACTION
```

No repetir Task PASS.

---

# 6. EJECUCIÓN / ANTI-BUCLES

Una vez al inicio:

```bash
php -v
php -m
composer validate --strict
git status --short
```

Confirmar `pdo,pdo_mysql,mbstring,dom,gd,fileinfo`, DB y storage.

TDD:

```text
RED → VERIFY RED → MINIMAL GREEN → VERIFY GREEN
```

Por Task: test focal + regresiones directamente afectadas.  
No full suite local rutinaria.

Nunca:

```text
same test + same code + same env = rerun
```

Fallo:

```text
leer → focal → root cause → 1 hipótesis → 1 cambio → rerun
```

Máximo 3 hipótesis; luego reevaluar owner/arquitectura una vez.

No polling CI.

Targets/fase:

```text
PRODUCTIVE_COMMITS <=2
DOC_COMMITS =1
FRESH_REVIEWS =1
LOCAL_FULL_SUITE_RUNS =0
REMOTE_CI_RUNS =1
```

---

# 7. PROTOCOLO ÚNICO DE CIERRE DE FASE

Después de la última Task productiva de cada fase:

```text
1 fresh review
→ repair solo Critical/Important
→ affected focused rerun
→ composer validate --strict
→ git diff --check
→ PRODUCT_HEAD
→ actualizar README/SPEC/checkpoint solamente
→ CLOSURE_HEAD docs-only
→ verify Product→Closure product files = 0
→ push una vez
→ abrir PR
→ CI empieza
→ SI local gate PASS: crear siguiente branch inmediatamente
```

**No esperar CI para continuar.**

Al final F20 se verifican las seis matrices.

Si una CI upstream falla:
```text
parar en próximo checkpoint
→ reparar owner
→ nueva CI solo owner
→ propagar repair por merge limpio
→ recertificar solo descendientes afectados
```

CI PR = merge-ref sintético. Registrar:

```text
PR_HEAD / PR_BASE / CI_RUN / CI_MERGE_REF
PHP8.3/8.4/8.5 tests+assertions
```

No Product CI separado si Product→Closure es docs-only.

Todos los PR:
```text
STACKED BATCH F15-F20
MERGE NOT AUTHORIZED
```

---

# 8. F15 — AGENDA/VISITAS

Budget:
```text
1 maintenance_visits | 1 VisitService | 0 perms/deps | migration 015
```

**T15.1 Core**  
`propose,scheduleDirect,find,listForRequest,selectSlot,reprogram,complete,cancel`; slots 1..10 futuros/únicos/ordenados; states `proposed|scheduled|completed|cancelled`; no `lease_id` duplicado; row locks.  
RED/GREEN schema+service. CKPT→T15.2.

**T15.2 Staff/concurrency**  
Races `select/select`, `select/reprogram`, `complete/cancel`, `reprogram/complete`; reuse Maintenance routes/UI + `maintenance.view/manage`; one VisitService bootstrap.  
CKPT→T15.3.

**T15.3 Tenant/security**  
`tenant guard → findForUser → request → visit`; tenant solo ve/select own proposed slot; no technician/complete/cancel. Freeze `1 table/1 service/0 perms/0 F16`.  
Affected F14/F8/auth/CSRF focal. CKPT→F15_CLOSE.

PR base: `main`.

---

# 9. F16 — RECORDATORIOS/EMAIL

Budget:
```text
1 notification_jobs | 1 NotificationService | 0 perms
phpmailer/phpmailer ^7.1 only | migration 016
0 Redis/workers/rule/template tables
```

**T16.1 Queue**  
`UNIQUE(dedupe_key)`; no persisted status; required transport Closure `(email,name,subject,html,text)`; methods `queue,generateReminders,processDue,queueCandidateEvent`; bounded retries.  
CKPT→T16.2.

**T16.2 Rules/events**  
Reminders solo `charges,F12,F13 expiry,F15 visits`; stable dedupe; candidate created/relevant-update queues only; never SMTP inline; templates in code.  
CKPT→T16.3.

**T16.3 SMTP/cron**  
Bootstrap PHPMailer transport; no `mail()` fallback; reuse `app/Notifications/routes.php`; `POST /cron/notifications` + `X-AlquilaPro-Cron-Secret`; exact CSRF exemption; `hash_equals`; bounded batch; safe counts.  
CKPT→T16.4.

**T16.4 Freeze**  
`1 table/1 service/PHPMailer only/0 worker-Redis-template-rule tables`; candidate routes contain no direct SMTP.  
CKPT→F16_CLOSE.

PR base: F15 branch.

---

# 10. F17 — WHATSAPP ASISTIDO

Budget:
```text
1 whatsapp_actions | 1 WhatsAppService | 1 whatsapp.use
reuse Notifications routes | 0 API/provider/webhook/deps | migration 017
```

**T17.1 Core**  
`prepare,find,markOpened,listRecent`; contexts `candidate|lease|maintenance_request`; safe phone→`wa.me`; templates/vars allowlisted; snapshots; NO sent/delivered/read/provider IDs.  
CKPT→T17.2.

**T17.2 Routes/UI**  
Add exactly `whatsapp.use`; owner bypass/tenant hard deny; private prepare/history/open; open=`markOpened → 303 HTTPS wa.me`; CSRF/forced-password/basePath.  
CKPT→T17.3.

**T17.3 Freeze**  
`1 table/1 service/1 perm/0 provider semantics`; focused F16/F17/auth.  
CKPT→F17_CLOSE.

PR base: F16 branch.

---

# 11. F18 — DOCUMENTOS/INVENTARIOS

Budget:
```text
1 documents | 1 DocumentService | 2 perms documents.view/manage
0 inventory/manual/version tables | 0 deps | migration 018
```

**T18.1 Core/versioning**  
`createFileVersion,createContentVersion,find,listCurrentForProperty,listCurrentForLease,history,privateFile`; scope `property XOR lease`; types approved; immutable rows + `supersedes_document_id`.  
CKPT→T18.2.

**T18.2 Storage/inventory**  
PNG/JPEG/WebP/PDF <=10MiB; physical cap before bounded read; MIME bytes/SHA/private random name; tamper/missing/symlink fail closed; bounded inventory JSON; no auto-charge.  
CKPT→T18.3.

**T18.3 Routes/security**  
Add exactly `documents.view/manage`; one route file; tenant `findForUser` before document; own lease docs/inventories + current property manual; no stored_name/hash/path leak.  
CKPT→T18.4.

**T18.4 Freeze**  
`1 table/1 service/2 perms/0 parallel document systems`; affected F4/F8/security focal.  
CKPT→F18_CLOSE.

PR base: F17 branch.

---

# 12. F19 — CONVIVENCIA/LEGAL

Budget:
```text
1 community_incidents | 1 CommunityService | 2 perms community.view/manage
reuse DocumentService | 0 legal/support/history tables | migration 019
```

**T19.1 Incident core**  
`create,find,listForLease,listForStaff,markReviewed,close,void`; states `open|reviewed|closed|voided`; forward transitions + locks.  
CKPT→T19.2.

**T19.2 Reuse F18**  
Add nullable `documents.community_incident_id`; exactly one scope `property|lease|incident`; types `incident_evidence|legal_notice`; `createIncidentFileVersion,listCurrentForIncident`; legal content `{source_label,source_url?,effective_on?,notice_text}`; no duplicate storage/CMS.  
CKPT→T19.3.

**T19.3 Routes/security**  
Add exactly `community.view/manage`; tenant read-only own lease; hide voided; resolution after closed; current property legal notices; ownership before lookup.  
CKPT→T19.4.

**T19.4 Freeze**  
`1 new table/1 service/2 perms/reuse F18`; affected F18/F19/lease/auth focal.  
CKPT→F19_CLOSE.

PR base: F18 branch.

---

# 13. F20 — MEMBRESÍAS/SERVICIOS

Budget:
```text
2 tables digital_services + service_memberships
1 MembershipService | 2 perms memberships.view/manage
0 provider/payment/history/renewal tables | migration 020
```

Hard rule:
```text
tenant acceptance NEVER creates F9 charge automatically
```

**T20.1 Catalog/schema**  
`MembershipService(PDO,FinanceService)`; `createService,updateService,findService,listServices`; exact 2 tables; historical membership rows immutable; service edits affect future snapshots only.  
CKPT→T20.2.

**T20.2 Acceptance/renewal**  
`accept,renew,end,findMembership,listForLease,listPendingChargeDecisions`; lock lease; reject overlapping active same-service; snapshot price/duration; renew=new row + `renews_membership_id`; concurrency.  
CKPT→T20.3.

**T20.3 Admin CREATE/SKIP**  
`decideCharge`; paid+undecided only; one-shot.  
CREATE: membership lock → amount=`price_snapshot` → canonical `FinanceService::createManualCharge(type=service)` → decision/create+charge+actor/time.  
SKIP: decision/skip+actor/time, no charge.  
Free service: no decision. Test CREATE/CREATE, CREATE/SKIP, SKIP/SKIP, rollback. No raw charge INSERT.  
CKPT→T20.4.

**T20.4 Routes/UI**  
Add exactly `memberships.view/manage`; staff catalog/history/pending with UI `Crear cargo` / `No crear cargo`; tenant `findForUser` before mutation; available services + accept/renew/history.  
CKPT→T20.5.

**T20.5 Freeze**  
`2 tables/1 service/2 perms/0 auto-charge/FinanceService only writer`; affected F20/F9/F8/auth focal.  
CKPT→F20_CLOSE.

PR base: F19 branch.

---

# 14. FINAL CERTIFICATION

Confirm F15–F20 PR CI:

```text
PHP 8.3 PASS
PHP 8.4 PASS
PHP 8.5 PASS
PR_HEAD/base/merge-ref exact
tests/assertions recorded
```

Propagated repairs, if any, must be recertified only where affected.

No merge.

---

# 15. FINAL REPORT = MISMO CHECKPOINT

No second report.

Por fase:

```text
status/branch/base
scope delivered/excluded
budget/schema/owner
Task RED/GREEN
ownership/security/concurrency
review/repairs/focused QA
Product/Closure + docs-only proof
PR/base/CI/merge-ref/counts
changed files
KISS/Zero Noise/Antipatch
real observations
```

Batch metrics:

```text
LOCAL_FULL_SUITE_RUNS
REMOTE_CI_RUNS
FRESH_REVIEWS
PRODUCTIVE_COMMITS
DOC_COMMITS
LOOP_ESCAPES
CI_REPAIRS
```

---

# 16. FINAL RESPONSE CODEX

```text
F15_F20_BATCH_RESULT: PASS | PARTIAL | BLOCKED
FINAL_REPORT: docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md
MAIN_START: <resolved exact main SHA at execution start>

F15: STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX
F16: STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX
F17: STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX
F18: STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX
F19: STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX
F20: STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX

KISS: PASS per completed phase
ZERO_NOISE: PASS per completed phase
PATCH_STACKING: NONE per completed phase

LOCAL_FULL_SUITE_RUNS: <n>
REMOTE_CI_RUNS: <n>
FRESH_REVIEWS: <n>
PRODUCTIVE_COMMITS: <n>
DOC_COMMITS: <n>
LOOP_ESCAPES: <n>
CI_REPAIRS: <n>

MERGES: NONE
BLOCKERS: NONE|details
NEXT_ACTION: CHATGPT/SOL FORENSIC AUDIT F15→F20
```

STOP. No merge. No F21. No optional cleanup.

---

# 17. AUDITORÍA 10 PASADAS

```text
1 KISS budgets                 PASS
2 ZERO_NOISE / convergence     PASS
3 ANTIPATCH / owners           PASS
4 SPEC-plan congruence         PASS
5 TDD focal coverage           PASS
6 controlled autonomy          PASS
7 anti-loop                    PASS
8 per-Task recovery checkpoint PASS
9 non-blocking CI efficiency   PASS
10 auditability/no merge/F21   PASS
```

`PROMPT_AUDIT_10_PASSES: PASS`
