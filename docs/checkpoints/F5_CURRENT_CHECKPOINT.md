# F5 CURRENT CHECKPOINT — F5.5 CLOSED / VERIFIED

**Fecha:** 2026-10-12 (America/Bogota)  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Branch:** `impl/f5-candidates-20261012`  
**Base:** `main` @ `648401a4927bfeb7525421a4edd2b5f2c60f06c4`  
**PR:** `#8` OPEN / DRAFT / NOT MERGED

> El SHA del commit documental que contiene este archivo no se auto-incrusta. El producto F5.5 certificado termina en `4907bf6e16d3c51190fd582cd3c5d136461ff3bc`; su CI exacto es `38287467062`.

## 1. Estado canónico

```text
F0-F4: CLOSED / VERIFIED / MERGED
F5_SPEC: APPROVED
F5_PLAN: APPROVED
F5.1_SCHEMA_PERMISSIONS: CLOSED / VERIFIED
F5.2_CANDIDATE_CORE_TOKEN: CLOSED / VERIFIED
F5.3_PUBLIC_START_CONTINUE: CLOSED / VERIFIED
F5.4_PROFILE_REFERENCES_UNIT_CHANGE: CLOSED / VERIFIED
F5.5_STRUCTURED_REQUIREMENTS_PRIVATE_DOCUMENTS: CLOSED / VERIFIED
F5.6_ADMIN_CANDIDATES: NOT STARTED
F5.7_CATALOG_CTA_LIFECYCLE: NOT STARTED
F5.8_HARDENING_CLOSURE: NOT STARTED
PR_READY: NO
MERGE_ALLOWED: NO
```

No abrir F5.7 antes de cerrar F5.6. Mantener PR Draft. **NO MERGEAR sin aprobación humana explícita.**

## 2. Leyes y método

Autoridad: README → SPEC maestro → PLAN maestro → SPEC F5 → PLAN F5 → checkpoint → tests/CI → código real.

- **LEY 1 — KISS:** simple, directo, eficiente, sin arquitectura anticipatoria.
- **LEY 2 — CERO RUIDO / CONVERGENCIA:** eliminar ruido probado; luego unir/simplificar/mejorar antes de añadir.
- TDD obligatorio: RED → RED verificado → GREEN mínimo → GREEN completo → Cero Ruido → checkpoint.
- No repositories/DTO/factories/interfaces sin necesidad probada.
- No merge sin autorización humana explícita.

## 3. F5.1–F5.4

Permanecen CLOSED / VERIFIED. F5.5 no cambió sus contratos canónicos: una sola entidad `candidates`, mismas cuatro tablas F5, exactamente dos permisos (`candidates.view`, `candidates.manage`), mismo auth/session, misma publicabilidad F4, mismo `CandidateService`.

## 4. F5.5 — alcance cerrado

Segundo y último servicio F5 permitido:

```php
CandidateDocumentService
```

Responsabilidades cerradas:
- requisitos estructurados por unidad;
- código único por unidad, inmutable y route-safe (`A-Z`, `a-z`, `0-9`, `_`, `-`, máximo 80); no se fuerza minúscula;
- label/description/required/active/order editables;
- reorder exige conjunto exacto perteneciente a la unidad;
- requisitos no se eliminan físicamente en F5: se inactivan;
- documentos PDF/JPEG/PNG, máximo 10 MiB;
- MIME real con `finfo`;
- nombre físico generado por servidor;
- almacenamiento privado fuera de webroot;
- una fila por candidate+requirement, con reemplazo seguro;
- requisito de upload debe estar activo y pertenecer a la unidad actual del candidato;
- `privateDocument()` valida ownership, existencia física, MIME y tamaño persistidos;
- submit final exige todos los requisitos actuales `required + active` con archivos físicamente válidos;
- requisito opcional/inactivo no bloquea;
- despublicar la unidad no bloquea al candidato ya creado.

## 5. B01 — validez física en submit — CLOSED / VERIFIED

Hueco demostrado: una fila documental en DB podía satisfacer submit aunque el archivo físico hubiera sido borrado o manipulado.

