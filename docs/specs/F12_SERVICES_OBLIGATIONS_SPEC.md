# F12 — SERVICIOS + OBLIGACIONES — SPEC

**Proyecto:** AlquilaPro  
**Fecha:** 2026-10-13 (America/Bogota)  
**Rama de diseño:** `spec/f12-services-obligations-20261013`  
**Estado:** `APPROVED DESIGN / USER SPEC REVIEW REQUIRED / IMPLEMENTATION BLOCKED ON F11 FINAL AUDIT + MERGE`  
**Fuentes canónicas:** SPEC/PLAN maestro V1.2, F9/F10/F11 SPEC/PLAN aprobados y código real integrado hasta la última fase fusionada.

---

# 1. OBJETIVO

F12 introduce la **autoridad operativa de servicios y obligaciones externas del arrendamiento** sin crear un segundo motor financiero ni un sistema documental genérico adelantado.

Debe permitir registrar y operar, por lease:

- servicios públicos u otras obligaciones de proveedor;
- administración PH;
- multas PH;
- monto, fechas y referencia externa;
- soporte fuente opcional;
- soporte de pago/satisfacción externa opcional;
- estado operativo derivado;
- vínculo opcional a un único cargo financiero F9;
- generación explícita de cargo F9 para `utility` y `fine_ph` cuando el administrador decida trasladar ese valor al inquilino;
- vínculo con el cargo `administration` mensual ya existente para administración PH.

F12 NO reemplaza F9/F11:

- F12 registra la obligación externa y su evidencia;
- F9/F11 siguen siendo la única autoridad de deuda, pagos, allocations, saldo y conciliación del inquilino.

F12 tampoco implementa recordatorios, email, WhatsApp, OCR, integración con EPM/proveedores, pólizas, mantenimiento ni sistema documental genérico.

---

# 2. DEPENDENCIAS Y GATE DE ARRANQUE

F12 depende funcionalmente de:

- F8 para `leases` y ownership tenant;
- F9 para `charges`, `FinanceService` y permisos `finance.view/manage`;
- F11 para la semántica financiera final de saldo/pagos, aunque F12 no crea ni concilia payments.

La implementación productiva F12 queda bloqueada hasta que:

1. F11 termine `CLOSED / VERIFIED`;
2. ChatGPT/Sol audite su cierre real;
3. F11 sea fusionada en `main`;
4. se fije el SHA exacto de `main` que contiene F11;
5. no existan cambios pendientes que alteren la autoridad de `charges`, `FinanceService`, ownership tenant o las superficies financieras que F12 reutiliza.

Esta rama documental NO es base autorizada de implementación.

La implementación F12 debe partir del `main` que contenga F11 final auditada y fusionada.

---

# 3. LEYES SUPREMAS

## 3.1 KISS

F12 debe resolver obligaciones externas con el mínimo razonable de piezas.

Preferencias obligatorias:

- una sola tabla F12;
- una sola clase de dominio F12;
- reutilizar `FinanceService` para cargos;
- reutilizar `finance.view/manage`;
- reutilizar portal tenant y superficie financiera existentes;
- soportes privados en la misma fila, sin tabla media;
- estados derivados, no columna `status`;
- cero dependencias runtime nuevas;
- cero automatización temporal.

## 3.2 CERO RUIDO / CONVERGENCIA

Orden obligatorio:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

F12 no debe crear por anticipado:

- `utility_periods` como autoridad separada;
- tabla separada para administración PH;
- tabla separada para multas PH;
- `obligation_status_history`;
- `obligation_files`;
- segundo motor de cargos;
- segundo saldo;
- segundo flujo de conciliación;
- recordatorios/email/WhatsApp;
- OCR;
- proveedor/API;
- sistema documental genérico;
- tablas F13/F14/F16/F18.

Los tres conceptos F12 comparten el mismo ciclo operativo: obligación externa, evidencia, vencimiento opcional, satisfacción externa opcional y eventual vínculo financiero.

## 3.3 ANTIPARCHES

Ante defectos:

