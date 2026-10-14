# AlquilaPro — checkpoint final recuperable F12 → F14

Fecha: 2026-10-14 (America/Bogota)  
Estado: **F12 / F13 / F14 CLOSED · VERIFIED · MERGED**  
Autoridad: este checkpoint supersede el estado pre-merge del batch F12→F14.

## 1. Estado canónico

```text
MAIN_START_BEFORE_BATCH:
f652a702562ffdf914e17e1f64aedafd1250ff38

F12_STATUS:
CLOSED / VERIFIED / MERGED
F12_PR:
#15
F12_BRANCH:
impl/f12-services-obligations-20261014
F12_BRANCH_HEAD:
ef0b6c23d50fd8dcc38a2880bbf65aff6553b4d6
F12_MERGE_SHA:
23cf7576c95fca5e55825d8b5ffd5903bf3d3149
F12_FINAL_CI:
38519741952
F12_FINAL_MERGE_REF:
81298b94ff37216712db30f4e6f7557c3e6fd99e
F12_PHP_8_3:
PASS — 406 tests / 4,193 assertions
F12_PHP_8_4:
PASS — 406 tests / 4,193 assertions
F12_PHP_8_5:
PASS — 406 tests / 4,193 assertions

F13_STATUS:
CLOSED / VERIFIED / MERGED
F13_PR:
#16
F13_BRANCH:
impl/f13-insurance-claims-20261014
F13_PRODUCT_HEAD:
a0cce18f1cc2a765046c861e57f08a8e245721e8
F13_DOC_CLOSURE:
89ce4be7d307d1d531d891528b59b4d26a6e0123
F13_PRIOR_REPAIR_HEAD:
935c02b5f1311fc3453ed32ea747b371c1d168cc
F13_SECURITY_REPAIR_HEAD:
b11705cb387129c51090bbef0c3658c4843f940d
F13_SEC_01:
CLOSED
F13_MERGE_SHA:
57911302bd4e97e76b1e0fa6dc71c8ef24372453
F13_FINAL_CI:
38553176872
F13_FINAL_MERGE_REF:
1a7e1c23f397718fd9083a7944642a87dd90fc09
F13_PHP_8_3:
PASS — 431 tests / 4,451 assertions
F13_PHP_8_4:
PASS — 431 tests / 4,451 assertions
F13_PHP_8_5:
PASS — 431 tests / 4,451 assertions

F14_STATUS:
CLOSED / VERIFIED / MERGED
F14_PR:
#17
F14_BRANCH:
impl/f14-maintenance-requests-20261014
F14_PRODUCT_HEAD:
d6e77fd7ced5237d03947360733fe198e0c443e8
F14_PRE_PROPAGATION_CLOSURE_HEAD:
11991b99bb307814eb5f01fc50de58dab8246ca1
F14_STACK_PROPAGATION_HEAD:
b3af752a360cd2ada051526806475b52cc7293ee
F14_MERGE_SHA:
c58ee5e660f51e873546f03a578fde6b5778ba19
F14_FINAL_CI:
38553704399
F14_FINAL_MERGE_REF:
b34531e332544af82bf4b0e86d5b8a8e164d025a
F14_PHP_8_3:
PASS — 443 tests / 4,604 assertions
F14_PHP_8_4:
PASS — 443 tests / 4,604 assertions
F14_PHP_8_5:
PASS — 443 tests / 4,604 assertions

PRODUCT_MAIN_AFTER_F14_MERGE:
c58ee5e660f51e873546f03a578fde6b5778ba19

KISS:
PASS
ZERO_NOISE:
PASS
PATCH_STACKING:
NONE
OPEN_FINDINGS_F12_F14:
NONE
```

## 2. Secuencia real de integración

La integración se hizo de forma secuencial y autorizada por el usuario:

```text
PR #15 F12 -> main
  merge 23cf7576c95fca5e55825d8b5ffd5903bf3d3149

PR #16 F13 -> retarget main
  se comprobó que main posterior a F12 y F12 branch head tenían cero diferencias de archivos
  mergeable_state=clean
  merge 57911302bd4e97e76b1e0fa6dc71c8ef24372453

PR #17 F14 -> retarget main
  se comprobó que main posterior a F13 y F13 repaired head tenían cero diferencias de archivos
  mergeable_state=clean
  merge c58ee5e660f51e873546f03a578fde6b5778ba19
```

La retargetización no disparó una nueva CI porque el cambio de base sólo añadió commits de merge; se comprobó explícitamente **cero delta de archivos** frente a las bases que ya habían sido certificadas por sus merge-ref CI. No se generaron CI/commits vacíos para forzar una verificación equivalente.

