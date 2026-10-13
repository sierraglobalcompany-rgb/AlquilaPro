# F10 — MEDIOS DE PAGO — SPEC

**Proyecto:** AlquilaPro  
**Fecha:** 2026-10-13 (America/Bogota)  
**Rama de diseño:** `spec/f10-payment-methods-20261013`  
**Estado:** `DRAFT / USER REVIEW REQUIRED / IMPLEMENTATION BLOCKED ON F9 FINAL AUDIT + MERGE`  
**Fuentes canónicas:** `ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`, `ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`, F9 SPEC/PLAN aprobados y patrones reales de storage/auth del repositorio.

---

# 1. OBJETIVO

F10 introduce la **única autoridad de configuración de medios de pago** que AlquilaPro muestra a los inquilinos para indicar **cómo y dónde pagar**.

Debe permitir configurar:

- cuenta bancaria;
- Llave Bre-B;
- QR Bre-B provisto por la entidad financiera;
- billetera/depósito electrónico;
- instrucción manual;
- orden de visualización;
- alcance global o por propiedad;
- activo/inactivo.

F10 **no registra pagos**, no concilia, no aplica dinero a cargos y no se conecta a bancos.

El hecho de que un medio aparezca en F10 **no significa que exista un pago**. Esa autoridad comienza en F11.

---

# 2. DEPENDENCIA Y GATE DE ARRANQUE

F10 depende de F9 porque reutiliza su autoridad financiera administrativa y sus permisos:

```text
finance.view
finance.manage
```

La implementación productiva F10 queda bloqueada hasta que:

1. F9 termine `CLOSED / VERIFIED`;
2. ChatGPT/Sol audite el cierre real de F9;
3. F9 sea fusionada en `main`;
4. se fije el SHA exacto de `main` que contiene F9;
5. no existan cambios pendientes que alteren el shell financiero o el portal tenant.

Esta rama de SPEC parte de `main` solo para aislar documentación.

**No es base autorizada de implementación F10.**

La implementación F10 debe partir del `main` que contenga F9 final auditada y fusionada.

---

# 3. LEYES SUPREMAS

## LEY 1 — KISS

F10 debe resolver configuración y visualización de medios de pago con:

- una sola tabla nueva;
- una sola clase de dominio nueva;
- máximo un route file nuevo;
- cero permisos nuevos;
- cero integraciones bancarias;
- cero rutas públicas;
- cero tablas transaccionales de pago.

Preferir nullable fields explícitos en una sola tabla antes que scopes polimórficos, tablas puente o JSON genérico.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

F10 no debe adelantar F11 ni crear compatibilidad especulativa con bancos/API/PSE.

No crear placeholders, providers, webhooks, tokens, payment intents ni adapters sin una integración real aprobada.

---

# 4. PRE-KISS — DECISIONES ESTRUCTURALES

## 4.1 Una sola tabla nueva

F10 añade exactamente:

`payment_methods`

No añade:

- `payment_method_scopes`;
- `lease_payment_methods`;
- `property_payment_methods`;
- `bank_accounts`;
- `wallets`;
- `breb_keys`;
- `payment_providers`;
- `bank_tokens`;
- `payment_transactions`;
- `payment_intents`.

## 4.2 Una sola clase de dominio

F10 añade exactamente:

`PaymentMethodService`

No crear:

- `BankAccountService`;
- `BrebService`;
- `QrService`;
- `WalletService`;
- `PaymentProviderService`;
- repositories/DTOs/interfaces.

La misma autoridad valida, persiste, ordena, resuelve alcance y maneja el QR privado.

## 4.3 Alcance sin tabla puente

F10 usa:

```text
property_id NULL  => alcance global
property_id = X   => alcance de esa propiedad
```

No existe `scope_type` ni `scope_id` polimórficos.

No existe alcance por lease en F10.

Para un arrendamiento:

```text
métodos aplicables = activos globales + activos de la property del lease
```

Orden:

```text
sort_order ASC, id ASC
```

No existe precedencia/override automática entre global y propiedad.

