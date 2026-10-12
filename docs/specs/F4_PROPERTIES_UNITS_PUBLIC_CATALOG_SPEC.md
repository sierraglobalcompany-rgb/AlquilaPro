# ALQUILA PRO — F4 SPEC FINAL: PROPIEDADES + UNIDADES + LANDING + CATÁLOGO PÚBLICO

**Fecha original:** 2026-10-11  
**Convergencia as-built:** 2026-10-12  
**Estado:** IMPLEMENTADA / QA FUNCIONAL Y VISUAL GREEN / CIERRE FINAL EN PROGRESO  
**Base canónica:** SPEC/PLAN Maestro V1.2  
**Fase:** F4 — Propiedades + unidades + publicación + landing + catálogo  
**PR:** #7 — OPEN / DRAFT / NOT MERGED  
**Merge autorizado:** NO

---

# 0. AUTORIDAD Y OBJETIVO

Esta SPEC contiene el contrato final implementado de F4. Sustituye las decisiones preliminares de esta misma SPEC que hayan quedado contradichas por convergencias explícitas y verificadas mediante tests/CI.

Orden de autoridad para F4:

`SPEC MAESTRA V1.2 → PLAN MAESTRO V1.2 → decisiones canónicas README → ESTA SPEC F4 FINAL → PLAN F4 AS-BUILT → checkpoint → tests/CI → código`.

Cuando una referencia maestra anterior usa `slug` o media pública por ID interno, para F4 prevalece esta convergencia específica documentada: `public_key` de 24 hex y foto pública por `publicKey + position`.

Objetivos F4:

1. administrar propiedades/conjuntos;
2. administrar unidades `apartment|parking` con una sola entidad;
3. guardar fotos y enlaces HTTPS externos de video;
4. controlar estado operativo e intención de publicación;
5. convertir `/` en landing pública para visitante instalado sin romper el shell autenticado;
6. ofrecer catálogo público derivado del inventario real;
7. preparar F5 sin crear candidatos, leads, aplicaciones ni CTA muerto.

F4 no implementa `Aplicar`.

---

# 1. LEYES F4

## Ley 1 — KISS

F4 mantiene una sola fuente de verdad por concepto:

- una tabla `units` para apartamentos y parqueaderos;
- un precio comercial por unidad;
- un estado operativo por unidad;
- una intención de publicación `is_public`;
- una regla canónica de publicabilidad;
- un catálogo derivado de `units`;
- una tabla simple de media;
- auth, CSRF, View, branding y shell existentes reutilizados.

No se crean repositorios, DTOs, factories, DI container, ORM, CMS de catálogo, segundo auth, frontend framework ni servicio de video.

## Ley 2 — Cero Ruido / Convergencia

