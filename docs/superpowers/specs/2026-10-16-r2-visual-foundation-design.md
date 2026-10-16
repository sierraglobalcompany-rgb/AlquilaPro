# AlquilaPro — R2 Visual Foundation Design

Fecha: 2026-10-16 (America/Bogota)
Estado: SPEC PARA REVISIÓN DEL OWNER
Base canónica: `main@71445785f39caec7f4e7059a2a80ffbd75468b2d`
Rama de trabajo: `impl/r2-visual-foundation-20261016`
Roadmap: `R0 -> R1 -> R2 -> R3 -> ... -> R14`
R1: MERGED / CLOSED GREEN
R3: NOT STARTED
F26: FROZEN
DEPLOYMENT_AUTHORIZED: NO

## 1. Objetivo

R2 debe dejar una foundation visual compartida, mínima y estable para que R3 pueda implementar la landing, catálogo, detalle y login públicos aprobados sin duplicar estilos, sin reutilizar accidentalmente el shell de autenticación y sin crear un segundo sistema visual.

R2 no diseña pantallas finales. Su resultado es estructural: separar correctamente las superficies `auth`, `public` y `app` dentro de los owners existentes, manteniendo una sola fuente de tokens/primitives y preservando el comportamiento funcional actual.

## 2. Intención de producto

La foundation debe permitir que AlquilaPro mantenga una identidad visual coherente en móvil, tablet y desktop mientras cada superficie conserva su propósito:

- `auth`: login/reset/change-password, centrado y enfocado en credenciales;
- `public`: landing, catálogo, detalle y flujo público; debe poder crecer a ancho completo y composición editorial/carrusel;
- `app`: shell privado administrativo/tenant con navegación propia.

El usuario público no debe quedar encerrado visualmente en una `auth-card`. El usuario autenticado no debe recibir estilos públicos por accidente. Los tokens, controles y reglas de accesibilidad sí se comparten.

## 3. Autoridades y precedencia

Para R2 se usa este orden:

1. GitHub vivo en `main@71445785...`.
2. CI exacta del HEAD de R2 cuando exista.
3. Esta spec, una vez aprobada.
4. `docs/design/APPROVED_PUBLIC_UI_BASELINE_2026-10-15.md`.
5. Contratos/tests existentes (`AssetContractTest`, `ShellRoutesTest` y regresión UI).
6. Specs/planes históricos F3 solo como contexto; no pueden reabrir arquitectura que ya existe.

La spec visual pública aprobada exige PHP SSR, CSS/JS existentes, no SPA, no segundo catálogo y una foundation compartida. R2 debe preparar esa foundation, no implementar aún el baseline final.

## 4. Hallazgo raíz

El problema estructural actual no es ausencia de design system. Los owners ya existen:

- `app/UI/View.php` centraliza `renderAuth`, `renderPublic`, `renderApp`;
- `resources/views/layouts/auth.php` es el layout de autenticación;
- `resources/views/layouts/public.php` es el layout público;
- `resources/views/layouts/app.php` es el shell privado;
- `assets/app.css` es la única hoja CSS y ya contiene tokens, controles, focus, safe-area, reduced motion y responsive;
- `tests/Unit/UI/AssetContractTest.php` protege contratos CSS/JS;
- `tests/Integration/UI/ShellRoutesTest.php` ya distingue `data-public-shell` de `data-f3-app-shell`.

El hueco concreto es que `resources/views/layouts/public.php` reutiliza las clases `auth-page`, `auth-shell` y `auth-card`. Eso acopla visualmente la superficie pública a autenticación y obliga a R3 a pelear contra un contenedor pensado para formularios centrados.

La corrección debe ser de ownership, no un parche CSS sobre cada nueva pantalla pública.

## 5. Decisión de diseño

### Opción elegida: convergencia mínima sobre owners existentes

R2 mantendrá una sola foundation y separará las superficies únicamente donde el contrato semántico realmente difiere.

Se preservan:

- un solo `View`;
- un solo `Branding`;
- un solo `assets/app.css`;
- layouts existentes;
- PHP SSR;
- JS nativo actual;
- tokens existentes como autoridad visual base.

Se introducirán wrappers/clases públicas explícitas dentro del layout existente, por ejemplo:

- `public-page`;
- `public-shell`;
- `public-header` / `public-brand` solo si el markup real los necesita;
- `public-main`.

Los nombres exactos pueden variar mínimamente durante implementación si un RED demuestra una forma más simple, pero el contrato obligatorio es que el layout público deje de depender de `auth-shell`/`auth-card` como estructura principal.

### Opciones descartadas

1. **Crear design system/framework/component library:** rechazado por KISS; duplica primitives y aumenta superficie de mantenimiento.
2. **Crear un segundo CSS público:** rechazado por Cero Ruido; produciría dos autoridades de tokens y responsive.
3. **Dejar `public` sobre `auth` y arreglar R3 con overrides:** rechazado por Anti-parche; causa cascada de excepciones CSS pantalla por pantalla.
4. **Refactor masivo de todo `app.css`:** rechazado; churn alto sin necesidad funcional demostrada.

