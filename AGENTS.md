# AGENTS.md — AlquilaPro

Este archivo define reglas durables de trabajo. No es un tablero de estado por commit; el estado vivo se consulta en GitHub, CI y el checkpoint canónico de la fase activa.

## 1. Autoridad

Orden único de autoridad:

1. GitHub del repositorio canónico.
2. CI del SHA exacto.
3. Checkpoint canónico de la fase activa.
4. SPEC / PLAN aprobado aplicable.
5. README y este AGENTS para identidad, metodología y guardrails durables.
6. Handoffs y chats como apoyo de recuperación, nunca por encima del repo/CI/checkpoint.

Si dos documentos discrepan, no se promedia ni se interpreta por conveniencia: se conserva la autoridad superior y se converge la inferior.

## 2. Ley KISS

Arquitectura simple, eficiente, optimizada y eficaz.

- Preferir A -> B.
- Reutilizar owners existentes.
- No crear servicios, tablas, providers, middlewares, colas, abstracciones o capas nuevas sin una necesidad demostrada por contrato/RED.
- No anticipar arquitectura futura.
- No duplicar lógica para resolver una variante local.

## 3. Ley Cero Ruido

Antes de agregar:

`ELIMINAR -> UNIR/CONVERGER -> SIMPLIFICAR -> MEJORAR/OPTIMIZAR -> AGREGAR`

Aplicaciones obligatorias:
- retirar autoridades duplicadas;
- no arrastrar checkpoints transitorios ya absorbidos por uno canónico;
- conservar evidencia única antes de eliminar archivos supersedidos;
- no conservar compatibilidad temporal sin un gate explícito de eliminación;
- no convertir documentación histórica en estado vivo.

## 4. Ley Anti-parche

Para cualquier bug, gap o incompatibilidad:

`REPRODUCE -> ROOT CAUSE -> OWNER -> BUSCAR DUPLICACIÓN -> RED -> VERIFICAR RED -> MINIMAL GREEN -> RETIRAR SUPERSEDED -> REGRESSION`

Prohibido:
- arreglar síntomas sin causa raíz;
- añadir special-cases laterales;
- crear una segunda autoridad;
- modificar tests legítimos solo para hacerlos verdes;
- afirmar PASS sin observar la evidencia exacta.

## 5. TDD y QA

Para cambio funcional:
1. escribir/reutilizar RED mínimo;
2. observar el fallo correcto;
3. GREEN mínimo;
4. suite focal;
5. suite completa;
6. CI PHP 8.3 / 8.4 / 8.5 sobre SHA exacto;
7. auditar regresiones, ruido y duplicación.

Un focal verde no equivale a una suite verde. Un SHA funcional no queda certificado hasta que la matriz requerida termine.

## 6. Git / PR / merge

- No trabajar directamente sobre `main` para una fase activa.
- Mantener PR de la fase como superficie de CI/revisión.
- No mergear sin autorización explícita del owner.
- No desplegar por inferencia a partir de un merge.
- No forzar refs salvo recuperación explícitamente autorizada y verificada.
- Antes de merge: CI verde del HEAD exacto, checkpoint canónico actualizado, documentación convergida y auditoría final.

## 7. Checkpoints

Crear checkpoint cuando:
- el owner lo solicite;
- exista una parada significativa;
- cierre una fase o gate relevante.

El checkpoint canónico debe contener:
- base / branch / PR / HEAD;
- estado de CI con run y SHA;
- contratos cerrados;
- bloqueos reales;
- siguiente acción exacta;
- merge/deploy authorization.

Cuando un checkpoint posterior absorbe toda la evidencia única de checkpoints transitorios anteriores, retirar los supersedidos del HEAD activo. Git conserva la historia.

## 8. Roadmap post-UAT vigente

Tras el rechazo humano pre-F26, el orden activo de recuperación es:

`R0 -> R1 -> R2 -> R3 -> R4 -> R5 -> R6 -> R7 -> R8 -> R9 -> R10 -> R11 -> R12 -> R13 -> R14`

