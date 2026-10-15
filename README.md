# AlquilaPro

Aplicación web SSR autoinstalable para administrar arriendos residenciales y parqueaderos, con catálogo público y operación privada para administración e inquilinos.

**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML/CSS + JavaScript mínimo  
**Destino:** Hostinger sobre HTTPS, solo después de certificación local y autorización explícita de F26.

## Autoridad y estado

La autoridad factual, en orden, es:

```text
GitHub real: código / migraciones / tests / SHA
→ CI del SHA exacto
→ checkpoint canónico de fase
→ SPEC / PLAN
→ documentación narrativa / chat
```

Estado actual certificado:

```text
F0→F22: CLOSED / VERIFIED / MERGED
F23: CLOSED / VERIFIED / MERGED
F24: GREEN_LOCAL / INTEGRATION_GATE_PENDING
F25: SERVER_READY: YES / INTEGRATION_GATE_PENDING
F26: DEPLOYMENT_HANDOFF_READY / DEPLOYMENT_AUTHORIZED: NO
F27: DOES NOT EXIST
```

Referencias actuales:

- `main@093d3ef689f61b0a64b7fe24cc55f3b32d70773d` — F23 mergeado y certificado.
- F23 remote closure: `097c257170cf2c1cef9b5fe90b70e91705c79d32`; CI #1003 PASS PHP 8.3/8.4/8.5.
- F24 closure: `4c1ce38af2814340e0dfecb0700e823c33836af8`; full local matrix PHP 8.3/8.4/8.5 PASS.
- F25 branch/head auditado antes de esta convergencia documental: `48f934bc340272af7b9fbf604fab5dfbc9e42271`.
- Artefacto SERVER_READY: `ALQUILA_PRO_V1_SERVER_READY.zip`, versión `1.0.0`, source SHA `647a02d2bd8a06cb034e8cb553151c3b471289da`, SHA-256 `3eb9376fee8d45dac0211e3a9fcd14e6f93069b248c6537c98ab2a85fedf34b3`.
- F25 rehearsal exacto desde extracción nueva + DB inicialmente vacía: PASS.
- F26 no se ejecuta hasta confirmar configuración segura de variables PHP por sitio y scheduler POST con header secreto en el plan Hostinger activo.

Para continuidad usar:

- `docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md`
- `docs/checkpoints/F23_CHECKPOINT_2026-10-15.md`
- `docs/checkpoints/F24_CHECKPOINT_2026-10-15.md`
- `docs/checkpoints/F25_CHECKPOINT_2026-10-15.md`
- `docs/checkpoints/CODEX_HANDOFF_F23_F25_LOCAL_FIRST_2026-10-15.md`
- `docs/qa/F25_SERVER_READY_REHEARSAL_2026-10-15.md`
- `docs/deployment/HOSTINGER_DEPLOYMENT_RUNBOOK.md`

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
- F23: `View` conserva la única autoridad de navegación; layouts no deciden permisos.
- F24: wizard de candidato deriva progreso sin persistencia nueva; `Installer::install()` sigue siendo el único writer final del instalador.
- F25: el ZIP certificado es inmutable; cualquier cambio de bytes exige nuevo hash, auditoría y rehearsal desde cero.

## Guardrails actuales

1. `main` sigue sin protección/required checks en la evidencia remota: **GOVERNANCE_ACTION_REQUIRED** hasta que GitHub demuestre lo contrario.
2. La carpeta local `.playwright-cli` es tooling/ruido local; no forma parte de Git ni del ZIP.
3. El artefacto F25 no autoriza despliegue por sí mismo.
4. Antes de F26 debe confirmarse en el hPanel real una vía segura para variables PHP y un cron/scheduler capaz de hacer POST con `X-AlquilaPro-Cron-Secret` sin exponer el secreto.
5. No existe F27.

## QA

La CI canónica usa PHP 8.3, 8.4 y 8.5, `composer validate --strict`, parser/lint PHP y PHPUnit. Nunca declarar una fase cerrada reutilizando CI de un SHA anterior. La certificación local de F24 y el rehearsal de F25 complementan, pero no sustituyen, el gate remoto de integración antes de mergear F24/F25 a `main`.
