# F6 CURRENT CHECKPOINT — 2026-10-12

**Proyecto:** Alquila Pro  
**Fase:** F6 — Estudio de aseguradora  
**Branch:** `impl/f6-screening-20261012`  
**PR:** #9 — Draft  
**Estado:** F6.1 **CLOSED / VERIFIED**; F6.2 **CLOSED / VERIFIED**; F6.3 **NEXT**

---

## 1. AUTORIDAD

- SPEC: `docs/specs/F6_INSURER_SCREENING_SPEC.md` — APPROVED
- PLAN: `docs/plans/F6_INSURER_SCREENING_IMPLEMENTATION_PLAN.md` — APPROVED
- Base de rama: `main @ 473370f143efcd8267f9b77001b97a4ce54ec1be`

---

## 2. F6.1 — SCHEMA FOUNDATION

### RED válido

HEAD RED: `4b9d82320931cb96ad7611a0a5f1564d526dff31`  
CI RED: `38335604993`

PHP 8.3:

- 212 tests
- 1459 assertions
- 5 failures exactos por ausencia de migration 006 / tabla F6
- Composer strict PASS

### GREEN

Producto HEAD F6.1: `47da041908f539401d96455895c8e90517348d63`  
CI GREEN: `38335699671` — **SUCCESS**

- PHP 8.3 PASS
- PHP 8.4 PASS
- PHP 8.5 PASS
- Composer validate --strict PASS
- PHPUnit: **212 tests / 1480 assertions / 0 failures / 0 errors**

Delta:

- +1 migration;
- +1 tabla `screening_processes`;
- 0 permisos;
- 0 auth;
- 0 servicios en F6.1;
- 0 dependencias.

---

## 3. F6.2 — SCREENING SERVICE + LIFECYCLE

### RED válido

HEAD RED: `9782ca1843d03a6b6a1325f85da5c1277673805f`  
CI RED: `38335965246`

PHP 8.3:

- **220 tests**
- **1488 assertions**
- **8 failures exactos**
- todos los fallos: `ScreeningService must exist for F6.2.`
- Composer strict PASS
- sin error de fixture, setup o sintaxis.

RED clasificado: **VALID**.

### GREEN mínimo

Servicio: `app/Candidates/ScreeningService.php`  
Commit servicio: `8a9c6674dcde5adf345c02054f40e628695a97ec`

Wiring mínimo `bootstrap/app.php`:  
HEAD final F6.2: `280ea98008ec1d209d0a1586806785da37f8dbf3`

Contratos certificados:

- creación solo para candidato `pending_screening`;
- un screening por candidato;
- creación siempre `pending`;
- estados exactos `pending|in_progress|approved|rejected`;
- metadata editable solo mientras `pending|in_progress`;
- terminal `approved|rejected` inmutable;
- resultado terminal obligatorio;
- timestamps deterministas;
- provider/email/longitudes/URL HTTPS validados;
- URL sin I/O de red;
- retiro posterior bloquea mutación y conserva screening;
- screening no cambia `candidates.status`;
- screening no cambia `units.status`;
- proyección pública exacta por allowlist;
- responsible user inmutable después de creación;
- direct PDO; sin repository/DTO/interface.

CI final F6.2: `38336244480` — **SUCCESS**

- PHP 8.3 PASS — **220 tests / 1560 assertions / 0 failures / 0 errors**
- PHP 8.4 PASS
- PHP 8.5 PASS
- Composer validate --strict PASS

### DELTA KISS ACUMULADO F6.1–F6.2

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
ADDED: 1 table + 1 service
REMOVED: 0
MERGED_OR_CONVERGED: service reuses CandidateService as candidate-state authority
DEPENDENCIES_DELTA: 0
DB_SCHEMA_DELTA: +1 table
PUBLIC_ROUTES_DELTA: 0
NEW_PERMISSIONS: 0
NEW_AUTH: 0
NEW_ROUTE_FILES: 0
NEW_SERVICES: 1 exacto (ScreeningService)
```

---

## 4. NEXT GATE — F6.3

`Administrative Candidate Screening`

Orden obligatorio:

`RED admin routes/UI → VERIFY RED → MINIMAL GREEN in existing routes/view → VERIFY GREEN → FULL CI → CONVERGE`

Archivos permitidos de producto:

- `app/Candidates/routes.php`;
- `resources/views/candidate_detail.php`.

No tocar en F6.3:

- nueva ruta pública;
- `public_candidate_continue.php`;
- CSS salvo RED visual reproducible;
- nuevos permisos;
- nuevo route file;
- nuevas tablas/servicios/dependencias.

Contrato admin:

- lectura sigue en `GET /admin/candidates/{id}`;
- una única mutación nueva: `POST /admin/candidates/{id}/screening`;
- `candidates.view` lee;
- `candidates.manage` muta;
- owner bypass existente;
- auth/forced-password/CSRF/basePath existentes;
- create vs update se resuelve sin screening ID público;
- terminal solo lectura;
- candidato retirado conserva screening pero no muta;
- todo valor SSR escapado.
