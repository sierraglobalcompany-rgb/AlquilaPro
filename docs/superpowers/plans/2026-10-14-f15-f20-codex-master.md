# ALQUILAPRO — CODEX MASTER F15 → F20
## FAST-KISS · stacked phases · recoverable checkpoint · no merges

> **Execution mode:** Use `superpowers:executing-plans` inline. The six phase plans below are authoritative for interfaces/tests; this file controls sequencing, efficiency, checkpoints, CI and hard stops.

**Repo:** `sierraglobalcompany-rgb/AlquilaPro`  
**Start rule:** F15 starts from the current `main` containing this master, the F15→F20 design checkpoint and all six plans. Resolve and record the exact SHA before product work.  
**Merge:** NOT AUTHORIZED.  
**Stop after:** F20 verified; do not start F21.

## 1. Authorities

Read once at batch start:

```text
docs/checkpoints/F14_MERGED_2026-10-14.md
docs/checkpoints/F15_F20_DESIGN_CHECKPOINT_2026-10-14.md
docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md
docs/specs/F20_PRODUCT_DECISION_2026-10-14.md
docs/specs/F20_MEMBERSHIPS_SERVICES_SPEC.md
```

Read the phase plan only when entering that phase:

```text
F15 docs/superpowers/plans/2026-10-14-f15-agenda-visits.md
F16 docs/superpowers/plans/2026-10-14-f16-reminders-email.md
F17 docs/superpowers/plans/2026-10-14-f17-whatsapp-assisted.md
F18 docs/superpowers/plans/2026-10-14-f18-documents-inventories.md
F19 docs/superpowers/plans/2026-10-14-f19-community-legal.md
F20 docs/superpowers/plans/2026-10-14-f20-memberships-services.md
```

Do not re-audit F1–F14.

## 2. Laws

```text
KISS:
minimum correct tables/classes/routes/states/deps

ZERO NOISE:
ELIMINAR → CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR

ANTIPATCH:
REPRODUCE → ROOT CAUSE → OWNER → RED → MINIMAL GREEN
→ REMOVE SUPERSEDED LOGIC → REGRESSION
```

Forbidden shortcuts:
- compatibility fallbacks;
- optional dependency injection only to preserve old tests;
- duplicate SQL writers;
- duplicate document/notification/finance authority;
- no-op commits/pushes;
- future F21+ scaffolding.

## 3. Stacked branches

```text
main
 ↓
impl/f15-agenda-visits-20261014
 ↓
impl/f16-reminders-email-20261014
 ↓
impl/f17-whatsapp-assisted-20261014
 ↓
impl/f18-documents-inventories-20261014
 ↓
impl/f19-community-legal-20261014
 ↓
impl/f20-memberships-services-20261014
```

Each next branch starts from the exact verified closure head of the prior phase.

Do not merge, squash or destructive-rebase.

## 4. Single recoverable checkpoint

Create on F15 and carry forward:

```text
docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md
```

Update only after:
- phase Core gate;
- phase Integration gate;
- phase final CI.

Minimum record:

```text
PHASE
BRANCH
BASE
MACRO
CURRENT_HEAD
DONE
RED_EVIDENCE
GREEN_EVIDENCE
FOCUSED_QA
REVIEW_FINDINGS
RULINGS
PRODUCT_HEAD
CLOSURE_HEAD
PR
PR_BASE
CI_RUN
CI_MERGE_REF
NEXT_EXACT_ACTION
```

If context is lost:

```text
git branch --show-current
git rev-parse HEAD
read checkpoint
read only current phase plan
continue NEXT_EXACT_ACTION
```

Never repeat a gate already marked PASS.

## 5. Fast execution protocol

At phase start:

```bash
php -v
php -m
composer validate --strict
git status --short
```

Confirm DB test access and writable required storage.

TDD per behavior:

```text
RED → VERIFY RED → MINIMAL GREEN → VERIFY GREEN
```

No full local suite per task.

Phase order:

```text
CORE focused GREEN
→ INTEGRATION focused GREEN
→ fresh whole-phase review
→ one repair pass if needed
→ affected focused rerun
→ composer validate --strict
→ git diff --check
→ PRODUCT_HEAD
→ docs-only CLOSURE_HEAD
→ one push
→ one PR CI PHP 8.3/8.4/8.5
→ phase gate
```

Target:

```text
LOCAL_FULL_SUITE_RUNS: 0
REMOTE_CI_RUNS: 1 per phase
FRESH_REVIEWS: 1 per phase
PRODUCTIVE_COMMITS: <=3 per phase
DOC_COMMITS: 1 per phase
LOOP_ESCAPES: 0
```

Remote PR matrix is the complete certification. Run local full suite only for a proven reason and record why.

## 6. Anti-loop

Never rerun:

```text
same test + same code + same environment
```

Failure protocol:
1. read complete failure;
2. reproduce focal;
3. root cause;
4. one hypothesis;
5. one change;
6. rerun focal.

Maximum 3 reasoned hypotheses. Then reevaluate architecture once; if budget must expand, STOP with blocker.

## 7. Phase budgets

### F15

```text
1 table maintenance_visits
1 VisitService
0 new permissions
0 runtime deps
0 notification/provider tables
migration 015
```

### F16

```text
1 table notification_jobs
1 NotificationService
0 new permissions
1 runtime dep only: phpmailer/phpmailer ^7.1
0 workers/Redis/rule/template tables
migration 016
```

### F17