Si administración configura dos medios similares, ambos son visibles según orden; F10 no intenta deduplicar semánticamente cuentas.

## 4.4 F10 configura recepción; F11 registra pagos

F10 solo responde:

```text
¿A dónde / cómo puede pagar el inquilino?
```

F11 responderá:

```text
¿Qué pago reportó el inquilino?
¿Qué monto?
¿Qué medio utilizó?
¿Cómo se concilia y se asigna?
```

No mezclar ambas autoridades.

---

# 5. PRESUPUESTO ARQUITECTÓNICO F10

```text
NEW_TABLES: 1
  payment_methods

NEW_DOMAIN_CLASSES: 1
  PaymentMethodService

NEW_ROUTE_FILES: 1 maximum
  app/Finance/payment_methods_routes.php
  OR reuse app/Finance/routes.php only if F9 final structure proves this is simpler

NEW_PERMISSIONS: 0
NEW_PUBLIC_ROUTES: 0
NEW_PAYMENT_TRANSACTION_TABLES: 0
NEW_BANK_INTEGRATIONS: 0
NEW_DIRECT_RUNTIME_DEPENDENCIES: 0
NEW_AUTH_SYSTEMS: 0
NEW_SESSION_SYSTEMS: 0
NEW_PROVIDER_ADAPTERS: 0
NEW_WEBHOOKS: 0
```

F10 reutiliza:

```text
finance.view
finance.manage
```

No crear `payment_methods.view/manage`.

---

# 6. MODELO `payment_methods`

Esquema conceptual exacto:

```text
id BIGINT UNSIGNED PK AUTO_INCREMENT
property_id BIGINT UNSIGNED NULL
type VARCHAR(32) NOT NULL
label VARCHAR(120) NOT NULL
recipient_name VARCHAR(190) NULL
institution_name VARCHAR(190) NULL
account_type VARCHAR(40) NULL
account_number VARCHAR(120) NULL
breb_key_type VARCHAR(40) NULL
breb_key_value VARCHAR(190) NULL
wallet_identifier VARCHAR(190) NULL
instructions VARCHAR(1000) NULL
qr_stored_name VARCHAR(190) NULL
qr_mime_type VARCHAR(80) NULL
qr_sha256 CHAR(64) NULL
qr_size_bytes BIGINT UNSIGNED NULL
sort_order INT UNSIGNED NOT NULL DEFAULT 1
is_active TINYINT(1) NOT NULL DEFAULT 1
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NULL
```

No añadir:

- `scope_type`;
- `scope_id`;
- `status`;
- `provider_id`;
- `api_key`;
- `secret`;
- `username`;
- `password`;
- `pin`;
- `token`;
- `webhook_secret`.

## 6.1 FK e índices

FK:

```text
property_id → properties.id ON DELETE RESTRICT
```

Índices aprobados:

```text
KEY payment_methods_property_active_order_index (property_id, is_active, sort_order)
KEY payment_methods_active_order_index (is_active, sort_order)
```

No crear índices por cada nullable field de cada tipo.

## 6.2 `label`

`label` es texto corto visible a staff e inquilino.

Ejemplos:

- `Cuenta Bancolombia`;
- `Llave Bre-B principal`;
- `QR Bre-B`;
- `Nequi`.

Debe estar escapado en HTML.

## 6.3 `recipient_name`

Nombre del titular/receptor visible al inquilino cuando sea útil.

No se deriva de owner/tenant/property porque el receptor puede ser una empresa o tercero autorizado distinto.

---

# 7. TIPOS F10

Allowlist exacta:

```text
bank_account
breb_key
breb_qr
wallet
manual
```

No añadir:

```text
pse
credit_card
cash
paypal
stripe
wompi
mercadopago
```

sin una fase/integración aprobada.

---

# 8. VALIDACIÓN POR TIPO

## 8.1 `bank_account`

Requiere:

```text
label
recipient_name
institution_name
account_type
account_number
```

Opcional:

```text
instructions
```

Debe dejar null:

```text
breb_key_type
breb_key_value
wallet_identifier
qr_*
```

## 8.2 `breb_key`

Requiere:

```text
label
breb_key_type
breb_key_value
```

Puede incluir:

```text
recipient_name
institution_name
instructions
```

Debe dejar null:

```text
account_type
account_number
wallet_identifier
qr_*
```

`breb_key_type` es descriptor de la Llave configurada, no una credencial bancaria.

F10 no registra ni modifica la Llave en Bre-B; solo muestra la llave que el administrador configuró desde su entidad financiera.

## 8.3 `breb_qr`

Requiere:

```text
label
QR válido almacenado privadamente
```

Puede incluir:

```text
recipient_name
institution_name
instructions
```

Debe dejar null:

```text
account_type
account_number
breb_key_type
breb_key_value
wallet_identifier
```

El QR debe provenir de una entidad/canal financiero legítimo.

AlquilaPro no genera contenido QR bancario interoperable.

## 8.4 `wallet`

Requiere:

```text
label
institution_name
wallet_identifier
```

Puede incluir:

```text
recipient_name
instructions
```

Debe dejar null:

```text
account_type
account_number
breb_key_type
breb_key_value
qr_*
```

## 8.5 `manual`

Requiere:

```text
label
instructions
```

Todos los campos bancarios/llave/wallet/QR deben quedar null.

---

# 9. SEGURIDAD DE DATOS FINANCIEROS

F10 puede almacenar datos de **enrutamiento de pago visibles al inquilino autenticado**, por ejemplo:

- número de cuenta;
- tipo de cuenta;
- Llave Bre-B;
- identificador de billetera;
- QR de recepción.

F10 nunca almacena:

- usuario bancario;
- contraseña;
- PIN;
- OTP;
- token de acceso bancario;
- API secret;
- llave privada;
- credencial de portal bancario;
- códigos dinámicos de autenticación.

No registrar esos valores en logs de aplicación.

Los errores de validación deben ser genéricos y no imprimir payload completo.

---

# 10. QR PRIVADO

## 10.1 Reutilización de patrón existente

F10 reutiliza el patrón ya probado por `UnitMediaService`/storage privado:

- MIME detectado por bytes con `finfo`;
- formatos allowlist;
- tamaño máximo;
- nombre aleatorio criptográfico;
- storage fuera del webroot;
- permisos de archivo restrictivos;
- ruta controlada para servir bytes;
- nunca confiar en extensión/nombre del navegador.

No crear una tabla media separada para un único QR opcional por medio.

## 10.2 Formatos

Allowlist:

```text
image/png
image/jpeg
image/webp
```

No SVG.

SVG queda excluido por complejidad/active-content risk innecesario.

## 10.3 Tamaño

Máximo F10:

```text
2 MiB
```

Un QR de pago no necesita 10 MiB.

## 10.4 Nombre

Patrón recomendado:

```text
qr-<32 hex>.<ext>
```

## 10.5 Integridad

Persistir:

```text
qr_stored_name
qr_mime_type
qr_sha256
qr_size_bytes
```

Al servir:

- path debe resolver dentro del directorio F10;
- archivo debe existir;
- MIME real debe coincidir;
- size debe coincidir;
- SHA256 debe coincidir.

Fallo de integridad:

```text
404
```

No exponer physical filename.

---

# 11. CICLO DE VIDA DEL MEDIO

F10 permite:

- crear;
- editar configuración;
- activar/desactivar;
- cambiar orden;
- reemplazar/eliminar QR.

No existe DELETE operativo del registro en V1.

Razón:

F11 podrá necesitar referenciar el método usado en reportes/pagos históricos.

Por tanto:

```text
retire = is_active = 0
```

No borrar fila desde UI normal.

Editar una configuración actual es válido porque F10 es configuración, no comprobante histórico.

F11 deberá congelar/snapshotear lo necesario al registrar un pago si el histórico exige conservar cómo estaba configurado en ese momento.

F10 no precrea ese snapshot.

---

# 12. ORDEN

`sort_order` es entero >= 1.

