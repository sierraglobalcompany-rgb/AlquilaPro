# Alquila Pro — F4 checkpoint pausado en Task 8

Fecha: 2026-10-12

## 1. Estado de repositorio

```text
REPO: sierraglobalcompany-rgb/AlquilaPro
BRANCH: impl/f4-properties-catalog-20261011
PR: #7 — F4: Properties, units and public catalog
PR_STATE: OPEN
PR_DRAFT: YES
PR_MERGED: NO
BASE: main
BASE_SHA: 940ebfae25a05d1589fe1ab5ceeaf667c2d08345
PRODUCT_HEAD_BEFORE_THIS_CHECKPOINT: 5b840f846ebe86fe15ad763b1e6aec6fa79f3979
MERGE_AUTHORIZATION: NO
```

No hacer merge sin aprobación humana explícita.

## 2. Leyes y método siguen vigentes

### LEY 1 — KISS

Una sola fuente de verdad, mínimo de piezas, capas, tablas, servicios y dependencias.

### LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

### TDD / ejecución

`SCOPE → PRE-KISS → SPEC/DESIGN → PLAN → RED → VERIFY RED → MINIMAL GREEN → VERIFY GREEN → CONVERGE → QA → TWO LAWS GATE → CHECKPOINT → REVIEW → MERGE`

No entrar a F5 mientras F4 no esté CLOSED / VERIFIED / MERGED.

## 3. Estado funcional de F4

### Tasks 1–4

Cerradas previamente y cubiertas por los checkpoints históricos de F4.

### Task 5 — landing + catálogo público

`CLOSED / VERIFIED`.

Último gate observado antes de abrir Task 6: PHP 8.3 / 8.4 / 8.5 en verde.

### Task 6 — media pública segura

`CLOSED / VERIFIED`.

Decisión canónica aplicada:

- NO exponer `unit_media.id` públicamente.
- Contrato público de foto: `/catalog/{publicKey}/photo/{position}`.
- `publicKey` ya es el identificador público canónico de la unidad.
- posición = ordinal de foto; no se crea token, tabla ni segundo identificador.
- posición 1 prioriza portada y luego orden configurado.
- respuesta pública usa MIME validado.
- `X-Content-Type-Options: nosniff`.
- `Cache-Control: no-store, max-age=0` para cumplir unpublish inmediato.
- si unidad deja de ser publicable, la siguiente petición de foto responde 404.
- catálogo y detalle muestran fotos sin filtrar IDs internos.
- subfolder cubierto.

RED principal:

```text
17058abaa2b657f6d565468a42f176a003f13ef1
```

GREEN final de Task 6:

```text
cd6910275676319e29d75de8ab4a784dd3ce6ff9
```

CI Task 6:

```text
RUN: 38209593141
PHP 8.3: PASS
PHP 8.4: PASS
PHP 8.5: PASS
146 tests / 848 assertions
```

### Task 7 — seguridad, base path y regresión

`CLOSED / VERIFIED`.

Se cerró sin introducir runtime nuevo. Solo se añadieron caracterizaciones de límites ya existentes:

- subfolder en guard/redirect de rutas privadas F4;
- preview privada de foto no permite IDOR cruzando `unitId` + `mediaId`;
- CSRF F4 ya tenía prueba focal independiente;
- forced password change ya bloquea F4 privada;
- storage sigue fuera del webroot y `.htaccess` bloquea acceso directo a `storage/`;
- public catalog/media ya tenía subfolder y privacidad cubiertos.

Commit de cierre de Task 7:

```text
5b840f846ebe86fe15ad763b1e6aec6fa79f3979
```

CI Task 7:

```text
RUN: 38209796436
PHP 8.3: PASS
PHP 8.4: PASS
PHP 8.5: PASS
148 tests / 860 assertions
0 failures
0 errors
Composer validate --strict: PASS
```

## 4. Task 8 — estado exacto al pausar

`OPEN / NOT CLOSED`.

Task 8 es el siguiente y único gate permitido.

Objetivos pendientes:

1. QA visual real.
2. Cero Ruido final.
3. convergencia documental.
4. CI exacto sobre HEAD final.
5. checkpoint canónico `F4_CLOSED`.
6. README actualizado.
7. segundo CI sobre HEAD documental final.
8. solo entonces PR Ready para revisión.

## 5. QA visual — NO certificar todavía

Se intentó preparar QA visual real desde este entorno, pero no se logró ejecutar un navegador utilizable de forma confiable.

Ruling:

```text
F4_VISUAL_QA: PENDING
```

No marcar PASS por inspección estática de código.

La QA real debe cubrir como mínimo:

- landing;
- catálogo;
- detalle público;
- lista de propiedades privada;
- detalle de propiedad;
- detalle de unidad;
- 360×800;
- 390×844;
- 412×915;
- 768×1024;
- desktop >=1280;
- equivalente a zoom 200%;
- teclado/focus;
- contenido largo;
- imagen faltante;
- Apps/dialog;
- sin overflow horizontal;
- sin solapamiento de navegación fija.

