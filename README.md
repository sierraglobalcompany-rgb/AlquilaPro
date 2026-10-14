# Alquila Pro

Aplicación web autoinstalable para administrar arrendamientos de vivienda y parqueaderos, con catálogo público, operación privada para administración/inquilinos y UX adaptativa por dispositivo.

**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + SSR HTML + CSS/JS mínimo  
**Destino final:** Hostinger, HTTPS, subdominio o subcarpeta; primero se certifica completamente en local.

## Estado canónico actual

```text
F0–F14: CLOSED / VERIFIED
F1–F14: MERGED
F14_MERGE_SHA: c58ee5e660f51e873546f03a578fde6b5778ba19

F15: local certified / PR #18 OPEN / not merged
F16: product complete / PR #19 OPEN stacked on F15 / not merged
F17: product complete + docs closure / PR #20 OPEN stacked on F16 / not merged
F18–F20: Codex batch still in progress; not visible remotely at this README refresh

F21–F22: design direction approved, SPEC/PLAN still pending finalization
F23–F26: UX/release design frozen; implementation not started
```

Codex está ejecutando el bloque F15→F20. Antes de asumir estado, verificar `main`, ramas `impl/f15...` a `impl/f20...`, PR #18+ y `docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md` en la rama más avanzada.

---

# 1. Punto de entrada obligatorio

Antes de tocar código o continuar un chat:

1. `README.md`;
2. `AGENTS.md`;
3. último handoff `docs/checkpoints/ALQUILAPRO_HANDOFF_FORENSIC_*`;
4. SPEC/PLAN maestro;
5. último checkpoint `*_MERGED_*`;
6. checkpoint de diseño/ejecución activo;
7. SPEC/PLAN de la fase;
8. PR/CI/código real.

Autoridad factual:

```text
GitHub/code/tests/CI
> checkpoint activo
> SPEC/PLAN
> handoff
> memoria de chat
```

---

# 2. Leyes supremas

## Ley 1 — KISS / arquitectura simple

La solución correcta debe ser simple, eficiente, optimizada, eficaz, auditable y mantenible, con el mínimo razonable de tablas, clases, estados, rutas, capas, dependencias y fuentes de verdad.

## Ley 2 — Cero Ruido / Convergencia

```text
ELIMINAR
→ UNIR / CONVERGER
→ SIMPLIFICAR
→ MEJORAR / OPTIMIZAR
→ AGREGAR
```

Antes de eliminar demostrar referencias, autoridad, reemplazo y regresión.

## Ley 3 — Antiparches

```text
REPRODUCE
→ ROOT CAUSE
→ OWNING AUTHORITY
→ SEARCH DUPLICATION
→ RED
→ MINIMAL GREEN
→ REMOVE SUPERSEDED LOGIC
→ REGRESSION
```

No compatibility fallbacks, segundos writers, optional dependencies para callers viejos ni parches laterales si puede corregirse el owner.

---

# 3. Metodología

```text
SCOPE
→ PRE-KISS
→ SPEC / DESIGN
→ PLAN
→ AUDIT 10 PASSES
→ RED
→ VERIFY RED
→ MINIMAL GREEN
→ VERIFY GREEN
→ CONVERGE
→ FOCUSED QA
→ FRESH REVIEW
→ KISS / ZERO-NOISE / ANTIPATCH GATE
→ CHECKPOINT
→ PR CI
→ FORENSIC AUDIT
→ MERGE AUTORIZADO
```

`continua` autoriza el siguiente gate cuando el contexto ya lo deja inequívoco. No merge automático fuera de un gate autorizado.

---

# 4. Preferencias de trabajo

- ChatGPT/Sol diseña, planifica, audita 10 pasadas, contrasta GitHub y busca huecos antes de implementar/delegar.
- Codex implementa TDD focal y refactor controlado; no vuelve a diseñar decisiones congeladas.
- Cuando se use Codex, entregar al usuario **un Markdown descargable**, no un prompt gigante pegado en el chat.
- Prompts Codex: específicos, guiados, eficientes, con autonomía local, hard stops, checkpoint recuperable, anti-bucles e informe final detallado.
- No repetir full suites/CI si el árbol y riesgo probado son equivalentes.

---

# 5. Estado cerrado F12→F14

```text
F12 PR #15 MERGED
merge 23cf7576c95fca5e55825d8b5ffd5903bf3d3149
CI 38519741952 — 406 tests / 4,193 assertions por matriz

F13 PR #16 MERGED
repair b11705cb387129c51090bbef0c3658c4843f940d
merge 57911302bd4e97e76b1e0fa6dc71c8ef24372453
CI 38553176872 — 431 / 4,451

F14 PR #17 MERGED
merge c58ee5e660f51e873546f03a578fde6b5778ba19
CI 38553704399 — 443 / 4,604
```

F13-SEC-01 cerrado: cap físico + `fstat()` + bounded read + MIME/SHA sobre los mismos bytes.

---

# 6. Bloque activo F15→F20

Master canónico:

```text
docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
V4 FINAL LEAN-FAST / AUDITED 10 PASSES
```

Checkpoint vivo:

```text
docs/checkpoints/F15_F20_BATCH_CHECKPOINT_2026-10-14.md
```

Estado remoto conocido al refrescar README:

```text
F15 branch impl/f15-agenda-visits-20261014
  product d2c23d44aeefd28ba70fce9ad1d8679d87a3275e
  closure c40cd33321739effdce0253f67dc372e412dccde
  PR #18 OPEN
  local review: 4 Important reparados
  main avanzó con documentación; PR requiere reconciliar README antes de certificación final

F16 branch impl/f16-reminders-email-20261014
  product 7b5118bc6b6ec29ceab8cfe3e2eb0901aafa3f7d
  closure b8c951a5359344fe441d8ab4be5dc86b07922c47
  PR #19 OPEN, stacked on F15
  256 affected tests / 3,066 assertions PASS
  3 Important reparados
  local host sin GD afecta AppBootstrapTest, no producto F16

F17 branch impl/f17-whatsapp-assisted-20261014
  product 56d221c61e3ec7ddf5ca710b022a44de112cd846
  closure d14d50f7e58fcf50dba9114e322c09fe0e9e9e66
  PR #20 OPEN, stacked on F16
  focused 56 tests / 445 assertions PASS
  0 Critical / 0 Important; 2 Minor diferidos

F18–F20: todavía pendientes/no visibles remotamente en este corte
MERGES: NONE
```

Presupuestos:

```text
F15 1 maintenance_visits + 1 VisitService
F16 1 notification_jobs + 1 NotificationService + PHPMailer ^7.1
F17 1 whatsapp_actions + 1 WhatsAppService + whatsapp.use
F18 1 documents + 1 DocumentService + documents.view/manage
F19 1 community_incidents + 1 CommunityService + reuse F18
F20 2 tablas digital_services/service_memberships + 1 MembershipService
```

F20:

```text
tenant accepts service → membership persists → NO automatic charge
paid membership → admin decides Crear cargo / No crear cargo
CREATE → FinanceService canonical writer
SKIP → terminal no-charge decision
```

---

# 7. Roadmap final F21→F26

No existe F27 en el roadmap canónico.

```text
F21 Bono + liquidación contractual
F22 Seguridad + privacidad + audit log
F23 Dashboard + Adaptive UX Final
F24 Release Readiness + Wizards + QA
F25 SERVER_READY ZIP + exact-artifact local rehearsal
F26 Manual Hostinger Deploy + smoke
```

## F23 UX adaptativa

- móvil + tablet: touch shell y launcher inferior fijo; preferido `Inicio / Apps / Cuenta`, máximo 5;
- tablet conserva shell táctil; no hereda automáticamente PC;
- PC: sidebar persistente + topbar + canvas amplio;
- una sola definición PHP de navegación y permisos para ambos shells;
- SSR/CSS/JS mínimo; no React/Vue/Tailwind por rediseño;
- UI-UX Pro Max se usa como guía, no dependencia runtime;
- Modern SaaS / Corporate Minimal Fresh; SVG locales, focus visible, contraste, safe-area y touch targets.

## F24 installer/candidate wizard

Mejorar autoridades existentes, no crear sistemas paralelos.

Installer:

```text
0 requisitos servidor
1 DB credentials + Test connection
2 empresa/administración
3 owner
4 resumen
5 instalar/migrar
6 éxito/login
```

No crea tenant/candidate/lease/propiedad ficticia ni tabla `companies` V1.

Candidate wizard:

```text
1 contexto/privacidad
2 personal/contacto
3 trabajo/ingresos
4 ocupantes/mascotas
5 referencias
6 documentos
7 revisar/enviar
```

Mismo candidate/token; no `candidate_wizard_steps`, `candidate_progress` ni `application_sessions`.

## F25

El ZIP `ALQUILA_PRO_V1_SERVER_READY.zip` solo obtiene SERVER_READY si el **artefacto exacto** instala desde cero localmente en carpeta limpia + BD vacía mediante el wizard real.

## F26

El usuario sube/extracta manualmente el ZIP aprobado en Hostinger y ejecuta el mismo installer + smoke. No SSH/API/deployment bot obligatorios.

Autoridades:

```text
docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md
docs/checkpoints/F23_F26_UX_RELEASE_DECISION_2026-10-14.md
```

---

# 8. Riesgos aprendidos

- TOCTOU: lock de autoridad canónica antes de mutar.
- Private storage: path seguro, no symlink, cap físico antes de lectura, bounded read, MIME/tamaño/SHA, fail-closed.
- Tenant IDOR: `LeaseService::findForUser()` antes de lookup lateral.
- `FinanceService` = único charge writer.
- Un solo auth/session/CSRF.
- `pull_request` CI suele certificar synthetic merge-ref; no confundir con PR HEAD.
- Product Head vs docs-only Closure Head.
- No constructor fallback/optional collaborator para conservar callers viejos.
- F16 = único motor automático de notificaciones.
- F18 = única autoridad documental genérica.
- F17 nunca inventa sent/delivered/read.
- F20 nunca cobra automáticamente al aceptar.
- Installer wizard ≠ candidate wizard ≠ tenant creation.
- PC no debe ser móvil estirado; F23 corrige el owner del shell, no una media-query parche.

---

# 9. Recovery

```text
README
→ AGENTS
→ último ALQUILAPRO_HANDOFF_FORENSIC_*.md
→ F14_MERGED
→ F15_F20_DESIGN_CHECKPOINT
→ F15_F20 Codex master V4
→ live F15_F20_BATCH_CHECKPOINT en la rama más avanzada
→ F23_F26 UX/release checkpoint
→ GitHub real
```

No reimplementar trabajo certificado sin evidencia de regresión o nueva SPEC aprobada.
