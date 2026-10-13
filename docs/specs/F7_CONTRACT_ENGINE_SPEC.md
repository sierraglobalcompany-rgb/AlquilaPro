# F7 — MOTOR DE CONTRATOS — SPEC ESPECÍFICA

**Fecha:** 2026-10-12 (America/Bogota)  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Fase:** F7 — Motor de contratos  
**Estado:** **SPEC ESPECÍFICA — APROBADA**  
**Base exacta:** `main @ 1b9ee85ebb224b310d4ab4f0c84a28628b86b747`  
**Branch:** `impl/f7-contracts-20261012`

---

# 0. AUTORIDAD Y CONTEXTO

Orden vigente:

`SPEC maestra V1.2 → PLAN maestro V1.2 → README → esta SPEC F7 → PLAN F7 → tests/CI → código real`.

F0–F6 están cerradas; F6 está **CLOSED / VERIFIED / MERGED**.

F7 reutiliza, sin duplicar:

- `users` + auth/sesión/CSRF/forced-password;
- `properties` y `units` como inventario único;
- `candidates` como candidatura única;
- `screening_processes` como estudio de aseguradora único.

No reabrir F1–F6 salvo evidencia concreta de regresión.

---

# 1. EVIDENCIA PREVIA — SPIKE PDF F7

Antes de esta SPEC se ejecutó un spike throwaway aprobado por el usuario.

- Rama: `spike/f7-dompdf-20261012`
- HEAD final: `352dd3abcec6d26644906ccc9247dc51d7545c18`
- Workflow: `F7 PDF Spike`
- Run final: `38339899173`
- Dompdf: `v3.1.6`
- PHP 8.3 / 8.4 / 8.5: PASS
- `composer check-platform-reqs`: PASS en las tres versiones
- PDF: 2 páginas, UTF-8 español, CSS, tabla, PNG local, salto, header/footer y numeración
- salida: `22583` bytes
- revisión visual: PASS
- `ext-dom`: requerida
- `ext-mbstring`: requerida y ya presente en Alquila Pro
- `ext-gd`: adoptada como requisito F7 para imágenes
- binarios externos: ninguno
- licencia observada: `LGPL-2.1-only`
- rama spike: NO MERGE a `main`.

Decisión congelada:

`dompdf/dompdf:^3.1.6`

No wkhtmltopdf, Chromium, Chrome headless, Node, Puppeteer ni otro ejecutable externo. Dompdf opera con recursos remotos desactivados.

---

# 2. OBJETIVO

Construir el motor administrativo mínimo para convertir un candidato con estudio aprobado en un contrato versionado, previsualizable, congelable y descargable en PDF, preservando candidato/unidad reales y sin crear todavía inquilino, lease ni portal contractual público.

Flujo F7:

`candidato + screening approved → draft → preview → immutable snapshot → PDF + SHA-256 → finalized`.

F7 **NO activa el arrendamiento**. Tenant, lease y ocupación operacional pertenecen a F8.

---

# 3. LEYES SUPREMAS F7

## LEY 1 — KISS

Máximo estructural F7:

- **3 tablas nuevas**;
- **2 clases de dominio nuevas**;
- **2 permisos nuevos**;
- **1 route file nuevo**;
- **1 dependencia runtime directa nueva**: Dompdf;
- **0 auth/login nuevos**;
- **0 repositories/interfaces/adapters/factories/DTOs**;
- **0 editor WYSIWYG / Word web**;
- **0 SPA**;
- **0 workers/queues/cron**;
- **0 firma electrónica**;
- **0 upload documental genérico**;
- **0 lease / tenant**.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`.

Reutilizar:

- `CandidateService`;
- `ScreeningService`;
- `PropertyService`;
- auth/session/forced-password;
- CSRF;
- `basePath`;
- shell visual F3;
- storage privado;
- SSR existente.

No crear fuentes paralelas para candidato, unidad, precio comercial, aseguradora, usuario o sesión.

---

# 4. ALCANCE

## 4.1 Incluye

- plantillas;
- versiones inmutables;
- plantilla en texto UTF-8, nunca HTML arbitrario;
- placeholders simples y allowlisted;
- cláusulas dentro del texto versionado;
- variables automáticas + manuales declaradas;
- draft contractual;
- canon/administración finales;
- fecha inicio, fecha fin y duración textual explícita;
- preview SSR;
- snapshot HTML interno;
- PDF privado;
- SHA-256 y tamaño;
- estados `draft | generated | finalized`;
- checklist/tareas JSON simples;
- anexos descriptivos JSON simples;
- permisos administrativos propios;
- descarga privada PDF;
- enlace contextual desde candidato aprobado;
- trazabilidad de creación/generación/finalización.

## 4.2 No incluye

- tenant/lease;
- ocupación o cambio automático de `units.status`;
- cambio automático de `candidates.status`;
- portal contractual candidato/inquilino;
- firma electrónica/digital;
- notaría integrada;
- uploads arbitrarios de anexos;
- merge de PDFs externos;
- OCR;
- Word/DOCX;
- rich-text/WYSIWYG;
- HTML/CSS/JS ingresado por usuarios;
- email/WhatsApp;
- recordatorios/cron;
- conclusiones o asesoría jurídica automáticas;
- plantillas obtenidas dinámicamente de internet.

---

# 5. REFINAMIENTO KISS DE TIPOS

La SPEC maestra conserva en roadmap:

- vivienda urbana;
- parqueadero independiente;
- otrosí;
- renovación;
- acta de entrega;
- acta de terminación;
- aviso de reajuste;
- aviso de terminación.

F7 V1 opera solo los dos tipos que tienen contexto completo con F4–F6:

- `urban_housing` — vivienda urbana;
- `independent_parking` — parqueadero independiente.

Los demás permanecen en roadmap, pero F7 no crea botones, seeds, rutas ni lógica muerta para ellos. Una SPEC posterior los habilitará cuando exista contexto de lease/documentos.

---

# 6. MODELO DE DATOS — EXACTAMENTE 3 TABLAS

Migration prevista: `007_create_contract_engine.sql`.

## 6.1 `contract_templates`

- `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`
- `name VARCHAR(190) NOT NULL`
- `document_type VARCHAR(40) NOT NULL`
- `is_active TINYINT(1) NOT NULL DEFAULT 1`
- `created_by_user_id BIGINT UNSIGNED NULL`
- `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
- `updated_at DATETIME NULL`
- PK `id`
- UNIQUE `name`
- FK `created_by_user_id → users.id ON DELETE SET NULL`

Tipos F7 exactos:

- `urban_housing`
- `independent_parking`

No tabla de tipos.

## 6.2 `contract_template_versions`

- `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`
- `template_id BIGINT UNSIGNED NOT NULL`
- `version_number SMALLINT UNSIGNED NOT NULL`
- `body_template LONGTEXT NOT NULL`
- `variables_json TEXT NOT NULL`
- `created_by_user_id BIGINT UNSIGNED NULL`
- `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
- PK `id`
- UNIQUE `(template_id, version_number)`
- FK `template_id → contract_templates.id`
- FK `created_by_user_id → users.id ON DELETE SET NULL`

No `updated_at`: una versión es inmutable. No DELETE de versiones desde UI.

## 6.3 `contracts`

- `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`
- `candidate_id BIGINT UNSIGNED NOT NULL`
- `unit_id BIGINT UNSIGNED NOT NULL`
- `template_version_id BIGINT UNSIGNED NOT NULL`
- `document_type VARCHAR(40) NOT NULL`
- `status VARCHAR(24) NOT NULL`
- `monthly_rent DECIMAL(12,2) NOT NULL`
- `administration_fee DECIMAL(12,2) NULL`
- `start_date DATE NOT NULL`
- `end_date DATE NOT NULL`
- `duration_text VARCHAR(120) NOT NULL`
- `manual_variables_json TEXT NULL`
- `tasks_json TEXT NULL`
- `annexes_json TEXT NULL`
- `resolved_variables_json LONGTEXT NULL`
- `snapshot_html LONGTEXT NULL`
- `pdf_stored_name VARCHAR(190) NULL`
- `pdf_sha256 CHAR(64) NULL`
- `pdf_size_bytes BIGINT UNSIGNED NULL`
- `created_by_user_id BIGINT UNSIGNED NULL`
- `finalized_by_user_id BIGINT UNSIGNED NULL`
- `generated_at DATETIME NULL`
- `finalized_at DATETIME NULL`
- `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
- `updated_at DATETIME NULL`
- PK `id`
- INDEX `candidate_id`
- INDEX `unit_id`
- INDEX `template_version_id`
- INDEX `status`
- FK `candidate_id → candidates.id`
- FK `unit_id → units.id`
- FK `template_version_id → contract_template_versions.id`
- FK `created_by_user_id → users.id ON DELETE SET NULL`
- FK `finalized_by_user_id → users.id ON DELETE SET NULL`

