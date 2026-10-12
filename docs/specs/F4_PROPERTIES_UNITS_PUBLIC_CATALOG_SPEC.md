# ALQUILA PRO — F4 SPEC: PROPIEDADES + UNIDADES + LANDING + CATÁLOGO PÚBLICO

**Fecha:** 2026-10-11  
**Estado:** DISEÑO F4 PARA REVISIÓN HUMANA  
**Base canónica:** SPEC/PLAN Maestro V1.2  
**Fase:** F4 — Propiedades + unidades + publicación + landing + catálogo  
**Código de producto en esta rama:** ninguno

---

# 0. AUTORIDAD Y OBJETIVO

Esta SPEC aterriza F4 después de la reauditoría V1.2.

Orden de autoridad:

`SPEC MAESTRA V1.2 → PLAN MAESTRO V1.2 → ESTA SPEC F4 → PLAN F4 → tests → código`

Objetivos F4:

1. administrar propiedades/conjuntos;
2. administrar unidades `apartment|parking`;
3. guardar fotos y enlaces externos de video;
4. controlar estado operativo y publicación;
5. convertir el dominio raíz en landing pública para visitantes;
6. ofrecer catálogo público de unidades realmente disponibles;
7. preparar F5 sin crear candidatos, leads ni solicitudes todavía.

F4 **NO** implementa `Aplicar`. F5 añadirá esa capacidad usando el candidato único de V1.2.

---

# 1. LEYES SUPREMAS EN F4

## Ley 1 — KISS

F4 debe mantener una sola fuente de verdad por concepto:

- una tabla `units` para apartamentos y parqueaderos;
- un precio comercial por unidad;
- un estado operativo por unidad;
- el catálogo consulta las mismas unidades del módulo administrativo;
- una tabla simple de media;
- auth, CSRF, View, branding y shell existentes se reutilizan.

No crear:

- `catalog_listings`;
- tablas completas separadas `apartments` y `parking_spaces`;
- CMS genérico;
- buscador externo;
- servicio propio de video;
- framework frontend nuevo;
- storage paralelo.

## Ley 2 — Cero Ruido / Convergencia

Orden:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

Rechazado en F4:

- boolean `available` paralelo al estado;
- precio de catálogo paralelo;
- descripción de catálogo paralela;
- permisos exclusivos de catálogo;
- botón `Aplicar` sin endpoint F5;
- rutas futuras de candidatos/contratos/cartera;
- imagen duplicada en DB + filesystem;
- upload/transcoding de video;
- hard-delete como sustituto de un estado.

---

# 2. EXPERIENCIA PÚBLICA

## 2.1 `/` — landing pública + shell autenticado

### Antes de instalar

Se conserva Foundation: `GET /` conduce al instalador.

### Instalado + visitante sin sesión

`GET /` muestra landing pública con:

- branding configurado;
- texto breve de apartamentos y parqueaderos disponibles;
- botón **Ver catálogo** → `/catalog`;
- botón **Iniciar sesión** → `/login`;
- resumen de disponibilidad únicamente si existen unidades publicables;
- cero información privada u operativa.

### Instalado + usuario autenticado

`GET /` conserva el shell privado F3.

Esto reemplaza intencionalmente el contrato F3 “instalado + sin sesión → `/login`”. El test F3 correspondiente se actualizará para el nuevo producto; no coexistirán dos contratos contradictorios.

## 2.2 `/catalog`

Ruta pública, sin sesión.

Solo lista unidades cuando:

`property.is_active = 1 AND unit.status = available AND unit.is_public = 1`

Cards mobile-first con:

- foto de portada;
- Apartamento / Parqueadero;
- nombre de propiedad/conjunto;
- ciudad;
- sector;
- `location_label` si existe;
- canon/precio mensual;
- administración separada cuando aplique;
- atributos principales según tipo;
- **Ver detalles**.

Filtros V1:

- Todos / Apartamentos / Parqueaderos;
- ciudad;
- sector.

No entran búsqueda full-text, mapas, geocoding, favoritos, ranking ni filtros avanzados.

## 2.3 `/catalog/{public_key}`

Detalle público.

`public_key` es obligatorio, inmutable desde UI, generado por servidor con `bin2hex(random_bytes(12))` y almacenado como 24 caracteres hexadecimales con índice único.

Muestra:

- galería de fotos;
- tipo;
- propiedad/conjunto;
- ciudad/sector;
- ubicación pública resumida;
- descripción;
- canon/precio mensual;
- administración separada si aplica;
- requisitos;
- atributos del apartamento/parqueadero;
- enlaces HTTPS de video;
- estado público “Disponible”.

No muestra:

- dirección exacta;
- notas privadas;
- matrícula inmobiliaria;
- contacto interno de administración;
- ID interno;
- observaciones operativas;
- datos de candidato/inquilino.

La dirección exacta no se publica en F4. Si se necesita posteriormente, requiere decisión explícita de producto.

## 2.4 `Aplicar`

No se renderiza en F4.

F5 lo añadirá únicamente cuando exista el flujo real que crea/continúa el candidato y relaciona su interés con la unidad.

---

# 3. MODELO DE DATOS

F4 tiene un máximo de tres tablas nuevas.

## 3.1 `properties`

Agrupa unidades. Puede representar un edificio, conjunto, inmueble base o ubicación administrativa. Una propiedad puede tener una sola unidad sin problema.

Campos mínimos:

- `id` BIGINT UNSIGNED PK;
- `name` VARCHAR(150) NOT NULL;
- `address` VARCHAR(255) NOT NULL — privado;
- `city` VARCHAR(120) NOT NULL;
- `sector` VARCHAR(120) NULL;
- `administration_name` VARCHAR(150) NULL;
- `administration_contact` VARCHAR(190) NULL;
- `notes` TEXT NULL — privadas;
- `is_active` TINYINT(1) NOT NULL DEFAULT 1;
- `created_at` DATETIME NOT NULL;
- `updated_at` DATETIME NULL.

No se crean tablas de ciudades, sectores ni administraciones en V1.

## 3.2 `units`

Una sola tabla para apartamento y parqueadero.

Campos comunes:

- `id` BIGINT UNSIGNED PK;
- `property_id` BIGINT UNSIGNED FK → `properties.id`;
- `public_key` CHAR(24) NOT NULL UNIQUE;
- `type` VARCHAR(20) NOT NULL: `apartment|parking`;
- `code` VARCHAR(80) NOT NULL;
- `location_label` VARCHAR(190) NULL — texto seguro para mostrar públicamente;
- `status` VARCHAR(24) NOT NULL;
- `is_public` TINYINT(1) NOT NULL DEFAULT 0;
- `monthly_price` DECIMAL(12,2) NULL;
- `description` TEXT NULL — pública si se publica;
- `requirements` TEXT NULL — públicos si se publica;
- `private_notes` TEXT NULL;
- `created_at` DATETIME NOT NULL;
- `updated_at` DATETIME NULL.

Restricción:

`UNIQUE(property_id, code)`

### Apartment — columnas nullable

- `unit_number` VARCHAR(50) NULL — operativo/privado;
- `tower` VARCHAR(80) NULL;
- `floor` VARCHAR(50) NULL;
- `area_m2` DECIMAL(8,2) NULL;
- `bedrooms` SMALLINT UNSIGNED NULL;
- `bathrooms` DECIMAL(4,1) NULL;
- `administration_fee` DECIMAL(12,2) NULL;
- `registry_number` VARCHAR(120) NULL — privado.

### Parking — columnas nullable

- `parking_level` VARCHAR(80) NULL;
- `dimensions` VARCHAR(120) NULL;
- `vehicle_type` VARCHAR(80) NULL;
- `linked_apartment_id` BIGINT UNSIGNED NULL FK → `units.id`.

### Parking → apartment

Relación opcional muchos-a-uno:

- solo una unidad `parking` puede tener `linked_apartment_id`;
- el destino debe ser `apartment`;
- debe pertenecer a la misma propiedad;
- varios parqueaderos pueden apuntar al mismo apartamento;
- no se crea tabla intermedia mientras la relación no tenga atributos/ciclo de vida propios.

## 3.3 Estados

Valores canónicos:

- `available` — Disponible;
- `in_process` — En proceso;
- `occupied` — Ocupada;
- `inactive` — Inactiva;
- `maintenance` — Mantenimiento.

No existe `available` boolean.

Visibilidad pública:

`PUBLIC_VISIBLE = property.is_active && unit.status == available && unit.is_public`

