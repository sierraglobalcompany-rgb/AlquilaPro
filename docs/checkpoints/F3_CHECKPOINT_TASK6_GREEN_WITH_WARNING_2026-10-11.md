# Alquila Pro — F3 Checkpoint — Task 6 Green con warning pendiente

**Fecha local:** 2026-10-11 (America/Bogota)  
**Repositorio:** `sierraglobalcompany-rgb/AlquilaPro`  
**Rama:** `impl/f3-mobile-shell-visual-20261011`  
**PR:** #4 — `F3: mobile shell + visual system`  
**Estado PR:** OPEN · DRAFT · NO MERGE  
**Base:** `main` @ `f4c42a75753a5527a01e50951e7cbdc15c203b9e`  
**HEAD funcional congelado antes de este checkpoint:** `808429da3ead81b6e3661065979f61d3c62072a3`

---

## 1. Motivo de este checkpoint

Se solicitó detener el trabajo antes de continuar para evitar otra interrupción del hilo.

Este checkpoint congela el estado real después de recuperar el trabajo posterior a la desconexión, completar Task 5, añadir los contratos de Task 6 y verificar el HEAD actual en CI.

**Regla de reanudación:** recuperar desde este archivo + HEAD real de PR #4 + CI; no reconstruir el estado desde memoria del chat.

---

## 2. Estado de F3 por tareas

### Task 1 — Branding base / requisitos runtime

**GREEN / verificada.**

- `Branding` reutiliza `settings`.
- MIME validado por bytes.
- logo máximo 2 MiB.
- almacenamiento fuera del webroot.
- nombres de archivo generados.
- `fileinfo` requerido.
- `+0` tablas y `+0` dependencias Composer.

### Task 2 — Shell visual de autenticación

**GREEN / verificada.**

- un único `View`.
- un layout auth compartido.
- un único `assets/app.css`.
- login/reset/cambio de contraseña convergidos en parciales.
- installer usa el mismo CSS sin depender de BD/branding instalado.

Checkpoint histórico anterior:

`docs/checkpoints/F3_CHECKPOINT_TASK2_GREEN_2026-10-11.md`

### Task 3 — Shell autenticado móvil

**GREEN / auditada.**

Incluye:

- `/` autenticado;
- `/account`;
- topbar;
- bottom navigation Inicio / Apps / Cuenta;
- launcher Apps con `<dialog>` nativo;
- `assets/app.js` mínimo;
- un único resolver `currentUser` compartido Auth/UI;
- `must_change_password` respetado;
- base path/subcarpeta soportado;
- cero rutas de módulos futuros.

Hallazgo de seguridad corregido durante auditoría:

- `resources/` estaba inicialmente fuera de la frontera Apache;
- RED: `97074d7826db8c6420617498fac825b5ed6c3602`;
- GREEN: `4025171c00af27ea25932289255fe08214915eaa`;
- se amplió una sola regla existente en `.htaccess`.

### Task 4 — Administración de usuarios dentro del shell

**GREEN / auditada.**

Se recuperó trabajo que había terminado durante una desconexión. No repetir.

Confirmado:

- `/admin/users` usa el shell F3;
- GET sigue protegido por `users.view`;
- creación/estado sigue protegida por `users.manage`;
- edición de permisos sigue protegida por `users.permissions`;
- autorización real permanece backend;
- cards responsivas, no tabla horizontal;
- renderer legacy eliminado;
- dependencia `views` sin uso eliminada.

### Task 5 — Apariencia + logo seguro

**GREEN / verificada en matriz PHP.**

RED previo:

- `9cec575913e3fced1b43cc3acfdc41d38226eebe` — contratos de Apariencia/logo;
- `82f8bc8dac085bf0895f9452af170fa01693dd47` — frontera CSRF de Apariencia.

El RED confirmó 89 tests totales con exactamente 8 fallos nuevos por rutas de Apariencia ausentes; F1/F2/F3 anteriores permanecían verdes.

Implementación mínima añadida:

