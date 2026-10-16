# AlquilaPro — R1 Auth GREEN — checkpoint canónico

**Fecha:** 2026-10-16  
**Estado:** R1 funcional GREEN certificado; cierre documental publicado y pendiente de CI del HEAD documental.  
**Owner UAT pre-F26:** REJECTED  
**F26:** FROZEN  
**MERGE_AUTHORIZED:** NO  
**DEPLOYMENT_AUTHORIZED:** NO

## Autoridad actual

- Repo: `sierraglobalcompany-rgb/AlquilaPro`
- Base `main`: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Branch: `impl/r1-r3-post-uat-recovery-20261015`
- PR: `#31` — DRAFT / OPEN / NOT MERGED
- Functional GREEN HEAD certificado: `25bf8a97002495f3288bf23de02c563d7a1e39c4`
- CI funcional: #1037 / run `38851445594`
- Resultado: PHP 8.3 / 8.4 / 8.5 **SUCCESS**
- PHP 8.3 observado: 642 tests / 7.659 assertions / 1 skipped

## R1 — cierre por tareas

### R1-T1 — schema de identificadores

GREEN.

- Migration: `023_extend_users_login_identifiers.sql`
- Añade únicamente `users.username` y `users.document_number`, ambos nullable + unique.
- Migraciones 001–022 permanecen inmutables.
- Schema GREEN de referencia: `c370ffe9f11f194bce84b9b0c47544930d6e25fc`
- CI #1010 / run `38837618122`: PHP 8.3 / 8.4 / 8.5 SUCCESS.

### R1-T2 — AuthService unificado

GREEN.

Contrato:
- email / username / document_number llegan al mismo `AuthService`;
- una sola consulta de autenticación;
- `LIMIT 2` y retorno únicamente con una fila => ambigüedad fail-closed;
- lockout, reset, sesiones y hashing existentes permanecen en el mismo owner.

### R1-T3A — PasswordPolicy canónica

GREEN.

- `PasswordPolicy::MIN_LENGTH = 6` es la única autoridad backend.
- AuthService, UserService e Installer la reutilizan.
- `admin_users.php`, `change_password.php`, `reset_password.php`, `install.php` convergieron a `minlength="6"`.
- Test estructural prohíbe reintroducir `minlength="12"` y texto `12 caracteres`.

### R1-T3B — tenant identity

GREEN.

Referencia certificada previa:
- SHA `2670a179784c73b07d96de7ad25d5fa26a3128df`
- CI #1025 / run `38844956914`: PHP 8.3 / 8.4 / 8.5 SUCCESS.

Contrato final:
- `document_number` del candidato es el identificador tenant;
- contraseña inicial = mismo documento;
- contraseña persistida hasheada;
- `must_change_password = 0`;
- Auth no cambia el casing del documento canónico del candidato; solo `trim`.

### R1-T4 — login HTTP/UI unificado

GREEN.

- RED: `dea18bc6fc05f1fa357d502c3d237d6c275a3c6d`
- GREEN funcional: `4b9b9e6b38558d6529ac3d6cad86e5fe5919b0ae`
- CI #1029 / run `38846178789`: PHP 8.3 / 8.4 / 8.5 SUCCESS.

Contrato:
- vista pública usa `name="identifier"`;
- copy actual: `Usuario, correo o cédula`;
- no existe input email-only en la vista;
- el mismo AuthService resuelve las tres identidades.

Deuda transitoria acotada:
- `routes.php` conserva fallback interno `identifier ?? email` para helpers/tests legados;
- no es una segunda lógica/provider;
- no hay evidencia de cliente público separado que lo necesite;
- **gate de eliminación: R3**, junto con el cierre definitivo de la nueva superficie pública de login, para evitar churn aislado de tests antes de esa fase.

### R1-T5 — managed identities + UI password convergence

RED legítimo:
- `a0e22c8594e6828cb3b8c02bb6daaa03cba6a33c`
- `4fe36629540170c0f1702d0ec0237fb3690a749d`
- `75ee50687573447168ff6bd4000cc7fd88e334cf`

GREEN candidato inicial:
- `032a2058fcd57901062500d015762b637216fb5a`

CI #1035 detectó una regresión real fuera del contrato T5: el normalizador uppercase de documento gestionado fue reutilizado indebidamente para tenant, alterando el documento canónico de LeaseActivation.

