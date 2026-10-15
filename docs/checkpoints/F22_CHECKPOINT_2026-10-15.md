# F22 — CHECKPOINT CANÓNICO DE CIERRE — 2026-10-15

## Autoridad

- Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
- PR: #27 `F22: security privacy and audit hardening`
- Base F22: `main@7c0f0c75c665b9888d26bfdbfd5be9bec4df3b82` — F21 merged.
- Product Head F22 certificado antes de convergencia: `edbac36b5033446ae710f97e3b81659abc24c0cc`.
- CI Product Head: #996 / PASS PHP 8.3, 8.4 y 8.5.
- PHP 8.3 en #996: **599 tests / 6412 assertions / 0 failures / 0 errors**.
- Este archivo pertenece al Closure Head documental de F22. La CI del SHA exacto del Closure Head será la autoridad final antes del merge.

## Estado de fase

- PRE-KISS: **CLOSED**.
- Task 1 — migration 022 + canonical `AuditService`: **CLOSED / GREEN**. CI #973 PASS PHP 8.3/8.4/8.5.
- Task 2 — owner-only audit viewer + centralized response headers: **CLOSED / GREEN**. CI #975 PASS PHP 8.3/8.4/8.5.
- Task 3 — explicit high-value audit events: **PRODUCT GREEN**.
- Task 3A — behavioral certification + atomicity repair for `user.created`: **CLOSED / GREEN**. CI #982 PASS PHP 8.3/8.4/8.5.
- Task 4 — F16 terminal recipient retention: **CLOSED / GREEN**. CI #988 PASS PHP 8.3/8.4/8.5.
- Task 5 — security/privacy hardening matrix: **CLOSED / GREEN / TEST-ONLY**. Product Head `0f1211fb457c9092d3de808a5081e866ada740ad`; CI #993 PASS PHP 8.3/8.4/8.5; PHP 8.3 = 598 tests / 6407 assertions.
- Task 6 — convergence/governance/final closure: **PRODUCT AUDIT GREEN / DOCS CONVERGED / FINAL GATES PENDING**.
- F23→F26: **FROZEN** hasta merge F22.

## Task 6 — reparación contractual probada de Memberships

La auditoría fresca detectó que `app/Memberships/routes.php` elegía el evento de auditoría desde el input HTTP `$decision`, mientras el contrato F22 exige usar el `charge_outcome` canónico retornado por `MembershipService`.

### RED

- Commit: `4aec788ec4805b5008e5fc7dd96bed0f37f430c8`.
- CI #995, PHP 8.3: **599 tests / 6410 assertions / exactamente 1 fallo**.
- Fallo reproducido: request `decision=create`, service outcome `skipped`; esperado `membership.charge_skipped`, actual `membership.charge_created`.

### GREEN mínimo

- Commit: `edbac36b5033446ae710f97e3b81659abc24c0cc`.
- Cambio lógico: la autoridad del evento deriva de `($result['charge_outcome'] ?? null) === 'created'`.
- Sin tabla, clase, permiso, servicio, ruta o dependencia nueva.
- CI #996: **PASS PHP 8.3 / 8.4 / 8.5**.
- PHP 8.3: **599 tests / 6412 assertions / 0 failures / 0 errors**.

## Presupuesto arquitectónico F22

```text
NEW_TABLES = 1              # audit_log
NEW_DOMAIN_CLASSES = 1      # AuditService
NEW_PERMISSIONS = 0
NEW_PUBLIC_ROUTES = 0
NEW_RUNTIME_DEPENDENCIES = 0
NEW_EVENT_BUS = 0
NEW_SECURITY_FRAMEWORK = 0
NEW_AUTH_STACK = 0
NEW_SCHEDULER = 0
```

Migration 022 crea `audit_log` y solo cambia `notification_jobs.recipient_email` / `recipient_name` a nullable para la regla de retención aprobada.

## Auditoría de cierre — 10 pasadas