No `ON DELETE CASCADE` sobre candidato, unidad o template version: evidencia contractual no desaparece por cascada.

## 6.4 JSON

Usar `TEXT/LONGTEXT` con JSON canónico validado en PHP, siguiendo el patrón simple de `permissions_json`. No depender de funciones JSON SQL.

---

# 7. FUENTES DE VERDAD

## Candidato

`candidates` sigue siendo la fuente. F7 no copia una persona contractual paralela.

## Unidad

`units` sigue siendo inventario único. `contracts.unit_id` se deriva del `candidate.unit_id` al crear draft; no se confía en un unit ID enviado por formulario.

## Estudio

`screening_processes` sigue siendo única fuente de aseguradora. No agregar `insurance_approved` ni screening status en contracts.

## Precio

`units.monthly_price` = precio comercial.  
`contracts.monthly_rent` = canon contractual final snapshot.

No sincronizar automáticamente uno con otro.

## Estados externos

F7 no duplica contrato en `candidates.status` y no cambia `units.status`. F8 decide cuándo finalized genera lease/tenant/ocupación.

---

# 8. ELEGIBILIDAD PARA CREAR DRAFT

Todas deben ser verdaderas:

1. candidato existe;
2. `candidate.status = pending_screening`;
3. existe screening para ese candidato;
4. `screening.status = approved`;
5. unidad del candidato existe;
6. compatibilidad:
   - `apartment → urban_housing`;
   - `parking → independent_parking`;
7. `units.status ∈ {available, in_process}`;
8. propiedad existe y `is_active = 1`;
9. template activo y version perteneciente a ese template.

Screening `pending|in_progress|rejected` bloquea. Candidate `withdrawn` bloquea crear/generar/finalizar.

## Duplicados

- máximo un `draft` activo por candidato;
- `generated` históricos pueden conservarse para no sobrescribir evidencia;
- máximo un `finalized` por candidato F7;
- finalized impide nuevos contratos iniciales F7.

Finalización usa transacción y bloqueo de candidato (`SELECT ... FOR UPDATE` o equivalente simple) para evitar doble finalized concurrente.

---

# 9. CICLO DE VIDA

Estados exactos:

- `draft`
- `generated`
- `finalized`

Transiciones:

- creación → `draft`
- `draft → draft`
- `draft → generated`
- `generated → finalized`
- `finalized` terminal

Nunca `generated → draft`, `finalized → generated` ni `finalized → draft`.

## `draft`

Editable:

- template version, siempre del mismo tipo documental;
- canon;
- administración;
- start/end date;
- duración textual;
- variables manuales;
- tareas;
- anexos descriptivos.

## `generated`

Congela:

- document type;
- candidate/unit/template version;
- condiciones económicas;
- start/end/duration;
- variables resueltas;
- anexos;
- snapshot HTML;
- PDF/hash/tamaño;
- generated_at.

Solo `tasks_json` puede seguir actualizándose como checklist administrativo; nunca altera snapshot/PDF.

## `finalized`

Solo agrega:

- `finalized_by_user_id`;
- `finalized_at`;
- `status = finalized`.

No implica firma digital/electrónica.

---

# 10. PLANTILLAS Y VERSIONES

## Plantilla

Identidad estable: nombre, tipo, activo/inactivo.

Desactivar template **solo impide nuevos drafts**. Drafts ya existentes y documentos generated/finalized conservan y pueden usar su versión pinneada; la historia no se invalida retroactivamente.

## Nueva versión

Modificar texto significa crear `version_number + 1`. Número server-side dentro de transacción. No editar ni borrar versiones existentes.

## Cuerpo seguro

