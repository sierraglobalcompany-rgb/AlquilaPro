# F11 — REPORTE DE PAGO + CONCILIACIÓN — SPEC

**Proyecto:** AlquilaPro  
**Fecha:** 2026-10-13 (America/Bogota)  
**Rama de diseño:** `spec/f11-payment-report-reconciliation-20261013`  
**Estado:** `APPROVED DESIGN / USER SPEC REVIEW REQUIRED / IMPLEMENTATION BLOCKED ON F10 FINAL AUDIT + MERGE`  
**Fuentes canónicas:** `ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`, `ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`, F9/F10 SPEC/PLAN aprobados y código real integrado hasta F8.

---

# 1. OBJETIVO

F11 introduce el **reporte de pago y conciliación humana** sobre el motor financiero único de AlquilaPro.

Debe permitir que un inquilino autenticado:

- reporte un pago sobre su arrendamiento;
- seleccione un medio de pago F10 aplicable;
- indique monto pagado;
- indique fecha de pago;
- indique hora aproximada opcional;
- distribuya el monto entre uno o varios cargos propios;
- adjunte comprobante opcional;
- envíe el reporte aun sin comprobante;
- vea el estado operativo del reporte.

Debe permitir que staff autorizado:

- vea reportes pendientes de conciliación;
- revise advertencias de duplicado probable;
- ajuste allocations antes de conciliar;
- concilie humanamente;
- rechace con motivo;
- preserve trazabilidad histórica.

F11 **no concilia automáticamente con bancos**, no consulta Bre-B, no ejecuta PSE, no contabiliza, no factura electrónicamente y no introduce ledger contable.

---

# 2. DEPENDENCIAS Y GATE DE ARRANQUE

F11 depende de:

- F8 para `leases` y ownership tenant;
- F9 para `charges`, `FinanceService`, cartera y permisos `finance.view/manage`;
- F10 para `payment_methods` y resolución de medios aplicables.

La implementación productiva F11 queda bloqueada hasta que:

1. F10 termine `CLOSED / VERIFIED`;
2. ChatGPT/Sol audite su cierre real;
3. F10 sea fusionada en `main`;
4. se fije el SHA exacto de `main` que contiene F10;
5. no existan cambios pendientes que alteren `charges`, `FinanceService`, `payment_methods`, ownership tenant o las superficies de Cartera.

Esta rama documental no es base autorizada de implementación.

La implementación F11 debe partir del `main` que contenga F10 final auditada y fusionada.

---

# 3. LEYES SUPREMAS

## 3.1 KISS

F11 debe resolver pagos/reportes/allocations con el mínimo razonable de piezas.

Preferencias obligatorias:

- reutilizar `FinanceService` en vez de crear `PaymentService`;
- una fila `payments` representa tanto el reporte pendiente como el pago conciliado;
- usar timestamps/actores para estado derivado, no columna `status`;
- dos tablas nuevas como máximo: `payments` y `payment_allocations`;
- comprobante opcional en la misma fila `payments`, sin tabla media;
- reutilizar `finance.view/manage`;
- reutilizar portal tenant y Cartera existentes;
- cero dependencias runtime nuevas;
- cero automatización bancaria.

## 3.2 CERO RUIDO / CONVERGENCIA

Orden obligatorio:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

F11 no debe crear por anticipado:

- `payment_reports` separado de `payments`;
- `payment_status_history`;
- `payment_receipts` table;
- `unapplied_balances`;
- `credits`;
- `refunds`;
- `bank_matches`;
- `provider_transactions`;
- `ledger_entries`;
- cron/webhooks.

El mismo hecho financiero no debe existir duplicado como “reporte” y luego como “pago”.

## 3.3 ANTIPARCHES

Ante defectos:

`REPRODUCE → ROOT CAUSE → OWNING AUTHORITY → CONVERGE/SIMPLIFY → RED → MINIMAL GREEN → REMOVE SUPERSEDED LOGIC → REGRESSION`

