# F18 Documents + Manual + Inventories Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create the single generic private document authority for property/lease documents, convivencia manuals and initial/final inventories.

**Architecture:** One immutable/versioned `documents` table and one `DocumentService`. File bytes stay outside webroot; inventory/manual content can be JSON-only. One route file serves staff and tenant-safe document projections, using existing lease ownership.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO, PSR-7 uploads, PHPUnit, SSR.

**Spec:** `docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md` — F18 section.

## Global Constraints

- Exactly 1 new table: `documents`.
- Exactly 1 new domain class: `AlquilaPro\Documents\DocumentService`.
- Exactly 2 permissions: `documents.view`, `documents.manage`.
- 0 document-type/inventory/version tables, runtime dependencies or public routes.
- Rows are immutable versions; no destructive update/delete.
- Do not migrate legacy module-specific evidence during F18.

## Review Focus

1. Exactly one scope is set; tenant can never cross lease/property ownership.
2. Supersedes lineage cannot cross scope/type or create self/cycles.
3. Private-file read rejects oversize/tampered/symlinked storage before unbounded read.
4. Inventory JSON is bounded and cannot silently become a second relational inventory system.
5. Current-version query returns only leaves of valid version chains.

---

### Task 1: Schema + immutable document core

**Files:**
- Create: `database/migrations/018_create_documents.sql`
- Create: `app/Documents/DocumentService.php`
- Create: `tests/Integration/Documents/DocumentSchemaTest.php`
- Create: `tests/Integration/Documents/DocumentServiceTest.php`

**Interfaces:**

```php
DocumentService::__construct(PDO $pdo, string $documentDirectory)
DocumentService::createFileVersion(?int $propertyId, ?int $leaseId, string $type, string $title, UploadedFileInterface $file, ?int $supersedesDocumentId, int $actorUserId, int $now): array
DocumentService::createContentVersion(?int $propertyId, ?int $leaseId, string $type, string $title, array $content, ?int $supersedesDocumentId, int $actorUserId, int $now): array
DocumentService::find(int $documentId): ?array
DocumentService::listCurrentForProperty(int $propertyId): array
DocumentService::listCurrentForLease(int $leaseId): array
DocumentService::history(int $documentId): array
DocumentService::privateFile(int $documentId): ?array
```

Initial types:

```text
property_document
lease_document
manual_convivencia
inventory_initial
inventory_final
other
```

- [ ] RED schema: property XOR lease, self-FK supersedes, minimal indexes, no status/history/type/item tables.
- [ ] GREEN migration.
- [ ] RED type/scope/title/version lineage validation.
- [ ] GREEN immutable rows and current-leaf queries.
- [ ] Run schema/core focused tests.

### Task 2: Private files + inventory snapshots

**Files:**
- Modify: `app/Documents/DocumentService.php`
- Modify: `tests/Integration/Documents/DocumentServiceTest.php`
- Create: `tests/Integration/Documents/DocumentStorageSecurityTest.php`

File contract:

```text
PNG/JPEG/WebP/PDF
<=10 MiB
MIME by bytes
random stored name
SHA256 + exact size
outside webroot
```

Inventory `content_json`:

```text
{
  general_notes: string|null,
  items: [{area,item,condition,quantity|null,notes|null}]
}
```

- [ ] RED bounded upload/read, missing/tampered/oversize/symlink fail closed.
- [ ] GREEN physical size cap before bounded read, then MIME/hash verification.
- [ ] RED inventory validation: bounded item count/text lengths, quantity nonnegative/null, no unknown top-level shape.
- [ ] GREEN normalized JSON snapshot; no automatic F14/F9 charge.
- [ ] Run private storage/inventory focused tests.

### Task 3: Permissions + staff/tenant surfaces

**Files:**
- Modify: `app/Auth/Permissions.php`
- Create: `app/Documents/routes.php`
- Modify: `bootstrap/app.php`
- Create: `resources/views/documents.php`
- Modify: `resources/views/tenant_lease_detail.php`
- Create: `tests/Integration/Documents/DocumentRoutesTest.php`
- Modify: `tests/Integration/Leases/TenantPortalRoutesTest.php`

- [ ] RED permissions owner/admin/tenant behavior.
- [ ] Staff can create new versions and view history/current docs.
- [ ] Tenant lease page exposes only safe current lease docs/inventories plus current property `manual_convivencia` applicable to that lease.
- [ ] Tenant private download proves `LeaseService::findForUser()` before document lookup/delivery.
- [ ] No stored_name/hash/path leakage in HTML.
- [ ] Wire `document_directory` + one service in bootstrap.
- [ ] Run routes/ownership/auth/basePath focused tests.

### Task 4: Architecture freeze + certification

**Files:**
- Create: `tests/Integration/Documents/DocumentSecurityRegressionTest.php`

- [ ] Freeze 1 table / 1 service / 2 permissions / 0 parallel inventory/manual/version tables.
- [ ] Freeze no legacy-evidence migration and no financial writes.
- [ ] Fresh review before remote CI; repair only Critical/Important.
- [ ] `composer validate --strict`, `git diff --check`, F18 + affected F4/F8/security focused tests.
- [ ] Product Head → docs-only Closure Head.
- [ ] One push; stacked PR against F17 closure branch; one PHP 8.3/8.4/8.5 PR CI.
- [ ] Stop without merge/F19 on failure.
