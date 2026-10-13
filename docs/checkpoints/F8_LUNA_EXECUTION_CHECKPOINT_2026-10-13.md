# F8 Luna Execution Checkpoint — 2026-10-13

```text
CHECKPOINT_RESULT: PASS
PHASE: 7 — PRODUCT DIFF REVIEW / TASK 8 QA
PHASE_STATUS: PRODUCT VERIFIED; CLOSURE DOCUMENTATION PENDING
BRANCH: impl/f8-tenant-lease-portal-20261013
RECOVERY_BASE: f4a7e3f4d5ec7dc9cdc0f3a7e78777f98c85461d (origin/main)
START_HEAD: 416218fc43ae363902d99eb82cf793a932d9f74b
VERIFIED_PREVIOUS_PRODUCT_HEAD: 827daabded3539b3488ade77e5a99b9b50105cea
PRODUCT_HEAD: 5569e07ec45170c95fc981c7f00254a637aa5e35
CURRENT_HEAD: 5569e07ec45170c95fc981c7f00254a637aa5e35 (before this checkpoint commit)
PRODUCT_CI_RUN_ID: 38440025461
PRODUCT_CI_SHA: 5569e07ec45170c95fc981c7f00254a637aa5e35
PRODUCT_CI: PHP 8.3/8.4/8.5 PASS
PHP_8_3_TESTS: 310
PHP_8_3_ASSERTIONS: 2611
PHP_8_3_FAILURES: 0
PHP_8_3_ERRORS: 0
LOCAL_PHP: 8.3.33 full suite 310/2611 PASS; 8.5.8 focused 57/591 PASS
COMPOSER_VALIDATE: PASS (composer validate --strict)
KISS_STATUS: PASS
ZERO_NOISE_STATUS: PASS
PATCH_STACKING: NONE
BLOCKERS: NONE
MERGE_STATUS: NOT AUTHORIZED / NOT ATTEMPTED
```

## Recovery and verified work

- Recovered the expected implementation branch from Task 7 checkpoint HEAD `416218fc43ae363902d99eb82cf793a932d9f74b`; `origin/main` was `f4a7e3f4d5ec7dc9cdc0f3a7e78777f98c85461d`.
- Prior product head `827daabded3539b3488ade77e5a99b9b50105cea` had CI `38431546341` green on PHP 8.3/8.4/8.5.
- Focused F8/Auth/Properties/Shell suite after the repair: 57 tests, 591 assertions, 0 failures/errors (PHP 8.5.8). Full local suite: 310 tests, 2611 assertions, 0 failures/errors (PHP 8.3.33 in the isolated QA image). Composer validation passes.
- Product CI `38440025461` is tied exactly to `PRODUCT_HEAD`; all three PHP jobs succeeded. The PHP 8.3 job reports 310 tests and 2611 assertions.

## Repair loop — unit status race

- **Symptom:** An editor could check for an active lease before activation committed, wait on its later `UPDATE units`, then overwrite the activation's `occupied` status.
- **Root cause:** `PropertyService::updateUnit()` performed its unit read and active-lease check outside a transaction and without serializing on the canonical unit row.
- **Owning authority:** Existing `PropertyService::updateUnit()`; activation already serializes on `units.id`.
- **RED:** Added `testUnitEditorSerializesStatusCheckWithLeaseActivation()`; the pre-fix implementation failed because the edit had no transaction/row lock.
- **GREEN:** The editor now owns a transaction when needed, locks the unit row first, checks active lease state while serialized, updates and commits under the lock, and rolls back on failure. Existing callers use the service without an ambient transaction.
- **Why this is root-cause repair:** It converges the editor on activation's existing unit-row authority; it introduces no new class, table, status, or parallel guard.
- Product repair commit: `5569e07ec45170c95fc981c7f00254a637aa5e35` (`fix(f8): serialize unit edits with lease activation`).

## Two Laws and forensic product review

```text
NEW_TABLES: 1 (leases)
NEW_DOMAIN_CLASSES: 1 (LeaseService)
NEW_F8_ROUTE_FILES: 1 (app/Leases/routes.php)
F8_ROUTES: 5 (2 admin POST, 3 tenant GET)
NEW_PERMISSIONS: 0
NEW_PUBLIC_ROUTES: 0
NEW_DIRECT_RUNTIME_DEPENDENCIES: 0
NEW_AUTH_SYSTEMS: 0
NEW_SESSION_SYSTEMS: 0
TENANTS_TABLE: 0
TENANT_USERS_TABLE: 0
TENANT_SESSIONS_TABLE: 0
LEASE_UNITS_TABLE: 0
LEASE_STATUS_COLUMN: 0
```

- The single `leases` row references the existing contract, tenant user, and actor users; contract snapshot data, unit identity/status, and PDFs stay with their existing authorities.
- Activation is one transaction: contract then unit lock, eligibility/duplicate checks, tenant identity and lease insert, guarded occupancy update, commit; credentials are returned only after commit. Ending locks one lease, records actor/time once, and does not release the unit.
- Tenant access reuses the existing session, forced password change, role checks, CSRF middleware, and `ContractService::privatePdf()`. Tenant ownership is constrained in SQL and foreign IDs return 404. The PDF route verifies signature, size, and SHA-256 and uses private no-store headers.
- Tenant views escape dynamic output; the shared layout routes tenant Home to `/tenant` and omits Apps/admin navigation. No physical PDF filename or plaintext credential is persisted.
- Dependency diff is empty. The product diff adds no parallel auth/session, permission, public route, duplicate lease economics/dates/unit/candidate, future module, or F9 surface. Existing F7 PDF header behavior is reused.
- Existing failure-only host runs were environment-specific (Windows GD/path separators and one incorrect Docker DB host); the authoritative Linux full suite and exact-HEAD CI pass above supersede them.

## Authorization ruling and next action

- The plan footer still said `F8_PLAN: USER REVIEW REQUIRED` / `F8_IMPLEMENTATION: NOT AUTHORIZED`. SPEC gate commit `044cb9b` and the current direct user request (`ejecuta el prompt`) authorize native execution. The footer is stale historical text and will be corrected in closure docs; no design decision is being changed.
- Product review and Product CI are complete. Next: converge SPEC, plan, and README; create the canonical closure checkpoint; run fresh CI on the exact closure HEAD; open/update the review-ready PR, audit its exact head and comments, then stop before merge.

```text
NEXT_EXACT_ACTION: Commit canonical F8 closure documentation, then verify fresh exact-HEAD CI.
MERGE: NOT AUTHORIZED
```
