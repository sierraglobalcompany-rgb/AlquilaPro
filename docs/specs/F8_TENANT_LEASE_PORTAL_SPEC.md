# F8 — INQUILINO + LEASE + PORTAL — SPEC

**Fecha:** 2026-10-13 (America/Bogota)  
**Estado:** APPROVED BY USER / PLAN APPROVED / IMPLEMENTATION IN PROGRESS  
**Base:** `main @ f4a7e3f4d5ec7dc9cdc0f3a7e78777f98c85461d`  
**F7 integrado:** `b6ab43fefd7b4d2933e3e3adff0ac4c23efdf1b8`  
**Branch de diseño:** `spec/f8-tenant-lease-portal-20261013`  
**Branch de implementación:** `impl/f8-tenant-lease-portal-20261013`

---

## 0. AUTORIDAD

Orden aplicable:

`SPEC maestra V1.2 → PLAN maestro V1.2 → README → esta SPEC F8 → plan F8 → tests/CI → código real`.

Esta SPEC fue aprobada por el usuario el 2026-10-13. El PLAN F8 también fue aprobado y la ejecución Native quedó autorizada. La implementación debe seguir el plan y detenerse antes del merge hasta una autorización explícita posterior.

---

## 1. OBJETIVO F8

Convertir un contrato F7 `finalized` en un arrendamiento operativo privado que:

1. reutiliza el mismo usuario/auth F2;
2. vincula el contrato finalizado con el usuario inquilino;
3. marca la unidad como ocupada únicamente al activar el arrendamiento;
4. habilita un portal SSR mínimo y contextual para el inquilino;
5. conserva aislamiento estricto entre usuarios;
6. no introduce cartera, pagos, pólizas, tickets, inventarios, documentos privados ni otras fases futuras.

Flujo canónico:

`candidate existente → screening approved → contract finalized → activar lease → user tenant → portal privado`

F8 no reemplaza ni duplica F2, F4, F5, F6 o F7.

---

## 2. PRE-KISS / REFINAMIENTO DE DISEÑO

### 2.1 Refinamiento frente al SCOPE inicial

El SCOPE inicial propuso `tenants + leases`. La auditoría previa a esta SPEC elimina `tenants` porque no demuestra ciclo de vida independiente.

Una tabla `tenants(candidate_id, user_id)` sería derivable desde:

`lease.contract_id → contracts.candidate_id`

y

`lease.user_id → users.id`.

Agregar esa tabla duplicaría una relación ya demostrable sin aportar estado, integridad o consulta imprescindible.

**Decisión aprobada:** F8 agrega solo `leases`.

Si en una fase futura aparece información/ciclo real de tenant que no pertenezca a `users`, `candidates` o `leases`, se reevalúa con evidencia.

### 2.2 `lease_units` también se rechaza en F8

F7 real tiene exactamente un `unit_id` por contrato. Por tanto F8 no crea `lease_units`.

La unidad del lease se deriva de:

`leases.contract_id → contracts.unit_id`.

Un apartamento y un parqueadero independientes pueden representarse mediante contratos/leases independientes sin tabla puente.

### 2.3 Sin duplicar fechas ni economía

F8 no copia a `leases`:

- `unit_id`;
- `candidate_id`;
- `monthly_rent`;
- `administration_fee`;
- `start_date`;
- `end_date`;
- `duration_text`.

Esos valores ya están congelados en el contrato F7 y se consultan por join.

---

## 3. PRESUPUESTO DE ARQUITECTURA

```text
NEW_TABLES: 1
  leases

NEW_DOMAIN_CLASSES: 1
  LeaseService

NEW_ROUTE_FILES: 1 máximo
  app/Leases/routes.php

NEW_PERMISSIONS: 0
NEW_PUBLIC_ROUTES: 0
NEW_DIRECT_RUNTIME_DEPENDENCIES: 0
NEW_AUTH_SYSTEMS: 0
NEW_SESSION_TABLES: 0
NEW_TENANT_TABLES: 0
NEW_LEASE_UNIT_JOIN_TABLES: 0
NEW_FINANCIAL_TABLES: 0
```

Modificar autoridades existentes cuando sea necesario es preferible a crear capas paralelas.

---

## 4. MODELO DE DATOS

### 4.1 Tabla `leases`

Campos propuestos:

```text
id BIGINT UNSIGNED PK AUTO_INCREMENT
contract_id BIGINT UNSIGNED NOT NULL UNIQUE
user_id BIGINT UNSIGNED NOT NULL
activated_by_user_id BIGINT UNSIGNED NULL
activated_at DATETIME NOT NULL
ended_by_user_id BIGINT UNSIGNED NULL
ended_at DATETIME NULL
```

