# AlquilaPro — CURRENT CHECKPOINT

**Corte:** 2026-10-23, America/Bogota. Prioridad de verdad: GitHub HEAD → CI del SHA → checkpoint → SPEC/PLAN → historial. Este checkpoint acompaña la rama temporal de integración; no cambia la autoridad de main.

## Estado del proyecto
- **Repositorio** `sierraglobalcompany-rgb/AlquilaPro`. **main** = `94ba5f486b158c2b1fed753f26c63f7b3570c97c` antes de esta integración, sin edición autorizada.
- **CAND-01** PR #45 integrado a main; no reiniciar QA histórico por este trabajo.
- **PR #52** [Información laboral](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/52) Draft, HEAD `0eea64d8a4fdba964d14da26345ff6e68369d36d`; producto V8 `b4cbe45145e6f21c07a093a27e1df8ed9ed7d922`, CI #1170 verde, Browser QA `PASS_WITH_SCOPE` y fidelidad global `NOT_CERTIFIED`.
- **PR #53** [Documentos](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/53) Draft, HEAD `4c20e6acbe88aac6a3b3714d49f4d9a598d56e41`; CI #1173 verde, Browser QA 6 capturas `PASS_WITH_SCOPE`, paridad del mockup global `NOT_CERTIFIED`.
- **Nueva rama aislada** `integration/cand02-documents-20261023-g1`, basada en HEAD de PR #52, incorpora exactamente el bloque `?section=documents` del PR #53 y sus reglas CSS, ruta de requisitos y tests originales. Añade UNA prueba causal de convivencia entre Work y Documentos. **CI integrado: NO EJECUTADO al redactar el checkpoint**. No asumir GREEN por la aprobación de ramas separadas.
- **Gates:** ninguna autorización de merge, deploy/Hostinger o inicio CAND-03+. PRs #52 y #53 conservados intactos.

## Contratos y leyes
- **KISS:** PHP SSR/Slim/PDO. Sin nuevos servicios, migraciones, dependencias, nuevos estados ni APIs de negocio.
- **Antirruido:** ramas mantienen su evidencia histórica; esta integración temporal no representa otro producto definitivo. Eliminar duplicación de transiciones `CandidateService::transitionStatus()` y vista posteriormente con RED causal, no dentro de esta unión.
- **Antiparches:** resolver composición en los dueños únicos `resources/views/candidate_detail.php` y `assets/app.css`, conservar código original CAND-01/02/Documentos, pruebas y permisos. Sin CSS por intuición.
- **UX/UI Pro:** mockups originales `02_INFORMACION_LABORAL.png` y `05_DOCUMENTOS.png`; QA separadas pasaron dentro del alcance, **no** certifican fidelidad original ni HEAD integrado. Verificar navegador conjunto una sola vez.
- **No confundir entidades:** `candidate_documents` (solicitud) no es `documents` de arriendo; estudio de aseguradora no es póliza; preparar WhatsApp no es enviar. No afirmar revisión ni score sin proceso real.

## Próximo gate único
Verificar CI PHP 8.3/8.4/8.5 del nuevo HEAD de `integration/cand02-documents-20261023-g1` y la nueva prueba de convivencia; después QA navegador focal Work/Documentos con el mismo rol/fixture sin reiterar toda la suite visual. Sin merge, deploy, CAND03 ni limpieza de PRs originales.
