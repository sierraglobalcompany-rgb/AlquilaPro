# AlquilaPro

Aplicación web SSR autoinstalable para administrar arriendos residenciales y parqueaderos, con catálogo público y operación privada para administración e inquilinos.

**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML/CSS + JavaScript mínimo  
**Destino:** Hostinger sobre HTTPS, después de certificación local/release readiness.

## Autoridad y estado

La autoridad factual, en orden, es:

```text
GitHub real: código / migraciones / tests / SHA
→ CI del SHA exacto
→ checkpoint canónico de fase
→ SPEC / PLAN
→ documentación narrativa / chat
```

Estado de cierre F22:

```text
F0→F21: CLOSED / VERIFIED / MERGED
F22: IMPLEMENTATION + PRODUCT VERIFICATION COMPLETE / FINAL CLOSURE CI + MERGE GATE
F23→F26: FROZEN / NOT STARTED hasta merge F22
F27: DOES NOT EXIST
```

Base usada para F22: `main@7c0f0c75c665b9888d26bfdbfd5be9bec4df3b82` (F21 merged).  
Product Head F22 certificado antes de convergencia documental/tooling: `edbac36b5033446ae710f97e3b81659abc24c0cc`, CI #996 PASS PHP 8.3/8.4/8.5; PHP 8.3 = 599 tests / 6412 assertions.

Para continuidad/cierre F22 usar:

- `docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md`
- `docs/plans/F22_SECURITY_PRIVACY_AUDIT_IMPLEMENTATION_PLAN.md` — contrato de ejecución, no tablero vivo
- `docs/checkpoints/F22_CHECKPOINT_2026-10-15.md` — checkpoint canónico de cierre
- PR #27 + CI del HEAD exacto — estado remoto operativo

Para la siguiente fase usar `docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md` **solo después del merge F22**.

## Leyes obligatorias

### 1. KISS

Arquitectura directa, simple, eficiente, optimizada y eficaz. Reutilizar owners existentes antes de agregar tablas, clases, servicios, permisos, rutas, schedulers, dependencias o frameworks.

### 2. Cero Ruido / Convergencia

```text
ELIMINAR
→ UNIR / CONVERGER
→ SIMPLIFICAR
→ MEJORAR / OPTIMIZAR
→ AGREGAR
```

Toda eliminación debe demostrar que no destruye autoridad, contrato, evidencia o recuperación necesaria. La historia útil vive en Git/checkpoints/PR/CI; no debe convertirse en una segunda autoridad viva.

### 3. Anti-parche

```text
REPRODUCE
→ ROOT CAUSE
→ OWNING AUTHORITY
→ SEARCH DUPLICATION
→ RED
→ MINIMAL GREEN
→ REMOVE SUPERSEDED LOGIC
→ REGRESSION
```

No corregir síntomas con capas laterales o fallbacks si puede corregirse el owner.

## Flujo de trabajo

```text
SCOPE
→ PRE-KISS
→ SPEC / DESIGN
→ PLAN
→ AUDIT 10 PASSES
→ RED
→ VERIFY RED
→ MINIMAL GREEN
→ REGRESSION
→ CI
→ FORENSIC AUDIT
→ CHECKPOINT
→ MERGE
```

Los checkpoints transitorios deben preferir ramas `checkpoint/...`; el HEAD final de una fase converge a un checkpoint documental canónico.

## Contratos que no deben romperse

- Tenant: resolver `LeaseService::findForUser()` antes de consultar recursos hijos.
- Finanzas: `FinanceService` conserva la autoridad financiera; no crear segundo writer/ledger.
- Notificaciones: `NotificationService` conserva cola, procesamiento y retención; reutilizar el cron protegido existente.
- WhatsApp V1 sigue siendo salida asistida por snapshot; no inventar estados `sent/delivered/read`.
- Documentos/private storage: path seguro, no symlink, cap físico, bounded read, MIME por bytes, integridad y fail-closed según owner.
- Memberships: aceptación/renovación no genera cargo automáticamente; la decisión administrativa usa la autoridad de `MembershipService`/`FinanceService`.
- F22: un solo `AuditService` y una sola tabla `audit_log`; no segunda pila de seguridad/logging/event bus.
- Audit metadata nunca debe contener passwords, raw tokens, cookies/sesiones, documento/receipt/payment bodies, SQL o exception traces.
- La acción de auditoría de Memberships deriva del `charge_outcome` canónico retornado por `MembershipService`, no del input HTTP.

## F22 — presupuesto arquitectónico certificado

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

La migración 022 crea `audit_log` y solo relaja a nullable `notification_jobs.recipient_email` / `recipient_name` para la retención terminal aprobada.

## Deuda / guardrails no bloqueantes

No convertir estas deudas en sistemas paralelos dentro de F22:

1. algunos scans/listados históricos conservan límites fijos o carecen de paginación;
2. F18 symlink fue certificado en Linux CI aunque no en Windows local;
3. limpieza de ramas históricas/scratch se hace en gate separado después del merge F22;
4. `main` sigue sin protección/required checks en la evidencia remota de cierre: **GOVERNANCE_ACTION_REQUIRED** hasta que GitHub demuestre lo contrario.

## QA

La CI canónica debe usar PHP 8.3, 8.4 y 8.5, `composer validate --strict`, lint PHP y PHPUnit. Nunca declarar una fase cerrada reutilizando CI de un SHA anterior.