Orden:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`.

F4 rechaza:

- boolean `available` paralelo a `status`;
- precio/descripción de catálogo paralelos;
- permisos exclusivos de catálogo;
- tabla `catalog_listings`;
- tablas completas separadas `apartments`/`parking_spaces`;
- CTA `Aplicar` antes de F5;
- media pública por ID interno;
- rutas futuras de candidatos/contratos/cartera;
- upload/transcoding de video;
- hard-delete como sustituto de estado.

---

# 2. MODELO DE DATOS FINAL

F4 agrega exactamente tres tablas:

- `properties`;
- `units`;
- `unit_media`.

## 2.1 `properties`

Campos funcionales mínimos:

- `id` PK;
- `name` requerido;
- `address` requerido y privado;
- `city` requerido;
- `sector` opcional;
- `administration_name` opcional;
- `administration_contact` opcional y privado;
- `notes` privadas;
- `is_active`;
- timestamps.

No hay tablas de ciudades, sectores ni administraciones en F4.

## 2.2 `units`

Una sola entidad para `apartment|parking`.

Campos comunes relevantes:

- `id` interno;
- `property_id`;
- `public_key`;
- `type`;
- `code`;
- `location_label` público opcional;
- `status`;
- `is_public`;
- `monthly_price`;
- `description` pública;
- `requirements` públicos;
- `private_notes`;
- timestamps.

Restricción: `UNIQUE(property_id, code)`.

### `public_key`

Contrato final:

- `CHAR(24)`;
- generado por servidor con `bin2hex(random_bytes(12))`;
- 24 caracteres hexadecimales;
- único;
- nunca aceptado desde formulario;
- inmutable desde UI;
- usado en detalle y media pública;
- no existe slug adicional en F4.

### `type`

Valores: `apartment|parking`.

El tipo queda inmutable después de crear la unidad. No se permite convertir apartamento↔parqueadero mediante edición.

### Estados

Valores canónicos:

- `available`;
- `in_process`;
- `occupied`;
- `inactive`;
- `maintenance`.

No existe booleano de disponibilidad paralelo.

### Apartment — campos nullable

- `unit_number` privado/operativo;
- `tower`;
- `floor`;
- `area_m2`;
- `bedrooms`;
- `bathrooms`;
- `administration_fee`;
- `registry_number` privado.

### Parking — campos nullable

- `parking_level`;
- `dimensions`;
- `vehicle_type`;
- `linked_apartment_id` opcional.

`linked_apartment_id` solo puede apuntar a un apartamento de la misma propiedad. Varios parqueaderos pueden apuntar al mismo apartamento. No se crea tabla puente.

## 2.3 `unit_media`

Una sola tabla de marketing media:

- `id` interno;
- `unit_id`;
- `type = photo|video_link`;
- `stored_name`;
- `mime_type`;
- `external_url`;
- `sort_order`;
- `is_cover`;
- `created_at`.

---

# 3. PUBLICABILIDAD CANÓNICA

Una unidad aparece públicamente únicamente cuando todas estas condiciones son verdaderas en el momento de lectura:

1. propiedad activa;
2. `unit.status = available`;
3. `unit.is_public = 1`;
4. `monthly_price > 0`;
5. `description` no vacía;
6. `requirements` no vacíos;
7. existe al menos una foto válida.

La regla es viva y se reutiliza en listado, detalle y media pública. No se persiste un segundo estado de “publicable”.

Si precio, estado, propiedad, intención o foto dejan de cumplir, la unidad y su media desaparecen públicamente de inmediato.

---

# 4. EXPERIENCIA PÚBLICA FINAL

## 4.1 `/`

### Antes de instalar

Se conserva el installer Foundation.

### Instalado + visitante

Muestra landing pública con branding, acceso a Catálogo e Iniciar sesión. El resumen de disponibilidad aparece solo cuando existe inventario realmente publicable.

### Instalado + usuario autenticado

Conserva el shell privado F3.

## 4.2 `GET /catalog`

Público y sin sesión.

Lista únicamente unidades publicables. Filtros V1:

- tipo (`apartment|parking`);
- ciudad;
- sector.

Tipo inválido devuelve 422.

Cards pueden mostrar:

- portada;
- tipo;
- propiedad;
- ciudad/sector;
- `location_label`;
- precio mensual;
- administración separada cuando aplique;
- atributos principales;
- enlace **Ver detalles**.

No hay full-text, mapas, geocoding, favoritos ni ranking.

## 4.3 `GET /catalog/{publicKey}`

Detalle público por `public_key` de 24 hex.

Muestra únicamente allowlist pública:

- galería;
- tipo;
- propiedad;
- ciudad/sector;
- ubicación resumida;
- descripción;
- precio;
- administración cuando aplique;
- requisitos;
- atributos públicos por tipo;
- enlaces HTTPS de video;
- disponibilidad pública.

No expone:

- dirección exacta;
- `id`;
- `property_id`;
- `linked_apartment_id`;
- matrícula;
- número interno de unidad;
- contacto interno de administración;
- notas privadas;
- timestamps operativos;
- candidato/inquilino.

Claves inexistentes, malformadas o ya no publicables devuelven 404.

## 4.4 Media pública

Contrato final:

`GET /catalog/{publicKey}/photo/{position}`

`position` es ordinal público derivado del orden de fotos; no corresponde a `unit_media.id`.

Antes de servir bytes:

1. `publicKey` resuelve una unidad actualmente publicable;
2. la posición resuelve una foto de esa unidad;
3. archivo queda dentro del directorio esperado;
4. MIME persistido procede de validación real.

Si falla cualquier condición → 404.

Headers mínimos:

- `Content-Type` validado;
- `X-Content-Type-Options: nosniff`;
- `Cache-Control: no-store` en F4 para evitar media stale tras despublicación.

## 4.5 Aplicar

No se renderiza en F4. F5 lo añadirá únicamente con flujo real de candidato.

---

# 5. MEDIA SEGURA

## Foto

- JPEG, PNG o WebP;
- máximo 10 MiB;
- MIME real con `fileinfo`;
- nombre aleatorio generado por servidor;
- fuera del webroot;
- nombre/extensión cliente no son fuente de confianza;
- rollback evita archivo huérfano ante fallo de persistencia;
- eliminación respeta pertenencia de unidad;
- máximo una portada efectiva por unidad;
- orden exacto y propiedad de IDs verificados en administración.

## Video

- enlace HTTPS externo;
- no upload;
- no transcoding;
- no iframe arbitrario;
- enlace público escapado y con wrapping visual seguro.

---

# 6. ADMINISTRACIÓN PRIVADA

Permisos exactamente:

- `properties.view`;
- `properties.manage`.

Owner conserva bypass.

`properties.view` permite lectura y preview privado. `properties.manage` permite mutaciones.

Rutas privadas implementadas bajo `/admin/properties` y `/admin/units`, reutilizando sesión, forced-password guard, CSRF global y base path existentes.

La UI privada incluye:

- listado/cards de propiedades;
- conteos simples por estado;
- detalle/edición de propiedad;
- activación/inactivación;
- creación de unidad;
- detalle/edición de unidad;
- tipo inmutable;
- campos contextuales por tipo;
- parking→apartment de la misma propiedad;
- estado/publicación;
- precio, descripción y requisitos;
- fotos, videos, portada y orden;
- preview privado;
- enlace público cuando corresponde.

Launcher **Propiedades** aparece solo para owner o usuario con `properties.view`.

---

# 7. SEGURIDAD Y PRIVACIDAD

F4 reutiliza las fronteras existentes y añade cobertura específica:

- CSRF global en POST privados;
- forced password change;
- permisos;
- subfolder/base-path público y privado;
- escape HTML;
- allowlist pública explícita;
- no IDOR en preview privado;
- media física fuera del webroot;
- bloqueo directo de storage por boundary existente;
- MIME real;
- path containment;
- no IDs internos en URLs públicas;
- media desaparece cuando deja de ser publicable.

---

# 8. UX / VISUAL

F4 reutiliza el sistema visual F3 y un layout público ligero. No agrega framework frontend ni segundo design system.

QA visual final verificada con Chromium/Playwright sobre DOM/CSS/JS reales:

- 360×800;
- 390×844;
- 412×915;
- 768×1024;
- 1280×900;
- reflow equivalente a 200%.

Pantallas:

- landing;
- catálogo;
- detalle público;
- propiedades;
- detalle propiedad;
- detalle unidad.

Verificado:

- sin overflow horizontal normal;
- imágenes responsivas;
- URLs largas con wrapping;
- foco visible;
- dialog Apps;
- bottom nav sin solapamiento;
- controles privados con primitivas CSS locales.

---

# 9. ARQUITECTURA FINAL F4

F4 conserva:

- 1 app PHP;
- 1 DB;
- 1 repo;
- 1 auth;
- 1 entidad `units`;
- 1 regla de publicabilidad;
- 1 `PropertyService`;
- 1 `UnitMediaService`;
- 1 archivo `app/Properties/routes.php` para rutas F4 públicas y privadas;
- 1 layout público ligero;
- CSS/JS nativos existentes.

Delta de dependencias runtime: 0.  
Delta Node/npm: 0.  
ORM/repository/DTO/DI container: 0.

---

# 10. FUERA DE ALCANCE F4

- candidatos;
- Aplicar;
- estudio aseguradora;
- contratos;
- leases/tenant portal;
- cartera/pagos;
- documentos privados;
- inventarios;
- upload/transcoding video;
- SEO avanzado;
- CMS independiente;
- analytics decorativa.

---

# 11. EVIDENCIA DE CIERRE FUNCIONAL

Último GREEN de producto previo a convergencia documental:

`54836fa6a3baae49f0f60085e39d7ccb1f4d8198`

CI:

- run `38211673768`;
- PHP 8.3 PASS;
- PHP 8.4 PASS;
- PHP 8.5 PASS;
- Composer validate PASS;
- PHPUnit: **151 tests / 873 assertions / 0 failures**.

F4 todavía no se considera mergeada hasta completar convergencia documental, limpieza segura, gate final y aprobación explícita del usuario.

---

# 12. GATE ACTUAL

```text
F4.8A CODE CONVERGENCE + VISUAL QA: CLOSED / GREEN
F4.8B DOCUMENT CONVERGENCE: IN PROGRESS
F4.8C SAFE CHECKPOINT CLEANUP: PENDING
F4.8D FINAL TWO-LAWS GATE + F4_CLOSED: PENDING
F4.8E PR REVIEW: PENDING
MERGE: NOT AUTHORIZED
F5: BLOCKED
```
