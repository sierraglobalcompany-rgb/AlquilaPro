# ALQUILAPRO — HANDOFF FORENSE DE RECUPERACIÓN V2

**Fecha:** 2026-10-14  
**Repo:** `sierraglobalcompany-rgb/AlquilaPro`  
**Estado:** F0–F14 cerradas/verificadas; F1–F14 mergeadas; Codex ejecutando F15→F20; F23→F26 diseño congelado.  
**Propósito:** continuidad entre chats sin reabrir trabajo cerrado ni perder leyes/decisiones.

## 1. Autoridad

```text
GitHub/code/tests/CI
> checkpoint vivo
> checkpoint final
> SPEC/PLAN
> handoff
> memoria de chat
```

Antes de actuar leer `README.md`, `AGENTS.md`, este handoff, `F14_MERGED`, el master/checkpoint F15→F20 y GitHub real.

## 2. Leyes obligatorias

### KISS

Solución simple, eficiente, optimizada, eficaz, auditable y mantenible con el mínimo razonable de tablas, clases, estados, rutas, capas, dependencias y fuentes de verdad.

### Cero Ruido

```text
ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR
```

Antes de borrar demostrar referencias, autoridad, reemplazo y regresión/CI.

### Antiparches

```text
REPRODUCE → ROOT CAUSE → OWNING AUTHORITY → SEARCH DUPLICATION
→ RED → MINIMAL GREEN → REMOVE SUPERSEDED LOGIC → REGRESSION
```

No compatibility fallbacks, segundos writers ni parches laterales si puede corregirse el owner.

## 3. Preferencias del usuario

ChatGPT/Sol debe revisar GitHub, recuperar pasado/presente/futuro, hacer PRE-KISS, diseñar, planificar, auditar 10 pasadas, buscar huecos y solo después implementar/delegar.

Cuando se use Codex, entregar al usuario **un Markdown descargable**; no pegar como salida principal el prompt enorme en el chat. Debe ser guiado, específico, eficiente, con budgets, autonomía local, hard stops, checkpoint recuperable, anti-loop e informe final detallado.

## 4. Estado integrado

```text
F0–F14 CLOSED / VERIFIED
F1–F14 MERGED
LAST_MERGED_PRODUCT_PHASE: F14
F14_MERGE_SHA: c58ee5e660f51e873546f03a578fde6b5778ba19
```

F12→F14 quedaron certificados con KISS/Zero Noise/Antipatch. No reabrir sin regresión o nueva SPEC.

## 5. Batch activo F15→F20

Autoridades:

```text
docs/checkpoints/F15_F20_DESIGN_CHECKPOINT_2026-10-14.md
docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md  # rama activa
```

### F15

```text
branch impl/f15-agenda-visits-20261014
product d2c23d44aeefd28ba70fce9ad1d8679d87a3275e
closure c40cd33321739effdce0253f67dc372e412dccde
PR #18 OPEN
local certified
review: 4 Important repaired
```

Gap: PR #18 tiene conflicto documental en `README.md` porque `main` avanzó con documentación. Resolver conscientemente al final del batch; no confundir con defecto de producto.

### F16

```text
branch impl/f16-reminders-email-20261014
product 7b5118bc6b6ec29ceab8cfe3e2eb0901aafa3f7d
closure b8c951a5359344fe441d8ab4be5dc86b07922c47
PR #19 OPEN stacked on F15
256 affected tests / 3066 assertions PASS
3 Important repaired
```

Minors diferidos: scans de reminder sin paginación; recipient name/email sin retention explícita. Revaluar con evidencia, especialmente en F22 privacy.

El host local de Codex no tiene GD; `AppBootstrapTest` puede fallar por precondición del installer. No atribuir automáticamente a producto F16.

### F17

```text
branch impl/f17-whatsapp-assisted-20261014
product 56d221c61e3ec7ddf5ca710b022a44de112cd846
closure d14d50f7e58fcf50dba9114e322c09fe0e9e9e66
PR #20 OPEN stacked on F16
56 tests / 445 assertions PASS
0 Critical / 0 Important
```

Minors: history latest 100 sin paginación; UI no muestra actor names. No añadir piezas sin demostrar necesidad.

F17 nunca persiste `sent|delivered|read|provider_message_id`.

### F18–F20

Codex sigue trabajando. Al último corte no había ramas/PR remotos F18–F20.

Budgets:

```text
F18 1 documents + 1 DocumentService + documents.view/manage
F19 1 community_incidents + 1 CommunityService + reuse F18
F20 digital_services + service_memberships + 1 MembershipService
```

F20: aceptación de servicio NO crea cargo automático. Para servicio pago, admin decide `Crear cargo` o `No crear cargo`; CREATE llama exclusivamente a `FinanceService`.

## 6. Errores aprendidos / zonas delicadas

- F8 TOCTOU unit edit vs lease activation → lock canonical `units` row.
- F11 constructor fallback ocultaba dependencia → constructor explícito + actualizar callers.
- GitHub PR CI suele probar synthetic merge-ref; registrar PR_HEAD/PR_BASE/CI_MERGE_REF.
- Blank allocation ≠ invalid allocation.
- Environment smoke antes de full QA; GD ya causó falsos negativos de entorno.
- F13 private reads: physical size cap + fstat + bounded read + MIME/SHA sobre mismos bytes.
- Tenant IDOR: ownership antes de lookup lateral.
- `FinanceService` único charge writer.
- F16 único motor automático de notificaciones.
- F18 única autoridad documental genérica.
- Product Head y Closure Head deben separarse; closure docs-only.
- No full suite/CI repetitiva sobre árbol/riesgo equivalente.

## 7. Futuro F21→F26

No existe F27.

