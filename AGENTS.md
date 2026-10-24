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

## 11. Diseño público aprobado

La implementación visual pública debe respetar la spec versionada aprobada. No reinterpretar mockups por memoria. Si una imagen y la spec textual se contradicen, resolver la contradicción y congelar una autoridad antes de implementar la superficie afectada.

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

## Ley 1 — KISS + Ponytail (adaptación, 2026-10-24)
Antes de modificar código, consultar `.agents/skills/kiss-ponytail/SKILL.md` si está disponible y conservar las seis leyes del canon existente. Preferir NO CREAR → REUTILIZAR repo → stdlib/nativo → dependencia existente → código mínimo claro. Trazar consumidores/contratos y preservar seguridad, auth/tenants, validación, datos, accesibilidad y QA. Ponytail no altera el diseño ni funcionalidades aprobadas; `ponytail-audit` no se ejecuta por rutina. El plugin global en Codex y sus hooks requieren instalación y confianza separadas: este archivo no prueba que estén activos.
