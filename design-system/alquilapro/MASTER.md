# AlquilaPro — Sistema visual y UX canónico del proyecto

**Estado:** adaptación factual del producto para Ley 4 V3, 2026-10-23. **No es un segundo motor de UI, CSS ni autoridad de mockups.** Instrucciones de método: [Ley 4 V3](../../docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md). Ley 5 certifica entregas. El estado vivo de ramas/CI vive en `docs/CURRENT_CHECKPOINT.md`, no en este Master.

## Identidad y jerarquía
- Producto: AlquilaPro, gestión de alquiler residencial, administración e inquilinos.
- Prioridad humana: **VERDAD → COMPRENSIÓN → DECISIÓN → ACCIÓN → DETALLE**. Diseño mobile-first, claro, cálido y sobrio; evitar marketing, tarjetas, KPIs y acciones ficticias.
- En conflictos: seguridad/permisos/datos reales → decisión reciente del owner → SPEC aprobada → mockup aprobado para apariencia y estados → componentes/tokens existentes → recomendaciones externas. No convertir una falta de backend en licencia para **quitar** una región del mockup; reconciliar contrato y autorizar el alcance antes de rediseñar. Una imagen no crea por sí misma API, entidad ni autorización.
- **Cinco leyes:** KISS, antirruido, antiparches, diseño Human-First (L4) y verificación independiente (L5). No autocertificar.

## Implementación y owners existentes
- Stack único **PHP 8.3–8.5, Slim SSR, PDO/MySQL/MariaDB, CSS y JS nativos**. Sin SPA, framework visual paralelo, motor de temas nuevo ni build Node para diseño.
- `app/UI/View.php` renderiza `renderAuth`, `renderPublic`, `renderApp` y modelo de navegación. Layouts: `resources/views/layouts/auth.php`, `public.php`, `app.php`.
- `assets/app.css` es **el** owner visual de tokens, estados, shells, grid y estilos; `assets/app.js` el JS común. `app/UI/Branding.php` es autoridad de branding y `app/UI/HumanLabels.php` de etiquetas humanas. Comprobar consumidores antes de añadir selectores, overrides o duplicados.
- Datos, operaciones, contratos, pagos, documentos y permisos pertenecen a servicios/rutas de cada dominio. `app/Auth/Permissions.php` controla capacidades; no habilitar acciones por CSS o condición de solo vista.
- `resources/views/partials/icons.php` contiene iconos disponibles. Reutilizarlos y validar su key; no introducir emoji o icon packs arbitrarios para simular un icono inexistente.

## Tokens existentes (verificar siempre contra `assets/app.css`)
- Fuente: `system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`.
- `--color-background:#F6F7F4`, `--color-surface:#FFFFFF`, `--color-text:#17211F`, `--color-muted:#66706D`, `--color-border:#E3E8E5`.
- `--color-primary:#1E6A5A`, `--color-success:#2F7D5A`, `--color-warning:#A66A16`, `--color-danger:#B84C4C`; `--brand-primary` se deriva del primario.
- `--radius-card:20px`, `--radius-control:12px`, `--shadow-soft` en CSS. Controles y botones base `min-height:48px`; estado `:focus-visible` con outline y scroll-margin.
- App shell mobile reserva espacio para navegación inferior y `safe-area-inset-bottom`; antes de cambiar offsets, comprobar scroll/foco al final y superposición. Tokens anteriores son **inventario**, no autorización para cambiar paleta.

## Referencias aprobadas — no sustituir
- Landing/catálogo/login públicos: `docs/design/APPROVED_PUBLIC_UI_BASELINE_2026-10-15.md` + los **PNG aprobados** identificados allí en Library del proyecto. No afirmar haberlos inspeccionado si faltan bytes accesibles.
- Candidatos: `docs/design/CANDIDATES_VISUAL_BASELINE_2026-10-17.md`, SPEC `docs/superpowers/specs/2026-10-17-candidates-human-first-redesign.md`, plan homónimo. Para CAND-03 referencia `03_OCUPANTES_MASCOTAS.png` (**seis regiones**), no la captura incompleta del PR #55. Nueva ampliación funcional autorizada por owner el 2026-10-23 vive en §9.3 de la SPEC de la rama activa.
- Otros módulos: verificar SPEC aplicable antes de diseñar; `docs/specs/F3_MOBILE_SHELL_VISUAL_DESIGN_SPEC.md` aporta precedente, no reemplaza aprobaciones posteriores.
- Mockups originales y variantes se conservan intactos; registrar ID/nombre/hash/estado y permisos antes de tocar vistas. Sólo el owner aprueba cambios visuales materiales.

## Contrato común de pantalla o flujo
- Identificar rol/tarea, ruta, estado `normal/vacío/error/éxito/sin_permiso` alcanzable, datos fuente, permiso, persistencia, error/reintento, texto humano y salida/volver.
- Mostrar **una acción principal real** por contexto y secundarias subordinadas; no borrar necesidades aprobadas para simplificar. Revisar escritorio, tableta y móvil con contenido largo y vacío. Conservar etiquetas visibles, foco de teclado, nombres accesibles, semántica y touch cómodo.
- Para CAND-03: fichas individuales reales, fotos privadas solo aportadas, manual de convivencia real autorizado, ningún puntaje inventado; completar regiones de referencia con placeholders **honestos** mientras se capturan datos. Una sección vacía puede ser útil si explica qué falta y cómo aportarlo.
- Cuando una referencia contenga datos/CTA sin owner funcional, marcar `CONTRATO_PENDIENTE`, reconciliar y solicitar decisión humana en lugar de omitir o inventar silenciosamente.

## Ley 4: ejecución económica
1. Seleccionar **un modo**: AUDITAR_EXISTENTE / DISEÑAR_NUEVO / IMPLEMENTAR_APROBADO / REFINAR / INSTALAR_AGENTE.
2. Verificar SHA/build, pantalla y mockup vigente; observar datos/roles y hacer una matriz de regiones/acciones antes de tocar UI.
3. Consultar cuando corresponda **un solo** playbook `github/awesome-copilot/skills/anti-ui-slop/reference/{audit|operate|new-work|polish|distill}.md`. Usar UI UX Pro Max (repositorio `nextlevelbuilder/ui-ux-pro-max-skill`) únicamente para dudas focales. Son **referencias, no autoridad ni dependencias instaladas**. Si no hay acceso, declarar `NO_ACCESIBLE`.
4. Reusar HTML SSR, estilos/tokens, iconos y servicios existentes; RED causal y GREEN mínimo para cambios funcionales; sin overrides CSS acumulativos.
5. Pruebas de interacción, seguridad por rol, teclado/foco, overflow y **capturas reales** comparadas al mismo viewport/estado. Preservar el mockup original.
6. Ley 5 separa `CI`, `FUNCIONAL`, `VISUAL` y `ACEPTACION_OWNER`; Codex no se da PASS final. Sin evidencia, `NOT_RUN`.

## Gate de instalación
La existencia de este archivo y la referencia desde `AGENTS.md` constituyen **integración documental en la rama**, no prueba de carga efectiva por Codex local. Confirmar en sesión nueva en raíz y desde `resources/views`, y revisar `AGENTS.override.md` y configuración global. No fusionar, publicar ni modificar production sin permiso explícito.
