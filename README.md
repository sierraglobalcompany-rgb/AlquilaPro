# Alquila Pro

Aplicación web mobile-first y autoinstalable para administrar arrendamientos de vivienda y parqueaderos, con superficie pública de catálogo y operación privada para administración/inquilinos.

**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML server-side + CSS/JS mínimo  
**Destino:** Hostinger; subdominio o subcarpeta HTTPS.  
**Estado actual:** F0–F5 CLOSED / VERIFIED / MERGED; F6 CLOSED / VERIFIED / NOT MERGED.  
**F5 merge SHA:** `8511f8f8eb0c086e84c1170940353ff5460da66b`.  
**F6 functional product HEAD:** `0be1cef83276091be1f7dfb8399da057f6093e35`.  
**F6 verified closure HEAD:** `4d2bd2ca6ad51d0af347121fe869cfe84a8c8ab6`.  
**F6 verified closure CI:** `38337944987` — PHP 8.3/8.4/8.5 PASS; PHP 8.3 `234 tests / 1672 assertions`.

---

# 1. PUNTO DE ENTRADA OBLIGATORIO

Antes de tocar código:

1. `README.md`;
2. `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`;
3. `docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`;
4. `docs/checkpoints/F6_CLOSED_2026-10-12.md` como autoridad técnica F6 actual;
5. `docs/specs/F6_INSURER_SCREENING_SPEC.md` y `docs/plans/F6_INSURER_SCREENING_IMPLEMENTATION_PLAN.md` cuando F6/F7 sea relevante;
6. `docs/checkpoints/F5_MERGED_2026-10-12.md` como autoridad histórica de integración F5;
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

No merge sin aprobación del usuario. Regla operativa vigente: cuando el usuario diga **“continua”**, autoriza ejecutar el siguiente gate previsto; si la fase ya está CLOSED / VERIFIED / READY, esa continuidad incluye el merge correspondiente.

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
F4_MERGE_SHA: 3226feeea0b1740659df46fae6499842dfbc894a
F4_FINAL_CI: 38213578871 — PHP 8.3 / 8.4 / 8.5 PASS — 151 tests / 873 assertions

F5: CLOSED / VERIFIED / MERGED
F5_FINAL_BRANCH_HEAD: 2010920d7bb1f0c854a32ddb22224abba531f408
F5_PR: #8 CLOSED / MERGED
F5_MERGE_SHA: 8511f8f8eb0c086e84c1170940353ff5460da66b
F5_FINAL_CI: 38292601759 — PHP 8.3 / 8.4 / 8.5 PASS — 207 tests / 1465 assertions

F6: CLOSED / VERIFIED / NOT MERGED
F6_FUNCTIONAL_PRODUCT_HEAD: 0be1cef83276091be1f7dfb8399da057f6093e35
F6_VERIFIED_CLOSURE_HEAD: 4d2bd2ca6ad51d0af347121fe869cfe84a8c8ab6
F6_PR: #9 OPEN / DRAFT
F6_VERIFIED_CLOSURE_CI: 38337944987 — PHP 8.3 / 8.4 / 8.5 PASS — 234 tests / 1672 assertions
F6_SECURITY_QA: PASS
F6_VISUAL_QA: PASS — CSS delta 0
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS

CURRENT_GATE: F6 FINAL DOCUMENT CONVERGENCE / PR REVIEW
NEXT: F7 only after F6 final CI + review + merge
```

No reimplementar F1–F6. No reabrir una fase certificada sin evidencia de regresión o una nueva SPEC aprobada.

---

# 5. DECISIONES CANÓNICAS HEREDADAS Y EXTENDIDAS

## 5.1 Auth único

Propietario, administrador, gestor e inquilino usan el mismo login, sesión, CSRF, recuperación, cambio de contraseña y logout. F8 debe evolucionar el auth existente, no duplicarlo.

El candidato no tiene login tradicional: continúa por capability token criptográfico hash-only en DB.

No crear `tenant-login`, `admin-login`, candidate login, segundo `AuthService` ni segunda tabla de sesiones.

## 5.2 Inventario único

F4 usa exactamente:

- `properties`;
- `units` para `apartment|parking`;
- `unit_media`.

F5/F6 reutilizan `units.id`; no existe inventario paralelo.

## 5.3 Precio y disponibilidad

- precio comercial: `units.monthly_price`;
- disponibilidad: `units.status`;
- intención de publicación: `units.is_public`;
- publicabilidad real: propiedad activa + `available` + `is_public=1` + precio + descripción + requisitos + al menos una foto válida.

Ni F5 ni F6 cambian `units.status` automáticamente.

## 5.4 Identificador y media pública

`public_key = bin2hex(random_bytes(12))` → 24 caracteres hexadecimales, generado por servidor y no editable.

Detalle público:

`GET /catalog/{publicKey}`.

Foto pública:

`GET /catalog/{publicKey}/photo/{position}`.

No se expone `unit_media.id` en URLs públicas. Las fotos viven fuera del webroot y solo se sirven mientras la unidad siga publicable. Video V1 sigue siendo enlace HTTPS externo.

## 5.5 Candidato único

F5 mantiene una sola entidad `candidates` y una sola `unit_id` activa. No existe `lead` ni `application` paralelo.

Estados F5 exactos:

`new | incomplete | verification | pending_screening | withdrawn`.

F6 comienza únicamente sobre `pending_screening` y **no duplica los estados de aseguradora en `candidates.status`**.

## 5.6 Estudio de aseguradora único

F6 usa exactamente una tabla `screening_processes` y un servicio `ScreeningService`.

SURA es el proveedor inicial sugerido en UI, no una arquitectura específica:

- no `sura_*` tables;
- no `SuraService`;
- no adapter/API/webhook/scraping/polling;
- no proveedor CRUD paralelo.

Estados del screening:

`pending | in_progress | approved | rejected`.

`approved|rejected` son terminales en F6. El resultado no autoaprueba al candidato y no crea contrato.

## 5.7 Permisos

F4:

- `properties.view`;
- `properties.manage`.

F5/F6:

- `candidates.view`;
- `candidates.manage`.

F6 agrega **0 permisos**. Owner conserva bypass.

## 5.8 Portal candidato y screening

Rutas públicas de aplicación siguen siendo las mismas F5:

- `GET|POST /apply/{publicKey}`;
- `GET|POST /apply/continue/{token}` y subacciones existentes.

F6 agrega **0 rutas públicas**. El screening se proyecta dentro de la continuación existente mediante `ScreeningService::publicForCandidate()`.

La URL externa del estudio:

- debe ser HTTPS absoluta;
- no recibe basePath ni token;
- usa `noopener noreferrer`;
- no se consulta server-side.

---

# 6. RESULTADO F5

F5 está **CLOSED / VERIFIED / MERGED**. Autoridad:

- `docs/checkpoints/F5_CLOSED_2026-10-12.md`;
- `docs/checkpoints/F5_MERGED_2026-10-12.md`.

No reabrir F5 sin evidencia.

---

# 7. RESULTADO F6

F6 quedó implementada y verificada con:

- +1 tabla exacta `screening_processes`;
- +1 servicio exacto `ScreeningService`;
- UNIQUE candidate_id;
- responsable interno nullable con FK a users;
- proveedor genérico y SURA solo como prefill;
- estados `pending|in_progress|approved|rejected`;
- terminal inmutable y resultado obligatorio;
- timestamps de inicio/resultado;
- URL HTTPS opcional sin requests externos;
- administración dentro del candidate detail existente;
- una sola mutación admin `/admin/candidates/{id}/screening` dentro del route file existente;
- `candidates.view/manage` reutilizados;
- auth / forced-password / CSRF / basePath reutilizados;
- portal candidato dentro de `/apply/continue/{token}`;
- proyección pública por allowlist;
- no IDs/notes/support/responsible en portal;
- enlace externo `noopener noreferrer`;
- fallback sin URL;
- screening terminal visible sin CTA;
- withdrawal conserva estudio y bloquea mutación/CTA;
- screening no cambia candidate status ni unit status;
- security regression PASS;
- visual audit PASS con CSS delta 0;
- dependencias runtime +0;
- npm +0;
- auth +0;
- permisos +0;
- route files +0;
- API SURA +0;
- LEY 1 KISS PASS;
- LEY 2 CERO RUIDO PASS.

Checkpoint canónico: `docs/checkpoints/F6_CLOSED_2026-10-12.md`.

---

# 8. ROADMAP V1.2

- F0 — SPEC + plan ✅
- F1 — Foundation + installer ✅
- F2 — Auth + usuarios + permisos ✅
- F3 — Shell móvil + sistema visual ✅
- F4 — Propiedades + unidades + landing + catálogo público ✅ CLOSED / VERIFIED / MERGED
- F5 — Aplicaciones + candidatos + referencias ✅ CLOSED / VERIFIED / MERGED
- F6 — Aseguradora/SURA ✅ **CLOSED / VERIFIED / NOT MERGED**
- **F7 — Contratos — BLOCKED hasta merge de F6**
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

# 9. PROTOCOLO DE RECUPERACIÓN

Al retomar trabajo:

1. verificar `main`, HEAD y PR #9 reales;
2. leer `docs/checkpoints/F6_CLOSED_2026-10-12.md`;
3. confirmar la CI exacta del HEAD final de PR #9;
4. no reabrir F6 sin evidencia de regresión o nueva SPEC;
5. F7 puede usar candidato + screening aprobados, sin copiarlos a entidades paralelas;
6. mantener KISS + Cero Ruido + TDD por microfases;
7. no abrir implementación F7 antes de integrar F6.

---

# 10. GATE ACTUAL

```text
LAST_PHASE: F6 CLOSED / VERIFIED / NOT MERGED
F6_VERIFIED_CLOSURE_HEAD: 4d2bd2ca6ad51d0af347121fe869cfe84a8c8ab6
F6_VERIFIED_CLOSURE_CI: 38337944987
PR: #9 OPEN / DRAFT UNTIL FINAL REVIEW
NEXT_PHASE: F7 — CONTRATOS
F7_STATUS: BLOCKED UNTIL F6 MERGE
NEXT_ACTION: FINAL DOC CONVERGENCE → EXACT CI → FINAL PR REVIEW → READY FOR REVIEW → STOP BEFORE MERGE
```
