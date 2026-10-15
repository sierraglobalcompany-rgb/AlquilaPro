# F22 — Security, Privacy & Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: use `superpowers:executing-plans` task-by-task. TDD is mandatory; every product task begins RED and closes GREEN before the next task.

**Goal:** Close AlquilaPro V1 security/privacy hardening with one append-only audit log, explicit high-value audit events, 90-day terminal notification-recipient redaction, centralized safe response headers, and evidence-driven regression hardening without adding a second auth/security/logging architecture.

**Architecture:** `AuditService` is the only new F22 domain class and the only authority for `audit_log`. High-value mutation routes call it explicitly only after the existing domain mutation succeeds, using the canonical entity id and validated `REMOTE_ADDR`; existing domain services remain owners of business state. `NotificationService` remains owner of notification-recipient retention and reuses the existing protected F16 cron. Common security headers live in bootstrap middleware, not route-by-route.

**Tech Stack:** PHP >=8.3 <8.6, Slim 4, PDO/MySQL, PHPUnit 11.5, existing SSR/view/session/CSRF stack.

**Spec:** `docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md` sections F22 / 16–30.

## Global Constraints

- Canonical base: `main@7c0f0c75c665b9888d26bfdbfd5be9bec4df3b82` (F21 merged).
- `NEW_TABLES = 1`: only `audit_log`.
- `NEW_DOMAIN_CLASSES = 1`: only `AuditService`.
- `NEW_PERMISSIONS = 0`; audit viewer is owner-only in V1.
- `NEW_PUBLIC_ROUTES = 0`.
- `NEW_RUNTIME_DEPENDENCIES = 0`.
- `NEW_EVENT_BUS = 0`; `NEW_SECURITY_FRAMEWORK = 0`; `NEW_AUTH_STACK = 0`; `NEW_SCHEDULER = 0`.
- Expected migration: `022_*`; migration may also make only `notification_jobs.recipient_email` and `recipient_name` nullable for the frozen retention rule.
- Audit log is append-only. No functional update/delete API.
- Audit metadata never contains request dumps, cookies, sessions, tokens, passwords, document/payment bodies, SQL, exception traces, SMTP/cron secrets, or equivalent payloads.
- Remote IP comes only from validated `REMOTE_ADDR`; ignore raw forwarding headers.
- Tenant child lookups happen only after `LeaseService::findForUser()`.
- F16 recipient retention is 90 days after terminal `sent_at`/`failed_at`, using the existing F16 cron.
- Security fixes require a reproducing RED; no speculative refactor.
- No F23 implementation until F22 closes.

## PRE-KISS Decisions