No requiere unicidad.

Resolución estable:

```text
ORDER BY sort_order ASC, id ASC
```

No crear tabla de ordering.

No implementar drag-and-drop si un input numérico/form SSR resuelve.

---

# 13. RESOLUCIÓN POR ALCANCE

Operación conceptual:

```text
PaymentMethodService::listForProperty(int $propertyId): array
```

Retorna únicamente:

```text
is_active = 1
AND (property_id IS NULL OR property_id = :property_id)
```

Ordenado por:

```text
sort_order ASC, id ASC
```

No filtrar por lease directamente en el servicio.

El route tenant obtiene primero el lease owned y usa su `property_id` canónico.

Esto preserva ownership en F8/F9 y evita pasar `property_id` desde navegador como autoridad.

---

# 14. ADMINISTRACIÓN / UX

## 14.1 Superficie administrativa

F10 debe vivir dentro de la app financiera existente.

Ruta recomendada:

```text
GET /admin/finance/payment-methods
```

La UI muestra:

- label;
- tipo;
- alcance (`Global` o nombre de propiedad);
- receptor/institución cuando aplique;
- orden;
- activo/inactivo;
- si tiene QR;
- edición.

No mostrar secrets porque no existen.

## 14.2 Creación

Ruta conceptual:

```text
POST /admin/finance/payment-methods
```

Usa `finance.manage`.

## 14.3 Actualización

Ruta conceptual:

```text
POST /admin/finance/payment-methods/{id}
```

Una sola mutación soporta:

- campos de configuración;
- activo/inactivo;
- sort_order;
- QR opcional nuevo;
- `remove_qr=1` cuando corresponda.

No crear una ruta por campo/acción si una actualización coherente resuelve.

## 14.4 Lectura staff

`finance.view` puede listar y ver configuración.

`finance.manage` puede crear/editar.

Owner conserva bypass existente.

Tenant nunca accede a rutas admin.

---

# 15. PORTAL TENANT

F10 reutiliza el detalle del arrendamiento existente.

No crear portal nuevo.

No crear `/tenant/payment-methods` como página separada si el lease detail resuelve.

Dentro de:

```text
GET /tenant/leases/{id}
```

mostrar sección:

```text
Cómo pagar
```

solo después de:

```text
LeaseService::findForUser(leaseId, currentUserId)
```

Con el lease owned se toma `property_id` y se resuelven métodos activos globales + property-specific.

Un lease finalizado/histórico puede seguir viendo métodos activos actuales si necesita atender obligaciones pendientes; F10 no infiere saldo ni bloquea por `ended_at`.

---

# 16. RUTA PRIVADA DEL QR TENANT

Para evitar data URI, filenames físicos o storage público, F10 añade una única ruta privada para servir el QR al tenant:

```text
GET /tenant/leases/{leaseId}/payment-methods/{methodId}/qr
```

Orden de seguridad:

1. auth tenant existente;
2. forced-password gate existente;
3. ownership `LeaseService::findForUser(leaseId, currentUserId)`;
4. obtener `property_id` del lease owned;
5. verificar que el method está activo y es global o de esa property;
6. verificar que method type es `breb_qr` y tiene QR íntegro;
7. servir bytes.

Foreign lease/method/scope mismatch/tampered file:

```text
404
```

Headers mínimos:

```text
Content-Type: detected image MIME
Content-Length: exact bytes
Cache-Control: private, no-store
X-Content-Type-Options: nosniff
```

No usar `Content-Disposition` con physical stored name.

---

# 17. RUTA STAFF PARA QR

No es obligatoria en F10.

La administración puede mostrar simplemente:

```text
QR configurado / Sin QR
```

Esto evita una ruta de preview extra sin necesidad operativa demostrada.

Si durante pruebas/UX se demuestra que staff necesita preview para verificar la imagen subida, debe reutilizar la misma autoridad de integridad y requerir `finance.view`; no se autoriza automáticamente por esta SPEC.

---

# 18. RUTAS F10 — PRESUPUESTO

Rutas nuevas esperadas:

