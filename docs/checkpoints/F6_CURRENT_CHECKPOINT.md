# F6 CURRENT CHECKPOINT — 2026-10-12

**Proyecto:** Alquila Pro  
**Fase:** F6 — Estudio de aseguradora  
**Branch:** `impl/f6-screening-20261012`  
**PR:** #9 — Draft  
**Estado:** F6.1 **CLOSED / VERIFIED**; F6.2 **CLOSED / VERIFIED**; F6.3 **CLOSED / VERIFIED**; F6.4 **NEXT**

---

## 1. AUTORIDAD

- SPEC: `docs/specs/F6_INSURER_SCREENING_SPEC.md` — APPROVED
- PLAN: `docs/plans/F6_INSURER_SCREENING_IMPLEMENTATION_PLAN.md` — APPROVED
- Base de rama: `main @ 473370f143efcd8267f9b77001b97a4ce54ec1be`

---

## 2. F6.1 — SCHEMA FOUNDATION

RED `4b9d82320931cb96ad7611a0a5f1564d526dff31` / CI `38335604993`: PHP 8.3 **212 tests / 1459 assertions / 5 expected failures**.  
GREEN `47da041908f539401d96455895c8e90517348d63` / CI `38335699671`: PHP 8.3/8.4/8.5 PASS; PHP 8.3 **212 tests / 1480 assertions**.

Resultado:

- +1 migration;
- +1 tabla `screening_processes`;
- UNIQUE candidate_id;
- FK candidato CASCADE;
- FK responsable SET NULL;
- 0 permisos/auth/dependencias.

---

## 3. F6.2 — SCREENING SERVICE + LIFECYCLE

RED `9782ca1843d03a6b6a1325f85da5c1277673805f` / CI `38335965246`: PHP 8.3 **220 tests / 1488 assertions / 8 expected failures**, todos por ausencia de `ScreeningService`.  
Servicio commit `8a9c6674dcde5adf345c02054f40e628695a97ec`.  
HEAD final wired `280ea98008ec1d209d0a1586806785da37f8dbf3` / CI `38336244480`: PHP 8.3/8.4/8.5 PASS; PHP 8.3 **220 tests / 1560 assertions**.

Certificado:

- candidato `pending_screening` como único gate;
- un proceso por candidato;
- estados exactos `pending|in_progress|approved|rejected`;
- terminal inmutable;
- resultado terminal obligatorio;
- timestamps exactos;
- HTTPS absoluto sin I/O;
- withdrawal bloquea mutación;
- 0 cambios automáticos a candidate/unit status;
- proyección pública allowlist;
- direct PDO, sin repository/DTO/interface.

---

## 4. F6.3 — ADMINISTRATIVE CANDIDATE SCREENING

### RED válido

HEAD RED: `b007dfd84c4e2d548766d4f8c388585c5d6598ba`  
CI RED: `38336536438`

PHP 8.3:

- **225 tests**
- **1570 assertions**
- **5 failures exactos**
- faltaba sección admin F6;
- faltaba POST screening;
- faltaba create/update admin;
- faltaba terminal/withdrawn read-only;
- faltaba render SSR escapado del screening.

Resto F1–F6.2: GREEN. RED: **VALID**.

### GREEN mínimo

Vista admin screening: `695fbca5eef3e80d917e46f8966a267cd366b8a5`  
HEAD final F6.3: `015017639f2dd2a16266bebdd52bbeea797062e7`

Producto modificado únicamente:

- `app/Candidates/routes.php`;
- `resources/views/candidate_detail.php`.

Contratos certificados:

- lectura sigue en `GET /admin/candidates/{id}`;
- único POST nuevo `/admin/candidates/{id}/screening`;
- `candidates.view` lee;
- `candidates.manage` muta;
- owner bypass;
- auth/forced-password/CSRF/basePath reutilizados;
- create/update sin screening ID público;
- SURA solo prefill de UI;
- responsible user = creador y no reasignable;
- terminal y withdrawn read-only;
- validación retorna 422 sin mutación parcial;
- SSR escapa campos del screening.

CI F6.3: `38336774996` — **SUCCESS**

- PHP 8.3 PASS — **225 tests / 1619 assertions / 0 failures / 0 errors**
- PHP 8.4 PASS
- PHP 8.5 PASS
- Composer validate --strict PASS

---

## 5. DELTA KISS ACUMULADO F6.1–F6.3

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
ADDED: 1 table + 1 service + 1 admin POST route inside existing route file
REMOVED: 0
MERGED_OR_CONVERGED: screening admin lives inside existing candidate module/detail
DEPENDENCIES_DELTA: 0
DB_SCHEMA_DELTA: +1 table
PUBLIC_ROUTES_DELTA: 0
NEW_PERMISSIONS: 0
NEW_AUTH: 0
NEW_ROUTE_FILES: 0
NEW_SERVICES: 1 exacto
```

---

## 6. NEXT GATE — F6.4

`Candidate Portal Screening Projection`

Orden:

`RED public portal → VERIFY RED → MINIMAL GREEN existing render helper/view → VERIFY GREEN → FULL CI → CONVERGE`

Producto permitido:

- `app/Candidates/routes.php` — solo pasar `screening->publicForCandidate()` al render existente;
- `resources/views/public_candidate_continue.php` — solo sección screening pública.

Guardrails:

- no nueva ruta pública;
- no nuevo login/token;
- screening se resuelve únicamente desde el candidato del capability token;
- no exponer screening/candidate/responsible IDs, notes ni support_reference;
- URL externa exacta HTTPS, sin basePath ni token;
- `rel="noopener noreferrer"` o protección superior;
- fallback cuando no hay URL;
- terminal muestra resultado y oculta CTA;
- withdrawn conserva sección pero oculta CTA;
- subfolder intacto;
- CSS solo con RED visual reproducible.
