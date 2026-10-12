# ALQUILA PRO — F3 WIP CHECKPOINT — TASK 5 RED

**Fecha local:** 2026-10-11 (America/Bogota)  
**Estado:** WIP — PAUSA SEGURA SOLICITADA POR EL USUARIO  
**Fase:** F3 — Mobile Shell + Visual System  
**PR:** #4 — `F3: mobile shell + visual system`  
**PR state:** OPEN + DRAFT + NOT MERGED  
**Branch:** `impl/f3-mobile-shell-visual-20261011`  
**Base:** `main` @ `f4c42a75753a5527a01e50951e7cbdc15c203b9e`  
**Task 5 RED source HEAD before this checkpoint:** `82f8bc8dac085bf0895f9452af170fa01693dd47`

---

## 1. REGLA DE REANUDACIÓN

No reconstruir contexto por intuición y no saltar directamente a Task 6/7.

Al reanudar:

1. leer este checkpoint;
2. confirmar que PR #4 sigue draft/no merged;
3. confirmar branch `impl/f3-mobile-shell-visual-20261011`;
4. retomar **Task 5 — Owner Appearance Screen + Safe Logo Route** desde GREEN;
5. no reescribir los tests RED salvo que exista un fallo del propio test;
6. después de GREEN completo, continuar Task 6;
7. no mergear F3 sin cierre/QA y aprobación explícita del usuario.

---

## 2. CONSTITUCIÓN TÉCNICA ACTIVA

### LEY 1 — KISS / ARQUITECTURA SIMPLE

Toda solución debe seguir siendo simple, directa, mantenible, auditable, rápida y fácil de desplegar. No añadir capas, servicios, dependencias, tablas o abstracciones sin necesidad real demostrada.

### LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

**ELIMINAR → FUSIONAR/CONVERGER → SIMPLIFICAR → MEJORAR → AÑADIR**

No dejar rutas muertas, UI ficticia, duplicación, dependencias sin uso, renderers legacy, estados redundantes ni código futuro vacío.

---

## 3. ESTADO F3 POR TASK

### Task 1 — Branding foundation

**Estado:** GREEN / CLOSED dentro de F3 WIP.

Disponible y probado:
- `AlquilaPro\UI\Branding`;
- settings para `app.name`, `brand.primary_color`, `brand.logo_path`;
- fallback seguro `Alquila Pro` + `#1E6A5A`;
- logo PNG/JPEG/WebP validado por bytes/MIME real;
- máximo 2 MiB;
- nombre generado aleatorio;
- storage interno;
- sustitución elimina el logo anterior;
- sin tabla nueva de uploads.

### Task 2 — Auth visual convergence

**Estado:** GREEN / CLOSED dentro de F3 WIP.

Disponible y probado:
- login/change/reset integrados al sistema visual F3;
- CSS local;
- branding configurable;
- subcarpetas soportadas;
- sin bottom nav en auth/installer;
- sin frameworks UI nuevos.

### Task 3 — Authenticated mobile shell

**Estado:** GREEN / CLOSED dentro de F3 WIP.

Implementado:
- `app/UI/routes.php`;
- `View::renderApp()`;
- layout autenticado móvil;
- Inicio / Apps / Cuenta;
- `<dialog>` nativo para Apps;
- `assets/app.js` mínimo;
- `must_change_password` bloquea acceso al shell;
- branding y subcarpetas funcionan;
- un único resolver `currentUser` compartido por Auth + UI;
- launcher solo expone rutas reales.

Decisión convergente importante:
- Task 3 **NO** mostró Apariencia porque su ruta todavía no existía;
- Apariencia se incorpora únicamente en Task 5, al mismo tiempo que su ruta real.

### Task 4 — User administration in shell

**Estado:** GREEN / CLOSED dentro de F3 WIP.

Implementado:
- `/admin/users` usa el mismo app shell;
- Apps queda marcado activo;
- usuarios en cards responsive;
- no tabla HTML;
- formulario de alta visible solo con `users.manage`;
- backend/autorización F2 preservados;
- renderer legacy eliminado;
- dependencia `views` legacy eliminada del wiring de Auth.

Durante Task 4 se activó también el guardrail web pendiente:
- `.htaccess` bloquea ahora `resources/` mediante la misma regla central existente;
- no se añadieron `.htaccess` por directorio.

### Último GREEN integral antes de Task 5

**Commit:** `4025171c00af27ea25932289255fe08214915eaa`

CI confirmado GREEN:
- PHP 8.3: PASS
- PHP 8.4: PASS
- PHP 8.5: PASS

Suite en ese punto:
- **80 tests**
- Task 1–4 + F1/F2 + SecurityBoundary verdes.

---

## 4. TASK 5 — ESTADO EXACTO AL PAUSAR

### Objetivo

Crear la pantalla owner-only de Apariencia y la ruta segura del logo actual, reutilizando `Branding` existente.

Rutas aprobadas:
- `GET /admin/appearance` — owner-only;
- `POST /admin/appearance` — owner-only + CSRF global;
- `GET /brand/logo` — sirve únicamente el logo actual validado; 404 si no existe.

No añadir:
- permiso nuevo;
- tabla nueva;
- servicio de uploads;
- tema oscuro;
- palette builder;
- cropper;
- nombre/path controlado por usuario para recuperar logo.

