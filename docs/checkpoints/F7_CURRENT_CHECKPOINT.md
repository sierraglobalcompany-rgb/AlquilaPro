# F7 CURRENT CHECKPOINT

**Fecha:** 2026-10-12 (America/Bogota)  
**Fase:** F7 — Motor de contratos  
**Microfase:** F7.1 — Runtime + Schema + Permissions  
**Estado:** **GREEN / VERIFIED — PAUSED — NO MERGE**  
**Branch:** `impl/f7-contracts-20261012`  
**Draft PR:** `#10` — `F7: Contract engine`

## Evidencia TDD

### RED verificado

- HEAD RED final: `a68ccdd0951e8626db5895042bb2bbb274505c18`
- CI RED: `38343905885`
- PHP 8.3 / 8.4 / 8.5: failure esperado.
- PHP 8.3: `242 tests / 1664 assertions / 11 failures`.
- Fallos esperados: migration 007 ausente, 3 tablas F7 ausentes, Dompdf ausente, `dom/gd` ausentes de RuntimeRequirements, permisos contractuales ausentes y diagnóstico del instalador sin esos checks.
- No hubo código productivo antes del RED.

### GREEN verificado

- Product GREEN HEAD: `cbc4b009de5113241612174fb389285b7157b298`
- CI GREEN: `38344595488`
- PHP 8.3: PASS — `242 tests / 1721 assertions / 0 failures / 0 errors`.
- PHP 8.4: PASS.
- PHP 8.5: PASS.
- `composer validate --strict`: PASS.
- `composer install`: PASS con lock reproducible.
- Dompdf instalado: `v3.1.6`.

## Delta F7.1 permanente

- Dependencia runtime directa: `+1` — `dompdf/dompdf:^3.1.6`.
- Dependencias transitivas nuevas resueltas por Composer: `dompdf/php-font-lib`, `dompdf/php-svg-lib`, `masterminds/html5`, `sabberworm/php-css-parser`.
- Runtime requirements: `+dom`, `+gd`; `mbstring` y `fileinfo` preservados.
- CI: PHP 8.3/8.4/8.5 solicita `pdo,pdo_mysql,mbstring,dom,gd,fileinfo`.
- Permisos: `+2` — `contracts.view`, `contracts.manage`.
- Schema: `+3` tablas exactas — `contract_templates`, `contract_template_versions`, `contracts`.
- Migration: `007_create_contract_engine.sql`.
- Clases F7: `0` en esta microfase.
- Route files F7: `0` en esta microfase.
- Rutas públicas: `0`.
- Tenant/lease: `0`.
- `Installer.php`: sin cambios; `RuntimeRequirements` existente fue suficiente.

## Cero Ruido / Convergencia

Se usó un workflow temporal exclusivamente para producir `composer.lock` mediante Composer real porque el entorno de edición no ejecuta Composer. El workflow fue eliminado antes del GREEN final.

El diff productivo permanente desde el RED contiene solo:

- `.github/workflows/ci.yml`
- `app/Auth/Permissions.php`
- `app/Foundation/RuntimeRequirements.php`
- `composer.json`
- `composer.lock`
- `database/migrations/007_create_contract_engine.sql`

No quedaron scripts/bootstrap temporales ni capas auxiliares.

## PR / pausa exacta

- Draft PR abierto: `#10`.
- PR state: OPEN / DRAFT / NOT MERGED.
- PR base: `main`.
- PR head branch: `impl/f7-contracts-20261012`.
- Checkpoint previo HEAD: `89b8de7c0e426c071a10ff575c4e8ea4d9546488`.
- CI disparada por ese checkpoint/PR: `38344848733`.
- Al momento de ordenar la pausa, esa CI estaba **IN PROGRESS** en PHP 8.3 / 8.4 / 8.5; no se declara resultado final sin nueva verificación.
- Este commit de checkpoint es el último cambio autorizado antes de detenerse.

## Gate congelado al pausar

```text
F7.1: GREEN / VERIFIED
DRAFT PR #10: OPEN
PR-CHECKPOINT CI 38344848733: IN PROGRESS AT PAUSE
F7.2: NOT STARTED
MERGE_ALLOWED: NO
PROJECT STATE: PAUSED BY USER
NEXT ON RESUME: verify latest branch HEAD + PR #10 + CI, then only if clean enter F7.2 TEMPLATE/VERSION RED
```

No iniciar F7.2, no marcar PR ready, no mergear y no modificar código hasta una nueva autorización explícita del usuario.
