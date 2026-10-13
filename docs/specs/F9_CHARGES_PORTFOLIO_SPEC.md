# F9 — CARGOS + CARTERA — SPEC

**Proyecto:** AlquilaPro  
**Fecha:** 2026-10-13 (America/Bogota)  
**Rama de diseño:** `spec/f9-charges-portfolio-20261013`  
**Estado:** `DRAFT / USER REVIEW REQUIRED / IMPLEMENTATION BLOCKED ON F8 FINAL AUDIT`  
**Fuentes canónicas:** `ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`, `ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`, F8 SPEC/PLAN/checkpoints y código real del repositorio.

---

# 1. OBJETIVO

F9 introduce el **único motor financiero de cargos y cartera** de AlquilaPro sin implementar todavía medios de pago, reportes de pago ni conciliación.

Debe permitir:

- generar cargos mensuales de canon y administración desde el snapshot contractual ya congelado;
- registrar cargos manuales legítimos de servicio, multa, reparación u otro;
- consultar cartera administrativa por arrendamiento/inquilino;
- mostrar al inquilino sus cargos y saldo dentro del portal F8 existente;
- identificar vencimiento/mora operativa sin inventar intereses, sanciones ni reglas legales automáticas;
- anular cargos con trazabilidad, sin borrarlos;
- dejar una única autoridad que F10/F11 extiendan después con medios de pago, pagos y allocations.

F9 **no es contabilidad**, no es facturación electrónica, no es conciliación bancaria y no es un ERP financiero.

---

# 2. DEPENDENCIA Y GATE DE ARRANQUE

F9 depende de F8 porque todo cargo pertenece a un `lease` canónico y el portal tenant ya existe allí.

La implementación productiva F9 queda bloqueada hasta que:

1. F8 termine `CLOSED / VERIFIED`;
2. ChatGPT/Sol audite el cierre real de F8 contra GitHub/CI;
3. se fije el SHA base exacto de implementación F9;
4. no existan bloqueos F8 que cambien `leases`, ownership tenant o portal.

Esta rama de SPEC parte de `main` F7 únicamente para aislar documentación. **No es base autorizada de implementación F9**.

La implementación F9 debe partir del F8 finalmente auditado/aceptado.

---

# 3. LEYES SUPREMAS

## LEY 1 — KISS

F9 debe resolver cargos/cartera con el mínimo razonable de tablas, clases, rutas, estados y dependencias.

Preferencias obligatorias:

- derivar en vez de persistir datos calculables;
- reutilizar `leases`, `contracts`, `users`, shell y portal existentes;
- una sola clase de dominio financiera;
- una sola tabla F9;
- SSR existente;
- cero dependencias runtime nuevas;
- cero automatización temporal/cron en F9.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

F9 no debe crear por anticipado piezas pertenecientes a F10/F11/F12/F13/F16/F20.

No se aceptan tablas vacías, tipos futuros, rutas futuras, estados de pago ficticios ni compatibilidad especulativa.

## 3.1 Supersesiones ya demostradas

La lista maestra V1.2 contiene candidatos históricos de persistencia como `tenants` y `lease_units`. F8 ya demostró que esos conceptos no requieren tablas propias: tenant = `users.role=tenant` y lease deriva la unidad mediante el contrato.

Por lo tanto F9 **no puede resucitar `tenants`, `lease_units`, `tenant_users` ni `tenant_sessions`** por el solo hecho de aparecer como candidatos antiguos en la SPEC maestra.

La implementación real más reciente y los checkpoints F8 prevalecen para esas decisiones ya convergidas.

---

# 4. PRE-KISS — DECISIONES ESTRUCTURALES

## 4.1 Una sola tabla nueva

F9 añade exactamente:

`charges`

No añade:

- `payments`;
- `payment_allocations`;
- `payment_methods`;
- `payment_reports`;
- `receivables`;
- `balances`;
- `late_fees`;
- `charge_status_history`;
- `charge_periods`;
- `ledger_entries`.

Los pagos y allocations entran en F11 sobre esta misma autoridad financiera.

## 4.2 Una sola clase de dominio

