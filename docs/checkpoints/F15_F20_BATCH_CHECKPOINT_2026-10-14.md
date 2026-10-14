# F15→F20 Batch Execution Checkpoint

~~~text
PREPACKAGE_BASE: 37350105ac8c1806ae69194082098dbe230e5838
MAIN_START: ddb24048326e3fd67df919f1bd829fac939d8861
MAIN_START_AUDIT: PASS — descendant of PREPACKAGE_BASE; only the V4 master and design checkpoint changed
CURRENT_PHASE: F15
CURRENT_TASK: F15_CLOSE / docs-only closure complete next; then push and open PR
BRANCH: impl/f15-agenda-visits-20261014
BASE: ddb24048326e3fd67df919f1bd829fac939d8861
PRODUCT_HEAD: d2c23d44aeefd28ba70fce9ad1d8679d87a3275e
CURRENT_HEAD: d2c23d44aeefd28ba70fce9ad1d8679d87a3275e (docs-only closure commit follows)
CLOSURE_HEAD: pending docs-only closure commit; capture exact SHA in next F16 checkpoint update
PR_BASE: main; observed origin/main cc4eabd5910d02dcf792209dcc15d749b581b72b
PR: pending
PR_HEAD: pending closure commit
CI_RUN: pending; no polling before F20
CI_MERGE_REF: pending
PHP_MATRIX: PHP 8.3/8.4/8.5 pending
WORKTREE: C:\Users\felip\.codex\worktrees\f15-f20-batch\AlquilaPro
WORKTREE_STATUS: only README, SPEC, checkpoint closure documents modified; PHPUnit cache untracked and excluded; product files unchanged since PRODUCT_HEAD
DONE: T15.1–T15.3 complete. Migration 015 adds one maintenance_visits table; one VisitService owns lifecycle and row locks. Staff routes/UI reuse maintenance.view/manage; bootstrap wires one service. Tenant selection verifies lease ownership before request/visit lookup and offers only current proposed slots. Review repair: blank optional technician fields normalize to null; scheduling mutations recheck slot time after locks; terminal F14 transitions reject while active visits remain; migration runner fixture expects and cleans migration 015.
RED: F15 schema test failed with the visit table absent; service tests errored with VisitService absent. T15.2 proposal-reprogram test failed on the old transition rule; agenda route and view-only history tests failed before their UI existed. T15.3 tenant portal test failed because no selection UI existed. Review repairs: blank staff fields returned 422; expired slot remained selectable after request-lock wait; terminal transition left a scheduled visit; migration runner expected 14 while migration 015 applied.
GREEN: Review repair red/green: staff blank-field route red 422 then green 303 (1 test/6 assertions); expired-slot lock-wait red scheduled stale slot then green rejection across select/reprogram/propose/direct (1/20); terminal request red left active visit then green blocks resolve/cancel until visit cancelled (1/5); migration runner red expected 14 vs 15 then green (2/30). Final focused gates: Maintenance F14/F15 PASS (25/252), TenantPortal PASS (10/220), CsrfRoutes PASS (5/9), MigrationRunner PASS (2/30). Composer strict validation PASS; git diff --check PASS. No local full suite run.
RULING/FINDING: (1) Narrowed F14 schema query from broad maintenance_* wildcard to maintenance_requests; F15 schema test asserts the complete allowed set. (2) Plan-named MaintenanceRoutesTest.php was absent, so created the focused file; existing F14 route coverage remains in TenantPortalRoutesTest.php. Cost if wrong: shared-route behavior may need another focused assertion. (3) Staff may reprogram proposed and scheduled visits; replacement proposal stays proposed, with locks preventing stale selection. Cost if wrong: tenants may see replaced options, but only the current set remains selectable. (4) Fresh whole-phase review found 4 Important, 0 Critical, 0 Minor; all four were repaired in one pass, followed by focused reruns; reviewer declined no behavior and ran no tests. Terminal F14 closure is rejected until active visits are completed/cancelled. Environment: PHP 8.5.8; pdo_mysql/mbstring/dom/fileinfo present; gd absent; test DB connects.
NEXT_EXACT_ACTION: make the single docs-only closure commit and verify Product→Closure changes are docs only; push branch once, open PR against main, attach it, then create F16 from F15 CLOSURE_HEAD after local gate without waiting for CI.

F15: LOCAL CERTIFIED / PR CI PENDING
F16: PENDING
F17: PENDING
F18: PENDING
F19: PENDING
F20: PENDING

LOCAL_FULL_SUITE_RUNS: 0
REMOTE_CI_RUNS: 0
FRESH_REVIEWS: 1
PRODUCTIVE_COMMITS: 2
DOC_COMMITS: 1 (closure commit pending)
LOOP_ESCAPES: 0
CI_REPAIRS: 0
MERGES: NONE
BLOCKERS: NONE; gd absence recorded for impact assessment before F18
~~~