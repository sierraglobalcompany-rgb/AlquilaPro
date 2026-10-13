# F8 Task 6 GREEN Checkpoint — 2026-10-13

## State

- Phase: F8 — Tenant + lease + private portal using the single existing auth system.
- Task 5: GREEN / VERIFIED.
- Task 6: GREEN / VERIFIED.
- Task 7: NOT STARTED.
- Merge: NOT AUTHORIZED / NOT ATTEMPTED.

## Continuity

- Branch: `impl/f8-tenant-lease-portal-20261013`
- Task 6 RED checkpoint: `de9537a01b8da2100fc57b0d809bfe88350e1108`
- Task 6 RED product commit: `a2f96ca96e17e3a5628fcca95cc57148e84ca143`
- Task 6 final product HEAD: `240046b831670d424a4917abf56379750c3f8f09`
- Final product tree: `5923d44c38b1e75a00ee268a8c7b0e78ded49b05`

Task 6 product commit chain from the RED checkpoint:

1. `fbdd090dff195be20d55f366a2f7362b6118e442` — tenant-owned lease projections.
2. `bc65d5cf47fc6e8ca782541e38af4df095ef9a35` — private tenant routes.
3. `ccfe334b0c94570f60a89c497a35dbd82247e28e` — minimal ContractService wiring into the existing F8 route file.
4. `789e94f1128ebb58dfadbaa83058c23b62438aef` — tenant lease list view.
5. `240046b831670d424a4917abf56379750c3f8f09` — tenant lease detail view.

## Implemented Contract

### LeaseService

Added only the approved ownership projections:

- `listForUser(int $userId): array`
  - ownership filtered by `leases.user_id` in SQL;
  - ACTIVE rows first;
  - then newest `activated_at`, then `id`.
- `findForUser(int $leaseId, int $userId): ?array`
  - lease ID + session user ID constrained in the same SQL query;
  - foreign or missing lease returns `null`.

The repeated F8 joined projection was converged into one private `leaseSelect()` helper and reused by `findByContract()`, `listForUser()` and `findForUser()`. No persistence, cache, copied authority or new service was added.

### Tenant routes

Added to the existing single `app/Leases/routes.php`:

- `GET /tenant`
- `GET /tenant/leases/{id}`
- `GET /tenant/leases/{id}/contract`

Guard order is canonical:

1. existing authenticated session required;
2. existing forced-password gate enforced;
3. `role=tenant` required;
4. session `user.id` is the only ownership authority.

No request parameter can select user/candidate/contract ownership.

### Tenant views

Added only:

- `resources/views/tenant_home.php`
- `resources/views/tenant_lease_detail.php`

Both reuse the existing F3 app shell and existing CSS primitives. All stored text is escaped. Apartment and parking context is derived from existing canonical unit/property/contract rows.

### Owned PDF

The tenant PDF route performs ownership lookup first, then delegates to existing `ContractService::privatePdf()`.

It preserves:

- F7 physical-path validation;
- exact size/hash verification;
- `%PDF-` verification;
- `Content-Type: application/pdf`;
- attachment delivery;
- `Cache-Control: private, no-store`;
- `X-Content-Type-Options: nosniff`;
- no physical `pdf_stored_name` disclosure.

Foreign lease, missing lease, missing PDF or tampered PDF returns `404`.

## Minimal Wiring Ruling

The approved Task 6 interface requires the F8 PDF route to call the existing `ContractService::privatePdf()`.

`bootstrap/app.php` therefore received one minimal dependency handoff: the already-instantiated `$contracts` object is passed to the already-existing F8 route file.

This is not a new layer. The alternatives — re-instantiating ContractService inside routes, injecting it into LeaseService, or creating a PDF/portal service — would add duplication and violate KISS/Cero Ruido.

## Delta Audit

`de9537a0...240046b8`:

- ahead by: `5`
- behind by: `0`
- changed product files: exactly `5`

Exact delta:

1. `app/Leases/LeaseService.php`
2. `app/Leases/routes.php`
3. `bootstrap/app.php`
4. `resources/views/tenant_home.php`
5. `resources/views/tenant_lease_detail.php`

No schema, auth service, permissions, CSS, Composer, public catalog, navigation root or future module file changed.

## GREEN Verification

GitHub Actions run: `38428697337`

Exact product HEAD tested: `240046b831670d424a4917abf56379750c3f8f09`

Matrix:

- PHP 8.3: PASS
- PHP 8.4: PASS
- PHP 8.5: PASS

PHP 8.3 exact full-suite evidence:

- Tests: `304`
- Assertions: `2530`
- Failures: `0`
- Errors: `0`

The full suite explicitly shows all four Task 6 RED cases now GREEN:

- `Tenant portal requires session password gate and tenant role`
- `Tenant home shows only owned leases active first with safe base path`
- `Tenant lease detail uses session ownership and contextual unit fields`
- `Owned contract pdf keeps private integrity and foreign lease is 404`

The same full suite also passes the existing F7 PDF suites:

- `Contract Pdf`
- `Contract Pdf Routes`

Thus the earlier RED of `304 tests / 2482 assertions / 4 failures` converged to `304 tests / 2530 assertions / 0 failures` without regression elsewhere.

## Two Laws Gate

### KISS — PASS

- one existing F8 domain class remains the only lease service;
- one existing F8 route file remains the only lease route surface;
- two minimal SSR views only;
- one minimal bootstrap dependency handoff;
- zero new tables;
- zero new permissions;
- zero new auth/session systems;
- zero new dependencies;
- zero CSS delta;
- zero public F8 routes;
- zero Task 7 navigation work.

### Cero Ruido / Convergencia — PASS

- ownership is derived from `leases.user_id`, not copied;
- lease economics/dates/unit/property remain derived from existing canonical tables;
- the joined projection was centralized instead of copied three times;
- tenant PDF reuses F7 `ContractService::privatePdf()` rather than adding a second PDF authority;
- current auth/session/password reset/change-password machinery remains the only auth stack;
- no future-module UI was introduced.

## Next Gate

Stop here.

On the next explicit `continua`, resume at **Task 7 RED only**:

1. re-read the exact Task 7 plan block;
2. write navigation/security architecture RED tests only;
3. verify the RED against current Task 6 GREEN;
4. do not implement Task 7 GREEN until that RED is proven;
5. do not merge F8.
