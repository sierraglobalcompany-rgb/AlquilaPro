# F22 — Security + Privacy + Audit Log Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close AlquilaPro V1 security/privacy gaps and add a small append-only audit log without introducing a second auth stack, event bus, SIEM, scheduler or security framework.

**Architecture:** Add one `AuditService` backed by one `audit_log` table. High-value domain owners write audit rows explicitly on the same PDO connection and, where the owner already has a transaction, before commit so business mutation + audit are atomic; non-transactional audited mutations receive the smallest local transaction needed. Cross-cutting hardening remains test-driven: reproduce each issue first, then modify only the owning file.

**Tech Stack:** PHP >=8.3 <8.6, Slim 4, PDO/MySQL, PHPUnit 11.5, existing SSR/auth/session/CSRF/storage architecture.

**Spec:** `docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md` — sections F22 / 16–29.

## Global Constraints

- `NEW_TABLES = 1`: `audit_log`.
- `NEW_DOMAIN_CLASSES = 1`: `AuditService`.
- `NEW_PERMISSIONS = 0`; audit viewer is owner-only in V1.
- `NEW_RUNTIME_DEPENDENCIES = 0`.
- `NEW_EVENT_BUS = 0`, `NEW_SECURITY_FRAMEWORK = 0`, `NEW_AUTH_STACK = 0`, `NEW_SCHEDULER = 0`.
- Audit log is append-only; no product update/delete endpoint.
- Audit metadata max 2048 bytes and never stores passwords, tokens, cookies, session dumps, document/payment payloads, SQL or exception traces.
- IP source is validated `REMOTE_ADDR`; do not trust arbitrary `X-Forwarded-For`.
- Tenant child lookups happen only after `LeaseService::findForUser()`.
- F16 recipient retention is 90 days after terminal `sent_at`/`failed_at`, using the existing F16 cron.
- Security fixes require a reproducing RED; no speculative refactor.
- No F23 implementation until F22 closes.

## Pre-Execution Gate

- [ ] F21 product and closure checkpoints exist and F21 focal/integrated/full suites are GREEN.
- [ ] Fetch the final F20/F21 heads so constructor signatures and route wiring in this plan are reconciled before code.
- [ ] Record base SHA and confirm worktree is clean.
- [ ] Confirm migration 022 is the next migration number after final F21.

## Review Focus

1. Audit insert failure must not leave a committed high-value mutation without its audit row — covered by Task 2 atomicity tests.
2. Recipient redaction must never affect pending/retryable jobs — covered by Task 3.
3. Tenant IDOR must resolve ownership before every child lookup across all modules — covered by Task 5.
4. Upload/path hardening must reject traversal, symlink and spoofed PHP without weakening valid files — covered by Task 6.
5. HTTPS-only headers/cookie behavior must not break local HTTP development — covered by Task 7.

---

### Task 1: Audit schema, service and owner-only viewer

**Files:**
- Create: `database/migrations/022_create_audit_log_and_privacy_retention.sql`
- Create: `app/Audit/AuditService.php`
- Create: `app/Audit/routes.php`
- Create: `resources/views/audit_log.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Integration/Security/AuditSchemaTest.php`
- Create: `tests/Integration/Security/AuditServiceTest.php`
- Create: `tests/Integration/Security/AuditRoutesTest.php`

**Interfaces:**
- Constructor: `AuditService::__construct(PDO $pdo)`.
- Produces:
  - `record(?int $actorUserId, string $action, string $entityType, ?int $entityId, string $summary, ?string $remoteIp, array $metadata = []): void`
  - `listRecent(int $limit = 100, ?int $beforeId = null): array`
- Owner route: `GET /admin/audit` — owner role only, cursor by `before_id`, max page size 100.

