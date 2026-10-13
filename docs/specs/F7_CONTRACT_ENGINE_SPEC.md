# F7 — MOTOR DE CONTRATOS — SPEC ESPECÍFICA

**Fecha:** 2026-10-12 (America/Bogota)  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Fase:** F7 — Motor de contratos  
**Estado:** **SPEC ESCRITA — PENDIENTE DE APROBACIÓN DEL USUARIO**  
**Base exacta:** `main @ 1b9ee85ebb224b310d4ab4f0c84a28628b86b747`  
**Branch:** `impl/f7-contracts-20261012`

---

# 0. AUTORIDAD Y CONTEXTO

Orden de autoridad vigente:

`SPEC maestra V1.2 → PLAN maestro V1.2 → README → esta SPEC F7 → futuro PLAN F7 → tests/CI → código real`.

F0–F6 están cerradas; F6 está **CLOSED / VERIFIED / MERGED**.

F7 parte de entidades ya existentes y no las duplica:

- `users` + auth/sesión/CSRF/forced-password existentes;
- `properties` y `units` como inventario único;
- `candidates` como candidatura única;
- `screening_processes` como único estudio de aseguradora.

No se reabre F1–F6 salvo evidencia concreta de regresión.

---

# 1. EVIDENCIA PREVIA — SPIKE PDF F7

Antes de esta SPEC se ejecutó un spike throwaway aprobado por el usuario.

## 1.1 Evidencia

- Rama throwaway: `spike/f7-dompdf-20261012`
- HEAD final del spike: `352dd3abcec6d26644906ccc9247dc51d7545c18`
- Workflow: `F7 PDF Spike`
- Run final: `38339899173`
- Dompdf probado: `v3.1.6`
- PHP 8.3: PASS
- PHP 8.4: PASS
- PHP 8.5: PASS
- `composer check-platform-reqs`: PASS en las tres versiones
- PDF generado: 2 páginas, UTF-8 español, CSS, tabla, PNG local, salto de página, header/footer y numeración
- salida observada: `22583` bytes
- revisión visual: PASS
- `ext-dom`: requerida por Dompdf
- `ext-mbstring`: requerida por Dompdf y ya requerida por Alquila Pro
- `ext-gd`: adoptada como requisito F7 para procesamiento de imágenes
- binarios externos: ninguno
- licencia observada del paquete: `LGPL-2.1-only`
- la rama del spike NO se integra a `main`.

## 1.2 Decisión congelada

F7 usará:

`dompdf/dompdf:^3.1.6`

No se usarán wkhtmltopdf, Chromium, Chrome headless, Node, Puppeteer ni ejecutables externos.

Dompdf operará con recursos remotos desactivados.

---

# 2. OBJETIVO

Construir el motor administrativo mínimo para convertir un candidato con estudio aprobado en un contrato versionado, previsualizable, congelable y descargable en PDF, preservando el contexto real de candidato/unidad y sin crear todavía inquilino, lease ni portal contractual público.

F7 debe resolver:

`candidato + screening aprobado → borrador contractual → preview → snapshot → PDF + hash → finalización administrativa`.

F7 **NO activa el arrendamiento**. La creación de tenant/lease y la ocupación operacional pertenecen a F8.

---

# 3. LEYES SUPREMAS F7

## LEY 1 — KISS

Máximo estructural F7 aprobado:

- **3 tablas nuevas**;
- **2 clases de dominio nuevas**;
- **2 permisos nuevos**;
- **1 route file nuevo**;
- **1 dependencia runtime directa nueva**: Dompdf;
- **0 auth nuevos**;
- **0 login nuevos**;
- **0 repositories**;
- **0 interfaces/adapters/factories/DTOs**;
- **0 editor WYSIWYG / Word web**;
- **0 SPA**;
- **0 workers/queues/cron**;
- **0 firma electrónica**;
- **0 upload documental genérico**;
- **0 lease / tenant**.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`.

F7 debe reutilizar:

- `CandidateService`;
- `ScreeningService`;
- `PropertyService`;
- auth/session/forced-password existentes;
- CSRF existente;
- `basePath` existente;
- shell visual F3;
- storage privado existente;
- patrón SSR existente.

No crear fuentes paralelas para candidato, unidad, precio comercial, aseguradora, usuario o sesión.

---

# 4. ALCANCE FUNCIONAL

## 4.1 Incluye

- plantillas contractuales;
- versiones inmutables de plantilla;
- cuerpo de plantilla en texto UTF-8, no HTML arbitrario;
- placeholders simples y allowlisted;
- cláusulas contenidas dentro del texto versionado;
- variables automáticas + manuales declaradas;
- borrador contractual;
- condiciones económicas finales del contrato;
- fecha de inicio y duración;
- preview SSR;
- snapshot HTML interno;
- PDF privado;
- SHA-256 del PDF;
- estados `draft | generated | finalized`;
- checklist/tareas simples en JSON;
- anexos descriptivos simples en JSON;
- permisos administrativos propios;
- acceso privado al PDF;
- enlace contextual desde candidato aprobado;
- trazabilidad de creador/generación/finalización.

## 4.2 No incluye

- tenant;
- lease;
- ocupación automática de unidad;
- cambio automático de `units.status`;
- cambio automático de `candidates.status`;
- portal contractual del candidato/inquilino;
- firma electrónica/digital;
- integración notarial;
- upload de anexos arbitrarios;
- merge de PDFs externos;
- OCR;
- Word/DOCX;
- editor rich-text/WYSIWYG;
- HTML ingresado por usuarios;
- JavaScript obligatorio;
- email/WhatsApp;
- recordatorios/cron;
- generación automática de conclusiones jurídicas;
- asesoría jurídica automática;
- plantillas obtenidas dinámicamente de internet.

---

# 5. REFINAMIENTO KISS DE TIPOS DOCUMENTALES

La SPEC maestra enumera como familia documental futura:

- vivienda urbana;
- parqueadero independiente;
- otrosí;
- renovación;
- acta de entrega;
- acta de terminación;
- aviso de reajuste;
- aviso de terminación.

F7 V1 **operará únicamente los dos contratos iniciales que pueden resolverse correctamente con el contexto existente F4–F6**:

- `urban_housing` — vivienda urbana;
- `independent_parking` — parqueadero independiente.

Los demás tipos permanecen en el roadmap, pero **no se crean botones, filas precargadas, rutas muertas ni lógica futura** en F7. Se activarán mediante SPEC posterior cuando exista el contexto de lease/documentos correspondiente.

Este refinamiento evita construir funcionalidad sin ciclo de vida real y mantiene Ley 1 + Ley 2.

---

# 6. MODELO DE DATOS — EXACTAMENTE 3 TABLAS

Migration prevista: `007_create_contract_engine.sql`.

## 6.1 `contract_templates`

Campos congelados:

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

`document_type` permitido en F7:

- `urban_housing`
- `independent_parking`

No tabla de tipos.

## 6.2 `contract_template_versions`

Campos congelados:

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

No `updated_at`: una versión creada es inmutable.

No DELETE de versiones desde UI.

## 6.3 `contracts`

Campos congelados:

- `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`
- `candidate_id BIGINT UNSIGNED NOT NULL`
- `unit_id BIGINT UNSIGNED NOT NULL`
- `template_version_id BIGINT UNSIGNED NOT NULL`
- `document_type VARCHAR(40) NOT NULL`
- `status VARCHAR(24) NOT NULL`
- `monthly_rent DECIMAL(12,2) NOT NULL`
- `administration_fee DECIMAL(12,2) NULL`
- `start_date DATE NOT NULL`
- `duration_months SMALLINT UNSIGNED NOT NULL`
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

No `ON DELETE CASCADE` sobre candidato, unidad, template version o contrato: un contrato/documento no debe desaparecer por cascada.

## 6.4 JSON sin nueva abstracción de BD

Se usa `TEXT/LONGTEXT` con JSON canónico validado por PHP, siguiendo el patrón simple ya existente de `permissions_json`.

No se agrega dependencia de funciones JSON del motor SQL.

---

# 7. FUENTES DE VERDAD

## 7.1 Candidato

`candidates` sigue siendo la fuente del candidato.

F7 no copia una “persona contractual” a otra tabla.

## 7.2 Unidad

`units` sigue siendo la fuente de unidad/inventario.

El `unit_id` del contrato debe ser exactamente el `unit_id` vigente del candidato al crear el borrador. No se acepta un `unit_id` arbitrario enviado por formulario.

## 7.3 Estudio

`screening_processes` sigue siendo la única fuente del resultado de aseguradora.

F7 no agrega `screening_status`, `insurance_approved` ni equivalentes a `contracts`.

## 7.4 Precio

`units.monthly_price` continúa siendo precio comercial/publicado.

`contracts.monthly_rent` es el **valor contractual final acordado** y constituye snapshot económico propio del contrato.

No debe sincronizarse automáticamente hacia atrás con `units.monthly_price`.

## 7.5 Estado de candidato y unidad

F7 **no duplica el estado contractual dentro de `candidates.status`**.

F7 **no cambia `units.status` automáticamente**.

F8 decidirá, con SPEC propia, cuándo un contrato finalizado crea lease, tenant y ocupación.

---

# 8. ELEGIBILIDAD PARA CREAR BORRADOR

Se puede crear un contrato inicial solo si todas son verdaderas:

1. candidato existe;
2. `candidate.status = pending_screening`;
3. existe exactamente un `screening_processes` para ese candidato;
4. `screening_processes.status = approved`;
5. la unidad del candidato existe;
6. la unidad es del tipo compatible con la plantilla:
   - `apartment → urban_housing`;
   - `parking → independent_parking`;
7. `units.status` es `available` o `in_process`;
8. la propiedad existe y está activa;
9. la plantilla/version está activa/usable al crear el borrador.

No se permite contrato sobre screening `pending`, `in_progress` o `rejected`.

Un `candidate.status = withdrawn` no puede crear, generar ni finalizar contratos nuevos.

## 8.1 Duplicados

F7 permite conservar borradores/PDFs históricos porque un documento generado es evidencia y no debe sobrescribirse.

Regla de servicio:

- máximo **un `draft` activo** por candidato en F7;
- puede existir más de un `generated` histórico si un documento tuvo que corregirse mediante un nuevo borrador;
- máximo **un `finalized`** por candidato en F7;
- una vez existe un `finalized`, no se crean más contratos iniciales F7 para ese candidato.

La finalización debe usar transacción y bloqueo de candidato (`SELECT ... FOR UPDATE` o equivalente simple) para evitar doble finalización concurrente.

---

# 9. CICLO DE VIDA DEL CONTRATO

Estados exactos:

- `draft`
- `generated`
- `finalized`

Transiciones:

- creación → `draft`
- `draft → draft` mediante guardado
- `draft → generated`
- `generated → finalized`
- `finalized` terminal

No:

- `generated → draft`;
- `finalized → generated`;
- `finalized → draft`;
- edición de contenido después de `generated`.

## 9.1 Semántica

### `draft`

Editable:

- template version;
- canon final;
- administración;
- fecha inicio;
- duración;
- variables manuales;
- tareas;
- anexos descriptivos.

### `generated`

Congela de forma inmutable:

- `document_type`;
- `candidate_id`;
- `unit_id`;
- `template_version_id`;
- condiciones económicas;
- fechas/duración;
- variables resueltas;
- anexos incluidos;
- `snapshot_html`;
- PDF;
- SHA-256;
- tamaño;
- `generated_at`.

Solo el checklist `tasks_json` puede seguir actualizándose para completar pasos administrativos previos a finalización. Ese checklist no altera el snapshot ni el PDF.

### `finalized`

Marca decisión administrativa final del documento.

Solo agrega:

- `finalized_by_user_id`;
- `finalized_at`;
- `status = finalized`.

No firma electrónicamente el archivo y no debe presentarse como firma digital.

---

# 10. PLANTILLAS Y VERSIONES

## 10.1 Plantilla

Una plantilla contiene identidad estable:

- nombre;
- tipo documental;
- activo/inactivo.

La edición de contenido nunca modifica una versión existente.

## 10.2 Nueva versión

Toda modificación de texto crea `version_number + 1`.

No se permite elegir manualmente el número de versión.

El servicio obtiene el último número dentro de transacción y crea el siguiente.

## 10.3 Cuerpo seguro

`body_template` es **texto UTF-8 plano**, no HTML.

Ejemplo de placeholder permitido:

`{{candidate.full_name}}`

El usuario administrativo no puede insertar:

- `<script>`;
- HTML arbitrario;
- CSS arbitrario;
- PHP;
- JavaScript;
- URL remota embebida;
- expresiones ejecutables.

Saltos de línea se convierten a HTML únicamente después de escapar contenido.

Cláusulas son secciones textuales dentro de `body_template`; no se crea tabla `clauses`.

---

# 11. VARIABLES

## 11.1 Sintaxis

Sintaxis única:

`{{namespace.key}}`

Regex conceptual:

`{{[a-z0-9_.]+}}`

No funciones, filtros, loops, condicionales ni expresiones.

## 11.2 Variables automáticas allowlisted F7

Mínimo previsto:

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
- `contract.duration_months`
- `contract.end_date`

El servidor calcula `contract.end_date` desde `start_date + duration_months` con regla contractual de fin el día anterior al mismo día calendario del periodo siguiente.

## 11.3 Variables manuales

Una versión puede declarar variables manuales mediante `variables_json`.

Formato lógico mínimo por entrada:

```json
{
  "key": "custom.example",
  "label": "Texto visible",
  "required": true
}
```

Reglas:

- namespace manual obligatorio: `custom.`;
- clave única dentro de la versión;
- label obligatorio;
- `required` boolean;
- no HTML;
- no secretos;
- valor máximo por variable: 4000 caracteres;
- máximo 50 variables manuales por versión.

`variables_json` debe coincidir con placeholders `custom.*` presentes en `body_template`.

Placeholder desconocido = versión rechazada.

## 11.4 Resolución

Al generar:

1. recargar candidato/unidad/propiedad/screening desde DB;
2. revalidar elegibilidad;
3. obtener versión exacta;
4. resolver allowlist automática;
5. validar manuales requeridas;
6. escapar todos los valores;
7. producir snapshot HTML interno;
8. guardar `resolved_variables_json`.

Nunca usar reflexión, `eval`, acceso dinámico SQL o nombres de columnas suministrados por plantilla.

---

# 12. CONDICIONES ECONÓMICAS Y FECHAS

## 12.1 Canon

`monthly_rent`:

- requerido;
- `> 0`;
- máximo `9999999999.99` según `DECIMAL(12,2)`;
- dos decimales normalizados.

Al crear borrador, UI puede precargar desde `units.monthly_price`, pero el usuario con permiso manage confirma/edita el valor contractual.

## 12.2 Administración

`administration_fee`:

- opcional;
- `>= 0`;
- si no se suministra puede precargarse desde `units.administration_fee`;
- queda congelada en generación.

## 12.3 Inicio y duración

- `start_date`: requerido, fecha válida;
- `duration_months`: entero entre 1 y 120;
- `end_date`: derivada, no campo editable de DB.

No calcular reajustes futuros en F7.

---

# 13. TAREAS Y ANEXOS — SIN TABLAS EXTRA

## 13.1 `tasks_json`

Checklist administrativo simple.

Estructura lógica:

```json
[
  {"label":"Revisar datos", "done":true},
  {"label":"Firma física recibida", "done":false}
]
```

Reglas:

- máximo 30 tareas;
- label texto plano, 1–190 caracteres;
- `done` boolean;
- no responsable, fecha, comentarios, prioridades ni workflow propio en F7.

Si luego requieren ciclo de vida independiente, una SPEC futura justificará tabla propia.

## 13.2 `annexes_json`

Metadatos descriptivos de anexos textuales del contrato.

Estructura lógica:

```json
[
  {"title":"Anexo 1", "note":"Descripción"}
]
```

Reglas:

- máximo 20;
- title 1–190;
- note opcional, texto plano;
- se incluyen como sección textual del snapshot/PDF;
- no contienen rutas, IDs de archivos, URLs ni uploads.

Los documentos privados/upload generales pertenecen a F18.

---

# 14. RENDERER PDF

Única clase dedicada:

`AlquilaPro\Contracts\ContractPdfRenderer`

Responsabilidades:

- recibir HTML interno ya construido/seguro;
- configurar Dompdf;
- carta/portrait por defecto;
- fuente Unicode segura (`DejaVu Sans`);
- CSS interno fijo del sistema;
- header/footer fijo;
- numeración de páginas;
- renderizar PDF;
- devolver bytes.

No responsabilidad de:

- leer DB;
- resolver permisos;
- validar candidato;
- descargar recursos remotos;
- mutar contrato;
- guardar archivos;
- decidir estados.

## 14.1 Seguridad Dompdf

Config obligatoria:

- `isRemoteEnabled = false`;
- sin recursos HTTP/HTTPS;
- sin HTML de usuario;
- sin CSS de usuario;
- sin rutas arbitrarias de usuario;
- activos locales únicamente resueltos por servidor;
- no binarios externos.

El renderer no expone opciones Dompdf al request HTTP.

---

# 15. SERVICIO DE DOMINIO

Único servicio nuevo:

`AlquilaPro\Contracts\ContractService`

Responsabilidades:

- listar/obtener templates;
- crear template;
- activar/desactivar template;
- crear versión inmutable;
- validar placeholders/variables;
- listar/obtener contratos;
- validar elegibilidad candidato/screening/unidad;
- crear draft;
- actualizar draft;
- construir preview;
- resolver variables;
- generar snapshot;
- invocar `ContractPdfRenderer`;
- persistir PDF privado + hash + tamaño;
- actualizar checklist permitido;
- finalizar con transacción;
- servir metadatos necesarios a rutas/vistas.

No crear `ContractRepository`, `TemplateService`, `VariableResolver`, `PdfService`, `ClauseService` ni capas equivalentes en F7.

---

# 16. SNAPSHOT E INMUTABILIDAD

Al ejecutar `generate`:

1. validar estado `draft`;
2. revalidar todas las fuentes;
3. resolver variables;
4. construir HTML interno;
5. renderizar PDF;
6. validar salida `%PDF-`;
7. calcular SHA-256 sobre bytes exactos;
8. escribir archivo privado con nombre aleatorio;
9. guardar snapshot/hash/tamaño/fecha;
10. transición atómica a `generated`.

Si cualquiera falla:

- contrato permanece `draft`;
- no se guarda estado parcial;
- archivo parcial se elimina;
- no se guarda hash falso.

Después de `generated`, contenido/snapshot/PDF/hash no cambian.

---

# 17. STORAGE PRIVADO

Directorio lógico:

`storage/contracts/`

Reglas:

- fuera del webroot;
- nombre aleatorio server-side, por ejemplo 32 hex + `.pdf`;
- DB guarda `pdf_stored_name`, no ruta pública;
- no usar nombre del candidato en filename físico;
- no aceptar path del request;
- escritura atómica temp → rename cuando sea posible;
- máximo de PDF generado F7: 10 MiB;
- PDF se entrega únicamente por ruta autenticada/controlada.

No symlinks públicos.

---

# 18. PERMISOS

Exactamente 2 permisos nuevos:

- `contracts.view`
- `contracts.manage`

Semántica:

### `contracts.view`

- listar contratos;
- ver contrato;
- ver templates/versiones;
- preview ya guardado;
- descargar PDF privado.

### `contracts.manage`

- crear template;
- activar/desactivar template;
- crear nueva versión;
- crear/editar draft;
- generar;
- modificar checklist permitido;
- finalizar.

Owner conserva bypass existente.

No crear permisos por estado, por tipo documental o por acción individual.

---

# 19. RUTAS Y UI ADMINISTRATIVA

Nuevo módulo lógico:

`app/Contracts/`

Nuevo route file único:

`app/Contracts/routes.php`

No routes públicas F7.

## 19.1 Rutas mínimas previstas

### Contratos

- `GET /admin/contracts`
- `POST /admin/contracts`
- `GET /admin/contracts/{id}`
- `POST /admin/contracts/{id}`
- `GET /admin/contracts/{id}/pdf`

`POST /admin/contracts/{id}` usa un `action` allowlisted:

- `save`
- `generate`
- `update_tasks`
- `finalize`

No crear una ruta POST diferente para cada acción mientras un único endpoint sea claro.

### Plantillas

- `GET /admin/contract-templates`
- `POST /admin/contract-templates`
- `GET /admin/contract-templates/{id}`
- `POST /admin/contract-templates/{id}`

Las mutaciones de template usan `action` allowlisted cuando corresponda.

## 19.2 Candidate detail

`GET /admin/candidates/{id}` puede mostrar sección contextual Contrato únicamente si el usuario puede ver contratos.

Si screening está aprobado y no existe finalized:

- mostrar enlace/CTA real hacia creación/contrato existente;
- no botón muerto.

No mover lógica contractual al route file de Candidates.

## 19.3 SSR

- HTML server-side;
- JS no obligatorio;
- forms tradicionales;
- reuse shell/layout/componentes CSS existentes;
- sin modal/wizard complejo;
- preview en la misma vista de detalle contractual.

---

# 20. PREVIEW

Preview no crea PDF ni cambia estado.

En `draft`, la vista muestra HTML generado internamente con los datos guardados actuales.

Reglas:

- misma función de resolución usada por `generate`;
- datos escapados;
- sin raw template HTML;
- watermark/texto visible `BORRADOR`;
- preview no es evidencia final;
- preview no tiene hash contractual.

No endpoint público de preview.

---

# 21. DESCARGA PDF

`GET /admin/contracts/{id}/pdf`:

- requiere auth;
- forced password guard existente;
- requiere `contracts.view` o owner bypass;
- solo `generated|finalized`;
- busca contrato por ID interno después de autorización;
- usa filename físico server-side;
- Content-Type `application/pdf`;
- Content-Disposition attachment con nombre humano seguro generado por servidor;
- no path traversal;
- 404/403 fail closed;
- no URL pública permanente.

---

# 22. SEGURIDAD

## 22.1 Auth / CSRF / permisos

Reutilizar exactamente F2/F3:

- auth único;
- SessionGuard;
- forced-password;
- CSRF;
- basePath;
- owner bypass.

No auth contractual paralelo.

## 22.2 XSS

- template = texto plano;
- valores = escape HTML;
- manual vars = texto plano;
- tareas/anexos = texto plano;
- HTML del snapshot solo lo genera código interno.

## 22.3 IDOR

No existe ruta contractual pública.

IDs contractuales solo en admin autenticado con permiso.

No aceptar candidate/unit/template IDs ocultos como autoridad cuando pueden derivarse server-side.

## 22.4 PDF

- remote disabled;
- no HTML arbitrario;
- no file URLs suministradas por request;
- storage privado;
- hash después del render;
- archivo parcial limpiado ante error.

## 22.5 Logs/errores

No loguear:

- cuerpo completo contractual;
- documento completo del candidato;
- PDF bytes;
- variables manuales sensibles;
- filesystem path completo.

Errores al usuario no exponen SQL, stack trace ni rutas internas.

---

# 23. RUNTIME / COMPOSER / INSTALLER

F7 añade una dependencia directa runtime:

`dompdf/dompdf:^3.1.6`

Cambios obligatorios de runtime:

- agregar `ext-dom` como requisito;
- conservar `ext-mbstring`;
- agregar `ext-gd` como requisito F7;
- conservar `fileinfo`;
- PHP sigue `>=8.3 <8.6`.

`RuntimeRequirements` e installer deben fallar de forma clara si faltan `dom` o `gd`.

CI debe solicitar explícitamente como mínimo:

`pdo, pdo_mysql, mbstring, dom, gd, fileinfo`.

No agregar ext-imagick/gmagick/zip como requisitos F7 sin evidencia nueva.

No agregar binarios.

---

# 24. LICENCIA / DISTRIBUCIÓN

El spike observó Dompdf `v3.1.6` bajo `LGPL-2.1-only`.

F7:

- consume la librería vía Composer;
- no planea modificar su código fuente;
- preserva archivos/licencias de vendor en el paquete final;
- F25 debe conservar revisión de notices/licencias de dependencias.

Esta sección es una decisión de distribución técnica, no una conclusión jurídica automatizada.

---

# 25. VALIDACIONES DE TEMPLATE

- name trim 1–190;
- document_type allowlist F7 exacta;
- body_template requerido;
- body_template máximo 250.000 caracteres;
- UTF-8 válido;
- placeholders con sintaxis válida;
- placeholder automático solo allowlist;
- placeholder manual `custom.*` declarado;
- variable manual declarada debe aparecer en body o se rechaza como ruido;
- máximo 50 manual variables;
- template inactivo no crea nuevos drafts;
- versiones nunca se editan/eliminan.

---

# 26. VALIDACIONES DE CONTRATO

- candidate ID válido y elegible;
- unit derivada del candidato;
- screening aprobado;
- tipo unidad ↔ tipo template compatible;
- monthly_rent > 0;
- administration_fee >= 0 si existe;
- start_date válida;
- duration_months 1–120;
- manual vars requeridas completas;
- tasks JSON válido y límites;
- annexes JSON válido y límites;
- template/version existentes;
- estado/transición válidos;
- generated/finalized no aceptan edición de contenido;
- finalized único por candidato F7.

---

# 27. PRUEBAS OBLIGATORIAS

## 27.1 Schema

- exactamente 3 tablas F7;
- columnas/FKs/índices esperados;
- template version UNIQUE;
- no tabla clauses;
- no tabla tasks;
- no tabla annexes;
- no lease/tenant;
- no auth paralelo.

## 27.2 Runtime/PDF

- Dompdf instalado en lock;
- Composer strict PASS;
- RuntimeRequirements exige dom/gd;
- installer muestra fallo entendible si faltan;
- PHP 8.3/8.4/8.5;
- UTF-8;
- CSS interno;
- página múltiple;
- header/footer;
- numeración;
- imagen local segura;
- remote disabled;
- output `%PDF-`;
- SHA-256 exacto;
- tamaño persistido;
- archivo privado.

## 27.3 Templates

- create template;
- duplicate name reject;
- type inválido reject;
- create v1;
- create v2 incremental;
- versión anterior inmutable;
- placeholder inválido reject;
- variable automática fuera allowlist reject;
- custom no declarada reject;
- variable declarada no usada reject;
- template inactivo bloquea nuevo draft.

## 27.4 Elegibilidad

- screening approved permite;
- pending/in_progress/rejected bloquean;
- candidate withdrawn bloquea;
- unit incompatible bloquea;
- unit maintenance/inactive/occupied bloquea;
- property inactive bloquea;
- unit ID forjado ignorado/rechazado;
- una draft activa por candidato.

## 27.5 Draft

- precarga económica desde unit solo como default;
- valor contractual puede diferir;
- no modifica units.monthly_price;
- fechas/duración;
- manual vars;
- tasks;
- annexes;
- preview BORRADOR;
- XSS escapado.

## 27.6 Generate

- draft→generated;
- revalida fuentes;
- resolved_variables snapshot;
- HTML snapshot;
- PDF privado;
- hash correcto;
- bytes/tamaño;
- generated_at;
- contenido queda inmutable;
- fallo de render deja draft y limpia parcial;
- segunda generación del mismo row bloqueada.

## 27.7 Finalize

- generated→finalized;
- draft→finalized bloqueado;
- finalized terminal;
- actor/fecha;
- doble finalized candidato bloqueado incluso bajo transacción;
- no crea lease;
- no crea tenant;
- no cambia candidate.status;
- no cambia unit.status.

## 27.8 Permisos/rutas

- anonymous blocked;
- forced-password blocked;
- view/manage split;
- owner bypass;
- CSRF mutaciones;
- basePath/subfolder;
- PDF download protegido;
- IDOR;
- 404/403 fail closed.

## 27.9 Regresión

Toda suite F1–F6 debe seguir GREEN en PHP 8.3/8.4/8.5.

---

# 28. MICROFASES PROPUESTAS

## F7.1 — Runtime + schema + permissions

- Dompdf productivo;
- ext-dom/ext-gd;
- migration 007;
- permisos;
- schema/runtime tests.

## F7.2 — Templates + versions

- ContractService base;
- template CRUD mínimo;
- versiones inmutables;
- placeholders/variables;
- admin SSR.

## F7.3 — Draft contractual + preview

- elegibilidad candidato/screening/unidad;
- draft;
- economics/dates;
- manual vars;
- tasks/annexes;
- preview seguro.

## F7.4 — Snapshot + PDF + hash

- ContractPdfRenderer;
- storage privado;
- generation transaction;
- SHA-256;
- download privado;
- immutability.

## F7.5 — Finalización + integración candidate detail

- generated→finalized;
- checklist;
- CTA/contexto candidato;
- finalized único;
- confirmar 0 lease/tenant/unit mutation.

## F7.6 — Security + visual + Cero Ruido + cierre

- IDOR/XSS/path traversal;
- permissions/CSRF/basePath;
- visual regression;
- PHP 8.3/8.4/8.5;
- full suite;
- Two Laws gate;
- checkpoint final.

Cada microfase:

`RED → VERIFY RED → MINIMAL GREEN → VERIFY GREEN → FULL CI → CONVERGE`.

---

# 29. BLOQUEADORES DE CIERRE

F7 NO puede cerrar si existe cualquiera:

- HTML arbitrario editable por usuario;
- editor Word/WYSIWYG;
- segunda auth;
- tenant/lease adelantado;
- cambio automático de candidate.status;
- cambio automático de unit.status;
- contrato sobre screening no aprobado;
- contrato sobre candidate withdrawn;
- raw remote resources habilitados en Dompdf;
- PDF dentro del webroot;
- path de PDF controlado por request;
- PDF sin hash;
- snapshot mutable después de generated;
- template version mutable;
- tabla clauses/tasks/annexes sin necesidad probada;
- upload documental F18 adelantado;
- dependencia runtime adicional no justificada;
- fallo F1–F6;
- PHP 8.3/8.4/8.5 no GREEN.

---

# 30. TWO LAWS GATE F7

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

Presupuesto esperado al cierre, salvo evidencia aprobada que obligue a cambiar esta SPEC:

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

# 31. GATE DE APROBACIÓN

Estado al escribir esta SPEC:

```text
F6: CLOSED / VERIFIED / MERGED
F7_SCOPE: APPROVED
F7_PRE_KISS: APPROVED
F7_DESIGN_IN_CHAT: APPROVED
F7_PDF_SPIKE: PASS
F7_SPEC_WRITTEN: YES
F7_SPEC_APPROVED_BY_USER: NO
F7_PLAN_WRITTEN: NO
F7_IMPLEMENTATION_ALLOWED: NO
```

Siguiente gate:

`USER APPROVES SPEC → WRITE F7 IMPLEMENTATION PLAN → USER APPROVES PLAN → RED F7.1`.

Presentar esta SPEC no autoriza todavía PLAN ni código productivo.