# F2 — Auth + Users + Permissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: use superpowers:executing-plans task-by-task. Steps use checkbox syntax for tracking.

**Goal:** agregar autenticación administrativa segura, sesiones, CSRF, administración de usuarios/permisos simples y recuperación manual por enlace de un solo uso, sin construir un RBAC genérico ni adelantar SMTP/F3.

**Architecture:** F2 reutiliza la tabla `users` creada en F1 y la amplía mediante una sola migration. La feature vive en `app/Auth/` y usa PDO directo; sesiones PHP nativas y permisos como lista simple por usuario. `owner` tiene bypass de permisos. La recuperación es administrada: un usuario autorizado genera un enlace one-use que puede compartir manualmente; el envío automático por email queda para F16.

**Tech Stack:** PHP 8.3–8.5, Slim 4, PDO/MariaDB, sesiones PHP, PHPUnit.

**Spec:** `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.1_AUDITADO.md`

## Global Constraints

- LEY 1 KISS: simple, optimizado, eficiente, eficaz, rápido y auditable.
- LEY 2 Cero Ruido: ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR.
- Una aplicación, una BD, un repositorio.
- Sin ORM, DI container, Redis, workers, event bus, SPA ni framework RBAC.
- No SMTP en F2; no adelantar F3.
- PHP `>=8.3 <8.6`.
- Todo POST de aplicación debe tener CSRF después de F2, incluido `/install`.
- Password mínimo: 12 caracteres.
- Sesión administrativa: timeout de inactividad de 30 minutos.
- Rate limit básico: 5 fallos consecutivos para usuario existente → bloqueo 15 minutos.
- Reset token: 32 bytes aleatorios, hash SHA-256 almacenado, vigencia 30 minutos, un solo uso.
- Solo se crean roles `admin` y `gestor` desde el panel; `owner` nace del installer.
- No borrar usuarios en V1; activar/desactivar preserva trazabilidad.

## Review Focus

1. Credenciales incorrectas, usuario inactivo o bloqueado nunca revelan cuál condición falló.
2. Una sesión expirada no puede seguir usando rutas protegidas.
3. Un CSRF ausente/incorrecto rechaza la mutación sin cambiar datos.
4. El único propietario activo no puede desactivarse.
5. Un reset token usado, vencido o alterado no puede reutilizarse.

---

## File Map

**Create**
- `database/migrations/003_extend_users_for_auth.sql` — campos de auth/permisos/reset.
- `app/Auth/AuthService.php` — authenticate/change password/reset token.
- `app/Auth/UserService.php` — usuarios administrados, estado y permisos.
- `app/Auth/SessionGuard.php` — sesión y expiración.
- `app/Auth/Csrf.php` — token CSRF único por sesión.
- `app/Auth/Permissions.php` — catálogo mínimo y autorización.
- `app/Auth/routes.php` — rutas F2; sin controllers adicionales.
- `resources/views/login.php`
- `resources/views/change_password.php`
- `resources/views/admin_users.php`
- `resources/views/reset_password.php`
- tests F2 bajo `tests/Unit/Auth/` y `tests/Integration/Auth/`.

**Modify**
- `index.php` — iniciar sesión con cookie segura.
- `bootstrap/app.php` — conectar módulo Auth y CSRF del installer.
- `resources/views/install.php` — hidden CSRF.
- tests F1 que hagan POST `/install`.

---

### Task 1: Migración auth sin nueva tabla

**Files:**
- Create: `database/migrations/003_extend_users_for_auth.sql`
- Test: `tests/Integration/Auth/AuthSchemaTest.php`

**Produces:** columnas `permissions_json`, `must_change_password`, `failed_login_attempts`, `locked_until`, `last_login_at`, `reset_token_hash`, `reset_expires_at` sobre `users`.

- [ ] Escribir RED que ejecute todas las migraciones y compruebe las columnas F2.
- [ ] Ejecutar CI y confirmar fallo por columnas ausentes.
- [ ] Implementar migration `003` con defaults seguros y sin tabla RBAC.
- [ ] Ejecutar matriz PHP 8.3/8.4/8.5.
- [ ] Commit `feat(auth): extend users for authentication`.

### Task 2: AuthService — login, bloqueo y contraseña

**Files:**
- Create: `app/Auth/AuthService.php`
- Test: `tests/Integration/Auth/AuthServiceTest.php`

**Interface:**
- `__construct(PDO $pdo)`
- `authenticate(string $email, string $password, DateTimeImmutable $now): ?array`
- `changePassword(int $userId, string $currentPassword, string $newPassword): bool`
- `issueResetToken(int $userId, DateTimeImmutable $now): string`
- `resetPassword(string $token, string $newPassword, DateTimeImmutable $now): bool`
- `findUser(int $userId): ?array`

**Behavior:** email normalizado a lowercase; 5 fallos bloquean 15 min; login correcto resetea fallos; usuario inactivo/bloqueado retorna `null`; contraseña nueva mínimo 12; reset limpia token al usarlo.

- [ ] RED login correcto/incorrecto/inactivo/bloqueo.
- [ ] RED cambio de contraseña.
- [ ] RED reset one-use/expired/altered.
- [ ] Implementar mínimo con PDO directo.
- [ ] Suite completa GREEN.
- [ ] Commit `feat(auth): add authentication service`.

### Task 3: SessionGuard + CSRF

**Files:**
- Create: `app/Auth/SessionGuard.php`
- Create: `app/Auth/Csrf.php`
- Test: `tests/Unit/Auth/SessionGuardTest.php`
- Test: `tests/Unit/Auth/CsrfTest.php`