F9 añade exactamente:

`FinanceService`

No crear:

- `ChargeService` + `PortfolioService` paralelos;
- repositories/DTOs/interfaces;
- event bus;
- accounting service;
- balance cache service.

F10/F11 deben extender el mismo motor antes de justificar otra autoridad.

## 4.3 Saldo derivado

En F9 todavía no existen pagos.

Por lo tanto:

`saldo = suma(amount) de cargos no anulados`

No persistir `balance`, `outstanding_amount`, `paid_amount` ni `remaining_amount` en `charges`.

Cuando F11 agregue allocations, el saldo se extenderá a:

`amount - allocated payments`

sin migrar una segunda fuente de verdad.

## 4.4 Estado derivado

F9 **no persiste `status` en charges**.

Proyección F9:

- `voided` si `voided_at IS NOT NULL`;
- `overdue` si no anulado y `due_date < today`;
- `pending` en los demás casos.

Los estados de la SPEC maestra que dependen de pagos/reportes (`partial`, `reported`, `proof_received`, `paid`) pertenecen a F11 y se derivarán de payments/reports/allocations cuando esas fuentes existan.

`in_claim` pertenece a la futura integración de pólizas/reclamaciones y no se precrea en F9.

## 4.5 Mora operativa, no interés legal

En F9 “mora” significa únicamente:

- cargo vencido;
- cantidad de días vencidos;
- monto vencido.

F9 no calcula:

- intereses moratorios;
- sanciones automáticas;
- indexaciones;
- prorrateos;
- honorarios;
- penalidades legales.

Una multa es siempre una acción administrativa explícita y justificable, nunca una consecuencia automática del reloj.

---

# 5. PRESUPUESTO ARQUITECTÓNICO F9

```text
NEW_TABLES: 1
  charges

NEW_DOMAIN_CLASSES: 1
  FinanceService

NEW_ROUTE_FILES: 1 maximum
  app/Finance/routes.php

NEW_PERMISSIONS: 2
  finance.view
  finance.manage

NEW_PUBLIC_ROUTES: 0
NEW_DIRECT_RUNTIME_DEPENDENCIES: 0
NEW_AUTH_SYSTEMS: 0
NEW_SESSION_SYSTEMS: 0
NEW_CRON_JOBS: 0
NEW_PAYMENT_TABLES: 0
NEW_BALANCE_TABLES: 0
NEW_STATUS_HISTORY_TABLES: 0
```

`finance.view/manage` se justifican porque cartera/pagos constituyen una autoridad operativa distinta de contratos y serán reutilizados por F10/F11. No debe usarse `contracts.manage` como permiso financiero genérico.

Owner conserva bypass según el sistema existente.

---

# 6. MODELO `charges`

Esquema conceptual exacto:

```text
id BIGINT UNSIGNED PK AUTO_INCREMENT
lease_id BIGINT UNSIGNED NOT NULL
type VARCHAR(32) NOT NULL
period_month DATE NULL
due_date DATE NOT NULL
amount DECIMAL(12,2) NOT NULL
description VARCHAR(500) NULL
created_by_user_id BIGINT UNSIGNED NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
voided_by_user_id BIGINT UNSIGNED NULL
voided_at DATETIME NULL
void_reason VARCHAR(500) NULL
```

No añadir columna `currency` en F9: los contratos F7 actuales tampoco modelan multi-moneda y V1 opera bajo una única moneda funcional. Multi-moneda requeriría una SPEC futura real, no una columna especulativa.

## 6.1 FK e índices

FK:

- `lease_id → leases.id ON DELETE RESTRICT`;
- `created_by_user_id → users.id ON DELETE SET NULL`;
- `voided_by_user_id → users.id ON DELETE SET NULL`.

Índices aprobados:

```text
KEY charges_lease_due_index (lease_id, due_date)
KEY charges_lease_period_type_index (lease_id, period_month, type)
KEY charges_created_by_user_index (created_by_user_id)
KEY charges_voided_by_user_index (voided_by_user_id)
```

No crear índices separados de `type`, `period_month` o `voided_at` sin una consulta demostrada que los necesite.

