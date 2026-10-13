# F13 — PÓLIZAS + RECLAMACIONES — SPEC

**Proyecto:** AlquilaPro  
**Fecha:** 2026-10-13 (America/Bogota)  
**Rama de diseño:** `spec/f13-insurance-claims-20261013`  
**Estado:** `APPROVED DESIGN / USER SPEC REVIEW REQUIRED / IMPLEMENTATION BLOCKED ON F12 FINAL AUDIT + MERGE`  
**Fuentes canónicas:** SPEC/PLAN maestro V1.2, F9–F12 SPEC/PLAN aprobados y código real integrado hasta la última fase fusionada.

---

# 1. OBJETIVO

F13 introduce la **autoridad operativa de pólizas de seguro y reclamaciones** del arrendamiento, sin crear un mini-sistema asegurador, un segundo motor financiero, un motor de notificaciones ni un sistema documental genérico adelantado.

Debe permitir:

- registrar pólizas configurables por lease;
- registrar proveedor/aseguradora y producto sin codificar SURA como regla eterna;
- conservar número de póliza y vigencia;
- congelar coberturas configurables como snapshot;
- conservar documentos privados de póliza;
- versionar/renovar mediante nueva póliza + vínculo de supersesión;
- crear reclamaciones ligadas a la póliza exacta que las cubre;
- conservar fecha del incidente, descripción y monto reclamado opcional;
- gestionar radicado y estado humano/externo de reclamación;
- conservar checklist configurable como snapshot;
- conservar documentos privados de reclamación;
- exponer alertas visuales derivadas por vencimiento sin cron/email/WhatsApp;
- mostrar al tenant un resumen mínimo de su cobertura, sin abrirle operación de reclamaciones en V1.

F13 NO interpreta automáticamente condiciones de cobertura, NO decide si un siniestro está cubierto, NO presenta reclamaciones por API, NO calcula indemnizaciones y NO altera cartera/pagos.

---

# 2. DEPENDENCIAS Y GATE DE ARRANQUE

F13 depende funcionalmente de:

- F8 para `leases` y ownership tenant;
- F9/F11 para mantener separada la autoridad financiera;
- F12 como fase previa de obligaciones, para no solapar multas/servicios con seguros;
- F16 futuro para recordatorios/notificaciones;
- F18 futuro para sistema documental genérico.

La implementación productiva F13 queda bloqueada hasta que:

1. F12 termine `CLOSED / VERIFIED`;
2. ChatGPT/Sol audite su cierre real;
3. F12 sea fusionada en `main`;
4. se fije el SHA exacto de `main` que contiene F12;
5. no existan cambios pendientes que alteren `leases`, ownership tenant, permisos, private storage o la autoridad financiera que F13 debe respetar.

Esta rama documental NO es base autorizada de implementación.

La implementación F13 debe partir del `main` que contenga F12 final auditada y fusionada.

---

# 3. LEYES SUPREMAS

## 3.1 KISS

F13 debe resolver pólizas + reclamaciones con el mínimo razonable de piezas:

- exactamente dos tablas F13;
- una sola clase de dominio F13;
- cero provider adapters;
- cero APIs de aseguradora;
- cero motor documental propio;
- cero motor de alertas propio;
- cero integración financiera automática;
- SSR y auth existentes;
- estados derivados donde sea posible y persistidos solo cuando representen un hecho externo/humano no derivable.

## 3.2 CERO RUIDO / CONVERGENCIA

Orden obligatorio:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

F13 no debe crear anticipadamente:

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

Coberturas, checklist y metadatos de documentos son snapshots configurables con ciclo subordinado a póliza/reclamación; no justifican tablas independientes en V1.

## 3.3 ANTIPARCHES

Ante defectos:

`REPRODUCE → ROOT CAUSE → OWNING AUTHORITY → CONVERGE/SIMPLIFY → RED → MINIMAL GREEN → REMOVE SUPERSEDED LOGIC → REGRESSION`

No codificar excepciones por aseguradora. Si una regla cambia entre proveedores, debe expresarse como datos/configuración o quedar como decisión humana.

---

# 4. PRE-KISS — DECISIONES ESTRUCTURALES

## 4.1 Dos tablas exactas

F13 añade:

```text
insurance_policies
insurance_claims
```

Razón: póliza y reclamación tienen ciclos de vida distintos y una póliza puede tener múltiples reclamaciones.