`REPRODUCE → ROOT CAUSE → OWNING AUTHORITY → CONVERGE/SIMPLIFY → RED → MINIMAL GREEN → REMOVE SUPERSEDED LOGIC → REGRESSION`

No apilar reglas laterales para servicios, administración y multas si una misma autoridad F12 puede expresarlas claramente.

---

# 4. PRE-KISS — DECISIONES ESTRUCTURALES

## 4.1 Una sola tabla nueva

F12 añade exactamente:

```text
lease_obligations
```

No añade:

- `utility_periods`;
- `utility_bills`;
- `ph_administration_obligations`;
- `ph_fines`;
- `obligation_documents`;
- `obligation_payments`;
- `service_charges`;
- `obligation_status_history`.

Razón: servicios, administración PH y multas tienen el mismo ciclo de vida operativo y no justifican tres entidades paralelas.

## 4.2 Una sola clase de dominio

F12 añade:

```text
ObligationService
```

Responsabilidad:

- CRUD operativo controlado de obligaciones;
- validación de tipo/campos/fechas/monto;
- gestión de los dos soportes privados;
- estado operativo derivado;
- vínculo con cargo financiero existente o generado explícitamente;
- proyecciones para admin/tenant.

No crear:

- `UtilityService`;
- `AdministrationPhService`;
- `FineService`;
- repositories/DTOs/interfaces;
- `DocumentService` genérico anticipado.

## 4.3 Una sola autoridad financiera

F12 NO calcula cartera.

F12 NO modifica saldo.

F12 NO crea payments.

Cuando una obligación debe trasladarse al inquilino:

```text
ObligationService → FinanceService
```

FinanceService sigue creando el cargo canónico.

`lease_obligations.charge_id` solo conserva vínculo/procedencia.

## 4.4 Dos dimensiones, no un estado artificial

La obligación externa puede estar satisfecha y a la vez existir un cargo al inquilino.

Ejemplo legítimo:

1. propietario paga factura de EPM;
2. F12 marca la obligación externa como satisfecha;
3. F12 genera cargo `service` F9 para recuperar ese valor del inquilino.

Por lo tanto:

```text
settled_at IS NOT NULL
```

y

```text
charge_id IS NOT NULL
```

PUEDEN coexistir.

No introducir un enum `status` que fuerce dimensiones independientes a un único valor.

---

# 5. PRESUPUESTO ARQUITECTÓNICO F12

```text
NEW_TABLES: 1
  lease_obligations

NEW_DOMAIN_CLASSES: 1
  ObligationService

NEW_ROUTE_FILES: 0 preferred
NEW_ROUTE_FILES: 1 maximum only if final finance/lease routes become materially less clear

NEW_PERMISSIONS: 0
NEW_PUBLIC_ROUTES: 0
NEW_RUNTIME_DEPENDENCIES: 0
NEW_DOCUMENT_TABLES: 0
NEW_PAYMENT_TABLES: 0
NEW_BALANCE_TABLES: 0
NEW_STATUS_HISTORY_TABLES: 0
NEW_CRON_JOBS: 0
NEW_PROVIDER_INTEGRATIONS: 0
```

Reutilizar:

```text
finance.view
finance.manage
```

Tenant usa ownership de lease, no permisos staff.

---

# 6. MODELO `lease_obligations`

Esquema conceptual:

```text
id BIGINT UNSIGNED PK AUTO_INCREMENT
lease_id BIGINT UNSIGNED NOT NULL
type VARCHAR(32) NOT NULL
service_name VARCHAR(120) NULL
description VARCHAR(500) NOT NULL
period_month DATE NULL
issued_on DATE NULL
due_on DATE NULL
amount DECIMAL(12,2) NOT NULL
external_reference VARCHAR(190) NULL

source_support_stored_name VARCHAR(190) NULL
source_support_mime_type VARCHAR(80) NULL
source_support_sha256 CHAR(64) NULL
source_support_size_bytes BIGINT UNSIGNED NULL

settlement_support_stored_name VARCHAR(190) NULL
settlement_support_mime_type VARCHAR(80) NULL
settlement_support_sha256 CHAR(64) NULL
settlement_support_size_bytes BIGINT UNSIGNED NULL

charge_id BIGINT UNSIGNED NULL

created_by_user_id BIGINT UNSIGNED NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NULL

settled_by_user_id BIGINT UNSIGNED NULL
settled_at DATETIME NULL

voided_by_user_id BIGINT UNSIGNED NULL
voided_at DATETIME NULL
void_reason VARCHAR(500) NULL
```