No apilar reglas laterales para pagos parciales, duplicados o conciliación.

---

# 4. PRE-KISS — DECISIONES ESTRUCTURALES

## 4.1 Dos tablas nuevas

F11 añade exactamente:

```text
payments
payment_allocations
```

No añade `payment_reports`.

Razón: el reporte pendiente y el pago conciliado son el mismo hecho con un ciclo de vida único.

## 4.2 Cero clases de dominio nuevas esperadas

F11 extiende:

```text
FinanceService
```

No crear:

- `PaymentService`;
- `ReconciliationService`;
- `AllocationService`;
- repositories/DTOs/interfaces;
- ledger/accounting service.

Solo si la realidad final F9/F10 demuestra que `FinanceService` ya excede claramente su autoridad se reabre SPEC; no dividir por anticipado.

## 4.3 Cero permisos nuevos

Reutilizar:

```text
finance.view
finance.manage
```

Tenant no recibe permisos staff; accede únicamente por ownership de lease.

## 4.4 Reporte y conciliación en una sola fila

Estado derivado de `payments`:

```text
pending_reconciliation:
  reconciled_at IS NULL
  AND rejected_at IS NULL

reconciled:
  reconciled_at IS NOT NULL

rejected:
  rejected_at IS NOT NULL
```

Invariante:

- `reconciled_at` y `rejected_at` no pueden coexistir;
- actor correspondiente solo existe junto con su timestamp;
- un segundo reconcile/reject falla cerrado y preserva actor/fecha original.

No persistir `status`.

---

# 5. PRESUPUESTO ARQUITECTÓNICO F11

```text
NEW_TABLES: 2
  payments
  payment_allocations

NEW_DOMAIN_CLASSES: 0 expected
  extend FinanceService

NEW_ROUTE_FILES: 0 preferred
NEW_ROUTE_FILES: 1 maximum only if F10/F9 final reality proves reuse materially worse

NEW_PERMISSIONS: 0
NEW_PUBLIC_ROUTES: 0
NEW_RUNTIME_DEPENDENCIES: 0
NEW_MEDIA_TABLES: 0
NEW_STATUS_HISTORY_TABLES: 0
NEW_LEDGER_TABLES: 0
NEW_BANK_INTEGRATIONS: 0
NEW_WEBHOOKS: 0
NEW_CRON_JOBS: 0
```

---

# 6. MODELO `payments`

Esquema conceptual:

```text
id BIGINT UNSIGNED PK AUTO_INCREMENT
lease_id BIGINT UNSIGNED NOT NULL
payment_method_id BIGINT UNSIGNED NOT NULL
amount DECIMAL(12,2) NOT NULL
paid_on DATE NOT NULL
paid_time TIME NULL
payment_method_snapshot_json TEXT NOT NULL
reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
receipt_stored_name VARCHAR(190) NULL
receipt_mime_type VARCHAR(80) NULL
receipt_sha256 CHAR(64) NULL
receipt_size_bytes BIGINT UNSIGNED NULL
reconciled_by_user_id BIGINT UNSIGNED NULL
reconciled_at DATETIME NULL
rejected_by_user_id BIGINT UNSIGNED NULL
rejected_at DATETIME NULL
rejection_reason VARCHAR(500) NULL
```

No añadir:

- `status`;
- `reported_by_user_id`;
- `balance`;
- `duplicate_of_id`;
- `is_duplicate`;
- `bank_reference_verified`;
- provider transaction IDs;
- accounting IDs.

## 6.1 Por qué no `reported_by_user_id`

El reporte F11 tenant se crea únicamente desde el lease owned.

La identidad del reportante es derivable de:

```text
payments.lease_id → leases.user_id
```

No duplicar esa autoridad.

Si una fase futura habilita pagos creados por staff con identidad distinta, deberá justificar su propio actor explícito.

## 6.2 FK

```text
lease_id → leases.id ON DELETE RESTRICT
payment_method_id → payment_methods.id ON DELETE RESTRICT
reconciled_by_user_id → users.id ON DELETE SET NULL
rejected_by_user_id → users.id ON DELETE SET NULL
```