No cascade de cargos al borrar lease.

## 6.2 Tipos F9

Allowlist F9 exacta:

```text
rent
administration
service
fine
repair
other
```

No añadir `membership` todavía: la membresía entra en F20 y entonces extiende la allowlist del mismo motor.

No añadir tipos específicos de servicios públicos: F12 podrá usar `service` con contexto/descripcion sin duplicar el motor financiero.

## 6.3 Reglas de monto

- `amount > 0`;
- máximo compatible con `DECIMAL(12,2)`;
- máximo 2 decimales;
- boundary de dominio trabaja con decimal/string normalizado, no `float` como autoridad monetaria;
- un cargo creado no se edita destructivamente.

Cargo incorrecto:

`anular + crear cargo correcto`

Esto conserva trazabilidad sin tabla de historial de ediciones.

## 6.4 `description`

`description` es **concepto financiero visible al tenant**, no campo de notas internas.

Para `service|fine|repair|other` es obligatorio y debe explicar de manera breve el concepto cobrado sin PII innecesaria ni diagnóstico/nota interna sensible.

Si un módulo futuro necesita evidencias o notas privadas, debe conservarlas en su propia autoridad y referenciar/generar el cargo de forma explícita; no sobrecargar `charges.description` como expediente.

## 6.5 Período

`period_month` representa el primer día del mes (`YYYY-MM-01`).

- obligatorio para `rent|administration`;
- opcional para `service|fine|repair|other`;
- no existe `period_end` porque es derivable;
- no existe tabla de períodos.

## 6.6 Vencimiento

`due_date` es explícito por cargo/período.

F9 no infiere por ley ni por cron una fecha de vencimiento “correcta”.

No persistir una segunda regla global de vencimiento en F9.

---

# 7. GENERACIÓN DE CANON Y ADMINISTRACIÓN

El contrato F7 ya congela:

- `monthly_rent`;
- `administration_fee`;
- `start_date`;
- `end_date`.

F9 lee estos valores mediante `lease → contract`.

## 7.1 Operación mensual

Operación conceptual:

`FinanceService::createMonthlyCharges(leaseId, periodMonth, dueDate, actorUserId)`

Orden mínimo:

1. BEGIN;
2. `lease FOR UPDATE`;
3. leer contrato asociado;
4. validar `period_month` dentro del rango mensual contractual;
5. rechazar `rent` activo duplicado del mismo lease/período;
6. crear `rent` por `contracts.monthly_rent`;
7. si `administration_fee` es no-null y > 0, rechazar duplicado y crear `administration`;
8. COMMIT;
9. devolver cargos creados.

No copiar valores contractuales a otra autoridad antes de crear el cargo.

El `amount` del cargo creado sí es snapshot financiero histórico y no cambia después.

## 7.2 Duplicados y concurrencia

No crear `dedupe_key` ni tabla de períodos.

Serializar por `lease FOR UPDATE`, luego consultar cargos `rent|administration` no anulados del mismo `period_month`.

Dos intentos concurrentes no pueden producir dos cánones activos para el mismo lease/período.

Debe probarse.

## 7.3 Lease terminado

Se pueden registrar cargos históricos/finales sobre un lease terminado.

Para `rent|administration`, el período debe estar dentro de `contracts.start_date/end_date`.

F9 no infiere prorrateo a partir de `ended_at` ni bloquea completar retroactivamente un período contractual faltante.

---

# 8. CARGOS MANUALES

Tipos manuales:

```text
service
fine
repair
other
```

Operación conceptual:

`FinanceService::createManualCharge(leaseId, type, amount, dueDate, description, periodMonth?, actorUserId)`

Reglas:

- lease existente;
- type en allowlist manual;
- amount > 0;
- description obligatoria;
- period opcional;
- no modifica contract/lease/candidate/screening/unit status;
- no crea pagos/reportes;
- no se dispara automáticamente desde módulos futuros.

`rent|administration` no entran por este camino, para no eludir reglas de snapshot/período/duplicado.

F12/F14/F20 podrán invocar explícitamente este mismo motor en fases futuras; F9 no crea esas integraciones.

