# Alquila Pro — F1 Foundation + Installer Implementation Plan

> **For agentic workers:** execute task-by-task with TDD. Do not merge to `main` until F1 closes with both supreme laws in PASS.

**Goal:** Create the smallest reliable PHP foundation and web installer that can boot Alquila Pro, validate its supported runtime, connect to MariaDB/MySQL, create the initial schema, capture base application/owner configuration, and lock the installer after successful completion.

**Architecture:** One Slim 4 application, server-rendered HTML, PDO, filesystem-backed local configuration outside public routes, simple SQL migrations and one installation state. No service container framework, ORM, Redis, workers, SPA, event bus, repositories/interfaces by convention, or future modules.

**Tech Stack:** PHP 8.3–8.5, Slim 4, Slim PSR-7, PDO, MariaDB/MySQL, PHPUnit, Composer.

**Spec:** `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.1_AUDITADO.md`

## Global Constraints

- LEY 1/KISS: simple, optimizada, eficiente, eficaz, rápida, auditable y actualizable.
- LEY 2/CERO RUIDO: ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR.
- One application, one database, one repository.
- PHP compatibility target: 8.3, 8.4 and 8.5.
- No Node requirement in production.
- No framework/service abstractions without demonstrated need.
- No code for SMTP, payment methods, PSE, business modules or future integrations in F1.
- Installer must work from a subdomain or subfolder and derive its base URL from the request.
- Credentials and secrets must never be committed.
- Every task closes only after RED → GREEN → full suite → two-laws gate.

## F1 Scope Ruling

The final product vision includes SMTP and initial payment-method configuration in the installer. Those modules do not exist yet. F1 intentionally does **not** build empty SMTP/payment screens or dormant transport code. They will be added when their real modules are implemented. This is required by the Cero Ruido law.

F1 does create the initial owner record because the installer must be able to finish and lock itself; F2 will implement login/session/permission behavior on top of that record.

## Review Focus

1. Subfolder URL detection must not duplicate path segments or trust arbitrary forwarded headers.
2. Database credentials must never appear in exception pages or logs.
3. Re-running migrations must be idempotent and must not recreate the owner.
4. Installation lock must prevent the installer from being reopened after successful completion.
5. Failed installation must not leave a false `installed` state.

---

## File map

Initial target; files may be merged if the two-laws gate proves a simpler boundary.

```text
composer.json
phpunit.xml
.github/workflows/ci.yml
index.php
.htaccess
bootstrap/app.php
config/.gitkeep
app/Foundation/BaseUrlDetector.php
app/Foundation/RuntimeRequirements.php
app/Foundation/DatabaseConfig.php
app/Foundation/DatabaseConnector.php
app/Foundation/MigrationRunner.php
app/Foundation/InstallationLock.php
app/Foundation/Installer.php
app/Foundation/AppConfig.php
database/migrations/001_create_schema_migrations.sql
database/migrations/002_create_users.sql
database/migrations/003_create_settings.sql
resources/views/install/*.php
storage/.htaccess
storage/app/.gitkeep
storage/logs/.gitkeep
tests/Unit/Foundation/*Test.php
tests/Integration/Foundation/*Test.php
docs/checkpoints/F1_FOUNDATION_INSTALLER_CLOSED.md
```

No file in this map is mandatory merely because it is listed. If two adjacent responsibilities are clearer together, converge them and record the decision.

---

### Task 1: Test/CI bootstrap + Base URL RED

**Produces:** reproducible PHPUnit execution in GitHub Actions and the first failing behavior test.

**Files:**
- Create `composer.json`
- Create `phpunit.xml`
- Create `.github/workflows/ci.yml`
- Create `tests/Unit/Foundation/BaseUrlDetectorTest.php`