## 6. Presupuesto KISS de R2

```text
DB_NEW_TABLES: 0
MIGRATIONS: 0
NEW_SERVICES: 0
NEW_REPOSITORIES: 0
NEW_CONTROLLERS: 0
COMPOSER_NEW_DEPENDENCIES: 0
NODE_NPM: 0
SPA: NO
CSS_FRAMEWORK: NO
FRONTEND_BUILD_PIPELINE: NO
SECOND_STYLESHEET: NO
NEW_JS_RUNTIME: NO
NEW_ROUTE_DOMAIN: NO
NEW_BUSINESS_BEHAVIOR: NO
```

R2 debe poder cerrarse modificando exclusivamente owners y tests existentes. Si aparece necesidad de una clase/subsystem nuevo, se considera señal de diseño incorrecto y se vuelve a root cause antes de agregarlo.

## 7. Alcance funcional

### 7.1 Public layout ownership

`resources/views/layouts/public.php` debe:

- conservar `<html lang="es">`, viewport responsive, título, branding y `basePath`;
- conservar `data-public-shell` para regresión/integración;
- dejar de usar `auth-page`, `auth-shell` y `auth-card` como contenedor principal;
- usar wrappers públicos explícitos;
- mantener logo/fallback textual y enlace a `/`;
- seguir renderizando exactamente un `$content` SSR;
- no añadir navegación pública final, carrusel, hero, cards de inmueble ni login embebido en R2.

### 7.2 Shared visual primitives

`assets/app.css` sigue siendo la única autoridad CSS.

R2 puede:

- conservar y ordenar tokens existentes sin cambiar su significado salvo necesidad demostrada;
- añadir reglas mínimas para `public-*`;
- compartir controles (`button`, inputs, focus, semantic states), tipografía, colores, spacing/radius/shadows existentes;
- preservar imágenes responsivas, wrapping, safe areas y reduced motion;
- evitar selectores demasiado globales nuevos que afecten módulos privados históricos.

R2 no hará un redesign del shell privado ni del auth layout.

### 7.3 Responsive contract

La foundation pública debe soportar sin hacks posteriores los tres tamaños de autoridad visual:

- 375x812;
- 768x1024;
- 1440x900.

R2 no necesita reproducir todavía las capturas aprobadas. Sí debe asegurar que `public-shell/public-main` puedan:

- usar ancho completo razonable en móvil;
- crecer en tablet;
- admitir composición ancha en desktop sin el límite `520px` de auth;
- mantener gutters consistentes y contenido sin overflow horizontal estructural.

### 7.4 Auth y app invariantes

R2 no debe alterar contratos funcionales de:

- login;
- reset/cambio de contraseña;
- `identifier` auth;
- permisos/navigation;
- tenant shell;
- branding persistence;
- CSRF;
- forced password change;
- rutas de negocio.

`auth.php` puede permanecer sin cambios si los tests demuestran que no necesita convergencia adicional.

`app.php` no debe tocarse salvo que una regla compartida mínima requiera un ajuste demostrable; por defecto queda fuera del diff.

## 8. No alcance — reservado a R3/R4

R2 NO implementa:

- carrusel de propiedades;
- landing final `Propiedades disponibles`;
- `Ver todas` final;
- tarjetas horizontal/mobile finales;
- thumbnails/galería final;
- detalle público final;
- login fijo bajo carrusel;
- resolución visual de X/no-X;
- resolución de copy `Usuario` vs `Usuario, correo o cédula`;
- eliminación del fallback HTTP `identifier ?? email`;
- imágenes demo/fixtures finales;
- JavaScript del carrusel;
- screenshot QA contra baseline.

Esos puntos siguen en R3/R4. Mezclarlos en R2 invalidaría el gate de foundation.

## 9. Contrato TDD

Implementación obligatoria:

`ROOT CAUSE -> OWNER -> DUPLICATION SEARCH -> RED -> VERIFY RED -> MINIMAL GREEN -> REGRESSION -> REMOVE SUPERSEDED -> CI`

### R2.1 — RED estructural

Ampliar tests existentes, preferiblemente sin crear un test file nuevo.

En `tests/Integration/UI/ShellRoutesTest.php`:

- el root público instalado sigue respondiendo 200;
- sigue conteniendo `data-public-shell`;
- contiene la nueva clase/wrapper público canónico;
- no contiene la estructura principal `class="auth-shell"` ni `class="auth-card"`;
- continúa enlazando a `/catalog` y `/login` mientras R3 no cambie contenido.

En `tests/Unit/UI/AssetContractTest.php`:

- existe contrato CSS explícito para la foundation pública;
- el public main/shell no está limitado al ancho auth de 520px;
- no aparece un segundo import/stylesheet remoto/framework;
- se preservan focus, safe-area y reduced-motion existentes.

RED debe fallar por la estructura actual, no por un test artificial sin valor de producto.

### R2.2 — Minimal GREEN