---

# 9. ANULACIÓN

No existe DELETE operativo.

Operación conceptual:

`FinanceService::voidCharge(chargeId, actorUserId, reason, now)`

Reglas:

1. transaction;
2. `charge FOR UPDATE`;
3. require `voided_at IS NULL`;
4. reason obligatoria;
5. guardar actor/time/reason una vez;
6. segundo intento falla cerrado y preserva originales;
7. no eliminar;
8. no reemplazar automáticamente.

Cargo anulado:

- permanece en histórico;
- no suma saldo;
- no suma mora;
- F11 no podrá aplicarle pagos.

`void_reason` es administrativo; no se expone al tenant por defecto.

---

# 10. SALDO Y CARTERA

## 10.1 Saldo F9

```text
outstanding = SUM(amount WHERE voided_at IS NULL)
overdue = SUM(amount WHERE voided_at IS NULL AND due_date < today)
```

No persistir estas sumas.

## 10.2 Días vencidos

```text
max(0, today - due_date)
```

Cargo anulado/no vencido: `0`.

No persistir `days_overdue`.

## 10.3 Fecha actual

La lógica que depende de “hoy” debe recibir fecha/clock testeable desde su boundary en vez de esconder dependencias temporales dentro de queries imposibles de fijar en pruebas.

---

# 11. ADMINISTRACIÓN / UX

## 11.1 Cartera central

Ruta privada mínima:

`GET /admin/finance`

Requiere `finance.view`.

Debe mostrar cartera operativa, no dashboard decorativo:

- inquilino;
- propiedad/unidad;
- lease;
- saldo total;
- saldo vencido;
- vencimiento pendiente más antiguo si existe;
- acceso al contexto existente.

Filtros solo si aportan valor real:

- con mora / sin mora;
- búsqueda simple inquilino/unidad.

Sin charts/KPIs en F9.

## 11.2 Contexto existente

Reutilizar detalle administrativo F8/F7 para:

- listar cargos del lease;
- generar canon/administración;
- crear cargo manual;
- anular cargo.

No crear CRUD separado de charges si el contexto existente resuelve la operación.

## 11.3 Rutas F9

```text
GET  /admin/finance
POST /admin/leases/{id}/charges/monthly
POST /admin/leases/{id}/charges
POST /admin/charges/{id}/void
```

Total esperado: **4 rutas privadas**.

Mutaciones:

- auth existente;
- forced-password gate;
- `finance.manage`;
- global CSRF;
- basePath;
- PRG 303.

0 rutas públicas.

---

# 12. PORTAL TENANT

Preferencia KISS: **0 rutas tenant nuevas**.

Extender:

`GET /tenant/leases/{id}`

con:

- saldo actual;
- saldo vencido;
- cargos;
- período;
- vencimiento;
- monto;
- estado derivado.

Ownership sigue siendo F8:

`leases.user_id = session user.id`

FinanceService no acepta `user_id` del navegador como autoridad de ownership.

No `/tenant/payments` en F9.

---

# 13. PROYECCIÓN Y PRIVACIDAD

Proyección tenant mínima:

- type traducible;
- period_month;
- due_date;
- amount;
- description;
- derived state;
- days_overdue.

Incluir `charge.id` solo si la vista/ruta privada realmente lo necesita.

No exponer:

- created/void actor IDs;
- `void_reason` por defecto;
- metadata interna;
- SQL;
- campos de otros leases.

HTML siempre escapado.

---

# 14. PERMISOS

```text
finance.view
finance.manage
```

- owner bypass existente;
- viewer lee cartera/cargos pero no muta;
- tenant no recibe permisos financieros staff;
- tenant accede solo por ownership F8;
- no permisos por tipo de cargo.

---

# 15. INTEGRIDAD Y TRANSACCIONES

Transacciones obligatorias en:

- monthly generation;
- manual creation cuando usa lock/validación de lease;
- void.

Locks mínimos:

- monthly/manual: `lease FOR UPDATE` cuando la integridad dependa de ese lease;
- void: `charge FOR UPDATE`.