```text
F21 Bono + liquidación contractual
F22 Seguridad + privacidad + audit log
F23 Dashboard + Adaptive UX Final
F24 Release Readiness + Wizards + QA
F25 SERVER_READY ZIP + exact-artifact local rehearsal
F26 Manual Hostinger Deploy + smoke
```

### F21

Design direction aprobada; SPEC/PLAN finales pendientes. PRE-KISS: máximo 2 tablas (`good_conduct_bonuses`, `lease_liquidations`) y una autoridad de terminación/liquidación. Bono separado, desactivado por defecto hasta revisión contractual; no segundo ledger; FinanceService sigue siendo la autoridad financiera. No duplicar `LeaseService::end()`.

### F22

Design direction aprobada; SPEC/PLAN pendientes. PRE-KISS: máximo 1 `audit_log` + 1 `AuditService`, append-only/minimal. Hardening OWASP, IDOR, tokens, uploads, sessions, CSRF, storage, headers/logs. No event bus ni auth paralelo.

### F23→F26

Autoridades:

```text
docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md
docs/checkpoints/F23_F26_UX_RELEASE_DECISION_2026-10-14.md
```

#### F23

No es solo dashboard. Cierra UX adaptativa:

- móvil + tablet = touch shell con launcher inferior; preferido `Inicio / Apps / Cuenta`, <=5;
- tablet conserva shell táctil;
- PC = sidebar persistente + topbar + canvas amplio;
- una sola definición PHP de navegación/permiso;
- SSR/CSS/JS mínimo; no React/Vue/Tailwind por rediseño;
- UI-UX Pro Max es referencia de diseño, NO runtime dependency;
- Modern SaaS / Corporate Minimal Fresh;
- targets 375/768/1024/1440.

#### F24

Release Readiness + installer wizard + candidate wizard + QA.

Installer mejora F1 existente:

```text
0 requirements
1 DB credentials + Test connection
2 company/settings
3 owner
4 summary
5 install/migrate
6 success/login
```

No crea tenant/candidate/lease/sample property y no añade `companies` table V1.

Candidate wizard mejora F5:

```text
context/privacy → personal/contact → work/income → occupants/pets
→ references → documents → review/submit
```

Mismo candidate/token; no wizard/progress/session tables.

F24 además certifica PHP 8.3/8.4/8.5, clean/upgrade migrations, installer, catalog, applications, auth, PDF, email mock, cron, uploads, permissions, UTF-8, timezone, responsive/accessibility.

#### F25

`ALQUILA_PRO_V1_SERVER_READY.zip` solo es SERVER_READY si el **ZIP exacto** se extrae en carpeta local limpia, usa BD vacía, completa installer real y pasa un flujo representativo. Luego se congela SHA-256.

#### F26

Subida/extracción MANUAL del ZIP aprobado a Hostinger + mismo installer + smoke. No SSH/Hostinger API/FTP bot/CI deploy obligatorios. Si un agente no tiene acceso real, debe reportar `DEPLOYMENT_HANDOFF_READY`, no fingir despliegue.

## 8. Huecos activos

```text
H01 F15 PR README conflict
H02 F16 minor pagination
H03 F16 recipient retention policy
H04 F17 history max 100
H05 F17 actor names UI
H06 F18–F20 not finished yet
H07 F21 SPEC/PLAN pending
H08 F22 SPEC/PLAN pending
H09 final F21→F26 Codex master pending
H10 .phpunit.cache/ local generated noise to verify/ignore later
H11 app/graphify-out/ untracked generated artifact to classify later
H12 noop-do-not-use branches / old docs-tmp branch hygiene
H13 main branch protection disabled
```

No convertir Minors/hygiene en arquitectura nueva sin evidencia.

## 9. Cuando Codex termine F15→F20

Auditar contra GitHub, no aceptar reporte por fe:

```text
F15→F20 branches/PR/base/head
changed files
migrations/services/permissions/routes
checkpoint vivo
RED/GREEN
fresh review
repairs
Product→Closure docs-only
CI merge-ref PHP 8.3/8.4/8.5
KISS / Zero Noise / Antipatch
```

Merge esperado secuencial F15→F20 con retarget de cada PR a `main`, demostrando equivalencia del tree o recertificando cuando el riesgo cambie.

## 10. Trabajo paralelo seguro

Mientras Codex termina F15→F20 se puede:

```text
finalizar SPEC F21
finalizar SPEC F22
escribir planes F21→F26
integrar diseño congelado F23→F26
preparar un único master Codex F21→F26
```

No implementar F15→F20 paralelo.

## 11. Recovery

```text
README.md
→ AGENTS.md
→ este handoff
→ F14_MERGED
→ F15_F20 design checkpoint
→ F15_F20 Codex master V4
→ live F15_F20 batch checkpoint de la rama más avanzada
→ F23_F26 UX/release checkpoint
→ GitHub real
```

## 12. Auditoría de este handoff — 10 pasadas

```text
1 GitHub factual state            PASS
2 KISS                             PASS
3 Zero Noise                       PASS
4 Antipatch                        PASS
5 Past F0→F14                      PASS
6 Active F15→F20                   PASS
7 Future F21→F26                   PASS
8 User/Codex working preferences   PASS
9 Critical risk lessons            PASS
10 Recoverability                  PASS
```

`HANDOFF_FORENSIC_AUDIT_10_PASSES: PASS`

## 13. Siguiente gate

```text
CODEX CONTINUES F18→F20
→ user brings final Codex result
→ ChatGPT/Sol forensic audit F15→F20
→ repair real findings only
→ sequential merge F15→F20
```

En paralelo:

```text
finalize F21/F22 SPEC/PLAN
→ prepare final F21→F26 Codex master
```