Índices/FK mínimos:

- UNIQUE `contract_id`;
- INDEX `user_id`;
- INDEX `activated_by_user_id`;
- INDEX `ended_by_user_id`;
- FK `contract_id → contracts.id ON DELETE RESTRICT`;
- FK `user_id → users.id ON DELETE RESTRICT`;
- FK `activated_by_user_id → users.id ON DELETE SET NULL`;
- FK `ended_by_user_id → users.id ON DELETE SET NULL`.

No `status` persistido.

Estado derivado:

```text
ACTIVE = ended_at IS NULL
ENDED  = ended_at IS NOT NULL
```

Esto evita `status` + `ended_at` divergentes.

### 4.2 Fuente de verdad del contexto

Para cada lease:

```text
user       = leases.user_id
contract   = leases.contract_id
candidate  = contracts.candidate_id
unit       = contracts.unit_id
property   = units.property_id
terms      = contracts frozen fields
pdf        = ContractService/privatePdf existing evidence
```

No hay columnas espejo.

---

## 5. AUTH ÚNICO

### 5.1 Login

El inquilino usa exactamente:

- `/login`;
- `AuthService`;
- `SessionGuard`;
- CSRF global;
- `/account/password`;
- `/reset-password`;
- sesión existente.

No existe:

- `/tenant-login`;
- `TenantAuthService`;
- segunda tabla de sesiones;
- password flow paralelo.

### 5.2 Rol

Se agrega `tenant` como valor válido de `users.role` solo para identidades creadas desde activación F8.

Un usuario tenant tiene `permissions_json = NULL/[]`.

El portal no depende de permisos administrativos; depende de identidad autenticada + ownership `leases.user_id`.

### 5.3 Alta de identidad tenant

No se habilita `tenant` en el formulario genérico `/admin/users`.

`UserService::create()` conserva su catálogo administrativo actual (`admin|gestor`). No se amplía ese método para aceptar `tenant`, porque la ruta `/admin/users` recibe el rol desde formulario y eso abriría creación manual de tenant huérfano.

La identidad tenant se crea exclusivamente desde activación F8 mediante un método específico y mínimo de `UserService` (nombre final a definir en plan, por ejemplo `createTenantIdentity()`).

Datos de alta:

- `full_name`: candidato canónico;
- `email`: email canónico del candidato;
- `role = tenant`;
- `is_active = 1`;
- `permissions_json = NULL`;
- `must_change_password = 1`;
- contraseña temporal generada por servidor.

Contraseña temporal:

- criptográficamente aleatoria;
- mínimo efectivo >= 12 caracteres;
- se almacena solo hash;
- el valor plano se devuelve una única vez tras activación;
- nunca se registra en logs, URL, DB ni checkpoint;
- el inquilino debe cambiarla antes de entrar al portal por el gate existente `must_change_password`.

Si la contraseña temporal se pierde después de la pantalla de activación, **no se vuelve a revelar ni regenerar silenciosamente**. El administrador usa el mecanismo existente de reset link, que mantiene una sola autoridad de recuperación.

### 5.4 Documento como contraseña — RECHAZADO

La idea histórica de usar documento como login/contraseña no se implementa.

Razones:

- el auth real usa email;
- el documento es predecible;
- rompería la política real de contraseña mínima;
- obligaría una vía paralela o degradaría seguridad.

### 5.5 Colisión de email

Activación F8 no auto-vincula un candidato a un usuario existente por coincidencia de email.

Si el email del candidato ya existe en `users`, el flujo falla cerrado con error administrativo explícito.

No se cambia el rol de un staff existente a `tenant` ni se vincula silenciosamente una identidad ajena.

Una resolución manual avanzada de colisiones queda fuera de F8 salvo evidencia real durante implementación/QA.

---

## 6. ACTIVACIÓN DEL LEASE

### 6.1 Entrada administrativa

La acción vive en el detalle administrativo del contrato F7 existente.

No se crea un CRUD administrativo paralelo de “inquilinos”.

CTA mínimo:

`Activar arrendamiento`

visible solo si:

- usuario autenticado;
- forced-password gate superado;
- `contracts.manage` permitido;
- contrato `finalized`;
- contrato aún no tiene lease.

### 6.2 Transacción canónica

La activación debe ser atómica.

Orden mínimo:

1. `BEGIN`;
2. lock del contrato `FOR UPDATE`;
3. releer contrato y exigir `finalized`;
4. lock de la unidad canónica `FOR UPDATE`;
5. exigir que unidad del contrato siga en estado activable `available|in_process`;
6. verificar que el contrato no tenga lease;
7. verificar que no exista otro lease ACTIVE cuya unidad contractual sea la misma;
8. releer candidato canónico;
9. crear identidad tenant mediante `UserService` usando email del candidato;
10. crear `leases`;
11. cambiar la misma `units.status` a `occupied`;
12. `COMMIT`;
13. devolver credencial temporal solo en la respuesta inmediata de éxito.

Si cualquier paso falla:

- rollback completo;
- no usuario huérfano;
- no lease parcial;
- no unidad ocupada sin lease;
- no credencial reportada como válida.

### 6.3 Sin automatización por fecha

F8 no activa automáticamente por `start_date`, cron ni scheduler.

La activación es una decisión humana explícita.

F16 será dueño del motor cron/recordatorios.

---

## 7. FINALIZAR/TERMINAR LEASE

F8 incluye una terminación administrativa mínima para soportar el requisito de contexto activo/inactivo.

Acción:

`Finalizar arrendamiento`

Reglas:

- requiere `contracts.manage`;
- lease debe estar ACTIVE;
- lock del lease;
- registra `ended_by_user_id`;
- `ended_at = now`;
- no altera contrato F7;
- no borra usuario;
- no desactiva automáticamente usuario;
- no cambia automáticamente la unidad a `available`.

Razón de no liberar unidad automáticamente:

terminar ocupación contractual no demuestra que la unidad esté lista para catálogo; puede requerir mantenimiento, inventario o revisión. El administrador conserva el control usando la autoridad existente `units.status`.

El tenant puede conservar acceso autenticado a información histórica de sus leases terminados, pero toda acción futura que requiera lease activo debe fallar cerrado.

---

## 8. INTEGRIDAD DE `units.status`

`units.status` continúa siendo la única autoridad de disponibilidad/ocupación.

F8 debe impedir una contradicción nueva: mientras exista un lease ACTIVE para una unidad, ninguna mutación administrativa existente puede cambiar esa unidad desde `occupied` a `available`, `in_process`, `inactive` o `maintenance`.

La protección debe incorporarse al flujo canónico de actualización de unidad existente, sin crear `is_occupied`, `lease_status`, `catalog_available` ni segunda fuente.

Después de terminar el lease, el administrador puede elegir explícitamente el siguiente estado usando el editor de unidad existente.

F8 no decide automáticamente si la unidad queda `available`, `maintenance` o `inactive`.

---

## 9. PORTAL DEL INQUILINO

### 9.1 Rutas privadas mínimas

```text
GET /tenant
GET /tenant/leases/{id}
GET /tenant/leases/{id}/contract
```

No rutas públicas F8.

### 9.2 Ownership

Cada lectura debe derivar contexto desde el usuario de sesión:

```text
leases.user_id = current_user.id
```

Nunca se acepta `user_id`, `tenant_id`, `candidate_id` o `contract_id` del navegador como autoridad de ownership.

Lease inexistente o ajeno:

`404` preferido para no filtrar existencia.

### 9.3 `/tenant`

Muestra los leases propios, priorizando ACTIVE.

Para cada lease puede mostrar únicamente datos ya existentes y seguros:

- propiedad;
- unidad;
- tipo;
- ubicación apropiada;
- fechas del contrato;
- canon;
- administración;
- estado derivado Active/Ended;
- enlace al contrato.

### 9.4 Detalle contextual por tipo de unidad

Apartamento:

- propiedad;
- unidad/número/torre/piso cuando existan;
- fechas;
- canon/administración;
- contrato.

Parqueadero:

- propiedad;
- código/ubicación/nivel cuando existan;
- canon;
- fechas;
- contrato.

No se muestran módulos de vivienda que no apliquen a parking.

### 9.5 Contrato PDF

El portal no lee archivos directamente.

Debe reutilizar la evidencia privada F7 y sus verificaciones de integridad.

Puede existir una ruta F8 que delegue a `ContractService::privatePdf()` y sirva el PDF solo después de verificar ownership del lease.

Headers privados existentes deben mantenerse:

- `Content-Type: application/pdf`;
- attachment;
- `Cache-Control: private, no-store`;
- `X-Content-Type-Options: nosniff`.

No se expone `pdf_stored_name`.

---

## 10. NAVEGACIÓN / SHELL

Después del login:

- `must_change_password=1` continúa enviando a `/account/password`;
- tras completar cambio, `/` decide el contexto.

