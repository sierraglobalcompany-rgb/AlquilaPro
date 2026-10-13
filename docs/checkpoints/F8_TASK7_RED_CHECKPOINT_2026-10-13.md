# F8 Task 7 RED Checkpoint — 2026-10-13

## State

- Phase: F8 — Tenant + lease + private portal using the single existing auth system.
- Task 6: GREEN / VERIFIED.
- Task 7: RED / VERIFIED / OPEN.
- Task 7 GREEN: NOT STARTED.
- Task 8: NOT STARTED.
- Merge: NOT AUTHORIZED / NOT ATTEMPTED.

## Continuity

- Branch: `impl/f8-tenant-lease-portal-20261013`
- Previous Task 6 checkpoint commit: `aa0752cbddef760982a1540060e0b2ddc22a9b34`
- Task 7 architecture RED commit: `a3e297520d9cae9471c6702166255313f883f53f`
- Task 7 shell RED commit: `ecf4a1481c6419b6129046f243c4dee048558526`
- Test-only guardrail correction: `eaa579591abe41db252bebfc7c8126fdea9ba9f9`
- Exact verified RED HEAD before this checkpoint: `eaa579591abe41db252bebfc7c8126fdea9ba9f9`

Task 7 RED delta from the Task 6 checkpoint contains tests only:

1. added `tests/Integration/Leases/LeaseSecurityRegressionTest.php`;
2. modified `tests/Integration/UI/ShellRoutesTest.php`.

No production, migration, dependency, CSS, route, view, service, or bootstrap file changed in this RED block.

## Canonical Task 7 Contract

Task 7 remains limited to navigation convergence and architectural closure:

- forced-password gate remains authoritative before tenant routing;
- authenticated tenant root `/` redirects to `/tenant`;
- staff root behavior remains unchanged;
- the existing F3 application layout is reused;
- tenant shell exposes `Inicio` and `Cuenta` but no Apps launcher/dialog or administrative navigation;
- tenant role cannot gain administrative UI/access through polluted `permissions_json`;
- F8 remains one lease table, one production `LeaseService`, one F8 route file, zero parallel tenant auth/session/user stack, zero new direct dependency and zero future-module surface;
- activation keeps the canonical unit lock before tenant/lease side effects;
- temporary credential remains body-only in the immediate private response and never travels in URL/action.

## RED Coverage Added

`ShellRoutesTest` now specifies:

1. a tenant still subject to `must_change_password` is redirected to the existing password-change route before any tenant-home redirect;
2. a ready tenant hitting `/` under a subfolder is redirected to the base-path-safe `/tenant` route;
3. the tenant shell uses `/tenant` as Inicio;
4. the tenant shell keeps Cuenta;
5. Apps trigger/dialog/admin links/empty Apps state are absent even if a tenant row is polluted with administrative permission values;
6. the existing admin boundary still rejects that tenant.

`LeaseSecurityRegressionTest` freezes:

1. exactly one F8 persistence table (`leases`), one production lease service and one F8 route file;
2. the exact five approved F8 routes and no public F8 route;
3. no `tenants`, `lease_units`, `tenant_users`, `tenant_sessions`, tenant-specific auth/session stack, or extra direct dependency;
4. shared auth/session/CSRF are reused rather than instantiated inside the F8 lease core;
5. the canonical unit lock precedes tenant identity and lease persistence side effects;
6. no future tenant modules are exposed;
7. the one-time credential never appears in a URL, redirect or form action.

## Guardrail Correction During RED Verification

The first architecture assertion incorrectly included `bootstrap/app.php` in the prohibition against `new SessionGuard()` / `new Csrf()`. That was a test bug because the bootstrap is precisely where the application's single shared instances are canonically created.

The assertion was corrected test-only in `eaa579591abe41db252bebfc7c8126fdea9ba9f9` so that:

- `new SessionGuard()` / `new Csrf()` remain forbidden inside `app/Leases/LeaseService.php` and `app/Leases/routes.php`;
- the shared bootstrap may instantiate the one canonical session/CSRF stack;
- tenant-specific parallel stack names remain forbidden across F8 composition.

No production behavior was changed to satisfy the guardrail.

## RED Verification

GitHub Actions run: `38430297944`

Exact RED HEAD: `eaa579591abe41db252bebfc7c8126fdea9ba9f9`

Matrix result:

- PHP 8.3: FAILURE — expected RED.
- PHP 8.4: FAILURE — expected RED.
- PHP 8.5: FAILURE — expected RED.

PHP 8.3 full-suite evidence:

- Tests: `309`
- Assertions: `2589`
- Failures: `2`
- Errors: `0`

The two remaining failures are exactly the intended Task 7 behavior gaps:

1. `Tenant root keeps password gate then redirects to private portal`
   - ready tenant root currently returns HTTP `200` instead of redirecting to `/tenant`;
2. `Tenant shell has only tenant home and account even with polluted permissions`
   - current shared layout still points Inicio to `/` and still renders the Apps trigger/dialog/empty state for tenant.

All `LeaseSecurityRegressionTest` architecture/security guardrails pass after the test-only scope correction. Existing Task 1–6 suites continue to pass inside the same run; there are no SQL, migration, fixture, syntax, Composer, bootstrap, or new production regressions.

## Two Laws Gate at RED

### KISS

PASS:

- RED changes tests only;
- no production implementation before verified failure;
- no new navigation layer, tenant shell, controller, permission or middleware invented;
- target GREEN is two minimal edits in existing authorities only.

### Cero Ruido / Convergencia

PASS:

- root behavior will converge in the existing root route;
- tenant visual navigation will converge in the existing F3 layout;
- guardrail explicitly rejects parallel tenant/auth/persistence stacks and future-module noise;
- no CSS delta is justified by this RED.

## Next Gate

On the next explicit `continua`, implement **Task 7 minimal GREEN only**:

1. in the existing authenticated root route, keep `must_change_password` first, then redirect `role=tenant` to base-path-safe `/tenant`;
2. make the existing app layout tenant-aware so tenant Inicio points to `/tenant` and Apps button/dialog are omitted;
3. do not add CSS unless a separately reproduced RED proves it necessary;
4. verify `ShellRoutesTest`, `LeaseSecurityRegressionTest`, `AssetContractTest`, and the full PHP 8.3/8.4/8.5 matrix;
5. create a Task 7 GREEN checkpoint and stop before Task 8.

Do not start Task 8, do not add future modules, and do not merge F8 in this gate.
