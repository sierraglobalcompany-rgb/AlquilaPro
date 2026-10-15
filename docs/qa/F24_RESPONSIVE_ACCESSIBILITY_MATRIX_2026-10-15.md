# F24 — Matriz responsive y accesibilidad

Fecha: 2026-10-15
Rama: `impl/f24-release-readiness-20261015`
Base de navegador: Playwright CLI, navegador Chromium; aplicación local y base de datos QA aislada.

## Viewports

| Viewport | Perfil verificado |
| --- | --- |
| 375 × 812 | Móvil |
| 768 × 1024 | Tableta vertical |
| 1024 × 768 | Tableta horizontal |
| 1440 × 900 | Escritorio |

Se inspeccionaron 36 combinaciones de ruta y viewport, cuatro estados del menú Apps y los siete pasos del instalador en los cuatro viewports. No se usaron datos de producción.

## Matriz de rutas

`PASS` significa que la pantalla cargó en Chromium, no tuvo overflow horizontal y mantuvo el shell/control esperado en ese breakpoint.

| Pantalla | 375 × 812 | 768 × 1024 | 1024 × 768 | 1440 × 900 |
| --- | --- | --- | --- | --- |
| Login `/login` | PASS | PASS | PASS | PASS |
| Inicio `/` | PASS; dock táctil | PASS; dock táctil | PASS; dock táctil | PASS; sidebar y sin dock |
| Apps (diálogo) | PASS; abre | PASS; abre | PASS; abre | PASS; oculto, navegación de escritorio |
| Propiedades `/admin/properties` | PASS | PASS | PASS | PASS |
| Unidad `/admin/units/1` | PASS | PASS | PASS | PASS |
| Candidatos `/admin/candidates` | PASS | PASS | PASS | PASS |
| Wizard público `/apply/{publicKey}` | PASS | PASS | PASS | PASS |
| Cartera `/admin/finance` | PASS | PASS | PASS | PASS |
| Mantenimiento `/admin/maintenance` | PASS | PASS | PASS | PASS |
| Portal `/tenant` | PASS; estado vacío | PASS; estado vacío | PASS; estado vacío | PASS; estado vacío |
| Instalador, pasos 0–6 | PASS | PASS | PASS | PASS |

Las rutas de propiedad/unidad y el wizard público usaron un inmueble QA con texto UTF-8 y una foto temporal. Candidatos, cartera, mantenimiento y portal del inquilino se inspeccionaron en estados vacíos; `/tenant` no tenía un arrendamiento asociado. El journey de arrendamiento y portal con datos está cubierto por `ReleaseCriticalFlowsTest` (F24-T4).

El instalador se completó de punta a punta en un contenedor PHP 8.3.33 con GD y una base MariaDB dedicada. Los pasos 0 a 5 respondieron correctamente; el paso 6 mostró confirmación. La contraseña del propietario no apareció en el resumen ni en la página final.

## Accesibilidad observada

- `lang="es"` en las pantallas probadas.
- Cero controles interactivos visibles sin nombre accesible en las rutas y estados del instalador inspeccionados.
- Cero campos de formulario con tamaño de texto calculado inferior a 16 px; los campos y botones principales conservan altura táctil de 48 px. El control de cuenta del topbar mide 44 px.
- Navegación con teclado: el primer campo de login recibió `:focus-visible` con un contorno sólido de 3 px.
- Preferencia `prefers-reduced-motion: reduce`: activa; transición y animación calculadas a `0.01 ms`.
- Diálogo Apps: Escape lo cierra, devuelve el foco al botón que lo abrió y cambia `aria-expanded` a `false`. En 375 px se confirmó que el último enlace sigue alcanzable desplazando el diálogo.
- Contraste de la paleta base calculado con los colores renderizados: texto `#17211F` sobre blanco 16.49:1; texto sobre canvas `#F6F7F4` 15.33:1; texto secundario `#66706D` sobre canvas 4.76:1; primario `#1E6A5A` sobre blanco y blanco sobre primario 6.43:1. Todos superan 4.5:1. La comprobación aplica a la paleta base usada en esta ejecución.

## Hallazgo y corrección

**F24-T5-01 — Etiquetas partidas en el diálogo Apps a 375 px.** La cuadrícula de dos columnas dejaba poco ancho para los títulos, y la regla global `a { overflow-wrap: anywhere; }` partía palabras como “Candidatos” y “Mantenimiento”. Reproducción confirmada en navegador real antes del cambio.

En `assets/app.css`, el breakpoint de hasta 420 px ahora reduce el padding y el espacio de las tarjetas, ajusta el tamaño del icono y del título y restablece el salto normal entre palabras. La captura posterior confirmó títulos completos, sin overflow horizontal, y desplazamiento interno hasta el último enlace. No se requirió un cambio de comportamiento ni se añadieron dependencias.

No se reprodujeron otros defectos visuales o de accesibilidad en las pantallas verificadas. La matriz no sustituye una auditoría WCAG completa ni prueba combinaciones de marca configuradas por un administrador.
