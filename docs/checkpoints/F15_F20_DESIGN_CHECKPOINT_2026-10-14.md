# ALQUILAPRO — F15→F20 EXECUTION-READY CHECKPOINT

**Fecha:** 2026-10-14  
**Estado:** `SPECS APPROVED / PLANS COMPLETE / CODEX MASTER READY / NO PRODUCT IMPLEMENTATION STARTED`  

```text
PROJECT: AlquilaPro
REPO: sierraglobalcompany-rgb/AlquilaPro

F12: CLOSED / VERIFIED / MERGED
F13: CLOSED / VERIFIED / MERGED
F14: CLOSED / VERIFIED / MERGED

F12_MERGE_SHA:
23cf7576c95fca5e55825d8b5ffd5903bf3d3149

F13_MERGE_SHA:
57911302bd4e97e76b1e0fa6dc71c8ef24372453

F14_MERGE_SHA:
c58ee5e660f51e873546f03a578fde6b5778ba19

F14_MERGED_CHECKPOINT_COMMIT:
87eea6b69796484462cc47391d8d60fdd09f8344
```

## Canonical specs

```text
BATCH_SPEC:
docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md
initial commit f6a9690fae1f2d0fb5deb3a836fb16f3f8d61bdc

F20_PRODUCT_DECISION:
docs/specs/F20_PRODUCT_DECISION_2026-10-14.md
final decision commit 1739e376eba5a8e2c740bfb253335e41bb87fc01

F20_FULL_SPEC:
docs/specs/F20_MEMBERSHIPS_SERVICES_SPEC.md
commit 54a506675d4e582c804460d3ba20651dc2d4b841
```

## Approved implementation plans

```text
F15:
docs/superpowers/plans/2026-10-14-f15-agenda-visits.md
commit 2e83966dae2477de2864557c7e070585824411e7

F16:
docs/superpowers/plans/2026-10-14-f16-reminders-email.md
commit 9879cb0b4ad907e26251c783e3b54764bdddd682

F17:
docs/superpowers/plans/2026-10-14-f17-whatsapp-assisted.md
commit b1d1518001142009107afb1cdd383ab42ceba63e

F18:
docs/superpowers/plans/2026-10-14-f18-documents-inventories.md
commit 301cb1bfafeb86b233e0fb0747b6aeea694908c6

F19:
docs/superpowers/plans/2026-10-14-f19-community-legal.md
commit 1aab820ee4df09e6cff0f5a535f9efe3f967be36

F20:
docs/superpowers/plans/2026-10-14-f20-memberships-services.md
commit 0a2e8f9de96612a30861bec0031e69f5e9432d13
```

## Codex execution master

```text
docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
commit 281845dac131b55fd7afa77aae09b898069ccf40
```

The master is intentionally DRY: detailed interfaces/tests live in the six phase plans; the master owns stacking, checkpoints, anti-loops, efficiency, CI and stop conditions.

## Frozen architecture budgets

```text
F15:
1 table maintenance_visits
1 VisitService
0 new permissions
0 runtime deps

F16:
1 table notification_jobs
1 NotificationService
0 new permissions
1 runtime dep: phpmailer/phpmailer ^7.1
0 workers/Redis/rule/template tables

F17:
1 table whatsapp_actions
1 WhatsAppService
1 permission whatsapp.use
reuse Notifications route file
0 API/provider/webhook

F18:
1 table documents
1 DocumentService
2 permissions documents.view/manage
0 inventory/manual/version tables

F19:
1 new table community_incidents
1 CommunityService
2 permissions community.view/manage
reuse F18 documents
0 legal/support/history tables

F20:
2 tables digital_services + service_memberships
1 MembershipService
2 permissions memberships.view/manage
0 payment/provider/history tables
```

## F20 final commercial rule

```text
tenant accepts service
→ membership is persisted immediately

if price_snapshot == 0:
→ no charge decision required

if price_snapshot > 0:
→ admin module shows exactly:
   CREATE / Crear cargo
   SKIP / No crear cargo

CREATE:
→ explicit transaction
→ canonical FinanceService::createManualCharge(type='service')
→ exactly one linked charge

SKIP:
→ terminal no-charge decision for that membership
→ no linked charge
```

Tenant acceptance NEVER creates a financial charge automatically.

## Laws / self-review

```text
KISS: PASS F15-F20
ZERO_NOISE: PASS
ANTIPATCH: PASS
PHASE_BOUNDARIES: PASS
FINANCE_AUTHORITY: PASS
DOCUMENT_AUTHORITY: PASS
NOTIFICATION_AUTHORITY: PASS
TENANT_OWNERSHIP: PASS
PLAN_INTERFACE_CONSISTENCY: PASS
PLAN_SPEC_COVERAGE: PASS
MASTER_10_PASS_AUDIT: PASS
```

## Execution mechanics

```text
STACK:
main
→ F15
→ F16
→ F17
→ F18
→ F19
→ F20

CHECKPOINT DURING CODEX:
docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md

TARGET:
LOCAL_FULL_SUITE_RUNS: 0
REMOTE_CI_RUNS: 1 per phase
FRESH_REVIEWS: 1 per phase
PRODUCTIVE_COMMITS: <=3 per phase
DOC_COMMITS: 1 per phase
LOOP_ESCAPES: 0
MERGES: NONE
```

## Current exact state

```text
F15_PRODUCT_STARTED: NO
F16_PRODUCT_STARTED: NO
F17_PRODUCT_STARTED: NO
F18_PRODUCT_STARTED: NO
F19_PRODUCT_STARTED: NO
F20_PRODUCT_STARTED: NO

OPEN_DESIGN_BLOCKERS: NONE
OPEN_PRODUCT_BLOCKERS: NONE

NEXT_EXACT_ACTION:
USER/CODEX executes docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
```

## Recovery rule

If this chat ends, a new thread should read, in order:

```text
1. docs/checkpoints/F14_MERGED_2026-10-14.md
2. docs/checkpoints/F15_F20_DESIGN_CHECKPOINT_2026-10-14.md
3. docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
4. current phase plan if implementation has started
5. docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md if it exists
6. current main / PR / CI GitHub truth
```

Do not reimplement F12-F14. Do not merge F15-F20 from Codex. After Codex closes the batch, ChatGPT/Sol performs forensic audit and sequential merge gates.