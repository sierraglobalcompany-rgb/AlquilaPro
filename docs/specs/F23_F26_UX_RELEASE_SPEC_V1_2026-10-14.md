# ALQUILAPRO — F23→F26 UX + RELEASE SPEC V1

**Fecha:** 2026-10-14  
**Estado:** DESIGN FROZEN / IMPLEMENTATION NOT STARTED  
**Base factual al diseñar:** `main` post-F14 + documentación F15→F20; Codex F15→F20 puede estar trabajando en paralelo.  
**Referencia UX externa:** `nextlevelbuilder/ui-ux-pro-max-skill` — referencia de diseño/QA únicamente; **NO runtime dependency**.

---

# 0. OBJETIVO

Cerrar AlquilaPro como producto moderno y desplegable sin convertirlo en SPA ni introducir arquitectura visual pesada.

Esta SPEC corrige dos huecos del roadmap final:

1. el shell actual usa barra inferior para todos los tamaños y no ofrece una experiencia PC realmente distinta;
2. F24→F26 deben certificar un installer wizard, candidate wizard, paquete local reproducible y despliegue manual a Hostinger.

Nuevo cierre:

```text
F23 — Dashboard + Adaptive UX Final
F24 — Release Readiness + Wizards + QA
F25 — SERVER_READY Package + Local Rehearsal
F26 — Manual Hostinger Deploy + Smoke
```

F21/F22 conservan su alcance funcional propio y anteceden este bloque.

---

# 1. LEYES

## KISS

Un único sistema visual, dos presentaciones adaptativas:

```text
TOUCH SHELL  = móvil + tablet
DESKTOP SHELL = PC
```

Mismos datos, mismas rutas, mismos permisos, mismo SSR.

No crear:

```text
mobile app separada
tablet app separada
desktop app separada
SPA
frontend framework obligatorio
second route tree
second auth
second component system
```

## Cero Ruido

Navegación se define desde una sola fuente PHP de módulos/permisos y se renderiza en dos shells visuales.

No duplicar manualmente listas de módulos para bottom launcher y sidebar.

## Antiparches

No resolver el problema con una media-query aislada sobre la barra actual.

Owner correcto:

```text
app layout + design tokens + shared navigation model
```

---

# 2. REFERENCIA UI/UX PRO MAX — REGLAS ADOPTADAS

De la referencia externa se adoptan como criterios de calidad:

```text
mobile-first
bottom navigation <=5 destinations
44×44px / 48×48dp touch targets
8px+ separation between touch controls
safe-area awareness
fixed bottom bar reserves content padding
no horizontal scroll
systematic breakpoints
desktop max-width/container discipline
visible focus states
keyboard navigation
contrast >=4.5:1 for normal text
SVG icons, not emoji icons
consistent icon language
reduced-motion support
one primary CTA per screen
text must reflow without clipping
```

La referencia clasifica Real Estate como estilo `Corporate Minimal Fresh`, con sensación profesional/confiable, tipografía sans limpia y sin efectos baratos o demasiado trendy.

Estas reglas son **design guidance**; no instalar el CLI/package en producción.

---

# 3. DESIGN DIRECTION

## Estilo

```text
Modern SaaS / Corporate Minimal Fresh
clean
professional
trustworthy
calm
high information clarity
subtle depth
```

Evitar:

```text
look 1990s
bevels
heavy gradients
random glassmorphism
neon
emoji icons
excess shadows
cramped tables
font sizes <12px
hover-only interactions
```

## Paleta default

Branding sigue configurable.

Default recomendado:

```text
primary: teal #0F766E
secondary: navy #1E3A8A
accent: gold #D4AF37 only for restrained emphasis
surface: #FFFFFF
canvas: #F8FAFC
text: #0F172A
muted: slate scale
```

Los componentes usan tokens semánticos, no raw hex repetidos.

## Tipografía

KISS:

```text
system UI sans stack
```

No Google Font obligatoria.

Escala mínima:

```text
12 label only
14 secondary
16 body/mobile inputs
18 section title
24 page title
32 hero/desktop dashboard only where useful
```

---

# 4. BREAKPOINT MODEL

AlquilaPro no usa user-agent sniffing.

Default = touch/mobile-first.

```text
0–767      mobile
768–1199   tablet/touch layout
>=1200     desktop candidate
```

Desktop shell se activa cuando el viewport permite escritorio; interacciones nunca deben depender exclusivamente de hover.

Target QA widths:

```text
375
768
1024
1440
```

Además verificar landscape touch.

---

# 5. TOUCH SHELL — MÓVIL + TABLET

## 5.1 Topbar

Compacta:

```text
brand / current context
optional page title
account avatar/action
```

