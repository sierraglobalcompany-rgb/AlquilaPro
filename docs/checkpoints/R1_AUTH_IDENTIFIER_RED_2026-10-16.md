# AlquilaPro — R1 Auth Identifier RED

Status: RED_PENDING_CI

- Parent schema GREEN: `c370ffe9f11f194bce84b9b0c47544930d6e25fc`
- RED test commit: `bdaa254fb69f39e0953554e3444ea0fcb680a409`
- Existing `AuthService` remains email-only at this point.
- New tests require email, username, document-number login through the same AuthService.
- Ambiguous cross-field identifiers must fail closed.
- No production auth behavior has been changed yet.
- F26: FROZEN.
- MERGE_AUTHORIZED: NO.
- DEPLOYMENT_AUTHORIZED: NO.
