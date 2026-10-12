# Alquila Pro

Aplicación web mobile-first y autoinstalable para administrar arrendamientos de vivienda y parqueaderos, con superficie pública de catálogo y operación privada para administración e inquilinos.

**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML server-side + CSS/JS mínimo  
**Destino:** Hostinger; subdominio o subcarpeta HTTPS.  
**Estado actual:** F0–F3 CLOSED / VERIFIED / MERGED; F4 implementación funcional y QA completadas, cierre documental/ceremonial en progreso en PR #7.  
**Merge F4 autorizado:** NO.

---

# 1. PUNTO DE ENTRADA OBLIGATORIO

Antes de tocar código:

1. `README.md`;
2. `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`;
3. `docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`;
4. `docs/specs/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_SPEC.md` cuando F4 sea relevante;
5. `docs/plans/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_IMPLEMENTATION_PLAN.md` cuando F4 sea relevante;
6. checkpoint más reciente de la fase;
7. tests y CI reales;
8. código real del repositorio.

Regla de autoridad:

`SPEC maestra → PLAN maestro → decisiones canónicas README → SPEC/PLAN específica de fase → checkpoint → tests/CI → código`.

Cuando una decisión específica posterior de fase documenta explícitamente una convergencia de un contrato preliminar del maestro, prevalece para esa fase hasta la siguiente revisión maestra. Esto evita revivir contratos históricos ya sustituidos.

V1.1 queda histórica.

---

# 2. LEYES SUPREMAS

## LEY 1 — KISS

Elegir la solución correcta con menos piezas, tablas, estados, dependencias y capas, conservando seguridad, trazabilidad e integridad.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

> **ELIMINAR → UNIR / CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR**

Antes de eliminar:

`REFERENCIAS → AUTORIDAD → REEMPLAZO → TEST → ELIMINAR → CI`.

No introducir código muerto, fuentes paralelas, módulos futuros, dependencias no usadas ni checkpoints redundantes sin una razón demostrable.

---

# 3. METODOLOGÍA

Roles:

- **ChatGPT:** producto, SPEC, arquitectura, UX, planes, auditoría, TDD, checkpoints y continuidad GitHub.
- **Codex:** implementación concreta, tests y refactor controlado cuando aporte valor.
- **Antigravity:** auditoría independiente/QA cuando aporte valor.

Ciclo:

`SCOPE → PRE-KISS → SPEC/DESIGN → PLAN → RED → VERIFY RED → MINIMAL GREEN → VERIFY GREEN → CONVERGE → QA → TWO LAWS GATE → CHECKPOINT → REVIEW → MERGE`.

No merge sin aprobación explícita del usuario.

---

# 4. ESTADO REAL DEL REPOSITORIO

```text
PROJECT: Alquila Pro
REPO: sierraglobalcompany-rgb/AlquilaPro
TARGET: alquilapro.sierraglobalcompany.com

F0: CLOSED
F1: CLOSED / VERIFIED / MERGED
F1_MERGE_SHA: a5225151358aaeca4698adf8cb85d815752a5ac4

F2: CLOSED / VERIFIED / MERGED
F2_MERGE_SHA: 96a4fa59ca82604d821061c6aed7d9edb3849ae1

F3: CLOSED / VERIFIED / MERGED
F3_MERGE_SHA: b35c32b9f8c99646879e22b1af92e6a54690ceca

F4: IMPLEMENTED / FUNCTIONAL QA GREEN / FINAL CLOSURE IN PROGRESS
F4_BRANCH: impl/f4-properties-catalog-20261011
F4_PR: #7 OPEN / DRAFT / NOT MERGED
F4_LAST_PRODUCT_GREEN: 54836fa6a3baae49f0f60085e39d7ccb1f4d8198
F4_LAST_CI: PHP 8.3 / 8.4 / 8.5 PASS — 151 tests / 873 assertions
F4_VISUAL_QA: PASS
CURRENT_GATE: F4.8B documentación → F4.8C limpieza segura → F4.8D gate final → F4.8E review
MERGE_AUTHORIZED: NO
```

No reimplementar F1–F3. No reabrir tareas F4 ya certificadas sin evidencia de regresión.

---

# 5. DECISIONES CANÓNICAS

## 5.1 Auth único

Propietario, administrador, gestor e inquilino usan el mismo login, sesión, CSRF, recuperación, cambio de contraseña y logout. F8 debe evolucionar el auth existente, no duplicarlo.

No crear `tenant-login`, `admin-login`, segundo `AuthService` ni segunda tabla de sesiones.

## 5.2 Inventario único

F4 usa exactamente:

- `properties`;
- `units` para `apartment|parking`;
- `unit_media`.

No existen tablas paralelas de catálogo, apartamentos o parqueaderos.

## 5.3 Precio y disponibilidad

- precio comercial: `units.monthly_price`;
- disponibilidad: `units.status`;
- intención de publicación: `units.is_public`;
- publicabilidad real: propiedad activa + `available` + `is_public=1` + precio + descripción + requisitos + al menos una foto válida.

