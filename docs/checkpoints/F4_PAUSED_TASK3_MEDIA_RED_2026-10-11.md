# F4 — CHECKPOINT DE PAUSA — TASK 3 MEDIA RED

Fecha local de pausa: 2026-10-11
Proyecto: Alquila Pro
Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
Fase: `F4 — Properties / Units / Public Catalog`
Estado: `PAUSED — TASK 3 MEDIA SECOND RED OPEN`

## 1. Refs canónicos

- Base `main`: `940ebfae25a05d1589fe1ab5ceeaf667c2d08345`
- Rama de implementación: `impl/f4-properties-catalog-20261011`
- PR: `#7 — F4: Properties, units and public catalog`
- PR state al pausar: `OPEN / DRAFT / NOT MERGED`
- HEAD funcional inmediatamente anterior al RED actual: `3a2ca9b01d3f70e34ca42973cf8b09e47d4b4136`
- HEAD RED inmediatamente anterior a este checkpoint: `bdaa71ae28a0e71d7de91d1d42e0aa69c5276d9a`

Este checkpoint es únicamente continuidad documental. No autoriza merge ni apertura de F5.

## 2. Estado real de tareas

### Task 1 — schema + permissions

Estado: `GREEN / CLOSED dentro de F4`.

Guardrails ya respetados:

- un solo modelo `units` para `apartment|parking`;
- máximo F4: tres tablas (`properties`, `units`, `unit_media`);
- dos permisos F4 exactos;
- sin segundo auth;
- sin `catalog_listings`;
- sin dependencias Composer/npm nuevas;
- `public_key` generado por servidor con 24 hex según SPEC F4.

### Task 2 — properties + units domain

Estado: `GREEN`.

Implementación relevante en `app/Properties/PropertyService.php`:

- CRUD de propiedades;
- activación/inactivación de propiedad sin borrar unidades;
- creación/edición/consulta de apartamentos y parqueaderos dentro de un solo modelo;
- estados de unidad validados;
- código único por propiedad;
- `public_key` no aceptado desde input y generado por servidor;
- validación de vínculo parqueadero → apartamento de la misma propiedad;
- consultas públicas esqueleto únicamente sobre propiedad activa + unidad `available` + `is_public = 1`.

Evidencia GREEN de Task 2:

- commit: `e8beb92f...`;
- suite: `103 tests / 551 assertions`;
- PHP 8.3: PASS;
- PHP 8.4: PASS;
- PHP 8.5: PASS.

## 3. Task 3 — media

Task 3 fue dividida en ciclos pequeños para conservar TDD y KISS.

### 3.1 Fotos — GREEN

Commit GREEN: `3a2ca9b01d3f70e34ca42973cf8b09e47d4b4136`

Archivo creado:

- `app/Properties/UnitMediaService.php`

Pruebas:

- `tests/Integration/Properties/UnitMediaServiceTest.php`

Comportamiento ya GREEN:

- fotos PNG/JPEG/WebP por MIME real usando `fileinfo`;
- máximo 10 MiB;
- nombre aleatorio generado por servidor;
- nombre/MIME suministrado por cliente no es autoridad;
- almacenamiento fuera del webroot en directorio privado;
- rollback del archivo si falla persistencia DB;
- eliminación de fila + archivo;
- rechazo de media perteneciente a otra unidad;
- lectura privada de foto;
- `publicPhoto()` solo permite foto de unidad actualmente activa/disponible/publicada.

Evidencia exacta del GREEN de fotos:

- CI run: `38198865110`;
- PHP 8.3: PASS;
- PHP 8.4: PASS;
- PHP 8.5: PASS;
- PHPUnit: `107 tests / 584 assertions / 0 failures / 0 errors`.

## 4. RED abierto actual — video + portada + orden

Commit RED: `bdaa71ae28a0e71d7de91d1d42e0aa69c5276d9a`

Solo se modificaron tests para abrir el siguiente ciclo de Task 3.

Casos RED añadidos:

1. `addVideoLink()`
   - acepta únicamente URL absoluta HTTPS;
   - trim de URL;
   - guarda solo `external_url`;
   - `stored_name = null`;
   - `mime_type = null`;
   - rechaza vacío, HTTP, `javascript:` y protocol-relative.

2. `setCover()`
   - portada solo puede ser una foto de la misma unidad;
   - una sola portada por unidad;
   - cambiar portada desmarca la anterior;
   - rechaza video;
   - rechaza media de otra unidad.

3. `reorder()`
   - recibe cada media propia exactamente una vez;
   - rechaza IDs duplicados;
   - rechaza lista incompleta;
   - rechaza ID ajeno;
   - persiste orden 1..N;
   - un intento inválido no altera el último orden válido.

### Evidencia RED

CI run: `38199009871`

Resultado de jobs al pausar:

- PHP 8.3: expected failure;
- PHP 8.4: expected failure;
- PHP 8.5: expected failure.

PHP 8.3 produjo exactamente:

- `110 tests`;
- `588 assertions`;
- `3 errors`;
- error 1: `UnitMediaService::addVideoLink()` no existe;
- error 2: flujo de portada se detiene porque `addVideoLink()` no existe;
- error 3: flujo de reordenamiento se detiene porque `addVideoLink()` no existe.

No se observaron regresiones en los 107 tests previos.

## 5. Importante — intento GREEN interrumpido

Después de validar el RED se inició una edición de `app/Properties/UnitMediaService.php`, pero el usuario solicitó checkpoint/pausa durante esa operación.

Verificación posterior:

- PR #7 continuó con HEAD `bdaa71ae28a0e71d7de91d1d42e0aa69c5276d9a` antes de crear este checkpoint;
- por tanto, **esa edición GREEN NO se persistió**;
- el código de producción permanece en el estado GREEN de fotos de `3a2ca9b...`;
- el único cambio funcional posterior es el test RED de `bdaa71ae...`.

No intentar “recuperar” código parcial desde el chat como autoridad. Al reanudar se debe implementar de nuevo el GREEN mínimo a partir del RED versionado.

## 6. Próximo paso exacto al reanudar

No rehacer Task 1, Task 2 ni fotos.

Reanudar exactamente desde:

`Task 3 — segundo ciclo: GREEN mínimo para addVideoLink() + setCover() + reorder()`

Orden obligatorio:

1. confirmar HEAD y este checkpoint;
2. confirmar que los tests RED siguen siendo los de `bdaa71ae...` más este checkpoint documental;
3. implementar únicamente `addVideoLink()`, `setCover()` y `reorder()` dentro de `UnitMediaService`;
4. no crear repository, DTO, MediaLibrary, controller genérico ni tabla nueva;
5. ejecutar CI completo PHP 8.3/8.4/8.5;
6. exigir GREEN completo antes de continuar con el siguiente subciclo de publicación/completitud;
7. no tocar rutas/vistas mientras este subciclo siga rojo.

## 7. Guardrails de continuidad

- SPEC F4 tiene autoridad sobre PLAN si existe contradicción.
- KISS y Cero Ruido siguen obligatorios.
- No agregar tablas fuera de las tres F4 ya aprobadas.
- No agregar Composer/npm.
- No aceptar `public_key` desde formularios.
- No crear un segundo modelo de disponibilidad.
- No crear catálogo paralelo.
- No abrir candidatos/leads/aplicaciones: corresponden a F5+.
- No mergear PR #7 sin aprobación humana explícita.
- No abrir F5 antes de cerrar/verificar/mergear F4 con aprobación.

## 8. Estado de salida

`F4: PAUSED`

`TASK_1: GREEN`

`TASK_2: GREEN`

`TASK_3_PHOTOS: GREEN`

`TASK_3_VIDEO_COVER_ORDER: RED OPEN`

`PR_7: OPEN / DRAFT / NOT MERGED`

`NEXT_ACTION: IMPLEMENT MINIMAL GREEN FOR addVideoLink + setCover + reorder`
