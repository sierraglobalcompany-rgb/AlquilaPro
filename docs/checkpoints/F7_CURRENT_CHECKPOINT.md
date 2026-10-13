# F7 CURRENT CHECKPOINT

**Fecha:** 2026-10-13 (America/Bogota)  
**Fase:** F7 — Motor de contratos  
**Microfase:** F7.5 — Finalization + Candidate Context  
**Estado:** **GREEN / VERIFIED — NO MERGE**  
**Branch:** `impl/f7-contracts-20261012`  
**Draft PR:** `#10` — `F7: Contract engine`

## Base verificada

- `main` base de F7: `1b9ee85ebb224b310d4ab4f0c84a28628b86b747`.
- F7.1 — Runtime + Schema + Permissions: GREEN / VERIFIED.
- F7.2 — Templates + Immutable Versions: GREEN / VERIFIED.
- F7.3 — Draft Contract + Safe Preview: GREEN / VERIFIED.
- F7.4 — Immutable Snapshot + PDF + SHA-256: GREEN / VERIFIED.
- Checkpoint canónico F7.4: `0737a37b73af4072c86d77c41251c07beb688c04`.
- PR #10: OPEN / DRAFT / NOT MERGED.
- Merge: NO autorizado.

## F7.5 — Bloque 1: tareas post-generación + finalización

### RED verificado

- RED HEAD: `bce1ca486a88dfe1030ac157f85cd68fdd1b1af9`.
- CI RED: `38351822631`.
- PHP 8.3: `267 tests / 2072 assertions / 3 failures`.
- Los 3 fallos pertenecieron exclusivamente a la superficie F7.5 ausente en `ContractService`.
- F1–F7.4 permanecieron verdes alrededor del RED.

### GREEN verificado

- GREEN HEAD dominio: `f28e21dfc3c110c5c8da65749159bda9f5ad6e43`.
- CI: `38352216640`.
- PHP 8.3 / 8.4 / 8.5: PASS.
- PHP 8.3: `267 tests / 2108 assertions / 0 failures / 0 errors`.

Implementado dentro del `ContractService` existente:

- `updateTasks()`:
  - valida el esquema canónico de tareas;
  - bloquea la fila contractual con `FOR UPDATE`;
  - permite mutación solo en `draft|generated`;
  - actualiza únicamente `tasks_json` + `updated_at`;
  - no toca snapshot, PDF, hash, canon, administración ni anexos.
- `finalize()`:
  - exige usuario responsable válido;
  - bloquea primero la fila del candidato con `FOR UPDATE`;
  - rechaza candidato `withdrawn`;
  - bloquea la fila contractual;
  - exige `status=generated`;
  - impide un segundo contrato `finalized` para el mismo candidato;
  - cambia únicamente `status`, `finalized_by_user_id`, `finalized_at`, `updated_at`;
  - `finalized` queda terminal, incluso para tareas.

Guardrails verificados:

- finalizar un `draft` falla cerrado;
- doble finalización falla cerrado;
- retiro inmediatamente anterior a finalizar falla cerrado;
- segundo contrato generado del mismo candidato no puede convertirse en segundo `finalized`;
- candidato y unidad conservan sus estados;
- no se crea tenant, lease, usuario ni otra entidad F8;
- snapshot/PDF/hash permanecen byte-for-byte inmutables durante tareas/finalización.

## F7.5 — Bloque 2: acciones privadas + contexto contractual del candidato

### RED verificado

- RED HEAD: `7972a11842c56446ee5436dcfafba170adafea3d`.
- CI RED: `38352513607`.
- PHP 8.3: `270 tests / 2120 assertions / 3 failures`.
- Los 3 fallos correspondieron exactamente a:
  - `canCreateDraftForCandidate()` aún no comprobaba una plantilla activa compatible con al menos una versión;
  - el detalle de contrato generado no exponía aún tareas/finalización/PDF;
  - el detalle del candidato no exponía aún contexto contractual condicionado por `contracts.view`.
- Dominio F7.5 y F1–F7.4 permanecieron verdes alrededor del RED.

### Product GREEN verificado

- Product GREEN HEAD: `b0503ac0018a260ade7a21b21a3890b5bcdcd383`.
- CI Product GREEN: `38353305219`.
- PHP 8.3: PASS — `270 tests / 2145 assertions / 0 failures / 0 errors`.
- PHP 8.4: PASS.
- PHP 8.5: PASS.
- `composer validate --strict`: PASS.
- `composer install --no-interaction --prefer-dist --no-progress`: PASS.

Implementación productiva F7.5 exacta:

- `app/Contracts/ContractService.php`:
  - `canCreateDraftForCandidate()` ahora exige plantilla activa del tipo compatible **y al menos una versión real** antes de exponer creación;
  - la autoridad de elegibilidad continúa centralizada en `ContractService`.
