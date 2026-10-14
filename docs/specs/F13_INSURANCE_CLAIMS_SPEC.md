# F13 — PÓLIZAS + RECLAMACIONES — SPEC

- **Proyecto:** AlquilaPro
- **Fecha:** 2026-10-13 (America/Bogota)
- **Rama de diseño:** `spec/f13-insurance-claims-20261013`
- **Estado:** `IMPLEMENTED / FOCUSED QA PASS / PR CI PENDING`
**Fuentes canónicas:** SPEC/PLAN maestro V1.2, F9–F12 SPEC/PLAN aprobados y código real integrado hasta la última fase fusionada.

---

# 1. OBJETIVO

F13 introduce la **autoridad operativa de pólizas de seguro y reclamaciones** del arrendamiento, sin crear un mini-sistema asegurador, un segundo motor financiero, un motor de notificaciones ni un sistema documental genérico adelantado.

Debe permitir:

- registrar pólizas configurables por lease;
- registrar aseguradora/proveedor y producto como datos, no como reglas hardcoded;
- conservar número de póliza y vigencia;
- congelar coberturas configurables como snapshot;
- conservar documentos privados de póliza;
- renovar/versionar creando una nueva póliza enlazada opcionalmente a la anterior;
- crear reclamaciones ligadas a la póliza exacta que las cubre;
- conservar incidente, descripción y monto reclamado opcional;
- gestionar presentación, radicado cuando exista y estado humano/externo;
- conservar checklist configurable como snapshot;
- conservar documentos privados de reclamación;
- exponer señales visuales derivadas por vencimiento sin cron/email/WhatsApp;
- mostrar al tenant un resumen mínimo de su póliza, sin abrirle reclamaciones ni documentos en V1.

F13 NO interpreta automáticamente cobertura, NO decide si un siniestro está cubierto, NO presenta reclamaciones por API, NO calcula indemnizaciones y NO altera cartera/pagos.

---

# 2. DEPENDENCIAS Y GATE DE ARRANQUE

F13 depende de:

- F8 para `leases` y ownership tenant;
- F9/F11 para mantener separada la autoridad financiera;
- F12 como fase previa de obligaciones, para no solapar servicios/multas con seguros;
- F16 futuro para recordatorios/notificaciones;
- F18 futuro para sistema documental genérico.

La implementación productiva F13 queda bloqueada hasta que:

1. F12 termine `CLOSED / VERIFIED`;
2. ChatGPT/Sol audite su cierre real;
3. F12 sea fusionada en `main`;
4. se fije el SHA exacto de `main` que contiene F12;
5. no existan cambios pendientes que alteren `leases`, ownership tenant, permisos, private storage o autoridad financiera.

Esta rama documental NO es base autorizada de implementación.

La implementación F13 debe partir del `main` que contenga F12 final auditada y fusionada.

### Excepción del batch autorizada por el usuario

El prompt maestro F12→F13→F14 autoriza explícitamente ejecutar F13 sin fusionar F12, en ramas apiladas y tomando como base el head de cierre F12 validado `ef0b6c23d50fd8dcc38a2880bbf65aff6553b4d6`. Esta regla de ejecución prevalece para este batch; no se realizó ningún merge.

---

# 3. LEYES SUPREMAS

## 3.1 KISS

F13 debe resolver pólizas + reclamaciones con el mínimo razonable de piezas:

- exactamente dos tablas F13;
- una sola clase de dominio F13;
- máximo un route file F13;
- cero provider adapters;
- cero APIs de aseguradora;
- cero motor documental propio;
- cero motor de alertas propio;
- cero integración financiera automática;
- SSR/auth existentes;
- estado derivado cuando sea posible y persistido solo cuando represente un hecho externo/humano no derivable.

## 3.2 CERO RUIDO / CONVERGENCIA

Orden obligatorio:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

No crear anticipadamente:

- `insurance_coverages`;
- `claim_checklist_items`;
- `insurance_documents`;
- `insurance_providers`;
- `policy_versions`;
- `claim_charge_links`;
- `claim_payments`;
- `claim_notifications`;
- `claim_status_history`;
- provider SDK/adapters;
- webhook/API integration;
- OCR;
- cron/reminder engine;
- F18 document authority.