No menu hamburger como navegación primaria.

## 5.2 Launcher inferior

Fixed bottom dock, safe-area aware.

Máximo 5 destinos; V1 preferido:

```text
Inicio
Apps
Cuenta
```

Puede mostrar un cuarto destino solo cuando sea universal y estable después de F20; no llenar la barra por estética.

`Apps` es el launcher principal para staff.

Touch requirements:

```text
min 48px hit area
>=8px separation
icon + short label
selected state visible beyond color alone
press feedback
aria-current
content bottom padding includes dock + env(safe-area-inset-bottom)
```

## 5.3 Apps launcher

En móvil:

```text
full-width bottom sheet / near-fullscreen sheet
```

En tablet:

```text
large centered sheet or anchored panel
```

Contenido:

```text
permission-filtered app grid
2 columns mobile
3–4 columns tablet depending width
```

No tabla de favoritos ni preferencias V1.

No new DB state.

## 5.4 Content layout

Mobile:

```text
single column
cards replace dense table presentation where needed
forms stacked
primary action easy thumb reach
sticky actions only when they materially help
```

Tablet:

```text
2-column cards where useful
wider forms
same bottom launcher
no desktop sidebar
```

---

# 6. DESKTOP SHELL — PC

Desktop PC debe sentirse distinto, no una versión estirada del móvil.

## 6.1 Sidebar

Persistent left sidebar:

```text
240–280px
brand at top
permission-filtered modules
clear active state
groups
```

Suggested groups:

```text
Operación
  Inicio
  Propiedades
  Candidatos
  Contratos

Finanzas
  Cartera

Gestión
  Seguros
  Mantenimiento
  Documentos
  Convivencia
  Servicios/Membresías

Administración
  Usuarios
  Apariencia
```

Only render modules that exist and user may view.

No placeholder future menu items.

## 6.2 Desktop topbar

```text
page title/context
breadcrumb only where useful
primary page action
account control
```

No duplicated global navigation in topbar.

## 6.3 Desktop content

```text
main canvas
max useful width ~1440px
grid/cards/tables chosen by data density
60–75 char readable prose width
keyboard-first operability
```

Tables remain tables on PC when they improve scanning.

Mobile does not simply horizontally scroll those desktop tables.

---

# 7. SINGLE NAVIGATION AUTHORITY

Create/extend one PHP navigation model/array owned by layout/bootstrap-level presentation code.

Each module item contains only presentation metadata:

```text
key
label
href
permission/role rule
icon key
group
```

Both:

```text
bottom Apps launcher
desktop sidebar
```

consume the same collection.

Do not create navigation tables or DB configuration.

---

# 8. ICON SYSTEM

Use one consistent SVG language, preferably Lucide-like outline geometry.

KISS implementation:

```text
small curated local SVG map / partials
```

No JS icon runtime package required.

Requirements:

```text
same stroke family
aria-hidden when decorative beside text
accessible name when icon-only
no emojis as nav icons
```

---

# 9. COMPONENT QUALITY BAR

Standardize existing SSR components through CSS/classes, not a frontend framework:

```text
buttons
inputs
selects
checkbox/radio
cards
metric cards
tables
badges/chips
empty states
alerts
dialogs/sheets
pagination
stepper
file upload
loading/disabled state
```

One primary CTA per screen.

Semantic status colors always include text/icon meaning.

---

# 10. PUBLIC UX

Public surfaces also receive final polish:

```text
landing
catalog
catalog detail
application wizard
login/reset
installer
```

They do not use the private app sidebar/dock.

Visual system remains related through tokens/typography.

---

# 11. CANDIDATE WIZARD

F24 improves the existing candidate flow; no new candidate/application authority.

Flow:

```text
1 unit/context + privacy consent
2 personal/contact
3 work/income
4 occupants/pets
5 references
6 required documents
7 review + submit
```

Rules:

```text
same candidate row
token continuation preserved
no candidate_wizard_steps table
no application_sessions table
step derived from route + completeness
visible progress stepper
back/cancel path
errors near field
save/continue
mobile-first
```

---

# 12. INSTALLER WIZARD

F24 improves existing Installer instead of creating another installer.

```text
Step 0 — server requirements
Step 1 — DB credentials + explicit Test connection
Step 2 — company/administration settings
Step 3 — owner account
Step 4 — summary
Step 5 — install/migrate
Step 6 — success/login
```

DB Next button enabled only after successful connection verification for current credentials.

Installer never creates:

```text
tenant
candidate
lease
fake property
sample business data
```

Company data remains settings/branding in V1; no `companies` table.

---

# 13. F23 — DASHBOARD + ADAPTIVE UX FINAL

