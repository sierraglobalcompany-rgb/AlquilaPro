# F20 — MEMBRESÍAS + SERVICIOS DIGITALES — SPEC

**Fecha:** 2026-10-14  
**Estado:** `APPROVED DESIGN / IMPLEMENTATION PLAN ALLOWED`  
**Base funcional:** F14 merged; F15→F19 se implementan antes de F20 en el batch apilado.  
**Autoridades relacionadas:** `FinanceService` (F9/F11), `LeaseService` (F8), auth/session/CSRF existentes.

## 1. Objetivo

Permitir que un tenant acepte servicios digitales/adicionales para un lease, conservando precio/duración históricos y obligando a una decisión administrativa explícita sobre el cargo cuando el servicio es pago.

Flujo canónico:

```text
tenant accepts service
→ immutable membership snapshot
→ if price_snapshot > 0: admin sees pending decision
→ admin chooses CREATE or SKIP
```

La aceptación tenant **nunca** crea automáticamente un cargo.

## 2. KISS budget

```text
NEW_TABLES: 2
  digital_services
  service_memberships

NEW_DOMAIN_CLASSES: 1
  MembershipService

NEW_ROUTE_FILES: <=1
NEW_PERMISSIONS: 2
  memberships.view
  memberships.manage

NEW_PUBLIC_ROUTES: 0
NEW_RUNTIME_DEPENDENCIES: 0
NEW_PAYMENT_TABLES: 0
NEW_STATUS_HISTORY_TABLES: 0
NEW_PROVIDER/WEBHOOK/CRON: 0
```

No crear `MembershipChargeService`, `BillingService`, `SubscriptionPaymentService`, provider adapters o tabla de renovaciones.

## 3. digital_services

```text
id BIGINT UNSIGNED PK
name VARCHAR(120) NOT NULL
description VARCHAR(1000) NOT NULL
current_price DECIMAL(12,2) NOT NULL
duration_days INT UNSIGNED NOT NULL
active TINYINT(1) NOT NULL DEFAULT 1
created_by_user_id BIGINT UNSIGNED NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NULL
```

Reglas:
- name trim 1..120;
- description trim 1..1000;
- price >= 0;
- duration_days > 0;
- servicios con histórico no se borran; se desactivan.

## 4. service_memberships

```text
id BIGINT UNSIGNED PK
service_id BIGINT UNSIGNED NOT NULL
lease_id BIGINT UNSIGNED NOT NULL
accepted_by_user_id BIGINT UNSIGNED NOT NULL
price_snapshot DECIMAL(12,2) NOT NULL
duration_days_snapshot INT UNSIGNED NOT NULL
starts_on DATE NOT NULL
ends_on DATE NOT NULL
accepted_at DATETIME NOT NULL
renews_membership_id BIGINT UNSIGNED NULL
ended_at DATETIME NULL
charge_decision VARCHAR(16) NULL
charge_decided_by_user_id BIGINT UNSIGNED NULL
charge_decided_at DATETIME NULL
charge_id BIGINT UNSIGNED NULL
```

FK:
- service_id -> digital_services RESTRICT;
- lease_id -> leases RESTRICT;
- accepted_by_user_id -> users RESTRICT;
- renews_membership_id -> service_memberships RESTRICT;
- charge_decided_by_user_id -> users SET NULL;
- charge_id -> charges RESTRICT.

`charge_id` nullable UNIQUE.

No membership status column. Estado derivado de fechas/ended_at.

## 5. Acceptance

Tenant flow:

```text
tenant guard
→ LeaseService::findForUser(leaseId,userId)
→ load active service
→ reject duplicate active membership for same service/lease
→ snapshot price + duration
→ insert membership
```

`starts_on` = acceptance local date unless staff-defined future-start behavior is explicitly added later. `ends_on` = starts_on + duration_days_snapshot - 1 day.

Tenant cannot choose charge behavior.

## 6. Paid charge decision

Derived pending decision:

```text
price_snapshot > 0
AND charge_decision IS NULL
```

Admin UI must show exactly two explicit actions:

```text
Crear cargo
No crear cargo
```

### CREATE

One transaction:

```text
BEGIN
→ service_membership FOR UPDATE
→ require paid + charge_decision NULL + charge_id NULL
→ FinanceService::createManualCharge(
     lease_id,
     'service',
     price_snapshot,
     admin_due_date,
     explicit/prefilled description,
     null,
     actor_user_id
   )
→ set charge_decision='create'
→ set charge_id
→ set charge_decided_by_user_id/at
→ COMMIT
```

No SQL directo a `charges` desde F20.

### SKIP

One transaction/locked row:

```text
require paid + charge_decision NULL
→ set charge_decision='skip'
→ charge_id stays NULL
→ actor/time
```

`CREATE` y `SKIP` son decisiones terminales para esa aceptación.

Si después se requiere un cobro excepcional tras `SKIP`, debe hacerse por el flujo manual F9 y no reescribir el histórico F20.

Free service (`price_snapshot = 0`): no charge decision required and charge fields remain null.

## 7. Renewal

Renewal = nueva membership row.

Rules:
- original membership remains immutable;
- new row snapshots current service price/duration;
- `renews_membership_id` references previous membership;
- duplicate concurrent renewal must not create two intended rows;
- no automatic financial charge on renewal acceptance; same admin decision applies if paid.

## 8. Staff / tenant surfaces

Staff:
- `memberships.view`: service/membership lists;
- `memberships.manage`: service CRUD/deactivate, explicit charge decision;
- owner bypass existing.

Tenant:
- sees active digital services available to own lease;
- accepts service;
- sees own membership history and derived dates;
- sees safe charge outcome only (`charge_created` boolean / skipped indication) without internal actor metadata.

No public routes.

## 9. Concurrency

Required tests:

```text
accept vs accept same service/lease → one active intended membership
CREATE vs CREATE → one charge
CREATE vs SKIP → one terminal decision
SKIP vs SKIP → one stable decision
renew vs renew → one intended renewal row
```

Primary lock: `service_memberships` row for charge decisions; canonical lease/service uniqueness query/lock strategy for acceptance/renewal.

## 10. Boundaries

F20 does not own:
- payment collection/reconciliation;
- notifications (F16);
- WhatsApp delivery (F17);
- generic documents (F18);
- refunds/credits;
- payment provider;
- automatic renew billing.

## 11. Gate

```text
NEW_TABLES: 2
NEW_DOMAIN_CLASSES: 1
NEW_PERMISSIONS: 2
NEW_PUBLIC_ROUTES: 0
NEW_RUNTIME_DEPENDENCIES: 0
AUTOMATIC_FINANCIAL_SIDE_EFFECT: 0
FINANCE_WRITER: FinanceService only
KISS: PASS
ZERO_NOISE: PASS
ANTIPATCH: PASS
```
