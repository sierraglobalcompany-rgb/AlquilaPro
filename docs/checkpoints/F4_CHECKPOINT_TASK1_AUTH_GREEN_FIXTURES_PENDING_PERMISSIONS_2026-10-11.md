# F4 CHECKPOINT — Task 1: Auth GREEN; fixtures restantes + permisos pendientes

Fecha local: 2026-10-11
Proyecto: Alquila Pro
Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
Rama: `impl/f4-properties-catalog-20261011`
PR: #7 — `F4: Properties, units and public catalog`

## 1. ESTADO CONGELADO

F4 sigue ABIERTA.

Task 1 sigue ABIERTA.

No avanzar a Task 2 hasta cerrar Task 1 con GREEN completo.

SHA funcional actual antes de este checkpoint:

`6453c4e58ad634e506ad6fb741a93a880bc328c6`

Commit:

`test(f4): isolate auth fixtures from F4 tables`

El branch estaba exactamente en ese SHA al iniciar este checkpoint.

## 2. QUÉ YA EXISTE DE TASK 1

Ya está escrita y versionada la migración:

`database/migrations/004_create_properties_units_media.sql`

Tablas F4 previstas por Task 1:

- `properties`
- `units`
- `unit_media`

La suite focal `PropertiesSchemaTest` está GREEN y confirmó en CI que:

- se crean solamente las tres tablas F4 esperadas;
- `properties.code` y `units.public_key` conservan unicidad;
- permanecen las foreign keys requeridas;
- no se agregó un booleano paralelo de disponibilidad.

## 3. CAUSA RAÍZ DEL CI ROJO YA INVESTIGADA

El problema principal NO es el SQL de F4.

La migración `004` introduce foreign keys nuevas. Varias suites F1/F2/F3 continuaban limpiando la BD con la lista histórica:

`settings`, `users`, `schema_migrations`

Al conservar `properties`, `units` o `unit_media` de pruebas anteriores, la siguiente ejecución de migrations intentaba volver a crear F4 sobre estado residual y `MigrationRunner` terminaba reportando:

`No fue posible aplicar la migración 004_create_properties_units_media.sql.`

La hipótesis fue validada con una corrección mínima en las suites Auth.

## 4. CORRECCIÓN YA APLICADA Y VALIDADA

En SHA `6453c4e58ad634e506ad6fb741a93a880bc328c6` se amplió el reset de BD a:

`unit_media`, `units`, `properties`, `settings`, `users`, `schema_migrations`

en estas suites:

- `tests/Integration/Auth/AuthRoutesTest.php`
- `tests/Integration/Auth/AuthSchemaTest.php`
- `tests/Integration/Auth/AuthServiceTest.php`
- `tests/Integration/Auth/CsrfRoutesTest.php`

`UserServiceTest.php` ya tenía antes del commit el reset F4 completo y foreign-key checks controlados.

Resultado observado en CI run #296 / PHP 8.3:

Todas estas suites Auth quedaron GREEN:

- Auth Routes: 7/7 GREEN
- Auth Schema: GREEN
- Auth Service: 6/6 GREEN
- Csrf Routes: 5/5 GREEN

Esto confirma la causa raíz del aislamiento de fixtures.

## 5. CI ACTUAL — TODAVÍA ROJO

Workflow run observado:

- CI run: `38197258365`
- run number: `296`
- head SHA: `6453c4e58ad634e506ad6fb741a93a880bc328c6`
- PHP 8.3: FAILURE
- PHP 8.4: FAILURE
- PHP 8.5: FAILURE

PHP 8.3 reportó:

- Tests: 97
- Assertions: 284
- Errors: 29
- Failures: 2

Los errores restantes por migration `004` se concentran en fixtures que TODAVÍA usan la lista antigua.

## 6. FIXTURES QUE FALTAN CORREGIR

Aplicar exactamente el mismo reset explícito, sin crear nueva infraestructura, en:

1. `tests/Integration/Foundation/AppBootstrapTest.php`
2. `tests/Integration/Foundation/InstallerTest.php`
3. `tests/Integration/UI/AppearanceRoutesTest.php`
4. `tests/Integration/UI/AuthVisualTest.php`
5. `tests/Integration/UI/BrandingTest.php`
6. `tests/Integration/UI/ShellRoutesTest.php`