RED añadió casos de archivo borrado y archivo manipulado. GREEN reutilizó `CandidateDocumentService::privateDocument()` desde `CandidateService::assertDocumentCompleteness()`; no se duplicó lógica de filesystem/MIME.

Se corrigió el fixture para que guardado y validación compartieran **la misma instancia real** de `CandidateDocumentService`, evitando un falso GREEN por storage distinto.

CI final B01: `38284137434` — PASS PHP 8.3/8.4/8.5.  
PHP 8.3: **185 tests / 1206 assertions**.

## 6. B02 — requirementCode route-safe — CLOSED / VERIFIED

La SPEC no imponía slug lowercase. Se evitó inventar esa política.

Contrato mínimo derivado de la URL pública:

```text
[A-Za-z0-9_-]+
longitud <= 80
```

El mismo validador privado se reutiliza en creación, comprobación de inmutabilidad y upload. Sin nueva clase/capa.

CI final B02: `38284901149` — PASS PHP 8.3/8.4/8.5.  
PHP 8.3: **186 tests / 1213 assertions**.

## 7. B03 — bootstrap, rutas y UI — CLOSED / VERIFIED

Se mantuvieron las piezas existentes; no se creó un segundo route file ni pantalla paralela.

Bootstrap:
- `candidate_document_directory` con default `storage/candidate-documents`;
- una única instancia `CandidateDocumentService($pdo, $candidateDocumentDirectory)`;
- esa misma instancia se inyecta en `CandidateService` y rutas.

Rutas F5.5 añadidas al mismo `app/Candidates/routes.php`:

```text
POST /admin/units/{id}/application-requirements
POST /admin/units/{id}/application-requirements/{requirementId}
POST /admin/units/{id}/application-requirements/order
POST /apply/continue/{token}/documents/{requirementCode}
POST /apply/continue/{token}/submit
```

Contratos verificados:
- mutaciones de requisitos requieren `candidates.manage`;
- CSRF y forced-password gate existentes se reutilizan;
- requisitos viven dentro del `unit_detail.php` existente;
- no existe acción/método de delete físico para requisitos;
- continuación pública muestra solo requisitos activos y estado documental, sin IDs internos;
- upload usa `requirementCode`, no requirement ID;
- upload reutiliza bucket throttle `continue`;
- basePath/subfolder preservado;
- submit reutiliza `CandidateService::submitByToken()`;
- candidate-only (`candidates.manage`) puede administrar requisitos sin heredar información/media F4;
- `properties.view` conserva su visibilidad F4 histórica.

La separación de permisos quedó cerrada con una corrección GREEN de **una sola línea** en `app/Properties/routes.php`:

```php
'canViewPropertyDetails' => Permissions::allows($user, 'properties.view'),
```

Producto certificado F5.5: `4907bf6e16d3c51190fd582cd3c5d136461ff3bc`.

## 8. TDD B03 / superficie de rutas

RED inicial de rutas/UI: `e89bdc1e59a202c9e7c5d9c3de69fbbdfbc4fb3e`.  
CI `38285422700`: PHP 8.3 **191 tests / 1231 assertions / 5 failures**, exactamente por la superficie F5.5 ausente; regresión previa verde.

El test de integración de rutas/UI se dejó en archivo único:

```text
tests/Integration/Candidates/CandidateDocumentsRoutesTest.php
```

**Ruling KISS:** el PLAN nombraba `CandidateDocumentsTest.php`/`CandidateServiceTest.php`, pero mezclar autorización/SSR/rutas en el test de dominio o en pruebas F4 aumentaba acoplamiento. Se creó un único test de integración F5.5 para la superficie HTTP; no produjo arquitectura de producto adicional.

Un assertion antiguo esperaba `404` para una ruta delete inexistente. Eso era un contrato de test incorrecto porque el error handler global convierte rutas no encontradas en 500. No se añadió ruta delete ni se cambió error handling: el contrato correcto verifica ausencia de `/delete` en UI y ausencia de `deleteRequirement()` en el servicio.

