# ALQUILA PRO — F4 SPEC: PROPIEDADES + UNIDADES + LANDING + CATÁLOGO PÚBLICO

**Fecha:** 2026-10-11  
**Estado:** DISEÑO F4 PARA REVISIÓN HUMANA  
**Base canónica:** SPEC/PLAN Maestro V1.2  
**Fase:** F4 — Propiedades + unidades + publicación + landing + catálogo  
**Código de producto en esta rama:** ninguno

---

# 0. AUTORIDAD Y OBJETIVO

Esta SPEC aterriza F4 después de la reauditoría V1.2.

Orden de autoridad para F4:

`SPEC MAESTRA V1.2 → PLAN MAESTRO V1.2 → ESTA SPEC F4 → PLAN F4 → tests → código`

Objetivo de F4:

1. administrar propiedades/conjuntos;
2. administrar unidades de tipo apartamento o parqueadero;
3. almacenar fotos y enlaces de video de cada unidad;
4. controlar estado operativo y publicación;
5. ofrecer una landing pública en el dominio principal;
6. ofrecer un catálogo público de unidades realmente disponibles;
7. preparar el punto de entrada para F5 sin crear todavía candidatos ni solicitudes.

F4 NO implementa el flujo `Aplicar`. Ese flujo pertenece a F5 y reutilizará el candidato único definido por la arquitectura V1.2.

---

# 1. LEYES SUPREMAS APLICADAS A F4

## Ley 1 — KISS

F4 debe resolver inventario y catálogo con el mínimo de fuentes de verdad.

Reglas obligatorias:

- una sola entidad `units` para apartamentos y parqueaderos;
- una sola fuente del precio comercial;
- una sola fuente del estado operativo;
- el catálogo consulta las mismas unidades del módulo administrativo;
- no existe tabla `catalog_listings`;
- no existe tabla separada `apartments` + `parking_spaces` con CRUD duplicado;
- no existe CMS genérico;
- no existe buscador externo;
- no existe servicio de video propio;
- no existe framework frontend nuevo;
- no existe segundo storage/media subsystem.

## Ley 2 — Cero Ruido / Convergencia

Orden obligatorio:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

F4 debe rechazar:

- `available` boolean duplicando el estado;
- precio de catálogo separado del canon/precio comercial;
- descripción de catálogo duplicada de una descripción pública ya utilizable;
- permisos exclusivos de catálogo si `properties.manage` resuelve;
- botones `Aplicar` antes de existir F5;
- rutas futuras de candidatos, contratos o cartera;
- imágenes guardadas tanto en DB como filesystem;
- uploads de video a Hostinger en V1;
- borrado físico de una unidad solo para “ocultarla”.

---

# 2. EXPERIENCIA PÚBLICA

## 2.1 Dominio raíz `/`

Después de la instalación:

### Visitante sin sesión

`GET /` muestra una landing pública con:

- branding de Alquila Pro;
- mensaje corto de disponibilidad de apartamentos y parqueaderos;
- botón real **Ver catálogo** → `/catalog`;
- botón real **Iniciar sesión** → `/login`;
- resumen simple de unidades disponibles solo si existen;
- cero módulos privados, saldos, usuarios o información operativa.

### Usuario autenticado

`GET /` conserva el comportamiento F3 y muestra el shell privado correspondiente.

### Antes de instalación

Se conserva el flujo Foundation existente hacia `/install`.

Esto reemplaza intencionalmente el contrato F3 “instalado + sin sesión → /login”. F4 debe actualizar esa prueba para demostrar la nueva landing pública, no mantener ambos comportamientos contradictorios.

## 2.2 Catálogo `/catalog`

Público, sin sesión.

Muestra únicamente unidades que cumplan simultáneamente:

- propiedad activa;
- unidad `status = available`;
- `is_public = 1`.

Vista mobile-first mediante cards.

Cada card muestra como máximo:

- foto de portada si existe;
- tipo: Apartamento / Parqueadero;
- título legible;
- propiedad/conjunto;
- ciudad;
- sector;
- ubicación resumida no sensible;
- precio mensual;
- atributos principales según tipo;
- enlace **Ver detalles**.

Filtros V1 mínimos:

- Todos / Apartamentos / Parqueaderos;
- ciudad;
- sector cuando aplique.

No se agrega motor de búsqueda, mapa, geocoding, favoritos, orden complejo ni filtros avanzados en F4.

## 2.3 Detalle público

Ruta conceptual: `GET /catalog/{public_key}`.

El identificador público debe ser estable y no depender de texto editable. Se recomienda un `public_key` aleatorio corto/seguro generado al crear la unidad y con índice único.

El detalle muestra:

- galería de fotos;
- tipo;
- propiedad/conjunto;
- ciudad/sector;
- descripción;
- precio mensual;
- requisitos de aplicación;
- atributos del apartamento/parqueadero;
- enlaces externos de video, si existen;
- estado público “Disponible”.

No muestra:

- notas privadas;
- matrícula inmobiliaria;
- contacto interno de administración;
- IDs de base de datos;
- observaciones operativas;
- datos de candidatos/inquilinos;
- dirección exacta por defecto.

La ubicación pública por defecto se limita a propiedad/conjunto + ciudad + sector. Si más adelante se requiere publicar dirección exacta, debe ser una decisión explícita de producto; no se filtra accidentalmente desde el campo privado `address`.

## 2.4 CTA “Aplicar”

NO existe en F4.

F5 lo añadirá al detalle cuando exista el endpoint real que:

- crea o continúa un candidato;
- vincula su interés con la unidad elegida;
- evita candidatos duplicados cuando sea razonablemente identificable;
- utiliza token seguro para continuación pública;
- no crea un segundo CRM de `leads`/`applications`.

---

# 3. MODELO DE DATOS F4

F4 puede crear únicamente las tablas estructurales necesarias para este ciclo de vida.

## 3.1 `properties`

Representa edificio, conjunto, propiedad base o ubicación administrativa que agrupa unidades.

Campos mínimos propuestos:

- `id` BIGINT UNSIGNED PK;
- `name` VARCHAR(150) NOT NULL;
- `address` VARCHAR(255) NOT NULL — privado/operativo;
- `city` VARCHAR(120) NOT NULL;
- `sector` VARCHAR(120) NULL;
- `administration_name` VARCHAR(150) NULL;
- `administration_contact` VARCHAR(190) NULL;
- `notes` TEXT NULL — privadas;
- `is_active` TINYINT(1) NOT NULL DEFAULT 1;
- `created_at` DATETIME;
- `updated_at` DATETIME NULL.

No se crea tabla de ciudades/sectores en V1.

## 3.2 `units`

Una sola tabla para apartamentos y parqueaderos.

Campos comunes mínimos:

- `id` BIGINT UNSIGNED PK;
- `property_id` BIGINT UNSIGNED FK → `properties.id`;
- `public_key` VARCHAR(40) NOT NULL UNIQUE;
- `type` VARCHAR(20) NOT NULL: `apartment|parking`;
- `code` VARCHAR(80) NOT NULL;
- `location_text` VARCHAR(190) NULL — ubicación interna resumida;
- `status` VARCHAR(24) NOT NULL;
- `is_public` TINYINT(1) NOT NULL DEFAULT 0;
- `monthly_price` DECIMAL(12,2) NULL;
- `description` TEXT NULL — texto apto para público;
- `requirements` TEXT NULL — requisitos públicos para futura aplicación;
- `private_notes` TEXT NULL;
- `created_at` DATETIME;
- `updated_at` DATETIME NULL.

Restricción lógica:

- `UNIQUE(property_id, code)`.

### Campos de apartamento, nullable cuando no apliquen