Para `role=tenant`:

`GET /` redirige a `/tenant`.

Para owner/admin/gestor:

se conserva el home privado actual.

No se crea un segundo layout de autenticación.

El portal reutiliza `renderApp()` y shell F3 con navegación mínima pertinente.

No se muestran tarjetas administrativas a tenant.

---

## 11. ADMINISTRACIÓN DE USUARIOS

`/admin/users` no debe convertirse en gestor manual de tenants.

Usuarios `tenant` pueden aparecer en lectura administrativa si hacerlo no rompe UI, pero:

- no se crean manualmente desde esa pantalla;
- no pueden recibir permisos administrativos;
- desactivarlos mediante operación existente bloquea login, pero no borra lease/histórico.

El catálogo de roles permitido por el alta genérica sigue sin incluir `tenant`.

`UserService::setPermissions()` debe rechazar cualquier intento de asignar permisos no vacíos a un usuario `role=tenant`, incluso mediante POST manipulado. Vaciar permisos de un tenant es idempotente/permitido.

Esto evita que una identidad de portal se convierta lateralmente en usuario administrativo sin un cambio arquitectónico explícito.

---

## 12. REGLAS DE INTEGRIDAD

1. Un contrato puede producir máximo un lease.
2. Solo contrato `finalized` puede activarse.
3. Un lease pertenece a un único `user_id`.
4. Una unidad no puede tener dos leases ACTIVE simultáneos.
5. Activación serializa por unidad para evitar doble ocupación concurrente.
6. `units.status` sigue siendo única fuente de disponibilidad operativa.
7. Activación cambia `available|in_process → occupied`.
8. Mientras haya lease ACTIVE, la unidad no puede salir de `occupied` por el editor existente.
9. Terminación no presume `occupied → available`.
10. Contrato F7 y PDF son inmutables desde F8.
11. F8 no cambia `candidates.status` ni `screening_processes`.
12. F8 no copia economía/fechas contractuales.
13. F8 no borra lease ni contrato desde operación normal.
14. Terminar lease registra actor + timestamp una sola vez.
15. Un usuario tenant nunca obtiene permisos administrativos dentro de F8.

---

## 13. SEGURIDAD

Debe conservar:

- auth único;
- session rotation existente;
- forced password change;
- password policy real;
- CSRF en toda mutación;
- prepared statements;
- output escaping;
- no PII en URL;
- no contraseña temporal en logs;
- no enumeration de leases;
- IDOR fail-closed;
- subfolder/basePath;
- contrato PDF privado;
- no storage directo;
- no public routes F8.

Pruebas específicas:

- tenant A no puede leer lease/PDF de tenant B;
- staff sin `contracts.manage` no activa/termina lease;
- tenant no puede acceder a `/admin/*` por su rol sin permisos;
- tenant no puede recibir permisos administrativos por POST manipulado;
- password temporal obliga cambio;
- usuario desactivado no puede entrar;
- colisión email falla cerrado;
- activation concurrency no crea dos leases activos para la misma unidad;
- unidad con lease ACTIVE no puede ser desocupada/publicada por mutación administrativa lateral.

---

## 14. UI / UX MÍNIMA

### Administración

En contrato finalized:

- resumen de unidad/candidato;
- botón `Activar arrendamiento`;
- tras éxito: mostrar email + contraseña temporal una única vez con advertencia de entrega segura;
- si la credencial se pierde: usar reset link existente, nunca volver a mostrar la contraseña temporal;
- si ya existe lease: mostrar estado y enlace contextual, sin repetir credencial;
- si lease ACTIVE: botón `Finalizar arrendamiento` con confirmación simple.

### Tenant

Inicio simple:

- saludo;
- “Mi arrendamiento” / “Mis arrendamientos” si existen varios históricos;
- tarjeta por lease;
- CTA `Ver contrato`.

No tabs vacíos de:

- pagos;
- cartera;
- servicios;
- pólizas;
- mantenimiento;
- inventarios;
- documentos;
- convivencia.

Esos módulos aparecerán solo cuando su fase real exista.

---

## 15. NO INCLUYE F8

- cargos/cartera F9;
- medios de pago F10;
- reportes/conciliación F11;
- servicios F12;
- pólizas F13;
- tickets/mantenimiento F14;
- agenda F15;
- cron/email F16;
- WhatsApp F17;
- documentos/inventarios F18;
- convivencia/legal F19;
- membresía F20;
- liquidación F21;
- firma electrónica;
- tenant login separado;
- tenant profile CRUD independiente;
- lease renewal engine;
- múltiples unidades dentro de un único lease;
- automatización de activación/terminación por fechas.