Coberturas, checklist y metadatos de documentos son snapshots configurables subordinados a póliza/reclamación; no justifican tablas propias en V1.

## 3.3 ANTIPARCHES

Ante defectos:

`REPRODUCE → ROOT CAUSE → OWNING AUTHORITY → CONVERGE/SIMPLIFY → RED → MINIMAL GREEN → REMOVE SUPERSEDED LOGIC → REGRESSION`

No crear excepciones por aseguradora. Si una regla cambia entre proveedores, debe expresarse como datos/configuración o quedar como decisión humana.

---

# 4. PRE-KISS — DECISIONES ESTRUCTURALES

## 4.1 Dos tablas exactas

F13 añade:

```text
insurance_policies
insurance_claims
```

Póliza y reclamación tienen ciclos distintos y una póliza puede tener múltiples reclamaciones.

No fusionarlas.

No añadir tablas subordinadas de cobertura/checklist/documentos.

## 4.2 Una sola clase de dominio

F13 añade:

```text
InsuranceService
```

Responsabilidades:

- crear/listar/consultar/cancelar pólizas;
- validar vigencia y snapshots;
- gestionar lineage de renovación/supersesión;
- gestionar documentos privados de póliza;
- crear/consultar/actualizar reclamaciones;
- validar transiciones;
- gestionar checklist snapshot;
- gestionar documentos privados de reclamación;
- producir proyecciones admin/tenant;
- producir señales derivadas por fechas.

No crear `PolicyService` + `ClaimService` separados en V1.

## 4.3 Una sola autoridad financiera

F13 NO:

- crea/modifica cargos;
- crea payments;
- asigna allocations;
- cambia outstanding;
- registra indemnización como pago del tenant;
- calcula deducibles financieros aplicables al tenant.

F9/F11 siguen siendo la única autoridad financiera.

`in_claim` puede ser una futura proyección, pero F13 V1 NO crea `insurance_claim_charges` ni persiste `in_claim` en `charges`.

---

# 5. PRESUPUESTO ARQUITECTÓNICO

```text
NEW_TABLES: 2
  insurance_policies
  insurance_claims

NEW_DOMAIN_CLASSES: 1
  InsuranceService

NEW_ROUTE_FILES: 1 maximum

NEW_PERMISSIONS: 2
  insurance.view
  insurance.manage

NEW_PUBLIC_ROUTES: 0
NEW_RUNTIME_DEPENDENCIES: 0
NEW_PROVIDER_TABLES: 0
NEW_COVERAGE_TABLES: 0
NEW_CHECKLIST_TABLES: 0
NEW_DOCUMENT_TABLES: 0
NEW_NOTIFICATION_TABLES: 0
NEW_PAYMENT_TABLES: 0
NEW_CHARGE_LINK_TABLES: 0
NEW_STATUS_HISTORY_TABLES: 0
NEW_CRON_JOBS: 0
NEW_PROVIDER_INTEGRATIONS: 0
```

`insurance.view/manage` se justifican porque pólizas/reclamaciones son una autoridad operativa distinta de cartera/contratos.

Owner conserva bypass existente.

Tenant usa ownership de lease, no permisos staff.

---

# 6. MODELO `insurance_policies`

Esquema conceptual exacto:

```text
id BIGINT UNSIGNED PK AUTO_INCREMENT
lease_id BIGINT UNSIGNED NOT NULL
provider_name VARCHAR(190) NOT NULL
product_name VARCHAR(190) NOT NULL
policy_number VARCHAR(190) NOT NULL
coverage_start DATE NOT NULL
coverage_end DATE NOT NULL
coverage_snapshot_json LONGTEXT NOT NULL
documents_json LONGTEXT NOT NULL
supersedes_policy_id BIGINT UNSIGNED NULL
internal_notes VARCHAR(1000) NULL
created_by_user_id BIGINT UNSIGNED NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NULL
cancelled_by_user_id BIGINT UNSIGNED NULL
cancelled_at DATETIME NULL
cancel_reason VARCHAR(500) NULL
```

No añadir:

- `status`;
- provider enum;
- SURA-specific fields;
- premium/payment accounting;
- reminder/notification fields;
- `current_version`;
- balance fields.

## 6.1 FK e índices

FK:

```text
lease_id → leases.id ON DELETE RESTRICT
supersedes_policy_id → insurance_policies.id ON DELETE RESTRICT
created_by_user_id → users.id ON DELETE SET NULL
cancelled_by_user_id → users.id ON DELETE SET NULL
```

Índices mínimos:

```text
KEY insurance_policies_lease_dates_index (lease_id, coverage_start, coverage_end)
KEY insurance_policies_policy_number_index (policy_number)
```

Los índices exigidos por FK se resuelven según engine real; no añadir índices decorativos por cada fecha/actor.

`policy_number` NO se fuerza globalmente UNIQUE.

---

# 7. VIGENCIA DE PÓLIZA

Regla:

```text
coverage_start <= coverage_end
```

Estado derivado usando `today` testeable:

```text
cancelled:
  cancelled_at IS NOT NULL

upcoming:
  cancelled_at IS NULL
  AND today < coverage_start

active:
  cancelled_at IS NULL
  AND coverage_start <= today <= coverage_end

expired:
  cancelled_at IS NULL
  AND today > coverage_end
```

No persistir `status` ni `days_until_expiry`.

UI puede mostrar `inicia en N días`, `vence en N días` o `venció hace N días`.

F13 no envía recordatorios.

---

# 8. COBERTURAS — SNAPSHOT CONFIGURABLE

`coverage_snapshot_json` es JSON UTF-8 validado por `InsuranceService`, persistido en LONGTEXT para no depender del tipo JSON del engine.

Forma V1:

```text
list<{
  name: string,
  limit_amount: decimal-string|null,
  deductible_amount: decimal-string|null,
  notes: string|null
}>
```

Reglas:

- lista no vacía;
- `name` obligatorio, trim y longitud <= 190;
- nombres duplicados normalizados dentro de la misma póliza se rechazan;
- importes, si existen, > 0, máximo 2 decimales y dentro de `DECIMAL(12,2)`;
- `notes` nullable, longitud <= 500;
- no provider-specific field names;
- no lógica de interpretación automática.

No existe un límite de cantidad de coberturas codificado por producto; el boundary HTTP general y el tamaño razonable del payload protegen contra abuso.

Cobertura es descripción operativa, no garantía de decisión de siniestro.

---

# 9. VERSIONADO / RENOVACIÓN

Una corrección o renovación material crea una NUEVA fila.

Opcionalmente:

```text
supersedes_policy_id = prior policy id
```

Reglas:

- la póliza previa debe existir;
- debe pertenecer al mismo lease;
- la nueva fila solo puede apuntar a una fila ya existente;
- `supersedes_policy_id` no se edita después de crear la póliza;
- la nueva póliza conserva snapshot/documentos propios;
- crear nueva póliza NO cancela automáticamente la anterior;
- vigencias pueden solaparse legítimamente.

Dado que la relación se fija solo al crear una fila nueva apuntando a una fila previa existente y luego queda inmutable, no se introduce arquitectura adicional de detección de ciclos.

No tabla `policy_versions`.

---

# 10. CANCELACIÓN DE PÓLIZA

No DELETE operativo.

Operación conceptual:

```text
InsuranceService::cancelPolicy(policyId, actorUserId, reason, now)
```

Reglas:

1. transaction;
2. policy `FOR UPDATE`;
3. require `cancelled_at IS NULL`;
4. reason obligatoria;
5. set actor/time/reason una vez;
6. no borrar documentos;
7. no cancelar/rechazar claims automáticamente;
8. segundo intento falla cerrado preservando originales.

`cancelled_at` registra el momento administrativo de cancelación conocido por el sistema.

Una póliza cancelada puede conservar y recibir una reclamación histórica si el incidente ocurrió:

- dentro de `coverage_start..coverage_end`; y
- en fecha igual o anterior a la fecha local de `cancelled_at`.

No puede recibir una reclamación cuyo incidente sea posterior a la cancelación registrada.

---

# 11. DOCUMENTOS DE PÓLIZA

F13 NO crea `insurance_documents`.