No cascade financiero.

## 6.3 Índices mínimos esperados

```text
KEY payments_lease_reported_index (lease_id, reported_at)
KEY payments_pending_index (reconciled_at, rejected_at, reported_at)
KEY payments_method_date_amount_index (payment_method_id, paid_on, amount)
KEY payments_receipt_sha_index (receipt_sha256)
KEY payments_reconciled_by_index (reconciled_by_user_id)
KEY payments_rejected_by_index (rejected_by_user_id)
```

La SPEC/PLAN de implementación debe confirmar estos índices contra las queries reales; no añadir otros por costumbre.

---

# 7. SNAPSHOT DEL MEDIO DE PAGO

F10 permite editar/desactivar configuración; F11 necesita preservar cómo se reportó el pago históricamente.

Por eso `payment_method_snapshot_json` congela únicamente datos visibles de enrutamiento relevantes al momento del reporte.

Puede incluir, según tipo:

```text
type
label
recipient_name
institution_name
account_type
account_number
breb_key_type
breb_key_value
wallet_identifier
instructions
property_id
```

No incluir:

- `qr_stored_name`;
- filesystem paths;
- hash internos innecesarios;
- actor IDs;
- passwords/PIN/OTP/tokens;
- secretos API;
- metadata administrativa no visible.

El snapshot es histórico, no autoridad para resolver el medio actual.

`payment_method_id` conserva la referencia canónica al método configurado.

---

# 8. MODELO `payment_allocations`

Esquema conceptual:

```text
id BIGINT UNSIGNED PK AUTO_INCREMENT
payment_id BIGINT UNSIGNED NOT NULL
charge_id BIGINT UNSIGNED NOT NULL
amount DECIMAL(12,2) NOT NULL
```

FK:

```text
payment_id → payments.id ON DELETE RESTRICT
charge_id → charges.id ON DELETE RESTRICT
```

Constraint/index:

```text
UNIQUE KEY payment_allocations_payment_charge_unique (payment_id, charge_id)
KEY payment_allocations_charge_index (charge_id)
```

No timestamps por allocation: la allocation comparte ciclo con el payment y se modifica solo mientras este está pendiente.

No actor por allocation: conciliador vive en `payments`.

No `status` por allocation.

---

# 9. REGLAS MONETARIAS

Payments y allocations usan decimal string / `DECIMAL(12,2)` como F9.

Reglas:

- amount > 0;
- máximo 2 decimales;
- dentro de `DECIMAL(12,2)`;
- no `float` como autoridad;
- `SUM(allocation.amount) = payment.amount` en reporte y conciliación;
- cada allocation > 0.

F11 no permite “saldo sin aplicar”.

Razón: introducir `unapplied cash`/créditos antes de una necesidad real crearía una tercera autoridad financiera.

Si un pago real excede los cargos seleccionados, el tenant debe distribuirlo solo hasta donde corresponda o esperar definición futura; F11 V1 no inventa créditos a favor.

---

# 10. FECHA Y HORA DEL PAGO

`paid_on` es obligatorio.

`paid_time` es opcional porque el roadmap exige hora aproximada cuando exista, no precisión falsa.

No persistir timezone por fila: la aplicación opera en `America/Bogota` en V1.

Validaciones:

- fecha real válida;
- no aceptar fechas absurdamente futuras;
- hora `HH:MM[:SS]` normalizada si se entrega;
- `reported_at` es timestamp del sistema, distinto de fecha/hora declarada del pago.

El PLAN debe fijar el límite futuro permitido de forma simple y testeable; no introducir motor temporal.

---

# 11. REPORTE TENANT

Operación conceptual:

```text
FinanceService::reportPayment(
  leaseId,
  paymentMethodId,
  amount,
  paidOn,
  paidTime?,
  allocations,
  receipt?,
  now
)
```

Orden mínimo:

