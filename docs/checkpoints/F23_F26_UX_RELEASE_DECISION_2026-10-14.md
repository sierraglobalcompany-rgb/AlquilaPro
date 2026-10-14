# F23→F26 — UX + RELEASE DECISION CHECKPOINT

**Fecha:** 2026-10-14  
**Estado:** DESIGN FROZEN / NO PRODUCT IMPLEMENTATION STARTED

## Autoridad

```text
docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md
```

Referencia UX externa:

```text
https://github.com/nextlevelbuilder/ui-ux-pro-max-skill
```

Uso:

```text
design/UX guidance only
NOT runtime dependency
```

## Decisiones congeladas

### F23

```text
Dashboard + Adaptive UX Final
```

- mobile/tablet = touch shell con launcher inferior;
- PC = desktop shell distinto con sidebar + topbar;
- una sola autoridad de navegación compartida;
- no SPA;
- no segunda app;
- no DB nav config;
- 0 tablas nuevas.

### Mobile/tablet

```text
bottom launcher <=5 destinations
preferred V1: Inicio / Apps / Cuenta
safe-area
48px touch targets
no horizontal scroll
```

Tablet conserva launcher inferior; no hereda automáticamente sidebar desktop.

### Desktop PC

```text
persistent left sidebar
permission-filtered grouped modules
desktop topbar
wide content canvas
tables when useful
keyboard accessibility
```

### F24

```text
Release Readiness + Wizards + QA
```

Incluye:

```text
installer wizard
candidate wizard
responsive/accessibility QA
PHP 8.3/8.4/8.5
clean/upgrade migration
installer/catalog/auth/PDF/email/cron/uploads/permissions/UTF-8/timezone
```

No nuevas tablas productivas esperadas.

### Installer wizard

```text
0 server requirements
1 DB credentials + Test connection
2 company/administration settings
3 owner account
4 summary
5 install/migrate
6 success/login
```

Nunca crea tenants/candidates/leases/sample properties.

### Candidate wizard

```text
1 context/privacy
2 personal/contact
3 work/income
4 occupants/pets
5 references
6 documents
7 review/submit
```

Mismo candidate/token; no wizard tables.

### F25

```text
SERVER_READY ZIP + exact-artifact local rehearsal
```

El ZIP solo es SERVER_READY si instala desde cero en carpeta local limpia + BD vacía usando el wizard real.

### F26

```text
Manual Hostinger Deploy + Smoke
```

El usuario sube/extracta manualmente el ZIP aprobado y ejecuta el mismo installer. No bot/SSH/API/deploy framework obligatorio.

## UI direction

```text
Modern SaaS / Corporate Minimal Fresh
professional
trustworthy
clean
subtle depth
system sans
semantic color tokens
SVG icons
```

Default visual direction may use teal/navy/gold restrained, while branding primary remains configurable.

## External UX rules adopted

```text
mobile-first
bottom nav <=5
44–48px touch targets
8px+ touch spacing
safe-area
fixed-bar content offset
375/768/1024/1440 QA widths
contrast >=4.5:1
visible focus
reduced motion
text reflow
SVG icons, no emoji nav icons
```

## Current-shell finding

Current `resources/views/layouts/app.php` uses one bottom navigation for all viewport classes. F23 must replace this with an adaptive shell, not patch the existing bottom bar for desktop.

## Laws

```text
KISS: PASS
ZERO_NOISE: PASS
ANTIPATCH: PASS
```

## Dependency boundary

Codex F15→F20 may still be active. Do not implement F23 UI on overlapping active branches. This checkpoint is design-only until F15→F20 is audited/merged and F21→F22 sequencing is resolved.

## Next

```text
1 finish/audit/merge F15→F20
2 finalize F21/F22 SPEC/PLAN
3 retain this F23→F26 SPEC
4 build one final F21→F26 Codex master
5 no F27
```
