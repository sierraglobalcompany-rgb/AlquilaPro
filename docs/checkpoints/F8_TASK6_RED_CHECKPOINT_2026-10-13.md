# F8 Task 6 RED Checkpoint — 2026-10-13

## State

- Phase: F8 — Tenant + lease + private portal using the single existing auth system.
- Task 5: GREEN / VERIFIED.
- Task 6: RED / VERIFIED / OPEN.
- Task 6 GREEN: NOT STARTED.
- Task 7: NOT STARTED.
- Merge: NOT AUTHORIZED / NOT ATTEMPTED.

## Continuity

- Branch: `impl/f8-tenant-lease-portal-20261013`
- Previous Task 5 checkpoint commit: `d0222c060713949b309d74ec44c0fc98dbbd1ac4`
- Task 6 RED commit: `a2f96ca96e17e3a5628fcca95cc57148e84ca143`
- RED commit message: `test(f8): define private tenant portal contract`
- Task 6 RED changed only: `tests/Integration/Leases/TenantPortalRoutesTest.php`
- No Task 6 production file was modified in the RED block.

## Canonical Task 6 Contract

Task 6 remains limited to the approved F8 plan:

- `LeaseService::listForUser(int $userId): array` — ACTIVE first, then newest activation.
- `LeaseService::findForUser(int $leaseId, int $userId): ?array` — ownership enforced by SQL.
- `GET /tenant`
- `GET /tenant/leases/{id}`
- `GET /tenant/leases/{id}/contract`
- Tenant session `user.id` is the only ownership authority.
- Foreign/missing lease detail or PDF must fail as 404.
- PDF must delegate to existing F7 private contract PDF evidence and retain private/no-store/nosniff/integrity behavior without exposing the physical filename.
- Apartment/parking context is derived from existing canonical property/unit/contract tables.
- No root-navigation changes in Task 6; those belong to Task 7.

## RED Coverage Added

`TenantPortalRoutesTest` now specifies:

1. guest `/tenant` requires login;
2. forced-password tenant is redirected to the existing password-change surface;
3. authenticated non-tenant is forbidden;
4. tenant home exposes only owned leases;
5. ACTIVE lease renders before ended history;
6. base path is preserved;
7. stored values are escaped against XSS;
8. apartment and parking contextual fields are represented from canonical data;
9. foreign/missing lease detail is 404;
10. owned PDF is attachment + private no-store + nosniff + exact body/length and never exposes `pdf_stored_name`;
11. foreign lease PDF is 404;
12. tampered or missing private PDF is 404.

## RED Verification

GitHub Actions run: `38427649416`

Matrix result for exact RED HEAD `a2f96ca96e17e3a5628fcca95cc57148e84ca143`:

- PHP 8.3: FAILURE — expected RED.
- PHP 8.4: FAILURE — expected RED.
- PHP 8.5: FAILURE — expected RED.

PHP 8.3 full-suite evidence:

- Tests: `304`
- Assertions: `2482`
- Failures: `4`
- Errors: `0`

All four failures are confined to `TenantPortalRoutesTest`:

- `Tenant portal requires session password gate and tenant role`
- `Tenant home shows only owned leases active first with safe base path`
- `Tenant lease detail uses session ownership and contextual unit fields`
- `Owned contract pdf keeps private integrity and foreign lease is 404`

The runtime log shows the underlying cause before each assertion: `Slim\Exception\HttpNotFoundException` on the not-yet-created tenant portal routes (`/tenant`, `/tenant/leases/{id}`, `/tenant/leases/{id}/contract`). The application’s generic error boundary converts the unhandled missing-route exception to HTTP 500, which is why the RED assertions see `500` instead of their required `302/200` outcomes.

There are no SQL/fixture errors, syntax errors, missing migration errors, or failures in pre-existing suites. The RED therefore fails for the intended reason: the approved Task 6 portal functionality does not yet exist.

## Two Laws Gate at RED

### KISS

PASS for the RED boundary:

- one new test file only;
- no production architecture added before a verified failing test;
- no speculative route/service/view implementation;
- Task 7 navigation deliberately excluded.

### Cero Ruido / Convergencia

PASS for the RED boundary:

- test reuses existing bootstrap/auth/session/database/private-PDF contracts;
- no parallel tenant auth, tenant persistence, copied economics, copied unit ownership, or second PDF path was introduced;
- ownership is specified against the existing `leases.user_id` authority.

## Next Gate

On the next explicit `continua`, resume exactly here and implement **Task 6 minimal GREEN only**:

1. add safe joined `listForUser()` / `findForUser()` projections in existing `LeaseService`;
2. add the three ownership-first GET routes to the existing single F8 route file;
3. add only the two approved tenant views;
4. reuse `ContractService::privatePdf()` for owned PDF delivery;
5. verify the focused Task 6 test, existing contract PDF tests, then the full PHP 8.3/8.4/8.5 matrix;
6. create a Task 6 GREEN checkpoint before opening Task 7.

Do not start Task 7, do not add root redirect/navigation changes, and do not merge F8 in this gate.
