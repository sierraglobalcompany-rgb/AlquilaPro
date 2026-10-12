# F4 CHECKPOINT — TASK 8 VISUAL QA GREEN / CONVERGENCIA DOCUMENTAL PENDIENTE

**Fecha:** 2026-10-12  
**Proyecto:** Alquila Pro  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Branch:** `impl/f4-properties-catalog-20261011`  
**PR:** #7 — OPEN / DRAFT / NOT MERGED  
**Main base:** `940ebfae25a05d1589fe1ab5ceeaf667c2d08345`  
**Último GREEN de producto antes de este checkpoint:** `54836fa6a3baae49f0f60085e39d7ccb1f4d8198`  
**Merge autorizado:** NO

---

# 1. MOTIVO DE ESTE CHECKPOINT

Se detiene el trabajo a petición explícita del usuario después de cerrar el bloque de QA visual de Task 8 y antes de iniciar la convergencia documental final.

Este checkpoint debe ser el punto de recuperación obligatorio al reanudar F4.

NO avanzar a F5.  
NO mergear PR #7.  
NO borrar checkpoints históricos todavía.  
NO volver a abrir tareas F4 ya certificadas sin evidencia de regresión.

---

# 2. LEYES Y METODOLOGÍA

## Ley 1 — KISS

Solución correcta con el mínimo razonable de piezas, tablas, capas, estados y dependencias.

## Ley 2 — Cero Ruido / Convergencia

Orden obligatorio:

`ELIMINAR → UNIR / CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

Antes de borrar:

`REFERENCIAS → AUTORIDAD → REEMPLAZO → TEST → ELIMINAR → CI`

## Ciclo de implementación

`SCOPE → PRE-KISS → SPEC/DESIGN → PLAN → RED → VERIFY RED → MINIMAL GREEN → VERIFY GREEN → CONVERGE → QA → TWO LAWS GATE → CHECKPOINT → REVIEW → MERGE`

No merge sin aprobación explícita del usuario.

---

# 3. ESTADO F4 AL PAUSAR

## Task 1 — Schema + permisos

**CLOSED / GREEN**

- `properties`
- `units`
- `unit_media`
- exactamente `properties.view` y `properties.manage`
- `public_key` canónico: 24 caracteres hex / `bin2hex(random_bytes(12))`

## Task 2 — Dominio Property/Unit

**CLOSED / GREEN**

- CRUD de propiedades
- unidad única `apartment|parking`
- estados canónicos
- parking → apartment mismo property
- `unit.type` inmutable después de creación
- `public_key` generado solo por servidor

## Task 3 — Media segura + publicabilidad

**CLOSED / GREEN**

- JPEG/PNG/WebP por MIME real
- máximo 10 MiB
- nombre aleatorio
- fuera de webroot
- rollback de archivos
- video HTTPS externo
- portada única
- reordenamiento
- publicabilidad viva/canónica
- media pública delega a la misma regla de publicabilidad

## Task 4 — Administración privada

**CLOSED / GREEN**

Incluye gates de auditoría resueltos:

- type immutability
- public allowlist
- parking/propertyUnits
- campos contextuales
- launcher Propiedades
- CSRF focal

## Task 5 — Landing + catálogo público

**CLOSED / GREEN**

- visitante instalado en `/` recibe landing pública
- usuario autenticado conserva shell F3
- `/catalog`
- filtros mínimos
- detalle `/catalog/{publicKey}`
- sin CTA Aplicar hasta F5

## Task 6 — Media pública

**CLOSED / GREEN**

Contrato final:

`GET /catalog/{publicKey}/photo/{position}`

No expone `unit_media.id`.

Cache pública se mantiene fail-closed con `no-store` para que una despublicación sea inmediata.

## Task 7 — Seguridad / subfolder / regresión

**CLOSED / GREEN**

- subfolder público/privado
- no IDOR en preview privado
- media pública desaparece al dejar de ser publicable
- CSRF y permisos
- privacidad pública

## Task 8 — Cero Ruido + QA visual + cierre

**EN PROGRESO — PRODUCTO/QA VISUAL GREEN; DOCUMENTACIÓN/CEREMONIA DE CIERRE PENDIENTE**

---

# 4. TRABAJO REALIZADO EN TASK 8 EN ESTE BLOQUE

## 4.1 Convergencia estructural de rutas

Commit:

`c10ec1284d73a63c309dc059d6c6ec16b8602b2f`

Se absorbió `app/Properties/public_routes.php` dentro de:

`app/Properties/routes.php`

Y `bootstrap/app.php` volvió a registrar una sola pieza F4.

Resultado:

- una sola fuente de rutas Properties F4;
- no segundo registrador público;
- sin nueva capa/servicio/dependencia.

CI del refactor: PHP 8.3 / 8.4 / 8.5 PASS.

## 4.2 QA visual real

Se ejecutó Chromium/Playwright local usando DOM, CSS y JS reales del producto mediante `page.set_content()` porque el sandbox bloqueó navegación `file://` y `127.0.0.1`.

