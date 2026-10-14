# F9 Preflight — Cargos + Cartera — 2026-10-13

```text
F9_STATUS: PREFLIGHT BASELINE COMPLETE / SPEC STATUS CONVERGENCE PENDING / PRODUCT NOT STARTED
REPOSITORY: sierraglobalcompany-rgb/AlquilaPro
BRANCH: impl/f9-charges-portfolio-20261013
F8_INTEGRATED_BASE_SHA: 89811cbba5ae922bab00b50dd02af08dbaa1cb8b
F8_MERGE_SHA: a1b50f0d1c6556531626fd35cba0668bd5c4029f
F8_STATUS: CLOSED / VERIFIED / MERGED
F9_SPEC: APPROVED BY USER / IMPORTED FROM DESIGN BRANCH
F9_PLAN: APPROVED BY USER / IMPORTED FROM DESIGN BRANCH
F9_PRODUCT_IMPLEMENTATION: NOT STARTED
MERGE_AUTHORIZED: NO
```

## Verified F8 integration points

- One canonical `leases` table remains the F8 persistence authority.
- One `LeaseService` remains the lease domain authority.
- Tenant ownership is resolved from the authenticated user through `leases.user_id`; foreign lease reads fail before lateral data composition.
- `app/Leases/routes.php` remains the tenant route composition point.
- `resources/views/tenant_lease_detail.php` remains the tenant lease detail view.
- `resources/views/contract_detail.php` remains the administrative contract/lease context.
- `Permissions::allows()` still fails closed for `role=tenant` after the owner bypass.
- F9 therefore does not require a design change before Task 1.

## Imported approved documents

Only the approved F9 design documents were ported from the historical docs branch onto the F8-integrated implementation branch:

- `docs/specs/F9_CHARGES_PORTFOLIO_SPEC.md`
- `docs/superpowers/plans/2026-10-13-f9-charges-portfolio.md`

No product commit from the historical F7-based docs branch was copied.

The imported SPEC still contains its historical design-branch banner stating that implementation was blocked on F8. That text is now stale because F8 is merged. It MUST be converged on this implementation branch before the first F9 product RED. This is a documentation state transition only, not a design reopening.

## Architecture budget to preserve

```text
NEW_TABLES: 1
  charges
NEW_DOMAIN_CLASSES: 1
  FinanceService
NEW_F9_ROUTE_FILES: 1 maximum
NEW_PERMISSIONS: 2
  finance.view
  finance.manage
NEW_PUBLIC_ROUTES: 0
NEW_DIRECT_RUNTIME_DEPENDENCIES: 0
NEW_PAYMENT_TABLES: 0
NEW_CRON_JOBS: 0
```

## Two Laws

```text
LAW_1_KISS: PASS
LAW_2_ZERO_NOISE: PASS
PATCH_STACKING: NONE
```

## Next exact action

Codex Luna may now execute the approved F9 master prompt against `impl/f9-charges-portfolio-20261013`.

First complete the remaining Task 0 documentation convergence:

1. update the F9 SPEC banner/gate to `APPROVED / IMPLEMENTATION IN PROGRESS`;
2. record `F8_INTEGRATED_BASE_SHA = 89811cbba5ae922bab00b50dd02af08dbaa1cb8b`;
3. keep `MERGE_AUTHORIZED = NO`;
4. commit that documentation-only state transition.

Then open **Task 1 RED** for the single `charges` schema, verify the expected RED, checkpoint, and continue according to the approved autonomous plan.

Do not start F10 product implementation. Do not merge F9 without a later explicit merge gate.