No fusionarlas en una sola tabla.

No crear tablas subordinadas de cobertura/checklist/documentos todavía.

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
- validar transiciones de reclamación;
- gestionar checklist snapshot;
- gestionar documentos privados de reclamación;
- producir proyecciones admin/tenant;
- producir alertas derivadas por fechas.

No crear `PolicyService` + `ClaimService` separados en V1.

## 4.3 Una sola autoridad financiera

F13 NO:

- crea cargos;
- modifica cargos;
- crea payments;
- asigna allocations;
- cambia outstanding;
- registra indemnizaciones como pago del tenant;
- calcula deducibles financieros aplicables al tenant.

F9/F11 siguen siendo la única autoridad financiera.

`in_claim` puede ser una futura proyección financiera, pero F13 V1 NO crea `insurance_claim_charges` ni persiste estado `in_claim` en `charges`.

---

# 5. PRESUPUESTO ARQUITECTÓNICO F13

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

`insurance.view/manage` se justifican porque pólizas/reclamaciones contienen información y operación distinta de cartera/contratos y pueden delegarse de forma independiente.

Owner conserva bypass existente.

Tenant NO usa permisos staff; usa ownership de lease.

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
- reminder fields;
- notification fields;
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

Los índices necesarios por FK se resuelven según engine real; no añadir índices decorativos por cada fecha/actor.

`policy_number` NO se fuerza globalmente UNIQUE: una renovación/proveedor puede conservar o reutilizar identificadores y la aplicación no debe inventar una garantía universal inexistente.

---

# 7. VIGENCIA DE PÓLIZA

Reglas:

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

Alertas visuales derivadas pueden mostrar:

- inicia en N días;
- vence en N días;
- venció hace N días.

F13 no envía recordatorios.

---

# 8. COBERTURAS — SNAPSHOT CONFIGURABLE

`coverage_snapshot_json` es JSON UTF-8 validado por `InsuranceService`, persistido en LONGTEXT para no introducir dependencia de tipo JSON del engine.

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
- máximo razonable congelado en PLAN/test para evitar payload abusivo;
- `name` obligatorio y no vacío;
- nombres duplicados normalizados dentro de la misma póliza se rechazan;
- importes, si existen, > 0 y máximo 2 decimales;
- `notes` bounded;
- no provider-specific field names;
- no lógica de interpretación automática.

Cobertura es descripción operativa, no garantía de decisión de siniestro.

---

# 9. VERSIONADO / RENOVACIÓN

Una corrección o renovación material de póliza crea una NUEVA fila.

Opcionalmente:

```text
supersedes_policy_id = prior policy id
```

Reglas:

- la póliza previa debe existir;
- debe pertenecer al mismo lease;
- no self-reference;
- no ciclos de supersesión;
- la nueva póliza conserva su propio snapshot y documentos;
- crear nueva póliza NO cancela automáticamente la anterior;
- vigencias pueden solaparse legítimamente.

No tabla `policy_versions`.

`supersedes_policy_id` expresa lineage, no autoridad de vigencia.

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
6. no borrar documents;
7. no cancelar/rechazar claims automáticamente;
8. segundo intento falla cerrado preservando originales.

Una reclamación ligada a póliza posteriormente cancelada conserva su historia.

---

# 11. DOCUMENTOS DE PÓLIZA

F13 NO crea tabla `insurance_documents`.

`documents_json` almacena solo metadatos de archivos privados.

Forma V1:

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

Bytes viven fuera del webroot.

Formatos:

```text
image/png
image/jpeg
image/webp
application/pdf
```

Máximo por archivo:

```text
5 MiB
```

Reglas:

- MIME por bytes (`finfo`);
- random stored filename;
- `document_id` aleatorio estable para routing, distinto del stored filename;
- hash SHA256;
- size exacto;
- path seguro;
- no stored-name/path leak;
- número máximo de documentos por policy congelado en PLAN/test para evitar payload ilimitado.

## 11.1 Mutabilidad documental de póliza

Mientras la póliza no esté cancelada y NO tenga claims:

- staff puede añadir documentos;
- staff puede remover un documento cargado por error.

Una vez existe al menos una reclamación o la póliza fue cancelada:

- documentos de póliza quedan append-only;
- no reemplazar ni borrar evidencia existente.

