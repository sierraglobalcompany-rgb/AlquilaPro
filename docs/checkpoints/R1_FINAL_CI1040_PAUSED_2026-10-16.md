# AlquilaPro — R1 final GREEN — owner merge gate checkpoint

**Fecha:** 2026-10-16  
**Tipo:** checkpoint de continuidad en rama separada; NO mueve la rama activa.  
**OWNER_REQUESTED_CHECKPOINTS:** YES  
**R1_CLOSURE_CERTIFIED:** YES  
**MERGE_AUTHORIZED:** NO  
**DEPLOYMENT_AUTHORIZED:** NO  
**R2:** NOT STARTED  
**F26:** FROZEN

## Autoridad exacta

- Repo: `sierraglobalcompany-rgb/AlquilaPro`
- Base `main`: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Rama activa: `impl/r1-r3-post-uat-recovery-20261015`
- HEAD activo: `2fabaf4800414fa807d6acd54ccd49f75da06919`
- PR activo: `#31` — `R1 Auth GREEN — owner merge gate`
- PR: OPEN / DRAFT / NOT MERGED / mergeable=true
- Rama activa vs main auditado: 36 commits ahead / 0 behind
- Review threads pendientes: 0
- Product GREEN: `25bf8a97002495f3288bf23de02c563d7a1e39c4`

## CI funcional

CI #1037 / run `38851445594` sobre `25bf8a97002495f3288bf23de02c563d7a1e39c4`:
- PHP 8.3 SUCCESS — 642 tests / 7.659 assertions / 1 skipped
- PHP 8.4 SUCCESS
- PHP 8.5 SUCCESS

## CI final del HEAD

CI #1040 / run `38852857032` sobre:

`2fabaf4800414fa807d6acd54ccd49f75da06919`

Resultado final observado:
- PHP 8.3: SUCCESS
- PHP 8.4: SUCCESS
- PHP 8.5: SUCCESS
- workflow: COMPLETED / SUCCESS

Por tanto:

`R1_FINAL_CLOSURE_CERTIFIED: YES`

## Contratos R1 cerrados

- un solo `AuthService` para email / username / documento;
- identidad ambigua => fail-closed;
- una sola `PasswordPolicy`, mínimo 6;
- admin/gestor: email obligatorio + username/document_number opcionales;
- opcionales vacíos -> `NULL`;
- username gestionado lower+trim;
- documento gestionado upper+trim;
- colisiones cruzadas centralizadas en `UserService`;
- índices UNIQUE como segunda barrera;
- tenant conserva exactamente el documento canónico del candidato tras `trim`;
- contraseña inicial tenant = documento hasheado;
- `must_change_password = 0`;
- sin IdentityService / tabla / provider / middleware paralelo.

## Regresión T5 resuelta por causa raíz

CI #1035 detectó que el uppercase del documento gestionado se reutilizó indebidamente para tenant.

Root cause:
- admin/gestor y tenant tienen contratos de normalización diferentes.

Fix mínimo:
- `25bf8a97002495f3288bf23de02c563d7a1e39c4`;
- 2 líneas en `UserService::createTenantIdentity()`;
- tenant volvió a conservar el casing exacto del candidato.

`LeaseActivationTest` protege documento/password exactos.

## Cero Ruido / documentación

HEAD activo `2fabaf48…` contiene:
- README actualizado al roadmap post-UAT R0–R14;
- AGENTS con una sola jerarquía de autoridad;
- un solo checkpoint canónico R1 en la rama activa: `docs/checkpoints/R1_AUTH_GREEN_CHECKPOINT_2026-10-16.md`;
- checkpoints transitorios previos absorbidos y retirados del HEAD activo;
- cero cambios runtime en la convergencia final.

Este checkpoint vive en la rama separada `checkpoint/r1-final-ci1040-20261016` para preservar continuidad sin ensuciar el HEAD activo ni disparar nueva CI sobre él.

## Deuda transitoria controlada

`app/Auth/routes.php` conserva `identifier ?? email` únicamente para helpers/tests legados.

- no es segunda autoridad;
- UI pública usa `identifier`;
- gate de eliminación: **R3**.

## Bloqueo visual antes de R3

Resolver y congelar una sola autoridad antes de implementar R3:
- spec textual: login sin X/cerrar;
- mockup mobile: muestra una X;
- copy: `Usuario` vs `Usuario, correo o cédula`.

No implementar por interpretación.

## Auditoría final de leyes

- KISS: PASS — owners existentes, sin arquitectura paralela.
- Cero Ruido: PASS — un checkpoint R1 canónico en HEAD; recuperación adicional fuera de la rama activa.
- Anti-parche: PASS — regresión reproducida, causa raíz aislada, fix mínimo y regresión completa.

## Tasks

```text
R1-FINAL-CI: CLOSED GREEN
R1-MERGE-GATE-AUDIT: CLOSED GREEN
R1-MERGE: BLOCKED — OWNER AUTHORIZATION REQUIRED
R2: NOT STARTED
```

## Próxima acción exacta

1. Leer primero PR #31; su body es el estado vivo de continuidad.
2. No tocar runtime ni abrir R2.
3. Esperar autorización explícita del owner para mergear R1.
4. Si el owner autoriza merge:
   - refrescar `main`;
   - verificar PR #31 head/base/mergeability;
   - verificar CI #1040 sigue asociada a `2fabaf48…` y SUCCESS;
   - mergear con expected head `2fabaf4800414fa807d6acd54ccd49f75da06919`;
   - verificar `main` post-merge;
   - crear checkpoint post-merge;
   - solo después abrir R2.

## Estado corto

```text
MAIN: b45f625e68622702335f5e606ae34d7410a2b7e6
ACTIVE_HEAD: 2fabaf4800414fa807d6acd54ccd49f75da06919
PR: #31 OPEN / DRAFT / NOT MERGED / MERGEABLE
R1_PRODUCT: GREEN
R1_FINAL_CI: #1040 PASS 8.3/8.4/8.5
R1_CLOSURE_CERTIFIED: YES
R2: NOT STARTED
PRE_F26_OWNER_UAT: REJECTED
F26: FROZEN
MERGE_AUTHORIZED: NO
DEPLOYMENT_AUTHORIZED: NO
ACTIVE_BRANCH_MOVED_BY_THIS_CHECKPOINT: NO
```

**STOP AT OWNER MERGE GATE.**