`documents_json` contiene solo metadatos:

```text
list<{
  document_id: 32hex,
  label: string,
  stored_name: string,
  mime: string,
  sha256: 64hex,
  size_bytes: int
}>
```

Bytes fuera webroot.

Formatos:

```text
image/png
image/jpeg
image/webp
application/pdf
```

Máximo por archivo: `5 MiB`.

Reglas:

- `label` trim, 1..190 caracteres;
- MIME por bytes (`finfo`);
- stored filename aleatorio;
- `document_id` aleatorio estable distinto del stored filename;
- SHA256 y size exacto;
- path seguro;
- no stored-name/path leak.

No se codifica un número máximo artificial de documentos por póliza; el acceso es staff-only y cada archivo ya tiene límite de tamaño.

## 11.1 Lifecycle documental de póliza

Mientras la póliza no esté cancelada y NO tenga claims:

- staff puede añadir documentos;
- staff puede remover un documento cargado por error.

Una vez existe al menos una reclamación o la póliza fue cancelada:

- documentos existentes quedan append-only;
- no reemplazar ni borrar evidencia existente;
- se pueden añadir documentos nuevos legítimos.

Las mutaciones bloquean la policy row para evitar lost updates.

---

# 12. INMUTABILIDAD DEL NÚCLEO DE PÓLIZA

Después de crear póliza, son inmutables en V1:

```text
lease_id
provider_name
product_name
policy_number
coverage_start
coverage_end
coverage_snapshot_json
supersedes_policy_id
```

Corrección material:

```text
create new policy
→ optional supersedes_policy_id
```

`internal_notes` puede actualizarse, pero no debe usarse para esconder condiciones que pertenecen a coverage snapshot.

---

# 13. MODELO `insurance_claims`

Esquema conceptual exacto:

```text
id BIGINT UNSIGNED PK AUTO_INCREMENT
policy_id BIGINT UNSIGNED NOT NULL
incident_on DATE NOT NULL
description VARCHAR(1000) NOT NULL
claimed_amount DECIMAL(12,2) NULL
status VARCHAR(32) NOT NULL
radicado VARCHAR(190) NULL
filed_on DATE NULL
resolved_on DATE NULL
resolution_notes VARCHAR(1000) NULL
checklist_snapshot_json LONGTEXT NOT NULL
documents_json LONGTEXT NOT NULL
created_by_user_id BIGINT UNSIGNED NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NULL
```

No añadir:

- `charge_id`;
- `payment_id`;
- `approved_amount`;
- provider transaction id;
- webhook fields;
- automatic decision score;
- reminder fields;
- status history table.

## 13.1 FK e índices

FK:

```text
policy_id → insurance_policies.id ON DELETE RESTRICT
created_by_user_id → users.id ON DELETE SET NULL
```

Índices mínimos:

```text
KEY insurance_claims_policy_status_index (policy_id, status)
KEY insurance_claims_radicado_index (radicado)
```

`radicado` nullable y NO globalmente UNIQUE.

---

# 14. FECHA DEL INCIDENTE

Reglas:

- `incident_on` obligatoria;
- `incident_on <= today`;
- debe caer dentro de `coverage_start..coverage_end` de la póliza;
- si policy está cancelada, `incident_on` debe ser <= fecha local de `cancelled_at`.

Crear claim debe bloquear la policy row para coordinar correctamente con cancelación y con el freeze documental que ocurre cuando nace la primera reclamación.

No limitar a una reclamación por póliza.

---

# 15. MONTO RECLAMADO

`claimed_amount` es opcional.

Si existe:

- > 0;
- máximo 2 decimales;
- dentro de `DECIMAL(12,2)`;
- boundary usa decimal-string;
- no float authority.

Es un dato del reclamo ante aseguradora.

NO reduce cartera, NO crea payment/crédito y NO representa automáticamente indemnización aprobada.

---

# 16. ESTADO DE RECLAMACIÓN

Claim `status` SÍ se persiste porque representa un hecho externo/humano no derivable solo de fechas.

Allowlist exacta:

```text
draft
filed
in_review
approved
partial
rejected
```

Terminales:

```text
approved
partial
rejected
```

