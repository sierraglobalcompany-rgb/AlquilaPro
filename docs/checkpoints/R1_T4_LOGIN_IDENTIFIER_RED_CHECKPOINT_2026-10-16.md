# AlquilaPro — Checkpoint de continuidad R1-T5 RED publicado

**Fecha:** 2026-10-16  
**Estado:** PARADA CONTROLADA solicitada por Andrés.

## Autoridad actual

- Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
- Rama: `impl/r1-r3-post-uat-recovery-20261015`
- PR draft: `#31`
- Main base verificado: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Merge autorizado: **NO**
- F26: **FROZEN**
- Deployment autorizado: **NO**

## R1 cerrado hasta T4

### R1-T1
GREEN / cerrado.

### R1-T2
GREEN / cerrado.

### R1-T3A
GREEN / cerrado.
- `PasswordPolicy` es la autoridad canónica.
- mínimo backend: **6 caracteres**.

### R1-T3B
GREEN / cerrado.

Commit certificado:
`2670a179784c73b07d96de7ad25d5fa26a3128df`

CI #1025 / run `38844956914`:
- PHP 8.3: SUCCESS
- PHP 8.4: SUCCESS
- PHP 8.5: SUCCESS

Contrato tenant:
- `document_number` = identificador principal;
- contraseña inicial = mismo documento;
- contraseña almacenada hasheada;
- `must_change_password = 0`;
- cambio posterior opcional.

### R1-T4
GREEN / cerrado formalmente.

RED:
`dea18bc6fc05f1fa357d502c3d237d6c275a3c6d`

GREEN funcional:
`4b9b9e6b38558d6529ac3d6cad86e5fe5919b0ae`

CI #1029 / run `38846178789` sobre el SHA funcional exacto:
- PHP 8.3: SUCCESS — 638 tests / 7.628 assertions / 1 skipped;
- PHP 8.4: SUCCESS;
- PHP 8.5: SUCCESS.

Contrato vigente:
- login público usa `identifier`;
- label `Usuario, correo o cédula`;
- mismo `AuthService` para email / username / document_number;
- fallback temporal interno a POST `email` para compatibilidad de tests/clientes antiguos;
- no existe provider paralelo.

## R1-T5 — estado exacto al detener

Objetivo: cerrar el último bloque funcional de R1 antes de R2/R3.

### T5A — mínimo visual de contraseña

RED publicado para exigir que estas vistas converjan a `minlength="6"` y eliminen restos de 12:
- `resources/views/admin_users.php`
- `resources/views/change_password.php`
- `resources/views/reset_password.php`
- `resources/views/install.php`

Test authority extendido:
`tests/Unit/Auth/PasswordPolicyAuthorityTest.php`

Commit RED parcial:
`a0e22c8594e6828cb3b8c02bb6daaa03cba6a33c`

### T5B — identificadores opcionales de admin/gestor

RED publicado para exigir:
- email obligatorio y normalizado;
- `username` opcional;
- `document_number` opcional;
- persistencia de ambos;
- proyección de ambos en `listUsers()`;
- UI `/admin/users` con campos `username` y `document_number`;
- ruta POST `/admin/users` pasa esos valores a `UserService`;
- login posterior por username;
- rechazo fail-closed de colisiones cruzadas entre email / username / document_number;
- sin nueva tabla/provider/capa de identidad.

Tests extendidos:
- `tests/Integration/Auth/UserServiceTest.php`
- `tests/Integration/Auth/AuthRoutesTest.php`

Commits RED:
- `4fe36629540170c0f1702d0ec0237fb3690a749d`
- `75ee50687573447168ff6bd4000cc7fd88e334cf`

### HEAD RED actual

`75ee50687573447168ff6bd4000cc7fd88e334cf`

**Importante:** hasta este HEAD de T5 solo se publicaron tests. No se aplicó GREEN de producción para T5.

## CI exacto pendiente

CI #1033  
Run: `38847060236`  
SHA: `75ee50687573447168ff6bd4000cc7fd88e334cf`

Último estado observado al crear este checkpoint:
- PHP 8.3: `Run tests` IN_PROGRESS
- PHP 8.4: `Run tests` IN_PROGRESS
- PHP 8.5: `Run tests` IN_PROGRESS

**No afirmar todavía que R1-T5 está RED confirmado.**

## Próximo paso exacto al reanudar

1. Consultar CI #1033 / run `38847060236`.
2. Confirmar que el RED falla únicamente por los contratos nuevos de T5.
3. Si el RED es limpio, aplicar GREEN mínimo, sin abrir R2/R3 todavía:
   - vistas password: `minlength=6` y texto coherente;
   - `UserService::create()` acepta username/document opcionales sin romper llamadas existentes;
   - normalización simple: email lower+trim, username lower+trim, documento trim+uppercase;
   - una sola comprobación de colisiones cruzadas;
   - `listUsers()` incluye username/document_number;
   - `/admin/users` expone y pasa los dos campos;
   - mantener índices unique existentes como segunda barrera.
4. Ejecutar CI completa PHP 8.3 / 8.4 / 8.5.
5. Solo con matriz verde cerrar R1.
6. Después abrir R2 visual y R3 landing/carrusel/login conforme a los mockups aprobados.

## Visual baseline preservado para después de R1

No reinterpretar:
- inventario disponible es el protagonista;
- mobile y desktop comparten identidad;
- una propiedad protagonista + vecinas visibles;
- desktop: foto(s) izquierda, información derecha;
- mobile: carrusel protagonista + login fijo debajo;
- `Propiedades disponibles` + `Ver todas`;
- sin Google login;
- sin X en login;
- sin hero de marketing;
- sin bottom-nav pública;
- sin `Más propiedades` duplicado;
- evitar scroll vertical innecesario en la landing objetivo.

## Guardrails

- KISS.
- Cero ruido / convergencia.
- Anti-parche.
- No editar migraciones históricas.
- No tocar F26.
- No desplegar.
- No mergear sin autorización explícita.
- No abrir R2/R3 antes de cerrar R1.
- No afirmar CI PASS o RED confirmado sin observarlo.

## Punto corto de reanudación

```text
BRANCH: impl/r1-r3-post-uat-recovery-20261015
HEAD: 75ee50687573447168ff6bd4000cc7fd88e334cf
PR: #31
R1-T4: CLOSED GREEN
R1-T5: RED TESTS PUBLISHED, RED NOT YET CONFIRMED
CI: #1033 / 38847060236
CI_STATE: PHP 8.3/8.4/8.5 RUN TESTS IN_PROGRESS
PRODUCTION_CHANGES_T5: NONE
NEXT: observe CI #1033 only
```

**STOP.**