Cambio mínimo esperado en cada fixture que corresponda:

```php
foreach (['unit_media', 'units', 'properties', 'settings', 'users', 'schema_migrations'] as $table) {
    $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
}
```

Mantener el orden hijo → padre de F4.

No introducir helper genérico, trait, framework de fixtures ni cambios de runtime en esta Task 1. Se evaluó esa opción y se descartó por KISS: para el alcance actual el patrón explícito existente es menor y más auditable.

## 7. RED FUNCIONAL LIMPIO QUE DEBE QUEDAR DESPUÉS

Después de corregir los seis fixtures anteriores, el RED funcional esperado es solamente el de permisos F4 en `UserServiceTest`.

CI run #296 mostró:

`Owner bypasses permissions and sanitizer keeps only known unique values` → FAILURE

La causa es que `app/Auth/Permissions.php` todavía no conoce:

- `properties.view`
- `properties.manage`

Task 1 exige agregar únicamente esos dos permisos al conjunto conocido existente.

No agregar permisos de catálogo, media, aplicaciones, tenants ni futuros módulos en esta Task.

## 8. ORDEN OBLIGATORIO AL RETOMAR

Retomar exactamente así:

1. Corregir SOLO los seis fixtures pendientes.
2. Commit independiente de aislamiento de fixtures.
3. Ejecutar/observar CI.
4. Confirmar que desaparecen los errores de migration `004`.
5. Confirmar que el único RED funcional restante corresponde a `properties.view/manage`.
6. Modificar SOLO `app/Auth/Permissions.php` para añadir esos dos permisos.
7. Ejecutar focal:
   - `PropertiesSchemaTest`
   - `UserServiceTest`
8. Ejecutar suite completa.
9. Exigir PHP 8.3 / 8.4 / 8.5 GREEN.
10. Recién entonces auditar Ley KISS + Ley Cero Ruido y cerrar Task 1.
11. Crear checkpoint Task 1 CLOSED antes de Task 2.

## 9. GUARDRAILS

No hacer durante esta reanudación:

- no implementar repositories/services/routes F4 todavía;
- no abrir Task 2 antes de GREEN completo de Task 1;
- no cambiar `MigrationRunner` para ocultar el problema de fixtures;
- no añadir tablas nuevas;
- no añadir dependencias Composer/NPM;
- no crear capas genéricas de test innecesarias;
- no modificar F1/F2/F3 funcionalmente;
- no mergear PR #7 sin aprobación explícita del usuario;
- no mostrar módulos futuros como funcionales.

## 10. LEYES

### Ley KISS

Estado provisional Task 1: EN CUMPLIMIENTO.

La corrección elegida mantiene el patrón explícito existente y evita abstracciones nuevas para una lista de seis tablas.

### Ley Cero Ruido / Convergencia

Orden aplicado:

`ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`

Se rechazó un helper de fixtures porque en este alcance agregaba una abstracción sin suficiente beneficio. Se conserva una única convención de limpieza explícita en todas las suites que ejecutan las migrations completas.

## 11. IMPORTANTE SOBRE EL ÚLTIMO INTENTO INTERRUMPIDO

Antes de este checkpoint se había comenzado a preparar en memoria/blob una corrección para los fixtures Foundation/UI, pero NO se movió el branch después del SHA `6453c4e58ad634e506ad6fb741a93a880bc328c6`.

Por tanto, al retomar NO asumir que `AppBootstrapTest`, `InstallerTest`, `AppearanceRoutesTest`, `AuthVisualTest`, `BrandingTest` o `ShellRoutesTest` ya están corregidos. Verificar el branch y aplicar el cambio completo de forma controlada.

## 12. PUNTO EXACTO DE REANUDACIÓN

Comenzar por:

`tests/Integration/Foundation/AppBootstrapTest.php`

Luego seguir con los otros cinco fixtures listados, sin tocar todavía `Permissions.php`.

Después medir CI y continuar RED → GREEN.

---

Este checkpoint es el punto de continuidad autoritativo para Task 1 mientras F4 siga abierta.