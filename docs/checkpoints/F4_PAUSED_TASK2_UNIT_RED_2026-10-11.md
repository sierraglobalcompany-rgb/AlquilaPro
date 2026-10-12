# F4 CHECKPOINT — PAUSED AT TASK 2 UNIT RED

**Project:** Alquila Pro  
**Phase:** F4 — Properties, Units and Public Catalog  
**Date:** 2026-10-11 (America/Bogota)  
**Branch:** `impl/f4-properties-catalog-20261011`  
**PR:** #7 — `F4: Properties, units and public catalog`  
**Base:** `main` @ `940ebfae25a05d1589fe1ab5ceeaf667c2d08345`  
**Product HEAD before this checkpoint:** `242c7c1befd4e452026287ee5d362ecc6a404359`  
**State:** PAUSED BY USER — DO NOT CONTINUE UNTIL EXPLICIT `continua`

---

## 1. AUTHORITY AND RULING

Canonical F4 authority remains:

1. `docs/specs/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_SPEC.md`
2. `docs/plans/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_IMPLEMENTATION_PLAN.md`
3. this checkpoint for exact execution state
4. tests / CI / code

Important ruling already applied:

- SPEC has authority over the contradictory PLAN line for `public_key`.
- F4 uses `bin2hex(random_bytes(12))` → **24 lowercase hex characters**.
- Database schema uses `CHAR(24)` unique.
- `public_key` is server-generated and must never be accepted from forms/input.
- Do not change this to 32 chars unless the SPEC is explicitly revised.

---

## 2. CURRENT F4 IMPLEMENTATION STATUS

### Task 1 — Schema + Permission Catalog

**Status:** GREEN / COMPLETE FOR CURRENT SCOPE.

Implemented:

- `database/migrations/004_create_properties_units_media.sql`
- exactly three F4 tables:
  - `properties`
  - `units`
  - `unit_media`
- no `catalog_listings`
- no `apartments`
- no `parking_spaces`
- `units.public_key` unique, `CHAR(24)`
- unique `(property_id, code)`
- self-FK `linked_apartment_id`
- F4 permissions added to existing catalog only:
  - `properties.view`
  - `properties.manage`
- existing permission sanitizer remains one source of truth.

Test-fixture compatibility fix performed during Task 1:

- F4 tables introduced FK ordering that broke older integration-test teardown/setup.
- Existing fixtures were converged to FK-safe cleanup by dropping F4 tables first under `FOREIGN_KEY_CHECKS = 0`, then restoring checks.
- This was test infrastructure only; no extra runtime abstraction was added.

Fresh verification evidence:

- CI run `38197883079`
- PHP 8.3: SUCCESS
- PHP 8.4: SUCCESS
- PHP 8.5: SUCCESS
- last inspected test output for this gate: `97 tests / 475 assertions`.

Key Task 1 GREEN commit:

- `44302c3a2a8c1ae18ad13db5df7861b4367651f4` — `feat(f4): add property permissions`

---

## 3. TASK 2 — PROPERTY AND UNIT DOMAIN SERVICE

### 3.1 Property sub-cycle

**Status:** GREEN.

Created:

- `app/Properties/PropertyService.php`
- `tests/Integration/Properties/PropertyServiceTest.php`

Implemented so far in `PropertyService`:

- `__construct(PDO $pdo)`
- `createProperty(array $input): array`
- `updateProperty(int $propertyId, array $input): array`
- `setPropertyActive(int $propertyId, bool $active): void`
- `listProperties(): array`
- `findProperty(int $propertyId): ?array`

Current behavior covered:

- required `name`, `address`, `city`
- optional `sector`, administration fields and private notes
- trimming / null normalization for blank optional values
- safe Spanish `RuntimeException` for invalid mutation input
- missing read returns `null`
- missing mutation target throws
- property deactivation preserves units; it does not delete inventory
- direct PDO only; no repository/DTO/interface layer.

RED evidence before implementation:

- commit `d324873029c2acbdc8949d110c760f4e508c6024`
- CI failed only the two new property tests because `PropertyService` did not yet exist; prior suite remained green.

