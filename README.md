# Alquila Pro

Aplicación web mobile-first y autoinstalable para administrar arrendamientos de vivienda y parqueaderos, con superficie pública de catálogo y operación privada para administración/inquilinos.

**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML server-side + CSS/JS mínimo  
**Destino:** Hostinger; subdominio o subcarpeta HTTPS.  
**Estado actual:** F0–F4 CLOSED / VERIFIED / MERGED.  
**F4 merge SHA:** `3226feeea0b1740659df46fae6499842dfbc894a`.  
**F5:** NOT STARTED; siguiente fase autorizada para diseño/implementación.

---

# 1. PUNTO DE ENTRADA OBLIGATORIO

Antes de tocar código:

1. `README.md`;
2. `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`;
3. `docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`;
4. `docs/checkpoints/F4_MERGED_2026-10-12.md`;
5. `docs/checkpoints/F4_CLOSED_2026-10-12.md` para detalle de F4;
6. `docs/specs/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_SPEC.md` y `docs/plans/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_IMPLEMENTATION_PLAN.md` solo cuando F4 sea relevante;
7. tests y CI reales;
8. código real del repositorio.

Regla de autoridad:

`SPEC maestra → PLAN maestro → decisiones canónicas README → SPEC/PLAN específica de fase → decisiones específicas posteriores documentadas → checkpoint → tests/CI → código`.

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

F4: CLOSED / VERIFIED / MERGED
F4_FINAL_HEAD: 5e1faa5d18b4397fcaac1b834d33c1d4fbd8bdea
F4_PR: #7 CLOSED / MERGED
F4_MERGE_SHA: 3226feeea0b1740659df46fae6499842dfbc894a
F4_FINAL_CI: 38213578871 — PHP 8.3 / 8.4 / 8.5 PASS — 151 tests / 873 assertions
F4_VISUAL_QA: PASS
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS

CURRENT_GATE: F5 — NOT STARTED
NEXT: F5 SCOPE / PRE-KISS / SPEC / PLAN
```

No reimplementar F1–F4. No reabrir F4 sin evidencia de regresión o una nueva SPEC aprobada.

---

# 5. DECISIONES CANÓNICAS HEREDADAS DE F4

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

No crear `catalog_price`, booleano paralelo de disponibilidad ni caché independiente de publicabilidad.

## 5.4 Identificador y media pública

`public_key = bin2hex(random_bytes(12))` → 24 caracteres hexadecimales, generado por servidor y no editable.

Detalle público:

`GET /catalog/{publicKey}`.

Foto pública:

`GET /catalog/{publicKey}/photo/{position}`.

No se expone `unit_media.id` en URLs públicas. Las fotos viven fuera del webroot y solo se sirven mientras la unidad siga publicable. Cache F4: `no-store`.

Video V1: enlace HTTPS externo; sin upload, transcoding ni iframe arbitrario.

## 5.5 Superficie pública

- visitante instalado en `/` → landing pública;
- usuario autenticado en `/` → shell privado F3;
- `/catalog` → listado público;
- `/catalog/{publicKey}` → detalle;
- F4 no muestra CTA **Aplicar**.

F5 podrá añadir **Aplicar** únicamente cuando exista el flujo real de candidato.

## 5.6 Permisos F4

Exactamente:

- `properties.view`;
- `properties.manage`.

Owner conserva bypass.

---

# 6. RESULTADO F4

F4 quedó implementada, verificada y mergeada con:

- CRUD/inactivación de propiedades;
- unidades apartamento/parqueadero en una sola entidad;
- relación parking → apartment del mismo property;
- `unit.type` inmutable después de creación;
- estados canónicos;
- precio, descripción, requisitos y notas privadas;
- publicabilidad viva;
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
- QA visual responsive y reflow equivalente a 200%;
- +3 tablas exactas;
- +2 permisos exactos;
- +0 dependencias runtime;
- +0 npm;
- un solo archivo de rutas F4;
- un solo auth;
- una sola fuente de precio y disponibilidad.

F4 NO incluye candidato, Aplicar, contratos, leases, documentos privados, video upload/transcoding, SEO avanzado ni CMS paralelo.

---

# 7. ROADMAP V1.2

- F0 — SPEC + plan ✅
- F1 — Foundation + installer ✅
- F2 — Auth + usuarios + permisos ✅
- F3 — Shell móvil + sistema visual ✅
- F4 — Propiedades + unidades + landing + catálogo público ✅ CLOSED / VERIFIED / MERGED
- **F5 — Aplicaciones + candidatos + referencias — NEXT / NOT STARTED**
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

---

# 8. PROTOCOLO DE RECUPERACIÓN

Al retomar trabajo:

1. verificar `main` y HEAD reales;
2. leer `docs/checkpoints/F4_MERGED_2026-10-12.md`;
3. contrastar SPEC/PLAN maestra con el alcance exacto de F5;
4. ejecutar PRE-KISS antes de diseñar F5;
5. crear SPEC/PLAN específica de F5 si hace falta para eliminar ambigüedad;
6. implementar F5 por microfases cerrables con TDD;
7. crear checkpoint entre microfases largas;
8. no reabrir contratos F4 certificados sin evidencia.

---

# 9. GATE ACTUAL

```text
LAST_PHASE: F4 CLOSED / VERIFIED / MERGED
MERGE_SHA: 3226feeea0b1740659df46fae6499842dfbc894a
NEXT_PHASE: F5 — APLICACIONES + CANDIDATOS + REFERENCIAS
F5_STATUS: NOT STARTED
NEXT_ACTION: SCOPE → PRE-KISS → SPEC/DESIGN → PLAN
```
