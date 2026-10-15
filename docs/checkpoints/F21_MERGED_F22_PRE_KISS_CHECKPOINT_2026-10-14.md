# AlquilaPro — Checkpoint F21 merged / F22 PRE-KISS

**Fecha local del proyecto:** 2026-10-14  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Propósito:** recovery checkpoint únicamente; no inicia implementación F22.

## Estado canónico

- `main`: `7c0f0c75c665b9888d26bfdbfd5be9bec4df3b82`
- F21 PR #26: **MERGED / CLOSED**
- F21 merge SHA: `7c0f0c75c665b9888d26bfdbfd5be9bec4df3b82`
- F21 product head certificado: `0a7438a5659623a42cb00c283e7edbe6b12337d0`
- F21 checkpoint final: `5e5d15cb2bf51fc8c115784b34824acec6f0cc2e`
- F21 product CI #969: GREEN PHP 8.3 / 8.4 / 8.5
- F21 final docs-head CI #970 / run `38671001817`: GREEN PHP 8.3 / 8.4 / 8.5
- PHP 8.3 product suite: **573 tests / 6024 assertions / 0 failures / 0 skips**
- F21 10-pass KISS / Zero Noise / Anti-patch / privacy / concurrency audit: **PASS**

## F21 final architecture

- exactly 2 F21 tables: `good_conduct_bonuses`, `lease_liquidations`;
- exactly 1 F21 domain class: `TerminationService`;
- 0 new permissions;
- 0 runtime dependencies;
- 0 public routes;
- existing `app/Leases/routes.php` remains the single lease route tree;
- `LeaseService::end()` remains the sole lease lifecycle writer;
- Finance/Documents/Insurance/Community/Obligation/Membership services remain canonical authorities;
- tenant liquidation resolves `LeaseService::findForUser()` before child lookup;
- good-conduct bonus remains default-off via `ALQUILAPRO_GOOD_CONDUCT_BONUS_ENABLED`.

## F22 status

**F22 implementation has NOT started.**  
Current state: **PRE-KISS / design audit in progress only.**

Frozen authority:

- `docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md`

F22 budget from SPEC:

- NEW_TABLES = 1: `audit_log`
- NEW_DOMAIN_CLASSES = 1: `AuditService`
- NEW_PERMISSIONS = 0
- AUDIT_VIEWER = owner-only in V1
- NEW_PUBLIC_ROUTES = 0
- NEW_RUNTIME_DEPENDENCIES = 0
- NEW_EVENT_BUS = 0
- NEW_SECURITY_FRAMEWORK = 0
- NEW_AUTH_STACK = 0
- NEW_SCHEDULER = 0
- expected migration: `022_*`

Do not add `security_events`, `login_events`, `audit_metadata`, `privacy_jobs` or `retention_rules` tables.

## PRE-KISS findings already verified

1. `AuditService` does not exist yet.
2. `audit_log` does not exist yet.
3. F16 `notification_jobs.recipient_email` and `recipient_name` are currently `NOT NULL`.
4. Therefore migration 022 will need the minimum compatible schema delta to make only those two recipient snapshot columns nullable if implementing the frozen 90-day redaction.
5. Notification retention owner is `NotificationService`; reuse the existing F16 notification cron. Do **not** add a second cron/scheduler.
6. F22 recipient retention rule is frozen at **90 days** after terminal `sent_at` or `failed_at`.
7. Preserve type/template/dedupe/scheduled/sent-or-failed/attempt_count/safe error category after recipient redaction; do not retain an alternate/hash copy of the recipient.
8. Global F22 baseline headers are not currently present in common bootstrap for at least `X-Frame-Options` / `Referrer-Policy`; F22 must centralize them rather than duplicate route-by-route.
9. Existing root `.htaccess` already denies direct access to internal paths including `.github`, `app`, `bootstrap`, `config`, `database`, `docs`, `resources`, `storage`, `tests`, `vendor`.
10. Current `main` branch remains **unprotected**. F22 governance must report `GOVERNANCE_ACTION_REQUIRED` if the available GitHub connection cannot enable protection; do not claim protection exists.
11. Auth/session/CSRF remain the existing single stack. The only current global POST CSRF exemption is the exact protected notifications cron path; F22 must preserve exactness, not widen it.
12. Error middleware already emits a generic external 500 and redacts raw candidate continuation tokens from logged paths; F22 should test/retain this rather than create a second error framework.

## Frozen F22 audit log contract

Minimum table:

- `id BIGINT UNSIGNED PK`
- `actor_user_id BIGINT UNSIGNED NULL` -> users SET NULL
- `occurred_at DATETIME NOT NULL`
- `action VARCHAR(80) NOT NULL`
- `entity_type VARCHAR(80) NOT NULL`
- `entity_id BIGINT UNSIGNED NULL`
- `summary VARCHAR(255) NOT NULL`
- `ip_address VARCHAR(45) NULL`
- `metadata_json TEXT NULL`

Append-only; no update/delete surface.

`AuditService::record(...)` remains the single new domain API. It must not start a business workflow or become an event bus.

Metadata constraints from SPEC:

- strict machine-key action/entity type;
- summary 1..255;
- metadata JSON <= 2048 bytes;
- safe scalar/small-list metadata only;
- no request dump, cookie/session/token/password, document/payment body, SQL, exception trace;
- IP only from validated `REMOTE_ADDR`; invalid IP -> NULL;
- do not trust raw `X-Forwarded-For`.

## Mandatory F22 high-value event baseline

- `user.created`
- `user.permissions_changed`
- `user.active_changed`
- `user.password_reset_issued`
- `appearance.updated`
- `contract.finalized`
- `lease.activated`
- `lease.ended`
- `charge.created`
- `charge.voided`
- `payment.reconciled`
- `payment.rejected`
- `document.version_created`
- `membership.charge_created`
- `membership.charge_skipped`
- `bonus.decided`
- `liquidation.finalized`

Do not audit every GET/page view/download/cron heartbeat/public spam.

## Next exact action after `continúa`

1. Resume F22 PRE-KISS against canonical `main@7c0f0c75...`.
2. Map each mandatory auditable mutation to its owning service/route and decide the smallest explicit injection point; avoid constructor fanout/circular dependencies.
3. Verify current shared response middleware/session/upload/private-file protections and identify only proven gaps.
4. Write the F22 implementation plan in small TDD tasks before product code.
5. Audit that plan for KISS / Zero Noise / Anti-patch / authority duplication.
6. Only then open the F22 implementation branch and begin with a RED schema/service contract.

## Hard stop

No F22 product files, tests, migrations, routes or services were created as part of this checkpoint. Resume only on a new explicit `continúa`.