- [ ] Add only runtime dependencies required to boot Slim and dev dependency PHPUnit.
- [ ] Add CI matrix PHP 8.3, 8.4, 8.5 running `composer validate`, `composer install`, then PHPUnit.
- [ ] Write failing tests for:
  - `https://alquilapro.sierraglobalcompany.com/` → base URL without trailing duplicate path.
  - `https://example.com/apps/alquila/` with script under subfolder → `https://example.com/apps/alquila`.
  - HTTP request remains HTTP in local/dev when HTTPS is not indicated.
- [ ] Trigger CI and verify RED because `BaseUrlDetector` does not exist.

**Two-laws gate:** tooling remains minimal; no code-quality tools added yet.

---

### Task 2: Base URL detector GREEN

**Files:**
- Create `app/Foundation/BaseUrlDetector.php`

**Interface:**

```php
BaseUrlDetector::fromServer(array $server): string
```

- [ ] Implement only the rules exercised by tests.
- [ ] Do not trust `X-Forwarded-*` in F1; Hostinger reverse-proxy support is added only if production evidence requires it.
- [ ] Run focal test and full suite.

**Expected:** all URL tests PASS on PHP 8.3–8.5.

---

### Task 3: Runtime requirements RED → GREEN

**Files:**
- Create `tests/Unit/Foundation/RuntimeRequirementsTest.php`
- Create `app/Foundation/RuntimeRequirements.php`

**Interface:**

```php
RuntimeRequirements::evaluate(string $phpVersion, array $extensions, bool $storageWritable): array
```

**Required checks:**
- PHP >= 8.3 and < 8.6 is supported for V1 validation.
- `pdo` required.
- `pdo_mysql` required.
- `mbstring` required.
- storage writable required.

Result contains explicit items and overall `ok` boolean.

- [ ] RED unsupported PHP 8.2.
- [ ] RED missing `pdo_mysql`.
- [ ] RED unwritable storage.
- [ ] GREEN supported complete environment.
- [ ] Full suite.

---

### Task 4: Database configuration and connection RED → GREEN

**Files:**
- Create `tests/Unit/Foundation/DatabaseConfigTest.php`
- Create `tests/Integration/Foundation/DatabaseConnectorTest.php`
- Create `app/Foundation/DatabaseConfig.php`
- Create `app/Foundation/DatabaseConnector.php`
- Extend CI with MariaDB service only when integration test is introduced.

**Interfaces:**

```php
DatabaseConfig::fromArray(array $input): DatabaseConfig
DatabaseConnector::connect(DatabaseConfig $config): PDO
```

**Rules:**
- host required;
- port 1–65535;
- database required and restricted to safe database-name characters;
- username required;
- password may be empty because some local environments allow it;
- PDO exceptions are converted to a sanitized installer error without password/DSN disclosure.

- [ ] RED invalid port/database.
- [ ] GREEN validation.
- [ ] RED integration cannot connect with invalid credentials and error is sanitized.
- [ ] GREEN connection to CI MariaDB.
- [ ] Full suite.

---

### Task 5: Migration runner RED → GREEN

**Files:**
- Create `tests/Integration/Foundation/MigrationRunnerTest.php`
- Create `app/Foundation/MigrationRunner.php`
- Create SQL migrations needed for F1 only.

**Interface:**

```php
MigrationRunner::migrate(PDO $pdo, string $migrationDirectory): MigrationResult
```

**Rules:**
- migrations ordered by filename;
- `schema_migrations` records applied migration IDs;
- a second run applies zero migrations;
- failed migration does not get recorded as applied;
- F1 schema contains only what F1 needs: users, settings, migration history.

- [ ] RED first run expectations.
- [ ] GREEN initial migrations.
- [ ] RED/GREEN idempotent second run.
- [ ] RED/GREEN failed migration bookkeeping.
- [ ] Full suite.

---

### Task 6: Installation lock RED → GREEN

**Files:**
- Create `tests/Unit/Foundation/InstallationLockTest.php`
- Create `app/Foundation/InstallationLock.php`

**Interface:**

```php
InstallationLock::isInstalled(): bool
InstallationLock::write(array $metadata): void
```