```text
GET  /admin/finance/payment-methods
POST /admin/finance/payment-methods
POST /admin/finance/payment-methods/{id}
GET  /tenant/leases/{leaseId}/payment-methods/{methodId}/qr
```

Total F10 esperado:

```text
4 rutas privadas
0 rutas públicas
```

Si F9 final usa un único `app/Finance/routes.php`, F10 debe preferir extenderlo si sigue siendo pequeño y claro.

Crear un segundo route file solo si mantener todas las rutas en uno viola legibilidad; el presupuesto permite máximo **un route file F10 adicional**, no uno obligatorio.

---

# 19. SERVICIO — INTERFACES CONCEPTUALES

Clase:

```text
PaymentMethodService
```

Constructor conceptual:

```text
__construct(PDO $pdo, string $qrDirectory)
```

Operaciones mínimas:

```text
create(array $input, ?UploadedFileInterface $qr): array
update(int $id, array $input, ?UploadedFileInterface $qr, bool $removeQr): array
listAll(): array
listForProperty(int $propertyId): array
find(int $id): ?array
privateQr(int $id): ?array
```

No crear service separado para archivos.

No crear repository.

No crear provider interface.

La dependencia `UploadedFileInterface` ya existe en el stack y sigue el patrón probado del proyecto; no añade runtime dependency.

---

# 20. ACTUALIZACIÓN Y ATOMICIDAD QR

Actualizar metadata DB y archivo debe evitar estados rotos.

Patrón mínimo:

- validar inputs y upload completamente antes de mutar;
- preparar nuevo archivo con nombre aleatorio;
- mover nuevo archivo;
- actualizar fila;
- si DB update falla, borrar nuevo archivo;
- solo después de update exitoso eliminar QR anterior si fue reemplazado/removido;
- si eliminación física anterior falla, no revertir metadata nueva a una referencia rota; registrar error técnico sin exponer datos sensibles y mantener coherencia DB→archivo actual.

No introducir filesystem transactions ficticias.

Tests deben pinnear rollback/cleanup de nuevo archivo ante fallo DB cuando el harness lo permita.

---

# 21. CAMPOS AJENOS AL TIPO

Antes de persistir, el servicio debe normalizar campos no aplicables a `NULL`.

Ejemplo:

cambiar de `bank_account` a `manual` debe limpiar:

```text
institution/account fields
breb fields
wallet fields
qr metadata/file
```

según el tipo destino.

No dejar residuos invisibles de un tipo anterior.

Esto es Cero Ruido a nivel de datos.

---

# 22. VALIDACIÓN DE PROPIEDAD

`property_id`:

- NULL = global;
- entero positivo existente = property scope;
- inexistente = fail closed.

No aceptar `property_id` del tenant.

Solo staff finance.manage lo configura.

No bloquear un método property-specific porque la property esté inactiva; puede existir obligación histórica. La visibilidad tenant depende de ownership del lease y `is_active` del método, no de publicación de catálogo.

---

# 23. PERMISOS

F10 añade:

```text
NEW_PERMISSIONS: 0
```

Reutiliza:

```text
finance.view
finance.manage
```

Si F9 final no contiene esos permisos exactamente, implementación F10 debe STOP y reabrir SPEC/planificación; no inventar sustitutos silenciosamente.

---

# 24. NO INCLUIDO

F10 excluye:

- registrar pagos;
- comprobantes;
- payment reports;
- payments;
- allocations;
- conciliación;
- estados parcial/pagado/reportado;
- validación bancaria;
- consulta de saldo bancario;
- API bancaria;
- PSE;
- tarjetas;
- webhooks;
- QR generado por la aplicación;
- cambio/registro de Llaves Bre-B ante una entidad;
- encrypt-at-rest custom framework;
- vault propio;
- tokenización bancaria;
- cron;
- notifications.

---

# 25. PRUEBAS OBLIGATORIAS — TDD

## Schema

- exactamente una tabla F10: `payment_methods`;
- columnas exactas;
- FK property RESTRICT;
- índices exactos;
- no provider/token/transaction tables;
- no auth/secret credential columns.

