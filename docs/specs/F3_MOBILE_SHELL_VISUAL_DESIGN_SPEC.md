# ALQUILA PRO — F3 MOBILE SHELL + SISTEMA VISUAL — SPEC

**Fecha:** 2026-10-11 (America/Bogota)  
**Fase:** F3 — Shell móvil + sistema visual  
**Estado:** SPEC escrita / pendiente de revisión del usuario  
**Base:** `main` posterior a F2 MERGED  
**Autoridad superior:** `ALQUILA_PRO_SPEC_MAESTRO_V1.1_AUDITADO.md` + `ALQUILA_PRO_PLAN_DESARROLLO_V1.1_AUDITADO.md`

---

# 1. OBJETIVO

F3 debe convertir la aplicación funcional pero visualmente mínima de F1/F2 en una interfaz coherente, moderna y mobile-first sin introducir arquitectura de frontend innecesaria.

Al cerrar F3, las funciones que **ya existen** deben sentirse parte de una sola aplicación:

- inicio autenticado;
- login;
- cambio de contraseña;
- reset de contraseña;
- administración de usuarios;
- cuenta/perfil;
- configuración visual básica.

F3 construye el **shell y lenguaje visual** que usarán las fases futuras. No construye todavía cartera, propiedades, solicitudes, contratos, notificaciones ni otros módulos de negocio.

---

# 2. INTENCIÓN DE PRODUCTO

La experiencia debe sentirse como un **Residential OS / PropTech premium**:

- limpia;
- sobria;
- cálida;
- moderna;
- rápida;
- fácil de entender con una mano;
- sin apariencia de ERP tradicional;
- sin menús laterales móviles;
- sin tablas densas como interfaz principal;
- sin gráficas decorativas;
- sin botones de funciones que todavía no existen.

El diseño debe soportar crecimiento posterior sin reconstruir la navegación.

---

# 3. DOS LEYES SUPREMAS APLICADAS A F3

## LEY 1 — KISS / ARQUITECTURA SIMPLE

F3 debe resolverse con el mínimo razonable de piezas.

Decisiones congeladas:

- HTML renderizado en servidor;
- CSS nativo;
- JavaScript mínimo;
- no SPA;
- no framework CSS;
- no librería de componentes;
- no icon package remoto obligatorio;
- no build step Node;
- no design-system framework;
- no DI container;
- no nueva base de datos para UI.

Alpine.js sigue permitido por la SPEC maestra, pero **no entra en F3 si HTML nativo + `<dialog>` + JavaScript pequeño resuelven el launcher y los estados actuales**.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

Aplicación concreta:

- una sola hoja CSS principal;
- un solo shell autenticado;
- un solo layout de autenticación;
- un solo patrón de bottom sheet;
- una sola fuente de verdad de branding;
- navegación generada solo con destinos reales;
- no crear placeholders de módulos futuros;
- no duplicar markup de topbar/nav en cada vista;
- no crear componentes genéricos que hoy no tienen dos consumidores reales.

---

# 4. PRE-KISS / PRESUPUESTO F3

```text
DB_NEW_TABLES: 0
COMPOSER_NEW_DEPENDENCIES: 0
NODE_NPM: 0
SPA: NO
CSS_FRAMEWORK: NO
FRONTEND_BUILD_PIPELINE: NO
GENERIC_DESIGN_SYSTEM: NO
FAKE_FUTURE_ROUTES: 0
FAKE_NOTIFICATIONS: 0
MOBILE_SIDEBAR: NO
```

La tabla genérica `settings` existente debe absorber branding; no se crea tabla `branding`.

---

# 5. ALCANCE P0 DE F3

## 5.1 Shell autenticado

Crear una estructura visual común para vistas autenticadas con:

- topbar;
- contenido principal;
- navegación inferior;
- botón central Apps;
- launcher Apps como bottom sheet;
- safe area móvil;
- ancho máximo razonable en desktop.

## 5.2 Shell de autenticación

Login, reset y cambio inicial de contraseña deben compartir:

- branding;
- tipografía;
- cards/formularios;
- estados de error;
- botones;
- accesibilidad;
- responsive.

No usan bottom navigation si el usuario todavía no tiene una sesión operativa normal.

## 5.3 Inicio administrativo

La ruta `/` instalada/autenticada deja de devolver texto plano.

Debe mostrar una pantalla de bienvenida real y sencilla:

- saludo/nombre del usuario;
- rol;
- acceso a las funciones existentes;
- estado del sistema sin métricas inventadas.

No se mostrarán cifras de cartera, propiedades, candidatos o notificaciones hasta que existan datos reales de esas fases.

## 5.4 Cuenta

Crear una pantalla `/account` con:

- nombre;
- correo;
- rol;
- enlace/call-to-action a cambio de contraseña;
- logout.

No se crea perfil editable completo en F3.

## 5.5 Administración de usuarios

La función F2 `/admin/users` se integra al shell visual.

Debe conservar toda la seguridad F2:

- permisos;
- CSRF;
- owner bypass;
- no escalamiento;
- último owner protegido.

La UI debe usar cards o filas responsivas, no una tabla horizontal que rompa móvil.

## 5.6 Apariencia / branding

El propietario debe poder configurar desde administración:

- nombre visible de la aplicación;
- logo opcional;
- color primario.

F3 puede guardar esos valores usando la tabla `settings` existente.

Claves previstas:

- `app.name` — ya existe desde installer;
- `brand.primary_color`;
- `brand.logo_path` o identificador equivalente según la solución final simple.

No crear tabla nueva.

### Logo

El logo:

- es opcional;
- debe tener fallback textual;
- solo admite imagen segura validada;
- no es información privada;
- no debe permitir ejecución de código;
- no debe depender de una CDN externa.

La estrategia exacta de almacenamiento se definirá en el plan F3, favoreciendo la opción con menor superficie de ataque y menos piezas.

### Color

Solo se configura **un color primario** en F3.

No se crea editor de tema completo, paletas complejas ni modo white-label avanzado.

Los colores semánticos de éxito, alerta y error permanecen controlados por el sistema para conservar legibilidad.

---

# 6. NAVEGACIÓN ADMINISTRATIVA

## 6.1 Principio

La estructura final deseada del producto es:

**Inicio · Cartera · [APPS] · Solicitudes · Cuenta**

Pero F3 no debe mostrar destinos muertos.

## 6.2 Estado real en F3

La navegación solo muestra destinos que funcionan en la versión actual.

Destinos mínimos reales:

- Inicio;
- Apps;
- Cuenta.

Si una función real merece acceso directo adicional durante F3, puede incluirse únicamente si no introduce una ruta ficticia.

Cuando F9/F14 agreguen Cartera/Solicitudes, esos destinos se activarán usando el mismo shell.

## 6.3 Apps launcher

El botón Apps es central y visualmente destacado.

El launcher debe usar un **bottom sheet basado preferiblemente en `<dialog>` nativo**.

En F3 solo puede listar apps existentes, por ejemplo:

- Usuarios / Administradores;
- Apariencia.

No listar:

- Propiedades;
- Cartera;
- Contratos;
- Pólizas;
- Solicitudes;
- Mantenimiento;
- Candidatos;

hasta que sus fases existan.

## 6.4 Topbar

Debe mostrar:

- logo o marca textual;
- nombre configurado;
- acceso a Cuenta mediante avatar/iniciales.

No mostrar campana de notificaciones hasta que exista un módulo real de notificaciones.

---

# 7. RESPONSIVE

## 7.1 Mobile-first

Diseñar primero para anchos típicos de teléfono.

Objetivos de revisión:

- 360 px;
- 375 px;
- 390 px;
- 412/430 px;
- tablet;
- desktop.

## 7.2 Touch

Acciones principales:

- mínimo práctico 44 px;
- preferencia 48 px en navegación/botones principales.

## 7.3 Safe areas

La navegación inferior debe considerar:

`env(safe-area-inset-bottom)`

para dispositivos con home indicator/notch.

## 7.4 Desktop

F3 no introduce sidebar.

En pantallas grandes:

- contenido centrado;
- ancho máximo legible;
- navegación puede mantenerse como dock inferior/compacto;
- no crear una segunda arquitectura de navegación.

---

# 8. LENGUAJE VISUAL

## 8.1 Default recomendado

Sin branding configurado:

- fondo general: neutro cálido muy claro;
- superficies: blanco;
- texto: carbón/verde muy oscuro;
- primario: verde petróleo sobrio;
- bordes: neutro suave;
- sombras: mínimas;
- radios: modernos, no excesivos.

Referencia de default:

```text
background: #F6F7F4
surface:    #FFFFFF
text:       #17211F
muted:      #66706D
border:     #E3E8E5
primary:    #1E6A5A
success:    #2F7D5A
warning:    #A66A16
danger:     #B84C4C
```

Los valores finales pueden ajustarse durante revisión visual sin cambiar arquitectura.

## 8.2 Tipografía

Usar stack de sistema.

No cargar Google Fonts ni recursos tipográficos externos en F3.

Objetivos:

- velocidad;
- privacidad;
- menos dependencias;
- buen render en Android/iOS/Windows/macOS.

## 8.3 Espaciado

Escala corta y consistente.

No crear una matriz extensa de tokens de diseño.

## 8.4 Cards

Cards solo cuando ayuden a agrupar información/acción.

No convertir cada texto en una tarjeta.

## 8.5 Estados semánticos

Patrones visibles para:

- éxito;
- advertencia;
- error;
- información;
- vacío.

No usar únicamente color para comunicar estado.

---

# 9. FORMULARIOS

Todos los formularios F1/F2 visualizados en F3 deben converger en un patrón común:

- label visible;
- input de ancho completo en móvil;
- mensaje de ayuda cuando haga falta;
- error cerca del campo o resumen claro;
- botones principales destacados;
- `autocomplete` adecuado;
- teclado/tipo de input correcto;
- foco visible.

Las contraseñas nunca se repueblan después de error.

---

# 10. SHEETS / DIALOGS

El launcher Apps es la primera implementación real del patrón sheet.

Requisitos:

- apertura explícita;
- cierre visible;
- Escape cuando el navegador lo soporte;
- click fuera puede cerrar si no arriesga pérdida de datos;
- foco manejado por `<dialog>` nativo;
- no scroll horizontal;
- altura máxima con scroll interno.

No crear un sistema modal abstracto para casos que todavía no existen.

---

# 11. LOADING / EMPTY / ERROR

## Loading

Como la aplicación es server-rendered, F3 no creará spinners globales ficticios.

Para formularios reales puede existir estado de submit/pending mínimo para evitar doble envío visual, solo si se puede implementar sin duplicar lógica del servidor.

## Empty

Cuando una pantalla real pueda carecer de contenido, usar mensaje + acción concreta.

No crear empty states para módulos futuros.

## Error

Conservar mensajes seguros del backend y representarlos dentro del lenguaje visual.

Nunca mostrar stack traces o secretos.

---

# 12. BRANDING COMO FUENTE DE VERDAD

El shell debe leer branding desde `settings`.

Fallbacks obligatorios:

```text
app.name -> "Alquila Pro"
brand.primary_color -> default del sistema
brand.logo -> marca textual si no existe
```

No duplicar branding en archivos PHP, CSS y sesiones.

El nombre configurado debe aparecer de manera consistente en:

- topbar;
- auth layout;
- title cuando sea razonable;
- apariencia.

---

# 13. ASSETS Y FRONTERA WEB

La app actual sirve desde la raíz con `.htaccess`.

F3 debe separar claramente:

## Público

- CSS;
- JavaScript mínimo;
- iconos/branding públicos seguros.

## Interno

- vistas PHP;
- código app;
- config;
- DB migrations;
- tests;
- vendor;
- documentos privados.

La auditoría previa detectó que `.htaccess` bloquea varias rutas internas pero no `resources/`.

F3 debe cerrar acceso HTTP directo a vistas PHP internas **sin bloquear los assets públicos elegidos por el plan**.

