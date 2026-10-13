# F7 CURRENT CHECKPOINT

**Fecha:** 2026-10-12 (America/Bogota)  
**Fase:** F7 — Motor de contratos  
**Microfase:** F7.2 — Templates + Immutable Versions  
**Estado:** **PARTIAL GREEN + ROUTE RED OPEN — PAUSED — NO MERGE**  
**Branch:** `impl/f7-contracts-20261012`  
**Draft PR:** `#10` — `F7: Contract engine`

## Base reanudada

- Checkpoint previo: `2dca3b08ee9089f86fb3913dbe3eee0596d9e4ae`.
- PR #10: OPEN / DRAFT / NOT MERGED.
- CI del checkpoint previo: `38344979348` — PASS.
- F7.1 permanece GREEN / VERIFIED.

## F7.2 — Bloque 1: dominio plantillas/versiones

### RED verificado

- RED HEAD: `36b0f7b75ac266023b6b024d2002fe43dadcb763`.
- CI RED push: `38345500485`.
- PHP 8.3: `246 tests / 1725 assertions / 4 failures`.
- Los 4 fallos esperados correspondieron exclusivamente a ausencia de `ContractService`.
- F1–F7.1 siguieron verdes alrededor del RED.

### GREEN verificado

- Product GREEN HEAD: `451be285dd0a1240ad1ec5680c36083cc96f7714`.
- CI PR GREEN: `38345722814`.
- PHP 8.3 / 8.4 / 8.5: PASS.
- PHP 8.3: `246 tests / 1760 assertions / 0 failures / 0 errors`.

Implementado en este bloque:

- `app/Contracts/ContractService.php`.
- creación/listado/consulta de templates;
- tipos exactos `urban_housing` y `independent_parking`;
- activación/desactivación sin borrar historial;
- versiones inmutables;
- numeración server-side dentro de transacción con bloqueo de template;
- `body_template` texto plano UTF-8;
- placeholders automáticos allowlisted;
- variables `custom.*` declaradas y canonicalizadas;
- rechazo de HTML/URL remota/placeholder desconocido/variables no usadas o duplicadas;
- sin repository, DTO, template engine, middleware ni tablas nuevas.

## F7.2 — Bloque 2: rutas/UI administrativas

### RED abierto

- Current pre-checkpoint RED HEAD: `4f6013b7c33b33d42cd0ec1a21b2576a5c87d4a2`.
- Test nuevo: `tests/Integration/Contracts/ContractAdminRoutesTest.php`.
- CI RED actual: `38346062075`.
- Estado al ordenar pausa: **IN PROGRESS**.
  - PHP 8.3: tests en ejecución.
  - PHP 8.4: tests en ejecución.
  - PHP 8.5: preparando entorno/setup.
- No se declara aún resultado RED final porque el usuario ordenó pausar antes de cerrar la matriz.

El RED de rutas fija:

- anónimo → `/login`;
- forced-password → `/account/password`;
- `contracts.view` lectura;
- `contracts.manage` mutación;
- owner bypass;
- CSRF global;
- basePath/subfolder correcto;
- launcher Apps muestra Contratos solo cuando corresponde;
- SSR escapa nombre/variables almacenadas;
- POST detail usa únicamente acciones allowlisted `toggle` / `new_version`;
- JSON manual inválido falla cerrado;
- viewer no puede mutar.

### Producción todavía NO implementada para este segundo bloque

No existen todavía:

- `app/Contracts/routes.php`;
- `resources/views/contract_templates.php`;
- `resources/views/contract_template_detail.php`;
- wiring de Contracts en `bootstrap/app.php`;
- card Contratos en `resources/views/layouts/app.php`.

No se inició F7.3.

## Arquitectura acumulada F7 al pausar

- Tablas F7: `3` exactas.
- Clases F7: `1/2` — solo `ContractService`.
- Route files F7: `0/1`.
- Vistas F7: `0` productivas todavía.
- Permisos F7: `2`.
- Dependencia runtime directa: `dompdf/dompdf:^3.1.6`.
- Rutas públicas contractuales: `0`.
- Tenant/lease/auth paralelo: `0`.
- F7.3+: no iniciado.

## Gate congelado al pausar

```text
F7.1: GREEN / VERIFIED
F7.2 DOMAIN: GREEN / VERIFIED
F7.2 ROUTE/UI RED: OPEN; CI 38346062075 IN PROGRESS AT PAUSE
DRAFT PR #10: OPEN / DRAFT / NOT MERGED
F7.3: NOT STARTED
MERGE_ALLOWED: NO
PROJECT STATE: PAUSED BY USER
NEXT ON RESUME: verify latest branch HEAD + PR #10 + CI 38346062075; if RED is valid, implement only F7.2 route/UI minimal GREEN, run full PHP 8.3/8.4/8.5 QA, update checkpoint, then stop/continue according to user authorization.
```

No implementar rutas/vistas, no iniciar F7.3, no marcar PR ready y no mergear hasta nueva autorización explícita del usuario.