Property GREEN implementation commit:

- `7060225d7d878afab416f65fa296c073c41a15ca` — `feat(f4): add property domain operations`

Fresh verification evidence:

- CI run `38198135229`
- PHP 8.3: SUCCESS
- PHP 8.4: SUCCESS
- PHP 8.5: SUCCESS

---

### 3.2 Unit sub-cycle

**Status:** RED OPEN — IMPLEMENTATION NOT STARTED.

Latest product commit:

- `242c7c1befd4e452026287ee5d362ecc6a404359` — `test(f4): add unit domain RED`

That commit extends `PropertyServiceTest.php` with RED coverage for:

- one `units` model for `apartment|parking`
- server-generated 24-hex `public_key`
- exact statuses:
  - `available`
  - `in_process`
  - `occupied`
  - `inactive`
  - `maintenance`
- rejection of unsupported type/status
- nonnegative numeric validation
- rejection of caller-supplied nonblank `public_key`
- unique code per property
- type-specific normalization:
  - apartment fields remain apartment-only
  - parking fields remain parking-only
- parking → apartment rules:
  - same property required
  - target must be apartment
  - no self-link
  - multiple parking units may reference one apartment
- unit reads/list/update contracts
- public-query skeleton only:
  - active property
  - `status=available`
  - `is_public=1`
  - optional type/city/sector filters
- full publication completeness (price + description + requirements + photo) remains Task 3, not Task 2.

**Important:** no unit methods have been implemented yet after this RED test commit.

At checkpoint creation, CI run `38198264690` for `242c7c1...` is still **IN PROGRESS**. Do not record its final result without refreshing GitHub Actions first.

Expected RED reason when execution resumes:

- `PropertyService` currently lacks:
  - `createUnit(...)`
  - `updateUnit(...)`
  - `findUnit(...)`
  - `listUnitsForProperty(...)`
  - `listPublicUnits(...)`
  - `findPublicUnit(...)`
  - `isUnitPublicable(...)` remains part of the planned interface and its completeness semantics are finalized in Task 3.

---

## 4. EXACT NEXT STEP ON RESUME

When the user explicitly says `continua`:

1. Refresh CI run `38198264690` and inspect its logs.
2. Confirm the unit RED fails for the expected missing unit methods/rules and not for unrelated regressions.
3. Implement the **minimal unit methods/rules only** in `app/Properties/PropertyService.php`:
   - no media service yet;
   - no routes/views yet;
   - no Task 3 publication completeness yet;
   - no extra abstractions.
4. Run full CI on PHP 8.3 / 8.4 / 8.5.
5. Do not mark Task 2 GREEN until the entire `PropertyServiceTest.php` and prior suite pass.
6. Only then open Task 3 — safe media lifecycle + complete publication predicate.

---

## 5. GUARDRAILS STILL ACTIVE

- mandatory order: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`
- TDD: RED → verify expected failure → minimal GREEN → regression/full CI
- one `units` table; no parallel apartment/parking/catalog models
- F4 schema delta remains exactly +3 tables maximum
- exactly two F4 permissions
- +0 Composer dependencies
- +0 Node/npm
- +0 frontend framework/build system
- no second auth/session system
- no candidates/leads/applications in F4
- no `Aplicar` CTA until F5
- no merge to `main` without explicit user approval
- do not open F5 until F4 closes and merge is explicitly approved

---

## 6. GIT / PR STATE AT PAUSE

PR #7 remains:

- OPEN
- DRAFT
- NOT MERGED
- head branch: `impl/f4-properties-catalog-20261011`
- product head before this checkpoint: `242c7c1befd4e452026287ee5d362ecc6a404359`

This checkpoint commit is documentation-only and must be treated as the new branch HEAD after creation. No product implementation may be added after it until the user explicitly resumes.

---

## 7. PAUSE CONTRACT

**STOP HERE.**

Do not:

- implement unit methods;
- modify runtime code;
- open Task 3;
- update routes/views;
- merge PR #7;
- start F5.

Resume only after explicit user instruction.