## Validation

- exact type allowlist;
- required fields per type;
- irrelevant fields cleared/null;
- label trimmed/non-empty;
- property global/existing/nonexistent;
- sort_order >=1;
- is_active normalized to 0/1 at SQL boundary;
- lengths enforced;
- no type leaves stale data from previous type.

## Scope

- global visible to any owned lease property;
- property-specific visible only matching property;
- inactive absent;
- stable order;
- tenant cannot choose property ID.

## QR upload

- PNG/JPEG/WebP valid;
- SVG/rejected MIME;
- oversize >2MiB rejected;
- empty upload rejected;
- random name;
- outside webroot;
- MIME from bytes;
- hash/size stored;
- replace removes superseded file after successful DB update;
- remove QR clears metadata;
- type change away from QR removes stale file/metadata;
- tampered bytes/hash/size/MIME produce 404.

## Admin routes

- guest login redirect;
- forced password gate;
- finance.view GET;
- finance.manage POST;
- viewer cannot mutate;
- tenant 403 even with polluted permissions;
- global CSRF;
- basePath;
- PRG 303;
- no future payment UI.

## Tenant

- ownership first;
- owned lease sees active applicable methods;
- foreign lease 404;
- global + matching property only;
- inactive absent;
- bank/Bre-B/wallet/manual fields escaped;
- no secrets/internal path/stored name;
- private QR 200 only in correct ownership/scope;
- wrong method/scope/tamper 404;
- no payment reporting button in F10.

## Architecture regression

Congelar:

```text
1 payment_methods table
1 PaymentMethodService
0 new permissions
4 private routes expected
0 public routes
0 transaction/payment tables
0 provider adapters
0 bank APIs
0 webhooks
0 new runtime dependencies
0 parallel auth/session
```

---

# 26. MAPA DE ARCHIVOS PROPUESTO

## Crear

```text
database/migrations/010_create_payment_methods.sql
app/Finance/PaymentMethodService.php
resources/views/payment_methods.php
tests/Integration/Finance/PaymentMethodSchemaTest.php
tests/Integration/Finance/PaymentMethodServiceTest.php
tests/Integration/Finance/PaymentMethodRoutesTest.php
tests/Integration/Finance/PaymentMethodSecurityRegressionTest.php
```

Route file:

- preferir extender el Finance route file existente si F9 final permanece pequeño;
- crear `app/Finance/payment_methods_routes.php` solo si separación real mejora legibilidad.

## Modificar previsiblemente

```text
bootstrap/app.php
app/Finance/routes.php OR composition equivalent after F9
app/Leases/routes.php
resources/views/tenant_lease_detail.php
resources/views/layouts/app.php only if Finance/Cartera navigation needs a link to methods
relevant F9/F8 security tests
README/checkpoints at closure
```

No CSS/JS nuevo salvo defecto visual reproducido.

Este mapa es provisional hasta contrastar con F9 final auditada/merged.

---

# 27. UX KISS

Admin:

- una lista;
- formulario crear/editar;
- select tipo;
- select alcance global/property;
- campos del tipo;
- upload QR solo para `breb_qr`;
- order numérico;
- active checkbox;
- sin drag/drop obligatorio;
- sin wizard;
- sin SPA.

Tenant:

- sección `Cómo pagar` dentro del lease;
- cards/rows existentes;
- datos claros y copiables;
- QR solo cuando aplique;
- sin botón `Ya pagué` hasta F11.

---

# 28. GATE ANTIPARCHES

Toda reparación F10:

`REPRODUCE → ROOT CAUSE → OWNING AUTHORITY → CONVERGE/SIMPLIFY → MINIMAL GREEN → REMOVE SUPERSEDED LOGIC → REGRESSION`

No aceptar:

- validación duplicada entre route/view/service;
- tipo-specific SQL disperso;
- QR storage fuera de PaymentMethodService;
- scope calculado distinto entre admin/tenant;
- fallback que expone globalmente un method mal configurado;
- route especial por cada tipo;
- permisos nuevos para evitar usar finance.manage;
- datos stale al cambiar type;
- physical filenames en HTML.

