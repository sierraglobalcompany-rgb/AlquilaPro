# F6 CURRENT CHECKPOINT — 2026-10-12

**Proyecto:** Alquila Pro  
**Fase:** F6 — Estudio de aseguradora  
**Branch:** `impl/f6-screening-20261012`  
**PR:** #9 — Draft  
**Estado:** F6.1 **CLOSED / VERIFIED**; F6.2 **NEXT**

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
- 5 failures
- 0 errores de sintaxis/setup
- Composer strict PASS

Fallos exactos esperados:

- MigrationRunner esperaba migration `006` y solo encontraba 5;
- `screening_processes` inexistente;
- columnas F6 inexistentes;
- UNIQUE(candidate_id) inexistente;
- FK nullable responsable / ON DELETE SET NULL inexistente.

RED clasificado: **VALID**.

### GREEN mínimo

Producto HEAD F6.1: `47da041908f539401d96455895c8e90517348d63`

Único archivo de producción agregado:

- `database/migrations/006_create_screening_processes.sql`

Tabla añadida:

- `screening_processes`

Restricciones:

- `candidate_id` UNIQUE;
- FK `candidate_id -> candidates.id ON DELETE CASCADE`;
- FK `responsible_user_id -> users.id ON DELETE SET NULL`;
- ningún cambio en `candidates.status`;
- ninguna tabla SURA/provider/history/support paralela.

CI GREEN: `38335699671` — **SUCCESS**

- PHP 8.3 PASS
- PHP 8.4 PASS
- PHP 8.5 PASS
- Composer validate --strict PASS
- PHPUnit: **212 tests / 1480 assertions / 0 failures / 0 errors**

No fue necesaria corrección especulativa de fixtures adicionales.

---

## 3. DELTA KISS F6.1

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
ADDED: 1 migration + 1 table + schema tests
REMOVED: 0
MERGED_OR_CONVERGED: migration runner extended to canonical migration count
DEPENDENCIES_DELTA: 0
DB_SCHEMA_DELTA: +1 table (screening_processes)
PUBLIC_ROUTES_DELTA: 0
NEW_PERMISSIONS: 0
NEW_AUTH: 0
NEW_SERVICES: 0
```

---

## 4. NEXT GATE — F6.2

`ScreeningService + lifecycle`

Orden obligatorio:

`RED service → VERIFY RED → MINIMAL GREEN ScreeningService + bootstrap wiring → VERIFY GREEN → FULL CI → CONVERGE`

No tocar admin UI, portal candidato ni F6.3/F6.4 antes de cerrar F6.2.

Interfaces congeladas por PLAN:

- `findForCandidate(int): ?array`
- `createForCandidate(int, int, array, int): array`
- `updateForCandidate(int, array, int): array`
- `publicForCandidate(int): ?array`

Guardrails:

- candidato debe estar `pending_screening` para crear/mutar;
- un proceso por candidato;
- `pending|in_progress|approved|rejected` exactos;
- terminal no reabre;
- resultado terminal obligatorio;
- no cambiar `candidates.status`;
- no cambiar `units.status`;
- URL solo HTTPS absoluta con host;
- proyección pública por allowlist;
- sin API SURA, repositorio, DTO, interface ni dependencia nueva.