Agregar documento nuevo sigue permitido si aporta evidencia legítima.

No filesystem transaction framework; usar orden seguro store→DB→cleanup ante fallo.

---

# 12. INMUTABILIDAD DEL NÚCLEO DE PÓLIZA

Después de crear póliza, los campos esenciales:

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

no se editan destructivamente en V1.

Corrección material:

```text
create new policy
→ optional supersedes_policy_id
```

`internal_notes` puede actualizarse mientras no se use para esconder condiciones contractuales que deberían vivir en coverage snapshot.

Esto preserva trazabilidad sin `policy_versions`.

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

`radicado` nullable y NO globalmente UNIQUE: la aplicación no asume semántica universal de todos los proveedores.

---

# 14. FECHA DEL INCIDENTE

Reglas:

- `incident_on` obligatoria;
- `incident_on <= today`;
- debe caer dentro de `coverage_start..coverage_end` de la póliza asociada.

Si el negocio requiere reportar un hecho fuera de vigencia para revisión humana, eso implica reabrir SPEC; V1 no inventa claims fuera del período contractual de póliza.

Una póliza cancelada puede conservar claims ya creados, pero no acepta nuevas reclamaciones después de `cancelled_at` en V1.

---

# 15. MONTO RECLAMADO

`claimed_amount` es opcional.

Si existe:

- > 0;
- máximo 2 decimales;
- dentro de `DECIMAL(12,2)`;
- boundary usa decimal-string;
- no float authority.

Este monto es informativo del reclamo ante aseguradora.

NO:

- reduce cartera;
- crea payment;
- crea crédito;
- representa automáticamente indemnización aprobada.

---

# 16. ESTADO DE RECLAMACIÓN

A diferencia del estado de póliza, claim `status` SÍ se persiste porque representa un hecho externo/humano no derivable solo de fechas.

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

No añadir `closed`, `archived`, `processing`, `pending_documents` ni otros alias sin nueva necesidad.

## 16.1 Transiciones permitidas

```text
draft → filed
filed → in_review
filed → approved|partial|rejected
in_review → approved|partial|rejected
```

No backward transitions.

No cambiar terminal a otro terminal.

No reapertura silenciosa.

---

# 17. RADICADO Y FECHAS DE CLAIM

Para `draft`:

```text
radicado = NULL permitido
filed_on = NULL permitido
resolved_on = NULL
```

Para `filed|in_review|approved|partial|rejected`:

```text
radicado requerido no vacío
filed_on requerido
```

Para terminal:

```text
resolved_on requerido
resolved_on >= filed_on
```

`filed_on` no puede ser anterior a `incident_on`.

Mientras claim no sea terminal, staff puede corregir `radicado`/`filed_on` si hubo error de digitación, conservando `updated_at`.

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

- `key` único dentro del claim;
- `label` no vacío;
- `required/completed` booleanos estrictos;
- checklist puede iniciar vacío si el proveedor no exige checklist conocido;
- staff puede actualizar `completed` mientras claim no sea terminal;
- una vez terminal, checklist se congela.

Checklist incompleto NO bloquea filing ni transición de estado automáticamente.

Razón: la aseguradora puede recibir el caso y solicitar faltantes posteriormente.

No tabla `claim_checklist_items`.

---

# 19. DOCUMENTOS DE RECLAMACIÓN

Mismo contrato privado de MIME/hash/size/document_id que documentos de póliza.

`documents_json` puede contener, por ejemplo:

- reclamación presentada;
- radicado;
- factura;
- fotografía;
- comunicación del proveedor;
- respuesta;
- soporte adicional.

No codificar categorías como enum rígido; `label` humano basta en V1.

## 19.1 Lifecycle documental del claim

Mientras `status=draft`:

- staff puede añadir documentos;
- staff puede remover documentos cargados por error.

Después de salir de draft:

- evidencia existente queda append-only;
- no reemplazar/borrar archivos ya vinculados;
- se pueden añadir documentos nuevos en `filed|in_review|terminal` para conservar respuestas/soportes recibidos.

Esto preserva trazabilidad sin tabla/versioning documental.

---

# 20. CREAR RECLAMACIÓN

Operación conceptual:

```text
InsuranceService::createClaim(policyId, incidentOn, description, claimedAmount?, checklist, documents?, actorUserId, today)
```

