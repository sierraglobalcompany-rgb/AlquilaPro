# ALQUILA PRO — F6 ESTUDIO DE ASEGURADORA

**Fecha:** 2026-10-12 (America/Bogota)  
**Fase:** F6 — Estudio de aseguradora  
**Estado:** SPEC ESPECÍFICA — APROBADA  
**Base:** `main` @ `473370f143efcd8267f9b77001b97a4ce54ec1be`  
**Branch:** `impl/f6-screening-20261012`

---

# 1. AUTORIDAD Y OBJETIVO

Esta SPEC concreta F6 a partir de:

1. `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`;
2. `docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`;
3. `docs/checkpoints/F5_MERGED_2026-10-12.md`;
4. decisiones F5 ya mergeadas;
5. diseño F6 aprobado por el usuario el 2026-10-12.

Objetivo F6:

> Registrar y exponer de forma segura el estudio de aseguradora de un candidato ya existente, usando un modelo genérico de screening con SURA como proveedor inicial, sin crear candidato paralelo, auth paralelo, automatización de terceros ni duplicar el estado del candidato.

F6 comienza únicamente después de F5 `CLOSED / VERIFIED / MERGED`.

---

# 2. LEYES SUPREMAS APLICADAS A F6

## LEY 1 — KISS

F6 debe resolverse con el mínimo de piezas:

- **1 tabla nueva máxima**: `screening_processes`;
- **1 servicio nuevo máximo**: `ScreeningService`;
- **0 permisos nuevos**;
- **0 auth nuevo**;
- **0 route files nuevos**;
- **0 dependencias runtime nuevas**;
- **0 workers/colas/cron**;
- **0 integración API de SURA**;
- **0 tablas específicas `sura_*`**;
- **0 estados de aseguradora duplicados en `candidates.status`**.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Reutilizar obligatoriamente:

- `candidates` como única persona/proceso de candidatura;
- `CandidateService` para candidato y sus estados F5;
- `candidates.view` y `candidates.manage`;
- `app/Candidates/routes.php`;
- `resources/views/candidate_detail.php`;
- `resources/views/public_candidate_continue.php`;
- auth, sesión, forced-password y CSRF existentes;
- `basePath` existente.

No crear:

- módulo/provider CRUD independiente;
- `SuraService`;
- adapter HTTP;
- webhook;
- polling;
- tabla de historial de estados;
- tabla de soportes;
- motor de notificaciones;
- portal/login nuevo de candidato.

---

# 3. ALCANCE FUNCIONAL

F6 incluye:

- crear un único proceso de estudio para un candidato elegible;
- proveedor genérico con SURA como valor inicial sugerido en UI;
- producto opcional;
- estado persistido;
- fecha de inicio;
- correo de contacto opcional;
- URL personalizada opcional;
- resultado;
- observaciones internas;
- referencia/radicado de soporte textual;
- responsable interno;
- fechas de resultado y actualización;
- administración desde el detalle del candidato;
- visualización segura en el portal público ya existente del candidato;
- fallback claro cuando no exista URL externa;
- cierre terminal aprobado/no aprobado de aseguradora sin autoaprobar al candidato.

F6 NO incluye:

- automatización de SURA o terceros;
- consumo de API;
- scraping;
- envío automático de email/WhatsApp;
- upload documental adicional;
- decisión del propietario;
- contrato;
- lease/inquilino;
- cambio automático de `units.status`;
- cambio automático de `candidates.status` por resultado de aseguradora;
- reintentos/múltiples estudios por candidato;
- histórico de varios proveedores en paralelo.

Si una fase futura demuestra necesidad de reestudio, múltiple proveedor o historial, se extiende el modelo con evidencia real; no se anticipa en F6.

---

# 4. MODELO CANÓNICO

## 4.1 Tabla única `screening_processes`

Campos:

- `id` BIGINT UNSIGNED PK AUTO_INCREMENT;
- `candidate_id` BIGINT UNSIGNED NOT NULL FK → `candidates.id` ON DELETE CASCADE;
- `provider` VARCHAR(120) NOT NULL;
- `product` VARCHAR(190) NULL;
- `status` VARCHAR(32) NOT NULL;
- `contact_email` VARCHAR(190) NULL;
- `portal_url` VARCHAR(2048) NULL;
- `result` TEXT NULL;
- `notes` TEXT NULL;
- `support_reference` VARCHAR(255) NULL;
- `responsible_user_id` BIGINT UNSIGNED NULL FK → `users.id` ON DELETE SET NULL;
- `started_at` DATETIME NULL;
- `result_at` DATETIME NULL;
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
- `updated_at` DATETIME NULL.

