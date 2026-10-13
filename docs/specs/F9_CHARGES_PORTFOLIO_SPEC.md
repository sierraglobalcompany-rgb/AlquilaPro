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

Esta SPEC puede diseñarse en paralelo, pero **no debe escribir producto F9 sobre una base F8 no auditada**.

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

Los estados de la SPEC maestra que dependen de pagos/reportes (`partial`, `reported`, `proof_received`, `paid`) pertenecen a F11 y se derivarán de payments/reports/allocations.

`in_claim` pertenece a la futura integración de pólizas/reclamaciones y no se precrea en F9.

Así se mantiene una única evolución del motor financiero sin estados imposibles antes de que existan sus fuentes.

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

Cualquier cargo adicional de multa debe ser una acción administrativa explícita y justificable, nunca una consecuencia automática del reloj.

---

# 5. PRESUPUESTO ARQUITECTÓNICO F9

Resultado esperado:

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

`finance.view/manage` se justifican porque cartera/pagos constituyen una autoridad operativa distinta de contratos y serán reutilizados por F10/F11. No debe reutilizarse `contracts.manage` para autorizar movimientos financieros.

Owner conserva bypass según el sistema de permisos existente.

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
created_at DATETIME NOT NULL
voided_by_user_id BIGINT UNSIGNED NULL
voided_at DATETIME NULL
void_reason VARCHAR(500) NULL
```

## 6.1 FK e índices

- `lease_id → leases.id ON DELETE RESTRICT`;
- `created_by_user_id → users.id ON DELETE SET NULL`;
- `voided_by_user_id → users.id ON DELETE SET NULL`.

Índices mínimos:

- `lease_id`;
- `due_date`;
- `type`;
- `period_month`;
- `voided_at` si el query plan real demuestra utilidad; evitar índices decorativos.

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

No añadir `membership` todavía: la membresía entra en F20 y en ese momento se extiende la allowlist del mismo motor.

No añadir tipos específicos de servicios públicos; F12 puede llamar posteriormente al mismo motor con `service` y descripción/source contextual sin duplicar un motor.

## 6.3 Reglas de monto

- `amount > 0`;
- máximo compatible con `DECIMAL(12,2)`;
- 2 decimales normalizados en boundary de dominio;
- no aceptar float como autoridad matemática si el proyecto puede conservar decimal/string seguro;
- un cargo creado no se edita destructivamente.

Si un cargo es incorrecto:

`anular + crear cargo correcto`

Esto conserva trazabilidad y evita una tabla de historial de ediciones.

## 6.4 Período

`period_month` representa el primer día del mes del período (`YYYY-MM-01`).

Reglas:

- obligatorio para `rent|administration`;
- opcional para `service|fine|repair|other`;
- no existe `period_end` porque es derivable para visualización;
- no existe tabla de períodos.

## 6.5 Vencimiento

`due_date` es explícito por cargo/período.

F9 no infiere por ley ni por cron una fecha de vencimiento “correcta”.

La UI puede reutilizar la última fecha elegida como comodidad visual solo si no se convierte en autoridad persistida paralela; la operación siempre confirma el vencimiento explícito.

---

# 7. GENERACIÓN DE CANON Y ADMINISTRACIÓN

El contrato F7 ya congela:

- `monthly_rent`;
- `administration_fee`;
- `start_date`;
- `end_date`.

F9 debe leer estos valores a través del `lease → contract` canónico.

## 7.1 Operación mensual

`FinanceService` expone una operación conceptual del tipo:

`createMonthlyCharges(leaseId, periodMonth, dueDate, actorUserId)`

Comportamiento:

1. BEGIN;
2. lock `lease` `FOR UPDATE`;
3. leer contrato asociado;
4. validar `period_month` dentro del rango mensual del contrato;
5. rechazar cargo `rent` no anulado duplicado para mismo lease/período;
6. crear `rent` por `contracts.monthly_rent`;
7. si `administration_fee` es no-null y > 0, rechazar duplicado y crear `administration`;
8. COMMIT;
9. devolver cargos creados.

No copiar a otra tabla los valores contractuales antes de crear el cargo.

El valor de cada cargo sí es snapshot financiero histórico: una vez creado, su `amount` no cambia aunque una fuente futura cambie.

## 7.2 Duplicados y concurrencia

No crear una columna artificial `dedupe_key` solo para facilitar este caso.

La regla de duplicado recurrente se resuelve dentro de la misma transacción, serializando por `lease FOR UPDATE` y consultando cargos `rent|administration` no anulados del mismo período.

Debe existir prueba de concurrencia/serialización suficiente para demostrar que dos intentos no generan dos cánones activos para el mismo lease/período.

## 7.3 Arrendamiento terminado

F9 puede registrar cargos históricos/finales sobre un lease terminado.

Para `rent|administration`, el período debe permanecer dentro de `contracts.start_date/end_date`.

F9 no infiere prorrateo por `ended_at` ni prohíbe retroactivamente completar un período contractual faltante. Esa decisión sigue siendo humana.

---

# 8. CARGOS MANUALES

Tipos manuales F9:

```text
service
fine
repair
other
```

Operación conceptual:

`createManualCharge(leaseId, type, amount, dueDate, description, periodMonth?, actorUserId)`

Reglas:

- `finance.manage`;
- lease debe existir;
- type dentro de allowlist manual;
- amount > 0;
- `description` obligatoria y trim no vacío;
- `period_month` opcional;
- no modifica contrato, lease, candidate, screening ni unit status;
- no crea pagos ni reportes;
- no se genera automáticamente desde módulos futuros.

F12/F14/F20 podrán llamar al mismo motor explícitamente en el futuro, pero F9 no crea esas integraciones todavía.

---

# 9. ANULACIÓN

No existe DELETE operativo de cargos.

Operación conceptual:

`voidCharge(chargeId, actorUserId, reason, now)`

Reglas:

1. transaction;
2. charge `FOR UPDATE`;
3. require `voided_at IS NULL`;
4. reason obligatoria;
5. guardar `voided_by_user_id`, `voided_at`, `void_reason` una sola vez;
6. segundo intento falla cerrado y conserva actor/fecha/razón originales;
7. no eliminar fila;
8. no crear cargo sustituto automáticamente.

Los cargos anulados:

- quedan visibles como histórico;
- no suman al saldo;
- no suman a mora;
- no pueden recibir pagos cuando F11 exista.

---

# 10. SALDO Y CARTERA

## 10.1 Saldo por lease en F9

```text
outstanding = SUM(amount WHERE voided_at IS NULL)
overdue = SUM(amount WHERE voided_at IS NULL AND due_date < today)
```

F9 no guarda estas sumas.

## 10.2 Días de mora

Por cargo:

```text
max(0, today - due_date)
```

Para un cargo no vencido o anulado: `0`.

No persistir `days_overdue`.

## 10.3 Fecha actual

La lógica de dominio que depende de “hoy” debe recibir el tiempo/fecha desde boundary testeable cuando sea necesario; evitar dependencias difíciles de probar a reloj global dentro de queries críticas.

---

# 11. ADMINISTRACIÓN / UX

## 11.1 Cartera central

Nueva ruta privada mínima:

`GET /admin/finance`

Requiere `finance.view`.

Muestra una cartera operativa simple, no dashboard decorativo.

Cada fila/agrupación debe permitir comprender como mínimo:

- inquilino;
- propiedad/unidad;
- lease;
- saldo total;
- saldo vencido;
- fecha del vencimiento más antiguo pendiente si existe;
- acceso al contexto contractual/arrendamiento existente.

Filtros mínimos solo si aportan valor real:

- con mora / sin mora;
- búsqueda simple por inquilino o unidad.

No crear charts, KPIs o analytics en F9.

## 11.2 Contexto del lease/contract

Reutilizar el detalle administrativo existente de F8/F7 para:

- mostrar cargos del lease;
- generar canon/administración del período;
- crear cargo manual;
- anular cargo.

No crear CRUD separado de `charges` si el contexto existente resuelve la operación.

## 11.3 Mutaciones F9

Rutas conceptuales máximas:

```text
POST /admin/leases/{id}/charges/monthly
POST /admin/leases/{id}/charges
POST /admin/charges/{id}/void
```

Más:

`GET /admin/finance`

Total F9 esperado: **4 rutas privadas**.

No rutas públicas.

Todas las mutaciones:

- auth existente;
- forced-password gate;
- `finance.manage`;
- CSRF global;
- basePath;
- PRG 303 cuando no haya secreto efímero.

---

# 12. PORTAL DEL INQUILINO

No crear nuevas rutas tenant salvo que una prueba de UX demuestre necesidad.

Preferencia F9:

modificar el detalle existente:

`GET /tenant/leases/{id}`

para incluir:

- saldo actual;
- saldo vencido;
- lista de cargos;
- período;
- vencimiento;
- monto;
- estado derivado F9.

Ownership sigue siendo la misma autoridad F8:

`leases.user_id = session user.id`

La capa financiera no recibe `user_id` del navegador como ownership.

Los cargos de otro lease/tenant nunca deben filtrarse lateralmente.

No crear `/tenant/payments` en F9.

---

# 13. PROYECCIÓN Y PRIVACIDAD

Proyección tenant de cargos debe incluir solo campos necesarios:

- charge id si la ruta/vista privada realmente lo requiere;
- type traducible;
- period_month;
- due_date;
- amount;
- description pública/operativa;
- estado derivado;
- days_overdue.

No exponer:

- actores internos;
- IDs administrativos innecesarios;
- razones internas sensibles si se decide mantener `void_reason` solo admin;
- query SQL/raw metadata.

Toda salida HTML escapada.

---

# 14. PERMISOS

Nuevos permisos F9:

```text
finance.view
finance.manage
```

Reglas:

- owner bypass existente;
- `finance.manage` no implica crear usuarios ni contratos;
- viewer puede leer cartera/cargos pero no mutar;
- tenant no obtiene estos permisos y accede solo a su proyección ownership-first;
- no crear permisos por tipo de cargo.

---

# 15. INTEGRIDAD Y TRANSACCIONES

Transacción obligatoria en:

- generación mensual;
- creación manual si existen validaciones de concurrencia que dependan del lease;
- anulación.

Lock mínimo propietario:

- monthly generation: `lease FOR UPDATE`;
- void: `charge FOR UPDATE`.

No lockear tablas enteras.

No mantener transacciones abiertas durante render o IO externo.

F9 no hace IO externo.

---

# 16. NO AUTOMATIZACIÓN TEMPORAL EN F9

F9 no genera automáticamente cargos al cambiar de mes.

No cron.

No scheduler.

No background worker.

No “catch up” automático.

Generación mensual es una acción explícita del administrador.

F16 podrá automatizar invocaciones idempotentes al mismo motor si luego se aprueba.

Esto evita crear en F9 un segundo motor de recordatorios/cron antes de su fase.

---

# 17. NO INCLUIDO

F9 excluye explícitamente:

- medios de pago (F10);
- pagos (F11);
- allocations (F11);
- reporte de pago/comprobante (F11);
- conciliación (F11);
- detección de duplicado de pagos (F11);
- servicios estructurados/utility periods (F12);
- reclamaciones de póliza (F13);
- tickets que generen cargo automáticamente (F14);
- recordatorios/cron/email (F16);
- membresía (F20);
- bono (F21);
- liquidación de terminación (F21/F22 según roadmap vigente);
- interés moratorio automático;
- contabilidad/facturación electrónica;
- exportaciones;
- dashboards analíticos.

---

# 18. PRUEBAS OBLIGATORIAS — CONTRATO TDD

## 18.1 Schema

- F9 crea exactamente `charges`;
- columnas exactas;
- FK lease RESTRICT;
- actors SET NULL;
- no payment/allocations/balance/status-history tables;
- no status column;
- no paid/outstanding columns.

## 18.2 FinanceService — monthly

- crea rent desde `contracts.monthly_rent`;
- crea administration solo si `administration_fee > 0`;
- snapshot de amount queda inmutable;
- period obligatorio y normalizado;
- period fuera de contract start/end falla;
- same lease/type/period no duplica cargo activo;
- cargo previamente voided permite corrección/recreación;
- serialización por lease evita doble canon concurrente;
- no muta contract/lease/unit/candidate/screening.

## 18.3 Manual charge

- allowlist exacta;
- rent/admin no entran por camino manual genérico si eso elude reglas recurrentes;
- amount <=0 falla;
- description faltante falla;
- optional period válido;
- ended lease permite cargos finales/manuales;
- no side effects externos.

## 18.4 Void

- primer void guarda actor/time/reason;
- segundo falla cerrado;
- originales se preservan;
- row no se elimina;
- void deja saldo/mora fuera;
- no auto-replacement.

## 18.5 Balance/overdue

- saldo suma solo no anulados;
- overdue usa due_date y fecha inyectada/controlada;
- days_overdue exacto;
- no se persiste balance;
- no se persiste overdue/status.

## 18.6 Admin routes

- auth;
- password gate;
- finance.view/manage;
- owner bypass;
- CSRF;
- basePath;
- viewer no muta;
- monthly/manual/void PRG correcto;
- cartera no contiene módulos futuros.

## 18.7 Tenant

- solo owned lease;
- IDOR fail closed;
- saldo/cargos propios visibles;
- cargo de otro tenant ausente;
- voided histórico no suma balance;
- HTML escapado;
- no payment UI futura.

## 18.8 Security/architecture regression

Congelar:

```text
1 charges table
1 FinanceService
1 Finance route file max
2 finance permissions
4 private routes max
0 public routes
0 payments tables
0 direct dependencies
0 cron
0 future module UI
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
- contexto admin F8/F7 que muestre el lease/cargos;
- `resources/views/contract_detail.php` o la autoridad administrativa exacta que quede tras F8 final;
- `resources/views/tenant_lease_detail.php`;
- tests de shell/launcher solo si se añade launcher Finance real;
- `README.md` y checkpoints al cierre.