`body_template` = texto UTF-8 plano, no HTML.

Placeholder ejemplo:

`{{candidate.full_name}}`

No permitir HTML, CSS, PHP, JavaScript, URL remota embebida ni expresiones ejecutables. Se escapa texto primero y luego código interno genera el HTML.

Las cláusulas viven en el texto versionado; no tabla `clauses`.

## Contenido jurídico

F7 no inventa ni precarga automáticamente cláusulas jurídicas como si fueran asesoría validada. El contenido contractual lo crea/revisa el administrador responsable. El sistema conserva versión, preview y snapshot, pero no decide suficiencia jurídica.

---

# 11. VARIABLES

## Sintaxis

Única:

`{{namespace.key}}`

Patrón conceptual:

`{{[a-z0-9_.]+}}`

Sin funciones, filtros, loops ni condicionales.

## Automáticas allowlisted

### Candidato

- `candidate.full_name`
- `candidate.document_type`
- `candidate.document_number`
- `candidate.phone`
- `candidate.email`
- `candidate.current_address`

### Propiedad/unidad

- `property.name`
- `property.address`
- `property.city`
- `property.sector`
- `unit.code`
- `unit.type`
- `unit.location_label`
- `unit.unit_number`
- `unit.tower`
- `unit.floor`

### Contrato

- `contract.monthly_rent`
- `contract.administration_fee`
- `contract.start_date`
- `contract.end_date`
- `contract.duration`

**No calcular automáticamente una fecha final jurídica desde “N meses”.** `start_date`, `end_date` y `duration_text` son datos explícitos confirmados por el administrador; se valida `end_date >= start_date`.

## Manuales

Una versión declara manuales en `variables_json`:

```json
{"key":"custom.example","label":"Texto visible","required":true}
```

Reglas:

- namespace `custom.` obligatorio;
- clave única;
- label obligatorio;
- required boolean;
- texto plano;
- máximo 4000 caracteres por valor;
- máximo 50 variables por versión;
- cada `custom.*` del body debe estar declarado;
- declaración no usada se rechaza como ruido.

Placeholder desconocido = versión rechazada.

## Resolución en generate

1. recargar candidate/unit/property/screening;
2. revalidar elegibilidad;
3. cargar versión exacta;
4. resolver allowlist automática;
5. validar manuales;
6. escapar valores;
7. producir HTML interno;
8. persistir `resolved_variables_json`.

Nunca reflexión, `eval`, SQL dinámico ni columnas indicadas por template.

---

# 12. ECONOMÍA Y FECHAS

## `monthly_rent`

- requerido;
- `> 0`;
- máximo compatible con `DECIMAL(12,2)`;
- dos decimales normalizados.

UI puede precargar `units.monthly_price`, pero el usuario manage confirma el canon contractual.

## `administration_fee`

- opcional;
- `>= 0`;
- puede precargarse desde `units.administration_fee`;
- se congela en generate.

## Fechas/duración

- `start_date`: fecha válida requerida;
- `end_date`: fecha válida requerida, `>= start_date`;
- `duration_text`: trim 1–120, requerido.

El software no reemplaza fechas confirmadas con inferencias calendáricas. No calcula reajustes futuros en F7.

---

# 13. TAREAS Y ANEXOS — SIN TABLAS EXTRA

## `tasks_json`

Checklist simple:

```json
[
  {"label":"Revisar datos","done":true},
  {"label":"Firma física recibida","done":false}
]
```

- máximo 30;
- label 1–190 texto plano;
- done boolean;
- sin responsable/fecha/comentario/prioridad/workflow propio.

Si luego necesitan ciclo de vida independiente, una SPEC futura justificará tabla propia.

## `annexes_json`

Metadatos descriptivos textuales:

```json
[
  {"title":"Anexo 1","note":"Descripción"}
]
```

- máximo 20;
- title 1–190;
- note opcional texto plano;
- se incluye como sección textual del snapshot/PDF;
- sin rutas, IDs de archivos, URLs ni uploads.

Uploads/documentos privados generales pertenecen a F18.

---

# 14. `ContractPdfRenderer`

Única clase PDF:

`AlquilaPro\Contracts\ContractPdfRenderer`

Responsabilidades:

- recibir HTML interno seguro;
- configurar Dompdf;
- letter/portrait;
- `DejaVu Sans`;
- CSS interno fijo;
- header/footer fijo;
- numeración;
- renderizar y devolver bytes.

No lee DB, no resuelve permisos, no valida candidato, no descarga recursos, no guarda archivos ni decide estados.

Config obligatoria:

- `isRemoteEnabled = false`;
- sin HTTP/HTTPS;
- sin HTML/CSS del request;
- sin rutas arbitrarias;
- activos locales solo server-side;
- sin binarios externos.

---

# 15. `ContractService`

Único servicio nuevo:

`AlquilaPro\Contracts\ContractService`

Responsabilidades:

- template list/get/create/toggle;
- crear versión inmutable;
- validar placeholders/variables;
- contract list/get;
- elegibilidad candidate/screening/unit;
- create/update draft;
- preview;
- resolver variables;
- snapshot;
- invocar renderer;
- persistir PDF/hash/tamaño;
- update checklist permitido;
- finalizar en transacción;
- entregar datos a rutas/vistas.

No crear `ContractRepository`, `TemplateService`, `VariableResolver`, `PdfService`, `ClauseService` ni equivalentes.

---

# 16. SNAPSHOT E INMUTABILIDAD

`generate`:

1. requiere `draft`;
2. revalida fuentes;
3. resuelve variables;
4. crea HTML interno;
5. renderiza PDF;
6. valida `%PDF-`;
7. SHA-256 sobre bytes exactos;
8. escribe archivo privado aleatorio;
9. guarda snapshot/hash/tamaño/generated_at;
10. cambia atómicamente a `generated`.

Ante fallo:

- sigue `draft`;
- no estado parcial;
- archivo parcial eliminado;
- no hash falso.

Después de generated, contenido/snapshot/PDF/hash son inmutables.

---

# 17. STORAGE PRIVADO

Directorio lógico:

`storage/contracts/`

Reglas:

- fuera del webroot;
- nombre random server-side, p. ej. 32 hex + `.pdf`;
- DB guarda solo `pdf_stored_name`;
- nombre físico sin datos del candidato;
- jamás path desde request;
- escritura temp → rename cuando sea posible;
- PDF máximo 10 MiB;
- entrega solo por ruta autorizada;
- no symlink público.

---

# 18. PERMISOS

Exactamente:

- `contracts.view`
- `contracts.manage`

`contracts.view`: list/detail/templates/preview/pdf.  
`contracts.manage`: templates/versions/draft/generate/tasks/finalize.

Owner conserva bypass. No permisos por estado/tipo/acción.

---

# 19. RUTAS Y UI

Nuevo módulo:

`app/Contracts/`

Un solo nuevo route file:

`app/Contracts/routes.php`

No rutas públicas F7.

## Contratos

- `GET /admin/contracts`
- `POST /admin/contracts`
- `GET /admin/contracts/{id}`
- `POST /admin/contracts/{id}`
- `GET /admin/contracts/{id}/pdf`

`POST /admin/contracts/{id}` action allowlist:

- `save`
- `generate`
- `update_tasks`
- `finalize`

## Templates

- `GET /admin/contract-templates`
- `POST /admin/contract-templates`
- `GET /admin/contract-templates/{id}`
- `POST /admin/contract-templates/{id}`

No endpoint POST por cada microacción si un action allowlisted es claro.

## Candidate detail

`GET /admin/candidates/{id}` puede mostrar sección Contrato si usuario posee `contracts.view`/owner. Con screening approved y sin finalized, CTA real hacia creación/existente. No lógica contractual dentro de Candidates.

## UX

SSR, forms normales, JS no obligatorio, shell/CSS existente, sin modal/wizard complejo. Preview en detalle contractual.

---

# 20. PREVIEW

En `draft`:

- no crea PDF;
- no cambia estado;
- usa la misma resolución base de `generate`;
- escapa datos;
- HTML solo interno;
- muestra `BORRADOR` visible;
- no tiene hash;
- no endpoint público.

---

# 21. PDF DOWNLOAD

`GET /admin/contracts/{id}/pdf`:

- auth;
- forced-password guard;
- `contracts.view` o owner;
- solo generated/finalized;
- autorización antes de acceder al archivo;
- filename físico server-side;
- `Content-Type: application/pdf`;
- attachment con nombre humano seguro server-side;
- sin path traversal;
- 404/403 fail closed;
- sin URL pública permanente.

---

# 22. SEGURIDAD

## Auth / CSRF

Reutilizar F2/F3: auth, SessionGuard, forced-password, CSRF, basePath y owner bypass. Sin auth contractual paralelo.

## XSS

Template/variables/tasks/annexes son texto plano. Todo valor se escapa. Snapshot HTML solo lo construye código interno.

## IDOR

Sin rutas contractuales públicas. IDs solo admin autenticado con permiso. Candidate/unit/template IDs no son autoridad cuando pueden derivarse server-side.

## PDF

Remote off, no raw HTML, no file URLs de request, storage privado, hash post-render, cleanup parcial.

## Logs/errores

No loguear cuerpo contractual completo, documento completo del candidato, PDF bytes, variables manuales sensibles ni path filesystem completo. No SQL/stack trace/path en error visible.

---

# 23. RUNTIME / COMPOSER / INSTALLER

Dependencia directa nueva:

`dompdf/dompdf:^3.1.6`

Requisitos runtime F7:

- `ext-dom` nuevo;
- `ext-mbstring` existente;
- `ext-gd` nuevo;
- `fileinfo` existente;
- PHP `>=8.3 <8.6`.

`RuntimeRequirements` e installer fallan claramente si faltan dom/gd.

CI solicita explícitamente:

`pdo, pdo_mysql, mbstring, dom, gd, fileinfo`.

No imagick/gmagick/zip obligatorias sin evidencia. No binarios.

---

# 24. LICENCIA / DISTRIBUCIÓN

El spike observó Dompdf v3.1.6 bajo `LGPL-2.1-only`.

F7 usa Composer, no planea modificar Dompdf y preserva archivos/notices de vendor. F25 mantiene revisión de notices/licencias del paquete final.

Esto registra una decisión técnica de distribución; no automatiza una conclusión jurídica.

---

# 25. VALIDACIONES

## Template/version

- name trim 1–190;
- type allowlist exacta;
- body requerido, UTF-8, máximo 250.000 caracteres;
- placeholders válidos;
- automáticos solo allowlist;
- custom declarado;
- declaración custom no usada = reject;
- máximo 50 custom vars;
- template inactivo bloquea **nuevo draft**, no historial/draft ya creado;
- versiones nunca edit/delete.

## Contract

- candidate elegible;
- unit derivada del candidate;
- screening approved;
- unit/type compatible;
- monthly_rent > 0;
- administration_fee >= 0 o null;
- start/end válidas y end >= start;
- duration_text 1–120;
- manual required completas;
- tasks/annexes JSON válidos y límites;
- template/version válidos;
- estado/transición válidos;
- generated/finalized no edición de contenido;
- finalized único por candidate F7.

---

# 26. PRUEBAS OBLIGATORIAS

## Schema

- exactamente 3 tablas F7;
- columnas/FKs/índices;
- version UNIQUE;
- no clauses/tasks/annex tables;
- no lease/tenant/auth paralelo.

## Runtime/PDF

- Dompdf lock;
- Composer strict;
- runtime dom/gd;
- installer fail claro;
- PHP 8.3/8.4/8.5;
- UTF-8/CSS/header/footer/pages/image local;
- remote off;
- `%PDF-`;
- SHA-256/tamaño exactos;
- archivo privado.

## Templates

- create;
- duplicate name reject;
- invalid type reject;
- v1/v2 incremental;
- old version immutable;
- bad placeholder reject;
- non-allowlisted auto reject;
- undeclared custom reject;
- unused custom reject;
- inactive blocks new draft but not existing pinned draft.

## Elegibilidad

- approved permite;
- pending/in_progress/rejected bloquean;
- withdrawn bloquea;
- incompatible unit bloquea;
- maintenance/inactive/occupied bloquean;
- property inactive bloquea;
- forged unit ID reject/ignored;
- una draft activa.

## Draft/preview