1. ownership ya resuelto por route mediante F8 `LeaseService::findForUser`;
2. validar lease y payment method aplicable/activo para property canónica del lease;
3. validar amount/date/time;
4. validar allocations pertenecen al mismo lease;
5. validar charges no anulados;
6. validar sum allocations = payment amount;
7. validar comprobante opcional;
8. congelar snapshot sanitizado del payment method;
9. BEGIN;
10. insertar payment pendiente;
11. insertar allocations propuestas;
12. COMMIT;
13. devolver reporte + advertencias de duplicado probable.

No reduce cartera todavía.

No cambia charge.

No cambia payment method.

---

# 12. ALLOCATIONS PENDIENTES VS. CONCILIADAS

Las allocations existen desde el reporte como **intención propuesta**.

Mientras `payment` está pendiente:

- aparecen al staff para revisión;
- no reducen outstanding;
- staff puede corregirlas al conciliar;
- tenant no puede editarlas después de enviar en V1.

Cuando el payment queda reconciliado:

- esas allocations pasan a ser la distribución financiera efectiva;
- `FinanceService` las usa para saldo derivado.

Cuando el payment queda rejected:

- las allocations permanecen históricas;
- no reducen saldo.

Esto evita tabla adicional de “proposed allocations”.

---

# 13. CONCILIACIÓN HUMANA

Operación conceptual:

```text
FinanceService::reconcilePayment(paymentId, finalAllocations, actorUserId, now)
```

Orden canónico:

```text
BEGIN
→ payment FOR UPDATE
→ require pending
→ load lease
→ lock affected charges in deterministic charge_id order
→ require same lease
→ require non-voided charges
→ recompute current outstanding per charge using already reconciled allocations only
→ validate final allocations >0
→ validate each allocation <= current outstanding
→ validate SUM(final allocations) = payment.amount
→ replace pending allocation set atomically if staff adjusted it
→ set reconciled_by_user_id / reconciled_at
→ COMMIT
```

No persiste saldo.

No muta `charges.amount`.

No marca `charges.status`.

No genera asientos contables.

## 13.1 Concurrencia

Dos conciliaciones concurrentes que intenten cubrir el mismo saldo no pueden sobreasignar un cargo.

Debe serializarse sobre los cargos involucrados en orden estable.

No crear distributed locks, dedupe keys ni tabla de saldos.

---

# 14. RECHAZO

Operación conceptual:

```text
FinanceService::rejectPayment(paymentId, reason, actorUserId, now)
```

Orden:

```text
BEGIN
→ payment FOR UPDATE
→ require pending
→ validate reason
→ set rejected_by_user_id / rejected_at / rejection_reason
→ COMMIT
```

No borrar payment.

No borrar receipt.

No borrar allocations históricas.

Segundo reject/reconcile falla cerrado.

`rejection_reason` puede mostrarse al tenant si la UI lo presenta como motivo operacional; no incluir notas internas sensibles.

---

# 15. COMPROBANTE PRIVADO

Comprobante es opcional.

Formatos permitidos:

```text
image/png
image/jpeg
image/webp
application/pdf
```

Máximo:

```text
5 MiB
```

Validación:

- MIME por bytes (`finfo`);
- no confiar en extensión;
- random stored filename;
- storage privado fuera webroot;
- hash SHA256;
- size exacto;
- permisos restrictivos;
- path traversal fail closed.

Persistir en `payments`:

```text
receipt_stored_name
receipt_mime_type
receipt_sha256
receipt_size_bytes
```

No tabla media porque V1 permite un solo comprobante opcional por reporte.

## 15.1 Integridad en lectura

Antes de servir:

- safe path;
- archivo existe;
- MIME real coincide;
- size coincide;
- SHA256 coincide.

Fallo:

```text
404
```

No exponer nombre físico.

## 15.2 Lifecycle

No reemplazo/delete de comprobante por tenant después del envío en V1.

Staff no reemplaza evidencia al conciliar/rechazar.

Si un upload se movió y la transacción DB falla, el nuevo archivo debe limpiarse.

