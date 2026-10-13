# Alquila Pro

Aplicación web mobile-first y autoinstalable para administrar arrendamientos de vivienda y parqueaderos, con superficie pública de catálogo y operación privada para administración/inquilinos.

**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML server-side + CSS/JS mínimo  
**Destino:** Hostinger; subdominio o subcarpeta HTTPS.  
**Estado actual:** F0–F6 CLOSED / VERIFIED / MERGED; **F7 CLOSED / VERIFIED / NOT MERGED**.  
**F6 merge SHA:** `875c990a5a3c9fa7a2adba3edcad6a790703280e`.  
**F7 final product HEAD:** `d10939aa2478a558fde0f104351c642ef23b91af`.  
**F7 final product CI:** `38355249681` — PHP 8.3/8.4/8.5 PASS; PHP 8.3 `274 tests / 2214 assertions / 0 failures / 0 errors`.

---

# 1. PUNTO DE ENTRADA OBLIGATORIO

Antes de tocar código:

1. `README.md`;
2. `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`;
3. `docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`;
4. último checkpoint `*_MERGED_*` de la fase integrada en `main`;
5. último checkpoint `*_CLOSED_*` de la fase activa/cerrada todavía no integrada;
6. SPEC/PLAN específica de la fase relevante;
7. tests y CI reales;
8. código real del repositorio.

Para F7 específicamente:

- `docs/checkpoints/F7_CLOSED_2026-10-12.md` — autoridad técnica de cierre hasta merge;
- `docs/specs/F7_CONTRACT_ENGINE_SPEC.md`;
- `docs/plans/F7_CONTRACT_ENGINE_IMPLEMENTATION_PLAN.md`.

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

F6: CLOSED / VERIFIED / MERGED
F6_FUNCTIONAL_PRODUCT_HEAD: 0be1cef83276091be1f7dfb8399da057f6093e35
F6_VERIFIED_CLOSURE_HEAD: 4d2bd2ca6ad51d0af347121fe869cfe84a8c8ab6
F6_FINAL_BRANCH_HEAD: 7fccbba585c85988913f86cf140be59e8a3f6b20
F6_PR: #9 CLOSED / MERGED
F6_MERGE_SHA: 875c990a5a3c9fa7a2adba3edcad6a790703280e
F6_FINAL_CI: 38338228406 — PHP 8.3 / 8.4 / 8.5 PASS — 234 tests / 1672 assertions / 0 failures / 0 errors

F7: CLOSED / VERIFIED / NOT MERGED
F7_BRANCH: impl/f7-contracts-20261012
F7_PR: #10 OPEN
F7_BASE_MAIN: 1b9ee85ebb224b310d4ab4f0c84a28628b86b747
F7_FINAL_PRODUCT_HEAD: d10939aa2478a558fde0f104351c642ef23b91af
F7_FINAL_PRODUCT_CI: 38355249681 — PHP 8.3 / 8.4 / 8.5 PASS
F7_PHP83: 274 tests / 2214 assertions / 0 failures / 0 errors
F7_SECURITY_QA: PASS
F7_VISUAL_QA: PASS — one reproduced overflow fixed with 7 CSS lines
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS

CURRENT_GATE: F7 FINAL PR REVIEW / READY FOR REVIEW
NEXT: merge only on explicit project continuation authorization
```

No reimplementar F1–F7. No reabrir una fase certificada sin evidencia de regresión o una nueva SPEC aprobada.

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

F5–F7 reutilizan `units.id`; no existe inventario paralelo.

## 5.3 Precio y disponibilidad

- precio comercial: `units.monthly_price`;
- disponibilidad: `units.status`;
- intención de publicación: `units.is_public`;
- publicabilidad real: propiedad activa + `available` + `is_public=1` + precio + descripción + requisitos + al menos una foto válida.

F5–F7 no cambian `units.status` automáticamente.

F7 copia el canon contractual acordado a `contracts.monthly_rent`; no sincroniza ese snapshot de vuelta al precio comercial.

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

F6 comienza únicamente sobre `pending_screening`; F7 consume el candidato aprobado por screening y **no duplica estados contractuales dentro de `candidates.status`**.

## 5.6 Estudio de aseguradora único

F6 usa exactamente una tabla `screening_processes` y un servicio `ScreeningService`.

SURA es el proveedor inicial sugerido en UI, no una arquitectura específica:

- no `sura_*` tables;
- no `SuraService`;
- no adapter/API/webhook/scraping/polling;
- no proveedor CRUD paralelo.

Estados del screening:

`pending | in_progress | approved | rejected`.

`approved|rejected` son terminales en F6. El resultado no cambia automáticamente el estado del candidato ni crea contrato por sí solo.

## 5.7 Contrato único F7

F7 usa exactamente:

- `contract_templates`;
- `contract_template_versions`;
- `contracts`;
- `ContractService`;
- `ContractPdfRenderer`.

Estados:

`draft | generated | finalized`.

Documentos V1:

- `urban_housing`;
- `independent_parking`.

Reglas clave:

- candidato debe seguir `pending_screening` con screening `approved` al crear/generar;
- unidad y propiedad se releen antes de mutaciones críticas;
- template version es inmutable;
- generated congela snapshot/PDF/hash/evidencia;
- `tasks_json` puede variar hasta finalized y no integra el snapshot contractual;
- finalized es terminal F7;
- F7 no crea tenant/lease, no ocupa la unidad y no representa firma digital;
- fecha inicial, final y duración son valores explícitos confirmados por administrador, sin inferencia legal automática;
- 0 rutas contractuales públicas.

PDF:

- Dompdf `^3.1.6`;
- remote resources off;
- storage privado `storage/contracts/`;
- nombre físico aleatorio sin PII;
- validación `%PDF-` + tamaño + SHA-256 + realpath antes de servir.

## 5.8 Permisos

F4:

- `properties.view`;
- `properties.manage`.

F5/F6:

- `candidates.view`;
- `candidates.manage`.

F7:

- `contracts.view`;
- `contracts.manage`.

Owner conserva bypass.

## 5.9 Portal candidato y screening

Rutas públicas de aplicación siguen siendo las mismas F5:

- `GET|POST /apply/{publicKey}`;
- `GET|POST /apply/continue/{token}` y subacciones existentes.

F6 y F7 agregan **0 rutas públicas**. El screening se proyecta dentro de la continuación existente mediante `ScreeningService::publicForCandidate()`.

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

F6 está **CLOSED / VERIFIED / MERGED**.

Resultado integrado:

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
- screening no cambia candidate status ni unit status;
- security regression PASS;
- visual audit PASS con CSS delta 0;
- LEY 1 KISS PASS;
- LEY 2 CERO RUIDO PASS.

Autoridad:

- `docs/checkpoints/F6_CLOSED_2026-10-12.md`;
- `docs/checkpoints/F6_MERGED_2026-10-12.md`.

---

# 8. RESULTADO F7

F7 está **CLOSED / VERIFIED / NOT MERGED**.

Resultado técnico:

- exactamente 3 tablas contractuales;
- exactamente 2 clases productivas F7;
- exactamente 1 route file Contracts;
- 2 permisos;
- Dompdf como única dependencia runtime directa nueva;
- templates versionados e inmutables;
- placeholders simples sin template engine;
- draft + preview seguro;
- snapshot HTML interno escapado;
- PDF privado con SHA-256/tamaño exactos;
- finalización terminal;
- candidate context delegado al ContractService;
- 0 rutas públicas contractuales;
- 0 tenant/lease/user/ocupación automática;
- security regression PASS;
- visual QA PASS después de corregir un overflow reproducido con 7 líneas CSS;
- CI final de producto `38355249681` PASS en PHP 8.3/8.4/8.5;
- PHP 8.3: 274 tests / 2214 assertions;
- LEY 1 KISS PASS;
- LEY 2 CERO RUIDO PASS.

Autoridad de cierre hasta integración:

- `docs/checkpoints/F7_CLOSED_2026-10-12.md`.

Después del merge verificado deberá existir:

- `docs/checkpoints/F7_MERGED_2026-10-12.md`.

---

# 9. ROADMAP V1.2

- F0 — SPEC + plan ✅
- F1 — Foundation + installer ✅
- F2 — Auth + usuarios + permisos ✅
- F3 — Shell móvil + sistema visual ✅
- F4 — Propiedades + unidades + landing + catálogo público ✅ CLOSED / VERIFIED / MERGED
- F5 — Aplicaciones + candidatos + referencias ✅ CLOSED / VERIFIED / MERGED
- F6 — Aseguradora/SURA ✅ CLOSED / VERIFIED / MERGED
- **F7 — Contratos ✅ CLOSED / VERIFIED / NOT MERGED**
- F8 — Inquilino + lease + portal usando auth único — bloqueado hasta merge F7 verificado
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

# 10. PROTOCOLO DE RECUPERACIÓN

Al retomar trabajo mientras F7 no esté mergeado:

1. verificar `main` real;
2. verificar branch `impl/f7-contracts-20261012` y PR #10;
3. leer `docs/checkpoints/F7_CLOSED_2026-10-12.md`;
4. no reabrir F7 sin evidencia de regresión o nueva SPEC;
5. revisar CI exacto del HEAD vigente antes de integrar;
6. no iniciar F8 antes de que F7 esté mergeado y el merge verificado;
7. mantener KISS + Cero Ruido + TDD por microfases.

Después del merge F7, el checkpoint `F7_MERGED_2026-10-12.md` pasa a ser la autoridad de integración.

---

# 11. GATE ACTUAL

```text
LAST_MERGED_PHASE: F6 CLOSED / VERIFIED / MERGED
F6_MERGE_SHA: 875c990a5a3c9fa7a2adba3edcad6a790703280e

ACTIVE_CLOSED_PHASE: F7 CLOSED / VERIFIED / NOT MERGED
F7_BRANCH: impl/f7-contracts-20261012
F7_PR: #10
F7_BASE_MAIN: 1b9ee85ebb224b310d4ab4f0c84a28628b86b747
F7_FINAL_PRODUCT_HEAD: d10939aa2478a558fde0f104351c642ef23b91af
F7_FINAL_PRODUCT_CI: 38355249681
F7_TESTS_PHP83: 274 / 2214
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS

CURRENT_ACTION: FINAL PR REVIEW → READY FOR REVIEW IF CLEAN
MERGE_ALLOWED: NO UNTIL NEXT EXPLICIT CONTINUA
NEXT_PHASE_AFTER_MERGE: F8 — INQUILINO + LEASE + PORTAL
```
