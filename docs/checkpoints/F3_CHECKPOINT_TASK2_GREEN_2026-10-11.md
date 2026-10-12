# ALQUILA PRO — F3 CHECKPOINT

**Fecha:** 2026-10-11 (America/Bogota)  
**Fase:** F3 — Mobile Shell + Visual System  
**Estado:** PAUSADO POR SOLICITUD DEL USUARIO  
**Rama:** `impl/f3-mobile-shell-visual-20261011`  
**PR:** #4 — draft, NO MERGE  
**Base integrada:** `main` @ `f4c42a75753a5527a01e50951e7cbdc15c203b9e`  
**Último SHA funcional verificado antes de este checkpoint:** `0b83aef955dd8f0aacb612cdda8e59e5abb744d2`

---

## 1. ESTADO EXACTO

F3 está parcialmente implementada y detenida después de cerrar Task 2 en GREEN.

### Task 1 — Branding persistence + secure logo storage

**Estado:** GREEN / verificado.

Implementado:
- `AlquilaPro\UI\Branding`.
- fallback seguro de nombre y color.
- actualización de nombre/color usando la tabla `settings` existente.
- logo PNG/JPEG/WEBP validado por bytes con `fileinfo`.
- límite de 2 MiB.
- nombre aleatorio generado por servidor.
- almacenamiento fuera del webroot.
- reemplazo elimina el logo generado anterior.
- `fileinfo` agregado a requisitos de runtime.

Cambios estructurales:
- tablas nuevas: `+0`.
- dependencias Composer nuevas: `+0`.
- rutas públicas nuevas: `+0`.

### Task 2 — Shared auth layout + native CSS

**Estado:** GREEN / verificado.

Implementado:
- `AlquilaPro\UI\View` con `renderAuth()`.
- un layout auth compartido: `resources/views/layouts/auth.php`.
- un stylesheet local: `assets/app.css`.
- login/reset/change-password convertidos de documentos HTML completos a parciales.
- branding configurable visible en las pantallas auth.
- soporte de `base_path` para assets.
- installer reutiliza el mismo CSS local sin depender de branding en BD.
- se retiró el CSS inline duplicado del installer.
- no se agregó JavaScript en Task 2.

Regresión funcional:
- AuthService, SessionGuard, CSRF y permisos F2 no fueron modificados semánticamente.

Cambios estructurales:
- tablas nuevas: `+0`.
- dependencias Composer nuevas: `+0`.
- JS runtime nuevo: `+0`.

---

## 2. EVIDENCIA DE VERIFICACIÓN

Workflow CI correspondiente al SHA funcional `0b83aef955dd8f0aacb612cdda8e59e5abb744d2`:

- Run: `38191289092`
- PHP 8.3: PASS
- PHP 8.4: PASS
- PHP 8.5: PASS

La suite completa incluye los tests previos F1/F2 y los nuevos tests F3 de Task 1 y Task 2.

---

## 3. TWO LAWS GATE

### LEY 1 — KISS

**PASS para Task 1 y Task 2.**

Evidencia:
- 1 clase de branding, sin repository/service/interface/DTO extra.
- 1 renderer visual.
- 1 layout auth.
- 1 stylesheet.
- 0 nuevas dependencias.
- 0 nuevas tablas.
- no framework JS.

### LEY 2 — CERO RUIDO / CONVERGENCIA

**PASS para Task 1 y Task 2.**

Eliminado/convergido:
- documentos HTML duplicados en login/reset/change-password.
- CSS inline duplicado del installer.
- branding disperso sustituido por una única lectura validada.

No se crearon módulos futuros, tablas vacías, flags temporales ni dependencias especulativas.

---

## 4. TASK 3 — ESTADO

**NO INICIADA.**

El usuario solicitó crear checkpoint y detener antes de que el RED de Task 3 quedara persistido.

Verificación realizada al pausar:
- `tests/Integration/UI/ShellRoutesTest.php` NO existe en la rama.
- no hay código de shell autenticado de Task 3.
- no existe todavía `app/UI/routes.php`.
- no existe todavía `resources/views/layouts/app.php`.
- no existe todavía `resources/views/home.php`.
- no existe todavía `resources/views/account.php`.
- no existe todavía `assets/app.js`.

Por tanto, la reanudación debe empezar en **Task 3 / Step 1 — RED tests**, no en implementación.

---

## 5. SIGUIENTE PASO EXACTO AL REANUDAR

1. Confirmar que la rama activa sigue siendo `impl/f3-mobile-shell-visual-20261011` y que PR #4 sigue draft/no merge.
2. Ejecutar/verificar suite actual antes de tocar código si el HEAD cambió.
3. Crear `tests/Integration/UI/ShellRoutesTest.php`.
4. Cubrir primero en RED:
   - `/` instalado sin sesión -> `/login`;
   - shell semántico `header/main/nav`;
   - Inicio / Apps / Cuenta;
   - `<dialog>` nativo para Apps;
   - branding configurable y fallbacks;
   - permisos reales únicamente para Users + Appearance;
   - no mostrar Cartera/Solicitudes/Propiedades todavía;
   - `must_change_password` bloquea el shell;
   - `base_path` sin duplicación.
5. Ejecutar RED real.
6. Solo después implementar Task 3 mínimo.

---

## 6. GUARDRAILS DE REANUDACIÓN

- No merge sin aprobación explícita del usuario.
- Mantener PR #4 en draft hasta F3 CLOSED/VERIFIED.
- RED -> GREEN por tarea.
- No crear navegación para módulos futuros.
- No sidebar móvil.
- No nueva dependencia JS.
- No nueva tabla para F3.
- Converger el current-user resolver en Task 3 en vez de duplicarlo entre Auth y UI.
- Respetar KISS y CERO RUIDO antes de cerrar cada tarea.

---

## 7. RESUMEN DE RECUPERACIÓN

**Punto seguro de recuperación:** Task 2 GREEN.  
**Siguiente acción:** Task 3 RED.  
**Merge:** prohibido por ahora.  
**PR #4:** abierto, draft.  
**F3 global:** OPEN / PAUSED.
