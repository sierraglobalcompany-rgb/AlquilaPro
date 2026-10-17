# AlquilaPro — Chat-End Recovery Checkpoint

Fecha: 2026-10-16 America/Bogota
Tipo: checkpoint de recuperación de cambio de chat; no sustituye GitHub vivo/CI.

## Autoridad viva al corte

- Repo: `sierraglobalcompany-rgb/AlquilaPro`.
- `main`: `85da06764b3d79f8a17750ba6dfc7eb432e4300f` (`docs: harden Codex delivery and evidence laws`).
- Main protection: `false`; required checks: off.
- README: convergido; estado móvil en `docs/CURRENT_CHECKPOINT.md`.
- AGENTS: KISS/Cero Ruido/Anti-parche + Codex descargable + NO CLAIM WITHOUT EVIDENCE reforzados en `85da0676...`.
- Deployment: NO autorizado.
- Pre-F26 owner UAT: REJECTED.
- F26: FROZEN.

## Leyes

1. KISS: simple, eficiente, optimizado, eficaz; A→B; reutilizar owners y no crear arquitectura sin necesidad demostrada.
2. Cero Ruido: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`; no eliminar sin referencias/autoridad/reemplazo/test.
3. Anti-parche: `REPRODUCE → ROOT CAUSE → OWNER → DUPLICATION → RED → VERIFY RED → MINIMAL GREEN → REMOVE SUPERSEDED → REGRESSION`.
4. Evidencia: `CLAIM → EXACT SHA → EXACT TEST/CI/EVIDENCE → RULING`; no trust-by-report.
5. Human-First para UI: verdad → comprensión → decisión → acción → detalle; real Browser requerido para final UI GREEN cuando aplique.

## Método del owner

Antes de implementar: revisar → recuperar contexto → GitHub → owners/duplicación → PRE-KISS → SPEC/design → plan → auditoría 10 pasadas → converger → RED → causal RED → minimal GREEN → QA/CI → forensic audit → checkpoint → merge gate.

`continua` = ejecutar el siguiente gate inequívocamente preparado, no volver a F0.

`crea un checkpoint y para` = crear/verificar checkpoint y detenerse.

Si se delega a Codex:
- prompt al usuario: un único `.md` descargable por batch/task;
- no pegar prompt completo en chat;
- Codex final report: `.md` descargable;
- ChatGPT/Sol lo audita contra GitHub + CI.

## Active — HF-P1C-B

- Scope: autor de plantillas contractual sin JSON crudo.
- Branch: `impl/hf-p1c-b-template-human-form-20261016`.
- PR #38: OPEN / DRAFT / NOT MERGED.
- PR mergeability: **RECHECK_REQUIRED**; durante el cierre GitHub la recalculó `false → true` sin cambiar el HEAD funcional. No congelar este valor; reconsultarlo en merge gate.
- HEAD/RED: `adf1214627490231d9848ec8b0612c8ac2e8c461`.
- Checkpoint previo: `checkpoint/hf-p1c-b-red-stop-20261016` → mismo SHA.
- Branch diff: exactamente 1 test nuevo, 196 líneas, 0 producción.
- Test: `tests/Integration/Contracts/HumanFirstContractTemplateAuthorRoutesTest.php`.
- CI #1092 / run `38986855330`: FAILURE causal PHP 8.3/8.4/8.5; Composer strict PASS.
- PHP 8.3: `658 tests / 7790 assertions / 1 failure / 1 skipped`.
- Único fallo: `testTemplateAuthorUsesStructuredVariableFieldsInsteadOfJson`.
- Causa: `variables_json` sigue como UI primaria; faltan `variables[0][key]`, `variables[0][label]`, `variables[0][required]`.
- GREEN: NOT WRITTEN.

### Next exact action

1. Revalidar main/branch/PR/checkpoint/CI.
2. NO repetir RED.
3. Implementar GREEN mínimo sólo en:
   - `resources/views/contract_template_detail.php`;
   - `app/Contracts/routes.php`.
4. Vista: campos SSR `variables[n][key|label|required]`.
5. Route: normalizar transporte HTML y `required` a bool.
6. Mantener `variables_json` sólo como fallback POST histórico acotado.
7. `ContractService::createTemplateVersion()` sigue único validador/truth owner profundo.
8. No DB/migration/JS/CSS/SPA/framework/new service.
9. Full CI PHP 8.3/8.4/8.5 + fresh audit antes de merge.

### Drift HF-P1C-B

La rama nació en `ce3f98de...`. Main avanzó con dos commits documentales (`a99c3cda...`, `85da0676...`). Al corte: rama ahead 1 / behind 2. `mergeable` se observó false y luego true por recálculo de GitHub; tratarlo como efímero. No rebasear por estética ni repetir RED; preservar línea TDD y reconciliar en merge gate con evidencia.

## Cerrado recientemente

- PR #34 Human-First governance docs: MERGED.
- PR #35 HF-P1A human labels: MERGED GREEN.
- PR #36 HF-P1B dashboard cockpit: MERGED GREEN; CI #1080 green 8.3/8.4/8.5, PHP8.3 `652/7724/1 skip/0 warnings`.
- PR #37 HF-P1C-A contract operator: MERGED GREEN; final `c05842c8...`; merge `b978219a...`; CI #1091 green 8.3/8.4/8.5, PHP8.3 `657/7782/1 skip/0 warnings`.

## R3 frozen

- PR #33 OPEN / DRAFT / NOT MERGED.
- Branch `impl/r3-public-surfaces-20261016`.
- HEAD `845295c777151216655b7a7866bc0dc9d93f3e2e`.
- Functional CI #1056 green 8.3/8.4/8.5.
- Browser QA 375x812 / 768x1024 / 1440x900: BLOCKED / NOT_CERTIFIED.
- Nuevo drift crítico: frente a `main@85da0676...`, R3 está DIVERGED, ahead 13 / behind 23, merge base `b0403a5a...`; PR #33 aparece no mergeable.
- No usar el antiguo merge gate sin reconciliar main + nueva CI + Browser QA.
- R4 NOT STARTED.

## Human-First governance gaps

- Master exacto V2.1 NO está en repo; fuente 70,982 bytes; SHA-256 `55265c92b7a49a4ac2ac976afb6789074b5f95ff5760f6d08b7b1d0e817f6940`; BLOCKED_TOOL_SAFETY.
- No resumir/truncar/bypassear.
- `.github/skills/erp-human-first-ui-ux/SKILL.md`: missing.
- `design-system/alquilapro/MASTER.md`: missing.
- `.github/pull_request_template.md`: missing.
- Browser control: blocked / not certified.

## Próximos Human-First después de P1C-B

- HF-P1D candidate detail progressive disclosure.
- HF-P1E obligations disclosure.
- HF-P1F tenant portal IA.
- HF-P2A candidates pagination/filter.
- HF-P2B feedback consistency.
- P2 human money/date formatting.

## Errores a tratar con pinzas

- PDO native prepared statements: no reutilizar el mismo named placeholder; HY093 probado.
- Candidate canonical document normalization vs stale lowercase fixture: no parchear dominio por fixture.
- Structural tests pueden quedar stale tras contrato aprobado: actualizar regresión semántica, no aflojar suite ni parchear producto.
- `pull_request` CI puede usar synthetic merge ref: registrar PR_HEAD/BASE/RUN/SYNTHETIC SHA.
- Nunca reutilizar run/job IDs de otra corrida.
- TOCTOU en writers: transaction/lock/re-read/validate/mutate/commit sólo cuando el riesgo esté demostrado.
- Tenant IDOR: session user → `LeaseService::findForUser()` → child lookup; nunca child first.
- Private storage: traversal/symlink/size/MIME/integrity/no-store/nosniff.
- `FinanceService` sigue único charge writer; no segundo ledger.
- Audit metadata no secrets/PII bodies/SQL/stack/temp passwords/tokens.
- Antes/después de tool write: verificar repo/ref/SHA/path/tool y después head/file/diff.
- No CSS override chains / `!important` como estrategia.
- Demo real no debe ocultar flujos rotos.
- Browser QA no se sustituye con lectura de CSS/tests.

## Handoff descargable fuera del repo

Archivo generado al cierre del chat:

`ALQUILAPRO_HANDOFF_FORENSE_CHAT_END_HF_P1C_B_RED_2026-10-16.md`

- líneas: 3325
- bytes: 52,255
- SHA-256: `b3abb1a3cbeca034627dd24831e5a1ddc0552783048be2ca05863c2cbd16d4a5`

Ese archivo es el handoff largo para adjuntar al chat nuevo. Este checkpoint GitHub es el puntero durable compacto.

## Hard stops

- no repetir HF-P1C-B RED;
- no iniciar R4;
- no mergear R3 as-is;
- no deploy;
- no usar old F25 artifact;
- no crear segundo ContractService ni segunda authority;
- no llamar UI GREEN sin Browser real cuando el gate lo exige.

`RECOVERY_READY=YES`
`RESUME_EXACTLY_FROM=HF-P1C-B minimal GREEN after adf12146`
`DEPLOYMENT_AUTHORIZED=NO`
