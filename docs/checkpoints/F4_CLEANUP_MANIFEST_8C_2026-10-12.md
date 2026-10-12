# F4.8C — SAFE CHECKPOINT CLEANUP MANIFEST

**Fecha:** 2026-10-12  
**Estado:** PRE-DELETE MANIFEST  
**Regla:** `REFERENCIAS → AUTORIDAD → REEMPLAZO → TEST → ELIMINAR → CI`

---

# 1. AUTORIDAD / REEMPLAZO

Antes de borrar, la recuperación útil ya está absorbida por:

1. `README.md`;
2. `docs/specs/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_SPEC.md`;
3. `docs/plans/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_IMPLEMENTATION_PLAN.md`;
4. `docs/decisions/F4_CONVERGENCIA_CONTRATOS_PUBLICOS_2026-10-12.md`;
5. `docs/checkpoints/F4_CANONICAL_HISTORY_PRE_FINAL_2026-10-12.md`;
6. `docs/checkpoints/F4_CHECKPOINT_8B_DOC_CONVERGENCE_CLOSED_2026-10-12.md`;
7. tests/CI/código.

---

# 2. ARCHIVOS WIP APROBADOS PARA ELIMINACIÓN

Todos expresan estados intermedios ya cerrados y no son autoridad vigente:

- `F4_CHECKPOINT_AUD05_AUD03_CLOSED_BEFORE_AUD06_2026-10-11.md`;
- `F4_CHECKPOINT_TASK1_AUTH_GREEN_FIXTURES_PENDING_PERMISSIONS_2026-10-11.md`;
- `F4_CHECKPOINT_TASK1_SCHEMA_WRITTEN_PENDING_PERMISSIONS_2026-10-11.md`;
- `F4_PAUSED_TASK2_UNIT_RED_2026-10-11.md`;
- `F4_PAUSED_TASK3_MEDIA_RED_2026-10-11.md`;
- `F4_PAUSED_TASK4_ADMIN_AUDIT_GREEN_2026-10-12.md`;
- `F4_PAUSED_TASK4_PRIVATE_ADMIN_GREEN_AUDIT_PENDING_2026-10-11.md`;
- `F4_PAUSED_TASK5_LANDING_GREEN_CI_PENDING_2026-10-12.md`;
- `F4_PAUSED_TASK5_PUBLIC_CATALOG_RED_2026-10-12.md`;
- `F4_PAUSED_TASK8_CERO_RUIDO_VISUAL_QA_PENDING_2026-10-12.md`.

---

# 3. ARCHIVOS QUE SE CONSERVAN

- reauditoría V1.2;
- checkpoints CLOSED/MERGED F1/F2/F3;
- `F4_TASK4_PRIVATE_ADMIN_CLOSED_2026-10-12.md` por ahora;
- `F4_CHECKPOINT_TASK8_VISUAL_QA_GREEN_DOC_CONVERGENCE_PENDING_2026-10-12.md` porque README aún lo referencia;
- checkpoint 8B;
- historial canónico pre-final;
- este manifest.

Los dos checkpoints F4 conservados por prudencia se reevaluarán únicamente después de crear `F4_CLOSED` en F4.8D.

---

# 4. PRUEBA PREVIA

Último CI documental antes de cleanup:

`38212621127` — PHP 8.3/8.4/8.5 PASS.

El cleanup no modifica código, schema, tests ni runtime.

---

# 5. POSTCONDICIÓN

Después de la eliminación:

1. verificar que los diez archivos ya no existan;
2. verificar que documentos canónicos sigan presentes;
3. ejecutar CI del HEAD;
4. crear checkpoint 8C CLOSED;
5. recién entonces abrir F4.8D.