- `unit_number` VARCHAR(50) NULL;
- `tower` VARCHAR(80) NULL;
- `floor` VARCHAR(50) NULL;
- `area_m2` DECIMAL(8,2) NULL;
- `bedrooms` SMALLINT UNSIGNED NULL;
- `bathrooms` DECIMAL(4,1) NULL;
- `administration_fee` DECIMAL(12,2) NULL;
- `registry_number` VARCHAR(120) NULL — privado;

### Campos de parqueadero, nullable cuando no apliquen

- `parking_level` VARCHAR(80) NULL;
- `parking_location` VARCHAR(190) NULL;
- `dimensions` VARCHAR(120) NULL;
- `vehicle_type` VARCHAR(80) NULL;

### Asociación parqueadero → apartamento

- `linked_apartment_id` BIGINT UNSIGNED NULL FK → `units.id`.

Reglas:

- solo una unidad `parking` puede usar `linked_apartment_id`;
- el destino debe ser una unidad `apartment` de la misma propiedad;
- F4 permite relación opcional uno-a-uno desde el parqueadero;
- si en uso real aparece necesidad de múltiples parqueaderos por apartamento, varios parkings pueden apuntar al mismo apartamento sin tabla intermedia;
- no crear `apartment_parking` salvo que futuras reglas exijan una relación con ciclo de vida propio.

## 3.3 Estados de unidad

Valores canónicos:

- `available` — Disponible;
- `in_process` — En proceso;
- `occupied` — Ocupada;
- `inactive` — Inactiva;
- `maintenance` — Mantenimiento.

La disponibilidad pública se deriva exclusivamente de `status = available`.

`is_public` expresa únicamente intención de publicación.

Por tanto:

`PUBLICABLE = property.is_active && unit.status == available && unit.is_public`

No existe columna `available`, `published_status` ni `catalog_status`.

## 3.4 `unit_media`

Una sola tabla para media comercial de unidades.

Campos mínimos:

- `id` BIGINT UNSIGNED PK;
- `unit_id` BIGINT UNSIGNED FK;
- `type` VARCHAR(20) NOT NULL: `photo|video_link`;
- `stored_name` VARCHAR(190) NULL;
- `mime_type` VARCHAR(80) NULL;
- `external_url` VARCHAR(1000) NULL;
- `sort_order` INT UNSIGNED NOT NULL DEFAULT 0;
- `is_cover` TINYINT(1) NOT NULL DEFAULT 0;
- `created_at` DATETIME.

Reglas:

### Foto

- `stored_name` requerido;
- `mime_type` requerido;
- `external_url` NULL;
- formatos V1: JPEG, PNG, WebP;
- MIME validado por bytes con `fileinfo`;
- tamaño máximo recomendado: 10 MiB por foto;
- nombre generado aleatoriamente;
- almacenamiento fuera del webroot;
- no confiar en extensión/nombre enviado por navegador.

### Video

- `external_url` HTTPS requerido;
- `stored_name` y `mime_type` NULL;
- no upload ni transcoding de video en F4;
- la UI pública abre el enlace externo de forma segura;
- no iframe arbitrario en V1.

### Portada

- máximo una foto marcada `is_cover = 1` por unidad a nivel de servicio;
- si no existe portada, se usa la primera foto por `sort_order`;
- si no hay fotos, se usa placeholder CSS/local, no recurso remoto.

---

# 4. PRECIO: UNA SOLA FUENTE

`units.monthly_price` es el precio comercial vigente que muestra el catálogo y la administración de F4.

No crear:

- `catalog_price`;
- `suggested_rent` paralelo;
- precio en `properties` para una unidad específica.

En fases de contrato, el valor contractual será snapshot propio del contrato/lease y no dependerá retroactivamente de cambios posteriores en `units.monthly_price`.

`administration_fee` es un concepto separado y solo aplica cuando corresponda.

---

# 5. ADMINISTRACIÓN F4

## 5.1 Permisos

Agregar exactamente:

- `properties.view`;
- `properties.manage`.

Owner conserva bypass existente.

`properties.view` permite leer propiedades, unidades y media.

`properties.manage` permite crear/editar, cambiar estados, publicación, precio y media.

No agregar `catalog.view`, `catalog.manage`, `unit.manage` ni permisos equivalentes duplicados.

## 5.2 Launcher Apps

“Propiedades” aparece en Apps únicamente cuando existe la ruta real de administración F4 y el usuario tiene autorización.

No se agregan botones de Candidatos, Contratos, Cartera o Solicitudes por anticipación.

## 5.3 Pantallas administrativas mínimas

### Lista de propiedades

- cards, no tabla si no aporta;
- nombre;
- ciudad/sector;
- activas/inactivas;
- conteo de unidades por estado;
- acceso al detalle.

### Detalle/edición de propiedad

- datos de propiedad;
- unidades relacionadas;
- crear apartamento/parqueadero;
- desactivar/activar propiedad;
- no hard-delete operacional.

### Detalle/edición de unidad

- campos comunes;
- campos contextuales según tipo;
- estado;
- `is_public`;
- precio;
- descripción/requisitos;
- media;
- asociación opcional parking→apartamento;
- preview/enlace al detalle público solo si realmente publicable.

No construir wizard genérico ni editor drag-and-drop.

---

# 6. RUTAS F4 — CONTRATOS DE COMPORTAMIENTO

Los paths exactos se congelarán en el PLAN, pero F4 debe cubrir como mínimo estas capacidades.

## Públicas

- `GET /` — landing para visitante; shell para autenticado;
- `GET /catalog` — catálogo;
- `GET /catalog/{public_key}` — detalle;
- `GET /catalog/media/{id}` — media pública solo cuando pertenezca a unidad publicable.

## Privadas

- listado de propiedades;
- crear propiedad;
- ver/editar propiedad;
- activar/desactivar propiedad;
- crear unidad;
- ver/editar unidad;
- cambiar estado/publicación;
- cargar/eliminar/ordenar/seleccionar portada de fotos;
- agregar/eliminar enlaces externos de video.

Todas las mutaciones privadas:

- autenticación;
- permiso `properties.manage`;
- CSRF global existente;
- validación server-side.

Lectura privada:

- autenticación;
- `properties.view` o owner.

---

# 7. MEDIA Y FRONTERA PÚBLICA

Las fotos físicas permanecen fuera del webroot.

La ruta pública de media recibe únicamente el ID de `unit_media`; nunca una ruta de filesystem del usuario.

Antes de servir una foto a visitante verifica:

1. media existe y `type = photo`;
2. unidad existe;
3. propiedad activa;
4. unidad `status = available`;
5. unidad `is_public = 1`;
6. archivo resuelto permanece dentro del directorio de media esperado.

Si falla cualquier condición → 404 para visitante.

Un usuario privado con `properties.view` puede previsualizar media de unidades no publicadas desde el módulo administrativo, sin crear una segunda copia del archivo.

Headers mínimos de imagen:

- `Content-Type` desde MIME persistido/validado;
- `X-Content-Type-Options: nosniff`;
- caché pública razonable solo para media efectivamente pública.

---

# 8. VALIDACIONES

## Propiedad

- nombre requerido;
- dirección privada requerida;
- ciudad requerida;
- sector opcional;
- no duplicar validaciones en JS como fuente de verdad.

## Unidad

- propiedad activa/existente para crear;
- `type` solo `apartment|parking`;
- `code` requerido y único por propiedad;
- estado dentro del conjunto canónico;
- precio >= 0 cuando exista;
- `is_public` boolean real;
- `public_key` generado por servidor;
- no aceptar `public_key` arbitrario desde formulario.

### Apartamento

- campos parking no deben convertirse en fuente de comportamiento;
- habitaciones/baños/área no negativos.

### Parqueadero