## 3. F12 — servicios y obligaciones

Arquitectura final:

```text
1 table: lease_obligations
1 service: ObligationService
0 new permissions
0 public routes
FinanceService remains canonical charge writer
```

Alcance entregado:
- obligaciones `utility`, `administration_ph`, `fine_ph`;
- soportes privados;
- estado externo derivado;
- generación/vínculo atómico con cargos canónicos;
- tenant read-only con ownership de lease;
- concurrencia generate/generate, generate/void y link/link.

No se duplicaron pagos, balances, proveedores, historial ni motores futuros.

## 4. F13 — pólizas y reclamaciones

Arquitectura final:

```text
2 tables: insurance_policies, insurance_claims
1 service: InsuranceService
<=1 route file
2 permissions: insurance.view, insurance.manage
0 public routes
0 financial writers
```

Finding de auditoría `F13-SEC-01` cerrado en `b11705cb...`:
- tamaño físico validado antes de leer;
- `fstat()` revalida el descriptor abierto;
- lectura limitada a `MAX_DOCUMENT_BYTES + 1`;
- MIME y SHA calculados sobre los mismos bytes;
- archivo missing/tampered/oversize falla cerrado.

El repair sólo modificó `InsuranceService.php` y su regresión. No expandió arquitectura.

## 5. F14 — solicitudes de mantenimiento

Arquitectura final:

```text
1 table: maintenance_requests
1 service: MaintenanceService
<=1 F14 route file
2 permissions: maintenance.view, maintenance.manage
0 public routes
0 runtime dependencies
0 visit/calendar tables
```

Alcance entregado:
- solicitudes lease-scoped;
- tenant/staff;
- categoría/prioridad;
- estados y asignación;
- clasificación de daño;
- fotos privadas con integridad;
- cargo `repair` explícito mediante FinanceService;
- concurrencia de fotos, terminales y charge/cancel.

La propagación `b3af752a...` incorporó únicamente el repair F13; no añadió cambios propios de producto F14.

## 6. Seguridad / arquitectura / leyes

```text
KISS: PASS
ZERO_NOISE: PASS
ANTIPATCH: PASS
SECOND_FINANCIAL_WRITER: NO
PARALLEL_AUTH_SYSTEM: NO
PUBLIC_INTERNAL_ROUTES: NO
SPECULATIVE_F15_PRODUCT: NO
```

No hay blockers abiertos de F12, F13 o F14.

## 7. Eficiencia observada

```text
LOCAL_FULL_SUITE_RUNS_FOR_FINAL_REPAIRS: 0
F13_SECURITY_REPAIR_REMOTE_CI: 1
F14_PROPAGATION_REMOTE_CI: 1
EMPTY_COMMITS_FOR_CI: 0
REDUNDANT_RETARGET_CI: 0
```

La política FAST-KISS se mantiene para fases siguientes:

```text
focused integrated
-> fresh review
-> one repair pass if needed
-> affected focused
-> one final PR CI
-> audit
-> merge
```

No repetir una suite o CI si el árbol probado es demostrablemente idéntico.

## 8. Continuidad si se pierde el chat

Antes de iniciar F15:

1. leer este checkpoint;
2. verificar `main` actual;
3. leer SPEC/PLAN maestro;
4. revisar código real F14 (`MaintenanceService`, routes, schema);
5. hacer PRE-KISS F15;
6. no reimplementar F12–F14.

Diseño adelantado en conversación para próximo bloque:

```text
F15: agenda + visitas, dependiente de F14
F16: motor único de recordatorios + cron + email
F17: WhatsApp asistido, sin fingir delivery/read
F18: autoridad documental genérica + manual + inventarios
F19: convivencia + contenido legal reutilizando F18
F20: requiere definición de producto antes de congelar schema
```

F20 permanece deliberadamente bloqueada hasta definir:

```text
A) servicios/add-ons para tenant/lease
B) suscripción owner/admin a AlquilaPro
C) otro consumidor concreto

Y:
¿aceptación crea cargo F9? YES / NO
```

## 9. Próxima acción

```text
CURRENT_GATE:
F14 MERGED / BATCH F12-F14 CLOSED

NEXT_PHASE:
F15 PRE-KISS + SPEC/PLAN auditado

NEXT_CODEX_BATCH_TARGET:
F15 -> F20, sólo después de aprobar las SPEC y resolver F20

DO_NOT:
reabrir F12-F14 sin evidencia de defecto
crear F15+ arquitectura antes de SPEC/PLAN
crear pagos/finanzas paralelos
```
