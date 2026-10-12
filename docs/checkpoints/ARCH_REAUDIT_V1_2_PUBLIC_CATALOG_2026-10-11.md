# ALQUILA PRO — REAUDITORÍA ARQUITECTÓNICA V1.2 / CATÁLOGO PÚBLICO

**Fecha local:** 2026-10-11 (America/Bogota)  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Rama documental:** `spec/v1.2-public-catalog-reaudit-20261011`  
**Estado:** DOCUMENTACIÓN / ARQUITECTURA — SIN CÓDIGO DE PRODUCTO NUEVO  

---

## 1. Motivo

Nueva necesidad de producto:

- el dominio público debe tener `Iniciar sesión` y `Catálogo`;
- catálogo de apartamentos y parqueaderos disponibles;
- precio, fotos, descripción, ciudad/sector, requisitos y video;
- CTA `Aplicar` que termine en el flujo real de candidatos;
- administración del catálogo desde el mismo sistema;
- mantener un solo login para staff + inquilinos;
- reauditar programa completo bajo KISS y Cero Ruido.

---

## 2. Fuentes auditadas

- README vigente;
- SPEC maestra V1.1;
- PLAN maestro V1.1;
- roadmap F0–F26;
- estado F1/F2/F3 mergeado;
- `app/Auth/AuthService.php` real;
- schema/migrations `users` reales;
- checkpoints vivos en `docs/checkpoints/`;
- arquitectura F3 de shell/branding.

---

## 3. Estado real recuperado

```text
F0 CLOSED
F1 CLOSED / VERIFIED / MERGED
F2 CLOSED / VERIFIED / MERGED
F3 CLOSED / VERIFIED / MERGED
F4 NOT STARTED
```

F3 merge SHA registrado previamente:
`b35c32b9f8c99646879e22b1af92e6a54690ceca`

Última suite F3 verificada:

- PHP 8.3 PASS
- PHP 8.4 PASS
- PHP 8.5 PASS
- 94 tests / 458 assertions / 0 warnings

No se reabre F1–F3.

---

## 4. Hallazgos arquitectónicos

### H1 — V1.1 no cubría superficie pública de negocio

El root instalado solo estaba concebido como entrada privada/login. Se necesita landing pública y catálogo.

**Resolución:** F4 modifica únicamente el caso `installed + no session`: `/` pasa a landing pública. Con sesión, `/` conserva shell privado. Antes de instalación conserva installer.

Esto evita crear `/app` solo por separación visual.

### H2 — Crear un módulo/tabla de catálogo separado duplicaría inventario

Una unidad ya contiene tipo, estado, precio, ubicación y disponibilidad.

**Resolución:** catálogo = proyección de `units`.

Rechazado:

- `catalog_listings`;
- `apartment_catalog`;
- `parking_catalog`;
- copia de precio/estado en CMS separado.

### H3 — “Aplicar” podría crear un CRM paralelo

Crear `lead`/`application` y después convertirlo en candidato duplicaría persona, estado y unidad de interés.

**Resolución:** F5 crea/continúa el mismo `candidate`, enlazado a la unidad.

No hay CTA `Aplicar` durante F4 si la ruta F5 no existe. Esto respeta Cero Ruido: cero botón muerto.

### H4 — Auth único vs identificador actual

Decisión de producto: propietario/admin/gestor/inquilino usan el mismo login.

Código real F2 hoy:

- `AuthService::authenticate()` recibe email;
- query real busca `users.email`;
- `users.email` es UNIQUE y NOT NULL.

SPEC histórica de inquilino: documento como usuario.

**Resolución prevista F8:** mantener el mismo `AuthService` y evolucionar el modelo con un identificador de login genérico, preferencia:

`users.login_identifier` UNIQUE

Migración prevista:

- backfill usuarios existentes con email normalizado;
- nuevos inquilinos usan documento normalizado;
- email sigue como dato de contacto;
- login UI puede rotular `Correo o documento`;
- no segundo AuthService/session/reset.

Esto debe congelarse en la SPEC específica F8 antes de código.

### H5 — Video self-hosted agrega arquitectura desproporcionada

Upload de video exige límites altos, Range/streaming, storage/bandwidth y posiblemente transcoding.

**Resolución V1:** URL HTTPS externa de video por unidad. Upload/transcoding queda P2.

### H6 — Fotos de catálogo y documentos privados no deben mezclarse

Fotos de marketing son publicables y ordenables; inventarios/documentos tienen privacidad/ciclo distinto.

**Resolución:** F4 `unit_media` solo marketing; F18 conserva sistema documental/inventarios privado.

