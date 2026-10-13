# F6 CURRENT CHECKPOINT — 2026-10-12

**Proyecto:** Alquila Pro  
**Fase:** F6 — Estudio de aseguradora  
**Branch:** `impl/f6-screening-20261012`  
**PR:** #9 — Draft  
**Estado:** F6.1–F6.4 **CLOSED / VERIFIED**; F6.5 **NEXT**

---

## 1. AUTORIDAD

- SPEC: `docs/specs/F6_INSURER_SCREENING_SPEC.md` — APPROVED
- PLAN: `docs/plans/F6_INSURER_SCREENING_IMPLEMENTATION_PLAN.md` — APPROVED
- Base de rama: `main @ 473370f143efcd8267f9b77001b97a4ce54ec1be`

---

## 2. F6.1 — SCHEMA FOUNDATION

RED `4b9d82320931cb96ad7611a0a5f1564d526dff31` / CI `38335604993`: PHP 8.3 **212 tests / 1459 assertions / 5 expected failures**.  
GREEN `47da041908f539401d96455895c8e90517348d63` / CI `38335699671`: PHP 8.3/8.4/8.5 PASS; PHP 8.3 **212 tests / 1480 assertions**.

Resultado: +1 tabla exacta `screening_processes`, sin permisos/auth/dependencias.

---

## 3. F6.2 — SCREENING SERVICE + LIFECYCLE

RED `9782ca1843d03a6b6a1325f85da5c1277673805f` / CI `38335965246`: PHP 8.3 **220 tests / 1488 assertions / 8 expected failures**, todos por ausencia de `ScreeningService`.  
Servicio `8a9c6674dcde5adf345c02054f40e628695a97ec`.  
HEAD final wired `280ea98008ec1d209d0a1586806785da37f8dbf3` / CI `38336244480`: PHP 8.3/8.4/8.5 PASS; PHP 8.3 **220 tests / 1560 assertions**.

Resultado: +1 servicio exacto, estados/validaciones/timestamps/allowlist certificados, sin efectos laterales en candidato/unidad.

---

## 4. F6.3 — ADMINISTRATIVE CANDIDATE SCREENING

RED `b007dfd84c4e2d548766d4f8c388585c5d6598ba` / CI `38336536438`: PHP 8.3 **225 tests / 1570 assertions / 5 expected failures**.  
GREEN final `015017639f2dd2a16266bebdd52bbeea797062e7` / CI `38336774996`: PHP 8.3/8.4/8.5 PASS; PHP 8.3 **225 tests / 1619 assertions**.

Resultado:

- lectura en GET existente `/admin/candidates/{id}`;
- un único POST `/admin/candidates/{id}/screening`;
- permisos/auth/forced-password/CSRF/basePath existentes;
- create/update sin screening ID;
- terminal/withdrawn solo lectura;
- SSR escapado.

---

## 5. F6.4 — CANDIDATE PORTAL SCREENING PROJECTION

### RED válido

HEAD RED: `4afbf28c6967c8cd3c9f0023c2096ca08bbd7d28`  
CI RED: `38337021295`

PHP 8.3:

- **231 tests**
- **1627 assertions**
- **5 failures exactos**
- faltaba screening público;
- faltaba escape/allowlist pública visible;
- faltaba fallback sin URL;
- faltaba resultado terminal;
- faltaba screening visible tras withdrawal.

El contrato histórico token inválido + subfolder ya permanecía GREEN. RED: **VALID**.

### GREEN mínimo

Vista pública: `4bd15bf1ae93a29e94f4ee14eba7236a4615aa03`  
HEAD final F6.4: `0be1cef83276091be1f7dfb8399da057f6093e35`

Producto modificado únicamente:

- `resources/views/public_candidate_continue.php`;
- `app/Candidates/routes.php` — el render existente recibe solo `ScreeningService::publicForCandidate()`.

Contratos certificados:

- ninguna ruta pública nueva;
- screening ligado exclusivamente al candidato resuelto por capability token;
- proyección pública por allowlist;
- no IDs internos/notes/support/responsible;
- provider/product/result escapados;
- estados humanos Pendiente / En estudio / Aprobado / No aprobado;
- URL externa exacta, sin basePath ni token;
- `target="_blank" rel="noopener noreferrer"`;
- fallback neutral sin URL;
- terminal muestra resultado y oculta CTA;
- withdrawn conserva screening y oculta CTA;
- subfolder intacto;
- 0 CSS nuevo.

CI F6.4: `38337267371` — **SUCCESS**

- PHP 8.3 PASS — **231 tests / 1647 assertions / 0 failures / 0 errors**
- PHP 8.4 PASS
- PHP 8.5 PASS
- Composer validate --strict PASS

---

## 6. DELTA KISS ACUMULADO F6.1–F6.4

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
ADDED: 1 table + 1 service + 1 admin POST route in existing route file + screening sections in existing views
REMOVED: 0
MERGED_OR_CONVERGED: admin and candidate screening reuse the existing Candidates module, routes, auth and continuation token
DEPENDENCIES_DELTA: 0
DB_SCHEMA_DELTA: +1 table
PUBLIC_ROUTES_DELTA: 0
NEW_PERMISSIONS: 0
NEW_AUTH: 0
NEW_ROUTE_FILES: 0
NEW_SERVICES: 1 exacto
CSS_DELTA: 0
```

---

## 7. NEXT GATE — F6.5

`Security + Visual QA + Zero-Noise Closure`

Orden:

`SECURITY REGRESSION → VISUAL CONTRACT AUDIT → ONLY IF REAL DEFECT: RED/GREEN → CERO RUIDO → FINAL CHECKPOINT → FINAL CI → REVIEW`

Reglas:

- no añadir funcionalidad;
- no CSS sin defecto reproducible;
- comprobar token/ID/private-field leakage, CSRF/auth/permissions/basePath, terminal/withdrawn fail-closed, URL noreferrer y errores genéricos;
- revisar visualmente/estáticamente admin create/update/read-only y portal candidate screening sobre primitives existentes;
- conservar exactamente 1 tabla / 1 servicio / 0 permisos / 0 auth / 0 route files / 0 deps;
- reemplazar este rolling checkpoint por `F6_CLOSED_2026-10-12.md` al cerrar;
- README debe quedar F6 CLOSED / VERIFIED / NOT MERGED; F7 bloqueada hasta merge.
