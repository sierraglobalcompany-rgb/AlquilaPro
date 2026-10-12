# AlquilaPro — F4 CHECKPOINT PAUSED

**Fecha:** 2026-10-12  
**Motivo:** pausa solicitada por el usuario.  
**Estado:** detener cambios de producto inmediatamente después de este checkpoint.

## 1. REPOSITORIO Y GOBERNANZA

- Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
- Rama: `impl/f4-properties-catalog-20261011`
- PR: `#7` — **OPEN / DRAFT / NOT MERGED**
- Base: `main`
- Base SHA: `940ebfae25a05d1589fe1ab5ceeaf667c2d08345`
- HEAD de código antes de este checkpoint: `54151e6c0c67f793c49b26cb29e5ebe7b18745ba`
- Merge: **PROHIBIDO sin aprobación humana explícita del usuario**.
- No abrir F5 mientras F4 siga abierto.

### Leyes de trabajo activas

1. KISS: simple, optimizado, eficiente, sin arquitectura innecesaria.
2. Cero Ruido: `ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR`.
3. TDD: `SCOPE → PRE-KISS → SPEC/DESIGN → PLAN → RED → VERIFY RED → MINIMAL GREEN → VERIFY GREEN → CONVERGE → QA → TWO LAWS GATE → CHECKPOINT → REVIEW → MERGE`.
4. No crear tablas, servicios, permisos, dependencias o abstracciones si el contrato puede resolverse con lo existente.
5. `is_public` es intención administrativa; la visibilidad pública se deriva en vivo de la publicabilidad canónica.

## 2. ESTADO FUNCIONAL RECUPERADO DE F4

F1–F3 permanecen cerradas históricamente. F4 continúa abierta.

Task 4 quedó funcionalmente cerrada antes de iniciar Task 5, incluyendo los guardrails auditados: publicabilidad viva, creación bloqueada en propiedad inactiva, tipo de unidad inmutable, allowlist pública, contexto parking/apartamento, launcher, CSRF, campos contextuales y conteos de propiedad.

### Publicabilidad canónica

Una unidad solo es visible públicamente cuando se cumplen simultáneamente:

```text
property.is_active
&& unit.status == available
&& unit.is_public == 1
&& monthly_price > 0
&& description no vacía
&& requirements no vacíos
&& existe al menos una foto
```

No introducir un segundo flag de disponibilidad ni un `PublicationEngine`.

## 3. TASK 5 — TRABAJO REALIZADO EN ESTA SESIÓN

### 3.1 Catálogo público `/catalog` — GREEN VERIFICADO

Implementación mínima:

- `GET /catalog`
- filtros `type`, `city`, `sector`
- reutiliza `PropertyService::listPublicUnits(...)`
- vista pública `resources/views/public_catalog.php`
- sin `CatalogService`, sin tabla nueva, sin permiso nuevo, sin dependencia nueva
- privacidad protegida por allowlist pública
- subfolder/base path cubierto

Commits principales:

- `0555edad85dc7f096d399925739ada58343586aa` — GREEN de `/catalog`

CI:

- Run `38208040133`
- PHP 8.3 / 8.4 / 8.5: **PASS**

### 3.2 Detalle público `/catalog/{public_key}` — GREEN VERIFICADO

RED:

- `c9a9eafd8af0d62fb709eb7e5a4bc4a985fe579b`
- 142 tests / 799 assertions / 3 fallos esperados porque la ruta aún no existía.

GREEN:

- `44c6ea95cefba5e37bdb11996a6190208a1105eb`
- `GET /catalog/{publicKey}`
- `findPublicUnit()` como puerta de publicabilidad
- 404 para clave inválida, inexistente o unidad ya no publicable
- datos privados no renderizados
- salida escapada contra XSS
- enlaces de video HTTPS únicamente y `rel="noopener noreferrer"`
- sin CTA `Aplicar`
- sin exponer fotos públicas todavía: esa frontera queda para Task 6

CI:

- Run `38208474441`
- PHP 8.3 / 8.4 / 8.5: **PASS**

### 3.3 Landing pública — último gate de Task 5

Contrato requerido:

- el resumen de disponibilidad solo aparece si existe inventario actualmente publicable;
- una unidad completa sin foto no activa el resumen;
- al añadir foto aparece el resumen;
- al pasar la unidad a `occupied`, el resumen desaparece;
- no crear contador, caché ni query de disponibilidad paralela: reutilizar `listPublicUnits()`.

RED:

- `8e1fd60ec748aa0bc70998c052c4b8d79c3eae2c`
- CI run `38208622745`
- RED válido: 143 tests / 825 assertions / 1 fallo esperado, porque la landing aún no mostraba el resumen.

GREEN convergente aplicado:

- `public_landing.php` muestra conteo solo cuando `availabilityCount > 0`.
- F4 pasó a ser dueño de `GET /`.
- se eliminó el root supersedido de `app/UI/routes.php` para evitar dos dueños del mismo contrato.
- el conteo reutiliza `PropertyService::listPublicUnits(...)`.

Primer GREEN candidato:

- `1ff3dbe5d96cf306d91eb06bc1daa58a28e08d14`
- CI run `38208772160`
- Resultado: **FAIL** en 8.3 / 8.4 / 8.5.
- Composer e infraestructura PASS.
- PHPUnit: 143 tests / 809 assertions / 4 fallos.
- Causa única real: `ArgumentCountError` en `GET /`, porque se llamó `listPublicUnits()` sin sus tres parámetros obligatorios.
- No fue un fallo del contrato de publicabilidad.

Corrección mínima:

- commit de código actual: `54151e6c0c67f793c49b26cb29e5ebe7b18745ba`
- cambio único: `listPublicUnits(null, null, null)` desde la landing.
- no se cambió el servicio ni su contrato.

## 4. CI EN CURSO AL MOMENTO DE LA PAUSA

Run actual:

- CI `38208938804`
- HEAD probado: `54151e6c0c67f793c49b26cb29e5ebe7b18745ba`

Estado observado justo antes de crear este checkpoint:

- PHP 8.4: Composer PASS, dependencias PASS, PHPUnit **in progress**.
- PHP 8.5: Composer PASS, dependencias PASS, PHPUnit **in progress**.
- PHP 8.3: runner activo, setup en progreso.

**Importante:** Task 5 NO debe declararse CLOSED todavía. Primero hay que leer el resultado final de `38208938804`.

## 5. SIGUIENTE PASO EXACTO AL REANUDAR

No modificar código antes de hacer esto:

1. Consultar CI run `38208938804` para el SHA `54151e6c...`.
2. Si PHP 8.3 / 8.4 / 8.5 están todos PASS:
   - registrar Task 5 como `CLOSED / VERIFIED`;
   - opcionalmente crear checkpoint de cierre de Task 5 si la secuencia documental lo requiere;
   - revisar Zero Noise de routing antes de abrir/cerrar F4.
3. Si algún job falla:
   - leer el log exacto;
   - corregir solo la causa comprobada;
   - repetir matriz completa.
4. Solo después de Task 5 GREEN completo, abrir Task 6 con RED.

## 6. TASK 6 — BLOQUEO DE DISEÑO QUE DEBE RESOLVERSE ANTES DEL RED

Existe una contradicción de privacidad heredada:

- la superficie pública no debe exponer IDs internos de base de datos;
- el plan histórico proponía `/catalog/media/{id}`, donde `{id}` sería `unit_media.id`.

No implementar esa ruta ciegamente.

Ruling KISS recomendado para Task 6:

- mantener `public_key` de la unidad como identificador público;
- resolver fotos públicamente sin revelar `unit_media.id`, por ejemplo mediante posición/orden público bajo la unidad (`/catalog/{publicKey}/photo/{position}`) o contrato equivalente simple;
- la resolución real ocurre server-side;
- seguir validando publicabilidad viva antes de entregar bytes;
- evitar cache positivo prolongado porque una unidad debe desaparecer inmediatamente al despublicarse; V1 preferible `no-store`/revalidación estricta.

Task 6 debe limitarse a la frontera de bytes de fotos; no mezclar leads, F5 ni nuevas capas.

## 7. DEUDA DE CONVERGENCIA / CERO RUIDO AÚN ABIERTA

Antes de cerrar F4 revisar:

- actualmente existen `app/Properties/routes.php` y `app/Properties/public_routes.php`.
- el PLAN original esperaba una única superficie de rutas F4.
- decidir por Cero Ruido si conviene convergerlas en un solo archivo o documentar explícitamente por qué la separación pública/privada es más simple; no mantener duplicación accidental.
- PR #7 conserva un body antiguo que todavía dice `Task 1`; actualizarlo solo cuando corresponda preparar review/Ready.
- `/health` aún reporta versión histórica `0.1.0-f1`; es deuda documental/técnica menor, no crear servicio/tabla para resolverla.

## 8. PROHIBICIONES AL REANUDAR

- NO mergear PR #7 sin aprobación explícita.
- NO abrir fase F5.
- NO añadir `Aplicar`.
- NO crear `CatalogService`, `PublicationEngine`, repositorios, DTOs, SPA, ORM o tabla extra.
- NO crear un segundo auth, precio o disponibilidad.
- NO exponer dirección privada, notas, teléfonos, matrículas, IDs internos o timestamps al público.
- NO asumir que Task 5 está cerrado hasta verificar CI `38208938804`.

## 9. ESTADO FINAL DE PAUSA

```text
F4: OPEN
PR #7: OPEN / DRAFT / NOT MERGED
Task 4: CLOSED / VERIFIED
Task 5 browse: GREEN / VERIFIED
Task 5 detail: GREEN / VERIFIED
Task 5 landing summary: GREEN CANDIDATE, CI PENDING
Current code SHA before checkpoint: 54151e6c0c67f793c49b26cb29e5ebe7b18745ba
Current CI: 38208938804 IN PROGRESS
Task 6: NOT STARTED
F5: NOT STARTED
MERGE: NOT AUTHORIZED
```

**Pausa obligatoria aquí.**