### H7 — Disponibilidad y precio podían duplicarse

**Resolución:**

- estado de `units` = fuente de disponibilidad;
- `is_public` controla intención de publicar, no disponibilidad;
- precio comercial mensual vive una vez en `units`;
- contrato posterior guarda valor final como snapshot.

No `available + catalog_available + occupied` ni `asking + catalog + suggested` equivalentes.

### H8 — Administración “Catálogo” no requiere otro permiso/módulo

**Resolución:** publicación vive dentro de Propiedades/Unidades. Puede existir una vista/filtro UI “Catálogo”, pero comparte datos y `properties.manage`.

No `catalog.manage` mientras no exista necesidad independiente.

### H9 — Checkpoints históricos temporales F1 siguen en árbol

Detectados:

- `F1_CHECKPOINT_PAUSA_2026-10-11.md`;
- `F1_CHECKPOINT_PRE_BLOQUEO_2026-10-11.md`.

F1 ya tiene `F1_CLOSED` y `F1_MERGED`.

**Resolución:** retirar los dos temporales del árbol vivo en esta rama; la historia queda en Git.

---

## 5. Enfoques comparados

### A — CMS catálogo + leads separados

Ventaja: separación conceptual superficial.

Problemas: duplica unidades, precios, disponibilidad, personas, estados y administración.

**REJECT.**

### B — Catálogo sobre units + tabla application separada

Mejor que A, pero `application` termina duplicando candidato para el caso real actual.

**REJECT V1.** Revisar solo si aparece ciclo de vida multi-solicitud real.

### C — Units como catálogo + candidate único

- F4: properties/units/media/publicación/landing/catalog browse/detail;
- F5: CTA Aplicar + candidate/references/docs/token;
- F8: tenant sobre auth único.

**SELECCIONADO.** Menor arquitectura y fuentes de verdad.

---

## 6. Roadmap corregido

F0–F3 conservan cierre.

Cambios materiales:

- **F4:** Propiedades + unidades + media + publicación + landing pública + catálogo.
- **F5:** Aplicaciones + candidatos + referencias. Activa CTA `Aplicar`.
- **F8:** Inquilino + lease + portal reutilizando auth único; evolución de identificador sin auth paralelo.
- F22 amplía QA a superficie pública/catálogo/tokens/media.
- F24/26 incluyen catálogo/aplicación en QA/smoke.

Resto conserva orden funcional.

---

## 7. DELTAS DE ARQUITECTURA DOCUMENTAL

```text
PRODUCT_CODE_DELTA: +0
COMPOSER_DELTA: +0
DB_SCHEMA_DELTA_NOW: +0
PUBLIC_ROUTE_DELTA_NOW: +0

PLANNED_F4_TABLES_CANDIDATE:
- properties
- units
- unit_media

REJECTED_TABLES:
- catalog_listings
- apartment_catalog
- parking_catalog
- leads
- application duplicada de candidates

PLANNED_F4_PUBLIC_ROUTES:
- /                (visitor landing; authenticated remains shell)
- /catalog
- /catalog/{slug}
- controlled public media route

PLANNED_F5_PUBLIC_ROUTES:
- /catalog/{slug}/apply (exact shape frozen in F5 spec)
```

---

## 8. TWO LAWS GATE — REAUDITORÍA

### LEY_1_KISS: PASS

Porque la propuesta:

- reutiliza una app/dominio/DB;
- mantiene un auth;
- usa una sola `units` para apartment/parking;
- deriva catálogo de units;
- deriva disponibilidad del estado;
- reutiliza candidato;
- evita video hosting complejo;
- evita permiso CMS nuevo.

### LEY_2_CERO_RUIDO: PASS DOCUMENTAL

Porque explícitamente:

- elimina propuesta de catálogo duplicado;
- elimina lead/application paralelo;
- evita login de tenant;
- evita disponibilidad/precio paralelos;
- separa correctamente marketing media de documentos privados;
- retira checkpoints temporales obsoletos;
- V1.1 queda histórica y V1.2 pasa a autoridad al integrarse.

---

## 9. Archivos canónicos nuevos

- `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`
- `docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`
- este checkpoint de reauditoría
- `README.md` actualizado para apuntar a V1.2

---

## 10. Gate siguiente

1. revisión humana de V1.2;
2. integrar rama documental a `main`;
3. crear SPEC específica F4;
4. revisar/aprobar SPEC F4;
5. crear plan ejecutable F4;
6. revisar/aprobar plan F4;
7. solo entonces abrir rama implementación y RED→GREEN.

**No hay código F4 aprobado/implementado todavía.**
