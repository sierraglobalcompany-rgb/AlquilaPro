# AlquilaPro — CURRENT CHECKPOINT

**Corte:** 2026-10-23. Fuente operativa: GitHub y CI del SHA exacto → este checkpoint → SPEC/PLAN aprobados → historial en Git. Reemplaza el estado vivo V5; el historial de capturas/QA V5 permanece en commits anteriores y PR #52.

## Autoridades y gates

| Área | Estado verificable |
| --- | --- |
| Repositorio / main | `sierraglobalcompany-rgb/AlquilaPro` · `main` `94ba5f486b158c2b1fed753f26c63f7b3570c97c`, CAND-01 integrada mediante PR #45. |
| CAND-02 / PR #52 | [Draft](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/52), rama `impl/cand-02-work-human-first-20261022`; **SHA de producto** `b4cbe45145e6f21c07a093a27e1df8ed9ed7d922`. |
| CAND-02 CI | [#1170](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39883431882) **SUCCESS** PHP 8.3/8.4/8.5 sobre SHA de producto. |
| CAND-02 QA | V8 **PASS_WITH_SCOPE**; ZIP evidencias V8 SHA-256 `8d44cd8b858ba4cbd2743c51222d81367f4b266c6e3606134936e949fffcc90e`; fidelidad visual global **NO CERTIFICADA**. |
| PR #53 / Documentos reales | [Draft](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/53), rama `impl/candidates-document-truth-20261023`, desde main, HEAD `88b0521b325ffb3ed9e78852ca1c05f1bc5122d2`. |
| PR #53 CI | [#1171](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39891808862) **SUCCESS** PHP 8.3/8.4/8.5; Browser QA **NOT_RUN**, paridad visual **NOT_CERTIFIED**. |
| Merge / deploy | **NO AUTORIZADOS** para #52 y #53; Hostinger sigue bloqueado. |
| CAND-03+ | Congelado hasta gates e integración de datos y diseño. |

## Diagnóstico de producto y datos

**Mockups:** ocho originales en Library `/AlquilaPro/Design/Candidatos/2026-10-17/`; `02_INFORMACION_LABORAL.png` SHA256 `fc889a00c6ab29324b1efb71e48ae68f0708542b8c6c7f20b81d52cd1aa9e2c1`. Son **dirección visual**, no persistencia/funcionalidad existente. El owner pidió funcionalidades útiles respaldadas por datos antes de seguir retocando arte; esta orden supera la inferencia de que bastaba una pantalla visual reducida.

**Arquitectura:** PHP SSR, Slim y PDO/MariaDB; el repo tiene tablas, servicios y pruebas para Candidatos, Documentos, Estudios, Contratos, WhatsApp asistido (contexto candidate), Notificaciones y Auditoría, además de otros módulos. Inventario de 23 migraciones, 51 archivos `app/` y 166 archivos `tests/` no certifica puesta en producción.

**CAND-02:** real solo `employment_status`, `employer_name`, `occupation`, `monthly_income`, `employment_notes`. V8 una card principal con ingreso y notas condicionales. El mockup contiene historial/verificación/otros ingresos/riesgos sin dueño real; no crearlos como KPI ficticio. V8 no aprobada en paridad visual original.

**PR #53 — integración data-first realizada:** `app/Candidates/routes.php` llama `CandidateDocumentService::listRequirementsForUnit(unit_id, true)` solo para sección Documentos; `candidate_detail.php` cruza con `listDocumentsForCandidate()` mostrando recibido/pendiente, obligatorio/opcional y conteo real sin declarar revisión/aprobación; preserva apertura privada y documentos históricos de requisito inactivo. Nuevas pruebas en `CandidatesAdminRoutesTest.php`; ningún schema nuevo, nuevo servicio o cambio de permisos.

**Redundancia real a resolver:** transición de estado en `CandidateService::transitionStatus()` y `resources/views/candidate_detail.php` duplicadas; converger a un solo owner dominio con RED/GREEN, no agregar otra lista. Mantener entidades distintas: estudio aseguradora ≠ póliza en arrendamiento; `candidate_documents` ≠ `documents` del inmueble/arriendo; precio publicado ≠ snapshot contractual. `pets_declared DEFAULT 0` exige auditar “no” frente a “no respondido”.

**Mensaje:** `WhatsAppService` ya soporta `candidate_follow_up` para contexto candidate; integra solo si `whatsapp.use`, conserva confirmación humana, no afirma entrega automática. Otros mockups requieren inventario dato→origen→permiso→vista antes de implementar controles.

## Próximos gates

1. **Único NEXT:** QA real Browser de Documentos PR #53 sobre HEAD `88b0521b325ffb3ed9e78852ca1c05f1bc5122d2`, usuario sintético con `candidates.view`, requisitos recibidos, pendientes, opcionales e inactivos, más vacío; capturas desktop/tablet/móvil y referencia original `05_DOCUMENTOS.png`; ChatGPT audita después.
2. PR #52 continúa Draft con QA V8 PASS_WITH_SCOPE, paridad no certificada. No mezclar SHA de ambas ramas sin revisar conflictos y correr CI tras integración autorizada.
3. Tras el gate anterior, eliminar duplicación de transiciones en owner único, mejorar CAND-03 a CAND-08 con datos reales; una única QA integral final, no rondas repetitivas de CSS.
4. Merge y Hostinger requieren autorizaciones distintas y explícitas; nunca automáticamente por CI verde.

## Cuatro leyes

**KISS:** simple, eficiente, optimizado; reutilizar owners/campos existentes, no SPA ni arquitectura paralela. **Antirruido:** eliminar/unir/simplificar solo con evidencia, checkpoint único y sin estados duplicados. **Antiparches:** causa raíz, RED causal, GREEN mínimo, limpiar superseded y regresión. **UX/UI Pro:** comparación mockup original / captura real con rol, viewport y datos comparables; no atribuir aprobaciones, scores ni acciones falsas.

`NEXT=PR53_DOCUMENTOS_BROWSER_QA_SHA_88b0521b325ffb3ed9e78852ca1c05f1bc5122d2`
