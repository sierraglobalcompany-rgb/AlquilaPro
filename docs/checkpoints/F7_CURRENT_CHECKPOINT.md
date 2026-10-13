# F7 CURRENT CHECKPOINT

**Fecha:** 2026-10-12 (America/Bogota)  
**Fase:** F7 — Motor de contratos  
**Microfase:** F7.2 — Templates + Immutable Versions  
**Estado:** **GREEN / VERIFIED — NO MERGE**  
**Branch:** `impl/f7-contracts-20261012`  
**Draft PR:** `#10` — `F7: Contract engine`

## Base

- F7.1: GREEN / VERIFIED.
- PR #10: OPEN / DRAFT / NOT MERGED.
- Merge: NO autorizado.
- F7.3: NOT STARTED.

## F7.2 — Bloque 1: dominio plantillas/versiones

### RED verificado

- RED HEAD: `36b0f7b75ac266023b6b024d2002fe43dadcb763`.
- CI RED: `38345500485`.
- PHP 8.3: `246 tests / 1725 assertions / 4 failures`.
- Los 4 fallos correspondieron exclusivamente a ausencia de `ContractService`.

### GREEN verificado

- GREEN HEAD dominio: `451be285dd0a1240ad1ec5680c36083cc96f7714`.
- CI: `38345722814`.
- PHP 8.3 / 8.4 / 8.5: PASS.
- PHP 8.3: `246 tests / 1760 assertions / 0 failures / 0 errors`.

Implementado:

- `app/Contracts/ContractService.php`;
- templates con tipos exactos `urban_housing` / `independent_parking`;
- activar/desactivar sin borrar historia;
- versiones inmutables y numeración server-side transaccional;
- texto UTF-8 plano;
- placeholders automáticos allowlisted;
- variables `custom.*` declaradas/canonicalizadas;
- rechazo fail-closed de HTML, URL remota, placeholders desconocidos y declaraciones ruidosas.

Sin repository, DTO, template engine, middleware ni tablas adicionales.

## F7.2 — Bloque 2: rutas/UI administrativas

### RED verificado

- RED HEAD: `4f6013b7c33b33d42cd0ec1a21b2576a5c87d4a2`.
- CI RED: `38346062075`.
- PHP 8.3: `249 tests / 1769 assertions / 3 failures`.
- Los 3 fallos fueron exclusivamente `ContractAdminRoutes` porque `/admin/contract-templates` todavía no existía.
- Dominio F7.2 y F1–F7.1 permanecieron verdes alrededor del RED.

### GREEN verificado

- Product GREEN HEAD: `24ad099bd600d158172f8a9850342b64383c13eb`.
- CI PR GREEN: `38346463214`.
- PHP 8.3: PASS — `249 tests / 1807 assertions / 0 failures / 0 errors`.
- PHP 8.4: PASS.
- PHP 8.5: PASS.
- `composer validate --strict`: PASS.
- `composer install --no-interaction --prefer-dist --no-progress`: PASS.

Implementado exactamente:

- `app/Contracts/routes.php`;
- `resources/views/contract_templates.php`;
- `resources/views/contract_template_detail.php`;
- wiring de `ContractService` en `bootstrap/app.php`;
- card Contratos en el launcher existente `resources/views/layouts/app.php`.

Rutas F7.2 exactas:

- `GET /admin/contract-templates`;
- `POST /admin/contract-templates`;
- `GET /admin/contract-templates/{id}`;
- `POST /admin/contract-templates/{id}` con acciones allowlisted `toggle` / `new_version`.

Guardrails verificados:

- auth existente;
- forced-password gate existente;
- `contracts.view` para lectura;
- `contracts.manage` para mutación;
- owner bypass;
- CSRF global existente;
- basePath/subfolder preservado;
- SSR escapa datos almacenados;
- JSON manual inválido falla cerrado;
- viewer no muta;
- no rutas públicas contractuales.

## Arquitectura acumulada F7

- Tablas F7: `3` exactas.
- Clases F7: `1/2` — `ContractService`.
- Route files F7: `1/1` — `app/Contracts/routes.php`.
- Vistas F7: `2`.
- Permisos F7: `2`.
- Dependencia runtime directa: `dompdf/dompdf:^3.1.6`.
- CSS F7 nuevo: `0`.
- Middleware F7 nuevo: `0`.
- Rutas públicas contractuales: `0`.
- Tenant/lease/auth paralelo: `0`.
- F7.3+: no iniciado.

## Two Laws Gate — F7.2

**KISS: PASS.** Una clase de dominio, un route file, dos vistas SSR y wiring directo; sin capas anticipatorias.

**Cero Ruido / Convergencia: PASS para el producto F7.2.** Se reutilizaron auth, permisos, sesión, CSRF, View, shell, CSS y basePath existentes; no se agregaron repositorios, DTOs, motores de plantilla, middleware, tablas ni assets innecesarios.

## Gate actual

```text
F7.1: GREEN / VERIFIED
F7.2: GREEN / VERIFIED
DRAFT PR #10: OPEN / DRAFT / NOT MERGED
F7.3: NOT STARTED
MERGE_ALLOWED: NO
NEXT AUTHORIZED GATE: F7.3 — Draft Contract + Safe Preview
```

No iniciar F7.3, no marcar PR ready y no mergear sin nueva autorización del usuario.