1. **Audit integration point:** route-level, immediately after successful domain mutation. This is the smallest place that already has actor, canonical entity id and HTTP `REMOTE_ADDR`; do not inject `AuditService` into Finance/Lease/Documents/Membership/etc. constructors.
2. **Audit failure behavior:** `AuditService::record()` is explicit and may throw; do not silently swallow audit-write failures. No hidden fallback/file logger.
3. **Audit read API:** same class may expose `listRecent(int $limit = 100): array` solely for the owner-only viewer; no repository class/table gateway.
4. **AuditService transaction rule:** `record()` never begins/commits/rolls back a business transaction. F22 does not retrofit cross-domain atomicity through an event bus.
5. **Metadata shape:** associative machine-key map; scalar/null values or small scalar lists only; no nested object graph. Service enforces bounded strings/lists and total encoded JSON <= 2048 bytes. Secret-shaped keys/unsafe values fail closed rather than being silently stored.
6. **Security headers:** one common response middleware sets `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, and `X-Frame-Options: SAMEORIGIN`. Existing route-specific `Cache-Control: private, no-store` remains authoritative for sensitive responses. Do not add CSP nonce infrastructure. Do not force HSTS merely for aesthetics.
7. **Notification retention:** `NotificationService::redactTerminalRecipients(string $now): int` owns the operation, uses a fixed internal 90-day retention constant, nulls only recipient name/email on terminal rows older than cutoff, and leaves operational fields intact.
8. **High-value event mapping:** routes record only the frozen baseline events. Do not audit every GET, download, cron heartbeat, failed public form, or ordinary CRUD not listed by the SPEC.

## Review Focus

1. Audit metadata containing secret-shaped keys/oversize JSON must fail closed and leave no row.
2. Owner-only audit viewer must reject non-owner users without creating a new permission catalog entry.
3. Notification recipients newer than/equal to the 90-day threshold must not be prematurely redacted; older terminal rows must be redacted without breaking history/processing.
4. Tenant child lookups must remain ownership-first across finance/payments/obligations/maintenance/documents/community/memberships/liquidation.
5. Common security middleware must not weaken the exact protected-cron CSRF exception, private response cache controls, installer flow, or local HTTP development.

---

### Task 1: Migration 022 + canonical AuditService

**Files:**
- Create: `database/migrations/022_create_audit_log.sql`
- Create: `app/Audit/AuditService.php`
- Create: `tests/Integration/Audit/AuditSchemaTest.php`
- Create: `tests/Integration/Audit/AuditServiceTest.php`
- Modify: `tests/Integration/Foundation/MigrationRunnerTest.php`

**Interfaces:**
- Produces `AuditService::__construct(PDO $pdo)`.
- Produces `record(?int $actorUserId, string $action, string $entityType, ?int $entityId, string $summary, ?string $remoteIp, array $metadata = []): void`.
- Produces `listRecent(int $limit = 100): array` for the owner-only viewer in Task 2.

- [ ] **Step 1 — RED schema:** assert migration 022 creates exactly one new table `audit_log` with the frozen columns/FK and no status/history/child metadata table; assert `notification_jobs.recipient_email` and `recipient_name` become nullable and no other F16 columns are relaxed.
- [ ] **Step 2 — Verify RED:** CI must fail because migration 022 and `AuditService` are absent, not from fixture/syntax errors.
- [ ] **Step 3 — Minimal migration GREEN:** create `audit_log`; alter only the two terminal recipient snapshot columns to NULL-compatible.
- [ ] **Step 4 — RED service contract:** test valid append, immutable surface, machine-key validation, summary 1..255, optional entity id, valid/invalid IP behavior, safe metadata, secret-shaped/oversize metadata rejection, deterministic recent ordering and bounded list limit.
- [ ] **Step 5 — Minimal `AuditService` GREEN:** one class, direct PDO, no domain calls, no transaction ownership, no update/delete methods.
- [ ] **Step 6 — GREEN matrix:** certify PHP 8.3/8.4/8.5 before Task 2.

### Task 2: Owner-only viewer + centralized response headers

**Files:**
- Create: `app/Audit/routes.php`
- Create: `resources/views/audit_log.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Integration/Audit/AuditRoutesTest.php`
- Create/modify focused security-header regression test under existing Foundation/Auth security test owner.

**Interfaces:**
- Authenticated route: `GET /admin/audit`.
- Owner role only; no new permission string.
- Bootstrap wires one `AuditService` and passes it through shared dependencies.

- [ ] **Step 1 — RED viewer:** anonymous -> login redirect; forced-password-change -> password route; non-owner -> 403; owner -> rows rendered from `listRecent()`; no raw metadata payload beyond safe rendered fields.
- [ ] **Step 2 — RED common headers:** normal public/authenticated responses receive `nosniff`, strict referrer policy and SAMEORIGIN; existing private-file `private, no-store` behavior remains unchanged.
- [ ] **Step 3 — GREEN wiring:** instantiate `AuditService` once, add one authenticated Audit route tree, add one SSR view, add one common response-header middleware.
- [ ] **Step 4 — Regression:** installer, login, cron, public catalog and private file routes continue working with existing auth/CSRF/cache contracts.
- [ ] **Step 5 — GREEN matrix:** PHP 8.3/8.4/8.5.

### Task 3: Explicit high-value audit events

**Files (modify only existing route owners):**
- `app/Auth/routes.php`
- `app/UI/routes.php`
- `app/Contracts/routes.php`
- `app/Leases/routes.php`
- `app/Finance/routes.php`
- `app/Documents/routes.php`
- `app/Memberships/routes.php`
- plus focused Audit integration test(s); no new business service class.

**Interfaces / frozen event mapping:**
- `user.created` — after `UserService::create()`; entity `user`, created user id.
- `user.permissions_changed` — after `setPermissions()`; entity `user`, route id.
- `user.active_changed` — after `setActive()`; entity `user`, route id; metadata only `active` boolean.
- `user.password_reset_issued` — after `AuthService::issueResetToken()`; entity `user`, route id; **never log token/link**.
- `appearance.updated` — after `Branding::update()`; entity `appearance`, null id; no logo bytes/path metadata.
- `contract.finalized` — after `ContractService::finalize()`; entity `contract`, contract id.
- `lease.activated` — after `LeaseService::activate()`; entity `lease`, returned lease id; do not log temporary password.
- `lease.ended` — after `LeaseService::end()`; entity `lease`, returned lease id.
- `charge.created` — direct Finance route charge creation. Capture returned charge id(s); monthly generation may emit one row per actually created charge. Do not duplicate this event for membership-generated charges; membership has its own event below.
- `charge.voided` — after `FinanceService::voidCharge()`; entity `charge`, charge id; no raw reason if unnecessary.
- `payment.reconciled` / `payment.rejected` — after canonical Finance mutations; entity `payment`, payment id; do not log allocations/receipt/rejection body.
- `document.version_created` — capture returned document from `createFileVersion()` / `createContentVersion()`; entity `document`, document id; metadata may contain only safe `type` + scope kind/id, never content/file hash/path/bytes.
- `membership.charge_created` / `membership.charge_skipped` — after `MembershipService::decideCharge()`; entity `membership`, membership id; choose action from returned `charge_outcome`; metadata may contain `charge_id` only when created.
- `bonus.decided` — after `TerminationService::decideBonus()`; entity `lease`, lease id; metadata only terminal decision, not amount/note.
- `liquidation.finalized` — after `TerminationService::finalizeLiquidation()`; entity `liquidation`, returned liquidation id; metadata may contain lease id only.

**Remote IP:** every route passes only `$request->getServerParams()['REMOTE_ADDR'] ?? null`; never forwarding headers.

- [ ] **Step 1 — RED integration tests:** one representative test per route owner plus a complete allowlist/coverage assertion that every frozen action appears exactly at its approved mutation surface and no GET/download/cron heartbeat is audited.
- [ ] **Step 2 — Verify RED:** fail because route owners do not yet receive/use AuditService.
- [ ] **Step 3 — Minimal GREEN:** add AuditService dependency from shared deps and explicit `record()` calls only after successful canonical mutations. Capture existing return values instead of re-querying when possible.
- [ ] **Step 4 — Leak regression:** password reset token, temporary password, document payload, receipt/payment allocations and F21 financial snapshot never appear in `audit_log`.
- [ ] **Step 5 — GREEN matrix:** PHP 8.3/8.4/8.5.

### Task 4: F16 terminal recipient retention

**Files:**
- Modify: `app/Notifications/NotificationService.php`
- Modify: `app/Notifications/routes.php`
- Modify/create focused notification retention integration test; reuse existing Notification test architecture.

**Interfaces:**
- `NotificationService::redactTerminalRecipients(string $now): int`.
- Existing `/cron/notifications` calls retention in the same execution; no new route, cron, scheduler or table.

- [ ] **Step 1 — RED:** sent/failed terminal rows older than 90 days redact `recipient_name` + `recipient_email`; rows newer than/equal to threshold and non-terminal rows remain unchanged; type/template/dedupe/scheduled/terminal timestamp/attempt_count/safe error remain readable.
- [ ] **Step 2 — Verify RED:** method absent and schema previously non-nullable are the expected causes.
- [ ] **Step 3 — GREEN owner method:** calculate UTC cutoff from validated `now`; one bounded UPDATE on terminal rows; set only the two recipient fields to NULL; return affected row count.
- [ ] **Step 4 — Reuse cron:** invoke redaction from the existing protected notification cron and add returned count to its safe JSON response if useful; do not audit cron heartbeat.
- [ ] **Step 5 — Regression:** `processDue()` behavior for live jobs unchanged; terminal history remains operational after redaction.
- [ ] **Step 6 — GREEN matrix:** PHP 8.3/8.4/8.5.

### Task 5: Security/privacy hardening matrix — repair only proven gaps

**Files:**
- Prefer existing security regression tests in Auth/Candidates/Properties/Documents/Finance/Leases/Foundation.
- Product files may change only when a RED demonstrates a concrete SPEC gap.

- [ ] **Step 1 — Public/catalog RED checks:** no private address/internal notes leakage, unpublished media 404, public identifiers do not expose private internals.
- [ ] **Step 2 — Candidate/token RED checks:** raw continuation token absent from persistent/log surfaces except intended one-time URL; expiration/rotation/non-enumeration preserved.
- [ ] **Step 3 — Tenant IDOR ordering checks:** finance, payments, obligations, maintenance/visits, documents, community, memberships and liquidation resolve current tenant lease ownership before child lookup.
- [ ] **Step 4 — Upload/storage checks:** size before unbounded read where applicable, MIME from bytes, hashes where owner supports them, spoof/traversal/symlink/path containment, private file `no-store` + `nosniff`.
- [ ] **Step 5 — Auth/session/CSRF/errors checks:** login rotates session, forced password change/inactivity preserved, every authenticated mutation uses global CSRF except exact protected cron, generic 500 boundary, safe candidate-token path logging, production cookie flags derived from HTTPS.
- [ ] **Step 6 — Redirect/external URL checks:** no open redirect; WhatsApp snapshot URL integrity preserved; provider URLs do not leak continuation token through new F22 behavior.
- [ ] **Step 7 — Minimal repair pass:** max one product repair pass for proven failures; no speculative hardening framework.
- [ ] **Step 8 — GREEN matrix:** PHP 8.3/8.4/8.5 after repairs (or test-only closure if no product gap exists).

### Task 6: Convergence, governance and final checkpoint

**Files:**
- Create: `docs/checkpoints/F22_CHECKPOINT_2026-10-14.md`
- Modify README/plan only if factual state needs convergence.

- [ ] **Step 1 — Full affected suites:** Audit/Auth/UI/Contracts/Leases/Finance/Documents/Memberships/Notifications plus Candidates/Properties/Foundation security regressions.
- [ ] **Step 2 — Full suite once:** `composer test`; capture exact test/assertion/skip counts.
- [ ] **Step 3 — Syntax/Composer gates:** `composer validate --strict` + PHP lint on changed PHP files.
- [ ] **Step 4 — Fresh 10-pass audit:** KISS, Zero Noise, Anti-patch, audit-table budget, auth-stack uniqueness, secret/privacy leakage, IDOR ordering, upload/storage, CSRF/headers/errors, retention/governance.
- [ ] **Step 5 — Governance truth:** re-read `main` protection. If still unprotected and admin mutation is unavailable, checkpoint exactly `GOVERNANCE_ACTION_REQUIRED`; do not fake success.
- [ ] **Step 6 — Final docs-only checkpoint + fresh CI matrix:** only after product head is certified.
- [ ] **Step 7 — Merge gate:** merge F22 only if head/base/mergeability unchanged and PHP 8.3/8.4/8.5 all `success`. F23 is next only after F22 closes.

## Self-review — 10 passes

1. **Scope:** PASS — only F22; F23–F26 untouched.
2. **KISS budget:** PASS — 1 table, 1 domain class, 0 permission/runtime/event-bus/auth/scheduler additions.
3. **Authority duplication:** PASS — business owners unchanged; AuditService only records evidence, NotificationService owns retention.
4. **Constructor fanout:** PASS — route-level explicit audit avoids injecting AuditService into all domain constructors.
5. **Secret/privacy:** PASS — metadata bounded/allowlisted; token/password/document/payment payloads forbidden.
6. **IDOR:** PASS — task explicitly re-certifies ownership-first tenant access.
7. **CSRF/auth:** PASS — existing global middleware preserved; exact cron exemption remains singular.
8. **Storage/headers:** PASS — common safe headers centralized; private cache/storage guards retained.
9. **Retention:** PASS — one existing cron, one owner method, no recipient backup/hash table.
10. **Governance/noise:** PASS — branch protection reported truthfully; repair only proven RED gaps; no speculative framework.

## Execution state

`PLAN_F22: READY / AUDITED`  
`PRODUCT_IMPLEMENTATION: NOT STARTED`  
`NEXT: create F22 implementation branch from canonical main and execute Task 1 RED only.`
