# F7 CURRENT CHECKPOINT

**Fecha:** 2026-10-13 (America/Bogota)  
**Fase:** F7 — Motor de contratos  
**Microfase:** F7.4 — Immutable Snapshot + PDF + SHA-256  
**Estado:** **GREEN / VERIFIED — NO MERGE**  
**Branch:** `impl/f7-contracts-20261012`  
**Draft PR:** `#10` — `F7: Contract engine`

## Base

- F7.1 — Runtime + Schema + Permissions: GREEN / VERIFIED.
- F7.2 — Templates + Immutable Versions: GREEN / VERIFIED.
- F7.3 — Draft Contract + Safe Preview: GREEN / VERIFIED.
- PR #10: OPEN / DRAFT / NOT MERGED.
- Merge: NO autorizado.
- F7.5: NOT STARTED.

## F7.4 — Bloque 1: renderer PDF concreto

### RED verificado

- RED HEAD: `314c970e7c301d872ca15e9bbdc590f317605182`.
- CI RED: `38348897211`.
- PHP 8.3: `258 tests / 1957 assertions / 1 failure`.
- El único fallo fue `F7.4 missing concrete ContractPdfRenderer`.
- F1–F7.3 permanecieron verdes alrededor del RED.

### GREEN verificado

- GREEN HEAD renderer: `64ca27b4bf25991aa0dfe8b4ca9b03188dd53e1d`.
- CI: `38349017950`.
- PHP 8.3 / 8.4 / 8.5: PASS.
- PHP 8.3: `258 tests / 1968 assertions / 0 failures / 0 errors`.

Implementado:

- `app/Contracts/ContractPdfRenderer.php` como segunda y última clase F7;
- Dompdf concreto, sin interface/factory/adapter;
- `isRemoteEnabled=false`;
- UTF-8 + DejaVu Sans;
- papel `letter`, orientación `portrait`;
- CSS/header/page numbering internos y fijos;
- salida validada por firma `%PDF-`;
- cero shell/exec/proc_open/binarios externos;
- el renderer no toca DB ni storage.

## F7.4 — Bloque 2: snapshot inmutable + generación + storage privado

### RED verificado

- RED HEAD: `a80c04f70fdca44c6c53cab7ec317f27c066ef2a`.
- CI RED: `38349228710`.
- PHP 8.3: `262 tests / 1972 assertions / 4 failures`.
- Los 4 fallos pertenecieron exclusivamente a la ausencia de `ContractService::generate()`.
- Renderer F7.4 y F1–F7.3 permanecieron verdes alrededor del RED.

### GREEN verificado

- GREEN HEAD dominio/storage: `477123dd6fc4c2ea31de78bf4f0e9b1520da5446`.
- CI: `38349587492`.
- PHP 8.3 / 8.4 / 8.5: PASS.
- PHP 8.3: `262 tests / 2041 assertions / 0 failures / 0 errors`.

Implementado dentro del `ContractService` existente:

- `generate()`;
- `privatePdf()`;
- resolución compartida segura preview/snapshot;
- límite PDF `10 MiB`;
- nombre físico aleatorio `32 hex + .pdf`;
- temporal aleatorio → escritura `LOCK_EX` → verificación size/hash → rename final;
- permisos físicos `0700` directorio / `0600` archivo donde el SO lo soporta;
- SHA-256 calculado sobre los bytes exactos renderizados;
- snapshot HTML interno escapado sin marca `BORRADOR`;
- `resolved_variables_json` preserva los valores canónicos resueltos;
- anexos se congelan dentro del snapshot/PDF;
- tareas se excluyen deliberadamente del snapshot/PDF porque el contrato F7 permite que `tasks_json` siga mutable después de `generated`;
- `generated_at`, tamaño, hash y nombre almacenado persistidos junto al cambio atómico a `generated`;
- segundo `generate()` rechazado;
- `updateDraft()` rechazado después de `generated`;
- fallo de render/write/DB deja el contrato en `draft` y elimina temporales/final parcial.

Revalidaciones al generar:

- candidato debe seguir `pending_screening`;
- screening debe seguir `approved`;
- `candidate.unit_id` debe seguir coincidiendo con la unidad fijada por el contrato;
- unidad debe seguir `available|in_process`;
- propiedad debe seguir activa;
- tipo de unidad/documento debe seguir compatible;
- una plantilla fijada por un draft puede estar desactivada posteriormente sin invalidar ese draft ya existente.

F7.4 no cambia `candidates.status`, `units.status`, precio comercial, tenant, lease ni auth.

Integridad de `privatePdf()`:

- solo `generated|finalized`;
- nombre físico allowlisted por patrón;
- realpath debe permanecer dentro del directorio contractual privado;
- archivo legible y firma `%PDF-`;
- tamaño físico exacto = DB y <=10 MiB;
- SHA-256 físico exacto = DB con `hash_equals`;
- tampering, nombre inseguro, ausencia u oversize → fail closed.

## F7.4 — Bloque 3: generación por ruta + descarga privada

### RED verificado

