# F4 — PROPERTIES, UNITS AND PUBLIC CATALOG — IMPLEMENTATION PLAN AS-BUILT

**Fecha plan original:** 2026-10-11  
**Convergencia as-built:** 2026-10-12  
**Estado:** TASKS 1–7 CLOSED / TASK 8 EN CIERRE  
**Branch:** `impl/f4-properties-catalog-20261011`  
**PR:** #7 — OPEN / DRAFT / NOT MERGED  
**Spec:** `docs/specs/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_SPEC.md`  
**Merge autorizado:** NO

---

# 0. OBJETIVO

Implementar inventario único de propiedades/unidades, media segura, administración privada, landing y catálogo público sin duplicar auth, precio, disponibilidad, catálogo ni flujo de candidatos.

Este documento ya no es una instrucción preliminar de “no implementar”. Registra el plan ejecutado, las convergencias adoptadas y el gate pendiente para cerrar F4.

---

# 1. ARQUITECTURA FINAL

Dominio único bajo `app/Properties`:

- `PropertyService`: propiedades, unidades, validación, publicabilidad y consultas de catálogo;
- `UnitMediaService`: fotos, video-links, orden, portada y filesystem;
- `app/Properties/routes.php`: todas las rutas públicas y privadas F4;
- `View::renderPublic()`: layout público ligero;
- shell F3 reutilizado para administración privada.

No se añadieron:

- repository layer;
- DTO layer;
- factory layer;
- DI container;
- ORM;
- SPA/framework frontend;
- media library;
- segundo auth;
- nueva dependencia runtime.

---

# 2. CONSTRAINTS CANÓNICOS

- exactamente 3 tablas F4: `properties`, `units`, `unit_media`;
- exactamente 2 permisos: `properties.view`, `properties.manage`;
- una sola entidad `units` para `apartment|parking`;
- estados: `available|in_process|occupied|inactive|maintenance`;
- `unit.type` inmutable después de creación;
- `public_key = bin2hex(random_bytes(12))` → 24 hex;
- `public_key` solo servidor y nunca aceptado desde formulario;
- precio único: `units.monthly_price`;
- publicabilidad viva: property activa + available + is_public + precio + descripción + requisitos + foto;
- foto JPEG/PNG/WebP por MIME real, máximo 10 MiB, random name, fuera webroot;
- video V1: URL HTTPS externa;
- catálogo no expone IDs DB ni datos privados;
- F4 no muestra `Aplicar`;
- Composer runtime delta: 0;
- Node/npm delta: 0;
- auth-system delta: 0.

---

# 3. CONTRATOS PÚBLICOS FINALES

```text
GET /                                  landing visitante / shell autenticado
GET /catalog                           listado + filtros
GET /catalog/{publicKey}               detalle público
GET /catalog/{publicKey}/photo/{position} foto pública controlada
```

Convergencia importante:

- se rechazó el contrato preliminar `/catalog/media/{id}` por exponer ID interno;
- no se agregó un segundo token/slug;
- se reutiliza `public_key` + posición ordinal;
- media usa `Cache-Control: no-store` en F4 para despublicación inmediata.

---

# 4. TASK 1 — SCHEMA + PERMISOS

**Estado:** CLOSED / GREEN

Implementado:

- migration `004_create_properties_units_media.sql`;
- `properties`;
- `units`;
- `unit_media`;
- FK/uniques;
- `public_key CHAR(24)`;
- `properties.view`;
- `properties.manage`.

No tablas paralelas de catálogo/apartamento/parqueadero.

---

# 5. TASK 2 — PROPERTY + UNIT DOMAIN

**Estado:** CLOSED / GREEN

Implementado:

- CRUD de propiedades;
- activación/inactivación;
- creación/edición de unidad;
- apartamento/parqueadero en una sola entidad;
- parking→apartment del mismo property;
- estados canónicos;
- validaciones numéricas;
- unique code por property;
- `public_key` server-side;
- `unit.type` inmutable;
- queries privadas y públicas.

---

# 6. TASK 3 — MEDIA SEGURA + PUBLICABILIDAD

**Estado:** CLOSED / GREEN

Implementado:

- validación bytes/MIME con `fileinfo`;
- JPEG/PNG/WebP;
- límite 10 MiB;
- nombres aleatorios;
- storage fuera webroot;
- rollback de archivo ante fallo de persistencia;
- eliminación segura;
- video HTTPS externo;
- portada única;
- reordenamiento exacto;
- publicabilidad canónica y viva;
- public media delega a la misma regla.

---

# 7. TASK 4 — ADMINISTRACIÓN PRIVADA

**Estado:** CLOSED / VERIFIED

Rutas privadas bajo `/admin/properties` y `/admin/units`.

Incluye:

- guards sesión/forced-password;
- permisos view/manage;
- CSRF global;
- listado/cards de propiedades;
- conteos simples por estado;
- detalle/edición de propiedad;
- creación y edición de unidad;
- campos contextuales por tipo;
- parking linked apartment same-property;
- media privada;
- launcher Propiedades.

Auditorías cerradas:

- AUD-03 allowlist pública;
- AUD-05 type immutability;
- AUD-06 propertyUnits/parking;
- AUD-07 launcher;
- AUD-08 CSRF focal;
- AUD-09 contextual UI;
- AUD-10 counts.

---

# 8. TASK 5 — LANDING + CATÁLOGO PÚBLICO

**Estado:** CLOSED / VERIFIED

Implementado:

- `/` público para visitante instalado;
- `/` shell F3 para autenticado;
- root ownership convergido a F4;
- `/catalog`;
- filtros type/city/sector;
- invalid type → 422;
- `/catalog/{publicKey}`;
- 404 por clave inválida/no publicable;
- escape XSS;
- privacy allowlist;
- videos HTTPS;
- subfolder;
- no `Aplicar`;
- resumen de disponibilidad en landing solo con inventario publicable vivo.

---

# 9. TASK 6 — FRONTERA DE FOTO PÚBLICA

**Estado:** CLOSED / VERIFIED

Contrato final:

`GET /catalog/{publicKey}/photo/{position}`

Implementado:

- sin `unit_media.id` público;
- posición ordinal pública;
- portada/galería;
- MIME correcto;
- `nosniff`;
- `no-store`;
- 404 inmediato al dejar de ser publicable;
- soporte subfolder.

---

# 10. TASK 7 — SECURITY / BASE PATH / REGRESSION

**Estado:** CLOSED / VERIFIED

Evidencia específica:

- CSRF F4;
- forced password;
- permisos;
- subfolder público/privado;
- storage fuera webroot;
- boundary Apache existente;
- preview privado sin IDOR entre unidades;
- media pública no stale;
- no IDs internos en superficie pública.

Task 7 se cerró sin runtime extra: las protecciones existentes fueron caracterizadas y reutilizadas.

---

# 11. TASK 8 — CERO RUIDO + QA VISUAL + CIERRE

**Estado:** EN PROGRESO

## F4.8A — Code convergence + visual QA

**CLOSED / GREEN**

Convergencias:

- `public_routes.php` absorbido por `routes.php`;
- bootstrap registra una sola pieza F4.

QA real Chromium/Playwright:

- 360×800;
- 390×844;
- 412×915;
- 768×1024;
- 1280×900;
- reflow equivalente a 200%.

Defectos encontrados y corregidos con RED→GREEN:

- imágenes públicas sin límite responsivo;
- primitivas privadas F4 sin CSS explícito;
- URL HTTPS larga provocando overflow.

Último producto GREEN:

`54836fa6a3baae49f0f60085e39d7ccb1f4d8198`

CI `38211673768`:

- PHP 8.3 PASS;
- PHP 8.4 PASS;
- PHP 8.5 PASS;
- Composer validate PASS;
- **151 tests / 873 assertions / 0 failures**.

## F4.8B — Document convergence

**IN PROGRESS**

Objetivo:

- README estado real;
- SPEC F4 as-built;
- PLAN F4 as-built;
- resolver lenguaje preliminar de `slug`/32 hex/media ID;
- actualizar PR #7.

## F4.8C — Safe checkpoint cleanup

**PENDING**

Secuencia obligatoria:

`REFERENCIAS → AUTORIDAD → REEMPLAZO → TEST → ELIMINAR → CI`.

No borrar checkpoints históricos útiles de F1–F3. Solo WIP F4 completamente sustituido.

## F4.8D — Final gate

**PENDING**

- diff audit;
- Composer validate;
- PHPUnit completo;
- CI 8.3/8.4/8.5 del HEAD exacto;
- KISS PASS;
- Cero Ruido PASS;
- checkpoint `F4_CLOSED_...md`.

## F4.8E — PR review

**PENDING**

- actualizar PR a cierre;
- review final;
- dejar listo para merge;
- NO mergear sin aprobación explícita.

---

# 12. TDD Y EVIDENCIA

Cada cambio funcional de F4 siguió:

`RED → VERIFY RED → MINIMAL GREEN → VERIFY GREEN`.

Los cambios de caracterización donde la protección ya existía se cerraron sin fabricar fallos productivos artificiales.

No se considera GREEN por inspección: los gates funcionales se certificaron con CI.

---

# 13. DELTA ARQUITECTÓNICO F4

```text
DB_TABLES: +3
PERMISSIONS: +2
DOMAIN_SERVICES: +2
F4_ROUTE_FILES_FINAL: 1
PUBLIC_LAYOUTS: +1
RUNTIME_DEPENDENCIES: +0
NODE_NPM: +0
AUTH_SYSTEMS: +0
ORM: +0
REPOSITORY_LAYER: +0
CATALOG_TABLES: +0
AVAILABILITY_PARALLEL_STATE: +0
FUTURE_APPLY_CTA: +0
```

---

# 14. GATE ACTUAL

```text
TASKS_1_7: CLOSED / VERIFIED
TASK_8A: CLOSED / VERIFIED
TASK_8B: IN PROGRESS
TASK_8C: PENDING
TASK_8D: PENDING
TASK_8E: PENDING
F4_MERGED: NO
MERGE_AUTHORIZED: NO
F5: BLOCKED
```

No abrir F5 antes de cierre + autorización de merge F4.
