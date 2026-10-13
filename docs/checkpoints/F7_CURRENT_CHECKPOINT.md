# F7 CURRENT CHECKPOINT

**Fecha:** 2026-10-12 (America/Bogota)  
**Fase:** F7 — Motor de contratos  
**Microfase:** F7.3 — Draft Contract + Safe Preview  
**Estado:** **GREEN / VERIFIED — NO MERGE**  
**Branch:** `impl/f7-contracts-20261012`  
**Draft PR:** `#10` — `F7: Contract engine`

## Base

- F7.1 — Runtime + Schema + Permissions: GREEN / VERIFIED.
- F7.2 — Templates + Immutable Versions: GREEN / VERIFIED.
- PR #10: OPEN / DRAFT / NOT MERGED.
- Merge: NO autorizado.
- F7.4: NOT STARTED.

## F7.3 — Bloque 1: dominio de borrador + preview

### RED verificado

- RED HEAD: `442a8d89754c58392d3525ddcc84caf2e6684cb0`.
- CI RED: `38347400006`.
- PHP 8.3: `255 tests / 1813 assertions / 6 failures`.
- Los 6 fallos pertenecieron exclusivamente al nuevo `ContractDraftTest` y reclamaron la superficie F7.3 ausente en `ContractService`.
- F1–F7.2 permanecieron verdes alrededor del RED.

### GREEN verificado

- GREEN HEAD dominio: `dc8619c787663ad003f04d2cd6fd9be2ccc84d04`.
- CI: `38347707558`.
- PHP 8.3 / 8.4 / 8.5: PASS.
- PHP 8.3: `255 tests / 1925 assertions / 0 failures / 0 errors`.

Implementado dentro del `ContractService` existente:

- `listContracts()`;
- `listForCandidate()`;
- `canCreateDraftForCandidate()`;
- `findContract()`;
- `createDraft()`;
- `updateDraft()`;
- `previewHtml()`.

Reglas verificadas:

- creación solo con candidato `pending_screening` + screening `approved`;
- unidad canónica siempre desde `candidate.unit_id`;
- `unit_id` enviado por cliente no es autoridad;
- tipos: `apartment → urban_housing`, `parking → independent_parking`;
- unidad solo `available|in_process`;
- propiedad activa obligatoria;
- plantilla activa obligatoria solo para crear un draft nuevo;
- un draft activo máximo por candidato;
- un contrato `finalized` bloquea otro contrato inicial;
- fila de candidato bloqueada con `FOR UPDATE` durante creación;
- canon contractual es snapshot negociado y no reescribe `units.monthly_price`;
- administración contractual tampoco reescribe unidad;
- candidato/unidad no cambian de estado;
- start/end/duration son explícitos; no existe cálculo legal automático;
- variables manuales, tareas y anexos se validan y canonicalizan;
- update solo mientras `status = draft`;
- draft ya fijado puede seguir usando su versión si la plantilla luego queda inactiva;
- preview solo para draft, marcado `BORRADOR`, con salida escapada;
- preview no escribe `snapshot_html`, PDF, hash ni archivo físico.

Sin clase, tabla, dependencia, middleware, repository ni DTO nuevos.

## F7.3 — Bloque 2: rutas/UI de borrador

### RED verificado

- RED HEAD: `508d1466fa396bbe10cde793b81b8163bacc479c`.
- CI RED: `38347943198`.
- PHP 8.5: `257 tests / 1930 assertions / 2 failures`.
- Los 2 fallos fueron exclusivamente las nuevas rutas aún inexistentes:
  - `GET /sub/admin/contracts` → `HttpNotFoundException`;
  - `POST /admin/contracts/1` → `HttpNotFoundException`.
- Los 6 tests de dominio F7.3 y F1–F7.2 permanecieron verdes alrededor del RED.

### GREEN verificado

- Product GREEN HEAD: `e1b24d7372e8add419c161f1778a7077d0d51120`.
- CI Product GREEN: `38348271595`.
- PHP 8.3: PASS — `257 tests / 1956 assertions / 0 failures / 0 errors`.
- PHP 8.4: PASS.
- PHP 8.5: PASS.
- `composer validate --strict`: PASS.
- `composer install --no-interaction --prefer-dist --no-progress`: PASS.

Implementado exactamente:

- extensión del único `app/Contracts/routes.php`;
- `resources/views/contracts.php`;
- `resources/views/contract_detail.php`.

Rutas F7.3 exactas:

- `GET /admin/contracts`;
- `POST /admin/contracts`;
- `GET /admin/contracts/{id}`;
- `POST /admin/contracts/{id}` con única acción F7.3 allowlisted: `save`.

Guardrails verificados:

- auth existente;
- forced-password gate existente;
- `contracts.view` para lectura;
- `contracts.manage` para mutación;
- owner bypass existente;
- CSRF global existente;
- basePath/subfolder preservado;
- JSON malformado falla cerrado con 422;
- viewer no puede mutar y no ve formulario de guardado;
- candidato, variables manuales y anexos maliciosos se muestran escapados;
- preview usa solo HTML interno ya escapado por `ContractService`;
- formulario de creación no contiene `unit_id` como autoridad;
- 0 rutas públicas contractuales;
- 0 CSS F7 nuevo.

## Arquitectura acumulada F7

- Tablas F7: `3` exactas.
- Clases F7: `1/2` — `ContractService`.
- Route files F7: `1/1` — `app/Contracts/routes.php`.
- Vistas F7: `4` — 2 de F7.2 + 2 de F7.3.
- Permisos F7: `2`.
- Dependencia runtime directa: `dompdf/dompdf:^3.1.6`.
- CSS F7 nuevo: `0`.
- Middleware F7 nuevo: `0`.
- Repositories/DTO/template engines nuevos: `0`.
- Rutas públicas contractuales: `0`.
- Tenant/lease/auth paralelo: `0`.
- F7.4+: no iniciado.

## Two Laws Gate — F7.3

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
ADDED: lógica draft/preview dentro de ContractService existente; 4 rutas privadas dentro del único route file; 2 vistas SSR.
REMOVED: 0; no apareció código muerto nuevo que requiriera eliminación.
MERGED_OR_CONVERGED: auth, SessionGuard, Permissions, CSRF, View, shell, CSS, basePath, CandidateService, ScreeningService y PropertyService reutilizados directamente.
DEPENDENCIES_DELTA: 0 en F7.3.
DB_SCHEMA_DELTA: 0 en F7.3.
PUBLIC_ROUTES_DELTA: 0.
PROOF: RED dominio 38347400006; GREEN dominio 38347707558; RED rutas/UI 38347943198; Product GREEN 38348271595 PASS PHP 8.3/8.4/8.5; PHP 8.3 = 257 tests / 1956 assertions.
```

## Gate actual

```text
F7.1: GREEN / VERIFIED
F7.2: GREEN / VERIFIED
F7.3: GREEN / VERIFIED
DRAFT PR #10: OPEN / DRAFT / NOT MERGED
F7.4: NOT STARTED
MERGE_ALLOWED: NO
NEXT AUTHORIZED GATE: F7.4 — Immutable Snapshot + PDF + SHA-256
```

No iniciar F7.4, no marcar PR ready y no mergear sin nueva autorización del usuario.