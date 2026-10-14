# ALQUILAPRO — F20 PRODUCT DECISION

**Fecha:** 2026-10-14  
**Estado:** `PARTIAL PRODUCT DECISION / ONE COMMERCIAL GATE REMAINS`

## Decisión confirmada por el usuario

```text
F20_CONSUMER_MODEL:
A = tenant/lease add-on service
```

F20 representa servicios digitales/adicionales que un inquilino puede aceptar para un lease existente.

No es:

```text
owner/admin SaaS subscription
AlquilaPro billing plan
property-level subscription
provider billing platform
```

## Consecuencias arquitectónicas ya válidas

La propuesta KISS sigue siendo:

```text
2 tables:
  digital_services
  service_memberships

1 domain authority:
  MembershipService
```

`digital_services` define el servicio vigente:

```text
name
description
current_price
duration
active
```

`service_memberships` conserva snapshot/histórico por lease:

```text
service_id
lease_id
accepted_by_user_id
price_snapshot
duration_snapshot
starts_on
ends_on
renews_membership_id
accepted_at
ended_at
```

Histórico = filas inmutables. No `membership_status_history`.

## Gate comercial todavía pendiente

Antes de congelar la SPEC F20 completa hay una única decisión:

```text
F20_CREATE_F9_CHARGE_ON_ACCEPTANCE:
YES | NO
```

Interpretación:

- `YES`: aceptar una membresía paga crea explícitamente el cargo correspondiente mediante la autoridad financiera existente F9/`FinanceService`; F20 no escribe cargos por SQL directo.
- `NO`: F20 registra aceptación/precio/histórico pero no crea obligación financiera automática; cualquier cobro se haría por un flujo financiero separado/explícito.

Hasta resolverlo:

```text
F20_FULL_SPEC: BLOCKED_ON_ONE_DECISION
F20_PLAN: BLOCKED
PRODUCT_IMPLEMENTATION: NOT AUTHORIZED
```

## Leyes

```text
KISS: PASS
ZERO_NOISE: PASS
ANTIPATCH: PASS
FINANCE_AUTHORITY: PRESERVED
```
