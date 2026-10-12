# F5 — Refinamiento de privacidad por identidad duplicada y transiciones

**Fecha:** 2026-10-12  
**Fase:** F5 — Aplicaciones + candidatos + referencias  
**Estado:** HISTÓRICO / FOLDED INTO F5 SPEC + PLAN / IMPLEMENTED + VERIFIED

> Este documento conserva únicamente la razón histórica de una decisión tomada durante F5. La autoridad vigente es `docs/specs/F5_APPLICATIONS_CANDIDATES_REFERENCES_SPEC.md`, `docs/plans/F5_APPLICATIONS_CANDIDATES_REFERENCES_IMPLEMENTATION_PLAN.md` y `docs/checkpoints/F5_CLOSED_2026-10-12.md`. No debe interpretarse como un contrato pendiente.

## 1. Motivo

La SPEC F5 exige simultáneamente:

1. que un candidato nuevo reciba un token raw de continuación en el flujo público;
2. que un documento ya existente no reciba ni regenere ese token sin intervención administrativa;
3. que el endpoint no revele datos privados del candidato ni capacidades reutilizables;
4. que F5 no agregue email/OTP/login candidato como canal fuera de banda.

Los puntos 1–4 no permiten ocultar de forma fuerte la **mera existencia** de una identidad frente a un actor que compare el resultado de un alta nueva con el de un documento ya registrado: el candidato nuevo debe obtener una capacidad de continuación que el duplicado no puede recibir de forma segura.

Agregar email/OTP, PIN/password candidato, sesión de autenticación paralela o un servicio de notificaciones solo para resolver esto violaría el alcance/KISS de F5.

## 2. Regla canónica F5

F5 interpreta `candidato no enumerable` como **no enumerabilidad del recurso candidato y ausencia de filtración de datos/capacidades**, no como garantía criptográfica de ocultación de existencia por documento sin canal fuera de banda.

Ante un documento duplicado, F5 debe:

- no crear otro candidato;
- no devolver/regenerar token;
- no devolver candidate ID, unidad, estado, nombre, email, teléfono ni otro dato privado;
- usar mensaje genérico, sin copy deliberado que indique estado/unidad;
- aplicar throttle básico;
- registrar cero dato adicional innecesario.

La continuación real permanece protegida por token criptográfico de 256 bits raw / SHA-256 persistido.

Si en una fase posterior se exige ocultación fuerte de existencia por identidad, debe resolverse con un canal fuera de banda real (email/OTP) reutilizando el motor correspondiente; no se introduce ahora un auth candidato paralelo.

## 3. Transiciones F5 canónicas

El envío final válido puede ocurrir desde `new` o `incomplete`.

```text
new -> incomplete | verification | withdrawn
incomplete -> verification | withdrawn
verification -> pending_screening | withdrawn
pending_screening -> withdrawn
withdrawn -> terminal en F5
```

`verification` desde `new|incomplete` solo lo produce `submitByToken()` tras validar completitud. La ruta administrativa no puede saltarse esa validación para simular un submit.

## 4. Resultado verificado

- DB delta adicional por esta decisión: 0;
- dependencias adicionales: 0;
- servicios adicionales: 0;
- auth adicional: 0;
- duplicate identity: cubierto por tests de dominio, rutas y seguridad;
- transiciones: cubiertas por `CandidateServiceTest` y rutas administrativas;
- estado final F5: `CLOSED / VERIFIED / NOT MERGED`.

Este documento se conserva porque explica por qué F5 **no** añadió OTP/email/auth candidato solo para intentar ocultar la existencia de una identidad duplicada.
