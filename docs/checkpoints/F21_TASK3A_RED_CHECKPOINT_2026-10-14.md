# AlquilaPro — F21 Task 3a RED checkpoint

Date: 2026-10-14

## Authoritative execution state

- Repository: `sierraglobalcompany-rgb/AlquilaPro`
- Active implementation branch: `impl/f21-bonus-liquidation-20261014`
- PR: #26 — OPEN / NOT MERGED
- Active implementation head: `1346bfcd68c411327edfdf80161be87ba9643a62`
- Canonical base for F21: `main@748c9d3d70ed33bb216daab8322bf6a75573ff85`

## Closed work

- F21 Task 1 — schema + canonical lease read: CLOSED / GREEN.
  - CI #922: PASS PHP 8.3 / 8.4 / 8.5.
- F21 Task 2 — terminal good-conduct bonus: CLOSED / GREEN.
  - Final behavioral/test head before docs: `4e92974df2c590f5c48400f4dcf36ba88d1d4842`.
  - CI #929: PASS PHP 8.3 / 8.4 / 8.5.
  - PHP 8.3 full suite: 552 tests / 5806 assertions / 0 failures.
- Recovery/docs convergence head: `e27d2715b3f0afb326592fb55e2406f651300222`.
  - CI #931: PASS PHP 8.3 / 8.4 / 8.5.

## Current exact point — Task 3a Insurance owner RED

PRE-KISS finding:
- `InsuranceService::listPolicies()` is global.
- `InsuranceService::tenantPolicySummary()` is lease-scoped but intentionally masks/omits IDs and claim context.
- `InsuranceService::listClaims(int $policyId)` already owns claim reads.
- Therefore F21 preview must NOT duplicate Insurance SQL inside `TerminationService`.

RED commit:
- `1346bfcd68c411327edfdf80161be87ba9643a62`
- New test only: `tests/Integration/Insurance/InsuranceLeaseReadTest.php`
- Product files changed in this RED: 0.

RED contract:
1. require `InsuranceService::listPoliciesForLease(int $leaseId, string $today)`;
2. isolate strictly by `lease_id`;
3. preserve stable order `coverage_start DESC, id DESC`;
4. preserve canonical `findPolicy()` projection/status derivation;
5. invalid date must fail closed with `RuntimeException`;
6. invalid/non-positive lease id returns `[]`.

CI #932:
- PHP 8.3 reached full suite and FAILED exactly for the intended missing capability.
- 554 tests / 5806 assertions.
- Error 1: undefined method `InsuranceService::listPoliciesForLease()` in lease-scoped read test.
- Failure 1: invalid-date test received the same undefined-method Error instead of expected `RuntimeException`.
- Existing `InsurancePolicyService` tests remained GREEN.
- At checkpoint time, PHP 8.4 / 8.5 from #932 may still be finishing; they are not needed to prove the RED cause.

## Next exact action

Do NOT start Termination preview yet.

Implement only the minimal GREEN in `app/Insurance/InsuranceService.php`:
- add `listPoliciesForLease(int $leaseId, string $today): array`;
- reuse existing `findPolicy()`;
- query only IDs for the requested lease using existing canonical order;
- no new class, table, permission, route, dependency, claim SQL or transaction;
- preferably converge `tenantPolicySummary()` to reuse the new lease-scoped read and remove duplicate lease-policy query logic if that change stays minimal and tests remain green.

Then run CI and require PHP 8.3 / 8.4 / 8.5 GREEN before opening `TerminationPreviewTest`.

## Guardrails

KISS / Zero Noise / Anti-patch / TDD.
`LeaseService::end()` remains sole lifecycle writer.
`FinanceService` remains sole charge/payment authority.
`DocumentService` remains document/inventory authority.
`InsuranceService` remains insurance authority.
No merge of PR #26 until F21 is fully completed, reviewed and explicitly authorized through the project continuation flow.

## Recovery order

1. this checkpoint for the exact interruption point;
2. `docs/checkpoints/F21_LIVE_HANDOFF_2026-10-14.md`;
3. PR #26 body/head/CI;
4. `docs/checkpoints/F21_PRE_KISS_EXECUTION_GATE_2026-10-14.md`;
5. `docs/plans/F21_BONUS_LIQUIDATION_IMPLEMENTATION_PLAN.md`;
6. frozen `docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md`.
