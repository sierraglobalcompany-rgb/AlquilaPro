# AlquilaPro — CURRENT CHECKPOINT

**Corte:** 2026-10-23, America/Bogota. **Autoridad:** GitHub HEAD exacto → CI del HEAD → este checkpoint → SPEC/AGENTS → historial de Git. Este documento es el estado activo de la **rama de integración**, no sustituye `main` ni autoriza merge o deploy.

## Identidad y estado verificable

- **Repo:** `sierraglobalcompany-rgb/AlquilaPro`.
- **Main verificado:** `94ba5f486b158c2b1fed753f26c63f7b3570c97c`; CAND-01 integrado anteriormente por PR #45. Sin cambios por este trabajo.
- **PR de integración:** [#54](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/54), **Draft, mergeable=true, no merged**; rama `integration/cand02-documents-20261023-g1`. **HEAD de código verificado antes de este commit documental:** `ffddc415c57c98588ca5c895d9e56f4eab0e1945`.
- **CI código [#1176](https://github.com/sierraglobalcompany-rgb/AlquilaPro/actions/runs/39904534274): SUCCESS PHP 8.3, 8.4, 8.5**, asociado a `ffddc415c57c98588ca5c895d9e56f4eab0e1945`.
- **Browser QA integrado:** `PASS_WITH_SCOPE` sobre `ffddc415c57c98588ca5c895d9e56f4eab0e1945`, evidencias: **seis PNG reales + dos referencias originales + métricas/manifiesto**, sin overflow horizontal ni errores inesperados de consola; validación independiente del ZIP **11/11 hashes**. **Paridad total de mockups `NOT_CERTIFIED`**. QA documenta estados sintéticos, no despliegue.
- **Documentación actualizada:** esta edición genera un **nuevo commit documental**; requerirá CI propio antes de integración final. **No afirmar que Browser QA midió el nuevo SHA**: se conserva evidencia sobre el padre de código sin cambio funcional/visual. No repetir Playwright por una edición exclusiva de Markdown.
- **PR #52** [Work](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/52), HEAD `0eea64d8a4fdba964d14da26345ff6e68369d36d`, Draft; V8 `PASS_WITH_SCOPE`. **PR #53** [Documentos](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/53), HEAD `4c20e6acbe88aac6a3b3714d49f4d9a598d56e41`, Draft; QA `PASS_WITH_SCOPE`. **Ambos continúan abiertos como fuentes históricas**: PR #54 ya incluye sus cambios funcionales; nunca fusionar los tres indiscriminadamente.
- **Merge:** `NO AUTORIZADO`. **Deploy Hostinger:** `NO AUTORIZADO`. **CAND-03+ / F26:** `FROZEN` hasta integración autorizada y siguiente gate.

## Contratos integrados y reservas

- `resources/views/candidate_detail.php`: sección **Work** contiene exclusivamente datos declarados existentes (empleo, empresa, ocupación, ingreso y notas) y sección **Documents** contiene requisitos activos, recibidos, pendientes, opcionales y documentos de requisitos inactivos. Una sola sección activa por solicitud.
- `app/Candidates/routes.php`: requisitos reales del `CandidateDocumentService` consultados al renderizar `?section=documents`; permisos previos preservados.
- `assets/app.css`: estilo específico para ambas secciones; corrección de navegación fija entre **1024 y 1199px** y menú/tab a partir de 1200px. Causa de CI #1175 identificada (orden de reglas del contrato de navegación), corregida en `ffddc415...`; CI #1176 verde.
- Pruebas de **convivencia Work/Documentos** y regresión de breakpoint añadidas, sin migraciones ni módulos nuevos.
- **En QA 1100px:** último enlace documental termina **97,5px antes** de la barra fija; resumen lateral recuperable al desplazarse hacia arriba. **No** catalogar superposición funcional si el final es accesible.
- **Límites:** historia/verificación laboral, revisión/solicitud/aprobación documental, miniaturas/acciones de mockups **no existen como flujo completo verificado**. No añadir simulaciones visuales ni aprobación inventada; futura implementación solo con fuentes de datos, permisos y UAT. Accesibilidad integral, dispositivo físico y paridad visual global no certificados. El Browser QA integrado no reejecutó todos los escenarios de PDF privado/403 de PR #53; conservar esa distinción.

## Cuatro leyes y próxima compuerta

- **KISS:** PHP SSR, Slim/PDO/MariaDB, owners existentes; sin arquitectura o capas paralelas.
- **Antirruido:** usar PR #54 como candidata única de integración; documentar supersesión y cerrar PRs anteriores **solo después de decisión autorizada**. Historial y evidencias permanecen en Git/PRs.
- **Antiparches:** causa de fallo #1175 corregida y cubierta por regresión; prohibido reabrir arreglos CSS sin fallo nuevo probado.
- **UX/UI Pro:** capturas reales, viewport/rol/datos, límites `PASS_WITH_SCOPE`; fidelidad global pendiente con funcionalidades sustentadas por backend.

**NEXT ÚNICO:** comprobar que el **CI del nuevo HEAD exclusivamente documental** termina verde; presentar dictamen del PR #54 y solicitar **autorización expresa de merge exclusivo del PR #54** (no deploy). Sin autorización, STOP. No iniciar CAND-03 ni crear nuevas ramas/pruebas/paquetes redundantes.