Restricción:

- UNIQUE (`candidate_id`).

Razón:

- F6 V1 permite **un proceso vigente/histórico por candidato**;
- evita duplicar persona/candidatura;
- no crea entidad SURA específica;
- permite otro proveedor futuro sin migration de proveedor;
- conserva el resultado aunque el candidato luego avance a otra fase.

## 4.2 Sin tabla de historial

F6 no crea `screening_status_history`.

La trazabilidad mínima suficiente en V1 es:

- estado persistido;
- `started_at`;
- `result_at`;
- `updated_at`;
- `responsible_user_id`.

Una bitácora completa solo entra si una necesidad regulatoria/operativa real la demuestra.

---

# 5. ESTADOS DEL PROCESO

Estados exactos F6:

- `pending`;
- `in_progress`;
- `approved`;
- `rejected`.

Transiciones permitidas:

- creación → `pending`;
- `pending` → `in_progress`;
- `pending` → `approved`;
- `pending` → `rejected`;
- `in_progress` → `approved`;
- `in_progress` → `rejected`;
- `approved` → ninguna;
- `rejected` → ninguna.

`approved` y `rejected` son terminales en F6.

Se permite cerrar directamente desde `pending` para no obligar a registrar un estado intermedio artificial cuando el administrador recibe el resultado ya resuelto.

---

# 6. RELACIÓN CON `candidates.status`

## 6.1 Regla de fuente única

El proceso de aseguradora tiene su estado propio en `screening_processes.status`.

F6 **no añade** a `candidates.status`:

- `in_screening`;
- `insurer_approved`;
- `insurer_rejected`;
- equivalentes.

Razón: serían dos fuentes que podrían divergir.

## 6.2 Elegibilidad

Solo puede crearse el estudio cuando:

`candidate.status = pending_screening`.

El candidato llega a `pending_screening` mediante el flujo F5 ya existente.

## 6.3 Resultado no autoaprueba candidato

Cuando el screening pasa a `approved` o `rejected`:

- `candidates.status` permanece `pending_screening` en F6;
- no se ejecuta transición automática del candidato;
- no se cambia `units.status`;
- no se crea contrato;
- no se toma decisión de propietario.

F7 o una SPEC posterior decidirá explícitamente cómo consumir un resultado de screening aprobado/rechazado.

## 6.4 Candidato retirado

Si el candidato pasa a `withdrawn` por una transición F5 existente:

- el registro `screening_processes` se conserva como historial del proceso;
- no se permite nueva mutación F6;
- no se muestra CTA externo de aseguradora en el portal candidato;
- no se elimina automáticamente el proceso.

---

# 7. VALIDACIONES DE DATOS

## `provider`

- requerido;
- trim;
- 1–120 caracteres;
- SURA es sugerencia inicial de UI, no valor estructural de BD.

## `product`

- opcional;
- máximo 190 caracteres.

## `contact_email`

- opcional;
- si existe, email válido;
- máximo 190 caracteres.

## `portal_url`

- opcional;
- si existe, URL absoluta HTTPS;
- máximo 2048 caracteres;
- no se realiza request HTTP desde Alquila Pro;
- no se valida disponibilidad remota;
- no se siguen redirects.

## `result`

- opcional mientras `pending|in_progress`;
- obligatorio y no vacío para `approved|rejected`;
- se considera resumen apto para mostrar al candidato;
- observaciones privadas pertenecen a `notes`.

## `notes`

- opcional;
- privado administrativo;
- nunca se muestra al candidato.

## `support_reference`

- opcional;
- texto/radicado/referencia de soporte;
- máximo 255 caracteres;
- no es upload;
- documentos generales permanecen fuera del alcance F6.

## `responsible_user_id`

- se asigna al usuario autenticado que crea el proceso;
- no se expone como ID al candidato;
- F6 no incorpora UI de reasignación.

---

# 8. TIMESTAMPS

Al crear:

- `created_at = CURRENT_TIMESTAMP`;
- `updated_at = NULL`;
- `started_at = NULL` mientras status=`pending`;
- `result_at = NULL`.

Al pasar por primera vez a `in_progress`:

- `started_at` toma timestamp si estaba NULL.

Al cerrar directamente `pending → approved|rejected`:

- `started_at` toma timestamp si estaba NULL;
- `result_at` toma timestamp.

Al cerrar `in_progress → approved|rejected`:

- `result_at` toma timestamp;
- `started_at` se conserva.

En cada actualización válida:

- `updated_at` se actualiza.

Los timestamps de cierre no se regeneran porque estados terminales no reabren en F6.

---

# 9. SERVICIO DE DOMINIO