No filesystem transaction framework.

---

# 16. DUPLICADOS PROBABLES

F11 **advierte**, no bloquea.

No persistir:

```text
is_duplicate
duplicate_of_id
duplicate_score
```

Candidatos de warning:

## 16.1 Comprobante idéntico

```text
same lease
AND receipt_sha256 identical
```

Alta señal.

## 16.2 Coincidencia de datos

```text
same lease
same amount
same payment_method_id
same paid_on
```

Si ambos tienen `paid_time`, una hora igual o cercana eleva la señal.

Si no hay hora, sigue siendo warning más débil.

## 16.3 Resultado

El reporte se guarda igualmente.

La UI puede mostrar:

```text
Posible reporte duplicado
```

con enlaces staff a candidatos relevantes.

No mostrar detalles de otros leases al tenant.

El duplicate detector no decide conciliación.

---

# 17. SALDO POST-F11

F9 definió saldo como suma de cargos no anulados porque no existían pagos.

F11 extiende la misma autoridad.

Para un cargo:

```text
allocated = SUM(payment_allocations.amount)
WHERE payment.reconciled_at IS NOT NULL
AND payment.rejected_at IS NULL
```

```text
outstanding = max(0, charge.amount - allocated)
```

No persistir `allocated`, `paid_amount`, `remaining_amount` ni `balance`.

Un pago pendiente/rechazado no reduce saldo.

---

# 18. ESTADO PROYECTADO DEL CARGO

No persistir status.

Estado primario visible F11:

```text
voided
paid
partial
proof_received
reported
overdue
pending
```

Prioridad:

1. `voided` si charge anulada;
2. `paid` si reconciled allocations cubren amount;
3. `partial` si reconciled allocated >0 y outstanding >0;
4. `proof_received` si existe payment pendiente asignado al cargo con comprobante;
5. `reported` si existe payment pendiente asignado sin comprobante;
6. `overdue` si outstanding >0 y due_date < today;
7. `pending`.

Además debe existir señal derivada independiente:

```text
is_overdue
outstanding
days_overdue
```

Porque un cargo puede estar `partial` y también vencido.

No forzar un único enum persistido a representar dimensiones distintas.

---

# 19. ADMIN / CARTERA

F11 se integra en la superficie financiera existente.

No crear una segunda app “Pagos”.

`GET /admin/finance` debe poder mostrar cola de:

```text
Pendientes de conciliación
```

con datos mínimos:

- tenant;
- property/unit;
- amount;
- paid_on/time;
- medio snapshot/label;
- comprobante sí/no;
- duplicate warning;
- reported_at;
- acceso al detalle.

Staff `finance.view` lee.

Staff `finance.manage` concilia/rechaza.

No dashboard decorativo.

---

# 20. TENANT PORTAL

Reutilizar detalle owned del lease.

El tenant puede:

- ver outstanding actual;
- ver cargos;
- seleccionar allocations;
- reportar pago;
- ver sus reportes y estado derivado;
- descargar/ver su propio comprobante si la UI lo necesita;
- ver motivo de rechazo operativo si existe.

No puede:

- editar pago enviado;
- conciliar;
- rechazar;
- acceder a payments de otro lease;
- elegir `property_id` como autoridad;
- asignar a charge de otro lease;
- asignar a charge voided;
- ver actor staff interno si no aporta valor.

Ownership debe resolverse antes de cualquier lookup lateral de payment/receipt.

---

# 21. RUTAS CONCEPTUALES MÍNIMAS

Preferencia: reutilizar route files F9/F10 existentes.

Rutas conceptuales:

```text
POST /tenant/leases/{id}/payments
GET  /tenant/leases/{leaseId}/payments/{paymentId}/receipt

GET  /admin/finance/payments/{id}
GET  /admin/finance/payments/{id}/receipt
POST /admin/finance/payments/{id}/reconcile
POST /admin/finance/payments/{id}/reject
```

`GET /admin/finance` existente lista pendientes.