El mapa final debe contrastarse contra el F8 cerrado antes de congelar PLAN.

---

# 20. UX KISS

F9 debe ser mobile-first reutilizando componentes existentes.

No CSS nuevo salvo defecto reproducido.

Preferencias:

- tarjetas/listas ya existentes;
- formularios SSR;
- HTML nativo;
- `input type=date`;
- `input type=month` si el soporte actual y pruebas lo permiten, normalizando server-side;
- confirmación explícita para anular;
- sin modales/JS nuevos si una página/form normal resuelve.

---

# 21. GATE ANTIPARCHES

Toda corrección durante F9 sigue:

`REPRODUCE → ROOT CAUSE → OWNING AUTHORITY → CONVERGE/SIMPLIFY → MINIMAL GREEN → REMOVE SUPERSEDED LOGIC → REGRESSION`

No aceptar:

- `if` lateral para esconder un estado inválido;
- segunda query que contradiga la autoridad de FinanceService;
- balance calculado distinto en admin y tenant;
- lógica de overdue duplicada en views;
- tipo de cargo inventado solo para un formulario;
- columnas cacheadas para evitar una query pequeña.

Las views reciben proyecciones ya coherentes; no deciden reglas financieras.

---

# 22. GATE DE CIERRE F9

Antes de declarar F9 CLOSED:

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