No se cambia todo el modelo de webroot en F3 si una regla simple resuelve el límite.

---

# 14. ICONOGRAFÍA

No depender de un paquete remoto.

Preferencias:

1. SVG inline/locales estrictamente necesarios;
2. pocos iconos, cada uno con propósito;
3. texto visible en navegación principal.

No crear una biblioteca de cientos de iconos.

---

# 15. ACCESIBILIDAD P0

F3 debe cumplir como mínimo:

- estructura semántica (`header`, `main`, `nav`);
- labels reales;
- foco visible;
- navegación por teclado en funciones existentes;
- contraste legible;
- botones con nombre accesible;
- iconos decorativos ocultos para lectores;
- `aria-current` en navegación activa;
- no depender solo del color;
- respetar `prefers-reduced-motion` para animaciones propias;
- zoom del navegador sin pérdida de función.

---

# 16. RENDIMIENTO

F3 debe mantener la interfaz ligera.

Objetivos arquitectónicos:

- CSS propio pequeño;
- JS propio pequeño;
- no fonts externas;
- no framework CSS;
- no imágenes pesadas obligatorias;
- no llamadas HTTP adicionales solo para renderizar shell;
- branding leído de la misma BD ya conectada;
- sin hydration ni bundles.

No se fija un número artificial de kilobytes como gate legal, pero cualquier crecimiento anormal debe explicarse en checkpoint.

---

# 17. SEGURIDAD

F3 no puede debilitar F2.

Debe conservar:

- autenticación;
- timeout de sesión;
- regeneración de session ID;
- CSRF global para POST;
- rotación CSRF;
- permisos;
- no escalamiento;
- errores sanitizados.

Branding:

- validar color contra formato permitido;
- validar MIME/archivo de logo;
- nombre de archivo no controlado directamente por usuario;
- no permitir SVG subido por usuario en F3, para evitar superficie XSS innecesaria;
- preferir PNG/JPEG/WebP si se implementa upload.

---

# 18. RUTAS FUNCIONALES ESPERADAS AL CIERRE

Existentes y preservadas:

- `/login`
- `/logout`
- `/account/password`
- `/reset-password`
- `/admin/users`
- `/health`
- `/install` cuando aplica

F3 agrega solo rutas reales necesarias para el shell, previstas:

- `/` — home administrativo autenticado;
- `/account` — cuenta;
- `/admin/appearance` — apariencia/branding;
- una ruta de entrega de logo solo si la estrategia de almacenamiento elegida la necesita.

No se agregan rutas de Cartera, Solicitudes, Propiedades u otros módulos futuros.

---

# 19. PERMISOS F3

## Inicio / Cuenta

Cualquier usuario administrativo autenticado.

## Usuarios

Permisos F2 existentes.

## Apariencia

En F3, solo `owner`.

No se agrega `settings.manage` todavía porque no existe otro caso real que justifique ampliar el catálogo de permisos.

Si más adelante administradores necesitan modificar configuración, se diseña con evidencia de uso.

---

# 20. NO-GO / FUERA DE ALCANCE

F3 NO implementa:

- propiedades;
- cartera;
- pagos;
- solicitudes;
- candidatos;
- contratos;
- pólizas;
- notificaciones;
- gráficos;
- sidebar;
- tema oscuro;
- selector de múltiples temas;
- drag-and-drop;
- PWA;
- app nativa;
- service worker;
- offline;
- frontend framework;
- sistema complejo de componentes;
- editor completo de perfil;
- módulos “próximamente”.

---

# 21. TESTING / EVIDENCIA ESPERADA

## HTTP / integración

Probar al menos:

- `/` no instalado conserva flujo installer;
- `/` instalado sin sesión redirige a login;
- `/` autenticado renderiza shell;
- app name configurado aparece;
- fallback de app name funciona;
- `/account` requiere login;
- `/admin/users` conserva permisos y aparece dentro del shell;
- `/admin/appearance` owner-only;
- guardar color/nombre requiere CSRF;
- color inválido rechazado;
- logo inválido rechazado si upload entra;
- vistas internas no son accesibles directamente por Apache boundary.

