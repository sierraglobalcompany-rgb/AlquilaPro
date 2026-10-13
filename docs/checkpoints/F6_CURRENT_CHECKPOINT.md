# F6 CURRENT CHECKPOINT — 2026-10-12

**Proyecto:** Alquila Pro  
**Fase:** F6 — Estudio de aseguradora  
**Branch:** `impl/f6-screening-20261012`  
**PR:** #9 — Draft  
**Estado:** F6.1–F6.4 **CLOSED / VERIFIED**; F6.5 **SECURITY/VISUAL VERIFIED LOCALLY BY CONTRACT — FINAL MATRIX PENDING**

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

RED `4afbf28c6967c8cd3c9f0023c2096ca08bbd7d28` / CI `38337021295`: PHP 8.3 **231 tests / 1627 assertions / 5 expected failures**.  
GREEN final `0be1cef83276091be1f7dfb8399da057f6093e35` / CI `38337267371`: PHP 8.3/8.4/8.5 PASS; PHP 8.3 **231 tests / 1647 assertions**.

Resultado:
- ninguna ruta pública nueva;
- `ScreeningService::publicForCandidate()` como única proyección al portal;
- no IDs internos/notes/support/responsible;
- provider/product/result escapados;
- estados humanos;
- URL externa exacta, sin basePath ni token;
- `target="_blank" rel="noopener noreferrer"`;
- fallback sin URL;
- terminal muestra resultado y oculta CTA;
- withdrawn conserva screening y oculta CTA;
- subfolder intacto;
- 0 CSS nuevo.

---

## 6. F6.5 — SECURITY + VISUAL + CERO RUIDO

### Auditoría visual

No se reprodujo defecto visual F6 nuevo. Las primitives F3–F5 existentes ya cubren:
- controles 100% de ancho;
- touch targets 44/48 px;
- foco visible;
- safe-area/bottom-nav;
- imágenes responsive;
- links largos con `overflow-wrap:anywhere`;
- `dl dd` y `.user-card > div` con wrap para valores largos.

**CSS_DELTA: 0**.

### Regresión de seguridad

Archivo: `tests/Integration/Candidates/ScreeningSecurityRegressionTest.php`.

Primer intento `c7d19a7ddfe5e1bac6345c997676cc1f8a54db9a` / CI `38337569417` encontró dos expectativas estáticas mal calibradas, no defectos de producto:
1. prohibía `findForCandidate()` también en la superficie admin privada;
2. una expectativa de CSRF buscaba barras invertidas literales.

Se corrigió **solo el test**, sin delta de producto, en:
`f8b31dc08743ef2f2832de8e1853e61f44314fb9`.

PHP 8.3 de CI `38337687592`: **234 tests / 1672 assertions / PASS**.  
PHP 8.5: PASS.  
PHP 8.4 quedó prolongado en ejecución sin fallo reportado; no se usa como prueba final.

La regresión certifica:
- una sola arquitectura F6;
- no `SuraService`, route file paralelo ni permisos `screening.*`;
- sin curl/Guzzle/webhook/requests de tercero;
- portal recibe `publicForCandidate()`, no fila privada;
- `noopener noreferrer`;
- sin notes/support/responsible/internal IDs en vista pública;
- admin reutiliza `candidates.manage`;
- CSRF global existente;
- redacción existente del raw continuation token.

### Cero Ruido

PR #9 auditado: archivos de producto dentro del presupuesto aprobado; no cambios en `Permissions.php`, `composer.*`, CSS, auth ni route files nuevos. Comentarios del PR: 0.

---

## 7. DELTA KISS F6

```text
LEY_1_KISS: PASS PENDIENTE SOLO DE MATRIZ FINAL
LEY_2_CERO_RUIDO: PASS PENDIENTE SOLO DE MATRIZ FINAL
ADDED: 1 table + 1 service + 1 admin POST route inside existing route file + screening sections in existing views + focused tests
REMOVED: 0
MERGED_OR_CONVERGED: F6 lives entirely inside the existing Candidates/auth/token/SSR architecture
DEPENDENCIES_DELTA: 0
DB_SCHEMA_DELTA: +1 table
PUBLIC_ROUTES_DELTA: 0
NEW_PERMISSIONS: 0
NEW_AUTH: 0
NEW_ROUTE_FILES: 0
NEW_SERVICES: 1 exacto
CSS_DELTA: 0
SURA_API_DELTA: 0
```

---

## 8. FINAL GATE

Este checkpoint no declara aún F6 CLOSED.

Siguiente acción obligatoria:
1. obtener una **nueva matriz completa PHP 8.3/8.4/8.5** sobre la HEAD actual;
2. si es GREEN, crear `F6_CLOSED_2026-10-12.md`;
3. converger README a F6 CLOSED / VERIFIED / NOT MERGED;
4. eliminar este rolling checkpoint;
5. ejecutar CI exacta del HEAD documental final;
6. revisión final PR #9 → Ready for Review;
7. detenerse antes de merge.