- focused F9 suite PASS;
- full suite PASS;
- Composer validate PASS;
- CI PHP 8.3/8.4/8.5 PASS;
- diff completo auditado;
- tenant ownership/IDOR PASS;
- no future-module leakage;
- documentación convergida;
- merge separado y no automático.

---

# 23. DECISIONES CONGELADAS POR ESTA SPEC

1. F9 = cargos + cartera, no pagos.
2. Una sola tabla nueva: `charges`.
3. Una sola clase financiera: `FinanceService`.
4. Los saldos son derivados.
5. `charges.status` no existe.
6. F9 solo proyecta `pending|overdue|voided`.
7. Estados de pago se incorporan cuando existan las fuentes F11.
8. Mora F9 no calcula intereses.
9. Canon/administración toman snapshot desde contrato F7 al crear el cargo.
10. Generación mensual es manual/expresa en F9.
11. No cron antes de F16.
12. Anulación, no delete/edit destructivo.
13. Tenant reutiliza rutas/portal F8; no segundo portal financiero.
14. `finance.view/manage` son la autoridad staff para F9–F11.
15. F9 no precrea membresía, payment methods, payments ni allocations.
16. La implementación debe partir del F8 finalmente auditado, no del branch de diseño F9 basado en F7.

---

# 24. GATE DE APROBACIÓN

```text
F9_PRE_KISS: PROPOSED
F9_SPEC: DRAFT / USER REVIEW REQUIRED
F9_PLAN: NOT AUTHORIZED YET
F9_IMPLEMENTATION: BLOCKED
F8_DEPENDENCY: WAITING FINAL AUDIT
```

Siguiente paso después de aprobación explícita de esta SPEC:

1. contrastar contra F8 final auditado;
2. ajustar mapa de archivos si la autoridad real cambió;
3. escribir PLAN F9 task-by-task;
4. auditar PLAN bajo KISS/Cero Ruido/Antiparches;
5. generar prompt maestro Markdown para Codex Luna;
6. solo entonces abrir implementación F9.