## Regresión

Suite F1/F2 completa debe seguir GREEN.

## Matriz

- PHP 8.3;
- PHP 8.4;
- PHP 8.5.

## Revisión visual

Validar al menos:

- 360×800;
- 390×844;
- 412×915;
- tablet;
- desktop ancho;
- sin overflow horizontal;
- bottom nav no tapa contenido;
- sheet usable con teclado;
- focus visible;
- zoom 200% razonable;
- safe-area.

La revisión visual puede apoyarse en navegador/Codex/Antigravity, pero no introduce dependencia runtime.

---

# 22. CRITERIOS DE ACEPTACIÓN F3

F3 puede cerrar únicamente si:

1. existe un shell administrativo coherente;
2. login/reset/password/users están visualmente convergidos;
3. `/` requiere autenticación y ya no devuelve texto plano;
4. topbar no contiene funcionalidades ficticias;
5. Apps solo muestra módulos reales;
6. bottom nav funciona en móvil sin overflow;
7. existe cuenta funcional;
8. branding mínimo es configurable usando `settings` existente;
9. logo tiene fallback seguro;
10. acceso directo a vistas internas está bloqueado;
11. no se agregó framework CSS/SPA/build Node;
12. no se agregó dependencia Composer sin necesidad;
13. no se agregó tabla nueva;
14. pruebas F1/F2 siguen GREEN;
15. pruebas F3 pasan en PHP 8.3/8.4/8.5;
16. revisión Android/iPhone/desktop pasa;
17. `LEY_1_KISS = PASS`;
18. `LEY_2_CERO_RUIDO = PASS`;
19. checkpoint F3 documenta added/removed/merged y deltas reales;
20. README queda actualizado al estado real.

---

# 23. RIESGOS Y DECISIONES

## Riesgo: construir navegación futura antes de los módulos

**Decisión:** no mostrar destinos muertos. El shell acepta que el número de destinos crezca por fases.

## Riesgo: sobrecrear design system

**Decisión:** estilos/patrones concretos usados por pantallas existentes; abstraer solo cuando haya repetición real.

## Riesgo: branding crea subsistema de archivos complejo

**Decisión:** usar `settings` + almacenamiento mínimo seguro; no tabla ni media library.

## Riesgo: views quedan expuestas por webroot raíz

**Decisión:** cerrar frontera Apache en F3 sin migrar toda la app a un nuevo webroot.

## Riesgo: JS crece

**Decisión:** `<dialog>` + JS mínimo; Alpine solo si el comportamiento real supera lo razonable con JS simple.

---

# 24. SELF-REVIEW DE LA SPEC

## Placeholder scan

- no hay TBD/TODO funcionales;
- la estrategia exacta de almacenamiento del logo queda deliberadamente para el plan técnico, porque no cambia el comportamiento del producto y debe elegirse contra el código real.

## Consistencia

- F3 respeta la SPEC maestra;
- no abre F4+;
- no contradice F2;
- branding utiliza `settings` existente;
- no crea navegación falsa.

## Scope

F3 es una sola fase coherente: shell + lenguaje visual + integración de pantallas existentes + branding mínimo.

## Ambigüedad resuelta

- no campana hasta notificaciones reales;
- no Cartera/Solicitudes hasta sus fases;
- no sidebar desktop paralelo;
- owner-only para Apariencia en F3;
- un único color configurable;
- SVG de usuario no permitido para logo en F3;
- Alpine no entra por anticipación.

---

# 25. GATE

Esta SPEC debe ser revisada y aprobada por el usuario antes de crear el mini-plan de implementación F3.

Después de aprobación:

1. invocar `writing-plans`;
2. crear `F3_MOBILE_SHELL_VISUAL_IMPLEMENTATION_PLAN.md`;
3. revisar el plan;
4. obtener aprobación del plan;
5. abrir rama de implementación limpia desde el `main` correspondiente o promover la rama según estrategia aprobada;
6. ejecutar RED → GREEN → CONVERGE → QA;
7. cerrar con Two Laws Gate y checkpoint.
