# AlquilaPro — Checkpoint R1-T4 Login Identifier RED

**Fecha:** 2026-10-16  
**Estado:** PARADA CONTROLADA solicitada por Andrés.

## Autoridad actual

- Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
- Rama: `impl/r1-r3-post-uat-recovery-20261015`
- HEAD antes de este checkpoint: `dea18bc6fc05f1fa357d502c3d237d6c275a3c6d`
- PR draft: `#31`
- Main base verificado: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Merge autorizado: **NO**
- F26: **FROZEN**
- Deployment autorizado: **NO**

## Estado R1

### R1-T1
GREEN / cerrado.

### R1-T2
GREEN / cerrado.

### R1-T3A
GREEN / cerrado.

- Política canónica de contraseña: `PasswordPolicy`
- mínimo: **6 caracteres**

### R1-T3B
GREEN / cerrado formalmente.

Commit test-only final:
`2670a179784c73b07d96de7ad25d5fa26a3128df`

CI #1025 / run `38844956914`:
- PHP 8.3: SUCCESS
- PHP 8.4: SUCCESS
- PHP 8.5: SUCCESS

Contrato tenant vigente:
- `document_number` = identificador principal
- contraseña inicial = mismo documento
- contraseña almacenada hasheada
- `must_change_password = 0`
- cambio posterior opcional
- normalización de documento permanece en `CandidateService::normalizeDocumentNumber()`
- no crear normalización paralela

## R1-T4 — estado exacto

Objetivo:
- el formulario de login deja de ser email-only;
- usa un único campo `identifier`;
- etiqueta visible: `Usuario, correo o cédula`;
- debe autenticar por email, username o document_number a través del mismo `AuthService`;
- no crear otro provider/servicio de autenticación.

### RED publicado

Commit:
`dea18bc6fc05f1fa357d502c3d237d6c275a3c6d`

Archivo modificado únicamente para RED:
`tests/Integration/Auth/AuthRoutesTest.php`

Nuevo test:
`testLoginUsesUnifiedIdentifierFieldAndAcceptsUsernameOrDocument`

Contrato exigido por el RED:
1. GET `/login` contiene `Usuario, correo o cédula`.
2. El input se llama `identifier`.
3. No debe existir `name="email"` en el login público.
4. POST `/login` con `identifier=staff.user` funciona.
5. POST `/login` con `identifier=CC123456` funciona.

### CI actual

CI #1026
Run: `38845625067`
SHA exacto: `dea18bc6fc05f1fa357d502c3d237d6c275a3c6d`

Último estado observado al crear el checkpoint:
- `queued`
- todavía NO se ha confirmado el RED real

**No afirmar que R1-T4 está RED confirmado hasta observar el fallo del CI #1026.**

## Próximo paso exacto al reanudar

1. Consultar CI #1026 / run `38845625067`.
2. Confirmar que el fallo proviene exclusivamente del nuevo contrato de `identifier`.
3. Si RED limpio:
   - cambiar `app/Auth/routes.php` para leer `$form['identifier']`;
   - cambiar `resources/views/login.php`:
     - label `Usuario, correo o cédula`;
     - input text, no type=email;
     - `name="identifier"`;
     - `autocomplete="username"`;
   - actualizar tests existentes que aún postean `email` al endpoint de login para usar `identifier` cuando representen el contrato nuevo.
4. GREEN focal.
5. CI completa PHP 8.3 / 8.4 / 8.5.
6. Solo después cerrar R1-T4.

## Auditoría R1 restante ya descubierta

Después de T4 aún queda al menos un bloque pequeño antes de cerrar R1:

### R1-T5 — alta administrativa + mínimos visuales

Hallazgos:
- `resources/views/admin_users.php` aún tiene `minlength="12"`.
- `resources/views/change_password.php` aún tiene `minlength="12"`.
- `resources/views/reset_password.php` aún tiene `minlength="12"`.
- `UserService::create()` todavía crea admin/gestor solo con email; no acepta `username` ni `document_number`.
- la UI de alta administrativa tampoco expone esos campos.

Objetivo posterior:
- converger todos los `minlength` visuales a 6;
- permitir username/document_number para admin/gestor sin obligarlos si no se necesitan;
- preservar email actual;
- validar colisiones de identificador de forma fail-closed y sin arquitectura paralela.

No implementar T5 antes de cerrar T4.

## Guardrails

- KISS.
- Cero ruido / convergencia.
- Anti-parche.
- No editar migraciones históricas.
- No tocar F26.
- No desplegar.
- No mergear sin autorización explícita.
- No abrir R2/R3 antes de cerrar R1 ordenadamente.
- No cambiar diseño visual aprobado durante R1 funcional.

## Punto corto de reanudación

```text
BRANCH: impl/r1-r3-post-uat-recovery-20261015
HEAD PRE-CHECKPOINT: dea18bc6fc05f1fa357d502c3d237d6c275a3c6d
CI: #1026 / 38845625067
STATE: R1-T4 RED TEST PUBLISHED, RED NOT YET CONFIRMED
NEXT: observe CI #1026 only
```

**STOP.**