Interpretación:
- R0 baseline visual;
- R1 auth;
- R2 design foundation;
- R3 landing/catalog/detail/login;
- R4 assets demo;
- R5 dashboard/shell;
- R6 properties/units/media;
- R7 candidates/wizard;
- R8 contracts/tenant;
- R9 finance;
- R10 remaining modules;
- R11 full demo;
- R12 human owner UAT;
- R13 rebuild F25;
- R14 Hostinger/F26.

La secuencia histórica F21–F26 sigue siendo evidencia del producto anterior, no el roadmap activo después del UAT rechazado.

No iniciar la fase siguiente hasta cerrar la anterior según CI/checkpoint y, cuando corresponda al flujo de integración vigente, completar su merge autorizado.

## 9. Auth durable contracts

- Una sola autoridad de autenticación: `AuthService`.
- Una sola política de contraseña: `PasswordPolicy`.
- Ambigüedad entre email / username / documento falla cerrada.
- `UserService` es el owner de creación/gestión de identidades de usuario.
- Los índices únicos son segunda barrera, no sustituto de la validación de dominio.
- Tenant conserva el documento canónico recibido del candidato; no reinterpretar su casing en Auth.
- Compatibilidad temporal debe documentar un gate de eliminación; no puede permanecer indefinidamente “por si acaso”.

## 10. Seguridad y producto

Mantener:
- CSRF global en mutaciones web;
- sesiones y forced-password gate existentes;
- lockout y reset de contraseña en el AuthService existente;
- respuestas de error sanitizadas;
- almacenamiento privado y verificación de integridad donde ya aplica;
- tenant fail-closed y sin permisos administrativos;
- SSR como arquitectura pública/privada, sin SPA paralela.

## 11. Ley 4 V3.0 — Diseño Human-First y mockups aprobados

**Obligatoria para cualquier tarea que afecte UX/UI en Codex:**
1. Leer el **canon único L4** `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md` y la aplicación factual `design-system/alquilapro/MASTER.md`.
2. Leer el mockup/PNG aprobado, la SPEC, la baseline vigente y `docs/CURRENT_CHECKPOINT.md` de la fase; comprobar ruta, rol, permiso, estado y datos reales antes de cambiar la vista.
3. Seleccionar un único modo L4: `AUDITAR_EXISTENTE`, `DISEÑAR_NUEVO`, `IMPLEMENTAR_APROBADO`, `REFINAR` o `INSTALAR_AGENTE`. Ante conflicto entre referencia y backend, documentar el contrato; **no omitir regiones ni inventar funciones por comodidad**.
4. Consultar **un** playbook pertinente de Anti UI Slop y solo consultas focales de UI UX Pro Max cuando haya acceso y una duda concreta; registrar `NO_ACCESIBLE` si corresponde. No instalar paquetes, generar otro design system ni sobreescribir el Master automáticamente.
5. Verificar UX humana, estados, responsive (móvil/tableta/escritorio), foco y acciones con **capturas reales** del mismo SHA, datos, rol y viewport. Una captura, test de CI o mockup aislado no certifica fidelidad.
6. Entregar hallazgos/materiales y evidencias a **Ley 5** para dictamen independiente; Codex no se autocertifica. Diseño aprobado no se sustituye sin decisión humana.

La implementación pública conserva la baseline en `docs/design/APPROVED_PUBLIC_UI_BASELINE_2026-10-15.md` y los PNG allí identificados. Candidatos usa `docs/design/CANDIDATES_VISUAL_BASELINE_2026-10-17.md`. Una imagen aprobada guía apariencia y estados; no autoriza por sí sola una nueva API, permisos, cobro o operación. La simplificación KISS nunca justifica perder capacidades aprobadas.

## 12. Delegación a Codex