Viewports normales verificados:

- 360×800
- 390×844
- 412×915
- 768×1024
- 1280×900

Pantallas verificadas:

- landing pública
- catálogo
- detalle público
- listado privado de propiedades
- detalle de propiedad
- detalle de unidad

Casos inspeccionados:

- overflow horizontal
- foco visible
- dialog Apps
- nav fija inferior
- contenido largo
- imágenes grandes
- URLs largas
- reflow equivalente a 200% de zoom

## 4.3 Defectos reales encontrados por QA visual

### Defecto A — imágenes públicas con ancho intrínseco

Catálogo/detalle podían desbordar horizontalmente porque imágenes de 1400 px no tenían contrato global responsivo.

### Defecto B — primitivas privadas F4 sin CSS explícito

Las vistas usaban:

- `.content-card`
- `.page-stack`
- `.card-list`
- `.button-row`
- `.check-row`

sin contrato visual local, dejando algunos controles heredar estilos genéricos inadecuados.

### Defecto C — URL HTTPS de video larga

Una URL de video larga podía forzar el ancho mínimo del grid de detalle público.

## 4.4 TDD visual aplicado

RED 1:

`db37b376fcd55936cc28fff20d3479905a899600`

Resultado CI esperado:

- 150 tests
- 866 assertions
- 2 fallos
- únicamente imágenes responsivas + primitivas F4 sin CSS.

GREEN 1:

`68a465534d25f29d8a23fd70609823cb9550c805`

Se agregó únicamente CSS mínimo requerido.

RED 2:

`f4f47ba176836588466f7f790866ca7eedee0cfe`

Resultado CI esperado:

- 151 tests
- 873 assertions
- 1 fallo
- únicamente `Long public links can wrap within cards`.

GREEN 2 FINAL:

`54836fa6a3baae49f0f60085e39d7ccb1f4d8198`

Cambio mínimo final:

`a { overflow-wrap: anywhere; }`

## 4.5 Certificación del GREEN visual

CI exacto del commit `54836fa6...`:

- Run: `38211673768`
- PHP 8.3 PASS
- PHP 8.4 PASS
- PHP 8.5 PASS
- Composer validate PASS
- PHPUnit: **151 tests / 873 assertions / 0 failures**

QA visual final:

- 30 casos normales sin overflow horizontal
- foco visible PASS
- dialog Apps PASS
- bottom nav sin solapamiento PASS
- 6 casos de reflow equivalente a 200% sin overflow
- capturas principales inspeccionadas en 360 px y 1280 px

---

# 5. CERO RUIDO — ESTADO ACTUAL

## Ya convergido

- una sola app PHP
- una sola BD
- un solo auth
- una sola entidad `units`
- una sola fuente de precio
- una sola fuente de disponibilidad
- una sola regla de publicabilidad
- una sola frontera media pública
- un solo archivo de rutas F4
- cero dependencias runtime nuevas
- cero Node/npm
- cero ORM/repositorios/DTO/DI container
- cero tabla catálogo paralela
- cero CTA futuro Aplicar

## Ruido documental todavía pendiente

### README.md

Está desactualizado y todavía declara:

- F4 NOT STARTED
- F4 como fase siguiente
- `/catalog/{slug}`
- contratos previos a la implementación real

### F4 SPEC

Todavía contiene contratos históricos contradichos por el producto ya auditado:

- header “diseño para revisión humana / código ninguno”
- media pública `GET /catalog/media/{id}` con ID interno

Debe converger al contrato final:

`GET /catalog/{publicKey}/photo/{position}`

### F4 PLAN

Tiene ruido histórico:

- estado “NO IMPLEMENTAR”
- `public_key` de 32 hex / `random_bytes(16)`
- algunos nombres de vistas preliminares
- interfaz antigua `publicPhoto(int $mediaId)`

El código, migration y SPEC de modelo ya usan el contrato correcto de 24 hex.

### PLAN MAESTRO V1.2

En F4 todavía habla de `slug` en varios puntos. El producto final usa `public_key`.

### SPEC MAESTRA V1.2

También contiene lenguaje preliminar de `slug`/campos de catálogo que no debe provocar una segunda fuente de verdad.

---

# 6. LO QUE FALTA PARA CERRAR F4

Al reanudar, dividir estrictamente en estas microfases y crear checkpoint al cerrar cada bloque relevante:

## F4.8B — Convergencia documental

1. actualizar README al estado real F4;
2. actualizar F4 SPEC al contrato final implementado;
3. actualizar F4 PLAN para reflejar decisiones finales y tareas ejecutadas;
4. converger PLAN/SPEC maestras donde aún digan `slug` cuando el contrato real es `public_key`;
5. actualizar PR #7 para que no diga Task 1.

NO cambiar producto salvo que aparezca una contradicción real con tests.

## F4.8C — Limpieza Cero Ruido de checkpoints

Antes de borrar cualquier checkpoint temporal:

1. crear checkpoint canónico de cierre F4 que absorba recuperación/historia necesaria;
2. demostrar qué documentos temporales quedaron completamente sustituidos;
3. conservar checkpoints históricos F1/F2/F3 y evidencia útil;
4. eliminar únicamente WIP F4 que sea realmente redundante;
5. no borrar por estética.

## F4.8D — Gate final F4

1. diff audit final;
2. Composer validate;
3. PHPUnit completo;
4. CI PHP 8.3/8.4/8.5 del HEAD documental exacto;
5. Ley 1 KISS = PASS;
6. Ley 2 Cero Ruido = PASS;
7. checkpoint `F4_CLOSED_...md`.

## F4.8E — Review / PR

1. actualizar PR #7 a estado de cierre;
2. dejarlo listo para review/merge;
3. NO MERGEAR;
4. esperar autorización explícita del usuario.

Solo después del merge autorizado de F4 se abre F5.

---

# 7. PORCENTAJE AL PAUSAR

Estimación por gates funcionales, no por líneas de código:

- F0: 100%
- F1: 100%
- F2: 100%
- F3: 100%
- F4: aproximadamente **95%**

Lo que resta de F4 es principalmente convergencia documental, limpieza segura, gate final y review/merge; no un bloque funcional grande nuevo.

Avance nominal del roadmap F0–F26 por fases: aproximadamente **18%** del sistema completo si cada fase se pondera igual. Este porcentaje NO representa duración real: fases futuras como contratos, cartera, inquilino y documentos tienen más carga que una fase documental.

---

# 8. PUNTO EXACTO DE REANUDACIÓN

Al reanudar:

1. leer este checkpoint;
2. verificar HEAD/PR #7;
3. NO repetir QA visual ya certificada salvo regresión;
4. comenzar exclusivamente por **F4.8B — Convergencia documental**;
5. trabajar una microfase a la vez;
6. checkpoint antes de pasar a la siguiente microfase;
7. no abrir F5;
8. no mergear sin aprobación.

---

# 9. ESTADO CONGELADO

```text
PROJECT: Alquila Pro
BASE_MAIN: 940ebfae25a05d1589fe1ab5ceeaf667c2d08345
BRANCH: impl/f4-properties-catalog-20261011
PR: #7 OPEN / DRAFT / NOT MERGED
LAST_PRODUCT_GREEN: 54836fa6a3baae49f0f60085e39d7ccb1f4d8198
LAST_CI_RUN: 38211673768
TESTS: 151
ASSERTIONS: 873
PHP_8_3: PASS
PHP_8_4: PASS
PHP_8_5: PASS
VISUAL_QA: PASS
F4_PROGRESS: ~95%
NEXT_GATE: F4.8B DOC CONVERGENCE
MERGE_AUTHORIZED: NO
STOP_NOW: YES
```