No añadir:

- `status`;
- `paid_amount`;
- `balance`;
- `tenant_payment_id`;
- `provider_id`;
- `reminder_sent_at`;
- `notification_status`;
- OCR fields;
- accounting IDs.

---

# 7. FK E ÍNDICES

FK:

```text
lease_id → leases.id ON DELETE RESTRICT
charge_id → charges.id ON DELETE RESTRICT
created_by_user_id → users.id ON DELETE SET NULL
settled_by_user_id → users.id ON DELETE SET NULL
voided_by_user_id → users.id ON DELETE SET NULL
```

`charge_id` debe ser UNIQUE cuando no es NULL:

```text
UNIQUE KEY lease_obligations_charge_unique (charge_id)
```

Ese UNIQUE ya indexa `charge_id`; NO crear un segundo índice simple redundante.

Índices mínimos congelados:

```text
KEY lease_obligations_lease_due_index (lease_id, due_on)
KEY lease_obligations_lease_period_type_index (lease_id, period_month, type)
```

Los índices de FK/actor que MariaDB/MySQL exija para integridad se resuelven según la migration real, sin añadir índices de consulta decorativos sobre `settled_at` o `voided_at` hasta demostrar una query que los necesite.

No cascade de obligaciones ni cargos.

---

# 8. TIPOS F12

Allowlist exacta:

```text
utility
administration_ph
fine_ph
```

No agregar tipos específicos como:

- electricity;
- water;
- gas;
- internet;
- cleaning;
- parking_fee;
- insurance.

Para `utility`, el nombre concreto vive en:

```text
service_name
```

Ejemplos:

```text
Energía
Acueducto
Gas
Internet
```

Esto evita convertir cada proveedor/servicio en tipo de dominio.

---

# 9. CAMPOS POR TIPO

## 9.1 `utility`

Requiere:

- `service_name` no vacío;
- `description`;
- `amount > 0`.

Puede usar:

- `period_month`;
- `issued_on`;
- `due_on`;
- `external_reference`;
- soporte fuente;
- soporte de satisfacción.

`period_month` es opcional porque una factura real puede no alinearse exactamente con un mes calendario.

## 9.2 `administration_ph`

Requiere:

- `description`;
- `period_month` exacto `YYYY-MM-01`;
- `amount > 0`.

`service_name` debe ser NULL.

Puede usar:

- `issued_on`;
- `due_on`;
- `external_reference`;
- ambos soportes.

No genera automáticamente un cargo nuevo.

## 9.3 `fine_ph`

Requiere:

- `description`;
- `amount > 0`.

`service_name` debe ser NULL.

`period_month` es opcional.

Puede usar:

- `issued_on`;
- `due_on`;
- `external_reference`;
- ambos soportes.

Puede generar explícitamente un cargo `fine` F9.

---

# 10. MONEY CONTRACT

`amount` usa decimal string / `DECIMAL(12,2)` como F9/F11.

Reglas:

- > 0;
- máximo 2 decimales;
- dentro de `DECIMAL(12,2)`;
- no usar float como autoridad;
- output normalizado a 2 decimales.

F12 no persiste saldo ni monto pagado.

`amount` representa el valor de la obligación externa, no necesariamente el saldo que el tenant debe al propietario.

Especialmente para `administration_ph`, el cargo financiero contractual F9 puede diferir del valor externo PH. Ese desacople es válido y NO debe resolverse sobreescribiendo ninguno de los dos valores.

---

# 11. FECHAS

`period_month` cuando exista:

```text
YYYY-MM-01
```

`issued_on` y `due_on` son opcionales.

Si ambos existen:

```text
issued_on <= due_on
```

No persistir `days_overdue`.