No añadir aliases como `closed`, `archived`, `processing`, `pending_documents`.

## 16.1 Transiciones permitidas

```text
draft → filed
filed → in_review
filed → approved|partial|rejected
in_review → approved|partial|rejected
```

No backward transitions.

No terminal→terminal.

No reapertura silenciosa.

---

# 17. RADICADO Y FECHAS DE CLAIM

Para `draft`:

```text
filed_on = NULL
resolved_on = NULL
radicado puede ser NULL
```

Para `filed|in_review|approved|partial|rejected`:

```text
filed_on requerido
incident_on <= filed_on <= today
```

`radicado` es opcional en cualquier estado porque algunos proveedores lo entregan después o usan otro identificador. Si existe debe ser trim no vacío y <=190 caracteres.

Para terminal:

```text
resolved_on requerido
filed_on <= resolved_on <= today
```

Mientras claim no sea terminal, staff puede corregir `radicado` y `filed_on` bajo lock si hubo error de digitación.

Una vez terminal, radicado y fechas quedan inmutables.

---

# 18. CHECKLIST — SNAPSHOT CONFIGURABLE

`checklist_snapshot_json` es LONGTEXT con JSON validado.

Forma V1:

```text
list<{
  key: string,
  label: string,
  required: bool,
  completed: bool
}>
```

Reglas:

- `key` trim, 1..64, allowlist `[a-z0-9_-]`;
- `key` único dentro del claim;
- `label` trim, 1..190;
- `required/completed` booleanos estrictos;
- checklist puede iniciar vacío;
- staff puede actualizar `completed` mientras claim no sea terminal;
- terminal congela checklist.

Checklist incompleto NO bloquea filing ni transición automáticamente.

No tabla `claim_checklist_items`.

---

# 19. DOCUMENTOS DE RECLAMACIÓN

Mismo contrato privado de MIME/hash/size/document_id que policy documents.

`documents_json` puede representar reclamación presentada, radicado, factura, foto, comunicación, respuesta o soporte adicional mediante `label` humano; no enum rígido de categorías.

No se codifica máximo artificial de cantidad; cada archivo mantiene límite de `5 MiB`.

## 19.1 Lifecycle documental del claim

Mientras `status=draft`:

- staff puede añadir;
- staff puede remover documentos cargados por error.

Después de salir de draft:

- evidencia existente queda append-only;
- no reemplazar/borrar archivo ya vinculado;
- se pueden añadir nuevos documentos en `filed|in_review|terminal`.

Las mutaciones bloquean claim row para evitar lost updates.

---

# 20. CREAR / EDITAR DRAFT CLAIM

Operación conceptual:

```text
InsuranceService::createClaim(policyId, incidentOn, description, claimedAmount?, checklist, documents?, actorUserId, today)
```

Debe:

- lock policy;
- validar policy/lease y vigencia;
- permitir policy cancelada solo para incidente histórico válido según sección 14;
- validar amount opcional;
- validar checklist;
- validar documentos;
- insertar `draft`;
- no crear cargo/payment;
- no notificar;
- no evaluar cobertura automáticamente.

Mientras `status=draft`, staff puede corregir:

- incident_on;
- description;
- claimed_amount;
- checklist;
- documentos según lifecycle.

Cada corrección revalida vigencia y reglas completas.

---

# 21. PRESENTAR / TRANSICIONAR CLAIM

Operación conceptual:

```text
InsuranceService::transitionClaim(claimId, targetStatus, filedOn?, radicado?, resolvedOn?, resolutionNotes?, actorUserId, today)
```

Reglas:

1. transaction;
2. claim `FOR UPDATE`;
3. validar transición exacta;
4. validar fechas y radicado opcional;
5. preservar policy/incident/description/amount;
6. actualizar estado y campos externos permitidos;
7. terminal congela status/radicado/fechas/checklist;
8. documentos siguen append-only para soportes de resolución.

Segundo intento incompatible falla cerrado.

No status history table en V1.

---

# 22. RELACIÓN CON CARGOS / `in_claim`

F13 V1 NO materializa relación claim↔charge.

Razón:

- un claim puede abarcar varios períodos/cargos;
- puede incluir daños sin cargo tenant;
- puede existir sin deuda tenant;
- FK simple sería falso;
- bridge anticipado viola Cero Ruido.

Por tanto:

```text
claim → policy → lease
```

es el único vínculo V1.

F9/F11 siguen mostrando cartera real sin modificar saldo por claim.

---

# 23. ALERTAS

F13 produce solo señales derivadas:

```text
days_until_policy_start
days_until_policy_expiry
policy_expired
claim_status
```

Puede mostrar `Vence en N días`, `Vencida hace N días`, `Reclamación en revisión`.

F13 NO crea reminder rule, cron, email, WhatsApp ni `alert_sent_at`.

F16 podrá consumir estas fechas/eventos luego.

---

# 24. STORAGE / INTEGRIDAD

Todos los bytes:

- fuera webroot;
- filename aleatorio;
- MIME por bytes;
- size exacto;
- SHA256;
- path seguro;
- permisos restrictivos.

Lectura:

```text
document_id exists
→ safe path
→ file exists
→ real MIME == stored MIME
→ exact size
→ exact SHA256
```

Fallo: `404`.

Headers:

```text
Content-Type: actual
Content-Length: exact
Cache-Control: private, no-store
X-Content-Type-Options: nosniff
```

No exponer stored filename/path.

---

# 25. ATOMICIDAD JSON + FILESYSTEM

Añadir documento:

```text
validate bytes
→ store new private file
→ lock owner row
→ re-read current documents_json
→ append metadata
→ DB update
→ success
```

Si DB falla: borrar solo el archivo nuevo.

Remover documento cuando lifecycle permita:

```text
lock owner row
→ re-read JSON
→ remove metadata
→ DB success
→ delete old physical file
```

Si delete físico posterior falla:

- DB no vuelve a apuntar al archivo;
- QA identifica orphan;
- no filesystem transaction framework.

---

# 26. CONCURRENCIA

Debe probarse:

1. dos `cancelPolicy` concurrentes dejan un solo actor/time;
2. dos uploads concurrentes sobre misma policy conservan ambas metadata;
3. claim creation concurrente con document removal de policy no permite borrar evidencia después de existir claim;
4. dos uploads concurrentes sobre mismo claim conservan ambas metadata;
5. dos transiciones concurrentes dejan una sola transición válida;
6. terminal transition concurrente con checklist update no modifica checklist después de terminal;
7. cancelación concurrente con createClaim revalida incident date contra el estado final de policy.

Locks:

- policy row `FOR UPDATE` para policy lifecycle/docs/createClaim;
- claim row `FOR UPDATE` para claim lifecycle/checklist/docs.

No lock service.

---

# 27. ADMINISTRACIÓN / UX

F13 puede tener superficie privada `Seguros`.

Staff `insurance.view` puede:

- listar/ver pólizas;
- ver vigencia/coberturas;
- ver documentos;
- listar/ver claims;
- ver checklist/radicado/estado.

Staff `insurance.manage` puede:

- crear póliza;
- añadir/remover documentos cuando lifecycle permita;
- cancelar póliza;
- crear/modificar draft claim;
- completar checklist;
- corregir referencia mientras no terminal;
- transicionar claim;
- añadir documentos.

No dashboard decorativo.

---

# 28. TENANT PORTAL

F13 V1 tenant es read-only y resumido.

Reutilizar `GET /tenant/leases/{id}`.

Tenant puede ver:

- provider_name;
- product_name;
- policy_number enmascarado;
- coverage_start/end;
- estado derivado;
- coberturas: `name`, `limit_amount`, `deductible_amount`.

Tenant NO ve:

- coverage `notes`;
- internal_notes;
- documentos de póliza;
- claims/radicados/checklist;
- documentos claim;
- resolution_notes;
- actor IDs.

Tenant NO puede crear/cancelar póliza, presentar claim, subir documentos ni transicionar estados.

---

# 29. ENMASCARADO DE PÓLIZA

Proyección tenant:

- longitud <= 4 → `••••`;
- longitud > 4 → `••••` + últimos 4 caracteres.

Admin autorizado ve valor completo.

No persistir masked value.

---

