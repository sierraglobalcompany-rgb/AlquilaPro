# AlquilaPro — F22 Task 5 Partial CI Checkpoint

Date: 2026-10-15
Branch: `impl/f22-security-privacy-audit-20261014`
PR: #27

## Exact recovery point

Task 5 is ACTIVE and must resume from candidate commit:

- Candidate HEAD: `0f1211fb457c9092d3de808a5081e866ada740ad`
- Existing recovery branch: `checkpoint/f22-task5-evidence-candidate-20261015`
- CI run: #993 / `38685454977`

## Task 5 work completed

Only regression evidence was added; no production/runtime behavior was changed:

1. `tests/Integration/Foundation/SecurityBoundaryTest.php`
   - certifies production session bootstrap uses strict mode and cookies only;
   - certifies the canonical session name;
   - certifies session cookie lifetime/path/Secure/HttpOnly/SameSite=Lax behavior;
   - certifies health and protected notification cron bootstrap boundaries remain explicit.

2. `tests/Integration/Notifications/WhatsAppSecurityRegressionTest.php`
   - certifies provider-facing WhatsApp surfaces cannot embed candidate continuation capabilities (`continuation_token`, `/apply/continue/`, `token=`).

The audit-view `Cache-Control: private, no-store` invariant was found to already exist in both product and `AuditRoutesTest`; no duplicate test was added.

## Matrix findings already confirmed

- Auth/session: inactivity, forced password change, CSRF rotation and PHP session ID regeneration already covered.
- Public catalog: private address, internal code and private notes are not exposed.
- Public media: uses public key + position, not media IDs; disappears when unit stops being publicable.
- Documents: MIME is derived from bytes; private reads verify MIME/hash/size; missing/tampered/symlinked/oversized files fail closed.
- Tenant IDOR: owned lease resolution precedes liquidation, visits/maintenance, obligations, payments, QR and contract delivery.
- Memberships: lease ownership is resolved before accept/renew and membership is scoped back to lease before renewal.
- WhatsApp: non-HTTPS stored destinations are rejected before open audit.
- F23 remains frozen.

## Membership audit note

`app/Memberships/routes.php` chooses the audit action from request input `$decision`, while the frozen F22 wording prefers the returned `charge_outcome`. Current `MembershipService::decideCharge()` only accepts `create|skip`, persists that exact decision and normalizes it to `created|skipped`, so no behavioral divergence was reproduced. Do not patch product unless a RED demonstrates a real mismatch.

## CI state at checkpoint

CI #993 / run `38685454977`:

- PHP 8.3: **PASS**
  - PHPUnit: **598 tests / 6407 assertions / 0 failures / 0 errors**
  - Composer validation: PASS
- PHP 8.4: **IN PROGRESS** at `Run tests`
- PHP 8.5: **IN PROGRESS** at `Run tests`

Do **not** mark Task 5 GREEN until both PHP 8.4 and PHP 8.5 also pass.

## Task 6 preflight finding (do not execute yet)

Governance was inspected only to prepare the next gate:

- `main` SHA remains `7c0f0c75c665b9888d26bfdbfd5be9bec4df3b82`.
- `main` reports `protected: false` and required status checks disabled.
- repository rulesets endpoint returns `[]`.
- detailed branch-protection endpoint returns 403 to this integration.
- PR #27 currently has no unresolved review threads.
- F22 candidate is 23 commits ahead of `main` and 0 behind.

Task 6 must therefore report `GOVERNANCE_ACTION_REQUIRED`; it must not claim branch protection is configured.

## Exact next action on resume

1. Check CI #993 for PHP 8.4 and PHP 8.5.
2. If either fails, fix only the failing Task 5 regression evidence (or a reproduced product gap) and checkpoint again.
3. If both pass, create `checkpoint/f22-task5-green-20261015` at the certified HEAD and update PR #27.
4. Only after that open Task 6 convergence/governance/final checkpoint.

## Guardrails

- KISS: simple, efficient, optimized, effective, direct architecture.
- Anti-noise: no duplicate security stack, no extra framework, no F23 work.
- Anti-patch: product changes require a reproducing RED.
- Mandatory checkpoints before each next gate.
