# AlquilaPro — R1 Auth GREEN — checkpoint canónico

**Fecha:** 2026-10-16  
**Estado funcional:** GREEN certificado.  
**Estado de integración:** cierre final en merge gate; no merge sin autorización explícita.  
**PRE_F26_OWNER_UAT:** REJECTED  
**F26:** FROZEN  
**MERGE_AUTHORIZED:** NO  
**DEPLOYMENT_AUTHORIZED:** NO

## Autoridad

- Repo: `sierraglobalcompany-rgb/AlquilaPro`
- Base `main`: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Branch: `impl/r1-r3-post-uat-recovery-20261015`
- PR: `#31` — DRAFT / OPEN / NOT MERGED
- Product GREEN: `25bf8a97002495f3288bf23de02c563d7a1e39c4`
- Closure + continuity HEAD certificado antes de esta convergencia final: `5a0a75738456b64e324b0da34bd93a3fd0819b99`
- CI exacta de ese HEAD: #1039 / run `38852239017` — PHP 8.3 / 8.4 / 8.5 SUCCESS.
- Este checkpoint reemplaza el checkpoint transitorio de pausa `R1_CLOSURE_PAUSED_CI1038_2026-10-16.md`, que se retira del HEAD en la misma convergencia anti-ruido.

> Regla de continuidad: el body de PR #31 es el estado vivo de ejecución porque puede actualizarse sin mover el HEAD ni crear pipelines rituales. Este archivo conserva la evidencia durable de cierre R1.

## R1 — contratos cerrados

### R1-T1 — schema

GREEN.

- `database/migrations/023_extend_users_login_identifiers.sql`
- añade solo `users.username` y `users.document_number`;
- ambos nullable + unique;
- migraciones 001–022 permanecen inmutables;
- schema GREEN de referencia: `c370ffe9f11f194bce84b9b0c47544930d6e25fc`;
- CI #1010 / run `38837618122`: PHP 8.3 / 8.4 / 8.5 SUCCESS.

### R1-T2 — AuthService unificado

GREEN.

- una sola autoridad: `AuthService`;
- login por email / username / document_number;
- una consulta de identidad con fail-closed ante ambigüedad;
- lockout, reset, hashing y sesiones siguen en el stack existente;
- no existe provider paralelo.

### R1-T3A — PasswordPolicy

GREEN.

- `PasswordPolicy::MIN_LENGTH = 6` es la única autoridad del mínimo;
- AuthService, UserService e Installer reutilizan esa policy;
- `admin_users.php`, `change_password.php`, `reset_password.php` e `install.php` convergen a `minlength="6"`;
- el test estructural impide reintroducir `minlength="12"` / `12 caracteres`.

### R1-T3B — tenant identity

GREEN.

- documento del candidato = identificador tenant;
- contraseña inicial = mismo documento;
- contraseña persistida hasheada;
- `must_change_password = 0`;
- tenant conserva exactamente el documento canónico del candidato después de `trim`; no se fuerza uppercase.

Referencia previa certificada:
- SHA `2670a179784c73b07d96de7ad25d5fa26a3128df`;
- CI #1025 / run `38844956914`: PHP 8.3 / 8.4 / 8.5 SUCCESS.

### R1-T4 — login HTTP/UI

GREEN.

- RED: `dea18bc6fc05f1fa357d502c3d237d6c275a3c6d`;
- GREEN funcional: `4b9b9e6b38558d6529ac3d6cad86e5fe5919b0ae`;
- CI #1029 / run `38846178789`: PHP 8.3 / 8.4 / 8.5 SUCCESS;
- UI pública usa `name="identifier"`;
- copy actual: `Usuario, correo o cédula`;
- mismo AuthService resuelve las tres identidades.

Compatibilidad transitoria controlada:
- `routes.php` conserva `identifier ?? email` para helpers/tests legados;
- no es una segunda autoridad;
- gate de eliminación: **R3**, cuando se reabra la superficie pública de login.

### R1-T5 — managed identities + visual password convergence

RED legítimo:
- `a0e22c8594e6828cb3b8c02bb6daaa03cba6a33c`;
- `4fe36629540170c0f1702d0ec0237fb3690a749d`;
- `75ee50687573447168ff6bd4000cc7fd88e334cf`.

GREEN candidato inicial:
- `032a2058fcd57901062500d015762b637216fb5a`.

CI #1035 detectó una regresión real: el uppercase del documento gestionado se reutilizó indebidamente para tenant.

Root cause:
- admin/gestor y tenant tienen contratos de normalización diferentes.

Fix mínimo:
- `25bf8a97002495f3288bf23de02c563d7a1e39c4`;
- 2 líneas funcionales en `UserService::createTenantIdentity()`;
- tenant vuelve a preservar el casing del documento del candidato.