- `GET /admin/appearance`;
- `POST /admin/appearance`;
- `GET /brand/logo`;
- vista `resources/views/appearance.php`;
- owner-only sin crear permiso nuevo;
- `Branding::current()/update()/logoFile()` reutilizados;
- color inválido => 422 sin mutación;
- PNG/JPEG/WebP seguros;
- SVG disfrazado y >2 MiB rechazados;
- logo almacenado fuera del webroot;
- `/brand/logo` no recibe path/filename del usuario;
- upload opcional con `UPLOAD_ERR_NO_FILE` tratado como ausencia de logo;
- Apariencia aparece en Apps únicamente para owner y solo después de existir su ruta real;
- auth shell y app shell muestran logo configurado con fallback textual.

Commits GREEN relevantes posteriores al RED:

- `79fca29a4adf5f21b224aeb748418bd2811a36e9`
- `2f5df433c485c80d1faa8f9ad6ed81ccf7e7e9e9`
- `63dc5386f1cab744e4a05b0738412cc87a399648`
- `6b954bad8d14a3f7829ce25c7827a3a333b0d010`
- `7e4b35d516ebffa09d66dd389bcf2ccf23d7a168`
- `b18ee448828c0a1f65336a0726a81d45780f1a94`
- `570f7e2aa5707829917fc1aa7b4bd779535e3113`
- `006a9484bcf3bc4c00b8fabd70ade5f4467ccb96`

CI de `006a9484…`:

- PHP 8.3 — PASS
- PHP 8.4 — PASS
- PHP 8.5 — PASS

### Task 6 — Frontera web + contratos de assets/accesibilidad

**GREEN funcional en CI, PERO NO CERRADA FORMALMENTE por warning PHPUnit pendiente.**

Contratos añadidos:

`tests/Unit/UI/AssetContractTest.php`

- CSS sin remote imports/font URLs;
- safe-area top/bottom;
- `prefers-reduced-motion`;
- foco visible;
- targets mínimos 44/48 px;
- JS usa `<dialog>` nativo;
- sin import/fetch/dependencia externa;
- assets sin etiquetas de módulos futuros.

`SecurityBoundaryTest` ahora también fija:

- directorios internos bloqueados, incluido `resources`;
- `assets` no debe quedar dentro de la regla de denegación.

Commits Task 6:

- `7428706c1af8f695a55ce91caba7f0b4b6ce6b8e` — asset contracts;
- `808429da3ead81b6e3661065979f61d3c62072a3` — assets públicos fuera del deny rule.

---

## 3. Evidencia CI del HEAD congelado

HEAD:

`808429da3ead81b6e3661065979f61d3c62072a3`

Workflow:

- `CI`
- run id: `38192875393`
- run number: `256`
- conclusión global: **SUCCESS**

Matriz:

- PHP 8.3 — PASS
- PHP 8.4 — PASS
- PHP 8.5 — PASS
- MariaDB 11.4 — servicio operativo

PHP 8.4 reportó:

```text
Tests: 94
Assertions: 458
Failures: 0
Errors: 0
Warnings: 1
```

**Importante:** aunque GitHub Actions marca SUCCESS, el plan F3 exige `0 warnings attributable to project code` para cerrar F3. Por tanto, este warning debe investigarse antes de `F3_CLOSED`.

No asumir que Task 6 está cerrada solo porque el workflow terminó SUCCESS.

---

## 4. Ley 1 — KISS

**PASS provisional Tasks 1–6. F3 todavía no cerrable.**

Delta arquitectónico confirmado hasta este punto:

- Composer dependencies: `+0`;
- DB migrations/tables: `+0`;
- Node/npm/build system: `+0`;
- nuevos permisos: `+0`;
- framework frontend: `+0`;
- una sola clase `Branding`;
- una sola clase `View`;
- un solo CSS;
- un JS de diálogo mínimo;
- una única fuente de branding en `settings`;
- logo fuera del webroot;
- sin media library;
- sin theme system;
- sin navigation service innecesario;
- sin sidebar móvil;
- sin SPA.

---

## 5. Ley 2 — Cero Ruido / Convergencia

**PASS provisional funcional, con limpieza documental pendiente antes del cierre.**

Ruido eliminado/evitado:

- renderer legacy de usuarios eliminado;
- `currentUser` duplicado evitado;
- enlace Apariencia no apareció hasta existir su ruta real;
- `resources/` integrado en una única regla Apache en vez de múltiples `.htaccess`;
- no se creó una segunda capa para branding;
- no se creó permiso `appearance.manage` innecesario;
- no hay destinos futuros ficticios.

Ruido documental detectado pendiente de eliminar durante Task 7:

- `docs/checkpoints/F3_WIP_TASK5_RED_2026-10-11.md` es redundante frente a checkpoints canónicos;
- revisar también los checkpoints temporales de F3 y conservar solo los que aporten continuidad real cuando exista `F3_CLOSED`.

Aplicar al cierre:

**ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AÑADIR**.

---

## 6. Pendientes exactos antes de F3 CLOSED

### A. Investigar el único PHPUnit warning

El HEAD actual tiene:

- 94 tests;
- 458 assertions;
- 1 warning.

Reanudar leyendo los logs del run `38192875393` y aislar la causa antes de modificar producción.

No silenciar warnings globalmente ni degradar PHPUnit config para lograr verde artificial.

### B. QA visual real obligatorio

El plan exige revisión renderizada en al menos:

- 360×800;
- 390×844;
- 412×915;
- tablet;
- desktop ancho.

Verificar:

- no overflow horizontal;
- bottom nav no tapa contenido;
- Apps abre/cierra;
- navegación por teclado;
- foco visible;
- safe area;
- zoom 200%;
- no sidebar;
- no módulos futuros.

**No marcar este gate por inspección de código solamente.**

### C. Sweep Cero Ruido

Buscar explícitamente:

- vistas/assets no usados;
- helpers duplicados;
- CSS muerto;
- JS muerto;
- settings redundantes;
- checkpoints temporales;
- placeholders de navegación;
- duplicación de branding.

### D. Auditoría final de delta

Registrar en `F3_CLOSED`:

```text
ADDED
REMOVED
MERGED_OR_CONVERGED
DEPENDENCIES_DELTA
DB_SCHEMA_DELTA
PUBLIC_ROUTES_DELTA
TESTS_ASSERTIONS
VISUAL_QA
LAW_1_KISS
LAW_2_ZERO_NOISE
```

### E. Solo después

- crear `docs/checkpoints/F3_CLOSED_2026-10-11.md`;
- actualizar `README.md`;
- ejecutar CI del HEAD documental exacto;
- actualizar PR #4;
- pasar de Draft a Ready únicamente cuando F3 esté CLOSED/VERIFIED;
- **NO MERGE sin aprobación explícita del usuario**.

---

## 7. Estado del PR

PR #4 permanece:

- OPEN
- DRAFT
- NO MERGE

No cambiar este estado durante esta pausa.

---

## 8. Estado congelado resumido

```text
PROJECT: Alquila Pro
PHASE: F3 — Mobile Shell + Visual System
BRANCH: impl/f3-mobile-shell-visual-20261011
PR: #4 OPEN / DRAFT / NO MERGE
BASE: main @ f4c42a75753a5527a01e50951e7cbdc15c203b9e
PRE_CHECKPOINT_HEAD: 808429da3ead81b6e3661065979f61d3c62072a3
TASK_1: GREEN
TASK_2: GREEN
TASK_3: GREEN + AUDITED
TASK_4: GREEN + AUDITED
TASK_5: GREEN + MATRIX PASS
TASK_6: FUNCTIONAL GREEN + MATRIX PASS / FORMAL CLOSE BLOCKED BY 1 WARNING
PHP_8_3: PASS
PHP_8_4: PASS
PHP_8_5: PASS
TESTS: 94
ASSERTIONS: 458
FAILURES: 0
ERRORS: 0
WARNINGS: 1
LAW_1_KISS: PASS PROVISIONAL
LAW_2_ZERO_NOISE: PASS PROVISIONAL
VISUAL_QA: PENDING
F3_CLOSED: NO
NEXT: INVESTIGATE WARNING -> VISUAL QA -> ZERO NOISE SWEEP -> F3 CLOSED
```

---

## 9. STOP RULE

Este checkpoint fue solicitado explícitamente para detener la ejecución.

**No investigar ni corregir el warning, no iniciar QA visual, no limpiar checkpoints y no cerrar F3 en este turno después de guardar este archivo.**