No locks de tabla completa.

No transacciones durante render ni IO externo.

F9 no hace IO externo.

---

# 16. NO AUTOMATIZACIÓN TEMPORAL

F9 no genera cargos automáticamente al cambiar de mes.

No cron.

No scheduler.

No worker.

No catch-up automático.

La generación mensual es acción explícita del administrador.

F16 podrá automatizar invocaciones idempotentes al mismo motor si se aprueba posteriormente.

---

# 17. NO INCLUIDO

F9 excluye:

- medios de pago (F10);
- payments/allocations/reportes/conciliación (F11);
- utility periods/servicios estructurados (F12);
- claims (F13);
- auto-charge desde tickets (F14);
- cron/reminders/email (F16);
- membership (F20);
- bono/liquidación (fases posteriores);
- interés moratorio automático;
- contabilidad/facturación electrónica;
- exportaciones;
- analytics decorativa.

---

# 18. PRUEBAS OBLIGATORIAS — TDD

## Schema

- exactamente `charges` como nueva tabla F9;
- columnas exactas;
- FK lease RESTRICT;
- actors SET NULL;
- índices exactos aprobados;
- no payments/allocations/balance/status-history tables;
- no status/paid/outstanding columns.

## Monthly

- rent desde contract monthly_rent;
- administration solo si >0;
- snapshot inmutable;
- period obligatorio/normalizado;
- fuera de rango contractual falla;
- duplicate activo same lease/type/period falla;
- voided permite corrección/recreación;
- concurrencia no duplica canon;
- no side effects en contract/lease/unit/candidate/screening.

## Manual

- allowlist exacta;
- rent/admin rechazados por camino manual;
- amount inválido falla;
- description requerida;
- optional period válido;
- ended lease permite cargos finales/manuales;
- no future side effects.

## Void

- primer void guarda actor/time/reason;
- segundo falla cerrado;
- originales preservados;
- row permanece;
- sale de saldo/mora;
- no replacement automático.

## Portfolio

- saldo solo no anulados;
- overdue por due date/clock controlado;
- days overdue exactos;
- no persistencia de balance/status.

## Admin routes

- auth;
- forced password;
- finance permissions;
- owner bypass;
- CSRF;
- basePath;
- viewer no muta;
- PRG;
- no future UI.

## Tenant

- owned lease only;
- IDOR fail closed;
- propios cargos/saldo visibles;
- foreign absent;
- voided no suma;
- XSS escaped;
- no payment UI.

## Security/architecture regression

Congelar:

```text
1 charges table
1 FinanceService
1 Finance route file max
2 finance permissions
4 private F9 routes max
0 public routes
0 payment tables
0 direct dependencies
0 cron
0 future module UI
0 tenant/lease duplicate tables
```

---

# 19. MAPA DE ARCHIVOS PROPUESTO

## Crear

- `database/migrations/009_create_charges.sql`
- `app/Finance/FinanceService.php`
- `app/Finance/routes.php`
- `resources/views/finance_index.php`
- `tests/Integration/Finance/ChargeSchemaTest.php`
- `tests/Integration/Finance/FinanceServiceTest.php`
- `tests/Integration/Finance/FinanceRoutesTest.php`
- `tests/Integration/Finance/FinanceSecurityRegressionTest.php`

## Modificar previsiblemente

- `app/Auth/Permissions.php`
- `bootstrap/app.php`
- autoridad administrativa exacta que quede tras F8 final;
- `resources/views/contract_detail.php` o vista equivalente real de contexto lease;
- `resources/views/tenant_lease_detail.php`;
- shell/launcher tests solo si se publica launcher Finance real;
- README/checkpoints al cierre.

Este mapa es provisional hasta contrastarlo con el F8 final auditado.

---

# 20. UX KISS

Mobile-first y componentes existentes.

No CSS nuevo salvo defecto reproducido.

Preferir:

- cards/lists actuales;
- SSR forms;
- HTML nativo;
- date/month inputs con normalización server-side;
- confirmación explícita para anular;
- sin modal/JS nuevo si un form normal resuelve.

---