`is_public` representa intención de publicación; el estado sigue siendo la única fuente de disponibilidad.

## 3.4 Reglas para activar `is_public`

Para cambiar `is_public` de 0 → 1, la unidad debe tener:

- `monthly_price > 0`;
- `description` no vacía;
- `requirements` no vacíos;
- al menos una foto válida.

No es obligatorio que en ese instante esté `available`; puede quedar preparada y oculta hasta volver a Disponible.

Una propiedad inactiva o una unidad no disponible nunca aparecen públicamente aunque `is_public = 1`.

## 3.5 `unit_media`

Una sola tabla:

- `id` BIGINT UNSIGNED PK;
- `unit_id` BIGINT UNSIGNED FK;
- `type` VARCHAR(20) NOT NULL: `photo|video_link`;
- `stored_name` VARCHAR(190) NULL;
- `mime_type` VARCHAR(80) NULL;
- `external_url` VARCHAR(1000) NULL;
- `sort_order` INT UNSIGNED NOT NULL DEFAULT 0;
- `is_cover` TINYINT(1) NOT NULL DEFAULT 0;
- `created_at` DATETIME NOT NULL.

### Foto

- JPEG, PNG o WebP;
- máximo **10 MiB**, regla obligatoria;
- MIME real por `fileinfo`;
- nombre aleatorio generado por servidor;
- almacenamiento fuera del webroot;
- `stored_name` + `mime_type` requeridos;
- `external_url = NULL`;
- extensión/nombre del cliente nunca son fuente de confianza.

### Video

- solo enlace externo HTTPS;
- `external_url` requerido;
- `stored_name = NULL`;
- `mime_type = NULL`;
- no upload;
- no transcoding;
- no iframe arbitrario;
- se abre como enlace externo seguro.

### Portada y orden

- máximo una foto `is_cover = 1` por unidad, garantizado por servicio/transacción;
- si no hay portada, primera foto por `sort_order`;
- reordenar mediante valores simples; no drag-and-drop obligatorio;
- si no hay foto, placeholder local/CSS en administración;
- una unidad no puede activarse como pública sin al menos una foto.

---

# 4. PRECIO: UNA FUENTE

`units.monthly_price` es el canon/precio comercial vigente de F4.

No crear `catalog_price` ni `suggested_rent` paralelo.

`administration_fee` permanece separado para apartamentos.

En catálogo:

- mostrar canon/precio;
- si `administration_fee > 0`, mostrarlo separado, sin sumarlo silenciosamente.

En contratos futuros, el valor contractual será snapshot propio; cambios posteriores al precio de unidad no modificarán contratos existentes.

---

# 5. ADMINISTRACIÓN Y PERMISOS

Agregar exactamente:

- `properties.view`;
- `properties.manage`.

Owner mantiene bypass.

`properties.view`:

- leer propiedades;
- leer unidades;
- previsualizar media.

`properties.manage`:

- crear/editar propiedades;
- crear/editar unidades;
- cambiar estado;
- cambiar intención de publicación;
- precio/requisitos;
- media.

No agregar `catalog.manage`, `catalog.view`, `units.manage` ni permisos equivalentes.

## Launcher Apps

“Propiedades” aparece solo cuando:

- la ruta real F4 existe;
- el usuario tiene `properties.view` o es owner.

No aparecen Candidatos, Contratos, Cartera ni Solicitudes antes de sus fases.

---

# 6. PANTALLAS PRIVADAS MÍNIMAS

## Propiedades

Cards con:

- nombre;
- ciudad/sector;
- activa/inactiva;
- conteos simples de unidades por estado;
- acceso al detalle.

## Detalle de propiedad

- datos privados;
- activar/desactivar;
- unidades relacionadas;
- crear apartamento/parqueadero.

## Detalle de unidad

- datos comunes;
- campos contextuales por tipo;
- precio;
- estado;
- intención de publicación;
- descripción;
- requisitos;
- media;
- parking→apartment opcional;
- preview público solo si `PUBLIC_VISIBLE`.

No wizard genérico. No editor drag-and-drop.

---

# 7. CONTRATOS DE RUTA

Paths exactos se congelan en el PLAN, pero la capacidad mínima es:

## Públicas

- `GET /` — landing visitante / shell autenticado;
- `GET /catalog`;
- `GET /catalog/{public_key}`;
- `GET /catalog/media/{id}`.