Debe:

- policy existente;
- policy no cancelada al momento de creación;
- incident dentro de vigencia;
- validar amount opcional;
- validar checklist snapshot;
- validar documentos opcionales;
- crear claim en `draft`;
- no crear cargo/payment;
- no enviar notificación;
- no evaluar cobertura automáticamente.

No limitar a una reclamación por póliza.

---

# 21. PRESENTAR / ACTUALIZAR ESTADO

Operación conceptual:

```text
InsuranceService::transitionClaim(claimId, targetStatus, radicado?, filedOn?, resolvedOn?, resolutionNotes?, actorUserId)
```

Reglas:

1. transaction;
2. claim `FOR UPDATE`;
3. validar transición exacta;
4. validar radicado/fechas según target;
5. preservar núcleo de incident/description/amount/policy;
6. actualizar estado y campos externos permitidos;
7. terminal queda inmutable para status/radicado/fechas/checklist;
8. documents pueden seguir append-only para soportes de resolución.

Segundo intento incompatible falla cerrado.

No status history table en V1.

---

# 22. RELACIÓN CON CARGOS / `in_claim`

F9 dejó `in_claim` como futura integración posible, pero F13 V1 NO materializa esa relación.

Razón:

- una reclamación puede abarcar varios períodos/cargos;
- puede incluir daños o conceptos sin cargo tenant;
- puede existir sin deuda tenant;
- forzar un FK simple `claim→charge` sería semánticamente falso;
- crear tabla bridge solo para anticipar `in_claim` viola Cero Ruido.

Por tanto:

```text
claim → policy → lease
```

es el único vínculo V1.

F9/F11 siguen mostrando cartera real sin modificar saldo por claim.

Si una necesidad futura demuestra relación muchos-a-muchos claim↔charges, se diseña entonces con SPEC propia.

---

# 23. ALERTAS

F13 produce solo señales derivadas de lectura:

```text
days_until_policy_start
days_until_policy_expiry
policy_expired
claim_status
```

Puede mostrar en UI:

- `Vence en N días`;
- `Vencida hace N días`;
- `Reclamación en revisión`.

F13 NO:

- crea reminder rule;
- ejecuta cron;
- envía email;
- envía WhatsApp;
- persiste `alert_sent_at`.

F16 podrá consumir fechas/eventos F13 posteriormente.

---

# 24. STORAGE / INTEGRIDAD DE DOCUMENTOS

Todos los bytes de póliza y claim:

- fuera webroot;
- filenames aleatorios;
- MIME real por bytes;
- size exacto;
- SHA256;
- path seguro;
- permisos restrictivos.

Lectura privada valida:

```text
metadata document_id exists
→ safe path
→ file exists
→ MIME real == stored MIME
→ size exacto
→ SHA256 exacto
```

Fallo:

```text
404
```

Headers:

```text
Content-Type: actual
Content-Length: exact
Cache-Control: private, no-store
X-Content-Type-Options: nosniff
```

No exponer `stored_name` ni path físico.

---

# 25. ATOMICIDAD DE DOCUMENTOS JSON + FILESYSTEM

Añadir documento:

```text
validate bytes
→ store new private file
→ build metadata
→ update JSON in DB
→ DB success
```

Si DB falla:

```text
delete new file
```

Remover documento cuando lifecycle lo permita:

```text
DB removes metadata
→ after DB success delete superseded physical file
```

Si delete físico posterior falla:

- DB no debe volver a apuntar al archivo;
- registrar/identificar orphan en QA;
- no inventar filesystem transaction framework.

Las mutaciones de `documents_json` deben bloquear su owner row `FOR UPDATE` para no perder uploads concurrentes.

---

# 26. CONCURRENCIA

Debe probarse al menos:

1. dos `cancelPolicy` concurrentes dejan un solo actor/time;
2. dos uploads concurrentes sobre misma policy no pierden metadata de uno;
3. dos uploads concurrentes sobre mismo claim no pierden metadata;
4. dos transiciones concurrentes de claim dejan una sola transición válida;
5. transición terminal concurrente contra actualización checklist no deja checklist modificado después de terminal;
6. dos renovaciones pueden coexistir; `supersedes_policy_id` no impone exclusión global;
7. no se crean ciclos de supersesión por carreras/actualización.

Autoridad de serialización:

- policy row `FOR UPDATE` para policy lifecycle/documents;
- claim row `FOR UPDATE` para claim lifecycle/checklist/documents.

No lock service.

---

# 27. ADMINISTRACIÓN / UX

F13 puede tener una superficie privada `Seguros` porque es una autoridad operativa distinta de cartera/contratos.

Staff `insurance.view` puede:

- listar/ver pólizas;
- ver vigencia/coberturas;
- ver documentos privados;
- listar/ver claims;
- ver checklist/radicado/estado.

Staff `insurance.manage` puede:

- crear póliza;
- añadir/remover documentos cuando lifecycle lo permita;
- cancelar póliza;
- crear claim;
- modificar draft;
- completar checklist;
- transicionar claim;
- añadir documentos de claim.

No dashboard decorativo.

Vista útil mínima:

- pólizas por lease;
- vigencia derivada;
- próximo vencimiento;
- claims abiertos/terminales;
- acceso al contexto de lease.

---

# 28. TENANT PORTAL

F13 V1 tenant es read-only y resumido.

Reutilizar `GET /tenant/leases/{id}`.

Tenant puede ver de su lease:

- provider_name;
- product_name;
- policy_number enmascarado;
- coverage_start/end;
- estado derivado de póliza;
- coberturas con `name`, `limit_amount`, `deductible_amount` y notas que sean explícitamente tenant-safe según datos configurados.

Tenant NO ve por defecto:

- `internal_notes`;
- documentos privados de póliza;
- claims;
- radicados;
- checklist;
- documentos de claim;
- resolution_notes;
- actor IDs.

F13 V1 NO permite al tenant:

- crear/cancelar póliza;
- presentar claim;
- subir documentos;
- transicionar estados.

Si más adelante se requiere portal de reclamación tenant, necesita diseño explícito porque abre un workflow y ACL documental propios.

---

# 29. ENMASCARADO DE NÚMERO DE PÓLIZA

Tenant nunca necesita el identificador completo por defecto.

Proyección V1:

- si longitud <= 4: mostrar `••••`;
- si longitud > 4: mostrar únicamente últimos 4 caracteres precedidos por `••••`.

Admin autorizado sí ve el valor completo.

No persistir valor enmascarado.

---

# 30. RUTAS CONCEPTUALES MÍNIMAS

Preferencia: un máximo de un route file F13, por ejemplo:

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

PLAN puede converger POSTs si reduce ruido sin volver ambiguo el boundary.

Tenant no necesita route file F13 separado si la proyección cabe en el route/detail de lease existente.

No rutas públicas.

---

# 31. AUTORIZACIÓN

Staff:

```text
insurance.view
insurance.manage
```

Owner conserva bypass existente.

Tenant:

```text
role=tenant + LeaseService::findForUser ownership
```

No reutilizar `finance.manage` ni `contracts.manage` como permiso genérico de seguros.

Global CSRF en mutaciones.

Forced-password gate permanece.

---

# 32. PRIVACIDAD / SEGURIDAD

Invariantes:

- documentos fuera webroot;
- staff auth + permiso antes de policy/claim/doc lookup;
- tenant ownership antes de proyección policy;
- tenant no recibe document routes F13 en V1;
- MIME por bytes;
- integrity check antes de servir;
- no path/stored-name leak;
- HTML escaped;
- CSRF en POST;
- basePath/subfolder;
- no payload JSON/document bytes en logs;
- no provider credentials;
- no API tokens;
- no automatic insurer decision.

---

# 33. NO INCLUIDO EN F13

No implementar:

- SURA hardcoded workflow;
- API SURA/aseguradoras;
- provider adapters;
- webhooks;
- OCR;
- automatic document classification;
- automatic coverage interpretation;
- automatic claim approval/rejection;
- automatic claim filing;
- claim↔charge relation;
- `in_claim` persisted on charges;
- insurance payments/indemnity accounting;
- tenant claim submission;
- cron;
- reminders;
- email;
- WhatsApp;
- generic document system F18;
- legal advice engine;
- accounting/e-invoicing.

---

# 34. ARQUITECTURA DE ARCHIVOS ESPERADA

F13 probablemente crea:

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

Producto esperado modifica, sujeto a preflight F12 final:

```text
app/Auth/Permissions.php
bootstrap/app.php
resources/views/apps.php or equivalent shell launcher
resources/views/tenant_lease_detail.php
app/Leases/routes.php
```