---

## 16. TEST PLAN OBLIGATORIO PARA FUTURO PLAN DE IMPLEMENTACIÓN

### Schema

- migration crea exactamente `leases` y ninguna tabla tenant paralela;
- `contract_id` UNIQUE;
- FKs correctas/restrictivas;
- actor de activación y terminación preservados con `SET NULL`;
- no `unit_id`, `candidate_id`, economía ni fechas duplicadas en leases;
- no status persistido.

### Auth/User

- `UserService::create()` continúa rechazando `tenant`;
- tenant identity solo se crea por método específico F8;
- formulario admin genérico no crea tenant ni por POST manipulado;
- tenant no acepta permisos no vacíos en `setPermissions()`;
- password temporal aleatoria/hash-only;
- must-change-password obligatorio;
- login usa email existente;
- reset/change-password existentes funcionan;
- temp password perdida se resuelve con reset, no revelación;
- email collision fail-closed.

### Activation

- solo finalized;
- draft/generated rechazados;
- contrato con lease rechazado;
- unidad `available|in_process` activa correctamente;
- unidad `occupied|inactive|maintenance` rechazada;
- usuario + lease + occupied son atómicos;
- fallo intermedio revierte todo;
- doble activación concurrente por misma unidad produce solo un ACTIVE;
- no muta candidate/screening/contract snapshot.

### Unit authority

- unidad con lease ACTIVE permanece `occupied`;
- update lateral a `available|in_process|inactive|maintenance` se rechaza;
- tras `ended_at`, el editor vuelve a permitir transición manual según reglas existentes;
- catálogo nunca publica unidad con lease ACTIVE.

### End

- solo ACTIVE;
- guarda `ended_by_user_id + ended_at` una vez;
- segunda terminación falla cerrado o queda idempotente según decisión exacta del plan, sin reescribir actor/fecha;
- no libera unidad automáticamente;
- no borra/desactiva usuario;
- histórico permanece visible al dueño.

### Portal

- auth required;
- forced password required;
- role tenant root → `/tenant`;
- owner/admin/gestor root conserva home;
- tenant solo ve sus leases;
- IDOR 404;
- apartment/parking muestran campos contextuales correctos;
- PDF ownership + hash/size validation F7;
- headers privados;
- no filename físico;
- basePath/subfolder correcto;
- escaping XSS.

### Regression

- F1–F7 suites intactas;
- PHP 8.3/8.4/8.5;
- Composer validate strict;
- security architecture test asegura 1 auth / 1 lease table / 0 public F8 routes.

---

## 17. TWO LAWS GATE — DISEÑO

```text
LEY_1_KISS: PASS PROVISIONAL
LEY_2_CERO_RUIDO: PASS PROVISIONAL

ADDED:
- 1 table: leases
- 1 domain class: LeaseService
- tenant identity path inside existing UserService/auth
- private tenant routes/views

REMOVED_FROM_INITIAL_SCOPE:
- tenants table
- lease_units table
- document-as-password
- duplicate tenant auth
- persisted lease status
- duplicated lease economics/dates/unit/candidate

MERGED_OR_CONVERGED:
- tenant identity → users
- tenant personal source → candidate via contract
- unit source → contract.unit_id
- economics/dates → immutable contract
- availability → units.status
- PDF → F7 ContractService/private evidence
- auth/session/password/reset → F2

DEPENDENCIES_DELTA: 0
DB_SCHEMA_DELTA: +1 table
PUBLIC_ROUTES_DELTA: 0
```

---

## 18. REVIEW POINTS — RESOLVED

Aprobados por el usuario el 2026-10-13:

1. **Sin tabla `tenants`**: tenant se representa como `users(role=tenant)` + relación contractual en `leases`.
2. **Terminación mínima**: `ended_at + ended_by_user_id`, sin liberar automáticamente la unidad.
3. **Bloqueo lateral de `units.status`** mientras exista lease ACTIVE, manteniendo `units.status` como única autoridad.

---

## 19. GATE

```text
F7: CLOSED / VERIFIED / MERGED
F8_SCOPE_PRE_KISS: APPROVED
F8_SPEC: APPROVED
F8_PLAN: APPROVED
F8_IMPLEMENTATION: COMPLETE
F8_QA: VERIFIED
F8_TWO_LAWS: PASS
F8_STATUS: CLOSED / VERIFIED / NOT MERGED
MERGE_AUTHORIZED: NO
NEXT: FINAL F8 FORENSIC REVIEW / MERGE GATE
```
