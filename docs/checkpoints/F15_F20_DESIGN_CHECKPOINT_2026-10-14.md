# ALQUILAPRO — F15→F20 DESIGN CHECKPOINT

**Fecha:** 2026-10-14  
**Estado:** `DESIGN WRITTEN / F20 CONSUMER MODEL RESOLVED / ONE COMMERCIAL GATE REMAINS / NO PRODUCT IMPLEMENTATION`  

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

F15_F20_SPEC:
docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md

F15_F20_SPEC_COMMIT:
f6a9690fae1f2d0fb5deb3a836fb16f3f8d61bdc

F20_PRODUCT_DECISION:
docs/specs/F20_PRODUCT_DECISION_2026-10-14.md

F20_PRODUCT_DECISION_COMMIT:
95a57885baa3276570b97f9f642b2b16fcdea795
```

## Design status

```text
F15 Agenda + Visits:
SPEC WRITTEN / READY FOR USER REVIEW
ARCHITECTURE: 1 table maintenance_visits + 1 VisitService
PERMISSIONS: reuse maintenance.view/manage
F16 SURFACE: NONE

F16 Reminders + Cron + Email:
SPEC WRITTEN / READY FOR USER REVIEW
ARCHITECTURE: 1 notification_jobs + 1 NotificationService
DEPENDENCY: PHPMailer allowed
WORKERS/REDIS: 0

F17 Assisted WhatsApp:
SPEC WRITTEN / READY FOR USER REVIEW
ARCHITECTURE: 1 whatsapp_actions + 1 WhatsAppService
PERMISSION: whatsapp.use
PROVIDER/API/WEBHOOK: 0
FAKE DELIVERY/READ STATE: 0

F18 Documents + Manual + Inventories:
SPEC WRITTEN / READY FOR USER REVIEW
ARCHITECTURE: 1 documents + 1 DocumentService
PERMISSIONS: documents.view/manage
PARALLEL INVENTORY/MANUAL TABLES: 0

F19 Community + Legal Content:
SPEC WRITTEN / READY FOR USER REVIEW
ARCHITECTURE: 1 community_incidents + 1 CommunityService
REUSE: F18 documents
LEGAL CMS TABLE: 0

F20 Memberships + Digital Services:
CONSUMER MODEL RESOLVED
MODEL: A = tenant/lease add-on service
PRE-KISS ARCHITECTURE: 2 tables digital_services + service_memberships; 1 MembershipService
FULL SPEC: BLOCKED ON ONE COMMERCIAL DECISION
```

## F20 confirmed product decision

```text
F20_CONSUMER_MODEL:
A = tenant/lease add-on service
```

Meaning:
- services are optional/additional offerings accepted by a tenant for a specific lease;
- not owner/admin SaaS billing;
- not property-level subscription;
- historical acceptance remains lease-scoped.

## F20 remaining decision

```text
F20_CREATE_F9_CHARGE_ON_ACCEPTANCE:
YES | NO
```

Until resolved:

```text
F20_FULL_SPEC: BLOCKED_ON_ONE_DECISION
F20_PLAN: BLOCKED
PRODUCT_IMPLEMENTATION: NOT AUTHORIZED
```

## Laws / audit

```text
KISS: PASS F15-F19 / F20 consumer model PASS, finance behavior intentionally gated
ZERO_NOISE: PASS
ANTIPATCH: PASS
PHASE_BOUNDARIES: PASS
FINANCE_AUTHORITY: PASS
DOCUMENT_AUTHORITY: PASS
NOTIFICATION_AUTHORITY: PASS
SECURITY_BOUNDARIES: PASS
PROMPT/DESIGN_REVIEW_PASSES: 10 PASS
```

## Next exact action

```text
1. USER decides whether F20 acceptance creates an F9 charge: YES or NO.
2. ChatGPT/Sol freezes F20 full SPEC.
3. ChatGPT/Sol writes implementation PLANs F15-F20.
4. Build ONE ultra-lean Codex master F15→F20 with recoverable checkpointing.
5. Codex implements stacked phases; no merge.
6. ChatGPT/Sol forensic audit + sequential merge gates.
```

## Recovery rule

If this chat ends, new thread should read in this order:

```text
1 docs/checkpoints/F14_MERGED_2026-10-14.md
2 docs/checkpoints/F15_F20_DESIGN_CHECKPOINT_2026-10-14.md
3 docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md
4 docs/specs/F20_PRODUCT_DECISION_2026-10-14.md
5 master SPEC + master roadmap
6 current main / GitHub truth
```

Do not reimplement F12-F14. Do not write F15-F20 product until written specs/plans are approved.