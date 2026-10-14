# Alquila Pro

Aplicación web autoinstalable para administrar arrendamientos de vivienda y parqueaderos, con catálogo público, operación privada para administración/inquilinos y UX adaptativa por dispositivo.

**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + SSR HTML + CSS/JS mínimo  
**Destino final:** Hostinger sobre HTTPS; primero se certifica completamente en local.

## Estado canónico actual

```text
F0→F20: CLOSED / VERIFIED / MERGED
MAIN_PRODUCT_BASE: 2884f41b145f912f0137dc498a5aef9d4fb24768

F21: SPEC + PLAN READY / implementation not started
F22: SPEC + PLAN READY / implementation not started
F23→F26: SPEC/PLANS READY / implementation not started
F27: DOES NOT EXIST
```

Cierre canónico F15→F20:

```text
docs/checkpoints/F15_F20_MERGED_FINAL_2026-10-14.md
```

---

# 1. Punto de entrada obligatorio

Antes de tocar código o continuar un chat:

1. `README.md`;
2. `AGENTS.md`;
3. último checkpoint `*_MERGED_FINAL_*` o equivalente;
4. SPEC/PLAN de la fase activa;
5. código, tests, PR y CI reales de GitHub.

Autoridad factual:

```text
GitHub/code/tests/CI
> checkpoint canónico
> SPEC/PLAN
> handoff
> memoria de chat
```

No reimplementar trabajo certificado sin evidencia de regresión o una nueva SPEC aprobada.

---

# 2. Leyes supremas

## Ley 1 — KISS / arquitectura simple

La solución correcta debe ser simple, eficiente, optimizada, eficaz, auditable y mantenible, con el mínimo razonable de tablas, clases, estados, rutas, capas, dependencias y fuentes de verdad.

Ir de A → B, no de A → C → D → E → B.

## Ley 2 — Cero Ruido / Convergencia

```text
ELIMINAR
→ UNIR / CONVERGER
→ SIMPLIFICAR
→ MEJORAR / OPTIMIZAR
→ AGREGAR
```

Antes de eliminar:

```text
REFERENCIAS → AUTORIDAD → REEMPLAZO → TEST → ELIMINAR → REGRESIÓN/CI
```

La historia útil se conserva en checkpoints/PR/CI; no debe duplicarse como segunda autoridad viva.

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

Pregunta obligatoria ante un bug: **¿dónde viviría esta regla si el sistema se diseñara correctamente desde cero?**

No crear compatibility fallbacks, segundos writers, optional dependencies para callers viejos ni lógica lateral si puede corregirse el owner.

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

`continua` autoriza el siguiente gate cuando el contexto ya lo deja inequívoco, incluidos merges secuenciales previamente preparados y auditados.

No saltar CI exacta del merge-ref cuando cambie la base de una PR.

---

# 4. Roles y forma de trabajo

- **ChatGPT/Sol:** producto, SPEC, arquitectura, UX, planes, auditoría, GitHub, QA y continuidad.
- **Codex:** implementación concreta TDD, refactor controlado y certificación local cuando aporte valor.
- **Antigravity / revisión independiente:** QA o revisión adicional cuando aporte valor real.

Cuando se delegue a Codex:

- un Markdown autocontenido por lote aprobado;
- TDD focal;
- checkpoint por tarea/fase;
- no loops;
- no rediseñar decisiones congeladas;
- no merge fuera del gate autorizado.

FAST-KISS:

```text
focused RED/GREEN
→ integrated checks
→ fresh review
→ one repair pass
→ affected focal
→ one exact PR CI matrix
```

Evitar full suites repetidas o CI equivalentes sin una razón nueva.

---

# 5. Estado integrado F15→F20

Secuencia final:

```text
F15 PR #18 → merge cfab94ce1a5b3a5bdd0f8a73cd49da07319a7915
F16 PR #19 → merge 01d8cda30f6a883954b42ff6538b534c0c91e768
F17 PR #20 → merge cfb57fc349a6e84da63282604c5e1a5f170e5242
F18 PR #21 → merge 9a9d3f6ab36b929e911b265a9e50a5a554410527
F19 PR #22 → merge 5166e3ead05014682a71b160a41189dac7aeffe4
F20 PR #23 → merge 2884f41b145f912f0137dc498a5aef9d4fb24768
```

CI de integración exacta:

```text
F15 #910 PASS PHP 8.3 / 8.4 / 8.5
F16 #911 PASS PHP 8.3 / 8.4 / 8.5
F17 #912 PASS PHP 8.3 / 8.4 / 8.5
F18 #913 PASS PHP 8.3 / 8.4 / 8.5
F19 #914 PASS PHP 8.3 / 8.4 / 8.5
F20 #915 PASS PHP 8.3 / 8.4 / 8.5
```

Presupuesto arquitectónico integrado:

```text
F15  maintenance_visits + VisitService
F16  notification_jobs + NotificationService + PHPMailer
F17  whatsapp_actions + WhatsAppService + whatsapp.use
F18  documents + DocumentService + documents.view/manage
F19  community_incidents + CommunityService + reutilización de documents
F20  digital_services + service_memberships + MembershipService
```

Migraciones canónicas actuales:

```text
015_create_maintenance_visits.sql
016_create_notification_jobs.sql
017_create_whatsapp_actions.sql
018_create_documents.sql
019_create_community_incidents.sql
020_create_digital_services_memberships.sql
```

No existe una segunda autoridad financiera, documental, de notificaciones, autenticación o almacenamiento.

---

# 6. Contratos que no deben romperse

## Tenant / ownership

```text
current user
→ LeaseService::findForUser()
→ 404 si no pertenece
→ lookup lateral del módulo
```

No consultar documentos, comunidad, membresías o finanzas de un lease antes del ownership gate.

## Finanzas

`FinanceService` es el único writer de cargos.

No crear ledger, balance writer, charge writer paralelo ni cargo automático desde módulos laterales.

## F16 notificaciones

`NotificationService` + `notification_jobs` es el único motor automático.

No agregar otro scheduler/worker/queue sin nueva SPEC demostrada.

## F17 WhatsApp

WhatsApp V1 es asistido:

- prepara snapshot;
- abre `https://wa.me`;
- registra actor/hora de apertura;
- no inventa `sent`, `delivered`, `read` ni provider IDs.

## F18 documentos

`DocumentService` + `documents` es la autoridad documental genérica.

Private storage debe conservar:

- path seguro;
- no symlink;
- cap físico antes de lectura;
- bounded read;
- tamaño/MIME/SHA sobre los mismos bytes;
- fail-closed.

## F20 membresías

```text
tenant acepta/renueva
→ membership persiste snapshot
→ NO auto-charge
```

Servicio pago:

```text
admin CREATE → FinanceService crea cargo
admin SKIP   → decisión terminal sin cargo
```

Reglas adicionales:

- términos obsoletos → rechazo;
- lock order para decisión: lease → membership;
- lease terminado → no aceptar/renovar;
- `MembershipService::listForLease()` es la API canónica para consumidores posteriores.

---

# 7. Deuda explícita no bloqueante

No convertir estas deudas en sistemas paralelos:

1. F16: scans de recordatorios sin paginación.
2. F16: retención de recipient name/email; F22 ya posee la redacción terminal a 90 días reutilizando cron existente.
3. F17: latest 100 sin paginación.
4. F17: nombres de actores no renderizados.
5. F18: symlink local no probado en Windows; CI Linux pasó.
6. F19: lista administrativa limitada a 200 incidentes sin paginación.
7. `.phpunit.cache/` y `app/graphify-out/`: scratch; no integrar sin demostrar productor, referencia y necesidad.
8. Protección/gobernanza real de `main`: F22 debe verificarla; si no existe, registrar `GOVERNANCE_ACTION_REQUIRED` sin fingir protección.

---

# 8. F21 — siguiente fase

Objetivo: bono de buena conducta + liquidación contractual.

Autoridades:

```text
docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md
docs/plans/F21_BONUS_LIQUIDATION_IMPLEMENTATION_PLAN.md
```

Antes de implementar:

1. PRE-KISS contra el contrato real F20 ya integrado.
2. No crear `hasPendingPaidChargeDecision()` ni API paralela; derivar el blocker desde `MembershipService::listForLease()` + `charge_decision_required`.
3. Resolver un único nombre canónico para el feature flag del bono usando la convención existente `ALQUILAPRO_*` si el código/configuración la confirma.
4. Reducir dependencias de `TerminationService` a las estrictamente necesarias.
5. Evitar transacciones anidadas y mantener orden de locks determinista.
6. `LeaseService::end()` sigue siendo el único writer del lifecycle del lease.
7. No crear payout/provider/wallet/deposit/escrow/ledger/history/status-history.

Presupuesto F21:

```text
máx. 2 tablas: good_conduct_bonuses, lease_liquidations
máx. 1 clase: TerminationService
0 permisos nuevos
0 dependencias runtime nuevas
0 rutas públicas
```

---

# 9. F22→F26

```text
F22 Seguridad + privacidad + audit log
F23 Dashboard + Adaptive UX Final
F24 Release Readiness + candidate/installer wizards + QA
F25 ALQUILA_PRO_V1_SERVER_READY.zip + rehearsal del artefacto exacto
F26 Hostinger manual deploy + smoke
```

No existe F27.

F23–F26 no deben adelantarse para resolver problemas de F21/F22.

F25 solo puede construir el ZIP desde `main` canónico post-F24, después de merges autorizados y certificados.

F26 despliega exactamente el artefacto aprobado por F25; si no hay acceso operativo del agente, el estado honesto es `DEPLOYMENT_HANDOFF_READY`.

---

# 10. Recovery

```text
README
→ AGENTS
→ docs/checkpoints/F15_F20_MERGED_FINAL_2026-10-14.md
→ docs/specs/F21_F22_SPEC_BATCH_V1_2026-10-14.md
→ docs/plans/F21_BONUS_LIQUIDATION_IMPLEMENTATION_PLAN.md
→ docs/plans/F22_SECURITY_PRIVACY_AUDIT_IMPLEMENTATION_PLAN.md
→ docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md
→ GitHub real
```

Punto de reanudación:

```text
main@2884f41b145f912f0137dc498a5aef9d4fb24768
→ canonical docs convergence
→ PRE-KISS F21
→ RED F21 Task 1
```