Si se delega un lote:
- entregar al usuario **un solo archivo `.md` descargable** por batch/task aprobado;
- **no pegar el prompt completo de Codex en el chat** como salida principal;
- el Markdown debe ser autocontenido, específico, optimizado, eficiente, sin redundancias y auditable;
- incluir scope, leyes, owners, base/HEAD, RED/GREEN, prohibiciones, hard stops, checkpoints, anti-loop, comandos de verificación, QA/CI, final report format y criterio de parada;
- Codex no autoriza merge ni deployment;
- Codex no rediseña lo ya aprobado ni crea layers/sistemas paralelos;
- cuando Codex termine un batch importante, su **informe final también debe entregarse como `.md` descargable** con base, HEAD, commits, changed files, tests, CI, issues, repairs, checkpoint, remaining work y hard stops;
- ChatGPT/Sol debe auditar ese informe contra GitHub + CI; **NO TRUST-BY-REPORT**;
- el chat no sustituye el Markdown descargable de Codex por una respuesta gigante.

## 13. Ley práctica de evidencia

`NO CLAIM WITHOUT EVIDENCE`

Antes de afirmar GREEN/PASS/MERGEABLE/DEPLOYED:

`CLAIM -> EXACT SHA -> EXACT TEST/CI/EVIDENCE -> RULING`

- No reutilizar CI de otro SHA.
- No confundir PR HEAD con synthetic merge SHA.
- No declarar CI PASS mientras haya jobs queued/in_progress.
- No aceptar un reporte de agente como sustituto de la evidencia directa.

## 14. Método de interacción con el owner

- Antes de implementar: revisar -> recuperar contexto -> contrastar GitHub -> localizar owner -> buscar duplicación -> planificar -> auditar el plan en diez pasadas -> mejorar/converger -> recién entonces implementar o delegar.
- `continua` significa ejecutar el siguiente gate inequívocamente preparado; no reiniciar el análisis desde cero.
- Si el owner pide `crea un checkpoint y para`: crear/verificar checkpoint durable y detenerse, sin seguir implementando.
- Si el owner pide un archivo: generarlo directamente como archivo descargable.

## Integración PingPongLaw — seis leyes (piloto documental F4)

- **Canon central fijado:** `sierraglobalcompany-rgb/PingPongLaw@84e961cadf482d234f4c0ee2ca60e8c7e9b388e8`; archivo `docs/engineering/LEYES_IMPLEMENTACION_1_A_6.md`. No asumir actualización automática con `main` ni lectura solo por el enlace.
- **Arranque:** leer las reglas locales de AlquilaPro y, mediante herramientas/acceso disponibles, el canon remoto **realmente**; registrar `CANON_READ=PASS/NOT_VERIFIED`, commit y la skill aplicable. Si no hay acceso remoto, conservar las reglas locales y declarar que no se verificó el canon: no inventar cumplimiento.
- **Seis leyes:** L1 KISS + Ponytail, L2 Antirruido, L3 Antiparches, L4 Diseño humano (Anti UI Slop y UI UX Pro Max como referencias focales), L5 Verificación independiente, L6 Antiping-pong. La skill local `.agents/skills/kiss-ponytail/SKILL.md` es **adaptador L1**, no copia ni instalador de nueve skills.
- **Prioridad de AlquilaPro:** prevalecen los contratos, permisos, TDD, matriz PHP 8.3–8.5, mockups Human-First, checkpoints, y reglas de merge/deploy de este archivo. Ponytail no los deroga.
- **Regla específica preservada:** §14 conserva su obligación original de auditar el plan en diez pasadas antes de implementar o delegar. L6 evita repetir auditorías sin evidencia nueva, pero no modifica esta obligación; una reducción requiere aprobación expresa del propietario.
- **ChatGPT dirige y ejecuta lo accesible**; Codex solo recibe un lote coherente cuando exige entorno local. Antes de más auditorías, preguntar `¿para qué?, ¿qué resultado cambiará?, ¿se puede implementar ya?`. No invocar automáticamente Ponytail Audit ni ambos análisis de diseño.
- **Aceptar solo con Ley 5:** evidencia del SHA/build y QA correspondiente. La presencia de esta cláusula **no prueba** que Codex cargó la skill, se instalaron hooks o una interfaz pasó pruebas visuales.
