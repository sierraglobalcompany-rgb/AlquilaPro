# CURRENT CHECKPOINT — CAND-03 / G5 UX integrado

**Corte:** 2026-10-27 America/Bogota. Implementación local autorizada; `CI_NUEVO=PENDING`, `LEY5_UX=PENDING`, `OWNER_UAT=PENDING`. No declarar PASS visual ni aceptación humana antes de revisar evidencias del nuevo SHA.

## Estado GitHub y base exacta

- Repo `sierraglobalcompany-rgb/AlquilaPro`; PR #55 OPEN/DRAFT, no merged. Rama `impl/cand-03-household-human-first-20261023`.
- Base autorizada: `46fe0f1553aa3eb1c852145a0a5be7759c7350da`, verificada en checkout detached y remoto antes de editar. El nuevo commit debe tener este padre exacto.
- CI #1212, run `40603582163`, intento 2, SUCCESS para el HEAD de PR `46fe0f15…`: PHPUnit PHP 8.3 (739 pruebas, 8,622 aserciones, 1 omitida), PHP 8.4 (739, 8,620, 1) y PHP 8.5 (739, 8,623, 1); Browser QA 12/12 y 62 PNG. La barrera `browser-harness-fatal` pasó.
- Artifact oficial #12780720443, digest `sha256:8b7d7d583f420cad0168304d17c0311435a63d51c4c5527f35cdec3c04272a3b`; manifiesto SHA-256 `08d52ce81656a23d24d7a51c846df6e8f49f8d7ce8b862d58fe6e1b1aadb9dff`, con 67/67 hashes verificados. Este CI es solo la base, no certifica los cambios de este lote.

## Causa y resolución UX

- El defecto causal estaba en el SSR de `resources/views/candidate_detail.php`: una sola zona plegable agrupaba las ediciones y dejaba el formulario `new` al final, alejando el alta directa de su encabezado y mezclando fichas.
- El RED reproducible, antes del cambio de vista, falló porque no existía un disclosure `data-household-action="create"` independiente por región (`Each region has one independent create disclosure. Expected 1, got 0`).
- La vista ahora presenta el formulario existente de alta junto al encabezado de cada región y un `<details name>` exclusivo dentro de cada ficha para editar y retirar ese registro. Se conservan SSR, rutas, CSRF, validación 422, permisos, fotos privadas y foco accesible. El CSS limita el cambio a los selectores CAND-03.
- El harness conserva los 12 escenarios existentes; prueba apertura táctil en móvil, alternancia entre dos ocupantes, edición individual, persistencia, 422 con foco y alerta en los cuatro viewports, roles de solo lectura, estados vacío/largo, scroll final y foco visible. Las nuevas capturas todavía dependen del CI del SHA de publicación.
- `design-system/alquilapro/MASTER.md` ahora describe las seis leyes, incluyendo L6; no se modificó el resto del Master.

## Verificación local

- RED previo observado y focal GREEN actual: 3 pruebas / 56 aserciones.
- `HumanFirstCandidateDetailViewTest.php`: 35 pruebas / 361 aserciones, PASS.
- `AssetContractTest.php`: 18 pruebas / 96 aserciones, PASS.
- PHP lint de vista y test, `node --check tests/browser/run.mjs` y `git diff --check`: PASS.
- La suite PHPUnit completa local no pudo avanzar: primer error en `AuditMutationBehaviorTest::testSuccessfulUserCreationWritesExactlyOneSafeAuditRow`, `RuntimeException: No fue posible conectar con la base de datos` desde `DatabaseConnector.php:27`. Se requiere confirmar la suite íntegra con la DB sintética del CI; no se atribuye este bloqueo local al cambio.
- Browser QA local: NOT_RUN; el runner exige evento real de PR #55 en GitHub Actions. No se falsificaron variables de CI.

## Worktrees, allowlist y próximo gate

- Antes de editar se registró snapshot SHA de los 9 worktrees. La rama autorizada está ocupada en otro worktree con 4 entradas WIP; el checkout actual permanece detached. `tests/browser/node_modules/` conserva sus 182 archivos untracked y sus hashes originales.
- La comparación final detectó un cambio de estado externo en dos worktrees ajenos: `app/Candidates/CandidateDocumentService.php` pasó de ` M` a `M ` en `h01-fixture-red-v8-4751`, y `AGENTS.md` pasó de ` M` a `M ` en el worktree temporal de Ley 4. Sus HEAD y SHA-256 de archivos siguen idénticos al snapshot inicial. Codex no modificó ni revirtió esos índices. Por la condición de snapshots WIP idénticos, `WIP_INTACTO=BLOCKED` y no se autoriza commit/push mientras el gate no se restablezca o el propietario decida cómo rebaselinarlo.
- Única allowlist local: `resources/views/candidate_detail.php`, `assets/app.css`, `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`, `tests/browser/run.mjs`, `docs/CURRENT_CHECKPOINT.md`, `design-system/alquilapro/MASTER.md`. El diff contiene solo esas seis rutas; nada está staged en el checkout autorizado y `node_modules/` permanece excluido.
- No se creó commit, no se hizo push, no se inició CI nuevo y no hay capturas del nuevo SHA. La autorización de publicación no se consumió, pero su condición de integridad de WIP no pasa.
- Cuando se resuelva el bloqueo de snapshots, el gate requerido sigue siendo PHPUnit PHP 8.3/8.4/8.5 y Browser QA 12/12, incluida `browser-harness-fatal`; recolectar capturas del nuevo SHA y manifiesto. Si ese CI falla, detenerse sin segunda publicación.
- No merge, deploy, Hostinger ni CAND-04. Ley 5 independiente y UAT del propietario quedan pendientes.
