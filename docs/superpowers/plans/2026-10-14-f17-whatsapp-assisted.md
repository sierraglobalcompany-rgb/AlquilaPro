# F17 Assisted WhatsApp Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add auditable assisted WhatsApp message preparation/opening without provider integration or false delivery/read states.

**Architecture:** One `whatsapp_actions` table and one `WhatsAppService` under the existing `AlquilaPro\Notifications` module. Reuse F16 `app/Notifications/routes.php`; add only permission `whatsapp.use`. Templates and variable rules live in code.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO, SSR, PHPUnit.

**Spec:** `docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md` — F17 section.

## Global Constraints

- Exactly 1 new table: `whatsapp_actions`.
- Exactly 1 new class: `AlquilaPro\Notifications\WhatsAppService`.
- 1 new permission: `whatsapp.use`.
- Reuse F16 Notifications route file; 0 new provider/API/webhook/runtime dependencies.
- Never persist `sent`, `delivered`, `read` or provider message ids.
- Opening WhatsApp is an authenticated/CSRF-protected user action.

## Review Focus

1. Audit row cannot be forged for another staff user through IDOR.
2. Phone normalization cannot create arbitrary protocols/URLs.
3. Template variables cannot inject uncontrolled template keys/placeholders.
4. Opening twice preserves first/open history without pretending delivery.
5. Tenant role remains denied even with polluted permissions.

---

### Task 1: Schema + WhatsApp authority

**Files:**
- Create: `database/migrations/017_create_whatsapp_actions.sql`
- Create: `app/Notifications/WhatsAppService.php`
- Create: `tests/Integration/Notifications/WhatsAppSchemaTest.php`
- Create: `tests/Integration/Notifications/WhatsAppServiceTest.php`

**Interfaces:**

```php
WhatsAppService::__construct(PDO $pdo)
WhatsAppService::prepare(string $contextType, int $contextId, string $recipientPhone, string $templateKey, array $variables, int $actorUserId, int $now): array
WhatsAppService::find(int $actionId): ?array
WhatsAppService::markOpened(int $actionId, int $actorUserId, int $now): array
WhatsAppService::listRecent(int $limit = 100): array
```

Initial context allowlist:

```text
candidate
lease
maintenance_request
```

Initial template keys remain code constants and must have allowlisted variables. Exact Spanish copy can be concise and operational; no DB template CMS.

- [x] RED schema: exact columns, no delivery/read/provider fields.
- [x] RED phone normalization to digits/E.164-style wa.me destination; reject malformed/empty.
- [x] RED template allowlist + missing/extra variable rejection.
- [x] GREEN snapshot of phone/message/URL at preparation time.
- [x] `markOpened()` records authenticated actor/time but never sent/delivery state.
- [x] Run schema/service focused tests.

### Task 2: Permissions + routes + UI

**Files:**
- Modify: `app/Auth/Permissions.php`
- Modify: `app/Notifications/routes.php`
- Modify: `bootstrap/app.php`
- Create: `resources/views/whatsapp.php`
- Create: `tests/Integration/Notifications/WhatsAppRoutesTest.php`
- Modify: permission/shell regressions.

Routes stay private, for example:

```text
GET  /admin/whatsapp
POST /admin/whatsapp
POST /admin/whatsapp/{id}/open
```

- [ ] RED `whatsapp.use` permission, owner bypass, tenant hard deny.
- [ ] RED prepare route requires valid existing context id for its allowlisted type.
- [ ] GREEN confirmation/history SSR page with snapshot, no secret/internal filesystem data.
- [ ] Open route calls `markOpened()` then 303 redirects to stored HTTPS `wa.me` URL.
- [ ] Keep CSRF/forced-password/basePath behavior.
- [ ] Run route/permission/auth focused tests.

### Task 3: Architecture/security freeze + certification

**Files:**
- Create: `tests/Integration/Notifications/WhatsAppSecurityRegressionTest.php`

- [ ] Freeze 1 table / 1 service / 1 permission / 0 API/webhook/provider/dependency.
- [ ] Assert no columns/strings for delivered/read/provider message id.
- [ ] Assert all open/prepare routes private and tenant denied.
- [ ] Fresh phase review before remote CI; repair only real Critical/Important findings.
- [ ] `composer validate --strict`, `git diff --check`, focused F16/F17/auth tests.
- [ ] Define Product Head; docs-only closure; one push.
- [ ] Stacked PR against F16 closure branch; one PHP 8.3/8.4/8.5 PR CI.
- [ ] Stop without merge/F18 if gate fails.
