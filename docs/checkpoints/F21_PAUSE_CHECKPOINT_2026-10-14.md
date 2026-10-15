# F21 — CHECKPOINT DE PAUSA 2026-10-14

**Motivo:** pausa solicitada por el usuario. No continuar implementación después de este punto hasta nueva orden explícita.

## Estado canónico

- Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
- Base F21: `main@748c9d3d70ed33bb216daab8322bf6a75573ff85`
- Rama activa: `impl/f21-bonus-liquidation-20261014`
- PR activa: **#26 — OPEN / NOT MERGED**
- Head actual antes de este checkpoint: `4bc007ce6524dce34044e78687f9995c181cad6f`
- Commit `4bc007ce...` es **docs-only**: actualiza el PLAN F21 y crea `docs/checkpoints/F21_LIVE_HANDOFF_2026-10-14.md`; 0 archivos de producto.

## Task 1

**CLOSED / GREEN**

- Schema 021 + canonical `LeaseService::find()`.
- CI #922: GREEN PHP 8.3 / 8.4 / 8.5.

## Task 2

**CLOSED / GREEN**

- Producto mínimo: `1dd9784bf03e2188ff003c07cf96656bcab20bdc`.
- Cierre test/convergencia antes de docs: `4e92974df2c590f5c48400f4dcf36ba88d1d4842`.
- CI #929: GREEN PHP 8.3 / 8.4 / 8.5.
- PHP 8.3: **552 tests / 5806 assertions / 0 failures**.
- Cobertura verificada:
  - feature gate;
  - amount/note bounds;
  - una fila pendiente por lease;
  - edición mientras pending;
  - decisiones terminales `approved|denied`;
  - sin escrituras Finance;
  - race decision-vs-decision;
  - race update-vs-decision;
  - sin mutación post-decisión.

## Estado de CI al pausar

- CI #930 corresponde al head docs-only `4bc007ce6524dce34044e78687f9995c181cad6f`.
- Al momento de crear este checkpoint, #930 seguía **IN PROGRESS** ejecutando PHPUnit en PHP 8.3 / 8.4 / 8.5.
- No se había observado fallo temprano.
- Al reanudar, **primera acción obligatoria**: verificar el resultado final de CI #930 antes de tocar producto.

## Task 3

**NOT STARTED**

No existe implementación de `TerminationService::preview()` todavía.

### Próximo paso exacto al reanudar

**Task 3a — Insurance owner RED**.

PRE-KISS ya confirmó un hueco real de autoridad de lectura:
- `InsuranceService::listPolicies(today)` es global y demasiado amplio;
- `InsuranceService::tenantPolicySummary(leaseId, today)` es lease-scoped pero deliberadamente oculta IDs y claims;
- `InsuranceService::listClaims(policyId)` ya existe y debe reutilizarse.

La reparación correcta es:
1. escribir un RED en el test owner existente de Insurance;
2. añadir el método de lectura lease-scoped más pequeño posible en `InsuranceService`, conservando IDs suficientes para componer `listClaims(policyId)`;
3. 0 nuevas tablas, 0 nuevas clases, 0 permisos, 0 rutas, 0 dependencias;
4. poner Insurance GREEN;
5. solo después escribir `TerminationPreviewTest` y luego `TerminationService::preview()`.

**Prohibido al reanudar:** duplicar SQL de Insurance dentro de `TerminationService`, crear una segunda autoridad de pólizas/claims o saltarse el RED owner.

## Guardrails vigentes

- KISS / arquitectura simple.
- Zero Noise: eliminar/converger/simplificar antes de añadir.
- Anti-patch: root cause + owner correcto + RED/GREEN.
- `LeaseService::end()` sigue siendo el único writer del lifecycle del lease.
- `FinanceService` sigue siendo la única autoridad de charges/payments.
- `DocumentService` sigue siendo autoridad de documentos/inventarios.
- F21 mantiene presupuesto: máximo 2 tablas y exactamente 1 clase de dominio nueva (`TerminationService`).
- No merge de PR #26 antes de Tasks 3–6 + convergencia y certificación final.

## Autoridad de recuperación

Orden recomendado al abrir un chat nuevo:
1. este checkpoint;
2. `docs/checkpoints/F21_LIVE_HANDOFF_2026-10-14.md`;
3. PR #26 actual (head/base/CI);
4. `docs/plans/F21_BONUS_LIQUIDATION_IMPLEMENTATION_PLAN.md`;
5. `docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md`.

## Marcador de pausa

`PAUSED_BY_USER / TASK 1 CLOSED / TASK 2 CLOSED / TASK 3 NOT STARTED / VERIFY CI #930 FIRST / PR #26 OPEN / NOT MERGED`