Root cause:
- managed admin/gestor y tenant tienen autoridades de normalización distintas;
- tenant debe conservar exactamente el documento del candidato.

Fix mínimo:
- SHA `25bf8a97002495f3288bf23de02c563d7a1e39c4`
- solo 2 líneas funcionales en `UserService::createTenantIdentity()`;
- se restauró `trim` sin uppercase para tenant.

CI #1037 / `38851445594`:
- PHP 8.3 SUCCESS — 642 tests / 7.659 assertions / 1 skipped;
- PHP 8.4 SUCCESS;
- PHP 8.5 SUCCESS.

Contrato T5 final:
- email administrado obligatorio y normalizado lower+trim;
- username opcional lower+trim;
- document_number opcional upper+trim;
- campos opcionales vacíos -> `NULL`;
- listUsers proyecta username/document;
- `/admin/users` expone y pasa ambos campos;
- una sola comprobación cruzada contra email/username/document_number;
- índices UNIQUE como segunda barrera;
- no se creó IdentityService, tabla, provider, trigger ni middleware nuevo.

## Auditoría de las tres leyes — 10 pasadas

1. **Base/autoridad:** PASS — branch 33 commits ahead / 0 behind del main auditado antes del cierre documental.
2. **Schema:** PASS — una migración, dos columnas nullable unique, sin editar histórico.
3. **Auth owner:** PASS — un AuthService y ambigüedad fail-closed.
4. **Password authority:** PASS — una PasswordPolicy y cuatro vistas convergidas.
5. **Managed identities:** PASS — un UserService, sin capa paralela.
6. **Tenant identity:** PASS tras root-cause fix `25bf8a97…`; contrato exacto protegido por LeaseActivationTest.
7. **Routes/UI:** PASS — identifier canónico; fallback legacy acotado con eliminación fijada en R3.
8. **Errores/auditoría:** PASS — las líneas `unhandled PDOException`/`/explode` observadas en CI corresponden a tests intencionales de rollback y boundary 500; no se parchean ni silencian.
9. **Ruido documental:** CONVERGIDO en este cierre — un checkpoint canónico reemplaza checkpoints transitorios de R1; Git conserva historia.
10. **Gobernanza:** código R1 GREEN; merge/deployment siguen bloqueados por autorización. `main` fue observado sin branch protection/status checks obligatorios; tratar esa configuración como gate de gobernanza separado, no como parche runtime.

## Visual baseline y R3

Spec canónica:
`docs/design/APPROVED_PUBLIC_UI_BASELINE_2026-10-15.md`

Antes de implementar R3 resolver una contradicción pendiente de autoridad visual:
- spec textual: login **sin X/cerrar**;
- mockup mobile aprobado preservado: muestra visualmente una X;
- copy también debe congelarse entre `Usuario` y `Usuario, correo o cédula`.

No implementar por interpretación. Resolver y congelar una sola autoridad primero.

## Roadmap vigente

`R0 -> R1 -> R2 -> R3 -> R4 -> R5 -> R6 -> R7 -> R8 -> R9 -> R10 -> R11 -> R12 -> R13 -> R14`

Estado al crear este checkpoint:

```text
R0: LOCKED
R1_FUNCTIONAL: GREEN CERTIFIED @ 25bf8a97002495f3288bf23de02c563d7a1e39c4
R1_CLOSURE_DOCS: PUBLISHED IN THIS COMMIT; EXACT CI REQUIRED
R2: NOT STARTED
R3: NOT STARTED
PRE_F26_OWNER_UAT: REJECTED
F26: FROZEN
MERGE_AUTHORIZED: NO
DEPLOYMENT_AUTHORIZED: NO
```

## Próxima acción exacta

1. Obtener CI completa PHP 8.3 / 8.4 / 8.5 del HEAD documental que contiene este checkpoint.
2. Verificar que PR #31 apunta a ese HEAD y no hay drift.
3. Actualizar título/body de PR para reflejar R1 GREEN / merge gate.
4. Auditar diff final y estado de main.
5. **Parar en merge gate**: no mergear sin autorización explícita del owner.
6. No abrir R2 mientras R1 no quede cerrado conforme al gate de integración vigente.