Modificar únicamente:

- `resources/views/layouts/public.php`;
- `assets/app.css`;
- los tests existentes necesarios.

No cambiar `public_landing.php` salvo que el RED demuestre una incompatibilidad estrictamente estructural; por defecto queda fuera.

No crear PHP nuevo.

### R2.3 — Regression

Ejecutar primero el conjunto focal UI:

- `tests/Integration/UI/ShellRoutesTest.php`;
- `tests/Integration/UI/AuthVisualTest.php`;
- `tests/Integration/UI/ResponsiveMarkupRegressionTest.php`;
- `tests/Unit/UI/AssetContractTest.php`.

Después ejecutar full suite.

CI exacta obligatoria PHP 8.3 / 8.4 / 8.5 sobre el HEAD final de R2.

## 10. Criterios de aceptación

R2 se considera GREEN solo si todos son ciertos:

1. `public.php` ya no usa auth shell/card como estructura primaria.
2. `auth.php` conserva su comportamiento visual/funcional.
3. `app.php` conserva navegación y permisos.
4. Sigue existiendo exactamente un stylesheet principal: `assets/app.css`.
5. No hay clases/servicios/paquetes/tablas nuevos.
6. Los tokens/primitives existentes siguen siendo la autoridad compartida.
7. El public shell admite 375/768/1440 sin límite auth de 520px.
8. No se implementa contenido reservado a R3/R4.
9. Focal tests GREEN.
10. Full suite GREEN.
11. CI PHP 8.3/8.4/8.5 GREEN sobre HEAD exacto.
12. Auditoría KISS / Cero Ruido / Anti-parche PASS.
13. Checkpoint canónico R2 creado solo al cierre; no acumular checkpoints transitorios.
14. No merge sin gate de integración vigente.
15. No deployment.

## 11. Regla Anti-Ruido

Orden obligatorio durante R2:

`ELIMINATE -> MERGE/CONVERGE -> SIMPLIFY -> IMPROVE/OPTIMIZE -> ADD`

Aplicación concreta:

- eliminar dependencia semántica `public -> auth`;
- converger sobre `View`/`app.css` existentes;
- no duplicar tokens;
- no copiar bloques CSS por breakpoint si una regla fluida los resuelve;
- no crear helpers/componentes con un solo consumidor;
- no tocar módulos de negocio no relacionados;
- no actualizar docs históricas para “hacerlas coincidir” si Git history ya preserva contexto.

## 12. Regla Anti-Parche

Prohibido cerrar R2 mediante:

- overrides `!important` para neutralizar `.auth-shell`;
- cadenas de selectores tipo `.public .auth-shell ...`;
- segundo layout público alternativo;
- stylesheet público adicional;
- condicionales por ruta dentro del CSS/JS;
- excepciones específicas por viewport para esconder el problema de ownership.

La solución debe corregir el owner `public.php` y la foundation compartida directamente.

## 13. Riesgos y mitigaciones

### Riesgo: regresión de auth
Mitigación: no renombrar clases auth; focal `AuthVisualTest` + full suite.

### Riesgo: selector global rompe módulos privados
Mitigación: reglas públicas namespaced `public-*`; cambios globales solo si ya son primitives compartidos y están protegidos.

### Riesgo: R2 absorbe R3
Mitigación: no tocar contenido final de landing/login/catalog; cualquier petición visual de pantalla se difiere explícitamente.

### Riesgo: foundation demasiado abstracta
Mitigación: cero clases PHP nuevas, cero component library, únicamente markup/layout/CSS que ya tiene consumidores reales.

## 14. Auditoría de diseño — 10 pasadas

1. **Scope:** una sola responsabilidad: foundation visual compartida; PASS.
2. **Ownership:** usa `View`, layouts y `app.css` existentes; PASS.
3. **KISS:** +0 arquitectura nueva; PASS.
4. **Cero Ruido:** elimina el acoplamiento public/auth en vez de duplicar; PASS.
5. **Anti-parche:** root cause localizado en layout owner; PASS.
6. **TDD:** RED estructural antes de GREEN; PASS.
7. **Responsive:** 375/768/1440 contemplados sin implementar pantallas; PASS.
8. **Seguridad:** no toca auth/CSRF/permisos/datos; PASS.
9. **Roadmap:** R3/R4 permanecen cerrados; PASS.
10. **Gobernanza:** CI exacta + checkpoint + merge gate; PASS.

## 15. Secuencia de implementación propuesta

```text
R2.0 SPEC APPROVED
  -> R2.1 FOUNDATION CONTRACT RED
  -> VERIFY RED
  -> R2.2 MINIMAL GREEN
  -> FOCAL REGRESSION
  -> FULL REGRESSION
  -> R2.3 CI 8.3/8.4/8.5
  -> R2.4 10-PASS AUDIT + CANONICAL CHECKPOINT
  -> MERGE GATE
```

No se inicia R2.1 hasta aprobación del owner de esta spec y creación del plan de implementación detallado conforme al workflow arquitectónico.