# 30. RUTAS CONCEPTUALES

Máximo un route file F13, preferencia:

```text
app/Insurance/routes.php
```

Staff conceptual:

```text
GET  /admin/insurance
POST /admin/leases/{id}/insurance-policies
POST /admin/insurance/policies/{id}/cancel
POST /admin/insurance/policies/{id}/documents
POST /admin/insurance/policies/{id}/documents/{documentId}/remove
GET  /admin/insurance/policies/{id}/documents/{documentId}
POST /admin/insurance/policies/{id}/claims
POST /admin/insurance/claims/{id}
POST /admin/insurance/claims/{id}/transition
POST /admin/insurance/claims/{id}/documents
POST /admin/insurance/claims/{id}/documents/{documentId}/remove
GET  /admin/insurance/claims/{id}/documents/{documentId}
```

PLAN puede converger POSTs si reduce ruido.

Tenant no necesita route file F13 separado si la proyección cabe en lease detail.

No rutas públicas.

---

# 31. AUTORIZACIÓN

Staff:

```text
insurance.view
insurance.manage
```

Owner conserva bypass.

Tenant:

```text
role=tenant + LeaseService::findForUser ownership
```

No reutilizar `finance.manage` ni `contracts.manage` como permiso genérico de seguros.

CSRF global en mutaciones. Forced-password gate permanece.

---

# 32. PRIVACIDAD / SEGURIDAD

Invariantes:

- documentos fuera webroot;
- staff auth + permiso antes de policy/claim/doc lookup;
- tenant ownership antes de proyección policy;
- tenant no tiene document routes F13;
- MIME por bytes e integrity check;
- no path/stored-name leak;
- HTML escaped;
- CSRF;
- basePath/subfolder;
- no payload JSON/document bytes en logs;
- no provider credentials/API tokens;
- no automatic insurer decision.

---

# 33. NO INCLUIDO

No implementar:

- SURA hardcoded workflow;
- API/provider adapters/webhooks;
- OCR/document classification;
- automatic coverage interpretation;
- automatic claim approval/rejection/filing;
- claim↔charge relation;
- `in_claim` persisted on charges;
- indemnity accounting;
- tenant claim submission;
- cron/reminders/email/WhatsApp;
- generic document system F18;
- legal advice engine;
- accounting/e-invoicing.

---

# 34. ARQUITECTURA DE ARCHIVOS ESPERADA

Probables nuevos archivos:

```text
database/migrations/013_create_insurance.sql
app/Insurance/InsuranceService.php
app/Insurance/routes.php
```

Tests esperados:

```text
tests/Integration/Insurance/InsuranceSchemaTest.php
tests/Integration/Insurance/InsurancePolicyServiceTest.php
tests/Integration/Insurance/InsuranceClaimServiceTest.php
tests/Integration/Insurance/InsuranceRoutesTest.php
tests/Integration/Insurance/InsuranceSecurityRegressionTest.php
```

Probables modificaciones, sujetas a preflight final:

```text
app/Auth/Permissions.php
bootstrap/app.php
resources/views/apps.php or shell equivalent
resources/views/tenant_lease_detail.php
app/Leases/routes.php
```

Una o dos vistas F13 enfocadas son aceptables si evitan archivos gigantes; PLAN congela paths exactos.

---

# 35. CRITERIOS DE ACEPTACIÓN

F13 no cierra si falla cualquiera:

1. exactamente dos tablas F13;
2. una sola `InsuranceService`;
3. provider/product son datos, no enums hardcoded;
4. policy status derivado;
5. coverage snapshot validado;
6. renovación = nueva policy + optional supersedes;
7. policy core inmutable;
8. cancel fail-closed;
9. historical claim permitido tras cancelación solo si incident fue previo/valido;
10. policy documents privados/integrity checked;
11. claim ligado a policy exacta;
12. incident dentro de vigencia;
13. claim status allowlist/transiciones exactas;
14. terminal inmutable;
15. filed/resolved dates no futuras;
16. radicado opcional/provider-neutral;
17. checklist validado y no bloquea filing automáticamente;
18. claim documents privados y append-only después de draft;
19. concurrencia no pierde documentos ni terminalidad;
20. F13 no escribe cargos/payments/allocations;
21. no claim-charge bridge;
22. alertas solo derivadas;
23. staff permissions exactas;
24. tenant ownership aislado;
25. tenant read-only, sin docs/claims/coverage notes;
26. policy number masked;
27. no public routes;
28. no provider integration/OCR/F18 adelantado;
29. PHP 8.3/8.4/8.5 PASS;
30. KISS PASS;
31. Cero Ruido PASS;
32. Patch stacking NONE.

