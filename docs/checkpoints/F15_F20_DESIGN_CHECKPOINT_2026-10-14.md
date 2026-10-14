# ALQUILAPRO — F15→F20 EXECUTION-READY CHECKPOINT

**Fecha:** 2026-10-14  
**Estado:** `SPECS APPROVED / PLANS COMPLETE / CODEX MASTER V4 READY / NO PRODUCT IMPLEMENTATION STARTED`

```text
PROJECT: AlquilaPro
REPO: sierraglobalcompany-rgb/AlquilaPro

F12: CLOSED / VERIFIED / MERGED
F13: CLOSED / VERIFIED / MERGED
F14: CLOSED / VERIFIED / MERGED

F12_MERGE_SHA: 23cf7576c95fca5e55825d8b5ffd5903bf3d3149
F13_MERGE_SHA: 57911302bd4e97e76b1e0fa6dc71c8ef24372453
F14_MERGE_SHA: c58ee5e660f51e873546f03a578fde6b5778ba19
F14_MERGED_CHECKPOINT_COMMIT: 87eea6b69796484462cc47391d8d60fdd09f8344
```

## Canonical design

```text
BATCH_SPEC:
docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md

F20_PRODUCT_DECISION:
docs/specs/F20_PRODUCT_DECISION_2026-10-14.md
final decision commit 1739e376eba5a8e2c740bfb253335e41bb87fc01

F20_FULL_SPEC:
docs/specs/F20_MEMBERSHIPS_SERVICES_SPEC.md
commit 54a506675d4e582c804460d3ba20651dc2d4b841
```

## Approved plans

```text
F15 docs/superpowers/plans/2026-10-14-f15-agenda-visits.md
F16 docs/superpowers/plans/2026-10-14-f16-reminders-email.md
F17 docs/superpowers/plans/2026-10-14-f17-whatsapp-assisted.md
F18 docs/superpowers/plans/2026-10-14-f18-documents-inventories.md
F19 docs/superpowers/plans/2026-10-14-f19-community-legal.md
F20 docs/superpowers/plans/2026-10-14-f20-memberships-services.md
```

## Canonical Codex master

```text
PATH:
docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md

VERSION:
V4 FINAL LEAN-FAST / AUDITED 10 PASSES

MASTER_COMMIT:
7348058fc5854c50537989dc04619af313d261fa
```

V4 is the only execution contract. Previous exported prompt versions are superseded.

## Frozen budgets

```text
F15: 1 maintenance_visits + 1 VisitService + 0 new permissions/deps
F16: 1 notification_jobs + 1 NotificationService + PHPMailer ^7.1 only
F17: 1 whatsapp_actions + 1 WhatsAppService + whatsapp.use
F18: 1 documents + 1 DocumentService + documents.view/manage
F19: 1 community_incidents + 1 CommunityService + community.view/manage + reuse F18
F20: 2 tables digital_services/service_memberships + 1 MembershipService + memberships.view/manage
```

## F20 final rule

```text
tenant accepts service
→ membership persists
→ NEVER automatic F9 charge

if price_snapshot == 0:
→ no admin financial decision

if price_snapshot > 0:
→ admin chooses exactly:
   CREATE / Crear cargo
   SKIP / No crear cargo

CREATE:
→ canonical FinanceService::createManualCharge(type='service')
→ exactly one linked charge

SKIP:
→ terminal no-charge decision
→ no linked charge
```

## V4 execution mechanics

```text
STACK:
main → F15 → F16 → F17 → F18 → F19 → F20

LIVE CHECKPOINT:
docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md

CHECKPOINT UPDATE:
after every Task, before continuing

RECOVERY:
read live checkpoint → continue NEXT_EXACT_ACTION → never repeat PASS

CI:
launch one PR CI per phase; do NOT wait before starting next stacked phase
final batch gate verifies all six matrices

TARGETS:
LOCAL_FULL_SUITE_RUNS: 0
REMOTE_CI_RUNS: 1 per phase normally
FRESH_REVIEWS: 1 per phase
PRODUCTIVE_COMMITS: <=2 per phase
DOC_COMMITS: 1 per phase
LOOP_ESCAPES: 0
MERGES: NONE
```

If an upstream CI fails after downstream work began:

```text
stop at next Task checkpoint
→ repair owning phase once
→ recertify owner
→ propagate by clean merge
→ recertify only affected descendants
```

## Laws / audit

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
CODEX executes docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
```

## Start rule

The V4 master was written on top of docs-only `PREPACKAGE_BASE`:

```text
37350105ac8c1806ae69194082098dbe230e5838
```

Before F15 product work Codex must resolve current `main` with:

```bash
git fetch origin
git switch main
git pull --ff-only
git rev-parse HEAD
```

That exact SHA becomes `MAIN_START`; it must contain this V4/checkpoint and be a docs-only descendant of the prepackage base.

## Recovery rule if this chat ends

```text
1 docs/checkpoints/F14_MERGED_2026-10-14.md
2 docs/checkpoints/F15_F20_DESIGN_CHECKPOINT_2026-10-14.md
3 docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
4 current phase plan only if needed
5 docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md if Codex started
6 current GitHub main / PR / CI truth
```

Do not reimplement F12-F14. Codex must not merge F15-F20. After batch close, ChatGPT/Sol performs forensic audit and sequential merge gates.