La señal externa de vencimiento se deriva usando `today` inyectable/testeable:

```text
external_overdue =
  voided_at IS NULL
  AND settled_at IS NULL
  AND due_on IS NOT NULL
  AND due_on < today
```

F12 no calcula intereses, sanciones automáticas ni días de mora financiera F9.

---

# 12. ESTADO OPERATIVO DERIVADO

No columna `status`.

Estado primario de la obligación externa:

```text
voided:
  voided_at IS NOT NULL

settled_external:
  voided_at IS NULL
  AND settled_at IS NOT NULL

overdue_external:
  voided_at IS NULL
  AND settled_at IS NULL
  AND due_on IS NOT NULL
  AND due_on < today

open:
  otherwise
```

Además exponer independientemente:

```text
is_charged = charge_id IS NOT NULL
external_overdue = derived boolean
```

No intentar condensar `settled_external` + `is_charged` en un enum único.

---

# 13. LEASE ACTIVO O TERMINADO

F12 puede registrar obligaciones legítimas sobre un lease activo o terminado mientras el lease exista.

Casos válidos incluyen:

- factura final recibida después de terminación;
- multa PH emitida por un hecho del período arrendado;
- administración PH pendiente de soporte histórico.

F12 no reabre el lease ni cambia `ended_at`.

Tenant solo ve obligaciones del lease que continúa siendo suyo por ownership canónico.

---

# 14. CREACIÓN / EDICIÓN

Operación conceptual:

```text
ObligationService::create(...)
```

Debe:

- validar lease existente;
- validar type;
- validar campos por tipo;
- validar money/date;
- validar soporte opcional;
- insertar una sola fila;
- no crear cargo automáticamente;
- no crear payment;
- no enviar notificación.

Edición permitida solo mientras la obligación:

```text
voided_at IS NULL
AND charge_id IS NULL
AND settled_at IS NULL
```

Puede corregir campos operativos y reemplazar/remover soporte fuente mientras sigue completamente abierta y sin efecto financiero/externo terminal.

Después de estar cargada financieramente o marcada satisfecha externamente, los datos esenciales quedan inmutables en V1.

Corrección posterior:

```text
void obligation when allowed
+ create corrected obligation
```

No tabla de historial de ediciones.

---

# 15. SOPORTE FUENTE PRIVADO

Opcional.

Representa, por ejemplo:

- factura de servicio;
- cobro de administración PH;
- documento/aviso de multa PH.

Formatos:

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
- random filename;
- storage privado fuera webroot;
- hash SHA256;
- size exacto;
- safe path;
- permisos restrictivos.

No tabla media porque F12 permite un soporte fuente por obligación.

---

# 16. SOPORTE DE SATISFACCIÓN EXTERNA

Opcional.

Representa evidencia de que la obligación externa fue atendida ante proveedor/PH.

Mismo contrato de archivo privado que soporte fuente.

No confundir con payment F11.

Un soporte de satisfacción externa prueba un hecho frente a proveedor/PH; NO demuestra por sí solo que el inquilino pagó una deuda F9 al propietario.

F11 conserva la autoridad de pagos del tenant.

---

# 17. MARCAR SATISFACCIÓN EXTERNA

Operación conceptual:

```text
ObligationService::settleExternal(obligationId, actorUserId, optionalSupport, now)
```

Reglas:

1. transaction;
2. obligation `FOR UPDATE`;
3. require not voided;
4. require `settled_at IS NULL`;
5. validar soporte opcional;
6. persistir soporte de satisfacción si existe;
7. set `settled_by_user_id` + `settled_at` una vez;
8. COMMIT.

Segundo intento falla cerrado y preserva actor/time originales.

`charge_id` puede ser NULL o no NULL.

Marcar satisfacción externa NO modifica ni concilia el cargo F9.

---

# 18. GENERAR CARGO PARA `utility`

Acción explícita, nunca automática.

Operación conceptual:

```text
ObligationService::generateCharge(obligationId, chargeDueDate, actorUserId)
```

Para `utility`:

- require not voided;
- require `charge_id IS NULL`;
- amount del cargo = amount de obligación;
- description financiera deriva de concepto sanitizado/visible;
- periodMonth puede copiar `period_month` si existe;
- due date del cargo viene explícitamente de `chargeDueDate`.

No copiar automáticamente `due_on` del proveedor como vencimiento del tenant: son obligaciones distintas.

Atomicidad requerida usando el mismo PDO/transaction boundary:

```text
BEGIN
→ obligation FOR UPDATE
→ revalidate not voided / charge_id IS NULL
→ FinanceService::createManualCharge(... type=service ...)
→ persist returned charge_id on obligation
→ COMMIT
```

F9 `createManualCharge()` es la autoridad de validación/INSERT del cargo y debe participar en la transacción exterior cuando comparta el mismo PDO; no duplicar su lógica ni crear una segunda escritura de `charges` en ObligationService.

Si crear el cargo o guardar el vínculo falla:

```text
ROLLBACK cargo + vínculo
```

No puede quedar cargo huérfano creado por F12.

No crear un segundo cargo si `charge_id` ya existe.

---

# 19. GENERAR CARGO PARA `fine_ph`

Misma acción explícita y misma atomicidad.

Mapeo:

```text
fine_ph → FinanceService manual type `fine`
```

Reglas:

- not voided;
- no existing charge;
- exact obligation amount;
- explicit charge due date;
- concept visible/sanitized;
- outer transaction sobre obligation + FinanceService insert + link;
- rollback conjunto ante fallo;
- no duplicate generation.

Una multa PH nunca se genera automáticamente por tiempo, evento ni cron.

---

# 20. ADMINISTRACIÓN PH — NO DUPLICAR DEUDA

F9 ya genera mensualmente el cargo financiero `administration` desde el snapshot contractual.

Por tanto F12:

```text
administration_ph
```

NO puede llamar `createManualCharge` para crear otro cargo de administración.

Solo puede enlazar un cargo F9 existente:

```text
ObligationService::linkAdministrationCharge(obligationId, chargeId, actorUserId)
```

Validar:

- obligation type = `administration_ph`;
- obligation not voided;
- `charge_id IS NULL`;
- charge exists;
- charge same lease;
- charge type = `administration`;
- charge not voided;
- charge period_month = obligation period_month;
- charge no está enlazado a otra obligación F12.

No exigir igualdad de monto.

Razón: el valor externo cobrado por PH y el valor contractual financiero del tenant pueden diferir legítimamente; cada autoridad conserva su propio monto.

La UI puede mostrar mismatch como información, no como error automático.

Link debe hacerse bajo transaction/locks coherentes; `UNIQUE(charge_id)` es la última barrera relacional contra doble vínculo concurrente.

---

# 21. CONSISTENCIA ENTRE OBLIGACIÓN Y CARGO

`charge_id` es procedencia/vínculo, no saldo duplicado.

Si una obligación genera cargo `utility|fine_ph`:

- F12 nunca recalcula su saldo;
- F9/F11 determinan estado financiero del cargo.

Si el cargo enlazado es anulado posteriormente en F9:

- el vínculo histórico permanece;
- `is_charged` representa que existió vínculo;
- UI administrativa muestra que el cargo está anulado usando proyección canónica F9;
- F12 no crea automáticamente reemplazo.

Para corregir una obligación cuyo cargo fue anulado:

```text
void obligation when allowed
→ create corrected obligation
→ explicit new charge/link action
```

No cascada automática.

---

# 22. ANULACIÓN DE OBLIGACIÓN

No DELETE operativo.

Operación conceptual:

```text
ObligationService::void(obligationId, actorUserId, reason, now)
```

Reglas:

1. transaction;
2. obligation `FOR UPDATE`;
3. require not already voided;
4. reason obligatoria;
5. si existe `charge_id` y el cargo enlazado sigue activo/no anulado, FAIL CLOSED;
6. si el cargo enlazado ya fue anulado, puede anularse la obligación;
7. set actor/time/reason una vez;
8. no borrar soportes;
9. COMMIT.

Esto evita una obligación anulada que siga justificando silenciosamente una deuda financiera activa.