# 21. GATE ANTIPARCHES

Toda reparación F9:

`REPRODUCE → ROOT CAUSE → OWNING AUTHORITY → CONVERGE/SIMPLIFY → MINIMAL GREEN → REMOVE SUPERSEDED LOGIC → REGRESSION`

No aceptar:

- if lateral para esconder estado inválido;
- balance calculado diferente en admin/tenant;
- overdue duplicado en views;
- segunda autoridad SQL contradictoria;
- tipos inventados por UI;
- campos cacheados para evitar queries simples;
- guards relajados globalmente para hacer pasar F9.

Views presentan; FinanceService/domain authority decide.

---

# 22. GATE DE CIERRE F9

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
PATCH_STACKING: NONE
NEW_TABLES: 1
NEW_DOMAIN_CLASSES: 1
NEW_ROUTE_FILES: 1 max
NEW_PERMISSIONS: 2
NEW_PUBLIC_ROUTES: 0
NEW_DIRECT_RUNTIME_DEPENDENCIES: 0
NEW_PAYMENT_TABLES: 0
NEW_CRON_JOBS: 0
```

Además:

- focused F9 PASS;
- full suite PASS;
- Composer validate PASS;
- CI PHP 8.3/8.4/8.5 PASS;
- product diff completo auditado;
- tenant ownership/IDOR PASS;
- no future leakage;
- docs convergidas;
- merge separado/no automático.

---

# 23. DECISIONES CONGELADAS

1. F9 = cargos + cartera, no pagos.
2. Una tabla nueva: `charges`.
3. Una clase financiera: `FinanceService`.
4. Saldos derivados.
5. Sin `charges.status`.
6. F9 proyecta `pending|overdue|voided`.
7. Estados de pago esperan F11.
8. Mora no calcula intereses.
9. Rent/admin snapshot desde contrato F7 al crear cargo.
10. Generación mensual manual en F9.
11. No cron antes de F16.
12. Anular, no delete/edit destructivo.
13. Tenant reutiliza portal F8.
14. `finance.view/manage` son autoridad staff F9–F11.
15. Sin membership/payment methods/payments/allocations anticipados.
16. Sin `tenants/lease_units` reintroducidos.
17. La implementación parte del F8 finalmente auditado, no de esta rama docs basada en F7.

---

# 24. AUTOAUDITORÍA DE DISEÑO — 10 PASADAS

1. **Scope:** F9 queda limitada a cargos/cartera; PASS.
2. **KISS:** 1 tabla + 1 clase + 1 route file; PASS.
3. **Cero Ruido:** no pagos/allocations/membership/cron adelantados; PASS.
4. **Autoridades:** lease/contract/user reutilizados; PASS.
5. **Estado:** saldo/status/overdue derivados, no duplicados; PASS.
6. **Concurrencia:** lease lock para recurrentes, charge lock para void; PASS.
7. **Seguridad:** auth/CSRF/permissions/basePath/tenant ownership heredados; PASS.
8. **UX:** contexto existente + una cartera central, sin CRUD paralelo; PASS.
9. **Evolución:** F10/F11 extienden el mismo motor sin tablas placeholder; PASS.
10. **Antiparches:** repair protocol y authority-first congelados; PASS.

No se encontró motivo demostrado para una segunda tabla F9, segunda clase de dominio, status persistido o automatización cron.

---

# 25. GATE DE APROBACIÓN

```text
F9_PRE_KISS: READY FOR USER REVIEW
F9_SPEC: DRAFT / USER REVIEW REQUIRED
F9_PLAN: NOT AUTHORIZED YET
F9_IMPLEMENTATION: BLOCKED
F8_DEPENDENCY: WAITING FINAL AUDIT
```

Después de aprobación explícita de esta SPEC:

1. contrastar contra F8 final auditado;
2. ajustar mapa de archivos a la autoridad real;
3. escribir PLAN F9 task-by-task;
4. auditar PLAN bajo KISS/Cero Ruido/Antiparches;
5. generar prompt maestro Markdown descargable para Codex Luna;
6. abrir implementación F9 solo sobre base F8 autorizada.
