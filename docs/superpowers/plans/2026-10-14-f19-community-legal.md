# F19 Community + Legal Content Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add convivencia incident workflow and versioned legal notices while reusing F18 as the only private document authority.

**Architecture:** One new `community_incidents` table and one `CommunityService`. Migration 019 also adds optional `documents.community_incident_id` so evidence is stored by the existing `DocumentService`; legal notices are property-scoped F18 documents. No separate legal/support/history tables.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO, PHPUnit, SSR.

**Spec:** `docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md` — F19 section.

## Global Constraints

- Exactly 1 new table: `community_incidents`.
- Exactly 1 new domain class: `AlquilaPro\Community\CommunityService`.
- Exactly 2 new permissions: `community.view`, `community.manage`.
- Reuse F18 `DocumentService`; 0 new support/legal-content/media tables.
- No finance writes, notifications, public routes or runtime dependencies.
- Tenant V1 is read-only for F19.

## Review Focus

1. Incident evidence document can never cross the incident lease boundary.
2. F19 document scope update preserves F18 property/lease XOR guarantees.
3. Voided incidents disappear from tenant projection but remain auditable for staff.
4. Legal notice versioning returns only current applicable property notice.
5. Tenant never receives internal actor ids or premature resolution notes.

---

### Task 1: Incident schema + lifecycle

**Files:**
- Create: `database/migrations/019_create_community_incidents.sql`
- Create: `app/Community/CommunityService.php`
- Create: `tests/Integration/Community/CommunitySchemaTest.php`
- Create: `tests/Integration/Community/CommunityServiceTest.php`

**Interfaces:**

```php
CommunityService::__construct(PDO $pdo)
CommunityService::create(int $leaseId, string $category, string $occurredOn, string $description, int $actorUserId, int $now): array
CommunityService::find(int $incidentId): ?array
CommunityService::listForLease(int $leaseId): array
CommunityService::listForStaff(?string $status = null): array
CommunityService::markReviewed(int $incidentId, int $actorUserId, int $now): array
CommunityService::close(int $incidentId, string $resolutionNotes, int $actorUserId, int $now): array
CommunityService::void(int $incidentId, string $reason, int $actorUserId, int $now): array
```

Status:

```text
open
reviewed
closed
voided
```

- [x] RED exact incident table, FKs/indexes, no support/history/legal table.
- [x] GREEN schema/lifecycle with row locks for terminal transitions.
- [x] RED category/date/description bounds and forward-only transitions.
- [x] GREEN service and derived safe projections.
- [x] Run schema/service focused tests.

Task 1 result: the initial RED found the missing table and migration 019. GREEN adds the exact incident schema and `CommunityService`; the final focused run passed 5 tests / 75 assertions. MySQL’s implicit FK support indexes are explicit in migration 019 and the schema test.

### Task 2: Converge incident evidence + legal notices into F18

**Files:**
- Modify: `database/migrations/019_create_community_incidents.sql`
- Modify: `app/Documents/DocumentService.php`
- Modify: F18 document schema/security tests.
- Create: `tests/Integration/Community/CommunityDocumentsTest.php`

Document changes:

```text
documents.community_incident_id nullable FK
scope becomes exactly one of:
  property_id
  lease_id
  community_incident_id
```

Add F18 type allowlist entries:

```text
incident_evidence
legal_notice
```

Add interface:

```php
DocumentService::createIncidentFileVersion(int $incidentId, string $title, UploadedFileInterface $file, ?int $supersedesDocumentId, int $actorUserId, int $now): array
DocumentService::listCurrentForIncident(int $incidentId): array
```

Legal notices remain existing property-scope `createContentVersion()` with validated `content_json`:

```text
source_label
source_url nullable
effective_on nullable
notice_text
```

- [ ] RED 3-way exclusive scope and no cross-scope supersedes.
- [ ] RED incident evidence private-file integrity uses F18 bounded-read contract.
- [ ] RED legal notice content/version validation.
- [ ] GREEN minimal DocumentService extension; no new storage class/table.
- [ ] Run F18 + F19 document regressions.

### Task 3: Permissions + staff/tenant surfaces

**Files:**
- Modify: `app/Auth/Permissions.php`
- Create: `app/Community/routes.php`
- Modify: `bootstrap/app.php`
- Create: `resources/views/community.php`
- Modify: `resources/views/tenant_lease_detail.php`
- Create: `tests/Integration/Community/CommunityRoutesTest.php`
- Modify: `tests/Integration/Leases/TenantPortalRoutesTest.php`

- [ ] RED `community.view/manage`, owner bypass, tenant deny for staff routes.
- [ ] Staff CRUD workflow uses PRG/CSRF and F18 document routes/service for evidence/legal content.
- [ ] Tenant own-lease projection: non-voided incidents with safe fields; resolution notes only after `closed`.
- [ ] Tenant sees current property `legal_notice` version(s) with source label/url; no internal actor metadata.
- [ ] Ownership via `LeaseService::findForUser()` before incident/document lookup.
- [ ] Run routes/permissions/F18 ownership focused tests.

### Task 4: Architecture freeze + certification

**Files:**
- Create: `tests/Integration/Community/CommunitySecurityRegressionTest.php`

- [ ] Freeze 1 new table / 1 service / 2 permissions / 0 legal/support/history tables.
- [ ] Freeze reuse of `DocumentService`; no duplicate file-read/storage logic in `CommunityService`.
- [ ] Fresh review before remote CI; repair only Critical/Important.
- [ ] `composer validate --strict`, `git diff --check`, affected F18/F19/lease/auth focused tests.
- [ ] Product Head → docs-only Closure Head.
- [ ] One push; stacked PR against F18 closure branch; one PHP 8.3/8.4/8.5 PR CI.
- [ ] Stop without merge/F20 on failure.
