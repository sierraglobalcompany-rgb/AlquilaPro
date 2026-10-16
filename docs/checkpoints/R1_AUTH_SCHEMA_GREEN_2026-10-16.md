# AlquilaPro — R1 Auth Schema Green

Status: GREEN

- Base main: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Implementation branch: `impl/r1-r3-post-uat-recovery-20261015`
- Schema closure SHA before R1-T2 RED: `c370ffe9f11f194bce84b9b0c47544930d6e25fc`
- Migration: `023_extend_users_login_identifiers.sql`
- Adds only nullable unique `users.username` and `users.document_number`.
- Existing migrations 001–022 remain unchanged.
- CI #1010 / run `38837618122`: completed success PHP 8.3 / 8.4 / 8.5.
- PHP 8.3 observed: 632 tests / 7581 assertions / 1 expected artifact skip.
- MigrationRunner and ReleaseMigration authorities converged to migration 023.
- No auth behavior changed yet at this checkpoint.
- Human UAT remains REJECTED.
- F26 remains FROZEN.
- MERGE_AUTHORIZED: NO.
- DEPLOYMENT_AUTHORIZED: NO.

Next: R1-T2 unified login identifier RED/GREEN in existing AuthService.
