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
- Inspección visual independiente del asistente: **favorable / PASS de fidelidad por regiones**. `OWNER_VISUAL_APPROVAL=PENDING`; no es aprobación del propietario ni certificación Human-First final. Native zoom 200%, dispositivos físicos y WCAG integral no certificados.
- Las 889 capturas anteriores corresponden al diagnóstico transversal del ERP, no a la certificación final de G2. Referencia externa: `ALQUILAPRO_G2_FIDELIDAD_2026-10-19.zip`, `ALQUILAPRO_G2_FINAL_REPORT_2026-10-19.md`.
- Merge doc-only PR #51 se integró en main `243dea9c821645dd554cc5fdd66cb6c9a0d4f2ce` después de CI #1132 GREEN.

## 5. Gate visual, integración y decisión del owner

**Regla absoluta:** mockup aprobado = contrato visual vinculante. Su composición, densidad, jerarquía, colores e iconografía se implementan. Sólo datos/acciones inexistentes pueden omitirse y cada diferencia requiere justificación específica. G1 fue rechazado por no cumplirla; G2 es favorable en inspección independiente, pero **espera la decisión visual explícita del owner**.

```text
G2_TECHNICAL_QA_PRE_MERGE = GREEN_AT_ce3e536ee2c3
G2_BROWSER_QA_PRE_MERGE = GREEN_CODEX_WITH_LOCAL_EVIDENCE
G2_ASSISTANT_VISUAL_REVIEW = FAVORABLE
OWNER_VISUAL_APPROVAL = PENDING
HUMAN_FIRST_FINAL = NOT_CERTIFIED
PR45_MERGE = NO
DEPLOYMENT = NOT_AUTHORIZED
```

La conciliación de GitHub debe mantener estas evidencias y ejecutar CI **nuevo** sobre el nuevo HEAD antes de cualquier gate de merge. Una mera resolución de conflictos no constituye aceptación del diseño. La revisión del propietario sigue pendiente; no pasar a CAND-02 sin cerrar el gate CAND-01.

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

1. Integrar únicamente la convergencia documental del PR #51: **CLOSED (merge `243dea9c`)**.
2. Conciliar PR #45 con main mediante merge de dos padres, sin cambiar los 14 blobs funcionales de G2; resolver checkpoint contra `main` y verificar alcance/diff + HEAD.
3. Ejecutar CI PHP 8.3/8.4/8.5 **sobre el HEAD post-conciliación**. Ante fallos, reproducir causa raíz, RED causal, GREEN mínimo y nuevo CI; verificar si un cambio funcional invalida capturas y repetirlas cuando aplique.
4. Obtener aceptación visual explícita del propietario para G2. Informe del asistente favorable **no reemplaza** su gate. Si rechaza una región, corregir sólo esa región y repetir sus pruebas/evidencias.
5. Sólo con HEAD exacto GREEN, reviews limpios, Browser QA aplicable y `HUMAN_FIRST_FINAL=CERTIFIED_BY_OWNER`: evaluar merger de PR #45. No merge anticipado.
6. Verificar main post-merge; checkpoint; recién entonces CAND-02.

Microbloques siguientes: CAND-02 trabajo; CAND-03 hogar/mascotas; CAND-04 referencias; CAND-05 documentos; CAND-06 aseguradora; CAND-07 contratos; CAND-08 administrar; CAND-09 lista. Cada uno: `TRUTH → UX CONTRACT → RED → GREEN → REGRESSION → CI → BROWSER QA → OWNER GATE → MERGE`. R3/PR #33 FROZEN, PR #43 SUPERSEDED; Hostinger **sólo al final de todo el proyecto**.

## 12. Recuperación rápida

1. Revalidar `main` y PR #45 vivo en GitHub (después de este merge de reconciliación el HEAD será nuevo).
2. Leer este único checkpoint, la spec y baseline aprobados; los handoffs son referencia, no reemplazan la autoridad.
3. Comprobar G2 `ce3e536ee2c3d2a6432a2c65aac71076612ab7c9`, CI #1135 y nuevas ejecuciones del merge HEAD; no reutilizar CI de otro SHA como pase.
4. Leer el dictamen visual G2 y el paquete de 50 PNG; owner acceptance sigue pendiente hasta que el propietario la emita.
5. Cuatro leyes: KISS, eliminar ruido, no parches, fidelidad a mockup aprobado.
6. PR #45 Draft/no merge, CAND-02 no iniciado, R3 congelado, Hostinger sólo final.

## 13. Estado resumido

```text
REPO = sierraglobalcompany-rgb/AlquilaPro
MAIN_CURRENT = REVALIDATE_LIVE
ACTIVE_WORK = CAND-01_G2
PR_45_LAST_CERTIFIED_G2_HEAD = ce3e536ee2c3d2a6432a2c65aac71076612ab7c9
PR_45_NEW_MERGE_HEAD = REVALIDATE_LIVE
G2_CI_EXACT_SHA = 1135_GREEN_PHP_8_3_8_4_8_5
G2_LOCAL_FULL = 672_TESTS_7908_ASSERTIONS_1_SKIP
POST_RECONCILIATION_CI = REQUIRED_ON_NEW_SHA
G2_BROWSER_QA = GREEN_PRE_RECONCILIATION
G2_VISUAL_ASSISTANT = FAVORABLE
G2_OWNER_VISUAL_APPROVAL = PENDING
HUMAN_FIRST_FINAL = NOT_CERTIFIED
PR45 = OPEN_DRAFT_NO_MERGE
CAND02 = NOT_STARTED
PR43 = SUPERSEDED_DRAFT
PR33_R3 = FROZEN
DEPLOYMENT = NOT_AUTHORIZED
HOSTINGER = FINAL_ONLY_AFTER_SYSTEM_QA_AND_OWNER_UAT
NEXT_SAFE_ACTION = CI_NEW_HEAD_THEN_OWNER_VISUAL_GATE
```
