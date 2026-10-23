# AlquilaPro — CURRENT CHECKPOINT (rama CAND-03)

**Corte:** 2026-10-23 America/Bogota. Verdad: GitHub HEAD → CI del SHA → contratos SPEC/PLAN → evidencia primaria. **No fusionar ni desplegar.**

## Repositorio y fase
- `sierraglobalcompany-rgb/AlquilaPro`; `main@414bcfeae0a3b4f8eea7262eefcb498e3f116307` tras [PR #54](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/54) fusionado por squash. PR #52 y #53 cerrados sin merge por supersesión. Hostinger `NOT_DEPLOYED`.
- **PR activo:** [#55](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/55), `impl/cand-03-household-human-first-20261023`, Draft, sin merge. **HEAD antes de este checkpoint:** `3f5f3a3ef5c66a51e5661167f4a87930ac7869e0`. Al editar este archivo cambiará el HEAD; inspeccionar GitHub, no reutilizar SHA histórico como actual.
- **Código CAND-03 anterior (INCOMPLETO visual/funcional):** `66a0d9a856366a315813ab6c84a2954f9b8f2263`. Browser QA V2 SHA256 `bbef35ad9c834a73bad6d80f5033aeea512fa178483a29c484e80ae9914c6c92` `PASS_WITH_SCOPE` solo de **tres** áreas históricas; visual original de seis `REJECTED`.
- **CI #1180** `SUCCESS` 8.3/8.4/8.5 sobre `a06d4841f85cd978d51a725428af9225ac184ab4`, antes de los nuevos cambios de **documentación**. **CI del HEAD de este checkpoint: NOT_VERIFIED.** No equivale a QA del producto ampliado.

## Alcance ampliado aprobado por owner
El owner indicó no aplazar fichas individuales de ocupantes/mascotas y luego ordenó continuar. Recuperar **CAND-03 completo ahora**, sin recortar el mockup por falta de backend. No autoriza merge/deploy. Identidad visual intacta: `03_OCUPANTES_MASCOTAS.png` 1448×1086 SHA256 `be9b8848afed75e31be6df6fd3e1d341b2b960b0d0ca71de68ad33efd3866ae0`.
- **SPEC maestra de UI Candidates §9.3 actualizada:** `docs/superpowers/specs/2026-10-17-candidates-human-first-redesign.md`.
- **PLAN Task 3 actualizado:** `docs/superpowers/plans/2026-10-17-candidates-human-first-redesign.md`.
- **Baseline §03 reconciliado:** `docs/design/CANDIDATES_VISUAL_BASELINE_2026-10-17.md`. El PNG no fue alterado.
- **Seis regiones obligatorias:** resumen, estado de información, fichas de ocupantes, mascotas con foto y manual contextual, reglas/observaciones, próximos pasos. Los riesgos/ratings, nombres/personas/fotos/estados ficticios y botones sin endpoint están prohibidos.
- **Datos nuevos mínimos:** personas adicionales sin duplicar al candidato, N mascotas con foto privada individual, marca nullable de respuesta expresa `pets_answered_at`; conteos legacy intactos. Nuevo wizard con respuesta no preseleccionada, edición durante draft, administrador con `candidates.manage`, lectura `candidates.view`, manual `documents.view + properties.view`. PDF y JSON privados de manual bajo `DocumentService`, versionados. Fotografías opcionales y protegidas, sin inventar foto ni validación de vacunas.
- Migración futura mínima a partir de versión libre posterior a 023; nombres y obligaciones en SPEC/PLAN. CSS scoped y sin reescribir el shell, Work, Documentos, Contratos ni other modules.

## Leyes de oro y Ley 5
- **L1 KISS:** completar flujo por menor arquitectura razonable. No presentar simpleza como producto incompleto.
- **L2 antirruido:** reutilizar `CandidateService`, `CandidateDocumentService`, `DocumentService`, rutas y storage/guardas; servicio dueño household mínimo solo si evita mezclar responsabilidades. No PR paralelo.
- **L3 antiparches:** migración segura y corrección de declaración Sí/No en captura; no falsedad usando `submitted_at`. Tests causales RED/real GREEN; retirar implementación supersedida con pruebas.
- **L4 diseño:** referencia PNG aprobada intacta; paridad por seis regiones y estados humanos, fotos de procedencia real.
- **L5 independencia:** distinguir implementación, CI, Browser QA y aceptación UX owner; sin autocertificación. QA históricos no cubren nueva funcionalidad.

## Gates y NEXT único
`G1 dominio/migración → G2 wizard/medios/CRUD → G3 documentos/manual → G4 UX 6 regiones → G5 PHP CI completo+Browser QA independiente+auditoría Ley5`.
- **NEXT:** entrega a Codex Luna de **UNA orden integrada** para desarrollar G1–G4 en la misma rama PR #55, con checkpoints de evidencia, y detenerse antes de merge/deploy/CAND-04. ChatGPT controla diseño, contratos y aceptación, no delega decisiones arquitectónicas no probadas. Si hay bloqueo técnico real, STOP y checkpoint causal.
- Nuevo commit solo documental no exige Playwright, pero sí verificar HEAD y CI antes de iniciar ejecución.