**Rules:**
- lock path under `storage/app/installed.lock`;
- atomic create/replace;
- JSON metadata contains schema version and installed timestamp, never secrets;
- unreadable/malformed lock fails safe as installed and requires administrator intervention rather than reopening installer.

- [ ] RED absent/present/malformed cases.
- [ ] GREEN.
- [ ] Full suite.

---

### Task 7: Installer transaction/service RED → GREEN

**Files:**
- Create `tests/Integration/Foundation/InstallerTest.php`
- Create or converge `app/Foundation/Installer.php`
- Create `app/Foundation/AppConfig.php` only if it reduces duplication.

**Installer input:**
- DB configuration;
- application name (default `Alquila Pro`);
- owner full name;
- owner email;
- owner initial password;
- detected base URL.

**Required behavior:**
1. Refuse if lock exists.
2. Validate runtime/input.
3. Connect DB.
4. Run migrations.
5. Create exactly one owner with `password_hash()`.
6. Persist non-secret settings in DB.
7. Persist DB credentials in protected local config outside web routes.
8. Write lock **last**.
9. If any step fails before lock, installation remains retryable.

- [ ] RED invalid owner data.
- [ ] RED DB failure leaves no lock.
- [ ] RED second install refused.
- [ ] GREEN complete install.
- [ ] Verify owner password is hashed.
- [ ] Full suite.

---

### Task 8: Slim boot + installer UI RED → GREEN

**Files:**
- Create `index.php`
- Create `bootstrap/app.php`
- Create `.htaccess`
- Create minimal installer views
- Create HTTP/route tests if framework test harness remains simple; otherwise test routing through callable handlers without adding another framework.

**Routes:**
- `GET /` → redirect to `/install` while not installed; minimal app placeholder when installed.
- `GET /install` → diagnostic.
- `POST /install` → submit complete F1 installer form.
- `GET /health` → non-secret status (`ok`, version) when application boots.

**Rules:**
- no credentials rendered back after POST;
- validation messages human-readable in Spanish;
- mobile-first form;
- installer unavailable once locked;
- no JavaScript dependency required for installation.

- [ ] RED route expectations.
- [ ] GREEN boot/UI.
- [ ] Full suite.

---

### Task 9: Storage protection + failure handling

**Files:**
- Create `storage/.htaccess`
- Add minimal exception rendering/logging.
- Add tests where behavior is testable without coupling to Apache implementation details.

**Rules:**
- storage denied from HTTP on Apache/Hostinger;
- production error page does not reveal stack traces/secrets;
- detailed logs remain server-side;
- config and lock paths are not routable.

- [ ] Security tests.
- [ ] Full suite.

---

### Task 10: F1 full verification and checkpoint

**Verification:**
- `composer validate --strict`
- PHPUnit full suite PHP 8.3, 8.4, 8.5 through CI.
- Clean migration from empty MariaDB.
- Second migration run applies 0.
- Failed install does not create lock.
- Successful install creates owner/settings/config/lock.
- Installer cannot reopen after lock.

**Two-laws audit:**

```text
LEY_1_KISS: PASS|FAIL
LEY_2_CERO_RUIDO: PASS|FAIL
ADDED:
REMOVED:
MERGED_OR_CONVERGED:
DEPENDENCIES_DELTA:
DB_SCHEMA_DELTA:
PUBLIC_ROUTES_DELTA:
WHY_THIS_IS_STILL_SIMPLE:
PROOF:
```

**Checkpoint:** `docs/checkpoints/F1_FOUNDATION_INSTALLER_CLOSED.md`

**F1 closes only if both laws PASS.**

---

## Explicitly deferred

Not F1:

- login/session UI;
- full permissions;
- SMTP transport;
- payment methods;
- properties;
- candidates;
- SURA;
- contracts;
- PDF;
- business dashboards;
- reminder cron behavior;
- PSE;
- WhatsApp.

Deferring these is intentional compliance with both supreme laws, not missing scope.
