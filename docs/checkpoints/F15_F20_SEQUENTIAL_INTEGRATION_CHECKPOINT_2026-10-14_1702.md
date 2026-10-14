# AlquilaPro — Checkpoint de integración secuencial F15→F20

Fecha: 2026-10-14 17:02 America/Bogota
Estado: CHECKPOINT / STOP REQUESTED

## Regla de autorización vigente

El usuario confirmó explícitamente que, dentro de este proyecto y cuando el gate ya está preparado y es inequívoco, `continúa` autoriza ejecutar el siguiente paso, incluidos merges secuenciales.

## Estado canónico de integración

### F15 — Agenda / visitas

- PR #18: MERGED.
- Merge SHA: `cfab94ce1a5b3a5bdd0f8a73cd49da07319a7915`.
- Reconciliación previa: README canónico + dos reparaciones test-only F8/F9 para reconocer la ruta tenant F15.
- Producto F15 no fue modificado durante la convergencia.
- CI final antes del merge: #910, PHP 8.3 / 8.4 / 8.5 PASS.

### F16 — Recordatorios / cron / email

- PR #19: MERGED.
- Merge SHA: `01d8cda30f6a883954b42ff6538b534c0c91e768`.
- Retarget a `main` después de F15.
- README convergido al blob canónico de `main`.
- Head de integración: `b6f23ad23dc00b4e93142d433765e924ff03bde9`.
- CI #911: PHP 8.3 / 8.4 / 8.5 PASS.

### F17 — WhatsApp asistido

- PR #20: MERGED.
- Merge SHA: `cfb57fc349a6e84da63282604c5e1a5f170e5242`.
- Retarget a `main` después de F16.
- README convergido al blob canónico de `main`.
- Head de integración: `614747f7d9af47c19d8bbf72beeb18ff5032076e`.
- CI #912: PHP 8.3 / 8.4 / 8.5 PASS.

### F18 — Documentos privados / inventarios

- PR #21: MERGED.
- Merge SHA: `9a9d3f6ab36b929e911b265a9e50a5a554410527`.
- Retarget a `main` después de F17.
- README final igualado exactamente al blob canónico de `main`.
- Head final de integración: `cb55ee4a8f3632684d80ae5a339c3723803fb036`.
- Merge-ref certificado antes del merge: `3ca25034ab06bf187e18738a041e146eda7e5de3`.
- CI #913 (`38642079431`): PHP 8.3 / 8.4 / 8.5 PASS.
- Durante la convergencia se detectó y corrigió inmediatamente una duplicación documental accidental en README; el head final quedó limpio y canónico antes de aceptar la CI.

## F19 — Convivencia / contenido legal — GATE ACTUAL

- PR #22: OPEN / NOT MERGED.
- Base actual: `main` en `9a9d3f6ab36b929e911b265a9e50a5a554410527`.
- Reparación test-only previa: `3d92694370ab7909d275a1c64327de9218af3e92`.
- La reparación elimina el test F9 sobreacoplado a la lista literal `use (...)` y verifica semánticamente ownership/404 antes de documentos, comunidad, membresías y finanzas.
- Producto F19 no cambió por esa reparación.
- README convergido exactamente con el blob canónico actual de `main` mediante commit:
  - `1fdceca79df2a9efe66b14f870b73444b1acb946`
- PR #22 ya recalculó como `mergeable: true`.
- Merge-ref actual: `0276b942859a2578f3d56f27b3f1a3072013a544`.
- CI actual: #914 (`38644228908`).
- Estado al crear este checkpoint:
  - PHP 8.3: PASS.
  - PHP 8.4: IN PROGRESS / PHPUnit.
  - PHP 8.5: IN PROGRESS / PHPUnit.
- No hay evidencia de regresión hasta este punto.

### Higiene F19

Se creó accidentalmente un archivo scratch `docs/checkpoints/.keep` durante una operación de integración. Se corrigió antes de continuar:

1. la rama se devolvió exactamente a `3d926943...` mediante force-with-lease;
2. el `.keep` quedó fuera de la historia activa;
3. después se creó únicamente el commit documental canónico `1fdceca...`.

No quedó ruido funcional ni documental derivado de ese accidente.

## F20 — Membresías / servicios digitales

- PR #23: OPEN / NOT MERGED.
- No se ha retargeteado todavía; se mantiene apilada sobre F19.
- Base registrada actualmente en PR: F19 `3d92694370ab7909d275a1c64327de9218af3e92`.
- Product Head real: `5f46c63f82672e6d34566284051a5f62e1db56db`.
- Cierre docs original: `5d2488286ce8370402544eaf4276a34a8d83a40d`.
- Convergencia test-only: `be3ee4b761bdfb31733662f3d1c1f03b40b3128a`.
- CI #907 ya fue PASS en PHP 8.3 / 8.4 / 8.5 sobre la pila F19/F20 previa.
- Debe recertificarse contra `main` solo después de que F19 sea fusionado.
- No iniciar F21 antes de integrar y auditar F20 en `main`.

## Próximo paso exacto al reanudar

1. Consultar CI #914 (`38644228908`).
2. Si PHP 8.3 / 8.4 / 8.5 están PASS y PR #22 sigue mergeable sobre el mismo head `1fdceca...`, fusionar F19 a `main`.
3. Confirmar SHA de merge F19.
4. Retargetear PR #23 F20 a `main`.
5. Resolver únicamente conflicto de apilado/documental si aparece; no tocar producto sin evidencia.
6. Generar CI nueva sobre el merge-ref exacto F20 vs `main`.
7. Solo con 8.3 / 8.4 / 8.5 PASS, fusionar F20.
8. Auditar `main` post-F20 antes de iniciar F21.

## Guardrails

- KISS: vigente.
- Cero Ruido: vigente.
- Antiparches: vigente.
- No crear segunda autoridad financiera, documental, notificaciones, auth o lifecycle.
- No iniciar F21 mientras F20 no esté integrado y auditado en `main`.
- No reusar CI de un merge-ref anterior para certificar un merge-ref nuevo.
- No modificar producto para resolver conflictos puramente documentales o tests estructurales obsoletos.

## Estado resumido

```text
F15  MERGED  ✅
F16  MERGED  ✅
F17  MERGED  ✅
F18  MERGED  ✅
F19  CI #914: 8.3 PASS / 8.4 RUNNING / 8.5 RUNNING ⏳
F20  OPEN / stacked / pendiente de turno
F21  NOT STARTED
```

STOP_HERE: YES