---

# 29. GATE DE CIERRE F10

```text
LAW_1_KISS: PASS
LAW_2_CERO_RUIDO: PASS
PATCH_STACKING: NONE
NEW_TABLES: 1
NEW_DOMAIN_CLASSES: 1
NEW_ROUTE_FILES: <=1
NEW_PERMISSIONS: 0
NEW_PUBLIC_ROUTES: 0
NEW_PAYMENT_TRANSACTION_TABLES: 0
NEW_BANK_INTEGRATIONS: 0
NEW_DIRECT_RUNTIME_DEPENDENCIES: 0
```

Además:

- focused F10 PASS;
- full suite PASS;
- Composer validate PASS;
- CI PHP 8.3/8.4/8.5 PASS;
- QR integrity/storage PASS;
- tenant ownership/scope PASS;
- no secrets persisted/logged;
- product diff auditado;
- merge separado/no automático.

---

# 30. DECISIONES CONGELADAS

1. F10 = configuración de medios de pago, no pagos.
2. Una tabla: `payment_methods`.
3. Una clase: `PaymentMethodService`.
4. Scope global/property mediante nullable `property_id`.
5. Sin tabla puente de scope.
6. Tipos: bank_account, breb_key, breb_qr, wallet, manual.
7. Reutiliza finance.view/manage; 0 permisos nuevos.
8. Sin delete operativo; desactivar para retirar.
9. QR provisto externamente, nunca generado como QR bancario por AlquilaPro.
10. QR privado fuera webroot, MIME-bytes, hash/size, nombre aleatorio.
11. No credenciales bancarias/PIN/OTP/tokens.
12. Tenant usa lease detail existente.
13. Ownership lease se resuelve antes del scope financiero.
14. 4 rutas privadas esperadas; 0 públicas.
15. F11 registrará pagos/conciliación y podrá snapshotear método si necesita histórico.
16. Implementación parte de F9 final auditada y merged en main.

---

# 31. AUTOAUDITORÍA DE DISEÑO — 10 PASADAS

1. **Scope:** F10 queda limitada a configuración/visualización; F11 no se adelanta — PASS.
2. **KISS:** 1 tabla + 1 servicio + <=1 route file — PASS.
3. **Cero Ruido:** nullable property scope evita tabla puente/polimorfismo — PASS.
4. **Permisos:** reutiliza finance.view/manage; 0 nuevos — PASS.
5. **Seguridad bancaria:** no passwords/PIN/OTP/API secrets — PASS.
6. **QR:** patrón existente, storage privado, hash/size/MIME, no SVG — PASS.
7. **Tenant isolation:** lease ownership antes de resolver method/scope — PASS.
8. **Evolución:** F11 puede referenciar/snapshotear method sin precrear transactions — PASS.
9. **Antiparches:** cambio de tipo limpia stale fields y QR, evitando residuos — PASS.
10. **Git/dependencia:** implementación bloqueada hasta F9 auditada + merged — PASS.

No se encontró motivo demostrado para tabla de scopes, tabla de QR, permisos nuevos, provider adapters, bank API o payment transaction tables en F10.

---

# 32. GATE DE APROBACIÓN

```text
F10_PRE_KISS: READY FOR USER REVIEW
F10_SPEC: DRAFT / USER REVIEW REQUIRED
F10_PLAN: NOT AUTHORIZED YET
F10_IMPLEMENTATION: BLOCKED
F9_DEPENDENCY: WAITING FINAL AUDIT + MERGE
```

Después de aprobación explícita:

1. contrastar contra F9 final auditada/merged;
2. ajustar mapa de archivos a la autoridad real;
3. escribir PLAN F10 task-by-task;
4. auditar PLAN KISS/Cero Ruido/Antiparches;
5. preparar prompt maestro Markdown para Codex Luna;
6. abrir implementación solo sobre base F9 integrada autorizada.