## Privadas

- listar/crear/ver/editar propiedad;
- activar/desactivar propiedad;
- crear/ver/editar unidad;
- cambiar estado/publicación;
- cargar/eliminar/reordenar/seleccionar portada;
- agregar/eliminar video links.

Mutaciones privadas:

- sesión;
- `properties.manage`;
- CSRF global existente;
- validación server-side.

Lecturas privadas:

- sesión;
- `properties.view` o owner.

---

# 8. MEDIA Y FRONTERA PÚBLICA

Fotos físicas fuera del webroot.

La ruta de media recibe ID de `unit_media`, nunca path del cliente.

Para visitante, antes de servir foto:

1. media existe y es `photo`;
2. unidad existe;
3. propiedad activa;
4. unidad `status = available`;
5. unidad `is_public = 1`;
6. archivo resuelto queda dentro del directorio esperado.

Si falla → 404.

Usuario autenticado con `properties.view` puede previsualizar media no pública sin duplicar el archivo.

Headers:

- `Content-Type` persistido tras validación real;
- `X-Content-Type-Options: nosniff`;
- cache pública solo cuando la media sea públicamente visible.

---

# 9. VALIDACIONES

## Property

- nombre requerido;
- dirección privada requerida;
- ciudad requerida;
- sector opcional.

## Unit

- property existente y activa al crear;
- `type` exacto;
- `code` requerido y único por property;
- estado canónico;
- precio no negativo cuando exista;
- `public_key` solo servidor;
- `is_public` boolean;
- publicación 0→1 exige precio, descripción, requisitos y foto.

## Apartment

- área/habitaciones/baños no negativos;
- columnas parking no deben dirigir comportamiento.

## Parking

- `linked_apartment_id` opcional;
- mismo property;
- destino apartment;
- no autoenlace.

## Media

- MIME real;
- máximo 10 MiB;
- JPEG/PNG/WebP;
- SVG rechazado;
- fallo DB posterior al movimiento elimina archivo nuevo;
- borrado evita archivo huérfano;
- URL de video HTTPS válida.

---

# 10. INACTIVACIÓN / BORRADO

No hay hard-delete operacional de properties ni units en F4.

## Property inactiva

- conserva unidades/media;
- desaparece todo su catálogo;
- sigue visible en administración.

## Unit `inactive`

- conserva datos/media;
- desaparece del catálogo.

`unit_media` sí puede eliminarse explícitamente por usuario autorizado, retirando registro y archivo físico cuando aplique.

No crear sistema de auditoría paralelo en F4.

---

# 11. SEGURIDAD Y PRIVACIDAD

Debe bloquear:

- IDOR administrativo;
- path traversal;
- PHP disfrazado de imagen;
- SVG activo;
- XSS en descripción/requisitos/video;
- edición sin permiso;
- mutación sin CSRF;
- publicación accidental de `private_notes`;
- exposición de `registry_number`;
- exposición de dirección exacta;
- exposición de administración/contacto privado;
- media de unidad no visible a visitante.

Texto dinámico escapado.

Video externo validado HTTPS y, al abrir nueva pestaña, `rel="noopener noreferrer"`.

---

# 12. UX / RESPONSIVE

Reutilizar F3:

- `View`;
- tokens/classes CSS existentes;
- branding;
- shell;
- Apps;
- safe areas;
- focus visible;
- touch targets;
- reduced motion.

Landing/catalog pueden requerir layout público pequeño, pero no un segundo design system.

No carousel library.

JS solo si una interacción real no queda bien con HTML/CSS.

QA visual:

- 360×800;
- 390×844;
- 412×915;
- 768×1024;
- desktop ≥1280 px;
- zoom equivalente 200%;
- teclado/focus;
- textos largos;
- foto ausente;
- cero overflow horizontal.

---

# 13. PERFORMANCE KISS

Escala inicial: decenas de unidades.

Usar:

- PDO directo/simple;
- índices útiles en FK, `public_key` y consulta pública;
- `loading="lazy"` para imágenes no críticas.

No usar:

- ElasticSearch;
- Redis;
- CDN obligatoria;
- pipeline thumbnails;
- paginación obligatoria sin volumen real;
- cache de aplicación sin medición.

---

# 14. TESTS OBLIGATORIOS

## Schema

