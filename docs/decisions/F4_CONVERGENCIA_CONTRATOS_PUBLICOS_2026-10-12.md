# F4 — DECISIÓN CANÓNICA DE CONVERGENCIA DE CONTRATOS PÚBLICOS

**Fecha:** 2026-10-12  
**Fase:** F4  
**Estado:** CANÓNICA PARA F4  
**Motivo:** eliminar contradicciones entre lenguaje preliminar V1.2 y el contrato F4 implementado/verificado.

---

# 1. ALCANCE

Esta decisión no reescribe ni reduce la SPEC/PLAN maestras V1.2 completas porque contienen roadmap futuro que debe preservarse. Sustituye únicamente, para F4, las referencias preliminares incompatibles con el producto ya probado.

Cuando una referencia maestra use `slug`, 32 hex o media pública por ID interno en contexto F4, se interpreta mediante este documento y la SPEC/PLAN F4 final.

---

# 2. IDENTIFICADOR PÚBLICO

Contrato F4 final:

```text
public_key = bin2hex(random_bytes(12))
CHAR(24)
24 caracteres hexadecimales
server-generated
unique
no editable
```

F4 no agrega `slug` adicional.

Detalle público:

`GET /catalog/{publicKey}`

Motivo KISS: `public_key` ya resuelve identificación pública no secuencial; un slug adicional crearía segunda identidad sin necesidad demostrada.

---

# 3. MEDIA PÚBLICA

Contrato F4 final:

`GET /catalog/{publicKey}/photo/{position}`

Se descarta para F4 el preliminar:

`GET /catalog/media/{id}`

Razón:

- `unit_media.id` es identificador interno;
- la privacidad pública exige no exponer IDs DB;
- `publicKey + position` reutiliza una identidad pública ya existente;
- no requiere token, tabla ni columna adicional.

`position` es ordinal público derivado del orden de fotos, no ID persistente público.

---

# 4. PUBLICABILIDAD

Una unidad es visible únicamente cuando cumple en lectura:

- propiedad activa;
- `status=available`;
- `is_public=1`;
- precio > 0;
- descripción no vacía;
- requisitos no vacíos;
- al menos una foto válida.

La misma regla gobierna listado, detalle y foto pública.

No se crea estado/cache paralelo de publicabilidad.

---

# 5. CACHE DE FOTO

F4 usa `Cache-Control: no-store` para fotos públicas.

Razón: una despublicación debe cortar acceso inmediatamente sin introducir invalidación CDN/cache como nueva arquitectura.

Una estrategia positiva de cache puede reconsiderarse únicamente con invalidación demostrada en una fase futura.

---

# 6. AUTORIDAD

Para detalles específicos F4:

`README decisiones canónicas → F4 SPEC FINAL → F4 PLAN AS-BUILT → ESTA DECISIÓN DE CONVERGENCIA → checkpoint → tests/CI → código`.

La SPEC/PLAN maestras V1.2 conservan autoridad sobre el producto/roadmap general. Este documento solo corrige términos preliminares F4 sin borrar información futura.

---

# 7. EVIDENCIA

Último GREEN funcional previo a esta convergencia documental:

`54836fa6a3baae49f0f60085e39d7ccb1f4d8198`

CI `38211673768`:

- PHP 8.3 PASS;
- PHP 8.4 PASS;
- PHP 8.5 PASS;
- 151 tests / 873 assertions / 0 failures.

---

# 8. LEYES

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
NEW_PUBLIC_IDENTIFIER: NO
NEW_TABLE: NO
NEW_DEPENDENCY: NO
INTERNAL_ID_EXPOSURE: NO
```