No ruta pública.

No API JSON nueva salvo necesidad real de UI demostrada.

No route file nuevo por simetría si los existentes siguen coherentes.

---

# 22. AUTORIZACIÓN

Staff:

```text
finance.view
finance.manage
```

Owner conserva bypass existente.

Tenant usa `role=tenant` + ownership de lease.

No crear:

```text
payments.view
payments.manage
reconciliation.manage
```

mientras `finance.*` cubra la autoridad sin ambigüedad.

Global CSRF para POST.

Forced-password gate permanece.

---

# 23. PRIVACIDAD Y SEGURIDAD

Invariantes:

- receipt siempre privado;
- ownership antes de payment lookup tenant;
- staff auth + permission antes de finance payment detail;
- no path physical leak;
- no bank credentials;
- no payload completo en logs;
- snapshot sanitizado;
- HTML escaped;
- CSRF en reporte/reconcile/reject;
- basePath/subfolder soportado;
- receipt `Cache-Control: private, no-store`;
- `X-Content-Type-Options: nosniff`;
- receipt route valida integridad antes de bytes.

---

# 24. ERRORES Y TRANSACCIONES

## Reporte

Si falla DB después de mover comprobante:

- limpiar archivo nuevo;
- no dejar payment parcial.

Si falla allocation:

- rollback payment + allocations;
- limpiar receipt si fue creado.

## Conciliación

Cualquier fallo:

- rollback allocations ajustadas;
- payment permanece pending;
- actor/time no se escriben parcialmente.

## Rechazo

Cualquier fallo:

- payment permanece pending.

No swallowing exceptions que oculten inconsistencia.

---

# 25. CORRECCIÓN DE PAYMENT PENDIENTE

Tenant no edita reporte enviado en V1.

Staff, durante conciliación, puede reemplazar el set de allocations dentro de la misma transacción.

Staff no cambia:

- payment amount;
- paid_on;
- paid_time;
- payment method snapshot;
- receipt.

Si esos datos esenciales son incorrectos:

```text
reject + tenant/new legitimate report
```

Esto evita convertir conciliación en CRUD destructivo de evidencia.

---

# 26. NO INCLUIDO EN F11

No implementar:

- bank API;
- Bre-B API;
- PSE;
- card acquiring;
- provider webhooks;
- automatic reconciliation;
- OCR receipt;
- bank statement import;
- refunds;
- reversals;
- chargebacks;
- credits/unapplied cash;
- accounting ledger;
- electronic invoicing;
- cron;
- email/WhatsApp notifications;
- reminders;
- F12+ obligations automation.

---

# 27. CONTRATOS DE CONCURRENCIA

Debe probarse al menos:

1. dos reconciliaciones simultáneas no sobreasignan el mismo cargo;
2. segundo reconcile sobre mismo payment falla cerrado;
3. reconcile vs reject concurrentes dejan un único resultado terminal;
4. payment pendiente no afecta saldo;
5. payment reconciliado sí afecta saldo exactamente una vez.

Usar locks existentes y deterministic ordering.

No inventar lock service.

---

# 28. ARQUITECTURA DE ARCHIVOS ESPERADA

F11 probablemente crea:

```text
database/migrations/011_create_payments.sql
```

que puede crear las dos tablas F11 si el estilo de migraciones existente admite múltiples DDL coherentes por fase.

Tests esperados:

```text
tests/Integration/Finance/PaymentSchemaTest.php
tests/Integration/Finance/PaymentReportTest.php
tests/Integration/Finance/PaymentReconciliationTest.php
tests/Integration/Finance/PaymentRoutesTest.php
tests/Integration/Finance/PaymentSecurityRegressionTest.php
```

Producto esperado principalmente modifica:

```text
app/Finance/FinanceService.php
app/Finance/routes.php
app/Leases/routes.php
resources/views/finance_index.php
resources/views/tenant_lease_detail.php
bootstrap/app.php only if receipt storage dependency requires it
```

