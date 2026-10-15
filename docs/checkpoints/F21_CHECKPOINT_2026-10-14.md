# F21 — Final Audited Checkpoint

**Date:** 2026-10-14  
**Repository:** `sierraglobalcompany-rgb/AlquilaPro`  
**PR:** #26 — `F21: bonus and immutable lease liquidation`  
**Base:** `main@748c9d3d70ed33bb216daab8322bf6a75573ff85`  
**Certified product head:** `0a7438a5659623a42cb00c283e7edbe6b12337d0`  
**State at checkpoint creation:** product GREEN and forensic audit PASS; this documentation commit still requires the final PR CI matrix before merge.

## Scope delivered

F21 implements the frozen V1 contract for:

- optional good-conduct bonus behind `ALQUILAPRO_GOOD_CONDUCT_BONUS_ENABLED`, default OFF;
- derived lease termination preview using existing domain authorities;
- critical blocker classification;
- one immutable final liquidation snapshot per lease;
- controlled staff and tenant SSR surfaces;
- no parallel lease-ending workflow.

## Architecture budget — PASS

- New tables: exactly 2 — `good_conduct_bonuses`, `lease_liquidations`.
- New F21 domain classes: exactly 1 — `TerminationService`.
- New permissions: 0.
- New runtime dependencies: 0.
- New public routes: 0.
- No ledger/payment/document/inventory/history/status-history persistence added by F21.
- Existing `app/Leases/routes.php` remains the single lease route tree.
- `LeaseService::end()` remains the sole lease lifecycle writer.
- `FinanceService`, `DocumentService`, `InsuranceService`, `CommunityService`, `ObligationService` and `MembershipService` remain their domain authorities.

## Canonical F21 persistence

`database/migrations/021_create_lease_termination.sql` creates only:

1. `good_conduct_bonuses`
   - unique one-row-per-lease authority;
   - amount `> 0`;
   - decision `NULL|approved|denied`;
   - terminal decisions are not reopened.

2. `lease_liquidations`
   - unique one-row-per-lease authority;
   - immutable append-once snapshot;
   - no status/draft/update/history model.

## Bonus workflow — PASS

- Feature disabled by default.
- Pending amount/note may be corrected.
- `approved|denied` is terminal.
- Decision-vs-decision concurrency: one winner.
- Update-vs-decision concurrency: serialized; no post-decision mutation.
- No charge/payment/refund/credit writer is invoked by the bonus workflow.

## Preview and blockers — PASS

Preview is derived from canonical service APIs and persists nothing.

Critical blockers are exactly the frozen F21 set implemented by the current scope:

- payment report pending reconciliation;
- paid F20 membership with `charge_decision_required === true`;
- existing bonus with pending decision;
- initial inventory present without final inventory;
- active lease / existing liquidation are enforced during finalization.

Outstanding balance, open community incident and open insurance claim remain evidence, not blockers by themselves.

The F20 blocker uses `MembershipService::listForLease()` directly; no duplicate F20 predicate/API was introduced.

## Final liquidation — PASS

`TerminationService::finalizeLiquidation()`:

1. begins one transaction;
2. locks canonical lease row;
3. requires `ended_at IS NOT NULL`;
4. rejects an existing final liquidation;
5. recomputes the canonical preview/blockers on the same PDO connection;
6. normalizes bounded handover/summary input;
7. stores one deterministic `schema_version = 1` snapshot;
8. commits.

It does not write `leases.ended_at`, unit status, charges or payments.

Concurrency `finalize vs finalize` has exactly one stable winner.

## Routes / permissions / privacy — PASS

Approved surfaces live only in the existing Leases route tree:

- `GET /admin/leases/{id}/termination` — `contracts.view`; finance detail requires `finance.view`.
- `POST /admin/leases/{id}/bonus` — existing manage guard + feature gate + global CSRF.
- `POST /admin/leases/{id}/bonus/decision` — existing manage guard + global CSRF.
- existing `POST /admin/leases/{id}/end` — unchanged lifecycle writer.
- `POST /admin/leases/{id}/liquidation` — existing manage guard plus `finance.view` + global CSRF.
- `GET /tenant/leases/{id}/liquidation` — authenticated tenant, own lease only, final liquidation only.

Tenant ownership is resolved through `LeaseService::findForUser()` before any liquidation lookup. Tenant liquidation projection excludes actor IDs, finalization audit context, private document metadata and cross-lease data.

Staff without `finance.view` receives no financial preview detail. A finalized staff view renders only safe liquidation status/date/summary outside the finance gate; it does not render the stored financial snapshot through the raw liquidation row.

## Feature flag — PASS

Production reads exactly one key:

`ALQUILAPRO_GOOD_CONDUCT_BONUS_ENABLED`

It is parsed with `FILTER_VALIDATE_BOOLEAN`; missing/empty configuration evaluates false. Bootstrap also defaults `good_conduct_bonus_enabled` to false.

## Task 5 certification — PASS

Certified product head: `0a7438a5659623a42cb00c283e7edbe6b12337d0`.

CI #969 / run `38670144761`:

- PHP 8.3 — SUCCESS;
- PHP 8.4 — SUCCESS;
- PHP 8.5 — SUCCESS.

PHP 8.3 full suite:

- 573 tests;
- 6024 assertions;
- 0 failures;
- 0 errors;
- 0 skips.

All six `Termination Routes` cases are active and GREEN, including authorization/redaction, feature gate/CSRF, no-auto-end liquidation, tenant ownership-first and canonical links.

## Forensic audit — 10 passes

1. **Schema budget:** PASS — exactly two F21 tables, no shadow persistence.
2. **Authority ownership:** PASS — F21 composes existing authorities and does not replace them.
3. **Lease lifecycle:** PASS — only `LeaseService::end()` writes lifecycle termination.
4. **Financial boundary:** PASS — F21 does not create/write charges or payments; finance detail remains permission-gated.
5. **Blocker semantics:** PASS — canonical projections, no duplicate F20 membership predicate.
6. **Immutability/concurrency:** PASS — one liquidation row, terminal bonus, required races covered.
7. **Tenant isolation/privacy:** PASS — ownership before child lookup, safe tenant projection.
8. **Auth/CSRF/permissions:** PASS — shared existing guards/middleware, no new permission namespace.
9. **KISS / Zero Noise / Anti-patch:** PASS — one composition service, one route tree, no runtime framework/provider added; test-only stale structural freezes were converged rather than patched around.
10. **Regression/CI:** PASS on certified product head — full remote matrix green on PHP 8.3/8.4/8.5.

## Reviewed non-blocking points

- `TerminationService` is comparatively large because it owns F21 composition plus explicit snapshot evidence projections. Splitting it would add classes/abstractions without removing a second source of truth, so no architecture split is justified in V1.
- The frozen SPEC permits later real monetary movement to remain under `FinanceService`; F21 does not invent a new rule forbidding all finance activity after a liquidation snapshot. No speculative Finance patch was added during final audit.
- Historical task/recovery checkpoints are retained as audit/recovery evidence; they are not runtime architecture and are superseded for current status by this checkpoint and GitHub/CI facts.

## Final gate remaining

This checkpoint commit is documentation-only and changes the PR head after product CI #969. Therefore the PR must receive one fresh CI matrix on the checkpoint head. Merge is allowed only if PHP 8.3, 8.4 and 8.5 all conclude `success` and the PR head/base remain unchanged and mergeable.

After that final green gate, F21 is ready to merge into `main`; F22 PRE-KISS is the next phase.