Cualquier defecto visual real debe convertirse en repro verificable antes de tocar CSS.

## 6. Cero Ruido — auditoría ya iniciada

Comparación realizada:

```text
BASE: 940ebfae25a05d1589fe1ab5ceeaf667c2d08345
HEAD: 5b840f846ebe86fe15ad763b1e6aec6fa79f3979
AHEAD_BY: 95 commits
```

Arquitectura confirmada hasta este checkpoint:

```text
TABLES F4: +3 exactas
- properties
- units
- unit_media

PERMISSIONS F4: +2 exactos
- properties.view
- properties.manage

DOMAIN SERVICES F4: +2
- PropertyService
- UnitMediaService

COMPOSER DEPS: +0
NPM DEPS: +0
SECOND AUTH: NO
CATALOG PARALLEL TABLE: NO
LEAD/APPLICATION TABLE: NO
SECOND PRICE SOURCE: NO
SECOND AVAILABILITY SOURCE: NO
```

### Ruido real detectado y todavía NO corregido

1. Existen dos archivos de rutas de Properties:
   - `app/Properties/routes.php`
   - `app/Properties/public_routes.php`

   El plan esperaba una sola pieza de rutas F4. Antes de agregar nada, evaluar `UNIR/CONVERGER` ambos en un solo `routes.php` si puede hacerse sin aumentar complejidad ni riesgo.

2. Existen múltiples checkpoints WIP/PAUSED históricos de F4.

   NO borrarlos todavía a ciegas. En el cierre final, crear primero el checkpoint canónico `F4_CLOSED_2026-10-12.md`, verificar que contiene toda la continuidad necesaria y después eliminar/converger los WIP que ya no tengan valor operativo.

3. README está obsoleto respecto al estado real:
   - todavía dice `F4: NOT STARTED`;
   - todavía describe F4 como siguiente fase;
   - debe actualizarse únicamente al cierre, no antes de superar QA + Two Laws gate.

4. El PLAN F4 todavía contiene el contrato histórico `/catalog/media/{id}` y `UnitMediaService::publicPhoto(int $mediaId)`.

   El runtime real y seguro ya convergió a `/catalog/{publicKey}/photo/{position}` para no exponer IDs internos. En el cierre documental hay que corregir SPEC/PLAN/checkpoint para que no sobreviva una autoridad contradictoria.

## 7. No hacer al reanudar

- NO empezar F5.
- NO mergear PR #7.
- NO marcar PR Ready todavía.
- NO declarar F4 CLOSED todavía.
- NO certificar QA visual sin ejecución real.
- NO agregar servicios, tablas, DTOs, repositorios, middleware o dependencias para Task 8.
- NO crear segundo identificador público para media.
- NO volver a `/catalog/media/{id}`.
- NO borrar checkpoints WIP hasta tener checkpoint canónico de cierre completo.
- NO tocar CSS por hipótesis.

## 8. Siguiente paso exacto al reanudar

Orden recomendado y obligatorio por KISS/Cero Ruido:

```text
1. Reconfirmar HEAD/PR/CI contra este checkpoint.
2. Terminar Cero Ruido:
   a. evaluar unión routes.php + public_routes.php;
   b. eliminar solo ruido demostrado;
   c. mantener +3 tablas / +2 permisos / +0 deps.
3. Ejecutar QA visual REAL.
4. Corregir únicamente defectos reproducidos.
5. Ejecutar full CI 8.3/8.4/8.5 sobre producto final.
6. Converger SPEC/PLAN/README con el contrato real.
7. Crear `docs/checkpoints/F4_CLOSED_2026-10-12.md`.
8. Eliminar/converger checkpoints WIP ya absorbidos por el cierre canónico.
9. Ejecutar CI otra vez sobre el HEAD documental exacto.
10. Solo si todo PASS: marcar PR #7 Ready for review.
11. Esperar aprobación humana explícita para merge.
```

## 9. Gate final de F4 todavía abierto

```text
TASK_5: CLOSED / VERIFIED
TASK_6: CLOSED / VERIFIED
TASK_7: CLOSED / VERIFIED
TASK_8: OPEN
F4_VISUAL_QA: PENDING
F4_CERO_RUIDO: IN_PROGRESS
F4_DOC_CONVERGENCE: PENDING
F4_FINAL_CI: PENDING
F4_CLOSED_CHECKPOINT: PENDING
PR_READY: NO
MERGE_ALLOWED: NO
F5_ALLOWED: NO
```

## 10. Punto seguro de recuperación

La recuperación correcta debe empezar leyendo este archivo y luego verificar GitHub real.

El HEAD productivo certificado inmediatamente antes de este checkpoint es:

```text
5b840f846ebe86fe15ad763b1e6aec6fa79f3979
```

La evidencia de CI asociada es:

```text
38209796436
148 tests / 860 assertions
PHP 8.3 / 8.4 / 8.5: PASS
```

Este checkpoint es documental. No contiene autorización de merge ni de inicio de F5.