No congelar paths finales hasta preflight F10 real.

---

# 29. CRITERIOS DE ACEPTACIÓN

F11 no puede cerrar si falla cualquiera:

1. tenant reporta pago con allocations exactas;
2. comprobante opcional;
3. ownership tenant aislado;
4. payment method aplicable validado desde F10;
5. snapshot sanitizado histórico;
6. payment pendiente no reduce cartera;
7. reconcile humano reduce saldo vía allocations;
8. partial funciona;
9. over-allocation rechazada;
10. concurrencia no sobreasigna;
11. reject preserva evidencia y no reduce saldo;
12. duplicate probable advierte sin bloquear;
13. receipt privado íntegro;
14. no payment_reports table;
15. no status persistido;
16. no balance persistido;
17. no nuevos permisos;
18. no rutas públicas;
19. no bank/provider integration;
20. full QA PHP 8.3/8.4/8.5 PASS;
21. KISS PASS;
22. Cero Ruido PASS;
23. Patch stacking NONE.

---

# 30. GATE KISS / CERO RUIDO

Presupuesto esperado:

```text
NEW_TABLES: 2
NEW_DOMAIN_CLASSES: 0 expected
NEW_PERMISSIONS: 0
NEW_PUBLIC_ROUTES: 0
NEW_RUNTIME_DEPENDENCIES: 0
NEW_MEDIA_TABLES: 0
NEW_STATUS_HISTORY_TABLES: 0
NEW_BANK_INTEGRATIONS: 0
NEW_WEBHOOKS: 0
NEW_CRON_JOBS: 0
```

Gate final debe explicar:

```text
ADDED
REMOVED
CONVERGED
DEPENDENCIES_DELTA
DB_SCHEMA_DELTA
PUBLIC_ROUTES_DELTA
PATCH_STACKING
PROOF
```

---

# 31. AUTOAUDITORÍA DE DISEÑO — 10 PASADAS

## Pasada 1 — autoridad financiera

`payments + payment_allocations` extienden el único motor financiero; no `payment_reports` paralelo. — PASS.

## Pasada 2 — KISS de persistencia

Dos tablas son el mínimo con integridad relacional para un pago que puede distribuirse entre múltiples cargos. JSON de allocations perdería FK/queries limpias. — PASS.

## Pasada 3 — estado derivado

`pending/reconciled/rejected` se deriva de timestamps/actores; no columna status/history. — PASS.

## Pasada 4 — parciales y saldo

Solo allocations reconciliadas reducen outstanding; no balance cache ni paid_amount persistido. — PASS.

## Pasada 5 — conciliación humana

Ninguna API bancaria ni decisión automática; staff controla resultado terminal. — PASS.

## Pasada 6 — duplicados

Detector es warning derivado, no flag persistido ni bloqueo de reporte legítimo. — PASS.

## Pasada 7 — comprobante

Un archivo opcional vive en `payments`; no media table. MIME/hash/size/storage privado. — PASS.

## Pasada 8 — seguridad/ownership

Lease ownership antes de payment/receipt; staff reutiliza finance permissions; 0 permisos nuevos. — PASS.

## Pasada 9 — antiparches/concurrencia

Locks en payment + charges con orden estable, sin lock service/dedupe architecture. — PASS.

## Pasada 10 — scope futuro

No credits/refunds/webhooks/cron/accounting/F12+ adelantado. — PASS.

---

# 32. GATE DE DOCUMENTO

```text
F11_PRE_KISS: APPROVED BY USER
F11_DESIGN: APPROVED BY USER
F11_SPEC: USER REVIEW REQUIRED
F11_PLAN: NOT WRITTEN
F11_IMPLEMENTATION: BLOCKED ON F10 FINAL AUDIT + MERGE
MERGE_AUTHORIZED: NO
```

Siguiente paso únicamente después de revisión/aprobación explícita de esta SPEC:

```text
WRITE F11 IMPLEMENTATION PLAN
```

No implementar producto F11 desde esta rama documental.