- [ ] **Step 1: Write RED schema test** asserting one new table only, required columns, no update/delete/status columns, and F16 recipient columns become nullable in the same migration without adding a privacy table.
- [ ] **Step 2: Run RED** with `vendor/bin/phpunit tests/Integration/Security/AuditSchemaTest.php`.
- [ ] **Step 3: Implement migration 022**: create `audit_log`; `ALTER notification_jobs` so `recipient_email` and `recipient_name` accept `NULL`.
- [ ] **Step 4: Write RED service tests** for action/entity validation, summary bounds, metadata <=2048 bytes, secret-shaped/unsupported metadata rejection, invalid IP -> `NULL`, and bounded cursor listing.
- [ ] **Step 5: Implement `AuditService`** as one explicit PDO writer/reader; it starts no transaction of its own so it participates in the caller's transaction when one exists.
- [ ] **Step 6: Write RED route tests** proving unauthenticated redirect, non-owner 403, owner 200, no new permission key and no audit mutation routes.
- [ ] **Step 7: Wire service + route once** in `bootstrap/app.php`; add minimal owner-only SSR table.
- [ ] **Step 8: Run GREEN** for Task 1 tests.
- [ ] **Step 9: Commit** `feat(f22): add append only audit log`.

### Task 2: Atomic high-value audit integration

**Files:**
- Modify: `bootstrap/app.php`
- Modify: `app/Auth/UserService.php`
- Modify if it owns the reset mutation: `app/Auth/AuthService.php`
- Modify: `app/UI/Branding.php`
- Modify: `app/Contracts/ContractService.php`
- Modify: `app/Leases/LeaseService.php`
- Modify: `app/Finance/FinanceService.php`
- Modify: `app/Documents/DocumentService.php`
- Modify: `app/Memberships/MembershipService.php`
- Modify: `app/Leases/TerminationService.php`
- Create: `tests/Integration/Security/AuditIntegrationTest.php`
- Create: `tests/Integration/Security/AuditAtomicityTest.php`

**Interfaces:**
- Each audited owner receives the same `AuditService` instance through constructor injection; no service locator/global/static bus.
- Allowlisted actions: `user.created`, `user.permissions_changed`, `user.active_changed`, `user.password_reset_issued`, `appearance.updated`, `contract.finalized`, `lease.activated`, `lease.ended`, `charge.created`, `charge.voided`, `payment.reconciled`, `payment.rejected`, `document.version_created`, `membership.charge_created`, `membership.charge_skipped`, `bonus.decided`, `liquidation.finalized`.

- [ ] **Step 1: Write RED integration tests** that execute one real mutation per allowlisted action and assert exactly one corresponding audit row with bounded safe metadata.
- [ ] **Step 2: Write RED atomicity tests** using an audit-table failure fixture/constraint so an audited mutation that has not committed cannot commit without its audit row.
- [ ] **Step 3: Inject `AuditService` explicitly** into only the owners listed above; keep it dependency-free beyond PDO.
- [ ] **Step 4: For existing transactional mutations**, call `AuditService::record()` after all domain validation/writes and before commit.
- [ ] **Step 5: For audited non-transactional mutations**, add the smallest local transaction around the existing mutation + audit insert; do not add a transaction abstraction.
- [ ] **Step 6: Keep summaries/metadata minimal**: entity ids, safe machine-state labels and no financial/document payload copies.
- [ ] **Step 7: Run GREEN** for audit integration/atomicity plus affected module suites.
- [ ] **Step 8: Commit** `feat(f22): audit high value mutations atomically`.

### Task 3: F16 recipient privacy retention using the existing cron

**Files:**
- Modify: `app/Notifications/NotificationService.php`
- Modify: `app/Notifications/routes.php`
- Create: `tests/Integration/Notifications/NotificationPrivacyRetentionTest.php`

**Interfaces:**
- Produces: `redactTerminalRecipients(string $now): int`.
- Constant: `TERMINAL_RECIPIENT_RETENTION_DAYS = 90`.

