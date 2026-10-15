# AlquilaPro — F22 Task 5 Preflight Checkpoint

Date: 2026-10-15
Branch: `impl/f22-security-privacy-audit-20261014`
PR: #27

## Recovery state

- F22 Task 1: GREEN / CLOSED.
- F22 Task 2: GREEN / CLOSED.
- F22 Task 3 product: GREEN.
- F22 Task 3A behavioral certification + atomicity repair: GREEN / CLOSED.
- F22 Task 4 F16 terminal-recipient retention: GREEN / CLOSED.
  - GREEN product commit: `f9a62d79e8691bd5e659fb73e463d51db7c3e314`.
  - CI #988 / run `38684069874`: PASS PHP 8.3 / 8.4 / 8.5.
  - PHP 8.3: 596 tests / 6391 assertions / 0 failures.
  - Recovery branch already exists: `checkpoint/f22-task4-green-20261015` at `65a9a59bbf9f95bba4a33938210575bba0f353cf`.

## Task 5 scope

Task 5 is a focused security/privacy hardening certification. It must reuse existing authorities and existing security regression suites. Do not create a parallel security architecture, framework, auth stack, scheduler, event bus, permission model, or duplicate mega-suite.

### Existing evidence to reuse

- Candidates security regressions already cover capability-token fail-closed behavior, duplicate-identity generic responses/throttling, global CSRF on F5 POST surfaces, private document IDOR/path traversal/fake MIME, and storage boundary.
- Properties security regressions already cover private route guarding/base-path correctness and cross-unit media IDOR.
- Foundation security regressions already cover generic 500 responses without secret leakage, blocked internal directories, runtime storage ignored by Git, and common security headers.
- Documents already have dedicated security regression and storage-security suites.
- Auth already has route/CSRF/service coverage.

## Exact RED target

Add only the missing sensitive audit-view cache invariant and session/cookie bootstrap regression evidence:

1. `/admin/audit` must return `Cache-Control: private, no-store` when authorized.
2. Existing production session/cookie bootstrap security must be asserted without changing runtime behavior unless a RED proves a real gap.
3. If the audit cache expectation fails, repair only the canonical owner with the smallest possible change.
4. No speculative hardening outside reproduced failures.

## Gate sequence

1. RED test only.
2. RED checkpoint.
3. Minimal GREEN repair only if RED proves a real product gap.
4. GREEN checkpoint.
5. Full CI PHP 8.3 / 8.4 / 8.5.
6. CI checkpoint and PR update.
7. Only then open Task 6.

## Guardrails

- KISS: simple, efficient, optimized, effective, direct architecture.
- Anti-noise: do not add historical/duplicate structures; remove or merge only when demonstrated safe.
- Anti-patch: every runtime repair must be preceded by a reproducing RED.
- F23 remains frozen until F22 is closed and merged.