RED final de privacidad/permisos: `fd2c9676f056671480a03bb11b6b620d94f8c364`.  
PHP 8.3: **192 tests / 1295 assertions / 1 failure**, únicamente porque `properties.view` había perdido la visibilidad F4 al no recibir la bandera explícita.

GREEN final: `4907bf6e16d3c51190fd582cd3c5d136461ff3bc`.

## 9. Certificación final F5.5

CI producto: `38287467062`

```text
PHP 8.3: PASS — 192 tests / 1298 assertions
PHP 8.4: PASS
PHP 8.5: PASS
Composer validate --strict: PASS
SecurityBoundary: PASS
Failures: 0
Errors: 0
```

Warnings de MariaDB `io_uring` y deprecación de Node 20 en `actions/checkout@v4` son ruido de runner y no fallos de producto.

## 10. Two Laws / Cero Ruido

Delta efectivo F5.5 desde el checkpoint de dominio `23ef4428eabac458297445c21efd7116f06b08c2` hasta producto `4907bf6e...`:

```text
NEW_TABLES_F5.5: 0
NEW_SERVICES_F5.5: 0 adicionales fuera del CandidateDocumentService ya previsto
TOTAL_F5_SERVICES: 2
NEW_ROUTE_FILES_F5.5: 0
NEW_PRODUCT_VIEWS_F5.5: 0
DEPENDENCIES_DELTA: 0
NPM_DELTA: 0
CSS_JS_DELTA: 0
SECOND_AUTH_OR_SESSION: NO
REPOSITORY_DTO_FACTORY_INTERFACE_DELTA: 0
PUBLIC_STORAGE_DIRECT_SERVING: NO
PARALLEL_APPLICATION_ENTITY: NO
PHYSICAL_REQUIREMENT_DELETE: NO
INTERNAL_IDS_IN_PUBLIC_DOCUMENT_ROUTES: NO
```

Archivos de producto tocados para cerrar wiring/guardrails F5.5:
- `app/Candidates/CandidateDocumentService.php`
- `app/Candidates/CandidateService.php`
- `app/Candidates/routes.php`
- `app/Properties/routes.php`
- `bootstrap/app.php`
- `resources/views/unit_detail.php`
- `resources/views/public_candidate_continue.php`

Conclusión:

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
F5.5: CLOSED / VERIFIED
```

## 11. Deuda explícita reservada para F5.8

No mezclar con F5.5 sin RED:

`bootstrap/app.php` registra la ruta completa en errores no manejados. Las rutas `/apply/continue/{token}` contienen el token raw de capacidad; una excepción no manejada podría incluirlo en logs.

La SPEC exige no revelar token/hash. Este hallazgo queda como gate de hardening F5.8. No fue necesario debilitar ni ampliar F5.5 para cerrarlo.

## 12. Próximo gate — F5.6

**F5.6 — Administrative Candidates Module**.

Rutas planificadas:

```text
GET  /admin/candidates
GET  /admin/candidates/{id}
POST /admin/candidates/{id}/status
POST /admin/candidates/{id}/token/regenerate
GET  /admin/candidates/{id}/documents/{documentId}
```

Orden obligatorio:
1. RED de autorización y SSR;
2. RED IDOR de documento privado;
3. RED regeneración de token;
4. verificar RED;
5. GREEN mínimo sobre `CandidateService`, `CandidateDocumentService`, mismo `app/Candidates/routes.php` y dos vistas admin previstas;
6. `candidates.view` para list/detail/document read;
7. `candidates.manage` para status/token regenerate;
8. owner bypass, CSRF, forced-password y basePath existentes;
9. admin NO puede simular submit/completitud para forzar `verification`;
10. focal + auth/shell regression + CI 8.3/8.4/8.5;
11. Cero Ruido + checkpoint;
12. no abrir F5.7 antes de cerrar F5.6.

PR #8 permanece **OPEN / DRAFT / NOT MERGED**. **NO MERGEAR sin aprobación humana explícita.**