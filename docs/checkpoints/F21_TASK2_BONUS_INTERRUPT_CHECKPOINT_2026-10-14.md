# AlquilaPro — F21 Task 2 Bonus — Interruption Checkpoint

**Date:** 2026-10-14  
**Repository:** `sierraglobalcompany-rgb/AlquilaPro`  
**Branch:** `impl/f21-bonus-liquidation-20261014`  
**PR:** #26 — `F21: bonus and immutable lease liquidation`  
**Base:** `main@748c9d3d70ed33bb216daab8322bf6a75573ff85`  
**Product HEAD before this docs checkpoint:** `1dd9784bf03e2188ff003c07cf96656bcab20bdc`

## Stop state

User explicitly requested: **create checkpoint and stop**.

Do **not** continue Task 2 repair, do **not** open Task 3, do **not** merge PR #26, and do **not** start F22+ from this checkpoint.

## Canonical status

- F21 Task 1: **GREEN / previously closed**.
- F21 Task 2 RED was defined test-first.
- Initial RED commit `0ef9cb14b5f0ee79ebf6096e47938d9d858f9df4` exposed a test-fixture defect (`HY093` from duplicated native PDO placeholder).
- Test-only fixture repair: `3108316726691ff23177e2356112ff521a1becbb`.
- CI #924 then produced the **valid RED**: bonus tests failed because `AlquilaPro\Leases\TerminationService` did not yet exist.
- Minimal Task 2 GREEN product commit: `1dd9784bf03e2188ff003c07cf96656bcab20bdc`.
- Product change in that GREEN: exactly one new class, `app/Leases/TerminationService.php`.
- No Task 2 routes, permissions, migrations, new tables, runtime dependencies, provider integrations, or finance writes were added.

## Task 2 behavioral evidence

On CI #925 / PHP 8.3, all six `TerminationBonus` tests passed:

1. feature-disabled mutation fails closed;
2. pending bonus creates once, normalizes money/note, and remains editable only while pending;
3. invalid amount, oversized note, and missing lease fail closed;
4. `approved` and `denied` are terminal and do not write charges/payments;
5. decision requires a pending bonus and exact `approved|denied` value;
6. concurrent terminal decisions produce one winner.

Therefore the Task 2 bonus behavior is **functionally GREEN in the focal test set inside the full suite**, but Task 2 is **NOT CLOSED** because the full CI is still red.

## Current CI blocker — do not patch blindly

CI #925 on PHP 8.3 finished with:

- `551` tests
- `5775` assertions
- `1` failure

The only observed failure is:

`tests/Integration/Leases/LeaseSecurityRegressionTest.php:33`

The test `F 8 keeps single lease architecture without parallel tenant stacks` asserts an exact list containing only:

- `app/Leases/LeaseService.php`

The new approved F21 authority adds:

- `app/Leases/TerminationService.php`

So the current blocker is an **older structural regression test whose exact file-list contract conflicts with the approved F21 architecture**. The six new F21 bonus tests themselves are green.

### Correct next action on resume

Apply the anti-patch rule before editing:

1. inspect `LeaseSecurityRegressionTest.php` and the F21 SPEC/plan authority;
2. prove whether the F8 test is stale/over-coupled rather than weakening a valid security invariant;
3. if confirmed, converge that test to assert the intended invariant (no parallel tenant/lease architecture) while explicitly allowing the approved `TerminationService` authority;
4. change only the owning regression test unless new evidence shows a product defect;
5. rerun CI matrix PHP 8.3/8.4/8.5;
6. close Task 2 only after full GREEN plus fresh review/KISS/Zero-Noise/anti-patch gate.

Do **not** add a second service, adapter, facade, compatibility file, duplicate namespace, or fake filename to satisfy the old exact-file assertion.

## PRE-KISS debt to audit before Task 3

`TerminationService` currently receives the future F21 collaborators (`LeaseService`, `FinanceService`, `ObligationService`, `InsuranceService`, `DocumentService`, `CommunityService`, `MembershipService`) plus PDO and the bonus feature gate, but Task 2 bonus methods currently use only the persistence/gate portion.

Before Task 3 liquidation work, re-run the planned PRE-KISS constructor-fanout review. Do not preserve unused constructor fanout merely because it exists. Converge dependencies to the minimum authority needed by the final F21 design, while avoiding nested transaction ownership.

## Task 3+ remains unopened

Not started in this checkpoint:

- liquidation preview/blockers;
- immutable final liquidation;
- liquidation concurrency;
- routes/UI/security for F21;
- final convergence/checkpoint.

`LeaseService::end()` remains the sole lease lifecycle writer. No liquidation implementation has been added yet.

## Known remote branch noise

During Task 2 preparation, auxiliary branches were accidentally created pointing at an existing commit and were never used for product development. Known names include:

- `scratch/f21-task2-red-build`
- `scratch/f21-task2-red-build2`
- `noop-should-not-use`
- `noop`
- `stop`

They contain no intentional product work and must **not** become authorities or bases. The current connector did not expose branch deletion during the operation. Clean them later with an authorized branch-delete path, after verifying their tips contain no unique commits.

## Non-negotiable laws on resume

- KISS / simple architecture.
- Zero Noise: eliminate, merge/converge, simplify, improve, then add.
- Anti-patch: root cause and owning authority before repair.
- TDD: RED verified before GREEN; no blind implementation.
- No merge without explicit user authorization.
- One task at a time; checkpoint before advancing.

## Resume pointer

Resume from:

**F21 Task 2 — full CI repair gate**

First inspect the stale `LeaseSecurityRegressionTest` exact-file assertion against approved F21 authority. Do not reopen already-passed bonus behavior unless evidence requires it.
