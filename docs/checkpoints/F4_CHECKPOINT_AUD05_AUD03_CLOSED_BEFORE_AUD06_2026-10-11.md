# ALQUILA PRO — F4 CHECKPOINT

Fecha local: 2026-10-11 (America/Bogota)
Proyecto: Alquila Pro
Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
PR: #7 — `F4: Properties, units and public catalog`
Estado PR: OPEN / DRAFT / NOT MERGED
Merge autorizado: NO

---

## 1. ESTADO GIT CANÓNICO

- Base branch: `main`
- Base SHA: `940ebfae25a05d1589fe1ab5ceeaf667c2d08345`
- Branch F4: `impl/f4-properties-catalog-20261011`
- Product HEAD antes de este checkpoint documental: `1907929eae1ac53e154e711da1c4ae1e16d38a63`
- Checkpoint HEAD: este commit documental.
- PR mergeable al momento del checkpoint: YES
- No se hizo merge.
- No se abrió fase F5.
- No se agregaron dependencias Composer/npm.
- No se agregó nueva tabla, permiso, servicio, DTO, repository, framework ni arquitectura paralela.

Este checkpoint es documental. Si el HEAD del branch queda por encima de `1907929e...` solo por este archivo, el **product HEAD** sigue siendo `1907929e...`.

---

## 2. MÉTODO Y LEYES QUE SIGUEN VIGENTES

Método obligatorio:

`SCOPE → PRE-KISS → SPEC/DESIGN → PLAN → RED → VERIFY RED → MINIMAL GREEN → VERIFY GREEN → CONVERGE → QA → TWO LAWS GATE → CHECKPOINT → REVIEW → MERGE`

Leyes:

1. **KISS**: simple, optimizado, eficiente, sin arquitectura innecesaria.
2. **Cero Ruido / Convergencia**: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`.

No resolver huecos agregando capas, tablas o abstracciones si una validación o convergencia pequeña basta.

---

## 3. TRABAJO CERRADO EN ESTA SESIÓN

### F4-AUD-05 — tipo de unidad inmutable

Estado: **CLOSED / GREEN / VERIFIED**.

Problema confirmado:
- `apartment ↔ parking` podía intentar cambiarse en `updateUnit()`.
- La base terminaba rechazándolo lateralmente con error PDO, pero no existía una regla de dominio explícita.

RED útil:
- commit test: `c1513ccc6cb1de334ce50670d0f8f4d8b9517dbf`
- CI RED: `38204781297`
- resultado: 125 tests / 692 assertions / 2 failures
- causa exacta: mensaje PDO genérico en ambos cambios de tipo.

GREEN mínimo:
- commit: `46f25bdf4d135a31aaddd0e0251a1c2e307dfaf1`
- archivo productivo tocado: `app/Properties/PropertyService.php`
- cambio: validación explícita que rechaza cambiar `type` de una unidad ya creada.
- mensaje canónico: `El tipo de la unidad no se puede cambiar.`

Verificación:
- CI: `38204995074`
- PHP 8.3: PASS
- PHP 8.4: PASS
- PHP 8.5: PASS

Ruling vigente:
- `type` de unidad es inmutable después de crearla.
- No crear sistema de transiciones ni historial de tipo.

---

### F4-AUD-03 — salida pública por allowlist

Estado: **CLOSED / GREEN / VERIFIED**.

Problema confirmado:
- `normalizePublicUnit()` partía de la entidad privada completa y luego hacía `unset()`.
- Eso exponía campos internos como `id` y hacía la privacidad frágil ante campos futuros.

RED:
- commit test: `f4d09ae185a1871bbdca057ee06c1b551059dce4`
- CI RED: `38205129776`
- resultado focal observado: 125 tests / 684 assertions / 1 failure
- causa exacta: salida pública todavía contenía la clave interna `id`.

GREEN mínimo:
- commit productivo: `1907929eae1ac53e154e711da1c4ae1e16d38a63`
- archivo: `app/Properties/PropertyService.php`
- cambio: `normalizePublicUnit()` ahora construye una **allowlist explícita**.
- se eliminó el patrón `normalizeUnit() + unset(...)` para la proyección pública.

Campos públicos permitidos actualmente:
- `public_key`
- `type`
- `location_label`
- `monthly_price`
- `description`
- `requirements`
- `tower`
- `floor`
- `area_m2`
- `bedrooms`
- `bathrooms`
- `administration_fee`
- `parking_level`
- `dimensions`
- `vehicle_type`
- `property_name`
- `city`
- `sector`

Campos que NO deben salir por esa proyección:
- `id`
- `property_id`
- `linked_apartment_id`
- `is_public`
- `private_notes`
- `unit_number`
- `registry_number`
- `created_at`
- `updated_at`
- otros IDs/metadatos internos futuros salvo ruling explícito.

Verificación GREEN:
- CI: `38205263045`
- PHP 8.3: PASS
- PHP 8.4: PASS
- PHP 8.5: PASS

---

## 4. INCIDENCIA DE TEST CORREGIDA DURANTE AUD-05

Hubo un primer test de inmutabilidad que produjo falso GREEN porque usaba `try/catch RuntimeException` y podía capturar indirectamente el fallo de PHPUnit.

Esto se corrigió antes de tocar producción:
- el test pasó a usar `expectException(RuntimeException::class)`;
- después se endureció con `expectExceptionMessage(...)` para exigir la regla semántica correcta.

No usar como evidencia el falso GREEN inicial. La evidencia válida de AUD-05 es el RED semántico de CI `38204781297` y el GREEN `38204995074`.

---

## 5. ESTADO EXACTO DE AUD-06

### F4-AUD-06 — selector de apartamento vinculado en parqueadero

Estado: **NOT STARTED IN CODE / RED NOT COMMITTED**.

Hueco ya identificado:
- `resources/views/unit_detail.php` espera `$propertyUnits` para ofrecer apartamentos vinculables.
- la ruta/controlador actual `renderUnit()` no entrega esa colección.

Comportamiento que debe demostrar el próximo RED:
1. un parqueadero muestra como opción los apartamentos de **su misma propiedad**;
2. el apartamento actualmente vinculado aparece seleccionado;
3. apartamentos de otra propiedad NO aparecen;
4. no crear servicio nuevo ni query layer nueva si `PropertyService::listUnitsForProperty()` ya resuelve el dato.

IMPORTANTE:
- se inició la preparación del test, pero el usuario pidió checkpoint antes de escribirlo;
- el intento de update quedó interrumpido y **NO llegó a modificar GitHub**;
- el HEAD real antes de este checkpoint siguió siendo `1907929e...`.

Próximo paso exacto al reanudar:

`AUD-06 RED HTTP focal → verificar fallo por ausencia de propertyUnits → GREEN mínimo pasando listUnitsForProperty(property_id) a la vista → matriz PHP 8.3/8.4/8.5`.

---

## 6. GATES F4 AÚN ABIERTOS SEGÚN HANDOFF COMPLEMENTARIO

Cerrados en esta sesión:
- AUD-05 — tipo de unidad inmutable.
- AUD-03 — allowlist pública.

Ya venían resueltos o parcialmente resueltos antes de esta sesión, pero F4 no se considera cerrada hasta auditar el conjunto.

Pendientes relevantes por revisar/cerrar:
- AUD-06 — `propertyUnits` en detalle de parqueadero.
- AUD-07 — launcher `Propiedades` visible solo con autorización correcta.
- AUD-08 — regresión CSRF focal en POST `/admin/properties`.
- AUD-09 — formularios contextuales apartment/parking con `type` no editable en edición.
- AUD-10 — decidir/converger conteos en tarjetas de propiedad según SPEC.
- AUD-11 / AUD-12 — lifecycle de archivos solo si se reproduce riesgo real y sin sobrearquitectura.
- Task 4 no debe marcarse CLOSED hasta completar su gate y matriz.
- Task 5 de F4 sigue NOT STARTED; **Task 5 de F4 ≠ fase F5**.

---

## 7. GUARDRAILS ABSOLUTOS AL REANUDAR

Mientras F4 siga abierta:
- NO abrir fase F5.
- NO candidate/application/lead/tenant/contracts/receivables/payments/tickets/notifications/WhatsApp.
- NO tabla de catálogo.
- NO segundo modelo de apartamentos/parqueaderos.
- NO segundo auth.
- NO segundo precio/availability.
- NO `catalog.*`, `units.*` o `media.manage` permissions nuevas.
- NO SPA/frontend framework.
- NO ORM/repository/DTO por este hueco.
- NO video upload/transcode.
- NO merge sin autorización humana explícita.

Permisos F4 esperados siguen siendo solo:
- `properties.view`
- `properties.manage`

más los permisos previos existentes del sistema.

---

## 8. DEUDA DOCUMENTAL VISIBLE

El body del PR #7 todavía dice `Current task: Task 1 — Schema + Permissions.` aunque el trabajo real está mucho más avanzado.

No corregirlo como parche aislado ahora si no aporta al gate inmediato. Debe convergerse antes de pasar PR a Ready / cerrar F4, junto con README/checkpoints temporales.

---

## 9. ESTADO DE CI CANÓNICO AL PARAR

Último GREEN de producto:
- product SHA: `1907929eae1ac53e154e711da1c4ae1e16d38a63`
- workflow run: `38205263045`
- PHP 8.3: SUCCESS
- PHP 8.4: SUCCESS
- PHP 8.5: SUCCESS

No afirmar cierre de Task 4 completo todavía.

---

## 10. PUNTO DE REANUDACIÓN CANÓNICO

Al recibir `continua`:

1. verificar PR #7, branch, base, HEAD y CI;
2. confirmar que cualquier HEAD por encima de `1907929e...` sea solo este checkpoint documental;
3. NO reabrir AUD-05 ni AUD-03 salvo evidencia nueva;
4. abrir **AUD-06** con RED HTTP focal;
5. GREEN mínimo reutilizando `listUnitsForProperty()`;
6. matriz PHP 8.3/8.4/8.5;
7. seguir AUD-07 → AUD-08 → AUD-09;
8. solo después evaluar cierre real de Task 4;
9. no abrir Task 5 hasta Task 4 CLOSED.

---

## 11. TWO LAWS SNAPSHOT

KISS: PASS para los cambios cerrados en esta sesión.

Cero Ruido: PASS para AUD-05 y AUD-03:
- no tablas nuevas;
- no dependencias nuevas;
- no permisos nuevos;
- no clases nuevas;
- no abstracciones nuevas;
- una regla explícita de dominio;
- una proyección pública por allowlist.

Task 4 global: **NOT CLOSED**.
F4 global: **OPEN**.
PR #7: **DRAFT / NOT MERGED**.
