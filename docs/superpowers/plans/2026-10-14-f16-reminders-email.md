# F16 Reminders + Cron + Email Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the single automatic reminder/email engine for AlquilaPro with idempotent jobs, protected cron processing and explicit SMTP transport.

**Architecture:** One `notification_jobs` table and one `NotificationService`. A required transport callable is injected; production builds it with PHPMailer, tests provide a fake. Rules/templates remain code/config, not tables. One protected POST cron endpoint is the only non-session mutation surface.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO, PHPMailer `^7.1`, PHPUnit.

**Spec:** `docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md` — F16 section.

## Global Constraints

- Exactly 1 new table: `notification_jobs`.
- Exactly 1 new domain class: `AlquilaPro\Notifications\NotificationService`.
- Add only `phpmailer/phpmailer:^7.1` as runtime dependency.
- 0 rule/template tables, Redis, queue systems, daemon workers or per-module mail engines.
- SMTP secrets only from settings/env; never DB, HTML or logs.
- Candidate email integration queues jobs; request routes never send SMTP directly.

## Review Focus

1. Duplicate cron/request execution never creates duplicate dedupe keys.
2. Missing/wrong cron secret cannot bypass CSRF into another POST route.
3. SMTP exception records bounded failure and never loops indefinitely.
4. Candidate event integration queues exactly once per event snapshot.
5. Reminder generators only expose recipient-safe payloads and real existing entities.

---

### Task 1: Schema + queue authority

**Files:**
- Create: `database/migrations/016_create_notification_jobs.sql`
- Create: `app/Notifications/NotificationService.php`
- Create: `tests/Integration/Notifications/NotificationSchemaTest.php`
- Create: `tests/Integration/Notifications/NotificationServiceTest.php`
- Modify: `composer.json`
- Modify: `composer.lock`

**Interfaces:**

Required transport callable signature:

```php
Closure(string $email, string $name, string $subject, string $htmlBody, string $textBody): void
```

Service:

```php
NotificationService::__construct(PDO $pdo, Closure $transport)
NotificationService::queue(string $type, string $email, string $name, string $subject, string $templateKey, array $payload, string $scheduledAt, string $dedupeKey): ?int
NotificationService::generateReminders(string $today, string $now): int
NotificationService::processDue(int $limit, string $now): array
NotificationService::queueCandidateEvent(int $candidateId, string $eventKey, string $scheduledAt): ?int
```

- [ ] RED schema: minimal columns, `UNIQUE(dedupe_key)`, no persisted status/rule/template table.
- [ ] GREEN migration.
- [ ] RED queue validation/idempotency, pending/sent/failed derived state, attempt cap and retry eligibility.
- [ ] GREEN minimal queue/process authority.
- [ ] Add PHPMailer `^7.1`; no other mail package.
- [ ] Run schema/service focused tests.

### Task 2: Reminder rules + candidate events

**Files:**
- Modify: `app/Notifications/NotificationService.php`
- Modify: `app/Candidates/routes.php`
- Create/modify: `tests/Integration/Notifications/NotificationRulesTest.php`
- Modify: relevant candidate route tests.

- [ ] RED reminder generation for real existing domains: due/overdue charges, F12 obligations, F13 policy expiry and F15 scheduled visits.
- [ ] Use stable dedupe keys containing entity id + rule/event date/version; no generic event bus.
- [ ] RED candidate-created / relevant candidate-update event queues one job and never sends inline.
- [ ] GREEN route integration calls `queueCandidateEvent()` after successful candidate mutation.
- [ ] Templates remain private methods/config keyed by allowlisted `template_key`.
- [ ] Run notification + affected Candidates/F9/F12/F13/F15 focused tests.

### Task 3: SMTP wiring + protected cron

**Files:**
- Create: `app/Notifications/routes.php`
- Modify: `bootstrap/app.php`
- Modify: config/settings example used by installer/runtime as applicable.
- Create: `tests/Integration/Notifications/NotificationCronRoutesTest.php`
- Modify: CSRF/security regression tests.

**Production wiring:**
- Build required transport closure in bootstrap using PHPMailer and SMTP settings.
- No optional production fallback and no native `mail()` fallback.
- Expected settings: host, port, username, password, encryption, from_email, from_name, `cron_secret`.

Cron:

```text
POST /cron/notifications
header: X-AlquilaPro-Cron-Secret
```

- [ ] RED missing/wrong secret → 403 with no job mutation/send.
- [ ] RED global CSRF remains mandatory for every other POST.
- [ ] GREEN exact-path CSRF exemption only for `/cron/notifications`; route validates secret with `hash_equals` before work.
- [ ] Route calls `generateReminders()` then `processDue()` with a bounded batch.
- [ ] Response contains only safe counts, never recipient/secrets/errors.
- [ ] Run cron/CSRF/auth focused tests.

### Task 4: Architecture freeze + certification

**Files:**
- Create: `tests/Integration/Notifications/NotificationSecurityRegressionTest.php`

- [ ] Freeze 1 table / 1 service / 1 approved dependency / 0 workers/Redis/rule/template tables.
- [ ] Prove candidate routes contain no direct PHPMailer/SMTP send.
- [ ] Environment smoke + fresh review before final CI.
- [ ] `composer validate --strict`, `git diff --check`, affected focused suite.
- [ ] Define `F16_PRODUCT_HEAD`; closure docs-only.
- [ ] One push; stacked PR against F15 closure branch; one PHP 8.3/8.4/8.5 PR CI.
- [ ] Record merge-ref/counts in batch checkpoint and stop on any blocker.
