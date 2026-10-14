# F15→F20 Batch Execution Checkpoint

~~~text
PREPACKAGE_BASE: 37350105ac8c1806ae69194082098dbe230e5838
MAIN_START: ddb24048326e3fd67df919f1bd829fac939d8861
MAIN_START_AUDIT: PASS — descendant of PREPACKAGE_BASE; only the V4 master and design checkpoint changed
CURRENT_PHASE: F16
CURRENT_TASK: F16 stacked PR/CI pending; then F17 implementation
BRANCH: impl/f16-reminders-email-20261014
BASE: c40cd33321739effdce0253f67dc372e412dccde (F15 CLOSURE_HEAD)
CURRENT_HEAD: F16 docs-only closure immediately after product head 7b5118b
WORKTREE: C:\Users\felip\.codex\worktrees\f15-f20-batch\AlquilaPro
WORKTREE_STATUS: F16 product and closure docs committed; PHPUnit cache untracked and excluded
F15_STATUS: LOCAL CERTIFIED / PR OPEN / MERGEABILITY CONFLICTING
F15_BRANCH: impl/f15-agenda-visits-20261014
F15_BASE: ddb24048326e3fd67df919f1bd829fac939d8861
F15_PRODUCT_HEAD: d2c23d44aeefd28ba70fce9ad1d8679d87a3275e
F15_CLOSURE_HEAD: c40cd33321739effdce0253f67dc372e412dccde
F15_PR: #18 https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/18
F15_PR_BASE: main@5fa4141c197204f60fafdb10690de3014ac4606a
F15_PR_HEAD: c40cd33321739effdce0253f67dc372e412dccde
F15_PR_CI: triggered; run ID and merge-ref pending final batch check
F15_PR_MERGEABILITY: CONFLICTING — README.md only; current main has three documentation commits beyond MAIN_START. Reconcile before final F15 CI certification.
F15_SCOPE: 1 migration/table; 1 VisitService; 0 permissions/dependencies/public routes; no F16 behavior
F15_REVIEW: 1 fresh whole-phase review; 4 Important fixed in one repair pass; 0 Critical; 0 Minor; no declined behavior
F15_LOCAL_QA: Maintenance 25/252; TenantPortal 10/220; CSRF 5/9; MigrationRunner 2/30; composer validate --strict PASS; git diff --check PASS
F16_SCOPE: 1 notification_jobs table; 1 NotificationService; PHPMailer ^7.1; no workers/Redis/rule/template tables; one protected cron route
F16_TASK1: Migration 016 and NotificationService queue/claim/send lifecycle. Due claims use 600-second leases, consume attempts immediately before transport, retry at most 3 times with 300-second delay, and terminalize expired exhausted claims; generic error keys only.
F16_TASK1_DESIGN: unique dedupe key; no status column; pending/sent/failed derived from timestamps; max 3 attempts; 300-second retry delay; transport errors persisted only as generic keys; batch capped at 100 and claimed under SKIP LOCKED.
F16_TASK2: Daily idempotent charge/obligation reminders, policy-expiry-day reminders, same-day scheduled-visit reminders; candidate-created/submitted events queued transactionally with candidate mutations. Payload/template allowlists; Bogota-local visit dates converted to UTC query bounds. Due financial rows target active lease tenants; paid/void charges, settled/void/charge-linked obligations, cancelled/superseded policies, ended leases and cancelled visits are excluded.
F16_TASK3: Protected `POST /cron/notifications`, timing-safe secret, exact-path/base-path CSRF exemption, no cron session, required SMTP environment, PHPMailer TLS/SSL, safe counts-only response, batch 50. Setup documented in docs/deployment/F16_SMTP_CRON.md.
F16_REVIEW: 1 fresh whole-phase review; 3 Important fixed (Bogota visit boundary, recoverable claim lease/attempt accounting, transactional candidate enqueue); 0 Critical. Minor findings deferred: reminder source scans are not paginated and recipient name/email retention has no explicit expiry policy.
F16_TASK4: Architecture freeze and candidate-route transport proof PASS. Focused regression suites PASS: Notifications 15/126; Candidates 84/805; Finance 93/1459; Maintenance 25/254; Insurance 24/252; CSRF 5/9; MigrationRunner 2/31; Lease security 3/49; Contract security 5/81. Total 256 tests/3066 assertions PASS. Composer strict, git diff check and PHP lint PASS. AppBootstrapTest 3/6 fail because GD is absent and installer preconditions reject the host; unrelated to F16. No full suite.
DONE: F15 locally certified, PR #18 open. F16 product head 7b5118b and docs-only closure commit complete; remote submission pending. PHPMailer ^7.1 resolves to 7.1.1.
RULING/FINDING: (1) F15 F14 schema wildcard narrowed to maintenance_requests; F15 plan-named MaintenanceRoutesTest.php was absent, so a focused file was created. (2) F15 staff may reprogram proposed/scheduled visit slots, with only current slots selectable. (3) F15 fresh review found 4 Important, all fixed; reviewer declined no behavior. (4) F16 SMTP errors are reduced to generic keys; attempt increments only at actual send; stale exhausted claims fail closed. (5) Reminder cadence follows the F16 choices above; candidate mail is only for created/submitted. (6) F16 minor review findings on pagination and recipient retention are recorded for follow-up, not required by the phase Critical/Important repair gate.
ENVIRONMENT: PHP 8.5.8; pdo_mysql/mbstring/dom/fileinfo present; gd absent; test DB connects.
NEXT_EXACT_ACTION: push F16 once, create stacked PR against impl/f15-agenda-visits-20261014, record remote check state without polling, then continue F17. Preserve F15 README conflict for final batch reconciliation. No PR merges.

F15: LOCAL CERTIFIED / PR CI PENDING RECONCILIATION
F16: PRODUCT COMPLETE / PR AND REMOTE CI PENDING
F17: PENDING
F18: PENDING
F19: PENDING
F20: PENDING

LOCAL_FULL_SUITE_RUNS: 0
REMOTE_CI_RUNS: 1 (F15 PR triggered; F16 PR not yet created)
FRESH_REVIEWS: 1
PRODUCTIVE_COMMITS: 3
DOC_COMMITS: 2
LOOP_ESCAPES: 0
CI_REPAIRS: 0
MERGES: NONE
BLOCKERS: F15 PR has a README.md conflict with newer main documentation; F16 may proceed under non-blocking CI rule. Host lacks GD, so three AppBootstrap installer assertions fail locally.
~~~