Segundo intento falla cerrado y conserva originales.

---

# 23. LIFECYCLE DE SOPORTES

Mientras obligación completamente abierta (`charge_id NULL`, `settled_at NULL`, `voided_at NULL`):

- staff puede añadir/reemplazar/remover soporte fuente;
- soporte de satisfacción normalmente entra con `settleExternal`.

Después de `settled_at` o `charge_id`:

- no reemplazar soporte fuente en V1;
- no reemplazar soporte de satisfacción;
- no borrar archivos desde operación normal.

Reemplazo de soporte fuente abierto usa orden seguro:

```text
validate new file
→ store new file
→ update DB metadata
→ after DB success remove superseded file
```

Si DB falla después de mover el nuevo archivo:

- limpiar archivo nuevo;
- conservar archivo/metadatos anteriores.

No filesystem transaction framework.

---

# 24. STORAGE / INTEGRIDAD DE SOPORTES

Lectura privada valida:

- path seguro;
- archivo existe;
- MIME real coincide;
- size coincide;
- SHA256 coincide.

Fallo:

```text
404
```

Headers esperados:

```text
Content-Type: actual
Content-Length: exact
Cache-Control: private, no-store
X-Content-Type-Options: nosniff
```

No exponer stored filename ni path físico.

---

# 25. ADMINISTRACIÓN / UX

F12 se integra en la superficie financiera existente.

No crear segunda app “Servicios”.

Staff `finance.view` puede:

- listar/ver obligaciones;
- ver soportes privados.

Staff `finance.manage` puede:

- crear/editar mientras sea mutable;
- marcar satisfacción externa;
- generar cargo utility/fine;
- enlazar administración existente;
- anular cuando sea legal.

Preferencia de navegación:

```text
Cartera → Obligaciones
```

o sección equivalente dentro de detalle de lease.

No dashboard decorativo.

---

# 26. TENANT PORTAL

Reutilizar `GET /tenant/leases/{id}`.

Tenant puede ver sus obligaciones propias relevantes:

- type/servicio;
- description visible;
- amount externo;
- period/fechas;
- estado externo derivado;
- si existe cargo financiero asociado, su estado financiero canónico F9/F11;
- soporte fuente cuando exista y la obligación no esté anulada.

Por defecto NO mostrar al tenant:

- `external_reference` si puede contener número de cuenta/referencia administrativa sensible;
- settlement support;
- actor IDs;
- notas administrativas internas.

F12 V1 NO permite al tenant:

- crear obligación;
- editar obligación;
- marcar satisfacción externa;
- subir comprobante de obligación externa;
- generar cargo;
- vincular cargo;
- anular.

Razón: un flujo tenant-upload/review/reject crearía un segundo proceso de conciliación parecido a F11 y no está exigido por el roadmap actual.

Ownership debe resolverse antes de cualquier lookup lateral de obligación/soporte.

---

# 27. VISIBILIDAD DE SOPORTES AL TENANT

Regla fija KISS V1:

```text
source support: tenant-visible for owned, non-voided obligation
settlement support: staff-only
```

No agregar ACL/tabla/enum de visibilidad mientras estas dos reglas cubran el producto.

La lectura tenant del source support siempre exige primero ownership del lease y pertenencia de la obligación a ese lease.

---

# 28. RUTAS CONCEPTUALES MÍNIMAS

Preferencia: reutilizar route files financieros/leases existentes.

Staff conceptual:

```text
GET  /admin/finance/obligations
POST /admin/leases/{id}/obligations
POST /admin/finance/obligations/{id}
POST /admin/finance/obligations/{id}/settle
POST /admin/finance/obligations/{id}/generate-charge
POST /admin/finance/obligations/{id}/link-administration-charge
POST /admin/finance/obligations/{id}/void
GET  /admin/finance/obligations/{id}/source-support
GET  /admin/finance/obligations/{id}/settlement-support
```

Tenant conceptual:

```text
GET /tenant/leases/{leaseId}/obligations/{obligationId}/source-support
```

El detalle del lease existente muestra obligaciones sin ruta de listado tenant separada.

No ruta pública.