- [ ] **Step 1: Write RED tests** proving rows terminal for >90 days get `recipient_email=NULL` and `recipient_name=NULL`; rows at/below threshold remain intact; pending/claimed/retryable rows are never redacted.
- [ ] **Step 2: Add RED operational-history test** proving type/template/dedupe/schedule/attempt/sent-or-failed/safe-error fields remain readable after redaction.
- [ ] **Step 3: Run RED** with `vendor/bin/phpunit tests/Integration/Notifications/NotificationPrivacyRetentionTest.php`.
- [ ] **Step 4: Implement `redactTerminalRecipients()`** with one bounded UPDATE predicate on terminal timestamps; no new table/hash/copy of recipient data.
- [ ] **Step 5: Invoke it from the existing protected `/cron/notifications` execution**; do not add another cron route or scheduler.
- [ ] **Step 6: Run GREEN** for Notifications suite.
- [ ] **Step 7: Commit** `feat(f22): redact terminal notification recipients`.

### Task 4: Public catalog and candidate surface hardening

**Files:**
- Create: `tests/Integration/Security/PublicSurfaceSecurityTest.php`
- Create: `tests/Integration/Security/CandidateSecurityRegressionTest.php`
- Modify only if a RED proves a defect: `app/Properties/routes.php`, `app/Properties/PropertyService.php`, `app/Candidates/routes.php`, `app/Candidates/CandidateService.php`, `app/Candidates/CandidateDocumentService.php`, `bootstrap/app.php`.

**Interfaces:** existing public routes only; no new public endpoint.

- [ ] **Step 1: Write regression tests** for public allowlist, unpublished media 404, no internal address/notes leakage, non-enumerable candidate flow, raw token absent from logs/responses except the intended one-time continuation URL, expiry/rotation fail closed, and generic duplicate identity response.
- [ ] **Step 2: Run the new tests before product edits** and classify every failure as `PRODUCT DEFECT`, `STALE TEST`, or `NO DEFECT`.
- [ ] **Step 3: For each real RED only**, repair the owning authority minimally; do not create new token/auth/rate-limit framework.
- [ ] **Step 4: Re-run focused tests until GREEN**, then run affected Properties/Candidates suites once.
- [ ] **Step 5: Commit only if product code changed**: `fix(f22): harden public candidate surfaces`.

### Task 5: Tenant IDOR ordering matrix

**Files:**
- Create: `tests/Integration/Security/TenantIdorMatrixTest.php`
- Modify only on proven RED: `app/Leases/routes.php`, `app/Finance/routes.php`, `app/Documents/routes.php`, `app/Maintenance/routes.php`, `app/Community/routes.php`, F20 membership routes, F21 termination routes.

**Interfaces:** mandatory sequence `current user -> LeaseService::findForUser() -> child lookup`.

- [ ] **Step 1: Build RED/green matrix** for finance, payments, obligations, maintenance/visits, documents, community, memberships and liquidation using two tenants and cross-lease ids.
- [ ] **Step 2: Add structural regression assertions only where semantic behavior alone cannot prove lookup order; assert the ownership call precedes child-service calls, not exact callback capture lists.**
- [ ] **Step 3: Run matrix before edits**; do not patch passing modules.
- [ ] **Step 4: Repair only failing route owners** by moving ownership resolution before child lookup.
- [ ] **Step 5: Run GREEN** and affected module suites.
- [ ] **Step 6: Commit only if needed**: `fix(f22): enforce tenant ownership before child lookups`.

### Task 6: Storage/upload/path security matrix

**Files:**
- Create: `tests/Integration/Security/StorageSecurityMatrixTest.php`
- Reuse existing module-specific storage security tests.
- Modify only on proven RED: candidate document, property media, contract, payment receipt, obligation support, insurance, maintenance photo, F18 DocumentService storage owners.

- [ ] **Step 1: Test every private upload/storage authority** for physical-size bound, bounded read, MIME-from-bytes, unsupported/PHP spoof rejection, traversal rejection, symlink rejection, canonical containment, integrity/hash enforcement where applicable, and `private, no-store` + `nosniff` on private downloads.
- [ ] **Step 2: Run matrix before edits** and record each failing authority separately.
- [ ] **Step 3: Repair each owner minimally**; converge duplicated safe helpers only when two or more copies are demonstrably identical and merging reduces code without creating a framework.
- [ ] **Step 4: Run GREEN** including the existing Linux symlink CI path.
- [ ] **Step 5: Commit only if needed**: `fix(f22): close private storage regressions`.

