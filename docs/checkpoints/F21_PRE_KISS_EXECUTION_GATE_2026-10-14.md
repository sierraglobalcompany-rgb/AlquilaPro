# F21 PRE-KISS Execution Gate — 2026-10-14

```text
PROJECT: AlquilaPro
PHASE: F21 — Bonus + Liquidation
BASE_MAIN: 748c9d3d70ed33bb216daab8322bf6a75573ff85
BRANCH: impl/f21-bonus-liquidation-20261014
F0→F20: CLOSED / VERIFIED / MERGED
F21_SPEC_PLAN: PRESENT ON MAIN
USER_GATE: `continua` authorizes the prepared next gate
IMPLEMENTATION: AUTHORIZED
MERGE_TO_MAIN: only after F21 closure/CI under the same established `continua` convention
```

## PRE-KISS rulings

1. **F20 contract revalidated.** `MembershipService::listForLease(int $leaseId)` remains canonical and exposes `charge_decision_required`. F21 will not add `hasPendingPaidChargeDecision()` or another predicate.
2. **Feature flag authority.** Use exactly `ALQUILAPRO_GOOD_CONDUCT_BONUS_ENABLED`; current environment configuration already uses the `ALQUILAPRO_*` convention. Default remains disabled.
3. **Lease read owner.** Task 1 adds only `LeaseService::find(int $leaseId): ?array`, implemented via existing `leaseSelect()` + `normalizeLease()`.
4. **Plan filename correction.** `tests/Integration/Leases/LeaseServiceTest.php` does not exist. The canonical lease lifecycle suite is `LeaseLifecycleTest.php`; no duplicate production owner will be created merely to satisfy an outdated filename in the plan.
5. **MigrationRunner owner.** `MigrationRunnerTest` keeps an explicit migration ledger/cleanup and must be advanced to migration 021 as part of Task 1. This is regression maintenance, not scope expansion.
6. **TerminationService dependencies.** Constructor fanout is accepted only because F21 is a composition service over existing canonical read authorities. Do not introduce repository/facade/container abstractions to hide those dependencies.
7. **No nested writer transactions.** F21 preview uses read-only public APIs. Finalization owns one transaction/lease lock and must not call domain methods that start independent write transactions.
8. **Insurance authority gap.** `InsuranceService::tenantPolicySummary()` deliberately omits policy IDs/claims, while `listPolicies()` is global. Before Task 3 GREEN, add the smallest read-only owner repair in `InsuranceService` (lease-scoped policy listing, with RED test) if still required. Do not duplicate insurance SQL inside `TerminationService`.
9. **Documents/Community/Finance/Obligations are sufficient.** Existing `DocumentService::listCurrentForLease()`, `CommunityService::listForLease()`, `FinanceService` lease/payment reads and `ObligationService::listForLease()` are reused; no parallel queries/services.
10. **TDD execution transport.** This assistant environment cannot clone GitHub (DNS unavailable). RED/GREEN evidence will therefore be obtained from GitHub PR CI: test-only RED commit first, observe expected failure, then minimal GREEN implementation and exact CI. No production code is written before the RED is observed.

## Task 1 execution decision

For remote-CI efficiency, Task 1's two independent RED assertions (migration 021 and canonical lease `find`) may be placed in the same test-only head and observed failing in one CI matrix. GREEN then implements only those two missing capabilities plus the necessary `MigrationRunnerTest` ledger update.

```text
TASK_1_PRODUCT_BUDGET:
  + database/migrations/021_create_lease_termination.sql
  + LeaseService::find()
  + tests only / migration regression maintenance
  + 0 other product behavior
```

## Hard stops

- no F22/F23 work while F21 active;
- no payout/refund/wallet/deposit/escrow/ledger/history/status-history systems;
- no second lease-ending route/writer;
- no second document/inventory authority;
- no automatic membership charge behavior change;
- no speculative architecture.
