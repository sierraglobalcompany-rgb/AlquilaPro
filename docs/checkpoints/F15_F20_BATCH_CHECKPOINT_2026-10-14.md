# F15→F20 Batch Execution Checkpoint

```text
PREPACKAGE_BASE: 37350105ac8c1806ae69194082098dbe230e5838
MAIN_START: ddb24048326e3fd67df919f1bd829fac939d8861
MAIN_START_AUDIT: PASS — descendant of PREPACKAGE_BASE; only the V4 master and design checkpoint changed
CURRENT_PHASE: F15
CURRENT_TASK: F15_CLOSE / Task 4 — fresh review and phase certification
BRANCH: impl/f15-agenda-visits-20261014
BASE: ddb24048326e3fd67df919f1bd829fac939d8861
CURRENT_HEAD: ddb24048326e3fd67df919f1bd829fac939d8861
WORKTREE: C:\Users\felip\.codex\worktrees\f15-f20-batch\AlquilaPro
WORKTREE_STATUS: F15 product/test files and this checkpoint modified; PHPUnit cache untracked and excluded; no commit yet
DONE: T15.1–T15.3 complete. Migration 015 adds one `maintenance_visits` table; one `VisitService` owns lifecycle and row locks. Staff routes/UI reuse `maintenance.view/manage`; bootstrap wires one service. Tenant selection verifies lease ownership before request/visit lookup and offers only current proposed slots.
RED: F15 schema test failed with the visit table absent; service tests errored with VisitService absent. T15.2 proposal-reprogram test failed on the old transition rule; agenda route and view-only history tests failed before their UI existed. T15.3 tenant portal test failed because no selection UI existed.
GREEN: F15 schema PASS (1/8); service PASS (5/31); concurrency PASS (4/45); staff/tenant portal and CSRF gate: Maintenance PASS (22 tests/223 assertions), TenantPortal PASS (10/220), CsrfRoutes PASS (5/9). Tenant ownership trace confirms `leases` lookup precedes request/visit reads; foreign lease makes no request/visit query. Security regression confirms unproposed times are rejected without mutation.
RULING/FINDING: F14 schema query was narrowed from the broad `maintenance_*` wildcard to `maintenance_requests`; F15 schema test asserts the combined allowed set. The plan named `MaintenanceRoutesTest.php`, which did not exist, so created the focused file; existing F14 route coverage remains in `TenantPortalRoutesTest.php`. Cost if wrong for this test-file ruling: shared tenant/staff integration coverage may need a second focused assertion. Environment: PHP 8.5.8, pdo_mysql/mbstring/dom/fileinfo present, gd absent; Composer strict validation and test-DB connection passed.
NEXT_EXACT_ACTION: commit the F15 product/test package with this checkpoint, conduct one fresh whole-phase review, repair only Critical/Important findings with focused reruns, run strict Composer validation and diff check, then record PRODUCT_HEAD and create a docs-only closure commit.

F15: IN_PROGRESS — T15.1, T15.2, T15.3 complete; Task 4 certification in progress
F16: PENDING
F17: PENDING
F18: PENDING
F19: PENDING
F20: PENDING

LOCAL_FULL_SUITE_RUNS: 0
REMOTE_CI_RUNS: 0
FRESH_REVIEWS: 0
PRODUCTIVE_COMMITS: 0
DOC_COMMITS: 0
LOOP_ESCAPES: 0
CI_REPAIRS: 0
MERGES: NONE
BLOCKERS: NONE at start; gd absence recorded for impact assessment before F18
```