- RED HEAD: `09bb436949c0eb3f3ff04da0a1b24d4cb0f5b0f7`.
- CI RED: `38349848567`.
- PHP 8.3: `264 tests / 2049 assertions / 2 failures`.
- Fallos exactos:
  - `POST /admin/contracts/{id}` con `action=generate` devolvía 422 porque aún no estaba allowlisted;
  - `GET /admin/contracts/{id}/pdf` no existía.
- Dominio/storage F7.4 y F1–F7.3 permanecieron verdes alrededor del RED.

### Product GREEN verificado

- Product GREEN HEAD: `0a70b447f4c1787e74625ec25226aba75cb21ebf`.
- CI Product GREEN: `38349980325`.
- PHP 8.3: PASS — `264 tests / 2069 assertions / 0 failures / 0 errors`.
- PHP 8.4: PASS.
- PHP 8.5: PASS.
- `composer validate --strict`: PASS.
- `composer install --no-interaction --prefer-dist --no-progress`: PASS.

Rutas/capacidades F7.4 exactas dentro del único `app/Contracts/routes.php`:

- `POST /admin/contracts/{id}` añade única acción F7.4 allowlisted `generate` además de `save`;
- `GET /admin/contracts/{id}/pdf` descarga privada.

Guardrails de ruta verificados:

- auth existente;
- forced-password gate existente;
- `contracts.manage` requerido para generar;
- `contracts.view` requerido para descargar;
- owner bypass existente;
- CSRF global existente para POST;
- basePath/subfolder preservado;
- draft/missing/tampered PDF → 404/fail closed;
- `Content-Type: application/pdf`;
- `Content-Disposition` usa nombre humano server-side `contrato-{id}.pdf`, nunca el nombre físico;
- `Cache-Control: private, no-store`;
- `X-Content-Type-Options: nosniff`;
- bytes/tamaño/SHA vuelven a verificarse inmediatamente antes de responder;
- 0 rutas públicas contractuales;
- 0 middleware/controladores/vistas/CSS nuevos en F7.4.

## Auditoría contra PLAN F7.4

El plan F7.4 fijó como archivos productivos únicamente:

- crear `app/Contracts/ContractPdfRenderer.php`;
- modificar `app/Contracts/ContractService.php`;
- modificar `app/Contracts/routes.php`.

Eso se respetó exactamente. `resources/views/contract_detail.php` no pertenece a F7.4 en el plan; su siguiente extensión está programada en F7.5. La prueba de rutas PDF se aisló en `ContractPdfRoutesTest.php` en lugar de inflar `ContractAdminRoutesTest.php`; es una decisión solo de pruebas y no altera arquitectura productiva.

Diff productivo F7.4 frente al checkpoint F7.3:

- `app/Contracts/ContractPdfRenderer.php`: añadido;
- `app/Contracts/ContractService.php`: modificado;
- `app/Contracts/routes.php`: modificado;
- ningún otro archivo productivo.

## Arquitectura acumulada F7

- Tablas F7: `3` exactas.
- Clases F7: `2/2` — `ContractService`, `ContractPdfRenderer`.
- Route files F7: `1/1` — `app/Contracts/routes.php`.
- Vistas F7: `4`, sin delta F7.4.
- Permisos F7: `2`.
- Dependencia runtime directa: `dompdf/dompdf:^3.1.6`, sin delta F7.4.
- CSS F7 nuevo: `0`.
- Middleware/controladores F7 nuevos: `0`.
- Repositories/DTO/interfaces/adapters/factories/template engines nuevos: `0`.
- Rutas públicas contractuales: `0`.
- Tenant/lease/auth paralelo: `0`.
- DB schema delta F7.4: `0`.

## Two Laws Gate — F7.4

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
ADDED: ContractPdfRenderer concreto; generate/privatePdf dentro de ContractService; action=generate y una descarga privada dentro del route file existente; pruebas TDD focales.
REMOVED: 0; no apareció código productivo muerto que justificara eliminación.
MERGED_OR_CONVERGED: resolución preview/snapshot convergida en un único resolvedDocument(); auth, SessionGuard, Permissions, CSRF, basePath, CandidateService, ScreeningService, PropertyService y Dompdf ya aprobado reutilizados.
DEPENDENCIES_DELTA: 0 en F7.4.
DB_SCHEMA_DELTA: 0 en F7.4.
PUBLIC_ROUTES_DELTA: 0.
PROOF: renderer RED 38348897211 → GREEN 38349017950; generación RED 38349228710 → GREEN 38349587492; rutas RED 38349848567 → Product GREEN 38349980325 PASS PHP 8.3/8.4/8.5; PHP 8.3 = 264 tests / 2069 assertions.
```

## Gate actual

```text
F7.1: GREEN / VERIFIED
F7.2: GREEN / VERIFIED
F7.3: GREEN / VERIFIED
F7.4: GREEN / VERIFIED
DRAFT PR #10: OPEN / DRAFT / NOT MERGED
F7.5: NOT STARTED
MERGE_ALLOWED: NO
NEXT AUTHORIZED GATE: F7.5 — Finalization + Candidate Context
```

No iniciar F7.5, no marcar PR ready y no mergear sin nueva autorización del usuario.