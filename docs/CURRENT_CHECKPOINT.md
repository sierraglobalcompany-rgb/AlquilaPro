# CURRENT CHECKPOINT — CAND-03 / G5

**Corte:** 2026-10-27 America/Bogota. `G5=EN_EJECUCION / CI_PENDIENTE`; dictamen independiente Ley 5 pendiente. No declarar PASS global ni paridad visual hasta revisar el artifact del HEAD publicado.

## Estado vivo verificado

- Repositorio: `sierraglobalcompany-rgb/AlquilaPro`.
- PR #55: OPEN, Draft, no merged. Rama `impl/cand-03-household-human-first-20261023`.
- Base local del lote y HEAD remoto antes de editar: `cd3b1d7aa72bd6a80a8ae044dcbe45e56a086e9a`; padre `09f114fa7cb1387958f354f8ff47581197663465`.
- CI #1209, run `40500170485`, terminó success para ese HEAD. PHPUnit pasó en PHP 8.3/8.4/8.5; Browser QA corrió solo en PHP 8.5. No certifica los cambios locales de este lote.
- `main` observado: `f682d09e140e0b86f5704630712df5116dad9d09`; GitHub API reportó para el PR una base distinta (`414bcfeae0a3b4f8eea7262eefcb498e3f116307`). Discrepancia previa conservada, no modificar.

## Lote local G5

La validación del guardado de ocupantes/mascotas vuelve a renderizar la ficha SSR completa con HTTP 422, abre únicamente el grupo y formulario afectado y presenta un aviso accesible. Conserva solo campos escalares acotados del formulario; nunca repone archivo ni datos fuera de allowlist. Los errores de consentimiento y fotografía enfocan y marcan el control correcto; el consentimiento confirmado se conserva solo durante la respuesta de error. Las acciones de gestión usan CTA visibles con foco y área táctil; no se añadieron endpoints, permisos ni datos.

El Browser QA autenticado conserva las comprobaciones de CSRF, ACL y CRUD y ahora añade los estados de consentimiento ausente y archivo de foto inválido con capturas. Playwright requiere el CI real; no se ejecutó localmente con variables de CI falsificadas.

## Evidencia local

- RED antes del ajuste: la ruta devolvía `text/plain` para errores de validación y los controles mantenían el resumen «Administrar fichas…»; no cumplían el contrato SSR/CTA aprobado.
- RED adicional: la prueba de foto/consentimiento falló porque el checkbox carecía de `aria-invalid` y foco (`expected true`, recibido vacío).
- GREEN focal final, PHP 8.5.8 + MariaDB 11.4 aislada en loopback: 5 pruebas, 196 assertions, PASS. Incluye validación transitoria, foto inválida, consentimiento, CRUD/foto privada y permisos.
- Suite final, PHP 8.5.8: 739 pruebas, 8.618 assertions, 2 skips, 266 warnings; PHPUnit terminó con resumen `OK`. Las advertencias corresponden al patrón preexistente de limpieza de objetos/temporales en `ServerReadyBuilderTest` (permisos `unlink`); no se cambió esa suite.
- `php -l` en los PHP afectados, `node --check tests/browser/run.mjs` y `git diff --check`: PASS. GD se cargó con override de proceso, sin cambiar `php.ini`.

## Alcance, protección y siguiente gate

Allowlist autorizada de siete rutas únicamente:
1. `app/Candidates/routes.php`
2. `resources/views/candidate_detail.php`
3. `assets/app.css`
4. `tests/Integration/Candidates/CandidatesAdminRoutesTest.php`
5. `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`
6. `tests/browser/run.mjs`
7. `docs/CURRENT_CHECKPOINT.md`

Los ocho worktrees externos estaban intactos en la última comparación con el snapshot previo al lote; repetir comparación/hash antes de publicar. La lectura remota más reciente volvió a confirmar HEAD `cd3b1d7…`, PR OPEN/Draft/no merged. Revalidar ambas condiciones justo antes de stage.

Una única publicación normal autorizada: exactamente un commit y un push con esos siete archivos a la rama del PR #55. Después, verificar CI del SHA exacto (PHPUnit 8.3/8.4/8.5, Browser QA PHP 8.5), descargar/inspeccionar las capturas y manifest reales y entregar informe externo con SHA-256. Si falla un control o aparece otro defecto, detenerse sin segundo push. No force push, rebase, merge, deploy, Hostinger ni CAND-04.
