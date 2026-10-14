# ALQUILAPRO — F20 PRODUCT DECISION

**Fecha:** 2026-10-14  
**Estado:** `PRODUCT DECISION COMPLETE / FULL SPEC CAN BE PLANNED`

## Decisiones confirmadas por el usuario

```text
F20_CONSUMER_MODEL:
A = tenant/lease add-on service

F20_CHARGE_BEHAVIOR:
NO AUTOMATIC CHARGE ON TENANT ACCEPTANCE

ADMIN_CHARGE_DECISION:
CREATE | SKIP
```

F20 representa servicios digitales/adicionales que un inquilino acepta para un lease existente.

No es:

```text
owner/admin SaaS subscription
AlquilaPro billing plan
property-level subscription
provider billing platform
```

## Flujo comercial congelado

Cuando el tenant acepta un servicio:

```text
tenant accepts service
→ membership acceptance is persisted immediately
→ if price_snapshot > 0, admin sees a pending charge decision
→ admin chooses CREATE or SKIP
```

Aceptar el servicio **nunca** crea por sí solo un cargo financiero.

### CREATE

La acción administrativa explícita:

```text
locks membership
→ revalidates pending charge decision
→ calls canonical FinanceService
→ creates exactly one F9 charge
→ stores charge_id
→ records decision actor/time
```

El cargo debe usar la autoridad financiera existente; F20 no escribe SQL directo a `charges`.

Tipo preferido del cargo:

```text
service
```

Monto:

```text
membership.price_snapshot
```

La fecha de vencimiento la suministra/confirma el administrador en la acción de creación del cargo.

### SKIP

La acción administrativa explícita:

```text
records charge_decision = skip
records actor/time
creates no charge
```

La decisión `SKIP` es histórica para esa membresía. No se crea un cargo F20 posterior sobre esa misma aceptación. Si negocio requiere un cobro excepcional posterior, debe hacerse mediante el flujo financiero manual canónico, sin reescribir el histórico F20.

## Arquitectura KISS congelada

```text
2 tables:
  digital_services
  service_memberships

1 domain authority:
  MembershipService

0 payment/provider tables
0 status-history tables
0 automatic finance writer
```

### digital_services

Define oferta vigente:

```text
id
name
description
current_price
duration_days
active
created_by_user_id
created_at
updated_at
```

No borrar servicios usados históricamente; desactivar.

### service_memberships

Snapshot/histórico lease-scoped:

```text
id
service_id
lease_id
accepted_by_user_id
price_snapshot
duration_days_snapshot
starts_on
ends_on
accepted_at
renews_membership_id nullable
ended_at nullable

charge_decision nullable       # create | skip
charge_decided_by_user_id nullable
charge_decided_at nullable
charge_id nullable
```

Reglas:

```text
price_snapshot == 0
  → no admin charge decision required

price_snapshot > 0 AND charge_decision IS NULL
  → admin UI shows pending decision: Crear cargo / No crear cargo

charge_decision == create
  → charge_id must be present after successful transaction

charge_decision == skip
  → charge_id must remain null
```

`charge_id` es nullable y UNIQUE para impedir más de un cargo F20 por membership.

Histórico = filas inmutables de membresía; no `membership_status_history`.

Renovación:
- nueva fila de `service_memberships`;
- snapshot nuevo de precio/duración;
- `renews_membership_id` apunta a la membresía previa;
- nunca modificar snapshots históricos.

## Autoridad financiera

```text
FinanceService = único writer de cargos
MembershipService = decide/coordina la acción explícita F20
```

La transacción de CREATE debe compartir el mismo PDO/transaction boundary y llamar a la API pública canónica de `FinanceService`.

No introducir:

```text
MembershipChargeService
BillingService
SubscriptionPaymentService
payment provider
webhook
cron de cobro
auto-renew financial charge
```

## Concurrencia mínima

Probar:

```text
CREATE vs CREATE → one charge
CREATE vs SKIP → one terminal decision
SKIP vs SKIP → first decision wins/idempotent outcome
renewal vs duplicate renewal → one intended renewal row
```

Lock principal:

```text
service_memberships row first
```

## Estado de diseño

```text
F20_CONSUMER_MODEL: RESOLVED
F20_CHARGE_BEHAVIOR: RESOLVED
F20_FULL_SPEC: READY TO FREEZE
F20_PLAN: READY TO WRITE
PRODUCT_IMPLEMENTATION: NOT YET AUTHORIZED
```

## Leyes

```text
KISS: PASS
ZERO_NOISE: PASS
ANTIPATCH: PASS
FINANCE_AUTHORITY: PRESERVED
AUTOMATIC_FINANCIAL_SIDE_EFFECT: NONE
```
