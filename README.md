# Alquila Pro

Aplicación web mobile-first y autoinstalable para administrar arrendamientos de vivienda y parqueaderos, con catálogo público y operación privada para administración e inquilinos.

**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + SSR HTML + CSS/JS mínimo  
**Destino:** Hostinger, HTTPS, subdominio o subcarpeta.  

## Estado canónico actual

```text
F0–F14: CLOSED / VERIFIED
F1–F14: MERGED
F14_MERGE_SHA: c58ee5e660f51e873546f03a578fde6b5778ba19

F15–F20:
SPEC APPROVED
PLANS COMPLETE
CODEX MASTER V4 READY
PRODUCT IMPLEMENTATION NOT YET VISIBLE IN GITHUB AT LAST AUDIT

CURRENT_MAIN_BEFORE_THIS_README_REFRESH:
ddb24048326e3fd67df919f1bd829fac939d8861
```

Codex puede estar trabajando localmente F15→F20. Antes de asumir estado, verificar siempre `main`, ramas `impl/f15...` a `impl/f20...`, PR abiertos y CI reales.

---

# 1. Punto de entrada obligatorio

Antes de tocar código o continuar un chat:

1. `README.md`;
2. `AGENTS.md`;
3. último handoff forense `docs/checkpoints/ALQUILAPRO_HANDOFF_FORENSIC_*`;
4. `docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`;
5. `docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`;
6. último checkpoint `*_MERGED_*` de la fase integrada;
7. checkpoint de diseño/ejecución de la fase activa;
8. SPEC/PLAN de la fase activa;
9. PR/CI/código real de GitHub.

La realidad empírica manda:

```text
GitHub/code/tests/CI > memoria del chat > documentación histórica
```

La documentación define intención y contratos; GitHub define el estado factual.

---

# 2. Leyes supremas

## Ley 1 — KISS / arquitectura simple

Elegir la solución correcta con el mínimo razonable de tablas, clases, estados, rutas, capas y dependencias.

Objetivo: simple, eficiente, optimizado, eficaz, auditable y mantenible. Nada de arquitectura compleja “por si acaso”.

## Ley 2 — Cero Ruido / Convergencia

Orden obligatorio antes de agregar:

```text
ELIMINAR
→ UNIR / CONVERGER
→ SIMPLIFICAR
→ MEJORAR / OPTIMIZAR
→ AGREGAR
```

Eliminar solo después de demostrar que referencias, autoridad, reemplazo y regresión quedan cubiertos.

## Ley 3 — Antiparches

Ante un fallo:

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

Pregunta obligatoria:

> ¿Dónde debería vivir esta regla si AlquilaPro hubiese sido diseñado correctamente desde cero?

No se aceptan compatibility fallbacks, optional dependencies para conservar callers viejos, segundos writers ni parche lateral sobre parche lateral.

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

No merge sin autorización del usuario. `continua` autoriza el siguiente gate previsto cuando el contexto ya lo deja inequívoco.

---

# 4. Roles

- **ChatGPT/Sol:** producto, SPEC, arquitectura, planes, auditoría forense, revisión de GitHub/CI, checkpoints y continuidad.
- **Codex:** implementación concreta, TDD focal, refactor controlado, Git y PR cuando aporta valor.
- **Antigravity:** QA/auditoría cruzada cuando aporte valor.

Cuando se use Codex, el usuario prefiere recibir **un Markdown descargable**, no un prompt largo pegado en el chat. El contrato de Codex debe ser específico, guiado, eficiente, con límites arquitectónicos claros pero suficiente autonomía para resolver detalles locales sin bloquearse.

---

# 5. Estado cerrado F12→F14

Autoridad: `docs/checkpoints/F12_F14_BATCH_CHECKPOINT_2026-10-14.md` y `docs/checkpoints/F14_MERGED_2026-10-14.md`.