No crear `catalog_price`, `available` boolean paralelo ni cache de publicabilidad independiente.

## 5.4 Identificador público F4

El identificador público canónico es:

`public_key = bin2hex(random_bytes(12))`

- 24 caracteres hexadecimales;
- generado por servidor;
- único;
- no editable desde UI;
- no usar slug adicional en F4.

Detalle público:

`GET /catalog/{publicKey}`.

## 5.5 Media pública F4

Las fotos se almacenan fuera del webroot y se sirven únicamente mientras la unidad siga publicable.

Contrato canónico:

`GET /catalog/{publicKey}/photo/{position}`.

No se expone `unit_media.id` en URLs públicas. `position` es un ordinal público derivado del orden de fotos. Cache fail-closed: `no-store` en F4 para que una despublicación sea efectiva inmediatamente.

Video V1: enlace HTTPS externo; sin upload, transcoding ni iframe arbitrario.

## 5.6 Superficie pública

- visitante instalado en `/` → landing pública;
- usuario autenticado en `/` → shell privado F3;
- `/catalog` → listado público;
- `/catalog/{publicKey}` → detalle;
- F4 no muestra CTA **Aplicar**.

F5 añadirá Aplicar cuando exista el flujo real de candidato.

## 5.7 Permisos F4

Exactamente:

- `properties.view`;
- `properties.manage`.

Owner conserva bypass. No `catalog.manage` ni permisos equivalentes.

---

# 6. F4 — RESULTADO IMPLEMENTADO

F4 incluye y ya tiene cobertura automatizada para:

- CRUD/inactivación de propiedades;
- unidades apartamento/parqueadero en una sola entidad;
- relación parking → apartment del mismo property;
- `unit.type` inmutable después de creación;
- estados canónicos;
- precio, descripción, requisitos y notas privadas;
- intención/publicabilidad;
- fotos JPEG/PNG/WebP por MIME real, máximo 10 MiB y fuera de webroot;
- video HTTPS externo;
- portada y orden;
- administración privada;
- launcher Propiedades;
- CSRF/permisos/subfolder/IDOR;
- landing pública;
- catálogo y filtros mínimos;
- detalle por `public_key`;
- media pública sin IDs internos;
- privacidad mediante allowlist pública;
- QA visual responsive y reflow 200%.

F4 NO incluye:

- candidato;
- Aplicar;
- contratos;
- leases;
- documentos privados;
- video upload/transcoding;
- SEO avanzado;
- CMS paralelo.

---

# 7. ROADMAP V1.2

- F0 — SPEC + plan ✅
- F1 — Foundation + installer ✅
- F2 — Auth + usuarios + permisos ✅
- F3 — Shell móvil + sistema visual ✅
- **F4 — Propiedades + unidades + landing + catálogo público — cierre final en progreso**
- F5 — Aplicaciones + candidatos + referencias
- F6 — Aseguradora/SURA
- F7 — Contratos
- F8 — Inquilino + lease + portal usando auth único
- F9 — Cargos + cartera
- F10 — Medios de pago
- F11 — Reporte + conciliación
- F12 — Servicios + obligaciones
- F13 — Pólizas + reclamaciones
- F14 — Solicitudes + mantenimiento
- F15 — Agenda + visitas
- F16 — Recordatorios + cron + email
- F17 — WhatsApp asistido
- F18 — Documentos + manual + inventarios
- F19 — Convivencia + contenido legal
- F20 — Membresías/servicios
- F21 — Bono + liquidación
- F22 — Seguridad + privacidad
- F23 — Dashboard final con datos reales
- F24 — QA PHP 8.3/8.4/8.5
- F25 — SERVER_READY
- F26 — Hostinger + smoke test

F5 permanece bloqueada hasta que F4 se cierre y el usuario autorice el merge.

---

# 8. PROTOCOLO DE RECUPERACIÓN

Al retomar trabajo:

1. verificar branch, PR y HEAD reales;
2. leer el checkpoint más reciente;
3. comparar documentación con tests/CI/código;
4. identificar el primer gate abierto;
5. continuar solo ese gate;
6. cerrar con evidencia;
7. crear checkpoint antes de abrir el siguiente bloque cuando el trabajo sea largo;
8. no mergear sin aprobación.

Checkpoint de recuperación actual mientras F4 siga abierta:

`docs/checkpoints/F4_CHECKPOINT_TASK8_VISUAL_QA_GREEN_DOC_CONVERGENCE_PENDING_2026-10-12.md`.

---

# 9. GATE ACTUAL

```text
PHASE: F4
MICROPHASE: F4.8B — CONVERGENCIA DOCUMENTAL
NEXT: F4.8C — LIMPIEZA SEGURA DE CHECKPOINTS
AFTER: F4.8D — GATE FINAL + F4_CLOSED
THEN: F4.8E — REVIEW PR #7
MERGE: BLOQUEADO HASTA APROBACIÓN EXPLÍCITA
F5: NO INICIAR
```