### RED ya creado

Commit de RED: `82f8bc8dac085bf0895f9452af170fa01693dd47`

Archivo nuevo:
- `tests/Integration/UI/AppearanceRoutesTest.php`

Boundary extendido:
- `tests/Integration/Auth/CsrfRoutesTest.php`

Casos RED creados:
1. `testAppearanceRequiresAuthentication`
2. `testAppearanceIsOwnerOnly`
3. `testOwnerCanUpdateAppNameAndPrimaryColor`
4. `testInvalidColorReturns422WithoutMutation`
5. `testValidPngUploadPersistsAndLogoRouteServesActualMime`
6. `testSvgAndOversizedLogoAreRejected`
7. `testAppearanceRespectsForcedPasswordChange`
8. `testAppearanceRespectsSubfolderPaths`
9. `testAppearanceMutationWithoutCsrfIsForbidden`

### RED verificado

PHP 8.3 confirmó:
- **89 tests**
- **404 assertions**
- **8 failures**

Los 8 failures son exactamente los 8 casos de `AppearanceRoutesTest` porque `/admin/appearance` todavía no existe y el error middleware devuelve 500 para el `HttpNotFoundException`.

Importante:
- `testAppearanceMutationWithoutCsrfIsForbidden` ya pasa;
- todas las pruebas previas F1/F2/F3 Tasks 1–4 pasan;
- Branding unit/integration tests pasan;
- SecurityBoundary pasa;
- no apareció una regresión productiva distinta del RED esperado.

Cuando se capturó este checkpoint, los jobs PHP 8.4/8.5 del RED seguían ejecutándose. No es necesario esperar su resultado para demostrar RED: PHP 8.3 ya prueba de forma suficiente que las rutas/vista faltan por la causa prevista.

---

## 5. SIGUIENTE PASO EXACTO

Retomar en **Task 5 / GREEN mínimo**, sin volver a diseñar Task 5.

Orden recomendado:

1. modificar `app/UI/routes.php`:
   - inyectar/reusar `Branding`;
   - guard owner-only;
   - `GET /admin/appearance`;
   - `POST /admin/appearance`;
   - `GET /brand/logo`;
2. crear `resources/views/appearance.php`;
3. modificar `resources/views/layouts/app.php` para mostrar **Apariencia** en Apps solo a owner, ahora que la ruta ya será real;
4. extender `assets/app.css` solo con estilos realmente usados por appearance;
5. no crear servicios/clases/tablas/permisos nuevos;
6. correr GREEN focal:
   - `BrandingTest`
   - `AppearanceRoutesTest`
   - `CsrfRoutesTest`;
7. correr suite completa y matriz PHP 8.3/8.4/8.5;
8. aplicar gate KISS + Cero Ruido;
9. solo entonces cerrar Task 5 y abrir Task 6.

---

## 6. ARCHIVOS PRODUCTIVOS RELEVANTES ACTUALES

- `app/UI/Branding.php`
- `app/UI/View.php`
- `app/UI/routes.php`
- `app/Auth/routes.php`
- `bootstrap/app.php`
- `resources/views/layouts/app.php`
- `resources/views/home.php`
- `resources/views/account.php`
- `resources/views/admin_users.php`
- `assets/app.css`
- `assets/app.js`
- `.htaccess`

Tests F3 relevantes:
- `tests/Integration/UI/BrandingTest.php`
- `tests/Integration/UI/AuthVisualTest.php`
- `tests/Integration/UI/ShellRoutesTest.php`
- `tests/Integration/UI/AppearanceRoutesTest.php`
- `tests/Integration/Auth/CsrfRoutesTest.php`
- `tests/Integration/Foundation/SecurityBoundaryTest.php`

---

## 7. NO HACER AL REANUDAR

- NO mergear PR #4.
- NO empezar Task 6 antes de Task 5 GREEN.
- NO crear permiso `appearance.manage`.
- NO crear tabla de uploads/branding.
- NO crear `UploadService`, `BrandingController`, `AppearanceService` o capas equivalentes sin necesidad demostrada.
- NO mostrar módulos futuros como Cartera, Solicitudes o Propiedades.
- NO reintroducir sidebar.
- NO duplicar current-user resolver.
- NO convertir el launcher en registro genérico de apps todavía.
- NO tocar F4.

---

## 8. CHECKPOINT GATE

```text
F3_STATUS: WIP
TASK_1: GREEN
TASK_2: GREEN
TASK_3: GREEN
TASK_4: GREEN
TASK_5: RED_CONFIRMED / GREEN_NOT_STARTED
TASK_6: NOT_STARTED
TASK_7: NOT_STARTED

LAST_FULL_GREEN_SHA: 4025171c00af27ea25932289255fe08214915eaa
TASK5_RED_SHA: 82f8bc8dac085bf0895f9452af170fa01693dd47
PR_4: OPEN_DRAFT_NOT_MERGED

LEY_1_KISS: PASS through Task 4; Task 5 open
LEY_2_CERO_RUIDO: PASS through Task 4; Task 5 open

NEXT_ACTION: implement Task 5 GREEN only
```

**Este documento es un checkpoint de pausa, no un cierre de F3.**