```text
F12 PR #15 MERGED
merge: 23cf7576c95fca5e55825d8b5ffd5903bf3d3149
CI: 38519741952
PHP 8.3/8.4/8.5: 406 tests / 4,193 assertions

F13 PR #16 MERGED
security repair: b11705cb387129c51090bbef0c3658c4843f940d
merge: 57911302bd4e97e76b1e0fa6dc71c8ef24372453
CI: 38553176872
PHP 8.3/8.4/8.5: 431 tests / 4,451 assertions

F14 PR #17 MERGED
merge: c58ee5e660f51e873546f03a578fde6b5778ba19
CI: 38553704399
PHP 8.3/8.4/8.5: 443 tests / 4,604 assertions
```

F13-SEC-01 está cerrado: lectura privada bounded antes de cargar bytes, validación `filesize/fstat`, MIME/SHA sobre los mismos bytes y fail-closed.

---

# 6. Bloque activo F15→F20

Autoridades:

```text
docs/checkpoints/F15_F20_DESIGN_CHECKPOINT_2026-10-14.md
docs/specs/F15_F20_SPEC_BATCH_V1_2026-10-14.md
docs/specs/F20_PRODUCT_DECISION_2026-10-14.md
docs/specs/F20_MEMBERSHIPS_SERVICES_SPEC.md
docs/superpowers/plans/2026-10-14-f15-f20-codex-master.md
```

Presupuestos congelados:

```text
F15: 1 maintenance_visits + 1 VisitService + 0 new permissions/deps
F16: 1 notification_jobs + 1 NotificationService + PHPMailer ^7.1 only
F17: 1 whatsapp_actions + 1 WhatsAppService + whatsapp.use
F18: 1 documents + 1 DocumentService + documents.view/manage
F19: 1 community_incidents + 1 CommunityService + community.view/manage + reuse F18
F20: 2 tables digital_services/service_memberships + 1 MembershipService + memberships.view/manage
```

F20 regla final:

```text
tenant accepts service
→ membership persists
→ NEVER automatic F9 charge

if paid:
admin chooses exactly:
CREATE / Crear cargo
SKIP / No crear cargo

CREATE → FinanceService::createManualCharge(type='service')
SKIP   → terminal no-charge decision
```

Master Codex canónico: **V4 FINAL LEAN-FAST / AUDITED 10 PASSES**.

Mecánica:

```text
stacked branches F15→F20
one live checkpoint
checkpoint after each Task
focused TDD
one fresh review per phase
CI non-blocking between phases
no merges by Codex
no F21
```

---

# 7. Roadmap restante

```text
F15 Agenda + visitas
F16 Recordatorios + cron + email
F17 WhatsApp asistido
F18 Documentos + manual + inventarios
F19 Convivencia + contenido legal
F20 Membresías / servicios digitales
F21 Bono + liquidación
F22 Seguridad + privacidad
F23 Dashboard final
F24 QA PHP 8.3 / 8.4 / 8.5
F25 ALQUILA_PRO_V1_SERVER_READY.zip
F26 Hostinger + smoke test
```

No adelantar F21+ dentro del batch F15→F20.

---

# 8. Riesgos que se tratan con especial cuidado

- carreras/TOCTOU: lock de la autoridad canónica antes de mutar;
- archivos privados: cap físico antes de leer, bounded read, MIME/hash/tamaño, fail-closed;
- tenant ownership: `LeaseService::findForUser()` antes de lookup lateral;
- finanzas: `FinanceService` es el único writer de cargos;
- auth: un solo login/session/CSRF;
- CI PR: distinguir PR HEAD del synthetic merge-ref probado;
- Product Head vs Closure Head: closure documental no debe alterar producto;
- no fallback oculto para preservar tests/callers viejos;
- no duplicar documentos, notificaciones, media ni estados;
- no forzar CI redundante si el árbol probado es demostrablemente idéntico;
- no full suites locales repetitivas sin riesgo distinto que cubrir.

---

# 9. Recuperación si se pierde el chat

```text
1 README.md
2 AGENTS.md
3 último ALQUILAPRO_HANDOFF_FORENSIC_*.md
4 F14_MERGED checkpoint
5 F15_F20_DESIGN_CHECKPOINT
6 F15_F20 Codex master V4
7 live F15_F20_BATCH_CHECKPOINT si existe
8 GitHub main/branches/PR/CI reales
```

No reimplementar fases cerradas sin evidencia de defecto o una nueva SPEC aprobada.
