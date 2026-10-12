# AlquilaPro — Checkpoint F5.5

Fecha: 2026-10-12

## Estado congelado

- Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
- Rama: `impl/f5-candidates-20261012`
- PR: `#8` — mantener **Draft**, no mergear.
- Último HEAD funcional antes de este checkpoint: `978b6cf66a07ae9cac33581663185bfa32ca3fcf`
- Fase activa: `F5.5 — documentos/requisitos de candidatos`.
- **NO abrir F5.6** hasta cerrar y certificar F5.5.
- Leyes vigentes: KISS, simple, eficiente, optimizado, sin capas ni arquitectura innecesaria; reutilizar antes de crear; no parche sobre parche; eliminar ruido solo tras comprobar que es seguro.

## Bloques cerrados

### B01 — integridad física de documentos requeridos

Estado: **CLOSED / VERIFIED**.

- `CandidateService::submitByToken()` valida el documento requerido contra el archivo físico real mediante la misma instancia de `CandidateDocumentService`.
- Casos certificados: archivo válido, archivo borrado y archivo manipulado.
- CI PASS en PHP 8.3 / 8.4 / 8.5.
- Run verde de referencia: `38284137434`.
- PHP 8.3: `185 tests / 1206 assertions`.

### B02 — códigos de requisito seguros para ruta pública

Estado: **CLOSED / VERIFIED**.

- `requirementCode` queda limitado a una clave estable de un solo segmento: letras, números, `_` y `-`, máximo 80 caracteres.
- No se fuerza lowercase y no se añadió normalizador/clase/capa nueva.
- Validador único reutilizado en creación, comprobación de inmutabilidad y upload.
- CI PASS en PHP 8.3 / 8.4 / 8.5.
- Run verde de referencia: `38284901149`.
- PHP 8.3: `186 tests / 1213 assertions`.

## B03 — wiring de rutas/UI

Estado: **OPEN — GREEN casi completo, NO certificado**.

### RED certificado

Commit RED: `e89bdc1e59a202c9e7c5d9c3de69fbbdfbc4fb3e`.

- Se añadió un solo test integral: `tests/Integration/Candidates/CandidateDocumentsRoutesTest.php`.
- RED: `191 tests / 1231 assertions / 5 failures`.
- Los 5 fallos correspondían exactamente a la superficie ausente:
  1. acceso de `candidates.manage` al contexto de unidad;
  2. rutas admin de requisitos;
  3. requisitos/documentos ausentes en continuación pública;
  4. upload + submit públicos ausentes;
  5. throttle compartido de continuación ausente.
- RED falló igual en PHP 8.3 / 8.4 / 8.5.

### GREEN implementado

HEAD funcional: `978b6cf66a07ae9cac33581663185bfa32ca3fcf`.

Archivos de producción tocados, sin crear capas nuevas:

1. `bootstrap/app.php`
   - crea una sola instancia compartida de `CandidateDocumentService`;
   - inyecta esa instancia en `CandidateService`, Properties routes y Candidate routes;
   - respeta `candidate_document_directory` configurable para tests/runtime.

2. `app/Candidates/routes.php`
   - CRUD mínimo de requisitos: crear, editar y reordenar;
   - NO existe delete de requisito;
   - upload público por `{requirementCode}`;
   - submit público;
   - upload y submit reutilizan el bucket de throttle `continue` existente;
   - CSRF/base path reutilizados.

3. `app/Properties/routes.php`
   - detalle de unidad reutilizado para contexto de requisitos;
   - `candidates.manage` puede alcanzar el contexto de unidad necesario para F5.5;
   - edición inmobiliaria sigue ligada a `properties.manage`.

4. `resources/views/unit_detail.php`
   - UI SSR para requisitos documentales dentro del detalle existente de unidad;
   - crear, editar, activar/desactivar y reordenar;
   - sin delete;
   - salida escapada.

5. `resources/views/public_candidate_continue.php`
   - requisitos activos visibles;
   - upload PDF/JPEG/PNG por código público;
   - estado Adjunto/Pendiente;
   - botón de submit;
   - sin IDs internos ni paths de storage en HTML.

### Primer CI del GREEN

Run: `38286091835`.

Resultado en PHP 8.3:

- `191 tests / 1283 assertions / 1 failure`.
- Los otros 190 tests pasan.
- PHP 8.4 y 8.5 también terminan con failure por el mismo estado general.

Único fallo actual:

`CandidateDocumentsRoutesTest::testCandidateManagerCanManageRequirementsInsideExistingUnitContext`

- El test espera `404` al hacer POST a una ruta delete inexistente.
- El error handler global histórico convierte `HttpNotFoundException` en `500`.
- **No crear una ruta delete artificial y no tocar el error handler solo para satisfacer el test.**
- El contrato real a fijar al retomar es: no debe existir acción de borrar requisito en UI ni capacidad física de delete en `CandidateDocumentService`.

## Revisión pendiente detectada antes de cerrar B03

Hay que congelar explícitamente una regresión de permisos/privacidad que todavía no está en test:

1. Un usuario F4 con `properties.view` debe seguir viendo la información/media de la unidad como antes.
2. Un usuario con solo `candidates.manage` debe ver únicamente el contexto mínimo necesario para administrar requisitos, sin heredar exposición innecesaria de datos privados de F4.

Al retomar:

1. ajustar el test del delete para comprobar **ausencia de delete en UI/servicio**, no código HTTP de una ruta inexistente;
2. añadir RED mínimo para la separación `properties.view` vs `candidates.manage`;
3. aplicar GREEN mínimo en `app/Properties/routes.php` / `resources/views/unit_detail.php` si el RED demuestra fuga o regresión;
4. correr suite completa PHP 8.3 / 8.4 / 8.5;
5. solo con todo verde declarar `B03 CLOSED / VERIFIED` y después `F5.5 CLOSED`;
6. mantener PR #8 en Draft y **NO mergear** sin aprobación explícita del usuario.

## Guardrails de continuidad

- No abrir F5.6.
- No crear servicios, repositories, controllers, route files, tablas, CSS o JS adicionales salvo RED real que lo justifique.
- No borrar requisitos físicos en F5.5.
- `CandidateDocumentService` sigue siendo la única pieza de dominio documental.
- `CandidateService` sigue siendo el dueño del estado/submission del candidato.
- El upload documental debe seguir usando MIME real, nombre generado, almacenamiento privado y reemplazo seguro.
- El submit debe seguir fallando si un requerido activo no tiene archivo físico íntegro.
- Mantener subfolder/base path y CSRF en todas las rutas.
- Nada de merge sin aprobación explícita.
