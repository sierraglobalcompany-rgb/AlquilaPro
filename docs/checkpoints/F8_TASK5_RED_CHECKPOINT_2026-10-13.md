# AlquilaPro — F8 Task 5 RED checkpoint — 2026-10-13

## Estado ejecutivo

- Fase: `F8 — Inquilino + lease + portal usando auth único`.
- Rama: `impl/f8-tenant-lease-portal-20261013`.
- Estado: `TASK 4 GREEN / VERIFIED` + `TASK 5 RED / OPEN`.
- Ley KISS: vigente.
- Ley Cero Ruido / Convergencia: vigente.
- No merge.
- No iniciar Task 6 ni portal tenant hasta cerrar Task 5 GREEN.

## Punto exacto de reanudación

HEAD funcional antes del RED de Task 5:

`f6a3a133cf07b01bf8d29df720f5cfc810c052a9`

Commit RED de Task 5:

`f038dc0dc5dbe0882f2b78159fd06b171519ef0c`

Mensaje:

`test(f8): define admin lease route contract`

Al retomar, verificar primero que la rama siga en este commit o descienda limpiamente de él.

## Task 4 — GREEN / VERIFIED

Task 4 cerró la autoridad entre lease activo y `units.status` sin crear una segunda fuente de estado.

Implementado:

- `LeaseService::end()` finaliza un lease activo una sola vez y conserva contrato, usuario y unidad.
- `PropertyService::updateUnit()` impide sacar una unidad de `occupied` mientras exista un lease activo.
- La publicación de una unidad con lease activo queda bloqueada por esa misma autoridad.
- Un lease finalizado no libera automáticamente la unidad; vuelven a aplicar las transiciones manuales ya existentes.

Corrección adicional demostrada por el RED:

- F4 pasaba `is_public=false` como booleano directo a PDO/MariaDB en `updateUnit()`.
- Con prepared statements nativos, ese borde fallaba en los nuevos casos F8.
- Se normalizó únicamente en el borde SQL a `0/1`.
- No se añadió nueva arquitectura, tabla, estado ni servicio.

Commit GREEN final de Task 4:

`f6a3a133cf07b01bf8d29df720f5cfc810c052a9`

CI:

`38424014221`

Resultado:

- PHP 8.3: PASS
- PHP 8.4: PASS
- PHP 8.5: PASS
- PHP 8.3: `297 tests / 2426 assertions / 0 failures / 0 errors`

Conclusión Task 4:

`GREEN / VERIFIED`

## Task 5 — RED / OPEN

Archivo RED creado:

`tests/Integration/Leases/LeaseAdminRoutesTest.php`

Contrato funcional exigido por el RED:

1. `POST /admin/contracts/{id}/lease`
   - requiere login;
   - respeta `must_change_password`;
   - requiere `contracts.manage`;
   - usa CSRF global;
   - respeta `base_path`;
   - activa únicamente desde contrato finalizado;
   - muestra la contraseña temporal solo en la respuesta privada inmediata;
   - `Cache-Control: private, no-store`;
   - la contraseña no reaparece al volver al detalle.

2. `POST /admin/leases/{id}/end`
   - mismas fronteras de auth/permission/CSRF;
   - finaliza lease;
   - redirige al contrato propietario respetando `base_path`.

3. El detalle contractual existente debe mostrar contexto de lease sin crear CRUD administrativo paralelo.

CI RED:

`38424737650`

PHP 8.3:

`300 tests / 2434 assertions / 3 failures`

Los tres fallos son los esperados por funcionalidad ausente:

- ruta de activación aún no registrada (`404` tratado por error global → `500`);
- detalle de contrato finalizado aún no muestra `Activar arrendamiento`;
- ruta de finalización aún no registrada (`404` tratado por error global → `500`).

No se detectaron regresiones fuera de Task 5.

Conclusión Task 5:

`RED VERIFIED / GREEN NOT IMPLEMENTED`

## Trabajo NO integrado después del RED

Durante la preparación del GREEN se generaron blobs temporales mediante GitHub API para:

- posible `app/Leases/routes.php`;
- posible vista de credencial temporal.

Esos blobs **no fueron conectados al árbol ni al branch** y no forman parte del estado del repositorio. Deben ignorarse al retomar; implementar nuevamente desde el RED y el estado real del branch.

Esto preserva TDD y evita arrastrar ruido.

## Siguiente acción única

Al próximo `continua`:

1. Verificar branch HEAD y CI RED.
2. Implementar **solo Task 5 GREEN mínimo**:
   - registrar `LeaseService` reutilizando `UserService` existente;
   - un único `app/Leases/routes.php` para las dos mutaciones;
   - enriquecer el GET de detalle contractual con `LeaseService::findByContract()`;
   - añadir únicamente los bloques visuales necesarios al detalle existente;
   - una vista efímera mínima para mostrar una sola vez la credencial temporal.
3. Ejecutar CI 8.3/8.4/8.5.
4. No abrir Task 6 hasta que Task 5 esté GREEN / VERIFIED.

## Guardrails KISS / Cero Ruido para reanudar

Prohibido en Task 5:

- crear tabla adicional;
- crear `TenantAuthService`, segundo login, segunda sesión o segundo CSRF;
- crear CRUD/index administrativo de leases;
- crear permisos nuevos si `contracts.view/manage` cubren el flujo aprobado;
- almacenar contraseña temporal en texto plano;
- crear estado paralelo a `leases.ended_at` o `units.status`;
- iniciar portal tenant o Task 6 antes de cerrar Task 5.

Arquitectura objetivo sigue siendo directa:

`Contrato finalizado → LeaseService → users(role=tenant) + leases → unidad occupied → auth existente`.