---

# 36. GATE KISS / CERO RUIDO

```text
TABLES: 2
DOMAIN_CLASSES: 1
ROUTE_FILES: <=1
PERMISSIONS: 2
PUBLIC_ROUTES: 0
RUNTIME_DEPS: 0
COVERAGE_TABLES: 0
CHECKLIST_TABLES: 0
DOCUMENT_TABLES: 0
PROVIDER_TABLES: 0
NOTIFICATION_TABLES: 0
CHARGE_LINK_TABLES: 0
PROVIDER_INTEGRATIONS: 0
CRON: 0
```

Buscar antes de cierre:

- tablas paralelas;
- provider-specific branches;
- duplicated file helpers;
- persisted policy status;
- claim status aliases;
- claim-charge wiring;
- reminder/email placeholders;
- F18 abstractions anticipadas.

---

# 37. AUDITORÍA DE SPEC — 10 PASADAS

## Pasada 1 — Alcance/roadmap

Póliza configurable, documentos, coberturas, fechas, alertas derivadas, reclamación, radicado opcional y checklist cubiertos. — PASS.

## Pasada 2 — KISS

Dos tablas, una clase, máximo un route file, cero adapters/engines paralelos. — PASS.

## Pasada 3 — Cero Ruido

Sin coverage/checklist/document/provider/history/bridge tables; snapshots subordinados quedan JSON validado. — PASS.

## Pasada 4 — Provider neutrality

Provider/product como datos; radicado no obligatorio; no SURA hardcode ni reglas eternas. — PASS.

## Pasada 5 — Versionado/vigencia

Renovación crea fila nueva; supersedes apunta solo a fila previa e inmutable; sin arquitectura de ciclos innecesaria. — PASS.

## Pasada 6 — Claims/trazabilidad

Estado persistido solo por ser hecho externo; transiciones forward-only; fechas no futuras; historical claim tras cancelación tratado explícitamente. — PASS.

## Pasada 7 — Documentos/privacidad

Metadatos JSON + bytes privados, document_id estable, MIME/hash/size, lifecycle append-only tras evidencia externa y locks contra lost update. — PASS.

## Pasada 8 — Seguridad/tenant

Dos permisos staff; tenant ownership-first/read-only; policy masked; sin docs/claims/coverage notes. — PASS.

## Pasada 9 — Finanzas/alertas/futuro

Sin claim-charge bridge, sin saldo/pagos, sin cron/email/WhatsApp, sin F18 anticipado. — PASS.

## Pasada 10 — Concurrencia/antiparches

Locks por owner row; cancel/createClaim, docs y terminal races cerradas; sin provider exceptions ni patch stacking. — PASS.

---

# 38. GATE DE DOCUMENTO

```text
F13_PRE_KISS: APPROVED BY USER
F13_DESIGN: APPROVED BY USER
F13_SPEC: IMPLEMENTED / FOCUSED QA PASS
F13_PLAN: EXECUTED FROM AUTHORITY COMMIT 8aa68b7f1b8d46722764aabea8f72a5e9521d9a5
F13_IMPLEMENTATION: REVIEW-READY / NOT MERGED
F13_BASE_HEAD: ef0b6c23d50fd8dcc38a2880bbf65aff6553b4d6
F13_PRODUCT_HEAD: a0cce18f1cc2a765046c861e57f08a8e245721e8
F13_PR_CI: PENDING
MERGE_AUTHORIZED: NO
```

Siguiente etapa:

```text
DOCS-ONLY CLOSURE → PUSH → PR CI PHP 8.3/8.4/8.5
```

La implementación se realizó en `impl/f13-insurance-claims-20261014`, no en la rama documental.