No JSON API nueva salvo necesidad real demostrada.

El PLAN/preflight puede converger varios POST en una actualización única si mejora claridad sin volver ambiguo el boundary; no crear endpoints por simetría.

---

# 29. AUTORIZACIÓN

Staff:

```text
finance.view
finance.manage
```

Owner conserva bypass existente.

Tenant:

```text
role=tenant + LeaseService::findForUser ownership
```

No crear:

```text
obligations.view
obligations.manage
utilities.manage
ph.manage
```

mientras `finance.*` cubra la operación.

Global CSRF en mutaciones.

Forced-password gate permanece.

---

# 30. PRIVACIDAD Y SEGURIDAD

Invariantes:

- soportes siempre fuera webroot;
- ownership tenant antes de obligation/support lookup;
- staff auth/permission antes de detail/support;
- MIME por bytes;
- integrity check antes de servir;
- no path leak;
- `external_reference` staff-only por defecto;
- no datos sensibles innecesarios en `description`;
- HTML escaped;
- CSRF en POST;
- basePath/subfolder;
- no payload completo de archivos/obligación en logs;
- no provider credentials;
- no integraciones bancarias.

---

# 31. NO INCLUIDO EN F12

No implementar:

- cálculo/lectura automática de medidores;
- API EPM/servicios;
- OCR de facturas;
- importación de facturas;
- autopay;
- bank/PSE/Bre-B API;
- payment reconciliation paralelo;
- cron;
- recordatorios;
- email;
- WhatsApp;
- notification log;
- pólizas/reclamaciones (F13);
- tickets/mantenimiento (F14);
- sistema documental genérico (F18);
- multas automáticas;
- intereses;
- prorrateos;
- contabilidad;
- facturación electrónica.

---

# 32. CONCURRENCIA

Debe probarse al menos:

1. dos `generateCharge` concurrentes sobre la misma obligación no crean dos cargos;
2. dos `settleExternal` concurrentes dejan un solo actor/time;
3. `void` vs `generateCharge` no deja obligación voided con cargo activo creado lateralmente;
4. dos links concurrentes no enlazan el mismo cargo a dos obligaciones;
5. una obligación no termina con dos `charge_id` distintos.

Autoridad de serialización principal:

```text
lease_obligations row FOR UPDATE
```

Para link/generate que toque cargo:

- lock obligation primero;
- usar el mismo PDO/transaction boundary cuando se cree cargo F9;
- luego leer/lockear cargo si la operación de link lo necesita;
- deterministic order para múltiples filas;
- no lock service.

El PLAN debe congelar el orden exacto tras conocer F11/F9 final integrado.

---

# 33. ARQUITECTURA DE ARCHIVOS ESPERADA

F12 probablemente crea:

```text
database/migrations/012_create_lease_obligations.sql
app/Finance/ObligationService.php
```

Tests esperados:

```text
tests/Integration/Finance/ObligationSchemaTest.php
tests/Integration/Finance/ObligationServiceTest.php
tests/Integration/Finance/ObligationRoutesTest.php
tests/Integration/Finance/ObligationSecurityRegressionTest.php
```

Producto esperado modifica, sujeto a preflight final:

```text
app/Finance/routes.php
app/Leases/routes.php
resources/views/finance_index.php or a focused obligations view
resources/views/tenant_lease_detail.php
bootstrap/app.php
```

No congelar constructor/rutas exactas hasta leer F11 final integrado.

---

# 34. CRITERIOS DE ACEPTACIÓN

F12 no puede cerrar si falla cualquiera:

1. una sola tabla `lease_obligations`;
2. tipos exactos `utility|administration_ph|fine_ph`;
3. money/date validation;
4. lease activo o terminado válido;
5. status externo derivado, no persistido;
6. `settled_at` y `charge_id` pueden coexistir legítimamente;
7. source support privado e íntegro;
8. settlement support privado e íntegro;
9. utility puede generar un único cargo F9 `service` explícitamente y de forma atómica;
10. fine_ph puede generar un único cargo F9 `fine` explícitamente y de forma atómica;
11. administration_ph NO genera segundo cargo y solo enlaza `administration` existente;
12. link de administración valida mismo lease/período/tipo;
13. mismatch de monto administración no destruye ninguna autoridad;
14. F12 no calcula saldo;
15. F12 no crea payments;
16. tenant ownership aislado;
17. staff permissions reutilizadas;
18. tenant no muta obligaciones en V1;
19. source support tenant-visible y settlement support staff-only según regla fija;
20. no rutas públicas;
21. no cron/notificaciones/OCR/provider integrations;
22. no document table genérica;
23. concurrencia no duplica cargos/links/settlements;
24. no cargo huérfano si falla el vínculo F12;
25. full QA PHP 8.3/8.4/8.5 PASS;
26. KISS PASS;
27. Cero Ruido PASS;
28. Patch stacking NONE.

---

# 35. GATE KISS / CERO RUIDO

Presupuesto esperado:

```text
NEW_TABLES: 1
NEW_DOMAIN_CLASSES: 1
NEW_PERMISSIONS: 0
NEW_PUBLIC_ROUTES: 0
NEW_RUNTIME_DEPENDENCIES: 0
NEW_DOCUMENT_TABLES: 0
NEW_PAYMENT_TABLES: 0
NEW_BALANCE_TABLES: 0
NEW_STATUS_HISTORY_TABLES: 0
NEW_CRON_JOBS: 0
NEW_PROVIDER_INTEGRATIONS: 0
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

# 36. AUTOAUDITORÍA DE DISEÑO — 10 PASADAS

## Pasada 1 — autoridad financiera

F12 conserva F9/F11 como única autoridad de deuda/saldo/pagos; `charge_id` es vínculo, no segundo saldo. — PASS.

## Pasada 2 — KISS de persistencia

Una tabla cubre servicios, administración y multas sin tablas paralelas; dos slots de soporte evitan media table prematura; se eliminó índice `charge_id` redundante porque UNIQUE ya lo cubre. — PASS.

## Pasada 3 — convergencia con F9

`utility → service`, `fine_ph → fine`; `administration_ph` reutiliza cargo mensual F9 y nunca crea duplicado. — PASS.

## Pasada 4 — dimensiones independientes

Se corrigió el supuesto de exclusión entre satisfacción externa y cargo: ambas pueden coexistir; no status artificial. — PASS.

## Pasada 5 — dinero/fechas

Monto externo no se confunde con saldo financiero; due date externo no se copia al cargo tenant; administración puede tener monto externo distinto del cargo contractual. — PASS.

## Pasada 6 — soportes/privacidad

Dos archivos máximos, privados, MIME/hash/size, replacement seguro solo antes de terminalidad; visibilidad tenant fija sin ACL genérica. — PASS.

## Pasada 7 — lifecycle/trazabilidad

Edición solo antes de efectos terminales; settle/void fail-closed; no DELETE; lease terminado soportado para obligaciones finales/históricas. — PASS.

## Pasada 8 — seguridad/ownership

Tenant ownership antes de lateral lookup; 0 permisos nuevos; tenant read-only F12 V1; external_reference staff-only. — PASS.

## Pasada 9 — concurrencia/atomicidad/antiparches

Fila obligation es lock principal; generación de cargo + link comparte transaction/como PDO para evitar cargo huérfano; no lock service ni segundo writer de charges. — PASS.

## Pasada 10 — scope futuro

Sin cron, recordatorios, OCR, providers, F13/F14/F16/F18, contabilidad o segundo proceso de pago. — PASS.

---

# 37. GATE DE DOCUMENTO

```text
F12_PRE_KISS: APPROVED BY USER
F12_DESIGN: APPROVED BY USER
F12_SPEC: USER REVIEW REQUIRED
F12_PLAN: NOT WRITTEN
F12_IMPLEMENTATION: BLOCKED ON F11 FINAL AUDIT + MERGE
MERGE_AUTHORIZED: NO
```

Siguiente paso únicamente después de aprobación explícita de esta SPEC:

```text
WRITE F12 IMPLEMENTATION PLAN
```

No implementar producto F12 desde esta rama documental.