**Interfaces:**
- `SessionGuard::login(array $user, int $now): void`
- `SessionGuard::logout(): void`
- `SessionGuard::userId(int $now): ?int`
- `SessionGuard::mustChangePassword(): bool`
- `Csrf::token(): string`
- `Csrf::validate(?string $token): bool`
- `Csrf::rotate(): void`

- [ ] RED login/session timeout 1800s/logout.
- [ ] RED CSRF válido/ausente/alterado/rotación.
- [ ] Implementar sobre `$_SESSION`, sin storage propio.
- [ ] Suite GREEN.
- [ ] Commit `feat(auth): add session and csrf guards`.

### Task 4: Permisos simples + UserService

**Files:**
- Create: `app/Auth/Permissions.php`
- Create: `app/Auth/UserService.php`
- Test: `tests/Integration/Auth/UserServiceTest.php`

**Permissions F2:**
- `users.view`
- `users.manage`
- `users.permissions`

**Interfaces:**
- `Permissions::allows(array $user, string $permission): bool` — owner siempre true.
- `Permissions::sanitize(array $permissions): array` — solo catálogo conocido.
- `UserService::listUsers(): array`
- `UserService::create(string $name, string $email, string $role, string $temporaryPassword, array $permissions): int`
- `UserService::setActive(int $userId, bool $active): void`
- `UserService::setPermissions(int $userId, array $permissions): void`

**Rules:** create solo `admin|gestor`; contraseña temporal fuerza cambio; no eliminar; último owner activo no se desactiva; permisos desconocidos se descartan/rechazan según test.

- [ ] RED permisos owner/admin/gestor.
- [ ] RED create + email único + temp password.
- [ ] RED desactivar usuario y protección último owner.
- [ ] RED actualizar permisos.
- [ ] Implementar sin tablas de roles/ACL.
- [ ] Suite GREEN.
- [ ] Commit `feat(auth): add simple user permissions`.

### Task 5: Rutas web F2 y recuperación administrada

**Files:**
- Create: `app/Auth/routes.php`
- Create views F2.
- Modify: `bootstrap/app.php`
- Test: `tests/Integration/Auth/AuthRoutesTest.php`

**Public routes:**
- `GET /login`
- `POST /login`
- `GET /reset-password`
- `POST /reset-password`

**Authenticated routes:**
- `POST /logout`
- `GET /account/password`
- `POST /account/password`

**Admin routes:**
- `GET /admin/users`
- `POST /admin/users`
- `POST /admin/users/{id}/status`
- `POST /admin/users/{id}/permissions`
- `POST /admin/users/{id}/reset-link`

**Behavior:** usuarios no autenticados redirigen a login; must-change-password fuerza `/account/password`; permisos protegen admin routes; reset-link devuelve/en pantalla un enlace one-use para compartir manualmente.

- [ ] RED login/logout/protected routes.
- [ ] RED usuario sin permiso = 403.
- [ ] RED must-change redirect.
- [ ] RED reset-link manual y consumo.
- [ ] Implementar rutas como callable file, sin controllers vacíos.
- [ ] Suite GREEN.
- [ ] Commit `feat(auth): wire authentication routes`.

### Task 6: CSRF global de mutaciones + sesión segura de producción

**Files:**
- Modify: `index.php`
- Modify: `bootstrap/app.php`
- Modify: `resources/views/install.php`
- Modify F1 POST tests.
- Test: `tests/Integration/Auth/CsrfRoutesTest.php`

**Rules:** cookie `HttpOnly`, `SameSite=Lax`, `Secure` bajo HTTPS; path respeta subcarpeta. Todos los POST del sistema validan CSRF; `/health` permanece público y sin sesión requerida.

- [ ] RED POST `/install` sin CSRF = 403 y no instala.
- [ ] RED login/admin mutation sin CSRF = 403.
- [ ] Actualizar formularios y entrypoint.
- [ ] Suite completa GREEN en 8.3/8.4/8.5.
- [ ] Commit `security(auth): enforce csrf and secure session`.

### Task 7: Auditoría final F2 + checkpoint

**Files:**
- Create: `docs/checkpoints/F2_CLOSED_2026-10-11.md`
- Modify: `README.md` solo al cerrar F2.

- [ ] Ejecutar `composer validate --strict`.
- [ ] Ejecutar PHPUnit completo en PHP 8.3, 8.4, 8.5 con MariaDB real.
- [ ] Revisar archivos/dependencias/schema/rutas.
- [ ] Aplicar Gate Ley 1 KISS.
- [ ] Aplicar Gate Ley 2 Cero Ruido / Convergencia.
- [ ] Registrar ADDED / REMOVED / MERGED / dependency delta / schema delta / route delta.
- [ ] Crear checkpoint y actualizar README con estado real.
- [ ] Dejar PR F2 listo para revisión, sin merge automático.

---

## PRE-KISS DECISIONS F2

- **No tabla `roles` ni `permissions`:** tres roles y tres permisos actuales no justifican RBAC relacional.
- **No tabla de reset tokens:** un único token vigente por usuario cabe en `users`; reduce joins/cleanup.
- **No tabla de sesiones:** usar sesiones PHP nativas.
- **No SMTP:** reset administrado por enlace manual hasta F16.
- **No controllers/services/repositories por capa:** un módulo `Auth` con servicios de responsabilidad directa + un archivo de rutas.
- **No delete de usuarios:** `is_active` conserva trazabilidad y evita complejidad referencial futura.

## Expected Architecture Delta

```text
ADDED:
- app/Auth/*
- migration 003
- views/tests F2

DB_SCHEMA_DELTA:
- ALTER users only
- +0 business tables

DEPENDENCIES_DELTA:
- +0 Composer packages expected

PUBLIC_ROUTES_DELTA:
- auth/reset routes only
```

**F2 inicia OPEN. Ninguna implementación de F3 está autorizada en esta rama.**
