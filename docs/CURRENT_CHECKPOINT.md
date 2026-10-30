# CURRENT CHECKPOINT — R7 / BLOQUE A PUBLICADO, BLOQUE B LOCAL_ONLY

**Actualizado:** 2026-10-29 (America/Bogota)
**Repositorio:** sierraglobalcompany-rgb/AlquilaPro
**PR:** #55, OPEN/DRAFT, no merged
**Rama remota:** impl/cand-03-household-human-first-20261023
**HEAD local y remoto:** 4231e7b1015f24b5bd1baf51311fd78550778360
**Checkout de trabajo:** detached HEAD; sin cambios staged
**MERGE=NO; DEPLOY=NO; RELEASE=NO; PRODUCCIÓN=NO.**

## Bloque A — CAND-04/05/06 publicado

- Commit 4231e7b1015f24b5bd1baf51311fd78550778360, hijo directo de d036cda5e11aea31184250213ff414d346e51e89; un push normal. PR permanece OPEN/DRAFT.
- CI #1219, run 40895207989, terminado SUCCESS en el SHA exacto: PHPUnit PHP 8.3, 8.4 y 8.5; Browser QA autenticado en PHP 8.5.
- Artifact: cand03-g5-browser-qa-40895207989-1, id 12907801102, SHA-256 sha256:adf11483f07da813ea7064218a802f9a8479d99b05cc24e9dfe4bcd87cad7bc7.
- Browser QA local posterior a publicación: 14 estados, 36/36 comprobaciones, 28 PNG; CSS servido coincide con su SHA. 0 diagnósticos inesperados; el 403 de ACL esperado está correlacionado. 28/28 hashes de capturas verificados.
- Cinco capturas móviles adicionales son de la copia previa a publicación y están etiquetadas así; no se atribuyen al SHA publicado.
- Aceptación visual provisional del propietario; no equivale a dictamen independiente Ley 5 ni UAT.

## Bloque B — CAND-07/08/09 LOCAL_ONLY

Implementación local basada en 4231e7b1015f24b5bd1baf51311fd78550778360. Sin commit ni push. Alcance: transiciones y contexto de contratos; administración con transiciones permitidas y acción de regenerar token secundaria; lista de candidatos con CTA único, contacto opcional, estado vacío y escape de contenido.

Cambios locales de código/pruebas en 11 rutas: CandidateService, rutas de candidatos, HumanLabels, vistas de detalle/lista, pruebas de servicio/rutas/vista, prueba de lista nueva y el fixture de HumanLabelsContractTest. Se actualizó este checkpoint como documentación local. El directorio tests/browser/node_modules ya existía sin seguimiento; se conservó intacto y se excluyó de los entregables.

### Pruebas

- Suite PHPUnit completa local: PHP 8.5.12, MariaDB 11.4.13 efímera en tmpfs, base sintética aislada alquila_test_b. Resultado: 749 pruebas, 8.765 aserciones, 0 errores, 0 fallos, 1 omitida; PHPUnit reportó 03:39.351. La prueba omitida es ServerReadyArtifactAuditTest::testCandidateZipPassesStaticAuditAndExtractsCleanly.
- HumanLabelsContractTest focal tras corregir su fixture para pasar las transiciones que la ruta real entrega: 4 pruebas, 14 aserciones, PASS.
- git diff --check: PASS.
- Se descartaron intentos parciales del runner por lentitud del montaje o configuración DB incorrecta; no cuentan como resultado de producto. El único PASS es la ejecución completa final, con la base tmpfs aislada y 11 archivos de código/prueba cotejados por SHA-256.

### Browser QA local

- Aplicación real en loopback, fixtures sintéticos aislados; SHA de los cinco archivos PHP servidos coincide con la copia de trabajo.
- 16 estados contratos/administración en 1448x1086, 1100x850, 768x1024 y 375x812: HTTP 200 en 16/16, overflow horizontal 0/16, errores de consola/página/red/HTTP 0; 32 PNG.
- Lista de candidatos single/empty: ocho estados adicionales en los mismos cuatro tamaños, HTTP 200 y 0 errores; 16 PNG.
- Captura móvil focal adicional del botón de regeneración: viewport 375x812, un gesto táctil cambió scrollY 0→198; el botón quedó completo en viewport, document.elementFromPoint alcanzó el control y no hubo overlay fijo en su centro. 0 diagnósticos; el formulario no se envió en esta medición. Captura SHA-256 f2cd92dcb14f7fbcff17565b543ea853d64b303f81f5faba4abcb477e1aefc40.
- Total del paquete de evidencia Bloque B: 24 estados estándar, 48 PNG, más 1 captura focal móvil (49). Las 82 capturas combinadas de A, B y móviles prepublicación verifican 82/82 SHA-256. Las 32 capturas de detalle/contrato se tomaron tras la corrección de estilo de acción; las 16 de lista provienen de una captura anterior del mismo código de lista, que no cambió con esa corrección.
- Incidencia local: en un primer intento de diagnóstico la interceptación del click no quedó confirmada; pudo regenerarse el token de la ficha sintética local. La medición final solo hizo scroll y hit-test; no envió el formulario ni contactó servicios externos.
- No existe CI remoto para el Bloque B, que sigue sin publicar.

## Integridad de worktrees y WIP

- Nueve worktrees visibles. Los ocho no ejecutores conservan HEAD, rama y cantidad de entradas de estado del snapshot inicial.
- 259/259 archivos WIP protegidos coinciden por SHA-256 con el snapshot; no se editaron otros worktrees.
- En el checkout ejecutor: detached HEAD 4231e7b; cambios locales no staged; tests/browser/node_modules excluido.
- La rama remota sigue en 4231e7b. No hubo commit, push, merge ni despliegue para el Bloque B.

## Próximo gate

Entregar evidencia de Bloque B para revisión independiente Ley 5 y aceptación/UAT humano. No publicar el Bloque B sin nueva autorización. Mantener PR #55 OPEN/DRAFT. No iniciar CAND-10 ni producción.