1. **Scope:** PASS — solo F22; no implementación F23.
2. **KISS budget:** PASS — 1 tabla, 1 clase de dominio; sin nueva permission/dependency/event-bus/auth-stack/scheduler.
3. **Authority uniqueness:** PASS — `AuditService` es la autoridad de `audit_log`; business services mantienen sus owners; `NotificationService` conserva retention.
4. **Anti-fanout:** PASS — auditoría explícita en route owners aprobados; sin event infrastructure lateral.
5. **Secrets/privacy:** PASS — metadata bounded y secret-shaped keys fail closed; no passwords, raw tokens, temporary credentials, document/payment bodies, SQL ni traces.
6. **Tenant IDOR:** PASS — ownership-first lease resolution se mantiene antes de child lookups.
7. **Auth/CSRF/session:** PASS — límites globales preservados; excepción cron sigue exacta/protegida; cookie/session production policy tiene regresión.
8. **Storage/headers/external URLs:** PASS — private storage fail-closed; headers comunes centralizados; audit viewer `private, no-store`; WhatsApp HTTPS-only y sin candidate continuation capability.
9. **Retention/evidence:** PASS — recipient name/email se redactan solo en terminal rows con corte estricto `< 90 días`, preservando evidencia operativa.
10. **Governance:** PRODUCT PASS / REPOSITORY ACTION REQUIRED — `main` sigue reportando sin protección y sin required checks en la evidencia remota disponible.

## Convergencia Cero Ruido

- `README.md` converge al estado real F21/F22 y deja de presentar F21/F22 como no iniciadas.
- `AGENTS.md` queda como reglas durables, no como tablero volátil de fases.
- `docs/plans/F22_SECURITY_PRIVACY_AUDIT_IMPLEMENTATION_PLAN.md` permanece como contrato de ejecución; no se usa como live status board.
- Los checkpoints transitorios Task 5 preflight/partial-CI fueron retirados del Closure Head porque sus ramas `checkpoint/...` conservan recuperación exacta.
- No se eliminan recovery branches dentro de esta PR.

## Sintaxis / Composer

`composer validate --strict` está certificado por CI #996 en PHP 8.3/8.4/8.5.

El PLAN F22 exige además una ejecución explícita de PHP lint. El workflow canónico actual no ejecuta `php -l`, y el intento de agregar ese paso fue bloqueado por la capa de seguridad de la integración. No se rodeó ese bloqueo ni se creó un workflow paralelo.

Por tanto:

**LINT_EXECUTION_REQUIRED**

El lint debe ejecutarse legítimamente sobre los PHP cambiados o sobre todos los PHP tracked antes del merge. No se declara PASS por inferencia aunque PHPUnit haya cargado/ejecutado el árbol con éxito.

## Gobernanza

La evidencia remota actual de `main` reporta:

- `protected: false`;
- required status checks disabled;
- rulesets observados vacíos en la auditoría previa;
- la integración actual no permite certificar/administrar protección detallada.

Ruling:

**GOVERNANCE_ACTION_REQUIRED**

Recomendación posterior/paralela al cierre: exigir PR, exigir CI canónica y bloquear force-push a `main`. No declarar estos controles activos hasta que GitHub lo demuestre.

## Merge gate

Estado: **MERGE HOLD — LINT_EXECUTION_REQUIRED + FINAL CLOSURE CI REQUIRED**.

PR #27 solo puede mergearse si se cumplen todos:

1. el HEAD de PR coincide exactamente con el Closure Head convergido;
2. se ejecuta y pasa el PHP lint requerido de forma legítima/reproducible;
3. CI final del Closure Head exacto pasa PHP 8.3, 8.4 y 8.5;
4. `composer validate --strict` permanece PASS;
5. base/head/mergeability siguen válidos y no hay review threads sin resolver;
6. no aparece implementación F23 en el diff.

Después del merge F22:

1. gate separado de branch hygiene para branches históricas/scratch/checkpoint supersedidas;
2. refrescar `main` y crear checkpoint post-merge;
3. iniciar F23 desde `main` usando `docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md`.
