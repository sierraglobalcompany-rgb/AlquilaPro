# F15→F20 Batch Execution Checkpoint

~~~text
PREPACKAGE_BASE: 37350105ac8c1806ae69194082098dbe230e5838
MAIN_START: ddb24048326e3fd67df919f1bd829fac939d8861
MAIN_START_AUDIT: PASS — descendant of PREPACKAGE_BASE; only the V4 master and design checkpoint changed
CURRENT_PHASE: F19
CURRENT_TASK: F19 Task 1 complete — incident schema + lifecycle; next T19.2
BRANCH: impl/f19-community-legal-20261014
BASE: 24f2da7dcf043ff4750434e18dcca66dfd37aab1 (F18 CLOSURE_HEAD)
CURRENT_HEAD: 24f2da7dcf043ff4750434e18dcca66dfd37aab1
WORKTREE: C:\Users\felip\.codex\worktrees\f15-f20-batch\AlquilaPro
WORKTREE_STATUS: F18 product complete with PR #21 (mergeable; CI intentionally unpolled); F19 Task 1 complete from exact F18 Closure Head; generated .phpunit.cache/ and app/graphify-out/ remain untracked
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
F16_STATUS: PRODUCT COMPLETE / STACKED PR #19 OPEN / MERGEABLE; checks intentionally unpolled until final batch review
F17_SCOPE: 1 whatsapp_actions table; 1 WhatsAppService under Notifications; 1 whatsapp.use permission; reuse Notifications routes; 0 provider/API/webhook/dependencies
F17_TASK1_RED: WhatsAppSchemaTest found no whatsapp_actions; WhatsAppServiceTest asserted the class absent; MigrationRunner expected 17 migrations and got 16.
F17_TASK1_GREEN: migration 017 + WhatsAppService snapshots normalized phone/message/HTTPS wa.me URL, validates exact template/context/variable allowlists, stores actor/time, and preserves first-open audit without delivery state. Notifications 22/179; MigrationRunner 2/32 PASS.
F17_TASK1_PRODUCT_HEAD: 19ad7ac (Task 1 code/tests + checkpoint/plan)
F17_TASK2_GREEN: Private SSR WhatsApp routes derive recipient/variables from existing candidate/lease/maintenance records; whatsapp.use with owner bypass and tenant hard deny; URL integrity checked before first-open audit; no SMTP dependency. Focused regressions: Notifications + Auth UserService + UI Shell = 53 tests/420 assertions PASS, no warnings.
F17_TASK3_LOCAL: WhatsAppSecurityRegressionTest freezes exact action schema, notification files, permission, runtime packages and route surface; integration regression denies anonymous prepare/open and tenant prepare/open. Focused Notifications + Auth UserService + UI Shell: 56 tests/445 assertions PASS. Composer strict, diff check, and PHP lint PASS.
F17_PRODUCT_HEAD: 56d221c61e3ec7ddf5ca710b022a44de112cd846
F17_REVIEW: fresh subagent review; 0 Critical/Important; 2 Minor deferred (latest 100 history rows without paging; staff actor names not rendered). Ruling: whatsapp.use is shared across allowlisted records because F17 specifies one feature permission and no per-record policy; risk if wrong is staff contacting records outside intended operational scope.
F17_CLOSURE_HEAD: d14d50f7e58fcf50dba9114e322c09fe0e9e9e66
F17_PR: #20 https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/20
F17_PR_BASE: impl/f16-reminders-email-20261014@b8c951a5359344fe441d8ab4be5dc86b07922c47
F17_PR_HEAD: d14d50f7e58fcf50dba9114e322c09fe0e9e9e66
F17_CI: triggered; intentionally unpolled until final batch check
F18_TASK1_RED: DocumentSchemaTest found no documents table; DocumentServiceTest failed because the core class was absent; MigrationRunner expected 18 migrations and found 17.
F18_TASK1_GREEN: migration 018 creates one XOR-scoped documents table with immutable snapshots and self-FK lineage; DocumentService validates type/scope/title, stores file/content versions, and returns current leaves/history. Focused schema/service/MigrationRunner: 6 tests/75 assertions PASS.
F18_TASK2_RED: upload/private-read tests confirmed missing, tampered, physical oversize and MIME spoof rejection; inventory test failed because createContentVersion did not normalize/bound inventory JSON. Symlink creation is unavailable on this Windows host and is explicitly skipped.
F18_TASK2_GREEN: inventory snapshots now enforce exact shape, <=200 items, bounded text and null/nonnegative integer quantities; existing privateFile checks physical cap before bounded read, inode/device, size, MIME and SHA-256. Focused DocumentServiceTest + DocumentStorageSecurityTest: 10 tests/77 assertions PASS, 1 symlink skip; no charge rows created.
F18_TASK3_RED: DocumentRoutesTest initially returned 404 for the missing staff route; tenant ownership test found an uncaptured DocumentService variable, corrected before final GREEN. Review pass also found partial-upload errors could fall through to content JSON; route now rejects every upload error except UPLOAD_ERR_NO_FILE and the regression proves 422/no insert.
F18_TASK3_GREEN: exactly documents.view/manage added; staff current/history/create/download surfaces wired; tenant page projects only current lease docs/inventories and current property manual. Trace proves LeaseService::findForUser runs before documents SQL and foreign lease requests issue no document query. Documents suite: 12 tests/108 assertions PASS with 1 Windows symlink skip; TenantPortalRoutesTest: 11 tests/245 assertions PASS.
F18_STORAGE: bootstrap default is a sibling private directory outside the app root; ALQUILAPRO_DOCUMENT_DIRECTORY can set the deployment path. .htaccess also denies /storage/ paths.
F18_TASK4_LOCAL: DocumentSecurityRegressionTest freezes one table/one service/two permissions/no parallel document systems and private route list; F8 regression guard updated for the DocumentService dependency while retaining lease ownership ordering. Final focal set (Documents, Auth UserService, F4 Properties security, F8 Lease security/tenant portal, Finance security, UI shell): 61 tests/767 assertions PASS, 1 Windows symlink skip. Composer strict, PHP lint, diff check PASS.
F18_REVIEW: fresh independent read-only review by Ampere; 0 Critical/Important and no Minor findings reported. Latest upload-error guard and regression were included. No review repair required.
F18_PRODUCT_HEAD: b0f06ac (feat(f18): add private document routes and tenant projections)
F18_PR_BASE: impl/f17-whatsapp-assisted-20261014@d14d50f7e58fcf50dba9114e322c09fe0e9e9e66
F18_CLOSURE_HEAD: 24f2da7dcf043ff4750434e18dcca66dfd37aab1
F18_PR: #21 https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/21 (OPEN / MERGEABLE)
F18_PR_HEAD: 24f2da7dcf043ff4750434e18dcca66dfd37aab1
F18_CI: triggered; intentionally unpolled until final batch check
F19_BASE: 24f2da7dcf043ff4750434e18dcca66dfd37aab1 (F18 CLOSURE_HEAD)
F19_TASK1_RED: CommunitySchemaTest found no community_incidents; MigrationRunnerTest expected 19 migrations and found 18; CommunityServiceTest setup failed because its table was absent.
F19_TASK1_GREEN: migration 019 + CommunityService lifecycle (row-locked forward transitions, bounded inputs, normalized staff records); MigrationRunner + Community schema/service focused set: 5 tests / 75 assertions PASS.
F19_TASK1_RULING: close allowed from open/reviewed; void allowed from any nonvoided state, including closed, as auditable terminal correction. Cost if wrong: closed records may be voided though policy intended immutable closure.
F19_TASK1_PRODUCT_HEAD: pending first natural product commit
F16_BRANCH: impl/f16-reminders-email-20261014
F16_BASE: c40cd33321739effdce0253f67dc372e412dccde
F16_PRODUCT_HEAD: 7b5118bc6b6ec29ceab8cfe3e2eb0901aafa3f7d
F16_CLOSURE_HEAD: b8c951a5359344fe441d8ab4be5dc86b07922c47
F16_PR: #19 https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/19
F16_PR_BASE: impl/f15-agenda-visits-20261014@c40cd33321739effdce0253f67dc372e412dccde
F16_PR_HEAD: b8c951a5359344fe441d8ab4be5dc86b07922c47
F16_PR_CI: triggered; run ID/merge-ref/counts pending final batch check
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
NEXT_EXACT_ACTION: T19.2 — write focused RED regressions for the three-way document scope, incident evidence ownership/versioning, and legal notice content; then extend existing migration 019 and DocumentService only.

F15: LOCAL CERTIFIED / PR CI PENDING RECONCILIATION
F16: PRODUCT COMPLETE / PR OPEN / REMOTE CI PENDING FINAL CHECK
F17: PRODUCT COMPLETE / PR #20 OPEN / REMOTE CI PENDING FINAL CHECK
F18: PRODUCT COMPLETE / PR #21 OPEN / MERGEABLE / CI PENDING FINAL CHECK
F19: TASK 1 COMPLETE / TASK 2 PENDING
F20: PENDING

LOCAL_FULL_SUITE_RUNS: 0
REMOTE_CI_RUNS: 4 (F15–F18 PRs triggered; final check pending)
FRESH_REVIEWS: 4
PRODUCTIVE_COMMITS: 7
DOC_COMMITS: 4
LOOP_ESCAPES: 0
CI_REPAIRS: 0
MERGES: NONE
BLOCKERS: F15 PR has a README.md conflict with newer main documentation; F16 may proceed under non-blocking CI rule. Host lacks GD, so three AppBootstrap installer assertions fail locally.
~~~