- defaults económicos no son autoridad;
- canon puede diferir sin tocar unit price;
- start/end/duration explicit;
- manual vars/tasks/annexes;
- BORRADOR;
- XSS escaped.

## Generate

- draft→generated;
- revalidación;
- resolved vars + HTML snapshot;
- private PDF/hash/size/generated_at;
- immutable;
- fallo deja draft y limpia parcial;
- segunda generación mismo row bloqueada.

## Finalize

- generated→finalized;
- draft→finalized bloqueado;
- terminal;
- actor/fecha;
- doble finalized bloqueado bajo transacción;
- no lease/tenant;
- no candidate/unit status mutation.

## Permisos/rutas

- anonymous/forced-password;
- view/manage split;
- owner bypass;
- CSRF;
- basePath/subfolder;
- protected PDF;
- IDOR;
- 404/403 fail closed.

## Regresión

F1–F6 GREEN completo en PHP 8.3/8.4/8.5.

---

# 27. MICROFASES

## F7.1 — Runtime + schema + permissions

Dompdf productivo, ext-dom/ext-gd, migration 007, permisos, tests runtime/schema.

## F7.2 — Templates + versions

ContractService base, CRUD mínimo, versiones inmutables, placeholders/variables, SSR admin.

## F7.3 — Draft + preview

Elegibilidad, draft, economics/dates, manual vars, tasks/annexes, preview seguro.

## F7.4 — Snapshot + PDF + hash

ContractPdfRenderer, private storage, transaction, SHA-256, download, immutability.

## F7.5 — Finalization + candidate context

Generated→finalized, checklist, CTA candidate, finalized único, confirmar 0 lease/tenant/unit mutation.

## F7.6 — Security + visual + Cero Ruido + closure

IDOR/XSS/path traversal, permissions/CSRF/basePath, visual regression, PHP matrix, full suite, Two Laws, checkpoint.

Cada microfase:

`RED → VERIFY RED → MINIMAL GREEN → VERIFY GREEN → FULL CI → CONVERGE`.

---

# 28. BLOQUEADORES DE CIERRE

No cerrar F7 si existe:

- HTML arbitrario editable;
- Word/WYSIWYG;
- segunda auth;
- tenant/lease adelantado;
- candidate/unit status auto-mutado;
- screening no approved;
- withdrawn contractual;
- remote resources Dompdf;
- PDF webroot/path de request;
- PDF sin hash;
- snapshot mutable post-generated;
- template version mutable;
- clauses/tasks/annex tables injustificadas;
- upload F18 adelantado;
- dependencia extra sin evidencia;
- cálculo automático ambiguo de fechas contractuales;
- regresión F1–F6;
- matriz PHP no GREEN.

---

# 29. TWO LAWS GATE

```text
LEY_1_KISS: PASS|FAIL
LEY_2_CERO_RUIDO: PASS|FAIL
ADDED:
REMOVED:
MERGED_OR_CONVERGED:
DEPENDENCIES_DELTA:
DB_SCHEMA_DELTA:
PUBLIC_ROUTES_DELTA:
PROOF:
```

FAIL bloquea cierre/merge.

Presupuesto esperado:

```text
NEW_TABLES: 3
NEW_DOMAIN_CLASSES: 2
NEW_PERMISSIONS: 2
NEW_ROUTE_FILES: 1
NEW_DIRECT_RUNTIME_DEPENDENCIES: 1
NEW_PUBLIC_ROUTES: 0
NEW_AUTH_SYSTEMS: 0
NEW_LEASE/TENANT_TABLES: 0
```

---

# 30. GATE DE APROBACIÓN

```text
F6: CLOSED / VERIFIED / MERGED
F7_SCOPE: APPROVED
F7_PRE_KISS: APPROVED
F7_DESIGN_IN_CHAT: APPROVED
F7_PDF_SPIKE: PASS
F7_SPEC_WRITTEN: YES
F7_SPEC_APPROVED_BY_USER: YES
F7_PLAN_WRITTEN: YES
F7_IMPLEMENTATION_ALLOWED: NO
```

Siguiente gate:

`USER APPROVES F7 IMPLEMENTATION PLAN → RED F7.1`.

La SPEC está aprobada. El PLAN existe pero requiere aprobación explícita del siguiente gate antes de iniciar RED o código productivo.