- `app/Contracts/routes.php`:
  - el POST existente `/admin/contracts/{id}` añade solo las acciones allowlisted `update_tasks` y `finalize`;
  - no se agregaron route files ni rutas públicas.
- `resources/views/contract_detail.php`:
  - `draft`: edición + acción real de generar;
  - `generated`: descarga PDF + actualización exclusiva de tareas + finalizar;
  - `finalized`: descarga PDF y lectura, sin formularios de mutación.
- `bootstrap/app.php`:
  - pasa la instancia existente de `ContractService` al módulo Candidates; es wiring, no servicio paralelo.
- `app/Candidates/routes.php`:
  - solo si el usuario posee `contracts.view`/owner, delega a `ContractService::listForCandidate()` y `canCreateDraftForCandidate()`;
  - no contiene SQL contractual ni reproduce reglas de screening/unidad/plantilla.
- `resources/views/candidate_detail.php`:
  - sin `contracts.view`: no existe sección contractual privada;
  - con `contracts.view`: muestra contratos existentes y enlaces con `basePath`;
  - CTA `Crear contrato` solo cuando `ContractService` confirma elegibilidad real;
  - draft/generated/finalized muestran enlace al contrato existente;
  - finalized no ofrece un nuevo contrato inicial.

## Auditoría de diff F7.5

Comparación canónica:

`0737a37b73af4072c86d77c41251c07beb688c04..b0503ac0018a260ade7a21b21a3890b5bcdcd383`

Resultado:

- exactamente `4` commits F7.5;
- `8` archivos afectados;
- `6` archivos productivos existentes modificados;
- `2` tests nuevos;
- `0` clases nuevas;
- `0` tablas nuevas;
- `0` permisos nuevos;
- `0` dependencias nuevas;
- `0` route files nuevos;
- `0` rutas públicas contractuales nuevas;
- `0` CSS/JS nuevos;
- `0` tenant/lease/auth paralelo.

Commits F7.5 exactos:

1. `bce1ca486a88dfe1030ac157f85cd68fdd1b1af9` — RED dominio.
2. `f28e21dfc3c110c5c8da65749159bda9f5ad6e43` — GREEN dominio.
3. `7972a11842c56446ee5436dcfafba170adafea3d` — RED rutas/contexto.
4. `b0503ac0018a260ade7a21b21a3890b5bcdcd383` — Product GREEN.

## Arquitectura acumulada F7

- Tablas F7: `3` exactas.
- Clases F7: `2/2` — `ContractService`, `ContractPdfRenderer`.
- Route files F7: `1/1` — `app/Contracts/routes.php`.
- Vistas F7: `4`, reutilizadas; sin vista nueva F7.5.
- Permisos F7: `2`.
- Dependencia runtime directa: `dompdf/dompdf:^3.1.6`.
- CSS F7 nuevo: `0`.
- Middleware/controladores nuevos: `0`.
- Repositories/DTO/interfaces/adapters/factories/template engines nuevos: `0`.
- Rutas públicas contractuales: `0`.
- Tenant/lease/auth paralelo: `0`.
- DB schema delta F7.5: `0`.

## Two Laws Gate — F7.5

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
ADDED: updateTasks/finalize dentro de ContractService; dos acciones allowlisted en el POST contractual existente; contexto contractual en vistas/rutas existentes; dos tests focales F7.5.
REMOVED: 0; no apareció código productivo muerto que justificara eliminación.
MERGED_OR_CONVERGED: elegibilidad de CTA centralizada en canCreateDraftForCandidate(); Candidate route delega en ContractService; auth, Permissions, CSRF, basePath y route/view existentes reutilizados.
DEPENDENCIES_DELTA: 0 en F7.5.
DB_SCHEMA_DELTA: 0 en F7.5.
PUBLIC_ROUTES_DELTA: 0.
PROOF: dominio RED 38351822631 → GREEN 38352216640; contexto/UI RED 38352513607 → Product GREEN 38353305219 PASS PHP 8.3/8.4/8.5; PHP 8.3 = 270 tests / 2145 assertions.
```

## Gate actual

```text
F7.1: GREEN / VERIFIED
F7.2: GREEN / VERIFIED
F7.3: GREEN / VERIFIED
F7.4: GREEN / VERIFIED
F7.5: GREEN / VERIFIED
DRAFT PR #10: OPEN / DRAFT / NOT MERGED
F7.6: NOT STARTED
MERGE_ALLOWED: NO
NEXT AUTHORIZED GATE: F7.6 — Security + Visual + Cero Ruido + Closure
```

No iniciar F7.6, no marcar PR ready y no mergear sin nueva autorización del usuario.