Una o dos vistas F13 enfocadas son aceptables si evitan mezclar póliza y claim en archivos gigantes; el PLAN congela paths exactos tras inspeccionar el repo integrado.

---

# 35. CRITERIOS DE ACEPTACIÓN

F13 no puede cerrar si falla cualquiera:

1. exactamente dos tablas F13;
2. una sola clase `InsuranceService`;
3. providers/productos son datos, no enums hardcoded;
4. policy status derivado, no persistido;
5. coverage snapshot validado/configurable;
6. renovación = nueva policy + optional supersedes;
7. no ciclos de supersesión;
8. policy core inmutable;
9. cancel fail-closed e idempotencia negativa;
10. documentos policy privados/integrity checked;
11. claim pertenece a policy exacta;
12. incident dentro de vigencia;
13. claim status allowlist exacta;
14. transiciones válidas y terminal inmutable;
15. radicado/fechas coherentes;
16. checklist snapshot validado y no bloquea filing automáticamente;
17. documentos claim privados y append-only después de draft;
18. concurrencia no pierde documentos ni terminalidad;
19. F13 no escribe cargos/payments/allocations;
20. no claim-charge bridge;
21. alertas solo derivadas, sin cron/notificaciones;
22. staff permissions exactas;
23. tenant ownership aislado;
24. tenant read-only y sin documentos/claims;
25. policy number tenant masked;
26. no public routes;
27. no provider integration/OCR/F18 adelantado;
28. PHP 8.3/8.4/8.5 PASS;
29. KISS PASS;
30. Cero Ruido PASS;
31. Patch stacking NONE.

---

# 36. GATE KISS / CERO RUIDO

Presupuesto final esperado:

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
- duplicated file helpers que deberían converger dentro de InsuranceService;
- persisted policy status derivable;
- claim aliases de estado;
- claim-charge wiring especulativo;
- reminder/email placeholders;
- F18 document abstractions anticipadas.

---

# 37. AUDITORÍA DE SPEC — 10 PASADAS

## Pasada 1 — Alcance/roadmap

Póliza configurable, documentos, coberturas, fechas, alertas derivadas, reclamación, radicado y checklist están cubiertos. — PASS.

## Pasada 2 — KISS

Dos tablas, una clase, máximo un route file, cero adapters/engines paralelos. — PASS.

## Pasada 3 — Cero Ruido

Sin coverage/checklist/document/provider/history/bridge tables; snapshots subordinados permanecen JSON validado. — PASS.

## Pasada 4 — Configurabilidad/provider neutrality

Provider/product son datos; no SURA hardcode; no reglas eternas por proveedor. — PASS.

## Pasada 5 — Versionado/vigencia

Renovación crea nueva fila; supersedes expresa lineage sin auto-cancelación; policy status derivado. — PASS.

## Pasada 6 — Claims/trazabilidad

Estado persistido solo porque es hecho externo; transiciones forward-only; radicado/fechas/terminalidad coherentes. — PASS.

## Pasada 7 — Documentos/privacidad

Metadatos JSON + bytes privados, document_id estable, MIME/hash/size, lifecycle append-only tras evidencia externa. — PASS.

## Pasada 8 — Seguridad/tenant

Dos permisos staff, tenant ownership-first y read-only, policy number masked, cero documents/claims al tenant V1. — PASS.

## Pasada 9 — Finanzas/alertas/futuro

Sin claim-charge bridge, sin modificar saldo, sin cron/email/WhatsApp, sin F18 anticipado. — PASS.

## Pasada 10 — Concurrencia/antiparches

Locks por owner row, no lost-update documental, terminal races cerradas, sin provider exceptions ni patch stacking. — PASS.

---

# 38. GATE DE DOCUMENTO

```text
F13_PRE_KISS: APPROVED BY USER
F13_DESIGN: APPROVED BY USER
F13_SPEC: USER REVIEW REQUIRED
F13_PLAN: NOT WRITTEN
F13_IMPLEMENTATION: BLOCKED ON F12 FINAL AUDIT + MERGE
MERGE_AUTHORIZED: NO
```

Siguiente etapa tras aprobación explícita de esta SPEC:

```text
WRITE F13 IMPLEMENTATION PLAN
```

No implementar producto desde esta rama documental.
