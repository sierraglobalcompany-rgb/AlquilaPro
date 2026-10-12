# F5 — Refinamiento de privacidad por identidad duplicada y transiciones

**Fecha:** 2026-10-12  
**Fase:** F5 — Aplicaciones + candidatos + referencias  
**Estado:** decisión de diseño posterior a la SPEC, pendiente de aprobación junto con el PLAN antes de código.

## 1. Motivo

La SPEC F5 exige simultáneamente:

1. que un candidato nuevo reciba un token raw de continuación en el flujo público;
2. que un documento ya existente no reciba ni regenere ese token sin intervención administrativa;
3. que el endpoint no revele si el documento ya existe;
4. que F5 no agregue email/OTP/login candidato como canal fuera de banda.

Los puntos 1–4 no permiten ocultar de forma fuerte la **mera existencia** de una identidad frente a un actor que compare el resultado de un alta nueva con el de un documento ya registrado: el candidato nuevo debe obtener una capacidad de continuación que el duplicado no puede recibir de forma segura.

Agregar email/OTP, PIN/password candidato, sesión de autenticación paralela o un servicio de notificaciones solo para resolver esto violaría el alcance/KISS de F5.

## 2. Regla canónica F5

F5 interpreta `candidato no enumerable` como **no enumerabilidad del recurso candidato y ausencia de filtración de datos/capacidades**, no como garantía criptográfica de ocultación de existencia por documento sin canal fuera de banda.

Ante un documento duplicado, F5 debe:

- no crear otro candidato;
- no devolver/regenerar token;
- no devolver candidate ID, unidad, estado, nombre, email, teléfono ni otro dato privado;
- usar mensaje genérico y mismo tipo de respuesta HTTP que el flujo de inicio maneje para una solicitud no continuable;
- evitar diferencias deliberadas de copy que indiquen estado/unidad;
- aplicar throttle básico;
- registrar cero dato adicional innecesario.

La continuación real permanece protegida por token criptográfico de 256 bits raw / SHA-256 persistido.

Si en una fase posterior se exige ocultación fuerte de existencia por identidad, debe resolverse con un canal fuera de banda real (email/OTP) reutilizando el motor correspondiente; no se introduce ahora un auth candidato paralelo.

## 3. Transiciones F5 corregidas

El envío final válido puede ocurrir desde `new` o `incomplete`.

Transiciones canónicas:

```text
new -> incomplete | verification | withdrawn
incomplete -> verification | withdrawn
verification -> pending_screening | withdrawn
pending_screening -> withdrawn
withdrawn -> terminal en F5
```

`verification` desde `new|incomplete` solo lo produce `submitByToken()` tras validar completitud. La ruta administrativa no debe saltarse esa validación para simular un submit.

## 4. Impacto

- DB delta: 0.
- dependencias: 0.
- servicios: 0 adicionales.
- auth: 0 adicionales.
- PLAN F5 debe incorporar esta decisión.
- La SPEC F5 se actualizará as-built al cierre o antes si otra corrección funcional lo exige.