### Task 7: Session, CSRF, headers, errors and secrets

**Files:**
- Create: `tests/Integration/Security/SessionCsrfHeadersTest.php`
- Create: `tests/Integration/Security/SecretsErrorsRegressionTest.php`
- Modify only where RED proves need: `index.php`, `bootstrap/app.php`, authenticated route files, `.htaccess`, `.gitignore`.

**Interfaces / required behavior:**
- Login rotates session id; existing forced-password/inactivity controls remain.
- Every authenticated POST has CSRF except exact `POST /cron/notifications`.
- Baseline dynamic headers: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: SAMEORIGIN`.
- HSTS only when detected base URL is HTTPS; no HSTS on local HTTP.
- Sensitive authenticated/private responses remain `Cache-Control: private, no-store`.

- [ ] **Step 1: Write tests** for session fixation, exact CSRF exemption, production cookie flags, baseline headers, HTTPS-only HSTS, generic 500/422 boundaries, redacted candidate token path logging, and absence of SMTP/cron/session/document secrets in logs/responses.
- [ ] **Step 2: Run before edits**; classify stale structural tests separately from product defects.
- [ ] **Step 3: Centralize common security headers in the existing bootstrap middleware** only if tests prove gaps; keep private-file headers at their current owner.
- [ ] **Step 4: Apply minimal owner fixes for any other RED**; do not add CSP nonce framework, WAF, OAuth/OIDC or new auth middleware stack.
- [ ] **Step 5: Run GREEN** plus Auth/Foundation integration suites.
- [ ] **Step 6: Commit only if needed**: `fix(f22): harden session csrf headers and error boundaries`.

### Task 8: Governance, convergence and checkpoint

**Files:**
- Create: `docs/checkpoints/F22_CHECKPOINT_2026-10-14.md`
- Modify only for verified operator instructions: `README.md`

- [ ] **Step 1: Run focal F22 tests** under `tests/Integration/Security` plus `NotificationPrivacyRetentionTest`.
- [ ] **Step 2: Run affected integrated suites** once: Auth, Candidates, Properties, Leases, Finance, Documents, Notifications, Memberships/F20, Termination/F21.
- [ ] **Step 3: Run full suite once** with `composer test`.
- [ ] **Step 4: Run `composer validate --strict` and PHP lint** for changed PHP files.
- [ ] **Step 5: Fresh-review entire F22 diff** for KISS/zero-noise/antipatch, secret leakage, audit payloads and duplicate security/auth abstractions; allow one evidence-based repair pass.
- [ ] **Step 6: Verify GitHub `main` protection state**. If still unprotected and no administrative connector is available, record `GOVERNANCE_ACTION_REQUIRED` with exact desired controls: PR required, CI required, force-push blocked. Do not claim it is enabled.
- [ ] **Step 7: Write checkpoint** with base/product/closure SHAs, exact test counts, security matrix outcome, governance state and `MERGE_AUTHORIZED: NO`.
- [ ] **Step 8: Commit** `docs(f22): close security privacy checkpoint`.

## Self-Review Result

- Spec coverage: PASS — audit, privacy retention, public/candidate, IDOR, storage, auth/session/CSRF, headers/errors/secrets and governance all map to tasks.
- Step scan: PASS — security repairs are conditional on reproducing REDs; no speculative patch task.
- Type consistency: PASS — one `AuditService`, one PDO-backed audit writer, explicit owner injection, no event bus.
- Review Focus: PASS — atomicity, retention safety, IDOR ordering, storage spoof/traversal/symlink and HTTPS/local behavior are each pinned to a task.
- Proportion: PASS — the plan specifies contracts/tests/gates without transcribing implementations.

**Execution status:** `PLAN WRITTEN / USER REVIEW REQUIRED / F21 GREEN REQUIRED / IMPLEMENTATION NOT AUTHORIZED / MERGE NOT AUTHORIZED`.