- `linked_apartment_id` opcional;
- si existe, debe apuntar a apartment de misma propiedad;
- no puede apuntarse a sí mismo.

## Media

- MIME real;
- máximo 10 MiB por foto;
- JPEG/PNG/WebP;
- fallo de persistencia DB elimina archivo recién movido;
- reemplazos/borrados deben evitar archivos huérfanos;
- URL de video requiere HTTPS válida.

---

# 9. BORRADO, INACTIVACIÓN Y TRAZABILIDAD

F4 prioriza desactivación sobre hard-delete.

## Propiedad

Desactivar una propiedad:

- no borra unidades;
- todas sus unidades desaparecen del catálogo automáticamente;
- sigue visible a usuarios autorizados.

## Unidad

Estado `inactive`:

- preserva información y media;
- la retira del catálogo;
- no borra físicamente.

Hard-delete puede permitirse únicamente si el registro acaba de crearse y no tiene dependencias/media relevantes; si complica F4, se omite por completo y se usa inactivación.

No agregar sistema de auditoría paralelo en F4; la auditoría transversal se consolida en fases previstas. Los timestamps actuales son suficientes para este alcance.

---

# 10. SEGURIDAD Y PRIVACIDAD

F4 debe impedir:

- IDOR en administración;
- path traversal en media;
- upload PHP disfrazado de imagen;
- SVG activo como foto;
- publicación accidental de notas privadas;
- exposición de `registry_number`;
- exposición de dirección exacta por defecto;
- publicación de unidad no disponible;
- edición sin permiso;
- mutación sin CSRF;
- XSS mediante descripción, requisitos o URL de video.

Todo texto dinámico se escapa en HTML.

Las URLs externas deben renderizarse como URLs validadas, con `rel="noopener noreferrer"` cuando abran nueva pestaña.

---

# 11. UX / RESPONSIVE

Reutilizar F3:

- `View`;
- shell app;
- auth existente;
- branding;
- CSS nativo;
- Apps launcher;
- safe areas;
- focus visible;
- touch targets;
- reduced motion.

Landing y catálogo requieren layout público ligero; no crear un segundo design system.

Preferencia:

- reutilizar tokens/clases CSS F3;
- añadir únicamente clases públicas/catalog específicas cuando haga falta;
- JS solo para interacción demostrada, por ejemplo galería simple si HTML/CSS no basta.

No carousel library.

QA visual mínimo:

- 360×800;
- 390×844;
- 412×915;
- 768×1024;
- desktop ≥ 1280 px;
- zoom equivalente 200%;
- teclado/focus;
- imágenes faltantes;
- cards con textos largos;
- cero overflow horizontal.

---

# 12. PERFORMANCE KISS

Escala objetivo inicial: decenas, no miles, de unidades.

Por eso F4 usa:

- consultas PDO simples;
- índices en FK, `public_key`, estado/publicación cuando aporte;
- paginación no obligatoria mientras el volumen real siga pequeño;
- `loading="lazy"` en imágenes no críticas;
- portada optimizada por selección, no por pipeline de thumbnails;
- sin ElasticSearch;
- sin CDN obligatoria;
- sin Redis/cache de aplicación.

Si el catálogo crece significativamente, medir antes de agregar infraestructura.

---

# 13. TESTS OBLIGATORIOS

## Schema / migrations

- migration desde cero;
- segunda ejecución idempotente por MigrationRunner;
- existen solo tablas F4 necesarias;
- FK/unique claves;
- no existe `catalog_listings`;
- no existe tabla separada de apartments/parking.

## Service / persistencia

- crear propiedad;
- crear apartment;
- crear parking;
- código único por propiedad;
- estados válidos/invalidos;
- relación parking→apartment válida;
- rechazo relación cruzada de propiedad/tipo;
- publicación/intención;
- precio único;
- desactivación no borra.

## Media

