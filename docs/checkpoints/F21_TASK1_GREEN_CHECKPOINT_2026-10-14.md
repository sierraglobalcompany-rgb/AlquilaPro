# F21 Task 1 GREEN Checkpoint — 2026-10-14

```text
PROJECT: AlquilaPro
PHASE: F21 — Bonus + Liquidation
STATUS: PAUSED BY USER AFTER TASK 1 GREEN
CANONICAL_MAIN_AT_F21_START: 748c9d3d70ed33bb216daab8322bf6a75573ff85
ACTIVE_BRANCH: impl/f21-bonus-liquidation-20261014
ACTIVE_PR: #26 — F21: bonus and immutable lease liquidation
F21_PRODUCT_HEAD: 71a6a0e6e3baf9a6bfdfbddf1311de05bcf74158
TASK_1: CLOSED / GREEN
TASK_2: NOT STARTED
MERGE_TO_MAIN: NOT PERFORMED
```

## 1. Contexto canónico previo

- F15→F20 quedó integrado y auditado en `main`.
- La convergencia documental F15→F20 fue integrada antes de abrir F21.
- Los SPEC/PLAN F21→F26 también quedaron disponibles desde `main` antes de crear la rama F21.
- F21 se abrió desde `main@748c9d3d70ed33bb216daab8322bf6a75573ff85`.
- PRE-KISS F21 ya existe en `docs/checkpoints/F21_PRE_KISS_EXECUTION_GATE_2026-10-14.md`.

## 2. Rulings PRE-KISS vigentes

1. Reutilizar `MembershipService::listForLease(int $leaseId)`; no crear predicate paralelo para charge decisions.
2. Feature flag canónico: `ALQUILAPRO_GOOD_CONDUCT_BONUS_ENABLED`, default off.
3. `LeaseService::find(int $leaseId): ?array` debe reutilizar `leaseSelect()` + `normalizeLease()`.
4. No crear `LeaseServiceTest.php` solo porque el plan lo mencionaba: el owner de lifecycle existente es `LeaseLifecycleTest.php`.
5. `MigrationRunnerTest` debe mantenerse al avanzar migraciones.
6. `TerminationService` puede componer autoridades existentes, pero sin nuevas capas/facades/repositorios.
7. No nested writer transactions dentro de la finalización F21.
8. Insurance presenta un authority gap para Task 3: si aún se necesita claims/policies lease-scoped, reparar primero `InsuranceService` con la mínima lectura pública y RED focal.
9. Finance/Documents/Community/Obligations tienen APIs reutilizables suficientes.
10. TDD remoto: test-only RED primero, CI esperado en rojo, después GREEN mínimo.

## 3. Task 1 — alcance ejecutado

Producto aprobado y realizado:

```text
+ database/migrations/021_create_lease_termination.sql
+ LeaseService::find(int $leaseId): ?array
+ mantenimiento de MigrationRunnerTest
+ tests focales de schema y canonical lease read
```

No se abrió Task 2 ni se creó `TerminationService`.

### Migración 021

Crea únicamente:

```text
good_conduct_bonuses
lease_liquidations
```

Corrección importante durante RED: la SPEC sí exige `good_conduct_bonuses.updated_at DATETIME NULL`; el test fue corregido antes del GREEN.

### LeaseService

`find(int $leaseId)` reutiliza el SELECT y la normalización canónicos; no duplica la proyección SQL.

## 4. Evidencia TDD Task 1

RED verificado remotamente antes de producto:

```text
545 tests
5722 assertions
2 failures exactos
```

Fallos esperados:

1. migración `021_create_lease_termination.sql` ausente;
2. `LeaseService::find()` ausente.

No hubo error de sintaxis, fixture ni fallo lateral.

GREEN final autorizado para Task 1:

```text
CI: #922
HEAD: 71a6a0e6e3baf9a6bfdfbddf1311de05bcf74158
PHP 8.3: PASS
PHP 8.4: PASS
PHP 8.5: PASS
```

PHP 8.3 registró:

```text
545 tests
5753 assertions
0 failures
0 errors
```

El mismo merge-ref terminó PASS en 8.4 y 8.5.

## 5. Diff antiparches Task 1

Comparación contra `main@748c9d3d...` antes del checkpoint:

```text
6 archivos exactos
0 cambios laterales de dominio
```

Archivos:

```text
app/Leases/LeaseService.php
database/migrations/021_create_lease_termination.sql
docs/checkpoints/F21_PRE_KISS_EXECUTION_GATE_2026-10-14.md
tests/Integration/Foundation/MigrationRunnerTest.php
tests/Integration/Leases/LeaseCanonicalReadTest.php
tests/Integration/Leases/TerminationSchemaTest.php
```

## 6. Estado GitHub al pausar

```text
PR #26: OPEN
mergeable: true
base: main@748c9d3d70ed33bb216daab8322bf6a75573ff85
head: impl/f21-bonus-liquidation-20261014@71a6a0e6e3baf9a6bfdfbddf1311de05bcf74158
changed files: 6
Task 1 CI authority: #922 GREEN 8.3/8.4/8.5
```

La descripción inicial de la PR todavía describe el primer RED y por tanto quedó documentalmente desactualizada; no afecta producto. Al reanudar, puede actualizarse una sola vez junto con el siguiente checkpoint o cierre, evitando commits/docs innecesarios.

## 7. Próximo paso exacto al reanudar

NO repetir Task 1.

Retomar aquí:

```text
Task 2 — Bonus terminal workflow
→ escribir RED test-only atómico
→ feature flag off por defecto
→ amount > 0
→ note <= 1000
→ una fila por lease
→ edición solo mientras pending
→ approved|denied terminal
→ concurrent decision one winner
→ bonus update vs decision serialized
→ 0 writes a charges/payments
→ observar RED correcto en CI
→ implementar GREEN mínimo en TerminationService
→ focal Finance regression
→ CI 8.3/8.4/8.5
→ checkpoint
```

Lock order recomendado para evitar deadlocks:

```text
lease row
→ bonus row
```

No abrir Task 3 hasta Task 2 GREEN.

## 8. Hard stops vigentes

- no F22/F23 mientras F21 esté activo;
- no payout/refund/wallet/deposit/escrow/ledger/history/status-history systems;
- no segundo lease-ending writer;
- no segunda autoridad de documentos/inventarios;
- no cambio al comportamiento de cobro F20;
- no merge de PR #26 hasta cierre completo de F21 y autorización correspondiente;
- no arquitectura especulativa.

## 9. Punto de recuperación corto

```text
RECOVER_FROM:
  branch = impl/f21-bonus-liquidation-20261014
  head = 71a6a0e6e3baf9a6bfdfbddf1311de05bcf74158
  pr = #26
  task1 = GREEN/CLOSED
  ci = #922 GREEN on PHP 8.3/8.4/8.5
  task2 = NOT STARTED
NEXT = Task 2 RED test-only
```
