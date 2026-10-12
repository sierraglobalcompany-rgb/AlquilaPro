# Alquila Pro

Aplicación web mobile-first y autoinstalable para administrar arrendamientos de vivienda y parqueaderos, con superficie pública de catálogo y operación privada para administración/inquilinos.

**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML server-side + CSS/JS mínimo  
**Destino:** Hostinger; subdominio o subcarpeta HTTPS.  
**Estado actual:** F0–F4 CLOSED / VERIFIED / MERGED; F5 CLOSED / VERIFIED / NOT MERGED.  
**F4 merge SHA:** `3226feeea0b1740659df46fae6499842dfbc894a`.  
**F5 final product HEAD:** `e47bae4e4873f9c07b5353a2134ea6b95f20b4c6`.  
**F5 final product CI:** `38292062089` — PHP 8.3/8.4/8.5 PASS; PHP 8.3 `207 tests / 1465 assertions`.

---

# 1. PUNTO DE ENTRADA OBLIGATORIO

Antes de tocar código:

1. `README.md`;
2. `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`;
3. `docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`;
4. `docs/checkpoints/F5_CLOSED_2026-10-12.md` para el estado F5 actual;
5. `docs/specs/F5_APPLICATIONS_CANDIDATES_REFERENCES_SPEC.md` y `docs/plans/F5_APPLICATIONS_CANDIDATES_REFERENCES_IMPLEMENTATION_PLAN.md` cuando F5/F6 sea relevante;
6. `docs/checkpoints/F4_MERGED_2026-10-12.md` y `docs/checkpoints/F4_CLOSED_2026-10-12.md` solo para historia F4;
7. tests y CI reales;
8. código real del repositorio.

Regla de autoridad:

`SPEC maestra → PLAN maestro → decisiones canónicas README → SPEC/PLAN específica de fase → checkpoint final → tests/CI → código`.

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

F5: CLOSED / VERIFIED / NOT MERGED
F5_FINAL_PRODUCT_HEAD: e47bae4e4873f9c07b5353a2134ea6b95f20b4c6
F5_PR: #8 OPEN
F5_FINAL_PRODUCT_CI: 38292062089 — PHP 8.3 / 8.4 / 8.5 PASS — 207 tests / 1465 assertions
F5_SECURITY_QA: PASS
F5_VISUAL_QA: PASS
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS

CURRENT_GATE: F5 FINAL PR REVIEW / STOP BEFORE MERGE
NEXT: F6 only after F5 merge authorization + merge
```

No reimplementar F1–F5. No reabrir una fase certificada sin evidencia de regresión o una nueva SPEC aprobada.

---

# 5. DECISIONES CANÓNICAS HEREDADAS Y EXTENDIDAS

## 5.1 Auth único

Propietario, administrador, gestor e inquilino usan el mismo login, sesión, CSRF, recuperación, cambio de contraseña y logout. F8 debe evolucionar el auth existente, no duplicarlo.

F5 tampoco creó login de candidato: la continuación pública usa un capability token criptográfico hash-only en DB.

No crear `tenant-login`, `admin-login`, candidate login, segundo `AuthService` ni segunda tabla de sesiones.

## 5.2 Inventario único

F4 usa exactamente:

- `properties`;
- `units` para `apartment|parking`;
- `unit_media`.

F5 reutiliza `units.id` como unidad actual del candidato y no crea inventario paralelo.

## 5.3 Precio y disponibilidad

- precio comercial: `units.monthly_price`;
- disponibilidad: `units.status`;
- intención de publicación: `units.is_public`;
- publicabilidad real: propiedad activa + `available` + `is_public=1` + precio + descripción + requisitos + al menos una foto válida.

F5 no cambia `units.status` automáticamente al iniciar o enviar una candidatura.

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
- F5 añade CTA **Aplicar** en detalle publicable;
- `GET|POST /apply/{publicKey}` inicia candidatura;
- `GET|POST /apply/continue/{token}` continúa el mismo candidato;
- rutas públicas F5 nunca usan candidate/document IDs internos.

## 5.6 Permisos

F4:

- `properties.view`;
- `properties.manage`.

F5 añade exactamente:

- `candidates.view`;
- `candidates.manage`.

Owner conserva bypass.

---

# 6. RESULTADO F5

F5 quedó implementada y verificada, pendiente únicamente de merge autorizado, con:

- candidato único candidate-centric; no `lead` ni `application` paralelo;
- +4 tablas exactas: `candidates`, `candidate_references`, `unit_application_requirements`, `candidate_documents`;
- +2 permisos exactos;
- +2 servicios exactos: `CandidateService`, `CandidateDocumentService`;
- una sola `unit_id` activa por candidato;
- identidad única normalizada por `(document_type, document_number)`;
- duplicate identity genérico, sin token ni datos privados;
- token raw 256-bit, SHA-256 persistido, TTL 7 días, rotación invalidante;
- raw continuation token redactado de error logs;
- perfil, referencias y cambio controlado de unidad;
- requisitos estructurados por unidad sin delete físico;
- documentos PDF/JPEG/PNG <=10 MiB, MIME real y storage privado;
- submit con validación física de documentos obligatorios;
- administración privada de candidatos y documentos anti-IDOR;
- CTA Aplicar por `public_key`;
- lifecycle que conserva candidatos existentes si la unidad deja de ser publicable;
- CSRF global, forced-password, basePath y auth existentes reutilizados;
- throttle V1 session-local sin mutación al 429;
- visual QA con wrap de valores largos y primitives F3/F4 reutilizados;
- +0 dependencias runtime;
- +0 npm;
- +0 auth paralelo;
- +0 notification engine;
- LEY 1 KISS PASS;
- LEY 2 CERO RUIDO PASS.

Checkpoint canónico: `docs/checkpoints/F5_CLOSED_2026-10-12.md`.

---

# 7. ROADMAP V1.2

- F0 — SPEC + plan ✅
- F1 — Foundation + installer ✅
- F2 — Auth + usuarios + permisos ✅
- F3 — Shell móvil + sistema visual ✅
- F4 — Propiedades + unidades + landing + catálogo público ✅ CLOSED / VERIFIED / MERGED
- F5 — Aplicaciones + candidatos + referencias ✅ **CLOSED / VERIFIED / NOT MERGED**
- **F6 — Aseguradora/SURA — BLOCKED hasta merge autorizado de F5**
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

1. verificar `main`, HEAD y PR reales;
2. leer `docs/checkpoints/F5_CLOSED_2026-10-12.md`;
3. confirmar que PR #8 no fue mergeado o, si fue autorizado y mergeado, registrar el merge SHA antes de abrir F6;
4. no reabrir contratos F5 certificados sin evidencia;
5. F6 debe extender el mismo candidato desde `pending_screening`, no copiarlo a una entidad paralela;
6. mantener KISS + Cero Ruido + TDD por microfases.

---

# 9. GATE ACTUAL

```text
LAST_PHASE: F5 CLOSED / VERIFIED / NOT MERGED
F5_FINAL_PRODUCT_HEAD: e47bae4e4873f9c07b5353a2134ea6b95f20b4c6
F5_FINAL_PRODUCT_CI: 38292062089
PR: #8 OPEN
MERGE_ALLOWED: NO
NEXT_PHASE: F6 — BLOCKED
NEXT_ACTION: FINAL PR REVIEW -> READY FOR REVIEW IF CLEAN -> STOP BEFORE MERGE
```