- JPEG/PNG/WebP válidos;
- MIME falso rechazado;
- SVG rechazado;
- >10 MiB rechazado;
- archivo generado fuera del webroot;
- eliminación limpia archivo + registro;
- video HTTPS válido;
- URL inválida rechazada;
- portada única.

## HTTP privado

- auth requerida;
- `properties.view` requerido para lectura;
- `properties.manage` requerido para mutaciones;
- CSRF requerido;
- owner bypass;
- Apps muestra Propiedades solo si ruta + permiso existen;
- must-change-password sigue bloqueando shell.

## HTTP público

- antes de instalar `/` conserva installer;
- visitante instalado `/` muestra landing;
- autenticado `/` muestra shell;
- `/catalog` funciona sin sesión;
- solo lista `property active + available + is_public`;
- detalle inexistente/no publicable → 404;
- no filtra notas privadas/dirección exacta/matrícula;
- media no publicable → 404;
- subfolder/basePath correcto.

## Regresión

- F1/F2/F3 siguen verdes;
- PHP 8.3/8.4/8.5.

---

# 14. DELTA ARQUITECTÓNICO MÁXIMO F4

Permitido inicialmente:

```text
DB_TABLES_DELTA: +3 máximo
- properties
- units
- unit_media

COMPOSER_DEPENDENCIES_DELTA: +0
NODE_NPM_DELTA: +0
AUTH_SYSTEMS_DELTA: +0
CATALOG_TABLES_DUPLICADAS: +0
PUBLIC_APPLICATION_TABLES: +0
NEW_PERMISSIONS: +2
- properties.view
- properties.manage
```

Clases runtime esperadas como techo, no cuota:

- `PropertyService` o nombre equivalente que concentre reglas/persistencia F4;
- archivo de rutas F4;
- vistas SSR necesarias.

Si durante implementación aparece una razón real para separar media en helper pequeño, debe demostrarse por reutilización o reducción clara de complejidad; no por patrón arquitectónico.

---

# 15. QUÉ NO ENTRA EN F4

- Aplicar;
- candidates;
- candidate references;
- consentimiento de datos del candidato;
- portal candidato;
- login de candidato;
- contratos;
- tenant/lease;
- cartera/pagos;
- agenda de visitas;
- WhatsApp;
- email automático;
- mapa/geocoding;
- favoritos;
- chat;
- analytics de catálogo;
- SEO avanzado;
- video upload/transcoding;
- firma;
- documentos privados;
- inventario de entrega;
- CMS genérico;
- multiempresa/SaaS.

---

# 16. PRE-KISS F4

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

# 17. TWO-LAWS DESIGN GATE

## Ley 1 — KISS

**PASS de diseño** porque:

- el catálogo es una consulta/proyección del inventario real;
- apartment/parking convergen en `units`;
- precio y disponibilidad tienen una sola fuente;
- media usa una tabla simple y filesystem existente;
- video se resuelve por URL externa;
- auth/CSRF/shell/branding se reutilizan.

## Ley 2 — Cero Ruido

**PASS de diseño** porque explícitamente se eliminan/rechazan:

- catálogo paralelo;
- lead/application paralelo;
- disponibilidad duplicada;
- precio duplicado;
- permisos duplicados;
- uploads de video;
- rutas/botones futuros;
- hard-delete usado como estado.

El PASS de diseño NO equivale al PASS de implementación. F4 solo podrá cerrar cuando el diff real, tests, CI y QA visual vuelvan a demostrar ambas leyes.

---

# 18. GATE DE ESTA SPEC

Esta SPEC es únicamente el diseño F4.

Antes de implementación:

1. revisión humana/aprobación de esta SPEC;
2. escribir PLAN F4 ejecutable con tareas RED→GREEN;
3. revisión humana/aprobación del PLAN;
4. seleccionar ejecución;
5. crear rama de implementación limpia desde `main` con documentación aprobada integrada;
6. comenzar TDD.

No crear migration, clase, ruta ni vista de producto antes de completar esos gates.
