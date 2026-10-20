# AlquilaPro — CURRENT CHECKPOINT

Actualizado: 2026-10-20  
Propósito: **puntero operativo único de continuidad, sin ruido histórico**.

> GitHub + CI son autoridad técnica. Este archivo resume el estado vivo; PRs/commits conservan el detalle forense.

## 1. Leyes y convención operativa

- **KISS**: A → B; simple, eficiente, optimizado y eficaz; sin arquitectura innecesaria.
- **Cero Ruido**: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`.
- **Anti-parche**: `REPRODUCE → ROOT CAUSE → OWNER → DUPLICACIÓN → RED → CAUSAL RED → MINIMAL GREEN → RETIRAR SUPERSEDED → REGRESSION`.
- **Human-First / Diseño**: persona > modelo de datos; lenguaje humano; jerarquía clara; No Infinite Excel; accesibilidad, responsive y Browser QA real como gates.
- **Contrato visual aprobado (decisión explícita del owner 2026-10-19)**: el mockup `01_SOLICITUD.png` se **implementa**, no se usa como inspiración. No sustituir estética, composición, iconografía, jerarquía o densidad aprobadas por una versión "más simple". Sólo se omiten o adaptan **datos/acciones inexistentes**, justificando la excepción específica y conservando el resto del diseño.
- **LEY 5 — Fidelidad visual verificable y cero autoaprobación (2026-10-20)**: un mockup aprobado es contrato visual obligatorio. El auditor debe comparar PNG original por hash con captura auténtica del SHA servido, por región Y por geometría global (hero, filas, cards, densidad, jerarquía, iconografía, espacios, color y responsive). `MATCH` significa equivalencia **demostrada**, jamás mera existencia de captura o mejora relativa. Toda fila, tarjeta o estructura esencial ausente = `VISUAL_RED`, aunque CI o TDD estén GREEN. KISS/antirruido simplifican código, no degradan la interfaz. No inventar capacidades: acción inexistente se omite puntualmente como `GAP_FUNCIONAL`, pero no convierte un bloque visual desaparecido en PASS. Implementador no se autocertifica; auditor independiente debe intentar refutar falsos PASS y mostrar evidencia; no pedirle al owner que encuentre errores evidentes ni solicitar aprobación mientras haya RED. Aplicable a todo AlquilaPro.
- **No claim without evidence**: SHA exacto + CI para GREEN técnico; Browser real para certificación visual.
- Merge y deployment son gates separados.
- Deployment: **NOT AUTHORIZED**.
- **Hostinger sólo al final:** no preparar ni publicar previews, hPanel, DNS, bases o despliegues durante desarrollo. Instalar únicamente el sistema completo tras QA integral + UAT y autorización final separada del owner.
- Convención del owner: `continua` autoriza ejecutar el siguiente gate inequívocamente preparado —incluido merge— sólo si sigue GREEN, sin drift y dentro del alcance ya auditado.
- `checkpoint y para` = persistir/verificar checkpoint y detenerse.

## 2. Autoridad viva y base integrada

Orden operativo:

1. GitHub del repositorio canónico.
2. CI del SHA exacto.
3. este checkpoint.
4. SPEC / PLAN aprobado aplicable.
5. `AGENTS.md` + README.
6. handoffs/chats sólo como recuperación.

Repositorio canónico: `sierraglobalcompany-rgb/AlquilaPro`.

```text
CAND01_PRODUCT_BASE = 7caf82e910edec4153f5cc49dcfb404f8f372b37
MAIN_AT_PR45_OPEN = 7caf82e910edec4153f5cc49dcfb404f8f372b37
MAIN_CURRENT = REVALIDATE_LIVE_BEFORE_ACTION
PR_44 = MERGED / CLOSED
DEPLOYMENT = NOT_AUTHORIZED
```

PR #44 integró la baseline visual aprobada, la spec y el plan de implementación de Candidatos. `main` puede avanzar después por commits/merges exclusivamente documentales sin cambiar la base funcional de CAND-01; por eso este checkpoint **no fija como perpetuo** el HEAD vivo de `main`: debe revalidarse en GitHub antes de actuar.

## 3. Trabajo activo — CAND-01 / PR #45

**G3 IMPLEMENTADO — QA TÉCNICO GREEN, QA VISUAL PENDIENTE (2026-10-20).**

- HEAD de código G3 exacto: `cbe3b27ae0b7a7bffc24aed88c8b69c1d7d9bb06`.
- CI #1147, run `39381365745`: **SUCCESS PHP 8.3, 8.4 y 8.5**, cada matriz 675 tests, 7941 assertions, 0 failures/errors, 1 skipped (preexistente).
- RED previo #1138, commit `7afe0409`: 10 fallos / 1 omitida con layout 1+2 antes del cambio. GREEN rediseñó overview como fila A (1), fila B (2), fila C (3) sin inventar funcionalidad.
- Ruta existente carga datos verdaderos desde `PropertyService`; `UnitMediaService` sólo con `properties.view` y ruta privada con 403/200 verificados en suite; reparada su inyección faltante en `bootstrap/app.php`.
- Code delta: exactamente seis archivos (`app/Candidates/routes.php`, `bootstrap/app.php`, `resources/views/candidate_detail.php`, `assets/app.css`, dos tests). Sin nuevos endpoints, tablas, framework, JS, servicios paralelos ni dependencia.
- `G3_SERVED_SHA=NOT_VERIFIED`; `G3_REAL_BROWSER_SCREENSHOTS=0`; `G3_VISUAL_FIDELITY=RED_UNTIL_INDEPENDENT_REVIEW`. Faltan capturas auténticas 1448×1086, 1440×900 full-page, 768×1024, 390×844 y 375×812, y matriz adversarial de 17 regiones contra PNG exacto. `OWNER_APPROVAL=NOT_REQUESTED_WHILE_RED`.
- El compromiso documental de este texto generará nuevo HEAD; CI #1147 certifica el código anterior, **no** convierte automáticamente el nuevo SHA en GREEN.

```text
ACTIVE_BLOCK = CAND-01 / G2
PR_45 = OPEN / DRAFT / NOT_MERGED
PR_45_BRANCH = impl/cand-01-shell-overview-20261017
PR_45_G2_LAST_VERIFIED_HEAD = ce3e536ee2c3d2a6432a2c65aac71076612ab7c9
PR_45_HEAD_AFTER_RECONCILIATION = REVALIDATE_LIVE
PR_45_MERGE = NO
CAND02 = NOT_STARTED
```

G2 implementó la composición visual aprobada de `01_SOLICITUD.png`: sidebar y topbar, hero, pestañas, panel contextual verde e identidad/fechas factuales. `View`, `candidate_detail.php`, `resources/views/layouts/app.php`, un parcial SVG y `assets/app.css` siguen siendo owners existentes. Una sola sección SSR activa por `?section=`; sin SPA, servicios, nuevas rutas, JS, dependencias ni migraciones. Los siete cuerpos restantes no fueron rediseñados: CAND-02 a CAND-08 siguen pendientes.

**El merge de mantenimiento de rama sólo resuelve la divergencia documental**: conservar blobs G2 de código y pruebas, documentos aprobados de `main` y este checkpoint convergido. Los SHA de pruebas anteriores certifican G2 antes de integrar `main`, no automáticamente el nuevo merge HEAD.

## 4. Evidencia exacta de CAND-01 G2

- G1 rechazado visualmente por el owner: `a186b0ab765a9547def7501905486f18159c6982`, pese a GREEN técnico (CI #1131).
- G2 TDD: RED composición `f864c06` (9 tests/3 fallos causales), GREEN `975a54f`. Segundo RED de ausencia factual `df6b55f`, GREEN `1459ef2`.
- G2 HEAD certificado antes de la conciliación: `ce3e536ee2c3d2a6432a2c65aac71076612ab7c9`.
- Full local G2 PHP 8.5: **672 tests, 7908 assertions, 0 failures, 0 errors, 1 skipped**. CI #1135, run `39367525206`: **SUCCESS PHP 8.3/8.4/8.5 para el HEAD exacto G2**.
- Browser QA local real: SHA servido comprobado por Codex, 375×812, 390×844, 768×1024, 1440×900; accesibilidad de teclado/foco, navegación, permisos, fallback, consola, red y sin overflow en pruebas observadas. Paquete G2: 50 PNG totales (mockup, antes y después, regresiones), no 50 capturas nuevas finales distintas.
- **RETRACTACIÓN de la evaluación anterior**: «G2 PASS, 14/17 MATCH, sin G3» fue un **falso positivo**. Reauditoría 2026-10-20 del mockup original `01_SOLICITUD.png` SHA256 `8f7b16a46cb9decf905c218a35e5535aa3acad29041f02840a93c5228c103d15` vs G2 `1440/overview_viewport.png`: **target 6 superficies (1+2+3), G2 sólo 3 (1+2)**, tercera fila ausente, resumen factual menos denso, topbar/hero diferentes. El ítem 17 del ledger original dice que MATCH significa «evidencia lado a lado», no coincidencia visual; 14/17 no era métrica válida. `G2_VISUAL_FIDELITY=RED`, sin aceptación del propietario. Auditoría externa: `ALQUILAPRO_LEY_5_FIDELIDAD_VISUAL_VERIFICABLE_Y_REAUDITORIA_G2_2026-10-20.md`, matriz recalificada de 17 regiones. No confundir GREEN técnico con fidelidad.
- Las 889 capturas anteriores corresponden al diagnóstico transversal del ERP, no a la certificación final de G2. Referencia externa: `ALQUILAPRO_G2_FIDELIDAD_2026-10-19.zip`, `ALQUILAPRO_G2_FINAL_REPORT_2026-10-19.md`.
- Merge doc-only PR #51 se integró en main `243dea9c821645dd554cc5fdd66cb6c9a0d4f2ce` después de CI #1132 GREEN.

## 5. Gate visual y decisión obligatoria

**Situación G3:** La implementación estructural 1+2+3 pasó las pruebas técnicas (CI #1147) pero **no ha sido observada en navegador**. Se conserva como `VISUAL_RED/NOT_VERIFIED` por Ley 5; no aceptar ni fusionar. Las limitaciones reales (búsqueda global, campana, acciones de editar/mensaje/evaluar) son `GAP_FUNCIONAL`, no PASS visual automático. El auditor debe refutar posibles coincidencias del nuevo render y documentar cada una de las 17 regiones sin reutilizar el antiguo ledger G2.

**Ley 5 activa.** Mockup aprobado = composición, jerarquía, densidad, iconografía y layout vinculantes. Omisiones funcionales individuales sólo están justificadas por ausencia real de backend, pero una fila/carta estructural desaparecida deja RED. **El dictamen anterior favorable está RETRACTADO**. El propietario NO tiene que reaprobar el mockup ni descubrir nuestras fallas: se corrigen antes de solicitar aceptación final.

```text
G2_TECHNICAL_QA = GREEN_CI_1135_PHP_8_3_8_4_8_5
PR45_RECONCILED_HEAD = 265532abdf41196e9aab81f6e743b857f490c087
PR45_RECONCILED_CI = 1136_GREEN_PHP_8_3_8_4_8_5
G2_BROWSER_QA_FUNCTIONAL = GREEN_CODEX_WITH_LOCAL_EVIDENCE
G2_VISUAL_FIDELITY = RED_REAUDIT_STRUCTURAL_DIFFERENCES
G2_ASSISTANT_PREVIOUS_VISUAL_PASS = RETRACTED
OWNER_VISUAL_APPROVAL = NOT_REQUESTED_WHILE_VISUAL_RED
HUMAN_FIRST_FINAL = NOT_CERTIFIED
PR45_MERGE = NO
DEPLOYMENT = NOT_AUTHORIZED
```

La nueva actualización documental produce un SHA nuevo: CI debe ejecutarse sobre ese SHA. No abrir CAND02, no integrar PR45, no usar Hostinger.

## 6. Diseño aprobado de Candidatos

Arquitectura:

```text
GET /admin/candidates/{id}?section=<key>
```

Keys:

```text
overview
work
household
references
documents
screening
contracts
manage
```

Regla de no redundancia:

```text
Nombre + estado + unidad -> shell
Identidad/contacto       -> Solicitud
Trabajo                  -> Información laboral
Hogar/mascotas           -> Ocupantes y mascotas
Referencias              -> Referencias
Archivos                 -> Documentos
Aseguradora              -> Estudio de aseguradora
Contratos                -> Contratos
Acciones sensibles       -> Administrar
```

Mockups mandan en arte/jerarquía/densidad; backend + permisos mandan en funciones. No inventar scores, ratings, mensajería, firmas, historial, pre-aprobación, datos estructurados de ocupantes/mascotas ni acciones inexistentes.

## 7. Spec / plan — estado documental

La spec `docs/superpowers/specs/2026-10-17-candidates-human-first-redesign.md` fue **aprobada por el owner el 2026-10-17** y su plan posterior ya registra esa aprobación.

El drift puramente documental del header/final de la spec fue cerrado por PR #47, sin modificar el contrato técnico/UX.

```text
SPEC_EFFECTIVE_STATUS = OWNER_APPROVED
SPEC_HEADER_DRIFT = CLOSED
SPEC_STATUS_CONVERGENCE_PR = 47_MERGED
SPEC_STATUS_CI = 1124_GREEN_8_3_8_4_8_5
TECHNICAL_CONTRACT_CHANGE = NO
```

No reabrir este drift salvo contradicción nueva y demostrable entre la spec, el plan y la verdad viva.

## 8. PR #43 — evidencia supersedida

```text
PR_43 = OPEN / DRAFT / DO_NOT_MERGE
PR_43_HEAD = e9594ef1e431eff2f3fc3193bb16a4533a1820af
HF_P1D_TECHNICAL_GREEN = YES
HF_P1D_PRODUCT_DESIGN_APPROVAL = REJECTED_FOR_DETAILS_STACK
```

Preservar su evidencia técnica/Browser histórica. **No reutilizar la solución de siete `<details>` como dirección visual.** Lección: colapsar un Excel no lo vuelve Human-First.

## 9. R3 / PR #33

```text
PR_33 = OPEN / DRAFT / FROZEN / NOT_MERGED
PR_33_HEAD = 845295c777151216655b7a7866bc0dc9d93f3e2e
R3_FUNCTIONAL = GREEN_HISTORICAL
R3_BROWSER_QA = BLOCKED / NOT_CERTIFIED
R4 = NOT_STARTED
```

No usar Browser QA de otra fase para certificar R3. No descongelar R3 por inferencia.

## 10. Gobernanza pendiente

Gaps conocidos en `main`:

```text
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
design-system/alquilapro/MASTER.md
.github/pull_request_template.md
.github/skills/erp-human-first-ui-ux/SKILL.md
```

Master Human-First V2.1 exacto respaldado externamente:

```text
SHA256 = 55265c92b7a49a4ac2ac976afb6789074b5f95ff5760f6d08b7b1d0e817f6940
```

No resumir, truncar ni fragmentar el Master para evadir bloqueos de seguridad.

`main` fue observado sin branch protection/required checks activos; no afirmar lo contrario sin nueva evidencia.

## 11. Próximos gates exactos

1. Código G3 implementado en PR #45, RED #1138 y CI #1147 GREEN sobre `cbe3b27ae0b7a7bffc24aed88c8b69c1d7d9bb06`. Persistir y verificar este checkpoint en HEAD posterior.
2. **Browser QA REAL de G3**: checkout limpio en máquina autorizada, verificar SHA realmente servido, demo aislada; capturas del viewport completo y full-page a 1448×1086 / 1440×900 / 768×1024 / 390×844 / 375×812, consola/red y flujos funcionales.
3. **Auditoría adversarial Ley 5**: comparar PNG `01_SOLICITUD.png` con screenshots G3, 17 regiones, geometría, densidad, jerarquía, filas 1+2+3, privacidad viewer/admin, estado, foco/teclado, no fake CTAs; `RED_ESENCIAL` / `GAP_FUNCIONAL` estructural veta PASS aunque CI GREEN.
4. Si hay RED: reproducir, causa raíz, RED→GREEN mínimo, regresión, CI y capturas nuevas. **No más informes favorables sin evidencia visual original**.
5. Sólo con visual completo PASS, CI HEAD exacto, revisión independiente limpia y aceptación explícita owner: considerar Ready/merge PR45. Después main/CI/checkpoint y CAND02.

R3/PR33 FROZEN, PR43 SUPERSEDED, Hostinger reservado para fin del ERP y UAT integral.

## 12. Recuperación rápida

1. Revalidar `main` y HEAD de PR #45 (tras el commit documental el HEAD difiere del commit de código G3).
2. Cinco leyes: KISS, cero ruido, antiparches, Human-First y Ley 5 de fidelidad verificable.
3. RED #1138 / GREEN #1147 de G3; código `cbe3b27ae0b7a7bffc24aed88c8b69c1d7d9bb06` sin screenshot G3 real todavía. No confundir CI técnico con fidelidad.
4. Leer el mockup original `01_SOLICITUD.png`, 17 regiones y `ALQUILAPRO_G3_CODEX_LUNA_LEY5_REPARACION_ESTRUCTURAL_2026-10-20.md` como contrato de Browser QA.
5. PR45 Draft/NO MERGE, CAND02 no iniciado, R3 congelado, Hostinger sólo final.

## 13. Estado resumido

```text
REPO = sierraglobalcompany-rgb/AlquilaPro
MAIN_CURRENT = REVALIDATE_LIVE
ACTIVE_WORK = CAND01_G3_VISUAL_QA_PENDING
G3_CODE_HEAD = cbe3b27ae0b7a7bffc24aed88c8b69c1d7d9bb06
PR45_NEW_DOC_HEAD = REVALIDATE_LIVE
G3_TECHNICAL = GREEN_CI1147_PHP_8_3_8_4_8_5
G3_TESTS = 675_TESTS_7941_ASSERTIONS_1_SKIP
G3_VISUAL_STRUCTURE_IMPLEMENTED = 1_PLUS_2_PLUS_3_IN_SSR
G3_SERVED_SHA = NOT_VERIFIED
G3_BROWSER_SCREENSHOTS = 0
G3_VISUAL_FIDELITY = RED_NOT_VERIFIED
G3_INDEPENDENT_REVIEW = PENDING
OWNER_VISUAL_APPROVAL = NOT_REQUESTED_WHILE_RED
HUMAN_FIRST_FINAL = NOT_CERTIFIED
LAW_5 = ACTIVE
PR45 = OPEN_DRAFT_NO_MERGE
CAND02 = NOT_STARTED
PR43 = SUPERSEDED
PR33_R3 = FROZEN
DEPLOYMENT = NOT_AUTHORIZED
HOSTINGER = FINAL_ONLY_AFTER_SYSTEM_QA_AND_OWNER_UAT
NEXT_SAFE_ACTION = VERIFY_NEW_SHA_CI_THEN_G3_REAL_BROWSER_ADVERSARIAL_AUDIT
```
