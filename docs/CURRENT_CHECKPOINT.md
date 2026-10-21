# AlquilaPro — CURRENT CHECKPOINT

**Fecha de corte:** 2026-10-21. **Rol:** única referencia operativa del estado actual. El historial completo de G1/G2/G3 queda en Git y evidencias del PR; no interpretar commits históricos como HEAD.

## 1. Estado comprobado y autoridad

| Campo | Estado |
| --- | --- |
| Repositorio | `sierraglobalcompany-rgb/AlquilaPro` |
| Rama activa | `impl/cand-01-shell-overview-20261017` |
| PR | [#45](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/45), OPEN / DRAFT / NOT_MERGED |
| `main` observado | `243dea9c821645dd554cc5fdd66cb6c9a0d4f2ce`; volver a comprobar antes de escribir |
| Último HEAD de producto ya verificado | `fbf2bf48aa18fb988c3c9ff96a003e7d80528a56` |
| CI de ese HEAD de producto | [#1159](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39536302708): SUCCESS PHP 8.3/8.4/8.5, 680 tests por runtime, 1 skipped, 0 failures |
| Aserciones CI #1159 | PHP 8.3 = 7.979; 8.4 = 7.980; 8.5 = 7.980 |
| Fase | CAND-01 / G3 — Solicitud, seis tarjetas en tres filas |
| Browser QA exacto HEAD `fbf2bf48` | **PENDIENTE**; no atribuir a este HEAD capturas anteriores |
| Fidelidad visual de CAND-01 | **RED_ESENCIAL / no certificada** |
| Owner UAT / merge / despliegue | NO autorizado |
| Otros frentes | CAND-02 NOT_STARTED; R3/PR #33 FROZEN; PR #43 SUPERSEDED, DRAFT |

**Importante:** este checkpoint es un cambio documental posterior al HEAD de producto `fbf2bf48`. Su commit genera un HEAD nuevo. La CI #1159 sólo acredita el commit previo: verificar CI del nuevo HEAD antes de cualquier afirmación de GREEN exacto. La evidencia visual de `17e7bce` NO certifica los cambios posteriores.

**Orden de autoridad:** GitHub (HEAD real) → CI del SHA exacto → este checkpoint → SPEC/PLAN aprobados → AGENTS/README → handoffs y chats. Si hay contradicción, conciliar; nunca inventar un PASS.

## 2. Leyes y forma de trabajo

1. **KISS:** simple, eficiente, optimizado y eficaz. Reutilizar PHP SSR/Slim4/PDO/MySQL-MariaDB; no SPA, servicios paralelos, tablas o abstracciones sin evidencia de necesidad.
2. **Cero ruido:** `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR/OPTIMIZAR → AGREGAR`. Antes de eliminar, demostrar que el comportamiento queda cubierto y que no se pierden contratos ni pruebas.
3. **Anti-parche:** `REPRODUCIR → CAUSA RAÍZ → OWNER → DUPLICADOS → RED CAUSAL → GREEN MÍNIMO → RETIRAR SOBRANTES → REGRESIONES`. No acumular reglas CSS laterales ni debilitar tests.
4. **Diseño/Human-First:** UI moderna, móvil y entendible; no Infinite Excel ni estados, acciones o datos inventados. El sistema de diseño debe respetar la SPEC y la referencia visual aprobada. La instalación/versionado exacto de UI UX Pro Max no está demostrado en el árbol de esta rama; no presumirlo ni instalar a ciegas.
5. **Ley complementaria de fidelidad verificable:** el PNG aprobado es contrato visual, no inspiración. Comparar contra captura REAL del SHA servido, geometría y 17 regiones, evitando autoaprobar por DOM, HTTP 200, CI o encaje básico. Excepciones funcionales requieren soporte en SPEC/rutas y no justifican desfigurar el resto del diseño.

**Interacción:** ChatGPT resuelve análisis, contrato, código remoto y verificación de GitHub directamente siempre que sea seguro. Usar Codex **al final y sólo** cuando el entorno local, base de datos o Browser QA real lo requieran. Si es imprescindible, un único lote autocontenido y auditable; evitar ping-pong. `continua` ejecuta el siguiente gate preparado, pero jamás implica ignorar un RED ni autorizar despliegue.

## 3. Contrato actual de CAND-01

- **SPEC aprobada:** `docs/superpowers/specs/2026-10-17-candidates-human-first-redesign.md`.
- **Baseline:** `docs/design/CANDIDATES_VISUAL_BASELINE_2026-10-17.md`; imagen `01_SOLICITUD.png`, SHA256 `8f7b16a46cb9decf905c218a35e5535aa3acad29041f02840a93c5228c103d15`.
- **Arquitectura:** GET `/admin/candidates/{id}?section=overview|work|household|references|documents|screening|contracts|manage`; una sola vista SSR activa.
- **Owners reales:** `app/Candidates/routes.php`, `resources/views/candidate_detail.php`, `app/UI/HumanLabels.php`, `resources/views/layouts/app.php`, `assets/app.css`; services ya existentes para propiedades, medios, candidatos, seguros y contratos. No crear una segunda autoridad.
- **Permisos:** `candidates.view` no implica `properties.view`; foto/canon/dirección privados fail-closed. Secciones `contracts`/`manage` sólo con sus permisos; nunca presentar `Sin registro` cuando el resultado es `Sin acceso`.
- **No inventar:** editar candidato, enviar mensaje, búsqueda global, campana, menú con acciones ficticias, `Continuar evaluación` o porcentaje de progreso sin endpoint/contrato. Las exclusiones se derivan de SPEC; cada excepción visual conserva el resto de composición y jerarquía.
- **Layout esencial:** fila A (1 resumen/estado), fila B (2 progreso + consulta), fila C (3 datos personales/contacto/inmueble). No ocultar ni suprimir tarjetas para simular fit.

## 4. Evidencia G3 heredada y cambios nuevos

- **Paquete G3 anterior:** HEAD `17e7bce398f38f670914e237fa26415582722679`, [CI #1158](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39506039397) SUCCESS PHP 8.3/8.4/8.5, 680 tests cada uno, 1 skipped. Browser anterior con 65 capturas actuales (58 hashes únicos), referencia, históricos y comparativas; auditoría independiente de 17 regiones y comentario [Ley 5 en PR](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/45#issuecomment-6304009090). Los ZIP originales y matriz se conservan en Library `/AlquilaPro/`.
- **Hecho del paquete anterior:** geometría 1+2+3 y seis tarjetas PASS en 1448×1086; diferencias visuales esenciales en topbar/hero, proporciones/íconos/tipo y densidad de tarjetas. Sin autorización para inventar botones o datos; visual global RED. No convertir 65 capturas en certificación del nuevo SHA.
- **Cambio directo nuevo:** commit [`fbf2bf48`](https://github.com/sierraglobalcompany-rgb/AlquilaPro/commit/fbf2bf48aa18fb988c3c9ff96a003e7d80528a56). Sólo tres archivos existentes: vista reemplaza `Próximos pasos` por `Secciones para consultar` (enlaces reales, no workflow); CSS `.candidate-card` radio 12px y titulares 700; test de semántica actualizado. CI #1159 GREEN; no nueva captura del commit.
- **No demostrado todavía:** la geometría posterior al nuevo peso tipográfico y radios, paridad visual definitiva, auditoría accesibilidad integral o dispositivos físicos. No marcar visual GREEN.

## 5. Bloqueos y decisiones pendientes

| Prioridad | Asunto | Acción correcta |
| --- | --- |
| P0 | Verificar geometría y fidelidad visual del HEAD actual | Capturar SHA servido real a 1448×1086, 1440×900, 768×1024, 390×844 y 375×812; comparar 17 regiones y registrar RED concretos |
| P0 | Diferencias de topbar/hero/panel estado | Resolver equivalencia estética sin acciones ficticias; no crear búsqueda, campana, mensajería ni botones mudos |
| P1 | Móvil/tableta: barra inferior fija cruza texto en scroll | La captura anterior demuestra intersección temporal, pero el texto es recuperable al final. Medir top/middle/bottom y foco antes de cambiar CSS global |
| P1 | CSS de foco global `:focus-visible{scroll-margin-block:100px}` | Smoke de Propiedades, Contratos, login, catálogo, candidatos y diálogo Apps; no duplicar CSS sin reproducir defecto |
| P1 | Repetición teléfono/correo en resumen y tarjeta contacto | Revisar con SPEC y utilidad contextual; evitar borrar campos o degradar seis tarjetas por una abstracción |
| P1 | PR #45 tiene descripción histórica anclada a `815bf352` | Actualizar descripción sin reescribir historial ni afirmar GREEN visual |
| P2 | UI UX Pro Max no está versionada en el árbol GitHub comprobado | Sólo registrar versión/commit si se comprueba su uso; no instalar a ciegas |

## 6. NEXT exacto y gates

1. **Verificar HEAD real y CI posterior a este commit documental**. Mantener PR #45 Draft. Una CI del HEAD exacto es obligatoria.
2. **Browser QA real del HEAD ya estabilizado**, con fixture demo seguro y comprobación de ruta/servidor/SHA/CSS servido, foto privada según permiso y estados. Recuperar las capturas antiguas de Library sólo como baseline histórica.
3. Auditoría adversarial visual **17 regiones** frente al mockup correcto; no declarar PASS sin evidencia en las cinco resoluciones; distinguir `PASS_GEOMETRIA`, `PASS_FUNCIONAL` y `PASS_VISUAL`.
4. Si persisten diferencias reales: microbloque mínimo en owners existentes, test RED causal, GREEN mínimo, suite focal/full, CI triple, nuevas capturas y regresión transversal. No añadir implementaciones cosméticas sin contrato.
5. Sólo si cero RED esencial, owner acepta explícitamente y CI exacto pasa: evaluar merge de PR #45; CAND-02 sólo después. **Hostinger/producción final-only** después del QA/UAT integral y autorización independiente.

## 7. Semáforo de recuperación

```text
TECHNICAL_PRE_DOC_UPDATE = GREEN_CI_1159@fbf2bf48aa18fb988c3c9ff96a003e7d80528a56
HEAD_AFTER_DOC_UPDATE = REVALIDATE_GITHUB_AND_CI
G3_VISUAL_EVIDENCE_HISTORIC = 17e7bce398f38f670914e237fa26415582722679
G3_VISUAL_NEW_HEAD = NO_NEW_BROWSER_CAPTURE
GEOMETRY_PREVIOUS_HEAD = PASS_1_PLUS_2_PLUS_3
VISUAL_FIDELITY = RED_ESENCIAL_NOT_CERTIFIED
PR45 = OPEN_DRAFT_NOT_MERGED
OWNER_APPROVAL = NOT_REQUESTED_WHILE_RED
CAND02 = NOT_STARTED
R3_PR33 = FROZEN
PR43 = SUPERSEDED
MAIN = DO_NOT_MODIFY
HOSTINGER = FINAL_ONLY_NO_DEPLOY
NEXT = CI_DOC_HEAD_THEN_EXACT_SHA_REAL_BROWSER_VISUAL_GATE
```