CI #1037 / run `38851445594`:
- PHP 8.3 SUCCESS — 642 tests / 7.659 assertions / 1 skipped;
- PHP 8.4 SUCCESS;
- PHP 8.5 SUCCESS.

Contrato T5 final:
- email administrado obligatorio: lower + trim;
- username opcional: lower + trim;
- document_number opcional: upper + trim;
- opcionales vacíos -> `NULL`;
- listUsers proyecta username/document_number;
- `/admin/users` expone y pasa ambos;
- una sola comprobación cruzada de colisiones en `UserService`;
- índices UNIQUE = segunda barrera;
- no se creó IdentityService, tabla, provider, trigger ni middleware nuevo.

## CI de cierre documental

### `f5db488547f479f596ea5f116ef40eebccd2a357`

Convergió:
- README al roadmap post-UAT R0–R14;
- AGENTS a un solo orden de autoridad;
- checkpoint canónico R1;
- retiro de cinco checkpoints transitorios previos;
- sin cambios de runtime.

### `5a0a75738456b64e324b0da34bd93a3fd0819b99`

Añadió solo checkpoint de pausa por solicitud del owner.

CI #1039 / run `38852239017` sobre ese HEAD exacto:
- PHP 8.3 SUCCESS;
- PHP 8.4 SUCCESS;
- PHP 8.5 SUCCESS.

Esta convergencia final absorbe ese checkpoint de pausa en el checkpoint canónico y elimina el transitorio del HEAD. Por ello debe existir una última CI exacta de este nuevo HEAD antes del merge gate definitivo; registrar su resultado en PR #31 sin crear otro commit solo para escribir el mismo resultado.

## Auditoría 10 pasadas — tres leyes

1. **Base/drift:** PASS — antes de esta convergencia la rama estaba 35 commits ahead / 0 behind de `main`.
2. **Schema:** PASS — una migración nueva; histórico intacto.
3. **Auth owner:** PASS — un AuthService.
4. **Password authority:** PASS — una PasswordPolicy.
5. **Managed identities:** PASS — un UserService; sin capa paralela.
6. **Tenant:** PASS después del root-cause fix; LeaseActivationTest protege documento/password exactos.
7. **Routes/UI:** PASS — identifier canónico; fallback temporal con gate de eliminación explícito R3.
8. **Errores/auditoría:** PASS — logs `unhandled PDOException` y `/explode` observados corresponden a tests intencionales de rollback/boundary; no se silencian con parches.
9. **Anti-ruido documental:** PASS — checkpoints intermedios absorbidos; este archivo es el checkpoint durable R1.
10. **Gobernanza:** runtime/cierre R1 verde; merge y deployment continúan bloqueados por autorización. `main` fue observado sin branch protection/status checks obligatorios; tratarlo como gate de gobernanza separado, no como cambio runtime.

## Visual baseline / R3 — bloqueo conocido

Spec:
`docs/design/APPROVED_PUBLIC_UI_BASELINE_2026-10-15.md`

Antes de implementar R3 congelar una sola autoridad para:
- spec textual: login **sin X/cerrar**;
- mockup mobile aprobado: visualmente muestra una X;
- copy: `Usuario` vs `Usuario, correo o cédula`.

No implementar por interpretación ni parchear CSS después.

## Roadmap vigente

`R0 -> R1 -> R2 -> R3 -> R4 -> R5 -> R6 -> R7 -> R8 -> R9 -> R10 -> R11 -> R12 -> R13 -> R14`

Estado durable:

```text
R0: LOCKED
R1_PRODUCT: GREEN @ 25bf8a97002495f3288bf23de02c563d7a1e39c4
R1_PRODUCT_CI: #1037 PASS 8.3/8.4/8.5
R1_CLOSURE_PARENT: 5a0a75738456b64e324b0da34bd93a3fd0819b99
R1_CLOSURE_PARENT_CI: #1039 PASS 8.3/8.4/8.5
R1_FINAL_DOC_CONVERGENCE: THIS COMMIT — EXACT CI REQUIRED BEFORE MERGE
PR: #31 DRAFT / OPEN / NOT MERGED
R2: NOT STARTED
PRE_F26_OWNER_UAT: REJECTED
F26: FROZEN
MERGE_AUTHORIZED: NO
DEPLOYMENT_AUTHORIZED: NO
```

## Próxima acción exacta

1. Obtener CI PHP 8.3 / 8.4 / 8.5 del HEAD exacto de esta convergencia final.
2. Verificar PR #31 head/base/mergeability y `main` sin drift.
3. Actualizar únicamente el body de PR #31 con la evidencia final; no crear otro commit ritual.
4. Auditar diff final KISS / Cero Ruido / Anti-parche.
5. **PARAR EN MERGE GATE.**
6. No mergear hasta autorización explícita del owner.
7. No abrir R2 antes de cerrar la integración R1 conforme al gate vigente.
