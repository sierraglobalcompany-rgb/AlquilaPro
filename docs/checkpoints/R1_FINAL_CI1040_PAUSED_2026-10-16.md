# AlquilaPro — R1 final CI #1040 paused — checkpoint transitorio

**Fecha:** 2026-10-16  
**Tipo:** checkpoint de continuidad en rama separada; NO mueve la rama activa.  
**OWNER_REQUESTED_CHECKPOINT:** YES  
**MERGE_AUTHORIZED:** NO  
**DEPLOYMENT_AUTHORIZED:** NO  
**R2:** NOT STARTED  
**F26:** FROZEN

## Autoridad exacta al pausar

- Repo: `sierraglobalcompany-rgb/AlquilaPro`
- Base `main`: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Rama activa: `impl/r1-r3-post-uat-recovery-20261015`
- HEAD activo preservado: `2fabaf4800414fa807d6acd54ccd49f75da06919`
- PR activo: `#31` — `R1 Auth GREEN — final CI / owner merge gate`
- PR: OPEN / DRAFT / NOT MERGED
- PR mergeable observado antes de esta pausa: TRUE
- Rama activa vs main: 36 commits ahead / 0 behind
- Product GREEN: `25bf8a97002495f3288bf23de02c563d7a1e39c4`
- Product CI #1037 / run `38851445594`: PHP 8.3 / 8.4 / 8.5 SUCCESS

## CI final activa

CI #1040 / run `38852857032` sobre el HEAD exacto:

`2fabaf4800414fa807d6acd54ccd49f75da06919`

Estado exacto al crear este checkpoint:
- PHP 8.3: **SUCCESS**
- PHP 8.4: **IN_PROGRESS** — `Run tests`
- PHP 8.5: **IN_PROGRESS** — `Run tests`

Por tanto:

`R1_FINAL_CLOSURE_CERTIFIED: NO TODAVÍA`

No declarar PASS final hasta observar 8.3 / 8.4 / 8.5 SUCCESS sobre este mismo SHA.

## Estado R1 funcional

R1 funcional está GREEN certificado:
- un solo `AuthService` para email / username / documento;
- ambigüedad fail-closed;
- una sola `PasswordPolicy`, mínimo 6;
- admin/gestor: email obligatorio + username/document_number opcionales;
- opcionales vacíos -> `NULL`;
- colisiones cruzadas centralizadas en `UserService`;
- índices UNIQUE como segunda barrera;
- tenant conserva el documento exacto del candidato tras `trim`;
- contraseña inicial tenant = documento hasheado;
- `must_change_password = 0`;
- sin IdentityService/tabla/provider/middleware paralelo.

## Regresión T5 ya resuelta

CI #1035 detectó que el uppercase de documento gestionado se reutilizó por error para tenant.

Root cause:
- admin/gestor y tenant tienen contratos de normalización distintos.

Fix mínimo:
- `25bf8a97002495f3288bf23de02c563d7a1e39c4`;
- 2 líneas en `UserService::createTenantIdentity()`;
- tenant vuelve a conservar el casing exacto del candidato.

CI #1037 certificó el fix en PHP 8.3 / 8.4 / 8.5.

## Convergencia anti-ruido

HEAD activo `2fabaf48…` ya contiene:
- README actualizado a roadmap R0–R14;
- AGENTS con un solo orden de autoridad;
- un solo checkpoint canónico R1: `docs/checkpoints/R1_AUTH_GREEN_CHECKPOINT_2026-10-16.md`;
- checkpoints transitorios previos absorbidos y retirados del HEAD activo;
- cero cambios runtime en la convergencia final.

Este checkpoint vive fuera de la rama activa precisamente para no reintroducir ruido ni invalidar CI #1040.

## Deuda transitoria deliberada

`app/Auth/routes.php` conserva `identifier ?? email` únicamente como compatibilidad interna de helpers/tests legados.

- no es una segunda autoridad;
- UI pública usa `identifier`;
- gate de eliminación: **R3**.

## Bloqueo visual previo a R3

Resolver y congelar antes de implementar R3:
- spec textual: login sin X/cerrar;
- mockup mobile aprobado: muestra una X;
- copy: `Usuario` vs `Usuario, correo o cédula`.

No implementar por interpretación.

## Estado vivo adicional

PR #31 es la superficie viva de continuidad porque puede actualizarse sin mover el HEAD.

Título actual:
`R1 Auth GREEN — final CI / owner merge gate`

El body del PR contiene:
- HEAD actual;
- CI #1040;
- contratos R1;
- deuda transitoria;
- bloqueo R3;
- próxima acción exacta.

## Próxima acción exacta al reanudar

1. Consultar CI #1040 / run `38852857032` sobre `2fabaf4800414fa807d6acd54ccd49f75da06919`.
2. Exigir PHP 8.3 / 8.4 / 8.5 = SUCCESS.
3. Verificar PR #31 sigue apuntando a `2fabaf48…`, base `main@b45f625e…`, mergeable y sin drift.
4. Actualizar SOLO el body de PR #31 con el resultado final, sin crear otro commit ritual.
5. Auditar diff final bajo KISS / Cero Ruido / Anti-parche.
6. **PARAR EN OWNER MERGE GATE.**
7. No mergear sin autorización explícita del owner.
8. No abrir R2 antes de cerrar integración R1.

## Estado corto

```text
MAIN: b45f625e68622702335f5e606ae34d7410a2b7e6
ACTIVE_HEAD: 2fabaf4800414fa807d6acd54ccd49f75da06919
PR: #31 OPEN / DRAFT / NOT MERGED
R1_PRODUCT: GREEN @ 25bf8a97002495f3288bf23de02c563d7a1e39c4
R1_PRODUCT_CI: #1037 PASS 8.3/8.4/8.5
R1_FINAL_CI: #1040 — 8.3 PASS; 8.4/8.5 IN_PROGRESS
R2: NOT STARTED
PRE_F26_OWNER_UAT: REJECTED
F26: FROZEN
MERGE_AUTHORIZED: NO
DEPLOYMENT_AUTHORIZED: NO
ACTIVE_BRANCH_MOVED_BY_THIS_CHECKPOINT: NO
```

**STOP POINT PRESERVED.**