F23 revised scope:

```text
final dashboard
shared navigation authority
touch shell
mobile/tablet bottom launcher
desktop sidebar shell
design tokens/component polish
responsive page-level adaptation
```

Architecture budget:

```text
NEW_TABLES: 0
NEW_RUNTIME_DEPS: 0 expected
NEW_DOMAIN_SERVICES: <=1 DashboardService only if query consolidation proves useful
NEW_AUTH: 0
NEW_ROUTE_TREES: 0
```

UI-UX Pro Max is reference only.

F23 must not change domain semantics.

Dashboard shows only actionable real data.

No decorative analytics.

---

# 14. F24 — RELEASE READINESS + WIZARDS + QA

F24 revised scope:

```text
installer wizard
candidate wizard
cross-device UX QA
accessibility QA
PHP 8.3/8.4/8.5
composer/phpunit
clean migration
upgrade migration
installer
catalog
applications
auth
PDF
email mock
cron
uploads
permissions
UTF-8
timezone
responsive
```

Architecture budget:

```text
NEW_PRODUCT_TABLES: 0
NEW_DOMAIN_SERVICES: 0 expected
```

F24 is convergence/hardening, not feature expansion.

---

# 15. F25 — SERVER_READY + LOCAL REHEARSAL

Build:

```text
ALQUILA_PRO_V1_SERVER_READY.zip
```

Include:

```text
vendor production
installer
config example
web config
storage dirs
permissions guidance
release notes
version
SHA-256 checksum
```

Never include real secrets/test credentials.

## Mandatory local rehearsal

Test the exact ZIP artifact:

```text
clean local folder
→ extract ZIP
→ empty DB
→ open installer
→ Test connection
→ company
→ owner
→ install
→ login
→ create property/unit
→ public catalog
→ candidate wizard via link
→ tenant/portal representative flow
→ files/PDF/email mock/cron/payment methods
```

Only artifact that passes this may be labeled SERVER_READY.

---

# 16. F26 — MANUAL HOSTINGER DEPLOY + SMOKE

F26 is not an automated deployment architecture.

User uploads/extracts the F25 artifact manually in Hostinger.

Then:

```text
create/select Hostinger DB
open domain
run same installer wizard
Test DB connection
complete company/owner
install
smoke
```

Smoke:

```text
HTTPS
landing
catalog
login
property/unit
candidate wizard
portal
uploads
PDF
SMTP/email
cron
Bre-B/QR
permissions
logs
responsive touch shell
responsive desktop shell
```

No sensitive production-like data in initial smoke.

If Hostinger access is unavailable to an agent, report `DEPLOYMENT_HANDOFF_READY`; never claim deployment happened.

---

# 17. RESPONSIVE ACCEPTANCE MATRIX

Minimum verification:

```text
375×812 mobile
768×1024 tablet portrait
1024×768 tablet landscape
1440×900 desktop
```

For every critical flow:

```text
no horizontal overflow
focus visible
keyboard usable on desktop
bottom dock does not obscure focused/control content
safe-area respected
labels do not clip
buttons/touch targets adequate
modal/sheet escapable
reduced motion respected
```

Critical screens:

```text
login
home/dashboard
apps launcher
properties/unit
candidate/admin
candidate public wizard
finance
maintenance
lease portal
installer wizard
```

---

# 18. PERFORMANCE / KISS GUARDRAILS

No frontend build chain required for production.

Prefer:

```text
one/few CSS files
minimal vanilla JS
SSR
local SVGs
lazy images
width/height or aspect-ratio
system font
```

Do not install UI-UX Pro Max into AlquilaPro runtime.

Do not add React/Vue/Tailwind solely for redesign.

---

# 19. 10-PASS DESIGN AUDIT

```text
1 KISS: one app, two adaptive shells              PASS
2 Zero Noise: one navigation authority            PASS
3 Antipatch: shell owner fixed, not CSS band-aid  PASS
4 Mobile UX: launcher <=5 + safe-area             PASS
5 Tablet UX: launcher preserved                   PASS
6 Desktop UX: sidebar/topbar distinct             PASS
7 Accessibility: contrast/focus/touch/reflow      PASS
8 Wizards: existing authorities improved          PASS
9 Release: exact ZIP locally rehearsed            PASS
10 Deploy: manual Hostinger + honest smoke        PASS
```

`F23_F26_UX_RELEASE_SPEC_AUDIT_10_PASSES: PASS`

---

# 20. NEXT

Do not implement this while Codex F15→F20 is touching overlapping product surfaces unless execution is explicitly sequenced.

After F15→F20 forensic audit/merge, use this SPEC to write/finalize F21→F26 implementation plans and the final Codex master.