Máximo un servicio nuevo:

`AlquilaPro\Candidates\ScreeningService`

Responsabilidades:

- obtener proceso por candidato;
- crear proceso elegible;
- validar provider/product/contact/URL;
- aplicar allowlist/transiciones de status;
- exigir resultado terminal;
- controlar timestamps;
- conservar un único proceso por candidato;
- rechazar mutación si candidato ya no está en `pending_screening`;
- producir proyección pública segura para portal candidato.

No debe:

- llamar SURA;
- enviar email;
- tocar `units.status`;
- auto-transicionar `candidates.status`;
- administrar usuarios;
- gestionar uploads.

---

# 10. SUPERFICIE ADMINISTRATIVA

F6 reutiliza el módulo Candidatos F5.

## Lectura

`GET /admin/candidates/{id}`

Añade sección **Estudio de aseguradora**.

Permiso existente:

- `candidates.view` para leer.

Debe mostrar, si existe:

- proveedor;
- producto;
- estado;
- correo de contacto;
- URL configurada;
- resultado;
- notas;
- soporte/radicado;
- responsable de forma humana si ya existe acceso simple al nombre; si no, no se agrega un lookup complejo solo para F6;
- started/result/created/updated timestamps.

## Mutación

Ruta única KISS:

`POST /admin/candidates/{id}/screening`

Permiso:

- `candidates.manage`.

Protecciones existentes:

- sesión;
- forced-password;
- CSRF;
- candidate ownership/context;
- basePath.

La misma ruta crea el proceso cuando no existe o aplica una actualización/transición cuando ya existe.

No existe:

- DELETE;
- provider CRUD;
- route separada por cada campo;
- endpoint AJAX.

## Terminal

Si el proceso está `approved|rejected`:

- el detalle lo muestra en solo lectura;
- no renderiza formulario de mutación F6.

---

# 11. PORTAL CANDIDATO

No se crea nueva autenticación ni nueva ruta pública.

Se reutiliza:

`GET /apply/continue/{token}`

La página añade sección **Estudio de aseguradora** cuando existe proceso.

Proyección pública permitida:

- provider;
- product;
- status con etiqueta humana;
- contact_email, si existe;
- portal_url, si existe y el proceso/candidato permiten mostrarla;
- result, cuando sea terminal;
- started_at;
- result_at.

Nunca exponer:

- `screening_processes.id`;
- `candidate_id`;
- `responsible_user_id`;
- `notes`;
- `support_reference` salvo futura decisión explícita;
- SQL;
- rutas internas;
- token hash.

## CTA URL externa

El botón/enlace externo se muestra únicamente si:

- `portal_url` existe y es HTTPS válida;
- candidato sigue `pending_screening`;
- screening está `pending|in_progress`.

Debe usar protección contra fuga del capability token por Referer, por ejemplo:

- `rel="noopener noreferrer"` cuando abra nueva pestaña;
- sin concatenar token a la URL externa.

Al estar `approved|rejected` no se necesita CTA externo.

## Fallback sin URL

Si no existe `portal_url`:

- la sección sigue mostrando proveedor y estado;
- muestra texto neutral indicando que el administrador gestiona el estudio y que no hay enlace externo disponible.

No se inventa URL ni integración.

---

# 12. SEGURIDAD Y PRIVACIDAD

## XSS

Todo campo del screening se escapa en SSR.

## CSRF

La mutación admin reutiliza el middleware CSRF existente.

## IDOR

La ruta admin opera únicamente sobre el candidato solicitado y autenticado/autorizado según las reglas F5.

El portal público obtiene screening exclusivamente a través del candidato resuelto por token válido; nunca por screening ID público.

## URLs

- solo HTTPS;
- no request server-side;
- no iframe arbitrario;
- no token interno anexado;
- salida HTML escapada;
- `noreferrer` para evitar fuga del token presente en URL de la página actual.

## Logs/errores

Mantener la redacción F5 del raw continuation token.

Errores F6 no deben revelar:

- SQL;
- IDs internos;
- token;
- notas privadas;
- filesystem.

---

# 13. SUBFOLDER / BASE PATH

Todas las rutas/forms/redirects F6 deben reutilizar `basePath`.

Pruebas deben cubrir al menos:

- admin candidate detail en subfolder;
- POST screening en subfolder;
- portal candidato continuation en subfolder;
- enlace externo no recibe prefijo basePath por ser URL absoluta externa.

---

# 14. UX MÍNIMA

## Admin

Dentro del detalle ya existente del candidato:

- título “Estudio de aseguradora”;
- un formulario SSR simple;
- SURA prellenado como provider al crear;
- status select según transición válida;
- URL/contacto/producto/resultado/notas/soporte;
- sin modal, wizard, SPA ni JavaScript obligatorio.

## Candidato

Dentro de su página de continuación existente:

- estado visible;
- proveedor/producto;
- enlace externo cuando exista;
- fallback cuando no exista;
- resultado final cuando aplique;
- sin login nuevo.

Reutilizar CSS/primitives F3–F5. No crear framework o módulo visual nuevo.

---

# 15. PRUEBAS OBLIGATORIAS

## 15.1 Schema / arquitectura

- existe exactamente una tabla F6 `screening_processes`;
- FK candidato correcta;
- FK responsable correcta;
- UNIQUE candidate_id;
- no tabla `sura_*`;
- no permisos nuevos;
- no auth nuevo;
- no dependencia nueva.

## 15.2 Servicio

- no crea screening para candidato distinto de `pending_screening`;
- crea exactamente uno;
- duplicado de candidato se rechaza/actualiza vía flujo canónico, no crea segundo proceso;
- provider requerido;
- email inválido rechazado;
- HTTP/no-HTTPS rechazado para portal URL;
- pending/in_progress permiten result vacío;
- approved/rejected exigen result;
- transiciones exactas;
- terminal no reabre;
- timestamps correctos;
- responsable asignado;
- withdrawal posterior bloquea mutaciones y conserva proceso;
- resultado de screening no cambia `candidates.status`;
- resultado no cambia `units.status`.

## 15.3 Admin routes/UI

- anónimo bloqueado;
- forced-password respetado;
- `candidates.view` ve;
- `candidates.manage` muta;
- view sin manage no muta;
- owner bypass existente funciona;
- CSRF requerido;
- basePath correcto;
- XSS escapado;
- terminal solo lectura.

## 15.4 Portal candidato

- token inválido/expirado mantiene fail-closed F5;
- screening solo corresponde al candidato del token;
- no expone IDs internos/notes/support/responsible;
- URL HTTPS mostrada cuando corresponde;
- enlace usa `noreferrer`/protección equivalente;
- sin URL muestra fallback;
- terminal muestra resultado y oculta CTA externo;
- withdrawal oculta CTA externo;
- subfolder correcto.

## 15.5 Regresión

Suite completa F1–F5 debe permanecer GREEN en PHP 8.3/8.4/8.5.

---

# 16. MICROFASES PROPUESTAS

La SPEC no autoriza implementación hasta existir PLAN aprobado.

Orden esperado del PLAN:

- **F6.1** — migration + schema contracts;
- **F6.2** — `ScreeningService` + estados/validaciones;
- **F6.3** — administración en candidate detail;
- **F6.4** — portal candidato + URL/fallback/referrer hardening;
- **F6.5** — security/visual regression + Cero Ruido + cierre.

Cada microfase:

`RED → VERIFY RED → MINIMAL GREEN → VERIFY GREEN → FULL CI → CONVERGE`.

---

# 17. GATE DE CIERRE F6

F6 no puede cerrar si ocurre cualquiera:

- candidato duplicado;
- estado de aseguradora duplicado en `candidates.status`;
- tabla/provider específico SURA;
- integración HTTP sin API oficial;
- autoaprobación del candidato;
- cambio automático de unidad;
- permiso nuevo sin necesidad;
- auth/login nuevo;
- exposición de token/IDs/notas privadas;
- URL externa sin protección de Referer;
- dependencia runtime nueva no demostrada;
- suite F1–F5 rota.

Gate final:

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

FAIL bloquea cierre y merge.

---

# 18. FUERA DE ALCANCE / FUTURO

Expresamente diferido:

- API oficial de aseguradora;
- webhooks;
- reintentos/polling;
- múltiples estudios/reestudios;
- adjuntos propios de screening;
- notificaciones automáticas;
- decisión del propietario;
- creación de contrato;
- lease/inquilino;
- historial regulatorio completo;
- dashboard/analytics de screening.

Estas piezas requieren su propia evidencia/SPEC futura y no se anticipan en F6.

---

# 19. ESTADO DE APROBACIÓN

```text
F5: CLOSED / VERIFIED / MERGED
F6_SCOPE: APPROVED
F6_PRE_KISS: APPROVED
F6_DESIGN_IN_CHAT: APPROVED
F6_SPEC_WRITTEN: YES
F6_SPEC_APPROVED_BY_USER: YES
F6_PLAN_WRITTEN: YES
F6_IMPLEMENTATION_ALLOWED: NO
```

Siguiente gate:

`USER REVIEW/APPROVAL OF F6 IMPLEMENTATION PLAN → RED F6.1`.