- instalación desde cero;
- MigrationRunner idempotente;
- solo `properties`, `units`, `unit_media` nuevas;
- FK/unique esperados;
- no `catalog_listings`;
- no tablas separadas apartment/parking.

## Persistencia/reglas

- crear property;
- apartment;
- parking;
- `public_key` generado, 24 hex, único;
- code único por property;
- estados válidos/invalidos;
- parking→apartment válido;
- rechazo cross-property/cross-type;
- publicación exige contenido mínimo;
- desactivación no borra;
- una fuente de precio.

## Media

- JPEG/PNG/WebP válidos;
- MIME falso/SVG rechazados;
- >10 MiB rechazado;
- fuera del webroot;
- eliminación limpia;
- video HTTPS válido;
- URL inválida rechazada;
- portada única.

## HTTP privado

- auth;
- `properties.view`;
- `properties.manage`;
- CSRF;
- owner bypass;
- must-change-password;
- Apps muestra Propiedades solo con ruta+permiso.

## HTTP público

- preinstall `/` conserva installer;
- visitante instalado `/` landing;
- autenticado `/` shell;
- `/catalog` público;
- solo `property active + available + is_public`;
- detalle no visible/inexistente → 404;
- no filtra dirección/notas/matrícula/contacto privado;
- media no visible → 404;
- basePath/subcarpeta correcto.

## Regresión

- F1/F2/F3 completas;
- PHP 8.3/8.4/8.5.

---

# 15. DELTA ARQUITECTÓNICO MÁXIMO

```text
DB_TABLES_DELTA: +3 máximo
- properties
- units
- unit_media

NEW_PERMISSIONS: +2
- properties.view
- properties.manage

COMPOSER_DEPENDENCIES_DELTA: +0
NODE_NPM_DELTA: +0
AUTH_SYSTEMS_DELTA: +0
CATALOG_TABLES_DUPLICADAS: +0
PUBLIC_APPLICATION_TABLES: +0
```

Techo runtime esperado, no cuota:

- un `PropertyService` o equivalente;
- un archivo de rutas F4;
- vistas SSR necesarias;
- CSS existente ampliado mínimamente.

Separar un helper de media solo si reduce complejidad real; no por patrón.

---

# 16. FUERA DE F4

- Aplicar;
- candidates/references;
- consentimiento candidato;
- portal/login candidato;
- contratos;
- tenant/lease;
- cartera/pagos;
- visitas;
- WhatsApp/email;
- mapas;
- favoritos;
- chat;
- analítica de catálogo;
- SEO avanzado;
- upload/transcoding de video;
- firma;
- documentos privados;
- inventarios de entrega;
- CMS genérico;
- multiempresa/SaaS.

---

# 17. PRE-KISS

```text
ONE_UNIT_MODEL: YES
ONE_CATALOG_SOURCE: YES
ONE_PRICE_SOURCE: YES
ONE_AUTH: YES
CATALOG_LISTINGS_TABLE: NO
SEPARATE_APARTMENT_TABLE: NO
SEPARATE_PARKING_TABLE: NO
PUBLIC_LEAD_TABLE: NO
VIDEO_HOSTING: NO
CSS_FRAMEWORK_NEW: NO
JS_FRAMEWORK_NEW: NO
COMPOSER_NEW: NO
FUTURE_BUTTONS: NO
```

---

# 18. TWO-LAWS DESIGN GATE

## Ley 1 KISS — PASS de diseño

Porque inventario y catálogo comparten `units`; precio, disponibilidad, media, auth, CSRF, branding y shell no se duplican.

## Ley 2 Cero Ruido — PASS de diseño

Porque se rechazan explícitamente catálogo paralelo, lead/application paralelo, estados/precios/permisos duplicados, video hosting, rutas futuras y hard-delete operacional.

El PASS documental no cierra F4. Implementación, diff, tests, CI y QA visual deben volver a demostrar ambas leyes.

---

# 19. GATE

Antes de cualquier código F4:

1. usuario revisa/aprueba esta SPEC;
2. se escribe PLAN F4 RED→GREEN;
3. usuario revisa/aprueba PLAN;
4. se integra documentación aprobada;
5. se crea rama limpia de implementación desde `main`;
6. comienza TDD.

Hasta entonces: **cero migration, cero clase, cero ruta y cero vista de producto F4**.
