# AlquilaPro — Checkpoint de continuidad R1-T4 GREEN / R1-T5 READY

**Fecha:** 2026-10-16  
**Estado:** continuidad activa; este archivo es la autoridad durable del punto actual.

## Autoridad actual

- Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
- Rama: `impl/r1-r3-post-uat-recovery-20261015`
- PR draft: `#31`
- Main base verificado: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Merge autorizado: **NO**
- F26: **FROZEN**
- Deployment autorizado: **NO**

## Estado R1

### R1-T1 — esquema de identificadores
GREEN / cerrado.

### R1-T2 — AuthService email/username/documento
GREEN / cerrado.

### R1-T3A — PasswordPolicy canónica
GREEN / cerrado.

- mínimo canónico: **6 caracteres**
- AuthService, UserService e Installer reutilizan la misma autoridad.

### R1-T3B — identidad tenant
GREEN / cerrado.

Commit certificado:
`2670a179784c73b07d96de7ad25d5fa26a3128df`

CI #1025 / run `38844956914`:
- PHP 8.3: SUCCESS
- PHP 8.4: SUCCESS
- PHP 8.5: SUCCESS

Contrato tenant vigente:
- `document_number` = identificador principal;
- contraseña inicial = mismo documento;
- contraseña persistida siempre hasheada;
- `must_change_password = 0`;
- cambio posterior opcional;
- `CandidateService::normalizeDocumentNumber()` permanece como autoridad de normalización del documento del candidato.

## R1-T4 — login HTTP/UI unificado

**GREEN / cerrado formalmente.**

### RED

Commit test-only:
`dea18bc6fc05f1fa357d502c3d237d6c275a3c6d`

CI #1026 confirmó RED limpio en PHP 8.3:
- 638 tests;
- 7.620 assertions;
- 1 failure;
- 1 skipped.

La única falla observada era exactamente el contrato nuevo de login: la vista todavía mostraba `Correo` y `name="email"`.

### GREEN

Cambios mínimos:

1. `resources/views/login.php`
   - label: `Usuario, correo o cédula`;
   - input `type="text"`;
   - `name="identifier"`;
   - `autocomplete="username"`;
   - se elimina el input público email-only.

2. `app/Auth/routes.php`
   - `identifier` es la entrada canónica del POST;
   - se conserva fallback transitorio de compatibilidad a `email` para tests/clientes internos antiguos;
   - ambos caminos llegan al mismo `AuthService`;
   - no existe segunda lógica/provider de autenticación.

Commits GREEN:
- `868c40ba9778fba10fd1a1e50a8ef2cccfecddef`
- `4b9b9e6b38558d6529ac3d6cad86e5fe5919b0ae`

### Gate certificado

CI #1029 / run `38846178789` sobre el SHA funcional exacto:
`4b9b9e6b38558d6529ac3d6cad86e5fe5919b0ae`

Resultado:
- PHP 8.3: SUCCESS — 638 tests / 7.628 assertions / 1 skipped;
- PHP 8.4: SUCCESS;
- PHP 8.5: SUCCESS.

Por tanto:

```text
R1-T4: GREEN
```

## R1-T5 — último bloque funcional de R1

**READY / todavía no implementado.**

La auditoría ya confirmó dos inconsistencias pendientes.

### T5A — política de contraseña en UI

Backend ya usa mínimo 6, pero las vistas conservan reglas antiguas:

- `resources/views/admin_users.php` → `minlength="12"`;
- `resources/views/change_password.php` → `minlength="12"`;
- `resources/views/reset_password.php` → `minlength="12"`;
- `resources/views/install.php` → `minlength="12"` y texto `Usa al menos 12 caracteres.`

Objetivo:
- converger todas las superficies visuales al mínimo canónico 6;
- no crear otra política;
- mantener `PasswordPolicy` como única autoridad de backend.

### T5B — identificadores opcionales de admin/gestor

Estado actual:
- `UserService::create()` crea admin/gestor solo con email;
- `listUsers()` no proyecta username/document_number;
- `/admin/users` no recibe username/document_number;
- `admin_users.php` no expone esos campos.

Objetivo KISS:
- email sigue obligatorio y único;
- username opcional;
- document_number opcional;
- normalizar email con lower/trim;
- document_number con trim + uppercase para coherencia de identidad;
- username con una regla simple y estable, sin nuevo servicio;
- impedir colisiones cruzadas entre `email`, `username` y `document_number` para no crear identificadores ambiguos;
- mantener los índices unique existentes como segunda barrera;
- no agregar tabla/provider/capa de identidad.

### Tests a reutilizar

No crear arquitectura de tests nueva. Extender:
- `tests/Integration/Auth/UserServiceTest.php`;
- `tests/Integration/Auth/AuthRoutesTest.php`;
- autoridad visual/política existente para detectar mínimos HTML obsoletos.

Metodología:

```text
RED focal
→ confirmar RED exacto
→ minimal GREEN
→ full CI PHP 8.3/8.4/8.5
→ cerrar R1
```

## Después de R1

No saltar directamente a producción.

Siguiente bloque:
- R2 design foundation usando los mockups aprobados y preservados;
- R3 landing/carrusel/login público mobile-first;
- assets coherentes por inmueble;
- posterior demo completa y nuevo UAT humano;
- solo después reconstrucción F25 y eventual F26.

## Visual baseline ya aprobado y preservado

La implementación pública debe seguir las referencias canónicas ya guardadas:
- mobile aprobado;
- desktop aprobado;
- responsive conjunto aprobado.

Principios que no deben desviarse:
- propiedades disponibles son la prioridad visual;
- una propiedad protagonista con vecinas visibles en carrusel;
- desktop: foto(s) a la izquierda e información a la derecha;
- mobile: carrusel protagonista y login fijo debajo;
- `Propiedades disponibles` + `Ver todas`;
- sin Google login;
- sin X para cerrar login;
- sin hero de marketing;
- sin bottom-nav pública;
- sin sección duplicada `Más propiedades`;
- no vertical-scroll innecesario en la landing objetivo.

## Guardrails

- KISS.
- Cero ruido / convergencia.
- Anti-parche.
- No editar migraciones históricas.
- No tocar F26.
- No desplegar.
- No mergear sin autorización explícita.
- No abrir R2/R3 antes de cerrar R1.
- No reinterpretar el mockup aprobado.

## Punto corto de reanudación

```text
BRANCH: impl/r1-r3-post-uat-recovery-20261015
FUNCTIONAL_HEAD_CERTIFIED: 4b9b9e6b38558d6529ac3d6cad86e5fe5919b0ae
CI: #1029 / 38846178789
CI_RESULT: PHP 8.3 + 8.4 + 8.5 SUCCESS
R1-T4: CLOSED GREEN
NEXT: R1-T5 RED — password UI + managed-user identifiers
```
