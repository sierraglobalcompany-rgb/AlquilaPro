## Interrupción Ley 4 V3 — instalación en repo y validación Codex

- **Decisión owner:** antes de implementar CAND-03 G1–G5, activar y auditar el protocolo adjunto `LEY 4 — AGENTE UNIVERSAL DE DISEÑO Y AUDITORÍA UX/UI | V3.0`.
- **Integración documental GitHub comprobada** sobre la misma rama PR #55: `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md` conserva íntegra y exactamente la V3 adjunta; `design-system/alquilapro/MASTER.md` describe CSS/tokens/layouts/owners reales; `AGENTS.md` §11 remite a ambos sin duplicar el protocolo y conserva las otras leyes. SHA documental **antes de este checkpoint** `d49524ede1530f6632e7bc5b06d8ca3b0d03bdda`.
- **Ley 4 instalado en repositorio: PASS documental** (diff exacto 3 rutas Markdown respecto a `91d5d8b89c84833a03da1d57f1e35bcc1c599a40`); **Codex local AGENTS.md cargado: NOT_VERIFIED** porque no hay acceso al Windows/Codex de usuario desde este chat. Verificar en sesión nueva con checkout/worktree aislado de la rama, en raíz y `resources/views`, respetando overrides globales. No confundir instrucciones persistentes con proceso permanente.
- **Fuentes externas revisadas como consulta, no copiadas ni instaladas:** Anti UI Slop SKILL y `reference/audit.md`, UI UX Pro Max `quick-reference.md`, documentación oficial Codex AGENTS. No crear segundo sistema CSS/JS, no usar `--persist` ni `--force` de Pro Max.
- **Visual CAND-03:** owner mantiene veto P1 por seis regiones no fieles; QA V2 sobre tres cards sigue evidencia histórica, **no aprobación**. No tocar mockups aprobados para lograr falsos PASS.
- **CI:** al registrar, el último HEAD L4 tiene ejecución GitHub CI #1187 en curso; verificar conclusión exacta. CI #1184 intento 1 falló prueba de concurrencia Financiera PHP 8.3; único rerun intento 2 finalmente SUCCESS, sin cambios de producto; no asumir suite verde del nuevo HEAD sin su propio resultado.
- **NEXT ÚNICO:** Codex Luna inicia sesión aislada con archivos actualizados, demuestra lectura real de L4+Master desde raíz y subcarpeta y ejecuta `AUDITAR_EXISTENTE` focal (CAND-03 fallido conocido + control ya implementado sin falsos positivos). Entrega evidencia **sin modificar código ni diseños**. ChatGPT aplica Ley 5 y después habilita prompt CAND-03 G1–G5. `PR55=DRAFT, MERGE=NO, DEPLOY=NO, CAND04=FROZEN`.

---

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
