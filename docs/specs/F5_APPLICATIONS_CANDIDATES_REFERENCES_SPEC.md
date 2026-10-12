# ALQUILA PRO — F5 APLICACIONES + CANDIDATOS + REFERENCIAS — SPEC

**Fecha:** 2026-10-12 (America/Bogota)  
**Estado:** DESIGN / SPEC — pendiente aprobación humana antes de PLAN o código  
**Base:** `main` post F4 MERGED (`648401a4927bfeb7525421a4edd2b5f2c60f06c4`)  
**Branch:** `impl/f5-candidates-20261012`

---

# 0. AUTORIDAD

Esta SPEC concreta F5 sin sustituir la SPEC/PLAN maestras V1.2.

Orden de autoridad para F5:

`SPEC maestra V1.2 → PLAN maestro V1.2 → README/decisiones canónicas → esta SPEC F5 → PLAN F5 futuro → checkpoint → tests/CI → código`.

Hereda sin reabrir:

- F2 auth único;
- F3 shell/branding/UI;
- F4 `properties`, `units`, `unit_media`, `public_key`, catálogo y publicabilidad viva.

F4 está CLOSED / VERIFIED / MERGED. F5 no puede duplicar sus fuentes de inventario, precio, disponibilidad, media ni auth.

---

# 1. OBJETIVO

Convertir una visita del catálogo en el **único candidato operativo** del proceso de arrendamiento, sin CRM, lead ni application paralelos.

Resultado esperado:

1. visitante abre una unidad publicable;
2. pulsa **Aplicar**;
3. inicia un candidato vinculado a esa unidad;
4. recibe un enlace criptográfico de continuación;
5. completa datos, referencias y documentos;
6. acepta tratamiento de datos;
7. envía la candidatura;
8. administración la revisa en el mismo sistema;
9. F6 continúa desde ese candidato, sin copiarlo a otra entidad.

---

# 2. PRE-KISS / LEYES

## LEY 1 — KISS

F5 debe usar el mínimo razonable de piezas.

Presupuesto objetivo:

- +4 tablas;
- +2 permisos;
- +2 servicios de dominio como máximo;
- +0 dependencias runtime;
- +0 npm;
- +0 login/auth alterno;
- +0 tabla `lead`;
- +0 tabla `applications`/`rental_applications`;
- +0 motor de email/notificaciones;
- +0 workflow engine genérico.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`.

Decisiones de convergencia:

- persona + candidatura activa = `candidate`;
- unidad objetivo = FK a `units` existente;
- CTA `Aplicar` usa `units.public_key` existente;
- auth staff reutiliza F2;
- candidato público usa token de continuación, no login;
- documentos F5 tienen ciclo privado propio y **no** reutilizan `unit_media`;
- requisitos comerciales de F4 continúan como texto; requisitos documentales estructurados pertenecen a F5.

---

# 3. ALCANCE

## 3.1 Incluye

- CTA `Aplicar` en detalle público solo cuando la unidad sea publicable;
- candidato único vinculado a una unidad de interés;
- formulario público de inicio;
- continuación mediante token seguro;
- nombre;
- tipo y número de documento;
- fecha de nacimiento opcional cuando aplique;
- celular/WhatsApp;
- correo;
- dirección actual;
- información laboral;
- ocupantes;
- mascotas declaradas;
- referencias repetibles;
- requisitos documentales configurables por unidad;
- documentos privados del candidato;
- consentimiento de tratamiento de datos;
- estados F5;
- listado/detalle administrativo;
- regeneración administrativa del token;
- cambio controlado de unidad de interés antes del punto de bloqueo;
- soporte subfolder;
- CSRF en mutaciones;
- rate-limit básico sin nueva infraestructura;
- pruebas de privacidad, token, IDOR, MIME y regresión F1–F4.

## 3.2 No incluye

- segundo login;
- cuentas de candidato;
- tabla de sesiones de candidato;
- lead/CRM;
- application paralela;
- historial multi-application por persona en F5;
- email automático;
- WhatsApp automático;
- OTP;
- estudio SURA/F6;
- decisión automática;
- contrato/F7;
- tenant/lease/F8;
- documentos contractuales privados de F18;
- OCR;
- firma electrónica;
- antivirus externo;
- storage cloud;
- workflow/event bus/colas.

---

# 4. DECISIÓN CENTRAL — CANDIDATE-CENTRIC

## 4.1 Una sola entidad operativa

No existe `lead` ni `application` separado.

`candidates` representa simultáneamente:

- la persona candidata;
- su proceso activo de aplicación;
- la unidad de interés actual;
- su estado de avance F5.

La unicidad de persona en F5 es:

`UNIQUE(document_type, document_number)`.

`document_type` y `document_number` se normalizan antes de persistir.

## 4.2 Una sola unidad de interés activa

Un candidato tiene exactamente una `unit_id` activa en F5.

Puede cambiarla únicamente si:

- estado = `new` o `incomplete`;
- `submitted_at IS NULL`;
- no tiene documentos cargados;
- la nueva unidad sigue siendo publicable.

Después del primer documento cargado o del envío final, la unidad queda bloqueada para el candidato.

Un cambio posterior solo puede hacerlo administración mediante una acción explícita futura si se demuestra necesidad. F5 no implementa historial multi-unidad.

## 4.3 Candidato existente intentando aplicar otra vez

El formulario público nunca revela si un documento ya existe.

Si llega un documento ya registrado:

- no crea duplicado;
- no devuelve nombre, correo, unidad ni estado;
- no entrega ni regenera token;
- responde de forma genérica indicando que, si ya existe un proceso, debe usar su enlace de continuación o contactar administración.

Recuperar/regenerar token requiere administración en F5. Email/OTP automático queda fuera hasta existir infraestructura real.

---

# 5. MODELO DE DATOS MÍNIMO

F5 agrega exactamente cuatro tablas.

## 5.1 `candidates`

Campos mínimos:

- `id` BIGINT UNSIGNED PK;
- `unit_id` BIGINT UNSIGNED NOT NULL FK → `units.id`;
- `document_type` VARCHAR(24) NOT NULL;
- `document_number` VARCHAR(64) NOT NULL;
- `full_name` VARCHAR(190) NOT NULL;
- `birth_date` DATE NULL;
- `phone` VARCHAR(50) NOT NULL;
- `email` VARCHAR(190) NOT NULL;
- `current_address` VARCHAR(255) NULL;
- `employment_status` VARCHAR(80) NULL;
- `employer_name` VARCHAR(190) NULL;
- `occupation` VARCHAR(190) NULL;
- `monthly_income` DECIMAL(12,2) NULL;
- `employment_notes` TEXT NULL;
- `occupants_count` SMALLINT UNSIGNED NULL;
- `occupants_notes` TEXT NULL;
- `pets_declared` TINYINT(1) NOT NULL DEFAULT 0;
- `pets_notes` TEXT NULL;
- `status` VARCHAR(32) NOT NULL;
- `continuation_token_hash` CHAR(64) NOT NULL;
- `continuation_token_expires_at` DATETIME NOT NULL;
- `consent_at` DATETIME NOT NULL;
- `submitted_at` DATETIME NULL;
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
- `updated_at` DATETIME NULL.

Restricciones:

- UNIQUE (`document_type`, `document_number`);
- UNIQUE (`continuation_token_hash`);
- FK `unit_id` → `units.id`;
- no `public_key` adicional para candidato;
- no password;
- no user/session FK para flujo público.

## 5.2 `candidate_references`

Referencias repetibles.

Campos:

- `id` BIGINT UNSIGNED PK;
- `candidate_id` BIGINT UNSIGNED NOT NULL FK → `candidates.id` ON DELETE CASCADE;
- `reference_type` VARCHAR(40) NULL;
- `name` VARCHAR(190) NOT NULL;
- `relationship` VARCHAR(120) NULL;
- `phone` VARCHAR(50) NOT NULL;
- `email` VARCHAR(190) NULL;
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
- `updated_at` DATETIME NULL.

No se crea entidad global `contacts` en F5.

## 5.3 `unit_application_requirements`

Configura únicamente requisitos documentales estructurados por unidad.

Campos:

- `id` BIGINT UNSIGNED PK;
- `unit_id` BIGINT UNSIGNED NOT NULL FK → `units.id` ON DELETE CASCADE;
- `code` VARCHAR(80) NOT NULL;
- `label` VARCHAR(190) NOT NULL;
- `description` TEXT NULL;
- `is_required` TINYINT(1) NOT NULL DEFAULT 1;
- `is_active` TINYINT(1) NOT NULL DEFAULT 1;
- `sort_order` INT UNSIGNED NOT NULL DEFAULT 0;
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
- `updated_at` DATETIME NULL.

Restricción:

- UNIQUE (`unit_id`, `code`).

No duplica `units.requirements`: F4 conserva texto comercial público; esta tabla define documentos operativos F5.

## 5.4 `candidate_documents`

Archivos privados aportados por candidato.

Campos:

- `id` BIGINT UNSIGNED PK;
- `candidate_id` BIGINT UNSIGNED NOT NULL FK → `candidates.id` ON DELETE CASCADE;
- `requirement_id` BIGINT UNSIGNED NOT NULL FK → `unit_application_requirements.id`;
- `stored_name` VARCHAR(190) NOT NULL;
- `original_name` VARCHAR(255) NULL;
- `mime_type` VARCHAR(80) NOT NULL;
- `size_bytes` BIGINT UNSIGNED NOT NULL;
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
- `updated_at` DATETIME NULL.

Restricción F5:

- UNIQUE (`candidate_id`, `requirement_id`) — un archivo vigente por requisito; cargar de nuevo reemplaza el anterior de forma segura.

No se reutiliza `unit_media` porque privacidad y ciclo de vida son distintos.

---

# 6. ESTADOS F5

Estados canónicos de F5:

- `new`;
- `incomplete`;
- `verification`;
- `pending_screening`;
- `withdrawn`.

Reglas:

- creación pública → `new`;
- al guardar avances parciales → `incomplete`;
- envío final válido → `verification`;
- administración puede avanzar `verification → pending_screening`;
- `withdrawn` cierra edición pública;
- F6 extiende el ciclo desde `pending_screening` sin crear proceso/candidato paralelo.

F5 no incorpora todavía estados de aseguradora, decisión propietario o contrato.

---

# 7. TOKEN DE CONTINUACIÓN

## 7.1 Generación

Token raw:

`bin2hex(random_bytes(32))`.

Persistencia:

`hash('sha256', raw_token)` en `continuation_token_hash`.

Nunca se almacena el token raw.

## 7.2 Semántica

El token:

- identifica un único candidato;
- no es login/auth paralelo;
- es no enumerable;
- tiene expiración;
- puede reutilizarse durante su vigencia para continuar el mismo proceso;
- puede rotarse por administración;
- al rotarse invalida inmediatamente el anterior.

Duración inicial propuesta F5: **7 días**.

La duración es configuración de dominio en código F5, no nueva tabla/settings hasta demostrar necesidad.

## 7.3 Expiración

Token expirado:

- no revela candidato;
- devuelve respuesta genérica/404 segura;
- no permite lectura ni mutación;
- recuperación requiere regeneración administrativa en F5.

---

# 8. FLUJO PÚBLICO

## 8.1 CTA catálogo

El detalle público de F4 añade **Aplicar** únicamente si la unidad sigue siendo publicable en el momento de renderizar.

Ruta inicial:

`GET /apply/{publicKey}`.

No se usa `unit_id` público.

## 8.2 Crear candidato

`POST /apply/{publicKey}`.

Debe:

1. revalidar publicabilidad viva;
2. validar CSRF;
3. aplicar throttle básico;
4. validar campos iniciales;
5. exigir consentimiento;
6. normalizar documento;
7. rechazar duplicado sin revelar existencia;
8. crear candidato + token en transacción;
9. mostrar/redirigir una sola vez a URL de continuación con token raw.

Campos iniciales mínimos:

- document_type;
- document_number;
- full_name;
- phone;
- email;
- consentimiento.

## 8.3 Continuar

`GET /apply/continue/{token}`.

Muestra únicamente el candidato asociado al token válido.

`POST /apply/continue/{token}` actualiza datos propios mientras estado sea editable.

No usa ID de candidato en URL pública.

## 8.4 Cambio de unidad

`POST /apply/continue/{token}/unit`.

Entrada: `public_key` de nueva unidad.

Solo permite cambio bajo las reglas de §4.2.

No crea segunda candidatura.

## 8.5 Referencias

`POST /apply/continue/{token}/references`.

El formulario envía el conjunto actual de referencias y el servicio lo reemplaza transaccionalmente.

Razón KISS:

- evita CRUD público de IDs de referencia;
- evita rutas delete/update individuales;
- reduce superficie IDOR.

## 8.6 Documentos

`POST /apply/continue/{token}/documents/{requirementCode}`.

El código debe pertenecer a la unidad actual del candidato y estar activo.

Reemplazar un documento:

- valida nuevo archivo;
- persiste de forma segura;
- sustituye registro;
- elimina archivo anterior solo cuando el nuevo quedó confirmado;
- no deja archivos huérfanos ante error.

## 8.7 Envío final

`POST /apply/continue/{token}/submit`.

Requisitos de envío:

- token vigente;
- estado editable;
- consentimiento registrado;
- datos básicos requeridos completos;
- información laboral mínima completa si fue iniciada;
- todos los `unit_application_requirements.is_required=1 AND is_active=1` satisfechos;
- archivos válidos existentes;
- no requiere que la unidad siga publicable si el candidato fue creado válidamente antes.

Al enviar:

- `status = verification`;
- `submitted_at = CURRENT_TIMESTAMP`;
- edición pública queda bloqueada salvo futura regla explícita.

F5 no reserva ni cambia automáticamente `units.status`.

---

# 9. UNIDAD DESPUBLICADA DURANTE EL PROCESO

Regla:

- una unidad no publicable no acepta candidatos **nuevos**;
- candidato ya creado puede continuar y enviar su proceso original;
- CTA desaparece automáticamente por F4;
- documentos y candidato existente no se eliminan;
- F5 no cambia automáticamente la unidad a `in_process`.

Razón:

la candidatura y la disponibilidad comercial tienen ciclos distintos; mezclar ambos generaría pérdida de datos y estados implícitos.

---

# 10. REQUISITOS Y DOCUMENTOS

## 10.1 Configuración administrativa

Los requisitos se administran desde contexto de la unidad existente; no se crea CMS/módulo independiente.

Permiso: `candidates.manage`.

Acciones mínimas:

- crear requisito;
- editar label/description/required/active/order;
- inactivar;
- ordenar.

No borrar físicamente requisito que ya tenga documentos asociados; se inactiva.

## 10.2 Tipos de archivo F5

Permitidos inicialmente:

- `application/pdf`;
- `image/jpeg`;
- `image/png`.

No SVG, HTML, PHP ni ejecutables.

Máximo inicial: **10 MiB por archivo**.

Validación por MIME real (`finfo`), no extensión declarada.

## 10.3 Storage

Directorio:

`storage/candidate-documents/`.

Reglas:

- fuera del webroot;
- nombre aleatorio;
- permisos restrictivos;
- nunca se sirve como archivo estático directo;
- no ruta pública por token para descargar en F5;
- acceso de lectura solo desde administración autenticada y autorizada.

---

# 11. ADMINISTRACIÓN

Permisos exactamente:

- `candidates.view`;
- `candidates.manage`.

Owner conserva bypass existente.

## 11.1 Launcher

Apps muestra **Candidatos** solo a usuario autorizado cuando las rutas F5 existan.

## 11.2 Listado

`GET /admin/candidates`.

Muestra al menos:

- nombre;
- documento en contexto privado;
- unidad;
- estado;
- teléfono;
- fecha creación/envío.

Filtros mínimos solo si aportan valor demostrado durante implementación: estado y unidad.

## 11.3 Detalle

`GET /admin/candidates/{id}`.

Muestra:

- datos del candidato;
- unidad;
- laboral;
- ocupantes;
- mascotas;
- referencias;
- requisitos/documentos;
- consentimiento;
- estado;
- timestamps.

## 11.4 Estado

`POST /admin/candidates/{id}/status`.

F5 permite únicamente transiciones válidas dentro de sus estados.

No autoaprueba aseguradora ni propietario.

## 11.5 Regenerar token

`POST /admin/candidates/{id}/token/regenerate`.

Requiere `candidates.manage` + CSRF.

Genera nuevo token, invalida anterior y muestra el nuevo enlace raw únicamente en la respuesta inmediata.

No envía email/WhatsApp en F5.

## 11.6 Documento privado

`GET /admin/candidates/{id}/documents/{documentId}`.

Debe comprobar:

- sesión;
- `candidates.view`;
- documento pertenece al candidato solicitado;
- archivo existe;
- MIME real coincide con MIME persistido;
- `nosniff`;
- `Cache-Control: private, no-store`.

No IDOR cross-candidate.

---

# 12. SERVICIOS DE DOMINIO

Máximo esperado:

## `CandidateService`

Responsable de:

- crear/encontrar candidato;
- unicidad de documento;
- token hash/expiración/rotación;
- datos personales/laborales;
- unidad de interés;
- referencias;
- estado/transiciones;
- consentimiento;
- validación de envío;
- consultas administrativas seguras.

## `CandidateDocumentService`

Responsable de:

- requisitos de unidad;
- uploads;
- MIME/tamaño/nombre/storage;
- reemplazo/rollback;
- acceso privado de archivos;
- verificación de requisitos documentales satisfechos.

No repositories/DTO/interfaces/factories mientras no exista variación real.

---

# 13. PRIVACIDAD Y SEGURIDAD

## 13.1 Público

Nunca exponer:

- candidate.id;
- documento de otro candidato;
- correo/teléfono de candidato existente;
- status de candidato existente;
- token hash;
- nombres físicos de archivos;
- rutas storage;
- IDs internos de documento en formulario público.

## 13.2 CSRF

Todas las mutaciones públicas/admin reutilizan el CSRF existente.

No se crea segundo middleware CSRF F5.

## 13.3 Rate-limit básico

F5 implementa throttle de creación/submit usando mecanismo local simple de sesión/ventana temporal existente o mínimo.

No nueva tabla, Redis, worker ni dependencia.

F22 hará hardening específico posterior.

## 13.4 XSS

Todo dato de candidato, referencia, requisito y filename se escapa en SSR.

## 13.5 IDOR

- público: candidato solo por token;
- admin: candidate/doc IDs siempre se validan por pertenencia y permiso;
- requisito público: `requirementCode` se valida contra unidad del candidato.

## 13.6 Logs/errores

Errores públicos no revelan:

- existencia de candidato;
- SQL;
- filesystem;
- hash/token;
- datos privados.

---

# 14. SUBFOLDER / BASE PATH

Todas las rutas, redirects y forms deben usar `basePath` existente.

Pruebas F5 deben cubrir al menos:

- `/sub/apply/{publicKey}`;
- continuación;
- CTA desde catálogo;
- redirects post-form;
- admin candidates;
- documento privado.

No hardcodear `/` absoluto cuando rompa instalación en subcarpeta.

---

# 15. UX MÍNIMA

## Público

Mobile-first, SSR, mismo branding público F4.

Secciones de continuación:

1. datos básicos;
2. laboral;
3. hogar/ocupantes/mascotas;
4. referencias;
5. documentos;
6. revisión y envío.

No wizard SPA obligatorio. Puede ser una sola página por secciones con anchors/formularios si resulta más simple.

## Privado

Reutiliza shell F3.

No dashboard nuevo en F5; solo módulo Candidatos accionable.

---

# 16. REGLAS DE VALIDACIÓN

Mínimas:

- documento tipo/número requeridos;
- nombre requerido;
- teléfono requerido;
- email válido requerido;
- ingreso mensual, si existe, >= 0;
- ocupantes, si existe, >= 0;
- consentimiento obligatorio al crear;
- pets_declared boolean estricto;
- token exacto/seguro;
- estados allowlist;
- unidad FK existente;
- nueva aplicación solo sobre unidad publicable;
- cambio de unidad solo bajo §4.2;
- requirementCode pertenece a unidad;
- archivos por MIME/tamaño.

No se fija en esta SPEC un número mínimo arbitrario de referencias porque la SPEC/PLAN maestras no lo definen. F5 debe permitir capturarlas y probar su persistencia/validación.

---

# 17. PRUEBAS OBLIGATORIAS

## Schema / arquitectura

- F5 crea exactamente 4 tablas;
- no crea `leads` ni `applications`;
- UNIQUE documento;
- UNIQUE token hash;
- FKs correctas;
- exactamente 2 permisos F5;
- Composer/npm delta = 0.

## CandidateService

- candidato nuevo válido;
- documento duplicado no duplica persona;
- documento duplicado no filtra existencia en capa pública;
- token raw nunca persistido;
- token hash resuelve candidato;
- token expirado falla cerrado;
- rotación invalida anterior;
- estados/transiciones;
- cambio de unidad permitido/bloqueado;
- unidad destino debe publicable;
- unidad despublicada no invalida candidato existente;
- final submit bloquea edición pública.

## Público

- CTA Aplicar solo en unidad publicable;
- GET/POST apply;
- unidad retirada no acepta candidato nuevo;
- candidato ya creado puede continuar si unidad se retira;
- duplicate document respuesta no enumerable;
- continuación token válido/expirado/inválido;
- CSRF;
- subfolder;
- XSS escaped;
- no internal candidate IDs.

## Referencias

- múltiples referencias;
- reemplazo transaccional del conjunto;
- ninguna referencia de otro candidato visible/editable.

## Requisitos/documentos

- crear/editar/inactivar/ordenar requisito;
- required vs optional;
- code único por unidad;
- PDF/JPEG/PNG válidos;
- SVG/PHP/fake MIME/oversize rechazados;
- storage fuera webroot;
- reemplazo sin orphan file;
- requisito de otra unidad rechazado;
- submit requiere documentos required activos;
- documento admin no IDOR;
- headers privados/nosniff.

## Admin

- auth requerido;
- forced password change;
- `candidates.view` read-only;
- `candidates.manage` muta;
- owner bypass;
- listado/detalle;
- estado;
- regenerar token + CSRF;
- launcher condicionado a permiso;
- subfolder.

## Regresión

- F1–F4 completas verdes;
- catálogo/public media no se rompen;
- auth único se mantiene;
- no botón Aplicar muerto;
- no segunda disponibilidad/precio.

---

# 18. MICROFASES PROPUESTAS

La implementación futura debe permanecer checkpointable.

## F5.1 — Schema + permisos

- migration 005;
- 4 tablas exactas;
- 2 permisos;
- tests schema/permissions.

## F5.2 — Candidate domain + token

- CandidateService;
- create/update/status;
- token hash/expiry/rotation;
- política documento único;
- política unidad única.

## F5.3 — Inicio/continuación pública

- `/apply/{publicKey}`;
- `/apply/continue/{token}`;
- CSRF/subfolder/throttle básico;
- no enumeration.

## F5.4 — Datos extendidos + referencias

- laboral;
- ocupantes;
- mascotas;
- referencias transaccionales;
- consentimiento.

## F5.5 — Requisitos + documentos privados

- config por unidad;
- CandidateDocumentService;
- MIME/storage/reemplazo;
- acceso admin privado.

## F5.6 — Submit + administración

- completeness;
- `verification`;
- listado/detalle;
- transitions;
- token regenerate.

## F5.7 — CTA Aplicar + convergencia catálogo

- CTA real en detalle F4;
- reglas de publicabilidad;
- candidato existente/nueva unidad;
- ningún botón/ruta futura.

## F5.8 — Seguridad + QA + Two Laws + cierre

- IDOR;
- privacy;
- subfolder;
- XSS;
- full CI 8.3/8.4/8.5;
- visual QA;
- Cero Ruido;
- `F5_CLOSED`;
- review;
- merge solo con aprobación explícita.

---

# 19. GATE DE CIERRE F5

F5 solo puede cerrar si:

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
ADDED_TABLES: 4
ADDED_PERMISSIONS: 2
NEW_RUNTIME_DEPENDENCIES: 0
NEW_NPM: 0
SECOND_AUTH: NO
LEAD_TABLE: NO
APPLICATION_TABLE: NO
CANDIDATE_SOURCE_OF_TRUTH: ONE
PUBLIC_CANDIDATE_ID_EXPOSURE: NO
CANDIDATE_DOCUMENTS_OUTSIDE_WEBROOT: YES
F1_F4_REGRESSION: PASS
PHP_8_3: PASS
PHP_8_4: PASS
PHP_8_5: PASS
```

Un FAIL bloquea cierre/merge.

---

# 20. OPEN QUESTIONS

No quedan preguntas funcionales bloqueantes para escribir el PLAN una vez esta SPEC sea aprobada.

Los detalles que pueden decidirse durante PLAN/TDD sin reabrir arquitectura son:

- copy exacto de formularios;
- composición visual de secciones;
- límites de longitud dentro de rangos razonables;
- orden visual de campos;
- mensajes de validación;
- si filtros admin mínimos se implementan en F5.6 o se omiten por YAGNI.

Cualquier cambio que agregue una quinta tabla F5, segundo auth, lead/application, motor de notificaciones o historial multi-unidad requiere volver a SPEC antes de código.