```text
1 table whatsapp_actions
1 WhatsAppService
reuse app/Notifications/routes.php
1 permission whatsapp.use
0 provider/API/webhook
migration 017
```

### F18

```text
1 table documents
1 DocumentService
2 permissions documents.view/manage
0 inventory/manual/version tables
migration 018
```

### F19

```text
1 new table community_incidents
1 CommunityService
2 permissions community.view/manage
reuse/extend F18 documents for evidence/legal notices
0 legal/support/history tables
migration 019
```

### F20

```text
2 tables digital_services + service_memberships
1 MembershipService
2 permissions memberships.view/manage
0 provider/payment/history tables
migration 020
```

F20 hard business rule:

```text
tenant accepts paid service
→ membership persists
→ NO automatic charge
→ admin must choose CREATE or SKIP

CREATE → FinanceService::createManualCharge(... type='service' ...)
SKIP   → terminal no-charge decision for that membership
```

## 8. Core / Integration / Certification mapping

For each phase, group the detailed plan tasks as:

```text
CORE:
schema + domain authority + lifecycle/storage/concurrency foundations

INTEGRATION:
routes + permissions + tenant/staff UI + cross-domain canonical service reuse

CERTIFICATION:
architecture/security regression + fresh review + focused QA + docs-only closure + PR CI
```

Do not invent extra macro-gates.

## 9. CI semantics

Workflow `pull_request` normally tests GitHub synthetic merge-ref.

Record per phase:

```text
PR_HEAD
PR_BASE
FINAL_PR_CI_RUN
FINAL_PR_CI_MERGE_SHA
PHP_8_3 result + tests/assertions
PHP_8_4 result + tests/assertions
PHP_8_5 result + tests/assertions
```

Do not claim merge-ref SHA equals PR head.

No separate Product CI if Product→Closure is docs-only.

## 10. PR stacking

```text
F15 PR base: main
F16 PR base: impl/f15-agenda-visits-20261014
F17 PR base: impl/f16-reminders-email-20261014
F18 PR base: impl/f17-whatsapp-assisted-20261014
F19 PR base: impl/f18-documents-inventories-20261014
F20 PR base: impl/f19-community-legal-20261014
```

PR titles:

```text
F15: agenda and visits
F16: reminders and email
F17: assisted WhatsApp
F18: documents and inventories
F19: community and legal content
F20: memberships and digital services
```

Every PR body contains:

```text
STACKED BATCH F15-F20
MERGE NOT AUTHORIZED
```

## 11. Phase gate

Continue to next phase only when:

```text
STATUS: CLOSED / VERIFIED STACKED / NOT MERGED
KISS: PASS
ZERO_NOISE: PASS
PATCH_STACKING: NONE
FOCUSED_QA: PASS
FRESH_REVIEW: PASS
PHP_8_3: PASS
PHP_8_4: PASS
PHP_8_5: PASS
BLOCKERS: NONE
```

If a phase blocks, preserve prior closed phases and STOP the rest of batch.

## 12. Hard blockers

Stop only if correct implementation requires:
- destructive Git/data loss;
- architecture budget expansion;
- duplicate finance/document/notification authority;
- auth/tenant ownership model change;
- unapproved external provider/API;
- irreversible security-sensitive action;
- CI impossible;
- Critical finding remains after one repair pass.

Expected RED, fixture/path/env/extension/test typo are not blockers.

## 13. Final report

The single checkpoint becomes the final detailed report. It must contain for each phase:

```text
scope delivered / excluded
architecture budget
schema/migration
domain interfaces
ownership/permissions
storage/security
concurrency
TDD evidence
focused QA
review + repairs
Product Head
Closure Head
Product→Closure docs-only proof
PR/base
CI run + merge-ref + matrix counts
changed-file inventory
KISS / Zero Noise / Antipatch
real remaining observations
```

Batch metrics:

```text
LOCAL_FULL_SUITE_RUNS
REMOTE_CI_RUNS
FRESH_REVIEWS
PRODUCTIVE_COMMITS
DOC_COMMITS
LOOP_ESCAPES
```

## 14. Final Codex response

```text
F15_F20_BATCH_RESULT: PASS | PARTIAL | BLOCKED
FINAL_REPORT: docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md
MAIN_START: <exact resolved SHA>

F15_STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX
F16_STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX
F17_STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX
F18_STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX
F19_STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX
F20_STATUS / PRODUCT_HEAD / CLOSURE_HEAD / PR / CI / MERGE_REF / PHP_MATRIX

KISS: PASS per completed phase
ZERO_NOISE: PASS per completed phase
PATCH_STACKING: NONE per completed phase
LOCAL_FULL_SUITE_RUNS: <n>
REMOTE_CI_RUNS: <n>
FRESH_REVIEWS: <n>
PRODUCTIVE_COMMITS: <n>
DOC_COMMITS: <n>
LOOP_ESCAPES: <n>
MERGES: NONE
BLOCKERS: NONE|details
NEXT_ACTION: CHATGPT/SOL FORENSIC AUDIT F15 → F20
```

Then STOP. No merge. No F21.

## 15. 10-pass execution audit

```text
1 KISS budgets: PASS
2 Zero Noise / convergence: PASS
3 Antipatch owners: PASS
4 Phase boundaries: PASS
5 TDD/focused gates: PASS
6 Efficiency / one CI per phase: PASS
7 Controlled autonomy: PASS
8 Anti-loop / recovery checkpoint: PASS
9 Security / ownership / merge-ref evidence: PASS
10 Auditability / no merge / no F21: PASS
```
